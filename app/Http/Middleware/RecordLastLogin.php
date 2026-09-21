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
        // Durante a personificação quem navega é o admin OU o revendedor: gravar
        // a entrada na pessoa dizia na lista de empresas que ela esteve cá — e
        // não esteve. Só a chave do admin deixava o revendedor contar como uso.
        if (auth()->check() && ! app(\App\Services\Plataforma\Personificacao::class)->activa()) {
            $user = auth()->user();

            /*
             * SÓ AS PESSOAS DAS EMPRESAS.
             *
             * No portal do cliente o `auth:client` põe o guard `client` como o
             * do pedido, e `auth()->user()` devolve um Client — que não tem
             * empresa activa. O `activeTenantId()` lá abaixo não existe nele e
             * TODAS as páginas do portal davam erro 500 cinco minutos depois de
             * o cliente entrar (o intervalo que trava este registo). O
             * CheckSubscription tem esta mesma guarda, pela mesma razão.
             */
            if (! $user instanceof \App\Models\User) {
                return $next($request);
            }

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
