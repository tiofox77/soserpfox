<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Um aparelho que corre o PWA.
 *
 * Inventário de instalações, não vigilância: guarda-se o que responde a "esta
 * empresa usa o PWA e está actualizada?" e mais nada.
 */
class PwaDevice extends Model
{
    protected $table = 'pwa_devices';

    protected $fillable = [
        'tenant_id', 'device_uuid', 'user_id', 'app_version',
        'standalone', 'platform', 'user_agent',
        'syncs', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'standalone'    => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Um aparelho falou connosco: regista ou actualiza.
     *
     * NUNCA REBENTA O PEDIDO DE QUEM CHAMA. Isto corre dentro da
     * sincronização, e a sincronização é o que faz uma loja vender. Um erro a
     * gravar telemetria não pode impedir um catálogo de descer — por isso
     * apanha-se tudo e segue-se.
     */
    public static function visto(int $tenantId, array $dados): void
    {
        $uuid = trim((string) ($dados['device_uuid'] ?? ''));

        if ($uuid === '' || mb_strlen($uuid) > 64) {
            return;
        }

        try {
            $aparelho = static::firstOrNew([
                'tenant_id'   => $tenantId,
                'device_uuid' => $uuid,
            ]);

            $aparelho->fill([
                'user_id'     => $dados['user_id'] ?? $aparelho->user_id,
                'app_version' => mb_substr((string) ($dados['app_version'] ?? ''), 0, 40) ?: $aparelho->app_version,
                'standalone'  => (bool) ($dados['standalone'] ?? $aparelho->standalone),
                'platform'    => mb_substr((string) ($dados['platform'] ?? ''), 0, 60) ?: $aparelho->platform,
                'user_agent'  => mb_substr((string) ($dados['user_agent'] ?? ''), 0, 255) ?: $aparelho->user_agent,
            ]);

            $aparelho->first_seen_at = $aparelho->first_seen_at ?: now();
            $aparelho->last_seen_at = now();
            $aparelho->syncs = (int) $aparelho->syncs + 1;

            $aparelho->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Está desactualizado? Compara com a versão que o servidor serve agora.
     *
     * Um aparelho sem versão declarada conta como desactualizado: ou é anterior
     * a isto existir, ou não está a dizer — e nos dois casos não se sabe o que
     * ele corre, que é o mesmo problema.
     */
    public function desactualizado(string $versaoAtual): bool
    {
        return $this->app_version !== $versaoAtual;
    }

    /** Há quanto tempo não fala connosco. */
    public function adormecido(int $dias = 14): bool
    {
        return !$this->last_seen_at || $this->last_seen_at->lt(now()->subDays($dias));
    }
}
