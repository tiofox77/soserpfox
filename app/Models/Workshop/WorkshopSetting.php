<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Model;

/**
 * AS DEFINIÇÕES DA OFICINA, uma linha por empresa (OF-11).
 *
 * O intervalo de revisão por omissão (o da viatura, quando o tem, manda), com
 * quanto tempo e quantos km de antecedência se chama o cliente, e se os
 * lembretes saem sozinhos por SMS/email (desligado: é dinheiro da empresa).
 */
class WorkshopSetting extends Model
{
    protected $table = 'workshop_settings';

    protected $fillable = [
        'tenant_id',
        'service_interval_km',
        'service_interval_months',
        'remind_days_before',
        'remind_km_before',
        'documents_days_before',
        'auto_reminders',
    ];

    // Os mesmos da migração: uma linha acabada de criar não relê os DEFAULT da base.
    protected $attributes = [
        'service_interval_km' => 10000,
        'service_interval_months' => 6,
        'remind_days_before' => 15,
        'remind_km_before' => 1000,
        'documents_days_before' => 30,
        'auto_reminders' => false,
    ];

    protected $casts = [
        'service_interval_km' => 'integer',
        'service_interval_months' => 'integer',
        'remind_days_before' => 'integer',
        'remind_km_before' => 'integer',
        'documents_days_before' => 'integer',
        'auto_reminders' => 'boolean',
    ];

    public static function getForTenant(int $tenantId): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId]);
    }
}
