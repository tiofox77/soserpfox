<?php

namespace App\Services\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;
use App\Models\Invoicing\SalesQuote;
use App\Models\Tenant;

/**
 * Transforma um modelo + um orçamento no HTML da proposta.
 *
 * ESCREVE PARA O DOMPDF, e isso manda no HTML que sai daqui: nada de flexbox
 * nem de grid — o dompdf ignora-os e a página desaba. Tudo o que precisa de
 * ficar lado a lado sai em `<table>`, e o espaçamento em padding. É feio de
 * ler e é a única coisa que imprime igual.
 *
 * O mesmo HTML serve a pré-visualização no ecrã: assim o que se vê a desenhar
 * o modelo é o que sai no PDF, e não uma aproximação que engana.
 */
class RenderizadorDeProposta
{
    private array $variaveis = [];
    private int $numeroDeSeccao = 0;

    public function render(QuoteTemplate $modelo, ?SalesQuote $orcamento, ?Tenant $empresa = null): string
    {
        $empresa = $empresa ?: ($orcamento?->tenant ?: Tenant::find($modelo->tenant_id));
        $this->variaveis = $this->montarVariaveis($orcamento, $empresa);
        $this->numeroDeSeccao = 0;

        $corpo = $modelo->estilo('editor_visual')
            ? $this->renderVisual($modelo, $orcamento, $empresa)
            : collect((array) $modelo->blocos)
                ->map(fn ($bloco) => $this->renderBloco($modelo, (array) $bloco, $orcamento, $empresa))
                ->implode('');

        return $this->envolver($modelo, $corpo, $empresa);
    }

    /**
     * Pré-visualização sem orçamento nenhum: o editor tem de mostrar alguma
     * coisa antes de existir um negócio. Enche as variáveis com um exemplo em
     * vez de as deixar cruas no ecrã — {{cliente.nome}} a meio de uma frase
     * não deixa ninguém julgar se o desenho está bom.
     */
    public function renderExemplo(QuoteTemplate $modelo, ?Tenant $empresa = null): string
    {
        return $this->render($modelo, null, $empresa);
    }

    // ── Blocos ───────────────────────────────────────────────────────────

    private function renderBloco(QuoteTemplate $modelo, array $b, ?SalesQuote $q, ?Tenant $t): string
    {
        $cor = $modelo->estilo('cor_principal');

        return match ($b['tipo'] ?? '') {
            'capa'          => $this->capa($b, $cor, $t),
            'titulo'        => $this->titulo($b, $cor),
            'texto'         => $this->texto($b),
            'campo_livre'   => $this->campoLivre($b, $q, $cor),
            'dados_cliente' => $this->dadosCliente($b, $q, $cor),
            'itens'         => $this->itens($b, $q, $cor),
            'totais'        => $this->totais($b, $q, $cor),
            'condicoes'     => $this->condicoes($b, $q, $cor),
            'assinaturas'   => $this->assinaturas($b),
            'imagem'        => $this->imagem($b),
            'quebra'        => '<div style="page-break-after:always"></div>',
            default         => '',
        };
    }

    /** Desenha páginas A4 com a geometria exacta guardada pelo editor. */
    private function renderVisual(QuoteTemplate $modelo, ?SalesQuote $q, ?Tenant $t): string
    {
        $blocos = collect((array) $modelo->blocos)->groupBy(
            fn ($b) => max(1, (int) data_get($b, 'layout.pagina', 1))
        );
        $paginas = max((int) ($modelo->estilo('paginas') ?: 1), (int) ($blocos->keys()->max() ?: 1));
        $html = '';

        for ($pagina = 1; $pagina <= $paginas; $pagina++) {
            $html .= '<section class="proposal-page">';
            foreach ($blocos->get($pagina, collect())->sortBy(fn ($b) => (int) data_get($b, 'layout.z', 1)) as $b) {
                $l = (array) ($b['layout'] ?? []);
                $x = max(0, min(794, (int) ($l['x'] ?? 28)));
                $y = max(0, min(1123, (int) ($l['y'] ?? 28)));
                $w = max(40, min(794 - $x, (int) ($l['largura'] ?? 738)));
                $h = max(28, min(1123 - $y, (int) ($l['altura'] ?? 120)));
                $z = max(1, min(999, (int) ($l['z'] ?? 1)));
                $conteudo = $this->renderBloco($modelo, (array) $b, $q, $t);
                // A paginação é controlada pelo canvas; uma quebra interna
                // herdada do editor antigo não pode criar uma folha fantasma.
                $conteudo = str_replace('page-break-after:always', 'page-break-after:auto', $conteudo);
                $html .= '<div class="proposal-element" style="left:' . $x . 'px;top:' . $y
                    . 'px;width:' . $w . 'px;height:' . $h . 'px;z-index:' . $z . '">' . $conteudo . '</div>';
            }
            $html .= '</section>';
        }

        return $html;
    }

    private function capa(array $b, string $cor, ?Tenant $t): string
    {
        $fundo = $b['cor_fundo'] ?? '';
        $claro = $fundo === '' || $this->corEscura($fundo) === false;
        $corTexto = $fundo !== '' && !$claro ? '#ffffff' : $cor;

        $logo = '';
        if (($b['mostrar_logo'] ?? true) && $t) {
            $ficheiro = $this->logoDaEmpresa($t);
            if ($ficheiro) {
                $logo = '<img src="' . $ficheiro . '" style="max-height:90px;max-width:260px;margin-bottom:26px">';
            }
        }

        $dados = '';
        if ($b['mostrar_dados'] ?? true) {
            $dados = '<table style="width:100%;margin-top:44px;font-size:11px;color:' . ($corTexto === '#ffffff' ? '#e5e7eb' : '#6b7280') . '">'
                . '<tr>'
                . '<td style="width:50%">' . $this->v('{{orcamento.numero}}') . '</td>'
                . '<td style="width:50%;text-align:right">' . $this->v('{{orcamento.data}}') . '</td>'
                . '</tr></table>';
        }

        $estiloFundo = $fundo !== '' ? 'background:' . e($fundo) . ';padding:60px 46px;' : 'padding:70px 0 40px;';

        return '<div style="' . $estiloFundo . 'text-align:center">'
            . $logo
            . '<div style="font-size:30px;font-weight:bold;color:' . e($corTexto) . ';line-height:1.25">' . $this->v($b['titulo'] ?? '') . '</div>'
            . '<div style="font-size:16px;color:' . ($corTexto === '#ffffff' ? '#e5e7eb' : '#4b5563') . ';margin-top:12px">' . $this->v($b['subtitulo'] ?? '') . '</div>'
            . $dados
            . '</div>'
            . (($b['quebrar_depois'] ?? true) ? '<div style="page-break-after:always"></div>' : '');
    }

    private function titulo(array $b, string $cor): string
    {
        $prefixo = '';
        if ($b['numerar'] ?? true) {
            $prefixo = (++$this->numeroDeSeccao) . '. ';
        }

        return '<h2 style="font-size:16px;font-weight:bold;color:' . e($cor) . ';margin:26px 0 10px;'
            . 'border-bottom:2px solid ' . e($cor) . ';padding-bottom:6px">'
            . e($prefixo) . $this->v($b['texto'] ?? '') . '</h2>';
    }

    private function texto(array $b): string
    {
        return '<div style="margin:0 0 14px;line-height:1.65">' . $this->v($b['html'] ?? '', false) . '</div>';
    }

    private function campoLivre(array $b, ?SalesQuote $q, string $cor): string
    {
        $chave = (string) ($b['chave'] ?? '');
        $valor = trim((string) (($q->campos_proposta ?? [])[$chave] ?? ''));

        $titulo = '';
        if (!empty($b['titulo'])) {
            $titulo = '<h2 style="font-size:16px;font-weight:bold;color:' . e($cor) . ';margin:26px 0 10px;'
                . 'border-bottom:2px solid ' . e($cor) . ';padding-bottom:6px">'
                . (++$this->numeroDeSeccao) . '. ' . $this->v($b['titulo']) . '</h2>';
        }

        if ($valor === '') {
            // Por preencher: no ecrã tem de se ver que falta ali qualquer
            // coisa. Um espaço em branco levava esta secção para o PDF sem
            // ninguém dar por ela.
            $corpo = '<div style="border:1px dashed #cbd5e1;background:#f8fafc;color:#94a3b8;'
                . 'padding:14px;font-style:italic;font-size:11px">'
                . e($b['rotulo'] ?? $chave) . ' — por preencher neste orçamento</div>';
        } else {
            $corpo = '<div style="line-height:1.65">' . nl2br(e($valor)) . '</div>';
        }

        return $titulo . '<div style="margin:0 0 14px">' . $corpo . '</div>';
    }

    private function dadosCliente(array $b, ?SalesQuote $q, string $cor): string
    {
        $linhas = [
            ['Cliente', $this->v('{{cliente.nome}}')],
        ];

        if ($b['mostrar_nif'] ?? true) {
            $linhas[] = ['NIF', $this->v('{{cliente.nif}}')];
        }

        $linhas[] = ['Morada', $this->v('{{cliente.morada}}')];
        $linhas[] = ['Proposta', $this->v('{{orcamento.numero}}')];
        $linhas[] = ['Data', $this->v('{{orcamento.data}}')];

        if ($b['mostrar_validade'] ?? true) {
            $linhas[] = ['Válida até', $this->v('{{orcamento.validade}}')];
        }

        $tr = '';
        foreach ($linhas as [$rotulo, $valor]) {
            $tr .= '<tr>'
                . '<td style="padding:5px 10px;color:#6b7280;font-size:11px;width:110px">' . e($rotulo) . '</td>'
                . '<td style="padding:5px 10px;font-weight:bold">' . $valor . '</td>'
                . '</tr>';
        }

        return '<table style="width:100%;border:1px solid #e5e7eb;border-left:3px solid ' . e($cor) . ';'
            . 'background:#f9fafb;margin:0 0 18px;border-collapse:collapse">' . $tr . '</table>';
    }

    private function itens(array $b, ?SalesQuote $q, string $cor): string
    {
        $comDesc = $b['mostrar_desconto'] ?? true;
        $comImp  = $b['mostrar_imposto'] ?? true;
        $comDescricao = $b['mostrar_descricao'] ?? true;

        $titulo = '';
        if (!empty($b['titulo'])) {
            $titulo = '<h2 style="font-size:16px;font-weight:bold;color:' . e($cor) . ';margin:26px 0 10px;'
                . 'border-bottom:2px solid ' . e($cor) . ';padding-bottom:6px">'
                . (++$this->numeroDeSeccao) . '. ' . $this->v($b['titulo']) . '</h2>';
        }

        $cab = '<tr style="background:' . e($cor) . ';color:#fff">'
            . '<th style="padding:8px;text-align:left;font-size:11px">Descrição</th>'
            . '<th style="padding:8px;text-align:center;font-size:11px;width:58px">Qtd</th>'
            . '<th style="padding:8px;text-align:right;font-size:11px;width:92px">Preço</th>'
            . ($comDesc ? '<th style="padding:8px;text-align:right;font-size:11px;width:62px">Desc.</th>' : '')
            . ($comImp ? '<th style="padding:8px;text-align:right;font-size:11px;width:62px">Imp.</th>' : '')
            . '<th style="padding:8px;text-align:right;font-size:11px;width:100px">Total</th>'
            . '</tr>';

        $linhas = $q ? $q->items : collect($this->itensDeExemplo());
        $tr = '';

        foreach ($linhas as $i => $item) {
            $fundo = $i % 2 ? '#f9fafb' : '#ffffff';
            $desc = '';
            if ($comDescricao && !empty($item->description)) {
                $desc = '<div style="font-size:10px;color:#6b7280;margin-top:3px">' . nl2br(e($item->description)) . '</div>';
            }

            $tr .= '<tr style="background:' . $fundo . '">'
                . '<td style="padding:8px;border-bottom:1px solid #e5e7eb">'
                    . '<strong>' . e($item->product_name ?? '—') . '</strong>' . $desc . '</td>'
                . '<td style="padding:8px;text-align:center;border-bottom:1px solid #e5e7eb">'
                    . rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') . '</td>'
                . '<td style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb">'
                    . $this->dinheiro($item->unit_price) . '</td>'
                . ($comDesc ? '<td style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb">'
                    . number_format((float) ($item->discount_percent ?? 0), 0) . '%</td>' : '')
                . ($comImp ? '<td style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb">'
                    . number_format((float) ($item->tax_rate ?? 0), 0) . '%</td>' : '')
                . '<td style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb;font-weight:bold">'
                    . $this->dinheiro($item->total) . '</td>'
                . '</tr>';
        }

        return $titulo . '<table style="width:100%;border-collapse:collapse;margin:0 0 14px;font-size:11px">'
            . $cab . $tr . '</table>';
    }

    private function totais(array $b, ?SalesQuote $q, string $cor): string
    {
        $sub  = $q ? $q->subtotal : 250000;
        $desc = $q ? ((float) $q->discount_amount + (float) $q->discount_commercial) : 0;
        $imp  = $q ? $q->tax_amount : 35000;
        $tot  = $q ? $q->total : 285000;

        $linha = function (string $r, $v, bool $forte = false) {
            return '<tr>'
                . '<td style="padding:5px 10px;text-align:right;color:#6b7280">' . e($r) . '</td>'
                . '<td style="padding:5px 10px;text-align:right;width:120px'
                    . ($forte ? ';font-weight:bold;font-size:15px' : '') . '">' . $this->dinheiro($v) . '</td>'
                . '</tr>';
        };

        $corpo = $linha('Subtotal', $sub)
            . ($desc > 0 ? $linha('Descontos', -$desc) : '')
            . $linha('Imposto', $imp);

        return '<table style="width:100%;margin:0 0 16px"><tr><td style="width:55%"></td><td>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb">'
            . $corpo
            . '<tr style="background:' . e($cor) . ';color:#fff">'
                . '<td style="padding:8px 10px;text-align:right;font-weight:bold">TOTAL</td>'
                . '<td style="padding:8px 10px;text-align:right;font-weight:bold;font-size:15px">' . $this->dinheiro($tot) . '</td>'
            . '</tr></table></td></tr></table>';
    }

    private function condicoes(array $b, ?SalesQuote $q, string $cor): string
    {
        // O que ficou escrito NO orçamento manda sobre o texto do modelo: se
        // alguém negociou condições especiais para este cliente, são essas que
        // têm de sair no papel.
        $html = trim((string) ($q->terms ?? '')) !== ''
            ? nl2br(e($q->terms))
            : $this->v($b['html'] ?? '', false);

        $titulo = '';
        if (!empty($b['titulo'])) {
            $titulo = '<h2 style="font-size:16px;font-weight:bold;color:' . e($cor) . ';margin:26px 0 10px;'
                . 'border-bottom:2px solid ' . e($cor) . ';padding-bottom:6px">'
                . (++$this->numeroDeSeccao) . '. ' . $this->v($b['titulo']) . '</h2>';
        }

        return $titulo . '<div style="font-size:11px;line-height:1.65;color:#374151;margin:0 0 14px">' . $html . '</div>';
    }

    private function assinaturas(array $b): string
    {
        $celula = function (string $texto) {
            return '<td style="width:45%;padding-top:52px;text-align:center;font-size:11px">'
                . '<div style="border-top:1px solid #9ca3af;padding-top:7px">' . $this->v($texto) . '</div></td>';
        };

        $linha = $celula($b['esquerda'] ?? '');
        if ($b['duas'] ?? true) {
            $linha .= '<td style="width:10%"></td>' . $celula($b['direita'] ?? '');
        }

        return '<table style="width:100%;margin-top:26px"><tr>' . $linha . '</tr></table>';
    }

    private function imagem(array $b): string
    {
        $url = trim((string) ($b['url'] ?? ''));
        if ($url === '') {
            return '<div style="border:1px dashed #cbd5e1;background:#f8fafc;color:#94a3b8;padding:26px;'
                . 'text-align:center;font-size:11px;margin:0 0 14px">Imagem por escolher</div>';
        }

        $largura = max(10, min(100, (int) ($b['largura'] ?? 100)));
        $legenda = trim((string) ($b['legenda'] ?? ''));

        return '<div style="text-align:center;margin:0 0 14px">'
            . '<img src="' . e($url) . '" style="width:' . $largura . '%">'
            . ($legenda !== '' ? '<div style="font-size:10px;color:#6b7280;margin-top:5px">' . e($legenda) . '</div>' : '')
            . '</div>';
    }

    // ── Moldura ──────────────────────────────────────────────────────────

    private function envolver(QuoteTemplate $modelo, string $corpo, ?Tenant $t): string
    {
        $fonte = $modelo->estilo('fonte') ?: 'Arial';
        $tam   = (int) ($modelo->estilo('tamanho_base') ?: 12);
        $corTx = $modelo->estilo('cor_texto') ?: '#111827';

        $rodape = '';
        if ($modelo->estilo('mostrar_rodape')) {
            $rodape = '<div style="position:fixed;bottom:0;left:0;right:0;text-align:center;'
                . 'font-size:9px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:5px">'
                . $this->v((string) $modelo->estilo('texto_rodape')) . '</div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<style>'
            . ($modelo->estilo('editor_visual') ? '@page{size:A4;margin:0}' : '@page{margin:26mm 16mm 22mm 16mm}')
            . 'body{font-family:' . e($fonte) . ',sans-serif;font-size:' . $tam . 'px;color:' . e($corTx) . ';margin:0}'
            . 'p{margin:0 0 9px}ul,ol{margin:0 0 9px;padding-left:20px}li{margin-bottom:4px}'
            . 'table{border-collapse:collapse}'
            . '.proposal-page{position:relative;width:794px;height:1123px;overflow:hidden;page-break-after:always;background:#fff}'
            . '.proposal-page:last-child{page-break-after:auto}.proposal-element{position:absolute;overflow:hidden;box-sizing:border-box}'
            . '</style></head><body>'
            . $rodape
            . $corpo
            . '</body></html>';
    }

    // ── Variáveis ────────────────────────────────────────────────────────

    /** Substitui as {{variaveis}} de um texto. */
    private function v(string $texto, bool $escapar = true): string
    {
        if ($texto === '') {
            return '';
        }

        $saida = strtr($texto, $this->variaveis);

        // Uma variável escrita à mão que não existe fica visível em vez de ir
        // parar ao PDF do cliente: melhor um risco no ecrã do que "{{cliete.nome}}"
        // impresso numa proposta.
        $saida = preg_replace('/\{\{\s*[\w.]+\s*\}\}/', '—', $saida);

        return $escapar ? e($saida) : $saida;
    }

    private function montarVariaveis(?SalesQuote $q, ?Tenant $t): array
    {
        $cliente = $q?->client;

        return [
            '{{empresa.nome}}'     => $t->name ?? '',
            '{{empresa.nif}}'      => $t->nif ?? '',
            '{{empresa.morada}}'   => $t->address ?? '',
            '{{empresa.telefone}}' => $t->phone ?? '',
            '{{empresa.email}}'    => $t->email ?? '',
            // A coluna `website` não existe em `tenants`: fica vazia em vez de
            // rebentar com "Undefined property" a meio de gerar um PDF.
            '{{empresa.website}}'  => '',

            '{{cliente.nome}}'     => $cliente->name ?? 'Cliente exemplo, Lda',
            '{{cliente.nif}}'      => $cliente->nif ?? '5000000000',
            '{{cliente.morada}}'   => $cliente->address ?? 'Luanda, Angola',
            '{{cliente.telefone}}' => $cliente->phone ?? '',
            '{{cliente.email}}'    => $cliente->email ?? '',

            '{{orcamento.numero}}'   => $q->quote_number ?? 'ORC 2026/0001',
            '{{orcamento.data}}'     => $q?->quote_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
            '{{orcamento.validade}}' => $q?->valid_until?->format('d/m/Y') ?? now()->addDays(30)->format('d/m/Y'),
            '{{orcamento.subtotal}}' => $this->dinheiro($q->subtotal ?? 250000),
            '{{orcamento.imposto}}'  => $this->dinheiro($q->tax_amount ?? 35000),
            '{{orcamento.total}}'    => $this->dinheiro($q->total ?? 285000),
            '{{orcamento.moeda}}'    => $q->currency ?? 'AOA',

            '{{utilizador.nome}}' => $q?->creator?->name ?? (auth()->user()->name ?? ''),
            '{{data.hoje}}'       => now()->format('d/m/Y'),
            '{{data.ano}}'        => now()->format('Y'),
        ];
    }

    // ── Auxiliares ───────────────────────────────────────────────────────

    private function dinheiro($valor): string
    {
        return number_format((float) $valor, 2, ',', '.') . ' Kz';
    }

    private function corEscura(string $hex): bool
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return false;
        }

        // Luminância percebida: o olho vê o verde muito mais do que o azul.
        $l = 0.299 * hexdec(substr($hex, 0, 2))
           + 0.587 * hexdec(substr($hex, 2, 2))
           + 0.114 * hexdec(substr($hex, 4, 2));

        return $l < 140;
    }

    private function logoDaEmpresa(Tenant $t): ?string
    {
        foreach (['logo', 'logo_path'] as $campo) {
            $v = $t->{$campo} ?? null;
            if (!$v) {
                continue;
            }

            // Caminho no disco: o dompdf lê ficheiros locais muito mais
            // depressa (e sem depender de rede) do que por URL.
            $caminho = storage_path('app/public/' . ltrim($v, '/'));

            return is_file($caminho) ? $caminho : asset('storage/' . ltrim($v, '/'));
        }

        return null;
    }

    /** Linhas de exemplo para a pré-visualização de um modelo sem orçamento. */
    private function itensDeExemplo(): array
    {
        return [
            (object) ['product_name' => 'Levantamento de requisitos', 'description' => 'Reuniões, análise e documento de âmbito.',
                      'quantity' => 1, 'unit_price' => 80000, 'discount_percent' => 0, 'tax_rate' => 14, 'total' => 80000],
            (object) ['product_name' => 'Desenvolvimento', 'description' => 'Implementação dos módulos acordados.',
                      'quantity' => 1, 'unit_price' => 150000, 'discount_percent' => 0, 'tax_rate' => 14, 'total' => 150000],
            (object) ['product_name' => 'Formação e arranque', 'description' => '8 horas de formação à equipa.',
                      'quantity' => 2, 'unit_price' => 10000, 'discount_percent' => 0, 'tax_rate' => 14, 'total' => 20000],
        ];
    }
}
