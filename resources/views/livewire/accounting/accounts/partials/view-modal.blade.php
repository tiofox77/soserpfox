@if($showViewModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeViewModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-cyan-600 to-blue-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-eye text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Detalhes da Conta</h3>
                    <p class="text-cyan-100 text-sm">Informações completas da conta contabilística</p>
                </div>
            </div>
            <button wire:click="closeViewModal" 
                    class="text-white hover:text-cyan-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="overflow-y-auto max-h-[calc(90vh-180px)] p-6">
            
            {{-- Informações Principais --}}
            <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-2xl p-6 mb-6 border border-emerald-200">
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-emerald-600"></i>Identificação
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-gray-500 mb-1">Código</p>
                        <p class="text-xl font-bold text-emerald-700">{{ $viewAccount->code }}</p>
                    </div>
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-gray-500 mb-1">Nível</p>
                        <p class="text-lg font-semibold text-gray-900">Nível {{ $viewAccount->level }}</p>
                    </div>
                    <div class="md:col-span-2 bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-gray-500 mb-1">Nome da Conta</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $viewAccount->name }}</p>
                    </div>
                </div>
            </div>

            {{-- Classificação --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-tags mr-2 text-purple-600"></i>Classificação
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center p-3 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-layer-group text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-blue-600 font-semibold">Tipo</p>
                            <p class="text-sm font-bold text-gray-900">
                                @if($viewAccount->type === 'asset') Ativo
                                @elseif($viewAccount->type === 'liability') Passivo
                                @elseif($viewAccount->type === 'equity') Capital Próprio
                                @elseif($viewAccount->type === 'revenue') Receitas
                                @elseif($viewAccount->type === 'expense') Gastos
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-200">
                        <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-balance-scale text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-green-600 font-semibold">Natureza</p>
                            <p class="text-sm font-bold text-gray-900">
                                {{ $viewAccount->nature === 'debit' ? 'Débito' : 'Crédito' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center p-3 bg-gradient-to-br from-amber-50 to-yellow-50 rounded-xl border border-amber-200">
                        <div class="w-10 h-10 bg-amber-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-sitemap text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-amber-600 font-semibold">Conta Pai</p>
                            <p class="text-sm font-bold text-gray-900">
                                @if($viewAccount->parent_id)
                                    {{ $viewAccount->parent->code ?? 'N/A' }}
                                @else
                                    Raiz
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Configurações Avançadas --}}
            @if($viewAccount->default_tax_id || $viewAccount->default_cost_center_id || $viewAccount->debit_reflection_account_id || $viewAccount->credit_reflection_account_id || $viewAccount->account_key || $viewAccount->account_subtype || $viewAccount->is_fixed_cost)
            <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl border border-purple-200 p-6 mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-sliders-h mr-2 text-purple-600"></i>Configurações Avançadas
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    @if($viewAccount->default_tax_id)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-purple-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-percent mr-1"></i>Imposto Padrão
                        </p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ $viewAccount->defaultTax->name ?? 'N/A' }}
                            ({{ $viewAccount->defaultTax->rate ?? 0 }}%)
                        </p>
                    </div>
                    @endif

                    @if($viewAccount->default_cost_center_id)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-indigo-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-building mr-1"></i>Centro de Custo
                        </p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ $viewAccount->defaultCostCenter->code ?? 'N/A' }} - 
                            {{ $viewAccount->defaultCostCenter->name ?? 'N/A' }}
                        </p>
                    </div>
                    @endif

                    @if($viewAccount->debit_reflection_account_id)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-green-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-arrow-right mr-1"></i>Reflexo Débito
                        </p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ $viewAccount->debitReflectionAccount->code ?? 'N/A' }} - 
                            {{ $viewAccount->debitReflectionAccount->name ?? 'N/A' }}
                        </p>
                    </div>
                    @endif

                    @if($viewAccount->credit_reflection_account_id)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-red-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-arrow-left mr-1"></i>Reflexo Crédito
                        </p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ $viewAccount->creditReflectionAccount->code ?? 'N/A' }} - 
                            {{ $viewAccount->creditReflectionAccount->name ?? 'N/A' }}
                        </p>
                    </div>
                    @endif

                    @if($viewAccount->account_key)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-yellow-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-key mr-1"></i>Chave da Conta
                        </p>
                        <p class="text-sm font-bold text-gray-900">{{ $viewAccount->account_key }}</p>
                    </div>
                    @endif

                    @if($viewAccount->account_subtype)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-pink-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-tags mr-1"></i>Subtipo
                        </p>
                        <p class="text-sm font-bold text-gray-900">{{ $viewAccount->account_subtype }}</p>
                    </div>
                    @endif

                    @if($viewAccount->is_fixed_cost)
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-blue-600 font-semibold mb-1 flex items-center">
                            <i class="fas fa-check-circle mr-1"></i>Custo Fixo
                        </p>
                        <p class="text-sm font-bold text-green-600">Sim</p>
                    </div>
                    @endif

                </div>
            </div>
            @endif

            {{-- Conta Resumo / Bloqueada --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @if($viewAccount->is_view)
                <div class="bg-gradient-to-br from-blue-50 to-sky-50 rounded-xl border border-blue-200 p-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-eye text-white text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-blue-600 font-semibold">Tipo de Conta</p>
                            <p class="text-sm font-bold text-gray-900">Conta de Visualização</p>
                            <p class="text-xs text-gray-600 mt-1">Apenas para agrupamento</p>
                        </div>
                    </div>
                </div>
                @endif
                
                @if($viewAccount->blocked)
                <div class="bg-gradient-to-br from-red-50 to-pink-50 rounded-xl border border-red-200 p-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-lock text-white text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-red-600 font-semibold">Status da Conta</p>
                            <p class="text-sm font-bold text-gray-900">Conta Bloqueada</p>
                            <p class="text-xs text-gray-600 mt-1">Sem novos lançamentos</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Descrição --}}
            @if($viewAccount->description)
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-align-left mr-2 text-blue-600"></i>Descrição
                </h4>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $viewAccount->description }}</p>
            </div>
            @endif

            {{-- Metadados --}}
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-clock mr-2 text-gray-500"></i>Informações do Sistema
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-plus text-gray-400 mr-2"></i>
                        <span>Criado em: <strong>{{ $viewAccount->created_at->format('d/m/Y H:i') }}</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar-check text-gray-400 mr-2"></i>
                        <span>Atualizado em: <strong>{{ $viewAccount->updated_at->format('d/m/Y H:i') }}</strong></span>
                    </div>
                    @if($viewAccount->integration_key)
                    <div class="flex items-center md:col-span-2">
                        <i class="fas fa-plug text-gray-400 mr-2"></i>
                        <span>Chave de Integração: <strong>{{ $viewAccount->integration_key }}</strong></span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
            <button wire:click="edit({{ $viewAccount->id }})" 
                    class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition font-semibold">
                <i class="fas fa-edit mr-2"></i>Editar
            </button>
            <button wire:click="closeViewModal" 
                    class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 transition font-semibold">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>
@endif
