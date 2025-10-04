<?php

namespace App\Models\Invoicing;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $table = 'invoicing_taxes';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'rate',
        'type',
        'saft_code',
        'saft_type',
        'exemption_reason',
        'is_default',
        'is_active',
        'include_in_price',
        'compound_tax',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'include_in_price' => 'boolean',
        'compound_tax' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'tax_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Helper methods
    public static function getDefaultTax($tenantId)
    {
        return static::forTenant($tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function getFormattedRateAttribute()
    {
        return $this->rate . '%';
    }

    public function getSaftTypeNameAttribute()
    {
        $types = [
            'NOR' => 'Normal',
            'RED' => 'Reduzida',
            'ISE' => 'Isento',
            'NS' => 'Não Sujeito',
            'OUT' => 'Outro',
        ];

        return $types[$this->saft_type] ?? '-';
    }
}
