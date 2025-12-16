<?php

namespace App\Models\Salon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SalonSettings extends Model
{
    use HasFactory;

    protected $table = 'salon_settings';

    protected $fillable = [
        'tenant_id',
        'booking_slug',
        'salon_name',
        'salon_description',
        'salon_address',
        'salon_phone',
        'salon_whatsapp',
        'salon_email',
        'salon_instagram',
        'salon_facebook',
        'salon_tiktok',
        'salon_website',
        'salon_google_maps_url',
        'logo',
        'cover_image',
        'primary_color',
        'secondary_color',
        'opening_time',
        'closing_time',
        'working_days',
        'slot_interval',
        'min_advance_booking_hours',
        'max_advance_booking_days',
        'cancellation_hours',
        'reminder_hours',
        'online_booking_enabled',
        'require_confirmation',
        'no_show_fee_percent',
        'require_deposit',
        'deposit_percent',
        'allow_online_payment',
        'booking_terms',
        'cancellation_policy',
        'meta_title',
        'meta_description',
        'welcome_message',
        'confirmation_message',
        'sms_template',
        'email_template',
        'gallery_images',
        'featured_services',
    ];

    protected $casts = [
        'working_days' => 'array',
        'gallery_images' => 'array',
        'featured_services' => 'array',
        'online_booking_enabled' => 'boolean',
        'require_confirmation' => 'boolean',
        'require_deposit' => 'boolean',
        'allow_online_payment' => 'boolean',
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
        'no_show_fee_percent' => 'decimal:2',
        'deposit_percent' => 'decimal:2',
    ];

    protected $attributes = [
        'primary_color' => '#ec4899',
        'secondary_color' => '#8b5cf6',
        'slot_interval' => 30,
        'min_advance_booking_hours' => 2,
        'max_advance_booking_days' => 30,
        'cancellation_hours' => 24,
        'reminder_hours' => 24,
        'online_booking_enabled' => true,
        'require_confirmation' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($settings) {
            // Gerar slug único se não existir
            if (empty($settings->booking_slug)) {
                $settings->booking_slug = static::generateUniqueSlug($settings->salon_name ?? 'salao');
            }
        });
    }

    /**
     * Gera um slug único para booking
     */
    public static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::where('booking_slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Regenerar slug baseado no nome
     */
    public function regenerateSlug(): string
    {
        $this->booking_slug = static::generateUniqueSlug($this->salon_name ?? 'salao');
        $this->save();
        return $this->booking_slug;
    }

    /**
     * Obter URL pública do booking
     */
    public function getBookingUrlAttribute(): string
    {
        return url('/agendar/' . $this->booking_slug);
    }

    /**
     * Obter ou criar settings para um tenant
     */
    public static function getForTenant(?int $tenantId = null): self
    {
        $tenantId = $tenantId ?? activeTenantId();
        
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'salon_name' => 'Meu Salão',
                'opening_time' => '09:00',
                'closing_time' => '19:00',
                'working_days' => [1, 2, 3, 4, 5, 6], // Seg a Sáb
            ]
        );
    }

    /**
     * Obter settings pelo slug de booking
     */
    public static function getBySlug(string $slug): ?self
    {
        return static::where('booking_slug', $slug)->first();
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeOnlineBookingEnabled($query)
    {
        return $query->where('online_booking_enabled', true);
    }

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Verifica se está aberto agora
     */
    public function isOpenNow(): bool
    {
        $now = now();
        $dayOfWeek = $now->dayOfWeekIso; // 1 = Segunda, 7 = Domingo
        
        if (!in_array($dayOfWeek, $this->working_days ?? [])) {
            return false;
        }
        
        $currentTime = $now->format('H:i');
        $openingTime = $this->opening_time ? $this->opening_time->format('H:i') : '09:00';
        $closingTime = $this->closing_time ? $this->closing_time->format('H:i') : '19:00';
        
        return $currentTime >= $openingTime && $currentTime <= $closingTime;
    }

    /**
     * Obter dias de funcionamento formatados
     */
    public function getWorkingDaysFormattedAttribute(): string
    {
        $days = [
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
        
        $workingDays = $this->working_days ?? [];
        
        if (empty($workingDays)) {
            return 'Não definido';
        }
        
        if ($workingDays == [1, 2, 3, 4, 5]) {
            return 'Segunda a Sexta';
        }
        
        if ($workingDays == [1, 2, 3, 4, 5, 6]) {
            return 'Segunda a Sábado';
        }
        
        if ($workingDays == [1, 2, 3, 4, 5, 6, 7]) {
            return 'Todos os dias';
        }
        
        return collect($workingDays)->map(fn($d) => $days[$d] ?? '')->filter()->implode(', ');
    }

    /**
     * Obter horário de funcionamento formatado
     */
    public function getScheduleFormattedAttribute(): string
    {
        $opening = $this->opening_time ? $this->opening_time->format('H:i') : '09:00';
        $closing = $this->closing_time ? $this->closing_time->format('H:i') : '19:00';
        
        return "{$opening} - {$closing}";
    }

    /**
     * Obter URL do logo ou placeholder
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }
        
        if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
            return $this->logo;
        }
        
        return \Storage::url($this->logo);
    }

    /**
     * Obter URL da cover ou placeholder
     */
    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover_image) {
            return null;
        }
        
        if (filter_var($this->cover_image, FILTER_VALIDATE_URL)) {
            return $this->cover_image;
        }
        
        return \Storage::url($this->cover_image);
    }
}
