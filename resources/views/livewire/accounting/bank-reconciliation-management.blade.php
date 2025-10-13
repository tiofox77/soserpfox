<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-exchange-alt mr-3"></i>
            Reconciliação Bancária
        </h1>
        <p class="text-blue-100 mt-2">Import e matching automático de extratos bancários</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Reconciliações</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Todas</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Pendentes</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['pending'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">A processar</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Reconciliadas</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['reconciled'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Confirmadas</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Diferenças</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['differences'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Com divergências</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Messages --}}
    @if(session()->has('success'))
    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded">
        <p class="text-green-800">{{ session('success') }}</p>
    </div>
    @endif

    @if(session()->has('error'))
    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded">
        <p class="text-red-800">{{ session('error') }}</p>
    </div>
    @endif

    {{-- Import Form --}}
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-file-upload mr-2 text-blue-600"></i>
            Importar Extrato Bancário
        </h2>
        
        <form wire:submit.prevent="importFile" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Conta Bancária</label>
                <select wire:model="accountId" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Selecione...</option>
                    @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
                @error('accountId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Arquivo</label>
                <select wire:model="fileType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="csv">CSV</option>
                    <option value="mt940">MT940</option>
                    <option value="ofx">OFX</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo</label>
                <input type="file" wire:model="file" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                @error('file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-upload mr-2"></i>Importar
                </button>
            </div>
        </form>
    </div>

    {{-- Reconciliations List --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-blue-600"></i>
                Reconciliações
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Conta</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Saldo Extrato</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Saldo Contab.</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Diferença</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($reconciliations as $rec)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ \Carbon\Carbon::parse($rec->statement_date)->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $rec->account->code }} - {{ $rec->account->name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 font-mono">
                            {{ number_format($rec->statement_balance, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 font-mono">
                            {{ number_format($rec->book_balance, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-mono font-bold {{ $rec->difference == 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($rec->difference, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($rec->status === 'draft')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Rascunho</span>
                            @elseif($rec->status === 'reconciled')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Reconciliado</span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Aprovado</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="text-blue-600 hover:text-blue-900 font-semibold text-sm">
                                <i class="fas fa-eye mr-1"></i>Ver
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhuma reconciliação encontrada</p>
                            <p class="text-sm mt-2">Importe um extrato bancário para começar</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            {{ $reconciliations->links() }}
        </div>
    </div>
</div>
