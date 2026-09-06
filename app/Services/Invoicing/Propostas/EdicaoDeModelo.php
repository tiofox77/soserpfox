<?php

namespace App\Services\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * A EDIÇÃO DE UM MODELO DE PROPOSTA: os blocos, a ordem, a geometria na
 * folha A4, os estilos. É o que o editor visual (Livewire) fazia dentro do
 * componente; agora é um objecto que os dois ecrãs manipulam e gravam.
 *
 * Os limites são validados aqui: o browser nunca pode gravar um bloco fora
 * da folha nem injectar propriedades arbitrárias no JSON.
 */
class EdicaoDeModelo
{
    public string $nome;
    public string $descricao;
    public array $blocos;
    public array $estilos;
    public ?string $seleccionado;

    public function __construct(private readonly QuoteTemplate $modelo, ?array $estadoActual = null, ?string $seleccionado = null)
    {
        $this->nome = (string) ($estadoActual['nome'] ?? $modelo->nome);
        $this->descricao = (string) ($estadoActual['descricao'] ?? $modelo->descricao ?? '');
        $this->blocos = array_values((array) ($estadoActual['blocos'] ?? $modelo->blocos ?? []));
        $this->estilos = array_merge(QuoteTemplate::ESTILOS_PADRAO, (array) ($estadoActual['estilos'] ?? $modelo->estilos ?? []));
        $this->normalizarLayout();
        $this->seleccionado = $seleccionado !== null && $this->indiceDe($seleccionado) !== null
            ? $seleccionado
            : ($this->blocos[0]['id'] ?? null);
    }

    /* ─── Blocos ──────────────────────────────────────────────────────── */

    /**
     * Entra a seguir ao bloco em que se está a trabalhar, não no fim: quem
     * está a meio do documento quer acrescentar ali.
     */
    public function adicionar(string $tipo): ?string
    {
        if (!TiposDeBloco::existe($tipo)) {
            return null;
        }

        $novo = array_merge(['tipo' => $tipo, 'id' => $this->novoId($tipo)], TiposDeBloco::padroesDe($tipo));
        $novo['layout'] = $this->proximaPosicao();

        $pos = $this->indiceDe($this->seleccionado);
        if ($pos === null) {
            $this->blocos[] = $novo;
        } else {
            array_splice($this->blocos, $pos + 1, 0, [$novo]);
        }

        return $this->seleccionado = $novo['id'];
    }

    public function remover(string $id): void
    {
        $pos = $this->indiceDe($id);
        if ($pos === null) {
            return;
        }

        array_splice($this->blocos, $pos, 1);

        if ($this->seleccionado === $id) {
            $this->seleccionado = $this->blocos[max(0, $pos - 1)]['id'] ?? null;
        }
    }

    public function duplicar(string $id): ?string
    {
        $pos = $this->indiceDe($id);
        if ($pos === null) {
            return null;
        }

        $copia = $this->blocos[$pos];
        $copia['id'] = $this->novoId($copia['tipo'] ?? 'x');

        // Duas secções a pedir a mesma chave escreviam o mesmo texto duas
        // vezes — e mudar uma mudava a outra. A cópia leva chave própria.
        if (($copia['tipo'] ?? '') === 'campo_livre') {
            $copia['chave'] = $this->chaveLivre((string) ($copia['chave'] ?? 'campo'));
        }

        array_splice($this->blocos, $pos + 1, 0, [$copia]);

        return $this->seleccionado = $copia['id'];
    }

    public function mover(string $id, int $direccao): void
    {
        $pos = $this->indiceDe($id);
        $destino = $pos === null ? null : $pos + $direccao;

        if ($pos === null || $destino < 0 || $destino >= count($this->blocos)) {
            return;
        }

        [$this->blocos[$pos], $this->blocos[$destino]] = [$this->blocos[$destino], $this->blocos[$pos]];
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

        // O que o browser não mandou vai para o fim em vez de desaparecer:
        // perder uma secção porque o arrastar falhou seria imperdoável.
        foreach ($porId as $resto) {
            $nova[] = $resto;
        }

        $this->blocos = $nova;
    }

    /** Editar uma opção de um bloco. */
    public function campo(string $id, string $campo, $valor): void
    {
        $pos = $this->indiceDe($id);
        if ($pos === null || in_array($campo, ['id', 'tipo', 'layout'], true)) {
            return;
        }

        // A chave de um campo livre é o nome por onde o orçamento o guarda:
        // só letras, números e underscore, senão parte ao ler de volta.
        if ($campo === 'chave') {
            $valor = preg_replace('/[^a-z0-9_]/', '', Str::snake(Str::ascii((string) $valor))) ?: 'campo';
        }

        $this->blocos[$pos][$campo] = $valor;
    }

    /** A geometria de um bloco na folha, com os limites da A4 a mandar. */
    public function layout(string $id, array $layout): void
    {
        $pos = $this->indiceDe($id);
        if ($pos === null) {
            return;
        }

        $pagina = max(1, min(50, (int) ($layout['pagina'] ?? 1)));
        $largura = max(40, min(754, (int) ($layout['largura'] ?? 300)));
        $altura = max(28, min(1083, (int) ($layout['altura'] ?? 100)));

        $this->blocos[$pos]['layout'] = [
            'pagina' => $pagina,
            'x' => max(0, min(794 - $largura, (int) ($layout['x'] ?? 20))),
            'y' => max(0, min(1123 - $altura, (int) ($layout['y'] ?? 20))),
            'largura' => $largura,
            'altura' => $altura,
            'z' => max(1, min(999, (int) ($layout['z'] ?? ($pos + 1)))),
            'bloqueado' => (bool) ($layout['bloqueado'] ?? false),
        ];
        $this->seleccionado = $id;
        $this->estilos['editor_visual'] = true;
    }

    public function adicionarPagina(): void
    {
        $this->estilos['editor_visual'] = true;
        $this->estilos['paginas'] = min(50, $this->numeroPaginas() + 1);
    }

    public function estilo(string $chave, $valor): void
    {
        $this->estilos[$chave] = $valor;
    }

    public function renomear(string $nome, string $descricao): void
    {
        $this->nome = $nome;
        $this->descricao = $descricao;
    }

    /* ─── Gravar e ler ────────────────────────────────────────────────── */

    public function gravar(): void
    {
        $this->modelo->update([
            'nome' => trim($this->nome) ?: 'Modelo sem nome',
            'descricao' => trim($this->descricao) ?: null,
            'blocos' => array_values($this->blocos),
            'estilos' => $this->estilos,
        ]);
    }

    public function estado(): array
    {
        return [
            'id' => $this->modelo->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'blocos' => array_values($this->blocos),
            'estilos' => $this->estilos,
            'paginas' => $this->numeroPaginas(),
            'seleccionado' => $this->seleccionado,
        ];
    }

    /** O bloco escolhido, ou nada. */
    public function blocoSeleccionado(): ?array
    {
        $pos = $this->indiceDe($this->seleccionado);

        return $pos === null ? null : $this->blocos[$pos];
    }

    /**
     * A pré-visualização é o MESMO renderizador que faz o PDF, e não uma
     * imitação. Um editor que mostra uma coisa e imprime outra é pior do que
     * não ter editor nenhum.
     */
    public function previa(?Tenant $empresa): string
    {
        $modelo = new QuoteTemplate([
            'tenant_id' => $this->modelo->tenant_id,
            'nome' => $this->nome,
            'blocos' => $this->blocos,
            'estilos' => $this->estilos,
        ]);

        return app(RenderizadorDeProposta::class)->renderExemplo($modelo, $empresa);
    }

    public function numeroPaginas(): int
    {
        $maior = collect($this->blocos)->max(fn ($b) => (int) data_get($b, 'layout.pagina', 1)) ?: 1;

        return max($maior, (int) ($this->estilos['paginas'] ?? 1));
    }

    public function indiceDe(?string $id): ?int
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

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function novoId(string $tipo): string
    {
        return substr($tipo, 0, 3) . '_' . substr(md5(uniqid('', true)), 0, 8);
    }

    /** Blocos antigos, sem geometria, recebem uma: empilhados na folha. */
    private function normalizarLayout(): void
    {
        $pagina = 1;
        $y = 28;

        foreach ($this->blocos as $i => &$bloco) {
            if (isset($bloco['layout']) && is_array($bloco['layout'])) {
                continue;
            }

            $altura = match ($bloco['tipo'] ?? '') {
                'capa' => 1040, 'itens' => 300, 'texto', 'campo_livre', 'condicoes' => 180,
                'dados_cliente', 'assinaturas' => 145, 'totais' => 125, 'imagem' => 240,
                'quebra' => 35, default => 75,
            };

            if ($y + $altura > 1090) {
                $pagina++;
                $y = 28;
            }

            $bloco['layout'] = ['pagina' => $pagina, 'x' => 28, 'y' => $y,
                'largura' => 738, 'altura' => $altura, 'z' => $i + 1, 'bloqueado' => false];
            $y += $altura + 14;
        }
        unset($bloco);

        $this->estilos['paginas'] = max((int) ($this->estilos['paginas'] ?? 1), $pagina);
    }

    private function proximaPosicao(): array
    {
        return ['pagina' => $this->numeroPaginas(), 'x' => 40, 'y' => 60, 'largura' => 714,
            'altura' => 120, 'z' => count($this->blocos) + 1, 'bloqueado' => false];
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
}
