<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Um problema do sistema, não uma linha de log.
 *
 * Mil ocorrências do mesmo erro são UMA linha com o contador a mil. Ver a
 * migração 2026_08_20_090000 e o App\Services\Agent\RegistoDeErros.
 */
class ErroDoSistema extends Model
{
    protected $table = 'erros_do_sistema';

    protected $fillable = [
        'fingerprint', 'nivel', 'mensagem', 'ficheiro', 'linha', 'excepcao',
        'contexto', 'tenant_id', 'url', 'ocorrencias',
        'primeira_vez', 'ultima_vez', 'notificado_em', 'notificacoes',
        'resolvido_em', 'resolvido_por', 'nota',
    ];

    protected $casts = [
        'contexto'      => 'array',
        'primeira_vez'  => 'datetime',
        'ultima_vez'    => 'datetime',
        'notificado_em' => 'datetime',
        'resolvido_em'  => 'datetime',
    ];

    /** Por resolver — o que interessa a quem está a vigiar. */
    public function scopeAbertos($query)
    {
        return $query->whereNull('resolvido_em');
    }

    /** Voltou a acontecer depois de alguém o dar por fechado. */
    public function reabrir(): void
    {
        $this->forceFill([
            'resolvido_em'  => null,
            'resolvido_por' => null,
            'notificado_em' => null,   // volta a merecer aviso
        ])->save();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** O que sai para o agente externo. Nunca o modelo inteiro. */
    public function paraOAgente(): array
    {
        return [
            'id'           => $this->id,
            'fingerprint'  => $this->fingerprint,
            'nivel'        => $this->nivel,
            'mensagem'     => $this->mensagem,
            'excepcao'     => $this->excepcao,
            'onde'         => $this->ficheiro ? "{$this->ficheiro}:{$this->linha}" : null,
            'url'          => $this->url,
            'tenant_id'    => $this->tenant_id,
            'empresa'      => $this->tenant?->name,
            'ocorrencias'  => (int) $this->ocorrencias,
            'primeira_vez' => optional($this->primeira_vez)->toIso8601String(),
            'ultima_vez'   => optional($this->ultima_vez)->toIso8601String(),
            'resolvido'    => $this->resolvido_em !== null,
            'contexto'     => $this->contexto,
        ];
    }
}
