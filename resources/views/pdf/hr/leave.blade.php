<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovativo de Licença — {{ $leave->leave_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 11px; color: #1a1a1a; background: #f1f5f9; }

        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: linear-gradient(135deg, #ea580c, #c2410c); color: #fff;
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,.25);
        }
        .toolbar button { background: #fff; color: #c2410c; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; margin-left: 8px; }
        .toolbar button.close-btn { background: rgba(255,255,255,.2); color: #fff; }
        .toolbar button:hover { opacity: .85; }

        .page {
            width: 210mm; margin: 80px auto 20px; background: #fff;
            padding: 20mm 15mm; box-shadow: 0 2px 12px rgba(0,0,0,.1);
        }

        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #ea580c; padding-bottom: 12px; margin-bottom: 20px; }
        .header .company { display: flex; align-items: center; gap: 12px; }
        .header .company img { height: 50px; }
        .header .company h2 { font-size: 16px; color: #9a3412; }
        .header .company p { font-size: 10px; color: #666; }
        .header .title { text-align: right; }
        .header .title h1 { font-size: 18px; color: #9a3412; text-transform: uppercase; }
        .header .title p { font-size: 10px; color: #666; }

        .section { margin-bottom: 16px; }
        .section-title {
            font-size: 12px; font-weight: 700; color: #fff; background: #ea580c;
            padding: 5px 10px; border-radius: 4px; margin-bottom: 8px;
        }
        .grid { display: grid; gap: 2px; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .field { background: #f8fafc; padding: 6px 8px; border: 1px solid #e2e8f0; }
        .field .label { font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: .5px; }
        .field .value { font-size: 11px; font-weight: 600; color: #1e293b; margin-top: 1px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #374151; }

        .info-box {
            background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px;
            padding: 10px; margin-top: 8px;
        }

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
            <i class="fas fa-calendar-times" style="font-size:20px"></i>
            <div>
                <div style="font-weight:700;font-size:14px">Comprovativo de Licença / Falta</div>
                <div style="font-size:11px;opacity:.8">{{ $leave->leave_number }} — {{ $leave->employee->full_name }}</div>
            </div>
        </div>
        <div>
            <button onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
            <button class="close-btn" onclick="window.close()"><i class="fas fa-times"></i> Fechar</button>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @php
        $tenant = \App\Models\Tenant::find(activeTenantId());
        $employee = $leave->employee;
        $statusLabels = [
            'pending' => 'Pendente', 'approved' => 'Aprovada',
            'rejected' => 'Rejeitada', 'cancelled' => 'Cancelada',
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
                    <h2>{{ optional($tenant)->nomeParaDocumentos() ?: 'Empresa' }}</h2>
                    <p>NIF: {{ $tenant->nif ?? '—' }} | Tel: {{ $tenant->phone ?? '—' }}</p>
                    <p>{{ $tenant->address ?? '' }}</p>
                </div>
            </div>
            <div class="title">
                <h1>Licença / Falta</h1>
                <p>Nº {{ $leave->leave_number }}</p>
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
                <div class="field"><div class="label">NIF</div><div class="value">{{ $employee->nif ?? '—' }}</div></div>
                <div class="field"><div class="label">Seg. Social</div><div class="value">{{ $employee->social_security_number ?? '—' }}</div></div>
            </div>
        </div>

        {{-- Detalhes da Licença --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-calendar-times"></i> Detalhes da Licença / Falta</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Tipo</div><div class="value">{{ $leave->leave_type_name }}</div></div>
                <div class="field">
                    <div class="label">Estado</div>
                    <div class="value"><span class="badge badge-{{ $leave->status }}">{{ $statusLabels[$leave->status] ?? $leave->status }}</span></div>
                </div>
                <div class="field"><div class="label">Remunerada</div><div class="value">{{ $leave->paid ? 'Sim' : 'Não' }}</div></div>
                <div class="field"><div class="label">Data Início</div><div class="value">{{ $leave->start_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Data Fim</div><div class="value">{{ $leave->end_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Total Dias</div><div class="value">{{ $leave->total_days ?? '—' }}</div></div>
                <div class="field"><div class="label">Dias Úteis</div><div class="value">{{ $leave->working_days ?? '—' }}</div></div>
                <div class="field"><div class="label">Atestado Médico</div><div class="value">{{ $leave->has_medical_certificate ? 'Sim' : 'Não' }}</div></div>
                @if($leave->deduction_amount > 0)
                <div class="field"><div class="label">Valor Desconto</div><div class="value">{{ number_format($leave->deduction_amount, 2, ',', '.') }} Kz</div></div>
                @endif
            </div>
        </div>

        {{-- Motivo --}}
        @if($leave->reason)
        <div class="section">
            <div class="section-title"><i class="fas fa-comment-alt"></i> Motivo</div>
            <div class="info-box">
                <div style="font-size:11px">{{ $leave->reason }}</div>
            </div>
        </div>
        @endif

        {{-- Aprovação --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-check-circle"></i> Aprovação</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Aprovado Por</div><div class="value">{{ $leave->approvedBy->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Data Aprovação</div><div class="value">{{ $leave->approved_at?->format('d/m/Y H:i') ?? '—' }}</div></div>
                <div class="field"><div class="label">Nº Documento</div><div class="value">{{ $leave->leave_number }}</div></div>
            </div>
        </div>

        {{-- Rejeição --}}
        @if($leave->status === 'rejected' && $leave->rejection_reason)
        <div class="section">
            <div class="section-title" style="background:#dc2626"><i class="fas fa-times-circle"></i> Motivo da Rejeição</div>
            <div class="info-box" style="background:#fef2f2;border-color:#fecaca">
                <div style="font-size:11px;color:#991b1b">{{ $leave->rejection_reason }}</div>
            </div>
        </div>
        @endif

        {{-- Notas --}}
        @if($leave->notes)
        <div class="section">
            <div class="section-title"><i class="fas fa-sticky-note"></i> Observações</div>
            <div class="info-box">
                <div style="font-size:11px">{{ $leave->notes }}</div>
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
                <p style="text-align:center;font-size:9px;color:#64748b;margin-top:4px">{{ $leave->approvedBy->name ?? '________________' }}</p>
            </div>
        </div>

        <div class="footer">
            {{ optional($tenant)->nomeParaDocumentos() ?: 'SOS ERP' }} — Comprovativo de Licença/Falta nº {{ $leave->leave_number }} — Emitido em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
