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

    protected static function col(string $rotulo, string $chave, ?string $formato = null, ?string $alinhar = null): array
    {
        return array_filter(['rotulo' => $rotulo, 'chave' => $chave, 'formato' => $formato, 'alinhar' => $alinhar ?? (in_array($formato, ['dinheiro', 'inteiro', 'numero', 'percentagem', 'dias'], true) ? 'direita' : null)]);
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
