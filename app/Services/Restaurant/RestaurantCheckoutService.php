<?php

namespace App\Services\Restaurant;

use App\Models\Client;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderEvent;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\OrderItemBilling;
use App\Models\Restaurant\PaymentAttempt;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Models\Invoicing\PosShift;
use App\Services\Invoicing\ModuleInvoiceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestaurantCheckoutService
{
    public function checkout(Order $order, array $data, int $tenantId, ?int $userId)
    {
        $key = (string) ($data['idempotency_key'] ?? '');
        if (!$key) throw new InvalidArgumentException('Chave de idempotência obrigatória.');
        $existing = PaymentAttempt::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
        if ($existing?->sales_invoice_id) return \App\Models\Invoicing\SalesInvoice::withoutGlobalScopes()->findOrFail($existing->sales_invoice_id);

        return DB::transaction(function () use ($order, $data, $tenantId, $userId, $key) {
            if (! PosShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)->whereNull('deleted_at')->where('status', 'open')->exists()) {
                throw new InvalidArgumentException('Abra o turno e o caixa antes de receber ou faturar uma venda do restaurante.');
            }
            $order = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($order->id);
            if (!in_array($order->status, ['served', 'ready', 'partially_billed'], true)) throw new InvalidArgumentException('A comanda ainda não está pronta para faturação.');

            $documentType = strtoupper($data['document_type'] ?? 'FR');
            if (!in_array($documentType, ['FR', 'FT'], true)) throw new InvalidArgumentException('Documento inválido.');
            $client = !empty($data['client_id'])
                ? Client::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($data['client_id'])
                : Client::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenantId, 'nif' => '999999999'], ['type' => 'pessoa_fisica', 'name' => 'Consumidor Final', 'country' => \App\Support\Geografia::PAIS_PADRAO, 'tax_regime' => 'geral', 'is_iva_subject' => false, 'is_active' => true]);
            if (!$client) throw new InvalidArgumentException('Cliente inválido para esta empresa.');

            $attempt = PaymentAttempt::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'idempotency_key' => $key],
                ['order_id' => $order->id, 'document_type' => $documentType, 'status' => 'processing']
            );
            $items = OrderItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_id', $order->id)->where('kitchen_status', '!=', 'voided')->lockForUpdate()->get()
                ->filter(fn ($item) => (float) $item->quantity > (float) $item->billed_quantity);
            if (!empty($data['item_ids'])) $items = $items->whereIn('id', array_map('intval', $data['item_ids']));
            if ($items->isEmpty()) throw new InvalidArgumentException('Todos os artigos desta comanda já foram faturados.');

            $settings = RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

            $linhas = $items->map(fn ($item) => [
                'product_id' => $item->product_id, 'name' => $item->product_name,
                'quantity' => (float) $item->quantity - (float) $item->billed_quantity,
                'unit_price' => (float) $item->unit_price, 'discount_percent' => (float) $item->discount_percent,
                'unit' => $item->unit,
            ])->values()->all();

            // A TAXA DE ENTREGA É UMA LINHA DA FACTURA.
            //
            // Estava no total da comanda e não ia à factura: o cliente pagava
            // uma coisa e o documento dizia outra. Só entra na PRIMEIRA
            // factura da comanda — numa conta dividida, cobrá-la em cada uma
            // seria cobrar o transporte tantas vezes quantas as pessoas.
            if ((float) $order->delivery_fee > 0 && ! $order->deliveryJaFacturada()) {
                $linhas[] = [
                    'product_id' => app(TaxaDeServico::class)->artigoDeEntrega($tenantId)->id,
                    'name' => TaxaDeServico::NOME_ENTREGA,
                    'quantity' => 1,
                    'unit_price' => (float) $order->delivery_fee,
                    'discount_percent' => 0,
                    'unit' => 'UN',
                ];
            }

            // E a TAXA DE SERVIÇO, quando a casa a cobra. Sobre o que está a
            // ser facturado agora, e não sobre a conta toda: numa conta
            // dividida, cada parte paga o serviço da sua parte.
            $baseDaTaxa = collect($linhas)
                ->reject(fn ($l) => $l['name'] === TaxaDeServico::NOME_ENTREGA)
                ->sum(fn ($l) => $l['quantity'] * $l['unit_price'] * (1 - ($l['discount_percent'] ?? 0) / 100));

            if ($settings && ($linhaTaxa = app(TaxaDeServico::class)->linhaPara($order, $settings, $tenantId, (float) $baseDaTaxa))) {
                $linhas[] = $linhaTaxa;
            }

            $invoice = app(ModuleInvoiceService::class)->emitir([
                'tenant_id' => $tenantId, 'client_id' => $client->id, 'warehouse_id' => $settings?->default_warehouse_id,
                'invoice_type' => $documentType, 'status' => $documentType === 'FR' ? 'paid' : 'sent',
                'origem_modulo' => 'restaurant', 'origem' => $order->order_number,
                'notes' => 'Comanda '.$order->order_number,
                'lines' => $linhas,
            ]);

            foreach ($items->values() as $index => $item) {
                $quantity = (float) $item->quantity - (float) $item->billed_quantity;
                $invoiceItem = $invoice->items[$index];
                OrderItemBilling::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId, 'order_id' => $order->id, 'order_item_id' => $item->id,
                    'sales_invoice_id' => $invoice->id, 'sales_invoice_item_id' => $invoiceItem->id,
                    'quantity' => $quantity, 'amount' => $invoiceItem->total,
                ]);
                $item->update(['billed_quantity' => (float) $item->billed_quantity + $quantity]);
            }

            if ($documentType === 'FR') {
                $payments = $data['payments'] ?? [['payment_method_id' => $data['payment_method_id'] ?? null, 'amount' => $invoice->total]];
                $payments = array_values(array_filter($payments, fn ($payment) => (float) ($payment['amount'] ?? 0) > 0));
                if (!$payments) throw new InvalidArgumentException('Indique pelo menos um pagamento válido.');
                $methodIds = array_map(fn ($payment) => (int) ($payment['payment_method_id'] ?? 0), $payments);
                if (count(array_unique($methodIds)) !== count($methodIds)) throw new InvalidArgumentException('O mesmo método não pode aparecer mais de uma vez no pagamento múltiplo.');
                if (abs(collect($payments)->sum('amount') - (float)$invoice->total) > .01) throw new InvalidArgumentException('A soma dos pagamentos deve ser igual ao total desta conta.');
                foreach ($payments as $payment) $this->registerPayment($invoice, $payment, $tenantId, $userId);

                // A GORJETA PASSA PELA CAIXA MAS NÃO PELA FACTURA.
                //
                // Não é venda da casa — é dinheiro do pessoal que entrou pelo
                // balcão. Pô-la na factura era declarar como receita dinheiro
                // que não é da empresa; deixá-la fora da caixa era garantir
                // que o dinheiro contado ao fecho do turno nunca batia com o
                // que o sistema dizia que devia lá estar, e o operador ficava
                // com uma diferença que não sabia explicar.
                $gorjeta = round((float) ($data['tip_amount'] ?? 0), 2);

                if ($gorjeta > 0 && ($settings?->tips_enabled ?? true)) {
                    $this->registarGorjeta($order, $invoice, $gorjeta, $payments[0], $tenantId, $userId);
                }
            }
            $remaining = OrderItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_id', $order->id)->where('kitchen_status', '!=', 'voided')->whereColumn('billed_quantity', '<', 'quantity')->exists();
            $order->update(['status' => $remaining ? 'partially_billed' : 'billed', 'client_id' => $client->id, 'closed_at' => $remaining ? null : now(), 'closed_by' => $remaining ? null : $userId]);
            if (!$remaining && $order->table_id) DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($order->table_id)->update(['status' => 'cleaning']);
            $attempt->update(['sales_invoice_id' => $invoice->id, 'amount' => $invoice->total, 'status' => 'completed']);
            OrderEvent::withoutGlobalScopes()->create(['tenant_id' => $tenantId, 'order_id' => $order->id, 'user_id' => $userId, 'event' => 'order_billed', 'payload' => ['invoice_id' => $invoice->id, 'document_type' => $documentType], 'ip_address' => app()->runningInConsole() ? null : request()->ip()]);
            return $invoice;
        });
    }

    /**
     * A gorjeta: entra na caixa, fica na comanda, não vai à factura.
     *
     * O movimento de tesouraria leva a categoria `tip` para os relatórios a
     * poderem separar o que é venda do que é gorjeta — sem isso, a gorjeta
     * inflava as vendas do dia e ninguém percebia porquê.
     */
    private function registarGorjeta(Order $order, $invoice, float $valor, array $pagamento, int $tenantId, ?int $userId): void
    {
        $method = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('is_active', true)->find($pagamento['payment_method_id'] ?? null);

        if (! $method) {
            return;
        }

        $destination = app(\App\Services\Treasury\TreasuryMovementService::class)->destination($method, $tenantId, userId: $userId);

        app(\App\Services\Treasury\TreasuryMovementService::class)->post([
            'tenant_id' => $tenantId, 'user_id' => $userId,
            'account_id' => $destination['account_id'], 'cash_register_id' => $destination['cash_register_id'],
            'payment_method_id' => $method->id,
            // SEM `invoice_id`: a gorjeta não é desta factura nem de nenhuma.
            // Ligá-la a uma factura fazia o valor recebido passar o valor
            // facturado, e a factura aparecia como paga a mais.
            'type' => 'income',
            'category' => 'tip',
            'amount' => $valor, 'currency' => 'AOA', 'transaction_date' => now(),
            'reference' => $order->order_number,
            'description' => 'Gorjeta - comanda '.$order->order_number,
            'status' => 'completed', 'is_reconciled' => false,
        ]);

        $order->update(['tip_amount' => (float) $order->tip_amount + $valor]);

        OrderEvent::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'order_id' => $order->id, 'user_id' => $userId,
            'event' => 'tip_registered',
            'payload' => ['amount' => $valor, 'payment_method_id' => $method->id],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private function registerPayment($invoice, array $data, int $tenantId, ?int $userId): void
    {
        $method = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->find($data['payment_method_id'] ?? null);
        if (!$method) throw new InvalidArgumentException('Método de pagamento inválido.');
        $destination = app(\App\Services\Treasury\TreasuryMovementService::class)->destination($method, $tenantId, userId: $userId);
        app(\App\Services\Treasury\TreasuryMovementService::class)->post([
            'tenant_id' => $tenantId, 'user_id' => $userId,
            'account_id' => $destination['account_id'], 'cash_register_id' => $destination['cash_register_id'],
            'payment_method_id' => $method->id, 'invoice_id' => $invoice->id,
            'type' => 'income',
            'category' => $method->type === 'cash' ? 'cash' : ($method->type === 'card' ? 'card' : 'bank_transfer'),
            'amount' => (float)$data['amount'], 'currency' => 'AOA', 'transaction_date' => now(),
            'reference' => $invoice->invoice_number, 'description' => 'Recebimento Restaurante - '.$invoice->invoice_number,
            'status' => 'completed', 'is_reconciled' => false,
        ]);
    }
}
