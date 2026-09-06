<?php

namespace App\Livewire\Invoicing;

use App\Services\Invoicing\GeradorDeSaft;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O GERADOR SAFT-AO, o ecrã de sempre.
 *
 * O XML e as contagens vivem no `GeradorDeSaft`, partilhado com o ecrã em
 * React. Aqui fica só o que é do ecrã: o período, as secções e a descarga.
 */
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
        foreach ($this->gerador()->estatisticas() as $campo => $valor) {
            $this->{$campo} = $valor;
        }
    }

    public function generateSAFT()
    {
        try {
            $saft = $this->gerador()->gerar();

            return response()->streamDownload(function () use ($saft) {
                echo $saft['xml'];
            }, $saft['ficheiro'], [
                'Content-Type' => 'application/xml',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao gerar SAFT: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    /** O serviço com o período e as secções que o ecrã tem neste momento. */
    private function gerador(): GeradorDeSaft
    {
        return new GeradorDeSaft(
            (int) activeTenantId(),
            (string) $this->startDate,
            (string) $this->endDate,
            (string) ($this->documentType ?: 'all'),
            array_intersect_key(get_object_vars($this), GeradorDeSaft::SECCOES),
        );
    }

    public function render()
    {
        return view('livewire.invoicing.saftgenerator');
    }
}
