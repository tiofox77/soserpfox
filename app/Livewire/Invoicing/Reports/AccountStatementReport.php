<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use App\Services\Invoicing\ContaCorrenteQuery;
use App\Services\Invoicing\Relatorios\ExtractoDeConta;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Extracto de conta corrente — de um cliente ou de um fornecedor.
 *
 * Responde à pergunta que um cliente faz ao telefone: "o que é que eu devo,
 * e porquê?", pela ordem dos acontecimentos, com saldo acumulado. A procura
 * e o extracto vêm do `ExtractoDeConta`, partilhado com o React.
 */
#[Layout('layouts.app')]
#[Title('Extracto de Conta Corrente')]
class AccountStatementReport extends Component
{
    use HasReportFilters;
    use UsaRelatorio;

    public const RELATORIO = 'account-statement';

    /** 'cliente' ou 'fornecedor' — as duas contas usam o mesmo extracto. */
    public string $entidade = ContaCorrenteQuery::CLIENTE;
    public ?int $entidadeId = null;
    public string $procura = '';

    public function mount(): void
    {
        // Um ano: um extracto de conta corrente lê-se para trás, não para o mês.
        $this->period = 'custom';
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedEntidade(): void
    {
        // Um id de cliente não serve para procurar um fornecedor.
        $this->entidadeId = null;
        $this->procura = '';
    }

    public function selecionar(int $id): void
    {
        $this->entidadeId = $id;
        $this->procura = '';
    }

    public function limpar(): void
    {
        $this->entidadeId = null;
        $this->procura = '';
    }

    /** Resultados da procura, já limitados à empresa activa. */
    public function getResultadosProperty()
    {
        return (new ExtractoDeConta())->procurar((int) activeTenantId(), $this->entidade, $this->procura);
    }

    public function getEntidadeSelecionadaProperty()
    {
        return (new ExtractoDeConta())->entidade((int) activeTenantId(), $this->entidade, $this->entidadeId);
    }

    public function render()
    {
        return view('livewire.invoicing.reports.account-statement-report', $this->dadosDoRelatorio());
    }
}
