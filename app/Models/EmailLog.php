<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'email_template_id',
        'smtp_setting_id',
        'user_id',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'subject',
        'body_preview',
        'template_slug',
        'template_data',
        'status',
        'error_message',
        'message_id',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'template_data' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Relacionamentos
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function smtpSetting(): BelongsTo
    {
        return $this->belongsTo(SmtpSetting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByTemplate($query, $templateSlug)
    {
        return $query->where('template_slug', $templateSlug);
    }

    /**
     * Métodos auxiliares
     */
    public function markAsSent($messageId = null)
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Criar log de email
     */
    public static function createLog(array $data)
    {
        return self::create([
            'tenant_id' => $data['tenant_id'] ?? null,
            'email_template_id' => $data['email_template_id'] ?? null,
            'smtp_setting_id' => $data['smtp_setting_id'] ?? null,
            'user_id' => auth()->id(),
            'to_email' => $data['to_email'],
            'to_name' => $data['to_name'] ?? null,
            'from_email' => $data['from_email'],
            'from_name' => $data['from_name'] ?? null,
            'subject' => $data['subject'],
            'body_preview' => $data['body_preview'] ?? null,
            'template_slug' => $data['template_slug'] ?? null,
            'template_data' => $data['template_data'] ?? null,
            'status' => 'pending',
        ]);
    }
}
