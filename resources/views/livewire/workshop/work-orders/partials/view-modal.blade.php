{{-- Modal de Visualização da Ordem de Serviço --}}
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ activeTab: 'info' }">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="closeViewModal"></div>

        <div class="inline-block w-full max-w-6xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-clipboard-list text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ $viewingWorkOrder->order_number }}</h3>
                        <p class="text-purple-100 text-sm">Ordem de Serviço #{{ $viewingWorkOrder->id }}</p>
                    </div>
                </div>
                <button wire:click="closeViewModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200 bg-gray-50 px-6">
                <nav class="flex space-x-4">
                    <button @click="activeTab = 'info'" 
                            :class="activeTab === 'info' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>Informações
                    </button>
                    <button @click="activeTab = 'items'" 
                            :class="activeTab === 'items' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-list mr-2"></i>Serviços & Peças
                        <span class="ml-2 bg-purple-100 text-purple-800 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $viewingWorkOrder->items->count() }}
                        </span>
                    </button>
                    <button @click="activeTab = 'financial'" 
                            :class="activeTab === 'financial' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-dollar-sign mr-2"></i>Financeiro
                    </button>
                    <button @click="activeTab = 'history'" 
                            :class="activeTab === 'history' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-history mr-2"></i>Histórico
                        <span class="ml-2 bg-gray-100 text-gray-800 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $viewingWorkOrder->history->count() }}
                        </span>
                    </button>
                    <button @click="activeTab = 'attachments'" 
                            :class="activeTab === 'attachments' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-paperclip mr-2"></i>Anexos
                        <span class="ml-2 bg-green-100 text-green-800 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $viewingWorkOrder->attachments->count() }}
                        </span>
                    </button>
                    <button @click="activeTab = 'invoicing'" 
                            :class="activeTab === 'invoicing' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-file-invoice-dollar mr-2"></i>Faturação
                        @if($viewingWorkOrder->invoice_id)
                            <span class="ml-2 bg-green-100 text-green-800 text-xs font-bold px-2 py-0.5 rounded-full">
                                <i class="fas fa-check"></i>
                            </span>
                        @else
                            <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-0.5 rounded-full">
                                Pendente
                            </span>
                        @endif
                    </button>
                </nav>
            </div>

            {{-- Content --}}
            <div class="p-6 max-h-[600px] overflow-y-auto">
                
                {{-- Tab: Informações --}}
                <div x-show="activeTab === 'info'" class="space-y-6">
                    {{-- Status e Prioridade --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                            <p class="text-sm text-gray-600 mb-2">Status</p>
                            @if($viewingWorkOrder->status === 'pending')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-400 text-yellow-900">
                                    <i class="fas fa-clock mr-2"></i>Pendente
                                </span>
                            @elseif($viewingWorkOrder->status === 'in_progress')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-500 text-white">
                                    <i class="fas fa-wrench mr-2"></i>Em Andamento
                                </span>
                            @elseif($viewingWorkOrder->status === 'completed')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-500 text-white">
                                    <i class="fas fa-check-circle mr-2"></i>Concluída
                                </span>
                            @elseif($viewingWorkOrder->status === 'delivered')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-purple-500 text-white">
                                    <i class="fas fa-flag-checkered mr-2"></i>Entregue
                                </span>
                            @endif
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 border border-purple-100">
                            <p class="text-sm text-gray-600 mb-2">Prioridade</p>
                            @if($viewingWorkOrder->priority === 'urgent')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-red-500 text-white">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Urgente
                                </span>
                            @elseif($viewingWorkOrder->priority === 'high')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-orange-500 text-white">
                                    <i class="fas fa-arrow-up mr-2"></i>Alta
                                </span>
                            @elseif($viewingWorkOrder->priority === 'normal')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-500 text-white">
                                    <i class="fas fa-minus mr-2"></i>Normal
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-500 text-white">
                                    <i class="fas fa-arrow-down mr-2"></i>Baixa
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Veículo e Proprietário --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-car text-blue-600 mr-2"></i>Veículo
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Placa</p>
                                <p class="font-bold text-gray-900">{{ $viewingWorkOrder->vehicle->plate }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Marca/Modelo</p>
                                <p class="font-bold text-gray-900">{{ $viewingWorkOrder->vehicle->brand }} {{ $viewingWorkOrder->vehicle->model }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Ano</p>
                                <p class="font-bold text-gray-900">{{ $viewingWorkOrder->vehicle->year }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Cor</p>
                                <p class="font-bold text-gray-900">{{ $viewingWorkOrder->vehicle->color }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">KM Entrada</p>
                                <p class="font-bold text-gray-900">{{ number_format($viewingWorkOrder->mileage_in, 0, ',', '.') }} km</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Proprietário</p>
                                <p class="font-bold text-gray-900">{{ $viewingWorkOrder->vehicle->owner_name }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Mecânico --}}
                    @if($viewingWorkOrder->mechanic)
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user-cog text-green-600 mr-2"></i>Mecânico Responsável
                        </h4>
                        <p class="font-bold text-gray-900">{{ $viewingWorkOrder->mechanic->name }}</p>
                    </div>
                    @endif

                    {{-- Datas --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
                            <p class="text-sm text-gray-600 mb-1">Data de Entrada</p>
                            <p class="font-bold text-gray-900">{{ $viewingWorkOrder->received_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @if($viewingWorkOrder->scheduled_for)
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                            <p class="text-sm text-gray-600 mb-1">Agendado Para</p>
                            <p class="font-bold text-gray-900">{{ $viewingWorkOrder->scheduled_for->format('d/m/Y H:i') }}</p>
                        </div>
                        @endif
                        @if($viewingWorkOrder->completed_at)
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200">
                            <p class="text-sm text-gray-600 mb-1">Data de Conclusão</p>
                            <p class="font-bold text-gray-900">{{ $viewingWorkOrder->completed_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @endif
                        @if($viewingWorkOrder->delivered_at)
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
                            <p class="text-sm text-gray-600 mb-1">Data de Entrega</p>
                            <p class="font-bold text-gray-900">{{ $viewingWorkOrder->delivered_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Descrições --}}
                    <div class="space-y-4">
                        <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                            <p class="text-sm font-bold text-red-900 mb-2"><i class="fas fa-exclamation-circle mr-2"></i>Problema Relatado</p>
                            <p class="text-gray-700">{{ $viewingWorkOrder->problem_description }}</p>
                        </div>
                        
                        @if($viewingWorkOrder->diagnosis)
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                            <p class="text-sm font-bold text-blue-900 mb-2"><i class="fas fa-stethoscope mr-2"></i>Diagnóstico</p>
                            <p class="text-gray-700">{{ $viewingWorkOrder->diagnosis }}</p>
                        </div>
                        @endif
                        
                        @if($viewingWorkOrder->work_performed)
                        <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                            <p class="text-sm font-bold text-green-900 mb-2"><i class="fas fa-tools mr-2"></i>Trabalho Realizado</p>
                            <p class="text-gray-700">{{ $viewingWorkOrder->work_performed }}</p>
                        </div>
                        @endif
                        
                        @if($viewingWorkOrder->recommendations)
                        <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200">
                            <p class="text-sm font-bold text-yellow-900 mb-2"><i class="fas fa-lightbulb mr-2"></i>Recomendações</p>
                            <p class="text-gray-700">{{ $viewingWorkOrder->recommendations }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Tab: Itens (Serviços & Peças) --}}
                <div x-show="activeTab === 'items'" class="space-y-6">
                    {{-- Botões de Adicionar --}}
                    <div class="flex gap-3">
                        <button wire:click="openItemModal('service')" 
                                class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-4 py-2 rounded-xl font-semibold transition-all shadow-lg flex items-center">
                            <i class="fas fa-plus mr-2"></i>Adicionar Serviço
                        </button>
                        <button wire:click="openItemModal('part')" 
                                class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-2 rounded-xl font-semibold transition-all shadow-lg flex items-center">
                            <i class="fas fa-plus mr-2"></i>Adicionar Peça
                        </button>
                    </div>

                    {{-- Tabela de Itens --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-gray-700 to-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase">Tipo</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase">Código</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase">Descrição</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-white uppercase">Qtd</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase">Preço Unit.</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase">Subtotal</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-white uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($viewingWorkOrder->items as $item)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3">
                                            @if($item->type === 'service')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                                    <i class="fas fa-wrench mr-1"></i>Serviço
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                                    <i class="fas fa-cog mr-1"></i>Peça
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 font-mono">{{ $item->code }}</td>
                                        <td class="px-4 py-3">
                                            <p class="text-sm font-bold text-gray-900">{{ $item->name }}</p>
                                            @if($item->description)
                                                <p class="text-xs text-gray-500">{{ $item->description }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm font-bold text-gray-900">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">{{ number_format($item->unit_price, 2, ',', '.') }} Kz</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-purple-600">{{ number_format($item->subtotal, 2, ',', '.') }} Kz</td>
                                        <td class="px-4 py-3 text-center">
                                            <button wire:click="deleteItem({{ $item->id }})" 
                                                    onclick="return confirm('Remover este item?')"
                                                    class="text-red-600 hover:text-red-900 hover:bg-red-50 p-2 rounded-lg transition-all">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center">
                                            <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                            <p class="text-gray-500 font-medium">Nenhum item adicionado</p>
                                            <p class="text-gray-400 text-sm mt-2">Clique em "Adicionar Serviço" ou "Adicionar Peça"</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Tab: Financeiro --}}
                <div x-show="activeTab === 'financial'" class="space-y-6">
                    {{-- Resumo Financeiro --}}
                    <div class="grid grid-cols-2 gap-6">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-blue-900">Mão de Obra</p>
                                <i class="fas fa-wrench text-blue-600 text-xl"></i>
                            </div>
                            <p class="text-3xl font-bold text-blue-900">{{ number_format($viewingWorkOrder->labor_total, 2, ',', '.') }} Kz</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-green-900">Peças</p>
                                <i class="fas fa-cog text-green-600 text-xl"></i>
                            </div>
                            <p class="text-3xl font-bold text-green-900">{{ number_format($viewingWorkOrder->parts_total, 2, ',', '.') }} Kz</p>
                        </div>
                    </div>

                    {{-- Detalhamento --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center pb-3 border-b">
                                <span class="text-gray-700 font-semibold">Subtotal</span>
                                <span class="text-gray-900 font-bold">{{ number_format($viewingWorkOrder->labor_total + $viewingWorkOrder->parts_total, 2, ',', '.') }} Kz</span>
                            </div>
                            
                            <div class="flex justify-between items-center pb-3 border-b">
                                <span class="text-gray-700 font-semibold">Desconto</span>
                                <span class="text-red-600 font-bold">-{{ number_format($viewingWorkOrder->discount, 2, ',', '.') }} Kz</span>
                            </div>
                            
                            <div class="flex justify-between items-center pb-3 border-b">
                                <span class="text-gray-700 font-semibold">IVA (14%)</span>
                                <span class="text-gray-900 font-bold">{{ number_format($viewingWorkOrder->tax, 2, ',', '.') }} Kz</span>
                            </div>
                            
                            <div class="flex justify-between items-center pt-3 bg-gradient-to-r from-purple-100 to-indigo-100 -mx-6 px-6 py-4 rounded-b-xl">
                                <span class="text-lg font-bold text-purple-900">TOTAL</span>
                                <span class="text-2xl font-bold text-purple-900">{{ number_format($viewingWorkOrder->total, 2, ',', '.') }} Kz</span>
                            </div>
                        </div>
                    </div>

                    {{-- Status de Pagamento --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4">Status de Pagamento</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Status</span>
                                @if($viewingWorkOrder->payment_status === 'paid')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-500 text-white">
                                        <i class="fas fa-check-circle mr-2"></i>Pago
                                    </span>
                                @elseif($viewingWorkOrder->payment_status === 'partial')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-500 text-white">
                                        <i class="fas fa-exclamation-circle mr-2"></i>Parcial
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-red-500 text-white">
                                        <i class="fas fa-times-circle mr-2"></i>Pendente
                                    </span>
                                @endif
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Valor Pago</span>
                                <span class="text-green-600 font-bold">{{ number_format($viewingWorkOrder->paid_amount, 2, ',', '.') }} Kz</span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Saldo Devedor</span>
                                <span class="text-red-600 font-bold">{{ number_format($viewingWorkOrder->balance_due, 2, ',', '.') }} Kz</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Tab: Histórico --}}
                <div x-show="activeTab === 'history'">
                    @if($viewingWorkOrder->history->count() > 0)
                        <div class="space-y-3">
                            @foreach($viewingWorkOrder->history as $log)
                                <div class="flex gap-4 p-4 bg-gray-50 rounded-xl border-l-4 border-{{ $log->color }}-500 hover:bg-gray-100 transition-all">
                                    {{-- Ícone --}}
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-{{ $log->color }}-100 text-{{ $log->color }}-600 rounded-full flex items-center justify-center">
                                            <i class="fas fa-{{ $log->icon }}"></i>
                                        </div>
                                    </div>
                                    
                                    {{-- Conteúdo --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between mb-1">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ $log->description }}
                                            </p>
                                            <span class="text-xs text-gray-500 ml-2 flex-shrink-0">
                                                {{ $log->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                        
                                        @if($log->field_name)
                                            <div class="text-xs text-gray-600 mt-1">
                                                <span class="font-medium">Campo:</span> {{ $log->field_name }}
                                                @if($log->old_value || $log->new_value)
                                                    <span class="mx-2">•</span>
                                                    <span class="line-through text-red-600">{{ $log->old_value }}</span>
                                                    <span class="mx-1">→</span>
                                                    <span class="text-green-600 font-medium">{{ $log->new_value }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        
                                        <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                                            <i class="fas fa-user text-gray-400"></i>
                                            <span>{{ $log->user->name ?? 'Sistema' }}</span>
                                            <span class="mx-1">•</span>
                                            <i class="fas fa-clock text-gray-400"></i>
                                            <span>{{ $log->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-history text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Nenhum histórico disponível</h3>
                            <p class="text-gray-500">As alterações nesta ordem de serviço aparecerão aqui.</p>
                        </div>
                    @endif
                </div>
                
                {{-- Tab: Anexos --}}
                <div x-show="activeTab === 'attachments'">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">Arquivos e Fotos</h4>
                        <button wire:click="openUploadModal" 
                                class="px-4 py-2 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white rounded-lg font-semibold transition-all flex items-center text-sm shadow-md">
                            <i class="fas fa-upload mr-2"></i>Upload
                        </button>
                    </div>
                    
                    @if($viewingWorkOrder->attachments->count() > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($viewingWorkOrder->attachments as $attachment)
                                <div class="relative group">
                                    {{-- Card do Anexo --}}
                                    <div class="bg-gray-50 rounded-xl border-2 border-gray-200 overflow-hidden hover:border-{{ $attachment->category_color }}-400 transition-all">
                                        {{-- Preview --}}
                                        @if($attachment->is_image)
                                            <div class="aspect-video bg-gray-100 overflow-hidden">
                                                <img src="{{ $attachment->file_url }}" 
                                                     alt="{{ $attachment->original_filename }}"
                                                     class="w-full h-full object-cover"
                                                     loading="lazy">
                                            </div>
                                        @else
                                            <div class="aspect-video bg-gradient-to-br from-{{ $attachment->category_color }}-100 to-{{ $attachment->category_color }}-200 flex items-center justify-center">
                                                <i class="fas fa-{{ $attachment->category_icon }} text-{{ $attachment->category_color }}-600 text-5xl"></i>
                                            </div>
                                        @endif
                                        
                                        {{-- Info --}}
                                        <div class="p-3">
                                            <div class="flex items-start justify-between gap-2 mb-2">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-semibold text-gray-900 truncate" title="{{ $attachment->original_filename }}">
                                                        {{ $attachment->original_filename }}
                                                    </p>
                                                    <p class="text-xs text-gray-500">{{ $attachment->file_size_formatted }}</p>
                                                </div>
                                                <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-{{ $attachment->category_color }}-100 text-{{ $attachment->category_color }}-800">
                                                    {{ $attachment->category_label }}
                                                </span>
                                            </div>
                                            
                                            @if($attachment->description)
                                                <p class="text-xs text-gray-600 mb-2 line-clamp-2">{{ $attachment->description }}</p>
                                            @endif
                                            
                                            <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-200">
                                                <span class="flex items-center">
                                                    <i class="fas fa-user text-gray-400 mr-1"></i>
                                                    {{ $attachment->user->name ?? 'Sistema' }}
                                                </span>
                                                <span>{{ $attachment->created_at->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Ações (aparecem no hover) --}}
                                    <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ $attachment->file_url }}" target="_blank" download
                                           class="w-8 h-8 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center justify-center shadow-lg transition-all">
                                            <i class="fas fa-download text-sm"></i>
                                        </a>
                                        <button wire:click="deleteAttachment({{ $attachment->id }})" 
                                                wire:confirm="Tem certeza que deseja remover este anexo?"
                                                class="w-8 h-8 bg-red-600 hover:bg-red-700 text-white rounded-lg flex items-center justify-center shadow-lg transition-all">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-paperclip text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Nenhum anexo</h3>
                            <p class="text-gray-500 mb-4">Adicione fotos ou documentos relacionados a esta OS.</p>
                            <button wire:click="openUploadModal" 
                                    class="px-6 py-2 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white rounded-xl font-semibold transition-all inline-flex items-center shadow-lg">
                                <i class="fas fa-upload mr-2"></i>Fazer Upload
                            </button>
                        </div>
                    @endif
                </div>
                
                {{-- Tab: Faturação --}}
                <div x-show="activeTab === 'invoicing'">
                    <div class="space-y-6">
                        @if($viewingWorkOrder->invoice_id)
                            {{-- Fatura Gerada --}}
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="flex items-center">
                                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg mr-4">
                                            <i class="fas fa-file-invoice-dollar text-white text-3xl"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-2xl font-bold text-green-900 mb-1">Fatura Gerada</h4>
                                            <p class="text-green-700 text-sm">Esta OS já foi faturada com sucesso</p>
                                        </div>
                                    </div>
                                    <span class="px-4 py-2 bg-green-600 text-white rounded-xl font-bold text-sm shadow-lg">
                                        <i class="fas fa-check-circle mr-2"></i>Faturado
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div class="bg-white rounded-xl p-4 shadow-sm">
                                        <p class="text-sm text-gray-600 mb-1">Número da Fatura</p>
                                        <p class="text-xl font-bold text-gray-900">{{ $viewingWorkOrder->invoice->invoice_number ?? 'N/A' }}</p>
                                    </div>
                                    <div class="bg-white rounded-xl p-4 shadow-sm">
                                        <p class="text-sm text-gray-600 mb-1">Data de Faturação</p>
                                        <p class="text-xl font-bold text-gray-900">{{ $viewingWorkOrder->invoiced_at ? $viewingWorkOrder->invoiced_at->format('d/m/Y H:i') : 'N/A' }}</p>
                                    </div>
                                    <div class="bg-white rounded-xl p-4 shadow-sm">
                                        <p class="text-sm text-gray-600 mb-1">Cliente</p>
                                        <p class="text-xl font-bold text-gray-900">{{ $viewingWorkOrder->invoice->client->name ?? 'N/A' }}</p>
                                    </div>
                                    <div class="bg-white rounded-xl p-4 shadow-sm">
                                        <p class="text-sm text-gray-600 mb-1">Valor Total</p>
                                        <p class="text-xl font-bold text-green-600">{{ number_format($viewingWorkOrder->total, 2, ',', '.') }} Kz</p>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <a href="/invoicing/sales/invoices/{{ $viewingWorkOrder->invoice_id }}" 
                                       class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-semibold transition-all flex items-center justify-center shadow-lg">
                                        <i class="fas fa-eye mr-2"></i>Ver Fatura
                                    </a>
                                    <a href="/invoicing/sales/invoices/{{ $viewingWorkOrder->invoice_id }}/preview" target="_blank"
                                       class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white rounded-xl font-semibold transition-all flex items-center justify-center shadow-lg">
                                        <i class="fas fa-file-pdf mr-2"></i>Preview / PDF
                                    </a>
                                </div>
                            </div>
                        @else
                            {{-- Nenhuma Fatura --}}
                            <div class="text-center py-12">
                                <div class="w-24 h-24 bg-gradient-to-br from-yellow-100 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                                    <i class="fas fa-file-invoice-dollar text-yellow-600 text-4xl"></i>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-3">Nenhuma Fatura Gerada</h3>
                                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                                    Esta ordem de serviço ainda não foi faturada. Clique no botão abaixo para gerar a fatura automaticamente.
                                </p>
                                
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl p-6 max-w-md mx-auto mb-6">
                                    <h4 class="font-bold text-blue-900 mb-3">O que será feito?</h4>
                                    <ul class="text-left space-y-2 text-sm text-blue-800">
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2"></i>
                                            <span>Criará uma fatura com todos os itens desta OS</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2"></i>
                                            <span>Vinculará automaticamente a fatura à OS</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2"></i>
                                            <span>Criará/usará o cliente baseado no proprietário</span>
                                        </li>
                                    </ul>
                                </div>
                                
                                <button wire:click="openInvoiceConfirmModal({{ $viewingWorkOrder->id }})" 
                                        class="px-8 py-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all inline-flex items-center shadow-xl hover:scale-105 transform">
                                    <i class="fas fa-file-invoice-dollar mr-3 text-xl"></i>Gerar Fatura Agora
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Footer com Botões de Ação --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <button wire:click="closeViewModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition-all">
                    Fechar
                </button>
                
                <div class="flex gap-3">
                    <button wire:click="edit({{ $viewingWorkOrder->id }})" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all flex items-center">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                    
                    <a href="{{ route('workshop.work-orders.print', $viewingWorkOrder->id) }}" target="_blank" 
                       class="px-6 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white rounded-xl font-semibold transition-all flex items-center shadow-lg">
                        <i class="fas fa-print mr-2"></i>Imprimir
                    </a>
                    
                    @if(!$viewingWorkOrder->invoice_id)
                        <button wire:click="openInvoiceConfirmModal({{ $viewingWorkOrder->id }})" 
                                class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition-all flex items-center shadow-lg">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>Gerar Fatura
                        </button>
                    @else
                        <a href="/invoicing/sales/invoices/{{ $viewingWorkOrder->invoice_id }}" 
                           class="px-6 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-semibold transition-all flex items-center shadow-lg">
                            <i class="fas fa-receipt mr-2"></i>Ver Fatura
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
