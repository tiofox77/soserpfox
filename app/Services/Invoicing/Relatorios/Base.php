<?php

namespace App\Services\Invoicing\Relatorios;

/**
 * O que todos os relatórios partilham: o período e uns atalhos de esquema.
 */
abstract class Base implements Relatorio
{
    /** O intervalo do mapa, pelo que veio nos filtros ou pelo atalho de omissão. */
    protected function intervalo(array $f, string $omissao = 'month'): array
    {
        return Periodo::intervalo($f['period'] ?? null, $f['dateFrom'] ?? null, $f['dateTo'] ?? null, $omissao);
    }

    /** Um filtro só com o que se pediu, sem strings vazias. */
    protected function filtro(array $f, string $nome): mixed
    {
        $v = $f[$nome] ?? null;

        return $v === '' ? null : $v;
    }

    /** A maior parte dos mapas não tem papel próprio: imprime-se o ecrã. */
    public function pdf(array $f, array $dados): ?string
    {
        return null;
    }

    protected static function col(string $rotulo, string $chave, ?string $formato = null, ?string $alinhar = null): array
    {
        return array_filter(['rotulo' => $rotulo, 'chave' => $chave, 'formato' => $formato, 'alinhar' => $alinhar ?? (in_array($formato, ['dinheiro', 'inteiro', 'numero', 'percentagem', 'dias'], true) ? 'direita' : null)]);
    }

    /**
     * UMA COLUNA QUE LEVA AO DOCUMENTO DA LINHA.
     *
     * O texto é o de `$chave` — é esse que o CSV exporta, porque uma folha de
     * cálculo não abre ligações de sessão. As moradas vêm em `$ligacao`, cada
     * uma o caminho de um campo DA LINHA: `abrir` (o próprio texto), `previsao`
     * (o quadrado verde de imprimir) e `pdf` (o vermelho). Uma linha sem morada
     * mostra só o texto: nem todos os movimentos têm papel.
     *
     * @param  array{abrir?: string, previsao?: string, pdf?: string}  $ligacao
     */
    protected static function colLigacao(string $rotulo, string $chave, array $ligacao): array
    {
        return ['rotulo' => $rotulo, 'chave' => $chave, 'formato' => 'ligacao', 'ligacao' => $ligacao];
    }

    protected static function cartao(string $rotulo, string $chave, string $formato = 'dinheiro', string $cor = 'gray'): array
    {
        return ['rotulo' => $rotulo, 'chave' => $chave, 'formato' => $formato, 'cor' => $cor];
    }

    protected static function opcoes(array $mapa): array
    {
        return collect($mapa)->map(fn ($rotulo, $valor) => ['valor' => (string) $valor, 'rotulo' => $rotulo])->values()->all();
    }
}
