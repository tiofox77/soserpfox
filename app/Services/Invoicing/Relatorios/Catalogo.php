<?php

namespace App\Services\Invoicing\Relatorios;

/**
 * O CATÁLOGO DOS RELATÓRIOS: cada slug e a sua classe, e as secções em que
 * a porta os arruma. As rotas, o mapa da migração e os dois ecrãs lêem
 * daqui — não há segunda lista.
 */
final class Catalogo
{
    public const RELATORIOS = [
        'charts' => Graficos::class,
        'profit-loss' => LucrosEPerdas::class,
        'margin' => Margens::class,
        'product-performance' => DesempenhoDeProdutos::class,
        'comparative' => Comparativo::class,
        'sales' => Vendas::class,
        'top-clients' => MelhoresClientes::class,
        'top-products' => MelhoresProdutos::class,
        'sales-by-user' => VendasPorVendedor::class,
        'purchases' => Compras::class,
        'top-suppliers' => MelhoresFornecedores::class,
        'best-supplier' => MelhorFornecedor::class,
        'accounts-receivable' => ContasAReceber::class,
        'accounts-payable' => ContasAPagar::class,
        'payment-methods' => MeiosDePagamento::class,
        'aging-clients' => AntiguidadeDeSaldos::class,
        'account-statement' => ExtractoDeConta::class,
        'vat' => Iva::class,
        'documents' => Documentos::class,
        'price-list' => TabelaDePrecos::class,
        'services' => Servicos::class,
        'expiry-report' => Validades::class,
        'stock-adjustments' => AjustesDeStock::class,
    ];

    public static function existe(string $slug): bool
    {
        return isset(self::RELATORIOS[$slug]);
    }

    public static function abrir(string $slug): Relatorio
    {
        $classe = self::RELATORIOS[$slug] ?? throw new \InvalidArgumentException("Relatório desconhecido: {$slug}");

        return new $classe();
    }

    /** O caminho da página em React. O de validades vive fora de /reports. */
    public static function caminho(string $slug): string
    {
        return $slug === 'expiry-report' ? '/invoicing/expiry-report/novo-ecra' : "/invoicing/reports/{$slug}/novo-ecra";
    }

    /** A permissão que abre cada mapa: o das validades também serve quem gere stock. */
    public static function permissao(string $slug): string
    {
        return $slug === 'expiry-report' ? 'invoicing.reports.view|invoicing.stock.view' : 'invoicing.reports.view';
    }

    /** As mesmas secções, na forma que a vista Blade de sempre lê (nomes de rota). */
    public static function paraAVistaDeSempre(): array
    {
        return array_map(fn ($s) => [
            'title' => __($s['titulo']),
            'icon' => $s['icone'],
            'color' => $s['cor'],
            'reports' => array_map(fn ($r) => [
                'name' => __($r['nome']),
                'desc' => __($r['desc']),
                'icon' => $r['icone'],
                'route' => $r['slug'] === 'expiry-report' ? 'invoicing.expiry-report' : 'invoicing.reports.' . $r['slug'],
            ], $s['relatorios']),
        ], self::seccoes());
    }

    /** As secções da porta dos relatórios, na ordem do ecrã de sempre. */
    public static function seccoes(): array
    {
        return [
            ['titulo' => 'Rentabilidade & Análise', 'icone' => 'fa-coins', 'cor' => 'emerald', 'relatorios' => [
                ['slug' => 'charts', 'nome' => 'Relatório em Gráficos', 'desc' => 'Evolução, rankings e cobrança num relance', 'icone' => 'fa-chart-area'],
                ['slug' => 'profit-loss', 'nome' => 'Lucros e Perdas (DRE)', 'desc' => 'Demonstração de resultados completa', 'icone' => 'fa-chart-line'],
                ['slug' => 'margin', 'nome' => 'Análise de Margem', 'desc' => 'Lucro e margem por produto', 'icone' => 'fa-percentage'],
                ['slug' => 'product-performance', 'nome' => 'Desempenho de Produtos', 'desc' => 'Vendas, stock, lucro e rotação', 'icone' => 'fa-chart-pie'],
                ['slug' => 'comparative', 'nome' => 'Comparativo', 'desc' => 'Variação entre dois períodos', 'icone' => 'fa-balance-scale'],
            ]],
            ['titulo' => 'Vendas', 'icone' => 'fa-arrow-trend-up', 'cor' => 'green', 'relatorios' => [
                ['slug' => 'sales', 'nome' => 'Mapa de Vendas', 'desc' => 'Vendas por período, cliente e status', 'icone' => 'fa-file-invoice'],
                ['slug' => 'top-clients', 'nome' => 'Top Clientes', 'desc' => 'Ranking de clientes por faturação', 'icone' => 'fa-crown'],
                ['slug' => 'top-products', 'nome' => 'Top Produtos Vendidos', 'desc' => 'Produtos com maior volume', 'icone' => 'fa-star'],
                ['slug' => 'sales-by-user', 'nome' => 'Vendas por Vendedor', 'desc' => 'Ranking e desempenho por utilizador', 'icone' => 'fa-user-tie'],
            ]],
            ['titulo' => 'Compras', 'icone' => 'fa-arrow-trend-down', 'cor' => 'orange', 'relatorios' => [
                ['slug' => 'purchases', 'nome' => 'Mapa de Compras', 'desc' => 'Compras por período e fornecedor', 'icone' => 'fa-shopping-cart'],
                ['slug' => 'top-suppliers', 'nome' => 'Top Fornecedores', 'desc' => 'Ranking de fornecedores', 'icone' => 'fa-truck'],
                ['slug' => 'best-supplier', 'nome' => 'Melhor Fornecedor', 'desc' => 'Score multi-critério (volume, fiabilidade)', 'icone' => 'fa-medal'],
            ]],
            ['titulo' => 'Contas Correntes', 'icone' => 'fa-balance-scale', 'cor' => 'blue', 'relatorios' => [
                ['slug' => 'accounts-receivable', 'nome' => 'Contas a Receber', 'desc' => 'Faturas pendentes de clientes', 'icone' => 'fa-hand-holding-usd'],
                ['slug' => 'accounts-payable', 'nome' => 'Contas a Pagar', 'desc' => 'Faturas pendentes a fornecedores', 'icone' => 'fa-money-bill-wave'],
                ['slug' => 'payment-methods', 'nome' => 'Recebimentos por Meio', 'desc' => 'Total recebido por forma de pagamento', 'icone' => 'fa-money-check-alt'],
                ['slug' => 'aging-clients', 'nome' => 'Aging de Clientes', 'desc' => 'Antiguidade de saldos por faixa', 'icone' => 'fa-clock'],
                ['slug' => 'account-statement', 'nome' => 'Extracto de Conta Corrente', 'desc' => 'Movimentos e saldo de um cliente ou fornecedor', 'icone' => 'fa-file-invoice-dollar'],
            ]],
            ['titulo' => 'Fiscal & SAFT', 'icone' => 'fa-landmark', 'cor' => 'red', 'relatorios' => [
                ['slug' => 'vat', 'nome' => 'Mapa de IVA', 'desc' => 'IVA liquidado, dedutível e a pagar', 'icone' => 'fa-percent'],
                ['slug' => 'documents', 'nome' => 'Mapa de Documentos', 'desc' => 'Resumo de faturas, NC, ND e recibos', 'icone' => 'fa-file-alt'],
            ]],
            ['titulo' => 'Produtos & Serviços', 'icone' => 'fa-box-open', 'cor' => 'pink', 'relatorios' => [
                ['slug' => 'price-list', 'nome' => 'Tabela de Preços e Lucro', 'desc' => 'Preço de compra, venda, lucro e margem', 'icone' => 'fa-tags'],
                ['slug' => 'services', 'nome' => 'Mapa de Serviços', 'desc' => 'Análise específica de serviços prestados', 'icone' => 'fa-concierge-bell'],
                ['slug' => 'expiry-report', 'nome' => 'Validade de Produtos', 'desc' => 'Produtos próximos da validade', 'icone' => 'fa-calendar-check'],
            ]],
            ['titulo' => 'Stock & Controlo', 'icone' => 'fa-boxes-stacked', 'cor' => 'amber', 'relatorios' => [
                ['slug' => 'stock-adjustments', 'nome' => 'Ajustes de Stock', 'desc' => 'Seguimento do que foi mexido à mão, por operador', 'icone' => 'fa-sliders'],
            ]],
        ];
    }
}
