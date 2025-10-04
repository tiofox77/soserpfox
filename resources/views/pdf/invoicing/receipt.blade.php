<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Recibo {{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #0066cc; padding-bottom: 20px; }
        .title { font-size: 28px; font-weight: bold; color: #0066cc; margin: 10px 0; }
        .info-box { background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .grid { display: table; width: 100%; margin: 10px 0; }
        .col { display: table-cell; padding: 5px; }
        .label { font-weight: bold; color: #666; }
        .value { color: #000; }
        .amount-box { background: #0066cc; color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
        .amount { font-size: 32px; font-weight: bold; }
        .footer { margin-top: 50px; border-top: 1px solid #ccc; padding-top: 20px; text-align: center; color: #666; }
        .signature { margin-top: 60px; text-align: center; }
        .signature-line { border-top: 1px solid #000; width: 300px; margin: 0 auto; padding-top: 5px; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1 style="margin: 0; font-size: 20px;">{{ $tenant->name ?? 'Empresa' }}</h1>
        <p style="margin: 5px 0; color: #666;">{{ $tenant->address ?? '' }}</p>
        <p style="margin: 5px 0; color: #666;">NIF: {{ $tenant->nif ?? '' }}</p>
        <div class="title">RECIBO</div>
        <p style="font-size: 14px; margin: 5px 0;">Nº {{ $receipt->receipt_number }}</p>
    </div>

    {{-- Tipo de Recibo --}}
    <div style="text-align: center; margin: 20px 0;">
        @if($receipt->type === 'sale')
            <span style="background: #10b981; color: white; padding: 8px 20px; border-radius: 20px; font-weight: bold;">
                RECIBO DE VENDA
            </span>
        @else
            <span style="background: #f97316; color: white; padding: 8px 20px; border-radius: 20px; font-weight: bold;">
                RECIBO DE COMPRA
            </span>
        @endif
    </div>

    {{-- Informações --}}
    <div class="info-box">
        <div class="grid">
            <div class="col">
                <div class="label">
                    {{ $receipt->type === 'sale' ? 'Cliente' : 'Fornecedor' }}:
                </div>
                <div class="value" style="font-size: 14px; font-weight: bold;">
                    {{ $receipt->entity_name }}
                </div>
                @if($receipt->client)
                    <div class="value" style="color: #666;">NIF: {{ $receipt->client->nif }}</div>
                @elseif($receipt->supplier)
                    <div class="value" style="color: #666;">NIF: {{ $receipt->supplier->nif }}</div>
                @endif
            </div>
            <div class="col" style="text-align: right;">
                <div class="label">Data de Pagamento:</div>
                <div class="value" style="font-size: 14px;">
                    {{ $receipt->payment_date->format('d/m/Y') }}
                </div>
            </div>
        </div>

        @if($receipt->invoice)
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
            <div class="label">Fatura Relacionada:</div>
            <div class="value">{{ $receipt->invoice->invoice_number }}</div>
        </div>
        @endif
    </div>

    {{-- Valor Pago --}}
    <div class="amount-box">
        <div style="font-size: 14px; margin-bottom: 10px;">VALOR PAGO</div>
        <div class="amount">{{ number_format($receipt->amount_paid, 2, ',', '.') }} AOA</div>
    </div>

    {{-- Detalhes do Pagamento --}}
    <div class="info-box">
        <div class="grid">
            <div class="col">
                <div class="label">Método de Pagamento:</div>
                <div class="value">{{ $receipt->payment_method_label }}</div>
            </div>
            @if($receipt->reference)
            <div class="col">
                <div class="label">Referência:</div>
                <div class="value">{{ $receipt->reference }}</div>
            </div>
            @endif
        </div>

        @if($receipt->notes)
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
            <div class="label">Observações:</div>
            <div class="value">{{ $receipt->notes }}</div>
        </div>
        @endif
    </div>

    {{-- Assinatura --}}
    <div class="signature">
        <p style="color: #666; margin-bottom: 60px;">Emitido por: {{ $receipt->creator->name ?? 'Sistema' }}</p>
        <div class="signature-line">
            <div>Assinatura e Carimbo</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>Este documento foi gerado eletronicamente e é válido sem assinatura.</p>
        <p style="font-size: 10px; margin-top: 10px;">
            Processado em {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>
</body>
</html>
