@php
    // ============================================
    // LÓGICA DE PAGINAÇÃO MANUAL
    // ============================================
    
    // Configurações de itens por página
    $itemsFirstPage = 24;      // Primeira página (tem header completo)
    $itemsMiddlePage = 25;     // Páginas do meio
    $itemsLastPage = 8;       // Última página (tem footer completo com totais)
    
    // Preparar items com dados do produto incluídos
    $items = $invoice->items->map(function($item) {
        return [
            'product_code' => $item->product->code ?? $item->product_code ?? '-',
            'product_name' => $item->product_name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_percent' => $item->discount_percent ?? 0,
            'discount_amount' => $item->discount_amount ?? 0,
            'tax_rate' => $item->tax_rate ?? 14,
            'tax_amount' => $item->tax_amount ?? 0,
            'subtotal' => $item->subtotal ?? ($item->quantity * $item->unit_price),
            'total' => $item->total ?? 0,
        ];
    })->toArray();
    $totalItems = count($items);
    
    // Calcular páginas
    $pages = [];
    $currentIndex = 0;
    $pageNumber = 1;
    
    // Calcular subtotais acumulados para "Transporte"
    $runningSubtotal = 0;
    $runningTax = 0;
    $runningTotal = 0;
    
    while ($currentIndex < $totalItems) {
        $isFirstPage = ($pageNumber === 1);
        
        // Determinar quantos itens restam
        $remainingItems = $totalItems - $currentIndex;
        
        // Determinar limite desta página
        if ($isFirstPage) {
            $limit = $itemsFirstPage;
        } else {
            $limit = $itemsMiddlePage;
        }
        
        // Ajustar para não ultrapassar os itens restantes
        $limit = min($limit, $remainingItems);
        
        // Verificar se esta é a última página (se vamos processar todos os itens restantes)
        $isLastPage = ($currentIndex + $limit >= $totalItems);
        
        // Extrair itens desta página
        $pageItems = array_slice($items, $currentIndex, $limit);
        
        // Calcular subtotais desta página
        $pageSubtotal = 0;
        $pageTax = 0;
        $pageTotal = 0;
        foreach ($pageItems as $item) {
            $pageSubtotal += ($item['quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);
            $pageTax += $item['tax_amount'] ?? 0;
            $pageTotal += $item['total'] ?? 0;
        }
        
        // Atualizar totais acumulados (transporte da página anterior)
        $transporteSubtotal = $runningSubtotal;
        $transporteTax = $runningTax;
        $transporteTotal = $runningTotal;
        
        // Acumular para próxima página
        $runningSubtotal += $pageSubtotal;
        $runningTax += $pageTax;
        $runningTotal += $pageTotal;
        
        $pages[] = [
            'number' => $pageNumber,
            'items' => $pageItems,
            'isFirst' => $isFirstPage,
            'isLast' => $isLastPage,
            'startIndex' => $currentIndex,
            'transporteSubtotal' => $transporteSubtotal,
            'transporteTax' => $transporteTax,
            'transporteTotal' => $transporteTotal,
            'pageSubtotal' => $pageSubtotal,
            'pageTax' => $pageTax,
            'pageTotal' => $pageTotal,
            'acumuladoSubtotal' => $runningSubtotal,
            'acumuladoTax' => $runningTax,
            'acumuladoTotal' => $runningTotal,
        ];
        
        $currentIndex += $limit;
        $pageNumber++;
    }
    
    $totalPages = count($pages);
    
    // Se só tem uma página, é primeira E última
    if ($totalPages === 1) {
        $pages[0]['isFirst'] = true;
        $pages[0]['isLast'] = true;
    }
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9.5px;
            line-height: 1.3;
            color: #000;
            background: #f0f0f0;
        }
        
        /* ============================================
           PÁGINA - Simula folha A4
           ============================================ */
        .page {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 10px auto;
            padding: 12mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            position: relative;
            display: flex;
            flex-direction: column;
            page-break-after: always;
        }
        
        .page:last-child {
            page-break-after: auto;
        }
        
        /* ============================================
           HEADER - Fixo em todas as páginas
           ============================================ */
        .page-header {
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
        }
        
        .company-section {
            width: 55%;
        }
        
        .logo {
            width: 90px;
            height: 55px;
            margin-bottom: 5px;
        }
        
        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .company-name {
            font-weight: bold;
            font-size: 11.5px;
            color: #2c5aa0;
        }
        
        .company-details {
            font-size: 8.5px;
            color: #333;
            line-height: 1.4;
        }
        
        .client-section {
            width: 40%;
            text-align: right;
        }
        
        .client-label {
            font-size: 7px;
            color: #666;
        }
        
        .client-name {
            font-weight: bold;
            font-size: 10.5px;
        }
        
        .client-nif {
            font-size: 8px;
        }
        
        .qr-code {
            margin-top: 5px;
        }
        
        .qr-code img {
            width: 65px;
            height: 65px;
        }
        
        /* Document Title */
        .doc-header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1a3d6e 100%);
            color: white;
            padding: 6px 10px;
            margin-bottom: 8px;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .doc-title {
            font-size: 12.5px;
            font-weight: bold;
        }
        
        .page-indicator {
            font-size: 9.5px;
            opacity: 0.9;
        }
        
        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5px;
        }
        
        .info-table th {
            background: #f0f0f0;
            padding: 4px 6px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        
        .info-table td {
            padding: 4px 6px;
            border: 1px solid #ddd;
        }
        
        /* ============================================
           BODY - Tabela de itens (flexível)
           ============================================ */
        .page-body {
            flex: 1;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        
        .items-table th {
            background: #2c5aa0;
            color: white;
            padding: 5px 3px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #1a3d6e;
            font-size: 7.5px;
        }
        
        .items-table td {
            padding: 4px 3px;
            border: 1px solid #ddd;
            text-align: center;
            vertical-align: middle;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .items-table .col-code { width: 7%; }
        .items-table .col-desc { width: 28%; text-align: left !important; }
        .items-table .col-qty { width: 5%; }
        .items-table .col-price { width: 11%; }
        .items-table .col-subtotal { width: 12%; }
        .items-table .col-disc { width: 5%; }
        .items-table .col-tax { width: 5%; }
        .items-table .col-tax-amount { width: 11%; }
        .items-table .col-total { width: 12%; }
        
        .currency {
            text-align: right !important;
            font-family: 'Courier New', monospace;
            font-size: 8.5px;
        }
        
        /* Linha de Transporte */
        .transporte-row {
            background: #e8f4fd !important;
            font-weight: bold;
        }
        
        .transporte-row td {
            border-top: 2px solid #2c5aa0;
        }
        
        /* ============================================
           FOOTER - Dinâmico
           ============================================ */
        .page-footer {
            margin-top: auto;
            padding-top: 8px;
        }
        
        /* Footer Simples (Transporte) */
        .footer-transporte {
            background: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd;
        }
        
        .footer-transporte-label {
            font-weight: bold;
            color: #2c5aa0;
        }
        
        .footer-transporte-values {
            display: flex;
            gap: 20px;
        }
        
        .footer-transporte-item {
            text-align: right;
        }
        
        .footer-transporte-item span {
            display: block;
        }
        
        .footer-transporte-item .label {
            font-size: 7px;
            color: #666;
        }
        
        .footer-transporte-item .value {
            font-weight: bold;
            font-size: 9px;
        }
        
        /* Footer Completo (última página) */
        .footer-complete {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        
        .footer-left {
            width: 58%;
        }
        
        .footer-right {
            width: 40%;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 8px;
            color: #2c5aa0;
            border-bottom: 1px solid #2c5aa0;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }
        
        /* Tax Table */
        .tax-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            margin-bottom: 8px;
        }
        
        .tax-table th, .tax-table td {
            padding: 3px 5px;
            border: 1px solid #ddd;
        }
        
        .tax-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        /* Bank Table */
        .bank-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            margin-bottom: 8px;
        }
        
        .bank-table th, .bank-table td {
            padding: 2px 4px;
            border: 1px solid #ddd;
        }
        
        .bank-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        /* Totals Box */
        .totals-box {
            border: 2px solid #2c5aa0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 6px;
            font-size: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .totals-row:last-of-type {
            border-bottom: none;
        }
        
        .totals-row.highlight {
            background: #2c5aa0;
            color: white;
            font-weight: bold;
            font-size: 10px;
            padding: 5px 6px;
        }
        
        .total-extenso {
            background: #f5f5f5;
            padding: 4px 6px;
            font-size: 7px;
            font-style: italic;
            text-align: center;
            border-top: 1px dashed #ccc;
        }
        
        /* System Info */
        .system-info {
            font-size: 6px;
            color: #666;
            text-align: center;
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px solid #eee;
        }
        
        /* Page Number Footer */
        .page-number-footer {
            text-align: center;
            font-size: 7px;
            color: #666;
            margin-top: 5px;
            padding-top: 4px;
            border-top: 1px solid #ddd;
        }
        
        /* ============================================
           PRINT STYLES
           ============================================ */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin: 0;
                padding: 0;
            }
            
            html {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            body {
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            
            .page {
                width: 210mm !important;
                height: 287mm !important;
                max-height: 287mm !important;
                margin: 0 !important;
                padding: 5mm 10mm !important;
                box-shadow: none !important;
                overflow: hidden !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
            }
            
            .page:last-child {
                page-break-after: avoid !important;
            }
            
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
            
            /* Esconder botões de ação na impressão */
            .no-print, .action-buttons-container {
                display: none !important;
            }
            
            /* Ajustar footer para caber */
            .page-footer {
                margin-top: auto !important;
                padding-top: 5px !important;
            }
            
            .footer-complete {
                gap: 10px !important;
            }
        }
    </style>
</head>
<body>
    @foreach($pages as $page)
    <div class="page">
        {{-- ============================================
            HEADER (em todas as páginas)
            ============================================ --}}
        <div class="page-header">
            <div class="header-content">
                <div class="company-section">
                    <div class="logo">
                        @if($tenant->logo)
                            <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Logo">
                        @else
                            <div style="width: 90px; height: 55px; background: #2c5aa0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 10px;">LOGO</div>
                        @endif
                    </div>
                    <div class="company-name">{{ $tenant->name }}</div>
                    <div class="company-details">
                        NIF: {{ $tenant->nif ?? 'N/A' }}<br>
                        {{ $tenant->address ?? '' }}<br>
                        Tel: {{ $tenant->phone ?? 'N/A' }} | {{ $tenant->email ?? '' }}
                    </div>
                </div>
                
                <div class="client-section">
                    <div class="client-label">Exmo.(s) Sr.(s)</div>
                    <div class="client-name">{{ $invoice->client->name }}</div>
                    <div class="client-nif">NIF: {{ $invoice->client->nif ?? '999999999' }}</div>
                    @if(isset($qrCode) && $qrCode['image'])
                        <div class="qr-code">
                            <img src="{{ $qrCode['image'] }}" alt="QR Code AGT">
                            @if($qrCode['atcud'])
                                <div style="font-size: 6px;">ATCUD: {{ $qrCode['atcud'] }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Document Header --}}
        <div class="doc-header">
            <div class="doc-title">FATURA DE VENDA N.º {{ $invoice->invoice_number }}</div>
            <div class="page-indicator">Página {{ $page['number'] }} de {{ $totalPages }}</div>
        </div>
        
        {{-- Info Table (só na primeira página) --}}
        @if($page['isFirst'])
        <table class="info-table">
            <tr>
                <th>Moeda</th>
                <th>Data Emissão</th>
                <th>Hora</th>
                <th>Data Venc.</th>
                <th>Operador</th>
                <th>Referência</th>
            </tr>
            <tr>
                <td>AOA</td>
                <td>{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                <td>{{ $invoice->created_at->format('H:i') }}</td>
                <td>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'N/A' }}</td>
                <td>{{ $invoice->creator->name ?? 'Sistema' }}</td>
                <td>{{ $invoice->reference ?? '-' }}</td>
            </tr>
        </table>
        @endif
        
        {{-- ============================================
            BODY - Tabela de itens
            ============================================ --}}
        <div class="page-body">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 3%; text-align: center;">#</th>
                        <th class="col-code">Código</th>
                        <th class="col-desc">Discriminação</th>
                        <th class="col-qty">Qtd.</th>
                        <th class="col-price">Preço Uni.</th>
                        <th class="col-subtotal">Total s/ Imp.</th>
                        <th class="col-disc">Desc%</th>
                        <th class="col-tax">Taxa%</th>
                        <th class="col-tax-amount">Tot. Imposto</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Linha de Transporte (páginas após a primeira) --}}
                    @if(!$page['isFirst'] && $page['transporteTotal'] > 0)
                    <tr class="transporte-row">
                        <td colspan="5" style="text-align: left; font-weight: bold;">TRANSPORTE</td>
                        <td class="currency">{{ number_format($page['transporteSubtotal'], 2, ',', '.') }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td class="currency">{{ number_format($page['transporteTax'], 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($page['transporteTotal'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    {{-- Itens da página --}}
                    @foreach($page['items'] as $idx => $item)
                    @php $globalIndex = $page['startIndex'] + $idx + 1; @endphp
                    <tr>
                        <td style="text-align: center; font-weight: bold; color: #999;">{{ $globalIndex }}</td>
                        <td>{{ $item['product_code'] }}</td>
                        <td class="col-desc">
                            {{ $item['product_name'] }}
                            @if($item['description'])
                                <br><small style="color: #666;">{{ $item['description'] }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($item['quantity'], 0, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item['unit_price'], 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item['quantity'] * $item['unit_price'], 2, ',', '.') }}</td>
                        <td>{{ number_format($item['discount_percent'] ?? 0, 0) }}%</td>
                        <td>{{ number_format($item['tax_rate'] ?? 14, 0) }}%</td>
                        <td class="currency">{{ number_format($item['tax_amount'] ?? 0, 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item['total'] ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    
                    {{-- Linha de Total Acumulado (a transportar) - páginas não-finais --}}
                    @if(!$page['isLast'])
                    <tr class="transporte-row">
                        <td colspan="5" style="text-align: left; font-weight: bold;">A TRANSPORTAR</td>
                        <td class="currency">{{ number_format($page['acumuladoSubtotal'], 2, ',', '.') }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td class="currency">{{ number_format($page['acumuladoTax'], 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($page['acumuladoTotal'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        
        {{-- ============================================
            FOOTER
            ============================================ --}}
        <div class="page-footer">
            @if($page['isLast'])
                {{-- Footer Completo (última página) --}}
                <div class="footer-complete">
                    <div class="footer-left">
                        {{-- Resumo de Impostos --}}
                        <div class="section-title">Resumo de Impostos</div>
                        <table class="tax-table">
                            <tr>
                                <th>Descrição</th>
                                <th>Taxa</th>
                                <th>Incidência</th>
                                <th>Total Imposto</th>
                            </tr>
                            <tr>
                                <td>IVA</td>
                                <td>14%</td>
                                <td class="currency">{{ number_format($invoice->subtotal, 2, ',', '.') }}</td>
                                <td class="currency">{{ number_format($invoice->tax_amount, 2, ',', '.') }}</td>
                            </tr>
                        </table>
                        
                        {{-- Dados Bancários --}}
                        @if(isset($bankAccounts) && $bankAccounts->count() > 0)
                        <div class="section-title">Dados Bancários</div>
                        <table class="bank-table">
                            <tr>
                                <th>Banco</th>
                                <th>Conta</th>
                                <th>IBAN</th>
                            </tr>
                            @foreach($bankAccounts as $account)
                            <tr>
                                <td>{{ $account->bank->name ?? 'N/A' }}</td>
                                <td>{{ $account->account_number }}</td>
                                <td>{{ $account->iban ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </table>
                        @endif
                        
                        <div class="system-info">
                            Processado por sistema certificado AGT | Regime: {{ $tenant->regime ?? 'Regime Geral' }}
                            @if($invoice->saft_hash)
                                | HASH: "{{ substr($invoice->saft_hash, -4) }}"
                            @endif
                        </div>
                    </div>
                    
                    <div class="footer-right">
                        <div class="totals-box">
                            <div class="totals-row">
                                <span>Total Ilíquido</span>
                                <span>{{ number_format($invoice->subtotal, 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="totals-row">
                                <span>Desc. Comercial</span>
                                <span>{{ number_format($invoice->discount_commercial ?? 0, 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="totals-row">
                                <span>Desc. Financeiro</span>
                                <span>{{ number_format($invoice->discount_financial ?? 0, 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="totals-row">
                                <span>IVA (14%)</span>
                                <span>{{ number_format($invoice->tax_amount, 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="totals-row">
                                <span>Retenção</span>
                                <span>{{ number_format($invoice->irt_amount ?? 0, 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="totals-row highlight">
                                <span>TOTAL A PAGAR</span>
                                <span>{{ number_format($invoice->total - ($invoice->irt_amount ?? 0), 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="total-extenso">
                                {{ numberToWords($invoice->total - ($invoice->irt_amount ?? 0), 'AOA') }}
                            </div>
                        </div>
                    </div>
                </div>
            @else
                {{-- Footer Simples (transporte) --}}
                <div class="footer-transporte">
                    <div class="footer-transporte-label">
                        <i class="fas fa-arrow-right"></i> CONTINUA NA PÁGINA {{ $page['number'] + 1 }}
                    </div>
                    <div class="footer-transporte-values">
                        <div class="footer-transporte-item">
                            <span class="label">Subtotal Acum.</span>
                            <span class="value">{{ number_format($page['acumuladoSubtotal'], 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="footer-transporte-item">
                            <span class="label">IVA Acum.</span>
                            <span class="value">{{ number_format($page['acumuladoTax'], 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="footer-transporte-item">
                            <span class="label">Total Acum.</span>
                            <span class="value">{{ number_format($page['acumuladoTotal'], 2, ',', '.') }} Kz</span>
                        </div>
                    </div>
                </div>
            @endif
            
            {{-- Número da página --}}
            <div class="page-number-footer">
                {{ $tenant->name }} | NIF: {{ $tenant->nif ?? 'N/A' }} | Página {{ $page['number'] }} de {{ $totalPages }}
            </div>
        </div>
    </div>@endforeach

    <script>
        // Remover página em branco extra na impressão
        window.addEventListener('beforeprint', function() {
            // Forçar recálculo do layout
            document.body.style.height = 'auto';
            
            // Remover qualquer espaço extra após última página
            const pages = document.querySelectorAll('.page');
            if (pages.length > 0) {
                const lastPage = pages[pages.length - 1];
                lastPage.style.pageBreakAfter = 'avoid';
                lastPage.style.breakAfter = 'avoid';
                lastPage.style.marginBottom = '0';
                lastPage.style.paddingBottom = '0';
            }
            
            // Remover espaços em branco do body
            document.body.style.marginBottom = '0';
            document.body.style.paddingBottom = '0';
        });
        
        window.addEventListener('afterprint', function() {
            // Restaurar estilos após impressão
            document.body.style.height = '';
            document.body.style.marginBottom = '';
            document.body.style.paddingBottom = '';
        });
    </script>
</body>
</html>
