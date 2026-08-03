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

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 422);
        }

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
