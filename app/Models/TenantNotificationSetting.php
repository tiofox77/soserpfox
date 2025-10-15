<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNotificationSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        // Email
        'email_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'from_email',
        'from_name',
        // SMS
        'sms_enabled',
        'sms_provider',
        'sms_account_sid',
        'sms_auth_token',
        'sms_from_number',
        'sms_api_token', // D7 Networks
        'sms_sender_id', // D7 Networks
        // WhatsApp
        'whatsapp_enabled',
        'whatsapp_provider',
        'whatsapp_account_sid',
        'whatsapp_auth_token',
        'whatsapp_from_number',
        'whatsapp_business_account_id',
        'whatsapp_sandbox',
        // Preferences
        'email_notifications',
        'sms_notifications',
        'whatsapp_notifications',
        'whatsapp_templates',
        'whatsapp_notification_templates', // Template ID para cada tipo WhatsApp
        'sms_notification_templates', // Template ID para cada tipo SMS
        'email_notification_templates', // Template ID para cada tipo Email
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'whatsapp_sandbox' => 'boolean',
        'email_notifications' => 'array',
        'sms_notifications' => 'array',
        'whatsapp_notifications' => 'array',
        'whatsapp_templates' => 'array',
        'whatsapp_notification_templates' => 'array',
        'sms_notification_templates' => 'array',
        'email_notification_templates' => 'array',
    ];

    /**
     * Get tenant settings
     */
    public static function getForTenant($tenantId)
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'email_notifications' => static::getDefaultEmailNotifications(),
                'sms_notifications' => static::getDefaultSmsNotifications(),
                'whatsapp_notifications' => static::getDefaultWhatsAppNotifications(),
            ]
        );
    }

    /**
     * Default email notifications
     */
    public static function getDefaultEmailNotifications(): array
    {
        return [
            'employee_created' => true,
            'salary_advance_approved' => true,
            'salary_advance_rejected' => true,
            'vacation_approved' => true,
            'vacation_rejected' => true,
            'payslip_ready' => true,
        ];
    }

    /**
     * Default SMS notifications
     */
    public static function getDefaultSmsNotifications(): array
    {
        return [
            'employee_created' => false,
            'salary_advance_approved' => false,
            'salary_advance_rejected' => false,
            'vacation_approved' => false,
            'vacation_rejected' => false,
            'payslip_ready' => false,
        ];
    }

    /**
     * Default WhatsApp notifications
     */
    public static function getDefaultWhatsAppNotifications(): array
    {
        return [
            'employee_created' => false,
            'salary_advance_approved' => false,
            'salary_advance_rejected' => false,
            'vacation_approved' => false,
            'vacation_rejected' => false,
            'payslip_ready' => false,
        ];
    }

    /**
     * Tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if email notification is enabled for type
     */
    public function isEmailNotificationEnabled(string $type): bool
    {
        return $this->email_enabled && ($this->email_notifications[$type] ?? false);
    }

    /**
     * Check if SMS notification is enabled for type
     */
    public function isSmsNotificationEnabled(string $type): bool
    {
        return $this->sms_enabled && ($this->sms_notifications[$type] ?? false);
    }

    /**
     * Check if WhatsApp notification is enabled for type
     */
    public function isWhatsAppNotificationEnabled(string $type): bool
    {
        return $this->whatsapp_enabled && ($this->whatsapp_notifications[$type] ?? false);
    }
}
