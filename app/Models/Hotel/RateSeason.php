<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;
use Carbon\Carbon;

class RateSeason extends Model
{
    protected $table = 'hotel_rate_seasons';

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'start_date',
        'end_date',
        'price_modifier',
        'modifier_type',
        'priority',
        'is_active',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price_modifier' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verifica se uma data está dentro desta temporada
     */
    public function containsDate($date): bool
    {
        $date = Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Aplica o modificador a um preço base
     */
    public function applyModifier(float $basePrice): float
    {
        return match($this->modifier_type) {
            'multiplier' => $basePrice * $this->price_modifier,
            'percentage' => $basePrice * (1 + ($this->price_modifier / 100)),
            'fixed' => $this->price_modifier,
            default => $basePrice,
        };
    }

    /**
     * Retorna a temporada ativa para uma data específica
     */
    public static function getForDate($tenantId, $date)
    {
        return self::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Retorna o percentual de modificação formatado
     */
    public function getModifierPercentageAttribute(): string
    {
        if ($this->modifier_type === 'multiplier') {
            $percent = ($this->price_modifier - 1) * 100;
            return ($percent >= 0 ? '+' : '') . number_format($percent, 0) . '%';
        }
        if ($this->modifier_type === 'percentage') {
            return ($this->price_modifier >= 0 ? '+' : '') . number_format($this->price_modifier, 0) . '%';
        }
        return number_format($this->price_modifier, 0, ',', '.') . ' Kz';
    }
}
