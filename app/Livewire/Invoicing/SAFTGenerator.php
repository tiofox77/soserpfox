<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.app')]
#[Title('Gerador SAFT-AO')]
class SAFTGenerator extends Component
{
    public $startDate;
    public $endDate;
    public $documentType = 'all'; // all, sales, purchases, inventory
    public $includeProducts = true;
    public $includeCustomers = true;
    public $includeSuppliers = true;
    public $includeTaxTable = true;
    public $includeCreditNotes = true;
    public $includeDebitNotes = true;
    public $includePayments = true;
    public $includeStockMovements = true;
    
    // Stats
    public $totalInvoices = 0;
    public $totalCreditNotes = 0;
    public $totalDebitNotes = 0;
    public $totalReceipts = 0;
    public $totalMovements = 0;
    public $totalCustomers = 0;
    public $totalSuppliers = 0;
    public $totalProducts = 0;
    public $totalValue = 0;

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->calculateStats();
    }

    public function updatedStartDate()
    {
        $this->calculateStats();
    }

    public function updatedEndDate()
    {
        $this->calculateStats();
    }

    public function updatedDocumentType()
    {
        $this->calculateStats();
    }

    public function calculateStats()
    {
        $tenantId = activeTenantId();

        // Faturas de Venda
        $salesQuery = SalesInvoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled']);

        // Faturas de Compra
        $purchasesQuery = PurchaseInvoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled']);

        if ($this->documentType === 'sales') {
            $this->totalInvoices = $salesQuery->count();
            $this->totalValue = $salesQuery->sum('total');
        } elseif ($this->documentType === 'purchases') {
            $this->totalInvoices = $purchasesQuery->count();
            $this->totalValue = $purchasesQuery->sum('total');
        } elseif ($this->documentType === 'inventory') {
            $this->totalInvoices = 0;
            $this->totalValue = 0;
        } else {
            $this->totalInvoices = $salesQuery->count() + $purchasesQuery->count();
            $this->totalValue = $salesQuery->sum('total') + $purchasesQuery->sum('total');
        }

        // Notas de Crédito
        $this->totalCreditNotes = CreditNote::where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->count();

        // Notas de Débito
        $this->totalDebitNotes = DebitNote::where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->count();

        // Recibos
        $this->totalReceipts = Receipt::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$this->startDate, $this->endDate])
            ->where('status', '!=', 'cancelled')
            ->count();

        // Movimentos de Stock
        $this->totalMovements = StockMovement::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->count();

        $this->totalCustomers = Client::where('tenant_id', $tenantId)->count();
        $this->totalSuppliers = Supplier::where('tenant_id', $tenantId)->count();
        $this->totalProducts = Product::where('tenant_id', $tenantId)->count();
    }

    public function generateSAFT()
    {
        try {
            $xml = $this->buildSAFTXML();
            
            $fileName = 'SAFT_AO_' . activeTenantId() . '_' . date('Y-m-d') . '.xml';
            
            Storage::disk('local')->put('saft/' . $fileName, $xml);
            
            return response()->streamDownload(function () use ($xml) {
                echo $xml;
            }, $fileName, [
                'Content-Type' => 'application/xml',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao gerar SAFT: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    private function getSourceID($userId): string
    {
        if (!$userId) return 'Sistema';
        $user = User::find($userId);
        return $user ? ($user->name ?? $user->email ?? (string) $userId) : (string) $userId;
    }

    private function buildSAFTXML()
    {
        $tenantId = activeTenantId();
        $tenant = auth()->user()->activeTenant();
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AuditFile></AuditFile>');
        $xml->addAttribute('xmlns', 'urn:OECD:StandardAuditFile-Tax:AO_1.01_01');
        
        // ═══════════════════════════════════════
        // HEADER
        // ═══════════════════════════════════════
        $header = $xml->addChild('Header');
        $header->addChild('AuditFileVersion', '1.01_01');
        $header->addChild('CompanyID', $tenant->nif ?? 'N/A');
        $header->addChild('TaxRegistrationNumber', $tenant->nif ?? 'N/A');
        $header->addChild('TaxAccountingBasis', 'F'); // F=Faturação
        $header->addChild('CompanyName', htmlspecialchars($tenant->name));
        
        $companyAddress = $header->addChild('CompanyAddress');
        $companyAddress->addChild('AddressDetail', htmlspecialchars($tenant->address ?? 'N/A'));
        $companyAddress->addChild('City', htmlspecialchars($tenant->city ?? 'Luanda'));
        $companyAddress->addChild('PostalCode', $tenant->postal_code ?? '0000');
        $companyAddress->addChild('Country', 'AO');
        
        $header->addChild('FiscalYear', date('Y', strtotime($this->startDate)));
        $header->addChild('StartDate', $this->startDate);
        $header->addChild('EndDate', $this->endDate);
        $header->addChild('CurrencyCode', 'AOA');
        $header->addChild('DateCreated', date('Y-m-d'));
        $header->addChild('TaxEntity', 'Global');
        $header->addChild('ProductCompanyTaxID', $tenant->nif ?? 'N/A');
        
        // Identidade do software é global e gerida pelo produtor no Super Admin.
        $settings = \App\Models\Invoicing\InvoicingSettings::where('tenant_id', $tenantId)->first();
        $header->addChild('SoftwareCertificateNumber', softwareSetting('invoicing', 'saft_software_cert', $settings?->agt_software_certificate ?? '0'));
        $header->addChild('ProductID', softwareSetting('invoicing', 'saft_product_id', 'SOS ERP/SOSERP'));
        $header->addChild('ProductVersion', softwareSetting('invoicing', 'saft_version', '1.0'));
        
        // ═══════════════════════════════════════
        // MASTER FILES
        // ═══════════════════════════════════════
        $masterFiles = $xml->addChild('MasterFiles');
        
        // --- TaxTable (obrigatório SAFT-AO) ---
        if ($this->includeTaxTable) {
            $taxes = Tax::where('tenant_id', $tenantId)->where('is_active', true)->get();
            foreach ($taxes as $tax) {
                $taxEntry = $masterFiles->addChild('TaxTableEntry');
                // TaxType: IVA para impostos IVA, IS para Imposto de Selo/outros
                $taxType = in_array(strtolower($tax->type), ['iva', 'vat']) ? 'IVA' : 'IS';
                $taxEntry->addChild('TaxType', $taxType);
                $taxEntry->addChild('TaxCountryRegion', 'AO');
                // TaxCode: usar saft_type (NOR/RED/ISE/NS) que é o código SAFT correto
                $taxEntry->addChild('TaxCode', $tax->saft_type ?? $tax->saft_code ?? 'NOR');
                $taxEntry->addChild('Description', htmlspecialchars($tax->name));
                $taxEntry->addChild('TaxPercentage', number_format($tax->rate, 2, '.', ''));
                $taxEntry->addChild('TaxExpirationDate', date('Y') . '-12-31');
            }
            
            // Se não houver impostos cadastrados, adicionar IVA padrão
            if ($taxes->isEmpty()) {
                $taxEntry = $masterFiles->addChild('TaxTableEntry');
                $taxEntry->addChild('TaxType', 'IVA');
                $taxEntry->addChild('TaxCountryRegion', 'AO');
                $taxEntry->addChild('TaxCode', 'NOR');
                $taxEntry->addChild('Description', 'IVA Normal');
                $taxEntry->addChild('TaxPercentage', '14.00');
                $taxEntry->addChild('TaxExpirationDate', date('Y') . '-12-31');
            }
        }

        // --- Customers ---
        if ($this->includeCustomers && ($this->documentType === 'all' || $this->documentType === 'sales')) {
            $clients = Client::where('tenant_id', $tenantId)->get();
            foreach ($clients as $client) {
                $customer = $masterFiles->addChild('Customer');
                $customer->addChild('CustomerID', $client->id);
                $customer->addChild('AccountID', 'Desconhecido');
                
                $nif = trim($client->nif ?? '');
                $customerTaxID = !empty($nif) && strlen($nif) >= 9 ? $nif : '999999999';
                $customer->addChild('CustomerTaxID', $customerTaxID);
                
                $customer->addChild('CompanyName', htmlspecialchars($client->name));
                
                $billingAddress = $customer->addChild('BillingAddress');
                $billingAddress->addChild('AddressDetail', htmlspecialchars($client->address ?? 'N/A'));
                $billingAddress->addChild('City', htmlspecialchars($client->city ?? 'Luanda'));
                $billingAddress->addChild('PostalCode', $client->postal_code ?? '0000');
                $billingAddress->addChild('Country', $client->country_code ?? 'AO');
                
                $customer->addChild('SelfBillingIndicator', '0');
            }
        }

        // --- Suppliers ---
        if ($this->includeSuppliers && ($this->documentType === 'all' || $this->documentType === 'purchases')) {
            $suppliers = Supplier::where('tenant_id', $tenantId)->get();
            foreach ($suppliers as $supplier) {
                $supplierNode = $masterFiles->addChild('Supplier');
                $supplierNode->addChild('SupplierID', $supplier->id);
                $supplierNode->addChild('AccountID', 'Desconhecido');
                
                $supplierNif = trim($supplier->nif ?? '');
                $supplierTaxID = !empty($supplierNif) && strlen($supplierNif) >= 9 && is_numeric($supplierNif) 
                    ? $supplierNif 
                    : '999999999';
                $supplierNode->addChild('SupplierTaxID', $supplierTaxID);
                
                $supplierNode->addChild('CompanyName', htmlspecialchars($supplier->name));
                
                $billingAddress = $supplierNode->addChild('BillingAddress');
                $billingAddress->addChild('AddressDetail', htmlspecialchars($supplier->address ?? 'N/A'));
                $billingAddress->addChild('City', htmlspecialchars($supplier->city ?? 'Luanda'));
                $billingAddress->addChild('PostalCode', $supplier->postal_code ?? '0000');
                $billingAddress->addChild('Country', $supplier->country_code ?? 'AO');
                
                $supplierNode->addChild('SelfBillingIndicator', '0');
            }
        }

        // --- Products ---
        if ($this->includeProducts) {
            $products = Product::where('tenant_id', $tenantId)->get();
            foreach ($products as $product) {
                $productNode = $masterFiles->addChild('Product');
                $productNode->addChild('ProductType', $product->type === 'service' ? 'S' : 'P');
                $productNode->addChild('ProductCode', $product->sku ?? (string) $product->id);
                $productNode->addChild('ProductGroup', htmlspecialchars($product->category->name ?? 'Geral'));
                $productNode->addChild('ProductDescription', htmlspecialchars($product->name));
                $productNode->addChild('ProductNumberCode', $product->barcode ?? $product->sku ?? (string) $product->id);
            }
        }

        // ═══════════════════════════════════════
        // SOURCE DOCUMENTS
        // ═══════════════════════════════════════
        $sourceDocuments = $xml->addChild('SourceDocuments');
        
        // --- SalesInvoices (FT + NC + ND) ---
        if ($this->documentType === 'all' || $this->documentType === 'sales') {
            $this->buildSalesInvoicesSection($sourceDocuments, $tenantId);
        }

        // --- Payments (Recibos) ---
        if ($this->includePayments && ($this->documentType === 'all' || $this->documentType === 'sales')) {
            $this->buildPaymentsSection($sourceDocuments, $tenantId);
        }

        // --- MovementOfGoods (Inventário/Stock) ---
        if ($this->includeStockMovements && ($this->documentType === 'all' || $this->documentType === 'inventory')) {
            $this->buildMovementOfGoodsSection($sourceDocuments, $tenantId);
        }

        return $xml->asXML();
    }

    /**
     * SalesInvoices: FT (Faturas), NC (Notas Crédito), ND (Notas Débito)
     */
    private function buildSalesInvoicesSection(\SimpleXMLElement $sourceDocuments, int $tenantId): void
    {
        $salesInvoicesNode = $sourceDocuments->addChild('SalesInvoices');

        // Código de isenção do REGIME do tenant — último recurso para linhas sem
        // código (antes estava 'M01' fixo, que declarava o regime errado à AGT).
        $tenantExemptionCode = \App\Models\Invoicing\Tax::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->value('exemption_code')
            ?: (\App\Models\Tenant::find($tenantId)?->regimeMeta()['exemption_code']
                ?? \App\Services\Tenant\TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE);

        // Faturas de Venda
        $invoices = SalesInvoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with(['client', 'items.product', 'items.taxRate'])
            ->get();

        // Notas de Crédito
        $creditNotes = collect();
        if ($this->includeCreditNotes) {
            $creditNotes = CreditNote::where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$this->startDate, $this->endDate])
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->with(['client', 'items.product'])
                ->get();
        }

        // Notas de Débito
        $debitNotes = collect();
        if ($this->includeDebitNotes) {
            $debitNotes = DebitNote::where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$this->startDate, $this->endDate])
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->with(['client', 'items.product'])
                ->get();
        }

        $totalEntries = $invoices->count() + $creditNotes->count() + $debitNotes->count();
        // SAFT-AO: NC são débitos (devolvem valor), FT+ND são créditos (cobram valor)
        $totalDebit = $creditNotes->sum('total');
        $totalCredit = $invoices->sum('total') + $debitNotes->sum('total');

        $salesInvoicesNode->addChild('NumberOfEntries', $totalEntries);
        $salesInvoicesNode->addChild('TotalDebit', number_format($totalDebit, 2, '.', ''));
        $salesInvoicesNode->addChild('TotalCredit', number_format($totalCredit, 2, '.', ''));
        
        // -- Faturas FT --
        foreach ($invoices as $invoice) {
            $node = $salesInvoicesNode->addChild('Invoice');
            $node->addChild('InvoiceNo', $invoice->invoice_number);
            
            $docStatus = $node->addChild('DocumentStatus');
            $invoiceStatus = $invoice->invoice_status ?? ($invoice->status === 'cancelled' ? 'A' : 'N');
            $docStatus->addChild('InvoiceStatus', $invoiceStatus);
            $docStatus->addChild('InvoiceStatusDate', 
                $invoice->invoice_status_date 
                    ? $invoice->invoice_status_date->format('Y-m-d\TH:i:s') 
                    : $invoice->invoice_date->format('Y-m-d\TH:i:s'));
            $docStatus->addChild('SourceID', $this->getSourceID($invoice->source_id ?? $invoice->created_by));
            $docStatus->addChild('SourceBilling', $invoice->source_billing ?? 'P');
            
            $node->addChild('Hash', $invoice->hash ?? $invoice->saft_hash ?? '0');
            $node->addChild('HashControl', $invoice->hash_control ?? '1');
            
            if ($invoice->atcud) {
                $node->addChild('ATCUD', $invoice->atcud);
            }
            
            $node->addChild('InvoiceDate', $invoice->invoice_date->format('Y-m-d'));
            $node->addChild('InvoiceType', $invoice->invoice_type ?? 'FT');
            
            $specialRegimes = $node->addChild('SpecialRegimes');
            $specialRegimes->addChild('SelfBillingIndicator', '0');
            $specialRegimes->addChild('CashVATSchemeIndicator', '0');
            $specialRegimes->addChild('ThirdPartiesBillingIndicator', '0');
            
            $node->addChild('SourceID', $this->getSourceID($invoice->source_id ?? $invoice->created_by));
            $node->addChild('SystemEntryDate', 
                $invoice->system_entry_date 
                    ? $invoice->system_entry_date->format('Y-m-d\TH:i:s') 
                    : $invoice->created_at->format('Y-m-d\TH:i:s'));
            $node->addChild('CustomerID', $invoice->client_id);
            
            // Lines
            foreach ($invoice->items as $index => $item) {
                $line = $node->addChild('Line');
                $line->addChild('LineNumber', $index + 1);
                $line->addChild('ProductCode', $item->product->sku ?? (string) $item->product_id);
                $line->addChild('ProductDescription', htmlspecialchars($item->description ?? $item->product->name ?? ''));
                $line->addChild('Quantity', number_format($item->quantity, 2, '.', ''));
                $line->addChild('UnitOfMeasure', $item->unit ?? 'UN');
                $line->addChild('UnitPrice', number_format($item->unit_price, 2, '.', ''));
                $line->addChild('TaxPointDate', $invoice->invoice_date->format('Y-m-d'));
                $line->addChild('Description', htmlspecialchars($item->description ?? $item->product->name ?? ''));
                $line->addChild('CreditAmount', number_format($item->subtotal ?? ($item->quantity * $item->unit_price), 2, '.', ''));
                
                $tax = $line->addChild('Tax');
                $tax->addChild('TaxType', 'IVA');
                $tax->addChild('TaxCountryRegion', 'AO');
                $taxCode = $item->tax_code ?: (((float) ($item->tax_rate ?? 0)) > 0 ? ($item->taxRate->saft_type ?? 'NOR') : 'ISE');
                $tax->addChild('TaxCode', $taxCode);
                $tax->addChild('TaxPercentage', number_format($item->tax_rate ?? $item->taxRate->rate ?? 0, 2, '.', ''));
                
                if ($taxCode === 'ISE' || ($item->tax_rate ?? 0) == 0) {
                    $line->addChild('TaxExemptionReason', htmlspecialchars($item->tax_exemption_reason ?: ($item->taxRate->exemption_reason ?? 'Isento de IVA')));
                    $line->addChild('TaxExemptionCode', $item->tax_exemption_code
                        ?: ($item->taxRate->exemption_code ?? $tenantExemptionCode));
                }
            }
            
            // DocumentTotals
            $totals = $node->addChild('DocumentTotals');
            $totals->addChild('TaxPayable', number_format($invoice->tax_payable ?? $invoice->tax_amount, 2, '.', ''));
            $totals->addChild('NetTotal', number_format($invoice->net_total ?? $invoice->subtotal, 2, '.', ''));
            $totals->addChild('GrossTotal', number_format($invoice->gross_total ?? $invoice->total, 2, '.', ''));
            $totals->addChild('Currency');
            $totals->Currency->addChild('CurrencyCode', $invoice->currency ?? 'AOA');
            $totals->Currency->addChild('CurrencyAmount', number_format($invoice->gross_total ?? $invoice->total, 2, '.', ''));
            $totals->Currency->addChild('ExchangeRate', number_format($invoice->exchange_rate ?? 1, 6, '.', ''));
        }
        
        // -- Notas de Crédito NC --
        foreach ($creditNotes as $cn) {
            $node = $salesInvoicesNode->addChild('Invoice');
            $node->addChild('InvoiceNo', $cn->credit_note_number);
            
            $docStatus = $node->addChild('DocumentStatus');
            $cnStatus = $cn->invoice_status ?? ($cn->status === 'cancelled' ? 'A' : 'N');
            $docStatus->addChild('InvoiceStatus', $cnStatus);
            $docStatus->addChild('InvoiceStatusDate', $cn->issue_date->format('Y-m-d\TH:i:s'));
            $docStatus->addChild('SourceID', $this->getSourceID($cn->created_by));
            $docStatus->addChild('SourceBilling', 'P');
            
            $node->addChild('Hash', $cn->hash ?? $cn->saft_hash ?? '0');
            $node->addChild('HashControl', $cn->hash_control ?? '1');
            
            if ($cn->atcud) {
                $node->addChild('ATCUD', $cn->atcud);
            }
            
            $node->addChild('InvoiceDate', $cn->issue_date->format('Y-m-d'));
            $node->addChild('InvoiceType', 'NC');
            
            $specialRegimes = $node->addChild('SpecialRegimes');
            $specialRegimes->addChild('SelfBillingIndicator', '0');
            $specialRegimes->addChild('CashVATSchemeIndicator', '0');
            $specialRegimes->addChild('ThirdPartiesBillingIndicator', '0');
            
            $node->addChild('SourceID', $this->getSourceID($cn->created_by));
            $node->addChild('SystemEntryDate', 
                $cn->system_entry_date 
                    ? $cn->system_entry_date->format('Y-m-d\TH:i:s') 
                    : $cn->created_at->format('Y-m-d\TH:i:s'));
            $node->addChild('CustomerID', $cn->client_id);
            
            // Lines
            foreach ($cn->items as $index => $item) {
                $line = $node->addChild('Line');
                $line->addChild('LineNumber', $index + 1);
                $line->addChild('ProductCode', $item->product->sku ?? (string) $item->product_id);
                $line->addChild('ProductDescription', htmlspecialchars($item->description ?? $item->product->name ?? ''));
                $line->addChild('Quantity', number_format($item->quantity, 2, '.', ''));
                $line->addChild('UnitOfMeasure', 'UN');
                $line->addChild('UnitPrice', number_format($item->unit_price, 2, '.', ''));
                $line->addChild('TaxPointDate', $cn->issue_date->format('Y-m-d'));
                $line->addChild('Description', htmlspecialchars($item->description ?? ''));
                $line->addChild('DebitAmount', number_format($item->subtotal ?? ($item->quantity * $item->unit_price), 2, '.', ''));
                
                $tax = $line->addChild('Tax');
                $tax->addChild('TaxType', 'IVA');
                $tax->addChild('TaxCountryRegion', 'AO');
                $cnTaxCode = $item->tax_code ?: (((float) ($item->tax_rate ?? 0)) > 0 ? ($item->taxRate->saft_type ?? 'NOR') : 'ISE');
                $tax->addChild('TaxCode', $cnTaxCode);
                $tax->addChild('TaxPercentage', number_format($item->tax_rate ?? $item->taxRate->rate ?? 0, 2, '.', ''));
                
                // Isenção de IVA (obrigatório SAFT-AO)
                if ($cnTaxCode === 'ISE' || ($item->tax_rate ?? 0) == 0) {
                    $line->addChild('TaxExemptionReason', htmlspecialchars($item->tax_exemption_reason ?: ($item->taxRate->exemption_reason ?? 'Isento de IVA')));
                    $line->addChild('TaxExemptionCode', $item->tax_exemption_code
                        ?: ($item->taxRate->exemption_code ?? $tenantExemptionCode));
                }
                
                // Referência à fatura original
                if ($cn->invoice_id) {
                    $ref = $line->addChild('References');
                    $ref->addChild('Reference', $cn->invoice->invoice_number ?? '');
                    $ref->addChild('Reason', htmlspecialchars($cn->reason_text ?? $cn->reason ?? 'Rectificação'));
                }
            }
            
            $totals = $node->addChild('DocumentTotals');
            $totals->addChild('TaxPayable', number_format($cn->tax_payable ?? $cn->tax_amount, 2, '.', ''));
            $totals->addChild('NetTotal', number_format($cn->net_total ?? $cn->subtotal, 2, '.', ''));
            $totals->addChild('GrossTotal', number_format($cn->gross_total ?? $cn->total, 2, '.', ''));
        }
        
        // -- Notas de Débito ND --
        foreach ($debitNotes as $dn) {
            $node = $salesInvoicesNode->addChild('Invoice');
            $node->addChild('InvoiceNo', $dn->debit_note_number);
            
            $docStatus = $node->addChild('DocumentStatus');
            $dnStatus = $dn->invoice_status ?? ($dn->status === 'cancelled' ? 'A' : 'N');
            $docStatus->addChild('InvoiceStatus', $dnStatus);
            $docStatus->addChild('InvoiceStatusDate', $dn->issue_date->format('Y-m-d\TH:i:s'));
            $docStatus->addChild('SourceID', $this->getSourceID($dn->created_by));
            $docStatus->addChild('SourceBilling', 'P');
            
            $node->addChild('Hash', $dn->hash ?? $dn->saft_hash ?? '0');
            $node->addChild('HashControl', $dn->hash_control ?? '1');
            
            if ($dn->atcud) {
                $node->addChild('ATCUD', $dn->atcud);
            }
            
            $node->addChild('InvoiceDate', $dn->issue_date->format('Y-m-d'));
            $node->addChild('InvoiceType', 'ND');
            
            $specialRegimes = $node->addChild('SpecialRegimes');
            $specialRegimes->addChild('SelfBillingIndicator', '0');
            $specialRegimes->addChild('CashVATSchemeIndicator', '0');
            $specialRegimes->addChild('ThirdPartiesBillingIndicator', '0');
            
            $node->addChild('SourceID', $this->getSourceID($dn->created_by));
            $node->addChild('SystemEntryDate', 
                $dn->system_entry_date 
                    ? $dn->system_entry_date->format('Y-m-d\TH:i:s') 
                    : $dn->created_at->format('Y-m-d\TH:i:s'));
            $node->addChild('CustomerID', $dn->client_id);
            
            // Lines
            foreach ($dn->items as $index => $item) {
                $line = $node->addChild('Line');
                $line->addChild('LineNumber', $index + 1);
                $line->addChild('ProductCode', $item->product->sku ?? (string) $item->product_id);
                $line->addChild('ProductDescription', htmlspecialchars($item->description ?? $item->product->name ?? ''));
                $line->addChild('Quantity', number_format($item->quantity, 2, '.', ''));
                $line->addChild('UnitOfMeasure', 'UN');
                $line->addChild('UnitPrice', number_format($item->unit_price, 2, '.', ''));
                $line->addChild('TaxPointDate', $dn->issue_date->format('Y-m-d'));
                $line->addChild('Description', htmlspecialchars($item->description ?? ''));
                $line->addChild('CreditAmount', number_format($item->subtotal ?? ($item->quantity * $item->unit_price), 2, '.', ''));
                
                $tax = $line->addChild('Tax');
                $tax->addChild('TaxType', 'IVA');
                $tax->addChild('TaxCountryRegion', 'AO');
                $dnTaxCode = $item->tax_code ?: (((float) ($item->tax_rate ?? 0)) > 0 ? ($item->taxRate->saft_type ?? 'NOR') : 'ISE');
                $tax->addChild('TaxCode', $dnTaxCode);
                $tax->addChild('TaxPercentage', number_format($item->tax_rate ?? $item->taxRate->rate ?? 0, 2, '.', ''));
                
                // Isenção de IVA (obrigatório SAFT-AO)
                if ($dnTaxCode === 'ISE' || ($item->tax_rate ?? 0) == 0) {
                    $line->addChild('TaxExemptionReason', htmlspecialchars($item->tax_exemption_reason ?: ($item->taxRate->exemption_reason ?? 'Isento de IVA')));
                    $line->addChild('TaxExemptionCode', $item->tax_exemption_code
                        ?: ($item->taxRate->exemption_code ?? $tenantExemptionCode));
                }
                
                // Referência à fatura original
                if ($dn->invoice_id) {
                    $ref = $line->addChild('References');
                    $ref->addChild('Reference', $dn->invoice->invoice_number ?? '');
                    $ref->addChild('Reason', htmlspecialchars($dn->reason ?? 'Débito'));
                }
            }
            
            $totals = $node->addChild('DocumentTotals');
            $totals->addChild('TaxPayable', number_format($dn->tax_payable ?? $dn->tax_amount, 2, '.', ''));
            $totals->addChild('NetTotal', number_format($dn->net_total ?? $dn->subtotal, 2, '.', ''));
            $totals->addChild('GrossTotal', number_format($dn->gross_total ?? $dn->total, 2, '.', ''));
        }
    }

    /**
     * Payments: Recibos (RC)
     */
    private function buildPaymentsSection(\SimpleXMLElement $sourceDocuments, int $tenantId): void
    {
        $receipts = Receipt::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$this->startDate, $this->endDate])
            ->where('status', '!=', 'cancelled')
            ->with(['client', 'invoice'])
            ->get();

        if ($receipts->isEmpty()) {
            return;
        }

        $paymentsNode = $sourceDocuments->addChild('Payments');
        $paymentsNode->addChild('NumberOfEntries', $receipts->count());
        $paymentsNode->addChild('TotalDebit', '0.00');
        $paymentsNode->addChild('TotalCredit', number_format($receipts->sum('amount_paid'), 2, '.', ''));

        foreach ($receipts as $receipt) {
            $payNode = $paymentsNode->addChild('Payment');
            $payNode->addChild('PaymentRefNo', $receipt->receipt_number);
            
            $docStatus = $payNode->addChild('DocumentStatus');
            $docStatus->addChild('PaymentStatus', $receipt->status === 'cancelled' ? 'A' : 'N');
            $docStatus->addChild('PaymentStatusDate', $receipt->payment_date->format('Y-m-d\TH:i:s'));
            $docStatus->addChild('SourceID', $this->getSourceID($receipt->created_by));
            $docStatus->addChild('SourcePayment', 'P');
            
            $payNode->addChild('Hash', $receipt->saft_hash ?? '0');
            $payNode->addChild('HashControl', '1');
            $payNode->addChild('PaymentType', 'RC'); // Recibo
            $payNode->addChild('SystemEntryDate', $receipt->created_at->format('Y-m-d\TH:i:s'));
            $payNode->addChild('TransactionDate', $receipt->payment_date->format('Y-m-d'));
            $payNode->addChild('CustomerID', $receipt->client_id ?? 0);
            
            // Linha do pagamento
            $line = $payNode->addChild('Line');
            $line->addChild('LineNumber', '1');
            
            if ($receipt->invoice) {
                $sourceDoc = $line->addChild('SourceDocumentID');
                $sourceDoc->addChild('OriginatingON', $receipt->invoice->invoice_number);
                $sourceDoc->addChild('InvoiceDate', $receipt->invoice->invoice_date->format('Y-m-d'));
            }
            
            $line->addChild('CreditAmount', number_format($receipt->amount_paid, 2, '.', ''));
            
            // DocumentTotals
            $totals = $payNode->addChild('DocumentTotals');
            $totals->addChild('TaxPayable', '0.00');
            $totals->addChild('NetTotal', number_format($receipt->amount_paid, 2, '.', ''));
            $totals->addChild('GrossTotal', number_format($receipt->amount_paid, 2, '.', ''));
            
            // PaymentMethod
            $pm = $payNode->addChild('PaymentMethod');
            $pm->addChild('PaymentMechanism', $this->mapPaymentMethod($receipt->payment_method));
            $pm->addChild('PaymentAmount', number_format($receipt->amount_paid, 2, '.', ''));
            $pm->addChild('PaymentDate', $receipt->payment_date->format('Y-m-d'));
        }
    }

    /**
     * MovementOfGoods: Movimentos de stock / inventário (SAFT-AO)
     */
    private function buildMovementOfGoodsSection(\SimpleXMLElement $sourceDocuments, int $tenantId): void
    {
        $movements = StockMovement::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->with(['product', 'warehouse', 'fromWarehouse', 'toWarehouse', 'user'])
            ->orderBy('created_at')
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        $movementNode = $sourceDocuments->addChild('MovementOfGoods');
        
        $totalQty = $movements->sum('quantity');
        $movementNode->addChild('NumberOfMovementLines', $movements->count());
        $movementNode->addChild('TotalQuantityIssued', number_format($totalQty, 2, '.', ''));

        // Agrupar por tipo para criar documentos separados
        $grouped = $movements->groupBy(function ($m) {
            return $m->type . '_' . $m->created_at->format('Y-m-d') . '_' . ($m->warehouse_id ?? 0);
        });

        $docNumber = 0;
        foreach ($grouped as $key => $group) {
            $docNumber++;
            $first = $group->first();
            
            $stockDoc = $movementNode->addChild('StockMovement');
            
            // DocumentNumber: Tipo Armazém/Sequencial
            $typePrefix = match($first->type) {
                'in' => 'GR',     // Guia de Receção
                'out' => 'GS',    // Guia de Saída
                'transfer' => 'GT', // Guia de Transferência
                'adjustment' => 'GA', // Guia de Ajuste
                default => 'GM',
            };
            $stockDoc->addChild('DocumentNumber', $typePrefix . ' ' . ($first->warehouse->code ?? 'A') . '/' . str_pad($docNumber, 6, '0', STR_PAD_LEFT));
            
            $docStatus = $stockDoc->addChild('DocumentStatus');
            $docStatus->addChild('MovementStatus', 'N');
            $docStatus->addChild('MovementStatusDate', $first->created_at->format('Y-m-d\TH:i:s'));
            $docStatus->addChild('SourceID', $this->getSourceID($first->user_id));
            $docStatus->addChild('SourceBilling', 'P');
            
            $stockDoc->addChild('Hash', '0');
            $stockDoc->addChild('HashControl', '1');
            $stockDoc->addChild('MovementDate', $first->created_at->format('Y-m-d'));
            
            // MovementType: GR (Guia Remessa), GT (Guia Transporte), GA (Global)
            $movType = match($first->type) {
                'in' => 'GR',
                'out' => 'GC',
                'transfer' => 'GT',
                default => 'GR',
            };
            $stockDoc->addChild('MovementType', $movType);
            
            $stockDoc->addChild('SourceID', $this->getSourceID($first->user_id));
            $stockDoc->addChild('SystemEntryDate', $first->created_at->format('Y-m-d\TH:i:s'));
            
            // Endereços
            if ($first->type === 'transfer' && $first->fromWarehouse && $first->toWarehouse) {
                $shipFrom = $stockDoc->addChild('ShipFrom');
                $shipFrom->addChild('DeliveryDate', $first->created_at->format('Y-m-d'));
                $fromAddr = $shipFrom->addChild('Address');
                $fromAddr->addChild('AddressDetail', htmlspecialchars($first->fromWarehouse->address ?? $first->fromWarehouse->name ?? 'N/A'));
                $fromAddr->addChild('City', htmlspecialchars($first->fromWarehouse->city ?? 'Luanda'));
                $fromAddr->addChild('PostalCode', '0000');
                $fromAddr->addChild('Country', 'AO');
                
                $shipTo = $stockDoc->addChild('ShipTo');
                $shipTo->addChild('DeliveryDate', $first->created_at->format('Y-m-d'));
                $toAddr = $shipTo->addChild('Address');
                $toAddr->addChild('AddressDetail', htmlspecialchars($first->toWarehouse->address ?? $first->toWarehouse->name ?? 'N/A'));
                $toAddr->addChild('City', htmlspecialchars($first->toWarehouse->city ?? 'Luanda'));
                $toAddr->addChild('PostalCode', '0000');
                $toAddr->addChild('Country', 'AO');
            } else if ($first->warehouse) {
                $shipFrom = $stockDoc->addChild('ShipFrom');
                $shipFrom->addChild('DeliveryDate', $first->created_at->format('Y-m-d'));
                $fromAddr = $shipFrom->addChild('Address');
                $fromAddr->addChild('AddressDetail', htmlspecialchars($first->warehouse->address ?? $first->warehouse->name ?? 'N/A'));
                $fromAddr->addChild('City', htmlspecialchars($first->warehouse->city ?? 'Luanda'));
                $fromAddr->addChild('PostalCode', '0000');
                $fromAddr->addChild('Country', 'AO');
            }
            
            // Lines
            $lineNum = 0;
            foreach ($group as $mov) {
                $lineNum++;
                $line = $stockDoc->addChild('Line');
                $line->addChild('LineNumber', $lineNum);
                $line->addChild('ProductCode', $mov->product->sku ?? (string) $mov->product_id);
                $line->addChild('ProductDescription', htmlspecialchars($mov->product->name ?? ''));
                $line->addChild('Quantity', number_format(abs($mov->quantity), 2, '.', ''));
                $line->addChild('UnitOfMeasure', 'UN');
                $line->addChild('UnitPrice', number_format($mov->unit_cost ?? 0, 2, '.', ''));
                $line->addChild('Description', htmlspecialchars($mov->notes ?? ($mov->product->name ?? '')));
                
                $lineTotal = abs($mov->total_cost ?? ($mov->quantity * ($mov->unit_cost ?? 0)));
                if (in_array($mov->type, ['in', 'adjustment'])) {
                    $line->addChild('CreditAmount', number_format($lineTotal, 2, '.', ''));
                } else {
                    $line->addChild('DebitAmount', number_format($lineTotal, 2, '.', ''));
                }
            }
            
            // DocumentTotals — somar valores absolutos de cada linha
            $docTotal = $group->sum(function ($m) {
                return abs($m->total_cost ?? ($m->quantity * ($m->unit_cost ?? 0)));
            });
            $totals = $stockDoc->addChild('DocumentTotals');
            $totals->addChild('TaxPayable', '0.00');
            $totals->addChild('NetTotal', number_format($docTotal, 2, '.', ''));
            $totals->addChild('GrossTotal', number_format($docTotal, 2, '.', ''));
        }
    }

    /**
     * Mapear método de pagamento para código SAFT-AO
     */
    private function mapPaymentMethod(?string $method): string
    {
        return match(strtolower($method ?? '')) {
            'cash', 'dinheiro', 'numerario' => 'NU',
            'transfer', 'transferencia', 'transferência' => 'TB',
            'check', 'cheque' => 'CH',
            'card', 'cartao', 'cartão', 'tpa' => 'CC',
            'multicaixa', 'mce' => 'DE',
            'mbway', 'mb way' => 'DE',
            default => 'OU', // Outro
        };
    }

    public function render()
    {
        return view('livewire.invoicing.saftgenerator');
    }
}
