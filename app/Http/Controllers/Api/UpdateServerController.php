<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use App\Models\Tenant;
use App\Services\Licensing\LicenseVerifier;
use Illuminate\Http\Request;

/**
 * SERVIDOR de atualizações (cloud): responde a "há update para mim?".
 *
 * Confia no token pela ASSINATURA para saber o tenant, e decide a versão-alvo
 * pelo ROLLOUT que o super admin definiu: `rollout='all'` na versão, ou um
 * registo em app_update_targets para aquele tenant (canary). Devolve o
 * manifesto ASSINADO — o cliente volta a verificá-lo antes de aplicar.
 */
class UpdateServerController extends Controller
{
    public function check(Request $request)
    {
        $dados = $request->validate([
            'token'        => 'required|string',
            'versao_atual' => 'nullable|string|max:40',
        ]);

        $cfg = config('licensing', []);
        $pub = $cfg['public_key'] ?? '';
        if (!$pub) {
            return response()->json(['erro' => 'servidor_sem_chave_publica'], 500);
        }

        $payload = (new LicenseVerifier($pub, $cfg))->payloadAssinado($dados['token']);
        if (!$payload || !$payload->tenantId()) {
            return response()->json(['erro' => 'assinatura_invalida'], 403);
        }

        $tenant = Tenant::find($payload->tenantId());
        if (!$tenant || !$tenant->is_active) {
            // Suspenso não recebe updates — resolve-se a subscrição primeiro.
            return response()->json(['atualizado' => true]);
        }

        $atual = $dados['versao_atual'] ?? '0.0.0';

        // Versões abertas a este tenant: rollout global 'all', OU alvo explícito.
        $alvos = AppUpdateTarget::where('tenant_id', $tenant->id)->pluck('versao')->all();

        $candidata = AppUpdate::query()
            ->where(fn ($q) => $q->where('rollout', AppUpdate::ROLLOUT_ALL)
                ->orWhereIn('versao', $alvos))
            ->get()
            // mais recente que a instalada
            ->filter(fn (AppUpdate $u) => version_compare($u->versao, $atual, '>'))
            // não saltar abaixo da versão mínima exigida pela candidata
            ->filter(fn (AppUpdate $u) => !$u->min_versao || version_compare($atual, $u->min_versao, '>='))
            ->sortBy('versao', SORT_NATURAL)
            ->last();

        if (!$candidata) {
            return response()->json(['atualizado' => true]);
        }

        return response()->json([
            'atualizado'  => false,
            'versao'      => $candidata->versao,
            'obrigatorio' => $candidata->obrigatorio,
            'notas'       => $candidata->notas,
            'manifesto'   => $candidata->manifesto,   // assinado; o cliente reverifica
        ]);
    }
}
