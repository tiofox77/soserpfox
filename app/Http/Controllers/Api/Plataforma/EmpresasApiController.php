<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\AvisosDeConta;
use App\Services\Plataforma\EliminarEmpresa;
use App\Services\Tenants\SinaisDeVida;
use App\Support\EstadoDaSubscricao;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * AS EMPRESAS DA PLATAFORMA — quem são, se estão vivas, e o que se lhes faz.
 *
 * É a área que mais dano pode fazer ao sistema inteiro: cria, desliga, suspende
 * e apaga empresas. As regras vêm todas do componente em Livewire, onde foram
 * sendo aprendidas uma a uma, e os comentários que explicam PORQUÊ vêm com elas.
 *
 * O QUE MUDOU DE SUBSTÂNCIA:
 *
 *  · O APAGAR DEFINITIVO passou a ter a mesma guarda em TODOS os passos. O
 *    componente verificava `is_super_admin` ao abrir e ao confirmar, mas o
 *    grupo de rotas usa `isPlatformSuperAdmin()` — duas regras para a mesma
 *    porta. Aqui a porta é o middleware do grupo, e só essa.
 *  · O PAÍS entra no formulário. A coluna existia, o componente tinha o valor
 *    por omissão, e não havia campo: uma empresa criada por aqui ficava com o
 *    país que o código decidisse, e o SAF-T leva o país.
 *  · OS AVISOS DE SUSPENSÃO saem por um serviço (`AvisosDeConta`) em vez de três
 *    cópias quase iguais de duzentas linhas.
 */
class EmpresasApiController extends Controller
{
    public const ORDENACOES = ['recentes', 'antigas', 'nome', 'entrada', 'facturas', 'artigos'];

    public const ESTADOS = ['activa', 'a_usar', 'a_montar', 'adormecida', 'vazia'];

    /**
     * A lista, em três tempos:
     *
     *   1. os ids que passam nos filtros de base (pesquisa, plano, activo) —
     *      uma consulta leve, só id/nome/data;
     *   2. os sinais de vida de TODOS esses ids — seis consultas fixas, seja
     *      qual for o número de empresas, e é isso que torna barato filtrar e
     *      ordenar por vitalidade, que a base de dados não sabe calcular;
     *   3. os modelos completos só da página que se vai mostrar.
     */
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
            'plano' => ['nullable', 'integer'],
            'activa' => ['nullable', Rule::in(['0', '1'])],
            'ordenar' => ['nullable', Rule::in(self::ORDENACOES)],
            'por_pagina' => ['nullable', Rule::in(['10', '25', '50'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $procura = trim((string) ($f['procura'] ?? ''));
        $porPagina = (int) ($f['por_pagina'] ?? 10);

        $base = Tenant::query()
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('email', 'like', "%{$procura}%")
                ->orWhere('company_name', 'like', "%{$procura}%")
                ->orWhere('nif', 'like', "%{$procura}%")))
            ->when(isset($f['activa']), fn ($q) => $q->where('is_active', $f['activa'] === '1'))
            ->when(! empty($f['plano']), fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s
                ->where('plan_id', (int) $f['plano'])
                ->whereIn('status', ['active', 'trial'])))
            ->get(['id', 'name', 'created_at']);

        $sinais = SinaisDeVida::para($base->pluck('id'));

        // As contagens por estado contam-se ANTES do filtro de estado, senão
        // clicar num cartão zerava todos os outros.
        $contagens = collect(self::ESTADOS)->mapWithKeys(fn ($e) => [$e => 0])->all();

        foreach ($sinais as $s) {
            $contagens[$s->estado['chave']] = ($contagens[$s->estado['chave']] ?? 0) + 1;
        }

        $filtrados = empty($f['estado'])
            ? $base
            : $base->filter(fn ($t) => ($sinais[$t->id]->estado['chave'] ?? '') === $f['estado']);

        $ordenados = match ($f['ordenar'] ?? 'recentes') {
            'antigas' => $filtrados->sortBy('created_at'),
            'nome' => $filtrados->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            // Datas nulas para o fim: quem nunca entrou não pode aparecer à
            // frente de quem entrou ontem.
            'entrada' => $filtrados->sortByDesc(fn ($t) => $sinais[$t->id]->ultima_entrada?->timestamp ?? -1),
            'facturas' => $filtrados->sortByDesc(fn ($t) => $sinais[$t->id]->facturas_30d ?? 0),
            'artigos' => $filtrados->sortByDesc(fn ($t) => $sinais[$t->id]->artigos ?? 0),
            default => $filtrados->sortByDesc('created_at'),
        };

        $total = $ordenados->count();
        $ultima = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, (int) ($f['pagina'] ?? 1)), $ultima);
        $daPagina = $ordenados->slice(($pagina - 1) * $porPagina, $porPagina)->pluck('id')->values();

        $modelos = Tenant::with(['activeSubscription.plan', 'modules'])
            ->withCount('users')
            ->whereIn('id', $daPagina)->get()
            ->sortBy(fn ($t) => $daPagina->search($t->id))->values();

        return response()->json([
            'empresas' => $modelos->map(fn (Tenant $t) => $this->linha($t, $sinais[$t->id] ?? null))->values(),
            'paginacao' => [
                'pagina' => $pagina,
                'ultima' => $ultima,
                'total' => $total,
                'por_pagina' => $porPagina,
            ],
            'contagens' => $contagens,
            'opcoes' => [
                'planos' => Plan::where('is_active', true)->orderBy('order')->get(['id', 'name'])
                    ->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => $p->name])->values(),
                'ordenacoes' => [
                    ['valor' => 'recentes', 'rotulo' => __('Mais recentes')],
                    ['valor' => 'antigas', 'rotulo' => __('Mais antigas')],
                    ['valor' => 'nome', 'rotulo' => __('Nome (A–Z)')],
                    ['valor' => 'entrada', 'rotulo' => __('Última entrada')],
                    ['valor' => 'facturas', 'rotulo' => __('Mais facturas (30d)')],
                    ['valor' => 'artigos', 'rotulo' => __('Maior catálogo')],
                ],
                'paises' => collect(Geografia::paises())
                    ->map(fn ($nome, $codigo) => ['valor' => $codigo, 'rotulo' => $nome])->values(),
            ],
        ]);
    }

    /** A ficha para ver — o que o modal de detalhes mostrava, e mais. */
    public function ver(int $id): JsonResponse
    {
        $t = Tenant::with(['modules', 'users', 'activeSubscription.plan'])->findOrFail($id);
        $sinais = SinaisDeVida::para([$t->id])[$t->id] ?? null;

        return response()->json([
            'empresa' => $this->linha($t, $sinais) + [
                'morada' => $t->address,
                'cidade' => $t->city,
                'codigo_postal' => $t->postal_code,
                'pais' => Geografia::nomeDoPais($t->country) ?? $t->country,
                'actualizada_em' => $t->updated_at?->format('d/m/Y H:i'),
                'limite_de_utilizadores' => $t->limiteDeUtilizadores(),
                // A ficha a prometer menos do que o plano dá: confunde quem lê.
                'ficha_abaixo_do_plano' => $t->fichaAbaixoDoPlano(),
                'motivo_da_desactivacao' => $t->deactivation_reason,
                'desactivada_em' => $t->deactivated_at?->format('d/m/Y H:i'),
                'documentos_emitidos' => $t->documentosEmitidos(),
                'limite_de_documentos' => $t->limiteDeDocumentos(),
                'pessoas' => $t->users->map(fn ($u) => [
                    'id' => $u->id, 'nome' => $u->name, 'email' => $u->email,
                ])->values(),
                'modulos_lista' => $t->modules->map(fn ($m) => [
                    'id' => $m->id,
                    'nome' => $m->name,
                    'icone' => str_starts_with((string) $m->icon, 'fa-') ? $m->icon : 'fa-'.($m->icon ?: 'puzzle-piece'),
                    'activo' => (bool) $m->pivot->is_active,
                ])->values(),
            ],
        ]);
    }

    /** A ficha para editar, com os nomes das colunas. */
    public function ficha(int $id): JsonResponse
    {
        $t = Tenant::with('activeSubscription.plan')->findOrFail($id);

        return response()->json([
            'ficha' => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'email' => $t->email,
                'phone' => $t->phone,
                'company_name' => $t->company_name,
                'nif' => $t->nif,
                'address' => $t->address,
                'city' => $t->city,
                'postal_code' => $t->postal_code,
                'country' => Geografia::normalizarPais($t->country) ?? Geografia::PAIS_PADRAO,
                'max_users' => (int) $t->max_users,
                'max_storage_mb' => (int) $t->max_storage_mb,
                'is_active' => (bool) $t->is_active,
            ],
            // O que o plano dá, para o formulário dizer que a ficha não desce
            // abaixo disso.
            'do_plano' => [
                'nome' => $t->activeSubscription?->plan?->name,
                'max_users' => (int) ($t->activeSubscription?->plan?->max_users ?? 0),
                'max_storage_mb' => (int) ($t->activeSubscription?->plan?->max_storage_mb ?? 0),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $empresa = $id ? Tenant::with('activeSubscription.plan')->findOrFail($id) : null;

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('tenants', 'slug')->ignore($empresa?->id)],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'nif' => ['nullable', 'nif_empresa'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2', function ($atributo, $valor, $falhar) {
                if (! Geografia::ehPaisValido($valor)) {
                    $falhar(__('Escolha um país da lista.'));
                }
            }],
            'max_users' => ['required', 'integer', 'min:1'],
            'max_storage_mb' => ['required', 'integer', 'min:100'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => __('nome'),
            'slug' => __('identificador'),
            'nif' => __('NIF'),
        ]);

        $dados['country'] = strtoupper($dados['country']);
        $dados['is_active'] = $dados['is_active'] ?? true;

        if ($empresa) {
            // A FICHA NÃO PODE PROMETER MENOS DO QUE O PLANO DÁ. Como o limite
            // é imposto, um número abaixo do plano só confundia: mostrava 10 a
            // quem paga por 50. Ao gravar sobe para o do plano — nunca desce,
            // porque a ficha serve para conceder MAIS.
            $dados['max_users'] = max((int) $dados['max_users'], (int) ($empresa->activeSubscription?->plan?->max_users ?? 0));
            $dados['max_storage_mb'] = max((int) $dados['max_storage_mb'], (int) ($empresa->activeSubscription?->plan?->max_storage_mb ?? 0));

            $empresa->update($dados);

            return response()->json([
                'message' => __('Empresa :nome guardada.', ['nome' => $empresa->name]),
                'id' => $empresa->id,
            ]);
        }

        // TUDO OU NADA. Sem transacção, uma falha a criar os papéis ou o plano
        // de contas deixava a empresa GRAVADA e meio montada: aparecia na
        // lista, e quem lá entrasse não tinha permissões nem contabilidade.
        $empresa = DB::transaction(function () use ($dados) {
            $empresa = Tenant::create($dados);

            createDefaultRolesForTenant($empresa->id);
            initializeAccountingDataForTenant($empresa->id);

            $this->provisionar($empresa);

            return $empresa;
        });

        Log::info('Empresa criada pelo dono da plataforma', ['tenant_id' => $empresa->id]);

        return response()->json([
            'message' => __('Empresa :nome criada e configurada.', ['nome' => $empresa->name]),
            'id' => $empresa->id,
        ], 201);
    }

    /**
     * DESLIGAR PEDE MOTIVO; LIGAR NÃO.
     *
     * O motivo vai no email a quem trabalha na empresa. Sem ele, trinta pessoas
     * recebiam «a sua conta foi suspensa» sem saber porquê.
     */
    public function desactivar(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'motivo.required' => __('Diga o motivo da desactivação.'),
            'motivo.min' => __('O motivo deve ter pelo menos 10 caracteres.'),
        ]);

        $empresa = Tenant::findOrFail($id);
        $pessoas = $empresa->users()->count();

        $empresa->update([
            'is_active' => false,
            'deactivation_reason' => $dados['motivo'],
            'deactivated_at' => now(),
            'deactivated_by' => auth()->id(),
        ]);

        // OS EMAILS SAEM DEPOIS DA RESPOSTA. Era um ciclo síncrono com uma
        // ligação SMTP por pessoa dentro do pedido: com trinta pessoas o pedido
        // excedia o tempo e o ecrã mostrava erro apesar de gravado. `defer`
        // corre no mesmo processo depois da resposta — sem fila, que este
        // alojamento não tem.
        defer(fn () => app(AvisosDeConta::class)->paraAEmpresa($empresa->fresh(), AvisosDeConta::SUSPENSA));

        return response()->json([
            'message' => __('Empresa :nome desactivada. A avisar :n pessoa(s) por email.', [
                'nome' => $empresa->name, 'n' => $pessoas,
            ]),
            'aviso' => true,
        ]);
    }

    public function activar(int $id): JsonResponse
    {
        $empresa = Tenant::findOrFail($id);
        $pessoas = $empresa->users()->count();

        $empresa->update([
            'is_active' => true,
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);

        defer(fn () => app(AvisosDeConta::class)->paraAEmpresa($empresa->fresh(), AvisosDeConta::REACTIVADA));

        return response()->json([
            'message' => __('Empresa :nome reactivada. A avisar :n pessoa(s) por email.', [
                'nome' => $empresa->name, 'n' => $pessoas,
            ]),
        ]);
    }

    /**
     * SUSPENDER — o `delete()` do modelo, que com SoftDeletes tira a empresa da
     * lista e a guarda na base. O botão antigo dizia «Excluir» e ninguém sabia
     * que era recuperável.
     */
    public function suspender(int $id): JsonResponse
    {
        $empresa = Tenant::findOrFail($id);
        $pode = $empresa->canBeDeleted();

        if (! $pode['can_delete']) {
            throw ValidationException::withMessages(['id' => $pode['reason']]);
        }

        $nome = $empresa->name;
        $empresa->delete();

        return response()->json(['message' => __('Empresa :nome suspensa: saiu da lista e os dados ficam guardados.', ['nome' => $nome])]);
    }

    /** O que se perde com o apagar definitivo — para decidir antes de decidir. */
    public function oQueSePerde(int $id, EliminarEmpresa $servico): JsonResponse
    {
        $empresa = Tenant::withTrashed()->findOrFail($id);

        return response()->json([
            'nome' => $empresa->name,
            'perdas' => collect($servico->oQueSePerde($empresa))
                ->map(fn ($n, $rotulo) => ['rotulo' => __($rotulo), 'quantos' => (int) $n])->values(),
            // A AGT não esquece: uma empresa que comunicou documentos não pode
            // desaparecer da base.
            'impedido' => $servico->comunicouAAgt($empresa)
                ? __('Esta empresa já comunicou documentos à AGT. Não pode ser apagada — suspenda-a.')
                : null,
        ]);
    }

    /**
     * APAGAR MESMO — e só com o nome escrito à mão. Escrever o nome não é
     * burocracia: é a diferença entre carregar num botão por engano e decidir.
     */
    public function apagarDefinitivo(Request $request, int $id, EliminarEmpresa $servico): JsonResponse
    {
        $empresa = Tenant::withTrashed()->findOrFail($id);

        $confirmacao = trim((string) $request->input('confirmacao', ''));

        if ($confirmacao !== trim((string) $empresa->name)) {
            throw ValidationException::withMessages([
                'confirmacao' => __('Escreva o nome da empresa exactamente como está.'),
            ]);
        }

        try {
            $linhas = $servico->eliminar($empresa);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['confirmacao' => $e->getMessage()]);
        }

        return response()->json([
            'message' => __('Empresa apagada em definitivo (:n registos).', ['n' => $linhas]),
        ]);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function linha(Tenant $t, ?object $sinais): array
    {
        $sub = $t->activeSubscription;
        $estado = EstadoDaSubscricao::para($t);
        $nif = preg_replace('/\D/', '', (string) $t->nif);

        return [
            'id' => $t->id,
            'nome' => $t->name,
            'slug' => $t->slug,
            'email' => $t->email,
            'telefone' => $t->phone,
            'razao_social' => $t->company_name,
            'nif' => $t->nif,
            // UM NIF QUE NÃO COMEÇA POR 5 NÃO É DE EMPRESA — e é ele que vai
            // nos documentos comunicados à AGT.
            'nif_de_empresa' => $nif === '' ? null : (bool) preg_match('/^5\d{8,9}$/', $nif),
            // Sem o anfitrião: o APP_URL à frente fazia o browser recusar a imagem
            // quando o sistema se abre por outro nome.
            'logo' => $t->logo ? (string) parse_url(\Illuminate\Support\Facades\Storage::url($t->logo), PHP_URL_PATH) : null,
            'activa' => (bool) $t->is_active,
            'criada_em' => $t->created_at?->format('d/m/Y'),
            'plano' => $sub?->plan?->name,
            'ciclo' => $sub?->billing_cycle ? \App\Support\CicloDeFacturacao::nome($sub->billing_cycle) : null,
            'max_utilizadores' => (int) $t->max_users,
            'max_espaco_mb' => (int) $t->max_storage_mb,
            'modulos' => $t->relationLoaded('modules') ? $t->modules->where('pivot.is_active', true)->count() : 0,
            'utilizadores' => (int) ($t->users_count ?? ($t->relationLoaded('users') ? $t->users->count() : 0)),
            'subscricao' => [
                'rotulo' => $estado['rotulo'],
                'cor' => $estado['cor'],
                'icone' => $estado['icone'],
                'detalhe' => $estado['detalhe'],
                'nota' => $estado['nota'] ?? null,
                'falta' => $estado['dias'] !== null ? EstadoDaSubscricao::quantoFalta($estado['dias']) : null,
                'ate' => $estado['ate'],
            ],
            'vida' => $sinais ? [
                'chave' => $sinais->estado['chave'],
                'texto' => __($sinais->estado['texto']),
                'facturas_30d' => (int) $sinais->facturas_30d,
                'artigos' => (int) $sinais->artigos,
                'movimentos_30d' => (int) $sinais->movimentos_30d,
                'entraram_30d' => (int) $sinais->entraram_30d,
                'utilizadores' => (int) $sinais->utilizadores,
                'ultima_entrada' => $sinais->ultima_entrada?->diffForHumans(short: true),
                'entrou_ha_pouco' => (bool) ($sinais->ultima_entrada && $sinais->ultima_entrada->gt(now()->subDays(30))),
            ] : null,
        ];
    }

    /**
     * O que uma empresa nova precisa para funcionar.
     *
     * As definições de RH e os modelos de notificação existiam agarrados à
     * empresa 1 e nenhuma empresa nova os recebia — quem abrisse esses ecrãs via
     * uma página em branco. Cada passo falha em silêncio: uma empresa não pode
     * deixar de ser criada porque um catálogo acessório teve um problema.
     */
    private function provisionar(Tenant $empresa): void
    {
        $passos = [
            'definições de RH' => fn () => \App\Services\HR\DefinicoesRH::garantirPara($empresa->id),
            'modelos de notificação' => fn () => \App\Services\Notifications\ModelosPadrao::garantirPara($empresa->id),
        ];

        foreach ($passos as $nome => $passo) {
            try {
                $passo();
            } catch (\Throwable $e) {
                Log::warning("Provisionamento parcial da empresa: {$nome}", [
                    'tenant_id' => $empresa->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
    }
}
