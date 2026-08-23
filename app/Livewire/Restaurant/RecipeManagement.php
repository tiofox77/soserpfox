<?php

namespace App\Livewire\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\RecipeItem;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Fichas Técnicas - Restaurante')]
class RecipeManagement extends Component
{
    public ?int $recipeId = null, $productId = null, $ingredientId = null;
    public float $yieldQuantity = 1, $ingredientQuantity = 1, $wastePercent = 0;
    public string $yieldUnit = 'UN', $ingredientUnit = 'UN';
    public string $search = '', $viewMode = 'grid';
    public bool $showEditor = false;

    public function setView(string $mode): void
    {
        if (in_array($mode, ['grid', 'list'], true)) $this->viewMode = $mode;
    }

    public function create(): void
    {
        $this->resetEditor();
        $this->showEditor = true;
    }

    public function select(int $id): void
    {
        $recipe = Recipe::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->recipeId = $recipe->id;
        $this->productId = $recipe->product_id;
        $this->yieldQuantity = (float) $recipe->yield_quantity;
        $this->yieldUnit = $recipe->yield_unit;
        $this->showEditor = true;
        $this->resetIngredient();
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
        $this->resetValidation();
    }

    public function saveRecipe(): void
    {
        $tenantId = activeTenantId();
        $this->validate([
            'productId' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'yieldQuantity' => ['required', 'numeric', 'min:0.0001'],
            'yieldUnit' => ['required', 'string', 'max:10'],
        ]);
        $recipe = Recipe::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $this->productId],
            ['yield_quantity' => $this->yieldQuantity, 'yield_unit' => strtoupper($this->yieldUnit), 'is_active' => true]
        );
        $this->recipeId = $recipe->id;
        $this->dispatch('notify', type: 'success', message: 'Ficha técnica guardada. Agora pode adicionar ingredientes.');
    }

    public function addIngredient(): void
    {
        $tenantId = activeTenantId();
        $this->validate([
            'recipeId' => ['required', 'integer'],
            'ingredientId' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'ingredientQuantity' => ['required', 'numeric', 'min:0.0001'],
            'ingredientUnit' => ['required', 'string', 'max:10'],
            'wastePercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $recipe = Recipe::where('tenant_id', $tenantId)->findOrFail($this->recipeId);
        if ($recipe->product_id === $this->ingredientId) {
            $this->addError('ingredientId', 'O prato não pode ser ingrediente de si próprio.');
            return;
        }
        RecipeItem::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'recipe_id' => $recipe->id, 'ingredient_product_id' => $this->ingredientId],
            ['quantity' => $this->ingredientQuantity, 'unit' => strtoupper($this->ingredientUnit), 'waste_percent' => $this->wastePercent]
        );
        $this->resetIngredient();
        $this->dispatch('notify', type: 'success', message: 'Ingrediente adicionado.');
    }

    public function removeIngredient(int $id): void
    {
        RecipeItem::where('tenant_id', activeTenantId())->where('recipe_id', $this->recipeId)->findOrFail($id)->delete();
        $this->dispatch('notify', type: 'success', message: 'Ingrediente removido.');
    }

    public function toggleActive(int $id): void
    {
        $recipe = Recipe::where('tenant_id', activeTenantId())->findOrFail($id);
        $recipe->update(['is_active' => !$recipe->is_active]);
    }

    public function deleteRecipe(int $id): void
    {
        $recipe = Recipe::where('tenant_id', activeTenantId())->findOrFail($id);
        $recipe->delete();
        if ($this->recipeId === $id) $this->closeEditor();
        $this->dispatch('notify', type: 'success', message: 'Ficha técnica eliminada.');
    }

    private function resetEditor(): void
    {
        $this->reset(['recipeId', 'productId', 'ingredientId']);
        $this->yieldQuantity = 1;
        $this->yieldUnit = 'UN';
        $this->resetIngredient();
        $this->resetValidation();
    }

    private function resetIngredient(): void
    {
        $this->ingredientId = null;
        $this->ingredientQuantity = 1;
        $this->ingredientUnit = 'UN';
        $this->wastePercent = 0;
        $this->resetValidation(['ingredientId', 'ingredientQuantity', 'ingredientUnit', 'wastePercent']);
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();
        $recipes = Recipe::with(['product', 'items.ingredient'])
            ->where('tenant_id', $tenantId)
            ->when(trim($this->search), fn ($query) => $query->whereHas('product', fn ($product) => $product->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%')))
            ->latest()->limit(100)->get();
        $selected = $this->recipeId ? Recipe::with(['product', 'items.ingredient'])->where('tenant_id', $tenantId)->find($this->recipeId) : null;
        $stats = [
            'total' => Recipe::where('tenant_id', $tenantId)->count(),
            'active' => Recipe::where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'ingredients' => RecipeItem::where('tenant_id', $tenantId)->count(),
        ];
        return view('livewire.restaurant.recipe-management', compact('products', 'recipes', 'selected', 'stats'));
    }
}
