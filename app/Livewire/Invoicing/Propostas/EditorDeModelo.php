<?php

namespace App\Livewire\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;
use App\Services\Invoicing\Propostas\RenderizadorDeProposta;
use App\Services\Invoicing\Propostas\TiposDeBloco;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O editor visual de um modelo de proposta.
 *
 * Três colunas: à esquerda as peças e a ordem, ao centro a folha A4 como vai
 * sair, à direita as opções do bloco escolhido.
 *
 * A pré-visualização é o MESMO renderizador que faz o PDF, e não uma imitação
 * em Tailwind. Um editor que mostra uma coisa e imprime outra é pior do que
 * não ter editor nenhum: só se descobre a diferença depois de a proposta já
 * ter seguido para o cliente.
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

        $this->modeloId  = $modelo->id;
        $this->nome      = $modelo->nome;
        $this->descricao = (string) $modelo->descricao;
        $this->blocos    = (array) $modelo->blocos;
        $this->estilos   = array_merge(QuoteTemplate::ESTILOS_PADRAO, (array) $modelo->estilos);

        $this->blocoSeleccionado = $this->blocos[0]['id'] ?? null;
    }

    // ── Blocos ───────────────────────────────────────────────────────────

    public function adicionarBloco(string $tipo): void
    {
        if (!TiposDeBloco::existe($tipo)) {
            return;
        }

        $novo = array_merge(['tipo' => $tipo, 'id' => $this->novoId($tipo)], TiposDeBloco::padroesDe($tipo));

        // Entra a seguir ao bloco em que se está a trabalhar, não no fim: quem
        // está a meio do documento quer acrescentar ali, e arrastar da última
        // posição até ao meio é trabalho que não devia existir.
        $pos = $this->indiceDe($this->blocoSeleccionado);
        if ($pos === null) {
            $this->blocos[] = $novo;
        } else {
            array_splice($this->blocos, $pos + 1, 0, [$novo]);
        }

        $this->blocoSeleccionado = $novo['id'];
        $this->guardar(false);
    }

    public function removerBloco(string $id): void
    {
        $pos = $this->indiceDe($id);
        if ($pos === null) {
            return;
        }

        array_splice($this->blocos, $pos, 1);

        if ($this->blocoSeleccionado === $id) {
            $this->blocoSeleccionado = $this->blocos[max(0, $pos - 1)]['id'] ?? null;
        }

        $this->guardar(false);
    }

    public function duplicarBloco(string $id): void
    {
        $pos = $this->indiceDe($id);
        if ($pos === null) {
            return;
        }

        $copia = $this->blocos[$pos];
        $copia['id'] = $this->novoId($copia['tipo'] ?? 'x');

        // Duas secções a pedir a mesma chave escreviam o mesmo texto duas
        // vezes — e mudar uma mudava a outra. A cópia leva chave própria.
        if (($copia['tipo'] ?? '') === 'campo_livre') {
            $copia['chave'] = $this->chaveLivre((string) ($copia['chave'] ?? 'campo'));
        }

        array_splice($this->blocos, $pos + 1, 0, [$copia]);
        $this->blocoSeleccionado = $copia['id'];
        $this->guardar(false);
    }

    public function moverBloco(string $id, int $direccao): void
    {
        $pos = $this->indiceDe($id);
        $destino = $pos === null ? null : $pos + $direccao;

        if ($pos === null || $destino < 0 || $destino >= count($this->blocos)) {
            return;
        }

        [$this->blocos[$pos], $this->blocos[$destino]] = [$this->blocos[$destino], $this->blocos[$pos]];
        $this->guardar(false);
    }

    /** Reordenar por arrastar: chega a lista de ids na ordem nova. */
    public function reordenar(array $ids): void
    {
        $porId = collect($this->blocos)->keyBy('id');
        $nova = [];

        foreach ($ids as $id) {
            if ($porId->has($id)) {
                $nova[] = $porId->get($id);
                $porId->forget($id);
            }
        }

        // O que o browser não mandou (bloco novo, corrida entre pedidos) vai
        // para o fim em vez de desaparecer: perder uma secção porque o
        // arrastar falhou seria imperdoável.
        foreach ($porId as $resto) {
            $nova[] = $resto;
        }

        $this->blocos = $nova;
        $this->guardar(false);
    }

    public function seleccionar(string $id): void
    {
        $this->blocoSeleccionado = $id;
    }

    /** Editar uma opção do bloco escolhido. */
    public function actualizarCampo(string $campo, $valor): void
    {
        $pos = $this->indiceDe($this->blocoSeleccionado);
        if ($pos === null) {
            return;
        }

        // A chave de um campo livre é o nome por onde o orçamento o guarda:
        // só letras, números e underscore, senão parte ao ler de volta.
        if ($campo === 'chave') {
            $valor = preg_replace('/[^a-z0-9_]/', '', \Str::snake(\Str::ascii((string) $valor))) ?: 'campo';
        }

        $this->blocos[$pos][$campo] = $valor;
        $this->guardar(false);
    }

    // ── Estilos ──────────────────────────────────────────────────────────

    public function actualizarEstilo(string $chave, $valor): void
    {
        $this->estilos[$chave] = $valor;
        $this->guardar(false);
    }

    // ── Gravar ───────────────────────────────────────────────────────────

    public function guardar(bool $avisar = true): void
    {
        $modelo = QuoteTemplate::where('tenant_id', activeTenantId())->find($this->modeloId);
        if (!$modelo) {
            $this->dispatch('error', message: __('Este modelo não pertence à empresa activa.'));

            return;
        }

        $modelo->update([
            'nome'      => trim($this->nome) ?: 'Modelo sem nome',
            'descricao' => trim($this->descricao) ?: null,
            'blocos'    => array_values($this->blocos),
            'estilos'   => $this->estilos,
        ]);

        if ($avisar) {
            $this->dispatch('success', message: __('Modelo guardado.'));
        }
    }

    // ── Auxiliares ───────────────────────────────────────────────────────

    private function indiceDe(?string $id): ?int
    {
        if ($id === null) {
            return null;
        }

        foreach ($this->blocos as $i => $b) {
            if (($b['id'] ?? null) === $id) {
                return $i;
            }
        }

        return null;
    }

    private function novoId(string $tipo): string
    {
        return substr($tipo, 0, 3) . '_' . substr(md5(uniqid('', true)), 0, 8);
    }

    private function chaveLivre(string $base): string
    {
        $usadas = collect($this->blocos)->pluck('chave')->filter()->all();
        $chave = $base;
        $n = 2;

        while (in_array($chave, $usadas, true)) {
            $chave = $base . '_' . $n++;
        }

        return $chave;
    }

    public function render()
    {
        $modelo = new QuoteTemplate([
            'tenant_id' => activeTenantId(),
            'nome'      => $this->nome,
            'blocos'    => $this->blocos,
            'estilos'   => $this->estilos,
        ]);

        return view('livewire.invoicing.propostas.editor-de-modelo', [
            'catalogo'  => TiposDeBloco::catalogo(),
            'variaveis' => TiposDeBloco::variaveis(),
            'bloco'     => $this->indiceDe($this->blocoSeleccionado) !== null
                ? $this->blocos[$this->indiceDe($this->blocoSeleccionado)]
                : null,
            'previa'    => app(RenderizadorDeProposta::class)->renderExemplo($modelo, activeTenant()),
        ]);
    }
}
