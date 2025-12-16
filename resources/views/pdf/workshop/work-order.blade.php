<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço - {{ $workOrder->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin-bottom: 20px; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { font-size: 12px; opacity: 0.9; }
        .info-section { border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .info-title { font-size: 14px; font-weight: bold; color: #667eea; margin-bottom: 10px; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; color: #666; padding: 5px 10px 5px 0; width: 30%; }
        .info-value { display: table-cell; padding: 5px 0; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 15px; font-size: 10px; font-weight: bold; }
        .badge-pending { background: #fbbf24; color: #92400e; }
        .badge-progress { background: #3b82f6; color: white; }
        .badge-completed { background: #10b981; color: white; }
        .badge-delivered { background: #8b5cf6; color: white; }
        .badge-urgent { background: #ef4444; color: white; }
        .badge-high { background: #f97316; color: white; }
        .badge-normal { background: #3b82f6; color: white; }
        .badge-low { background: #6b7280; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #4b5563; color: white; padding: 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        .item-service { background: #eff6ff; }
        .item-part { background: #f0fdf4; }
        .totals-section { background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-top: 15px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #d1d5db; }
        .totals-row.total { font-size: 16px; font-weight: bold; background: #667eea; color: white; padding: 12px; border-radius: 5px; margin-top: 10px; }
        .description-box { background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px; margin: 10px 0; border-radius: 5px; }
        .description-box.diagnosis { background: #eff6ff; border-left-color: #3b82f6; }
        .description-box.work { background: #f0fdf4; border-left-color: #10b981; }
        .description-box.recommendations { background: #fefce8; border-left-color: #eab308; }
        .description-title { font-weight: bold; margin-bottom: 5px; font-size: 11px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 9px; }
        .signature-section { display: table; width: 100%; margin-top: 40px; }
        .signature-box { display: table-cell; width: 48%; text-align: center; }
        .signature-line { border-top: 2px solid #333; margin: 50px 20px 5px 20px; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>ORDEM DE SERVIÇO</h1>
        <p>{{ $workOrder->order_number }} | {{ $workOrder->received_at->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Status e Prioridade --}}
    <div style="margin-bottom: 15px;">
        <strong>Status:</strong> 
        @if($workOrder->status === 'pending')
            <span class="badge badge-pending">PENDENTE</span>
        @elseif($workOrder->status === 'in_progress')
            <span class="badge badge-progress">EM ANDAMENTO</span>
        @elseif($workOrder->status === 'completed')
            <span class="badge badge-completed">CONCLUÍDA</span>
        @elseif($workOrder->status === 'delivered')
            <span class="badge badge-delivered">ENTREGUE</span>
        @endif
        
        <strong style="margin-left: 20px;">Prioridade:</strong> 
        @if($workOrder->priority === 'urgent')
            <span class="badge badge-urgent">URGENTE</span>
        @elseif($workOrder->priority === 'high')
            <span class="badge badge-high">ALTA</span>
        @elseif($workOrder->priority === 'normal')
            <span class="badge badge-normal">NORMAL</span>
        @else
            <span class="badge badge-low">BAIXA</span>
        @endif
    </div>

    {{-- Informações do Veículo --}}
    <div class="info-section">
        <div class="info-title">🚗 DADOS DO VEÍCULO</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Placa:</div>
                <div class="info-value">{{ $workOrder->vehicle->plate }}</div>
                <div class="info-label">Marca/Modelo:</div>
                <div class="info-value">{{ $workOrder->vehicle->brand }} {{ $workOrder->vehicle->model }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Ano:</div>
                <div class="info-value">{{ $workOrder->vehicle->year }}</div>
                <div class="info-label">Cor:</div>
                <div class="info-value">{{ $workOrder->vehicle->color }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">KM Entrada:</div>
                <div class="info-value">{{ number_format($workOrder->mileage_in, 0, ',', '.') }} km</div>
                <div class="info-label">Proprietário:</div>
                <div class="info-value">{{ $workOrder->vehicle->owner_name }}</div>
            </div>
            @if($workOrder->vehicle->owner_phone)
            <div class="info-row">
                <div class="info-label">Telefone:</div>
                <div class="info-value">{{ $workOrder->vehicle->owner_phone }}</div>
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $workOrder->vehicle->owner_email ?? 'N/A' }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Mecânico Responsável --}}
    @if($workOrder->mechanic)
    <div class="info-section">
        <div class="info-title">👨‍🔧 MECÂNICO RESPONSÁVEL</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value">{{ $workOrder->mechanic->name }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Datas --}}
    <div class="info-section">
        <div class="info-title">📅 DATAS</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Entrada:</div>
                <div class="info-value">{{ $workOrder->received_at->format('d/m/Y H:i') }}</div>
                @if($workOrder->scheduled_for)
                <div class="info-label">Agendado:</div>
                <div class="info-value">{{ $workOrder->scheduled_for->format('d/m/Y H:i') }}</div>
                @endif
            </div>
            @if($workOrder->completed_at || $workOrder->delivered_at)
            <div class="info-row">
                @if($workOrder->completed_at)
                <div class="info-label">Conclusão:</div>
                <div class="info-value">{{ $workOrder->completed_at->format('d/m/Y H:i') }}</div>
                @endif
                @if($workOrder->delivered_at)
                <div class="info-label">Entrega:</div>
                <div class="info-value">{{ $workOrder->delivered_at->format('d/m/Y H:i') }}</div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Descrições --}}
    <div class="description-box">
        <div class="description-title">⚠️ PROBLEMA RELATADO</div>
        <div>{{ $workOrder->problem_description }}</div>
    </div>

    @if($workOrder->diagnosis)
    <div class="description-box diagnosis">
        <div class="description-title">🔍 DIAGNÓSTICO</div>
        <div>{{ $workOrder->diagnosis }}</div>
    </div>
    @endif

    @if($workOrder->work_performed)
    <div class="description-box work">
        <div class="description-title">🔧 TRABALHO REALIZADO</div>
        <div>{{ $workOrder->work_performed }}</div>
    </div>
    @endif

    @if($workOrder->recommendations)
    <div class="description-box recommendations">
        <div class="description-title">💡 RECOMENDAÇÕES</div>
        <div>{{ $workOrder->recommendations }}</div>
    </div>
    @endif

    {{-- Itens (Serviços e Peças) --}}
    @if($workOrder->items->count() > 0)
    <div style="margin-top: 20px;">
        <h3 style="color: #667eea; margin-bottom: 10px;">SERVIÇOS E PEÇAS</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">TIPO</th>
                    <th style="width: 15%;">CÓDIGO</th>
                    <th style="width: 35%;">DESCRIÇÃO</th>
                    <th style="width: 10%; text-align: center;">QTD</th>
                    <th style="width: 15%; text-align: right;">PREÇO UNIT.</th>
                    <th style="width: 15%; text-align: right;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->items as $item)
                <tr class="{{ $item->type === 'service' ? 'item-service' : 'item-part' }}">
                    <td style="font-weight: bold; font-size: 9px;">
                        {{ $item->type === 'service' ? 'SERVIÇO' : 'PEÇA' }}
                    </td>
                    <td style="font-family: monospace;">{{ $item->code }}</td>
                    <td>
                        <strong>{{ $item->name }}</strong>
                        @if($item->description)
                            <br><span style="font-size: 9px; color: #666;">{{ $item->description }}</span>
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: bold;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">{{ number_format($item->unit_price, 2, ',', '.') }} Kz</td>
                    <td style="text-align: right; font-weight: bold;">{{ number_format($item->subtotal, 2, ',', '.') }} Kz</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Totais --}}
    <div class="totals-section">
        <div class="totals-row">
            <span>Mão de Obra:</span>
            <span style="font-weight: bold;">{{ number_format($workOrder->labor_total, 2, ',', '.') }} Kz</span>
        </div>
        <div class="totals-row">
            <span>Peças:</span>
            <span style="font-weight: bold;">{{ number_format($workOrder->parts_total, 2, ',', '.') }} Kz</span>
        </div>
        <div class="totals-row">
            <span>Subtotal:</span>
            <span style="font-weight: bold;">{{ number_format($workOrder->labor_total + $workOrder->parts_total, 2, ',', '.') }} Kz</span>
        </div>
        @if($workOrder->discount > 0)
        <div class="totals-row">
            <span>Desconto:</span>
            <span style="font-weight: bold; color: #ef4444;">-{{ number_format($workOrder->discount, 2, ',', '.') }} Kz</span>
        </div>
        @endif
        <div class="totals-row">
            <span>IVA (14%):</span>
            <span style="font-weight: bold;">{{ number_format($workOrder->tax, 2, ',', '.') }} Kz</span>
        </div>
        <div class="totals-row total">
            <span>TOTAL:</span>
            <span>{{ number_format($workOrder->total, 2, ',', '.') }} Kz</span>
        </div>
    </div>

    {{-- Assinaturas --}}
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Mecânico Responsável</strong>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Cliente</strong>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>Documento gerado em {{ now()->format('d/m/Y H:i') }}</p>
        <p>Este documento é válido como comprovante de serviço prestado</p>
        @if($workOrder->warranty_expires)
        <p style="margin-top: 10px; font-weight: bold;">
            Garantia válida até: {{ $workOrder->warranty_expires->format('d/m/Y') }}
        </p>
        @endif
    </div>
</body>
</html>
