<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    /**
     * Mostrar página de aceitar convite
     */
    public function show($token)
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();
        
        // Verificar se o convite já expirou
        if ($invitation->isExpired()) {
            $invitation->markAsExpired();
            return view('invitation.expired', compact('invitation'));
        }
        
        // Verificar se já foi aceito
        if ($invitation->status === 'accepted') {
            return view('invitation.already-accepted', compact('invitation'));
        }
        
        // Verificar se foi cancelado
        if ($invitation->status === 'cancelled') {
            return view('invitation.cancelled', compact('invitation'));
        }
        
        return view('invitation.accept', compact('invitation'));
    }
    
    /**
     * Processar aceitação do convite
     */
    public function accept(Request $request, $token)
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();
        
        // Validar
        if ($invitation->isExpired()) {
            return redirect()->route('invitation.accept', $token)
                ->with('error', 'Este convite expirou.');
        }
        
        if ($invitation->status !== 'pending') {
            return redirect()->route('invitation.accept', $token)
                ->with('error', 'Este convite não está mais disponível.');
        }
        
        // Validar dados
        $validated = $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);
        
        DB::beginTransaction();
        
        try {
            // Criar usuário
            $user = User::create([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
                'tenant_id' => $invitation->tenant_id,
                'is_active' => true,
                'is_super_admin' => false,
            ]);
            
            // Vincular ao tenant
            $user->tenants()->attach($invitation->tenant_id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
            
            // Atribuir role se especificada
            if ($invitation->role) {
                setPermissionsTeamId($invitation->tenant_id);
                
                $role = \Spatie\Permission\Models\Role::firstOrCreate(
                    ['name' => $invitation->role, 'guard_name' => 'web'],
                    ['tenant_id' => $invitation->tenant_id]
                );
                
                $user->assignRole($role);
            }
            
            // Marcar convite como aceito
            $invitation->markAsAccepted($user->id);
            
            DB::commit();
            
            // Login automático
            Auth::login($user);
            
            return redirect()->route('home')
                ->with('success', 'Bem-vindo(a)! Sua conta foi criada com sucesso.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Erro ao aceitar convite', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitation->id
            ]);
            
            return redirect()->route('invitation.accept', $token)
                ->with('error', 'Erro ao criar sua conta. Tente novamente.');
        }
    }
}
