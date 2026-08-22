<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Conta para consulta — {{ $order->order_number }}</title>
    <style>
        *{box-sizing:border-box} body{margin:0;background:#e2e8f0;color:#0f172a;font-family:Arial,sans-serif}.sheet{width:80mm;min-height:120mm;margin:20px auto;background:#fff;padding:7mm 5mm;box-shadow:0 12px 35px #0f172a33}.center{text-align:center}.logo{display:block;max-width:42mm;max-height:18mm;margin:0 auto 3mm;object-fit:contain}.company{font-size:16px;font-weight:800}.muted{color:#475569;font-size:11px}.warning{margin:4mm 0;border:2px dashed #ea580c;padding:3mm;text-align:center;color:#c2410c;font-size:12px;font-weight:900}.row{display:flex;justify-content:space-between;gap:4mm;padding:1.5mm 0}.meta{border-bottom:1px dashed #94a3b8;padding-bottom:3mm;margin-bottom:2mm;font-size:11px}.item{border-bottom:1px dotted #cbd5e1;padding:2mm 0;font-size:11px}.item strong{display:block}.totals{margin-top:3mm;border-top:2px solid #0f172a;padding-top:2mm;font-size:12px}.grand{font-size:17px;font-weight:900}.footer{margin-top:6mm;border-top:1px dashed #94a3b8;padding-top:4mm;text-align:center;font-size:10px}.actions{width:80mm;margin:0 auto 20px;display:flex;gap:8px}.actions button{flex:1;border:0;border-radius:10px;padding:12px;background:#ea580c;color:white;font-weight:800;cursor:pointer}.actions button:last-child{background:#334155}@media print{body{background:#fff}.sheet{margin:0;box-shadow:none;width:80mm}.actions{display:none}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
<div class="sheet">
    @if($tenant->logo)<img class="logo" src="{{ asset('storage/'.$tenant->logo) }}" alt="Logo">@endif
    <div class="center company">{{ $tenant->nomeParaDocumentos() }}</div>
    <div class="center muted">NIF {{ $tenant->nif }}@if($tenant->phone) · {{ $tenant->phone }}@endif</div>
    <div class="warning">CONTA PARA CONSULTA<br><span style="font-size:10px">NÃO É DOCUMENTO FISCAL</span></div>
    <div class="meta">
        <div class="row"><span>Comanda</span><strong>{{ $order->order_number }}</strong></div>
        <div class="row"><span>Mesa/Canal</span><strong>{{ $order->table?->name ?? ucfirst($order->channel) }}</strong></div>
        <div class="row"><span>Data</span><strong>{{ now()->format('d/m/Y H:i') }}</strong></div>
        <div class="row"><span>Operador</span><strong>{{ $order->waiter?->name ?? auth()->user()?->name }}</strong></div>
    </div>
    @foreach($order->items->where('kitchen_status', '!=', 'voided') as $item)
        <div class="item">
            <strong>{{ rtrim(rtrim(number_format($item->quantity, 3, ',', '.'), '0'), ',') }}× {{ $item->product_name }}</strong>
            <div class="row muted"><span>{{ number_format($item->unit_price, 2, ',', '.') }} Kz</span><b>{{ number_format($item->line_total, 2, ',', '.') }} Kz</b></div>
            @if($item->notes)<div class="muted">{{ $item->notes }}</div>@endif
        </div>
    @endforeach
    <div class="totals">
        <div class="row"><span>Subtotal</span><strong>{{ number_format($order->subtotal, 2, ',', '.') }} Kz</strong></div>
        @if((float)$order->discount_total > 0)<div class="row"><span>Desconto</span><strong>- {{ number_format($order->discount_total, 2, ',', '.') }} Kz</strong></div>@endif
        <div class="row"><span>IVA incluído</span><strong>{{ number_format($order->tax_total, 2, ',', '.') }} Kz</strong></div>
        <div class="row grand"><span>TOTAL</span><span>{{ number_format($order->grand_total, 2, ',', '.') }} Kz</span></div>
    </div>
    <div class="footer">Documento exclusivamente para conferência da conta.<br>O documento fiscal será emitido no pagamento.</div>
</div>
<div class="actions"><button onclick="window.print()">Imprimir conta</button><button onclick="window.close()">Fechar</button></div>
@if($autoPrint)<script>window.addEventListener('load',()=>window.print())</script>@endif
</body>
</html>
