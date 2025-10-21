<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adiantamento Salarial - {{ $advance->advance_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #1f2937;
            padding: 15px;
            max-width: 210mm;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 3px solid #2563eb;
        }
        .logo {
            max-width: 120px;
            max-height: 50px;
        }
        .company-info {
            text-align: right;
            flex: 1;
        }
        .company-info h1 {
            font-size: 16px;
            color: #2563eb;
            margin-bottom: 2px;
            font-weight: bold;
        }
        .company-info p {
            font-size: 8px;
            color: #6b7280;
            margin: 1px 0;
        }
        .document-title {
            text-align: center;
            margin: 8px 0;
            padding: 8px;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
        }
        .document-title h2 {
            font-size: 14px;
            color: #1e40af;
            margin-bottom: 2px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .document-title p {
            font-size: 8px;
            color: #6b7280;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 9px;
        }
        .info-table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            font-size: 9px;
            text-transform: uppercase;
        }
        .info-table td {
            padding: 5px 8px;
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
            background: #dbeafe;
            border: 3px solid #2563eb;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin: 8px 0;
        }
        .amount-box .label {
            font-size: 9px;
            color: #1e40af;
            margin-bottom: 3px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .amount-box .value {
            font-size: 22px;
            color: #1e3a8a;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .amount-box .installments {
            font-size: 9px;
            color: #1e40af;
            margin-top: 3px;
            font-weight: 600;
        }
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .payment-table th,
        .payment-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #e5e7eb;
        }
        .payment-table th {
            background: #f3f4f6;
            font-weight: 600;
            font-size: 11px;
            color: #4b5563;
            text-transform: uppercase;
        }
        .payment-table td {
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        .status-paid {
            background: #dbeafe;
            color: #1e40af;
        }
        .signature-section {
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            margin-top: 8px;
        }
        .signature-box {
            flex: 1;
            text-align: center;
            border: 2px solid #d1d5db;
            padding: 10px;
            border-radius: 6px;
            background: #f9fafb;
            min-height: 85px;
        }
        .signature-title {
            font-size: 9px;
            color: #2563eb;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .signature-line {
            border-top: 2px solid #374151;
            margin-top: 30px;
            padding-top: 4px;
        }
        .signature-label {
            font-size: 8px;
            color: #6b7280;
            margin-top: 3px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .signature-name {
            font-weight: bold;
            margin-top: 2px;
            font-size: 10px;
            color: #111827;
        }
        .footer {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
        }
        .section-title {
            font-size: 10px;
            color: #2563eb;
            font-weight: bold;
            margin: 8px 0 4px 0;
            padding: 4px 8px;
            background: #eff6ff;
            border-left: 3px solid #2563eb;
            text-transform: uppercase;
        }
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .notes {
            background: #fef3c7;
            border-left: 3px solid #f59e0b;
            padding: 6px 10px;
            margin: 6px 0;
            border-radius: 4px;
        }
        .notes-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 3px;
            font-size: 9px;
            text-transform: uppercase;
        }
        .notes-content {
            font-size: 8px;
            color: #78350f;
            line-height: 1.3;
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
                <div style="width: 150px; height: 60px; background: #2563eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
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
        <h2>TERMO DE ADIANTAMENTO SALARIAL</h2>
        <p>Documento Nº {{ $advance->advance_number }} | Emitido em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Dados do Funcionário --}}
    <div class="section-title">Dados do Funcionário</div>
    <table class="info-table">
        <tr>
            <td class="label">Nome Completo</td>
            <td class="value">{{ $advance->employee->full_name }}</td>
            <td class="label">Matrícula</td>
            <td class="value">{{ $advance->employee->employee_number }}</td>
        </tr>
        <tr>
            <td class="label">Cargo</td>
            <td class="value">{{ $advance->employee->position ?? 'N/A' }}</td>
            <td class="label">Departamento</td>
            <td class="value">{{ $advance->employee->department ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- Valor do Adiantamento --}}
    <div class="amount-box">
        <div class="label">VALOR SOLICITADO</div>
        <div class="value">{{ number_format($advance->requested_amount, 2, ',', '.') }} Kz</div>
        <div class="installments">
            Parcelado em <strong>{{ $advance->installments }}x</strong> de 
            <strong>{{ number_format($advance->installment_amount, 2, ',', '.') }} Kz</strong>
        </div>
    </div>

    {{-- Detalhes do Adiantamento --}}
    <div class="section-title">Detalhes do Adiantamento</div>
    <table class="info-table">
        <tr>
            <td class="label">Data Solicitação</td>
            <td class="value">{{ $advance->request_date->format('d/m/Y') }}</td>
            <td class="label">Status</td>
            <td class="value">
                @if($advance->status === 'pending') Pendente
                @elseif($advance->status === 'approved') Aprovado
                @elseif($advance->status === 'rejected') Rejeitado
                @elseif($advance->status === 'paid') Pago
                @elseif($advance->status === 'in_deduction') Em Dedução
                @elseif($advance->status === 'completed') Completado
                @else {{ ucfirst($advance->status) }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Salário Base</td>
            <td class="value">{{ number_format($advance->base_salary, 2, ',', '.') }} Kz</td>
            <td class="label">Máximo Permitido</td>
            <td class="value">{{ number_format($advance->max_allowed, 2, ',', '.') }} Kz</td>
        </tr>
    </table>

    @if($advance->reason)
    <div class="notes">
        <div class="notes-title">Motivo da Solicitação:</div>
        <div class="notes-content">{{ $advance->reason }}</div>
    </div>
    @endif

    @if($advance->status === 'approved' && $advance->approved_amount)
    <div class="section-title">Informações de Aprovação</div>
    <table class="info-table">
        <tr>
            <td class="label">Valor Aprovado</td>
            <td class="value">{{ number_format($advance->approved_amount, 2, ',', '.') }} Kz</td>
            <td class="label">Aprovado por</td>
            <td class="value">{{ $advance->approvedBy->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label" colspan="1">Data de Aprovação</td>
            <td class="value" colspan="3">{{ $advance->approved_at ? $advance->approved_at->format('d/m/Y H:i') : 'N/A' }}</td>
        </tr>
    </table>
    @endif

    {{-- Termos e Condições --}}
    <div class="section-title">Termos e Condições</div>
    <div style="font-size: 8px; line-height: 1.4; padding: 4px 8px;">
        <p style="margin-bottom: 3px;">
            <strong>1.</strong> Desconto automático em {{ $advance->installments }}x de {{ number_format($advance->installment_amount, 2, ',', '.') }} Kz. 
            <strong>2.</strong> Primeiro desconto na folha subsequente. 
            <strong>3.</strong> Saldo remanescente descontado das verbas rescisórias.
        </p>
    </div>

    {{-- Assinaturas --}}
    <div class="section-title" style="text-align: center;">Assinaturas</div>
    <table style="width: 100%; margin-top: 10px; border-collapse: collapse;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding: 0 5px;">
                <div class="signature-box">
                    <div class="signature-title">QUEM RECEBE</div>
                    <div class="signature-line">
                        <div class="signature-name">{{ $advance->employee->full_name }}</div>
                        <div class="signature-label">Funcionário</div>
                        <div class="signature-label" style="font-size: 7px;">{{ $advance->employee->employee_number }}</div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top; padding: 0 5px;">
                <div class="signature-box">
                    <div class="signature-title">QUEM APROVA</div>
                    <div class="signature-line">
                        @if($advance->status === 'approved' && $advance->approvedBy)
                            <div class="signature-name">{{ $advance->approvedBy->name }}</div>
                            <div class="signature-label">Aprovado em {{ $advance->approved_at->format('d/m/Y') }}</div>
                        @else
                            <div class="signature-name">_________________________________</div>
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
