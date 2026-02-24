<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pagamento - {{ $payrollItem->employee->full_name }}</title>
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
            border-bottom: 2px solid #059669;
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
            color: #059669;
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
            background: #d1fae5;
            border-left: 3px solid #059669;
        }
        .document-title h2 {
            font-size: 12px;
            color: #065f46;
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
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
            font-size: 8px;
        }
        .salary-table th {
            background: #059669;
            color: white;
            font-weight: bold;
            text-align: left;
            padding: 3px 5px;
            border: 1px solid #047857;
            font-size: 8px;
            text-transform: uppercase;
        }
        .salary-table td {
            padding: 3px 5px;
            border: 1px solid #e5e7eb;
        }
        .salary-table td.desc {
            color: #374151;
        }
        .salary-table td.amount {
            text-align: right;
            font-weight: 600;
            color: #111827;
        }
        .salary-table tr.subtotal td {
            background: #f3f4f6;
            font-weight: bold;
            color: #1f2937;
        }
        .salary-table tr.total td {
            background: #059669;
            color: white;
            font-weight: bold;
            font-size: 9px;
        }
        .net-box {
            background: #d1fae5;
            border: 2px solid #059669;
            border-radius: 6px;
            padding: 6px;
            text-align: center;
            margin: 4px 0;
        }
        .net-box .label {
            font-size: 8px;
            color: #065f46;
            margin-bottom: 2px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .net-box .value {
            font-size: 18px;
            color: #047857;
            font-weight: bold;
            letter-spacing: 0.5px;
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
            color: #059669;
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
            color: #059669;
            font-weight: bold;
            margin: 4px 0 2px 0;
            padding: 3px 6px;
            background: #d1fae5;
            border-left: 2px solid #059669;
            text-transform: uppercase;
        }
        @media print {
            body {
                padding: 20px;
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
                <div style="width: 100px; height: 40px; background: #059669; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 10px;">
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
        <h2>RECIBO DE PAGAMENTO</h2>
        <p>Período: {{ $payrollItem->payroll->period_start->format('d/m/Y') }} a {{ $payrollItem->payroll->period_end->format('d/m/Y') }} | Ref: {{ $payrollItem->payroll->payroll_number }}</p>
    </div>

    {{-- Dados do Funcionário --}}
    <div class="section-title">Dados do Funcionário</div>
    <table class="info-table">
        <tr>
            <td class="label">Nome Completo</td>
            <td class="value" colspan="3">{{ $payrollItem->employee->full_name }}</td>
        </tr>
        <tr>
            <td class="label">Matrícula</td>
            <td class="value">{{ $payrollItem->employee->employee_number }}</td>
            <td class="label">Cargo</td>
            <td class="value">{{ $payrollItem->employee->position ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td class="value">{{ $payrollItem->employee->department ?? 'N/A' }}</td>
            <td class="label">Dias Trabalhados</td>
            <td class="value">{{ $payrollItem->worked_days ?? 22 }} dias</td>
        </tr>
    </table>

    {{-- Vencimentos --}}
    <div class="section-title">Vencimentos</div>
    <table class="salary-table">
        <tr>
            <td class="desc">Salário Base</td>
            <td class="amount">{{ number_format($payrollItem->base_salary ?? 0, 2, ',', '.') }} Kz</td>
        </tr>
        @if($payrollItem->food_allowance > 0)
        <tr>
            <td class="desc">Subsídio de Alimentação</td>
            <td class="amount">{{ number_format($payrollItem->food_allowance, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->transport_allowance > 0)
        <tr>
            <td class="desc">Subsídio de Transporte</td>
            <td class="amount">{{ number_format($payrollItem->transport_allowance, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->housing_allowance > 0)
        <tr>
            <td class="desc">Subsídio de Habitação</td>
            <td class="amount">{{ number_format($payrollItem->housing_allowance, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->overtime_pay > 0)
        <tr>
            <td class="desc">Horas Extras ({{ $payrollItem->overtime_hours ?? 0 }}h)</td>
            <td class="amount">{{ number_format($payrollItem->overtime_pay, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->bonus > 0)
        <tr>
            <td class="desc">Bónus</td>
            <td class="amount">{{ number_format($payrollItem->bonus, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->commission > 0)
        <tr>
            <td class="desc">Comissões</td>
            <td class="amount">{{ number_format($payrollItem->commission, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        <tr class="subtotal">
            <td><strong>TOTAL BRUTO</strong></td>
            <td class="amount"><strong>{{ number_format($payrollItem->gross_salary ?? 0, 2, ',', '.') }} Kz</strong></td>
        </tr>
    </table>

    {{-- Descontos --}}
    <div class="section-title">Descontos e Deduções</div>
    <table class="salary-table">
        @if($payrollItem->inss_employee > 0)
        <tr>
            <td class="desc">INSS Funcionário (3%)</td>
            <td class="amount">{{ number_format($payrollItem->inss_employee, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->irt_amount > 0)
        <tr>
            <td class="desc">IRT ({{ number_format($payrollItem->irt_rate ?? 0, 1) }}%)</td>
            <td class="amount">{{ number_format($payrollItem->irt_amount, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->advance_payment > 0)
        <tr>
            <td class="desc">Adiantamento Salarial</td>
            <td class="amount">{{ number_format($payrollItem->advance_payment, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->loan_deduction > 0)
        <tr>
            <td class="desc">Empréstimo</td>
            <td class="amount">{{ number_format($payrollItem->loan_deduction, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->absence_deduction > 0)
        <tr>
            <td class="desc">Faltas ({{ $payrollItem->absence_days ?? 0 }} dias)</td>
            <td class="amount">{{ number_format($payrollItem->absence_deduction, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->late_deduction > 0)
        <tr>
            <td class="desc">Atrasos</td>
            <td class="amount">{{ number_format($payrollItem->late_deduction, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        @if($payrollItem->other_deductions > 0)
        <tr>
            <td class="desc">Outras Deduções</td>
            <td class="amount">{{ number_format($payrollItem->other_deductions, 2, ',', '.') }} Kz</td>
        </tr>
        @endif
        <tr class="subtotal">
            <td><strong>TOTAL DESCONTOS</strong></td>
            <td class="amount"><strong>{{ number_format($payrollItem->total_deductions ?? 0, 2, ',', '.') }} Kz</strong></td>
        </tr>
    </table>

    {{-- Salário Líquido --}}
    <div class="net-box">
        <div class="label">SALÁRIO LÍQUIDO A RECEBER</div>
        <div class="value">{{ number_format($payrollItem->net_salary ?? 0, 2, ',', '.') }} Kz</div>
    </div>

    {{-- Assinaturas --}}
    <div class="section-title" style="text-align: center; margin-top: 6px;">Assinaturas</div>
    <table style="width: 100%; margin-top: 4px; border-collapse: collapse;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding: 0 3px;">
                <div class="signature-box">
                    <div class="signature-title">FUNCIONÁRIO</div>
                    <div class="signature-line">
                        <div class="signature-name">{{ $payrollItem->employee->full_name }}</div>
                        <div class="signature-label">Recebi o valor acima • {{ $payrollItem->employee->employee_number }}</div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top; padding: 0 3px;">
                <div class="signature-box">
                    <div class="signature-title">RECURSOS HUMANOS</div>
                    <div class="signature-line">
                        <div class="signature-name">_____________________________</div>
                        <div class="signature-label">Autorizado • {{ now()->format('d/m/Y') }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        <p>Este recibo foi gerado eletronicamente em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</p>
        <p>{{ auth()->user()->tenant->name }} - Confidencial</p>
    </div>
</body>
</html>
