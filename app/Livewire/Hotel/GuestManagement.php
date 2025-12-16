<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Client;

class GuestManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $vipFilter = '';
    public $showModal = false;
    public $showViewModal = false;
    public $editingId = null;
    public $viewingGuest = null;
    public $viewMode = 'grid';

    // Form fields
    public $name = '';
    public $email = '';
    public $phone = '';
    public $document_type = 'bi';
    public $document_number = '';
    public $nationality = 'Angola';
    public $birth_date = '';
    public $gender = '';
    public $address = '';
    public $city = '';
    public $country = 'Angola';
    public $nif = '';
    public $notes = '';
    public $is_vip = false;
    public $is_blacklisted = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'document_type' => 'nullable|string|max:50',
        'document_number' => 'nullable|string|max:50',
        'nationality' => 'nullable|string|max:100',
        'birth_date' => 'nullable|date',
        'gender' => 'nullable|in:male,female,other',
        'address' => 'nullable|string',
        'city' => 'nullable|string|max:100',
        'country' => 'nullable|string|max:100',
        'nif' => 'nullable|string|max:50',
        'notes' => 'nullable|string',
        'is_vip' => 'boolean',
        'is_blacklisted' => 'boolean',
    ];

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset([
            'editingId', 'name', 'email', 'phone', 'document_type', 'document_number',
            'nationality', 'birth_date', 'gender', 'address', 'city', 'country',
            'nif', 'notes', 'is_vip', 'is_blacklisted'
        ]);
        $this->nationality = 'Angola';
        $this->country = 'Angola';
        $this->document_type = 'bi';
    }

    public function edit($id)
    {
        $client = Client::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->phone = $client->phone;
        $this->document_type = $client->document_type ?? 'bi';
        $this->document_number = $client->document_number;
        $this->nationality = $client->nationality ?? 'Angola';
        $this->birth_date = $client->birth_date?->format('Y-m-d');
        $this->gender = $client->gender;
        $this->address = $client->address;
        $this->city = $client->city;
        $this->country = $client->country ?? 'Angola';
        $this->nif = $client->nif;
        $this->notes = $client->notes;
        $this->is_vip = $client->hotel_vip;
        $this->is_blacklisted = $client->hotel_blacklisted;
        
        $this->showModal = true;
    }

    public function view($id)
    {
        $this->viewingGuest = Client::where('tenant_id', activeTenantId())
            ->findOrFail($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingGuest = null;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'type' => 'individual',
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'nationality' => $this->nationality,
            'birth_date' => $this->birth_date ?: null,
            'gender' => $this->gender ?: null,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'nif' => $this->nif,
            'notes' => $this->notes,
            'hotel_vip' => $this->is_vip,
            'hotel_blacklisted' => $this->is_blacklisted,
            'is_active' => true,
        ];

        if ($this->editingId) {
            Client::where('tenant_id', activeTenantId())->findOrFail($this->editingId)->update($data);
            $this->dispatch('notify', message: 'Hospede atualizado com sucesso!', type: 'success');
        } else {
            Client::create($data);
            $this->dispatch('notify', message: 'Hospede criado com sucesso!', type: 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleVip($id)
    {
        $client = Client::where('tenant_id', activeTenantId())->findOrFail($id);
        $client->update(['hotel_vip' => !$client->hotel_vip]);
        $this->dispatch('notify', message: 'Status VIP atualizado!', type: 'success');
    }

    public function toggleBlacklist($id)
    {
        $client = Client::where('tenant_id', activeTenantId())->findOrFail($id);
        $client->update(['hotel_blacklisted' => !$client->hotel_blacklisted]);
        $status = $client->hotel_blacklisted ? 'adicionado a' : 'removido da';
        $this->dispatch('notify', message: "Hospede {$status} lista negra!", type: 'success');
    }

    public function render()
    {
        $guests = Client::where('tenant_id', activeTenantId())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%')
                      ->orWhere('document_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->vipFilter === 'vip', fn($q) => $q->where('hotel_vip', true))
            ->when($this->vipFilter === 'blacklisted', fn($q) => $q->where('hotel_blacklisted', true))
            ->when($this->vipFilter === 'regular', fn($q) => $q->where('hotel_vip', false)->where('hotel_blacklisted', false))
            ->orderBy('name')
            ->paginate(12);

        $stats = [
            'total' => Client::where('tenant_id', activeTenantId())->count(),
            'vip' => Client::where('tenant_id', activeTenantId())->where('hotel_vip', true)->count(),
            'blacklisted' => Client::where('tenant_id', activeTenantId())->where('hotel_blacklisted', true)->count(),
        ];

        return view('livewire.hotel.guests.guests', compact('guests', 'stats'))
            ->layout('layouts.app');
    }
}
