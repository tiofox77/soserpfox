<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Support\GaleriaDeIcones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * OS MÓDULOS — o catálogo do que a plataforma sabe fazer.
 *
 * DUAS COISAS QUE O ECRÃ ANTIGO MOSTRAVA E NÃO DEIXAVA MEXER:
 *
 *  · AS DEPENDÊNCIAS. A lista contava-as («3 dependências») e o formulário não
 *    tinha campo nenhum para elas — só se punham por `tinker`. E são elas que
 *    fazem `moduleSlugsWithDependencies()` levar a tesouraria atrás da
 *    facturação: um módulo activado sem a dependência dá ecrãs que rebentam.
 *  · Um módulo não pode depender DE SI MESMO nem de um slug que não existe, e
 *    era o que uma escrita à mão permitia.
 *
 * O ÍCONE guarda-se pelado (`puzzle-piece`), como sempre esteve, porque o Blade
 * do menu monta `fas fa-{{ $icon }}`; para fora vai o código completo, que é o
 * que a galeria e os ecrãs em React falam.
 */
class ModulosApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $procura = trim((string) $request->query('procura', ''));

        $modulos = Module::withCount([
                'tenants' => fn ($q) => $q->where('tenant_module.is_active', true),
            ])
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('slug', 'like', "%{$procura}%")
                ->orWhere('description', 'like', "%{$procura}%")))
            ->orderBy('order')->get();

        // OS PLANOS DE CADA MÓDULO numa consulta só: eram uma por módulo, e é
        // esse número que diz se se pode apagar.
        $porModulo = \Illuminate\Support\Facades\DB::table('plan_module')
            ->select('module_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as quantos'))
            ->groupBy('module_id')->pluck('quantos', 'module_id');

        $nomes = Module::orderBy('order')->pluck('name', 'slug');

        return response()->json([
            'modulos' => $modulos->map(function (Module $m) use ($porModulo, $nomes) {
                $planos = (int) ($porModulo[$m->id] ?? 0);
                $empresas = (int) $m->tenants_count;

                return [
                    'id' => $m->id,
                    'nome' => $m->name,
                    'slug' => $m->slug,
                    'descricao' => $m->description,
                    'icone' => $this->comFa($m->icon),
                    'versao' => $m->version,
                    'ordem' => (int) $m->order,
                    'activo' => (bool) $m->is_active,
                    'nucleo' => (bool) $m->is_core,
                    'preco' => (float) $m->default_price,
                    'dependencias' => collect($m->dependencies ?? [])
                        ->map(fn ($slug) => ['slug' => $slug, 'nome' => $nomes[$slug] ?? $slug])
                        ->values(),
                    'empresas' => $empresas,
                    'planos' => $planos,
                    // O que impede o apagar, dito por ordem de quem manda.
                    'pode_apagar' => ! $m->is_core && $empresas === 0 && $planos === 0,
                    'porque_nao_apaga' => $this->porqueNaoApaga($m, $empresas, $planos),
                ];
            }),

            'numeros' => [
                'modulos' => Module::count(),
                'activos' => Module::where('is_active', true)->count(),
                'nucleo' => Module::where('is_core', true)->count(),
                'planos' => Plan::count(),
            ],

            // A lista vive em PHP porque há formulários em React e em Blade, e
            // duas listas em dois sítios divergem à primeira adição.
            'galeria_de_icones' => GaleriaDeIcones::grupos(),

            // Para escolher dependências de uma lista, não à mão.
            'escolhas' => Module::orderBy('order')->get(['slug', 'name'])
                ->map(fn (Module $m) => ['valor' => $m->slug, 'rotulo' => $m->name])->values(),
        ]);
    }

    public function ficha(int $id): JsonResponse
    {
        $m = Module::findOrFail($id);

        return response()->json([
            'ficha' => [
                'id' => $m->id,
                'name' => $m->name,
                'slug' => $m->slug,
                'description' => $m->description,
                'icon' => $this->comFa($m->icon),
                'version' => $m->version,
                'order' => (int) $m->order,
                'is_active' => (bool) $m->is_active,
                'is_core' => (bool) $m->is_core,
                'default_price' => (float) $m->default_price,
                'dependencies' => array_values($m->dependencies ?? []),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $modulo = $id ? Module::findOrFail($id) : null;

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/',
                'unique:modules,slug'.($modulo ? ",{$modulo->id}" : '')],
            'description' => ['required', 'string', 'max:500'],
            'icon' => ['required', 'string', 'max:60'],
            'version' => ['required', 'string', 'max:20'],
            'order' => ['required', 'integer', 'min:0'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_core' => ['boolean'],
            'dependencies' => ['array'],
            'dependencies.*' => ['string', 'exists:modules,slug'],
        ], [], [
            'name' => __('nome'),
            'slug' => __('identificador'),
            'description' => __('descrição'),
            'icon' => __('ícone'),
        ]);

        $dependencias = array_values(array_unique($dados['dependencies'] ?? []));

        // UM MÓDULO NÃO DEPENDE DE SI MESMO. `moduleSlugsWithDependencies()`
        // resolve a árvore, e um ciclo de um passo dá-lhe voltas sem fim.
        if (in_array($dados['slug'], $dependencias, true)) {
            throw ValidationException::withMessages([
                'dependencies' => __('Um módulo não pode depender de si mesmo.'),
            ]);
        }

        $campos = [
            'name' => $dados['name'],
            'slug' => $dados['slug'],
            'description' => $dados['description'],
            // Pelado na coluna, como o menu em Blade espera encontrá-lo.
            'icon' => $this->semFa($dados['icon']),
            'version' => $dados['version'],
            'order' => $dados['order'],
            'default_price' => (float) ($dados['default_price'] ?? 0),
            'is_active' => $dados['is_active'] ?? false,
            'is_core' => $dados['is_core'] ?? false,
            'dependencies' => $dependencias,
        ];

        if (! $modulo) {
            $modulo = Module::create($campos);

            return response()->json([
                'message' => __('Módulo :nome criado.', ['nome' => $modulo->name]),
                'id' => $modulo->id,
            ], 201);
        }

        $modulo->update($campos);

        return response()->json([
            'message' => __('Módulo :nome guardado.', ['nome' => $modulo->name]),
            'id' => $modulo->id,
        ]);
    }

    public function alternar(int $id): JsonResponse
    {
        $modulo = Module::findOrFail($id);
        $empresas = $modulo->tenants()->wherePivot('is_active', true)->count();
        $vaiFicarActivo = ! $modulo->is_active;

        $modulo->update(['is_active' => $vaiFicarActivo]);

        if (! $vaiFicarActivo && $empresas > 0) {
            return response()->json([
                'message' => __('Módulo :nome desligado. Atenção: :n empresa(s) estavam a usá-lo.', [
                    'nome' => $modulo->name, 'n' => $empresas,
                ]),
                'aviso' => true,
            ]);
        }

        return response()->json([
            'message' => $vaiFicarActivo
                ? __('Módulo :nome ligado.', ['nome' => $modulo->name])
                : __('Módulo :nome desligado.', ['nome' => $modulo->name]),
        ]);
    }

    public function apagar(int $id): JsonResponse
    {
        $modulo = Module::findOrFail($id);
        $empresas = $modulo->tenants()->wherePivot('is_active', true)->count();
        $planos = Plan::whereHas('modules', fn ($q) => $q->where('modules.id', $modulo->id))->count();

        $porque = $this->porqueNaoApaga($modulo, $empresas, $planos);

        if ($porque !== null) {
            throw ValidationException::withMessages(['id' => $porque]);
        }

        $nome = $modulo->name;
        $modulo->delete();

        return response()->json(['message' => __('Módulo :nome apagado.', ['nome' => $nome])]);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function porqueNaoApaga(Module $m, int $empresas, int $planos): ?string
    {
        if ($m->is_core) {
            return __('Este é um módulo de núcleo: não se apaga.');
        }

        if ($empresas > 0) {
            return __('Está a ser usado por :n empresa(s). Desligue-o nessas empresas primeiro.', ['n' => $empresas]);
        }

        if ($planos > 0) {
            return __('Está em :n plano(s). Retire-o dos planos primeiro.', ['n' => $planos]);
        }

        $dependentes = Module::where('dependencies', 'like', '%"'.$m->slug.'"%')
            ->where('id', '!=', $m->id)->pluck('name');

        // QUEM DEPENDE DELE. Apagar um módulo de que outro depende deixava a
        // dependência a apontar para um slug que já não existe.
        if ($dependentes->isNotEmpty()) {
            return __('Os módulos :nomes dependem deste.', ['nomes' => $dependentes->implode(', ')]);
        }

        return null;
    }

    private function comFa(?string $icone): string
    {
        $icone = trim((string) $icone);

        if ($icone === '') {
            return 'fa-puzzle-piece';
        }

        return str_starts_with($icone, 'fa-') ? $icone : 'fa-'.$icone;
    }

    private function semFa(string $icone): string
    {
        return preg_replace('/^fa-/', '', trim($icone)) ?: 'puzzle-piece';
    }
}
