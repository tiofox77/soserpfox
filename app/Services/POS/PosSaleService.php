<?php

namespace App\Services\POS;

use App\Helpers\InvoiceCalculationHelper;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PosShift;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\PaymentMethod as TreasuryPaymentMethod;
use App\Models\Treasury\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de criação de vendas POS (Fatura-Recibo) a partir de um payload.
 *
 * Usado pelo endpoint API de sincronização do POS Offline (PWA). Cria uma
 * Fatura-Recibo fiscal completa (com hash SAFT-AO, transação de tesouraria,
 * registo no turno e atualização de stock), de forma IDEMPOTENTE via local_uuid.
 *
 * A lógica espelha App\Livewire\POS\POSSystem::completeSale() para manter
 * coerência fiscal entre vendas online (Livewire) e vendas sincronizadas (PWA).
 */
class PosSaleService
{
    /**
     * Cria (ou devolve, se já existir) uma Fatura-Recibo a partir do payload.
     *
     * @param array $payload  Estrutura validada vinda do PWA
     * @param int   $tenantId Tenant ativo
     * @param int   $userId   Operador (created_by / source_id)
     * @return SalesInvoice
     */
    public function createFromPayload(array $payload, int $tenantId, int $userId): SalesInvoice
    {
        $localUuid = $payload['local_uuid'] ?? null;

        // ---- Idempotência: já sincronizada? ----
        if ($localUuid) {
            $existing = SalesInvoice::where('tenant_id', $tenantId)
                ->where('local_uuid', $localUuid)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Esta verificação sozinha tem janela de corrida: com a rede a oscilar o
        // PWA reenvia a mesma venda e dois pedidos passam aqui antes de qualquer
        // um gravar — daria factura, desconto de stock e tesouraria a dobrar. O
        // índice único (tenant_id, local_uuid) fecha a janela e a colisão é
        // apanhada abaixo como "já sincronizada".
        try {
            return $this->gravarVenda($payload, $tenantId, $userId, $localUuid);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $jaGravada = $localUuid
                ? SalesInvoice::where('tenant_id', $tenantId)->where('local_uuid', $localUuid)->first()
                : null;

            if ($jaGravada) {
                \Illuminate\Support\Facades\Log::info('PosSaleService: venda já sincronizada (corrida no reenvio)', [
                    'local_uuid' => $localUuid,
                    'invoice'    => $jaGravada->invoice_number,
                    'tenant_id'  => $tenantId,
                ]);

                return $jaGravada;
            }

            throw $e;
        }
    }

    /** Gravação propriamente dita (ver createFromPayload). */
    private function gravarVenda(array $payload, int $tenantId, int $userId, ?string $localUuid): SalesInvoice
    {

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw new \InvalidArgumentException('Venda sem itens.');
        }

        $paymentMethod = $payload['payment_method'] ?? 'cash';
        $discountCommercial = (float) ($payload['discount_commercial'] ?? 0);
        $notes = $payload['notes'] ?? null;

        return DB::transaction(function () use (
            $payload, $items, $tenantId, $userId, $localUuid,
            $paymentMethod, $discountCommercial, $notes
        ) {
            // ---- Cliente (consumidor final por omissão) ----
            $client = $this->resolveClient($payload, $tenantId);

            // ---- Tax autoritativo: pré-carregar produtos do payload
            //      e resolver tax_rate/tax_type/exemption a partir da DB
            //      (NUNCA confiar nos defaults do PWA, que pode ter catálogo
            //       desactualizado — antes do regime de isenção, p.ex.).
            $productIds = collect($items)
                ->pluck('product_id')
                ->filter(fn ($id) => is_numeric($id) && $id > 0)
                ->unique()
                ->values();
            $productsById = $productIds->isNotEmpty()
                ? Product::with('taxRate')->whereIn('id', $productIds)
                    ->where('tenant_id', $tenantId)->get()->keyBy('id')
                : collect();

            // Tax DEFAULT do tenant — usado quando item não tem product_id (serviço ad-hoc)
            $defaultTax = \App\Models\Invoicing\Tax::where('tenant_id', $tenantId)
                ->where('is_default', true)->first();

            // Resolução do imposto pela FONTE ÚNICA partilhada com o POS online,
            // a faturação e as notas (TaxResolver). Garante, entre outras coisas,
            // que uma linha a 0% leva SEMPRE código de isenção — o resolvedor
            // próprio que existia aqui podia devolver ISE sem motivo (a AGT
            // rejeita esses documentos).
            $resolveTax = function (array $item) use ($productsById, $tenantId): array {
                $pid = $item['product_id'] ?? null;
                $product = ($pid && is_numeric($pid) && $productsById->has((int) $pid))
                    ? $productsById[(int) $pid]
                    : null;

                $tx = \App\Services\Invoicing\TaxResolver::forProduct($product, $tenantId);

                return [
                    'rate'   => $tx['rate'],
                    'type'   => $tx['type'],
                    // fallback ao que veio do dispositivo offline, se existir
                    'exempt' => $tx['exemption_code'] ?: ($item['exemption_reason'] ?? null),
                ];
            };

            // ---- Cálculos AGT ----
            $cartItemsForCalc = collect($items)->map(function ($item) use ($resolveTax) {
                $tx = $resolveTax($item);
                return (object) [
                    'price' => (float) ($item['unit_price'] ?? 0),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'attributes' => [
                        'discount_percent' => 0,
                        'tax_rate' => $tx['rate'],
                    ],
                ];
            });

            $calc = InvoiceCalculationHelper::calculateTotals(
                $cartItemsForCalc,
                $discountCommercial,
                0,
                0,
                false
            );

            // ---- Série POS (FR) + número ----
            $series = InvoicingSeries::getIssuanceSeries($tenantId, 'pos');
            $invoiceNumber = $series->getNextNumber();

            $amountReceived = (float) ($payload['amount_received'] ?? $calc['total']);

            // ---- Data do documento (usa a data local da venda offline) ----
            $saleDate = !empty($payload['created_at_local'])
                ? \Carbon\Carbon::parse($payload['created_at_local'])
                : now();

            // Armazém: usa o default do tenant (mesmo que o POS online)
            $whId = defaultWarehouseId();

            $invoice = SalesInvoice::create([
                'tenant_id'           => $tenantId,
                'series_id'           => $series->id,
                'local_uuid'          => $localUuid,
                'client_id'           => $client->id,
                'warehouse_id'        => $whId,
                'invoice_type'        => 'FR', // Fatura-Recibo (SAFT-AO)
                'invoice_number'      => $invoiceNumber,
                'invoice_date'        => $saleDate,
                'due_date'            => $saleDate,
                'status'              => 'paid',
                'subtotal'            => $calc['subtotal'],
                'net_total'           => $calc['incidencia_iva'],
                'tax_amount'          => $calc['tax_amount'],
                'tax_payable'         => $calc['tax_amount'],
                'irt_amount'          => $calc['irt_amount'],
                // SAFT-AO: GrossTotal = NetTotal + TaxPayable (a retenção vai no
                // seu campo próprio). Ver a mesma correcção em POSSystem.
                'gross_total'         => $calc['incidencia_iva'] + $calc['tax_amount'],
                'discount_amount'     => $calc['desconto_comercial_total'],
                'discount_commercial' => $discountCommercial,
                'total'               => $calc['total'],
                'paid_amount'         => $amountReceived,
                'invoice_status'      => 'F',
                'invoice_status_date' => now(),
                'source_id'           => $userId,
                'source_billing'      => 'P',
                'system_entry_date'   => now(),
                'notes'               => $notes,
                'payment_method'      => $paymentMethod,
                'created_by'          => $userId,
            ]);

            // ---- Itens + stock ----
            $itemOrder = 0;
            foreach ($items as $item) {
                // Tax AUTORITATIVO (DB do servidor, não payload)
                $tx        = $resolveTax($item);
                $taxRate   = $tx['rate'];
                $taxType   = $tx['type'];
                $exempt    = $tx['exempt'];

                $unit      = $item['unit'] ?? 'UN';
                $qty       = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $subtotal  = round($unitPrice * $qty, 2);
                $taxAmount = round($subtotal * ($taxRate / 100), 2);

                $isService = !empty($item['is_service']);
                $productId = $item['product_id'] ?? null;
                $taxCode   = ($taxType === 'isento' || $taxRate == 0) ? 'ISE' : 'NOR';

                $linha = SalesInvoiceItem::create([
                    'sales_invoice_id'     => $invoice->id,
                    'product_id'           => $productId,
                    'product_name'         => $item['product_name'] ?? 'Item',
                    'description'          => $isService ? '[SERVIÇO] ' . ($item['product_name'] ?? '') : ($item['product_name'] ?? null),
                    'quantity'             => $qty,
                    'unit'                 => $unit,
                    'unit_price'           => $unitPrice,
                    'discount_percent'     => 0,
                    'discount_amount'      => 0,
                    'subtotal'             => $subtotal,
                    'tax_rate'             => $taxRate,
                    'tax_amount'           => $taxAmount,
                    'total'                => $subtotal + $taxAmount,
                    'order'                => ++$itemOrder,
                    'tax_code'             => $taxCode,
                    'tax_country_region'   => 'AO',
                    // Código (varchar 10) vs descrição (varchar 255) — normalizar sempre
                    'tax_exemption_code'   => $taxCode === 'ISE' ? Product::normalizeExemptionCode($exempt) : null,
                    'tax_exemption_reason' => $taxCode === 'ISE' ? Product::exemptionReasonText($exempt) : null,
                ]);

                // Atualizar stock (só produtos com id numérico) — deduz do
                // armazém da venda quando existe linha em invoicing_stocks.
                // O agregado products.stock_quantity é mantido pelo StockObserver
                // no save() da linha; escrevê-lo aqui manualmente sobrepunha o
                // valor do observer e perpetuava divergências agregado/armazéns.
                if (!$isService && $productId && is_numeric($productId)) {
                    $product = Product::where("tenant_id", $tenantId)->find($productId);
                    if ($product) {
                        // Regra única (BaixaDeStock): desconta a quantidade toda,
                        // mesmo que o armazém fique negativo. O max(0, …) que
                        // aqui estava travava o stock em zero enquanto o
                        // movimento registava a quantidade toda — era essa a
                        // origem de "vendidas 12, saíram 10".
                        \App\Services\Invoicing\BaixaDeStock::aplicar(
                            (int) $tenantId,
                            $whId ? (int) $whId : null,
                            $product,
                            (float) $qty
                        );

                        // ---- Lotes ----
                        //
                        // Isto não existia. O SalesInvoiceObserver aloca os
                        // lotes dentro do reduceStock, mas esse sai logo à
                        // entrada quando já há um movimento de saída a
                        // referenciar a factura — e a sincronização do PWA cria
                        // esse movimento ela própria. Resultado: uma venda
                        // offline descia o stock do armazém e deixava os lotes
                        // intactos.
                        //
                        // Numa farmácia ou num supermercado isso é caro: o
                        // relatório de validade sai dos lotes, portanto mandava
                        // abater produto que já tinha sido vendido, e a
                        // rastreabilidade — de que lote saiu esta caixa — não
                        // existia para nada que passasse pelo POS offline.
                        $this->consumirLotes($invoice, $linha, $product, (float) $qty, $whId, $tenantId);

                        // Ledger: movimento da venda (semAplicarStock para o hook created
                        // não voltar a debitar — o stock já foi atualizado acima).
                        if ($whId) {
                            \App\Models\Invoicing\StockMovement::semAplicarStock(function () use ($invoice, $product, $qty, $whId, $tenantId, $userId) {
                                \App\Models\Invoicing\StockMovement::create([
                                    'tenant_id'      => $tenantId,
                                    'warehouse_id'   => $whId,
                                    'product_id'     => $product->id,
                                    'type'           => 'out',
                                    'quantity'       => $qty,
                                    'unit_cost'      => $product->cost,
                                    'reference_type' => SalesInvoice::class,
                                    'reference_id'   => $invoice->id,
                                    'user_id'        => $userId,
                                    'notes'          => 'Venda POS (sync) - ' . $invoice->invoice_number,
                                ]);
                            });
                        }
                    }
                }
            }

            // ---- Tesouraria ----
            $this->createTreasuryTransaction($invoice, $client, $paymentMethod, $notes, $tenantId, $userId);

            // ---- Turno aberto do operador (best-effort) ----
            $this->linkToOpenShift($invoice, $client, $paymentMethod, $tenantId, $userId, $items, $amountReceived);

            // ---- Hash SAFT-AO (encadear) ----
            try {
                $invoice->generateHash();
            } catch (\Throwable $e) {
                Log::error('PosSaleService: erro ao gerar hash', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);
            }

            // ---- Auto-submeter à AGT se configurado ----
            try {
                $settings = InvoicingSettings::forTenant($tenantId);
                if (!empty($settings->agt_auto_submit)) {
                    // fresh(): a colecção `items` deste objecto foi lida na
                    // criação da factura, antes de existirem linhas, e o
                    // Eloquent guardou-a vazia. Sem isto o documento seguia
                    // para a AGT com os totais e ZERO linhas.
                    $invoice->fresh()->submitToAGT();
                }
            } catch (\Throwable $e) {
                Log::error('PosSaleService: erro submeter AGT', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);
            }

            return $invoice->fresh();
        });
    }

    /**
     * Resolve o cliente do payload. Se não houver, devolve o Consumidor Final.
     */
    /**
     * Dá baixa nos lotes do artigo vendido e regista de onde saiu.
     *
     * Regras, e cada uma tem uma razão:
     *
     *   · só para artigos com rastreio por lote. A maioria do catálogo não o
     *     usa e não pode passar a falhar por causa disso;
     *
     *   · FIFO pela validade, como no POS online — sai primeiro o que expira
     *     antes, que é a razão de ser dos lotes;
     *
     *   · lotes expirados NÃO são consumidos. O allocateFIFO salta-os. Dar
     *     baixa num lote fora de prazo apagava a prova de que há produto
     *     estragado no armazém;
     *
     *   · se a alocação falhar, a venda NÃO falha. Ela já aconteceu: o cliente
     *     levou o produto e o documento é fiscal. Fica o aviso no log e o
     *     stock do armazém, que já desceu, continua certo.
     */
    private function consumirLotes(
        SalesInvoice $invoice,
        SalesInvoiceItem $linha,
        Product $product,
        float $quantidade,
        $warehouseId,
        int $tenantId
    ): void {
        if (!$warehouseId || !($product->track_batches ?? false)) {
            return;
        }

        try {
            $servico = app(\App\Services\BatchAllocationService::class);

            $alocacao = $servico->allocateFIFO($product->id, (int) $warehouseId, $quantidade);

            if (!$alocacao['success']) {
                Log::warning('PosSaleService: sem lotes para a venda offline; stock descontado sem lote', [
                    'invoice'    => $invoice->invoice_number,
                    'product_id' => $product->id,
                    'quantidade' => $quantidade,
                    'motivo'     => $alocacao['message'] ?? null,
                ]);

                return;
            }

            $servico->confirmAllocation($alocacao['allocations']);

            foreach ($alocacao['allocations'] as $parte) {
                \App\Models\Invoicing\BatchAllocation::create([
                    'tenant_id'             => $tenantId,
                    'document_type'         => SalesInvoice::class,
                    'document_id'           => $invoice->id,
                    'document_item_id'      => $linha->id,
                    'product_batch_id'      => $parte['batch_id'],
                    'product_id'            => $product->id,
                    'quantity_allocated'    => $parte['quantity'],
                    'expiry_date_snapshot'  => $parte['expiry_date'],
                    'batch_number_snapshot' => $parte['batch_number'],
                    'status'                => 'confirmed',
                ]);
            }
        } catch (\Throwable $e) {
            // A venda não pode cair por causa dos lotes — ver acima.
            Log::error('PosSaleService: falha ao consumir lotes', [
                'invoice'    => $invoice->invoice_number,
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function resolveClient(array $payload, int $tenantId): Client
    {
        $clientId = $payload['client_id'] ?? null;
        if ($clientId && is_numeric($clientId)) {
            $client = Client::where('tenant_id', $tenantId)->find($clientId);
            if ($client) {
                return $client;
            }
        }

        // Consumidor Final (NIF 999999999) — por tenant, idempotente.
        // NOTA: 'type' é ENUM ['pessoa_fisica','pessoa_juridica'] e 'tax_regime'
        // tem default 'geral'. O NIF é único por (tenant_id, nif).
        return Client::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'nif'       => '999999999',
            ],
            [
                'type'           => 'pessoa_fisica',
                'name'           => 'Consumidor Final',
                'country'        => 'Angola',
                'tax_regime'     => 'geral',
                'is_iva_subject' => false,
                'is_active'      => true,
            ]
        );
    }

    /**
     * Cria transação de tesouraria (income) associada à fatura.
     */
    protected function createTreasuryTransaction(
        SalesInvoice $invoice,
        Client $client,
        string $paymentMethod,
        ?string $notes,
        int $tenantId,
        int $userId
    ): void {
        $code = strtolower((string) $paymentMethod);

        // Método do Treasury (case-insensitive pelo código)
        $treasuryPaymentMethod = TreasuryPaymentMethod::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(code) = ?', [$code])
            ->first();

        // Categoria: derivar do TIPO do método quando existir; senão, mapa por código
        $typeToCategory = [
            'cash' => 'cash', 'card' => 'card', 'bank_transfer' => 'bank_transfer',
            'digital_wallet' => 'digital_payment', 'check' => 'bank_transfer',
        ];
        $codeToCategory = [
            'cash' => 'cash', 'transfer' => 'bank_transfer', 'multicaixa' => 'card',
            'mcx' => 'card', 'tpa' => 'card', 'card' => 'card',
            'mbway' => 'digital_payment', 'mobile' => 'digital_payment',
        ];
        $category = $treasuryPaymentMethod
            ? ($typeToCategory[$treasuryPaymentMethod->type] ?? 'cash')
            : ($codeToCategory[$code] ?? 'cash');

        $isCash = $treasuryPaymentMethod ? ($treasuryPaymentMethod->type === 'cash') : ($code === 'cash');

        $cashRegisterId = null;
        $activeCashRegister = null;
        if ($isCash) {
            $activeCashRegister = CashRegister::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('status', 'open')
                ->first();
            if ($activeCashRegister) {
                $cashRegisterId = $activeCashRegister->id;
            }
        }

        Transaction::create([
            'tenant_id'          => $tenantId,
            'user_id'            => $userId,
            'cash_register_id'   => $cashRegisterId,
            'payment_method_id'  => $treasuryPaymentMethod?->id,
            'invoice_id'         => $invoice->id,
            'transaction_number' => 'TRX-' . strtoupper(uniqid()),
            'type'               => 'income',
            'category'           => $category,
            'amount'             => $invoice->total,
            'currency'           => 'AOA',
            'transaction_date'   => now(),
            'reference'          => $invoice->invoice_number,
            'description'        => 'Venda POS Offline - Fatura: ' . $invoice->invoice_number . ' - Cliente: ' . $client->name,
            'notes'              => $notes,
            'status'             => 'completed',
            'is_reconciled'      => false,
        ]);

        if ($cashRegisterId && $activeCashRegister) {
            $activeCashRegister->current_balance += $invoice->total;
            $activeCashRegister->save();
        }
    }

    /**
     * Liga a venda ao turno aberto do operador, se existir (best-effort).
     */
    protected function linkToOpenShift(
        SalesInvoice $invoice,
        Client $client,
        string $paymentMethod,
        int $tenantId,
        int $userId,
        array $items,
        float $amountReceived
    ): void {
        $shift = PosShift::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (!$shift) {
            return;
        }

        try {
            $shift->addTransaction([
                'type'             => 'invoice',
                'reference_type'   => SalesInvoice::class,
                'reference_id'     => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'payment_method'   => $paymentMethod,
                'amount'           => $invoice->total,
                'description'      => 'Venda POS Offline - ' . $invoice->invoice_number,
                'metadata'         => [
                    'client_id'       => $client->id,
                    'client_name'     => $client->name,
                    'items_count'     => count($items),
                    'amount_received' => $amountReceived,
                    'offline_sync'    => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('PosSaleService: falha ao registar no turno', [
                'invoice' => $invoice->invoice_number,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
