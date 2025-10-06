<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Gestão de Lotes e Validades</h1>
        <p class="text-gray-600 mt-1">Controle de validade e lotes de produtos</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-gray-600 text-sm">Lotes Ativos</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $activeCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-gray-600 text-sm">Expirando em Breve</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $expiringCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600">
                    <i class="fas fa-times-circle text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-gray-600 text-sm">Expirados</p>
                    <p class="text-2xl font-bold text-red-600">{{ $expiredCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-boxes text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-gray-600 text-sm">Total de Lotes</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $batches->total() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters and Actions --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <input type="text" wire:model.live="search" 
                       placeholder="Buscar por lote ou produto..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <select wire:model.live="filterProduct" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os Produtos</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select wire:model.live="filterWarehouse" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os Armazéns</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select wire:model.live="filterStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os Status</option>
                    <option value="active">Ativo</option>
                    <option value="expiring_soon">Expirando em Breve</option>
                    <option value="expired">Expirados</option>
                    <option value="sold_out">Esgotados</option>
                </select>
            </div>

            <div>
                <button wire:click="create" 
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Novo Lote
                </button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lote</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Armazém</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fabricação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Validade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disponível</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($batches as $batch)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $batch->product->name }}</div>
                        <div class="text-sm text-gray-500">{{ $batch->product->code }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        {{ $batch->batch_number ?? '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        {{ $batch->warehouse->name ?? '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        {{ $batch->manufacturing_date?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="px-6 py-4">
                        @if($batch->expiry_date)
                            <div class="text-sm text-gray-900">{{ $batch->expiry_date->format('d/m/Y') }}</div>
                            @if($batch->days_until_expiry !== null)
                                @if($batch->days_until_expiry < 0)
                                    <div class="text-xs text-red-600 font-semibold">
                                        Expirado há {{ abs($batch->days_until_expiry) }} dias
                                    </div>
                                @elseif($batch->days_until_expiry <= $batch->alert_days)
                                    <div class="text-xs text-orange-600 font-semibold">
                                        Expira em {{ $batch->days_until_expiry }} dias
                                    </div>
                                @else
                                    <div class="text-xs text-green-600">
                                        {{ $batch->days_until_expiry }} dias restantes
                                    </div>
                                @endif
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        {{ number_format($batch->quantity, 2, ',', '.') }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold {{ $batch->quantity_available > 0 ? 'text-green-600' : 'text-gray-400' }}">
                            {{ number_format($batch->quantity_available, 2, ',', '.') }}
                        </div>
                        @if($batch->quantity > 0)
                            <div class="text-xs text-gray-500">
                                {{ number_format(($batch->quantity_available / $batch->quantity) * 100, 0) }}%
                            </div>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            {{ $batch->status_color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $batch->status_color === 'orange' ? 'bg-orange-100 text-orange-800' : '' }}
                            {{ $batch->status_color === 'red' ? 'bg-red-100 text-red-800' : '' }}
                            {{ $batch->status_color === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}">
                            {{ $batch->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button wire:click="edit({{ $batch->id }})" 
                                class="text-blue-600 hover:text-blue-900 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button wire:click="delete({{ $batch->id }})" 
                                wire:confirm="Tem certeza que deseja excluir este lote?"
                                class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                        <p>Nenhum lote encontrado</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t">
            {{ $batches->links() }}
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">
                    {{ $editingId ? 'Editar Lote' : 'Novo Lote' }}
                </h2>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Produto *</label>
                        <select wire:model="product_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Selecione um produto</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                            @endforeach
                        </select>
                        @error('product_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Número do Lote</label>
                        <input type="text" wire:model="batch_number" placeholder="Ex: L2025001" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('batch_number') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Armazém</label>
                        <select wire:model="warehouse_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Selecione um armazém</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data de Fabricação</label>
                        <input type="date" wire:model="manufacturing_date" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('manufacturing_date') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data de Validade</label>
                        <input type="date" wire:model="expiry_date" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('expiry_date') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade *</label>
                        <input type="number" wire:model="quantity" step="0.01" placeholder="0.00"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('quantity') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Preço de Custo</label>
                        <input type="number" wire:model="cost_price" step="0.01" placeholder="0.00"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('cost_price') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dias de Alerta *</label>
                        <input type="number" wire:model="alert_days" min="1" max="365"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('alert_days') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">Alertar X dias antes da validade</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                        <textarea wire:model="notes" rows="3" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        @error('notes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                <button wire:click="closeModal" 
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button wire:click="save" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    {{ $editingId ? 'Atualizar' : 'Criar' }} Lote
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
