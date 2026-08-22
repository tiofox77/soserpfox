<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\EliminarEmpresa;
use App\Support\NifAngolano;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CRUD completo da plataforma, para o agente.
 *
 * A API do agente sabia ler quase tudo e escrever quase nada: aprovar um
 * pedido, mandar um seguimento, suspender uma empresa. Tudo o resto — criar,
 * corrigir, apagar — obrigava a abrir o painel à mão, o que faz do agente um
 * observador com opinião em vez de alguém que resolve.
 *
 * QUEM DECIDE
 * -----------
 * Quem gere a plataforma. Não é este código que julga se uma empresa deve ser
 * suspensa por ter o NIF mal preenchido — isso é uma decisão de negócio e o
 * agente foi mandado tomá-la. O que este código garante é outra coisa:
 *
 *   · nada acontece por engano — cada escrita exige `Idempotency-Key`, e as
 *     que doem exigem motivo escrito;
 *   · nada acontece sem rasto — quem, quando, o quê e porquê, em
 *     `agent_requests` e no log;
 *   · o que é reversível diz que é, e o que não é avisa antes.
 *
 * A diferença entre proteger a decisão e proteger do acidente. Só a segunda é
 * trabalho do código.
 */
class GestaoController extends Controller
{
    private function agente()
    {
        return request()->attributes->get('agente');
    }

    // ══════════════════════════════════════════════════════════════
    //  Empresas
    // ══════════════════════════════════════════════════════════════

    /**
     * Criar uma empresa.
     *
     * Sem plano e sem utilizadores: nasce vazia e inactiva. Dar-lhe plano é
     * uma decisão de receita e tem caminho próprio (o painel, ou um pedido
     * aprovado); criar utilizadores mexe em credenciais, que esta API nunca
     * toca.
     */
    public function criarEmpresa(Request $request)
    {
        $dados = $request->validate([
            'nome'   => 'required|string|min:3|max:255',
            'nif'    => 'nullable|string|max:20',
            'email'  => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:30',
            'motivo' => 'required|string|min:8|max:500',
        ]);

        $empresa = Tenant::create([
            'name'      => $dados['nome'],
            'slug'      => $this->slugUnico($dados['nome']),
            'nif'       => $dados['nif'] ?? null,
            'email'     => $dados['email'] ?? null,
            'phone'     => $dados['telefone'] ?? null,
            // Nasce INACTIVA de propósito: uma empresa criada por um agente
            // não deve poder ser usada antes de alguém a olhar.
            'is_active' => false,
        ]);

        $this->registar('empresa criada', $empresa, $dados['motivo'], [
            'nome' => $empresa->name,
        ]);

        return response()->json([
            'empresa' => $this->empresaResumida($empresa),
            'nota'    => 'Nasce INACTIVA e sem plano. Activar e atribuir plano são decisões '
                . 'com caminho próprio — ver POST /tenants/{id}/estado.',
        ], 201);
    }

    /**
     * Corrigir os dados de uma empresa.
     *
     * É o caso que motivou isto: um NIF de pessoa singular num campo de
     * empresa corrige-se — não se suspende o cliente por causa dele.
     */
    public function actualizarEmpresa(Request $request, Tenant $tenant)
    {
        $dados = $request->validate([
            'nome'     => 'sometimes|string|min:3|max:255',
            'nif'      => 'sometimes|nullable|string|max:20',
            'email'    => 'sometimes|nullable|email|max:255',
            'telefone' => 'sometimes|nullable|string|max:30',
            'estado'   => 'sometimes|in:ativa,ativo,suspensa,suspenso,reactivar,reativar',
            'motivo'   => 'sometimes|nullable|string|min:8|max:500',
        ]);

        if (array_key_exists('estado', $dados) && empty($dados['motivo'])) {
            return response()->json(['erro' => 'O motivo é obrigatório para mudar o estado da empresa.'], 422);
        }

        $mapa = ['nome' => 'name', 'nif' => 'nif', 'email' => 'email', 'telefone' => 'phone'];
        $antes = [];
        $mudou = [];

        foreach ($mapa as $entrada => $coluna) {
            if (!array_key_exists($entrada, $dados)) {
                continue;
            }

            if ((string) $tenant->{$coluna} === (string) $dados[$entrada]) {
                continue;
            }

            $antes[$coluna] = $tenant->{$coluna};
            $tenant->{$coluna} = $dados[$entrada];
            $mudou[] = $coluna;
        }

        if (array_key_exists('estado', $dados)) {
            $activo = in_array($dados['estado'], ['ativa', 'ativo', 'reactivar', 'reativar'], true);
            if ((bool) $tenant->is_active !== $activo) {
                $antes['is_active'] = (bool) $tenant->is_active;
                $tenant->is_active = $activo;
                $mudou[] = 'is_active';
            }
        }

        if (empty($mudou)) {
            return response()->json([
                'empresa' => $this->empresaResumida($tenant),
                'mudou'   => [],
                'nota'    => 'Nada a alterar: os valores enviados são os que já lá estavam.',
            ]);
        }

        $tenant->save();

        $this->registar('empresa actualizada', $tenant, $dados['motivo'] ?? 'actualização solicitada pela API v2', [
            'campos' => $mudou,
            'antes'  => $antes,
        ]);

        return response()->json([
            'empresa' => $this->empresaResumida($tenant),
            'mudou'   => $mudou,
            'antes'   => $antes,
        ]);
    }

    /**
     * Ver o que se perde ao apagar — SEM apagar.
     *
     * Existe para que a decisão possa ser tomada com a lista à frente. Apagar
     * uma empresa é a única coisa nesta API que não tem volta.
     */
    public function previsaoDeEliminacao(Tenant $tenant, EliminarEmpresa $servico)
    {
        $guarda = $tenant->canBeDeleted();

        return response()->json([
            'empresa'      => $this->empresaResumida($tenant),
            'pode_apagar'  => (bool) $guarda['can_delete'],
            'impedimento'  => $guarda['reason'],
            'actividade'   => $guarda['encontrado'] ?? [],
            'comunicou_agt' => $servico->comunicouAAgt($tenant),
            'o_que_se_perde' => $servico->oQueSePerde($tenant),
            'aviso' => 'Isto é irreversível. Uma empresa COM actividade não se apaga — '
                . 'suspende-se (POST /tenants/{id}/estado).',
        ]);
    }

    /**
     * Apagar uma empresa.
     *
     * Escopo próprio (`tenants:delete`), confirmação explícita e motivo. A
     * guarda do modelo continua a mandar: uma empresa com facturas, recibos ou
     * qualquer documento fiscal NÃO se apaga, por mais confirmações que
     * venham no pedido — isso não é uma opinião do agente, é a lei.
     */
    public function apagarEmpresa(Request $request, Tenant $tenant, EliminarEmpresa $servico)
    {
        $dados = $request->validate([
            // Não basta um booleano: tem de escrever o nome da empresa. É a
            // diferença entre confirmar e carregar por reflexo.
            'confirmo_o_nome' => 'required|string',
            'motivo'          => 'required|string|min:8|max:500',
        ]);

        if (trim($dados['confirmo_o_nome']) !== trim((string) $tenant->name)) {
            return response()->json([
                'erro' => 'O nome de confirmação não bate certo com o da empresa.',
                'esperado' => $tenant->name,
            ], 422);
        }

        $guarda = $tenant->canBeDeleted();

        if (!$guarda['can_delete']) {
            return response()->json([
                'erro'        => 'Esta empresa tem actividade e não pode ser apagada.',
                'impedimento' => $guarda['reason'],
                'actividade'  => $guarda['encontrado'] ?? [],
                'alternativa' => 'Suspender: POST /tenants/' . $tenant->id . '/estado',
            ], 409);
        }

        $nome = $tenant->name;
        $id = $tenant->id;
        $perdido = $servico->oQueSePerde($tenant);

        $this->registar('empresa APAGADA', $tenant, $dados['motivo'], [
            'nome'    => $nome,
            'perdido' => $perdido,
        ], 'error');

        try {
            $servico->eliminar($tenant);
        } catch (\DomainException $e) {
            // A guarda da AGT: uma empresa que já comunicou documentos ao
            // fisco não se apaga, e isso não é negociável por confirmação
            // nenhuma. Sem isto rebentava num 500 e o agente não percebia
            // que a recusa tinha razão de ser.
            return response()->json([
                'erro'        => $e->getMessage(),
                'alternativa' => 'Suspender: POST /tenants/' . $id . '/estado',
            ], 409);
        }

        return response()->json([
            'apagada' => ['id' => $id, 'nome' => $nome],
            'perdido' => $perdido,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Planos
    // ══════════════════════════════════════════════════════════════

    public function planos(Request $request)
    {
        $planos = Plan::with('modules:id,slug,name')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn ($p) => $this->planoResumido($p));

        return response()->json(['planos' => $planos]);
    }

    public function plano(Plan $plan)
    {
        return response()->json(['plano' => $this->planoResumido($plan->load('modules:id,slug,name'))]);
    }

    /**
     * Criar um plano.
     *
     * Nasce FORA DA MONTRA (`is_public = false`). Um plano criado por um
     * agente não deve aparecer na página de preços antes de alguém o ver — e
     * pô-lo público é uma linha no painel.
     */
    public function criarPlano(Request $request)
    {
        $dados = $request->validate($this->regrasDoPlano());

        // ARMADILHA: um plano com mensalidade 0 é tratado em todo o sistema
        // como "o plano gratuito" e queima a cortesia única do cliente.
        if ((float) $dados['preco_mensal'] <= 0) {
            return response()->json([
                'erro' => 'A mensalidade tem de ser maior que zero. Um plano a zero é tratado '
                    . 'como o plano gratuito e gasta a cortesia única do cliente. Para oferecer, '
                    . 'ponha um valor simbólico e faça o desconto na cobrança.',
            ], 422);
        }

        $plano = Plan::create([
            'name'           => $dados['nome'],
            'slug'           => $this->slugUnicoDePlano($dados['nome']),
            'description'    => $dados['descricao'] ?? null,
            'price_monthly'  => round((float) $dados['preco_mensal'], 2),
            'price_yearly'   => round((float) ($dados['preco_anual'] ?? $dados['preco_mensal'] * 12), 2),
            'trial_days'     => (int) ($dados['dias_de_teste'] ?? 0),
            'max_users'      => (int) ($dados['max_utilizadores'] ?? 5),
            'max_companies'  => (int) ($dados['max_empresas'] ?? 1),
            'max_storage_mb' => (int) ($dados['max_armazenamento_mb'] ?? 2000),
            'is_active'      => true,
            'is_public'      => false,
            'auto_activate'  => false,
            'order'          => 99,
        ]);

        $this->sincronizarModulos($plano, $dados['modulos'] ?? null);

        $this->registar('plano criado', null, $dados['motivo'], [
            'plano' => $plano->slug,
        ]);

        return response()->json([
            'plano' => $this->planoResumido($plano->load('modules:id,slug,name')),
            'nota'  => 'Nasce FORA DA MONTRA (is_public = false). Não aparece na página de '
                . 'preços nem no registo até alguém o publicar.',
        ], 201);
    }

    public function actualizarPlano(Request $request, Plan $plan)
    {
        $dados = $request->validate($this->regrasDoPlano(obrigatorios: false));

        if (array_key_exists('preco_mensal', $dados) && (float) $dados['preco_mensal'] <= 0) {
            return response()->json([
                'erro' => 'A mensalidade tem de ser maior que zero — ver POST /plans.',
            ], 422);
        }

        $mapa = [
            'nome' => 'name', 'descricao' => 'description',
            'preco_mensal' => 'price_monthly', 'preco_anual' => 'price_yearly',
            'dias_de_teste' => 'trial_days', 'max_utilizadores' => 'max_users',
            'max_empresas' => 'max_companies', 'max_armazenamento_mb' => 'max_storage_mb',
        ];

        $antes = [];

        foreach ($mapa as $entrada => $coluna) {
            if (array_key_exists($entrada, $dados) && (string) $plan->{$coluna} !== (string) $dados[$entrada]) {
                $antes[$coluna] = $plan->{$coluna};
                $plan->{$coluna} = $dados[$entrada];
            }
        }

        $plan->save();

        if (array_key_exists('modulos', $dados)) {
            $antes['modulos'] = $plan->modules()->pluck('slug')->all();
            $this->sincronizarModulos($plan, $dados['modulos']);
        }

        $this->registar('plano actualizado', null, $dados['motivo'], [
            'plano' => $plan->slug,
            'antes' => $antes,
        ]);

        return response()->json([
            'plano' => $this->planoResumido($plan->fresh()->load('modules:id,slug,name')),
            'antes' => $antes,
            'nota'  => 'Mudar os módulos de um plano NÃO tira acesso a quem já o tem: o portão '
                . 'é o pivô tenant_module, não o plano.',
        ]);
    }

    /**
     * Desactivar um plano.
     *
     * Não se apaga: há subscrições a apontar-lhe, e apagá-lo deixaria clientes
     * com uma subscrição órfã. Desactivar tira-o da montra e impede novas
     * adesões, sem mexer em quem já lá está.
     */
    public function desactivarPlano(Request $request, Plan $plan)
    {
        $dados = $request->validate([
            'motivo' => 'required|string|min:8|max:500',
            'activo' => 'sometimes|boolean',
        ]);

        $activo = (bool) ($dados['activo'] ?? false);
        $emUso = $plan->subscriptions()->whereIn('status', ['active', 'trial'])->count();

        $plan->forceFill(['is_active' => $activo, 'is_public' => $activo ? $plan->is_public : false])->save();

        $this->registar($activo ? 'plano reactivado' : 'plano desactivado', null, $dados['motivo'], [
            'plano' => $plan->slug,
            'subscricoes_vivas' => $emUso,
        ]);

        return response()->json([
            'plano' => $this->planoResumido($plan->fresh()->load('modules:id,slug,name')),
            'subscricoes_vivas' => $emUso,
            'nota' => $emUso > 0
                ? "Há {$emUso} subscrição(ões) viva(s) neste plano. Continuam a funcionar — "
                    . 'desactivar só impede novas adesões.'
                : 'Sem subscrições vivas.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Auxiliares
    // ══════════════════════════════════════════════════════════════

    private function regrasDoPlano(bool $obrigatorios = true): array
    {
        $req = $obrigatorios ? 'required' : 'sometimes';

        return [
            'nome'                 => "{$req}|string|min:3|max:255",
            'preco_mensal'         => "{$req}|numeric|min:0",
            'descricao'            => 'sometimes|nullable|string|max:1000',
            'preco_anual'          => 'sometimes|numeric|min:0',
            'dias_de_teste'        => 'sometimes|integer|min:0|max:365',
            'max_utilizadores'     => 'sometimes|integer|min:1',
            'max_empresas'         => 'sometimes|integer|min:1',
            'max_armazenamento_mb' => 'sometimes|integer|min:100',
            'modulos'              => 'sometimes|array',
            'modulos.*'            => ['string', Rule::exists('modules', 'slug')],
            'motivo'               => 'required|string|min:8|max:500',
        ];
    }

    private function sincronizarModulos(Plan $plano, ?array $slugs): void
    {
        if ($slugs === null) {
            return;
        }

        $plano->modules()->sync(Module::whereIn('slug', $slugs)->pluck('id')->all());
    }

    private function empresaResumida(Tenant $t): array
    {
        $nif = NifAngolano::classificar($t->nif);

        return [
            'id'       => $t->id,
            'nome'     => $t->name,
            'slug'     => $t->slug,
            'nif'      => $nif['nif'],
            'nif_estado' => $nif['estado'],
            'nif_motivo' => $nif['motivo'],
            'email'    => $t->email,
            'telefone' => $t->phone,
            'activa'   => (bool) $t->is_active,
            'criada_em' => optional($t->created_at)->toIso8601String(),
        ];
    }

    private function planoResumido(Plan $p): array
    {
        return [
            'id'            => $p->id,
            'slug'          => $p->slug,
            'nome'          => $p->name,
            'descricao'     => $p->description,
            'preco_mensal'  => (float) $p->price_monthly,
            'preco_anual'   => (float) $p->price_yearly,
            'dias_de_teste' => (int) $p->trial_days,
            'max_utilizadores' => (int) $p->max_users,
            'max_empresas'  => (int) $p->max_companies,
            'activo'        => (bool) $p->is_active,
            'na_montra'     => (bool) $p->is_public,
            'modulos'       => $p->relationLoaded('modules')
                ? $p->modules->pluck('slug')->all()
                : $p->modules()->pluck('slug')->all(),
        ];
    }

    private function slugUnico(string $nome): string
    {
        $base = Str::limit(Str::slug($nome), 50, '');
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    private function slugUnicoDePlano(string $nome): string
    {
        $base = Str::limit(Str::slug($nome), 50, '');
        $slug = $base;
        $i = 1;

        while (Plan::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    /**
     * O rasto.
     *
     * Cada pedido já fica em `agent_requests` pelo middleware; isto acrescenta
     * o QUE mudou e PORQUÊ, que é o que falta quando alguém pergunta, três
     * semanas depois, porque é que aquela empresa está suspensa.
     */
    private function registar(string $accao, ?Tenant $empresa, string $motivo, array $extra = [], string $nivel = 'warning'): void
    {
        Log::{$nivel}("Agente: {$accao}", array_merge([
            'agente'    => $this->agente()?->nome(),
            'tenant_id' => $empresa?->id,
            'motivo'    => $motivo,
        ], $extra));
    }
}
