<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; color: #7c3aed; }
        .header .company { font-size: 13px; font-weight: bold; }
        .header .meta { font-size: 10px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3e8ff; color: #6b21a8; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        .type-row td { background: #faf5ff; font-weight: bold; color: #7c3aed; }
        .subtotal td { font-weight: bold; border-top: 1px solid #c4b5fd; }
        .total td { font-weight: bold; font-size: 13px; background: #ede9fe; border-top: 2px solid #7c3aed; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company['name'] ?? 'Empresa' }}</div>
        @if(!empty($company['nif']))<div class="meta">NIF: {{ $company['nif'] }}</div>@endif
        <h1>Mapa de Retenções na Fonte</h1>
        <div class="meta">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Documento</th>
                <th>Conta</th>
                <th>Descrição</th>
                <th class="num">Valor Retido (Kz)</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($data['types'] ?? []) as $type)
                <tr class="type-row"><td colspan="5">{{ $type['name'] }} — {{ $type['count'] }} mov.</td></tr>
                @forelse($type['lines'] as $line)
                    <tr>
                        <td>{{ $line['date'] ? \Carbon\Carbon::parse($line['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $line['ref'] ?: '-' }}</td>
                        <td>{{ $line['account_code'] }} - {{ $line['account_name'] }}</td>
                        <td>{{ $line['narration'] ?: '-' }}</td>
                        <td class="num">{{ number_format($line['amount'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:#9ca3af">Sem movimentos.</td></tr>
                @endforelse
                <tr class="subtotal"><td colspan="4">Subtotal {{ $type['name'] }}</td><td class="num">{{ number_format($type['total'], 2, ',', '.') }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="4">TOTAL RETIDO</td><td class="num">{{ number_format($data['total'] ?? 0, 2, ',', '.') }}</td></tr>
        </tbody>
    </table>
</body>
</html>
