<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SmtpSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_email',
        'from_name',
        'is_default',
        'is_active',
        'last_tested_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Relacionamentos
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Accessors & Mutators
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    public function getPasswordAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Configurar o mailer com estas configurações
     */
    public function configure()
    {
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => null,
        ]);

        Config::set('mail.from', [
            'address' => $this->from_email,
            'name' => $this->from_name,
        ]);
    }

    /**
     * Testar conexão SMTP
     */
    public function testConnection(): array
    {
        try {
            // Testar conexão socket direta
            $socket = @fsockopen(
                $this->host,
                $this->port,
                $errno,
                $errstr,
                10
            );

            if (!$socket) {
                throw new \Exception("Não foi possível conectar ao servidor SMTP: {$errstr} ({$errno})");
            }

            // Ler resposta do servidor
            $response = fgets($socket, 515);
            
            if (!$response || strpos($response, '220') !== 0) {
                fclose($socket);
                throw new \Exception("Servidor SMTP não respondeu corretamente: {$response}");
            }

            fclose($socket);
            
            $this->update(['last_tested_at' => now()]);
            
            return [
                'success' => true,
                'message' => 'Conexão SMTP estabelecida com sucesso! Host: ' . $this->host . ':' . $this->port,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Obter configuração SMTP para um tenant
     * Se não tiver específica, usa a padrão
     */
    public static function getForTenant($tenantId = null)
    {
        if ($tenantId) {
            $setting = self::forTenant($tenantId)->active()->first();
            if ($setting) {
                return $setting;
            }
        }

        // Retornar configuração padrão
        return self::default()->active()->first();
    }
}
