<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Seguranca\TravaoDeEntradas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticação por token (Bearer) para a app móvel de Faturação.
 * Token leve próprio (sem Sanctum) — ver ResolveApiToken middleware.
 */
class AuthController extends Controller
{
    /** bcrypt de uma senha que ninguém tem — só para gastar o mesmo tempo. */
    private const HASH_DE_DISFARCE = '$2y$12$4XPcdx2eom2T4V3tjNvkiu/y8h7uLd6.zIfU1loYIHXRPtaSzoPTe';

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:60',
        ]);

        /*
         * TENTATIVAS CONTADAS, com a regra do site: 5 falhas e a porta fecha
         * 10 minutos; 20 falhas do mesmo IP em quaisquer emails, também
         * (App\Support\Seguranca\TravaoDeEntradas). Auditorias de 2026-09-13 e
         * 2026-09-15.
         */
        $travao = TravaoDeEntradas::para('api');

        if ($segundos = $travao->bloqueadoPor($data['email'], $request->ip())) {
            return response()->json([
                'message' => TravaoDeEntradas::mensagemDeBloqueio($segundos),
                'bloqueado_segundos' => $segundos,
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        // O MESMO TRABALHO exista ou não a conta: sem o Hash::check a um hash
        // qualquer, um email inexistente respondia em milissegundos e um
        // existente demorava o bcrypt — o relógio dizia que contas há.
        $senhaCerta = Hash::check($data['password'], $user?->password ?? self::HASH_DE_DISFARCE);

        if (!$user || !$senhaCerta || !$user->is_active) {
            $restam = $travao->falhou($data['email'], $request->ip());

            return response()->json([
                'message' => TravaoDeEntradas::mensagemDeFalha($restam, __('Credenciais inválidas.')),
            ], $restam === 0 ? 429 : 422);
        }

        $travao->entrou($data['email'], $request->ip());

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
