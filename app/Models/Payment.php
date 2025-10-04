<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_payments';

    protected $fillable = [
        'invoice_id', 'tenant_id', 'user_id', 'payment_method', 'reference_number',
        'amount', 'payment_date', 'bank_name', 'bank_account', 'bank_iban',
        'proof_file', 'proof_original_name', 'notes', 'status',
        'verified_by', 'verified_at', 'rejection_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    // Relacionamentos
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
