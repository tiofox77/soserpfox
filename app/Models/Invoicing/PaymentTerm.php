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
     * A condição com que um cliente novo desta empresa nasce.
     *
     * UMA SÓ AUTORIDADE, e é a coluna `is_default`. Podia ter-se guardado a
     * escolha em `invoicing_settings` e ficariam duas — que é como se acaba
     * com o ecrã das condições a dizer uma coisa e o das definições a dizer
     * outra. O ecrã de Configurações escreve aqui; toda a gente lê daqui.
     *
     * Sem nenhuma marcada, vale a primeira activa por ordem de apresentação:
     * um cliente sem condição nenhuma fica sem vencimento na factura, e mais
     * vale um prazo razoável do que nenhum.
     */
    public static function padraoDe(?int $tenantId): ?self
    {
        if (!$tenantId) {
            return null;
        }

        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Marca esta condição como a dos clientes novos, tirando a marca à
     * anterior. Uma empresa com duas condições "por omissão" não tem nenhuma.
     */
    public static function definirPadrao(int $tenantId, ?int $termId): void
    {
        static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (!$termId) {
            return;
        }

        static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($termId)
            ->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * Semeia as condições padrão numa empresa. Idempotente e aditivo: quem já
     * as tem não recebe duplicados; quem não tem nenhuma recebe as que faltam.
     */
    /**
     * UMA EMPRESA SEM CATÁLOGO NENHUM RECEBE OS PADRÕES — e só essa.
     *
     * Os padrões nasciam no `mount()` dos ecrãs Livewire dos clientes e das
     * condições. Com os ecrãs em React, só o catálogo das condições e as
     * definições os criavam: numa empresa que nunca abriu nenhum dos dois, o
     * formulário do cliente mostrava «— Sem condição —» e mais nada, e os
     * clientes nasciam sem vencimento (15/09/2026, empresa de testes #102).
     *
     * SÓ QUANDO NÃO HÁ NENHUMA. Uma empresa que apagou o «Depósito» de
     * propósito não o vê voltar por ter aberto a lista de clientes.
     */
    public static function garantirCatalogo(?int $tenantId): void
    {
        if (!$tenantId) {
            return;
        }

        if (!static::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            static::provisionarPadroes($tenantId);
        }
    }

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
