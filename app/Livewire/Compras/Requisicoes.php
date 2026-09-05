<?php

namespace App\Livewire\Compras;

use App\Models\Compras\Requisicao;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\Compras\FluxoDaRequisicao;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Requisições de compra — o pedido interno, antes de haver fornecedor.
 *
 * O ecrã segue a página de Produtos (padrão da casa): cabeçalho em gradiente,
 * cartões arredondados, lista com acções no hover e tudo o que decide em
 * modais. Compras em lima→verde.
 */
#[Layout('layouts.app')]
#[Title('Requisições de Compra')]
class Requisicoes extends Component
{
    use WithPagination;

    public string $procurar = '';

    public string $estado = 'todos';

    // ── Modal de criar/editar ────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editandoId = null;

    public ?int $warehouseId = null;

    public string $necessariaEm = '';

    public string $justificacao = '';

    /** @var array<int, array<string, mixed>> */
    public array $linhas = [];

    public string $procuraArtigo = '';

    // ── Modal de ver/decidir ─────────────────────────────────────────────
    public ?int $verId = null;

    public bool $showRejeitar = false;

    public string $motivoRecusa = '';

    public ?int $confirmarCancelarId = null;

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Criar / editar
    // ─────────────────────────────────────────────────────────────────────

    public function novaRequisicao(): void
    {
        $this->reset(['editandoId', 'necessariaEm', 'justificacao', 'linhas', 'procuraArtigo']);
        $this->warehouseId = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)->orderBy('id')->value('id');
        $this->showForm = true;
    }

    public function editar(int $id): void
    {
        $req = Requisicao::where('tenant_id', activeTenantId())->with('itens')->findOrFail($id);

        if (! $req->podeEditar()) {
            $this->dispatch('notify', type: 'error', message: 'Só um rascunho se edita.');

            return;
        }

        $this->editandoId = $req->id;
        $this->warehouseId = $req->warehouse_id;
        $this->necessariaEm = $req->necessaria_em?->format('Y-m-d') ?? '';
        $this->justificacao = (string) $req->justificacao;
        $this->linhas = $req->itens->map(fn ($i) => [
            'product_id' => $i->product_id,
            'descricao' => $i->descricao,
            'quantidade' => (float) $i->quantidade,
            'custo_estimado' => $i->custo_estimado !== null ? (float) $i->custo_estimado : '',
            'unidade' => $i->unidade,
            'notas' => $i->notas,
        ])->all();
        $this->procuraArtigo = '';
        $this->showForm = true;
    }

    /** Sugestões do catálogo enquanto se escreve (a partir de 2 letras). */
    public function getSugestoesProperty()
    {
        $termo = trim($this->procuraArtigo);

        if (mb_strlen($termo) < 2) {
            return collect();
        }

        return Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'code', 'unit', 'cost']);
    }

    public function adicionarDoCatalogo(int $productId): void
    {
        $p = Product::where('tenant_id', activeTenantId())->find($productId);

        if (! $p) {
            return;
        }

        $this->linhas[] = [
            'product_id' => $p->id,
            'descricao' => $p->name,
            'quantidade' => 1,
            'custo_estimado' => $p->cost ? (float) $p->cost : '',
            'unidade' => $p->unit,
            'notas' => null,
        ];
        $this->procuraArtigo = '';
    }

    /** Para pedir algo que ainda não existe no catálogo — caso legítimo e comum. */
    public function adicionarLinhaLivre(): void
    {
        $this->linhas[] = [
            'product_id' => null,
            'descricao' => '',
            'quantidade' => 1,
            'custo_estimado' => '',
            'unidade' => null,
            'notas' => null,
        ];
    }

    public function removerLinha(int $indice): void
    {
        unset($this->linhas[$indice]);
        $this->linhas = array_values($this->linhas);
    }

    public function guardar(FluxoDaRequisicao $fluxo): void
    {
        if (! $this->podeOu('compras.requisicoes.manage')) {
            return;
        }

        $dados = [
            'warehouse_id' => $this->warehouseId ?: null,
            'necessaria_em' => $this->necessariaEm ?: null,
            'justificacao' => trim($this->justificacao) ?: null,
        ];

        try {
            if ($this->editandoId) {
                $req = Requisicao::where('tenant_id', activeTenantId())->findOrFail($this->editandoId);
                $fluxo->actualizar($req, activeTenantId(), $dados, $this->linhas);
                $mensagem = 'Requisição actualizada.';
            } else {
                $fluxo->criar(activeTenantId(), auth()->id(), $dados, $this->linhas);
                $mensagem = 'Requisição criada como rascunho.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->reset(['editandoId', 'linhas', 'justificacao', 'necessariaEm', 'procuraArtigo']);
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Circuito
    // ─────────────────────────────────────────────────────────────────────

    public function submeter(int $id, FluxoDaRequisicao $fluxo): void
    {
        if (! $this->podeOu('compras.requisicoes.manage')) {
            return;
        }

        $this->correr(fn () => $fluxo->submeter($this->minha($id), activeTenantId()), 'Requisição submetida para aprovação.');
    }

    public function aprovar(int $id, FluxoDaRequisicao $fluxo): void
    {
        if (! $this->podeOu('compras.requisicoes.decidir')) {
            return;
        }

        $this->correr(fn () => $fluxo->aprovar($this->minha($id), activeTenantId(), auth()->id()), 'Requisição aprovada — já pode virar encomenda.');
    }

    /** Abre a caixa do motivo. Um método só, em vez de dois `$set` na vista. */
    public function abrirRecusa(int $id): void
    {
        if (! $this->podeOu('compras.requisicoes.decidir')) {
            return;
        }

        $this->verId = $id;
        $this->motivoRecusa = '';
        $this->showRejeitar = true;
    }

    public function rejeitar(FluxoDaRequisicao $fluxo): void
    {
        if (! $this->verId || ! $this->podeOu('compras.requisicoes.decidir')) {
            return;
        }

        $feito = $this->correr(
            fn () => $fluxo->rejeitar($this->minha($this->verId), activeTenantId(), auth()->id(), $this->motivoRecusa),
            'Requisição recusada.'
        );

        if ($feito) {
            $this->showRejeitar = false;
            $this->motivoRecusa = '';
        }
    }

    public function cancelar(FluxoDaRequisicao $fluxo): void
    {
        if (! $this->confirmarCancelarId) {
            return;
        }

        $this->correr(fn () => $fluxo->cancelar($this->minha($this->confirmarCancelarId), activeTenantId()), 'Requisição cancelada.');
        $this->confirmarCancelarId = null;
    }

    /**
     * Quem pede não é quem aprova.
     *
     * A separação é a razão de ser de uma requisição: sem ela, o circuito é
     * só burocracia. Os botões já se escondem na vista, mas a decisão de
     * autoridade tem de valer também quando o pedido chega por outra via.
     */
    private function podeOu(string $permissao): bool
    {
        if (auth()->user()?->can($permissao)) {
            return true;
        }

        $this->dispatch('notify', type: 'error', message: 'Não tem permissão para isto.');

        return false;
    }

    /** Corre a acção e transforma um erro de domínio em aviso no ecrã. */
    private function correr(callable $accao, string $sucesso): bool
    {
        try {
            $accao();
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return false;
        }

        $this->dispatch('notify', type: 'success', message: $sucesso);

        return true;
    }

    /** Nunca confiar no id que vem do browser. */
    private function minha(int $id): Requisicao
    {
        return Requisicao::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function getDetalheProperty(): ?Requisicao
    {
        if (! $this->verId) {
            return null;
        }

        return Requisicao::where('tenant_id', activeTenantId())
            ->with(['itens.produto:id,name,code', 'warehouse:id,name', 'autor:id,name', 'decisor:id,name', 'encomendas:id,numero,requisicao_id,estado'])
            ->find($this->verId);
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $base = Requisicao::where('tenant_id', $tenantId)
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)
                    ->orWhere('justificacao', 'like', $t)
                    ->orWhereHas('itens', fn ($i) => $i->where('descricao', 'like', $t)));
            });

        $resumo = [
            'submetidas' => Requisicao::where('tenant_id', $tenantId)->where('estado', 'submetida')->count(),
            'aprovadas' => Requisicao::where('tenant_id', $tenantId)->where('estado', 'aprovada')->count(),
            'rascunhos' => Requisicao::where('tenant_id', $tenantId)->where('estado', 'rascunho')->count(),
            'total' => Requisicao::where('tenant_id', $tenantId)->count(),
        ];

        return view('livewire.compras.requisicoes', [
            'requisicoes' => $base->with(['itens:id,requisicao_id,descricao,quantidade', 'autor:id,name', 'warehouse:id,name'])
                ->latest()->paginate(15),
            'resumo' => $resumo,
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
