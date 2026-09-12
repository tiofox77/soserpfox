<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\RestaurantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A CARTA — os pratos e as categorias, no mesmo ecrã.
 *
 * PORQUE É UM SÓ. Montar o menu obrigava a saltar entre dois ecrãs: os
 * produtos da Facturação (cheios de campos que um prato não usa) e as
 * categorias (noutro sítio). Criar dez pratos eram dez idas ao formulário
 * grande, e quem só queria mudar um preço tinha de o encontrar lá no meio.
 *
 * OS MESMOS DADOS DE SEMPRE: `Category` e `Product`. O que se monta aqui
 * aparece no balcão, no PWA e na carta online porque É o mesmo registo —
 * este ecrã não inventa tabela nenhuma.
 *
 * NUNCA SE APAGA UM PRATO daqui: um prato já vendido está em facturas, e
 * apagá-lo deixava documentos fiscais a apontar para o vazio. Esconder faz o
 * mesmo serviço sem partir nada.
 */
class CartaApiController extends Controller
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
        $this->exigir($request, 'restaurant.menu.view');

        $tenantId = activeTenantId();

        // A carta de um restaurante novo começa com as categorias da casa —
        // é o que evita a primeira visita a um ecrã completamente vazio.
        Category::seedRestaurantDefaults($tenantId);

        $definicoes = RestaurantSettings::forTenant($tenantId);

        return response()->json([
            'categorias' => $this->categorias($tenantId),
            'impostos' => Tax::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('rate')
                ->get(['id', 'name', 'rate'])
                ->map(fn (Tax $i) => ['valor' => (string) $i->id, 'rotulo' => $i->name])->values(),
            'definicoes' => [
                'exige_ficha' => (bool) $definicoes->require_recipe_for_products,
                'carta_publica' => (bool) $definicoes->online_menu_enabled,
            ],
            // A MESMA lista de ícones do resto da casa. Escrever `fa-…` à mão
            // era o que o ecrã em Blade pedia, e um erro de letra dava uma
            // categoria com um quadrado vazio ao lado do nome.
            'galeria_de_icones' => \App\Support\GaleriaDeIcones::grupos(),
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('restaurant.menu.manage'),
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function categorias(int $tenantId): array
    {
        return Category::where('tenant_id', $tenantId)
            ->withCount('products')
            ->orderBy('order')->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'descricao' => $c->description,
                'icone' => $c->icon ?: 'fa-utensils',
                'cor' => $c->color ?: '#EA580C',
                'activa' => (bool) $c->is_active,
                'ordem' => (int) $c->order,
                'pratos' => (int) $c->products_count,
                // A categoria «Geral» é a rede de segurança do catálogo: não
                // se apaga nem se desliga, senão os artigos ficam sem casa.
                'da_casa' => $c->slug === 'geral',
            ])->values()->all();
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.view');

        $tenantId = activeTenantId();
        $definicoes = RestaurantSettings::forTenant($tenantId);

        $filtros = $request->validate([
            'categoria' => ['nullable', 'integer'],
            'procura' => ['nullable', 'string', 'max:100'],
            'so_fora' => ['nullable', 'boolean'],
        ]);

        $pratos = Product::where('tenant_id', $tenantId)
            ->with('category:id,name')
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when($filtros['so_fora'] ?? false, fn ($q) => $q->where('is_active', false))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('code', 'like', $t));
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'code', 'price', 'unit', 'category_id', 'is_active', 'featured_image']);

        /*
         * Os pratos SEM FICHA TÉCNICA, quando a casa a exige: estão na carta
         * mas NÃO aparecem no balcão. Sem esta marca, «criei o prato e ele
         * não aparece» era um mistério sem pista nenhuma.
         */
        $comFicha = $definicoes->require_recipe_for_products
            ? Recipe::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('is_active', true)->pluck('product_id')->flip()
            : collect();

        return response()->json([
            'categorias' => $this->categorias($tenantId),
            'data' => $pratos->map(fn (Product $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'codigo' => $p->code,
                'preco' => (float) $p->price,
                'unidade' => $p->unit,
                'categoria' => $p->category?->name,
                'category_id' => $p->category_id,
                'disponivel' => (bool) $p->is_active,
                'imagem' => $p->featured_image,
                'falta_ficha' => $definicoes->require_recipe_for_products && ! $comFicha->has($p->id),
            ])->values(),
            'exige_ficha' => (bool) $definicoes->require_recipe_for_products,
        ]);
    }

    /* ─── Os pratos ────────────────────────────────────────────────────── */

    public function criarPrato(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', Rule::exists('invoicing_categories', 'id')->where('tenant_id', $tenantId)],
        ], [], ['name' => __('nome do prato'), 'price' => __('preço')]);

        $definicoes = RestaurantSettings::forTenant($tenantId);
        $imposto = Tax::where('tenant_id', $tenantId)->where('is_active', true)->orderByDesc('rate')->first();

        $prato = Product::create([
            'tenant_id' => $tenantId,
            'category_id' => $dados['category_id'] ?? null,
            'type' => 'produto',
            'name' => trim($dados['name']),
            'price' => (float) $dados['price'],
            'cost' => 0,
            'unit' => 'UN',
            'tax_type' => 'iva',
            'tax_rate_id' => $imposto?->id,
            /*
             * Um prato consome INGREDIENTES (pela ficha técnica), não a si
             * próprio. Stock do prato em si quase nunca é o que se quer — e
             * quando for, liga-se no ecrã de Produtos.
             */
            'manage_stock' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => $definicoes->require_recipe_for_products
                // A regra da casa manda: com fichas obrigatórias, um prato sem
                // ficha não vende. Cria-se na mesma, mas AVISA-SE já.
                ? __('Criado — mas esta casa exige ficha técnica: complete-a para o prato aparecer no balcão.')
                : __('No menu. Escreva o próximo.'),
            'aviso' => (bool) $definicoes->require_recipe_for_products,
            'data' => ['id' => $prato->id, 'nome' => $prato->name],
        ], 201);
    }

    /**
     * O preço muda-se a tocar-lhe — sem formulário.
     *
     * O `parse` devolve 0,0 para lixo, e um dedo em falso não pode pôr um
     * prato a custar zero. Zero só quando foi mesmo escrito zero.
     */
    public function preco(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $cru = trim((string) $request->input('price', ''));
        $preco = MoneyHelper::parse($cru);
        $escreveuZero = (bool) preg_match('/^0+([.,]0+)?$/', $cru);

        if ($cru === '' || $preco < 0 || ($preco === 0.0 && ! $escreveuZero)) {
            $this->recusa(__('Preço inválido — o de antes fica.'));
        }

        Product::where('tenant_id', activeTenantId())->findOrFail($id)->update(['price' => $preco]);

        return response()->json(['message' => __('Preço actualizado.'), 'preco' => $preco]);
    }

    public function nome(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $nome = trim((string) $request->input('name', ''));

        if (mb_strlen($nome) < 2) {
            $this->recusa(__('O nome não pode ficar vazio — o de antes fica.'));
        }

        Product::where('tenant_id', activeTenantId())->findOrFail($id)->update(['name' => $nome]);

        return response()->json(['message' => __('Nome actualizado.')]);
    }

    public function disponibilidade(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $prato = Product::where('tenant_id', activeTenantId())->findOrFail($id);
        $prato->update(['is_active' => ! $prato->is_active]);

        return response()->json([
            'message' => $prato->is_active ? __('No menu.') : __('Fora do menu.'),
            'disponivel' => (bool) $prato->is_active,
        ]);
    }

    public function categoriaDoPrato(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'category_id' => ['nullable', Rule::exists('invoicing_categories', 'id')->where('tenant_id', $tenantId)],
        ]);

        Product::where('tenant_id', $tenantId)->findOrFail($id)
            ->update(['category_id' => $dados['category_id'] ?? null]);

        return response()->json(['message' => __('Categoria actualizada.')]);
    }

    /* ─── As categorias ────────────────────────────────────────────────── */

    public function guardarCategoria(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100',
                Rule::unique('invoicing_categories', 'name')->where('tenant_id', $tenantId)->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
        ], [], ['name' => __('nome da categoria')]);

        $valores = [
            'tenant_id' => $tenantId,
            'name' => trim($dados['name']),
            'slug' => Str::slug($dados['name']),
            'description' => ($dados['description'] ?? '') ?: null,
            'icon' => $dados['icon'],
            'color' => mb_strtoupper($dados['color']),
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        if ($id) {
            Category::where('tenant_id', $tenantId)->findOrFail($id)->update($valores);
        } else {
            $valores['order'] = (int) Category::where('tenant_id', $tenantId)->max('order') + 1;
            Category::create($valores);
        }

        return response()->json([
            'message' => __('Categoria guardada e disponível no balcão e na Facturação.'),
            'categorias' => $this->categorias($tenantId),
        ], $id ? 200 : 201);
    }

    public function alternarCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $tenantId = activeTenantId();
        $categoria = Category::where('tenant_id', $tenantId)->findOrFail($id);

        if ($categoria->slug === 'geral' && $categoria->is_active) {
            $this->recusa(__('A categoria Geral deve permanecer activa.'));
        }

        $categoria->update(['is_active' => ! $categoria->is_active]);

        return response()->json(['message' => __('Categoria actualizada.'), 'categorias' => $this->categorias($tenantId)]);
    }

    public function apagarCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $tenantId = activeTenantId();
        $categoria = Category::where('tenant_id', $tenantId)->findOrFail($id);

        if ($categoria->slug === 'geral') {
            $this->recusa(__('A categoria Geral é padrão e não pode ser eliminada.'));
        }

        if ($categoria->products()->exists()) {
            $this->recusa(__('Esta categoria possui produtos. Mova-os antes de eliminar.'));
        }

        $categoria->delete();

        return response()->json(['message' => __('Categoria eliminada.'), 'categorias' => $this->categorias($tenantId)]);
    }

    /** A ordem das categorias É a ordem da carta — no balcão e na carta online. */
    public function moverCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $dados = $request->validate(['direccao' => ['required', Rule::in(['cima', 'baixo'])]]);

        $tenantId = activeTenantId();
        $categoria = Category::where('tenant_id', $tenantId)->findOrFail($id);

        $vizinha = Category::where('tenant_id', $tenantId)
            ->when($dados['direccao'] === 'cima',
                fn ($q) => $q->where('order', '<', $categoria->order)->orderByDesc('order'),
                fn ($q) => $q->where('order', '>', $categoria->order)->orderBy('order'))
            ->first();

        if ($vizinha) {
            // Troca simples de posições: robusta mesmo com ordens repetidas.
            [$a, $b] = [$categoria->order, $vizinha->order];

            if ($a === $b) {
                $b = $dados['direccao'] === 'cima' ? $a - 1 : $a + 1;
            }

            $categoria->update(['order' => $b]);
            $vizinha->update(['order' => $a]);
        }

        return response()->json(['categorias' => $this->categorias($tenantId)]);
    }
}
