<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Voucher Reserva - {{ $reservation->reservation_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; color: #1f2937; }
        .page { max-width: 820px; margin: 20px auto; background: #fff; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px; }
        .toolbar { max-width: 820px; margin: 20px auto 0; text-align: right; }
        .btn { background: #6d28d9; color: #fff; border: 0; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #5b21b6; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #6d28d9; padding-bottom: 20px; margin-bottom: 25px; }
        .header h1 { margin: 0; color: #6d28d9; font-size: 28px; }
        .header .sub { color: #6b7280; font-size: 14px; margin-top: 4px; }
        .hotel-info { text-align: right; font-size: 13px; color: #4b5563; }
        .hotel-info .name { font-weight: 800; font-size: 16px; color: #111827; }
        .banner { background: linear-gradient(90deg, #6d28d9, #4f46e5); color: #fff; padding: 25px; border-radius: 10px; margin-bottom: 25px; text-align: center; }
        .banner h2 { margin: 0 0 6px; font-size: 22px; }
        .banner .code { font-family: monospace; font-size: 18px; letter-spacing: 3px; background: rgba(255,255,255,0.2); display: inline-block; padding: 6px 14px; border-radius: 6px; margin-top: 8px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .card { background: #f9fafb; border-left: 4px solid #6d28d9; padding: 18px; border-radius: 6px; }
        .card h3 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: #6b7280; letter-spacing: 1px; }
        .card .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .card .row .label { color: #6b7280; }
        .card .row .value { font-weight: 600; color: #111827; }
        .qr-block { text-align: center; padding: 20px; border: 2px dashed #c4b5fd; border-radius: 10px; margin-bottom: 25px; background: #faf5ff; }
        .qr-block .qr { display: inline-block; background: #fff; padding: 15px; border-radius: 8px; }
        .qr-block p { font-size: 13px; color: #6b7280; margin: 10px 0 0; }
        .totals { background: linear-gradient(90deg, #059669, #10b981); color: #fff; padding: 20px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .totals .label { font-size: 13px; opacity: 0.9; text-transform: uppercase; }
        .totals .amount { font-size: 28px; font-weight: 800; }
        .policies { font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 20px; line-height: 1.6; }
        .policies strong { color: #374151; }
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
                <h1>🏨 VOUCHER DE RESERVA</h1>
                <div class="sub">{{ $reservation->reservation_number }} · Emitido em {{ now()->format('d/m/Y H:i') }}</div>
            </div>
            <div class="hotel-info">
                <div class="name">{{ $settings->hotel_name ?? 'Hotel' }}</div>
                @if($settings->hotel_address) <div>{{ $settings->hotel_address }}</div> @endif
                @if($settings->hotel_city) <div>{{ $settings->hotel_city }}{{ $settings->hotel_country ? ', ' . $settings->hotel_country : '' }}</div> @endif
                @if($settings->hotel_phone) <div>{{ $settings->hotel_phone }}</div> @endif
                @if($settings->hotel_email) <div>{{ $settings->hotel_email }}</div> @endif
            </div>
        </div>

        <div class="banner">
            <h2>Reserva Confirmada</h2>
            <div>Apresente este voucher à chegada</div>
            <div class="code">{{ $reservation->confirmation_code }}</div>
        </div>

        <div class="grid">
            <div class="card">
                <h3>Hóspede</h3>
                <div class="row"><span class="label">Nome:</span><span class="value">{{ $reservation->guest?->name ?? $reservation->client?->name ?? '—' }}</span></div>
                @if($reservation->guest?->document_number)
                    <div class="row"><span class="label">Documento:</span><span class="value">{{ $reservation->guest->document_number }}</span></div>
                @endif
                @if($reservation->guest?->email)
                    <div class="row"><span class="label">Email:</span><span class="value">{{ $reservation->guest->email }}</span></div>
                @endif
                @if($reservation->guest?->phone)
                    <div class="row"><span class="label">Telefone:</span><span class="value">{{ $reservation->guest->phone }}</span></div>
                @endif
            </div>
            <div class="card">
                <h3>Estadia</h3>
                <div class="row"><span class="label">Check-in:</span><span class="value">{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}</span></div>
                <div class="row"><span class="label">Check-out:</span><span class="value">{{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}</span></div>
                <div class="row"><span class="label">Noites:</span><span class="value">{{ $reservation->nights }}</span></div>
                <div class="row"><span class="label">Adultos:</span><span class="value">{{ $reservation->adults }}</span></div>
                @if($reservation->children > 0)
                    <div class="row"><span class="label">Crianças:</span><span class="value">{{ $reservation->children }}</span></div>
                @endif
                <div class="row"><span class="label">Tipo Quarto:</span><span class="value">{{ $reservation->roomType?->name ?? '—' }}</span></div>
                @if($reservation->room)
                    <div class="row"><span class="label">Quarto:</span><span class="value">Nº {{ $reservation->room->room_number }}</span></div>
                @endif
            </div>
        </div>

        <div class="qr-block">
            <div class="qr">{!! $qrSvg !!}</div>
            <p><strong>Check-in Expresso:</strong> escaneie o QR Code para efectuar check-in</p>
        </div>

        <div class="totals">
            <div>
                <div class="label">Total da Estadia</div>
                <div style="font-size: 12px; opacity: 0.85;">{{ $reservation->nights }} noites · IVA incluído</div>
            </div>
            <div class="amount">{{ number_format($reservation->total, 2, ',', '.') }} Kz</div>
        </div>

        @if($reservation->special_requests)
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong>📝 Pedidos Especiais:</strong><br>
                {{ $reservation->special_requests }}
            </div>
        @endif

        <div class="policies">
            <p><strong>Horário Check-in:</strong> a partir das {{ $settings->default_check_in_time ?? '14:00' }} · <strong>Check-out:</strong> até às {{ $settings->default_check_out_time ?? '12:00' }}</p>
            @if($settings->booking_policies)
                <p><strong>Política de Reserva:</strong> {{ $settings->booking_policies }}</p>
            @endif
            @if($settings->cancellation_policies)
                <p><strong>Política de Cancelamento:</strong> {{ $settings->cancellation_policies }}</p>
            @endif
            <p style="text-align: center; margin-top: 20px; color: #9ca3af;">Documento emitido electronicamente · {{ $settings->hotel_name ?? 'Hotel' }}</p>
        </div>
    </div>
</body>
</html>
