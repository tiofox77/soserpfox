<?php

namespace App\Services\Invoicing\Relatorios;

use App\Services\Invoicing\Analytics\GraficosDeFacturacao;

/**
 * O relatório em gráficos: o mesmo que os outros mapas contam em tabelas,
 * visto de relance. Os números vêm do `GraficosDeFacturacao`; aqui só se
 * escolhe o período.
 */
class Graficos extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'charts',
            'titulo' => 'Relatório em Gráficos',
            'descricao' => 'Evolução, rankings e cobrança num relance.',
            'periodo' => ['omissao' => 'year'],
            'filtros' => [],
            'cartoes' => [
                self::cartao('Vendas', 'g.resumo.vendas', 'dinheiro', 'green'),
                self::cartao('Documentos', 'g.resumo.documentos', 'inteiro', 'blue'),
                self::cartao('Ticket médio', 'g.resumo.ticket', 'dinheiro', 'purple'),
                self::cartao('Compras', 'g.resumo.compras', 'dinheiro', 'orange'),
                self::cartao('Recebido', 'g.resumo.recebido', 'dinheiro', 'gray'),
            ],
            'tabelas' => [],
            'csv' => false,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'year');

        return ['g' => GraficosDeFacturacao::para($tenantId, $de, $ate)->tudo(), 'de' => $de, 'ate' => $ate];
    }
}
