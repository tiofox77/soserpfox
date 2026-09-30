<?php

namespace App\Console\Commands;

use App\Models\AgentToken;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Recebe apenas hash, por ficheiro privado colocado pelo operador via FTP. */
class ProvisionarAgente extends Command
{
    protected $signature = 'agente:provisionar';
    protected $description = 'Consome pedido privado de rotacao; nunca recebe nem devolve o segredo';

    public function handle(): int
    {
        $path = storage_path('app/agente/provisionar.json');
        if (!is_file($path) || is_link($path)) {
            $this->error('Pedido privado ausente. Nenhuma credencial criada.');
            return self::FAILURE;
        }
        $handle = fopen($path, 'rb');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) fclose($handle);
            $this->error('Pedido em curso.');
            return self::FAILURE;
        }
        try {
            $data = json_decode(stream_get_contents($handle), true, 32, JSON_THROW_ON_ERROR);
            validator($data, [
                'prefix' => ['required', 'regex:/^[a-f0-9]{12}$/'],
                'token_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
                'owner_email' => ['required', 'email'],
                'replace_prefix' => ['required', 'regex:/^[a-f0-9]{12}$/', 'different:prefix'],
                'issued_at' => ['required', 'date'],
                'days' => ['required', 'integer', 'min:1', 'max:365'],
                'scopes' => ['required', 'array', 'min:1'],
                'scopes.*' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(config('agent.escopos')))],
            ])->validate();
            $issued = \Carbon\Carbon::parse($data['issued_at']);
            if ($issued->lt(now()->subMinutes(30)) || $issued->gt(now()->addMinute())) {
                throw new \RuntimeException('Pedido fora da janela de 30 minutos.');
            }
            $token = DB::transaction(function () use ($data, $issued) {
                $owner = User::where('email', $data['owner_email'])->where('is_super_admin', true)
                    ->where('is_active', true)->firstOrFail();
                $old = AgentToken::where('prefix', $data['replace_prefix'])->lockForUpdate()->firstOrFail();
                $existing = AgentToken::where('prefix', $data['prefix'])->first();
                if ($existing) {
                    if (!hash_equals($existing->token_hash, $data['token_hash']) || $existing->owner_user_id !== $owner->id) {
                        throw new \RuntimeException('Prefixo ocupado por outra credencial.');
                    }
                    return $existing;
                }
                if ($old->revoked_at) throw new \RuntimeException('Credencial anterior ja revogada.');
                $ips = $old->allowed_ips ?? [];
                if (config('agent.token.exigir_ips', true) && !$ips) {
                    throw new \RuntimeException('Credencial anterior sem IP; configure uma lista antes de renovar.');
                }
                $token = AgentToken::create([
                    'name' => 'openclaw', 'prefix' => $data['prefix'], 'token_hash' => $data['token_hash'],
                    'owner_user_id' => $owner->id, 'scopes' => array_values(array_unique($data['scopes'])),
                    'allowed_ips' => $ips, 'expires_at' => $issued->copy()->addDays($data['days']),
                    'created_by' => $owner->id,
                ]);
                $old->update(['revoked_at' => now(), 'revoked_by' => $owner->id,
                    'revoked_reason' => 'Rotacao autorizada pelo responsavel; nova credencial '.$token->prefix]);
                return $token;
            });
            Log::notice('agent.token.rotated', ['prefix' => $token->prefix, 'owner_user_id' => $token->owner_user_id,
                'replaced_prefix' => $data['replace_prefix'], 'scopes' => $token->scopes,
                'expires_at' => $token->expires_at->toIso8601String()]);
            $this->info(json_encode(['prefix' => $token->prefix, 'owner' => $data['owner_email'],
                'expires_at' => $token->expires_at->toIso8601String(), 'scopes' => count($token->scopes),
                'allowed_ips' => $token->allowed_ips], JSON_UNESCAPED_SLASHES));
            // Fechar antes de apagar para suportar Windows; o prefixo garante idempotencia.
            flock($handle, LOCK_UN);
            fclose($handle);
            $handle = null;
            if (!unlink($path)) $this->warn('Remova o pedido privado por FTP.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Excecoes SQL podem conter bindings: nao devolver mensagens cruas.
            $this->error('Provisionamento recusado: valide responsavel superadmin activo, prefixo anterior, escopos, IPs e data do pedido.');
            return self::FAILURE;
        } finally {
            if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); }
        }
    }
}
