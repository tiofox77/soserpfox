<?php

namespace App\Models\Compras;

use App\Models\Invoicing\Receipt;
use App\Models\Supplier;
use App\Models\Treasury\Transaction;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * O PEDIDO DE PAGAMENTO À TESOURARIA (PP-AAAA-NNNNNN).
 *
 * Quem compra pede; o tesoureiro paga. O pedido guarda o valor, o prazo e a
 * forma sugerida; o pagamento guarda quem pagou, quando, por onde, e o rasto
 * do dinheiro — o recibo de compra e o movimento de tesouraria.
 *
 * Documento interno, não fiscal: sem série da AGT.
 */
class PedidoDePagamento extends Model
{
    use BelongsToTenant;

    protected $table = 'compras_pagamentos';

    protected $fillable = [
        'tenant_id', 'numero', 'encomenda_id', 'supplier_id', 'valor', 'forma_sugerida', 'data_limite', 'notas',
        'estado', 'ronda', 'pedido_por', 'tesoureiro_id', 'motivo_recusa', 'decidido_por', 'decidido_em',
        'pago_por', 'pago_em', 'forma_paga', 'referencia', 'receipt_id', 'transaction_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_limite' => 'date',
        'decidido_em' => 'datetime',
        'pago_em' => 'datetime',
    ];

    public const ESTADOS = [
        'em_aprovacao' => 'À espera de aprovação',
        'por_pagar' => 'Por pagar',
        'pago' => 'Pago',
        'recusado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];

    /** Os que ainda contam para o que a encomenda tem pedido. */
    public const ACTIVOS = ['em_aprovacao', 'por_pagar', 'pago'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $p) {
            if (empty($p->numero)) {
                $p->numero = static::gerarNumero((int) $p->tenant_id);
            }
        });
    }

    public static function gerarNumero(int $tenantId): string
    {
        $prefixo = 'PP-'.now()->year.'-';

        $ultimo = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('numero', 'like', $prefixo.'%')
            ->orderByDesc('id')
            ->value('numero');

        $seguinte = $ultimo ? ((int) str_replace($prefixo, '', $ultimo)) + 1 : 1;

        return $prefixo.str_pad((string) $seguinte, 6, '0', STR_PAD_LEFT);
    }

    public function encomenda()
    {
        return $this->belongsTo(Encomenda::class, 'encomenda_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function pedidoPor()
    {
        return $this->belongsTo(User::class, 'pedido_por');
    }

    public function tesoureiro()
    {
        return $this->belongsTo(User::class, 'tesoureiro_id');
    }

    public function pagoPor()
    {
        return $this->belongsTo(User::class, 'pago_por');
    }

    public function decididoPor()
    {
        return $this->belongsTo(User::class, 'decidido_por');
    }

    public function recibo()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    public function movimento()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function aprovacoes()
    {
        return $this->hasMany(Aprovacao::class, 'aprovavel_id')
            ->where('aprovavel_type', self::class)
            ->orderBy('id');
    }

    public function estadoRotulo(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
