<?php

namespace App\Models\Copias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * De quanto em quanto tempo, quantas se guardam, e se vão cifradas.
 *
 * Uma por âmbito: `tenant_id` nulo é a plataforma. Sem linha, valem os valores
 * de config/copias.php (`AgendaDeCopia::para()` cria-a com eles).
 */
class AgendaDeCopia extends Model
{
    protected $table = 'agendas_de_copia';

    protected $guarded = ['id'];

    protected $casts = [
        'activa' => 'boolean',
        'cifrar' => 'boolean',
        'ultima_em' => 'datetime',
        'proxima_em' => 'datetime',
    ];

    protected $hidden = ['frase'];

    public static function para(?int $tenantId): self
    {
        $omissao = config($tenantId ? 'copias.empresa' : 'copias.plataforma');

        return self::firstOrCreate(['tenant_id' => $tenantId], [
            'activa' => $omissao['activa'],
            'intervalo_horas' => $omissao['intervalo_horas'],
            'manter_locais' => $omissao['manter_locais'],
            'proxima_em' => now(),
        ]);
    }

    public function fraseEmClaro(): ?string
    {
        if (! $this->frase) {
            return null;
        }

        try {
            return Crypt::decryptString($this->frase);
        } catch (\Throwable) {
            return null;
        }
    }

    public function guardarFrase(?string $frase): void
    {
        $this->frase = $frase ? Crypt::encryptString($frase) : null;
    }

    /** Está na hora? */
    public function devida(): bool
    {
        return $this->activa && ($this->proxima_em === null || $this->proxima_em->lte(now()));
    }

    public function marcarFeita(): void
    {
        $this->forceFill([
            'ultima_em' => now(),
            'proxima_em' => now()->addHours(max(1, (int) $this->intervalo_horas)),
        ])->save();
    }
}
