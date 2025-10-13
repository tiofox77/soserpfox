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
        'role_id',
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
        \Log::info('📧 Enviando convite de usuário', [
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
        ]);
        
        // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
        $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
        
        if (!$smtpSetting) {
            \Log::error('❌ Configuração SMTP não encontrada no banco');
            throw new \Exception('Configuração SMTP não encontrada');
        }
        
        \Log::info('📧 Configuração SMTP encontrada', [
            'host' => $smtpSetting->host,
            'port' => $smtpSetting->port,
            'encryption' => $smtpSetting->encryption,
        ]);
        
        // CONFIGURAR SMTP usando método configure() do modelo
        $smtpSetting->configure();
        \Log::info('✅ SMTP configurado do banco de dados');
        
        // BUSCAR TEMPLATE DO BANCO
        $template = \App\Models\EmailTemplate::where('slug', 'user-invitation')->first();
        
        if (!$template) {
            \Log::error('❌ Template user-invitation não encontrado');
            throw new \Exception('Template user-invitation não encontrado');
        }
        
        \Log::info('📄 Template user-invitation encontrado', [
            'id' => $template->id,
            'subject' => $template->subject,
        ]);
        
        // Dados para o template
        $data = [
            'inviter_name' => $this->invitedBy->name,
            'invited_name' => $this->name,
            'user_name' => $this->name, // Alias para compatibilidade
            'name' => $this->name, // Alias para compatibilidade
            'tenant_name' => $this->tenant->name,
            'company_name' => $this->tenant->company_name ?? $this->tenant->name,
            'invite_url' => $this->getInviteUrl(),
            'invite_link' => $this->getInviteUrl(), // Alias
            'expires_in_days' => now()->diffInDays($this->expires_at),
            'expiry_date' => $this->expires_at->format('d/m/Y'),
            'app_name' => config('app.name', 'SOS ERP'),
            'app_url' => config('app.url'),
            'support_email' => $smtpSetting->from_email,
            'email' => $this->email,
        ];
        
        // Log dos dados enviados
        \Log::info('📋 Dados para o template', [
            'data' => $data,
        ]);
        
        // Renderizar template do BD
        $rendered = $template->render($data);
        
        \Log::info('📧 Template renderizado', [
            'to' => $this->email,
            'subject' => $rendered['subject'],
            'body_preview' => substr($rendered['body_html'], 0, 200),
        ]);
        
        // Enviar email usando HTML DO TEMPLATE
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($rendered) {
            $message->to($this->email, $this->name)
                    ->subject($rendered['subject'])
                    ->html($rendered['body_html']);
        });
        
        \Log::info('✅ Email de convite enviado', ['to' => $this->email]);
    }
}
