<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Etiqueta TAG') }} — {{ $workOrder->order_number }}</title>
    {{--
        A ETIQUETA DA CHAVE (OF-20).

        Uma etiqueta = 62 × 40 mm (impressora de etiquetas) ou oito numa A4. O QR
        abre a folha de obra no telemóvel de quem a lê. O TAG# é o da viatura;
        sem ele fica a caixa em branco para escrever à mão.
    --}}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color: #0f172a; background: #e2e8f0; }
        .barra { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center; padding: 12px 16px; background: #0f172a; color: #fff; font-size: 14px; }
        .barra a, .barra button { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.08); color: #fff; font-weight: 600; text-decoration: none; cursor: pointer; font-size: 13px; transition: transform .15s, background .15s; }
        .barra a:hover, .barra button:hover { background: rgba(255,255,255,.18); transform: translateY(-1px); }
        .barra .activo { background: #f59e0b; border-color: #f59e0b; color: #1c1917; }
        .barra .imprimir { background: #4f46e5; border-color: #4f46e5; }
        .folha { display: flex; flex-wrap: wrap; gap: 6mm; justify-content: center; padding: 10mm; }
        .etiqueta { width: 62mm; height: 40mm; background: #fff; border: 0.3mm dashed #94a3b8; border-radius: 2.5mm; padding: 2mm; display: grid; grid-template-columns: 1fr 19mm; gap: 1.5mm; overflow: hidden; page-break-inside: avoid; box-shadow: 0 4px 16px rgba(15,23,42,.12); }
        .esquerda { display: flex; flex-direction: column; justify-content: space-between; min-width: 0; }
        .empresa { font-size: 5.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tag { display: flex; align-items: baseline; gap: 1.2mm; }
        .tag span { font-size: 6pt; font-weight: 800; color: #b45309; }
        .tag b { display: inline-block; min-width: 18mm; padding: 0.3mm 1.6mm; border-radius: 1.2mm; background: #0f172a; color: #fff; font-size: 17pt; line-height: 1.05; font-weight: 900; letter-spacing: .02em; }
        .tag b.vazio { background: #fff; border: 0.4mm solid #0f172a; height: 7mm; }
        .chapa { display: inline-flex; align-items: center; gap: 0.8mm; border: 0.35mm solid #0f172a; border-radius: 1mm; padding: 0.2mm 1.2mm; font-family: 'Arial Narrow', Arial, sans-serif; font-weight: 800; font-size: 8.5pt; letter-spacing: .03em; width: max-content; max-width: 100%; }
        .chapa i { display: inline-block; width: 2.4mm; height: 1.6mm; background: linear-gradient(#cc092f 50%, #000 50%); border-radius: .2mm; }
        .linha { font-size: 6pt; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .linha strong { font-family: ui-monospace, Menlo, Consolas, monospace; color: #0f172a; }
        .direita { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .6mm; }
        .direita svg { width: 19mm; height: 19mm; display: block; }
        .direita small { font-size: 4.5pt; color: #64748b; text-align: center; line-height: 1.1; }
        .furo { width: 3.2mm; height: 3.2mm; border-radius: 50%; border: 0.3mm solid #cbd5e1; margin-bottom: .3mm; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            @if($formato === 'etiqueta')
                @page { size: 62mm 40mm; margin: 0; }
                .folha { padding: 0; gap: 0; display: block; }
                .etiqueta { border: none; border-radius: 0; box-shadow: none; page-break-after: always; }
            @else
                @page { size: A4 portrait; margin: 8mm; }
                .folha { padding: 0; gap: 4mm; justify-content: flex-start; }
                .etiqueta { box-shadow: none; }
            @endif
        }
    </style>
</head>
<body>
    <div class="barra">
        <strong>{{ __('Etiqueta TAG') }} · {{ $workOrder->order_number }}</strong>
        <a href="?formato=etiqueta&copias=1" class="{{ $formato === 'etiqueta' ? 'activo' : '' }}">{{ __('Etiqueta 62 × 40 mm') }}</a>
        <a href="?formato=a4&copias=8" class="{{ $formato === 'a4' ? 'activo' : '' }}">{{ __('Folha A4 (8)') }}</a>
        @if($formato === 'etiqueta')
            <a href="?formato=etiqueta&copias={{ min(8, $copias + 1) }}">+ {{ __('cópia') }}</a>
        @endif
        <button type="button" class="imprimir" onclick="window.print()">{{ __('Imprimir') }}</button>
    </div>

    <div class="folha">
        @for($i = 0; $i < $copias; $i++)
            <div class="etiqueta">
                <div class="esquerda">
                    <div class="empresa">{{ $empresa }}</div>
                    <div class="tag">
                        <span>TAG#</span>
                        @if($workOrder->vehicle?->tag_number)
                            <b>{{ $workOrder->vehicle->tag_number }}</b>
                        @else
                            <b class="vazio"></b>
                        @endif
                    </div>
                    <div class="chapa"><i></i>{{ $workOrder->vehicle?->plate ?? '—' }}</div>
                    <div class="linha">{{ trim(($workOrder->vehicle?->brand ?? '') . ' ' . ($workOrder->vehicle?->model ?? '')) }}@if($workOrder->vehicle?->owner_name) · {{ \Illuminate\Support\Str::before($workOrder->vehicle->owner_name, ' ') }}@endif</div>
                    <div class="linha"><strong>{{ $workOrder->order_number }}</strong>@if($workOrder->vehicle?->work_order_ref) · WO# <strong>{{ $workOrder->vehicle->work_order_ref }}</strong>@endif · {{ $workOrder->received_at?->format('d/m') }}</div>
                </div>
                <div class="direita">
                    <div class="furo" aria-hidden="true"></div>
                    {!! $qr !!}
                    <small>{{ __('Leia para abrir a folha de obra') }}</small>
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
