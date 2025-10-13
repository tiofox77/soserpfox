
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma {{ $proforma->proforma_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.1;
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
            padding: 15mm;
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
            margin-bottom: 15px;
            font-size: 9px;
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
            margin-bottom: 30px;
            font-size: 9px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 4px;
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
            padding-top: 30px;
        }
        
        .bottom-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .left-bottom {
            flex: 1;
            margin-right: 30px;
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
            font-size: 9px;
            margin-bottom: 10px;
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
            flex: 0 0 200px;
        }
        
        .summary-section {
            border: 2px solid #2c5aa0;
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 9px;
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
        
        /* Estilos para faturas anuladas e creditadas */
        .document-status-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            font-weight: bold;
            color: rgba(220, 53, 69, 0.3);
            border: 8px solid rgba(220, 53, 69, 0.3);
            padding: 20px 40px;
            text-align: center;
            z-index: 1000;
            pointer-events: none;
            text-transform: uppercase;
        }
        
        .document-status-warning {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            color: #856404;
        }
        
        .credit-notes-section {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
        }
        
        .credit-notes-title {
            font-weight: bold;
            color: #721c24;
            margin-bottom: 8px;
            font-size: 10px;
        }
        
        .credit-note-item {
            font-size: 9px;
            margin-bottom: 5px;
            padding: 5px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 3px;
        }
        
        .status-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            pointer-events: none;
        }
        
        .overlay-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            text-align: center;
        }
        
        .overlay-text {
            font-size: 48px;
            font-weight: bold;
            color: rgba(220, 53, 69, 0.4);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            margin-bottom: 10px;
            letter-spacing: 8px;
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
        
        .credit-notes-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border: 1px solid #ffc107;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            margin-top: 5px;
            text-align: center;
        }
        
        .credit-notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }
        
        .credit-notes-table th,
        .credit-notes-table td {
            border: 1px solid #dc3545;
            padding: 5px;
            text-align: left;
        }
        
        .credit-notes-table th {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-draft {
            background-color: #ffeaa7;
            color: #6c5ce7;
            border: 1px solid #fdcb6e;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .page-wrapper {
                box-shadow: none;
                margin: 0;
                padding: 15mm;
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
                        <div class="client-name">{{ $proforma->client->name }}</div>
                        <div class="client-nif">NIF: {{ $proforma->client->nif ?? 'Consumidor Final' }}</div>
                        <div class="doc-type">Original</div>
                        
                        
                        
                        
                        
                    </div>
                    
                    <div class="qr-section">
                        <div class="qr-code">
                            <img src="data:image/png;base64,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" alt="QR Code" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="doc-header">
                <div class="doc-title" style="text-align: left;">Proforma de Venda n.º {{ $proforma->proforma_number }}</div>
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
                        <td>AOA</td>
                        <td>{{ $proforma->proforma_date->format('d/m/Y') }}</td>
                        <td>{{ $proforma->created_at->format('H:i') }}</td>
                        <td>{{ $proforma->valid_until ? $proforma->valid_until->format('d/m/Y') : 'N/A' }}</td>
                        <td>{{ $proforma->creator->name ?? 'Sistema' }}</td>
                        <td>{{ $proforma->reference ?? $proforma->proforma_number }}</td>
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
                    
                    @foreach($proforma->items as $item)
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
                                    <td class="currency">{{ number_format($proforma->subtotal, 2, ',', '.') }}</td>
                                    <td class="currency">{{ number_format($proforma->tax_amount, 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="regime-section">
                        <div class="regime-title">Regime Fiscal</div>
                        <div>{{ $tenant->regime ?? 'Regime Geral' }}</div>
                    </div>

                    
                        @if($bankAccounts && $bankAccounts->count() > 0)
                        <div class="bank-section">
                            <div class="bank-title">DADOS BANCÁRIOS</div>
                            <table class="bank-table">
                                <thead>
                                    <tr>
                                        <th>Banco</th>
                                        <th>Conta</th>
                                        <th>IBAN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bankAccounts as $account)
                                        <tr>
                                            <td>{{ $account->bank->name ?? 'N/A' }}</td>
                                            <td>{{ $account->account_number }}</td>
                                            <td>{{ $account->iban ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    

                    <div class="system-info">
                        Processado por sistema certificado AGT | Regime: {{ $tenant->regime ?? 'Regime Geral' }}
                        @if($proforma->saft_hash)
                            <br>
                            <strong>HASH e SAFT-AO:</strong> "{{ substr($proforma->saft_hash, -4) }}"
                        @endif
                    </div>
                </div>

                <div class="right-bottom">
                    <div class="summary-section">
                        <div class="summary-row">
                            <span>Total Ilíquido</span>
                            <span>{{ number_format($proforma->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Comercial</span>
                            <span>{{ number_format($proforma->discount_commercial ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Financeiro</span>
                            <span>{{ number_format($proforma->discount_financial ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Incidência IVA</span>
                            <span>{{ number_format($proforma->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>IVA</span>
                            <span>{{ number_format($proforma->tax_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Total da Proforma</span>
                            <span>{{ number_format($proforma->total, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Retenção</span>
                            <span>{{ number_format($proforma->irt_amount ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total a Pagar</span>
                            <span>{{ number_format($proforma->total - ($proforma->irt_amount ?? 0), 2, ',', '.') }}</span>
                        </div>
                        
                        <div class="total-extenso">
                            {{ numberToWords($proforma->total - ($proforma->irt_amount ?? 0), 'AOA') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="agt-description">
                Esta proforma foi processada pelo Sistema de Facturação | Regime: {{ $tenant->regime ?? 'Regime Geral' }}
            </div>

            <div class="page-footer">
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
    