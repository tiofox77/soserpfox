<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant;
use App\Models\HR\Employee;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_work_orders';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'vehicle_id',
        'mechanic_id',
        'received_at',
        'scheduled_for',
        'started_at',
        'completed_at',
        'delivered_at',
        'mileage_in',
        'problem_description',
        'diagnosis',
        'work_performed',
        'recommendations',
        'status',
        'priority',
        'labor_total',
        'parts_total',
        'discount',
        'tax',
        'total',
        'payment_status',
        'paid_amount',
        'warranty_days',
        'warranty_expires',
        'notes',
        'invoice_id',
        'invoiced_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'warranty_expires' => 'date',
        'mileage_in' => 'integer',
        'labor_total' => 'decimal:2',
        'parts_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'warranty_days' => 'integer',
    ];

    protected $appends = [
        'formatted_total',
        'days_in_service',
        'is_overdue',
        'balance_due'
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function mechanic()
    {
        return $this->belongsTo(Employee::class, 'mechanic_id');
    }

    public function items()
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function services()
    {
        return $this->items()->where('type', 'service');
    }

    public function parts()
    {
        return $this->items()->where('type', 'part');
    }
    
    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }
    
    public function history()
    {
        return $this->hasMany(WorkOrderHistory::class)->orderBy('created_at', 'desc');
    }
    
    public function attachments()
    {
        return $this->hasMany(WorkOrderAttachment::class)->orderBy('created_at', 'desc');
    }
    
    public function payments()
    {
        return $this->hasMany(WorkOrderPayment::class)->orderBy('payment_date', 'desc');
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total, 2, ',', '.') . ' Kz';
    }

    public function getDaysInServiceAttribute()
    {
        if (!$this->received_at) return 0;
        
        $endDate = $this->delivered_at ?? now();
        return $this->received_at->diffInDays($endDate);
    }

    public function getIsOverdueAttribute()
    {
        if (!$this->scheduled_for) return false;
        
        return $this->scheduled_for->isPast() && 
               !in_array($this->status, ['completed', 'delivered', 'cancelled']);
    }

    public function getBalanceDueAttribute()
    {
        return $this->total - $this->paid_amount;
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    // Methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    public function calculateTotals()
    {
        $this->labor_total = $this->services()->sum('subtotal');
        $this->parts_total = $this->parts()->sum('subtotal');
        
        $subtotal = $this->labor_total + $this->parts_total;
        $afterDiscount = $subtotal - $this->discount;
        
        $this->total = $afterDiscount + $this->tax;
        $this->save();
    }

    public function markAsInProgress()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'warranty_expires' => now()->addDays($this->warranty_days),
        ]);
        
        // Processar baixa de estoque automaticamente
        $this->processStockMovement();
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function addPayment($amount)
    {
        $this->paid_amount += $amount;
        
        if ($this->paid_amount >= $this->total) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        }
        
        $this->save();
    }
    
    /**
     * Converter Ordem de Serviço em Fatura de Venda
     * Cria uma Sales Invoice com todos os itens da OS
     */
    public function convertToInvoice()
    {
        // Verificar se já foi faturada
        if ($this->invoice_id) {
            throw new \Exception('Esta ordem de serviço já foi faturada. Fatura: ' . $this->invoice->invoice_number);
        }
        
        // Verificar se tem veículo
        if (!$this->vehicle) {
            throw new \Exception('Ordem de serviço sem veículo associado.');
        }
        
        \DB::beginTransaction();
        try {
            // Usar cliente vinculado ao veículo ou criar/buscar baseado no proprietário
            if ($this->vehicle->client_id) {
                $client = \App\Models\Client::find($this->vehicle->client_id);
            } else {
                // Criar ou buscar cliente baseado no proprietário do veículo
                $client = \App\Models\Client::firstOrCreate(
                    [
                        'tenant_id' => $this->tenant_id,
                        'email' => $this->vehicle->owner_email,
                    ],
                    [
                        'name' => $this->vehicle->owner_name,
                        'phone' => $this->vehicle->owner_phone,
                        'nif' => $this->vehicle->owner_nif ?? '999999999', // NIF genérico se não houver
                        'address' => $this->vehicle->owner_address ?? '',
                        'client_type' => 'individual',
                        'status' => 'active',
                    ]
                );
                
                // Vincular cliente ao veículo para próximas vezes
                $this->vehicle->update(['client_id' => $client->id]);
            }
            
            // Obter warehouse padrão
            $warehouse = \App\Models\Invoicing\Warehouse::getDefault($this->tenant_id);
            if (!$warehouse) {
                throw new \Exception('Nenhum armazém padrão configurado.');
            }
            
            // Criar a fatura (número gerado automaticamente no boot)
            $invoice = \App\Models\Invoicing\SalesInvoice::create([
                'tenant_id' => $this->tenant_id,
                'client_id' => $client->id,
                'warehouse_id' => $warehouse->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'status' => 'draft',
                'is_service' => true,
                'discount_amount' => $this->discount ?? 0,
                'discount_commercial' => 0,
                'discount_financial' => 0,
                'notes' => "Fatura gerada automaticamente da OS: {$this->order_number}\nVeículo: {$this->vehicle->plate} - {$this->vehicle->brand} {$this->vehicle->model}",
                'created_by' => auth()->id(),
            ]);
            
            // Copiar itens da OS para a fatura e calcular totais (conforme AGT Angola)
            $order = 0;
            $invoice_subtotal = 0;
            $invoice_tax_amount = 0;
            
            foreach ($this->items as $item) {
                // AGT Angola: Todos itens DEVEM ter product_id
                // Se não tiver, criar/buscar produto genérico
                $productId = $item->product_id;
                
                if (!$productId) {
                    // Buscar ou criar produto genérico para serviço
                    $product = \App\Models\Product::firstOrCreate(
                        [
                            'tenant_id' => $this->tenant_id,
                            'name' => $item->name,
                        ],
                        [
                            'sku' => 'SRV-' . \Str::slug($item->name),
                            'type' => 'service',
                            'price' => $item->unit_price,
                            'track_inventory' => false,
                            'is_active' => true,
                            'description' => 'Serviço de oficina',
                        ]
                    );
                    $productId = $product->id;
                    
                    \Log::info("Workshop: Produto criado automaticamente para AGT - {$item->name} (ID: {$productId})");
                }
                
                // Calcular valores conforme AGT Angola
                $valorBrutoLinha = $item->unit_price * $item->quantity;
                $descontoPercent = $item->discount_percent ?? 0;
                $descontoAmount = $valorBrutoLinha * ($descontoPercent / 100);
                $subtotal = $valorBrutoLinha;
                $valorAposDesconto = $valorBrutoLinha - $descontoAmount;
                $taxRate = 14; // IVA padrão Angola
                $taxAmount = $valorAposDesconto * ($taxRate / 100);
                $total = $valorAposDesconto + $taxAmount;
                
                // Acumular totais
                $invoice_subtotal += $valorBrutoLinha;
                $invoice_tax_amount += $taxAmount;
                
                \App\Models\Invoicing\SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $productId,  // Sempre preenchido ✅
                    'product_name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => 'UN',
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $descontoPercent,
                    'discount_amount' => $descontoAmount,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'order' => ++$order,
                ]);
            }
            
            // Calcular total da fatura conforme AGT Angola
            $desconto_comercial_total = $invoice->discount_commercial + $invoice->discount_amount;
            $valor_apos_desc_comercial = $invoice_subtotal - $desconto_comercial_total;
            $incidencia_iva = $valor_apos_desc_comercial - $invoice->discount_financial;
            
            // IRT 6,5% para serviços (Workshop é serviço)
            $irt_amount = $invoice->is_service ? $incidencia_iva * 0.065 : 0;
            
            $total_final = $incidencia_iva + $invoice_tax_amount - $irt_amount;
            
            // Atualizar totais da fatura
            $invoice->subtotal = $invoice_subtotal;
            $invoice->tax_amount = $invoice_tax_amount;
            $invoice->irt_amount = $irt_amount;
            $invoice->total = $total_final;
            $invoice->save();
            
            // Gerar HASH SAFT-AO conforme regulamento Angola
            $previousInvoice = \App\Models\Invoicing\SalesInvoice::where('tenant_id', $this->tenant_id)
                ->where('id', '<', $invoice->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();
            
            $hash = \App\Helpers\SAFTHelper::generateHash(
                $invoice->invoice_date->format('Y-m-d'),
                $invoice->created_at->format('Y-m-d H:i:s'),
                $invoice->invoice_number,
                $invoice->total,
                $previousInvoice->saft_hash ?? null
            );
            
            if ($hash) {
                $invoice->saft_hash = $hash;
                $invoice->save();
            }
            
            // Vincular fatura à OS
            $this->update([
                'invoice_id' => $invoice->id,
                'invoiced_at' => now(),
            ]);
            
            \DB::commit();
            
            \Log::info("Workshop: OS {$this->order_number} convertida em fatura {$invoice->invoice_number}");
            
            return $invoice;
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Workshop: Erro ao converter OS {$this->order_number} em fatura: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Processar baixa automática de estoque para peças usadas
     * Cria movimentos de saída (TYPE_OUT) para cada item com product_id
     */
    public function processStockMovement()
    {
        // Buscar apenas itens que são peças (têm product_id)
        $parts = $this->parts()->whereNotNull('product_id')->get();
        
        if ($parts->isEmpty()) {
            return; // Nenhuma peça para processar
        }
        
        // Obter warehouse padrão do tenant (primeiro armazém)
        $warehouse = \App\Models\Invoicing\Warehouse::where('tenant_id', $this->tenant_id)
            ->first();
        
        if (!$warehouse) {
            \Log::warning("Workshop: Nenhum armazém encontrado para baixa de estoque. OS: {$this->order_number}");
            return;
        }
        
        foreach ($parts as $item) {
            try {
                // Verificar se já existe movimento para este item
                $existingMovement = \App\Models\Invoicing\StockMovement::where('reference_type', 'WorkOrder')
                    ->where('reference_id', $this->id)
                    ->where('product_id', $item->product_id)
                    ->first();
                
                if ($existingMovement) {
                    continue; // Já processado, pular
                }
                
                // Criar movimento de saída
                \App\Models\Invoicing\StockMovement::createExit([
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_price,
                    'total_cost' => $item->subtotal,
                    'reference_type' => 'WorkOrder',
                    'reference_id' => $this->id,
                    'notes' => "Baixa automática - OS: {$this->order_number} - {$item->name}",
                ]);
                
                \Log::info("Workshop: Estoque baixado - Produto ID: {$item->product_id}, Qtd: {$item->quantity}, OS: {$this->order_number}");
                
            } catch (\Exception $e) {
                \Log::error("Workshop: Erro ao processar baixa de estoque - OS: {$this->order_number}, Produto ID: {$item->product_id}, Erro: {$e->getMessage()}");
            }
        }
    }
}
