<?php

namespace App\Livewire\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;
use App\Services\Invoicing\Propostas\EdicaoDeModelo;
use App\Services\Invoicing\Propostas\TiposDeBloco;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O editor visual de um modelo de proposta — o ecrã de sempre.
 *
 * Três colunas: à esquerda as peças e a ordem, ao centro a folha A4 como vai
 * sair, à direita as opções do bloco escolhido. As operações sobre os blocos,
 * a geometria e os estilos vivem na `EdicaoDeModelo`, partilhada com o ecrã
 * em React; aqui fica o estado do ecrã e o canvas.
 */
#[Layout('layouts.app')]
#[Title('Editor de Modelo de Proposta')]
class EditorDeModelo extends Component
{
    public ?int $modeloId = null;
    public string $nome = '';
    public string $descricao = '';
    public array $blocos = [];
    public array $estilos = [];
    public ?string $blocoSeleccionado = null;
    public bool $mostrarVariaveis = false;

    public function mount($id = null)
    {
        if (!auth()->user()->can('invoicing.sales.quotes.edit')) {
            abort(403);
        }

        $modelo = QuoteTemplate::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->modeloId = $modelo->id;
        $this->adoptar(new EdicaoDeModelo($modelo));
    }

    /** A edição com o estado que o ecrã tem neste momento. */
    private function edicao(): ?EdicaoDeModelo
    {
        $modelo = QuoteTemplate::where('tenant_id', activeTenantId())->find($this->modeloId);
        if (!$modelo) {
            $this->dispatch('error', message: __('Este modelo não pertence à empresa activa.'));

            return null;
        }

        return new EdicaoDeModelo($modelo, [
            'nome' => $this->nome, 'descricao' => $this->descricao, 'blocos' => $this->blocos, 'estilos' => $this->estilos,
        ], $this->blocoSeleccionado);
    }

    private function adoptar(EdicaoDeModelo $e): void
    {
        $this->nome = $e->nome;
        $this->descricao = $e->descricao;
        $this->blocos = $e->blocos;
        $this->estilos = $e->estilos;
        $this->blocoSeleccionado = $e->seleccionado;
    }

    /** Aplica uma operação, grava e devolve o estado ao ecrã. */
    private function aplicar(callable $operacao, bool $canvas = true, bool $avisar = false): void
    {
        $e = $this->edicao();
        if (!$e) {
            return;
        }

        $operacao($e);
        $e->gravar();
        $this->adoptar($e);

        if ($avisar) {
            $this->dispatch('success', message: __('Modelo guardado.'));
        }
        if ($canvas) {
            $this->sincronizarCanvas($e);
        }
    }

    // ── Blocos ───────────────────────────────────────────────────────────

    public function adicionarBloco(string $tipo): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->adicionar($tipo));
    }

    public function removerBloco(string $id): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->remover($id));
    }

    public function duplicarBloco(string $id): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->duplicar($id));
    }

    public function moverBloco(string $id, int $direccao): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->mover($id, $direccao), canvas: false);
    }

    /** Reordenar por arrastar: chega a lista de ids na ordem nova. */
    public function reordenar(array $ids): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->reordenar($ids), canvas: false);
    }

    public function seleccionar(string $id): void
    {
        $this->blocoSeleccionado = $id;
    }

    /** Editar uma opção do bloco escolhido. */
    public function actualizarCampo(string $campo, $valor): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->campo((string) $this->blocoSeleccionado, $campo, $valor));
    }

    /** Persiste uma alteração geométrica feita no canvas; os limites são do serviço. */
    public function actualizarLayout(string $id, array $layout): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->layout($id, $layout), canvas: false);
    }

    public function adicionarPagina(): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->adicionarPagina());
    }

    // ── Estilos ──────────────────────────────────────────────────────────

    public function actualizarEstilo(string $chave, $valor): void
    {
        $this->aplicar(fn (EdicaoDeModelo $e) => $e->estilo($chave, $valor), canvas: false);
    }

    // ── Gravar ───────────────────────────────────────────────────────────

    public function guardar(bool $avisar = true): void
    {
        $this->aplicar(fn () => null, canvas: false, avisar: $avisar);
    }

    // ── Auxiliares ───────────────────────────────────────────────────────

    private function sincronizarCanvas(EdicaoDeModelo $e): void
    {
        $this->dispatch('canvas-atualizado', blocos: $e->blocos, paginas: $e->numeroPaginas(), seleccionado: $e->seleccionado);
    }

    public function render()
    {
        $modelo = new QuoteTemplate([
            'tenant_id' => activeTenantId(),
            'nome' => $this->nome,
            'blocos' => $this->blocos,
            'estilos' => $this->estilos,
        ]);
        $e = new EdicaoDeModelo($modelo, null, $this->blocoSeleccionado);

        return view('livewire.invoicing.propostas.editor-de-modelo', [
            'catalogo' => TiposDeBloco::catalogo(),
            'variaveis' => TiposDeBloco::variaveis(),
            'bloco' => $e->blocoSeleccionado(),
            'previa' => $e->previa(activeTenant()),
        ]);
    }
}
