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
