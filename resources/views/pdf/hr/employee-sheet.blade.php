<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha do Trabalhador — {{ $employee->full_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 11px; color: #1a1a1a; background: #f1f5f9; }

        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: linear-gradient(135deg, #2563eb, #4f46e5); color: #fff;
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,.25);
        }
        .toolbar button { background: #fff; color: #2563eb; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; margin-left: 8px; }
        .toolbar button.close-btn { background: rgba(255,255,255,.2); color: #fff; }
        .toolbar button:hover { opacity: .85; }

        .page {
            width: 210mm; margin: 80px auto 20px; background: #fff;
            padding: 20mm 15mm; box-shadow: 0 2px 12px rgba(0,0,0,.1);
        }

        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 16px; }
        .header .company { display: flex; align-items: center; gap: 12px; }
        .header .company img { height: 50px; }
        .header .company h2 { font-size: 16px; color: #1e40af; }
        .header .company p { font-size: 10px; color: #666; }
        .header .title { text-align: right; }
        .header .title h1 { font-size: 18px; color: #1e40af; text-transform: uppercase; }
        .header .title p { font-size: 10px; color: #666; }

        .section { margin-bottom: 14px; }
        .section-title {
            font-size: 12px; font-weight: 700; color: #fff; background: #2563eb;
            padding: 5px 10px; border-radius: 4px; margin-bottom: 8px;
        }
        .grid { display: grid; gap: 2px; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .field { background: #f8fafc; padding: 6px 8px; border: 1px solid #e2e8f0; }
        .field .label { font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: .5px; }
        .field .value { font-size: 11px; font-weight: 600; color: #1e293b; margin-top: 1px; }

        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; text-transform: uppercase;
        }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }

        .photo-placeholder {
            width: 90px; height: 110px; background: #e2e8f0; border: 2px solid #cbd5e1;
            display: flex; align-items: center; justify-content: center; border-radius: 6px;
            font-size: 28px; color: #94a3b8;
        }

        .footer { margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; text-align: center; font-size: 9px; color: #94a3b8; }

        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 10mm; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
    {{-- Toolbar --}}
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px">
            <i class="fas fa-user" style="font-size:20px"></i>
            <div>
                <div style="font-weight:700;font-size:14px">Ficha do Trabalhador</div>
                <div style="font-size:11px;opacity:.8">{{ $employee->full_name }}</div>
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
        $contract = $employee->activeContract;
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
                <h1>Ficha do Trabalhador</h1>
                <p>Emitido: {{ now()->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        {{-- Info Principal + Foto --}}
        <div style="display:flex; gap:16px; margin-bottom:14px;">
            <div style="flex:1">
                <div class="section">
                    <div class="section-title"><i class="fas fa-user-circle"></i> Dados Pessoais</div>
                    <div class="grid grid-3">
                        <div class="field"><div class="label">Nome Completo</div><div class="value">{{ $employee->full_name }}</div></div>
                        <div class="field"><div class="label">Nº Funcionário</div><div class="value">{{ $employee->employee_number ?? '—' }}</div></div>
                        <div class="field">
                            <div class="label">Estado</div>
                            <div class="value">
                                <span class="badge {{ $employee->status === 'active' ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $employee->status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>
                        <div class="field"><div class="label">Data Nascimento</div><div class="value">{{ $employee->birth_date?->format('d/m/Y') ?? '—' }}</div></div>
                        <div class="field"><div class="label">Idade</div><div class="value">{{ $employee->age ?? '—' }} anos</div></div>
                        <div class="field"><div class="label">Género</div><div class="value">{{ $employee->gender === 'male' ? 'Masculino' : ($employee->gender === 'female' ? 'Feminino' : '—') }}</div></div>
                    </div>
                </div>
            </div>
            <div class="photo-placeholder">
                @if($employee->photo)
                    <img src="{{ asset('storage/' . $employee->photo) }}" style="width:100%;height:100%;object-fit:cover;border-radius:4px" alt="Foto">
                @else
                    <i class="fas fa-user"></i>
                @endif
            </div>
        </div>

        {{-- Documentos --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-id-card"></i> Documentos</div>
            <div class="grid grid-4">
                <div class="field"><div class="label">NIF</div><div class="value">{{ $employee->nif ?? '—' }}</div></div>
                <div class="field"><div class="label">Nº BI</div><div class="value">{{ $employee->bi_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Validade BI</div><div class="value">{{ $employee->bi_expiry_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Seg. Social</div><div class="value">{{ $employee->social_security_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Passaporte</div><div class="value">{{ $employee->passport_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Val. Passaporte</div><div class="value">{{ $employee->passport_expiry_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Carta Condução</div><div class="value">{{ $employee->driver_license_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Categoria</div><div class="value">{{ $employee->driver_license_category ?? '—' }}</div></div>
            </div>
        </div>

        {{-- Contato --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-address-book"></i> Contato</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Email</div><div class="value">{{ $employee->email ?? '—' }}</div></div>
                <div class="field"><div class="label">Telefone</div><div class="value">{{ $employee->phone ?? '—' }}</div></div>
                <div class="field"><div class="label">Telemóvel</div><div class="value">{{ $employee->mobile ?? '—' }}</div></div>
            </div>
            <div class="grid grid-2" style="margin-top:2px">
                <div class="field"><div class="label">Morada</div><div class="value">{{ $employee->address ?? '—' }}</div></div>
                <div class="field"><div class="label">Cidade / Província</div><div class="value">{{ collect([$employee->city, $employee->province])->filter()->implode(', ') ?: '—' }}</div></div>
            </div>
        </div>

        {{-- Dados Profissionais --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-briefcase"></i> Dados Profissionais</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Departamento</div><div class="value">{{ $employee->department->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Cargo/Função</div><div class="value">{{ $employee->position->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Turno</div><div class="value">{{ $employee->shift->name ?? '—' }}</div></div>
                <div class="field"><div class="label">Superior Hierárquico</div><div class="value">{{ $employee->manager->full_name ?? '—' }}</div></div>
                <div class="field"><div class="label">Data de Admissão</div><div class="value">{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Antiguidade</div><div class="value">{{ $employee->years_of_service }} ano(s)</div></div>
                <div class="field"><div class="label">Tipo Contrato</div><div class="value">{{ $contract->contract_type ?? '—' }}</div></div>
                <div class="field"><div class="label">Início Contrato</div><div class="value">{{ $contract?->start_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><div class="label">Fim Contrato</div><div class="value">{{ $contract?->end_date?->format('d/m/Y') ?? 'Indeterminado' }}</div></div>
            </div>
        </div>

        {{-- Remuneração --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-money-bill-wave"></i> Remuneração</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Salário Base</div><div class="value">{{ number_format($contract->base_salary ?? $employee->salary ?? 0, 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Sub. Alimentação</div><div class="value">{{ number_format((float)\App\Models\HR\HRSetting::get('monthly_food_allowance', 0), 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Sub. Transporte</div><div class="value">{{ number_format((float)\App\Models\HR\HRSetting::get('monthly_transport_allowance', 0), 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Sub. Habitação</div><div class="value">{{ number_format($contract->housing_allowance ?? 0, 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Bônus</div><div class="value">{{ number_format($employee->bonus ?? 0, 2, ',', '.') }} Kz</div></div>
                <div class="field"><div class="label">Abono Família</div><div class="value">{{ number_format($employee->family_allowance ?? 0, 2, ',', '.') }} Kz</div></div>
            </div>
        </div>

        {{-- Dados Bancários --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-university"></i> Dados Bancários</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Banco</div><div class="value">{{ $employee->bank_name ?? '—' }}</div></div>
                <div class="field"><div class="label">Nº Conta</div><div class="value">{{ $employee->bank_account ?? '—' }}</div></div>
                <div class="field"><div class="label">IBAN</div><div class="value">{{ $employee->iban ?? '—' }}</div></div>
            </div>
        </div>

        {{-- Seguro --}}
        <div class="section">
            <div class="section-title"><i class="fas fa-shield-alt"></i> Seguros e Benefícios</div>
            <div class="grid grid-3">
                <div class="field"><div class="label">Seguro Saúde</div><div class="value">{{ $employee->health_insurance_number ?? '—' }}</div></div>
                <div class="field"><div class="label">Seguradora</div><div class="value">{{ $employee->health_insurance_provider ?? '—' }}</div></div>
                <div class="field"><div class="label">Validade Seguro</div><div class="value">{{ $employee->health_insurance_expiry_date?->format('d/m/Y') ?? '—' }}</div></div>
            </div>
        </div>

        {{-- Notas --}}
        @if($employee->notes)
        <div class="section">
            <div class="section-title"><i class="fas fa-sticky-note"></i> Observações</div>
            <div class="field" style="background:#fffbeb;border-color:#fde68a">
                <div class="value" style="font-weight:400">{{ $employee->notes }}</div>
            </div>
        </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            {{ optional($tenant)->nomeParaDocumentos() ?: 'SOS ERP' }} — Ficha do Trabalhador gerada em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
