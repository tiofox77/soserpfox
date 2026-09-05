<?php

namespace App\Livewire\Projetos;

use App\Models\Client;
use App\Models\Projetos\Projeto;
use App\Models\User;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Projetos\FluxoDoProjeto;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista de projetos, com o detalhe onde vive o orçamento e a facturação.
 *
 * O ecrã segue a página de Produtos (padrão da casa). Projetos em violeta.
 */
#[Layout('layouts.app')]
#[Title('Projetos')]
class Projetos extends Component
{
    use WithPagination;

    public string $procurar = '';

    public string $estado = 'todos';

    // ── Criar / editar ───────────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editandoId = null;

    public string $nome = '';

    public ?int $clientId = null;

    public ?int $responsavelId = null;

    public string $dataInicio = '';

    public string $dataFimPrevista = '';

    public string $orcamento = '';

    public string $valorHora = '';

    public string $descricao = '';

    // ── Detalhe ──────────────────────────────────────────────────────────
    public ?int $verId = null;

    public ?int $confirmarFacturarId = null;

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function novoProjeto(): void
    {
        $this->reset(['editandoId', 'nome', 'clientId', 'responsavelId', 'dataInicio',
            'dataFimPrevista', 'orcamento', 'valorHora', 'descricao']);
        $this->dataInicio = now()->format('Y-m-d');
        $this->showForm = true;
    }

    public function editar(int $id): void
    {
        $p = $this->meu($id);

        $this->editandoId = $p->id;
        $this->nome = $p->nome;
        $this->clientId = $p->client_id;
        $this->responsavelId = $p->responsavel_id;
        $this->dataInicio = $p->data_inicio?->format('Y-m-d') ?? '';
        $this->dataFimPrevista = $p->data_fim_prevista?->format('Y-m-d') ?? '';
        $this->orcamento = $p->orcamento !== null ? (string) (float) $p->orcamento : '';
        $this->valorHora = $p->valor_hora !== null ? (string) (float) $p->valor_hora : '';
        $this->descricao = (string) $p->descricao;
        $this->showForm = true;
    }

    public function guardar(FluxoDoProjeto $fluxo): void
    {
        if (! $this->podeOu('projetos.gerir')) {
            return;
        }

        $dados = [
            'nome' => $this->nome,
            'client_id' => $this->clientId ?: null,
            'responsavel_id' => $this->responsavelId ?: null,
            'data_inicio' => $this->dataInicio ?: null,
            'data_fim_prevista' => $this->dataFimPrevista ?: null,
            'orcamento' => $this->orcamento,
            'valor_hora' => $this->valorHora,
            'descricao' => $this->descricao,
        ];

        try {
            if ($this->editandoId) {
                $fluxo->actualizar($this->meu($this->editandoId), activeTenantId(), $dados);
                $mensagem = 'Projeto actualizado.';
            } else {
                $fluxo->criar(activeTenantId(), auth()->id(), $dados);
                $mensagem = 'Projeto criado como rascunho.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->reset(['editandoId', 'nome', 'descricao']);
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    public function mudarEstado(int $id, string $novo, FluxoDoProjeto $fluxo): void
    {
        if (! $this->podeOu('projetos.gerir')) {
            return;
        }

        try {
            $p = $fluxo->mudarEstado($this->meu($id), activeTenantId(), $novo);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('notify', type: 'success', message: "Projeto {$p->codigo}: {$p->estadoRotulo()}.");
    }

    public function facturar(FluxoDoProjeto $fluxo, ModuleInvoiceService $facturacao): void
    {
        if (! $this->confirmarFacturarId || ! $this->podeOu('projetos.facturar')) {
            return;
        }

        try {
            $factura = $fluxo->facturarHoras($this->meu($this->confirmarFacturarId), activeTenantId(), $facturacao);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
            $this->confirmarFacturarId = null;

            return;
        }

        $this->confirmarFacturarId = null;
        $this->dispatch('notify', type: 'success',
            message: "Factura {$factura->invoice_number} criada em rascunho com as horas do projeto.");
    }

    /** Fronteira de autoridade: facturar emite um documento a um cliente. */
    private function podeOu(string $permissao): bool
    {
        if (auth()->user()?->can($permissao)) {
            return true;
        }

        $this->dispatch('notify', type: 'error', message: 'Não tem permissão para isto.');

        return false;
    }

    private function meu(int $id): Projeto
    {
        return Projeto::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function getDetalheProperty(): ?array
    {
        if (! $this->verId) {
            return null;
        }

        $p = Projeto::where('tenant_id', activeTenantId())
            ->with(['cliente:id,name', 'responsavel:id,name', 'autor:id,name'])
            ->find($this->verId);

        if (! $p) {
            return null;
        }

        return [
            'projeto' => $p,
            'consumido' => $p->consumido(),
            'horas' => $p->horasLancadas(),
            'percentagem' => $p->percentagemDoOrcamento(),
            'porFacturar' => app(FluxoDoProjeto::class)->porFacturar($p),
            // O laço fecha aqui: o que já saiu para cobrança e em que
            // documentos. Sem isto, facturava-se e perdia-se o rasto.
            'facturado' => $p->facturado(),
            'horasFacturadas' => $p->horasFacturadas(),
            'facturas' => $p->facturas(),
            'tarefas' => [
                'abertas' => $p->tarefas()->whereIn('estado', \App\Models\Projetos\Tarefa::ABERTAS)->count(),
                'total' => $p->tarefas()->count(),
            ],
        ];
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $base = Projeto::where('tenant_id', $tenantId)
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)->orWhere('codigo', 'like', $t));
            });

        $resumo = [
            'activos' => Projeto::where('tenant_id', $tenantId)->where('estado', 'activo')->count(),
            'concluidos' => Projeto::where('tenant_id', $tenantId)->where('estado', 'concluido')->count(),
            'total' => Projeto::where('tenant_id', $tenantId)->count(),
        ];

        return view('livewire.projetos.projetos', [
            'projetos' => $base->with(['cliente:id,name', 'responsavel:id,name'])->latest()->paginate(12),
            'resumo' => $resumo,
            'clientes' => Client::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'utilizadores' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }
}
