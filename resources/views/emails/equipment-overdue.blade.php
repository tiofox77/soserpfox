<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta de Equipamentos</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .alert-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #ef4444;
            background: #fee2e2;
            border-radius: 5px;
        }
        .warning-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #f59e0b;
            background: #fef3c7;
            border-radius: 5px;
        }
        .equipment-item {
            background: #f9fafb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .equipment-name {
            font-weight: bold;
            color: #1f2937;
            font-size: 16px;
        }
        .equipment-info {
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }
        .days-overdue {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        .days-warning {
            display: inline-block;
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">⏰</div>
            <h1>Alerta de Equipamentos</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Sistema de Gestão de Equipamentos</p>
        </div>

        <p>Olá! Este é um alerta automático sobre equipamentos que requerem sua atenção.</p>

        @if($overdueEquipment->count() > 0)
        <div class="alert-section">
            <h2 style="margin-top: 0; color: #dc2626; font-size: 18px;">
                🚨 {{ $overdueEquipment->count() }} Equipamento(s) Atrasado(s)
            </h2>
            <p>Os seguintes equipamentos estão com devolução atrasada:</p>
            
            @foreach($overdueEquipment as $equipment)
            <div class="equipment-item">
                <div class="equipment-name">{{ $equipment->name }}</div>
                <div class="equipment-info">
                    <strong>Cliente:</strong> {{ $equipment->borrowedToClient->name ?? 'N/A' }}<br>
                    <strong>Data de Devolução:</strong> {{ $equipment->return_due_date->format('d/m/Y') }}<br>
                    @if($equipment->serial_number)
                    <strong>Número de Série:</strong> {{ $equipment->serial_number }}<br>
                    @endif
                    <strong>Localização:</strong> {{ $equipment->location ?? 'Não informada' }}
                </div>
                <span class="days-overdue">{{ $equipment->days_overdue }} dias de atraso</span>
            </div>
            @endforeach
        </div>
        @endif

        @if($maintenanceDue->count() > 0)
        <div class="warning-section">
            <h2 style="margin-top: 0; color: #d97706; font-size: 18px;">
                🔧 {{ $maintenanceDue->count() }} Manutenção(ões) Próxima(s)
            </h2>
            <p>Os seguintes equipamentos precisam de manutenção em breve:</p>
            
            @foreach($maintenanceDue as $equipment)
            <div class="equipment-item">
                <div class="equipment-name">{{ $equipment->name }}</div>
                <div class="equipment-info">
                    <strong>Data da Manutenção:</strong> {{ $equipment->next_maintenance_date->format('d/m/Y') }}<br>
                    @if($equipment->serial_number)
                    <strong>Número de Série:</strong> {{ $equipment->serial_number }}<br>
                    @endif
                    <strong>Localização:</strong> {{ $equipment->location ?? 'Não informada' }}<br>
                    <strong>Total de Usos:</strong> {{ $equipment->total_uses }}
                </div>
                <span class="days-warning">
                    {{ $equipment->next_maintenance_date->diffForHumans() }}
                </span>
            </div>
            @endforeach
        </div>
        @endif

        <div style="text-align: center;">
            <a href="{{ route('events.equipment.index') }}" class="button">
                Ver Todos os Equipamentos
            </a>
        </div>

        <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <strong>💡 Dica:</strong> Acesse o dashboard de equipamentos para ver relatórios detalhados e estatísticas em tempo real.
        </div>

        <div class="footer">
            <p>Este é um email automático do Sistema de Gestão de Equipamentos - SOS ERP</p>
            <p>Enviado em {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</body>
</html>
