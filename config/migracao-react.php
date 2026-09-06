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

    ],

];
