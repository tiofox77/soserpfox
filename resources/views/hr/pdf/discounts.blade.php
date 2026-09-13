<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Desconto Salarial #{{ $discount->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 9px; line-height: 1.2; color: #1f2937; padding: 10px 15px; max-width: 210mm; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 2px solid #dc2626; }
        .logo { max-width: 100px; max-height: 40px; }
        .company-info { text-align: right; flex: 1; }
        .company-info h1 { font-size: 13px; color: #dc2626; margin-bottom: 1px; font-weight: bold; }
        .company-info p { font-size: 7px; color: #6b7280; margin: 0; }
        .document-title { text-align: center; margin: 4px 0; padding: 5px; background: #fef2f2; border-left: 3px solid #dc2626; }
        .document-title h2 { font-size: 12px; color: #991b1b; margin-bottom: 1px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
        .document-title p { font-size: 7px; color: #6b7280; }
        .info-table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 8px; }
        .info-table th { background: #f3f4f6; color: #374151; font-weight: bold; text-align: left; padding: 3px 5px; border: 1px solid #d1d5db; font-size: 8px; text-transform: uppercase; }
        .info-table td { padding: 3px 5px; border: 1px solid #e5e7eb; color: #1f2937; }
        .info-table td.label { background: #f9fafb; font-weight: 600; color: #4b5563; width: 35%; }
        .info-table td.value { color: #111827; font-weight: 500; }
        .amount-box { background: #fef2f2; border: 2px solid #dc2626; border-radius: 6px; padding: 6px; text-align: center; margin: 4px 0; }
        .amount-box .label { font-size: 8px; color: #991b1b; margin-bottom: 2px; font-weight: bold; text-transform: uppercase; }
        .amount-box .value { font-size: 16px; color: #7f1d1d; font-weight: bold; letter-spacing: 0.5px; }
        .amount-box .installments { font-size: 8px; color: #991b1b; margin-top: 2px; font-weight: 600; }
        .section-title { font-size: 9px; color: #dc2626; font-weight: bold; margin: 4px 0 2px 0; padding: 3px 6px; background: #fef2f2; border-left: 2px solid #dc2626; text-transform: uppercase; }
        .signature-section { margin-top: 6px; page-break-inside: avoid; }
        .signatures { display: flex; justify-content: space-between; gap: 15px; margin-top: 4px; }
        .signature-box { flex: 1; text-align: center; border: 1px solid #d1d5db; padding: 6px; border-radius: 4px; background: #f9fafb; min-height: 60px; }
        .signature-title { font-size: 7px; color: #dc2626; font-weight: bold; margin-bottom: 3px; text-transform: uppercase; }
        .signature-line { border-top: 1px solid #374151; margin-top: 20px; padding-top: 3px; }
        .signature-label { font-size: 7px; color: #6b7280; margin-top: 2px; font-weight: 600; text-transform: uppercase; }
        .signature-name { font-weight: bold; margin-top: 1px; font-size: 8px; color: #111827; }
        .notes { background: #fef3c7; border-left: 2px solid #f59e0b; padding: 4px 6px; margin: 3px 0; border-radius: 3px; }
        .notes-title { font-weight: bold; color: #92400e; margin-bottom: 2px; font-size: 7px; text-transform: uppercase; }
        .notes-content { font-size: 7px; color: #78350f; line-height: 1.2; }
        .footer { margin-top: 4px; padding-top: 3px; border-top: 1px solid #d1d5db; text-align: center; font-size: 6px; color: #9ca3af; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <div>
            @php
                $tenant = $discount->employee->tenant ?? auth()->user()->activeTenant();
                $logoPath = $tenant && $tenant->logo ? public_path('storage/' . $tenant->logo) : null;
            @endphp
            @if($logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" class="logo" alt="Logo">
            @endif
        </div>
        <div class="company-info">
            <h1>{{ $tenant->name ?? 'Empresa' }}</h1>
            <p>{{ $tenant->address ?? '' }}</p>
            <p>NIF: {{ $tenant->nif ?? '' }} | Tel: {{ $tenant->phone ?? '' }}</p>
        </div>
    </div>

    {{-- Title --}}
    <div class="document-title">
        <h2>Desconto Salarial</h2>
        <p>Documento N.º {{ $discount->id }} | Emitido em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Employee Info --}}
    <div class="section-title">Dados do Funcionário</div>
    <table class="info-table">
        <tr>
            <td class="label">Nome Completo</td>
            <td class="value">{{ $discount->employee->full_name }}</td>
            <td class="label">N.º Funcionário</td>
            <td class="value">{{ $discount->employee->employee_number }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td class="value">{{ $discount->employee->department->name ?? '-' }}</td>
            <td class="label">Cargo</td>
            <td class="value">{{ $discount->employee->position->name ?? '-' }}</td>
        </tr>
    </table>

    {{-- Discount Details --}}
    <div class="section-title">Detalhes do Desconto</div>
    <table class="info-table">
        <tr>
            <td class="label">Tipo de Desconto</td>
            <td class="value">
                @php
                    $types = [
                        'salary_advance' => 'Adiantamento Salarial',
                        'loan' => 'Empréstimo',
                        'damage' => 'Dano/Prejuízo',
                        'absence' => 'Falta Injustificada',
                        'tax_adjustment' => 'Ajuste Fiscal',
                        'insurance' => 'Seguro',
                        'union_fee' => 'Quota Sindical',
                        'other' => 'Outro',
                    ];
                @endphp
                {{ $types[$discount->discount_type] ?? $discount->discount_type }}
            </td>
            <td class="label">Estado</td>
            <td class="value">
                @php
                    $statuses = [
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                        'completed' => 'Concluído',
                        'cancelled' => 'Cancelado',
                    ];
                @endphp
                {{ $statuses[$discount->status] ?? $discount->status }}
            </td>
        </tr>
        <tr>
            <td class="label">Data do Pedido</td>
            <td class="value">{{ $discount->request_date?->format('d/m/Y') ?? '-' }}</td>
            <td class="label">Aprovado por</td>
            <td class="value">{{ $discount->approvedBy->name ?? '-' }}</td>
        </tr>
    </table>

    {{-- Amount --}}
    <div class="amount-box">
        <div class="label">Valor Total do Desconto</div>
        <div class="value">{{ number_format($discount->amount, 2, ',', '.') }} Kz</div>
        @if($discount->installments > 1)
        <div class="installments">
            {{ $discount->installments }} prestações de {{ number_format($discount->installment_amount, 2, ',', '.') }} Kz
            @php $paid = $discount->installments - $discount->remaining_installments; @endphp
            @if($paid > 0)
                | {{ $paid }} pagas | Restam {{ $discount->remaining_installments }}
            @endif
        </div>
        @endif
    </div>

    {{-- Description --}}
    @if($discount->reason)
    <div class="notes">
        <div class="notes-title">Descrição / Motivo</div>
        <div class="notes-content">{{ $discount->reason }}</div>
    </div>
    @endif

    @if($discount->notes)
    <div class="notes">
        <div class="notes-title">Observações</div>
        <div class="notes-content">{{ $discount->notes }}</div>
    </div>
    @endif

    {{-- Signatures --}}
    <div class="signature-section">
        <div class="section-title">Assinaturas</div>
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-title">Funcionário</div>
                <div class="signature-line"></div>
                <div class="signature-name">{{ $discount->employee->full_name }}</div>
                <div class="signature-label">Assinatura</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">Aprovado por</div>
                <div class="signature-line"></div>
                <div class="signature-name">{{ $discount->approvedBy->name ?? '________________' }}</div>
                <div class="signature-label">Assinatura</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">Recursos Humanos</div>
                <div class="signature-line"></div>
                <div class="signature-name">________________</div>
                <div class="signature-label">Assinatura e Carimbo</div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>Documento gerado automaticamente pelo sistema SOS ERP em {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>Este documento é válido sem assinatura digital conforme legislação vigente.</p>
    </div>
</body>
</html>
