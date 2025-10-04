<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-exchange-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Transações</h2>
                    <p class="text-teal-100 text-sm">Movimentos financeiros e extratos</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Transação
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-teal-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-exchange-alt text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-teal-600 font-semibold mb-2">Total Transações</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $transactions->total() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-arrow-down text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Entradas</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalIncome, 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-arrow-up text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Saídas</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalExpense, 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-balance-scale text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Saldo</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalIncome - $totalExpense, 2) }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <input wire:model.live="search" type="text" placeholder="Pesquisar transação..." 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
            </div>
            <div>
                <select wire:model.live="filterType" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500">
                    <option value="">Todos Tipos</option>
                    <option value="income">Entrada</option>
                    <option value="expense">Saída</option>
                    <option value="transfer">Transferência</option>
                </select>
            </div>
            <div>
                <select wire:model.live="filterStatus" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500">
                    <option value="">Todos Status</option>
                    <option value="pending">Pendente</option>
                    <option value="completed">Concluído</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div>
                <select wire:model.live="perPage" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500">
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
                <thead class="bg-gradient-to-r from-teal-50 to-cyan-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-teal-700 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-teal-700 uppercase tracking-wider">Número</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-teal-700 uppercase tracking-wider">Descrição</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-teal-700 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-teal-700 uppercase tracking-wider">Método</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-teal-700 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-teal-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-teal-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($transactions as $transaction)
                        <tr class="hover:bg-teal-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $transaction->transaction_date->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-mono bg-gray-100 text-gray-700 rounded">
                                    {{ $transaction->transaction_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-gray-900">{{ Str::limit($transaction->description ?? 'Sem descrição', 30) }}</p>
                                @if($transaction->category)
                                    <p class="text-xs text-gray-500">{{ ucfirst($transaction->category) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($transaction->type === 'income')
                                    <span class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">
                                        <i class="fas fa-arrow-down mr-1"></i>Entrada
                                    </span>
                                @elseif($transaction->type === 'expense')
                                    <span class="px-3 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">
                                        <i class="fas fa-arrow-up mr-1"></i>Saída
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-semibold bg-blue-100 text-blue-700 rounded-full">
                                        <i class="fas fa-exchange-alt mr-1"></i>Transferência
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $transaction->paymentMethod->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-lg font-bold {{ $transaction->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction->type === 'income' ? '+' : '-' }} {{ number_format($transaction->amount, 2) }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $transaction->currency }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($transaction->status === 'completed')
                                    <span class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">
                                        Concluído
                                    </span>
                                @elseif($transaction->status === 'pending')
                                    <span class="px-3 py-1 text-xs font-semibold bg-yellow-100 text-yellow-700 rounded-full">
                                        Pendente
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">
                                        Cancelado
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="view({{ $transaction->id }})" class="text-teal-600 hover:text-teal-900 mr-3">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button wire:click="edit({{ $transaction->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $transaction->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <i class="fas fa-exchange-alt text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhuma transação encontrada</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $transactions->links() }}
        </div>
    </div>

    @include('livewire.treasury.transactions.partials.form-modal')
    @include('livewire.treasury.transactions.partials.delete-modal')
</div>
