<?php

namespace App\Models\Salon;

use App\Models\Client as BaseClient;

/**
 * Salon Client - Usa a tabela invoicing_clients diretamente
 * Compatível com SAFT e faturação
 */
class Client extends BaseClient
{
    /**
     * Campos adicionais do salão que serão guardados no campo 'notes' como JSON
     */
    protected $salonFields = [
        'preferences',
        'allergies',
        'total_visits',
        'total_spent',
        'loyalty_points',
        'is_vip',
        'last_visit_at',
    ];

    protected $appends = ['first_name', 'whatsapp_link', 'salon_data'];

    // Accessors
    public function getFirstNameAttribute()
    {
        return explode(' ', $this->name)[0];
    }

    public function getWhatsappAttribute()
    {
        return $this->mobile ?? $this->phone;
    }

    public function getWhatsappLinkAttribute()
    {
        $number = $this->mobile ?? $this->phone;
        if (!$number) return null;
        $number = preg_replace('/[^0-9]/', '', $number);
        return "https://wa.me/{$number}";
    }

    /**
     * Dados específicos do salão guardados como JSON
     */
    public function getSalonDataAttribute()
    {
        $notes = $this->notes;
        if ($notes && str_starts_with($notes, '{')) {
            return json_decode($notes, true) ?? [];
        }
        return [];
    }

    public function getTotalVisitsAttribute()
    {
        return $this->salon_data['total_visits'] ?? 0;
    }

    public function getTotalSpentAttribute()
    {
        return $this->salon_data['total_spent'] ?? 0;
    }

    public function getLoyaltyPointsAttribute()
    {
        return $this->salon_data['loyalty_points'] ?? 0;
    }

    public function getIsVipAttribute()
    {
        return $this->salon_data['is_vip'] ?? false;
    }

    public function getLastVisitAtAttribute()
    {
        $date = $this->salon_data['last_visit_at'] ?? null;
        return $date ? \Carbon\Carbon::parse($date) : null;
    }

    public function getPreferencesAttribute()
    {
        return $this->salon_data['preferences'] ?? [];
    }

    public function getAllergiesAttribute()
    {
        return $this->salon_data['allergies'] ?? [];
    }

    // Configurações VIP
    const VIP_MIN_VISITS = 10;          // Mínimo de visitas para VIP automático
    const VIP_MIN_SPENT = 100000;       // Mínimo gasto (Kz) para VIP automático
    const LOYALTY_POINTS_PER_1000 = 10; // Pontos por cada 1000 Kz gastos

    // Methods
    public function updateSalonData(array $data)
    {
        $salonData = $this->salon_data;
        $salonData = array_merge($salonData, $data);
        $this->notes = json_encode($salonData);
        $this->save();
        return $this;
    }

    /**
     * Alias para registerVisit
     */
    public function incrementVisit($amount = 0)
    {
        return $this->registerVisit($amount);
    }

    /**
     * Registar uma visita concluída
     */
    public function registerVisit($amount = 0)
    {
        $newVisits = $this->total_visits + 1;
        $newSpent = $this->total_spent + $amount;
        $newPoints = $this->loyalty_points + floor($amount / 1000) * self::LOYALTY_POINTS_PER_1000;
        
        $this->updateSalonData([
            'total_visits' => $newVisits,
            'total_spent' => $newSpent,
            'loyalty_points' => $newPoints,
            'last_visit_at' => now()->toISOString(),
        ]);

        // Verificar VIP automático
        $this->checkAutoVip();

        return $this;
    }

    /**
     * Recalcular estatísticas a partir dos agendamentos
     */
    public function recalculateStats()
    {
        $completed = $this->appointments()
            ->where('status', 'completed')
            ->get();

        $totalVisits = $completed->count();
        $totalSpent = $completed->sum('total_price');
        $lastVisit = $completed->sortByDesc('date')->first()?->date;

        $this->updateSalonData([
            'total_visits' => $totalVisits,
            'total_spent' => $totalSpent,
            'loyalty_points' => floor($totalSpent / 1000) * self::LOYALTY_POINTS_PER_1000,
            'last_visit_at' => $lastVisit?->toISOString(),
        ]);

        $this->checkAutoVip();

        return $this;
    }

    /**
     * Verificar e aplicar VIP automático
     */
    public function checkAutoVip()
    {
        if ($this->is_vip) return; // Já é VIP

        $meetsVisitCriteria = $this->total_visits >= self::VIP_MIN_VISITS;
        $meetsSpentCriteria = $this->total_spent >= self::VIP_MIN_SPENT;

        if ($meetsVisitCriteria || $meetsSpentCriteria) {
            $this->setVip(true);
        }
    }

    /**
     * Adicionar pontos de fidelidade
     */
    public function addLoyaltyPoints($points)
    {
        $this->updateSalonData([
            'loyalty_points' => $this->loyalty_points + $points,
        ]);
        return $this;
    }

    /**
     * Usar pontos de fidelidade
     */
    public function useLoyaltyPoints($points)
    {
        if ($points > $this->loyalty_points) {
            return false;
        }
        $this->updateSalonData([
            'loyalty_points' => $this->loyalty_points - $points,
        ]);
        return true;
    }

    /**
     * Definir status VIP
     */
    public function setVip($isVip = true)
    {
        $this->updateSalonData(['is_vip' => $isVip]);
        return $this;
    }

    /**
     * Verificar se cliente merece desconto VIP
     */
    public function getVipDiscountPercent()
    {
        if (!$this->is_vip) return 0;
        
        // Desconto progressivo baseado em visitas
        if ($this->total_visits >= 50) return 15;
        if ($this->total_visits >= 25) return 10;
        return 5;
    }

    /**
     * Verificar se é aniversário do cliente (para promoções)
     */
    public function isBirthday()
    {
        if (!$this->birth_date) return false;
        return $this->birth_date->format('m-d') === now()->format('m-d');
    }

    /**
     * Verificar se é mês de aniversário
     */
    public function isBirthdayMonth()
    {
        if (!$this->birth_date) return false;
        return $this->birth_date->format('m') === now()->format('m');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeVip($query)
    {
        return $query->where('notes', 'like', '%"is_vip":true%');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('mobile', 'like', "%{$term}%");
        });
    }

    // Relationships
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    public function completedAppointments()
    {
        return $this->hasMany(Appointment::class, 'client_id')
            ->where('status', 'completed');
    }

    public function upcomingAppointments()
    {
        return $this->hasMany(Appointment::class, 'client_id')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time');
    }
}
