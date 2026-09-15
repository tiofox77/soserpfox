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
     * A página do convite — o ecrã `entrada/convite`, com o estado decidido aqui.
     *
     * Um convite que já tinha sido marcado como expirado (na primeira visita)
     * voltava a mostrar o formulário na segunda: o `isExpired()` só olha aos
     * pendentes, e o estado `expired` não era nenhum dos três casos. Quem
     * escolhia a senha levava «não está mais disponível» — sem saber porquê.
     */
    public function show($token)
    {
        $invitation = UserInvitation::with(['tenant', 'invitedBy'])->where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->markAsExpired();
        }

        $estado = match (true) {
            $invitation->status === 'expired' => 'expirado',
            $invitation->status === 'accepted' => 'aceite',
            $invitation->status === 'cancelled' => 'cancelado',
            default => 'pendente',
        };

        $titulo = [
            'pendente' => 'Aceitar Convite',
            'expirado' => 'Convite Expirado',
            'aceite' => 'Convite Já Aceito',
            'cancelado' => 'Convite Cancelado',
        ][$estado];

        return \App\Support\EcraReact::solta('entrada/convite', $titulo, \App\Support\Entrada::comum() + [
            'estado' => $estado,
            'login' => route('login'),
            'acao' => route('invitation.accept.post', $invitation->token),
            'convite' => [
                'nome' => $invitation->name,
                'email' => $invitation->email,
                'empresa' => $invitation->tenant?->name,
                'convidou' => $invitation->invitedBy?->name,
                'funcao' => $invitation->role,
                'expira' => $invitation->expires_at?->diffForHumans(),
                'expirou_em' => $invitation->expires_at?->format('d/m/Y'),
                'aceite_em' => $invitation->accepted_at?->format('d/m/Y'),
            ],
        ])();
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
            'password' => ['required', 'confirmed', \App\Support\Seguranca\RegraDaSenha::regra()],
        ]);
        
        DB::beginTransaction();

        try {
            // O limite de utilizadores TEM de ser verificado AQUI, e não só ao
            // emitir o convite: N convites emitidos com uma vaga livre eram N
            // pessoas a entrar. Este é o último ponto antes de a conta existir.
            //
            // lockForUpdate para dois convites aceites ao mesmo segundo não
            // passarem ambos pela mesma vaga.
            $tenant = \App\Models\Tenant::whereKey($invitation->tenant_id)->lockForUpdate()->first();

            if ($tenant && !$tenant->cabeMaisUmUtilizador()) {
                DB::rollBack();

                return redirect()->route('login')->with('error',
                    'A empresa atingiu o limite de utilizadores do seu plano ('
                    . $tenant->limiteDeUtilizadores() . '). Peça ao administrador para '
                    . 'libertar uma conta ou aumentar o plano.');
            }

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
            
            // O PAPEL É O DA EMPRESA DO CONVITE.
            //
            // Procurava-se pelo nome em todas as empresas (`firstOrCreate` sem
            // `tenant_id` na procura): um convite para «Caixa» podia apanhar o
            // papel «Caixa» de OUTRA empresa, com as permissões que lá lhe
            // deram. Primeiro o papel escolhido (role_id), depois o nome — e
            // sempre dentro da empresa.
            if ($invitation->role_id || $invitation->role) {
                setPermissionsTeamId($invitation->tenant_id);

                $role = $invitation->role_id
                    ? \Spatie\Permission\Models\Role::whereKey($invitation->role_id)->where('tenant_id', $invitation->tenant_id)->first()
                    : null;

                if (! $role && $invitation->role) {
                    $role = \Spatie\Permission\Models\Role::where('name', $invitation->role)
                        ->where('guard_name', 'web')
                        ->where('tenant_id', $invitation->tenant_id)
                        ->first()
                        ?? \Spatie\Permission\Models\Role::create([
                            'name' => $invitation->role,
                            'guard_name' => 'web',
                            'tenant_id' => $invitation->tenant_id,
                        ]);
                }

                if ($role) {
                    $user->assignRole($role);
                }
            }
            
            // Marcar convite como aceito
            $invitation->markAsAccepted($user->id);
            
            DB::commit();

            // Aceitar o convite é aceitar os Termos e ter visto a Política —
            // o ecrã di-lo junto ao botão; fica a prova (RGPD art. 7.º).
            foreach (['termos', 'privacidade'] as $tipo) {
                \App\Services\Privacidade\Consentimentos::registar($tipo, true, 'convite', $user, null, $request);
            }

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
