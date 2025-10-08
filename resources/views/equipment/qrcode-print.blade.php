<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - {{ $equipment->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 20px; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto p-8">
        {{-- Botões de Ação --}}
        <div class="no-print mb-6 flex gap-4">
            <button onclick="window.print()" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-bold flex items-center transition">
                <i class="fas fa-print mr-2"></i>
                Imprimir QR Code
            </button>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-bold flex items-center transition">
                <i class="fas fa-times mr-2"></i>
                Fechar
            </button>
        </div>

        {{-- QR Code Card --}}
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $equipment->name }}</h1>
                <p class="text-gray-600">{{ $equipment->category }}</p>
                @if($equipment->serial_number)
                <p class="text-sm text-gray-500 mt-2">
                    <i class="fas fa-barcode mr-1"></i>
                    {{ $equipment->serial_number }}
                </p>
                @endif
            </div>

            {{-- QR Code --}}
            <div class="flex justify-center mb-8">
                <div class="border-4 border-purple-600 rounded-2xl p-6 bg-white">
                    <img src="{{ route('events.equipment.qrcode', $equipment->id) }}" 
                         alt="QR Code {{ $equipment->name }}"
                         class="w-80 h-80">
                </div>
            </div>

            {{-- Informações --}}
            <div class="grid grid-cols-2 gap-6 text-left border-t pt-6">
                <div>
                    <p class="text-sm text-gray-500 font-semibold mb-1">Status</p>
                    <span class="px-4 py-2 rounded-full text-sm font-bold text-white inline-block" 
                          style="background-color: {{ $equipment->status_color }}">
                        {{ $equipment->status_label }}
                    </span>
                </div>
                
                @if($equipment->location)
                <div>
                    <p class="text-sm text-gray-500 font-semibold mb-1">Localização</p>
                    <p class="text-gray-900 font-bold">
                        <i class="fas fa-map-marker-alt text-purple-600 mr-2"></i>
                        {{ $equipment->location }}
                    </p>
                </div>
                @endif

                <div>
                    <p class="text-sm text-gray-500 font-semibold mb-1">Total de Usos</p>
                    <p class="text-gray-900 font-bold">
                        <i class="fas fa-history text-purple-600 mr-2"></i>
                        {{ $equipment->total_uses }} vezes
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 font-semibold mb-1">Código</p>
                    <p class="text-gray-900 font-bold">#{{ $equipment->id }}</p>
                </div>
            </div>

            {{-- Instruções --}}
            <div class="mt-8 bg-purple-50 rounded-xl p-6 text-left">
                <h3 class="font-bold text-purple-900 mb-3 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Como usar este QR Code
                </h3>
                <ul class="space-y-2 text-sm text-purple-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-purple-600 mr-2 mt-1"></i>
                        <span>Escaneie este código com seu smartphone para acessar as informações do equipamento</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-purple-600 mr-2 mt-1"></i>
                        <span>Use para rastreamento rápido de localização e status</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-purple-600 mr-2 mt-1"></i>
                        <span>Cole este código no equipamento para identificação visual</span>
                    </li>
                </ul>
            </div>

            {{-- Footer --}}
            <div class="mt-8 pt-6 border-t text-center text-sm text-gray-500">
                <p>Gerado em {{ now()->format('d/m/Y H:i') }}</p>
                <p class="mt-2">Sistema de Gestão de Equipamentos - SOS ERP</p>
            </div>
        </div>

        {{-- Grade para Impressão Múltipla (opcional) --}}
        <div class="no-print mt-8">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Imprimir Múltiplos (Grade)</h3>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-3 gap-4">
                    @for($i = 0; $i < 6; $i++)
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                        <img src="{{ route('events.equipment.qrcode', $equipment->id) }}" 
                             alt="QR Code"
                             class="w-32 h-32 mx-auto mb-2">
                        <p class="text-xs font-bold text-gray-700">{{ $equipment->name }}</p>
                        <p class="text-xs text-gray-500">{{ $equipment->serial_number ?? '#' . $equipment->id }}</p>
                    </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print opcional
        // window.onload = () => setTimeout(() => window.print(), 500);
    </script>
</body>
</html>
