<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma instalação on-premise conhecida (ver a migração para o porquê).
 * É a fonte da lista de "clientes offline" no painel.
 */
class LicencaEmitida extends Model
{
    protected $table = 'licencas_emitidas';

    protected $fillable = [
        'tenant_id', 'license_request_id', 'fingerprint', 'plano', 'modulos',
        'max_users', 'token', 'emitida_em', 'expira_em', 'ultimo_checkin',
        'versao_instalada', 'ultimo_ip',
    ];

    protected $casts = [
        'modulos'        => 'array',
        'emitida_em'     => 'datetime',
        'expira_em'      => 'datetime',
        'ultimo_checkin' => 'datetime',
        'max_users'      => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pedido()
    {
        return $this->belongsTo(LicenseRequest::class, 'license_request_id');
    }

    /** Dias desde o último contacto. null = nunca ligou. */
    public function diasSemContacto(): ?int
    {
        return $this->ultimo_checkin ? (int) $this->ultimo_checkin->diffInDays(now()) : null;
    }

    /**
     * Como está esta instalação, do ponto de vista de quem vende.
     * Só olha para o que o SERVIDOR sabe — o veredicto verdadeiro é o que a
     * própria máquina calcula com a licença à frente.
     */
    public function situacao(): string
    {
        if ($this->expira_em && $this->expira_em->isPast()) {
            return 'expirada';
        }
        if (!$this->ultimo_checkin) {
            return 'nunca_ligou';
        }
        if ($this->diasSemContacto() >= 15) {
            return 'silenciosa';
        }

        return 'activa';
    }
}
