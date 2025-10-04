<?php

namespace App\Models\Invoicing;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicingSeries extends Model
{
    use HasFactory;

    protected $table = 'invoicing_series';

    protected $fillable = [
        'tenant_id',
        'document_type',
        'series_code',
        'name',
        'prefix',
        'include_year',
        'next_number',
        'number_padding',
        'is_default',
        'is_active',
        'current_year',
        'reset_yearly',
        'description',
    ];

    protected $casts = [
        'include_year' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'reset_yearly' => 'boolean',
        'next_number' => 'integer',
        'number_padding' => 'integer',
        'current_year' => 'integer',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Get next document number
    public function getNextNumber()
    {
        $currentYear = now()->year;
        
        // Verificar se precisa resetar numeração
        if ($this->reset_yearly && $this->current_year != $currentYear) {
            $this->update([
                'next_number' => 1,
                'current_year' => $currentYear,
            ]);
        }
        
        $number = $this->next_number;
        $this->increment('next_number');
        
        return $this->formatNumber($number);
    }

    // Format document number
    public function formatNumber($number)
    {
        // Formatar número com padding
        $formattedNumber = str_pad($number, $this->number_padding, '0', STR_PAD_LEFT);
        
        // Construir formato: FT A/2025/000001 ou FT A/000001
        $parts = [$this->prefix, $this->series_code];
        
        if ($this->include_year) {
            $parts[] = $this->current_year ?? now()->year;
        }
        
        $parts[] = $formattedNumber;
        
        return implode(' ', $parts);
    }

    // Get preview of next number
    public function previewNextNumber()
    {
        return $this->formatNumber($this->next_number);
    }

    // Get default series for document type
    public static function getDefaultSeries($tenantId, $documentType)
    {
        return static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
