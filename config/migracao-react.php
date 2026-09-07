<?php

/**
 * O MAPA DA MIGRAÇÃO PARA REACT.
 *
 * Este ficheiro é a lista do que há para fazer. O comando `react:progresso`
 * lê-o, vai ao disco confirmar o que está lá e diz onde vamos — por isso os
 * números não podem mentir: se aqui disser que um ecrã está feito e o ficheiro
 * não existir, o comando acusa em vez de contar.
 *
 * CADA ECRÃ TEM TRÊS ESTADOS, e é de propósito que são três:
 *
 *   por fazer   — só existe o Livewire
 *   a conviver  — o React já existe e serve, mas o Livewire continua de pé
 *   feito       — o Livewire foi apagado e já não há por onde voltar
 *
 * Contar «a conviver» como feito era mentir para nós próprios: enquanto os dois
 * existirem, há duas implementações da mesma coisa — que é exactamente o
 * problema que esta migração existe para acabar.
 *
 * LISTA E EDITOR SÃO ENTRADAS SEPARADAS, e foi preciso separá-las: as cinco
 * listas de documentos ficaram feitas de uma vez, e apontar as entradas para os
 * componentes `…Create` fazia o contador dizer 64% quando os editores — que é
 * onde está a matemática do imposto — não tinham uma linha escrita.
 *
 * COMO SE ACRESCENTA UM ECRÃ: uma linha em `ecras`, com o componente Livewire,
 * a vista Blade e o ficheiro React que o há-de substituir. O caminho do React
 * pode ainda não existir; é isso que o «por fazer» quer dizer.
 */

return [

    /*
     * Onde vive o código React. Relativo à raiz do projecto.
     */
    'raiz' => 'resources/js',

    'frentes' => [

        'Casca e menu' => [
            'nota' => 'O layout que todos os 244 ecrãs usam.',
            'ecras' => [
                /*
                 * Menu, barra do topo e casca são UMA entrada porque vivem no
                 * mesmo ficheiro. Separá-los em três contava as 2.621 linhas
                 * do app.blade.php três vezes e dava um total inventado.
                 */
                [
                    'nome'     => 'Casca, menu e barra do topo',
                    'blade'    => 'resources/views/layouts/app.blade.php',
                    'react'    => 'ecras/casca/Casca.tsx',
                ],
            ],
        ],

        'Facturação: listas e painel' => [
            'nota' => 'Cinco destas listas saem todas do mesmo ListaDeDocumentos.tsx — o que muda entre elas vem do TiposDeDocumento.',
            'ecras' => [
                [
                    'nome'     => 'Painel da facturação',
                    'livewire' => 'app/Livewire/Invoicing/InvoicingDashboard.php',
                    'blade'    => 'resources/views/livewire/invoicing/invoicing-dashboard.blade.php',
                    'react'    => 'ecras/facturacao/Painel.tsx',
                ],
                [
                    'nome'     => 'Lista de facturas de venda',
                    'livewire' => 'app/Livewire/Invoicing/Sales/Invoices.php',
                    'blade'    => 'resources/views/livewire/invoicing/faturas-venda/invoices.blade.php',
                    'react'    => 'ecras/facturacao/vendas/ListaDeFacturas.tsx',
                ],
                [
                    'nome'     => 'Lista de proformas de venda',
                    'livewire' => 'app/Livewire/Invoicing/Sales/Proformas.php',
                    'blade'    => 'resources/views/livewire/invoicing/proformas-venda/proformas.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de orçamentos',
                    'livewire' => 'app/Livewire/Invoicing/Sales/Quotes.php',
                    'blade'    => 'resources/views/livewire/invoicing/orcamentos-venda/orcamentos.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de facturas de compra',
                    'livewire' => 'app/Livewire/Invoicing/Purchases/Invoices.php',
                    'blade'    => 'resources/views/livewire/invoicing/faturas-compra/invoices.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de proformas de compra',
                    'livewire' => 'app/Livewire/Invoicing/Purchases/Proformas.php',
                    'blade'    => 'resources/views/livewire/invoicing/proformas-compra/proformas.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de recibos',
                    'livewire' => 'app/Livewire/Invoicing/Receipts/Receipts.php',
                    'blade'    => 'resources/views/livewire/invoicing/receipts/receipts.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de notas de crédito',
                    'livewire' => 'app/Livewire/Invoicing/CreditNotes/CreditNotes.php',
                    'blade'    => 'resources/views/livewire/invoicing/credit-notes/credit-notes.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de notas de débito',
                    'livewire' => 'app/Livewire/Invoicing/DebitNotes/DebitNotes.php',
                    'blade'    => 'resources/views/livewire/invoicing/debit-notes/debit-notes.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Lista de adiantamentos',
                    'livewire' => 'app/Livewire/Invoicing/Advances/Advances.php',
                    'blade'    => 'resources/views/livewire/invoicing/advances/advances.blade.php',
                    'react'    => 'ecras/facturacao/ListaDeDocumentos.tsx',
                ],
                [
                    'nome'     => 'Clientes',
                    'livewire' => 'app/Livewire/Invoicing/Clients.php',
                    'blade'    => 'resources/views/livewire/invoicing/clients.blade.php',
                    'react'    => 'ecras/facturacao/Clientes.tsx',
                ],
                [
                    'nome'     => 'Produtos',
                    'livewire' => 'app/Livewire/Invoicing/Products.php',
                    'blade'    => 'resources/views/livewire/invoicing/products/products.blade.php',
                    'react'    => 'ecras/facturacao/Produtos.tsx',
                ],
            ],
        ],

        'Facturação: emissores' => [
            'nota' => 'Onde está a matemática do imposto e a assinatura da AGT. Entram por último, e com o travão de mão puxado.',
            'ecras' => [
                [
                    'nome'     => 'Emitir factura de venda',
                    'livewire' => 'app/Livewire/Invoicing/Sales/InvoiceCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/faturas-venda/invoice-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirFactura.tsx',
                ],
                [
                    'nome'     => 'Emitir proforma de venda',
                    'livewire' => 'app/Livewire/Invoicing/Sales/ProformaCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/proformas-venda/proforma-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirProposta.tsx',
                ],
                [
                    'nome'     => 'Emitir orçamento',
                    'livewire' => 'app/Livewire/Invoicing/Sales/QuoteCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/orcamentos-venda/orcamento-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirProposta.tsx',
                ],
                [
                    'nome'     => 'Emitir factura de compra',
                    'livewire' => 'app/Livewire/Invoicing/Purchases/InvoiceCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/faturas-compra/invoice-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirFacturaDeCompra.tsx',
                ],
                [
                    'nome'     => 'Emitir proforma de compra',
                    'livewire' => 'app/Livewire/Invoicing/Purchases/ProformaCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/proformas-compra/proforma-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirProposta.tsx',
                ],
                [
                    'nome'     => 'Registar recibo',
                    'livewire' => 'app/Livewire/Invoicing/Receipts/ReceiptCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/receipts/receipt-create.blade.php',
                    'react'    => 'ecras/facturacao/RegistarRecibo.tsx',
                ],
                [
                    'nome'     => 'Nota de crédito',
                    'livewire' => 'app/Livewire/Invoicing/CreditNotes/CreditNoteCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/credit-notes/credit-note-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirNota.tsx',
                ],
                [
                    'nome'     => 'Nota de débito',
                    'livewire' => 'app/Livewire/Invoicing/DebitNotes/DebitNoteCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/debit-notes/debit-note-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirNota.tsx',
                ],
                [
                    'nome'     => 'Definições da facturação',
                    'livewire' => 'app/Livewire/Invoicing/Settings.php',
                    'blade'    => 'resources/views/livewire/invoicing/settings.blade.php',
                    'react'    => 'ecras/facturacao/Definicoes.tsx',
                ],
                [
                    'nome'     => 'Registar adiantamento',
                    'livewire' => 'app/Livewire/Invoicing/Advances/AdvanceCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/advances/advance-create.blade.php',
                    'react'    => 'ecras/facturacao/EmitirAdiantamento.tsx',
                ],
                [
                    'nome'     => 'Guias de transporte',
                    'livewire' => 'app/Livewire/Invoicing/TransportGuides/TransportGuides.php',
                    'blade'    => 'resources/views/livewire/invoicing/transport-guides/transport-guides.blade.php',
                    'react'    => 'ecras/facturacao/GuiasDeTransporte.tsx',
                ],
                [
                    'nome'     => 'Importações',
                    'livewire' => 'app/Livewire/Invoicing/Imports/Imports.php',
                    'blade'    => 'resources/views/livewire/invoicing/imports/imports.blade.php',
                    'react'    => 'ecras/facturacao/Importacoes.tsx',
                ],
                [
                    'nome'     => 'Modal de pagamento',
                    'livewire' => 'app/Livewire/Invoicing/PaymentModal.php',
                    'blade'    => 'resources/views/livewire/invoicing/payment-modal.blade.php',
                    'react'    => 'ecras/facturacao/RegistarPagamento.tsx',
                ],
            ],
        ],

        'Facturação: stock' => [
            'nota' => 'O stock por armazém, as quebras, os lotes e as transferências. As regras ficam nos ganchos do StockMovement — nunca se soma à mão.',
            'ecras' => [
                [
                    'nome'     => 'Gestão de stock',
                    'livewire' => 'app/Livewire/Invoicing/StockManagement.php',
                    'blade'    => 'resources/views/livewire/invoicing/stock/stock-management.blade.php',
                    'react'    => 'ecras/facturacao/Stock.tsx',
                ],
                [
                    'nome'     => 'Quebras de stock',
                    'livewire' => 'app/Livewire/Invoicing/Quebras.php',
                    'blade'    => 'resources/views/livewire/invoicing/quebras.blade.php',
                    'react'    => 'ecras/facturacao/Quebras.tsx',
                ],
                [
                    'nome'     => 'Lotes e validades',
                    'livewire' => 'app/Livewire/Invoicing/ProductBatches/ProductBatches.php',
                    'blade'    => 'resources/views/livewire/invoicing/product-batches/product-batches.blade.php',
                    'react'    => 'ecras/facturacao/Lotes.tsx',
                ],
                [
                    'nome'     => 'Transferências entre armazéns',
                    'livewire' => 'app/Livewire/Invoicing/WarehouseTransfer.php',
                    'blade'    => 'resources/views/livewire/invoicing/warehouse-transfer.blade.php',
                    'react'    => 'ecras/facturacao/TransferenciasEntreArmazens.tsx',
                ],
                [
                    'nome'     => 'Transferências entre empresas',
                    'livewire' => 'app/Livewire/Invoicing/InterCompanyTransfer.php',
                    'blade'    => 'resources/views/livewire/invoicing/inter-company-transfer.blade.php',
                    'react'    => 'ecras/facturacao/TransferenciasEntreEmpresas.tsx',
                ],
            ],
        ],

        'Facturação: catálogos' => [
            'nota' => 'Seis ecrãs com a mesma forma saem todos do mesmo Catalogo.tsx — o esquema de cada um vem do Catalogos.',
            'ecras' => [
                [
                    'nome'     => 'Fornecedores',
                    'livewire' => 'app/Livewire/Invoicing/Suppliers.php',
                    'blade'    => 'resources/views/livewire/invoicing/suppliers/suppliers.blade.php',
                    'react'    => 'ecras/facturacao/Catalogo.tsx',
                ],
                [
                    'nome'     => 'Categorias',
                    'livewire' => 'app/Livewire/Invoicing/Categories.php',
                    'blade'    => 'resources/views/livewire/invoicing/categories/categories.blade.php',
                    'react'    => 'ecras/facturacao/Catalogo.tsx',
                ],
                [
                    'nome'     => 'Marcas',
                    'livewire' => 'app/Livewire/Invoicing/Brands.php',
                    'blade'    => 'resources/views/livewire/invoicing/brands/brands.blade.php',
                    'react'    => 'ecras/facturacao/Catalogo.tsx',
                ],
                [
                    'nome'     => 'Armazéns',
                    'livewire' => 'app/Livewire/Invoicing/Warehouses.php',
                    'blade'    => 'resources/views/livewire/invoicing/warehouses/warehouses.blade.php',
                    'react'    => 'ecras/facturacao/Catalogo.tsx',
                ],
                [
                    'nome'     => 'Condições de pagamento',
                    'livewire' => 'app/Livewire/Invoicing/PaymentTerms.php',
                    'blade'    => 'resources/views/livewire/invoicing/payment-terms.blade.php',
                    'react'    => 'ecras/facturacao/Catalogo.tsx',
                ],
                [
                    'nome'     => 'Impostos',
                    'livewire' => 'app/Livewire/Invoicing/TaxManagement.php',
                    'blade'    => 'resources/views/livewire/invoicing/tax-management.blade.php',
                    'react'    => 'ecras/facturacao/Catalogo.tsx',
                ],
            ],
        ],

        'Facturação: fiscal e AGT' => [
            'nota' => 'O XML do SAFT saiu do ecrã para o GeradorDeSaft e as séries gravam pelo GestaoDeSeries. AGTDocumentGenerator e AGTValidationModal não têm rota nem vista que os use — não contam.',
            'ecras' => [
                [
                    'nome'     => 'Séries de documentos',
                    'livewire' => 'app/Livewire/Invoicing/SeriesManagement.php',
                    'blade'    => 'resources/views/livewire/invoicing/series-management.blade.php',
                    'react'    => 'ecras/facturacao/Series.tsx',
                ],
                [
                    'nome'     => 'Auditoria',
                    'livewire' => 'app/Livewire/Invoicing/AuditTrailViewer.php',
                    'blade'    => 'resources/views/livewire/invoicing/audit-trail-viewer.blade.php',
                    'react'    => 'ecras/facturacao/Auditoria.tsx',
                ],
                [
                    'nome'     => 'Gerador SAFT-AO',
                    'livewire' => 'app/Livewire/Invoicing/SAFTGenerator.php',
                    'blade'    => 'resources/views/livewire/invoicing/saftgenerator.blade.php',
                    'react'    => 'ecras/facturacao/Saft.tsx',
                ],
                [
                    'nome'     => 'Configurações AGT',
                    'livewire' => 'app/Livewire/Invoicing/AGTSettings.php',
                    'blade'    => 'resources/views/livewire/invoicing/agt-settings.blade.php',
                    'react'    => 'ecras/facturacao/Agt.tsx',
                ],
                [
                    'nome'     => 'Credenciais AGT do contribuinte',
                    'livewire' => 'app/Livewire/Invoicing/AGTCredentials.php',
                    'blade'    => 'resources/views/livewire/invoicing/agt-credentials.blade.php',
                    'react'    => 'ecras/facturacao/CredenciaisAgt.tsx',
                ],
                /*
                 * Passou despercebido ao mapa até ao fim: vive em
                 * `app/Livewire/Agt/`, e não em `app/Livewire/Invoicing/`
                 * como os outros 71.
                 */
                [
                    'nome'     => 'Facturas recebidas (adquirente) — AGT',
                    'livewire' => 'app/Livewire/Agt/AdquirenteIndex.php',
                    'blade'    => 'resources/views/livewire/agt/adquirente-index.blade.php',
                    'react'    => 'ecras/facturacao/AdquirenteAgt.tsx',
                ],
            ],
        ],
        'Facturação: relatórios' => [
            'nota' => 'Cada mapa é uma classe em Services\\Invoicing\\Relatorios (esquema + dados); os 22 mapas de tabela saem todos do mesmo Relatorio.tsx, e o Livewire pede os números ao mesmo Catalogo.',
            'ecras' => [
                [
                    'nome'     => 'Relatórios (porta)',
                    'livewire' => 'app/Livewire/Invoicing/Reports/ReportsHub.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/reports-hub.blade.php',
                    'react'    => 'ecras/facturacao/RelatoriosHub.tsx',
                ],
                [
                    'nome'     => 'Relatório em Gráficos',
                    'livewire' => 'app/Livewire/Invoicing/Reports/GraficosReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/graficos-report.blade.php',
                    'react'    => 'ecras/facturacao/Graficos.tsx',
                ],
                [
                    'nome'     => 'Lucros e Perdas (DRE)',
                    'livewire' => 'app/Livewire/Invoicing/Reports/ProfitLossReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/profit-loss-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Análise de Margem',
                    'livewire' => 'app/Livewire/Invoicing/Reports/MarginReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/margin-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Desempenho de Produtos',
                    'livewire' => 'app/Livewire/Invoicing/Reports/ProductPerformanceReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/product-performance-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Comparativo entre Períodos',
                    'livewire' => 'app/Livewire/Invoicing/Reports/ComparativeReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/comparative-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Mapa de Vendas',
                    'livewire' => 'app/Livewire/Invoicing/Reports/SalesReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/sales-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Top Clientes',
                    'livewire' => 'app/Livewire/Invoicing/Reports/TopClientsReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/top-clients-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Top Produtos Vendidos',
                    'livewire' => 'app/Livewire/Invoicing/Reports/TopProductsReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/top-products-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Vendas por Vendedor',
                    'livewire' => 'app/Livewire/Invoicing/Reports/SalesByUserReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/sales-by-user-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Mapa de Compras',
                    'livewire' => 'app/Livewire/Invoicing/Reports/PurchasesReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/purchases-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Top Fornecedores',
                    'livewire' => 'app/Livewire/Invoicing/Reports/TopSuppliersReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/top-suppliers-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Melhor Fornecedor',
                    'livewire' => 'app/Livewire/Invoicing/Reports/BestSupplierReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/best-supplier-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Contas a Receber',
                    'livewire' => 'app/Livewire/Invoicing/Reports/AccountsReceivableReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/accounts-receivable-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Contas a Pagar',
                    'livewire' => 'app/Livewire/Invoicing/Reports/AccountsPayableReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/accounts-payable-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Recebimentos por Meio de Pagamento',
                    'livewire' => 'app/Livewire/Invoicing/Reports/PaymentMethodsReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/payment-methods-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Aging de Clientes',
                    'livewire' => 'app/Livewire/Invoicing/Reports/AgingClientsReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/aging-clients-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Extracto de Conta Corrente',
                    'livewire' => 'app/Livewire/Invoicing/Reports/AccountStatementReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/account-statement-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Mapa de IVA',
                    'livewire' => 'app/Livewire/Invoicing/Reports/VatReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/vat-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Mapa de Documentos',
                    'livewire' => 'app/Livewire/Invoicing/Reports/DocumentsReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/documents-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Tabela de Preços e Lucro',
                    'livewire' => 'app/Livewire/Invoicing/Reports/PriceListReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/price-list-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Mapa de Serviços',
                    'livewire' => 'app/Livewire/Invoicing/Reports/ServicesReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/services-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Validade de Produtos',
                    'livewire' => 'app/Livewire/Invoicing/Reports/ExpiryReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/expiry-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
                [
                    'nome'     => 'Ajustes de Stock',
                    'livewire' => 'app/Livewire/Invoicing/Reports/StockAdjustmentsReport.php',
                    'blade'    => 'resources/views/livewire/invoicing/reports/stock-adjustments-report.blade.php',
                    'react'    => 'ecras/facturacao/Relatorio.tsx',
                ],
            ],
        ],

        'Facturação: POS, offline e propostas' => [
            'nota' => 'Os turnos gravam pela TurnosDoPos, a cópia pela LeituraDeCopiaOffline, o PIN pela DefinicaoDePin e os modelos pela GestaoDeModelos/EdicaoDeModelo. O editor em React posiciona os blocos por números, não por arrastar.',
            'ecras' => [
                [
                    'nome'     => 'Turno do POS',
                    'livewire' => 'app/Livewire/Invoicing/Pos/PosShiftManager.php',
                    'blade'    => 'resources/views/livewire/invoicing/pos/pos-shift-manager.blade.php',
                    'react'    => 'ecras/facturacao/TurnosDoPos.tsx',
                ],
                [
                    'nome'     => 'Histórico de turnos',
                    'livewire' => 'app/Livewire/Invoicing/Pos/ShiftHistory.php',
                    'blade'    => 'resources/views/livewire/invoicing/pos/shift-history.blade.php',
                    'react'    => 'ecras/facturacao/HistoricoDeTurnos.tsx',
                ],
                [
                    'nome'     => 'Importar cópia offline',
                    'livewire' => 'app/Livewire/Invoicing/ImportarCopiaOffline.php',
                    'blade'    => 'resources/views/livewire/invoicing/importar-copia-offline.blade.php',
                    'react'    => 'ecras/facturacao/ImportarCopiaOffline.tsx',
                ],
                [
                    'nome'     => 'PIN de turno',
                    'livewire' => 'app/Livewire/Invoicing/Offline/DefinirPin.php',
                    'blade'    => 'resources/views/livewire/invoicing/offline/definir-pin.blade.php',
                    'react'    => 'ecras/facturacao/DefinirPin.tsx',
                ],
                [
                    'nome'     => 'Modelos de proposta',
                    'livewire' => 'app/Livewire/Invoicing/Propostas/ModelosDeProposta.php',
                    'blade'    => 'resources/views/livewire/invoicing/propostas/modelos-de-proposta.blade.php',
                    'react'    => 'ecras/facturacao/ModelosDeProposta.tsx',
                ],
                [
                    'nome'     => 'Editor de modelo de proposta',
                    'livewire' => 'app/Livewire/Invoicing/Propostas/EditorDeModelo.php',
                    'blade'    => 'resources/views/livewire/invoicing/propostas/editor-de-modelo.blade.php',
                    'react'    => 'ecras/facturacao/EditorDeModelo.tsx',
                ],
            ],
        ],

    ],

];
