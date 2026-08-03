<div class="p-6">
    {{-- Header com animação --}}
    <div class="mb-6 flex items-center justify-between animate-fade-in">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                <div class="bg-gradient-to-br from-cyan-500 to-blue-600 p-3 rounded-xl mr-3 shadow-lg animate-bounce-slow">
                    <i class="fas fa-ship text-white"></i>
                </div>
                Importações de Mercadorias
            </h2>
            <p class="text-gray-600 mt-1 flex items-center">
                <i class="fas fa-globe-africa text-cyan-600 mr-2"></i>
                Gestão completa do processo de importação - Angola
            </p>
        </div>
        @can('invoicing.imports.create')
        <button wire:click="openCreateModal" 
                class="px-6 py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-2xl hover:scale-105 transform">
            <i class="fas fa-plus-circle mr-2"></i>Nova Importação
        </button>
        @endcan
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-cyan-200 text-xs font-medium">Total Importações</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-ship text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Em Trânsito</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['in_transit'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-shipping-fast text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Desembaraço Pendente</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['customs_pending'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-landmark text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Valor Total (CIF)</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_value'], 2) }}</p>
                    <p class="text-green-200 text-xs">USD</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-dollar-sign text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros com ícones --}}
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6 border border-gray-100">
        <div class="flex items-center mb-3">
            <i class="fas fa-filter text-cyan-600 mr-2"></i>
            <h3 class="font-semibold text-gray-700">Filtros</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar importação..." 
                       class="pl-10 w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500 transition-all">
            </div>
            
            <div class="relative">
                <i class="fas fa-flag absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <select wire:model.live="filterStatus" class="pl-10 w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="">📊 Todos os Status</option>
                    <option value="quotation">📋 Cotação</option>
                    <option value="order_placed">✅ Pedido Realizado</option>
                    <option value="payment_pending">⏳ Pagamento Pendente</option>
                    <option value="payment_confirmed">💰 Pagamento Confirmado</option>
                    <option value="in_transit">🚢 Em Trânsito</option>
                    <option value="customs_pending">🏛️ Desembaraço Pendente</option>
                    <option value="customs_inspection">🔍 Inspeção Alfandegária</option>
                    <option value="customs_cleared">✔️ Desembaraçado</option>
                    <option value="in_warehouse">📦 No Armazém</option>
                    <option value="completed">🎉 Concluído</option>
                </select>
            </div>
            
            <div class="relative">
                <i class="fas fa-building absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <select wire:model.live="filterSupplier" class="pl-10 w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="">🌐 Todos os Fornecedores</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <button wire:click="$refresh" 
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-all duration-200 hover:scale-105 transform">
                <i class="fas fa-sync-alt mr-2"></i>Atualizar
            </button>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-cyan-50 to-blue-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-cyan-700 uppercase">Nº Importação</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-cyan-700 uppercase">Fornecedor</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-cyan-700 uppercase">Origem</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-cyan-700 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-cyan-700 uppercase">Valor CIF</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-cyan-700 uppercase">ETA</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-cyan-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-cyan-700 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($imports as $import)
                        <tr class="hover:bg-cyan-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-mono bg-cyan-100 text-cyan-800 rounded-full font-bold">
                                    {{ $import->import_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $import->supplier->name }}</div>
                                <div class="text-xs text-gray-500">{{ $import->reference }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $import->origin_country }}</div>
                                <div class="text-xs text-gray-500">{{ $import->origin_port }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $icons = ['maritime' => 'fa-ship', 'air' => 'fa-plane', 'land' => 'fa-truck'];
                                    $labels = ['maritime' => 'Marítimo', 'air' => 'Aéreo', 'land' => 'Terrestre'];
                                @endphp
                                <span class="text-sm text-gray-600">
                                    <i class="fas {{ $icons[$import->transport_type] }} mr-1"></i>
                                    {{ $labels[$import->transport_type] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-sm font-bold text-gray-900">{{ number_format($import->cif_value, 2) }}</p>
                                <p class="text-xs text-gray-500">USD</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $import->expected_arrival_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-{{ $import->status_color }}-100 text-{{ $import->status_color }}-700">
                                    {{ $import->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="viewImport({{ $import->id }})" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                                            title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @can('invoicing.imports.edit')
                                    <button wire:click="openEditModal({{ $import->id }})" 
                                            class="p-2 text-cyan-600 hover:bg-cyan-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @endcan
                                    <button wire:click="printImport({{ $import->id }})" 
                                            class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                                            title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    @can('invoicing.imports.delete')
                                    <button wire:click="confirmDelete({{ $import->id }})" 
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                                            title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <i class="fas fa-ship text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhuma importação encontrada</p>
                                <p class="text-gray-400 text-sm mt-2">Crie sua primeira importação para começar</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($imports->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $imports->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Create/Edit com animação --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl animate-scale-in">
            {{-- Header da Modal com gradiente e ícone animado --}}
            <div class="bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 px-6 py-5 rounded-t-2xl relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-cyan-400/20 to-blue-400/20 animate-pulse"></div>
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-white/20 backdrop-blur-sm p-3 rounded-xl mr-3 animate-bounce-slow">
                            <i class="fas fa-{{ $isEditing ? 'edit' : 'plus-circle' }} text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">
                                {{ $isEditing ? 'Editar' : 'Nova' }} Importação
                            </h3>
                            <p class="text-cyan-100 text-xs mt-0.5">
                                <i class="fas fa-info-circle mr-1"></i>
                                {{ $isEditing ? 'Atualizar dados da importação' : 'Registar nova importação de mercadorias' }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeModal" class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-lg transition-all">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6 space-y-6">
                {{-- Secção: Identificação --}}
                <section class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-gray-100">
                        <span class="w-7 h-7 rounded-lg bg-cyan-100 text-cyan-600 flex items-center justify-center text-xs"><i class="fas fa-building"></i></span>
                        <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Identificação</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Fornecedor <span class="text-red-500">*</span></label>
                            <select wire:model="supplier_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition @error('supplier_id') border-red-400 @enderror">
                                <option value="">Selecione o fornecedor...</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Armazém Destino</label>
                            <select wire:model="warehouse_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                                <option value="">Selecione o armazém...</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}{{ $warehouse->is_default ? ' (Principal)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Referência / Ordem de Compra</label>
                            <input type="text" wire:model="reference" placeholder="Ex.: PO-2025-001" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                        </div>
                    </div>
                </section>

                {{-- Secção: Logística --}}
                <section class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-gray-100">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs"><i class="fas fa-route"></i></span>
                        <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Logística & Transporte</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Data do Pedido <span class="text-red-500">*</span></label>
                            <input type="date" wire:model="order_date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition @error('order_date') border-red-400 @enderror">
                            @error('order_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">ETA (Prev. Chegada)</label>
                            <input type="date" wire:model="expected_arrival_date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition @error('expected_arrival_date') border-red-400 @enderror">
                            @error('expected_arrival_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Transporte <span class="text-red-500">*</span></label>
                            <select wire:model="transport_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                                <option value="maritime">🚢 Marítimo</option>
                                <option value="air">✈️ Aéreo</option>
                                <option value="land">🚚 Terrestre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">País de Origem <span class="text-red-500">*</span></label>
                            <input type="text" list="origin-countries" wire:model="origin_country" placeholder="Ex.: China" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition @error('origin_country') border-red-400 @enderror">
                            <datalist id="origin-countries">
                                <option value="China"></option>
                                <option value="Portugal"></option>
                                <option value="Brasil"></option>
                                <option value="África do Sul"></option>
                                <option value="Índia"></option>
                                <option value="Emirados Árabes Unidos"></option>
                                <option value="Turquia"></option>
                                <option value="Espanha"></option>
                                <option value="Estados Unidos"></option>
                            </datalist>
                            @error('origin_country') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Porto de Origem</label>
                            <input type="text" wire:model="origin_port" placeholder="Ex.: Shanghai" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Porto de Destino</label>
                            <input type="text" wire:model="destination_port" placeholder="Luanda" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                        </div>
                    </div>
                </section>

                {{-- Secção: Valores (CIF) --}}
                <section class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-gray-100">
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs"><i class="fas fa-dollar-sign"></i></span>
                        <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Valores (USD)</h4>
                    </div>
                    <div class="bg-gradient-to-br from-slate-50 to-blue-50 border border-blue-100 rounded-xl p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Valor FOB <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="fob_value" class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition @error('fob_value') border-red-400 @enderror">
                                </div>
                                @error('fob_value') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Frete</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="freight_cost" class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Seguro</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="insurance_cost" class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 bg-white border-2 border-dashed border-blue-300 rounded-lg p-3 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Valor CIF</span>
                                <p class="text-[11px] text-gray-400"><i class="fas fa-calculator mr-1"></i>FOB + Frete + Seguro</p>
                            </div>
                            <span class="text-2xl font-extrabold text-blue-700">${{ number_format($cif_value ?: 0, 2) }}</span>
                        </div>
                    </div>
                </section>

                {{-- Secção: Observações --}}
                <section>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-comment-dots text-gray-400 mr-1"></i>Observações</label>
                    <textarea wire:model="notes" rows="3" placeholder="Notas adicionais sobre a importação..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition"></textarea>
                </section>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-2xl flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                <button wire:click="closeModal" 
                        class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 rounded-lg font-semibold text-sm transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="px-6 py-2.5 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg font-semibold text-sm transition shadow-md hover:shadow-lg disabled:opacity-60">
                    <i class="fas fa-save mr-2" wire:loading.remove wire:target="save"></i>
                    <i class="fas fa-spinner fa-spin mr-2" wire:loading wire:target="save"></i>
                    {{ $isEditing ? 'Atualizar' : 'Salvar' }} Importação
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Delete com animação --}}
    @if($showDeleteModal && $deletingImport)
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl animate-scale-in">
            {{-- Header da Modal --}}
            <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 rounded-t-2xl relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-red-400/20 to-red-600/20 animate-pulse"></div>
                <div class="relative flex items-center">
                    <div class="bg-white/20 backdrop-blur-sm p-3 rounded-xl mr-3">
                        <i class="fas fa-exclamation-triangle text-white text-xl animate-bounce-slow"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">
                            Confirmar Exclusão
                        </h3>
                        <p class="text-red-100 text-xs mt-0.5">
                            <i class="fas fa-info-circle mr-1"></i>
                            Ação irreversível
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-question-circle text-red-600 mr-2"></i>
                        Tem certeza que deseja eliminar a importação:
                    </p>
                    <div class="bg-white border-2 border-red-300 rounded-lg p-3 mt-3">
                        <p class="font-mono text-lg font-bold text-gray-900 text-center">
                            {{ $deletingImport->import_number }}
                        </p>
                        <p class="text-sm text-gray-600 text-center mt-1">
                            {{ $deletingImport->supplier->name }}
                        </p>
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <p class="text-sm text-yellow-800 flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-0.5"></i>
                        <span><strong>Aviso:</strong> Esta ação não pode ser desfeita! Todos os dados relacionados serão permanentemente eliminados.</span>
                    </p>
                </div>
            </div>
            
            <div class="px-6 pb-6 flex gap-3">
                <button wire:click="closeDeleteModal" 
                        class="flex-1 px-4 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition-all duration-200 hover:scale-105 transform">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="deleteImport" 
                        class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-all duration-200 hover:scale-105 transform">
                    <i class="fas fa-trash-alt mr-2"></i>Sim, Eliminar
                </button>
            </div>
        </div>
    </div>
    @endif

    <style>
        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes scale-in {
            from { 
                opacity: 0;
                transform: scale(0.95);
            }
            to { 
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
        
        .animate-scale-in {
            animation: scale-in 0.3s ease-out;
        }
        
        .animate-bounce-slow {
            animation: bounce-slow 3s ease-in-out infinite;
        }
        
        /* Hover effects */
        .hover\:scale-105:hover {
            transform: scale(1.05);
        }
        
        .hover\:scale-110:hover {
            transform: scale(1.10);
        }
        
        /* Transition improvements */
        .transition-all {
            transition: all 0.3s ease;
        }
    </style>
</div>
