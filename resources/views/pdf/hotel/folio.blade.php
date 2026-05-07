<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Folio - {{ $reservation->reservation_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; color: #1f2937; font-size: 13px; }
        .page { max-width: 820px; margin: 20px auto; background: #fff; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px; }
        .toolbar { max-width: 820px; margin: 20px auto 0; text-align: right; }
        .btn { background: #059669; color: #fff; border: 0; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #059669; padding-bottom: 20px; margin-bottom: 25px; }
        .header h1 { margin: 0; color: #059669; font-size: 26px; }
        .header .sub { color: #6b7280; font-size: 12px; margin-top: 4px; }
        .hotel-info { text-align: right; font-size: 12px; color: #4b5563; }
        .hotel-info .name { font-weight: 800; font-size: 15px; color: #111827; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .info-box { background: #f9fafb; border-left: 4px solid #059669; padding: 12px; border-radius: 5px; }
        .info-box h4 { margin: 0 0 6px; text-transform: uppercase; font-size: 10px; color: #6b7280; letter-spacing: 1px; }
        .info-box p { margin: 0; font-size: 13px; font-weight: 600; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th { background: #059669; color: #fff; padding: 10px 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        table td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        table tbody tr:nth-child(even) { background: #f9fafb; }
        .category-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; background: #ddd6fe; color: #5b21b6; }
        .totals-box { background: #ecfdf5; border: 2px solid #10b981; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .totals-box table { margin: 0; }
        .totals-box td { border: 0; padding: 6px 0; }
        .totals-box .final { font-size: 18px; font-weight: 800; color: #059669; border-top: 2px solid #10b981; padding-top: 10px !important; }
        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; padding: 20px; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
    </div>
    <div class="page">
        <div class="header">
            <div>
                <h1>📋 CONTA DO HÓSPEDE (FOLIO)</h1>
                <div class="sub">{{ $reservation->reservation_number }} · Emitido {{ now()->format('d/m/Y H:i') }}</div>
            </div>
            <div class="hotel-info">
                <div class="name">{{ $settings->hotel_name ?? 'Hotel' }}</div>
                @if($settings->hotel_address) <div>{{ $settings->hotel_address }}</div> @endif
                @if($settings->hotel_phone) <div>{{ $settings->hotel_phone }}</div> @endif
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h4>Hóspede</h4>
                <p>{{ $reservation->guest?->name ?? $reservation->client?->name ?? '—' }}</p>
            </div>
            <div class="info-box">
                <h4>Quarto</h4>
                <p>{{ $reservation->room?->room_number ? 'Nº ' . $reservation->room->room_number : '—' }} · {{ $reservation->roomType?->name ?? '' }}</p>
            </div>
            <div class="info-box">
                <h4>Estadia</h4>
                <p>{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }} ({{ $reservation->nights }} noites)</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Categoria</th>
                    <th>Descrição</th>
                    <th style="text-align: center;">Qtd</th>
                    <th style="text-align: right;">Preço Unit.</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                {{-- Hospedagem --}}
                <tr>
                    <td>{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}</td>
                    <td><span class="category-badge" style="background: #dbeafe; color: #1e40af;">Hospedagem</span></td>
                    <td>{{ $reservation->roomType?->name ?? 'Quarto' }} × {{ $reservation->nights }} noites</td>
                    <td style="text-align: center;">{{ $reservation->nights }}</td>
                    <td style="text-align: right;">{{ number_format($reservation->room_rate, 2, ',', '.') }}</td>
                    <td style="text-align: right; font-weight: 700;">{{ number_format($reservation->subtotal, 2, ',', '.') }} Kz</td>
                </tr>
                {{-- Extras / Consumos --}}
                @foreach($reservation->items as $item)
                    <tr>
                        <td>{{ $item->charged_at?->format('d/m/Y') ?? $item->date?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="category-badge">{{ $item->category_label ?? $item->type_label }}</span></td>
                        <td>{{ $item->description }}</td>
                        <td style="text-align: center;">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                        <td style="text-align: right;">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td style="text-align: right; font-weight: 700;">{{ number_format($item->total, 2, ',', '.') }} Kz</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-box">
            <table>
                <tr><td>Hospedagem</td><td style="text-align: right;">{{ number_format($reservation->subtotal, 2, ',', '.') }} Kz</td></tr>
                <tr><td>Consumos / Extras</td><td style="text-align: right;">{{ number_format($reservation->extras_total, 2, ',', '.') }} Kz</td></tr>
                @if($reservation->discount > 0)
                    <tr><td>Desconto</td><td style="text-align: right; color: #dc2626;">-{{ number_format($reservation->discount, 2, ',', '.') }} Kz</td></tr>
                @endif
                @if($reservation->tax > 0)
                    <tr><td>IVA (14%)</td><td style="text-align: right;">{{ number_format($reservation->tax, 2, ',', '.') }} Kz</td></tr>
                @endif
                <tr class="final"><td>TOTAL</td><td style="text-align: right;">{{ number_format($reservation->total, 2, ',', '.') }} Kz</td></tr>
                <tr><td>Pago</td><td style="text-align: right; color: #059669;">{{ number_format($reservation->paid_amount, 2, ',', '.') }} Kz</td></tr>
                <tr><td style="font-weight: 700;">Saldo Devido</td><td style="text-align: right; font-weight: 700; color: {{ $reservation->balance_due > 0 ? '#dc2626' : '#059669' }};">{{ number_format(max(0, $reservation->balance_due), 2, ',', '.') }} Kz</td></tr>
            </table>
        </div>

        <p style="text-align: center; margin-top: 30px; color: #9ca3af; font-size: 11px;">
            Documento emitido electronicamente · {{ $settings->hotel_name ?? 'Hotel' }} · Obrigado pela sua estadia!
        </p>
    </div>
</body>
</html>
