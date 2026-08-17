
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Crédito {{ $creditNote->credit_note_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /*
         * Página, para o DomPDF.
         *
         * O documento saía cortado à direita e a passar para uma segunda
         * página. A folha estava escrita para o browser: a caixa tinha 210 mm
         * de largura fixa e as margens da página vinham por omissão (cerca de
         * 25 mm de cada lado), portanto 210 + 50 mm num papel de 210. O que
         * excedia caía fora.
         *
         * O @page estava declarado dentro de @media print, e a margem que ele
         * anulava nunca chegava a ser aplicada. Aqui fora, é o próprio papel
         * que define a margem, e o conteúdo ocupa o que sobra.
         */
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.1;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }

        /*
         * display:flex não existe no DomPDF — era ignorado, e com ele o
         * alinhamento que dele dependia. Blocos normais, que é o que ele sabe
         * compor.
         */
        .page-wrapper {
            width: 100%;
            background: white;
            margin: 0;
            padding: 0;
            display: block;
        }
        
        .main-content {
            display: block;
        }
        
        .header-section {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        
        .company-info {
            display: table-cell;
            width: 62%;
            vertical-align: top;
        }
        
        .logo-section {
            display: block;
            margin-bottom: 6px;
        }
        
        .logo {
            width: 100px;
            height: 60px;
            border-radius: 5px;
            margin-bottom: 4px;
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
            background: #22c55e;
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
            color: #b91c1c;
        }
        
        .company-details {
            font-size: 7.5px;
            line-height: 1.3;
            margin-top: 4px;
        }
        
        .right-section {
            display: table-cell;
            width: 38%;
            vertical-align: top;
            text-align: right;
        }
        
        .client-info {
            text-align: left;
            width: 100%;
            margin-bottom: 8px;
            padding: 6px 8px;
            background-color: #f8f9fa;
            border-left: 4px solid #b91c1c;
        }
        
        .client-label {
            font-weight: bold;
            font-size: 8px;
            color: #b91c1c;
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
            width: 100px;
            height: 100px;
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
            margin: 6px 0;
            border-bottom: 2px solid #b91c1c;
            padding-bottom: 4px;
        }
        
        .doc-title {
            font-weight: bold;
            font-size: 12px;
            color: #b91c1c;
        }
        
        .doc-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 8px;
        }
        
        .doc-info-table th,
        .doc-info-table td {
            border: 1px solid #ddd;
            padding: 3px;
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
            margin-bottom: 0;
            font-size: 8px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 2px 3px;
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
            padding-top: 8px;
        }
        
        .bottom-section {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        
        .left-bottom {
            display: table-cell;
            vertical-align: top;
            padding-right: 15px;
        }
        
        .tax-section {
            margin-bottom: 6px;
        }
        
        .tax-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 3px;
            color: #b91c1c;
        }
        
        .tax-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 4px;
        }
        
        .tax-table th,
        .tax-table td {
            border: 1px solid #ddd;
            padding: 2px 3px;
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
            margin-bottom: 4px;
        }
        
        .regime-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 2px;
            color: #b91c1c;
        }
        
        .bank-section {
            margin-bottom: 4px;
        }
        
        .bank-title {
            font-weight: bold;
            font-size: 8px;
            margin-bottom: 3px;
            color: #b91c1c;
        }
        
        .bank-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
        }
        
        .bank-table th,
        .bank-table td {
            border: 1px solid #ddd;
            padding: 2px;
            text-align: center;
        }
        
        .bank-table th {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }
        
        .system-info {
            font-size: 7px;
            text-align: center;
            margin-bottom: 4px;
            color: #666;
        }
        
        .right-bottom {
            display: table-cell;
            width: 200px;
            vertical-align: top;
        }
        
        .summary-section {
            border: 2px solid #b91c1c;
            padding: 6px 8px;
            background-color: #f9f9f9;
        }
        
        .summary-row {
            display: block;
            overflow: hidden;
            margin-bottom: 1px;
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
            font-size: 7px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
            margin-top: 6px;
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
            
            @page {
                size: A4;
                margin: 0;
            }
            
            .page-wrapper {
                box-shadow: none;
                margin: 0;
                padding: 10mm 12mm;
                width: 210mm;
                height: 297mm;
                max-height: 297mm;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    
    
    <div class="page-wrapper">
        @include('pdf.invoicing.partials.agt-signature-sidebar', ['document' => $creditNote, 'documentLabel' => 'Nota de Crédito SOS ERP'])
        <div class="main-content">
            <div class="header-section">
                <div class="company-info">
                    <div class="logo-section">
                        <div class="logo">
                            @include('pdf.invoicing.partials.logo')
                        </div>
                        <div>
                            <div class="company-name">{{ $tenant->nomeParaDocumentos() }}</div>
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
                    <div class="supplier-info">
                        <div class="supplier-label">Cliente</div>
                        <div class="supplier-name">{{ $creditNote->client->name }}</div>
                        <div class="supplier-nif">NIF: {{ $creditNote->client->nif ?? 'N/D' }}</div>
                        <div class="doc-type">Original</div>
                        
                        
                        
                        
                        
                    </div>
                    
                    <div class="qr-section">
                        <div class="qr-code">
                            @if(isset($qrCode) && $qrCode['image'])
                                <img src="{{ $qrCode['image'] }}" alt="QR Code AGT" style="width: 100px; height: 100px;" />
                                @if($qrCode['atcud'])
                                    <div style="font-size: 6px; text-align: center; margin-top: 2px;">
                                        @if(!empty($qrCode['atcud']))ATCUD: {{ $qrCode['atcud'] }}@endif
                                    </div>
                                @endif
                            @else
                                <div style="width: 100px; height: 100px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #999;">
                                    QR Code
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="doc-header">
                <div class="doc-title" style="text-align: left;">Nota de Crédito n.º {{ $creditNote->credit_note_number }}</div>
                @if($creditNote->invoice)
                <div style="font-size: 9px; color: #666; margin-top: 5px; text-align: left;">
                    Referente à Fatura: {{ $creditNote->invoice->invoice_number }} ({{ $creditNote->invoice->invoice_date->format('d/m/Y') }})
                </div>
                @endif
            </div>

            <table class="doc-info-table">
                <thead>
                    <tr>
                        <th>Moeda</th>
                        <th>Data De Emissão</th>
                        <th>Hora De Emissão</th>
                        <th>Motivo</th>
                        <th>Operador</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>AOA</td>
                        <td>{{ $creditNote->issue_date->format('d/m/Y') }}</td>
                        <td>{{ $creditNote->created_at->format('H:i') }}</td>
                        <td>{{ $creditNote->reason_text ?? $creditNote->reason_expression }} — {{ $creditNote->reason_label }}</td>
                        <td>{{ $creditNote->creator->name ?? 'Sistema' }}</td>
                        <td>{{ $creditNote->status_label }}</td>
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
                    
                    @foreach($creditNote->items as $item)
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
                            @include("pdf.invoicing.partials.tax-summary", ["doc" => $creditNote])
                        </table>
                    </div>

                    <div class="regime-section">
                        <div class="regime-title">Regime Fiscal</div>
                        <div>{{ method_exists($tenant, "regimeLabel") ? $tenant->regimeLabel() : ($tenant->regime ?? "Regime Geral") }}</div>
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
                        Processado por sistema certificado AGT | Regime: {{ method_exists($tenant, "regimeLabel") ? $tenant->regimeLabel() : ($tenant->regime ?? "Regime Geral") }}
                        <br>
                        <strong>ID Certificado:</strong> {{ \App\Helpers\AGTHelper::softwareValidationNumber() }} — SOS ERP - SOLUÇÕES EMPRESARIAIS
                        @if($creditNote->hash)
                            <br>
                            <strong>HASH e SAFT-AO:</strong> "{{ substr($creditNote->hash, -4) }}"
                        @endif
                    </div>
                </div>

                <div class="right-bottom">
                    <div class="summary-section">
                        <div class="summary-row">
                            <span>Total Ilíquido</span>
                            <span>{{ number_format($creditNote->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Comercial</span>
                            <span>0,00</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Financeiro</span>
                            <span>0,00</span>
                        </div>
                        <div class="summary-row">
                            <span>Incidência IVA</span>
                            <span>{{ number_format($creditNote->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>IVA</span>
                            <span>{{ number_format($creditNote->tax_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Total da NC</span>
                            <span>{{ number_format($creditNote->total, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Retenção</span>
                            <span>0,00</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total a Creditar</span>
                            <span>{{ number_format($creditNote->total, 2, ',', '.') }}</span>
                        </div>
                        
                        <div class="total-extenso">
                            {{ numberToWords($creditNote->total, 'AOA') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="agt-description">
                Esta proforma foi processada pelo Sistema de Facturação | Regime: {{ method_exists($tenant, "regimeLabel") ? $tenant->regimeLabel() : ($tenant->regime ?? "Regime Geral") }}
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
    