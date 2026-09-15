<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrderItem extends Model
{
    use HasFactory;

    protected $table = 'workshop_work_order_items';

    protected $fillable = [
        'work_order_id',
        'service_id',
        'product_id',
        'invoice_id',
        'type',
        'code',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'hours',
        'mechanic_id',
        'part_number',
        'brand',
        'is_original',
        // OF-03: approved | pending | declined — só as aprovadas contam, saem do stock e vão à factura.
        'approval',
        'approval_at',
        'approval_by',
    ];

    public const APROVACOES = ['approved' => 'Aprovada', 'pending' => 'À espera do cliente', 'declined' => 'Recusada'];

    protected $casts = [
        'approval_at' => 'datetime',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hours' => 'decimal:2',
        'is_original' => 'boolean',
    ];

    protected $appends = ['formatted_subtotal'];

    // Relationships
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * O MECÂNICO QUE FEZ ESTA LINHA — o da OFICINA, não o do RH.
     *
     * A caixa de escolha sempre ofereceu `workshop_mechanics` (é a lista de
     * mecânicos que o ecrã carrega), e a relação lia `hr_employees`: o nome
     * que aparecia na linha era o do funcionário com o MESMO NÚMERO, que é
     * outra pessoa. A chave estrangeira apontava para o sítio errado desde o
     * início — a da própria ordem foi corrigida em 2025-11-05 e esta ficou.
     */
    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class, 'mechanic_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }

    // Accessors
    public function getFormattedSubtotalAttribute()
    {
        return number_format($this->subtotal, 2, ',', '.') . ' Kz';
    }

    // Methods
    public function isService()
    {
        return $this->type === 'service';
    }

    public function isPart()
    {
        return $this->type === 'part';
    }

    /** Calcula desconto e subtotal em memória (não grava). */
    public function aplicarSubtotal()
    {
        $baseAmount = (float) $this->quantity * (float) $this->unit_price;

        if ($this->discount_percent > 0) {
            $this->discount_amount = $baseAmount * ((float) $this->discount_percent / 100);
        }

        $this->subtotal = $baseAmount - (float) $this->discount_amount;
    }

    protected static function booted()
    {
        // O subtotal é calculado ANTES de gravar.
        //
        // Antes era calculado DEPOIS, nos eventos `created`/`updated`, e cada um
        // chamava $this->save() outra vez. Como o Eloquent só sincroniza o
        // `original` DEPOIS de disparar `updated`, o item continuava a parecer
        // "sujo" dentro do próprio evento e voltava a gravar — recursão
        // infinita. Na prática, adicionar uma peça ou um serviço a uma Ordem de
        // Serviço ficava pendurado até o PHP esgotar tempo/memória.
        static::saving(function ($item) {
            $item->aplicarSubtotal();
        });

        // Totais da OS: é outro modelo, não reentra.
        static::saved(function ($item) {
            $item->workOrder?->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->workOrder?->calculateTotals();
        });

        // OF-12: a linha recusada fica guardada na viatura (e sai se a decisão voltar atrás).
        static::created(function ($item) {
            if ($item->approval === 'declined') {
                \App\Services\Workshop\RecomendacoesAdiadas::decisaoMudou($item);
            }
        });
        static::updated(function ($item) {
            if ($item->wasChanged('approval')) {
                \App\Services\Workshop\RecomendacoesAdiadas::decisaoMudou($item);
            }
        });
    }
}
