<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use App\Traits\BelongsToTenant;

class Transaction extends Model
{
    use BelongsToTenant;

    protected $table = 'treasury_transactions';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'account_id',
        'cash_register_id',
        'payment_method_id',
        'invoice_id',
        'purchase_id',
        'related_type',
        'related_id',
        'transaction_number',
        'type',
        'transaction_type_id',
        'category',
        'transaction_category_id',
        'amount',
        'currency',
        'transaction_date',
        'reference',
        'description',
        'notes',
        'status',
        'is_reconciled',
        'reconciled_at',
        'attachment',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];
    
    /**
     * Próximo número de transação, por empresa e ano — à prova de colisões.
     *
     * O bug antigo: cada ecrã fazia `orderBy('id')->first()` + `substr(-4)+1`.
     * Quando a última transação vinha de um gerador `uniqid()` (POS, factura,
     * restaurante), o `substr(-4)` dava lixo, o contador reiniciava e chocava
     * com um número já existente (erro 1062 no índice único por tenant).
     *
     * Aqui olha-se SÓ para os números no formato canónico deste ano
     * (`TRX-AAAA-####`), tira-se o MAIOR sufixo numérico (CAST, não a última
     * linha por id) e soma-se 1. Os números antigos/uniqid são ignorados e já
     * não envenenam a sequência.
     */
    public static function gerarNumero(int $tenantId, string $prefixo = 'TRX'): string
    {
        $ano = date('Y');
        $inicio = "{$prefixo}-{$ano}-";

        $ultimo = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('transaction_number', 'like', $inicio . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(transaction_number, "-", -1) AS UNSIGNED) DESC')
            ->value('transaction_number');

        $seq = $ultimo ? ((int) substr($ultimo, strrpos($ultimo, '-') + 1)) + 1 : 1;

        return $inicio . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cria uma transação com número gerado, repetindo se houver colisão.
     *
     * Duas caixas a registar ao mesmo tempo podem calcular o mesmo número; em
     * vez de rebentar na cara do utilizador (o que estava a acontecer), apanha
     * o 1062 e tenta o número seguinte. Ao fim de várias tentativas cai num
     * sufixo aleatório — nunca falha o registo por causa do número.
     */
    public static function criar(array $attrs, string $prefixo = 'TRX'): self
    {
        $tenantId = (int) ($attrs['tenant_id'] ?? activeTenantId());

        for ($tentativa = 0; $tentativa < 6; $tentativa++) {
            $attrs['transaction_number'] = static::gerarNumero($tenantId, $prefixo);
            try {
                return static::create($attrs);
            } catch (QueryException $e) {
                // 23000/1062 = entrada duplicada: tentar o número seguinte.
                if (($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) && $tentativa < 5) {
                    usleep(random_int(1000, 6000));
                    continue;
                }
                throw $e;
            }
        }

        // Último recurso: número único garantido, para nunca prender a operação.
        $attrs['transaction_number'] = $prefixo . '-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        return static::create($attrs);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }
    
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }
    
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }
    
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
