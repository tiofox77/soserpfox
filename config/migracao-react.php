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
 * COMO SE ACRESCENTA UM ECRÃ: uma linha em `ecras`, com o componente Livewire,
 * a vista Blade e o ficheiro React que o há-de substituir. O caminho do React
 * pode ainda não existir; é isso que o «por fazer» quer dizer.
 */

return [

    /*
     * Onde vive o código React. Relativo à raiz do projecto.
     */
    'raiz' => 'resources/js',

    /*
     * As frentes de trabalho, pela ordem por que vão ser feitas.
     *
     * `livewire` e `blade` são o que existe hoje — servem para medir o tamanho
     * do que falta. `react` é o ficheiro que os substitui.
     */
    'frentes' => [

        'Casca e menu' => [
            'nota' => 'O layout que todos os 244 ecrãs usam. Entra primeiro porque tudo o resto assenta nele.',
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

        'Facturação' => [
            'nota' => 'O módulo inteiro: listas, emissores, notas e painel.',
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
                    'nome'     => 'Criar factura de venda',
                    'livewire' => 'app/Livewire/Invoicing/Sales/InvoiceCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/faturas-venda/invoice-create.blade.php',
                    'react'    => 'ecras/facturacao/vendas/CriarFactura.tsx',
                ],
                [
                    'nome'     => 'Proformas de venda',
                    'livewire' => 'app/Livewire/Invoicing/Sales/ProformaCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/proformas-venda/proformas.blade.php',
                    'react'    => 'ecras/facturacao/vendas/Proformas.tsx',
                ],
                [
                    'nome'     => 'Orçamentos',
                    'livewire' => 'app/Livewire/Invoicing/Sales/QuoteCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/orcamentos-venda/quotes.blade.php',
                    'react'    => 'ecras/facturacao/vendas/Orcamentos.tsx',
                ],
                [
                    'nome'     => 'Notas de crédito',
                    'livewire' => 'app/Livewire/Invoicing/CreditNotes/CreditNoteCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/credit-notes/credit-note-create.blade.php',
                    'react'    => 'ecras/facturacao/notas/NotaDeCredito.tsx',
                ],
                [
                    'nome'     => 'Notas de débito',
                    'livewire' => 'app/Livewire/Invoicing/DebitNotes/DebitNoteCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/debit-notes/debit-note-create.blade.php',
                    'react'    => 'ecras/facturacao/notas/NotaDeDebito.tsx',
                ],
                [
                    'nome'     => 'Recibos',
                    'livewire' => 'app/Livewire/Invoicing/Receipts/ReceiptCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/receipts/receipt-create.blade.php',
                    'react'    => 'ecras/facturacao/Recibos.tsx',
                ],
                [
                    'nome'     => 'Facturas de compra',
                    'livewire' => 'app/Livewire/Invoicing/Purchases/InvoiceCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/faturas-compra/invoices.blade.php',
                    'react'    => 'ecras/facturacao/compras/ListaDeFacturas.tsx',
                ],
                [
                    'nome'     => 'Proformas de compra',
                    'livewire' => 'app/Livewire/Invoicing/Purchases/ProformaCreate.php',
                    'blade'    => 'resources/views/livewire/invoicing/proformas-compra/proformas.blade.php',
                    'react'    => 'ecras/facturacao/compras/Proformas.tsx',
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
                [
                    'nome'     => 'Definições da facturação',
                    'livewire' => 'app/Livewire/Invoicing/Settings.php',
                    'blade'    => 'resources/views/livewire/invoicing/settings.blade.php',
                    'react'    => 'ecras/facturacao/Definicoes.tsx',
                ],
            ],
        ],

    ],

];
