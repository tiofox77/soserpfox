<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IRTTaxBracket extends Model
{
    use HasFactory;

    protected $table = 'irt_tax_brackets';

    protected $fillable = [
        'tenant_id',
        'bracket_number',
        'min_income',
        'max_income',
        'fixed_amount',
        'tax_rate',
        'is_active',
    ];

    protected $casts = [
        'min_income' => 'decimal:2',
        'max_income' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the matching bracket for a given taxable income
     * Returns the highest bracket where min_income <= $income
     */
    public static function getBracketForIncome(float $income, ?int $tenantId = null): ?self
    {
        if (!$tenantId && auth()->check()) {
            $user = auth()->user();
            $tenantId = method_exists($user, 'activeTenantId') ? $user->activeTenantId() : ($user->tenant_id ?? null);
        }

        if (!$tenantId) return null;

        return self::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('min_income', '<=', $income)
            ->where(function ($q) use ($income) {
                $q->where('max_income', '>=', $income)
                  ->orWhereNull('max_income');
            })
            ->orderBy('min_income', 'desc')
            ->first();
    }

    /**
     * Calculate IRT tax for a given taxable income
     * Formula: IRT = PF + (taxa × excesso)
     */
    public static function calculateIRT(float $taxableIncome, ?int $tenantId = null): float
    {
        if ($taxableIncome <= 0) return 0;

        $bracket = self::getBracketForIncome($taxableIncome, $tenantId);

        if (!$bracket) return 0;

        $excess = max(0, $taxableIncome - $bracket->min_income);
        $irt = $bracket->fixed_amount + ($bracket->tax_rate * $excess);

        return round($irt, 2);
    }

    /**
     * Get all active brackets for display
     */
    public static function getAllBrackets(?int $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        if (!$tenantId && auth()->check()) {
            $user = auth()->user();
            $tenantId = method_exists($user, 'activeTenantId') ? $user->activeTenantId() : ($user->tenant_id ?? null);
        }

        return self::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('bracket_number')
            ->get();
    }
}
