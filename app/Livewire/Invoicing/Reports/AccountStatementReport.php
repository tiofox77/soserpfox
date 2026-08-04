<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Client;
use App\Models\Supplier;
use App\Services\Invoicing\ContaCorrenteQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Extracto de conta corrente — de um cliente ou de um fornecedor.
 *
 * Não existia. Havia o mapa de contas a receber (quanto está por pagar, hoje) e
 * o aging (há quanto tempo), mas nenhum respondia à pergunta que um cliente faz
 * ao telefone: "o que é que eu devo, e porquê?".
 *
 * O extracto responde pela ordem dos acontecimentos, com saldo acumulado e o
 * saldo que transitava de antes do período.
 */
#[Layout('layouts.app')]
#[Title('Extracto de Conta Corrente')]
class AccountStatementReport extends Component
{
    use HasReportFilters;

    /** 'cliente' ou 'fornecedor' — as duas contas usam o mesmo extracto. */
    public string $entidade = ContaCorrenteQuery::CLIENTE;

    public ?int $entidadeId = null;

    public string $procura = '';

    public function mount(): void
    {
        // Um ano: um extracto de conta corrente lê-se para trás, não para o mês.
        $this->period   = 'custom';
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    public function updatedEntidade(): void
    {
        // Um id de cliente não serve para procurar um fornecedor.
        $this->entidadeId = null;
        $this->procura    = '';
    }

    public function selecionar(int $id): void
    {
        $this->entidadeId = $id;
        $this->procura    = '';
    }

    public function limpar(): void
    {
        $this->entidadeId = null;
        $this->procura    = '';
    }

    private function ehCliente(): bool
    {
        return $this->entidade === ContaCorrenteQuery::CLIENTE;
    }

    /** Resultados da procura, já limitados à empresa activa. */
    public function getResultadosProperty()
    {
        $termo = trim($this->procura);

        if ($termo === '') {
            return collect();
        }

        $modelo = $this->ehCliente() ? Client::class : Supplier::class;

        return $modelo::where('tenant_id', activeTenantId())
            ->where(fn ($q) => $q->where('name', 'like', "%{$termo}%")
                                 ->orWhere('nif', 'like', "%{$termo}%"))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'nif']);
    }

    public function getEntidadeSelecionadaProperty()
    {
        if (!$this->entidadeId) {
            return null;
        }

        $modelo = $this->ehCliente() ? Client::class : Supplier::class;

        return $modelo::where('tenant_id', activeTenantId())->find($this->entidadeId);
    }

    private function consulta(): ContaCorrenteQuery
    {
        return new ContaCorrenteQuery(
            activeTenantId(),
            $this->entidade,
            $this->entidadeId,
            $this->dateFrom,
            $this->dateTo,
        );
    }

    public function render()
    {
        $consulta = $this->consulta();

        return view('livewire.invoicing.reports.account-statement-report', [
            'movimentos' => $this->entidadeId ? $consulta->movimentos() : collect(),
            'resumo'     => $this->entidadeId ? $consulta->resumo() : null,
        ]);
    }
}
