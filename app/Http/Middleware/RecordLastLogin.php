<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordLastLogin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // Atualizar last_login_at apenas se passou mais de 5 minutos do último login
            // Isso evita updates desnecessários a cada request
            if (!$user->last_login_at || $user->last_login_at->diffInMinutes(now()) > 5) {
                $user->update([
                    'last_login_at' => now(),
                ]);
            }
        }

        return $next($request);
    }
}
