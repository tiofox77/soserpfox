<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant;
use App\Models\HR\Employee;
use App\Traits\HasTenantNumber;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, HasTenantNumber;

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

    /**
     * @return array Peças que NÃO conseguiram sair do stock (vazio = tudo bem).
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'warranty_expires' => now()->addDays($this->warranty_days),
        ]);

        // Processar baixa de estoque automaticamente
        return $this->processStockMovement() ?: [];
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
            // Trancar a OS e reconfirmar dentro da transacção: dois cliques no
            // botão de facturar chegavam aqui os dois com invoice_id ainda a
            // NULL e emitiam duas facturas para a mesma OS.
            $bloqueada = static::where('id', $this->id)->lockForUpdate()->first();
            if ($bloqueada && $bloqueada->invoice_id) {
                throw new \Exception('Esta ordem de serviço já foi faturada. Fatura: ' . $bloqueada->invoice->invoice_number);
            }

            // Usar cliente vinculado ao veículo ou criar/buscar baseado no proprietário
            $client = null;
            if ($this->vehicle->client_id) {
                // Com scope de empresa: um id de outra empresa devolvia o cliente
                // dela, ou null — e o `$client->id` a seguir rebentava.
                $client = \App\Models\Client::where('tenant_id', $this->tenant_id)
                    ->find($this->vehicle->client_id);
            }

            if (!$client) {
                // Procurar pelo NIF — é a identidade fiscal e é ela que tem
                // índice único (tenant_id, nif). Procurar pelo EMAIL, como
                // antes, nunca encontrava nada nos veículos sem email (a maioria)
                // e o firstOrCreate tentava criar mais um cliente com o NIF
                // genérico 999999999, rebentando com "Duplicate entry" — ou
                // seja, facturar uma OS de um cliente de passagem falhava sempre.
                $nif = $this->vehicle->owner_nif ?: '999999999';

                $client = \App\Models\Client::where('tenant_id', $this->tenant_id)
                    ->where('nif', $nif)
                    ->first();

                if (!$client && $this->vehicle->owner_email) {
                    $client = \App\Models\Client::where('tenant_id', $this->tenant_id)
                        ->where('email', $this->vehicle->owner_email)
                        ->first();
                }

                if (!$client) {
                    $client = \App\Models\Client::create([
                        'tenant_id'   => $this->tenant_id,
                        'name'        => $this->vehicle->owner_name ?: 'Consumidor Final',
                        'email'       => $this->vehicle->owner_email,
                        'phone'       => $this->vehicle->owner_phone,
                        'nif'         => $nif,
                        'address'     => $this->vehicle->owner_address ?? '',
                        // A coluna é `type`, ENUM('pessoa_fisica','pessoa_juridica').
                        // 'client_type' não existe e era descartado em silêncio.
                        // Importa: é este campo que decide se há retenção de IRT.
                        'type' => 'pessoa_fisica',
                        'status'      => 'active',
                    ]);
                }

                // Vincular cliente ao veículo para próximas vezes
                $this->vehicle->update(['client_id' => $client->id]);
            }
            
            // Obter warehouse padrão
            $warehouse = \App\Models\Invoicing\Warehouse::getDefault($this->tenant_id);
            if (!$warehouse) {
                throw new \Exception('Nenhum armazém padrão configurado.');
            }

            $invoice = $this->emitirFactura($client, $warehouse);

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
     * Entrega as linhas da OS ao emissor fiscal partilhado.
     *
     * Toda a fiscalidade (imposto por linha, isenções, retenção de IRT,
     * descontos, totais SAFT, hash) vive em ModuleInvoiceService — a mesma que
     * a facturação usa. A cópia que existia aqui fixava IVA a 14%, retinha IRT
     * também sobre as peças, perdia os descontos de linha e deixava
     * net_total/gross_total a zero num documento assinado.
     */
    protected function emitirFactura(\App\Models\Client $client, $warehouse)
    {
        $servico = app(\App\Services\Invoicing\ModuleInvoiceService::class);

        $linhas = [];

        foreach ($this->items as $item) {
            $ehServico = $item->type === 'service';

            // A AGT exige artigo do catálogo na linha — o mesmo vínculo que os
            // clientes já têm. Uma peça traz o seu produto; a mão-de-obra passa
            // a ter (e reutilizar) um artigo de serviço próprio.
            $productId = $item->product_id;

            if (!$productId) {
                $productId = $servico->produtoDoCatalogo(
                    $this->tenant_id,
                    $item->name,
                    (float) $item->unit_price,
                    $ehServico ? 'service' : 'product'
                )->id;

                // Guardar o vínculo para as próximas facturas desta linha
                $item->update(['product_id' => $productId]);
            }

            $linhas[] = [
                'product_id'       => $productId,
                'name'             => $item->name,
                'quantity'         => (float) $item->quantity,
                'unit_price'       => (float) $item->unit_price,
                'discount_percent' => (float) ($item->discount_percent ?? 0),
                'is_service'       => $ehServico,
            ];
        }

        return $servico->emitir([
            'tenant_id'           => $this->tenant_id,
            'client_id'           => $client->id,
            'warehouse_id'        => $warehouse->id,
            'lines'               => $linhas,
            'discount_commercial' => (float) ($this->discount ?? 0),
            // Retenção de IRT: só quando o adquirente é PESSOA COLECTIVA.
            //
            // Quem retém os 6,5% sobre prestação de serviços é o cliente, e só
            // quando está obrigado a isso — uma empresa. Um particular que
            // manda arranjar o carro não retém nada. A oficina aplicava-a a
            // toda a gente, pelo que ao cliente particular era descontado do
            // documento um imposto que ninguém ia entregar ao Estado: a oficina
            // recebia 6,5% a menos do que devia.
            'retencao_irt'        => $client->type === 'pessoa_juridica',
            'origem_modulo'       => 'oficina',
            'origem'              => $this->order_number,
            'notes'               => "Fatura gerada automaticamente da OS: {$this->order_number}\n"
                . "Veículo: {$this->vehicle->plate} - {$this->vehicle->brand} {$this->vehicle->model}",
        ]);
    }

    /** @deprecated Substituído por emitirFactura(); mantido só como referência histórica. */
    
    /**
     * Processar baixa automática de estoque para peças usadas
     * Cria movimentos de saída (TYPE_OUT) para cada item com product_id
     */
    public function processStockMovement()
    {
        // Peças com produto associado, agregadas por produto: duas linhas da
        // mesma peça davam só um movimento, porque a verificação de duplicado
        // abaixo é por produto — a segunda linha nunca saía do stock.
        $porProduto = $this->parts()->whereNotNull('product_id')->get()
            ->groupBy('product_id');

        if ($porProduto->isEmpty()) {
            return []; // Nenhuma peça para processar
        }

        $warehouse = $this->armazemDeBaixa();

        if (!$warehouse) {
            \Log::warning("Workshop: Nenhum armazém encontrado para baixa de estoque. OS: {$this->order_number}");
            return ['Nenhum armazém configurado para dar baixa das peças.'];
        }

        $falhas = [];

        foreach ($porProduto as $productId => $linhas) {
            try {
                // Já processado? (chave: esta OS + este produto)
                $existingMovement = \App\Models\Invoicing\StockMovement::where('reference_type', 'WorkOrder')
                    ->where('reference_id', $this->id)
                    ->where('product_id', $productId)
                    ->where('type', \App\Models\Invoicing\StockMovement::TYPE_OUT)
                    ->exists();

                if ($existingMovement) {
                    continue; // Já processado, pular
                }

                $quantidade = (float) $linhas->sum('quantity');
                $custo = (float) $linhas->sum('subtotal');
                $nomes = $linhas->pluck('name')->unique()->implode(', ');

                // Movimento e baixa TÊM de ser atómicos.
                //
                // O hook que desconta o stock é o `created` do StockMovement,
                // ou seja corre DEPOIS do insert. Sem transacção, quando
                // Stock::removeStock() rebentava por falta de stock, a linha do
                // movimento ficava na mesma gravada: a verificação de duplicado
                // acima passava a saltar esta OS para sempre (a peça nunca mais
                // saía) e, pior, um cancelamento posterior via
                // returnStockMovement() criava a entrada de devolução —
                // INFLACIONANDO o stock com peças que nunca chegaram a sair.
                \DB::transaction(function () use ($warehouse, $productId, $quantidade, $custo, $nomes) {
                    \App\Models\Invoicing\StockMovement::createExit([
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $productId,
                        'quantity' => $quantidade,
                        'unit_cost' => $quantidade > 0 ? $custo / $quantidade : 0,
                        'total_cost' => $custo,
                        'reference_type' => 'WorkOrder',
                        'reference_id' => $this->id,
                        'notes' => "Baixa automática - OS: {$this->order_number} - {$nomes}",
                    ]);
                });

                \Log::info("Workshop: Estoque baixado - Produto ID: {$productId}, Qtd: {$quantidade}, OS: {$this->order_number}");

            } catch (\Exception $e) {
                // Devolvido ao chamador: antes só ia para o log e o ecrã dizia
                // "Estoque baixado automaticamente" mesmo quando nada saiu.
                $nome = $linhas->first()->name ?? "produto #{$productId}";
                $falhas[] = "{$nome}: {$e->getMessage()}";
                \Log::error("Workshop: Erro ao processar baixa de estoque - OS: {$this->order_number}, Produto ID: {$productId}, Erro: {$e->getMessage()}");
            }
        }

        return $falhas;
    }

    /**
     * Devolve ao stock as peças já baixadas (OS cancelada ou apagada).
     *
     * Sem isto, cancelar uma OS depois de concluída deixava as peças fora do
     * stock para sempre — o inventário ficava permanentemente abaixo do real.
     */
    public function returnStockMovement()
    {
        $saidas = \App\Models\Invoicing\StockMovement::where('reference_type', 'WorkOrder')
            ->where('reference_id', $this->id)
            ->where('type', \App\Models\Invoicing\StockMovement::TYPE_OUT)
            ->get();

        if ($saidas->isEmpty()) {
            return; // Nada tinha saído
        }

        foreach ($saidas as $saida) {
            // Já devolvido? (entrada com a mesma referência e produto)
            $jaDevolvido = \App\Models\Invoicing\StockMovement::where('reference_type', 'WorkOrder')
                ->where('reference_id', $this->id)
                ->where('product_id', $saida->product_id)
                ->where('type', \App\Models\Invoicing\StockMovement::TYPE_IN)
                ->exists();

            if ($jaDevolvido) {
                continue;
            }

            try {
                \App\Models\Invoicing\StockMovement::createEntry([
                    'warehouse_id' => $saida->warehouse_id,
                    'product_id'   => $saida->product_id,
                    'quantity'     => $saida->quantity,
                    'unit_cost'    => $saida->unit_cost,
                    'total_cost'   => $saida->total_cost,
                    'reference_type' => 'WorkOrder',
                    'reference_id'   => $this->id,
                    'notes' => "Devolução ao stock - OS {$this->order_number} anulada",
                ]);

                \Log::info("Workshop: Estoque devolvido - Produto ID: {$saida->product_id}, Qtd: {$saida->quantity}, OS: {$this->order_number}");
            } catch (\Exception $e) {
                \Log::error("Workshop: Erro ao devolver estoque - OS: {$this->order_number}, Produto ID: {$saida->product_id}, Erro: {$e->getMessage()}");
            }
        }
    }

    /**
     * Armazém de onde as peças saem.
     *
     * Tem de ser o MESMO que a factura usa (Warehouse::getDefault), senão a
     * peça saía de um armazém e a factura descontava noutro. O `->first()`
     * que aqui estava devolvia o armazém de menor id, que muitas vezes não é
     * o predefinido nem sequer está activo.
     */
    protected function armazemDeBaixa()
    {
        return \App\Models\Invoicing\Warehouse::getDefault($this->tenant_id)
            ?: \App\Models\Invoicing\Warehouse::where('tenant_id', $this->tenant_id)
                ->where('is_active', true)
                ->first();
    }
}
