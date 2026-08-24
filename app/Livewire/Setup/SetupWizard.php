<?php

namespace App\Livewire\Setup;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\LicenseManager;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Assistente de 1.ª utilização do on-premise. Numa instalação fresca a BD só
 * tem o super admin do sistema e NENHUMA empresa. Depois de a licença estar
 * activa, este assistente cria a empresa (pré-preenchida a partir dos dados
 * assinados na licença) e o utilizador administrador — provisionando o tenant
 * como o registo normal faz (papéis, contabilidade PGC-AO, módulos, subscrição).
 */
#[Layout('layouts.guest')]
class SetupWizard extends Component
{
    // Empresa (pré-preenchida da licença)
    public string $empresa = '';
    public string $nif = '';
    public string $regime = Tenant::REGIME_GERAL;
    public string $endereco = '';
    public string $telefone = '';
    public string $emailEmpresa = '';
    public ?string $plano = null;

    // Administrador
    public string $adminNome = '';
    public string $adminEmail = '';
    public string $adminPassword = '';
    public string $adminPassword_confirmation = '';

    public function mount()
    {
        // Setup já feito? (já existe empresa) -> vai para o login.
        if (Tenant::query()->exists()) {
            return redirect('/login');
        }

        // Precisa de licença válida primeiro.
        $estado = app(LicenseManager::class)->estado();
        if ($estado->bloqueiaTudo()) {
            return redirect()->route('licenca.index');
        }

        // Pré-preenche a partir da licença.
        if ($p = $estado->payload) {
            $this->empresa = $p->empresa() ?? '';
            $this->nif = $p->nif() ?? '';
            $this->plano = $p->plano();
            $this->emailEmpresa = '';
        }
    }

    public function finalizar()
    {
        $this->validate([
            'empresa'       => 'required|string|min:3|max:255',
            'nif'           => 'nullable|string|max:20',
            'regime'        => 'required|string',
            'telefone'      => 'nullable|string|max:30',
            'emailEmpresa'  => 'nullable|email|max:255',
            'adminNome'     => 'required|string|min:3|max:255',
            'adminEmail'    => 'required|email|max:255|unique:users,email',
            'adminPassword' => 'required|string|min:8|confirmed',
        ]);

        if (Tenant::query()->exists()) {
            return redirect('/login');
        }

        $plano = $this->plano
            ? (Plan::where('slug', $this->plano)->orWhere('name', $this->plano)->first() ?? Plan::query()->first())
            : Plan::query()->first();

        // Um 500 anónimo no último passo do assistente deixa o cliente sem
        // saber se a empresa ficou criada ou não. A transacção garante que não
        // fica nada a meio; a mensagem diz o que falhou.
        try {
        DB::transaction(function () use ($plano) {
            $tenant = Tenant::create([
                'name'         => $this->empresa,
                'company_name' => $this->empresa,
                'nif'          => $this->nif ?: null,
                'regime'       => Tenant::canonicalRegime($this->regime),
                'address'      => $this->endereco ?: null,
                'phone'        => $this->telefone ?: null,
                'email'        => $this->emailEmpresa ?: $this->adminEmail,
                'is_active'    => true,
            ]);

            $user = User::create([
                'name'           => $this->adminNome,
                'email'          => $this->adminEmail,
                'password'       => Hash::make($this->adminPassword),
                'is_active'      => true,
                'is_super_admin' => false,
            ]);
            $user->tenants()->attach($tenant->id, ['is_active' => true, 'joined_at' => now()]);
            $user->tenant_id = $tenant->id;
            $user->save();

            setPermissionsTeamId($tenant->id);
            createDefaultRolesForTenant($tenant->id);
            $role = \Spatie\Permission\Models\Role::where('name', 'Super Admin')
                ->where('tenant_id', $tenant->id)->first();
            if ($role) {
                $user->assignRole($role);
            }

            initializeAccountingDataForTenant($tenant->id);

            if ($plano) {
                $tenant->subscriptions()->create([
                    'plan_id'              => $plano->id,
                    'status'               => 'active',
                    // O prazo real é imposto pela LICENÇA (renova por check-in);
                    // a subscrição local só satisfaz o CheckSubscription.
                    'current_period_start' => now(),
                    'current_period_end'   => now()->addYears(10),
                    'ends_at'              => now()->addYears(10),
                    // NOT NULL sem default — sem isto a criação da empresa
                    // rebentava com erro 500 no fim do assistente.
                    'amount'               => $plano->price_monthly ?? 0,
                    'billing_cycle'        => 'monthly',
                ]);

                $sync = new TenantModuleSyncService();
                foreach ($plano->modules()->pluck('modules.slug')->toArray() as $slug) {
                    $sync->activateModule($tenant, $slug);
                }
            }
        });
        } catch (\Throwable $e) {
            \Log::error('Setup on-premise falhou', ['erro' => $e->getMessage()]);
            $this->addError('empresa', 'Não foi possível criar a empresa: ' . $e->getMessage());

            return null;
        }

        session()->flash('ok', 'Empresa criada. Já pode entrar com o administrador.');

        return redirect('/login');
    }

    public function render()
    {
        return view('livewire.setup.setup-wizard', [
            'regimes' => Tenant::REGIMES,
        ]);
    }
}
