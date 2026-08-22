
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento {{ $quote->quote_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
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
            max-height: 297mm;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            padding: 10mm 12mm;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .company-info {
            flex: 0 0 60%;
        }

        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
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
            font-size: 7.5px;
            line-height: 1.3;
            margin-top: 4px;
        }

        .right-section {
            flex: 0 0 35%;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            position: relative;
            margin-top: 0;
        }

        .client-info {
            text-align: left;
            width: 100%;
            margin-bottom: 8px;
            padding: 6px 8px;
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

        .doc-header {
            text-align: center;
            margin: 6px 0;
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 4px;
        }

        .doc-title {
            font-weight: bold;
            font-size: 12px;
            color: #2c5aa0;
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
            /* A descrição de um serviço pode ser longa: parte a palavra em vez de
               esticar a coluna e desalinhar o resto da tabela. */
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            max-width: 220px;
        }

        .items-table .discriminacao .item-nome {
            font-weight: bold;
        }

        .items-table .discriminacao .item-descricao {
            display: block;
            margin-top: 1px;
            font-size: 7px;
            line-height: 1.25;
            color: #666;
            white-space: pre-line; /* respeita as quebras de linha que o utilizador escreveu */
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
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .left-bottom {
            flex: 1;
            margin-right: 15px;
        }

        .tax-section {
            margin-bottom: 6px;
        }

        .tax-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 3px;
            color: #2c5aa0;
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
            color: #2c5aa0;
        }

        .bank-section {
            margin-bottom: 4px;
        }

        .bank-title {
            font-weight: bold;
            font-size: 8px;
            margin-bottom: 3px;
            color: #2c5aa0;
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
            flex: 0 0 200px;
        }

        .summary-section {
            border: 2px solid #2c5aa0;
            padding: 6px 8px;
            background-color: #f9f9f9;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
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

        .doc-note {
            font-size: 8px;
            text-align: center;
            margin-top: 10px;
            color: #333;
            font-weight: bold;
        }

        .notes-block {
            font-size: 8px;
            margin-top: 8px;
            padding: 6px 8px;
            background-color: #f8f9fa;
            border-left: 3px solid #2c5aa0;
            white-space: pre-line;
        }

        .notes-block .notes-title {
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 2px;
        }

        .page-footer {
            text-align: center;
            font-size: 7px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
            margin-top: 6px;
            color: #666;
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
    @include("pdf.invoicing.partials.estilo-dompdf")
</head>
<body>
    <div class="page-wrapper">
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
                    <div class="client-info">
                        <div class="client-label">Exmo.(s) Sr.(s)</div>
                        <div class="client-name">{{ $quote->client->name }}</div>
                        <div class="client-nif">NIF: {{ $quote->client->nif ?? 'Consumidor Final' }}</div>
                        <div class="doc-type">Orçamento</div>
                    </div>
                </div>
            </div>

            <div class="doc-header">
                <div class="doc-title" style="text-align: left;">Orçamento n.º {{ $quote->quote_number }}</div>
            </div>

            <table class="doc-info-table">
                <thead>
                    <tr>
                        <th>Moeda</th>
                        <th>Data De Emissão</th>
                        <th>Hora De Emissão</th>
                        <th>Válido Até</th>
                        <th>Operador</th>
                        <th>Referência</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>AOA</td>
                        <td>{{ $quote->quote_date->format('d/m/Y') }}</td>
                        <td>{{ $quote->created_at->format('H:i') }}</td>
                        <td>{{ $quote->valid_until ? $quote->valid_until->format('d/m/Y') : 'N/A' }}</td>
                        <td>{{ $quote->creator->name ?? 'Sistema' }}</td>
                        <td>{{ $quote->quote_number }}</td>
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
                    @foreach($quote->items as $item)
                    <tr>
                        <td>{{ $item->product->code ?? '-' }}</td>
                        <td class="discriminacao">
                            <span class="item-nome">{{ $item->product_name }}</span>
                            @if($item->description)
                                <span class="item-descricao">{{ $item->description }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($item->quantity, 0, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }}</td>
                        <td>{{ number_format($item->discount_percent ?? 0, 0) }}%</td>
                        <td>{{ number_format($item->tax_rate ?? 0, 0) }}%</td>
                        <td class="currency">{{ number_format($item->tax_amount, 2, ',', '.') }}</td>
                        <td class="currency">{{ number_format($item->total, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($quote->notes || $quote->terms)
            <div class="notes-block">
                @if($quote->notes)
                    <div class="notes-title">Notas</div>
                    <div>{{ $quote->notes }}</div>
                @endif
                @if($quote->terms)
                    <div class="notes-title" style="margin-top:4px;">Termos e Condições</div>
                    <div>{{ $quote->terms }}</div>
                @endif
            </div>
            @endif
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
                            @include("pdf.invoicing.partials.tax-summary", ["doc" => $quote])
                        </table>
                    </div>

                    <div class="regime-section">
                        <div class="regime-title">Regime Fiscal</div>
                        <div>{{ method_exists($tenant, "regimeLabel") ? $tenant->regimeLabel() : ($tenant->regime ?? "Regime Geral") }}</div>
                    </div>

                    @if(isset($bankAccounts) && $bankAccounts && $bankAccounts->count() > 0)
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
                        Documento comercial — NÃO é documento fiscal e não foi comunicado à AGT.
                    </div>
                </div>

                <div class="right-bottom">
                    <div class="summary-section">
                        <div class="summary-row">
                            <span>Total Ilíquido</span>
                            <span>{{ number_format($quote->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Comercial</span>
                            <span>{{ number_format($quote->discount_commercial ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Desc. Financeiro</span>
                            <span>{{ number_format($quote->discount_financial ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Incidência IVA</span>
                            <span>{{ number_format($quote->subtotal, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>IVA</span>
                            <span>{{ number_format($quote->tax_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Total do Orçamento</span>
                            <span>{{ number_format($quote->total, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Retenção</span>
                            <span>{{ number_format($quote->irt_amount ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total a Pagar</span>
                            <span>{{ number_format($quote->total - ($quote->irt_amount ?? 0), 2, ',', '.') }}</span>
                        </div>

                        <div class="total-extenso">
                            {{ numberToWords($quote->total - ($quote->irt_amount ?? 0), 'AOA') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="doc-note">
                Este orçamento é uma proposta comercial. Não substitui factura e não tem valor fiscal.
            </div>

            <div class="page-footer">
                Documento processado em sistema certificado | Todos os direitos reservados
            </div>
        </div>
    </div>

    {{-- Script para abrir diálogo de impressão automaticamente --}}
    <script>
        if (window.opener || document.referrer.includes('/quotes/create')) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>
