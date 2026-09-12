<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;

class MoveLine extends Model
{
    use BelongsToTenant;

    protected $table = 'accounting_move_lines';
    
    protected $fillable = [
        'tenant_id',
        'move_id',
        'account_id',
        'name',
        'partner_id',
        'debit',
        'credit',
        'balance',
        'tax_id',
        'tax_amount',
        'document_ref',
        'narration',
    ];
    
    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];
    
    // Relações
    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * A LINHA DO EXTRACTO que já foi conciliada com esta.
     *
     * ESTA RELAÇÃO NÃO EXISTIA, e o serviço da reconciliação bancária
     * procurava-a: `whereDoesntHave('bankReconciliationItem')` no
     * `findMatchingSuggestions()`. Sem ela, o Eloquent atira «Call to undefined
     * relationship» — e como o `importStatementFile()` chama o auto-match no fim,
     * IMPORTAR UM EXTRACTO FALHAVA SEMPRE, desde o primeiro dia. O ecrã
     * apanhava a excepção e mostrava-a como «Erro ao importar».
     *
     * É por ela que uma linha de lançamento já conciliada deixa de aparecer nas
     * sugestões: cada uma casa com uma linha do extracto, não com duas.
     */
    public function bankReconciliationItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BankReconciliationItem::class, 'move_line_id');
    }
}
