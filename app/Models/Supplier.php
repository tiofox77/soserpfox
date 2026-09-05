<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_suppliers';

    // A segunda cópia da lista das províncias vivia aqui. Ver
    // App\Support\Geografia — 21 províncias e os municípios, num sítio só.

    // Países disponíveis
    // A segunda cópia da lista dos países vivia aqui. Ver App\Support\Geografia.

    protected $fillable = [
        'tenant_id', 'type', 'name', 'nif', 'logo', 'email', 'phone', 'mobile',
        'address', 'city', 'province', 'municipality', 'neighbourhood', 'postal_code', 'country',
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

    public function products()
    {
        return $this->hasMany(Product::class);
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
