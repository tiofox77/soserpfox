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
    ];

    public static function getForTenant($tenantId = null)
    {
        $tenantId = $tenantId ?? activeTenantId();
        
        $settings = static::firstOrCreate(
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
     * Obter settings pelo slug de booking
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('booking_slug', $slug)->first();
    }

    public static function generateUniqueSlug($name)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('booking_slug', $slug)->exists()) {
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
