<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Str;

class HotelSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'hotel_settings';

    protected $fillable = [
        'tenant_id',
        'hotel_name',
        'hotel_description',
        'hotel_address',
        'hotel_city',
        'hotel_country',
        'hotel_phone',
        'hotel_whatsapp',
        'hotel_email',
        'hotel_website',
        'star_rating',
        'instagram',
        'facebook',
        'google_maps_url',
        'tripadvisor_url',
        'booking_com_url',
        'primary_color',
        'secondary_color',
        'logo',
        'cover_image',
        'gallery_images',
        'default_check_in_time',
        'default_check_out_time',
        'early_check_in_available',
        'late_check_out_available',
        'early_check_in_fee',
        'late_check_out_fee',
        'min_advance_booking_hours',
        'min_advance_booking_days',
        'max_advance_booking_days',
        'cancellation_hours',
        'online_booking_enabled',
        'require_deposit',
        'deposit_percent',
        'tax_rate',
        'booking_policies',
        'cancellation_policies',
        'house_rules',
        'booking_slug',
        'meta_title',
        'meta_description',
        'welcome_message',
        'amenities_list',
        'featured_rooms',
        'payment_methods',
        'currency',
        'overbooking_enabled',
        'overbooking_percent',
        'loyalty_enabled',
        'loyalty_points_per_kz',
        'loyalty_tier_silver',
        'loyalty_tier_gold',
        'loyalty_tier_platinum',
        'notify_reservation_confirmed',
        'notify_pre_arrival',
        'notify_post_stay',
    ];

    protected $casts = [
        'star_rating' => 'integer',
        'default_check_in_time' => 'datetime:H:i',
        'default_check_out_time' => 'datetime:H:i',
        'early_check_in_available' => 'boolean',
        'late_check_out_available' => 'boolean',
        'early_check_in_fee' => 'decimal:2',
        'late_check_out_fee' => 'decimal:2',
        'min_advance_booking_hours' => 'integer',
        'min_advance_booking_days' => 'integer',
        'max_advance_booking_days' => 'integer',
        'cancellation_hours' => 'integer',
        'online_booking_enabled' => 'boolean',
        'require_deposit' => 'boolean',
        'deposit_percent' => 'integer',
        'tax_rate' => 'decimal:2',
        'gallery_images' => 'array',
        'amenities_list' => 'array',
        'featured_rooms' => 'array',
        'payment_methods' => 'array',
        'overbooking_enabled' => 'boolean',
        'overbooking_percent' => 'integer',
        'loyalty_enabled' => 'boolean',
        'loyalty_points_per_kz' => 'decimal:4',
        'loyalty_tier_silver' => 'integer',
        'loyalty_tier_gold' => 'integer',
        'loyalty_tier_platinum' => 'integer',
        'notify_reservation_confirmed' => 'boolean',
        'notify_pre_arrival' => 'boolean',
        'notify_post_stay' => 'boolean',
    ];

    /**
     * As definições de UMA empresa — a activa, ou outra dita por quem chama.
     *
     * SEM O ESCOPO, e é preciso: com o escopo a filtrar pela empresa ACTIVA,
     * um `getForTenant($outra)` não encontrava a linha que existe e o
     * `firstOrCreate` criava uma SEGUNDA para essa empresa. É o que acontecia
     * na página pública de check-in, que resolve o hotel pela reserva.
     *
     * Quem passa um id aqui já decidiu de que empresa fala.
     */
    public static function getForTenant($tenantId = null)
    {
        $tenantId = $tenantId ?? activeTenantId();

        $settings = static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'hotel_name' => 'Meu Hotel',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#6366f1',
                'star_rating' => 3,
                'default_check_in_time' => '14:00',
                'default_check_out_time' => '12:00',
            ]
        );
        
        // Gerar slug se não existir
        if (empty($settings->booking_slug)) {
            $settings->booking_slug = static::generateUniqueSlug($settings->hotel_name ?? 'hotel');
            $settings->save();
        }
        
        return $settings;
    }

    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return \Storage::url($this->logo);
        }
        return null;
    }

    public function getCoverUrlAttribute()
    {
        if ($this->cover_image) {
            return \Storage::url($this->cover_image);
        }
        return null;
    }

    public function getBookingUrlAttribute()
    {
        if ($this->booking_slug) {
            return url('/hotel/booking/' . $this->booking_slug);
        }
        return null;
    }
    
    /**
     * O HOTEL PELA SUA MORADA PÚBLICA.
     *
     * SEM O ESCOPO DE EMPRESA, e de propósito: numa página pública o SLUG é
     * que escolhe a empresa. Quem abre `/hotel/booking/miramar` pode não ter
     * sessão nenhuma — e se tiver, pode estar com outra empresa activa. Com o
     * escopo a filtrar, a página do hotel do lado dava 404 a quem tinha a sua
     * própria empresa aberta noutro separador.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::withoutGlobalScopes()->where('booking_slug', $slug)->first();
    }

    /**
     * Um slug que ainda ninguém usa — E NÃO SÓ NESTA EMPRESA.
     *
     * O slug é a morada pública e tem de ser único no mundo, não por empresa:
     * com o escopo a filtrar, dois hotéis chegavam ao mesmo «miramar» e o
     * segundo roubava as reservas do primeiro.
     */
    public static function generateUniqueSlug($name)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::withoutGlobalScopes()->where('booking_slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    public function regenerateSlug()
    {
        $this->booking_slug = static::generateUniqueSlug($this->hotel_name);
        $this->save();
        return $this->booking_slug;
    }

}
