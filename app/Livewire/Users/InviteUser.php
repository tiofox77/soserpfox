<?php

namespace App\Livewire\Users;

use Livewire\Component;
use App\Models\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Ligado a uma rota, portanto e uma pagina inteira: sem isto o Livewire
// procura o layout components.layouts.app, que este projecto nao tem, e
// a pagina rebenta com 500 assim que o modulo estiver ligado.
#[\Livewire\Attributes\Layout('layouts.app')]
class InviteUser extends Component
{
    public $name = '';
    public $email = '';
    public $role = '';
    public $showModal = false;
    public $sending = false;
    
    public $invitations = [];
    
    protected $rules = [
        'name' => 'required|min:3',
        'email' => 'required|email',
        'role' => 'nullable|string',
    ];
    
    public function mount()
    {
        $this->loadInvitations();
    }
    
    public function loadInvitations()
    {
        $this->invitations = UserInvitation::with(['invitedBy', 'user'])
            ->forTenant(activeTenantId())
            ->latest()
            ->get();
    }
    
    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function sendInvitation()
    {
        $this->validate();
        
        $this->sending = true;
        
        try {
            // Verificar se o email já está em uso
            $existingUser = User::where('email', $this->email)
                ->where('tenant_id', activeTenantId())
                ->first();
                
            if ($existingUser) {
                session()->flash('error', 'Este email já está em uso por um usuário existente.');
                $this->dispatch('error', message: 'Este email já está em uso.');
                $this->sending = false;
                return;
            }
            
            // Verificar se já existe um convite pendente
            $pendingInvitation = UserInvitation::where('email', $this->email)
                ->where('tenant_id', activeTenantId())
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();
                
            if ($pendingInvitation) {
                session()->flash('error', 'Já existe um convite pendente para este email.');
                $this->dispatch('error', message: 'Convite pendente já existe.');
                $this->sending = false;
                return;
            }
            
            DB::beginTransaction();
            
            // Criar convite
            $invitation = UserInvitation::create([
                'tenant_id' => activeTenantId(),
                'invited_by' => auth()->id(),
                'email' => $this->email,
                'name' => $this->name,
                'role' => $this->role,
            ]);
            
            // Enviar email
            $invitation->sendInvitationEmail();
            
            DB::commit();
            
            session()->flash('success', "Convite enviado com sucesso para {$this->email}!");
            $this->dispatch('success', message: 'Convite enviado com sucesso!');
            
            $this->loadInvitations();
            $this->closeModal();
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao enviar convite', [
                'error' => $e->getMessage(),
                'email' => $this->email
            ]);
            
            session()->flash('error', 'Erro ao enviar convite: ' . $e->getMessage());
            $this->dispatch('error', message: 'Erro ao enviar convite.');
        }
        
        $this->sending = false;
    }
    
    public function cancelInvitation($invitationId)
    {
        try {
            $invitation = UserInvitation::findOrFail($invitationId);
            
            // Verificar se pertence ao tenant
            if ((int) $invitation->tenant_id !== (int) activeTenantId()) {
                session()->flash('error', 'Convite não encontrado.');
                return;
            }
            
            $invitation->markAsCancelled();
            
            session()->flash('success', 'Convite cancelado com sucesso!');
            $this->dispatch('success', message: 'Convite cancelado!');
            
            $this->loadInvitations();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cancelar convite.');
        }
    }
    
    public function resendInvitation($invitationId)
    {
        try {
            $invitation = UserInvitation::findOrFail($invitationId);
            
            // Verificar se pertence ao tenant
            if ((int) $invitation->tenant_id !== (int) activeTenantId()) {
                session()->flash('error', 'Convite não encontrado.');
                return;
            }
            
            // Atualizar data de expiração
            $invitation->update([
                'expires_at' => now()->addDays(7),
                'status' => 'pending',
            ]);
            
            // Reenviar email
            $invitation->sendInvitationEmail();
            
            session()->flash('success', 'Convite reenviado com sucesso!');
            $this->dispatch('success', message: 'Convite reenviado!');
            
            $this->loadInvitations();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao reenviar convite.');
        }
    }
    
    protected function resetForm()
    {
        $this->name = '';
        $this->email = '';
        $this->role = '';
        $this->resetErrorBag();
    }
    
    public function render()
    {
        return view('livewire.users.invite-user');
    }
}
