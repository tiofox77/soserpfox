<?php

namespace App\Models\Invoicing;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductBatch extends Model
{
    // O BelongsToTenant faltava aqui, e só aqui: o Warehouse tem-no, a
    // PurchaseInvoice tem-no. Sem ele não há global scope, e um id vindo do
    // cliente chegava a qualquer lote de qualquer empresa. O
    // InterCompanyTransfer já chamava withoutGlobalScopes() neste modelo —
    // contava com um scope que nunca existiu.
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'invoicing_product_batches';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'quantity_available',
        'purchase_invoice_id',
        'supplier_name',
        'cost_price',
        'status',
        'alert_days',
        'notes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'quantity' => 'decimal:2',
        'quantity_available' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    // Relacionamentos
    //
    // O tenant() vem do BelongsToTenant.

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('quantity_available', '>', 0);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', 'active')
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays($days))
            ->whereDate('expiry_date', '>=', Carbon::now());
    }

    public function scopeExpired($query)
    {
        return $query->whereDate('expiry_date', '<', Carbon::now());
    }

    // Accessors

    /**
     * Dias inteiros até à validade. Negativo se já passou.
     *
     * O diffInDays do Carbon 3 devolve um float com as horas lá dentro: um
     * lote que expira daqui a 30 dias dava 29,3958… às duas da tarde. O ecrã
     * mostrava a dízima e a comparação com o alert_days mudava de lado
     * conforme a hora a que se abrisse a página.
     *
     * Comparam-se dias, não instantes — daí o startOfDay dos dois lados.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        return (int) round(
            Carbon::now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false)
        );
    }

    /**
     * Um lote expira no FIM do dia da validade, não no princípio.
     *
     * Este acessor dizia que sim a partir da meia-noite (isAfter sobre uma
     * data que o cast põe às 00:00), enquanto o scopeExpired() usava
     * `expiry_date < hoje` e dizia que não. O mesmo lote aparecia "Expirado"
     * no crachá e ficava de fora da lista de expirados — e o allocateFIFO
     * recusava-o, tirando da venda um produto que ainda estava bom.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->days_until_expiry < 0;
    }

    public function getIsExpiringSoonAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        $daysUntilExpiry = $this->days_until_expiry;
        return $daysUntilExpiry !== null && $daysUntilExpiry <= $this->alert_days && $daysUntilExpiry >= 0;
    }

    public function getStatusColorAttribute()
    {
        if ($this->is_expired) {
            return 'red';
        }
        
        if ($this->is_expiring_soon) {
            return 'orange';
        }
        
        if ($this->quantity_available <= 0) {
            return 'gray';
        }
        
        return 'green';
    }

    public function getStatusLabelAttribute()
    {
        if ($this->is_expired) {
            return 'Expirado';
        }
        
        if ($this->is_expiring_soon) {
            return 'Expira em breve';
        }
        
        if ($this->quantity_available <= 0) {
            return 'Esgotado';
        }
        
        return 'Ativo';
    }

    // Métodos

    /**
     * Só a coluna do estado é gravada.
     *
     * Um $this->save() aqui regravaria a linha inteira a partir da memória, e
     * isso desfazia o cuidado que o decreaseQuantity acabou de ter: a
     * quantidade voltava a ser escrita com o valor que este objecto tem em
     * mãos, que pode já estar velho.
     */
    public function updateStatus()
    {
        if ($this->is_expired) {
            $novo = 'expired';
        } elseif ($this->quantity_available <= 0) {
            $novo = 'sold_out';
        } else {
            $novo = 'active';
        }

        if ($this->status !== $novo) {
            $this->status = $novo;

            static::withoutGlobalScopes()
                ->whereKey($this->getKey())
                ->update(['status' => $novo]);
        }
    }

    /**
     * Tira quantidade do lote — na base de dados, não em memória.
     *
     * Isto era `$this->quantity_available -= $amount; $this->save()`: lia o
     * disponível para memória, subtraía e regravava a linha inteira. Dois
     * pedidos que leiam 100 e tirem 10 cada gravam ambos 90 — a segunda
     * escrita apaga a primeira e as unidades desaparecem sem deixar rasto.
     * Num POS com vários caixas a vender o mesmo artigo, isto acontece.
     *
     * A subtracção passa a ser feita pelo SQL, condicionada ao que lá está:
     * se entretanto alguém esvaziou o lote, o UPDATE não afecta linha nenhuma
     * e a operação falha em vez de vender o que já não existe.
     *
     * (A regra de nunca usar increment/decrement vale para invoicing_stocks,
     * onde o agregado é derivado das linhas. Aqui o quantity_available É a
     * fonte da verdade — não há observer nem agregado a manter.)
     */
    public function decreaseQuantity($amount)
    {
        $amount = (float) $amount;

        $afectadas = static::withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->where('quantity_available', '>=', $amount)
            ->update([
                'quantity_available' => DB::raw('quantity_available - ' . $amount),
            ]);

        if ($afectadas === 0) {
            throw new \Exception('Quantidade insuficiente no lote');
        }

        $this->refresh();
        $this->updateStatus();

        return $this;
    }

    public function increaseQuantity($amount)
    {
        $amount = (float) $amount;

        static::withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->update([
                'quantity_available' => DB::raw('quantity_available + ' . $amount),
            ]);

        $this->refresh();
        $this->updateStatus();

        return $this;
    }
}
