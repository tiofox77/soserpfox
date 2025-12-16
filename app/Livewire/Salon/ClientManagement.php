<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Client;

#[Layout('layouts.app')]
#[Title('Clientes - Salão de Beleza')]
class ClientManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $filterVip = '';
    public $perPage = 15;
    public $showModal = false;
    public $showViewModal = false;
    public $showDeleteModal = false;
    public $editingId = null;
    public $viewingClient = null;
    public $deletingClientId = null;
    public $deletingClientName = '';

    // Form fields
    public $name, $email, $phone, $whatsapp, $birth_date, $gender;
    public $address, $notes, $referred_by;
    public $country = 'AO', $province, $city, $postal_code;
    public $receives_sms = true, $receives_email = true, $is_vip = false;
    public $allergies = [];

    // Stats
    public $totalVip = 0;
    public $totalRegular = 0;

    protected function rules()
    {
        return [
            'name' => 'required|min:2',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterVip()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterVip']);
        $this->resetPage();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingClient = null;
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $client = Client::find($id);
            $this->editingId = $id;
            $this->name = $client->name;
            $this->email = $client->email;
            $this->phone = $client->phone;
            $this->whatsapp = $client->mobile ?? $client->whatsapp;
            $this->birth_date = $client->birth_date?->format('Y-m-d');
            $this->gender = $client->gender;
            $this->address = $client->address;
            $this->country = $client->country ?? 'AO';
            $this->province = $client->province;
            $this->city = $client->city;
            $this->postal_code = $client->postal_code;
            $this->notes = $client->notes;
            $this->referred_by = $client->referred_by;
            $this->receives_sms = $client->receives_sms;
            $this->receives_email = $client->receives_email;
            $this->is_vip = $client->is_vip;
            $this->allergies = $client->allergies ?? [];
        }
        $this->showModal = true;
    }

    public function view($id)
    {
        $this->viewingClient = Client::with(['appointments' => function ($q) {
            $q->with(['professional', 'services.service'])->latest('date')->take(10);
        }])->find($id);
        $this->showViewModal = true;
    }

    public function save()
    {
        $this->validate();

        // Dados base do cliente (invoicing_clients)
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->whatsapp ?: $this->phone,
            'address' => $this->address,
            'country' => $this->country,
            'province' => $this->province,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'type' => 'particular',
            'is_active' => true,
        ];

        // Dados específicos do salão (guardados como JSON no notes)
        $salonData = [
            'is_vip' => $this->is_vip,
            'allergies' => $this->allergies,
            'preferences' => [],
            'total_visits' => 0,
            'total_spent' => 0,
            'loyalty_points' => 0,
        ];

        if ($this->editingId) {
            $client = Client::find($this->editingId);
            $client->update($data);
            $client->updateSalonData($salonData);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente atualizado!']);
        } else {
            $data['notes'] = json_encode($salonData);
            Client::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente criado!']);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleVip($id)
    {
        $client = Client::find($id);
        $client->setVip(!$client->is_vip);
    }

    public function openDeleteModal($id)
    {
        $client = Client::find($id);
        $this->deletingClientId = $id;
        $this->deletingClientName = $client->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        $client = Client::find($this->deletingClientId);
        if ($client->appointments()->whereIn('status', ['scheduled', 'confirmed'])->count() > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Cliente tem agendamentos pendentes!']);
            $this->cancelDelete();
            return;
        }
        $client->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente removido!']);
        $this->cancelDelete();
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingClientId = null;
        $this->deletingClientName = '';
    }

    private function resetForm()
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'whatsapp', 'birth_date', 'gender', 'address', 'province', 'city', 'postal_code', 'notes', 'referred_by', 'allergies']);
        $this->country = 'AO';
        $this->receives_sms = true;
        $this->receives_email = true;
        $this->is_vip = false;
    }

    public function render()
    {
        // Stats
        $this->totalVip = Client::forTenant()->vip()->count();
        $this->totalRegular = Client::forTenant()->count() - $this->totalVip;

        $clients = Client::forTenant()
            ->withCount('appointments')
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterVip === 'vip', fn($q) => $q->vip())
            ->when($this->filterVip === 'regular', fn($q) => $q->whereRaw("(notes NOT LIKE '%\"is_vip\":true%' OR notes IS NULL)"))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.salon.clients.clients', compact('clients'));
    }
}
