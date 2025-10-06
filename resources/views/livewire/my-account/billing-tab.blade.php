<div class="space-y-6">
    
    {{-- Header com Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-receipt text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Total</span>
            </div>
            <h3 class="text-3xl font-bold">{{ $orders->total() }}</h3>
            <p class="text-sm opacity-90">Pedidos/Faturas</p>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-check-circle text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Pagos</span>
            </div>
            <h3 class="text-3xl font-bold">{{ $orders->where('status', 'completed')->count() }}</h3>
            <p class="text-sm opacity-90">Pedidos Completos</p>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-clock text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Pendentes</span>
            </div>
            <h3 class="text-3xl font-bold">{{ $orders->where('status', 'pending')->count() }}</h3>
            <p class="text-sm opacity-90">Aguardando Pagamento</p>
        </div>
    </div>

    {{-- Histórico de Pedidos --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Histórico de Pedidos</h2>
                    <p class="text-sm text-gray-600 mt-1">Todos os seus pedidos e faturas</p>
                </div>
                <div class="text-right">
                    <span class="text-sm text-gray-500">Total de {{ $orders->total() }} pedidos</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            @if($orders->count() > 0)
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pedido</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Empresa</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Plano</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($orders as $order)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-file-invoice text-blue-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">#{{ $order->id }}</div>
                                            <div class="text-xs text-gray-500">{{ $order->order_number ?? 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $order->tenant->name ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $order->plan->name ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ ucfirst($order->billing_cycle ?? 'mensal') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">{{ number_format($order->amount ?? 0, 2, ',', '.') }} Kz</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusConfig = [
                                            'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'clock', 'label' => 'Pendente'],
                                            'approved' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'check', 'label' => 'Aprovado'],
                                            'processing' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'icon' => 'spinner', 'label' => 'Processando'],
                                            'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-circle', 'label' => 'Completo'],
                                            'paid' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-double', 'label' => 'Pago'],
                                            'cancelled' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'times-circle', 'label' => 'Cancelado'],
                                            'failed' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'exclamation-circle', 'label' => 'Falhou'],
                                            'refunded' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'undo', 'label' => 'Reembolsado'],
                                        ];
                                        $currentStatus = $order->status ?? 'pending';
                                        $status = $statusConfig[$currentStatus] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'question', 'label' => ucfirst($currentStatus)];
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $status['bg'] }} {{ $status['text'] }}">
                                        <i class="fas fa-{{ $status['icon'] }} mr-1"></i>
                                        {{ $status['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $order->created_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center space-x-2">
                                        <button wire:click="viewOrder({{ $order->id }})" class="text-gray-600 hover:text-gray-800">
                                            <i class="fas fa-eye mr-1"></i>Ver
                                        </button>
                                        @if($order->status === 'pending')
                                            <span class="text-gray-300">|</span>
                                            <button wire:click="openPaymentModal({{ $order->id }})" class="text-blue-600 hover:text-blue-800 font-semibold">
                                                <i class="fas fa-credit-card mr-1"></i>Pagar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $orders->links() }}
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-receipt text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhum pedido encontrado</h3>
                    <p class="text-gray-600 mb-4">Você ainda não tem nenhum pedido ou fatura registrado.</p>
                    <a href="{{ route('my-account', ['tab' => 'plan']) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-crown mr-2"></i>Ver Planos
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Info Box --}}
    <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 text-xl mt-0.5 mr-3"></i>
            <div>
                <h4 class="font-semibold text-blue-900 mb-1">Informações sobre Faturas</h4>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Faturas são geradas automaticamente após confirmação de pagamento</li>
                    <li>• Você pode baixar suas faturas em PDF a qualquer momento</li>
                    <li>• Pedidos pendentes expiram após 7 dias sem pagamento</li>
                </ul>
            </div>
        </div>
    </div>

</div>
