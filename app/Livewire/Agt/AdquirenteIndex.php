<?php

namespace App\Livewire\Agt;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTErrorCode;
use App\Services\AGT\AGTHttpClient;
use App\Services\AGT\AGTPayloadBuilder;
use App\Services\AGT\QueryService;
use App\Services\AGT\ValidateService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel do Adquirente — DS.120 §§4.3, 4.4, 4.7.
 *
 * Funcionalidades:
 *  - Listar facturas recebidas por período (/listarFacturas)
 *  - Consultar detalhe de factura (/consultarFactura)
 *  - Confirmar (C) ou Rejeitar (R) facturas (/validarDocumento)
 */
#[Layout('layouts.app')]
#[Title('Facturas Recebidas (Adquirente) — AGT')]
class AdquirenteIndex extends Component
{
    public string $queryStartDate;
    public string $queryEndDate;
    public array $invoices = [];
    public ?array $selectedDocument = null;
    public ?string $selectedDocumentNo = null;
    public bool $loading = false;
    public ?string $errorMessage = null;

    // Validação (4.7)
    public bool $showValidationModal = false;
    public string $validationAction = 'C'; // C ou R
    public ?float $deductibleVATPercentage = null;
    public ?float $nonDeductibleAmount = null;

    public function mount(): void
    {
        $this->queryStartDate = now()->startOfMonth()->format('Y-m-d');
        $this->queryEndDate   = now()->format('Y-m-d');
    }

    /** Lista facturas recebidas no período (DS.120 §4.3). */
    public function listInvoices(): void
    {
        $this->loading      = true;
        $this->errorMessage = null;
        $this->invoices     = [];

        try {
            $tenantId = auth()->user()?->tenant_id;
            $settings = InvoicingSettings::forTenant($tenantId);
            if (!$settings) {
                $this->errorMessage = 'Configurações AGT do tenant não encontradas.';
                return;
            }

            $builder = new AGTPayloadBuilder($settings);
            $http    = new AGTHttpClient($settings);
            $nif     = $settings->tenant?->nif ?? $settings->company_nif ?? '';

            $payload = $builder->buildListarFacturas(
                $nif,
                $this->queryStartDate,
                $this->queryEndDate
            );

            $result = $http->post(
                AGTHttpClient::ENDPOINT_LIST_INVOICES,
                $payload,
                'ListarFacturas'
            );

            if (!$result['ok']) {
                $this->errorMessage = AGTErrorCode::formatList(
                    $result['response']['errorList'] ?? $result['error']
                );
                return;
            }

            $this->invoices = $result['response']['resultEntryList'] ?? [];
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            Log::error('AdquirenteIndex::listInvoices falhou', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    /** Abre detalhe de uma factura (DS.120 §4.4). */
    public function viewDocument(string $documentNo): void
    {
        $this->loading           = true;
        $this->errorMessage      = null;
        $this->selectedDocument  = null;
        $this->selectedDocumentNo = $documentNo;

        try {
            $tenantId = auth()->user()?->tenant_id;
            $settings = InvoicingSettings::forTenant($tenantId);
            $service  = new QueryService($settings);
            $result   = $service->consultByNumber($documentNo);

            if (!$result['ok']) {
                $this->errorMessage = AGTErrorCode::formatList(
                    $result['response']['errorList'] ?? $result['error']
                );
                return;
            }

            $this->selectedDocument = $result['response'];
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function openValidationModal(string $action): void
    {
        $this->validationAction        = in_array($action, ['C', 'R'], true) ? $action : 'C';
        $this->deductibleVATPercentage = null;
        $this->nonDeductibleAmount     = null;
        $this->showValidationModal     = true;
    }

    /** Confirma ou rejeita o documento seleccionado (DS.120 §4.7). */
    public function submitValidation(): void
    {
        if (!$this->selectedDocumentNo) {
            $this->errorMessage = 'Nenhum documento seleccionado.';
            return;
        }

        $this->loading      = true;
        $this->errorMessage = null;

        try {
            $tenantId = auth()->user()?->tenant_id;
            $settings = InvoicingSettings::forTenant($tenantId);
            $service  = new ValidateService($settings);

            $result = $this->validationAction === 'C'
                ? $service->confirm(
                    $this->selectedDocumentNo,
                    $this->deductibleVATPercentage,
                    $this->nonDeductibleAmount
                )
                : $service->reject($this->selectedDocumentNo);

            if (!$result['ok']) {
                $this->errorMessage = AGTErrorCode::formatList(
                    $result['errorList'] ?? $result['error']
                );
                return;
            }

            session()->flash('agt_success', sprintf(
                'Documento %s: %s (%s)',
                $this->selectedDocumentNo,
                $result['actionResultCode'] ?? 'OK',
                $result['documentStatusCode'] ?? '—'
            ));
            $this->showValidationModal = false;
            $this->listInvoices(); // refresh
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function render()
    {
        return view('livewire.agt.adquirente-index');
    }
}

