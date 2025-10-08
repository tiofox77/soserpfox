<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'invited_by',
        'email',
        'name',
        'token',
        'role',
        'status',
        'expires_at',
        'accepted_at',
        'user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Boot do modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
            
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7); // Expira em 7 dias
            }
        });
    }

    /**
     * Relacionamentos
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '<=', now());
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Métodos
     */
    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at < now();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at > now();
    }

    public function markAsAccepted($userId): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'user_id' => $userId,
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    /**
     * Gerar URL do convite
     */
    public function getInviteUrl(): string
    {
        return route('invitation.accept', ['token' => $this->token]);
    }

    /**
     * Enviar email de convite
     */
    public function sendInvitationEmail(): void
    {
        $data = [
            'inviter_name' => $this->invitedBy->name,
            'invited_name' => $this->name,
            'tenant_name' => $this->tenant->name,
            'invite_url' => $this->getInviteUrl(),
            'expires_in_days' => now()->diffInDays($this->expires_at),
            'app_name' => config('app.name', 'SOS ERP'),
        ];

        \Illuminate\Support\Facades\Mail::to($this->email)
            ->send(new \App\Mail\TemplateMail('user_invitation', $data, $this->tenant_id));
    }
}
