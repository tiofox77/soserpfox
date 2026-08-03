<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #ea580c; padding-bottom: 10px; margin-bottom: 14px; }
        .company { font-size: 14px; font-weight: bold; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 20px; margin: 0; color: #ea580c; }
        .doc-title .num { font-size: 13px; font-weight: bold; }
        .meta { width: 100%; margin-bottom: 14px; }
        .meta td { padding: 3px 6px; vertical-align: top; font-size: 11px; }
        .meta .label { color: #6b7280; width: 130px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        table.items th { background: #fff7ed; color: #9a3412; text-align: left; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; margin-top: 10px; }
        .footer { margin-top: 30px; font-size: 10px; color: #6b7280; text-align: center; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { width: 50%; text-align: center; padding-top: 24px; border-top: 1px solid #9ca3af; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <div class="company">{{ $company['name'] }}</div>
            @if($company['nif'])<div>NIF: {{ $company['nif'] }}</div>@endif
            @if($company['address'])<div>{{ $company['address'] }}</div>@endif
        </div>
        <div class="doc-title">
            <h1>{{ $guide->typeLabel() }}</h1>
            <div class="num">{{ $guide->guide_number }}</div>
            <div>{{ optional($guide->issue_date)->format('d/m/Y') }}</div>
        </div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Cliente:</td><td><strong>{{ $guide->client->name ?? '—' }}</strong></td>
            <td class="label">Fatura de origem:</td><td>{{ $guide->invoice->invoice_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Viatura (matrícula):</td><td>{{ $guide->vehicle_plate ?: '—' }}</td>
            <td class="label">Data/hora de carga:</td><td>{{ $guide->loading_datetime ? $guide->loading_datetime->format('d/m/Y H:i') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Motorista:</td><td>{{ $guide->driver_name ?: '—' }} @if($guide->driver_document)({{ $guide->driver_document }})@endif</td>
            <td class="label">Estado:</td><td>{{ $guide->status === 'cancelled' ? 'Anulada' : 'Emitida' }}</td>
        </tr>
        <tr>
            <td class="label">Local de carga:</td><td>{{ $guide->load_address ?: '—' }}</td>
            <td class="label">Local de descarga:</td><td>{{ $guide->unload_address ?: '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th>Descrição</th>
                <th class="num" style="width:120px">Quantidade</th>
                <th style="width:80px">Unidade</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guide->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->description ?: $item->product_name }}</td>
                    <td class="num">{{ number_format($item->quantity, 3, ',', '.') }}</td>
                    <td>{{ $item->unit }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#9ca3af">Sem itens.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($guide->notes)
        <div class="box"><strong>Observações:</strong> {{ $guide->notes }}</div>
    @endif

    <table class="sign">
        <tr>
            <td>O Expedidor</td>
            <td>O Motorista / Transportador</td>
        </tr>
    </table>

    @if($guide->hash)
        @php
            $h = $guide->hash ?? $guide->saft_hash;
            // Resumo do hash conforme SAFT (1º, 11º, 21º, 31º caracteres)
            $hashSummary = strlen($h) >= 31 ? ($h[0] . $h[10] . $h[20] . $h[30]) : substr($h, 0, 4);
        @endphp
        <div class="box" style="margin-top:18px; font-size:10px;">
            @if($guide->atcud)<div><strong>ATCUD:</strong> {{ $guide->atcud }}</div>@endif
            <div><strong>{{ $hashSummary }}</strong> — Documento processado por programa certificado</div>
        </div>
    @endif

    <div class="footer">Documento de transporte de mercadorias — {{ $company['name'] }} · Gerado em {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
