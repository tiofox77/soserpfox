<!-- Modal de Upgrade -->
@if($showUpgradeModal && $selectedPlanForUpgrade)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showUpgradeModal') }" x-show="show" x-cloak>
    <!-- Overlay -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" 
         x-show="show"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         wire:click="closeUpgradeModal">
    </div>

    <!-- Modal -->
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden"
             x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.away="$wire.closeUpgradeModal()">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-5 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                        <i class="fas fa-rocket text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white">Fazer Upgrade</h3>
                        <p class="text-blue-100 text-sm">Evolua seu plano para {{ $selectedPlanForUpgrade->name }}</p>
                    </div>
                </div>
                <button wire:click="closeUpgradeModal" class="text-white/80 hover:text-white text-2xl transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="overflow-y-auto max-h-[calc(90vh-140px)]">
                <div class="p-6 space-y-6">
                    
                    @if($upgradeStep == 2)
                        {{-- STEP 2: PAGAMENTO --}}
                        <div class="space-y-6">
                            <!-- Resumo do Pedido -->
                            <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-6 border-2 border-blue-200">
                                <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-receipt text-blue-600 mr-2"></i>
                                    Resumo do Pedido
                                </h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-600">Plano:</p>
                                        <p class="font-bold text-gray-900">{{ $selectedPlanForUpgrade->name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-600">Ciclo:</p>
                                        <p class="font-bold text-gray-900">
                                            @switch($upgradeBillingCycle)
                                                @case('yearly') Anual @break
                                                @case('semiannual') Semestral @break
                                                @case('quarterly') Trimestral @break
                                                @default Mensal
                                            @endswitch
                                        </p>
                                    </div>
                                    <div class="col-span-2 pt-3 border-t border-blue-200">
                                        <p class="text-gray-600 mb-1">Valor Total:</p>
                                        <p class="text-3xl font-bold text-blue-600">
                                            @php
                                                $amount = match($upgradeBillingCycle) {
                                                    'yearly' => $selectedPlanForUpgrade->price_yearly,
                                                    'semiannual' => $selectedPlanForUpgrade->price_semiannual,
                                                    'quarterly' => $selectedPlanForUpgrade->price_quarterly,
                                                    default => $selectedPlanForUpgrade->price_monthly,
                                                };
                                            @endphp
                                            {{ number_format($amount, 2, ',', '.') }} Kz
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Dados Bancários -->
                            <div class="bg-white rounded-2xl border-2 border-gray-200 p-6">
                                <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-university text-green-600 mr-2"></i>
                                    Dados para Transferência Bancária
                                </h4>
                                
                                <div class="space-y-4">
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs text-gray-600 mb-1">Banco:</p>
                                        <p class="font-bold text-gray-900">BAI - Banco Angolano de Investimentos</p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs text-gray-600 mb-1">Titular:</p>
                                        <p class="font-bold text-gray-900">SOS ERP - SISTEMAS DE GESTÃO</p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs text-gray-600 mb-1">IBAN:</p>
                                        <div class="flex items-center justify-between">
                                            <p class="font-bold text-gray-900">AO06 0040 0000 1234 5678 9012 3</p>
                                            <button type="button" onclick="navigator.clipboard.writeText('AO0600400000123456789012 3')" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs text-gray-600 mb-1">Referência:</p>
                                        <div class="flex items-center justify-between">
                                            @php
                                                $reference = 'UPGRADE-' . auth()->user()->id . '-' . now()->format('Ymd');
                                            @endphp
                                            <p class="font-bold text-gray-900">{{ $reference }}</p>
                                            <button type="button" onclick="navigator.clipboard.writeText('{{ $reference }}')" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Comprovativo -->
                            <div class="bg-white rounded-2xl border-2 border-gray-200 p-6">
                                <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-file-upload text-purple-600 mr-2"></i>
                                    Anexar Comprovativo de Pagamento (Opcional)
                                </h4>
                                
                                <div class="space-y-3">
                                    <input type="file" 
                                           wire:model="paymentProof" 
                                           accept="image/*,application/pdf"
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                    
                                    @if($paymentProof)
                                        <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                                            <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                            <span class="text-sm text-green-800 font-semibold">Arquivo selecionado com sucesso!</span>
                                        </div>
                                    @endif

                                    @error('paymentProof') 
                                        <span class="text-red-500 text-sm flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </span> 
                                    @enderror
                                    
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Formatos aceitos: PDF, JPG, PNG (máx. 5MB)
                                    </p>
                                </div>
                            </div>

                            <!-- Instruções -->
                            <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
                                <h5 class="font-semibold text-blue-900 mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>Como proceder:
                                </h5>
                                <ol class="text-sm text-blue-800 space-y-2 list-decimal list-inside">
                                    <li>Realize a transferência bancária usando os dados acima</li>
                                    <li>Use a referência indicada para facilitar a identificação</li>
                                    <li>Anexe o comprovativo (ou faça depois na área de faturas)</li>
                                    <li>Nossa equipe validará e ativará seu plano em até 24h úteis</li>
                                </ol>
                            </div>
                        </div>
                    @else
                        {{-- STEP 1: SELEÇÃO DO PLANO --}}
                    
                    <!-- Plano Selecionado -->
                    <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-6 border-2 border-blue-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Plano Selecionado</p>
                                <h4 class="text-3xl font-bold text-gray-900">{{ $selectedPlanForUpgrade->name }}</h4>
                            </div>
                            @if($selectedPlanForUpgrade->is_featured)
                                <span class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-orange-500 text-white text-sm font-bold rounded-full">
                                    <i class="fas fa-fire mr-1"></i>Popular
                                </span>
                            @endif
                        </div>

                        <!-- Ciclo de Pagamento -->
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-calendar-alt mr-2"></i>Ciclo de Pagamento
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <!-- Mensal -->
                                <button type="button" 
                                        wire:click="$set('upgradeBillingCycle', 'monthly')"
                                        class="p-3 rounded-xl border-2 transition {{ $upgradeBillingCycle === 'monthly' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-300' }}">
                                    <div class="text-center">
                                        <i class="fas fa-calendar-day text-xl {{ $upgradeBillingCycle === 'monthly' ? 'text-blue-600' : 'text-gray-400' }} mb-1"></i>
                                        <p class="font-semibold text-sm text-gray-900">Mensal</p>
                                        <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format($selectedPlanForUpgrade->price_monthly, 0) }} Kz</p>
                                        <p class="text-xs text-gray-500">por mês</p>
                                    </div>
                                </button>

                                <!-- Trimestral -->
                                <button type="button"
                                        wire:click="$set('upgradeBillingCycle', 'quarterly')"
                                        class="p-3 rounded-xl border-2 transition {{ $upgradeBillingCycle === 'quarterly' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-300' }} relative">
                                    @if($selectedPlanForUpgrade->price_quarterly > 0)
                                        @php
                                            $quarterlySavings = ($selectedPlanForUpgrade->price_monthly * 3) - $selectedPlanForUpgrade->price_quarterly;
                                            $quarterlySavingsPercent = $quarterlySavings > 0 ? round(($quarterlySavings / ($selectedPlanForUpgrade->price_monthly * 3)) * 100) : 0;
                                        @endphp
                                        @if($quarterlySavingsPercent > 0)
                                            <div class="absolute -top-2 -right-2 px-2 py-1 bg-green-500 text-white text-xs font-bold rounded-full">
                                                -{{ $quarterlySavingsPercent }}%
                                            </div>
                                        @endif
                                    @endif
                                    <div class="text-center">
                                        <i class="fas fa-calendar-week text-xl {{ $upgradeBillingCycle === 'quarterly' ? 'text-blue-600' : 'text-gray-400' }} mb-1"></i>
                                        <p class="font-semibold text-sm text-gray-900">Trimestral</p>
                                        <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format($selectedPlanForUpgrade->price_quarterly, 0) }} Kz</p>
                                        <p class="text-xs text-gray-500">3 meses</p>
                                    </div>
                                </button>

                                <!-- Semestral -->
                                <button type="button"
                                        wire:click="$set('upgradeBillingCycle', 'semiannual')"
                                        class="p-3 rounded-xl border-2 transition {{ $upgradeBillingCycle === 'semiannual' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-300' }} relative">
                                    @if($selectedPlanForUpgrade->price_semiannual > 0)
                                        @php
                                            $semiannualSavings = ($selectedPlanForUpgrade->price_monthly * 6) - $selectedPlanForUpgrade->price_semiannual;
                                            $semiannualSavingsPercent = $semiannualSavings > 0 ? round(($semiannualSavings / ($selectedPlanForUpgrade->price_monthly * 6)) * 100) : 0;
                                        @endphp
                                        @if($semiannualSavingsPercent > 0)
                                            <div class="absolute -top-2 -right-2 px-2 py-1 bg-green-500 text-white text-xs font-bold rounded-full">
                                                -{{ $semiannualSavingsPercent }}%
                                            </div>
                                        @endif
                                    @endif
                                    <div class="text-center">
                                        <i class="fas fa-calendar-alt text-xl {{ $upgradeBillingCycle === 'semiannual' ? 'text-blue-600' : 'text-gray-400' }} mb-1"></i>
                                        <p class="font-semibold text-sm text-gray-900">Semestral</p>
                                        <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format($selectedPlanForUpgrade->price_semiannual, 0) }} Kz</p>
                                        <p class="text-xs text-gray-500">6 meses</p>
                                    </div>
                                </button>

                                <!-- Anual -->
                                <button type="button"
                                        wire:click="$set('upgradeBillingCycle', 'yearly')"
                                        class="p-3 rounded-xl border-2 transition {{ $upgradeBillingCycle === 'yearly' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-300' }} relative">
                                    <div class="absolute -top-2 -right-2 px-3 py-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white text-xs font-bold rounded-full shadow-lg">
                                        <i class="fas fa-gift mr-1"></i>+2 Meses Grátis
                                    </div>
                                    <div class="text-center">
                                        <i class="fas fa-calendar-year text-xl {{ $upgradeBillingCycle === 'yearly' ? 'text-blue-600' : 'text-gray-400' }} mb-1"></i>
                                        <p class="font-semibold text-sm text-gray-900">Anual</p>
                                        <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format($selectedPlanForUpgrade->price_yearly, 0) }} Kz</p>
                                        <p class="text-xs text-green-600 font-semibold">14 meses pelo preço de 12</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Resumo do Valor -->
                        <div class="bg-white rounded-xl p-4 border-2 border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-gray-600">Valor do Plano:</span>
                                <span class="text-xl font-bold text-gray-900">
                                    @php
                                        $amount = match($upgradeBillingCycle) {
                                            'yearly' => $selectedPlanForUpgrade->price_yearly,
                                            'semiannual' => $selectedPlanForUpgrade->price_semiannual,
                                            'quarterly' => $selectedPlanForUpgrade->price_quarterly,
                                            default => $selectedPlanForUpgrade->price_monthly,
                                        };
                                    @endphp
                                    {{ number_format($amount, 2, ',', '.') }} Kz
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Período:</span>
                                <span>
                                    @switch($upgradeBillingCycle)
                                        @case('yearly')
                                            <span class="font-semibold text-green-600">14 meses</span> <span class="text-xs">(12 + 2 grátis 🎁)</span>
                                            @break
                                        @case('semiannual')
                                            6 meses
                                            @break
                                        @case('quarterly')
                                            3 meses
                                            @break
                                        @default
                                            1 mês
                                    @endswitch
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Benefícios do Plano -->
                    <div>
                        <h5 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-gift text-blue-600 mr-2"></i>
                            O que você ganha com este plano:
                        </h5>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                                <i class="fas fa-users text-3xl text-blue-600 mb-2"></i>
                                <p class="text-sm text-gray-600 mb-1">Utilizadores</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $selectedPlanForUpgrade->max_users }}</p>
                            </div>
                            <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                                <i class="fas fa-building text-3xl text-green-600 mb-2"></i>
                                <p class="text-sm text-gray-600 mb-1">Empresas</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $selectedPlanForUpgrade->max_companies >= 999 ? '∞' : $selectedPlanForUpgrade->max_companies }}</p>
                            </div>
                            <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                                <i class="fas fa-database text-3xl text-purple-600 mb-2"></i>
                                <p class="text-sm text-gray-600 mb-1">Storage</p>
                                <p class="text-2xl font-bold text-gray-900">{{ number_format($selectedPlanForUpgrade->max_storage_mb / 1000, 1) }}GB</p>
                            </div>
                        </div>

                        <!-- Features -->
                        @if($selectedPlanForUpgrade->features && count($selectedPlanForUpgrade->features) > 0)
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <p class="font-semibold text-gray-900 mb-3">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>Recursos Incluídos:
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($selectedPlanForUpgrade->features as $feature)
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check text-green-500 mt-1 flex-shrink-0"></i>
                                            <span class="text-sm text-gray-700">{{ $feature }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Módulos -->
                        @if($selectedPlanForUpgrade->modules && $selectedPlanForUpgrade->modules->count() > 0)
                            <div class="bg-blue-50 rounded-xl p-4 border border-blue-200 mt-4">
                                <p class="font-semibold text-gray-900 mb-3">
                                    <i class="fas fa-puzzle-piece text-blue-600 mr-2"></i>
                                    {{ $selectedPlanForUpgrade->modules->count() }} Módulos Incluídos:
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($selectedPlanForUpgrade->modules as $module)
                                        <span class="px-3 py-1 bg-white border border-blue-300 text-blue-700 rounded-full text-sm font-semibold">
                                            {{ $module->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Informações Importantes -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-yellow-600 text-xl mr-3 mt-0.5"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-semibold mb-2">Como funciona:</p>
                                <ul class="space-y-1 list-disc list-inside">
                                    <li>Ao confirmar, será criado um pedido de upgrade</li>
                                    <li>Nossa equipe irá processar e enviar os dados de pagamento</li>
                                    <li>Após confirmação do pagamento, seu plano será ativado automaticamente</li>
                                    <li>Você receberá um email com todas as instruções</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Footer com Botões -->
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                @if($upgradeStep == 1)
                    <button type="button" 
                            wire:click="closeUpgradeModal"
                            class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    
                    <button type="button"
                            wire:click="goToPaymentStep"
                            class="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-xl font-semibold transition shadow-lg">
                        <i class="fas fa-arrow-right mr-2"></i>Continuar para Pagamento
                    </button>
                @else
                    <button type="button" 
                            wire:click="backToSelectPlan"
                            class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </button>
                    
                    <button type="button"
                            wire:click="processUpgrade"
                            wire:loading.attr="disabled"
                            class="px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition shadow-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="processUpgrade">
                            <i class="fas fa-check-circle mr-2"></i>Confirmar Pedido
                        </span>
                        <span wire:loading wire:target="processUpgrade">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
