<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Restaurant\VenueLimitRequest;
use App\Services\Restaurant\RestaurantVenueLimitService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin')]
#[Title('Pedidos de Estabelecimentos')]
class RestaurantVenueRequests extends Component
{
    use WithPagination;

    public string $status = 'pending';
    public string $search = '';
    public ?int $reviewingId = null;
    public int $approvedLimit = 2;
    public string $adminNotes = '';

    public function openReview(int $id): void
    {
        $request = VenueLimitRequest::withoutGlobalScopes()->findOrFail($id);
        $this->reviewingId = $request->id;
        $this->approvedLimit = $request->requested_limit;
        $this->adminNotes = '';
    }

    public function approve(RestaurantVenueLimitService $service): void
    {
        $this->validate(['approvedLimit' => ['required', 'integer', 'min:2', 'max:100'], 'adminNotes' => ['nullable', 'string', 'max:1000']]);
        $service->review($this->reviewingId, auth()->id(), true, $this->approvedLimit, $this->adminNotes);
        $this->reset(['reviewingId', 'adminNotes']);
        $this->dispatch('notify', type: 'success', message: 'Quota aprovada e atualizada para a empresa.');
    }

    public function reject(RestaurantVenueLimitService $service): void
    {
        $this->validate(['adminNotes' => ['required', 'string', 'min:3', 'max:1000']]);
        $service->review($this->reviewingId, auth()->id(), false, null, $this->adminNotes);
        $this->reset(['reviewingId', 'adminNotes']);
        $this->dispatch('notify', type: 'success', message: 'Pedido recusado.');
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function render()
    {
        $query = VenueLimitRequest::withoutGlobalScopes()->with(['tenant', 'requester', 'reviewer'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', "%{$this->search}%")->orWhere('company_name', 'like', "%{$this->search}%")))
            ->latest();

        return view('livewire.super-admin.restaurant-venue-requests', [
            'requests' => $query->paginate(15),
            'counts' => VenueLimitRequest::withoutGlobalScopes()->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }
}
