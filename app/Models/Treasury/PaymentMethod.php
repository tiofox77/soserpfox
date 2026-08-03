<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class PaymentMethod extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_payment_methods';
    
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'description',
        'icon',
        'color',
        'fee_percentage',
        'fee_fixed',
        'requires_account',
        'is_active',
        'sort_order',
    ];
    
    protected $casts = [
        'fee_percentage' => 'decimal:2',
        'fee_fixed' => 'decimal:2',
        'requires_account' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Conjunto canónico de métodos de pagamento de Angola.
     * Fonte única — usado na criação do tenant e no backfill.
     */
    public static function defaultSet(): array
    {
        return [
            ['name' => 'Dinheiro',               'code' => 'CASH',     'type' => 'cash',           'description' => 'Pagamento em dinheiro (Kwanzas)',   'icon' => 'fa-money-bill-wave', 'color' => '#10b981', 'fee_percentage' => 0,   'fee_fixed' => 0, 'requires_account' => false, 'is_active' => true,  'sort_order' => 1],
            ['name' => 'Multicaixa Express',     'code' => 'MCX',      'type' => 'digital_wallet', 'description' => 'Multicaixa Express (carteira digital)', 'icon' => 'fa-mobile-alt', 'color' => '#ef4444', 'fee_percentage' => 0,   'fee_fixed' => 0, 'requires_account' => false, 'is_active' => true,  'sort_order' => 2],
            ['name' => 'TPA (Multicaixa)',       'code' => 'TPA',      'type' => 'card',           'description' => 'Terminal de Pagamento Automático',  'icon' => 'fa-credit-card', 'color' => '#3b82f6', 'fee_percentage' => 2.5, 'fee_fixed' => 0, 'requires_account' => false, 'is_active' => true,  'sort_order' => 3],
            ['name' => 'Transferência Bancária', 'code' => 'TRANSFER', 'type' => 'bank_transfer',  'description' => 'Transferência bancária',            'icon' => 'fa-exchange-alt', 'color' => '#8b5cf6', 'fee_percentage' => 0,  'fee_fixed' => 0, 'requires_account' => true,  'is_active' => true,  'sort_order' => 4],
            ['name' => 'Cheque',                 'code' => 'CHECK',    'type' => 'check',          'description' => 'Pagamento em cheque',               'icon' => 'fa-money-check', 'color' => '#f59e0b', 'fee_percentage' => 0,   'fee_fixed' => 0, 'requires_account' => true,  'is_active' => true,  'sort_order' => 5],
            ['name' => 'Débito Direto',          'code' => 'DEBIT',    'type' => 'bank_transfer',  'description' => 'Débito direto em conta',            'icon' => 'fa-university', 'color' => '#6b7280', 'fee_percentage' => 0,   'fee_fixed' => 0, 'requires_account' => true,  'is_active' => true,  'sort_order' => 6],
            ['name' => 'MB Way Angola',          'code' => 'MBWAY',    'type' => 'digital_wallet', 'description' => 'MB Way Angola (se disponível)',     'icon' => 'fa-wallet', 'color' => '#ec4899', 'fee_percentage' => 0,   'fee_fixed' => 0, 'requires_account' => false, 'is_active' => false, 'sort_order' => 7],
        ];
    }

    /**
     * Cria/garante os métodos de pagamento padrão para um tenant (idempotente
     * por código). Devolve o nº de métodos criados.
     */
    public static function seedDefaultsForTenant($tenantId): int
    {
        $created = 0;
        foreach (static::defaultSet() as $method) {
            $exists = static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $method['code'])
                ->exists();
            if ($exists) {
                continue;
            }
            static::withoutGlobalScopes()->create(array_merge($method, ['tenant_id' => $tenantId]));
            $created++;
        }
        return $created;
    }
}
