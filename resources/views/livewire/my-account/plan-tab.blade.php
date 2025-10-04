<!-- Pedidos Pendentes -->
@if($pendingOrders->count() > 0 || $pendingSubscriptions->count() > 0)
    <div class="mb-6 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-2xl shadow-xl p-6 text-white">
        <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3">
                <i class="fas fa-clock text-2xl animate-pulse"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold">Aguardando Aprovação</h3>
                <p class="text-yellow-100 text-sm">Seu pagamento está em análise</p>
            </div>
        </div>
        
        @foreach($pendingOrders as $order)
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 mb-3">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-building mr-2"></i>
                            <h4 class="font-bold">{{ $order->tenant->name }}</h4>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-yellow-100 text-xs">Plano</p>
                                <p class="font-semibold">{{ $order->plan->name }}</p>
                            </div>
                            <div>
                                <p class="text-yellow-100 text-xs">Valor</p>
                                <p class="font-semibold">{{ number_format($order->amount, 2) }} Kz</p>
                            </div>
                            <div>
                                <p class="text-yellow-100 text-xs">Data</p>
                                <p class="font-semibold">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                        
                        @if($order->payment_reference)
                            <div class="mt-2 text-xs">
                                <i class="fas fa-hashtag mr-1"></i>
                                Referência: {{ $order->payment_reference }}
                            </div>
                        @endif
                    </div>
                    
                    <div class="ml-4">
                        <div class="px-4 py-2 bg-yellow-600 rounded-lg font-semibold text-sm">
                            <i class="fas fa-hourglass-half mr-1"></i>Pendente
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        
        <div class="mt-4 p-3 bg-white/10 backdrop-blur-sm rounded-lg text-sm">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>O que acontece agora?</strong> Nosso time está verificando seu pagamento. Você receberá acesso total assim que for aprovado (geralmente em até 24h).
        </div>
    </div>
@endif

<!-- Meu Plano Tab -->
<div class="bg-white rounded-2xl shadow-lg p-6">
    @if($currentPlan)
        <div class="max-w-2xl mx-auto">
            <!-- Plano Atual Card -->
            <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl p-8 text-white mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-blue-100 text-sm mb-1">Plano Atual</p>
                        <h2 class="text-3xl font-bold">{{ $currentPlan->name }}</h2>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-crown text-3xl"></i>
                    </div>
                </div>
                
                <div class="flex items-baseline space-x-2 mb-6">
                    <span class="text-4xl font-bold">{{ number_format($currentPlan->price_monthly, 2) }}</span>
                    <span class="text-blue-100">Kz/mês</span>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white/10 rounded-lg p-3">
                        <div class="text-blue-100 text-xs mb-1">Utilizadores</div>
                        <div class="text-xl font-bold">{{ $currentPlan->max_users }}</div>
                    </div>
                    <div class="bg-white/10 rounded-lg p-3">
                        <div class="text-blue-100 text-xs mb-1">Empresas</div>
                        <div class="text-xl font-bold">{{ $currentPlan->max_companies >= 999 ? '∞' : $currentPlan->max_companies }}</div>
                    </div>
                    <div class="bg-white/10 rounded-lg p-3">
                        <div class="text-blue-100 text-xs mb-1">Storage</div>
                        <div class="text-xl font-bold">{{ number_format($currentPlan->max_storage_mb / 1000, 1) }}GB</div>
                    </div>
                    <div class="bg-white/10 rounded-lg p-3">
                        <div class="text-blue-100 text-xs mb-1">Trial</div>
                        <div class="text-xl font-bold">{{ $currentPlan->trial_days }} dias</div>
                    </div>
                </div>
            </div>

            <!-- Features -->
            @if($currentPlan->features)
                <div class="mb-6">
                    <h3 class="font-bold text-gray-900 mb-4">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        Recursos Incluídos
                    </h3>
                    <div class="space-y-2">
                        @foreach($currentPlan->features as $feature)
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-green-600 text-xs"></i>
                                </div>
                                <span class="text-gray-700">{{ $feature }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="flex space-x-4">
                <button class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition">
                    <i class="fas fa-arrow-up mr-2"></i>Fazer Upgrade
                </button>
                <button class="px-6 py-3 border-2 border-gray-300 hover:border-gray-400 text-gray-700 rounded-xl font-semibold transition">
                    <i class="fas fa-file-invoice mr-2"></i>Ver Faturas
                </button>
            </div>
        </div>
    @else
        <div class="text-center py-12">
            <i class="fas fa-info-circle text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600">Nenhum plano ativo encontrado</p>
        </div>
    @endif
</div>
