<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Proforma {{ $proforma->proforma_number }}</title>
    <style>
        @page {
            margin: 10mm;
            size: A4 portrait;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #000;
            background: #f5f5f5;
            margin: 0;
            padding: 20px 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        
        .page-wrapper {
            width: 210mm;
            min-height: 297mm;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            padding: 12mm 8mm;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            padding: 0;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .page-wrapper {
                box-shadow: none;
                margin: 0;
                padding: 12mm 8mm;
            }
        }
        
        /* HEADER */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .header-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .header-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
            padding-top: 10px;
        }
        
        .logo {
            width: 80px;
            height: 50px;
            background: #4a90e2;
            margin-bottom: 5px;
            text-align: center;
            line-height: 50px;
            color: white;
            font-weight: bold;
        }
        
        .company-name {
            font-weight: bold;
            font-size: 9px;
            color: #2c5aa0;
            margin-bottom: 3px;
        }
        
        .company-details {
            font-size: 7px;
            line-height: 1.3;
        }
        
        .client-box {
            background: #f8f9fa;
            border-left: 3px solid #2c5aa0;
            padding: 5px;
            margin-bottom: 10px;
            font-size: 7px;
        }
        
        .qr-box {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7px;
            color: #999;
        }
        
        .client-name {
            font-weight: bold;
            font-size: 8px;
        }
        
        /* TITLE */
        .doc-title {
            text-align: left;
            border-bottom: 2px solid #2c5aa0;
            padding: 5px 0;
            margin: 10px 0;
            font-weight: bold;
            font-size: 10px;
            color: #2c5aa0;
        }
        
        /* TABLES */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7px;
        }
        
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 2px;
            text-align: center;
        }
        
        table th {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .text-left {
            text-align: left !important;
            padding-left: 3px !important;
        }
        
        .text-right {
            text-align: right !important;
            padding-right: 3px !important;
        }
        
        /* FOOTER */
        .footer {
            display: table;
            width: 100%;
            margin-top: 15px;
            page-break-inside: avoid;
        }
        
        .footer-left {
            display: table-cell;
            width: 55%;
            vertical-align: top;
            padding-right: 15px;
        }
        
        .footer-right {
            display: table-cell;
            width: 45%;
            vertical-align: top;
        }
        
        .totals-box {
            border: 2px solid #2c5aa0;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 3px;
        }
        
        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 2px;
            font-size: 7px;
        }
        
        .totals-row > span:first-child {
            display: table-cell;
            text-align: left;
        }
        
        .totals-row > span:last-child {
            display: table-cell;
            text-align: right;
            font-weight: bold;
        }
        
        .totals-final {
            border-top: 2px solid #666;
            margin-top: 5px;
            padding-top: 5px;
            font-weight: bold;
            background: #f0f4f8;
            margin-left: -8px;
            margin-right: -8px;
            padding-left: 8px;
            padding-right: 8px;
        }
        
        .section-title {
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
            font-size: 8px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 2px;
        }
        
        .agt-info {
            text-align: center;
            font-size: 7px;
            margin-top: 10px;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 3px;
            color: #333;
            font-weight: bold;
        }
        
        .footer-text {
            text-align: center;
            font-size: 6px;
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #ccc;
            color: #666;
        }
        
        .extenso {
            text-align: center;
            font-size: 6px;
            font-style: italic;
            margin-top: 3px;
            color: #666;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="container">
            <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <div class="logo">LOGO</div>
                <div class="company-name">{{ $tenant->name }}</div>
                <div class="company-details">
                    NIF: {{ $tenant->nif ?? 'N/A' }}<br>
                    {{ $tenant->address ?? 'N/A' }}<br>
                    Tel: {{ $tenant->phone ?? 'N/A' }}
                </div>
            </div>
            <div class="header-right">
                <div class="client-box">
                    <strong>Exmo.(s) Sr.(s)</strong><br>
                    <span class="client-name">{{ $proforma->client->name }}</span><br>
                    NIF: {{ $proforma->client->nif ?? 'Consumidor Final' }}<br>
                    <strong>Original</strong>
                </div>
                <div class="qr-box">QR</div>
            </div>
        </div>

        <!-- TITLE -->
        <div class="doc-title">
            Proforma de Venda n.º {{ $proforma->proforma_number }}
        </div>

        <!-- INFO TABLE -->
        <table>
            <thead>
                <tr>
                    <th>Moeda</th>
                    <th>Data Emissão</th>
                    <th>Hora</th>
                    <th>Validade</th>
                    <th>Operador</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>AOA</td>
                    <td>{{ $proforma->proforma_date->format('d/m/Y') }}</td>
                    <td>{{ $proforma->created_at->format('H:i') }}</td>
                    <td>{{ $proforma->valid_until ? $proforma->valid_until->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $proforma->creator->name ?? 'Sistema' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- ITEMS TABLE -->
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Código</th>
                    <th style="width: 30%;">Descrição</th>
                    <th style="width: 8%;">Qtd</th>
                    <th style="width: 12%;">Preço</th>
                    <th style="width: 12%;">Subtotal</th>
                    <th style="width: 8%;">Desc%</th>
                    <th style="width: 8%;">IVA%</th>
                    <th style="width: 12%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($proforma->items as $item)
                <tr>
                    <td>{{ $item->product->code ?? '-' }}</td>
                    <td class="text-left">{{ $item->product_name }}</td>
                    <td>{{ number_format($item->quantity, 0, ',', '.') }}</td>
                    <td class="currency">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="currency">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }}</td>
                    <td>{{ number_format($item->discount_percent ?? 0, 0) }}%</td>
                    <td>{{ number_format($item->tax_rate ?? 14, 0) }}%</td>
                    <td class="text-right">{{ number_format($item->total, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <!-- FOOTER -->
        <div class="footer">
            <div class="footer-left">
                <div class="section-title">Resumo de Impostos</div>
                <table style="margin-bottom: 10px;">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Taxa</th>
                            <th>Incidência</th>
                            <th>Total IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>IVA</td>
                            <td>14%</td>
                            <td class="text-right">{{ number_format($proforma->subtotal, 2, ',', '.') }} Kz</td>
                            <td class="text-right">{{ number_format($proforma->tax_amount, 2, ',', '.') }} Kz</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 10px; padding: 5px; background: #f8f9fa; border-radius: 3px;">
                    <div class="section-title" style="border: none; margin-bottom: 3px;">Regime Fiscal</div>
                    <div style="font-size: 7px;">{{ $tenant->regime ?? 'Regime Geral' }}</div>
                </div>
            </div>

            <div class="footer-right">
                <div class="totals-box">
                    <div class="totals-row">
                        <span>Total Ilíquido</span>
                        <span>{{ number_format($proforma->subtotal, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="totals-row">
                        <span>Desc. Comercial</span>
                        <span>{{ number_format($proforma->discount_commercial ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="totals-row">
                        <span>Desc. Financeiro</span>
                        <span>{{ number_format($proforma->discount_financial ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="totals-row">
                        <span>IVA (14%)</span>
                        <span>{{ number_format($proforma->tax_amount, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="totals-row">
                        <span>Retenção</span>
                        <span>{{ number_format($proforma->irt_amount ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="totals-row totals-final">
                        <span>TOTAL A PAGAR</span>
                        <span>{{ number_format($proforma->total - ($proforma->irt_amount ?? 0), 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="extenso">
                        {{ number_format($proforma->total - ($proforma->irt_amount ?? 0), 2, ',', '.') }} AOA
                    </div>
                </div>
            </div>
        </div>

        <div class="agt-info">
            Processado por sistema certificado AGT | Regime: {{ $tenant->regime ?? 'Regime Geral' }}
            @if($proforma->saft_hash)
                <br>
                <strong>HASH e SAFT-AO:</strong> "{{ substr($proforma->saft_hash, -4) }}"
            @endif
        </div>

        <div class="footer-text">
            Documento processado em sistema certificado | Todos os direitos reservados
        </div>
        </div>
    </div>
    
    {{-- Script para abrir diálogo de impressão automaticamente --}}
    <script>
        // Verificar se foi aberto em nova janela
        if (window.opener || document.referrer.includes('/proformas/create')) {
            // Aguardar o carregamento completo da página
            window.addEventListener('load', function() {
                // Aguardar 500ms para garantir que tudo foi renderizado
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>
