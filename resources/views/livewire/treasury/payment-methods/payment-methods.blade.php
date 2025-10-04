<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-money-bill-wave text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Métodos de Pagamento</h2>
                    <p class="text-green-100 text-sm">Gerir métodos de pagamento disponíveis</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-green-600 hover:bg-green-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Método
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Total</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $paymentMethods->total() }}</p>
            <p class="text-xs text-gray-500">Métodos disponíveis</p>
        </div>

        <!-- Ativos -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $activeCount }}</p>
            <p class="text-xs text-gray-500">Em uso</p>
        </div>

        <!-- Inativos -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-times-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Inativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $inactiveCount }}</p>
            <p class="text-xs text-gray-500">Desativados</p>
        </div>
    </div>

    <!-- Filtros e Pesquisa -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <input wire:model.live="search" type="text" placeholder="Pesquisar método..." 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
            </div>
            <div>
                <select wire:model.live="filterStatus" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500">
                    <option value="">Todos Status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </select>
            </div>
            <div>
                <select wire:model.live="perPage" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500">
                    <option value="10">10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-green-50 to-emerald-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Método</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Taxas</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-green-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($paymentMethods as $method)
                        <tr class="hover:bg-green-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg bg-{{ $method->color ?? 'gray' }}-100 flex items-center justify-center mr-3">
                                        <i class="fas {{ $method->icon }} text-{{ $method->color ?? 'gray' }}-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $method->name }}</p>
                                        @if($method->description)
                                            <p class="text-xs text-gray-500">{{ Str::limit($method->description, 30) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">
                                    {{ $method->code }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold bg-blue-100 text-blue-700 rounded-full">
                                    {{ ucfirst($method->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                @if($method->fee_percentage > 0 || $method->fee_fixed > 0)
                                    <div>
                                        @if($method->fee_percentage > 0)
                                            <span class="text-xs">{{ $method->fee_percentage }}%</span>
                                        @endif
                                        @if($method->fee_fixed > 0)
                                            <span class="text-xs">+ {{ number_format($method->fee_fixed, 2) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400">Sem taxa</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $method->id }})" 
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2
                                        {{ $method->is_active ? 'bg-green-600' : 'bg-gray-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $method->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="edit({{ $method->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $method->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhum método de pagamento encontrado</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $paymentMethods->links() }}
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.treasury.payment-methods.partials.form-modal')
    @include('livewire.treasury.payment-methods.partials.delete-modal')
</div>
