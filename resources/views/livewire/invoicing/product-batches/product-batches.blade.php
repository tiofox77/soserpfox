<div>
    {{-- Toast Notifications --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:success.window="show = true; message = $event.detail.message; type = 'success'; setTimeout(() => show = false, 4000)"
         x-on:error.window="show = true; message = $event.detail.message; type = 'error'; setTimeout(() => show = false, 4000)"
         x-show="show" x-transition x-cloak
         class="fixed top-4 right-4 z-[100] max-w-sm">
        <div :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-4 rounded-xl shadow-2xl flex items-center">
            <i :class="type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'" class="mr-3 text-lg"></i>
            <span x-text="message" class="font-semibold text-sm"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-boxes text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">{{ __('Gestão de Lotes e Validades') }}</h1>
                    <p class="text-teal-100 text-sm">{{ __('Controle de validade e lotes de produtos') }}</p>
                </div>
            </div>
            @can('invoicing.product-batches.create')
            <button wire:click="create" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>{{ __('Novo Lote') }}
            </button>
            @endcan
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border-2 border-green-100">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase">{{ __('Ativos') }}</p>
                    <p class="text-2xl font-bold text-green-600">{{ $activeCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border-2 border-orange-100">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase">{{ __('A Expirar') }}</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $expiringCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border-2 border-red-100">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-times-circle text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase">{{ __('Expirados') }}</p>
                    <p class="text-2xl font-bold text-red-600">{{ $expiredCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border-2 border-blue-100">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-layer-group text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase">{{ __('Total') }}</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $batches->total() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('🔍 Buscar lote ou produto...') }}"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
            </div>
            <div>
                <select wire:model.live="filterProduct" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                    <option value="">{{ __('Todos os Produtos') }}</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="filterWarehouse" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                    <option value="">{{ __('Todos os Armazéns') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="filterStatus" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                    <option value="">{{ __('Todos os Status') }}</option>
                    <option value="active">{{ __('Ativo') }}</option>
                    <option value="expiring_soon">{{ __('A Expirar') }}</option>
                    <option value="expired">{{ __('Expirados') }}</option>
                    <option value="sold_out">{{ __('Esgotados') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-teal-50 to-cyan-50 border-b-2 border-teal-100">
            <h3 class="text-lg font-bold text-teal-900 flex items-center">
                <i class="fas fa-list mr-2"></i>{{ __('Lista de Lotes') }}
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Produto') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Lote') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Armazém') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Fabricação') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Validade') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Qtd. Total') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Disponível') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Acções') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($batches as $batch)
                    <tr class="hover:bg-teal-50/50 transition">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900">{{ $batch->product->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">{{ $batch->product->code ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-xs font-bold bg-blue-100 text-blue-800 rounded-full">
                                {{ $batch->batch_number ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <span class="px-3 py-1 text-xs font-bold bg-gray-100 text-gray-700 rounded-full">
                                <i class="fas fa-warehouse mr-1"></i>{{ $batch->warehouse->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            {{ $batch->manufacturing_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($batch->expiry_date)
                                <div class="text-sm font-semibold text-gray-900">{{ $batch->expiry_date->format('d/m/Y') }}</div>
                                @if($batch->days_until_expiry !== null)
                                    @if($batch->days_until_expiry < 0)
                                        <span class="text-xs font-bold text-red-600">
                                            <i class="fas fa-exclamation-circle mr-1"></i>Expirado há {{ abs($batch->days_until_expiry) }}d
                                        </span>
                                    @elseif($batch->days_until_expiry <= $batch->alert_days)
                                        <span class="text-xs font-bold text-orange-600">
                                            <i class="fas fa-clock mr-1"></i>{{ $batch->days_until_expiry }}d restantes
                                        </span>
                                    @else
                                        <span class="text-xs text-green-600">
                                            <i class="fas fa-check mr-1"></i>{{ $batch->days_until_expiry }}d restantes
                                        </span>
                                    @endif
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-sm font-bold text-gray-900">{{ number_format($batch->quantity, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-lg font-bold {{ $batch->quantity_available > 0 ? 'text-green-600' : 'text-gray-400' }}">
                                {{ number_format($batch->quantity_available, 2) }}
                            </span>
                            @if($batch->quantity > 0)
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                    <div class="h-1.5 rounded-full {{ $batch->status_color === 'green' ? 'bg-green-500' : ($batch->status_color === 'orange' ? 'bg-orange-500' : ($batch->status_color === 'red' ? 'bg-red-500' : 'bg-gray-400')) }}"
                                         style="width: {{ min(100, ($batch->quantity_available / $batch->quantity) * 100) }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 text-xs font-bold rounded-full
                                {{ $batch->status_color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $batch->status_color === 'orange' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $batch->status_color === 'red' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $batch->status_color === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}">
                                {{ $batch->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                @can('invoicing.product-batches.edit')
                                <button wire:click="edit({{ $batch->id }})"
                                        class="w-9 h-9 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-lg transition flex items-center justify-center"
                                        title="{{ __('Editar lote') }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endcan
                                @can('invoicing.product-batches.delete')
                                <button wire:click="delete({{ $batch->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este lote?"
                                        class="w-9 h-9 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg transition flex items-center justify-center"
                                        title="{{ __('Excluir lote') }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endcan
                                @cannot('invoicing.product-batches.edit')
                                    @cannot('invoicing.product-batches.delete')
                                        <span class="text-xs text-gray-400 italic">{{ __('Sem acções') }}</span>
                                    @endcannot
                                @endcannot
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-box-open text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold mb-2">{{ __('Nenhum lote encontrado') }}</p>
                                <p class="text-gray-400 text-sm">{{ __('Crie um novo lote usando o botão acima') }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $batches->links() }}
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-[calc(100%-1rem)] sm:w-full max-h-[94vh] overflow-y-auto">
            <div class="sticky top-0 bg-gradient-to-r from-teal-600 to-cyan-600 px-4 sm:px-8 py-4 sm:py-6 rounded-t-2xl flex items-center justify-between z-10">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-boxes text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">{{ $editingId ? 'Editar Lote' : 'Novo Lote' }}</h2>
                        <p class="text-teal-100 text-sm">{{ $editingId ? 'Actualizar dados do lote' : 'Registar um novo lote de produto' }}</p>
                    </div>
                </div>
                <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-4 sm:p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Produto *') }}</label>
                        <select wire:model="product_id" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                            <option value="">{{ __('Selecione um produto') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                            @endforeach
                        </select>
                        @error('product_id') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Número do Lote') }}</label>
                        <input type="text" wire:model="batch_number" placeholder="{{ __('Ex: L2025001') }}"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                        @error('batch_number') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Armazém') }}</label>
                        <select wire:model="warehouse_id" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                            <option value="">{{ __('Selecione um armazém') }}</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Data de Fabricação') }}</label>
                        <input type="date" wire:model="manufacturing_date"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                        @error('manufacturing_date') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Data de Validade') }}</label>
                        <input type="date" wire:model="expiry_date"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                        @error('expiry_date') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Quantidade *') }}</label>
                        <input type="number" wire:model="quantity" step="0.01" placeholder="0.00"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition text-lg font-semibold">
                        @error('quantity') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Preço de Custo') }}</label>
                        <input type="number" wire:model="cost_price" step="0.01" placeholder="0.00"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                        @error('cost_price') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Dias de Alerta *') }}</label>
                        <input type="number" wire:model="alert_days" min="1" max="365"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition">
                        @error('alert_days') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">{{ __('Alertar X dias antes da validade') }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Observações') }}</label>
                        <textarea wire:model="notes" rows="3"
                                  class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition"></textarea>
                        @error('notes') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
                <button wire:click="closeModal"
                        class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                </button>
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-700 hover:to-cyan-700 text-white px-8 py-3 rounded-xl font-semibold shadow-lg transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-check mr-2"></i>{{ $editingId ? 'Actualizar' : 'Criar' }} Lote
                    </span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-2"></i>{{ __('A processar...') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
