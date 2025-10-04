<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advance extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'invoicing_advances';

    protected $fillable = [
        'tenant_id',
        'advance_number',
        'type',
        'client_id',
        'supplier_id',
        'payment_date',
        'amount',
        'payment_method',
        'purpose',
        'notes',
        'used_amount',
        'remaining_amount',
        'status',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AdvanceUsage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
                     ->whereColumn('amount_remaining', '>', 0);
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($advance) {
            if (empty($advance->advance_number)) {
                $advance->advance_number = self::generateAdvanceNumber();
            }
            
            if (empty($advance->remaining_amount)) {
                $advance->remaining_amount = $advance->amount;
            }
            
            if (empty($advance->used_amount)) {
                $advance->used_amount = 0;
            }
        });
    }

    // Gerar número de adiantamento
    public static function generateAdvanceNumber()
    {
        $year = date('Y');
        
        $lastAdvance = self::where('tenant_id', activeTenantId())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastAdvance ? ((int) substr($lastAdvance->advance_number, -4)) + 1 : 1;

        return sprintf('AD/%s/%04d', $year, $nextNumber);
    }

    // Usar adiantamento
    public function use($amount, $invoiceId = null)
    {
        if ($amount > $this->remaining_amount) {
            throw new \Exception('Valor superior ao saldo disponível do adiantamento.');
        }

        $this->used_amount += $amount;
        $this->remaining_amount -= $amount;

        if ($this->remaining_amount <= 0) {
            $this->status = 'fully_used';
        }

        $this->save();

        // Registrar uso
        AdvanceUsage::create([
            'advance_id' => $this->id,
            'invoice_id' => $invoiceId,
            'amount_used' => $amount,
            'used_date' => now(),
        ]);

        return true;
    }

    // Reverter uso
    public function reverseUsage($usageId)
    {
        $usage = $this->usages()->findOrFail($usageId);

        $this->used_amount -= $usage->amount_used;
        $this->remaining_amount += $usage->amount_used;
        $this->status = 'available';
        $this->save();

        $usage->delete();

        return true;
    }

    // Cancelar adiantamento
    public function cancel()
    {
        if ($this->used_amount > 0) {
            throw new \Exception('Não é possível cancelar adiantamento já utilizado.');
        }

        $this->status = 'cancelled';
        $this->save();

        return true;
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'available' => 'Disponível',
            'partially_used' => 'Parcialmente Usado',
            'fully_used' => 'Totalmente Usado',
            'refunded' => 'Reembolsado',
            'cancelled' => 'Cancelado',
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'available' => 'green',
            'partially_used' => 'yellow',
            'fully_used' => 'gray',
            'refunded' => 'blue',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getPaymentMethodLabelAttribute()
    {
        $methods = [
            'cash' => 'Dinheiro',
            'transfer' => 'Transferência',
            'multicaixa' => 'Multicaixa',
            'tpa' => 'TPA',
            'check' => 'Cheque',
            'mbway' => 'MB Way',
            'other' => 'Outro',
        ];

        return $methods[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}
