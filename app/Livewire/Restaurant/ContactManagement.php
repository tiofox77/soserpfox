<?php

namespace App\Livewire\Restaurant;

use App\Models\Client;
use App\Models\Supplier;
use App\Rules\ValidateNIF;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Clientes e Fornecedores - Restaurante')]
class ContactManagement extends Component
{
    public string $tab = 'clients';
    public bool $showForm = false;
    public string $name = '', $nif = '', $email = '', $phone = '', $mobile = '', $address = '';
    public string $city = '', $province = '', $postalCode = '', $country = 'AO', $taxRegime = 'geral';
    public string $type = 'pessoa_fisica';
    public bool $isIvaSubject = true;
    public float $creditLimit = 0;
    public int $paymentTermDays = 0;

    public function create(string $tab): void
    {
        $this->reset(['name', 'nif', 'email', 'phone', 'mobile', 'address', 'city', 'province', 'postalCode', 'creditLimit', 'paymentTermDays']);
        $this->country = 'AO'; $this->taxRegime = 'geral'; $this->isIvaSubject = true;
        $this->tab = in_array($tab, ['clients', 'suppliers'], true) ? $tab : 'clients';
        $this->type = $this->tab === 'suppliers' ? 'pessoa_juridica' : 'pessoa_fisica';
        $this->showForm = true;
    }

    public function save(): void
    {
        $table = $this->tab === 'suppliers' ? 'invoicing_suppliers' : 'invoicing_clients';
        $nifRules = [$this->tab === 'clients' ? 'required' : 'nullable', 'string', 'max:30'];
        if ($this->country === 'AO') $nifRules[] = new ValidateNIF();
        $nifRules[] = Rule::unique($table, 'nif')->where('tenant_id', activeTenantId());
        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'nif' => $nifRules,
            'type' => ['required', Rule::in(['pessoa_fisica', 'pessoa_juridica'])],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'], 'province' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:30'],
            'country' => ['required', Rule::in(array_keys($this->countries()))],
            'taxRegime' => ['required', Rule::in(['geral', 'simplificado', 'isento'])],
            'isIvaSubject' => ['boolean'], 'creditLimit' => ['numeric', 'min:0'],
            'paymentTermDays' => ['integer', 'min:0', 'max:3650'],
        ]);
        $model = $this->tab === 'suppliers' ? Supplier::class : Client::class;
        $model::create([
            'tenant_id' => activeTenantId(), 'type' => $data['type'], 'name' => trim($data['name']),
            'nif' => $data['nif'] ?: null, 'email' => $data['email'] ?: null, 'phone' => $data['phone'] ?: null,
            'mobile' => $data['mobile'] ?: null, 'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null, 'province' => $data['province'] ?: null,
            'postal_code' => $data['postalCode'] ?: null, 'country' => $data['country'],
            'tax_regime' => $data['taxRegime'], 'is_iva_subject' => $data['isIvaSubject'],
            'credit_limit' => $data['creditLimit'], 'payment_term_days' => $data['paymentTermDays'], 'is_active' => true,
        ]);
        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: ($this->tab === 'suppliers' ? 'Fornecedor' : 'Cliente').' criado e disponível na Faturação e no Restaurante.');
    }

    public function render()
    {
        return view('livewire.restaurant.contact-management', [
            'clients' => Client::where('tenant_id', activeTenantId())->where('is_active', true)->latest()->limit(100)->get(),
            'suppliers' => Supplier::where('tenant_id', activeTenantId())->where('is_active', true)->latest()->limit(100)->get(),
            'countries' => $this->countries(), 'provinces' => Client::PROVINCIAS_ANGOLA,
        ]);
    }

    private function countries(): array
    {
        return ['AO'=>'Angola','PT'=>'Portugal','MZ'=>'Moçambique','BR'=>'Brasil','CV'=>'Cabo Verde','GW'=>'Guiné-Bissau','ST'=>'São Tomé e Príncipe','ZA'=>'África do Sul','NA'=>'Namíbia','CD'=>'R. D. Congo','OTHER'=>'Outro'];
    }
}
