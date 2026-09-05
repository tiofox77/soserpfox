<?php

namespace App\Livewire\Salon;

use App\Livewire\POS\POSSystem;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;
use App\Models\Salon\Product as SalonProduct;
use App\Models\Client;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('POS - Salão de Beleza')]
class SalonPOS extends POSSystem
{
    public $activeTab = 'services'; // 'services' ou 'products'
    
    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }
    
    public function addServiceToCart($serviceId)
    {
        // Scope ao tenant: $serviceId vem do browser e um find() sem filtro
        // deixava vender o serviço de OUTRA empresa — com o preço dela e a
        // aparecer na factura desta.
        $service = Service::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->find($serviceId);

        if (!$service) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Serviço não encontrado nesta empresa.',
            ]);
            return;
        }

        // Taxa pela fonte ÚNICA (regime da empresa), como no resto do sistema.
        // O default_tax_rate das definições ignorava o regime de isenção e,
        // quando dava 0%, a linha seguia sem código de isenção — a AGT recusa
        // uma linha sem imposto e sem motivo.
        // forProduct(null) cai no imposto por omissão da empresa e, quando o
        // regime é isento, devolve já o código de isenção.
        $tx = \App\Services\Invoicing\TaxResolver::forProduct(null, activeTenantId());
        $taxRate = $tx['rate'];

        $cartItemId = 'service_' . $service->id;
        $cart = \Darryldecode\Cart\Facades\CartFacade::session(auth()->id());
        
        // Verificar se já existe no carrinho
        $existingItem = $cart->get($cartItemId);
        
        if ($existingItem) {
            // Atualizar quantidade
            $cart->update($cartItemId, [
                'quantity' => 1, // Adiciona +1
            ]);
            $newQuantity = $existingItem->quantity + 1;
        } else {
            // Adicionar novo
            $cart->add([
                'id' => $cartItemId,
                'name' => $service->name,
                'price' => $service->price,
                'quantity' => 1,
                'attributes' => [
                    'type' => 'service',
                    'duration' => $service->duration,
                    'tax_rate' => $taxRate,
                    // A linha isenta TEM de levar motivo, senão a AGT recusa
                    'exemption_reason' => $tx['exemption_code'] ?? null,
                    'service_id' => $service->id,
                ]
            ]);
            $newQuantity = 1;
        }
        
        $this->loadCart();
        
        $this->dispatch('item-added');
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ ' . $service->name . ' (' . $newQuantity . 'x)'
        ]);
    }
    
    public function render()
    {
        // Serviços do salão
        $servicesQuery = Service::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->search && $this->activeTab === 'services') {
            $servicesQuery->where('name', 'like', '%' . $this->search . '%');
        }
        
        if ($this->selectedCategory && $this->activeTab === 'services') {
            // Pelo JSON e não pela coluna: a coluna `category_id` aponta para as
            // categorias da facturação e está sempre a nulo nos serviços do
            // salão — escolher uma categoria não mostrava serviço nenhum.
            $servicesQuery->daCategoria($this->selectedCategory);
        }
        
        $services = $servicesQuery->orderBy('name')->limit(50)->get();
        
        // Categorias de serviços
        // A contagem vem de um sítio único: o `withCount` dava sempre zero
        // porque conta pela coluna, e a categoria do salão vive no JSON.
        $serviceCategories = ServiceCategory::comContagens(
            ServiceCategory::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->get()
        );
        
        // Produtos do salão (usando modelo base que inclui produtos do invoicing).
        //
        // O filtro pelo agregado stock_quantity mostrava produtos cujo stock
        // está TODO noutro armazém — apareciam disponíveis aqui e a venda não
        // encontrava linha no armazém do salão. Filtrar pela linha do armazém
        // em uso, com o agregado só como recurso para as empresas que ainda não
        // têm linhas nenhumas (regime legado).
        $whId = $this->warehouseId;

        $productsQuery = \App\Models\Product::where('invoicing_products.tenant_id', activeTenantId())
            ->where('invoicing_products.is_active', true)
            ->when($whId, function ($q) use ($whId) {
                $q->where(function ($sub) use ($whId) {
                    $sub->whereHas('stocks', fn ($s) => $s->where('warehouse_id', $whId)->where('quantity', '>', 0))
                        // legado: produto sem linha em armazém nenhum
                        ->orWhere(fn ($leg) => $leg->whereDoesntHave('stocks')
                            ->where('invoicing_products.stock_quantity', '>', 0));
                });
            }, function ($q) {
                $q->where('invoicing_products.stock_quantity', '>', 0);
            });
            
        if ($this->search && $this->activeTab === 'products') {
            $productsQuery->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }
        
        if ($this->selectedCategory && $this->activeTab === 'products') {
            $productsQuery->where('category_id', $this->selectedCategory);
        }
        
        $products = $productsQuery->orderBy('name')->limit(50)->get();
        
        // Categorias de produtos
        $categories = Category::where('tenant_id', activeTenantId())
            ->withCount('products')
            ->get();
        
        // Clientes
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('phone', 'like', '%' . $this->searchClient . '%');
            });
        }
        
        $clients = $clientsQuery->limit(10)->get();

        return view('livewire.salon.pos.salon-pos', [
            'services' => $services,
            'serviceCategories' => $serviceCategories,
            'products' => $products,
            'categories' => $categories,
            'clients' => $clients,
        ]);
    }
}
