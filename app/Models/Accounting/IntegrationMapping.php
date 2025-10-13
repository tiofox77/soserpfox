<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationMapping extends Model
{
    use HasFactory;

    protected $table = 'accounting_integration_mappings';

    protected $fillable = [
        'tenant_id',
        'event',
        'journal_id',
        'debit_account_id',
        'credit_account_id',
        'vat_account_id',
        'conditions',
        'auto_post',
        'active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'auto_post' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Relação com tenant
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Relação com diário
     */
    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Relação com conta de débito
     */
    public function debitAccount()
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    /**
     * Relação com conta de crédito
     */
    public function creditAccount()
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    /**
     * Relação com conta de IVA
     */
    public function vatAccount()
    {
        return $this->belongsTo(Account::class, 'vat_account_id');
    }
}
