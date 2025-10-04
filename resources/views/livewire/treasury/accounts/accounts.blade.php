<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-wallet text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Contas Bancárias</h2>
                    <p class="text-purple-100 text-sm">Gerir contas bancárias da empresa</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Conta
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-wallet text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Total Contas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $accounts->total() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-dollar-sign text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Saldo Total AOA</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalBalanceAOA, 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-dollar-sign text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Saldo Total USD</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalBalanceUSD, 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Ativas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $activeCount }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <input wire:model.live="search" type="text" placeholder="Pesquisar conta..." 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
            </div>
            <div>
                <select wire:model.live="filterCurrency" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                    <option value="">Todas Moedas</option>
                    <option value="AOA">AOA</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
            <div>
                <select wire:model.live="perPage" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500">
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
                <thead class="bg-gradient-to-r from-purple-50 to-indigo-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Conta</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Banco</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Número</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Moeda</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-purple-700 uppercase tracking-wider">Saldo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-purple-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($accounts as $account)
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $account->account_name }}</p>
                                    @if($account->is_default)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-700 rounded-full">
                                            <i class="fas fa-star mr-1"></i>Padrão
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $account->bank->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-mono bg-gray-100 text-gray-700 rounded">
                                    {{ $account->account_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold bg-blue-100 text-blue-700 rounded-full">
                                    {{ $account->currency }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-lg font-bold text-gray-900">{{ number_format($account->current_balance, 2) }}</p>
                                <p class="text-xs text-gray-500">{{ $account->currency }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $account->id }})" 
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                                        {{ $account->is_active ? 'bg-purple-600' : 'bg-gray-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $account->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="edit({{ $account->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $account->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <i class="fas fa-wallet text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhuma conta bancária encontrada</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $accounts->links() }}
        </div>
    </div>

    @include('livewire.treasury.accounts.partials.form-modal')
    @include('livewire.treasury.accounts.partials.delete-modal')
</div>
