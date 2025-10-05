<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_clients';

    // Províncias de Angola
    public const PROVINCIAS_ANGOLA = [
        'Bengo', 'Benguela', 'Bié', 'Cabinda', 'Cuando Cubango',
        'Cuanza Norte', 'Cuanza Sul', 'Cunene', 'Huambo', 'Huíla',
        'Luanda', 'Lunda Norte', 'Lunda Sul', 'Malanje', 'Moxico',
        'Namibe', 'Uíge', 'Zaire'
    ];

    // Países disponíveis (África + Portugal)
    public const PAISES = [
        'Angola', 'Portugal', 'Moçambique', 'Brasil', 'Cabo Verde',
        'Guiné-Bissau', 'São Tomé e Príncipe', 'Outro'
    ];

    protected $fillable = [
        'tenant_id', 'type', 'name', 'nif', 'logo', 'email', 'phone', 'mobile',
        'address', 'city', 'province', 'postal_code', 'country',
        'tax_regime', 'is_iva_subject', 'credit_limit', 'payment_term_days',
        'website', 'notes', 'is_active'
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
    ];

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices()
    {
        return $this->hasMany(InvoicingInvoice::class, 'client_id');
    }
    
    public function billingInvoices()
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }
    
    /**
     * Retorna código ISO 3166-1-alpha-2 do país para SAFT
     * 
     * @return string Código de 2 letras (ex: AO, PT, MZ)
     */
    public function getCountryCodeAttribute(): string
    {
        $countryMap = [
            'Angola' => 'AO',
            'Portugal' => 'PT',
            'Moçambique' => 'MZ',
            'Mozambique' => 'MZ',
            'Brasil' => 'BR',
            'Brazil' => 'BR',
            'Cabo Verde' => 'CV',
            'Guiné-Bissau' => 'GW',
            'São Tomé e Príncipe' => 'ST',
            'AO' => 'AO',
            'PT' => 'PT',
            'MZ' => 'MZ',
            'BR' => 'BR',
            'CV' => 'CV',
            'GW' => 'GW',
            'ST' => 'ST',
        ];
        
        $country = $this->country ?? 'AO';
        
        // Se já está no formato correto (2 letras)
        if (strlen($country) === 2) {
            return strtoupper($country);
        }
        
        // Buscar no mapa
        return $countryMap[$country] ?? 'AO';
    }
}
