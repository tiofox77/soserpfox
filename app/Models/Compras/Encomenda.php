<?php

namespace App\Models\Compras;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Encomenda ao fornecedor (ENC).
 *
 * Documento comercial, NÃO fiscal: sem série, sem hash SAFT-AO, sem AGT. O
 * número é por empresa, ENC-AAAA-NNNNNN. Encomendar não mexe no stock — quem
 * mexe é a recepção. Ver a migração para o porquê.
 */
class Encomenda extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'compras_encomendas';

    protected $fillable = [
        'tenant_id',
        'numero',
        'supplier_id',
        'warehouse_id',
        'requisicao_id',
        'data_encomenda',
        'entrega_prevista',
        'estado',
        'ronda_aprovacao',
        'aprovada_em',
        'motivo_recusa',
        'subtotal',
        'desconto',
        'imposto',
        'total',
        'moeda',
        'cambio',
        'notas',
        'condicoes',
        'purchase_invoice_id',
        'created_by',
    ];

    protected $casts = [
        'data_encomenda' => 'date',
        'entrega_prevista' => 'date',
        'aprovada_em' => 'datetime',
        'subtotal' => 'decimal:2',
        'desconto' => 'decimal:2',
        'imposto' => 'decimal:2',
        'total' => 'decimal:2',
        'cambio' => 'decimal:4',
    ];

    public const ESTADOS = [
        'rascunho' => 'Rascunho',
        'em_aprovacao' => 'À espera de aprovação',
        'aprovada' => 'Aprovada',
        'enviada' => 'Enviada',
        'confirmada' => 'Confirmada',
        'parcial' => 'Recebida em parte',
        'recebida' => 'Recebida',
        'cancelada' => 'Cancelada',
    ];

    /** Estados em que a mercadoria ainda pode chegar. */
    public const ABERTOS = ['enviada', 'confirmada', 'parcial'];

    /**
     * Estados em que já se pode pedir o pagamento: a encomenda está definida
     * (aprovada, ou enviada ao fornecedor quando a empresa não exige
     * aprovação). Num rascunho o preço ainda pode mudar.
     */
    public const PAGAVEIS = ['aprovada', 'enviada', 'confirmada', 'parcial', 'recebida'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $enc) {
            if (empty($enc->numero)) {
                $enc->numero = static::gerarNumero($enc->tenant_id);
            }

            if (empty($enc->warehouse_id)) {
                $armazem = Warehouse::getDefault($enc->tenant_id);
                if ($armazem) {
                    $enc->warehouse_id = $armazem->id;
                }
            }
        });
    }

    /** Número sequencial por empresa: ENC-AAAA-NNNNNN. */
    public static function gerarNumero(?int $tenantId): string
    {
        $prefixo = 'ENC-'.now()->year.'-';

        $ultima = static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('numero', 'like', $prefixo.'%')
            ->orderByDesc('id')
            ->first();

        $proximo = $ultima ? ((int) str_replace($prefixo, '', $ultima->numero)) + 1 : 1;

        return $prefixo.str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
    }

    public function itens()
    {
        return $this->hasMany(EncomendaItem::class, 'encomenda_id')->orderBy('ordem');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requisicao()
    {
        return $this->belongsTo(Requisicao::class, 'requisicao_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function factura()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function pagamentos()
    {
        return $this->hasMany(PedidoDePagamento::class, 'encomenda_id')->orderBy('id');
    }

    /** O que já foi pedido à tesouraria (pedidos à espera, por pagar ou pagos). */
    public function valorPedido(): float
    {
        return round((float) $this->pagamentos()->whereIn('estado', PedidoDePagamento::ACTIVOS)->sum('valor'), 2);
    }

    public function valorPago(): float
    {
        return round((float) $this->pagamentos()->where('estado', 'pago')->sum('valor'), 2);
    }

    /**
     * Quanto se deve ao fornecedor por esta encomenda: o total da factura
     * quando já está emitida (é ela que manda — factura-se o que chegou), senão
     * o total da encomenda.
     */
    public function valorAPagar(): float
    {
        $factura = $this->purchase_invoice_id ? $this->factura : null;

        if ($factura && ! in_array($factura->status, ['draft', 'cancelled'], true)) {
            return round((float) $factura->total, 2);
        }

        return round((float) $this->total, 2);
    }

    public function podeEditar(): bool
    {
        return $this->estado === 'rascunho';
    }

    /** Há mercadoria por receber e a encomenda não está morta. */
    public function podeReceber(): bool
    {
        return in_array($this->estado, self::ABERTOS, true)
            && $this->itens->contains(fn ($i) => $i->porReceber() > 0);
    }

    /** Só se factura o que já chegou, e uma vez só. */
    public function podeFacturar(): bool
    {
        return $this->purchase_invoice_id === null
            && in_array($this->estado, ['parcial', 'recebida'], true)
            && $this->itens->sum(fn ($i) => (float) $i->quantidade_recebida) > 0;
    }

    public function estadoRotulo(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Prometida para trás e ainda por receber por inteiro. */
    public function estaAtrasada(): bool
    {
        return $this->entrega_prevista !== null
            && in_array($this->estado, self::ABERTOS, true)
            && $this->entrega_prevista->isPast();
    }

    /**
     * Recalcula os totais a partir das linhas gravadas.
     *
     * O cabeçalho nunca é escrito à mão: as linhas são a verdade, e é daqui
     * que o total sai sempre.
     */
    public function recalcularTotais(): void
    {
        $this->load('itens');

        $subtotal = (float) $this->itens->sum('subtotal');
        $imposto = (float) $this->itens->sum('imposto');
        $desconto = (float) $this->itens->sum(fn ($i) => $i->descontoValor());

        $this->update([
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'imposto' => $imposto,
            'total' => $subtotal + $imposto,
        ]);
    }
}
