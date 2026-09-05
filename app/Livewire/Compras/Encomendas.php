<?php

namespace App\Livewire\Compras;

use App\Models\Compras\Encomenda;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Compras\FluxoDaEncomenda;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Encomendas ao fornecedor — enviar, receber e facturar.
 *
 * A recepção é o momento em que o stock entra, por isso vive aqui num modal
 * próprio que mostra o que falta linha a linha. Facturar só aparece depois de
 * ter chegado alguma coisa.
 */
#[Layout('layouts.app')]
#[Title('Encomendas de Compra')]
class Encomendas extends Component
{
    use WithPagination;

    public string $procurar = '';

    public string $estado = 'todos';

    // ── Criar / editar ───────────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editandoId = null;

    public ?int $supplierId = null;

    public ?int $warehouseId = null;

    public ?int $requisicaoId = null;

    public string $dataEncomenda = '';

    public string $entregaPrevista = '';

    public string $notas = '';

    /** @var array<int, array<string, mixed>> */
    public array $linhas = [];

    public string $procuraArtigo = '';

    // ── Ver / receber / facturar ─────────────────────────────────────────
    public ?int $verId = null;

    public ?int $receberId = null;

    /** @var array<int, mixed> */
    public array $recebido = [];

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

    public function novaEncomenda(): void
    {
        $this->reset(['editandoId', 'supplierId', 'requisicaoId', 'entregaPrevista', 'notas', 'linhas', 'procuraArtigo']);
        $this->dataEncomenda = now()->format('Y-m-d');
        $this->warehouseId = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->value('id');
        $this->showForm = true;
    }

    public function editar(int $id): void
    {
        $enc = Encomenda::where('tenant_id', activeTenantId())->with('itens')->findOrFail($id);

        if (! $enc->podeEditar()) {
            $this->dispatch('notify', type: 'error', message: 'Só um rascunho se edita.');

            return;
        }

        $this->editandoId = $enc->id;
        $this->supplierId = $enc->supplier_id;
        $this->warehouseId = $enc->warehouse_id;
        $this->requisicaoId = $enc->requisicao_id;
        $this->dataEncomenda = $enc->data_encomenda?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->entregaPrevista = $enc->entrega_prevista?->format('Y-m-d') ?? '';
        $this->notas = (string) $enc->notas;
        $this->linhas = $enc->itens->map(fn ($i) => [
            'product_id' => $i->product_id,
            'descricao' => $i->descricao,
            'quantidade' => (float) $i->quantidade,
            'preco_unitario' => (float) $i->preco_unitario,
            'desconto_percent' => (float) $i->desconto_percent,
            'unidade' => $i->unidade,
        ])->all();
        $this->procuraArtigo = '';
        $this->showForm = true;
    }

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
            'preco_unitario' => (float) ($p->cost ?? 0),
            'desconto_percent' => 0,
            'unidade' => $p->unit,
        ];
        $this->procuraArtigo = '';
    }

    public function adicionarLinhaLivre(): void
    {
        $this->linhas[] = [
            'product_id' => null,
            'descricao' => '',
            'quantidade' => 1,
            'preco_unitario' => 0,
            'desconto_percent' => 0,
            'unidade' => null,
        ];
    }

    public function removerLinha(int $indice): void
    {
        unset($this->linhas[$indice]);
        $this->linhas = array_values($this->linhas);
    }

    public function guardar(FluxoDaEncomenda $fluxo): void
    {
        if (! $this->podeOu('compras.encomendas.manage')) {
            return;
        }

        $dados = [
            'supplier_id' => $this->supplierId ?: null,
            'warehouse_id' => $this->warehouseId ?: null,
            'requisicao_id' => $this->requisicaoId ?: null,
            'data_encomenda' => $this->dataEncomenda ?: now()->toDateString(),
            'entrega_prevista' => $this->entregaPrevista ?: null,
            'notas' => trim($this->notas) ?: null,
        ];

        try {
            if ($this->editandoId) {
                $enc = Encomenda::where('tenant_id', activeTenantId())->findOrFail($this->editandoId);
                $fluxo->actualizar($enc, activeTenantId(), $dados, $this->linhas);
                $mensagem = 'Encomenda actualizada.';
            } else {
                $fluxo->criar(activeTenantId(), auth()->id(), $dados, $this->linhas);
                $mensagem = 'Encomenda criada como rascunho.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->reset(['editandoId', 'linhas', 'notas', 'entregaPrevista', 'procuraArtigo']);
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    /** Puxar as linhas por encomendar de uma requisição aprovada. */
    public function daRequisicao(int $requisicaoId, FluxoDaEncomenda $fluxo): void
    {
        if (! $this->supplierId) {
            $this->dispatch('notify', type: 'error', message: 'Escolha primeiro o fornecedor.');

            return;
        }

        $req = Requisicao::where('tenant_id', activeTenantId())->with('itens')->find($requisicaoId);

        if (! $req) {
            return;
        }

        try {
            $enc = $fluxo->daRequisicao($req, activeTenantId(), auth()->id(), $this->supplierId, [
                'warehouse_id' => $this->warehouseId,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->reset(['linhas', 'requisicaoId']);
        $this->dispatch('notify', type: 'success', message: "Encomenda {$enc->numero} criada da requisição {$req->numero}.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Circuito
    // ─────────────────────────────────────────────────────────────────────

    public function enviar(int $id, FluxoDaEncomenda $fluxo): void
    {
        if (! $this->podeOu('compras.encomendas.manage')) {
            return;
        }

        $this->correr(fn () => $fluxo->enviar($this->minha($id), activeTenantId()), 'Encomenda enviada ao fornecedor.');
    }

    public function confirmar(int $id, FluxoDaEncomenda $fluxo): void
    {
        $this->correr(fn () => $fluxo->confirmar($this->minha($id), activeTenantId()), 'Encomenda confirmada pelo fornecedor.');
    }

    /** Abre o modal de recepção já com o que falta de cada linha sugerido. */
    public function abrirRecepcao(int $id): void
    {
        $enc = Encomenda::where('tenant_id', activeTenantId())->with('itens')->findOrFail($id);

        if (! $enc->podeReceber()) {
            $this->dispatch('notify', type: 'error', message: 'Esta encomenda não está à espera de mercadoria.');

            return;
        }

        $this->receberId = $enc->id;
        $this->recebido = $enc->itens->mapWithKeys(fn ($i) => [$i->id => $i->porReceber() ?: ''])->all();
    }

    public function receber(FluxoDaEncomenda $fluxo): void
    {
        if (! $this->receberId || ! $this->podeOu('compras.encomendas.receber')) {
            return;
        }

        $feito = $this->correr(
            fn () => $fluxo->receber($this->minha($this->receberId), activeTenantId(), auth()->id(), $this->recebido),
            'Mercadoria recebida — o stock já entrou.'
        );

        if ($feito) {
            $this->receberId = null;
            $this->recebido = [];
        }
    }

    public function facturar(int $id, FluxoDaEncomenda $fluxo): void
    {
        if (! $this->podeOu('compras.encomendas.manage')) {
            return;
        }

        try {
            $factura = $fluxo->facturar($this->minha($id), activeTenantId(), auth()->id());
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('notify', type: 'success', message: "Factura de compra {$factura->invoice_number} criada em rascunho.");
    }

    public function cancelar(FluxoDaEncomenda $fluxo): void
    {
        if (! $this->confirmarCancelarId) {
            return;
        }

        $this->correr(fn () => $fluxo->cancelar($this->minha($this->confirmarCancelarId), activeTenantId()), 'Encomenda cancelada.');
        $this->confirmarCancelarId = null;
    }

    /**
     * Receber mercadoria dá ENTRADA DE STOCK — é o acto de mais consequência
     * deste ecrã, e por isso tem permissão própria, como a contagem física.
     * Os botões escondem-se na vista; isto vale mesmo quando não se vê.
     */
    private function podeOu(string $permissao): bool
    {
        if (auth()->user()?->can($permissao)) {
            return true;
        }

        $this->dispatch('notify', type: 'error', message: 'Não tem permissão para isto.');

        return false;
    }

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

    private function minha(int $id): Encomenda
    {
        return Encomenda::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function getDetalheProperty(): ?Encomenda
    {
        if (! $this->verId) {
            return null;
        }

        return Encomenda::where('tenant_id', activeTenantId())
            ->with(['itens', 'fornecedor:id,name', 'warehouse:id,name', 'autor:id,name', 'requisicao:id,numero', 'factura:id,invoice_number,status'])
            ->find($this->verId);
    }

    public function getRecepcaoProperty(): ?Encomenda
    {
        if (! $this->receberId) {
            return null;
        }

        return Encomenda::where('tenant_id', activeTenantId())
            ->with(['itens', 'fornecedor:id,name', 'warehouse:id,name'])
            ->find($this->receberId);
    }

    /** Requisições aprovadas ainda com linhas por encomendar. */
    public function getRequisicoesAbertasProperty()
    {
        return Requisicao::where('tenant_id', activeTenantId())
            ->whereIn('estado', ['aprovada', 'encomendada'])
            ->with('itens')
            ->latest()
            ->get()
            ->filter(fn ($r) => $r->itens->contains(fn ($i) => $i->porEncomendar() > 0))
            ->take(10);
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $base = Encomenda::where('tenant_id', $tenantId)
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)
                    ->orWhereHas('fornecedor', fn ($f) => $f->where('name', 'like', $t))
                    ->orWhereHas('itens', fn ($i) => $i->where('descricao', 'like', $t)));
            });

        $abertas = Encomenda::where('tenant_id', $tenantId)->whereIn('estado', Encomenda::ABERTOS);

        $resumo = [
            'abertas' => (clone $abertas)->count(),
            'atrasadas' => (clone $abertas)->whereNotNull('entrega_prevista')
                ->whereDate('entrega_prevista', '<', now()->toDateString())->count(),
            'por_facturar' => Encomenda::where('tenant_id', $tenantId)
                ->whereIn('estado', ['parcial', 'recebida'])->whereNull('purchase_invoice_id')->count(),
            'valor_aberto' => (float) (clone $abertas)->sum('total'),
        ];

        return view('livewire.compras.encomendas', [
            'encomendas' => $base->with(['fornecedor:id,name', 'itens:id,encomenda_id,quantidade,quantidade_recebida', 'warehouse:id,name'])
                ->latest()->paginate(15),
            'resumo' => $resumo,
            'fornecedores' => Supplier::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
