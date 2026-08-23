<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Condição de pagamento de um cliente (pronto pagamento, 15 dias, 30 dias,
 * depósito, …). O catálogo é por empresa e gerido pelo utilizador; `days`
 * alimenta o vencimento da factura.
 */
class PaymentTerm extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_payment_terms';

    protected $fillable = [
        'tenant_id',
        'name',
        'days',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'days' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Padrões com que uma empresa nasce. O utilizador pode acrescentar mais. */
    public const PADROES = [
        ['name' => 'Pronto Pagamento', 'days' => 0,  'is_default' => true,  'sort_order' => 1],
        ['name' => 'Pagamento a 15 dias', 'days' => 15, 'is_default' => false, 'sort_order' => 2],
        ['name' => 'Pagamento a 30 dias', 'days' => 30, 'is_default' => false, 'sort_order' => 3],
        ['name' => 'Depósito', 'days' => 0, 'is_default' => false, 'sort_order' => 4],
    ];

    public function clients()
    {
        return $this->hasMany(Client::class, 'payment_term_id');
    }

    /**
     * Semeia as condições padrão numa empresa. Idempotente e aditivo: quem já
     * as tem não recebe duplicados; quem não tem nenhuma recebe as que faltam.
     */
    public static function provisionarPadroes(int $tenantId): int
    {
        $criadas = 0;
        foreach (self::PADROES as $padrao) {
            $term = static::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $padrao['name']],
                [
                    'days' => $padrao['days'],
                    'is_default' => $padrao['is_default'],
                    'is_active' => true,
                    'sort_order' => $padrao['sort_order'],
                ]
            );
            if ($term->wasRecentlyCreated) {
                $criadas++;
            }
        }

        return $criadas;
    }
}
