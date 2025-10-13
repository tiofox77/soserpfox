<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-sitemap mr-3 text-emerald-600"></i>
                    Plano de Contas
                </h1>
                <p class="text-gray-600 mt-1">Gestão do plano de contas contabilístico</p>
            </div>
            <button wire:click="openModal" 
                    class="px-6 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-xl hover:shadow-lg transition flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Nova Conta
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                    <i class="fas fa-list text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Total de Contas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalAccounts }}</p>
            <p class="text-xs text-gray-500">Todas as contas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                    <i class="fas fa-wallet text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Contas de Ativo</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalAssets }}</p>
            <p class="text-xs text-gray-500">Caixa, bancos, clientes</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50">
                    <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Contas de Passivo</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalLiabilities }}</p>
            <p class="text-xs text-gray-500">Fornecedores, empréstimos</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50">
                    <i class="fas fa-arrow-trend-up text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Contas de Receita</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalRevenue }}</p>
            <p class="text-xs text-gray-500">Vendas e serviços</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pesquisar</label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500"
                       placeholder="Código ou nome da conta...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Conta</label>
                <select wire:model.live="typeFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                    <option value="">Todos os tipos</option>
                    <option value="asset">Ativo</option>
                    <option value="liability">Passivo</option>
                    <option value="equity">Capital Próprio</option>
                    <option value="revenue">Receitas</option>
                    <option value="expense">Gastos</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Natureza</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Nível</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($accounts as $account)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $account->code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $account->name }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                @if($account->type === 'asset') bg-green-100 text-green-800
                                @elseif($account->type === 'liability') bg-red-100 text-red-800
                                @elseif($account->type === 'equity') bg-indigo-100 text-indigo-800
                                @elseif($account->type === 'revenue') bg-blue-100 text-blue-800
                                @else bg-purple-100 text-purple-800
                                @endif">
                                {{ ucfirst($account->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $account->nature === 'debit' ? 'bg-cyan-100 text-cyan-800' : 'bg-orange-100 text-orange-800' }}">
                                {{ ucfirst($account->nature) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-center">
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">
                                Nível {{ $account->level }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($account->blocked)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                    <i class="fas fa-lock mr-1"></i>Bloqueada
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i>Ativa
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="edit({{ $account->id }})" 
                                    class="text-blue-600 hover:text-blue-800 mx-1">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button wire:click="delete({{ $account->id }})" 
                                    onclick="confirm('Tem certeza?') || event.stopImmediatePropagation()"
                                    class="text-red-600 hover:text-red-800 mx-1">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3 block"></i>
                            Nenhuma conta encontrada
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

    {{-- Modal --}}
    @if($showModal)
        @include('livewire.accounting.accounts.partials.form-modal')
    @endif
</div>
