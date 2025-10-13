<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-circle text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Bem-vindo, {{ $client->name }}!</h2>
                    <p class="text-blue-100 text-sm">Gerencie suas faturas, eventos e documentos</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-file-invoice text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Total de Faturas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['total_invoices'] }}</p>
            <p class="text-xs text-gray-500">No total</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/50 icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Pendentes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['pending_invoices'] }}</p>
            <p class="text-xs text-gray-500">Aguardando pagamento</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Pagas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['paid_invoices'] }}</p>
            <p class="text-xs text-gray-500">Faturas quitadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Total Faturado</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($stats['total_amount'], 2, ',', '.') }} Kz</p>
            <p class="text-xs text-gray-500">Valor acumulado</p>
        </div>
    </div>

    {{-- Últimas Faturas --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-file-invoice mr-2 text-blue-600"></i>Últimas Faturas
                </h2>
                @if(Route::has('client.invoices'))
                    <a href="{{ route('client.invoices') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Ver todas <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-500">{{ $invoice->invoice_date->format('d/m/Y') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($invoice->total ?? 0, 2, ',', '.') }} Kz</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($invoice->status === 'paid')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>Paga
                                    </span>
                                @elseif($invoice->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                        <i class="fas fa-clock mr-1"></i>Pendente
                                    </span>
                                @elseif($invoice->status === 'cancelled')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-times mr-1"></i>Cancelada
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        {{ $invoice->status_label ?? ucfirst($invoice->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="text-gray-400 text-xs">
                                    <i class="fas fa-info-circle mr-1"></i>Em breve
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="text-gray-400">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p class="text-sm">Nenhuma fatura encontrada</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Próximos Eventos --}}
    @if($upcomingEvents->count() > 0)
        <div class="bg-white rounded-lg shadow mt-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-calendar-alt mr-2 text-indigo-600"></i>Próximos Eventos
                    </h2>
                    @if(Route::has('client.events'))
                        <a href="{{ route('client.events') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            Ver todos <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    @endif
                </div>
            </div>

            <div class="p-6 space-y-4">
                @foreach($upcomingEvents as $event)
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-sm font-mono text-gray-500">{{ $event->event_number }}</span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        @if($event->status === 'confirmado') bg-blue-100 text-blue-800
                                        @elseif($event->status === 'em_andamento') bg-green-100 text-green-800
                                        @else bg-yellow-100 text-yellow-800
                                        @endif">
                                        {{ $event->status_label }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $event->name }}</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar text-blue-500 w-5 mr-2"></i>
                                        <span>{{ $event->start_date->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @if($event->venue)
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt text-red-500 w-5 mr-2"></i>
                                            <span>{{ $event->venue->name }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @if($event->total_value)
                                <div class="ml-4 text-right">
                                    <p class="text-sm text-gray-500">Valor</p>
                                    <p class="text-lg font-bold text-indigo-600">{{ number_format($event->total_value, 2, ',', '.') }} Kz</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
