<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticação por token (Bearer) para a app móvel de Faturação.
 * Token leve próprio (sem Sanctum) — ver ResolveApiToken middleware.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:60',
        ]);

        /*
         * TENTATIVAS CONTADAS. O /login do site limita a cinco por minuto; esta
         * porta paralela não limitava nada — adivinhava-se a senha de qualquer
         * email sem travão (auditoria de segurança de 2026-09-13).
         */
        $chave = 'api-login:' . mb_strtolower($data['email']) . '|' . $request->ip();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($chave, 5)) {
            return response()->json([
                'message' => __('Demasiadas tentativas. Tente de novo dentro de :s segundos.', ['s' => \Illuminate\Support\Facades\RateLimiter::availableIn($chave)]),
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password) || !$user->is_active) {
            \Illuminate\Support\Facades\RateLimiter::hit($chave, 60);

            return response()->json(['message' => 'Credenciais inválidas.'], 422);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($chave);

        // Tenant ativo (default do utilizador / 1º que pertence)
        $tenant = method_exists($user, 'activeTenant') ? $user->activeTenant() : null;

        // Gerar token em claro (devolvido 1x) e guardar o hash
        $plain = bin2hex(random_bytes(32));
        ApiToken::create([
            'user_id' => $user->id,
            'name' => $data['device_name'] ?? 'mobile',
            'token' => ApiToken::hashToken($plain),
        ]);

        return response()->json([
            'token' => $plain,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name,
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => activeTenantId(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            ApiToken::where('token', ApiToken::hashToken($bearer))->delete();
        }
        return response()->json(['ok' => true]);
    }
}
