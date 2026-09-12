<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;
use App\Support\GaleriaDeIcones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS SERVIÇOS DO SALÃO — e as categorias, no mesmo ecrã.
 *
 * ESTAVAM EM DOIS ECRÃS e são a mesma decisão: a ordem das categorias é a ordem
 * por que os serviços aparecem no balcão e na página de marcação, e isso
 * decide-se a olhar para os serviços.
 *
 * O SERVIÇO É UM ARTIGO DA FACTURAÇÃO (`invoicing_products`, `type='servico'`,
 * `module='salon'`) e não uma tabela à parte: é assim que ele entra numa
 * factura com imposto e vai ao SAFT. A duração, a comissão e a categoria são do
 * salão e vivem no JSON do `description` — por isso `where('category_id')` NÃO
 * encontra nada, e a contagem por categoria tem de vir do acessor.
 */
class ServicosApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.services.view');

        return response()->json([
            'categorias' => $this->categorias(),
            'galeria_de_icones' => GaleriaDeIcones::grupos(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('salon.services.create'),
                'pode_editar' => (bool) $request->user()?->can('salon.services.edit'),
                'pode_apagar' => (bool) $request->user()?->can('salon.services.delete'),
                'pode_gerir_categorias' => (bool) $request->user()?->can('salon.categories.edit'),
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function categorias(): array
    {
        $lista = ServiceCategory::forTenant()->orderBy('order')->orderBy('name')->get();

        // A CONTAGEM VERDADEIRA. O `withCount` conta pela coluna `category_id`,
        // e a categoria de um serviço do salão vive no JSON — dizia sempre zero,
        // e o travão que impede apagar uma categoria cheia nunca disparava.
        $contagens = ServiceCategory::contagens();

        return $lista->map(fn (ServiceCategory $c) => [
            'id' => $c->id,
            'nome' => $c->name,
            'descricao' => $c->description,
            'icone' => $c->icon ?: 'fa-spa',
            'cor' => $c->color ?: '#6366f1',
            'ordem' => (int) $c->order,
            'activa' => (bool) $c->is_active,
            'servicos' => (int) ($contagens[$c->id] ?? 0),
        ])->values()->all();
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.services.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Service::forTenant()
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->forCategory($c))
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 15);

        $categorias = collect($this->categorias())->keyBy('id');

        return response()->json([
            'data' => collect($lista->items())->map(fn (Service $s) => [
                'id' => $s->id,
                'nome' => $s->name,
                'codigo' => $s->code,
                'descricao' => $s->text_description,
                'category_id' => $s->category_id,
                'categoria' => $categorias[$s->category_id]['nome'] ?? null,
                'duracao' => (int) $s->duration,
                'duracao_rotulo' => $s->duration_formatted,
                'preco' => (float) $s->price,
                'custo' => (float) $s->cost,
                'comissao' => (float) $s->commission_percent,
                'activo' => (bool) $s->is_active,
                'marcacao_online' => (bool) $s->online_booking,
                // A margem só quer dizer alguma coisa quando há preço e custo.
                'margem' => (float) $s->price > 0 && (float) $s->cost > 0
                    ? round(((((float) $s->price) - ((float) $s->cost)) / ((float) $s->price)) * 100, 1)
                    : null,
            ])->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'categorias' => $this->categorias(),
            'resumo' => [
                'total' => Service::forTenant()->count(),
                'activos' => Service::forTenant()->active()->count(),
                'categorias' => ServiceCategory::forTenant()->count(),
            ],
        ]);
    }

    /* ─── Os serviços ──────────────────────────────────────────────────── */

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'salon.services.edit' : 'salon.services.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'category_id' => ['required', Rule::exists('salon_service_categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'text_description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'online_booking' => ['boolean'],
        ], [], [
            'category_id' => __('categoria'), 'name' => __('nome'),
            'duration' => __('duração'), 'price' => __('preço'),
        ]);

        if ($id) {
            $servico = Service::forTenant()->findOrFail($id);

            $servico->update([
                'name' => $dados['name'],
                'price' => $dados['price'],
                'cost' => $dados['cost'] ?? 0,
                'is_active' => (bool) ($dados['is_active'] ?? true),
            ]);

            /*
             * O JSON ESCREVE-SE INTEIRO, e não pelo `updateSalonData`.
             *
             * Esse guarda `['salon' => …, 'text' => $this->text_description]`
             * — e o `text_description` é um ACESSOR que lê a descrição ANTIGA.
             * A descrição escrita no formulário nunca chegava à base: mudava-se
             * o texto de um serviço, gravava-se, e ele voltava ao anterior.
             */
            $servico->description = json_encode([
                'salon' => array_merge($servico->salon_data, [
                    'category_id' => (int) $dados['category_id'],
                    'duration' => (int) $dados['duration'],
                    'commission_percent' => (float) ($dados['commission_percent'] ?? 0),
                    'online_booking' => (bool) ($dados['online_booking'] ?? true),
                ]),
                'text' => $dados['text_description'] ?? '',
            ]);

            $servico->save();
        } else {
            Service::createService([
                'name' => $dados['name'],
                'price' => $dados['price'],
                'cost' => $dados['cost'] ?? 0,
                'is_active' => (bool) ($dados['is_active'] ?? true),
                'category_id' => $dados['category_id'],
                'duration' => $dados['duration'],
                'commission_percent' => $dados['commission_percent'] ?? 0,
                'online_booking' => (bool) ($dados['online_booking'] ?? true),
                'text_description' => $dados['text_description'] ?? '',
            ]);
        }

        return response()->json(['message' => __('Serviço guardado.')], $id ? 200 : 201);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.services.edit');

        $servico = Service::forTenant()->findOrFail($id);
        $servico->update(['is_active' => ! $servico->is_active]);

        return response()->json([
            'message' => $servico->is_active ? __('Serviço activo.') : __('Serviço desligado.'),
            'activo' => (bool) $servico->is_active,
        ]);
    }

    /**
     * ESCONDER, E NÃO APAGAR, quando o serviço já foi vendido.
     *
     * Um serviço já facturado está em documentos fiscais; apagá-lo deixava-os a
     * apontar para o vazio. O ecrã em Livewire apagava sem perguntar nada.
     */
    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.services.delete');

        $servico = Service::forTenant()->findOrFail($id);

        $jaMarcado = \App\Models\Salon\AppointmentService::where('service_id', $servico->id)->exists();

        if ($jaMarcado) {
            $this->recusa(__('Este serviço já esteve em marcações e não se apaga — desligue-o.'));
        }

        $servico->delete();

        return response()->json(['message' => __('Serviço eliminado.')]);
    }

    /* ─── As categorias ────────────────────────────────────────────────── */

    public function guardarCategoria(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'salon.categories.edit' : 'salon.categories.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100',
                Rule::unique('salon_service_categories', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))
                    ->ignore($id)],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => __('nome')]);

        $valores = [
            'name' => $dados['name'],
            'icon' => ($dados['icon'] ?? '') ?: 'fa-spa',
            'color' => ($dados['color'] ?? '') ?: '#6366F1',
            'description' => $dados['description'] ?? null,
            'order' => (int) ($dados['order'] ?? 0),
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        if ($id) {
            ServiceCategory::forTenant()->findOrFail($id)->update($valores);
        } else {
            ServiceCategory::create($valores + ['tenant_id' => $tenantId]);
        }

        return response()->json([
            'message' => __('Categoria guardada.'),
            'categorias' => $this->categorias(),
        ], $id ? 200 : 201);
    }

    public function apagarCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.categories.edit');

        $categoria = ServiceCategory::forTenant()->findOrFail($id);

        // O travão lia um `withCount` que era SEMPRE zero: apagava-se uma
        // categoria cheia de serviços e ficavam todos órfãos.
        if ((ServiceCategory::contagens()[$categoria->id] ?? 0) > 0) {
            $this->recusa(__('Esta categoria tem serviços. Mova-os antes de a eliminar.'));
        }

        $categoria->delete();

        return response()->json(['message' => __('Categoria eliminada.'), 'categorias' => $this->categorias()]);
    }

    /** A ordem das categorias É a ordem em que os serviços aparecem. */
    public function moverCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.categories.edit');

        $dados = $request->validate(['direccao' => ['required', Rule::in(['cima', 'baixo'])]]);

        $categoria = ServiceCategory::forTenant()->findOrFail($id);

        $vizinha = ServiceCategory::forTenant()
            ->when($dados['direccao'] === 'cima',
                fn ($q) => $q->where('order', '<', $categoria->order)->orderByDesc('order'),
                fn ($q) => $q->where('order', '>', $categoria->order)->orderBy('order'))
            ->first();

        if ($vizinha) {
            // Troca simples: robusta mesmo com ordens repetidas.
            [$a, $b] = [(int) $categoria->order, (int) $vizinha->order];

            if ($a === $b) {
                $b = $dados['direccao'] === 'cima' ? $a - 1 : $a + 1;
            }

            $categoria->update(['order' => $b]);
            $vizinha->update(['order' => $a]);
        }

        return response()->json(['categorias' => $this->categorias()]);
    }
}
