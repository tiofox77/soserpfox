<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo - {{ $payrollItem->employee->full_name }} - {{ $payrollItem->payroll->payroll_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.3; color: #1f2937; background: #f3f4f6; }

        /* ── Toolbar (hidden on print) ── */
        .toolbar { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: linear-gradient(135deg, #059669, #047857); padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 12px rgba(0,0,0,.2); }
        .toolbar-title { color: white; font-size: 14px; font-weight: 700; }
        .toolbar-sub { color: #d1fae5; font-size: 11px; }
        .toolbar-actions { display: flex; gap: 8px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; text-decoration: none; }
        .btn-print { background: white; color: #059669; }
        .btn-print:hover { background: #d1fae5; transform: scale(1.03); }
        .btn-back { background: rgba(255,255,255,.15); color: white; }
        .btn-back:hover { background: rgba(255,255,255,.25); }

        /* ── Page wrapper ── */
        .page-wrap { max-width: 210mm; margin: 76px auto 30px; background: white; box-shadow: 0 4px 24px rgba(0,0,0,.12); border-radius: 4px; padding: 8mm 10mm; }

        /* ── Via ── */
        .via { min-height: 130mm; padding: 3mm 0; }
        .cut-line { border-top: 1.5px dashed #9ca3af; text-align: center; padding: 3px 0; font-size: 7pt; color: #9ca3af; letter-spacing: 1px; margin: 2mm 0; }

        .header-row { width: 100%; margin-bottom: 4px; }
        .header-row td { vertical-align: middle; }
        .logo { max-width: 70px; max-height: 30px; }
        .logo-placeholder { width: 70px; height: 28px; background: #059669; border-radius: 4px; text-align: center; line-height: 28px; color: white; font-weight: bold; font-size: 8pt; }
        .company-name { font-size: 12pt; color: #059669; font-weight: bold; }
        .company-detail { font-size: 7pt; color: #6b7280; }
        .via-badge { display: inline-block; padding: 2px 8px; font-size: 7pt; font-weight: bold; text-transform: uppercase; border-radius: 3px; }
        .via-empresa { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .via-func { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

        .doc-title { text-align: center; padding: 4px; background: #d1fae5; border-left: 3px solid #059669; margin: 4px 0; }
        .doc-title h2 { font-size: 11pt; color: #065f46; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; margin: 0; }
        .doc-title span { font-size: 7pt; color: #6b7280; }

        .info-tbl { width: 100%; border-collapse: collapse; margin: 3px 0; font-size: 7.5pt; }
        .info-tbl td { padding: 2.5px 5px; border: 1px solid #e5e7eb; }
        .info-tbl .lbl { background: #f9fafb; font-weight: 600; color: #4b5563; width: 22%; }
        .info-tbl .val { color: #111827; font-weight: 500; }

        .columns { width: 100%; border-collapse: collapse; margin: 3px 0; }
        .columns > tbody > tr > td { width: 50%; vertical-align: top; }
        .col-left { padding-right: 4px; }
        .col-right { padding-left: 4px; }

        .stbl { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        .stbl td { padding: 2.5px 5px; border: 1px solid #e5e7eb; }
        .stbl .desc { color: #374151; }
        .stbl .amt { text-align: right; font-weight: 600; color: #111827; white-space: nowrap; }
        .stbl .sub td { background: #f3f4f6; font-weight: bold; }

        .net-box { background: #d1fae5; border: 2px solid #059669; border-radius: 5px; padding: 5px; text-align: center; margin: 4px 0; }
        .net-box .lbl { font-size: 7.5pt; color: #065f46; font-weight: bold; text-transform: uppercase; }
        .net-box .val { font-size: 15pt; color: #047857; font-weight: bold; }

        .sig-tbl { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .sig-box { text-align: center; border: 1px solid #d1d5db; padding: 5px; border-radius: 3px; background: #f9fafb; }
        .sig-title { font-size: 6.5pt; color: #059669; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .sig-line { border-top: 1px solid #374151; margin-top: 16px; padding-top: 2px; }
        .sig-name { font-weight: bold; font-size: 7.5pt; color: #111827; }
        .sig-label { font-size: 6.5pt; color: #6b7280; font-weight: 600; }

        .footer-line { text-align: center; font-size: 6pt; color: #9ca3af; margin-top: 3px; }
        .section-hdr { font-size: 8pt; color: #059669; font-weight: bold; padding: 2px 5px; background: #d1fae5; border-left: 2px solid #059669; text-transform: uppercase; margin: 3px 0 2px 0; }

        /* ── Print ── */
        @media print {
            .toolbar { display: none !important; }
            body { background: white; }
            .page-wrap { margin: 0; box-shadow: none; border-radius: 0; padding: 0; max-width: none; }
            @page { margin: 8mm 10mm; size: A4 portrait; }
        }
    </style>
</head>
<body>
    {{-- Toolbar --}}
    <div class="toolbar">
        <div>
            <div class="toolbar-title">Recibo de Pagamento</div>
            <div class="toolbar-sub">{{ $payrollItem->employee->full_name }} — {{ $payrollItem->payroll->payroll_number }}</div>
        </div>
        <div class="toolbar-actions">
            <button class="btn btn-back" onclick="window.close(); return false;">&#10005; Fechar</button>
            <button class="btn btn-print" onclick="window.print();">&#128424; Imprimir</button>
        </div>
    </div>

    <div class="page-wrap">
    @foreach(['empresa', 'funcionario'] as $via)
        @if($via === 'funcionario')
            <div class="cut-line">- - - - - - - - - - - - - - - CORTAR AQUI - - - - - - - - - - - - - - -</div>
        @endif

        <div class="via">
            <table class="header-row" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="width: 80px;">
                        @if(auth()->user()->tenant->logo)
                            <img src="{{ asset('storage/' . auth()->user()->tenant->logo) }}" alt="Logo" class="logo">
                        @else
                            <div class="logo-placeholder">{{ \Illuminate\Support\Str::limit(auth()->user()->tenant->name, 12) }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="company-name">{{ auth()->user()->tenant->name }}</span><br>
                        @if(auth()->user()->tenant->nif)<span class="company-detail"><strong>NIF:</strong> {{ auth()->user()->tenant->nif }}</span> @endif
                        @if(auth()->user()->tenant->phone)<span class="company-detail">| <strong>Tel:</strong> {{ auth()->user()->tenant->phone }}</span>@endif
                    </td>
                    <td style="text-align: right; width: 100px;">
                        <span class="via-badge {{ $via === 'empresa' ? 'via-empresa' : 'via-func' }}">
                            {{ $via === 'empresa' ? '1.a Via - Empresa' : '2.a Via - Funcionario' }}
                        </span>
                    </td>
                </tr>
            </table>

            <div class="doc-title">
                <h2>RECIBO DE PAGAMENTO</h2>
                <span>Periodo: {{ $payrollItem->payroll->period_start->format('d/m/Y') }} a {{ $payrollItem->payroll->period_end->format('d/m/Y') }} | Ref: {{ $payrollItem->payroll->payroll_number }}</span>
            </div>

            <table class="info-tbl">
                <tr>
                    <td class="lbl">Nome</td>
                    <td class="val">{{ $payrollItem->employee->full_name }}</td>
                    <td class="lbl">Matricula</td>
                    <td class="val">{{ $payrollItem->employee->employee_number }}</td>
                </tr>
                <tr>
                    <td class="lbl">Cargo</td>
                    <td class="val">{{ $payrollItem->employee->position ?? 'N/A' }}</td>
                    <td class="lbl">Departamento</td>
                    <td class="val">{{ $payrollItem->employee->department ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Dias Trabalhados</td>
                    <td class="val">{{ $payrollItem->worked_days ?? 0 }} / {{ $payrollItem->total_working_days ?? 22 }}</td>
                    <td class="lbl">Faltas</td>
                    <td class="val">{{ $payrollItem->absence_days ?? 0 }} dias</td>
                </tr>
            </table>

            <table class="columns" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="col-left">
                        <div class="section-hdr">Vencimentos</div>
                        <table class="stbl">
                            <tr><td class="desc">Salario Base</td><td class="amt">{{ number_format($payrollItem->base_salary ?? 0, 2, ',', '.') }}</td></tr>
                            @if(($payrollItem->food_allowance ?? 0) > 0)
                            <tr><td class="desc">Subs. Alimentacao</td><td class="amt">{{ number_format($payrollItem->food_allowance, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->transport_allowance ?? 0) > 0)
                            <tr><td class="desc">Subs. Transporte</td><td class="amt">{{ number_format($payrollItem->transport_allowance, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->housing_allowance ?? 0) > 0)
                            <tr><td class="desc">Subs. Habitacao</td><td class="amt">{{ number_format($payrollItem->housing_allowance, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->overtime_pay ?? 0) > 0)
                            <tr><td class="desc">Horas Extra ({{ $payrollItem->overtime_hours ?? 0 }}h)</td><td class="amt">{{ number_format($payrollItem->overtime_pay, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->night_shift_allowance ?? 0) > 0)
                            <tr><td class="desc">Turno Noturno</td><td class="amt">{{ number_format($payrollItem->night_shift_allowance, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->bonus ?? 0) > 0)
                            <tr><td class="desc">Bonus</td><td class="amt">{{ number_format($payrollItem->bonus, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->family_allowance ?? 0) > 0)
                            <tr><td class="desc">Abono Familia</td><td class="amt">{{ number_format($payrollItem->family_allowance, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->position_subsidy ?? 0) > 0)
                            <tr><td class="desc">Subs. Cargo</td><td class="amt">{{ number_format($payrollItem->position_subsidy, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->performance_subsidy ?? 0) > 0)
                            <tr><td class="desc">Subs. Desempenho</td><td class="amt">{{ number_format($payrollItem->performance_subsidy, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->christmas_subsidy_amount ?? 0) > 0)
                            <tr><td class="desc">Subs. Natal (13o)</td><td class="amt">{{ number_format($payrollItem->christmas_subsidy_amount, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->vacation_subsidy_amount ?? 0) > 0)
                            <tr><td class="desc">Subs. Ferias (14o)</td><td class="amt">{{ number_format($payrollItem->vacation_subsidy_amount, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->commission ?? 0) > 0)
                            <tr><td class="desc">Comissoes</td><td class="amt">{{ number_format($payrollItem->commission, 2, ',', '.') }}</td></tr>
                            @endif
                            <tr class="sub"><td><strong>TOTAL BRUTO</strong></td><td class="amt"><strong>{{ number_format($payrollItem->gross_salary ?? 0, 2, ',', '.') }} Kz</strong></td></tr>
                        </table>
                    </td>
                    <td class="col-right">
                        <div class="section-hdr">Descontos</div>
                        <table class="stbl">
                            @if(($payrollItem->inss_employee ?? 0) > 0)
                            <tr><td class="desc">INSS (3%)</td><td class="amt">{{ number_format($payrollItem->inss_employee, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->irt_amount ?? 0) > 0)
                            <tr><td class="desc">IRT ({{ number_format($payrollItem->irt_rate ?? 0, 1) }}%)</td><td class="amt">{{ number_format($payrollItem->irt_amount, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->advance_payment ?? 0) > 0)
                            <tr><td class="desc">Adiantamento</td><td class="amt">{{ number_format($payrollItem->advance_payment, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->discount_deduction ?? 0) > 0)
                            <tr><td class="desc">Descontos Salariais</td><td class="amt">{{ number_format($payrollItem->discount_deduction, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->loan_deduction ?? 0) > 0)
                            <tr><td class="desc">Emprestimo</td><td class="amt">{{ number_format($payrollItem->loan_deduction, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->absence_deduction ?? 0) > 0)
                            <tr><td class="desc">Faltas ({{ $payrollItem->absence_days ?? 0 }}d)</td><td class="amt">{{ number_format($payrollItem->absence_deduction, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->food_deduction ?? 0) > 0)
                            <tr><td class="desc">Desc. Alimentacao</td><td class="amt">{{ number_format($payrollItem->food_deduction, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->late_deduction ?? 0) > 0)
                            <tr><td class="desc">Atrasos</td><td class="amt">{{ number_format($payrollItem->late_deduction, 2, ',', '.') }}</td></tr>
                            @endif
                            @if(($payrollItem->other_deductions ?? 0) > 0)
                            <tr><td class="desc">Outros Descontos</td><td class="amt">{{ number_format($payrollItem->other_deductions, 2, ',', '.') }}</td></tr>
                            @endif
                            <tr class="sub"><td><strong>TOTAL DESCONTOS</strong></td><td class="amt"><strong>{{ number_format($payrollItem->total_deductions ?? 0, 2, ',', '.') }} Kz</strong></td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="net-box">
                <div class="lbl">SALARIO LIQUIDO A RECEBER</div>
                <div class="val">{{ number_format($payrollItem->net_salary ?? 0, 2, ',', '.') }} Kz</div>
            </div>

            <table class="sig-tbl" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="width: 48%; padding: 0 2px;">
                        <div class="sig-box">
                            <div class="sig-title">Funcionario</div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $payrollItem->employee->full_name }}</div>
                                <div class="sig-label">Recebi | {{ $payrollItem->employee->employee_number }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 4%;"></td>
                    <td style="width: 48%; padding: 0 2px;">
                        <div class="sig-box">
                            <div class="sig-title">Recursos Humanos</div>
                            <div class="sig-line">
                                <div class="sig-name">_________________________</div>
                                <div class="sig-label">Autorizado | {{ now()->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-line">
                Gerado em {{ now()->format('d/m/Y H:i') }} | {{ auth()->user()->tenant->name }} - Confidencial
                | INSS Patronal (8%): {{ number_format($payrollItem->inss_employer ?? 0, 2, ',', '.') }} Kz
            </div>
        </div>
    @endforeach
    </div>
</body>
</html>
