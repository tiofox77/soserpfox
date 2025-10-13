<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-book mr-3 text-blue-600"></i>
                    Diários Contabilísticos
                </h1>
                <p class="text-gray-600 mt-1">Gestão de diários para organização de lançamentos</p>
            </div>
            <button wire:click="openModal" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Novo Diário
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total de Diários</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Todos os diários</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-book text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Diários Ativos</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['active'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Em uso</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Vendas & Compras</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['sales_purchase'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Principais</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-indigo-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Banco & Caixa</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['bank_cash'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Tesouraria</p>
                </div>
                <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-university text-cyan-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pesquisar</label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"
                       placeholder="Código ou nome do diário...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Diário</label>
                <select wire:model.live="typeFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os tipos</option>
                    <option value="sale">Vendas</option>
                    <option value="purchase">Compras</option>
                    <option value="cash">Caixa</option>
                    <option value="bank">Banco</option>
                    <option value="payroll">Salários</option>
                    <option value="adjustment">Ajustes</option>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Prefixo Seq.</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Último Nº</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($journals as $journal)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $journal->code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $journal->name }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                @if($journal->type === 'sale') bg-green-100 text-green-800
                                @elseif($journal->type === 'purchase') bg-red-100 text-red-800
                                @elseif($journal->type === 'cash') bg-yellow-100 text-yellow-800
                                @elseif($journal->type === 'bank') bg-blue-100 text-blue-800
                                @elseif($journal->type === 'payroll') bg-purple-100 text-purple-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                @if($journal->type === 'sale') Vendas
                                @elseif($journal->type === 'purchase') Compras
                                @elseif($journal->type === 'cash') Caixa
                                @elseif($journal->type === 'bank') Banco
                                @elseif($journal->type === 'payroll') Salários
                                @else Ajustes
                                @endif
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $journal->sequence_prefix }}</td>
                        <td class="px-6 py-4 text-sm text-center font-semibold text-gray-900">{{ $journal->last_number }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($journal->active)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i>Ativo
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                    <i class="fas fa-times mr-1"></i>Inativo
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="edit({{ $journal->id }})" 
                                    class="text-blue-600 hover:text-blue-800 mx-1">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button wire:click="delete({{ $journal->id }})" 
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
                            Nenhum diário encontrado
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $journals->links() }}
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
        @include('livewire.accounting.journals.partials.form-modal')
    @endif
</div>
