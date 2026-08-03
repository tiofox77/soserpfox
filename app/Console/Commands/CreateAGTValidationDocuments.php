<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\CreditNoteItem;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\DebitNoteItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\AGT\AGTService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateAGTValidationDocuments extends Command
{
    protected $signature = 'agt:create-validation-documents {--tenant=11} {--submit}';
    protected $description = 'Cria FT, RC, NC e ND para validar a integracao AGT';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $series = InvoicingSeries::where('tenant_id', $tenantId)
            ->whereNotNull('agt_series_id')->get()->keyBy('document_type');

        foreach (['invoice', 'receipt', 'credit_note', 'debit_note'] as $type) {
            if (!$series->has($type)) {
                $this->error("Falta serie AGT registada: {$type}");
                return self::FAILURE;
            }
        }

        $client = Client::where('tenant_id', $tenantId)->firstOrFail();
        $product = Product::where('tenant_id', $tenantId)->where('is_active', true)->firstOrFail();
        $warehouse = Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->firstOrFail();
        $userId = DB::table('tenant_user')->where('tenant_id', $tenantId)->value('user_id')
            ?? DB::table('users')->where('tenant_id', $tenantId)->value('id');

        $documents = DB::transaction(function () use (
            $tenantId, $series, $client, $product, $warehouse, $userId
        ) {
            $invoice = SalesInvoice::create([
                'tenant_id' => $tenantId, 'series_id' => $series['invoice']->id,
                'invoice_number' => $series['invoice']->getNextNumber(), 'invoice_type' => 'FT',
                'invoice_status' => 'N', 'invoice_status_date' => now(),
                'client_id' => $client->id, 'warehouse_id' => $warehouse->id,
                'invoice_date' => today(), 'due_date' => today()->addDays(30),
                'system_entry_date' => now(), 'status' => 'pending',
                'subtotal' => 1000, 'net_total' => 1000, 'tax_amount' => 140,
                'tax_payable' => 140, 'total' => 1140, 'gross_total' => 1140,
                'currency' => 'AOA', 'created_by' => $userId,
                'source_id' => 'AGT-VALIDATION', 'source_billing' => 'SOSERP/1',
                'hash_control' => '1', 'notes' => 'Validacao AGT Sandbox',
            ]);
            SalesInvoiceItem::create($this->buildLine('sales_invoice_id', $invoice->id, $product, 1000));
            $invoice->generateHash();

            $receipt = Receipt::create([
                'tenant_id' => $tenantId,
                'series_id' => $series['receipt']->id,
                'receipt_number' => $series['receipt']->getNextNumber(),
                'type' => 'sale', 'invoice_id' => $invoice->id, 'client_id' => $client->id,
                'payment_date' => today(), 'payment_method' => 'cash',
                'amount_paid' => 1140, 'remaining_amount' => 0,
                'status' => 'issued', 'created_by' => $userId,
                'notes' => 'Validacao AGT Sandbox',
            ]);

            $credit = CreditNote::create([
                'tenant_id' => $tenantId, 'series_id' => $series['credit_note']->id,
                'credit_note_number' => $series['credit_note']->getNextNumber(),
                'invoice_id' => $invoice->id, 'client_id' => $client->id,
                'warehouse_id' => $warehouse->id, 'issue_date' => today(),
                'system_entry_date' => now(), 'reason' => 'return',
                'reason_text' => 'Rectificacao parcial para validacao AGT',
                'type' => 'partial', 'status' => 'issued', 'invoice_status' => 'N',
                'subtotal' => 100, 'net_total' => 100, 'tax_amount' => 14,
                'tax_payable' => 14, 'total' => 114, 'gross_total' => 114,
                'created_by' => $userId, 'source_billing' => 'SOSERP/1',
            ]);
            CreditNoteItem::create(array_merge(
                $this->buildLine('credit_note_id', $credit->id, $product, 100),
                ['reference_invoice_no' => $invoice->invoice_number,
                 'reference_item_line_no' => 1, 'reference_reason' => 'Rectificacao parcial']
            ));

            $debit = DebitNote::create([
                'tenant_id' => $tenantId, 'series_id' => $series['debit_note']->id,
                'debit_note_number' => $series['debit_note']->getNextNumber(),
                'invoice_id' => $invoice->id, 'client_id' => $client->id,
                'warehouse_id' => $warehouse->id, 'issue_date' => today(),
                'system_entry_date' => now(), 'due_date' => today()->addDays(30),
                'reason' => 'additional_charge', 'status' => 'issued', 'invoice_status' => 'N',
                'subtotal' => 50, 'net_total' => 50, 'tax_amount' => 7,
                'tax_payable' => 7, 'total' => 57, 'gross_total' => 57,
                'created_by' => $userId, 'source_billing' => 'SOSERP/1',
            ]);
            DebitNoteItem::create($this->buildLine('debit_note_id', $debit->id, $product, 50));

            return [$invoice, $receipt, $credit, $debit];
        });

        foreach ($documents as $document) {
            $number = $document->invoice_number ?? $document->receipt_number
                ?? $document->credit_note_number ?? $document->debit_note_number;
            $this->info("Criado: {$number} (ID {$document->id})");
            if ($this->option('submit')) {
                $result = (new AGTService($tenantId))->submitToAGT($document->fresh());
                $this->line(json_encode([
                    'documento' => $number, 'sucesso' => $result['success'] ?? false,
                    'requestID' => $result['requestID'] ?? null,
                    'erro' => $result['error'] ?? null, 'resposta' => $result['response'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }

    private function buildLine(string $foreignKey, int $id, Product $product, float $net): array
    {
        return [
            $foreignKey => $id, 'product_id' => $product->id,
            'product_name' => $product->name, 'description' => $product->name,
            'quantity' => 1, 'unit' => 'UN', 'unit_price' => $net,
            'unit_price_base' => $net, 'discount_percent' => 0, 'discount_amount' => 0,
            'subtotal' => $net, 'tax_rate' => 14, 'tax_amount' => $net * .14,
            'total' => $net * 1.14, 'credit_amount' => $net, 'order' => 1,
            'tax_country_region' => 'AO', 'tax_code' => 'NOR',
        ];
    }
}
