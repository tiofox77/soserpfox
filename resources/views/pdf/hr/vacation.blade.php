<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovativo de Férias — {{ $vacation->vacation_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 11px; color: #1a1a1a; background: #f1f5f9; }

        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: linear-gradient(135deg, #d97706, #b45309); color: #fff;
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,.25);
        }
        .toolbar button { background: #fff; color: #b45309; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; margin-left: 8px; }
        .toolbar button.close-btn { background: rgba(255,255,255,.2); color: #fff; }
        .toolbar button:hover { opacity: .85; }

        .page {
            width: 210mm; margin: 80px auto 20px; background: #fff;
            padding: 20mm 15mm; box-shadow: 0 2px 12px rgba(0,0,0,.1);
        }

        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #d97706; padding-bottom: 12px; margin-bottom: 20px; }
        .header .company { display: flex; align-items: center; gap: 12px; }
        .header .company img { height: 50px; }
        .header .company h2 { font-size: 16px; color: #92400e; }
        .header .company p { font-size: 10px; color: #666; }
        .header .title { text-align: right; }
        .header .title h1 { font-size: 18px; color: #92400e; text-transform: uppercase; }
        .header .title p { font-size: 10px; color: #666; }

        .section { margin-bottom: 16px; }
        .section-title {
            font-size: 12px; font-weight: 700; color: #fff; background: #d97706;
            padding: 5px 10px; border-radius: 4px; margin-bottom: 8px;
        }
        .grid { display: grid; gap: 2px; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .field { background: #f8fafc; padding: 6px 8px; border: 1px solid #e2e8f0; }
        .field .label { font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: .5px; }
        .field .value { font-size: 11px; font-weight: 600; color: #1e293b; margin-top: 1px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dbeafe; color: #1e40af; }
        .badge-in_progress { background: #e0e7ff; color: #3730a3; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #374151; }

        .amount-box {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 2px solid #f59e0b; border-radius: 8px; padding: 12px;
            text-align: center; margin-top: 12px;
        }
        .amount-box .label { font-size: 10px; color: #92400e; text-transform: uppercase; }
        .amount-box .value { font-size: 22px; font-weight: 800; color: #78350f; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; padding-top: 20px; }
        .sig-line { border-top: 1px solid #1a1a1a; padding-top: 6px; text-align: center; font-size: 10px; }

        .footer { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 8px; text-align: center; font-size: 9px; color: #94a3b8; }

        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 10mm; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px">
            <i class="fas fa-umbrella-beach" style="font-size:20px"></i>
            <div>
                <div style="font-weight:700;font-size:14px">Comprovativo de Férias</div>
                <div style="font-size:11px;opacity:.8">{{ $vacation->vacation_number }} — {{ $vacation->employee->full_name }}</div>
            </div>
        </div>
        <div>
            <button onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
            <button class="close-btn" onclick="window.close()"><i class="fas fa-times"></i> Fechar</button>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @php
        $tenant = auth()->user()->activeTenant ?? null;
        $employee = $vacation->employee;
        $statusLabels = [
            'pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Rejeitada',
            'in_progress' => 'Em Andamento', 'completed' => 'Concluída', 'cancelled' => 'Cancelada',
        ];
    @endphp

    <div class="page">
        {{-- Header --}}
        <div class="header">
            <div class="company">
                @if($tenant && $tenant->logo)
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Logo">
                @endif
                <div>
                    <h2>{{ $tenant->name ?? 'Empresa' }}</h2>
                    <p>NIF: {{ $tenant->nif ?? '—' }} | Tel: {{ $tenant->phone ?? '—' }}</p>
                    <p>{{ $tenant->address ?? '' }}</p>
                </div>
            </div>
            <div class="title">
                <h1>Comprovativo de Férias</h1>
                <p>Nº {{ $vacation->vacation_number }}</p>
                <p>Emitido: {{ now()->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        {{-- Dados do Funcionário --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-user"></i> Dados do Funcionário</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Nome Completo</div><div class="value">{{ $employee->full_name }}</div></div>
                <div class="field"><div class="label">Nº Funcionário</div><div class="value">{{ $employee->employee_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Departamento</div><div class="value">{{ $employee->department->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Cargo</div><div class="value">{{ $employee->position->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Data Admissão</div><div class="value">{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Antiguidade</div><div class="value">{{ $employee->years_of_service }} ano(s)</div></div>
            </div>
        </div>

        {{-- Detalhes das Férias --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-umbrella-beach"></i> Detalhes das Férias</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Tipo de Férias</div><div class="value">{{ $vacation->vacation_type_name }}</div></div>
                <div class="field"><div class="label">Ano de Referência</div><div class="value">{{ $vacation->reference_year ?? '—' }}</div></div>
                <div class="field">
                    <div class="label">Estado</div>
                    <div class="value"><span class="badge badge-{{ $vacation->status }}">{{ $statusLabels[$vacation->status] ?? $vacation->status }}</span></div>
                </div>
                <div class="field"><div class="label">Data Início</div><div class="value">{{ $vacation->start_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Data Fim</div><div class="value">{{ $vacation->end_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Regresso Previsto</div><div class="value">{{ $vacation->expected_return_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Dias Solicitados</div><div class="value">{{ $vacation->requested_days ?? '—' }}</div></div>
                <div class="field"><div class="label">Dias Úteis</div><div class="value">{{ $vacation->working_days ?? '—' }}</div></div>
                <div class="field"><div class="label">Dias Direito Anual</div><div class="value">{{ $vacation->entitled_days ?? '—' }}</div></div>
            </div>
        </div>

        {{-- Informação Financeira --}}
        @if($vacation->vacation_pay || $vacation->subsidy_amount || $vacation->total_amount)
        <div class="section">
            <div class="section-title"><i class="fas fa-money-bill-wave"></i> Informação Financeira</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Valor Diário</div><div class="value">{{ number_format($vacation->daily_rate ?? 0, 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Remuneração Férias</div><div class="value">{{ number_format($vacation->vacation_pay ?? 0, 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Subsídio Férias</div><div class="value">{{ number_format($vacation->subsidy_amount ?? 0, 2, ',', '.') }} Kz</div></div>
            </div>
            <div class="amount-box">
                <div class="label">Valor Total a Receber</div>
                <div class="value">{{ number_format($vacation->total_amount ?? 0, 2, ',', '.') }} Kz</div>
            </div>
        </div>
        @endif

        {{-- Substituição --}}
        @if($vacation->replacementEmployee)
        <div class="section">
            <div class="section-title"><i class="fas fa-exchange-alt"></i> Substituição</div>
            <div class="grid grid-2">
                <div class="field"><div class="label">Funcionário Substituto</div><div class="value">{{ $vacation->replacementEmployee->full_name }}</div></div>
                <div class="field"><div class="label">Departamento</div><div class="value">{{ $vacation->replacementEmployee->department->name ?? '—' }}</div></div>
            </div>
        </div>
        @endif

        {{-- Aprovação --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-check-circle"></i> Aprovação</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Aprovado Por</div><div class="value">{{ $vacation->approvedBy->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Data Aprovação</div><div class="value">{{ $vacation->approved_at?->format('d/m/Y H:i') ?? '—' }}</div></div>
                <div class="field"><div class="label">Pago</div><div class="value">{{ $vacation->paid ? 'Sim — ' . ($vacation->paid_date?->format('d/m/Y') ?? '') : 'Não' }}</div></div>
            </div>
        </div>

        {{-- Notas --}}
        @if($vacation->notes)
        <div class="section">
            <div class="section-title"><i class="fas fa-sticky-note"></i> Observações</div>
            <div class="field" style="background:#fffbeb;border-color:#fde68a">
                <div class="value" style="font-weight:400">{{ $vacation->notes }}</div>
            </div>
        </div>
        @endif

        {{-- Assinaturas --}}
        <div class="signatures">
            <div>
                <div class="sig-line">O Funcionário</div>
                <p style="text-align:center;font-size:9px;color:#64748b;margin-top:4px">{{ $employee->full_name }}</p>
            </div>
            <div>
                <div class="sig-line">A Direção / RH</div>
                <p style="text-align:center;font-size:9px;color:#64748b;margin-top:4px">{{ $vacation->approvedBy->name ?? '________________' }}</p>
            </div>
        </div>

        <div class="footer">
            {{ $tenant->name ?? 'SOS ERP' }} — Comprovativo de Férias nº {{ $vacation->vacation_number }} — Emitido em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
