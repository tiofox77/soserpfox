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
                <a href="#upgrade-plans" class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-xl font-semibold transition text-center shadow-lg">
                    <i class="fas fa-arrow-up mr-2"></i>Fazer Upgrade
                </a>
                <button wire:click="setActiveTab('billing')" class="px-6 py-3 border-2 border-gray-300 hover:border-gray-400 text-gray-700 rounded-xl font-semibold transition">
                    <i class="fas fa-file-invoice mr-2"></i>Ver Faturas
                </button>
            </div>
        </div>
        
        <!-- Comparação de Planos / Upgrade -->
        <div id="upgrade-plans" class="mt-8">
            <div class="text-center mb-8">
                <h3 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-rocket text-blue-600 mr-2"></i>
                    Planos Disponíveis
                </h3>
                <p class="text-gray-600">Escolha o melhor plano para o seu negócio</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($availablePlans as $plan)
                    <div class="bg-white rounded-2xl shadow-lg border-2 {{ $currentPlan && $plan->id === $currentPlan->id ? 'border-blue-500' : 'border-gray-200' }} p-6 relative hover:shadow-2xl transition-all transform hover:-translate-y-1">
                        
                        @if($currentPlan && $plan->id === $currentPlan->id)
                            <div class="absolute top-0 right-0 -mt-3 -mr-3">
                                <span class="inline-flex items-center px-4 py-1 bg-blue-600 text-white text-xs font-bold rounded-full shadow-lg">
                                    <i class="fas fa-star mr-1"></i>Seu Plano
                                </span>
                            </div>
                        @elseif($plan->is_featured)
                            <div class="absolute top-0 right-0 -mt-3 -mr-3">
                                <span class="inline-flex items-center px-4 py-1 bg-gradient-to-r from-yellow-400 to-orange-500 text-white text-xs font-bold rounded-full shadow-lg">
                                    <i class="fas fa-fire mr-1"></i>Popular
                                </span>
                            </div>
                        @endif

                        <!-- Header -->
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-{{ $plan->is_featured ? 'crown' : 'box' }} text-3xl text-blue-600"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h4>
                            <div class="flex items-baseline justify-center space-x-1">
                                <span class="text-4xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0) }}</span>
                                <span class="text-gray-600">Kz/mês</span>
                            </div>
                            @if($plan->price_yearly > 0)
                                <p class="text-sm text-green-600 mt-2">
                                    <i class="fas fa-tag mr-1"></i>
                                    Economize {{ $plan->getYearlySavingsPercentage() }}% no anual
                                </p>
                            @endif
                        </div>

                        <!-- Limites -->
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-600">
                                    <i class="fas fa-users mr-2 text-blue-500"></i>Utilizadores
                                </span>
                                <span class="font-semibold text-gray-900">{{ $plan->max_users }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-600">
                                    <i class="fas fa-building mr-2 text-green-500"></i>Empresas
                                </span>
                                <span class="font-semibold text-gray-900">{{ $plan->max_companies >= 999 ? '∞' : $plan->max_companies }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-600">
                                    <i class="fas fa-database mr-2 text-purple-500"></i>Storage
                                </span>
                                <span class="font-semibold text-gray-900">{{ number_format($plan->max_storage_mb / 1000, 1) }}GB</span>
                            </div>
                        </div>

                        <!-- Features (primeiras 5) -->
                        @if($plan->features && count($plan->features) > 0)
                            <div class="space-y-2 mb-6">
                                @foreach(array_slice($plan->features, 0, 5) as $feature)
                                    <div class="flex items-start space-x-2">
                                        <i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                                        <span class="text-sm text-gray-700">{{ $feature }}</span>
                                    </div>
                                @endforeach
                                @if(count($plan->features) > 5)
                                    <p class="text-xs text-gray-500 italic">
                                        + {{ count($plan->features) - 5 }} recursos adicionais
                                    </p>
                                @endif
                            </div>
                        @endif

                        <!-- Módulos -->
                        @if($plan->modules && $plan->modules->count() > 0)
                            <div class="mb-6">
                                <p class="text-xs font-semibold text-gray-600 mb-2">
                                    <i class="fas fa-puzzle-piece mr-1"></i>
                                    {{ $plan->modules->count() }} Módulos incluídos
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($plan->modules->take(4) as $module)
                                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full">
                                            {{ $module->name }}
                                        </span>
                                    @endforeach
                                    @if($plan->modules->count() > 4)
                                        <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                                            +{{ $plan->modules->count() - 4 }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Action Button -->
                        @if($currentPlan && $plan->id === $currentPlan->id)
                            <button disabled class="w-full px-6 py-3 bg-gray-300 text-gray-600 rounded-xl font-semibold cursor-not-allowed">
                                <i class="fas fa-check mr-2"></i>Plano Atual
                            </button>
                        @elseif(!$currentPlan || $plan->price_monthly > $currentPlan->price_monthly)
                            <button wire:click="openUpgradeModal({{ $plan->id }})" class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-xl font-semibold transition shadow-lg">
                                <i class="fas fa-arrow-up mr-2"></i>Fazer Upgrade
                            </button>
                        @else
                            <button wire:click="openUpgradeModal({{ $plan->id }})" class="w-full px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white rounded-xl font-semibold transition shadow-lg">
                                <i class="fas fa-exchange-alt mr-2"></i>Mudar para este Plano
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Info Box -->
            <div class="mt-8 bg-blue-50 border-l-4 border-blue-500 rounded-lg p-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 text-2xl mr-4 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-blue-900 mb-2">Informações sobre Upgrade</h4>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>• Ao fazer upgrade, você terá acesso imediato aos novos recursos</li>
                            <li>• O valor será proporcional ao período restante da sua assinatura</li>
                            <li>• Você pode fazer downgrade ou cancelar a qualquer momento</li>
                            <li>• Todos os seus dados são mantidos durante a transição</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-12">
            <i class="fas fa-info-circle text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600">Nenhum plano ativo encontrado</p>
        </div>
    @endif
</div>
