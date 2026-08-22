<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#e2e8f0;color:#000;font:14px Arial,sans-serif}.ticket{width:80mm;margin:18px auto;background:#fff;padding:4mm;box-shadow:0 12px 35px #0f172a33}.head{display:flex;justify-content:space-between;gap:3mm;border-bottom:2px dashed #9ca3af;padding-bottom:3mm}.company{min-width:0;flex:1}.logo{display:block;width:42mm;height:13mm;object-fit:contain;object-position:left center;margin-bottom:1.5mm}.qr{width:25mm;text-align:center}.qr img{width:25mm;height:25mm}.small{font-size:11px}.tiny{font-size:9px}.center{text-align:center}.bold{font-weight:700}.row{display:flex;justify-content:space-between;gap:3mm;padding:.7mm 0}.section{margin-top:3mm;padding-bottom:3mm;border-bottom:1px dashed #9ca3af}.title{margin:3mm 0;text-align:center;font-size:14px;font-weight:800}.items{width:100%;border-collapse:collapse;font-size:10px}.items th,.items td{padding:1.5mm .5mm;border-bottom:1px dotted #cbd5e1}.items th{text-align:left}.items .num{text-align:right;white-space:nowrap}.total{margin-top:2mm;border-top:2px solid #000;padding-top:2mm;font-size:16px;font-weight:800}.footer{margin-top:4mm;text-align:center;font-size:9px;line-height:1.45}.actions{width:80mm;margin:0 auto 18px;display:flex;gap:8px}.actions button{flex:1;border:0;border-radius:10px;padding:12px;background:#059669;color:#fff;font-weight:800;cursor:pointer}.actions button:last-child{background:#334155}@media print{html,body{width:80mm;background:#fff}.ticket{width:80mm;margin:0;box-shadow:none;padding:2mm}.actions{display:none}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
@php
    try { $ticketQR = getAGTQRData($invoice, 100); }
    catch (\Throwable $e) { $ticketQR = ['image' => null, 'atcud' => null]; }
    $logo = $tenant->logo
        ? (str_starts_with($tenant->logo, 'http') ? $tenant->logo : asset('storage/'.ltrim($tenant->logo, '/')))
        : app_logo();
    $formas = $invoice->payments;
@endphp
<main class="ticket" id="ticket">
    <header class="head">
        <div class="company">
            @if($logo)<img class="logo" src="{{ $logo }}" alt="{{ $tenant->nomeParaDocumentos() }}">@endif
            <div class="bold">{{ mb_strtoupper($tenant->nomeParaDocumentos()) }}</div>
            <div class="small">NIF: {{ $tenant->nif }}</div>
            @if($tenant->address)<div class="small">{{ $tenant->address }}</div>@endif
            @if($tenant->phone)<div class="small">Tel: {{ $tenant->phone }}</div>@endif
        </div>
        @if(!empty($ticketQR['image']))
            <div class="qr"><img src="{{ $ticketQR['image'] }}" alt="QR AGT">@if(!empty($ticketQR['atcud']))<div class="tiny">ATCUD: {{ $ticketQR['atcud'] }}</div>@endif</div>
        @endif
    </header>

    <h1 class="title">{{ $invoice->invoice_type === 'FR' ? 'FACTURA RECIBO' : 'FACTURA' }}</h1>
    <section class="section small">
        <div class="row"><b>DOCUMENTO:</b><span>{{ $invoice->invoice_number }}</span></div>
        <div class="row"><b>DATA:</b><span>{{ ($invoice->system_entry_date ?? $invoice->invoice_date)->format('d/m/Y H:i') }}</span></div>
        <div class="row"><b>OPERADOR:</b><span>{{ $invoice->creator?->name ?? auth()->user()->name }}</span></div>
    </section>
    <section class="section small">
        <div class="bold">CLIENTE</div>
        <div>{{ $invoice->client?->name ?? 'Consumidor Final' }}</div>
        <div>NIF: {{ $invoice->client?->nif ?? '999999999' }}</div>
    </section>

    <section class="section">
        <table class="items">
            <thead><tr><th>ITEM</th><th class="num">QTD</th><th class="num">PREÇO</th><th class="num">TOTAL</th></tr></thead>
            <tbody>
            @foreach($invoice->items as $item)
                <tr><td>{{ $item->product_name }}</td><td class="num">{{ number_format($item->quantity, 2, ',', '.') }}</td><td class="num">{{ number_format($item->unit_price, 2, ',', '.') }}</td><td class="num">{{ number_format($item->subtotal, 2, ',', '.') }}</td></tr>
                <tr><td colspan="4" class="tiny">@if((float)$item->tax_rate > 0)IVA {{ number_format($item->tax_rate, 0) }}%: {{ number_format($item->tax_amount, 2, ',', '.') }} Kz @else Isento de IVA @endif</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="section small">
        <div class="bold">RESUMO FISCAL</div>
        <div class="row"><span>Base tributável</span><span>{{ number_format($invoice->subtotal, 2, ',', '.') }} Kz</span></div>
        @if((float)$invoice->discount_amount > 0)<div class="row"><span>Desconto</span><span>-{{ number_format($invoice->discount_amount, 2, ',', '.') }} Kz</span></div>@endif
        <div class="row"><span>Total IVA</span><span>{{ number_format($invoice->tax_amount, 2, ',', '.') }} Kz</span></div>
        <div class="row total"><span>TOTAL</span><span>{{ number_format($invoice->total, 2, ',', '.') }} Kz</span></div>
    </section>

    <section class="section small">
        @if($formas->count() > 1)
            <div class="bold">FORMAS DE PAGAMENTO</div>
            @foreach($formas as $forma)<div class="row"><span>{{ mb_strtoupper($forma->payment_method) }}</span><span>{{ number_format($forma->amount, 2, ',', '.') }} Kz</span></div>@endforeach
        @else
            <div class="row"><span>Forma de pagamento</span><b>{{ mb_strtoupper($formas->first()?->payment_method ?? $invoice->payment_method ?? 'Dinheiro') }}</b></div>
        @endif
        <div class="row"><span>Valor recebido</span><span>{{ number_format($invoice->paid_amount ?: $invoice->total, 2, ',', '.') }} Kz</span></div>
    </section>

    <footer class="footer">
        <div class="bold">Processado por programa validado</div>
        <div class="bold">Certificado AGT Nº {{ \App\Helpers\AGTHelper::softwareValidationNumber() }}</div>
        <div>Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</div>
        @if($invoice->saft_hash)<div style="margin-top:2mm;word-break:break-all">HASH: {{ substr($invoice->saft_hash, 0, 4) }}-{{ $invoice->hash_control ?? '1' }}</div>@endif
        <div class="bold" style="margin-top:2mm">Obrigado pela sua preferência!</div>
    </footer>
</main>
<div class="actions"><button onclick="window.print()">Imprimir ticket</button><button onclick="window.close()">Fechar</button></div>
@if($autoPrint)<script>window.addEventListener('load',()=>window.print())</script>@endif
</body>
</html>
