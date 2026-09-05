<?php

namespace App\Livewire\Restaurant;

use App\Models\Category;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\RestaurantSettings;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A carta do restaurante, montada num ecrã só.
 *
 * PORQUE EXISTE. Montar o menu obrigava a saltar entre dois ecrãs genéricos:
 * os produtos da Facturação (cheios de campos que um prato não usa) e as
 * categorias (noutro sítio). Criar dez pratos eram dez idas ao formulário
 * grande — e quem só queria mudar um preço tinha de o encontrar lá no meio.
 *
 * Aqui tudo é em linha: escreve-se o nome e o preço, Enter, próximo. O preço
 * muda-se a tocar-lhe. A categoria arrasta-se pelo seletor. Nada de páginas
 * de formulário para uma carta que muda todas as semanas.
 *
 * OS MESMOS DADOS DE SEMPRE: `Category` e `Product` — o que se monta aqui
 * aparece no POS, no PWA e no menu online, porque É o mesmo registo. Este
 * ecrã não inventa nenhuma tabela nova.
 */
#[Layout('layouts.app')]
#[Title('Montar o Menu')]
class MontarMenu extends Component
{
    public ?int $categoriaId = null;

    public string $procurar = '';

    // O prato novo — nome e preço, mais nada. O resto tem valores certos por
    // omissão (serviço de restaurante: sem stock, imposto mais alto, UN).
    public string $novoPrato = '';

    public ?string $novoPreco = null;

    // A categoria nova, criada sem sair do ecrã.
    public string $novaCategoria = '';

    public function mount(): void
    {
        Category::seedRestaurantDefaults(activeTenantId());
    }

    public function criarPrato(): void
    {
        $dados = $this->validate([
            'novoPrato' => ['required', 'string', 'min:2', 'max:255'],
            'novoPreco' => ['required', 'numeric', 'min:0'],
        ], [], ['novoPrato' => 'nome do prato', 'novoPreco' => 'preço']);

        $tenantId = activeTenantId();

        $definicoes = RestaurantSettings::forTenant($tenantId);

        if ($definicoes->require_recipe_for_products) {
            // A regra da casa manda: com fichas técnicas obrigatórias, um
            // prato sem ficha não vende. Cria-se na mesma — mas AVISA-SE já,
            // e o prato fica marcado no ecrã até ter ficha.
            $this->dispatch('notify', type: 'warning',
                message: 'Criado — mas esta casa exige ficha técnica: complete-a para o prato aparecer no POS.');
        }

        $imposto = Tax::where('tenant_id', $tenantId)->where('is_active', true)->orderByDesc('rate')->first();

        Product::create([
            'tenant_id' => $tenantId,
            'category_id' => $this->categoriaId,
            'type' => 'produto',
            'name' => trim($this->novoPrato),
            'price' => (float) $this->novoPreco,
            'cost' => 0,
            'unit' => 'UN',
            'tax_type' => 'iva',
            'tax_rate_id' => $imposto?->id,
            // Um prato consome INGREDIENTES (pela ficha técnica), não a si
            // próprio. Stock do prato em si quase nunca é o que se quer — e
            // quando for, liga-se no ecrã de Produtos.
            'manage_stock' => false,
            'is_active' => true,
        ]);

        $this->reset(['novoPrato', 'novoPreco']);

        if (! $definicoes->require_recipe_for_products) {
            $this->dispatch('notify', type: 'success', message: 'No menu. Escreva o próximo.');
        }
    }

    /** O preço muda-se a tocar-lhe — sem formulário. */
    public function mudarPreco(int $id, $valor): void
    {
        $prato = Product::where('tenant_id', activeTenantId())->findOrFail($id);

        $cru = trim((string) $valor);
        $preco = \App\Helpers\MoneyHelper::parse($cru);

        // O parse devolve 0.0 para lixo — e um dedo em falso não pode pôr um
        // prato a custar zero. Zero só quando foi mesmo escrito zero.
        $escreveuZero = (bool) preg_match('/^0+([.,]0+)?$/', $cru);

        if ($cru === '' || $preco < 0 || ($preco === 0.0 && ! $escreveuZero)) {
            $this->dispatch('notify', type: 'error', message: 'Preço inválido — o de antes fica.');

            return;
        }

        $prato->update(['price' => $preco]);
    }

    public function mudarNome(int $id, $valor): void
    {
        $nome = trim((string) $valor);

        if (mb_strlen($nome) < 2) {
            $this->dispatch('notify', type: 'error', message: 'O nome não pode ficar vazio — o de antes fica.');

            return;
        }

        Product::where('tenant_id', activeTenantId())->findOrFail($id)->update(['name' => $nome]);
    }

    /**
     * Fora do menu / no menu. NUNCA se apaga daqui: um prato já vendido está
     * em facturas, e apagá-lo deixava documentos fiscais a apontar para o
     * vazio. Esconder faz o mesmo serviço sem partir nada.
     */
    public function alternarDisponivel(int $id): void
    {
        $prato = Product::where('tenant_id', activeTenantId())->findOrFail($id);
        $prato->update(['is_active' => ! $prato->is_active]);
    }

    public function mudarCategoria(int $id, $categoriaId): void
    {
        $categoria = $categoriaId
            ? Category::where('tenant_id', activeTenantId())->findOrFail((int) $categoriaId)
            : null;

        Product::where('tenant_id', activeTenantId())->findOrFail($id)
            ->update(['category_id' => $categoria?->id]);
    }

    public function criarCategoria(): void
    {
        $this->validate(
            ['novaCategoria' => ['required', 'string', 'min:2', 'max:100']],
            [],
            ['novaCategoria' => 'nome da categoria']
        );

        $nome = trim($this->novaCategoria);
        $tenantId = activeTenantId();

        if (Category::where('tenant_id', $tenantId)->where('name', $nome)->exists()) {
            $this->dispatch('notify', type: 'error', message: 'Essa categoria já existe.');

            return;
        }

        $categoria = Category::create([
            'tenant_id' => $tenantId,
            'name' => $nome,
            'slug' => Str::slug($nome),
            'icon' => 'fa-utensils',
            'color' => '#EA580C',
            'is_active' => true,
            'order' => (int) Category::where('tenant_id', $tenantId)->max('order') + 1,
        ]);

        $this->novaCategoria = '';
        $this->categoriaId = $categoria->id;
    }

    /** A ordem das categorias É a ordem da carta — no POS e no menu online. */
    public function moverCategoria(int $id, string $direccao): void
    {
        $tenantId = activeTenantId();
        $categoria = Category::where('tenant_id', $tenantId)->findOrFail($id);

        $vizinha = Category::where('tenant_id', $tenantId)
            ->when($direccao === 'cima',
                fn ($q) => $q->where('order', '<', $categoria->order)->orderByDesc('order'),
                fn ($q) => $q->where('order', '>', $categoria->order)->orderBy('order'))
            ->first();

        if (! $vizinha) {
            return;
        }

        // Troca simples de posições: robusta mesmo com ordens repetidas.
        [$a, $b] = [$categoria->order, $vizinha->order];

        if ($a === $b) {
            $b = $direccao === 'cima' ? $a - 1 : $a + 1;
        }

        $categoria->update(['order' => $b]);
        $vizinha->update(['order' => $a]);
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $definicoes = RestaurantSettings::forTenant($tenantId);

        $categorias = Category::where('tenant_id', $tenantId)
            ->withCount('products')
            ->orderBy('order')->orderBy('name')
            ->get();

        $pratos = Product::where('tenant_id', $tenantId)
            ->with('category:id,name')
            ->when($this->categoriaId, fn ($q) => $q->where('category_id', $this->categoriaId))
            ->when(trim($this->procurar) !== '', function ($q) {
                $termo = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $termo)->orWhere('code', 'like', $termo));
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        // Os pratos sem ficha técnica, quando a casa a exige: estão na carta
        // mas NÃO aparecem no POS — sem esta marca, "criei o prato e ele não
        // aparece" era um mistério sem pista nenhuma.
        $comFicha = $definicoes->require_recipe_for_products
            ? Recipe::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('is_active', true)->pluck('product_id')->flip()
            : collect();

        return view('livewire.restaurant.montar-menu', compact('categorias', 'pratos', 'definicoes', 'comFicha'));
    }
}
