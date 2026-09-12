<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\RecipeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS FICHAS TÉCNICAS — o que cada prato leva.
 *
 * É a ficha que liga um prato ao stock: sem ela, vender um bitoque não tira
 * carne nenhuma do armazém. É também ela que a definição «exigir ficha
 * técnica» usa para decidir o que aparece no balcão.
 *
 * O CUSTO é a soma dos ingredientes com a quebra incluída — 200 g de carne a
 * 10% de quebra custam 220 g. Quem monta a ficha vê o custo por dose ao lado
 * do preço de venda, que é a única forma de saber se o prato dá dinheiro.
 */
class FichasApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.view');

        $tenantId = activeTenantId();

        return response()->json([
            'artigos' => Product::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'unit', 'price', 'cost'])
                ->map(fn (Product $p) => [
                    'valor' => (string) $p->id,
                    'rotulo' => $p->name,
                    'unidade' => $p->unit ?: 'UN',
                    'preco' => (float) $p->price,
                    'custo' => (float) $p->cost,
                ])->values(),
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('restaurant.recipes.manage')],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.view');

        $tenantId = activeTenantId();
        $procura = trim((string) $request->string('procura'));

        $fichas = Recipe::with(['product:id,name,price,unit', 'items.ingredient:id,name,unit,cost'])
            ->where('tenant_id', $tenantId)
            ->when($procura !== '', fn ($q) => $q->whereHas('product',
                fn ($p) => $p->where('name', 'like', "%{$procura}%")->orWhere('code', 'like', "%{$procura}%")))
            ->latest()->limit(100)->get();

        return response()->json([
            'data' => $fichas->map(fn (Recipe $f) => $this->ficha($f))->values(),
            'resumo' => [
                'total' => Recipe::where('tenant_id', $tenantId)->count(),
                'activas' => Recipe::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'ingredientes' => RecipeItem::where('tenant_id', $tenantId)->count(),
            ],
        ]);
    }

    private function ficha(Recipe $f): array
    {
        $linhas = $f->items->map(function (RecipeItem $l) {
            // A quebra faz parte do custo: 200 g a 10% de quebra são 220 g
            // compradas para pôr 200 g no prato.
            $comQuebra = (float) $l->quantity * (1 + ((float) $l->waste_percent / 100));

            return [
                'id' => $l->id,
                'ingredient_product_id' => $l->ingredient_product_id,
                'nome' => $l->ingredient?->name ?? '—',
                'quantidade' => (float) $l->quantity,
                'unidade' => $l->unit,
                'quebra' => (float) $l->waste_percent,
                'quantidade_com_quebra' => round($comQuebra, 4),
                'custo' => round($comQuebra * (float) ($l->ingredient?->cost ?? 0), 2),
            ];
        })->values();

        $custo = round($linhas->sum('custo'), 2);
        $rende = max(0.0001, (float) $f->yield_quantity);
        $preco = (float) ($f->product?->price ?? 0);

        return [
            'id' => $f->id,
            'product_id' => $f->product_id,
            'prato' => $f->product?->name ?? '—',
            'preco_de_venda' => $preco,
            'rende' => (float) $f->yield_quantity,
            'unidade' => $f->yield_unit,
            'activa' => (bool) $f->is_active,
            'custo' => $custo,
            'custo_por_dose' => round($custo / $rende, 2),
            // A margem só quer dizer alguma coisa quando há preço E custo.
            'margem' => $preco > 0 && $custo > 0
                ? round((($preco - ($custo / $rende)) / $preco) * 100, 1)
                : null,
            'ingredientes' => $linhas,
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'product_id' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'yield_quantity' => ['required', 'numeric', 'min:0.0001'],
            'yield_unit' => ['required', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ], [], ['product_id' => __('prato'), 'yield_quantity' => __('rendimento')]);

        /*
         * UMA FICHA POR PRATO. O `updateOrCreate` sobre (empresa, prato) é o
         * que impede duas fichas do mesmo bitoque — e com duas, qual delas
         * consome o stock não teria resposta.
         */
        $ficha = Recipe::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $dados['product_id']],
            [
                'yield_quantity' => $dados['yield_quantity'],
                'yield_unit' => mb_strtoupper($dados['yield_unit']),
                'is_active' => (bool) ($dados['is_active'] ?? true),
            ],
        );

        if ($id && $ficha->id !== $id) {
            // Mudaram o prato para um que já tem ficha: diz-se, em vez de
            // deixar duas fichas a fingir que são uma.
            throw ValidationException::withMessages([
                'product_id' => [__('Esse prato já tem ficha técnica.')],
            ]);
        }

        return response()->json([
            'message' => __('Ficha técnica guardada. Agora pode adicionar ingredientes.'),
            'data' => $this->ficha($ficha->fresh(['product', 'items.ingredient'])),
        ], $id ? 200 : 201);
    }

    public function acrescentar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'ingredient_product_id' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit' => ['required', 'string', 'max:10'],
            'waste_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [], [
            'ingredient_product_id' => __('ingrediente'),
            'quantity' => __('quantidade'), 'waste_percent' => __('quebra'),
        ]);

        $ficha = Recipe::where('tenant_id', $tenantId)->findOrFail($id);

        if ($ficha->product_id === (int) $dados['ingredient_product_id']) {
            throw ValidationException::withMessages([
                'ingredient_product_id' => [__('O prato não pode ser ingrediente de si próprio.')],
            ]);
        }

        RecipeItem::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'recipe_id' => $ficha->id, 'ingredient_product_id' => $dados['ingredient_product_id']],
            [
                'quantity' => $dados['quantity'],
                'unit' => mb_strtoupper($dados['unit']),
                'waste_percent' => $dados['waste_percent'],
            ],
        );

        return response()->json([
            'message' => __('Ingrediente adicionado.'),
            'data' => $this->ficha($ficha->fresh(['product', 'items.ingredient'])),
        ]);
    }

    public function removerIngrediente(Request $request, int $id, int $linha): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.manage');

        $tenantId = activeTenantId();

        RecipeItem::where('tenant_id', $tenantId)->where('recipe_id', $id)->findOrFail($linha)->delete();

        $ficha = Recipe::with(['product', 'items.ingredient'])->where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json(['message' => __('Ingrediente removido.'), 'data' => $this->ficha($ficha)]);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.manage');

        $ficha = Recipe::where('tenant_id', activeTenantId())->findOrFail($id);
        $ficha->update(['is_active' => ! $ficha->is_active]);

        return response()->json([
            'message' => $ficha->is_active ? __('Ficha activa.') : __('Ficha desactivada.'),
            'activa' => (bool) $ficha->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.recipes.manage');

        Recipe::where('tenant_id', activeTenantId())->findOrFail($id)->delete();

        return response()->json(['message' => __('Ficha técnica eliminada.')]);
    }
}
