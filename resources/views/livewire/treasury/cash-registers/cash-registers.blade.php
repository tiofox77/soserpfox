<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-cash-register text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Caixas</h2>
                    <p class="text-orange-100 text-sm">Gestão de caixas e movimentos diários</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-orange-600 hover:bg-orange-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Caixa
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-cash-register text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Total Caixas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $cashRegisters->total() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-lock-open text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Caixas Abertos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $openCount }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-lock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Caixas Fechados</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $closedCount }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-dollar-sign text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Saldo Total</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalBalance, 2) }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <input wire:model.live="search" type="text" placeholder="Pesquisar caixa..." 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
            </div>
            <div>
                <select wire:model.live="filterStatus" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500">
                    <option value="">Todos Status</option>
                    <option value="open">Abertos</option>
                    <option value="closed">Fechados</option>
                </select>
            </div>
            <div>
                <select wire:model.live="perPage" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500">
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
                <thead class="bg-gradient-to-r from-orange-50 to-red-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-orange-700 uppercase tracking-wider">Caixa</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-orange-700 uppercase tracking-wider">Responsável</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-orange-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-orange-700 uppercase tracking-wider">Saldo Atual</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-orange-700 uppercase tracking-wider">Abertura</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-orange-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($cashRegisters as $register)
                        <tr class="hover:bg-orange-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-cash-register text-orange-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $register->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $register->code }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $register->user->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($register->status === 'open')
                                    <span class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">
                                        <i class="fas fa-lock-open mr-1"></i>Aberto
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">
                                        <i class="fas fa-lock mr-1"></i>Fechado
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-lg font-bold text-gray-900">{{ number_format($register->current_balance, 2) }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $register->opened_at ? $register->opened_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($register->status === 'closed')
                                    <button wire:click="openCashRegister({{ $register->id }})" class="text-green-600 hover:text-green-900 mr-3">
                                        <i class="fas fa-lock-open"></i>
                                    </button>
                                @else
                                    <button wire:click="closeCashRegister({{ $register->id }})" class="text-red-600 hover:text-red-900 mr-3">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                @endif
                                <button wire:click="edit({{ $register->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $register->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="fas fa-cash-register text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhum caixa encontrado</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $cashRegisters->links() }}
        </div>
    </div>

    @include('livewire.treasury.cash-registers.partials.form-modal')
    @include('livewire.treasury.cash-registers.partials.delete-modal')
</div>
