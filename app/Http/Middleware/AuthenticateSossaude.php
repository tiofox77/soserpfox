<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticateSossaude
{
    public function handle(Request $request, Closure $next)
    {
        // Always return JSON, including validation failures, without a web session.
        $request->headers->set('Accept', 'application/json');
        $token = $request->bearerToken();
        $credential = $token ? DB::table('sossaude_credentials')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first() : null;
        if (!$credential) {
            return response()->json(['error' => 'invalid_credentials'], 401);
        }
        if ($request->header('X-Bridge-Version') !== '1') {
            return response()->json(['error' => 'unsupported_contract_version'], 422);
        }
        if ($request->header('X-ERP-Tenant') !== (string) $credential->tenant_id) {
            return response()->json(['error' => 'tenant_mismatch'], 403);
        }
        $active = DB::table('tenants')->where('id', $credential->tenant_id)
            ->whereNull('deleted_at')->where('is_active', true)->exists();
        if (!$active) {
            return response()->json(['error' => 'tenant_unavailable'], 403);
        }
        $request->attributes->set('sossaude_credential', $credential);
        DB::table('sossaude_credentials')->where('id', $credential->id)->update(['last_used_at' => now()]);

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
