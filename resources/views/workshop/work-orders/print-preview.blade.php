<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Serviço - {{ $workOrder->order_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Estilos para visualização na tela */
        @media screen {
            body {
                background: #f3f4f6;
                padding: 20px;
            }
            .print-container {
                max-width: 210mm;
                margin: 0 auto;
                background: white;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                border-radius: 8px;
            }
            .no-print {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
            }
        }

        /* Estilos para impressão - COMPACTO para 1 página */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            html, body {
                background: white;
                padding: 0;
                margin: 0;
                font-size: 9pt !important;
            }
            .print-container {
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
            .page-break-avoid {
                page-break-inside: avoid;
            }
        }

        /* Estilos comuns */
        .header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .info-section {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .description-problem {
            background: #fef2f2;
            border-left: 3px solid #ef4444;
        }
        .description-diagnosis {
            background: #eff6ff;
            border-left: 3px solid #3b82f6;
        }
        .description-work {
            background: #f0fdf4;
            border-left: 3px solid #10b981;
        }
        .description-recommendations {
            background: #fefce8;
            border-left: 3px solid #eab308;
        }
        .item-service {
            background: #eff6ff;
        }
        .item-part {
            background: #f0fdf4;
        }
    </style>
</head>
<body class="text-gray-800">
    {{-- Botões de ação (não imprime) --}}
    <div class="no-print flex gap-3">
        <button onclick="window.print()" 
                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold shadow-lg flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir
        </button>
        <button onclick="window.close()" 
                class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold shadow-lg flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Fechar
        </button>
    </div>

    <div class="print-container">
        {{-- Header Compacto --}}
        <div class="header-gradient text-white px-4 py-3 rounded-t-lg print:rounded-none">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold tracking-tight">ORDEM DE SERVIÇO</h1>
                    <p class="text-purple-200 text-xs">Documento de controle de serviço automotivo</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold">{{ $workOrder->order_number }}</p>
                    <p class="text-purple-200 text-xs">{{ $workOrder->received_at->format('d/m/Y') }}</p>
                </div>
            </div>
        </div>

        <div class="p-4 space-y-3">
            {{-- Status e Prioridade --}}
            <div class="flex items-center gap-4 pb-2 border-b border-gray-200">
                <div class="flex items-center gap-1">
                    <span class="text-xs font-semibold text-gray-600">Status:</span>
                    @if($workOrder->status === 'pending')
                        <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">PENDENTE</span>
                    @elseif($workOrder->status === 'in_progress')
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-bold">EM ANDAMENTO</span>
                    @elseif($workOrder->status === 'completed')
                        <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-bold">CONCLUÍDA</span>
                    @elseif($workOrder->status === 'delivered')
                        <span class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs font-bold">ENTREGUE</span>
                    @endif
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-xs font-semibold text-gray-600">Prioridade:</span>
                    @if($workOrder->priority === 'urgent')
                        <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded-full text-xs font-bold">URGENTE</span>
                    @elseif($workOrder->priority === 'high')
                        <span class="px-2 py-0.5 bg-orange-100 text-orange-800 rounded-full text-xs font-bold">ALTA</span>
                    @elseif($workOrder->priority === 'normal')
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-bold">NORMAL</span>
                    @else
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">BAIXA</span>
                    @endif
                </div>
            </div>

            {{-- Dados do Veículo + Datas (lado a lado) --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="info-section p-3">
                    <h3 class="text-xs font-bold text-purple-700 mb-2 flex items-center gap-1 border-b border-purple-200 pb-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        DADOS DO VEÍCULO
                    </h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div><span class="font-semibold text-gray-500">Placa:</span> <span class="font-bold">{{ $workOrder->vehicle->plate }}</span></div>
                        <div><span class="font-semibold text-gray-500">Marca:</span> {{ $workOrder->vehicle->brand }} {{ $workOrder->vehicle->model }}</div>
                        <div><span class="font-semibold text-gray-500">Ano:</span> {{ $workOrder->vehicle->year }}</div>
                        <div><span class="font-semibold text-gray-500">Cor:</span> {{ $workOrder->vehicle->color }}</div>
                        <div><span class="font-semibold text-gray-500">KM:</span> {{ number_format($workOrder->mileage_in, 0, ',', '.') }} km</div>
                        <div><span class="font-semibold text-gray-500">Proprietário:</span> {{ $workOrder->vehicle->owner_name }}</div>
                        @if($workOrder->vehicle->owner_phone)
                        <div><span class="font-semibold text-gray-500">Tel:</span> {{ $workOrder->vehicle->owner_phone }}</div>
                        @endif
                        @if($workOrder->vehicle->owner_email)
                        <div><span class="font-semibold text-gray-500">Email:</span> {{ $workOrder->vehicle->owner_email }}</div>
                        @endif
                    </div>
                </div>

                <div class="info-section p-3">
                    <h3 class="text-xs font-bold text-purple-700 mb-2 flex items-center gap-1 border-b border-purple-200 pb-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        DATAS
                    </h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div><span class="font-semibold text-gray-500">Entrada:</span> {{ $workOrder->received_at->format('d/m/Y H:i') }}</div>
                        @if($workOrder->scheduled_for)
                        <div><span class="font-semibold text-gray-500">Agendado:</span> {{ $workOrder->scheduled_for->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($workOrder->completed_at)
                        <div><span class="font-semibold text-gray-500">Conclusão:</span> {{ $workOrder->completed_at->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($workOrder->delivered_at)
                        <div><span class="font-semibold text-gray-500">Entrega:</span> {{ $workOrder->delivered_at->format('d/m/Y H:i') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Descrições (compactas) --}}
            <div class="space-y-2">
                <div class="description-problem px-3 py-2 rounded-r">
                    <h4 class="font-bold text-red-700 text-xs mb-0.5">⚠ PROBLEMA RELATADO</h4>
                    <p class="text-gray-700 text-xs">{{ $workOrder->problem_description }}</p>
                </div>

                @if($workOrder->diagnosis)
                <div class="description-diagnosis px-3 py-2 rounded-r">
                    <h4 class="font-bold text-blue-700 text-xs mb-0.5">🔍 DIAGNÓSTICO</h4>
                    <p class="text-gray-700 text-xs">{{ $workOrder->diagnosis }}</p>
                </div>
                @endif

                @if($workOrder->work_performed)
                <div class="description-work px-3 py-2 rounded-r">
                    <h4 class="font-bold text-green-700 text-xs mb-0.5">🔧 TRABALHO REALIZADO</h4>
                    <p class="text-gray-700 text-xs">{{ $workOrder->work_performed }}</p>
                </div>
                @endif

                @if($workOrder->recommendations)
                <div class="description-recommendations px-3 py-2 rounded-r">
                    <h4 class="font-bold text-yellow-700 text-xs mb-0.5">💡 RECOMENDAÇÕES</h4>
                    <p class="text-gray-700 text-xs">{{ $workOrder->recommendations }}</p>
                </div>
                @endif
            </div>

            {{-- Serviços e Peças (compacto) --}}
            @if($workOrder->items->count() > 0)
            <div class="page-break-avoid">
                <h3 class="text-xs font-bold text-purple-700 mb-1">📋 SERVIÇOS E PEÇAS</h3>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="text-left py-1 px-2 font-semibold">TIPO</th>
                            <th class="text-left py-1 px-2 font-semibold">CÓDIGO</th>
                            <th class="text-left py-1 px-2 font-semibold">DESCRIÇÃO</th>
                            <th class="text-center py-1 px-2 font-semibold">QTD</th>
                            <th class="text-right py-1 px-2 font-semibold">PREÇO UNIT.</th>
                            <th class="text-right py-1 px-2 font-semibold">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workOrder->items as $item)
                        <tr class="{{ $item->type === 'service' ? 'item-service' : 'item-part' }} border-b border-gray-200">
                            <td class="py-1 px-2 font-bold">{{ $item->type === 'service' ? 'SERVIÇO' : 'PEÇA' }}</td>
                            <td class="py-1 px-2 font-mono">{{ $item->code }}</td>
                            <td class="py-1 px-2 font-semibold">{{ $item->name }}</td>
                            <td class="py-1 px-2 text-center font-bold">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->unit_price, 2, ',', '.') }} Kz</td>
                            <td class="py-1 px-2 text-right font-bold">{{ number_format($item->subtotal, 2, ',', '.') }} Kz</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Totais + Assinaturas (lado a lado) --}}
            <div class="grid grid-cols-2 gap-4 mt-3 page-break-avoid">
                {{-- Totais --}}
                <div class="bg-gray-50 border border-gray-200 rounded p-3">
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between py-0.5 border-b border-gray-200">
                            <span class="text-gray-600">Mão de Obra:</span>
                            <span class="font-bold">{{ number_format($workOrder->labor_total, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex justify-between py-0.5 border-b border-gray-200">
                            <span class="text-gray-600">Peças:</span>
                            <span class="font-bold">{{ number_format($workOrder->parts_total, 2, ',', '.') }} Kz</span>
                        </div>
                        @if($workOrder->discount > 0)
                        <div class="flex justify-between py-0.5 border-b border-gray-200">
                            <span class="text-gray-600">Desconto:</span>
                            <span class="font-bold text-red-600">-{{ number_format($workOrder->discount, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        <div class="flex justify-between py-0.5 border-b border-gray-200">
                            <span class="text-gray-600">IVA (14%):</span>
                            <span class="font-bold">{{ number_format($workOrder->tax, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex justify-between py-2 bg-purple-600 text-white rounded px-3 mt-1">
                            <span class="font-bold">TOTAL:</span>
                            <span class="font-bold">{{ number_format($workOrder->total, 2, ',', '.') }} Kz</span>
                        </div>
                    </div>
                </div>

                {{-- Assinaturas --}}
                <div class="flex flex-col justify-end">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <div class="border-t-2 border-gray-800 mx-2 pt-1"></div>
                            <p class="font-bold text-xs">Mecânico</p>
                            @if($workOrder->mechanic)
                                <p class="text-xs text-gray-500">{{ $workOrder->mechanic->name }}</p>
                            @endif
                        </div>
                        <div class="text-center">
                            <div class="border-t-2 border-gray-800 mx-2 pt-1"></div>
                            <p class="font-bold text-xs">Cliente</p>
                            <p class="text-xs text-gray-500">{{ $workOrder->vehicle->owner_name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="text-center mt-3 pt-2 border-t border-gray-200 text-xs text-gray-400">
                <p>Documento gerado em {{ now()->format('d/m/Y H:i') }} | Este documento é válido como comprovante de serviço prestado
                @if($workOrder->warranty_expires)
                 | <span class="font-bold text-purple-700">Garantia até: {{ $workOrder->warranty_expires->format('d/m/Y') }}</span>
                @endif
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus na janela para que Ctrl+P funcione imediatamente
        window.focus();
    </script>
</body>
</html>
