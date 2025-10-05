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

    // Format document number (Padrão AGT Angola)
    public function formatNumber($number)
    {
        // Formatar número com padding
        $formattedNumber = str_pad($number, $this->number_padding, '0', STR_PAD_LEFT);
        
        // Formato AGT Angola: [TIPO] [SÉRIE] [ANO]/[NÚMERO]
        // Exemplo: FT A 2025/000001
        
        $parts = [];
        
        // Prefixo obrigatório (FT, FR, NC, ND, etc.)
        if ($this->prefix) {
            $parts[] = $this->prefix;
        }
        
        // Série adicional (A, B, C, etc.)
        if ($this->series_code) {
            $parts[] = $this->series_code;
        }
        
        // Ano e número
        if ($this->include_year) {
            $year = $this->current_year ?? now()->year;
            $parts[] = $year . '/' . $formattedNumber;
        } else {
            $parts[] = $formattedNumber;
        }
        
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
        $series = static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        
        // Se não existe, criar série padrão AGT
        if (!$series) {
            $series = static::createDefaultSeries($tenantId, $documentType);
        }
        
        return $series;
    }
    
    // Criar série padrão AGT Angola
    public static function createDefaultSeries($tenantId, $documentType)
    {
        // Mapeamento de tipos para prefixos AGT
        $agtPrefixes = [
            'invoice' => 'FT',      // Fatura
            'proforma' => 'PR',     // Proforma
            'receipt' => 'RC',      // Recibo
            'pos' => 'FR',          // Fatura-Recibo
            'credit_note' => 'NC',  // Nota de Crédito
            'debit_note' => 'ND',   // Nota de Débito
            'advance' => 'AD',      // Adiantamento
            'purchase' => 'FC',     // Fatura de Compra
        ];
        
        $prefix = $agtPrefixes[$documentType] ?? 'DOC';
        
        // Buscar configurações para série inicial
        $settings = InvoicingSettings::forTenant($tenantId);
        
        return static::create([
            'tenant_id' => $tenantId,
            'document_type' => $documentType,
            'series_code' => 'A',  // Série padrão A
            'name' => "Série {$prefix} A",
            'prefix' => $prefix,   // FT, FR, NC, etc. (fixo AGT)
            'include_year' => true,
            'next_number' => 1,
            'number_padding' => 6,
            'is_default' => true,
            'is_active' => true,
            'current_year' => now()->year,
            'reset_yearly' => true,
            'description' => "Série padrão AGT para {$prefix}",
        ]);
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
