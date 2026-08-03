<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class IntegrationMapping extends Model
{
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'accounting_integration_mappings';

    protected $fillable = [
        'tenant_id',
        'event',
        'journal_id',
        'debit_account_id',
        'credit_account_id',
        'vat_account_id',
        'conditions',
        'auto_post',
        'active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'auto_post' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Resolução de contas por integration_key.
     *
     * Num plano IMPORTADO a mesma chave fica em dezenas de contas: a inferência
     * do ImportChartOfAccounts é por nome e apanha tudo o que mencione
     * "cliente", "fornecedor" ou "liquid". Sem estas regras o mapeamento
     * escolhia a primeira por id — foi assim que as vendas foram lançar contra
     * "Adiantamentos de Clientes" e "IVA Dedutível — Autoliquidação".
     *
     * TIPO_ESPERADO é o filtro forte (asset/liability/revenue/expense está
     * correcto em todo o plano); EXCLUSOES_NOME afasta o que menciona a
     * contraparte sem ser a conta corrente.
     */
    public const TIPO_ESPERADO = [
        'receivables'   => 'asset',
        'payables'      => 'liability',
        'cash'          => 'asset',
        'bank'          => 'asset',
        'vat_collected' => 'liability',
        'vat_paid'      => 'asset',
        'sales'         => 'revenue',
        'cogs'          => 'expense',
    ];

    public const EXCLUSOES_NOME = [
        'receivables'   => '/adiantament|provis|descont|garantia|saldos credores|duvidos|descontad/i',
        'payables'      => '/adiantament|provis|saldos devedores/i',
        'vat_collected' => '/autoliquida|auto-liquida|dedut|oficios/i',
        'vat_paid'      => '/autoliquida|auto-liquida|liquidado/i',
        // Depósitos A PRAZO não são a conta de movimento dos recebimentos
        'bank'          => '/a prazo|outros dep/i',
    ];

    /**
     * Como reconhecer, pelo NOME, a conta de cada papel — usado apenas para
     * preencher chaves em FALTA num plano já importado (ver
     * accounting:fix-integration-keys --completar). Não re-etiqueta nada que já
     * tenha chave.
     */
    public const PADROES_NOME = [
        'receivables'   => '/^clientes|clientes.*corrente/i',
        'payables'      => '/^fornecedores|fornecedores.*corrente/i',
        'cash'          => '/^caixa/i',
        'bank'          => '/dep[óo]sitos? [àa] ordem|^bancos?$/i',
        'vat_collected' => '/iva liquidado/i',
        'vat_paid'      => '/iva.*dedut/i',
        'sales'         => '/^vendas/i',
        'cogs'          => '/custo.*(mercador|exist[êe]nc|vendid)|cmvmc/i',
    ];

    /**
     * A conta a usar para uma chave: do tipo certo, sem os falsos positivos e a
     * mais GERAL que reste (menor nível, menor código).
     */
    public static function resolveAccount(int $tenantId, string $key): ?Account
    {
        $query = Account::where('tenant_id', $tenantId)->where('integration_key', $key);

        if ($tipo = self::TIPO_ESPERADO[$key] ?? null) {
            $query->where('type', $tipo);
        }

        $candidatas = $query->orderBy('level')->orderBy('code')->get();

        if ($padrao = self::EXCLUSOES_NOME[$key] ?? null) {
            $filtradas = $candidatas->reject(fn ($c) => preg_match($padrao, (string) $c->name));
            // Nunca ficar sem nada por causa de um filtro
            $candidatas = $filtradas->isNotEmpty() ? $filtradas : $candidatas;
        }

        if ($candidatas->isEmpty()) {
            return null;
        }

        // Fica a mais GERAL do tipo certo (menor nível, menor código). Tentou-se
        // preferir folhas, mas num plano importado de 1500+ contas todas têm
        // descendentes e a escolha caía em contas absurdas
        // ("IVA Dedutível — Existências — M. interno — 2%"). O que estas regras
        // garantem é a CLASSE e o SINAL certos; qual a conta exacta dentro da
        // classe é decisão do contabilista, que a ajusta no mapeamento.
        $movimentavel = $candidatas->firstWhere('is_view', false);
        if ($movimentavel) {
            return $movimentavel;
        }

        // Só há contas-mãe: descer à folha mais geral, porque o balanço filtra
        // is_view=false e não apanharia a mãe.
        return Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->where('code', 'like', $candidatas->first()->code . '%')
            ->orderBy('level')->orderBy('code')
            ->first();
    }

    /**
     * Relação com tenant
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Relação com diário
     */
    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Relação com conta de débito
     */
    public function debitAccount()
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    /**
     * Relação com conta de crédito
     */
    public function creditAccount()
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    /**
     * Relação com conta de IVA
     */
    public function vatAccount()
    {
        return $this->belongsTo(Account::class, 'vat_account_id');
    }
}
