<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura de Venda {{ $invoice->invoice_number }}</title>
    <style>
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
            display: flex;
            flex-direction: column;
        }
        
        .main-content {
            flex: 1;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .company-info {
            flex: 0 0 60%;
        }
        
        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .logo {
            width: 120px;
            height: 80px;
            border-radius: 5px;
            margin-bottom: 8px;
            position: relative;
            background-color: transparent;
        }
        
        .logo-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 4px;
            border: none;
        }
        
        .logo-fallback {
            width: 100%;
            height: 100%;
            background: #4a90e2;
            border-radius: 4px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            border: none;
        }
        
        .company-name {
            font-weight: bold;
            font-size: 11px;
            color: #2c5aa0;
        }
        
        .company-details {
            font-size: 8px;
            line-height: 1.3;
            margin-top: 8px;
        }
        
        .right-section {
            flex: 0 0 35%;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            position: relative;
            margin-top: 30px;
        }
        
        .client-info {
            text-align: left;
            width: 100%;
            margin-bottom: 25px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #2c5aa0;
        }
        
        .client-label {
            font-weight: bold;
            font-size: 8px;
            color: #2c5aa0;
        }
        
        .client-name {
            font-size: 9px;
            margin: 2px 0;
            font-weight: bold;
        }
        
        .client-nif {
            font-size: 8px;
            margin-bottom: 5px;
        }
        
        .doc-type {
            font-weight: bold;
            font-size: 9px;
        }
        
        .qr-section {
            text-align: right;
            width: 100%;
        }
        
        .qr-code {
            width: 80px;
            height: 80px;
            display: block;
            margin-left: auto;
            background: white;
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .doc-header {
            text-align: center;
            margin: 15px 0;
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 8px;
        }
        
        .doc-title {
            font-weight: bold;
            font-size: 12px;
            color: #2c5aa0;
        }
        
        .doc-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8px;
        }
        
        .doc-info-table th,
        .doc-info-table td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: center;
        }
        
        .doc-info-table th {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 8px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 3px 2px;
            text-align: center;
        }
        
        .items-table th {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }
        
        .items-table .discriminacao {
            text-align: left;
            padding-left: 5px;
        }
        
        .items-table .currency {
            text-align: right;
            padding-right: 5px;
        }
        
        .footer-section {
            margin-top: auto;
            padding-top: 15px;
        }
        
        .bottom-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .left-bottom {
            flex: 1;
            margin-right: 15px;
        }
        
        .tax-section {
            margin-bottom: 15px;
        }
        
        .tax-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 5px;
            color: #2c5aa0;
        }
        
        .tax-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 8px;
        }
        
        .tax-table th,
        .tax-table td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: center;
        }
        
        .tax-table th {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }
        
        .tax-table .currency {
            text-align: right;
            padding-right: 5px;
        }
        
        .regime-section {
            margin-bottom: 12px;
        }
        
        .regime-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 2px;
            color: #2c5aa0;
        }
        
        .bank-section {
            margin-bottom: 12px;
        }
        
        .bank-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 5px;
            color: #2c5aa0;
        }
        
        .bank-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        
        .bank-table th,
        .bank-table td {
            border: 1px solid #ddd;
            padding: 3px;
            text-align: center;
        }
        
        .bank-table th {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }
        
        .system-info {
            font-size: 8px;
            text-align: center;
            margin-bottom: 15px;
            color: #666;
        }
        
        .right-bottom {
            flex: 0 0 170px;
        }
        
        .summary-section {
            border: 2px solid #2c5aa0;
            padding: 8px;
            background-color: #f9f9f9;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 8px;
        }
        
        .summary-total {
            border-top: 2px solid #666;
            margin-top: 5px;
            padding-top: 5px;
            font-weight: bold;
            background-color: #f8f9fa;
            color: #333;
            margin-left: -10px;
            margin-right: -10px;
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .total-extenso {
            font-size: 8px;
            font-style: italic;
            text-align: center;
            margin-top: 5px;
            text-transform: uppercase;
        }
        
        .agt-description {
            font-size: 8px;
            text-align: center;
            margin-top: 10px;
            color: #333;
            font-weight: bold;
        }
        
        .page-footer {
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            margin-top: 20px;
            color: #666;
        }
        
        .cancelled-warning {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border: 1px solid #dc3545;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            margin-top: 5px;
            text-align: center;
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
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="header-section">
                <div class="company-info">
                    <div class="logo-section">
                        <div class="logo">
                            @if($tenant->logo)
                                <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Logo da Empresa" class="logo-image" onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=\'logo-fallback\'>LOGO</div>';" />
                            @else
                                <div class="logo-fallback">LOGO</div>
                            @endif
                        </div>
                        <div>
                            <div class="company-name">{{ $tenant->name }}</div>
                        </div>
                    </div>
                    <div class="company-details">
                        NIF: {{ $tenant->nif ?? 'N/A' }}<br>
                        Endereço: {{ $tenant->address ?? 'N/A' }}<br>
                        Telefone: {{ $tenant->phone ?? 'N/A' }}<br>
                        E-mail: {{ $tenant->email ?? 'N/A' }}<br>
                        @if($tenant->website)
                            Website: {{ $tenant->website }}
                        @endif
                    </div>
                </div>

                <div class="right-section">
                    <div class="client-info">
                        <div class="client-label">Exmo.(s) Sr.(s)</div>
                        <div class="client-name">{{ $invoice->client->name }}</div>
                        <div class="client-nif">NIF: {{ $invoice->client->nif ?? 'Consumidor Final' }}</div>
                        <div class="doc-type">Original</div>
                        
                        @if($invoice->status === 'cancelled')
                            <div class="cancelled-warning">
                                ⚠️ FATURA ANULADA
                            </div>
                        @endif
                    </div>
                    
                    <div class="qr-section">
                        <div class="qr-code">
                            <div style="width: 80px; height: 80px; background: #f0f0f0; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #666;">QR</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="doc-header">
                <div class="doc-title" style="text-align: left;">Fatura de Venda n.º {{ $invoice->invoice_number }}</div>
            </div>

            <table class="doc-info-table">
                <thead>
                    <tr>
                        <th>Moeda</th>
                        <th>Data De Emissão</th>
                        <th>Hora De Emissão</th>
                        <th>Data de Venc.</th>
                        <th>Operador</th>
                        <th>Referência</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $invoice->currency ?? 'AOA' }}</td>
                        <td>{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                        <td>{{ $invoice->created_at->format('H:i') }}</td>
                        <td>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'N/A' }}</td>
                        <td>{{ $invoice->creator->name ?? 'Sistema' }}</td>
                        <td>{{ $invoice->reference ?? $invoice->invoice_number }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Discriminação</th>
                        <th>Qtd.</th>
                        <th>Preço Uni.</th>
                        <th>Total sem Imposto</th>
                        <th>Desc%</th>
                        <th>Taxa%</th>
                        <th>Total Imposto</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product->code ?? '-' }}</td>
                        <td class="discriminacao">
                            {{ $item->product_name }}
                            @if($item->description)
                                <br><small>{{ $item->description }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($item->quantity, 0, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }}</td>
                        <td>{{ number_format($item->discount_percent ?? 0, 0) }}%</td>
                        <td>{{ number_format($item->tax_rate ?? 14, 0) }}%</td>
                        <td class="currency">{{ number_format($item->tax_amount, 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item->total, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer-section">
            <div class="bottom-section">
                <div class="left-bottom">
                    <div class="tax-section">
                        <div class="tax-title">Resumo de Impostos</div>
                        <table class="tax-table">
                            <thead>
                                <tr>
                                    <th>Descrição</th>
                                    <th>Taxa</th>
                                    <th>Incidência</th>
                                    <th>Total Imposto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>IVA</td>
                                    <td>14%</td>
                                    <td class="currency">{{ number_format($invoice->subtotal, 2, ',', '.') }}</td>
                                    <td class="currency">{{ number_format($invoice->tax_amount, 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="regime-section">
                        <div class="regime-title">Regime Fiscal</div>
                        <div>{{ $tenant->regime ?? 'Regime Geral' }}</div>
                    </div>

                    <div class="system-info">
                        Processado por sistema certificado AGT | Regime: {{ $tenant->regime ?? 'Regime Geral' }}
                    </div>
                </div>

                <div class="right-bottom">
                    <div class="summary-section">
                        <div class="summary-row">
                            <span>Total Ilíquido</span>
                            <span>{{ number_format($invoice->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Comercial</span>
                            <span>{{ number_format($invoice->discount_commercial ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Financeiro</span>
                            <span>{{ number_format($invoice->discount_financial ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Incidência IVA</span>
                            <span>{{ number_format($invoice->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>IVA</span>
                            <span>{{ number_format($invoice->tax_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Total da Fatura</span>
                            <span>{{ number_format($invoice->total, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Retenção</span>
                            <span>{{ number_format($invoice->irt_amount ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total a Pagar</span>
                            <span>{{ number_format($invoice->total - ($invoice->irt_amount ?? 0), 2, ',', '.') }}</span>
                        </div>
                        
                        <div class="total-extenso">
                            {{ number_format($invoice->total - ($invoice->irt_amount ?? 0), 2, ',', '.') }} AOA
                        </div>
                    </div>
                </div>
            </div>

            <div class="agt-description">
                Esta factura foi processada pelo Sistema de Facturação da SOFTEC LDA, certif. n.º 2025/AGT
            </div>

            <div class="page-footer">
                Documento processado em sistema certificado | Todos os direitos reservados
            </div>
        </div>
    </div>
</body>
</html>
