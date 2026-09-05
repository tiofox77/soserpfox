<?php

namespace App\Livewire;

use Livewire\Component;

class TenantSwitcher extends Component
{
    public $tenants = [];
    public $activeTenantId;
    public $activeTenantName;
    public $hasExceededLimit = false;
    public $currentCount = 0;
    public $maxAllowed = 1;
    
    public function mount()
    {
        $this->loadTenants();
    }
    
    public function loadTenants()
    {
        $user = auth()->user();
        $this->tenants = $user->tenants;
        
        $activeTenant = $user->activeTenant();
        if ($activeTenant) {
            $this->activeTenantId = $activeTenant->id;
            $this->activeTenantName = $activeTenant->name;
        }
        
        // Verificar limite
        if (!$user->is_super_admin) {
            $this->currentCount = $user->tenants()->count();
            $this->maxAllowed = $user->getMaxCompaniesLimit();
            $this->hasExceededLimit = $this->currentCount > $this->maxAllowed;
        }
    }
    
    public function switchTenant($tenantId)
    {
        $user = auth()->user();

        // A empresa de onde se sai, lida ANTES da troca — depois já é a nova.
        $anterior = activeTenantId();

        // O limite do plano controla a criacao de novas empresas. Empresas que
        // ja pertencem ao utilizador devem continuar acessiveis para consulta.
        if ($user->switchTenant($tenantId)) {
            // Fica na trilha da empresa em que se ENTROU. Quem trabalha em
            // várias casas deixa assim, em cada uma, a hora a que lá esteve —
            // que é a pergunta quando aparece um acto estranho.
            app(\App\Services\Audit\AuditRecorder::class)->acto(
                'empresa.trocada',
                (int) $tenantId,
                ['de' => $anterior]
            );

            // REDIRECCIONAMENTO COMPLETO, não reload no cliente.
            //
            // A versão anterior mudava a sessão nesta acção Livewire e mandava
            // o browser fazer `window.location.reload()`. Isso deixava a
            // página a que se voltava com o snapshot e o token da EMPRESA
            // ANTERIOR: a primeira acção a seguir batia num 409/419 e o ecrã
            // dizia «a sessão expirou» e recarregava — e era preciso um segundo
            // clique para ver a empresa certa. Recarregar a MESMA página também
            // podia aterrar num registo que já não existe na empresa nova.
            //
            // Um redireccionamento do servidor entrega uma página nova, com
            // sessão e token frescos, e aterra em casa — onde nada depende da
            // empresa de onde se veio.
            return $this->redirectRoute('home');
        } else {
            // Tentar entrar numa empresa a que não se pertence é sinal de
            // sondagem, não engano. Regista-se na empresa onde a pessoa está.
            app(\App\Services\Audit\AuditRecorder::class)->acto(
                'empresa.troca_recusada',
                null,
                ['tentou_entrar_em' => (int) $tenantId]
            );

            $this->dispatch('error', message: 'Você não tem permissão para acessar esta empresa!');
        }
    }
    
    public function render()
    {
        return view('livewire.tenant-switcher');
    }
}
