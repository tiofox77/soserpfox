<?php

namespace App\Services\Restaurant;

use App\Models\Product;
use App\Models\Invoicing\PosShift;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderEvent;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Services\Invoicing\TaxResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RestaurantOrderService
{
    public function open(array $data, int $tenantId, ?int $userId): Order
    {
        return DB::transaction(function () use ($data, $tenantId, $userId) {
            $venue = Venue::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->find($data['venue_id'] ?? null);
            if (!$venue) {
                throw new InvalidArgumentException('Estabelecimento inválido para esta empresa.');
            }

            // Idempotência vem antes da validação da mesa: num retry legítimo a
            // mesa já está ocupada precisamente pela comanda criada na 1.ª chamada.
            $localUuid = $data['local_uuid'] ?? (string) Str::uuid();
            $existing = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('local_uuid', $localUuid)
                ->first();
            if ($existing) {
                return $existing;
            }

            $table = null;
            if (!empty($data['table_id'])) {
                $table = DiningTable::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('venue_id', $venue->id)
                    ->lockForUpdate()
                    ->find($data['table_id']);
                if (!$table || !$table->is_active || in_array($table->status, ['blocked', 'cleaning'], true)) {
                    throw new InvalidArgumentException('A mesa não está disponível para atendimento.');
                }
                $hasOpenOrder = Order::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('table_id', $table->id)
                    ->whereIn('status', Order::OPEN_STATUSES)
                    ->exists();
                if ($hasOpenOrder) {
                    throw new InvalidArgumentException('A mesa já possui uma comanda aberta.');
                }
            }

            $settings = RestaurantSettings::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();
            if (!$settings) {
                RestaurantSettings::forTenant($tenantId);
                $settings = RestaurantSettings::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            if (!PosShift::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('user_id', $userId)->whereNull('deleted_at')->where('status', 'open')->exists()) {
                throw new InvalidArgumentException('Abra o turno e o caixa antes de iniciar vendas no restaurante.');
            }
            // O CONTADOR NÃO PODE ANDAR ATRÁS DO QUE JÁ FOI USADO.
            //
            // `next_order_number` é a sugestão; a verdade são as comandas que
            // existem. Um contador reposto (o `bancada:producao` põe-no a 1, e
            // uma definição recriada também) dava um CMD que já existia: a
            // chave única recusava, a transacção desfazia-se e o contador
            // nunca avançava — uma comanda feita sem rede ficava a voltar à
            // fila para sempre (#76 em produção, 2026-09-13). Retoma-se depois
            // do maior número usado, dentro da mesma tranca das definições.
            $maiorUsado = (int) Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('order_number', 'like', 'CMD-%')
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(order_number, '-', -1) AS UNSIGNED)) AS maior")
                ->value('maior');
            $number = max((int) $settings->next_order_number, $maiorUsado + 1, 1);
            $settings->update(['next_order_number' => $number + 1]);

            $canal = $table ? 'table' : ($data['channel'] ?? 'counter');

            if (! array_key_exists($canal, Order::CANAIS)) {
                throw new InvalidArgumentException("Canal de venda desconhecido: {$canal}.");
            }

            // UMA ENTREGA SEM MORADA NÃO CHEGA A LADO NENHUM.
            //
            // Vale a pena recusar aqui e não só no ecrã: a comanda também
            // entra pela API e pelo PWA, e uma entrega registada sem destino
            // só se descobre quando o estafeta está à porta a perguntar para
            // onde vai. O telefone é a única forma de a desfazer.
            if ($canal === 'delivery') {
                foreach (['delivery_address' => 'a morada', 'customer_phone' => 'o telefone'] as $campo => $nome) {
                    if (trim((string) ($data[$campo] ?? '')) === '') {
                        throw new InvalidArgumentException("Uma entrega precisa de {$nome} do cliente.");
                    }
                }
            }

            // No take-away basta o telefone: é por ele que se avisa que está
            // pronto, e é por ele que se encontra o dono do saco no balcão.
            if ($canal === 'takeaway' && trim((string) ($data['customer_phone'] ?? '')) === '') {
                throw new InvalidArgumentException('Um take-away precisa do telefone do cliente.');
            }

            $order = Order::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'venue_id' => $venue->id,
                'table_id' => $table?->id,
                'client_id' => $data['client_id'] ?? null,
                'waiter_id' => $userId,
                'order_number' => sprintf('CMD-%s-%06d', now()->format('Y'), $number),
                'local_uuid' => $localUuid,
                'channel' => $canal,
                'status' => 'draft',
                'guest_count' => max(1, (int) ($data['guest_count'] ?? 1)),
                'notes' => $data['notes'] ?? null,
                'customer_name' => trim((string) ($data['customer_name'] ?? '')) ?: null,
                'customer_phone' => trim((string) ($data['customer_phone'] ?? '')) ?: null,
                'delivery_address' => $canal === 'delivery'
                    ? trim((string) $data['delivery_address'])
                    : null,
                'delivery_fee' => $canal === 'delivery'
                    ? max(0, (float) ($data['delivery_fee'] ?? 0))
                    : 0,
            ]);

            if ($table) {
                $table->update(['status' => 'occupied']);
            }

            // A taxa de entrega já conta no total antes de haver pratos: quem
            // abre a comanda tem de ver o que já está a cobrar. Sem isto, o
            // total ficava a zero até se juntar o primeiro artigo.
            if ((float) $order->delivery_fee > 0) {
                $this->recalculate($order, $tenantId);
                $order->refresh();
            }

            $this->event($order, 'order_opened', null, ['channel' => $order->channel], $userId);

            return $order->fresh(['table', 'venue', 'items']);
        });
    }

    public function addItem(Order $order, int $productId, float $quantity, ?string $notes, int $tenantId, ?int $userId): OrderItem
    {
        if ($order->tenant_id !== $tenantId || !in_array($order->status, ['draft', 'confirmed', 'in_preparation', 'ready', 'served'], true)) {
            throw new InvalidArgumentException('A comanda já não permite adicionar artigos.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A quantidade deve ser superior a zero.');
        }

        $product = Product::where('tenant_id', $tenantId)->where('is_active', true)->find($productId);
        if (!$product) {
            throw new InvalidArgumentException('Artigo inválido para esta empresa.');
        }
        $settings = RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        if ($settings?->require_recipe_for_products && !\App\Models\Restaurant\Recipe::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('product_id', $product->id)->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Este prato precisa de uma ficha técnica ativa antes de ser vendido.');
        }

        return DB::transaction(function () use ($order, $product, $quantity, $notes, $tenantId, $userId) {
            $tax = TaxResolver::forProductId($product->id, $tenantId);
            $unitPrice = (float) $product->price;
            $base = round($quantity * $unitPrice, 2);
            $taxAmount = round($base * ((float) $tax['rate'] / 100), 2);

            $item = OrderItem::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit' => $product->unit ?: 'UN',
                'unit_price' => $unitPrice,
                'tax_rate' => $tax['rate'],
                'tax_amount' => $taxAmount,
                'line_total' => $base + $taxAmount,
                'kitchen_status' => 'draft',
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->recalculate($order, $tenantId);
            $this->event($order, 'item_added', $item, [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ], $userId);

            return $item;
        });
    }

    public function removeItem(OrderItem $item, int $tenantId, ?int $userId, string $reason): void
    {
        $order = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($item->order_id);
        if ($item->tenant_id !== $tenantId || $item->kitchen_status !== 'draft'
            || !in_array($order->status, ['draft', 'confirmed', 'in_preparation', 'ready', 'served'], true)) {
            throw new InvalidArgumentException('O artigo já foi confirmado. Registe um cancelamento/desperdício em vez de o apagar.');
        }

        DB::transaction(function () use ($item, $order, $tenantId, $userId, $reason) {
            $snapshot = $item->only(['id', 'product_id', 'product_name', 'quantity', 'line_total']);
            $item->delete();
            $this->recalculate($order, $tenantId);
            $this->event($order, 'item_removed', null, $snapshot + ['reason' => $reason], $userId);
        });
    }

    public function updateItemQuantity(OrderItem $item, float $quantity, int $tenantId, ?int $userId): OrderItem
    {
        $order = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($item->order_id);
        if ($item->tenant_id !== $tenantId || $item->kitchen_status !== 'draft'
            || !in_array($order->status, ['draft', 'confirmed', 'in_preparation', 'ready', 'served'], true)) {
            throw new InvalidArgumentException('A quantidade só pode ser alterada antes do envio do artigo à cozinha.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A quantidade deve ser superior a zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $order, $tenantId, $userId) {
            $tax = TaxResolver::forProductId($item->product_id, $tenantId);
            $base = round($quantity * (float) $item->unit_price, 2);
            $discount = round($base * ((float) $item->discount_percent / 100), 2);
            $taxable = max(0, $base - $discount);
            $taxAmount = round($taxable * ((float) $tax['rate'] / 100), 2);
            $before = (float) $item->quantity;

            $item->update([
                'quantity' => $quantity,
                'discount_amount' => $discount,
                'tax_rate' => $tax['rate'],
                'tax_amount' => $taxAmount,
                'line_total' => $taxable + $taxAmount,
            ]);
            $this->recalculate($order, $tenantId);
            $this->event($order, 'item_quantity_updated', $item, [
                'from' => $before, 'to' => $quantity, 'tax_rate' => $tax['rate'],
            ], $userId);

            return $item->fresh();
        });
    }

    public function changeTableStatus(DiningTable $table, string $status, int $tenantId): DiningTable
    {
        if ($table->tenant_id !== $tenantId || !in_array($status, ['available', 'reserved', 'cleaning', 'blocked'], true)) {
            throw new InvalidArgumentException('Estado da mesa inválido.');
        }

        return DB::transaction(function () use ($table, $status, $tenantId) {
            $table = DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($table->id);
            if ($table->activeOrder()->exists()) {
                throw new InvalidArgumentException('A mesa possui uma comanda aberta e não pode mudar de estado manualmente.');
            }
            $table->update(['status' => $status]);
            return $table->fresh();
        });
    }

    public function confirm(Order $order, int $tenantId, ?int $userId): Order
    {
        if ($order->tenant_id !== $tenantId || !in_array($order->status, ['draft', 'confirmed', 'in_preparation', 'ready', 'served'], true)) {
            throw new InvalidArgumentException('Esta comanda já não permite enviar artigos à cozinha.');
        }
        if (!$order->items()->where('kitchen_status', 'draft')->exists()) {
            if ($order->status === 'confirmed') {
                throw new InvalidArgumentException('Não existem novos artigos para enviar à cozinha.');
            }
            throw new InvalidArgumentException('Adicione pelo menos um artigo antes de confirmar.');
        }

        return DB::transaction(function () use ($order, $tenantId, $userId) {
            $settings = RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();
            $order->items()->where('kitchen_status', 'draft')->update(['kitchen_status' => 'queued']);
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            if (!(bool) ($settings->use_kitchen_workflow ?? true)) {
                $order->items()->where('kitchen_status', 'queued')->update(['kitchen_status' => 'served']);
                $order->update(['status' => 'served']);
                if ($order->table_id) {
                    DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($order->table_id)->update(['status' => 'served']);
                }
                if ($settings->consume_stock_on_kitchen) {
                    app(RestaurantStockService::class)->consume($order->fresh('venue'), $tenantId, $userId);
                }
                $this->event($order, 'order_fast_ready', null, ['kitchen' => false], $userId);
                return $order->fresh(['items', 'table', 'venue']);
            }
            if ($order->table_id) {
                DiningTable::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($order->table_id)
                    ->update(['status' => 'waiting_kitchen']);
            }
            $this->event($order, 'order_confirmed', null, [], $userId);
            $order = $order->fresh(['items', 'table', 'venue']);
            app(RestaurantKitchenService::class)->dispatch($order, $tenantId, $userId);
            return $order->fresh(['items', 'table', 'venue']);
        });
    }

    public function releaseTable(Order $order, int $tenantId, ?int $userId): void
    {
        if ($order->tenant_id !== $tenantId || $order->status !== 'billed' || !$order->table_id) {
            throw new InvalidArgumentException('A mesa só pode ser libertada depois do fecho integral da comanda.');
        }
        DB::transaction(function () use ($order, $tenantId, $userId) {
            $hasPending = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('table_id', $order->table_id)
                ->where('id', '!=', $order->id)->whereIn('status', Order::OPEN_STATUSES)->lockForUpdate()->exists();
            if ($hasPending) throw new InvalidArgumentException('A mesa ainda possui outra comanda pendente.');
            DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($order->table_id)->update(['status' => 'available']);
            $this->event($order, 'table_released', null, ['table_id' => $order->table_id], $userId);
        });
    }

    public function transfer(Order $order,int $targetTableId,int $tenantId,?int $userId):Order{return DB::transaction(function()use($order,$targetTableId,$tenantId,$userId){$order=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->whereIn('status',Order::OPEN_STATUSES)->lockForUpdate()->findOrFail($order->id);$target=DiningTable::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('venue_id',$order->venue_id)->where('status','available')->lockForUpdate()->find($targetTableId);if(!$target)throw new InvalidArgumentException('A mesa de destino não está livre.');$old=DiningTable::withoutGlobalScopes()->where('tenant_id',$tenantId)->lockForUpdate()->find($order->table_id);$order->update(['table_id'=>$target->id]);$target->update(['status'=>$old?->status??'occupied']);if($old)$old->update(['status'=>'available']);$this->event($order,'table_transferred',null,['from'=>$old?->id,'to'=>$target->id],$userId);return $order->fresh(['table','items']);});}

    public function merge(Order $source,Order $target,int $tenantId,?int $userId):Order{return DB::transaction(function()use($source,$target,$tenantId,$userId){$source=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->lockForUpdate()->findOrFail($source->id);$target=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->lockForUpdate()->findOrFail($target->id);if($source->id===$target->id||$source->venue_id!==$target->venue_id||!in_array($source->status,Order::OPEN_STATUSES,true)||!in_array($target->status,Order::OPEN_STATUSES,true))throw new InvalidArgumentException('Comandas inválidas para junção.');if($source->items()->where('billed_quantity','>',0)->exists())throw new InvalidArgumentException('Não é possível juntar uma comanda parcialmente faturada.');OrderItem::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('order_id',$source->id)->update(['order_id'=>$target->id]);$sourceTable=$source->table_id;$source->update(['status'=>'cancelled','closed_at'=>now(),'closed_by'=>$userId,'notes'=>trim(($source->notes??'').' Juntada a '.$target->order_number)]);if($sourceTable)DiningTable::withoutGlobalScopes()->where('tenant_id',$tenantId)->whereKey($sourceTable)->update(['status'=>'available']);$this->recalculate($target,$tenantId);$this->event($target,'orders_merged',null,['source_order_id'=>$source->id],$userId);return $target->fresh(['table','items']);});}

    public function voidProducedItem(OrderItem $item,string $reason,int $tenantId,?int $userId):void{if($item->tenant_id!==$tenantId||!in_array($item->kitchen_status,['queued','accepted','preparing','ready'],true))throw new InvalidArgumentException('Este artigo não pode ser anulado por este fluxo.');if(trim($reason)==='')throw new InvalidArgumentException('O motivo é obrigatório.');DB::transaction(function()use($item,$reason,$tenantId,$userId){$item->update(['kitchen_status'=>'voided']);\App\Models\Restaurant\KitchenTicketItem::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('order_item_id',$item->id)->update(['status'=>'voided']);\App\Models\Restaurant\Waste::withoutGlobalScopes()->create(['tenant_id'=>$tenantId,'order_id'=>$item->order_id,'order_item_id'=>$item->id,'quantity'=>$item->quantity,'reason'=>$reason,'user_id'=>$userId]);$order=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->findOrFail($item->order_id);$this->recalculate($order,$tenantId);$this->event($order,'produced_item_voided',$item,['reason'=>$reason,'quantity'=>$item->quantity],$userId);});}

    protected function recalculate(Order $order, int $tenantId): void
    {
        $totals = OrderItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->where('kitchen_status', '!=', 'voided')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) subtotal')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) discount_total')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) tax_total')
            ->selectRaw('COALESCE(SUM(line_total), 0) grand_total')
            ->first();

        // A TAXA DE ENTREGA ENTRA NO TOTAL, e é a única coisa do total que
        // não sai de uma linha. Deixá-la de fora fazia a comanda mostrar um
        // valor e o cliente pagar outro — e a diferença só aparecia ao fechar
        // a caixa, sem ninguém saber de onde vinha.
        $entrega = (float) $order->delivery_fee;

        $order->update([
            'subtotal' => $totals->subtotal,
            'discount_total' => $totals->discount_total,
            'tax_total' => $totals->tax_total,
            'grand_total' => $totals->grand_total + $entrega,
        ]);
    }

    /**
     * A venda para fora saiu para o cliente.
     *
     * Separa «pronto na cozinha» de «já vai a caminho», que é a pergunta que
     * se faz ao telefone e que a cozinha não sabe responder.
     */
    public function despachar(Order $order, int $tenantId, ?int $userId): Order
    {
        if ($order->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Comanda de outra empresa.');
        }

        if (! $order->paraFora()) {
            throw new InvalidArgumentException('Só se despacha um take-away ou uma entrega.');
        }

        if ($order->dispatched_at) {
            return $order;
        }

        $order->update(['dispatched_at' => now(), 'status' => 'served']);
        $this->event($order, 'order_dispatched', null, ['channel' => $order->channel], $userId);

        return $order->fresh();
    }

    protected function event(Order $order, string $event, ?OrderItem $item, array $payload, ?int $userId): void
    {
        OrderEvent::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'user_id' => $userId,
            'event' => $event,
            'payload' => $payload,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
