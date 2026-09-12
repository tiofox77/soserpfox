<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * OS PLANOS DA PLATAFORMA — o que se vende, por quanto, e com o que dentro.
 *
 * QUATRO COLUNAS QUE O ECRÃ NUNCA DEIXOU TOCAR, e que existem na tabela desde
 * o princípio. Não é cosmética: sem elas o ecrã criava planos quebrados.
 *
 *  · `price_quarterly` e `price_semiannual`. A página «A minha conta» oferece
 *    ao cliente subscrição TRIMESTRAL e SEMESTRAL, e vai buscar o preço a
 *    estas duas colunas. Um plano criado por este ecrã nascia com ambas a
 *    zero, e o modelo, ao ler zero, cobra 3× e 6× o mensal — ou seja, o
 *    trimestre e o semestre saíam SEM DESCONTO NENHUM, e não havia onde o
 *    dar. Só um comando de consola (`PrecoDoPlano`) os sabia pôr. Agora estão
 *    no formulário, com a sugestão de 5% e 10% que o comando usava.
 *  · `max_documents`. O tecto de documentos fiscais do plano, que viaja para a
 *    subscrição e trava a numeração. Só existia no comando que criou o FOX
 *    Friendly (500). Vazio = sem tecto, e é o que se diz no formulário.
 *  · `is_public`. Um plano pode estar ACTIVO e fora da montra — é assim que
 *    nascem os planos à medida (`PlanoAMedida`) e os do agente. O ecrã não
 *    mostrava nem deixava mudar, pelo que um plano feito à medida não havia
 *    maneira de o publicar, e na lista era indistinguível dos outros.
 *  · `is_promotional`. A marca do FOX Friendly: activável UMA vez por empresa.
 *
 * E O `included_modules`, o JSON antigo que duplica a tabela de ligação: ficava
 * a apodrecer a cada gravação por aqui, enquanto três comandos o lêem. Gravar
 * os módulos passa a escrever os dois, com os mesmos slugs.
 */
class PlanosApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $procura = trim((string) $request->query('procura', ''));

        $planos = Plan::with('modules:id,name,slug,icon')
            ->withCount([
                'subscriptions as subscricoes_activas' => fn ($q) => $q->where('status', 'active'),
                'subscriptions as subscricoes_presas' => fn ($q) => $q->whereIn('status', ['active', 'trial', 'pending']),
            ])
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('slug', 'like', "%{$procura}%")
                ->orWhere('description', 'like', "%{$procura}%")))
            ->orderBy('order')->get()
            ->map(fn (Plan $p) => $this->linha($p));

        return response()->json([
            'planos' => $planos,
            'modulos' => Module::active()->orderBy('order')
                ->get(['id', 'name', 'slug', 'icon', 'is_core', 'default_price'])
                ->map(fn (Module $m) => [
                    'id' => $m->id,
                    'nome' => $m->name,
                    'slug' => $m->slug,
                    'icone' => $this->comFa($m->icon),
                    'nucleo' => (bool) $m->is_core,
                    'preco' => (float) $m->default_price,
                ]),
            'numeros' => [
                'planos' => Plan::count(),
                'activos' => Plan::where('is_active', true)->count(),
                'na_montra' => Plan::where('is_active', true)->where('is_public', true)->count(),
                'modulos' => Module::where('is_active', true)->count(),
            ],
        ]);
    }

    public function ficha(int $id): JsonResponse
    {
        $p = Plan::with('modules:id')->findOrFail($id);

        return response()->json([
            'ficha' => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'price_monthly' => (float) $p->price_monthly,
                'price_quarterly' => (float) $p->price_quarterly,
                'price_semiannual' => (float) $p->price_semiannual,
                'price_yearly' => (float) $p->price_yearly,
                'max_users' => (int) $p->max_users,
                'max_companies' => (int) $p->max_companies,
                'max_storage_mb' => (int) $p->max_storage_mb,
                // Nulo é «sem tecto», e é diferente de zero.
                'max_documents' => $p->max_documents === null ? null : (int) $p->max_documents,
                'trial_days' => (int) $p->trial_days,
                'order' => (int) $p->order,
                'is_active' => (bool) $p->is_active,
                'is_public' => (bool) $p->is_public,
                'is_featured' => (bool) $p->is_featured,
                'is_promotional' => (bool) $p->is_promotional,
                'auto_activate' => (bool) $p->auto_activate,
                'features' => array_values($p->features ?? []),
                'modulos' => $p->modules->pluck('id')->all(),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $plano = $id ? Plan::findOrFail($id) : null;

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/',
                'unique:plans,slug'.($plano ? ",{$plano->id}" : '')],
            'description' => ['required', 'string', 'max:500'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_quarterly' => ['required', 'numeric', 'min:0'],
            'price_semiannual' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'max_companies' => ['required', 'integer', 'min:1'],
            'max_storage_mb' => ['required', 'integer', 'min:100'],
            'max_documents' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_promotional' => ['boolean'],
            'auto_activate' => ['boolean'],
            'features' => ['array'],
            'features.*' => ['string', 'max:180'],
            'modulos' => ['array'],
            'modulos.*' => ['integer', 'exists:modules,id'],
        ], [], [
            'name' => __('nome'),
            'slug' => __('identificador'),
            'description' => __('descrição'),
        ]);

        // UM PLANO NA MONTRA TEM DE ESTAR ACTIVO. Publicar um plano desactivado
        // punha-o na página de preços a levar quem clicasse a um beco.
        if (($dados['is_public'] ?? false) && ! ($dados['is_active'] ?? false)) {
            throw ValidationException::withMessages([
                'is_public' => __('Um plano fora de serviço não pode estar na montra.'),
            ]);
        }

        $modulos = array_values(array_unique($dados['modulos'] ?? []));
        $slugs = Module::whereIn('id', $modulos)->orderBy('order')->pluck('slug')->all();

        $campos = [
            'name' => $dados['name'],
            'slug' => $dados['slug'],
            'description' => $dados['description'],
            'price_monthly' => $dados['price_monthly'],
            'price_quarterly' => $dados['price_quarterly'],
            'price_semiannual' => $dados['price_semiannual'],
            'price_yearly' => $dados['price_yearly'],
            'max_users' => $dados['max_users'],
            'max_companies' => $dados['max_companies'],
            'max_storage_mb' => $dados['max_storage_mb'],
            'max_documents' => $dados['max_documents'] ?? null,
            'trial_days' => $dados['trial_days'],
            'order' => $dados['order'],
            'is_active' => $dados['is_active'] ?? false,
            'is_public' => $dados['is_public'] ?? false,
            'is_featured' => $dados['is_featured'] ?? false,
            'is_promotional' => $dados['is_promotional'] ?? false,
            'auto_activate' => $dados['auto_activate'] ?? false,
            'features' => array_values(array_filter(array_map('trim', $dados['features'] ?? []))),
            // O JSON antigo em passo com a tabela de ligação, e não a arrastar
            // o que lá estava de uma gravação anterior.
            'included_modules' => $slugs,
        ];

        if (! $plano) {
            $plano = Plan::create($campos);
            $plano->modules()->sync($modulos);

            $quantos = $plano->syncModulesToTenants();

            if ($quantos > 0) {
                Log::info("Módulos do novo plano '{$plano->name}' sincronizados com {$quantos} empresa(s).");
            }

            return response()->json([
                'message' => __('Plano :nome criado.', ['nome' => $plano->name]),
                'id' => $plano->id,
            ], 201);
        }

        // OS MÓDULOS DE ANTES, para saber o que entrou e o que saiu: é isso que
        // manda nas empresas que já têm este plano.
        $antes = $plano->modules->pluck('id')->all();

        $plano->update($campos);
        $plano->modules()->sync($modulos);

        $entraram = array_diff($modulos, $antes);
        $sairam = array_diff($antes, $modulos);
        $recados = [];

        if ($entraram) {
            $quantas = 0;

            foreach ($entraram as $moduloId) {
                $quantas += $plano->addModuleToTenants($moduloId);
            }

            if ($quantas > 0) {
                $recados[] = __(':empresas empresa(s) receberam :modulos módulo(s).', [
                    'empresas' => $quantas, 'modulos' => count($entraram),
                ]);
            }
        }

        if ($sairam) {
            $quantas = 0;

            foreach ($sairam as $moduloId) {
                $quantas += $plano->removeModuleFromTenants($moduloId);
            }

            if ($quantas > 0) {
                $recados[] = __(':empresas empresa(s) perderam :modulos módulo(s).', [
                    'empresas' => $quantas, 'modulos' => count($sairam),
                ]);
            }
        }

        if ($recados) {
            Log::info("Sincronização automática do plano '{$plano->name}'", $recados);
        }

        return response()->json([
            'message' => trim(__('Plano :nome guardado.', ['nome' => $plano->name]).' '.implode(' ', $recados)),
            'id' => $plano->id,
        ]);
    }

    /** Ligar e desligar um plano — com o aviso de quem fica pelo caminho. */
    public function alternar(int $id): JsonResponse
    {
        $plano = Plan::findOrFail($id);
        $activas = $plano->subscriptions()->where('status', 'active')->count();
        $vaiFicarActivo = ! $plano->is_active;

        $plano->update([
            'is_active' => $vaiFicarActivo,
            // DESLIGAR TIRA DA MONTRA. Um plano fora de serviço que continuasse
            // na página de preços era um convite para um beco sem saída.
            'is_public' => $vaiFicarActivo ? $plano->is_public : false,
        ]);

        if (! $vaiFicarActivo && $activas > 0) {
            return response()->json([
                'message' => __('Plano :nome desligado. Atenção: :n subscrição(ões) activa(s) continuam a funcionar, mas não haverá novas adesões.', [
                    'nome' => $plano->name, 'n' => $activas,
                ]),
                'aviso' => true,
            ]);
        }

        return response()->json([
            'message' => $vaiFicarActivo
                ? __('Plano :nome ligado.', ['nome' => $plano->name])
                : __('Plano :nome desligado.', ['nome' => $plano->name]),
        ]);
    }

    public function apagar(int $id): JsonResponse
    {
        $plano = Plan::findOrFail($id);

        // QUEM ESTÁ A PAGAR MANDA. Apagar um plano com subscrições presas
        // deixava empresas com um plano que já não existe.
        $presas = $plano->subscriptions()->whereIn('status', ['active', 'trial', 'pending'])->count();

        if ($presas > 0) {
            throw ValidationException::withMessages([
                'id' => __('O plano :nome tem :n subscrição(ões) activa(s) ou em ensaio. Cancele-as primeiro.', [
                    'nome' => $plano->name, 'n' => $presas,
                ]),
            ]);
        }

        $nome = $plano->name;
        $plano->modules()->detach();
        $plano->delete();

        return response()->json(['message' => __('Plano :nome apagado.', ['nome' => $nome])]);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function linha(Plan $p): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->name,
            'slug' => $p->slug,
            'descricao' => $p->description,
            'preco_mensal' => (float) $p->price_monthly,
            'preco_trimestral' => (float) $p->price_quarterly,
            'preco_semestral' => (float) $p->price_semiannual,
            'preco_anual' => (float) $p->price_yearly,
            'desconto_anual' => (int) $p->getYearlySavingsPercentage(),
            'poupanca_anual' => (float) $p->getYearlySavings(),
            'max_utilizadores' => (int) $p->max_users,
            'max_empresas' => (int) $p->max_companies,
            'max_espaco_mb' => (int) $p->max_storage_mb,
            'max_documentos' => $p->max_documents === null ? null : (int) $p->max_documents,
            'dias_de_ensaio' => (int) $p->trial_days,
            'ordem' => (int) $p->order,
            'activo' => (bool) $p->is_active,
            'na_montra' => (bool) $p->is_public,
            'destacado' => (bool) $p->is_featured,
            'promocional' => (bool) $p->is_promotional,
            'activa_sozinho' => (bool) $p->auto_activate,
            'funcionalidades' => array_values($p->features ?? []),
            'modulos' => $p->modules->map(fn (Module $m) => [
                'id' => $m->id,
                'nome' => $m->name,
                'slug' => $m->slug,
                'icone' => $this->comFa($m->icon),
            ])->values(),
            'subscricoes_activas' => (int) $p->subscricoes_activas,
            // Com subscrições presas não há apagar — e o ecrã diz porquê.
            'pode_apagar' => (int) $p->subscricoes_presas === 0,
            'subscricoes_presas' => (int) $p->subscricoes_presas,
        ];
    }

    /**
     * A coluna `icon` dos módulos guarda o nome pelado (`puzzle-piece`) e o
     * Blade montava `fas fa-{{ $icon }}`. A galeria e os ecrãs em React falam
     * códigos completos: a conversão faz-se aqui, numa fronteira só.
     */
    private function comFa(?string $icone): string
    {
        $icone = trim((string) $icone);

        if ($icone === '') {
            return 'fa-puzzle-piece';
        }

        return str_starts_with($icone, 'fa-') ? $icone : 'fa-'.$icone;
    }
}
