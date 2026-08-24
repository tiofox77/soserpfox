<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Um pedido de licença de uma instalação on-premise. Ver a migração para o
 * porquê de cada campo.
 */
class LicenseRequest extends Model
{
    protected $table = 'license_requests';

    protected $fillable = [
        'codigo', 'empresa', 'nif', 'email', 'telefone', 'responsavel',
        'utilizadores', 'fingerprint', 'versao', 'observacoes',
        'estado', 'tenant_id', 'licenca', 'motivo_recusa',
        'aprovado_em', 'entregue_em', 'ip',
    ];

    protected $casts = [
        'aprovado_em'  => 'datetime',
        'entregue_em'  => 'datetime',
        'utilizadores' => 'integer',
    ];

    public const PENDENTE = 'pendente';
    public const APROVADO = 'aprovado';
    public const RECUSADO = 'recusado';

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Código legível, fácil de ditar ao telefone. */
    public static function gerarCodigo(): string
    {
        // Sem I/O/0/1 para não se confundirem quando alguém lê em voz alta.
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 12; $i++) {
            $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            if ($i === 3 || $i === 7) {
                $c .= '-';
            }
        }

        return $c;
    }
}
