<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AgenteAutenticado;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/** Contexto efemero apenas para as rotas administrativas explicitamente publicadas. */
class ContextoAdministrativoDoAgente
{
    public function handle(Request $request, Closure $next)
    {
        $agent = app(AgenteAutenticado::class);
        $owner = User::find($agent->responsavelId());
        abort_unless($owner && $owner->is_super_admin && $owner->is_active, 403,
            'O responsavel tem de ser um superadmin activo.');

        if (! $request->isMethod('GET')) {
            $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:1000'],
                'confirmar' => ['required', 'accepted']]);
        }

        $guard = Auth::guard();
        $previous = $guard->user();
        $team = getPermissionsTeamId();
        $resolver = $request->getUserResolver();
        $status = 500;
        try {
            // Nao cria sessao, cookie ou acesso ao painel web.
            $guard->setUser($owner);
            $request->setUserResolver(fn () => $owner);
            $response = $next($request);
            $status = $response->getStatusCode();
            return $response;
        } finally {
            $previous ? $guard->setUser($previous) : $guard->forgetUser();
            $request->setUserResolver($resolver);
            setPermissionsTeamId($team);
            // Nunca guardar corpo, passwords ou conteudo de mensagens.
            Log::notice('agent.admin.request', ['agent_token_id' => $agent->id(),
                'owner_user_id' => $owner->id, 'method' => $request->method(),
                'path' => $request->path(), 'status' => $status,
                'idempotency_key' => $request->header('Idempotency-Key')]);
        }
    }
}
