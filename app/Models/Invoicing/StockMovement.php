<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_stock_movements';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'type',
        'quantity',
        'balance_before',
        'balance_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'batch_reference',
        'from_warehouse_id',
        'to_warehouse_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Tipos de movimento
    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Suspende SÓ a aplicação automática ao stock, não os eventos todos.
     *
     * Quase todos os sítios que criam movimentos já actualizaram o stock à mão
     * (a venda, a compra, a transferência, o POS) e usavam
     * `StockMovement::withoutEvents()` para o hook abaixo não voltar a debitar.
     * Só que `withoutEvents` desliga TODOS os observers — incluindo o de
     * auditoria. O resultado era que os movimentos que mais importam, os das
     * vendas, eram os únicos que não deixavam rasto na trilha.
     */
    protected static bool $ignorarAplicacaoAoStock = false;

    public static function semAplicarStock(callable $accao)
    {
        $anterior = static::$ignorarAplicacaoAoStock;
        static::$ignorarAplicacaoAoStock = true;

        try {
            return $accao();
        } finally {
            static::$ignorarAplicacaoAoStock = $anterior;
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($movement) {
            if (!static::$ignorarAplicacaoAoStock) {
                $movement->updateStock();
            }

            // Depois de o stock estar aplicado — venha daqui ou de quem chamou.
            $movement->carimbarSaldos();
        });
    }

    /**
     * Grava o saldo antes e depois deste movimento.
     *
     * A coluna "Saldo" do histórico vinha vazia em todas as linhas: só o ecrã
     * de ajustes preenchia balance_after, e vendas, entradas e transferências
     * deixavam-no nulo. Sem saldos não se lê o histórico — sobretudo nos
     * ajustes, cuja quantidade é o valor FINAL e não uma variação.
     *
     * Corre depois de o stock estar aplicado, por isso o saldo lido É o de
     * depois. O de antes deriva-se do efeito do movimento; num ajuste não pode
     * derivar-se (a quantidade não é uma variação) e por isso só fica gravado
     * quando quem o criou o indicar.
     */
    public function carimbarSaldos(): void
    {
        $depois = $this->balance_after !== null
            ? (float) $this->balance_after
            : $this->saldoActual();

        if ($depois === null) {
            return;
        }

        $antes = $this->balance_before !== null ? (float) $this->balance_before : null;

        if ($antes === null) {
            $qtd = (float) $this->quantity;

            $antes = match ($this->type) {
                'in'       => $depois - $qtd,
                'out'      => $depois + $qtd,
                // A perna que este movimento representa é a do warehouse_id.
                'transfer' => $this->to_warehouse_id === $this->warehouse_id
                    ? $depois - abs($qtd)
                    : $depois + abs($qtd),
                // Ajuste: a quantidade é o valor final, não dá para derivar.
                default    => null,
            };
        }

        $this->balance_after  = $depois;
        $this->balance_before = $antes;

        // saveQuietly: voltar a disparar `created` reaplicava o stock.
        $this->saveQuietly();
    }

    /** Saldo actual do produto neste armazém, ou o agregado se não houver linha. */
    private function saldoActual(): ?float
    {
        if (!$this->warehouse_id || !$this->product_id) {
            return null;
        }

        $linha = Stock::where('tenant_id', $this->tenant_id)
            ->where('warehouse_id', $this->warehouse_id)
            ->where('product_id', $this->product_id)
            ->first();

        if ($linha) {
            return (float) $linha->quantity;
        }

        // Sem linha: o produto pode viver do agregado (ver BaixaDeStock).
        $produto = \App\Models\Product::find($this->product_id);

        return $produto ? (float) ($produto->stock_quantity ?? 0) : null;
    }

    // Relacionamentos
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Métodos
    public function updateStock()
    {
        switch ($this->type) {
            case self::TYPE_IN:
                Stock::addStock($this->warehouse_id, $this->product_id, $this->quantity, $this->unit_cost);
                break;

            case self::TYPE_OUT:
                Stock::removeStock($this->warehouse_id, $this->product_id, $this->quantity);
                break;

            case self::TYPE_TRANSFER:
                if ($this->from_warehouse_id && $this->to_warehouse_id) {
                    Stock::removeStock($this->from_warehouse_id, $this->product_id, $this->quantity);
                    Stock::addStock($this->to_warehouse_id, $this->product_id, $this->quantity, $this->unit_cost);
                }
                break;

            case self::TYPE_ADJUSTMENT:
                // Ajuste direto do stock
                $stock = Stock::where('warehouse_id', $this->warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->where('tenant_id', $this->tenant_id)
                    ->first();

                if ($stock) {
                    $stock->quantity = $this->quantity;
                    $stock->save();
                } else {
                    Stock::addStock($this->warehouse_id, $this->product_id, $this->quantity, $this->unit_cost);
                }
                break;
        }
    }

    public static function createEntry($data)
    {
        return static::create(array_merge($data, [
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'type' => self::TYPE_IN,
        ]));
    }

    public static function createExit($data)
    {
        return static::create(array_merge($data, [
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'type' => self::TYPE_OUT,
        ]));
    }

    /**
     * Corre `$accao` com uma referência de lote reservada só para ela.
     *
     * A referência é MOV/AAAA/NNNNNN, sequencial POR EMPRESA e reiniciada a
     * cada ano — o mesmo desenho da numeração dos documentos de faturação. Como
     * o índice não é único (nem pode ser: várias linhas partilham a referência
     * de propósito), duas movimentações simultâneas que levassem o mesmo número
     * fundiam-se num só documento, em silêncio.
     *
     * O que fecha essa janela é um BLOQUEIO NOMEADO do MySQL, segurado desde o
     * cálculo do número até ao fim da gravação. Chegou a ser um `lockForUpdate`
     * sobre a linha da empresa em `tenants`, e isso era muito pior do que o
     * problema: quase todas as tabelas têm chave estrangeira para `tenants`, e
     * o InnoDB pede um bloqueio partilhado sobre a linha-mãe a cada inserção —
     * um operador a conferir um contentor parava as vendas, o POS e tudo o mais
     * da empresa enquanto o lote não terminasse. O bloqueio nomeado só trava
     * outra movimentação em lote da MESMA empresa, que é exactamente o que se
     * quer travar.
     */
    public static function comLoteReservado(?int $tenantId, callable $accao)
    {
        $tenantId = $tenantId ?: activeTenantId();
        $nome     = "soserp:mov:{$tenantId}";

        // 10s a esperar: uma movimentação normal demora menos de um segundo.
        // O `?: 0` cobre o NULL que o MySQL devolve em caso de erro.
        $obtido = (int) (\Illuminate\Support\Facades\DB::selectOne(
            'SELECT GET_LOCK(?, 10) AS obtido', [$nome]
        )->obtido ?? 0);

        if (!$obtido) {
            throw new \RuntimeException(
                'Já existe outra movimentação de stock a ser registada nesta empresa. Tente daqui a instantes.'
            );
        }

        try {
            return $accao(static::proximaReferenciaLote($tenantId));
        } finally {
            \Illuminate\Support\Facades\DB::selectOne('SELECT RELEASE_LOCK(?)', [$nome]);
        }
    }

    /**
     * O próximo número da sequência desta empresa.
     *
     * Só é seguro sob o bloqueio de `comLoteReservado()` — sozinho não impede
     * que dois pedidos leiam o mesmo máximo.
     */
    protected static function proximaReferenciaLote(int $tenantId): string
    {
        $prefixo = 'MOV/' . now()->year . '/';

        // `max()` e não um ORDER BY calculado: com o índice (tenant_id,
        // batch_reference) isto resolve-se no índice, enquanto o
        // `ORDER BY CAST(SUBSTRING_INDEX(...))` obrigava a ordenar em memória
        // todos os movimentos do ano — e a fazê-lo com o bloqueio na mão.
        //
        // A comparação é de texto, o que só é equivalente à numérica enquanto o
        // enchimento tiver largura fixa. Aos seis dígitos isso dá 999.999 lotes
        // por ano e por empresa; o `str_pad` abaixo deixa de encher a partir daí
        // e a ordenação passaria a mentir. Está muito longe de qualquer uso real,
        // mas fica dito.
        $ultima = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_reference', 'like', $prefixo . '%')
            ->max('batch_reference');

        $proximo = $ultima
            ? ((int) substr($ultima, strlen($prefixo))) + 1
            : 1;

        return $prefixo . str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
    }

    /** Movimentos de um lote, pela ordem em que foram registados. */
    public function scopeDoLote($query, string $referencia, ?int $tenantId = null)
    {
        return $query
            ->where('tenant_id', $tenantId ?: activeTenantId())
            ->where('batch_reference', $referencia)
            ->orderBy('id');
    }

    /**
     * A morada do papel de um lote — `pdf` descarrega, `preview` abre no browser.
     *
     * UM SÍTIO SÓ PARA A MORADA, e com a mesma regra da rota: a rota só aceita
     * letras, algarismos, `/`, `_` e `-` na referência (o `where` que deixa as
     * barras do MOV/AAAA/NNNNNN passar). Uma referência antiga com outro
     * carácter daria uma ligação que nunca corresponde — um 404 com cara de
     * documento perdido. Sem referência, ou com uma que a rota não apanha, não
     * há ligação nenhuma, e o ecrã mostra só o texto.
     */
    public static function moradaDoLote(?string $referencia, string $papel = 'pdf'): ?string
    {
        if ($referencia === null || !preg_match('#^[A-Za-z0-9/_-]+$#', $referencia)) {
            return null;
        }

        return '/invoicing/stock/movimentacao/' . $referencia . '/' . ($papel === 'preview' ? 'preview' : 'pdf');
    }

    public static function createTransfer($fromWarehouseId, $toWarehouseId, $productId, $quantity, $notes = null)
    {
        return static::create([
            'tenant_id' => activeTenantId(),
            'warehouse_id' => $fromWarehouseId,
            'product_id' => $productId,
            'type' => self::TYPE_TRANSFER,
            'quantity' => $quantity,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'user_id' => auth()->id(),
            'notes' => $notes,
        ]);
    }

    public static function createAdjustment($warehouseId, $productId, $newQuantity, $notes = null)
    {
        return static::create([
            'tenant_id' => activeTenantId(),
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'type' => self::TYPE_ADJUSTMENT,
            'quantity' => $newQuantity,
            'user_id' => auth()->id(),
            'notes' => $notes,
        ]);
    }
}
