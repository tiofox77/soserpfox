<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Boletim de Alojamento - SEF</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; color: #111827; font-size: 13px; }
        .page { max-width: 780px; margin: 20px auto; background: #fff; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 8px; }
        .toolbar { max-width: 780px; margin: 20px auto 0; text-align: right; }
        .btn { background: #1e40af; color: #fff; border: 0; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .header { text-align: center; border-bottom: 3px double #1e40af; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { margin: 0; color: #1e40af; font-size: 22px; letter-spacing: 2px; }
        .header .sub { color: #6b7280; font-size: 12px; margin-top: 4px; }
        .section { margin-bottom: 20px; }
        .section h2 { background: #1e40af; color: #fff; padding: 8px 14px; margin: 0 0 10px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; border-radius: 4px; }
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .field { border-bottom: 1px solid #d1d5db; padding: 8px 0; }
        .field .label { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700; letter-spacing: 0.5px; }
        .field .value { font-size: 14px; color: #111827; margin-top: 2px; min-height: 20px; }
        .field.full { grid-column: 1 / -1; }
        .signature { margin-top: 40px; border-top: 1px solid #9ca3af; padding-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .signature-block { text-align: center; }
        .signature-block .line { border-top: 1px solid #111827; margin-bottom: 6px; padding-top: 30px; }
        .signature-block .label { font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .note { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 14px; font-size: 11px; color: #92400e; margin-top: 20px; }
        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; }
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
            <h1>BOLETIM DE ALOJAMENTO</h1>
            <div class="sub">SEF · Serviço de Migração e Estrangeiros · Registo de Hóspede</div>
            <div class="sub">{{ $settings->hotel_name ?? 'Estabelecimento Hoteleiro' }} · {{ now()->format('d/m/Y') }}</div>
        </div>

        <div class="section">
            <h2>1. Identificação do Hóspede</h2>
            <div class="field-grid">
                <div class="field full"><div class="label">Nome Completo</div><div class="value">{{ $reservation->guest?->name ?? $reservation->client?->name ?? '' }}</div></div>
                <div class="field"><div class="label">Tipo Documento</div><div class="value">{{ $reservation->guest?->document_type_label ?? '' }}</div></div>
                <div class="field"><div class="label">Nº Documento</div><div class="value">{{ $reservation->guest?->document_number ?? '' }}</div></div>
                <div class="field"><div class="label">Nacionalidade</div><div class="value">{{ $reservation->guest?->nationality ?? '' }}</div></div>
                <div class="field"><div class="label">Data de Nascimento</div><div class="value">{{ $reservation->guest?->birth_date?->format('d/m/Y') ?? '' }}</div></div>
                <div class="field"><div class="label">Género</div><div class="value">{{ ucfirst($reservation->guest?->gender ?? '') }}</div></div>
                <div class="field"><div class="label">País de Residência</div><div class="value">{{ $reservation->guest?->country ?? '' }}</div></div>
            </div>
        </div>

        <div class="section">
            <h2>2. Morada de Residência</h2>
            <div class="field-grid">
                <div class="field full"><div class="label">Endereço</div><div class="value">{{ $reservation->guest?->address ?? '' }}</div></div>
                <div class="field"><div class="label">Cidade</div><div class="value">{{ $reservation->guest?->city ?? '' }}</div></div>
                <div class="field"><div class="label">País</div><div class="value">{{ $reservation->guest?->country ?? '' }}</div></div>
            </div>
        </div>

        <div class="section">
            <h2>3. Dados da Estadia</h2>
            <div class="field-grid">
                <div class="field"><div class="label">Reserva Nº</div><div class="value">{{ $reservation->reservation_number }}</div></div>
                <div class="field"><div class="label">Quarto</div><div class="value">{{ $reservation->room?->room_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Data Check-in</div><div class="value">{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}</div></div>
                <div class="field"><div class="label">Data Check-out</div><div class="value">{{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}</div></div>
                <div class="field"><div class="label">Nº Noites</div><div class="value">{{ $reservation->nights }}</div></div>
                <div class="field"><div class="label">Nº Pessoas</div><div class="value">{{ $reservation->adults + $reservation->children }} ({{ $reservation->adults }} adultos{{ $reservation->children > 0 ? ', ' . $reservation->children . ' crianças' : '' }})</div></div>
            </div>
        </div>

        <div class="section">
            <h2>4. Estabelecimento</h2>
            <div class="field-grid">
                <div class="field full"><div class="label">Nome</div><div class="value">{{ $settings->hotel_name ?? '' }}</div></div>
                <div class="field full"><div class="label">Morada</div><div class="value">{{ $settings->hotel_address ?? '' }}{{ $settings->hotel_city ? ', ' . $settings->hotel_city : '' }}</div></div>
                <div class="field"><div class="label">Telefone</div><div class="value">{{ $settings->hotel_phone ?? '' }}</div></div>
                <div class="field"><div class="label">Email</div><div class="value">{{ $settings->hotel_email ?? '' }}</div></div>
            </div>
        </div>

        <div class="signature">
            <div class="signature-block">
                <div class="line"></div>
                <div class="label">Assinatura do Hóspede</div>
            </div>
            <div class="signature-block">
                <div class="line"></div>
                <div class="label">Responsável do Estabelecimento</div>
            </div>
        </div>

        <div class="note">
            <strong>Nota legal:</strong> Este boletim deve ser conservado e disponibilizado às autoridades competentes (SEF) nos termos da legislação em vigor.
        </div>
    </div>
</body>
</html>
