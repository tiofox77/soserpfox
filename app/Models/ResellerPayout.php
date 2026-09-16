<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** UM PAGAMENTO DE COMISSÕES a um revendedor (RV-12). */
class ResellerPayout extends Model
{
    public const METODOS = [
        'transferencia' => 'Transferência bancária',
        'multicaixa' => 'Multicaixa Express',
        'numerario' => 'Numerário',
        'credito' => 'Crédito na subscrição',
        'outro' => 'Outro',
    ];

    protected $fillable = ['reseller_id', 'amount', 'method', 'reference', 'paid_at', 'notes', 'user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function revendedor(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(ResellerCommission::class, 'payout_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
