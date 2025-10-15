<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class WhatsAppSetting extends Model
{
    protected $fillable = [
        'twilio_account_sid',
        'twilio_auth_token',
        'whatsapp_from_number',
        'whatsapp_business_account_id',
        'is_enabled',
        'is_sandbox',
        'templates',
        'notification_settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_sandbox' => 'boolean',
        'templates' => 'array',
        'notification_settings' => 'array',
    ];

    /**
     * Get singleton instance
     */
    public static function getSettings()
    {
        return static::firstOrCreate([]);
    }

    /**
     * Check if WhatsApp is enabled
     */
    public function isActive(): bool
    {
        return $this->is_enabled && 
               !empty($this->twilio_account_sid) && 
               !empty($this->twilio_auth_token) && 
               !empty($this->whatsapp_from_number);
    }

    /**
     * Get template by name
     */
    public function getTemplate(string $name): ?array
    {
        if (!$this->templates) {
            return null;
        }

        return collect($this->templates)->firstWhere('name', $name);
    }

    /**
     * Check if notification type is enabled
     */
    public function isNotificationEnabled(string $type): bool
    {
        if (!$this->notification_settings) {
            return false;
        }

        return $this->notification_settings[$type] ?? false;
    }
}
