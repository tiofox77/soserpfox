<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horas Extras - {{ $overtime->overtime_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 9px;
            line-height: 1.2;
            color: #1f2937;
            padding: 10px 15px;
            max-width: 210mm;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
            padding-bottom: 5px;
            border-bottom: 2px solid #6366f1;
        }
        .logo {
            max-width: 100px;
            max-height: 40px;
        }
        .company-info {
            text-align: right;
            flex: 1;
        }
        .company-info h1 {
            font-size: 13px;
            color: #6366f1;
            margin-bottom: 1px;
            font-weight: bold;
        }
        .company-info p {
            font-size: 7px;
            color: #6b7280;
            margin: 0;
        }
        .document-title {
            text-align: center;
            margin: 4px 0;
            padding: 5px;
            background: #eef2ff;
            border-left: 3px solid #6366f1;
        }
        .document-title h2 {
            font-size: 12px;
            color: #4338ca;
            margin-bottom: 1px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .document-title p {
            font-size: 7px;
            color: #6b7280;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
            font-size: 8px;
        }
        .info-table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 3px 5px;
            border: 1px solid #d1d5db;
            font-size: 8px;
            text-transform: uppercase;
        }
        .info-table td {
            padding: 3px 5px;
            border: 1px solid #e5e7eb;
            color: #1f2937;
        }
        .info-table td.label {
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
            width: 35%;
        }
        .info-table td.value {
            color: #111827;
            font-weight: 500;
        }
        .amount-box {
            background: #eef2ff;
            border: 2px solid #6366f1;
            border-radius: 6px;
            padding: 6px;
            text-align: center;
            margin: 4px 0;
        }
        .amount-box .label {
            font-size: 8px;
            color: #4338ca;
            margin-bottom: 2px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .amount-box .value {
            font-size: 16px;
            color: #3730a3;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .amount-box .hours {
            font-size: 8px;
            color: #4338ca;
            margin-top: 2px;
            font-weight: 600;
        }
        .signature-section {
            margin-top: 6px;
            page-break-inside: avoid;
        }
        .signature-box {
            flex: 1;
            text-align: center;
            border: 1px solid #d1d5db;
            padding: 6px;
            border-radius: 4px;
            background: #f9fafb;
            min-height: 60px;
        }
        .signature-title {
            font-size: 7px;
            color: #6366f1;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .signature-line {
            border-top: 1px solid #374151;
            margin-top: 20px;
            padding-top: 3px;
        }
        .signature-label {
            font-size: 7px;
            color: #6b7280;
            margin-top: 2px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .signature-name {
            font-weight: bold;
            margin-top: 1px;
            font-size: 8px;
            color: #111827;
        }
        .footer {
            margin-top: 4px;
            padding-top: 3px;
            border-top: 1px solid #d1d5db;
            text-align: center;
            font-size: 6px;
            color: #9ca3af;
        }
        .section-title {
            font-size: 9px;
            color: #6366f1;
            font-weight: bold;
            margin: 4px 0 2px 0;
            padding: 3px 6px;
            background: #eef2ff;
            border-left: 2px solid #6366f1;
            text-transform: uppercase;
        }
        .notes {
            background: #fef3c7;
            border-left: 2px solid #f59e0b;
            padding: 4px 6px;
            margin: 3px 0;
            border-radius: 3px;
        }
        .notes-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 2px;
            font-size: 7px;
            text-transform: uppercase;
        }
        .notes-content {
            font-size: 7px;
            color: #78350f;
            line-height: 1.2;
        }
        @media print {
            body {
                padding: 20px;
            }
            .signature-section {
                page-break-before: avoid;
            }
        }
    </style>
</head>
<body>
    {{-- Header com Logo --}}
    <div class="header">
        <div>
            @if(auth()->user()->tenant->logo)
                <img src="{{ public_path('storage/' . auth()->user()->tenant->logo) }}" alt="Logo" class="logo">
            @else
                <div style="width: 100px; height: 40px; background: #6366f1; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 10px;">
                    {{ auth()->user()->tenant->name }}
                </div>
            @endif
        </div>
        <div class="company-info">
            <h1>{{ auth()->user()->tenant->name }}</h1>
            @if(auth()->user()->tenant->nif)
                <p><strong>NIF:</strong> {{ auth()->user()->tenant->nif }}</p>
            @endif
            @if(auth()->user()->tenant->address)
                <p>{{ auth()->user()->tenant->address }}</p>
            @endif
            @if(auth()->user()->tenant->phone)
                <p><strong>Tel:</strong> {{ auth()->user()->tenant->phone }}</p>
            @endif
        </div>
    </div>

    {{-- Título do Documento --}}
    <div class="document-title">
        <h2>COMPROVANTE DE HORAS EXTRAS</h2>
        <p>Documento Nº {{ $overtime->overtime_number }} | Emitido em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Dados do Funcionário --}}
    <div class="section-title">Dados do Funcionário</div>
    <table class="info-table">
        <tr>
            <td class="label">Nome Completo</td>
            <td class="value">{{ $overtime->employee->full_name }}</td>
            <td class="label">Matrícula</td>
            <td class="value">{{ $overtime->employee->employee_number }}</td>
        </tr>
        <tr>
            <td class="label">Cargo</td>
            <td class="value">{{ $overtime->employee->position ?? 'N/A' }}</td>
            <td class="label">Departamento</td>
            <td class="value">{{ $overtime->employee->department ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- Detalhes das Horas Extras --}}
    <div class="section-title">Detalhes das Horas Extras</div>
    <table class="info-table">
        <tr>
            <td class="label">Data</td>
            <td class="value">{{ $overtime->date->format('d/m/Y') }}</td>
            <td class="label">Tipo</td>
            <td class="value">
                @if($overtime->overtime_type === 'weekday') Dia Útil (+50%)
                @elseif($overtime->overtime_type === 'weekend') Fim de Semana (+100%)
                @elseif($overtime->overtime_type === 'holiday') Feriado (+100%)
                @elseif($overtime->overtime_type === 'night') Noturno (+25%)
                @else {{ ucfirst($overtime->overtime_type) }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Horário Início</td>
            <td class="value">{{ $overtime->start_time }}</td>
            <td class="label">Horário Fim</td>
            <td class="value">{{ $overtime->end_time }}</td>
        </tr>
        <tr>
            <td class="label">Total de Horas</td>
            <td class="value"><strong>{{ number_format($overtime->total_hours, 2) }}h</strong></td>
            <td class="label">Status</td>
            <td class="value">
                @if($overtime->status === 'pending') Pendente
                @elseif($overtime->status === 'approved') Aprovado
                @elseif($overtime->status === 'rejected') Rejeitado
                @elseif($overtime->status === 'paid') Pago
                @else {{ ucfirst($overtime->status) }}
                @endif
            </td>
        </tr>
    </table>

    {{-- Valor a Receber --}}
    <div class="amount-box">
        <div class="label">VALOR A RECEBER</div>
        <div class="value">{{ number_format($overtime->total_amount, 2, ',', '.') }} Kz</div>
        <div class="hours">
            {{ number_format($overtime->total_hours, 2) }} horas × 
            {{ number_format($overtime->hourly_rate, 2, ',', '.') }} Kz/h × 
            {{ $overtime->multiplier }}
        </div>
    </div>

    @if($overtime->description)
    <div class="notes">
        <div class="notes-title">Descrição:</div>
        <div class="notes-content">{{ $overtime->description }}</div>
    </div>
    @endif

    @if($overtime->status === 'approved' && $overtime->approvedBy)
    <div class="section-title">Informações de Aprovação</div>
    <table class="info-table">
        <tr>
            <td class="label">Aprovado por</td>
            <td class="value">{{ $overtime->approvedBy->name }}</td>
            <td class="label">Data de Aprovação</td>
            <td class="value">{{ $overtime->approved_at->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
    @endif

    @if($overtime->status === 'rejected' && $overtime->rejection_reason)
    <div class="notes" style="background: #fee2e2; border-left-color: #ef4444;">
        <div class="notes-title" style="color: #991b1b;">Motivo da Rejeição:</div>
        <div class="notes-content" style="color: #7f1d1d;">{{ $overtime->rejection_reason }}</div>
    </div>
    @endif

    {{-- Assinaturas --}}
    <div class="section-title" style="text-align: center; margin-top: 6px;">Assinaturas</div>
    <table style="width: 100%; margin-top: 4px; border-collapse: collapse;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding: 0 3px;">
                <div class="signature-box">
                    <div class="signature-title">FUNCIONÁRIO</div>
                    <div class="signature-line">
                        <div class="signature-name">{{ $overtime->employee->full_name }}</div>
                        <div class="signature-label">Matrícula • {{ $overtime->employee->employee_number }}</div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top; padding: 0 3px;">
                <div class="signature-box">
                    <div class="signature-title">AUTORIZAÇÃO</div>
                    <div class="signature-line">
                        @if($overtime->status === 'approved' && $overtime->approvedBy)
                            <div class="signature-name">{{ $overtime->approvedBy->name }}</div>
                            <div class="signature-label">Aprovado em {{ $overtime->approved_at->format('d/m/Y') }}</div>
                        @else
                            <div class="signature-name">_____________________________</div>
                            <div class="signature-label">Recursos Humanos</div>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        <p>Este documento foi gerado eletronicamente em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</p>
        <p>{{ auth()->user()->tenant->name }} - Todos os direitos reservados</p>
    </div>
</body>
</html>
