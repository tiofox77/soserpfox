<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\PlanoAMedida;
use App\Services\Plataforma\TrocarDePlano;
use App\Services\Tenant\TenantModuleSyncService;
use App\Support\AcordoDeSubscricao;
use App\Support\CicloDeFacturacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * O PLANO DE UMA EMPRESA — o acordo que tem, o que mudaria, e a mudança.
 *
 * O RESUMO É CALCULADO PELA MESMA REGRA QUE GRAVA (`AcordoDeSubscricao`). O
 * ecrã em Livewire já o fazia assim; em React isso significa um pedido de
 * resumo a cada mudança do formulário, e não uma cópia da conta em JavaScript
 * — duas contas em duas línguas divergem à primeira regra nova, e o ecrã
 * prometia uma coisa e gravava outra.
 */
class EmpresaPlanoApiController extends Controller
{
    public function index(int $empresa): JsonResponse
    {
        $t = Tenant::with('activeSubscription.plan')->findOrFail($empresa);
        $s = $t->activeSubscription;

        return response()->json([
            'empresa' => ['id' => $t->id, 'nome' => $t->name],
            'actual' => $s ? [
                'plano_id' => $s->plan_id,
                'plano' => $s->plan?->name,
                'ciclo' => CicloDeFacturacao::normalizar($s->billing_cycle),
                'ciclo_nome' => CicloDeFacturacao::nome($s->billing_cycle),
                'valor' => (float) $s->amount,
                'termina_em' => $s->current_period_end?->format('d/m/Y'),
                'faltam' => $s->current_period_end ? max(0, (int) now()->diffInDays($s->current_period_end, false)) : null,
                'dias_personalizados' => $s->dias_personalizados ? (int) $s->dias_personalizados : null,
                'com_oferta' => (bool) ($s->com_oferta ?? true),
                'preco_por_utilizador' => $s->preco_por_utilizador !== null ? (float) $s->preco_por_utilizador : null,
                'utilizadores_cobrados' => $s->utilizadores_cobrados ? (int) $s->utilizadores_cobrados : null,
                'max_documentos' => $s->max_documentos !== null ? (int) $s->max_documentos : null,
                'max_utilizadores' => (int) ($s->plan?->max_users ?? 0),
                'max_espaco_mb' => (int) ($s->plan?->max_storage_mb ?? 0),
            ] : null,
            'documentos' => [
                'emitidos' => $t->documentosEmitidos(),
                'tecto' => $t->limiteDeDocumentos(),
            ],
            'planos' => Plan::where('is_active', true)->orderBy('order')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'descricao' => $p->description,
                'destacado' => (bool) $p->is_featured,
                'na_montra' => (bool) $p->is_public,
                'max_utilizadores' => (int) $p->max_users,
                'max_espaco_mb' => (int) $p->max_storage_mb,
                'preco_mensal' => (float) $p->price_monthly,
                'preco_anual' => (float) $p->price_yearly,
            ])->values(),
        ]);
    }

    /** O que a escolha dá — pela regra que vai gravar. */
    public function resumo(Request $request, int $empresa): JsonResponse
    {
        Tenant::findOrFail($empresa);
        [$plano, $ciclo, $opcoes] = $this->escolha($request);

        try {
            $acordo = AcordoDeSubscricao::calcular($plano, $ciclo, $opcoes);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['dias' => $e->getMessage()]);
        }

        return response()->json([
            'fim' => $acordo['fim']->format('d/m/Y'),
            'dias' => $acordo['dias'],
            'valor' => (float) $acordo['valor'],
            'base' => $acordo['base'],
            'oferta_aplicavel' => (bool) $acordo['oferta_aplicavel'],
            'com_oferta' => (bool) $acordo['com_oferta'],
            'max_documentos' => $acordo['max_documentos'],
            'ciclo_nome' => $acordo['dias_personalizados']
                ? __(':n dias', ['n' => $acordo['dias_personalizados']])
                : CicloDeFacturacao::nome($ciclo),
        ]);
    }

    public function guardar(Request $request, int $empresa): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);
        [$plano, $ciclo, $opcoes] = $this->escolha($request);

        DB::transaction(function () use ($t, $plano, $ciclo, $opcoes) {
            // A TROCA VIVE NO `TrocarDePlano`: o antigo é cancelado e nasce um
            // novo com dias novos. Reaproveitar a subscrição e só trocar o
            // plano deixava lá o fim do teste e as datas do plano anterior.
            app(TrocarDePlano::class)->aplicar($t, $plano, $ciclo, $opcoes);

            $this->sincronizarModulos($t, $plano);
        });

        return response()->json([
            'message' => __('Plano de :empresa passou a :plano. Limites e módulos sincronizados.', [
                'empresa' => $t->name, 'plano' => $plano->name,
            ]),
        ]);
    }

    /* ─── O plano à medida ────────────────────────────────────────────── */

    /**
     * O que abrir o «à medida» precisa: o que a empresa já tem, e o preço de
     * cada módulo — o combinado no pivô primeiro, o de catálogo depois.
     */
    public function medida(int $empresa): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);

        // O PREÇO DE CADA MÓDULO NUMA CONSULTA: era um `modules()->first()` por
        // módulo, dentro do ciclo.
        $doPivo = $t->modules()->get(['modules.id', 'modules.slug'])
            ->mapWithKeys(fn ($m) => [$m->slug => ['preco' => $m->pivot->price ?? null, 'activo' => (bool) $m->pivot->is_active]]);

        $modulos = Module::where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'empresa' => ['id' => $t->id, 'nome' => $t->name],
            'sugestao' => [
                'nome' => __('Plano :empresa', ['empresa' => $t->name]),
                'utilizadores' => max(5, (int) $t->max_users),
                'empresas' => 1,
                'armazenamento' => max(2000, (int) $t->max_storage_mb),
                'ciclo' => 'monthly',
            ],
            'modulos' => $modulos->map(fn (Module $m) => [
                'slug' => $m->slug,
                'nome' => $m->name,
                'icone' => str_starts_with((string) $m->icon, 'fa-') ? $m->icon : 'fa-'.($m->icon ?: 'puzzle-piece'),
                'preco' => (float) ($doPivo[$m->slug]['preco'] ?? $m->default_price ?? 0),
                // O que a empresa JÁ TEM activo: vê-se o que se acrescenta ou
                // tira, em vez de montar às cegas.
                'ja_tem' => (bool) ($doPivo[$m->slug]['activo'] ?? false),
                'dependencias' => array_values($m->dependencies ?? []),
            ])->values(),
        ]);
    }

    public function guardarMedida(Request $request, int $empresa, PlanoAMedida $servico): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:3', 'max:120'],
            'modulos' => ['required', 'array', 'min:1'],
            'modulos.*' => ['string', 'exists:modules,slug'],
            'precos' => ['array'],
            'precos.*' => ['nullable', 'numeric', 'min:0'],
            'testes' => ['array'],
            'testes.*' => ['nullable', 'integer', 'min:0', 'max:365'],
            'preco_anual' => ['nullable', 'numeric', 'min:0'],
            'utilizadores' => ['required', 'integer', 'min:1'],
            'empresas' => ['required', 'integer', 'min:1'],
            'armazenamento' => ['required', 'integer', 'min:100'],
            'ciclo' => ['required', 'in:monthly,quarterly,semiannual,yearly'],
            'ja_pago' => ['boolean'],
        ], [
            'modulos.required' => __('Escolha pelo menos um módulo.'),
        ]);

        $mensal = PlanoAMedida::somar($dados['modulos'], $dados['precos'] ?? []);

        // A SOMA TEM DE DAR MAIS DO QUE ZERO. Um plano a zero é tratado em todo
        // o sistema como o plano gratuito e gasta a cortesia única do cliente.
        if ($mensal <= 0) {
            throw ValidationException::withMessages([
                'precos' => __('A soma dos módulos tem de ser maior que zero — um plano a zero gasta a cortesia única do cliente.'),
            ]);
        }

        try {
            $plano = $servico->criarEAtribuir($t, [
                'nome' => $dados['nome'],
                'modulos' => $dados['modulos'],
                'precos' => $dados['precos'] ?? [],
                'testes' => $dados['testes'] ?? [],
                'preco_mensal' => $mensal,
                'preco_anual' => $dados['preco_anual'] ?? null,
                'max_users' => $dados['utilizadores'],
                'max_companies' => $dados['empresas'],
                'max_storage_mb' => $dados['armazenamento'],
                'trial_days' => 0, // o teste aqui é POR MÓDULO
                'ciclo' => $dados['ciclo'],
                'ja_pago' => $dados['ja_pago'] ?? false,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['nome' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Plano à medida falhou', ['tenant_id' => $t->id, 'erro' => $e->getMessage()]);

            throw ValidationException::withMessages(['nome' => __('O plano não foi criado: :erro', ['erro' => $e->getMessage()])]);
        }

        return response()->json([
            'message' => __('Plano «:plano» criado e atribuído a :empresa.', ['plano' => $plano->name, 'empresa' => $t->name]),
        ], 201);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    /** @return array{0: Plan, 1: string, 2: array} */
    private function escolha(Request $request): array
    {
        $dados = $request->validate([
            'plano' => ['required', 'integer', 'exists:plans,id'],
            'ciclo' => ['required', 'in:monthly,quarterly,semiannual,yearly'],
            'com_oferta' => ['boolean'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'preco_por_utilizador' => ['nullable', 'numeric', 'min:0'],
            'utilizadores' => ['nullable', 'integer', 'min:1'],
            'max_documentos' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ], [], [
            'plano' => __('plano'),
            'dias' => __('dias'),
        ]);

        $opcoes = [
            'com_oferta' => $dados['com_oferta'] ?? true,
            'dias' => $dados['dias'] ?? null,
            'preco_por_utilizador' => $dados['preco_por_utilizador'] ?? null,
            'utilizadores' => $dados['utilizadores'] ?? null,
        ];

        // Vazio é «o que o plano der» — só se passa quando foi escrito, porque
        // o acordo distingue a chave ausente de a chave a nulo.
        if (isset($dados['max_documentos'])) {
            $opcoes['max_documentos'] = (int) $dados['max_documentos'];
        }

        return [Plan::findOrFail($dados['plano']), $dados['ciclo'], $opcoes];
    }

    /**
     * Os módulos do plano JÁ COM as dependências (Facturação ⇒ Tesouraria).
     *
     * O `detach()`+`attach()` antigo destruía o pivô inteiro e, nos planos que
     * não listam a Tesouraria, deixava o cliente sem métodos de pagamento.
     */
    private function sincronizarModulos(Tenant $t, Plan $plano): void
    {
        $doPlano = $plano->moduleSlugsWithDependencies();
        $sync = new TenantModuleSyncService();

        $activos = $t->modules()->wherePivot('is_active', true)->pluck('modules.slug')->all();

        foreach (array_diff($activos, $doPlano) as $slug) {
            $sync->deactivateModule($t, $slug);
        }

        foreach ($doPlano as $slug) {
            $sync->activateModule($t, $slug);
        }
    }
}
