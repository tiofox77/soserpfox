<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordLastLogin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Durante a personificação quem navega é o admin: gravar a entrada na
        // pessoa dizia na lista de empresas que ela esteve cá — e não esteve.
        if (auth()->check() && ! session()->has(\App\Services\Plataforma\Personificacao::CHAVE_DO_ADMIN)) {
            $user = auth()->user();

            // Atualizar last_login_at apenas se passou mais de 5 minutos do último login
            // Isso evita updates desnecessários a cada request
            if (!$user->last_login_at || $user->last_login_at->diffInMinutes(now()) > 5) {
                $user->update([
                    'last_login_at' => now(),
                ]);

                $this->registarNaEmpresa($user);
            }
        }

        return $next($request);
    }

    /**
     * O acesso também fica na EMPRESA em que a pessoa está.
     *
     * O `last_login_at` é um só para todas as empresas da pessoa: o dono de
     * três empresas, a trabalhar numa, fazia as outras duas parecerem vivas na
     * lista da plataforma. Vai à boleia do mesmo intervalo de cinco minutos —
     * não custa um pedido a mais — e quem troca de empresa fica registado na
     * nova, no máximo, cinco minutos depois.
     */
    private function registarNaEmpresa($user): void
    {
        try {
            $empresa = $user->activeTenantId();

            if ($empresa) {
                DB::table('tenant_user')
                    ->where('tenant_id', $empresa)
                    ->where('user_id', $user->id)
                    ->update(['ultimo_acesso_em' => now()]);
            }
        } catch (QueryException) {
            // Só no intervalo entre subir os ficheiros e correr a migração:
            // o registo de uma entrada nunca pode deitar uma página abaixo.
        }
    }
}
