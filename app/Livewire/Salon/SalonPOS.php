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
        $service = Service::find($serviceId);
        if (!$service) return;
        
        // Obter configurações de impostos
        $settings = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
        $taxRate = $settings->default_tax_rate ?? 14;
        
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
            $servicesQuery->where('category_id', $this->selectedCategory);
        }
        
        $services = $servicesQuery->orderBy('name')->limit(50)->get();
        
        // Categorias de serviços
        $serviceCategories = ServiceCategory::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->withCount('services')
            ->get();
        
        // Produtos do salão (usando modelo base que inclui produtos do invoicing)
        $productsQuery = \App\Models\Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0);
            
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
