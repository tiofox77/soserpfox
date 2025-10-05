<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Product;
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
    public $documentType = 'all'; // all, sales, purchases
    public $includeProducts = true;
    public $includeCustomers = true;
    public $includeSuppliers = true;
    
    // Stats
    public $totalInvoices = 0;
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
        // Faturas de Venda
        $salesQuery = SalesInvoice::where('tenant_id', activeTenantId())
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled']);

        // Faturas de Compra
        $purchasesQuery = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled']);

        if ($this->documentType === 'sales') {
            $this->totalInvoices = $salesQuery->count();
            $this->totalValue = $salesQuery->sum('total');
        } elseif ($this->documentType === 'purchases') {
            $this->totalInvoices = $purchasesQuery->count();
            $this->totalValue = $purchasesQuery->sum('total');
        } else {
            $this->totalInvoices = $salesQuery->count() + $purchasesQuery->count();
            $this->totalValue = $salesQuery->sum('total') + $purchasesQuery->sum('total');
        }

        $this->totalCustomers = Client::where('tenant_id', activeTenantId())->count();
        $this->totalSuppliers = Supplier::where('tenant_id', activeTenantId())->count();
        $this->totalProducts = Product::where('tenant_id', activeTenantId())->count();
    }

    public function generateSAFT()
    {
        try {
            // Gerar XML SAFT
            $xml = $this->buildSAFTXML();
            
            // Nome do arquivo
            $fileName = 'SAFT_AO_' . activeTenantId() . '_' . date('Y-m-d') . '.xml';
            
            // Salvar temporariamente
            Storage::disk('local')->put('saft/' . $fileName, $xml);
            
            // Download
            return response()->streamDownload(function () use ($xml) {
                echo $xml;
            }, $fileName, [
                'Content-Type' => 'application/xml',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao gerar SAFT: ' . $e->getMessage()
            ]);
        }
    }

    private function buildSAFTXML()
    {
        $tenant = auth()->user()->activeTenant();
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AuditFile></AuditFile>');
        $xml->addAttribute('xmlns', 'urn:OECD:StandardAuditFile-Tax:AO_1.01_01');
        
        // Header
        $header = $xml->addChild('Header');
        $header->addChild('AuditFileVersion', '1.01_01');
        $header->addChild('CompanyID', $tenant->nif ?? 'N/A');
        $header->addChild('TaxRegistrationNumber', $tenant->nif ?? 'N/A');
        $header->addChild('TaxAccountingBasis', 'F'); // Faturação
        $header->addChild('CompanyName', $tenant->name);
        
        $companyAddress = $header->addChild('CompanyAddress');
        $companyAddress->addChild('AddressDetail', $tenant->address ?? 'N/A');
        $companyAddress->addChild('City', $tenant->city ?? 'Luanda');
        $companyAddress->addChild('PostalCode', $tenant->postal_code ?? '0000');
        $companyAddress->addChild('Country', 'AO');
        
        $header->addChild('FiscalYear', date('Y'));
        $header->addChild('StartDate', $this->startDate);
        $header->addChild('EndDate', $this->endDate);
        $header->addChild('CurrencyCode', 'AOA');
        $header->addChild('DateCreated', date('Y-m-d'));
        $header->addChild('TaxEntity', 'Global');
        $header->addChild('ProductCompanyTaxID', $tenant->nif ?? 'N/A');
        $header->addChild('SoftwareCertificateNumber', '0');
        $header->addChild('ProductID', 'SOS ERP/1.0');
        $header->addChild('ProductVersion', '1.0');
        
        // MasterFiles
        $masterFiles = $xml->addChild('MasterFiles');
        
        // Customers
        if ($this->includeCustomers && ($this->documentType === 'all' || $this->documentType === 'sales')) {
            $clients = Client::where('tenant_id', activeTenantId())->get();
            foreach ($clients as $client) {
                $customer = $masterFiles->addChild('Customer');
                $customer->addChild('CustomerID', $client->id);
                $customer->addChild('AccountID', 'Desconhecido');
                
                // CustomerTaxID: deve ser válido, usar 999999999 se vazio
                $nif = trim($client->nif ?? '');
                $customerTaxID = !empty($nif) && strlen($nif) === 9 ? $nif : '999999999';
                $customer->addChild('CustomerTaxID', $customerTaxID);
                
                $customer->addChild('CompanyName', htmlspecialchars($client->name));
                
                $billingAddress = $customer->addChild('BillingAddress');
                $billingAddress->addChild('AddressDetail', htmlspecialchars($client->address ?? 'N/A'));
                $billingAddress->addChild('City', htmlspecialchars($client->city ?? 'Luanda'));
                $billingAddress->addChild('PostalCode', $client->postal_code ?? '0000');
                
                // Country: ISO 3166-1-alpha-2 (2 letras) usando método do model
                $billingAddress->addChild('Country', $client->country_code);
                
                $customer->addChild('SelfBillingIndicator', '0');
            }
        }

        // Suppliers
        if ($this->includeSuppliers && ($this->documentType === 'all' || $this->documentType === 'purchases')) {
            $suppliers = Supplier::where('tenant_id', activeTenantId())->get();
            foreach ($suppliers as $supplier) {
                $supplierNode = $masterFiles->addChild('Supplier');
                $supplierNode->addChild('SupplierID', $supplier->id);
                $supplierNode->addChild('AccountID', 'Desconhecido');
                
                // SupplierTaxID: DEVE ser válido (9 dígitos), usar 999999999 se vazio ou inválido
                $supplierNif = trim($supplier->nif ?? '');
                $supplierTaxID = !empty($supplierNif) && strlen($supplierNif) === 9 && is_numeric($supplierNif) 
                    ? $supplierNif 
                    : '999999999';
                $supplierNode->addChild('SupplierTaxID', $supplierTaxID);
                
                $supplierNode->addChild('CompanyName', htmlspecialchars($supplier->name));
                
                $billingAddress = $supplierNode->addChild('BillingAddress');
                $billingAddress->addChild('AddressDetail', htmlspecialchars($supplier->address ?? 'N/A'));
                $billingAddress->addChild('City', htmlspecialchars($supplier->city ?? 'Luanda'));
                $billingAddress->addChild('PostalCode', $supplier->postal_code ?? '0000');
                
                // Country: ISO 3166-1-alpha-2 (2 letras) usando método do model
                $billingAddress->addChild('Country', $supplier->country_code);
                
                $supplierNode->addChild('SelfBillingIndicator', '0');
            }
        }

        // Products
        if ($this->includeProducts) {
            $products = Product::where('tenant_id', activeTenantId())->get();
            foreach ($products as $product) {
                $productNode = $masterFiles->addChild('Product');
                $productNode->addChild('ProductType', $product->type === 'product' ? 'P' : 'S');
                $productNode->addChild('ProductCode', $product->sku ?? $product->id);
                $productNode->addChild('ProductGroup', $product->category->name ?? 'Geral');
                $productNode->addChild('ProductDescription', htmlspecialchars($product->name));
                $productNode->addChild('ProductNumberCode', $product->barcode ?? $product->sku ?? $product->id);
            }
        }

        // SourceDocuments
        $sourceDocuments = $xml->addChild('SourceDocuments');
        
        // SalesInvoices
        if ($this->documentType === 'all' || $this->documentType === 'sales') {
            $salesInvoices = $sourceDocuments->addChild('SalesInvoices');
            
            $invoices = SalesInvoice::where('tenant_id', activeTenantId())
                ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->with(['client', 'items.product'])
                ->get();
            
            $salesInvoices->addChild('NumberOfEntries', $invoices->count());
            $salesInvoices->addChild('TotalDebit', '0.00');
            $salesInvoices->addChild('TotalCredit', number_format($invoices->sum('total'), 2, '.', ''));
            
            foreach ($invoices as $invoice) {
                $invoiceNode = $salesInvoices->addChild('Invoice');
                $invoiceNode->addChild('InvoiceNo', $invoice->invoice_number);
                $invoiceNode->addChild('DocumentStatus');
                $invoiceNode->DocumentStatus->addChild('InvoiceStatus', 'N'); // Normal
                $invoiceNode->DocumentStatus->addChild('InvoiceStatusDate', $invoice->invoice_date->format('Y-m-d\TH:i:s'));
                $invoiceNode->DocumentStatus->addChild('SourceID', $invoice->created_by);
                $invoiceNode->DocumentStatus->addChild('SourceBilling', 'P');
                
                $invoiceNode->addChild('Hash', $invoice->saft_hash ?? '0');
                $invoiceNode->addChild('InvoiceDate', $invoice->invoice_date->format('Y-m-d'));
                $invoiceNode->addChild('InvoiceType', 'FT');
                $invoiceNode->addChild('SourceID', $invoice->created_by);
                $invoiceNode->addChild('SystemEntryDate', $invoice->created_at->format('Y-m-d\TH:i:s'));
                $invoiceNode->addChild('CustomerID', $invoice->client_id);
                
                // Lines
                foreach ($invoice->items as $index => $item) {
                    $line = $invoiceNode->addChild('Line');
                    $line->addChild('LineNumber', $index + 1);
                    $line->addChild('ProductCode', $item->product->sku ?? $item->product_id);
                    $line->addChild('ProductDescription', htmlspecialchars($item->description));
                    $line->addChild('Quantity', number_format($item->quantity, 2, '.', ''));
                    $line->addChild('UnitOfMeasure', 'UN');
                    $line->addChild('UnitPrice', number_format($item->unit_price, 2, '.', ''));
                    $line->addChild('TaxPointDate', $invoice->invoice_date->format('Y-m-d'));
                    $line->addChild('Description', htmlspecialchars($item->description));
                    $line->addChild('CreditAmount', number_format($item->subtotal, 2, '.', ''));
                    
                    $tax = $line->addChild('Tax');
                    $tax->addChild('TaxType', 'IVA');
                    $tax->addChild('TaxCountryRegion', 'AO');
                    $tax->addChild('TaxCode', 'NOR');
                    $tax->addChild('TaxPercentage', number_format($item->tax_rate ?? 14, 2, '.', ''));
                }
                
                // DocumentTotals
                $totals = $invoiceNode->addChild('DocumentTotals');
                $totals->addChild('TaxPayable', number_format($invoice->tax_amount, 2, '.', ''));
                $totals->addChild('NetTotal', number_format($invoice->subtotal, 2, '.', ''));
                $totals->addChild('GrossTotal', number_format($invoice->total, 2, '.', ''));
            }
        }

        return $xml->asXML();
    }

    public function render()
    {
        return view('livewire.invoicing.saftgenerator');
    }
}

