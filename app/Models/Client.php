<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Client extends Authenticatable
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
        'website', 'notes', 'is_active', 'password', 'portal_access',
        'last_login_at', 'password_changed_at',
        // Hotel guest fields
        'hotel_vip', 'hotel_blacklisted', 'document_type', 'document_number',
        'nationality', 'birth_date', 'gender',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'is_active' => 'boolean',
        'portal_access' => 'boolean',
        'credit_limit' => 'decimal:2',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        // Hotel guest casts
        'hotel_vip' => 'boolean',
        'hotel_blacklisted' => 'boolean',
        'birth_date' => 'date',
    ];

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    // Loyalty / Fidelidade
    const LOYALTY_POINTS_PER_1000 = 10;
    const VIP_MIN_VISITS = 10;
    const VIP_MIN_SPENT = 100000;

    public function getLoyaltyDataAttribute()
    {
        if ($this->notes && str_starts_with($this->notes, '{')) {
            return json_decode($this->notes, true) ?? [];
        }
        return [];
    }

    public function updateLoyaltyData(array $data)
    {
        $loyaltyData = $this->loyalty_data;
        $loyaltyData = array_merge($loyaltyData, $data);
        $this->notes = json_encode($loyaltyData);
        $this->save();
        return $this;
    }

    public function incrementStays($amount = 0)
    {
        $data = $this->loyalty_data;
        $newVisits = ($data['total_visits'] ?? 0) + 1;
        $newSpent = ($data['total_spent'] ?? 0) + $amount;
        $newPoints = ($data['loyalty_points'] ?? 0) + floor($amount / 1000) * self::LOYALTY_POINTS_PER_1000;

        $this->updateLoyaltyData([
            'total_visits' => $newVisits,
            'total_spent' => $newSpent,
            'loyalty_points' => $newPoints,
            'last_visit_at' => now()->toISOString(),
        ]);

        // Auto VIP
        if (!$this->hotel_vip && ($newVisits >= self::VIP_MIN_VISITS || $newSpent >= self::VIP_MIN_SPENT)) {
            $this->update(['hotel_vip' => true]);
        }

        return $this;
    }

    public function addLoyaltyPoints($points)
    {
        $data = $this->loyalty_data;
        $this->updateLoyaltyData([
            'loyalty_points' => ($data['loyalty_points'] ?? 0) + $points,
        ]);
        return $this;
    }

    public function useLoyaltyPoints($points)
    {
        $data = $this->loyalty_data;
        $current = $data['loyalty_points'] ?? 0;
        if ($points > $current) return false;
        $this->updateLoyaltyData(['loyalty_points' => $current - $points]);
        return true;
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
