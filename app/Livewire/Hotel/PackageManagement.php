<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Hotel\Package;
use App\Models\Hotel\PromoCode;
use App\Models\Hotel\RoomType;

class PackageManagement extends Component
{
    use WithPagination;

    public $activeTab = 'packages'; // packages, promo_codes
    public $search = '';
    
    // Package Modal
    public $showPackageModal = false;
    public $editingPackageId = null;
    public $packageName = '';
    public $packageDescription = '';
    public $packageType = 'other';
    public $packagePrice = '';
    public $packageDiscountPercentage = '';
    public $packageDiscountAmount = '';
    public $packageMinNights = 1;
    public $packageMaxNights = '';
    public $packageValidFrom = '';
    public $packageValidUntil = '';
    public $packageIncludedServices = [];
    public $packageRoomTypeIds = [];
    public $packageIsActive = true;
    public $packageShowOnline = true;
    
    // New service input
    public $newService = '';
    
    // Promo Code Modal
    public $showPromoModal = false;
    public $editingPromoId = null;
    public $promoCode = '';
    public $promoName = '';
    public $promoDescription = '';
    public $promoDiscountType = 'percentage';
    public $promoDiscountValue = '';
    public $promoMinAmount = '';
    public $promoMaxDiscount = '';
    public $promoUsageLimit = '';
    public $promoUsagePerCustomer = 1;
    public $promoValidFrom = '';
    public $promoValidUntil = '';
    public $promoRoomTypeIds = [];
    public $promoIsActive = true;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // Package Methods
    public function openPackageModal($id = null)
    {
        $this->resetPackageForm();
        
        if ($id) {
            $package = Package::find($id);
            if ($package) {
                $this->editingPackageId = $id;
                $this->packageName = $package->name;
                $this->packageDescription = $package->description;
                $this->packageType = $package->type;
                $this->packagePrice = $package->price;
                $this->packageDiscountPercentage = $package->discount_percentage;
                $this->packageDiscountAmount = $package->discount_amount;
                $this->packageMinNights = $package->min_nights;
                $this->packageMaxNights = $package->max_nights;
                $this->packageValidFrom = $package->valid_from?->format('Y-m-d');
                $this->packageValidUntil = $package->valid_until?->format('Y-m-d');
                $this->packageIncludedServices = $package->included_services ?? [];
                $this->packageRoomTypeIds = $package->room_type_ids ?? [];
                $this->packageIsActive = $package->is_active;
                $this->packageShowOnline = $package->show_online;
            }
        }
        
        $this->showPackageModal = true;
    }

    public function resetPackageForm()
    {
        $this->reset([
            'editingPackageId', 'packageName', 'packageDescription', 'packageType',
            'packagePrice', 'packageDiscountPercentage', 'packageDiscountAmount',
            'packageMinNights', 'packageMaxNights', 'packageValidFrom', 'packageValidUntil',
            'packageIncludedServices', 'packageRoomTypeIds', 'newService'
        ]);
        $this->packageMinNights = 1;
        $this->packageIsActive = true;
        $this->packageShowOnline = true;
    }

    public function addService()
    {
        if (!empty($this->newService)) {
            $this->packageIncludedServices[] = $this->newService;
            $this->newService = '';
        }
    }

    public function removeService($index)
    {
        unset($this->packageIncludedServices[$index]);
        $this->packageIncludedServices = array_values($this->packageIncludedServices);
    }

    public function savePackage()
    {
        $this->validate([
            'packageName' => 'required|string|max:255',
            'packageType' => 'required|string',
            'packageMinNights' => 'required|integer|min:1',
        ]);

        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $this->packageName,
            'description' => $this->packageDescription,
            'type' => $this->packageType,
            'price' => $this->packagePrice ?: null,
            'discount_percentage' => $this->packageDiscountPercentage ?: null,
            'discount_amount' => $this->packageDiscountAmount ?: null,
            'min_nights' => $this->packageMinNights,
            'max_nights' => $this->packageMaxNights ?: null,
            'valid_from' => $this->packageValidFrom ?: null,
            'valid_until' => $this->packageValidUntil ?: null,
            'included_services' => $this->packageIncludedServices,
            'room_type_ids' => $this->packageRoomTypeIds,
            'is_active' => $this->packageIsActive,
            'show_online' => $this->packageShowOnline,
        ];

        if ($this->editingPackageId) {
            Package::find($this->editingPackageId)->update($data);
            session()->flash('success', 'Pacote atualizado com sucesso!');
        } else {
            Package::create($data);
            session()->flash('success', 'Pacote criado com sucesso!');
        }

        $this->showPackageModal = false;
        $this->resetPackageForm();
    }

    public function deletePackage($id)
    {
        Package::find($id)?->delete();
        session()->flash('success', 'Pacote removido com sucesso!');
    }

    public function togglePackageStatus($id)
    {
        $package = Package::find($id);
        if ($package) {
            $package->update(['is_active' => !$package->is_active]);
        }
    }

    // Promo Code Methods
    public function openPromoModal($id = null)
    {
        $this->resetPromoForm();
        
        if ($id) {
            $promo = PromoCode::find($id);
            if ($promo) {
                $this->editingPromoId = $id;
                $this->promoCode = $promo->code;
                $this->promoName = $promo->name;
                $this->promoDescription = $promo->description;
                $this->promoDiscountType = $promo->discount_type;
                $this->promoDiscountValue = $promo->discount_value;
                $this->promoMinAmount = $promo->min_amount;
                $this->promoMaxDiscount = $promo->max_discount;
                $this->promoUsageLimit = $promo->usage_limit;
                $this->promoUsagePerCustomer = $promo->usage_per_customer;
                $this->promoValidFrom = $promo->valid_from?->format('Y-m-d');
                $this->promoValidUntil = $promo->valid_until?->format('Y-m-d');
                $this->promoRoomTypeIds = $promo->room_type_ids ?? [];
                $this->promoIsActive = $promo->is_active;
            }
        } else {
            // Gerar código automático
            $this->promoCode = strtoupper(\Illuminate\Support\Str::random(8));
        }
        
        $this->showPromoModal = true;
    }

    public function resetPromoForm()
    {
        $this->reset([
            'editingPromoId', 'promoCode', 'promoName', 'promoDescription',
            'promoDiscountType', 'promoDiscountValue', 'promoMinAmount', 'promoMaxDiscount',
            'promoUsageLimit', 'promoValidFrom', 'promoValidUntil', 'promoRoomTypeIds'
        ]);
        $this->promoDiscountType = 'percentage';
        $this->promoUsagePerCustomer = 1;
        $this->promoIsActive = true;
    }

    public function generateCode()
    {
        $this->promoCode = strtoupper(\Illuminate\Support\Str::random(8));
    }

    public function savePromo()
    {
        $this->validate([
            'promoCode' => 'required|string|max:50',
            'promoName' => 'required|string|max:255',
            'promoDiscountType' => 'required|in:percentage,fixed',
            'promoDiscountValue' => 'required|numeric|min:0',
        ]);

        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'code' => strtoupper($this->promoCode),
            'name' => $this->promoName,
            'description' => $this->promoDescription,
            'discount_type' => $this->promoDiscountType,
            'discount_value' => $this->promoDiscountValue,
            'min_amount' => $this->promoMinAmount ?: null,
            'max_discount' => $this->promoMaxDiscount ?: null,
            'usage_limit' => $this->promoUsageLimit ?: null,
            'usage_per_customer' => $this->promoUsagePerCustomer,
            'valid_from' => $this->promoValidFrom ?: null,
            'valid_until' => $this->promoValidUntil ?: null,
            'room_type_ids' => $this->promoRoomTypeIds,
            'is_active' => $this->promoIsActive,
        ];

        if ($this->editingPromoId) {
            PromoCode::find($this->editingPromoId)->update($data);
            session()->flash('success', 'Código promocional atualizado!');
        } else {
            PromoCode::create($data);
            session()->flash('success', 'Código promocional criado!');
        }

        $this->showPromoModal = false;
        $this->resetPromoForm();
    }

    public function deletePromo($id)
    {
        PromoCode::find($id)?->delete();
        session()->flash('success', 'Código removido com sucesso!');
    }

    public function togglePromoStatus($id)
    {
        $promo = PromoCode::find($id);
        if ($promo) {
            $promo->update(['is_active' => !$promo->is_active]);
        }
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $packages = Package::where('tenant_id', $tenantId)
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->paginate(10, ['*'], 'packagesPage');

        $promoCodes = PromoCode::where('tenant_id', $tenantId)
            ->when($this->search, fn($q) => $q->where('code', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'promosPage');

        $roomTypes = RoomType::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stats = [
            'total_packages' => Package::where('tenant_id', $tenantId)->count(),
            'active_packages' => Package::where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'total_promos' => PromoCode::where('tenant_id', $tenantId)->count(),
            'active_promos' => PromoCode::where('tenant_id', $tenantId)->where('is_active', true)->count(),
        ];

        return view('livewire.hotel.package-management', [
            'packages' => $packages,
            'promoCodes' => $promoCodes,
            'roomTypes' => $roomTypes,
            'stats' => $stats,
        ])->layout('layouts.app');
    }
}
