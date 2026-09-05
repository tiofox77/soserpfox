<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-history text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Histórico de Turnos') }}</h2>
                    <p class="text-indigo-100 text-sm">{{ __('Relatório completo de todos os turnos') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-filter text-indigo-600 mr-2"></i>
            {{ __('Filtros') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Data Inicial') }}</label>
                <input type="date" wire:model.live="dateFrom"
                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Data Final') }}</label>
                <input type="date" wire:model.live="dateTo"
                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            {{-- O filtro por operador só existe para quem pode ver os turnos
                 de todos. Sem esse direito, o ecrã mostra apenas os do próprio
                 e a lista de colegas não tem aqui nada que fazer. --}}
            @if(!$this->ownOnly)
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Usuário') }}</label>
                <select wire:model.live="userId"
                        class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    {{-- Só o rótulo é que se traduz. O value="" e os value="open"/"closed"
                         são dados que vão para a query — mexer neles partia o filtro. --}}
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="flex items-end">
                <p class="text-xs text-gray-500 pb-2">
                    <i class="fas fa-user-lock mr-1"></i>{{ __('A mostrar apenas os seus turnos.') }}
                </p>
            </div>
            @endif
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Status') }}</label>
                <select wire:model.live="status"
                        class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="open">{{ __('Aberto') }}</option>
                    <option value="closed">{{ __('Fechado') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Tabela de Turnos --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-indigo-50 to-purple-50">
                    <tr>
                        <th class="text-left p-4 text-sm font-bold text-gray-700">{{ __('Turno') }}</th>
                        <th class="text-left p-4 text-sm font-bold text-gray-700">{{ __('Usuário') }}</th>
                        <th class="text-left p-4 text-sm font-bold text-gray-700">{{ __('Abertura') }}</th>
                        <th class="text-left p-4 text-sm font-bold text-gray-700">{{ __('Fechamento') }}</th>
                        <th class="text-right p-4 text-sm font-bold text-gray-700">{{ __('Saldo Inicial') }}</th>
                        <th class="text-right p-4 text-sm font-bold text-gray-700">{{ __('Total Vendas') }}</th>
                        <th class="text-right p-4 text-sm font-bold text-gray-700">{{ __('Diferença') }}</th>
                        <th class="text-center p-4 text-sm font-bold text-gray-700">{{ __('Status') }}</th>
                        <th class="text-center p-4 text-sm font-bold text-gray-700">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                            <td class="p-4">
                                <span class="font-bold text-indigo-600">{{ $shift->shift_number }}</span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-2">
                                        <i class="fas fa-user text-indigo-600 text-xs"></i>
                                    </div>
                                    <span class="text-sm text-gray-700">{{ $shift->user->name }}</span>
                                </div>
                            </td>
                            <td class="p-4 text-sm text-gray-700">
                                {{ $shift->opened_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-4 text-sm text-gray-700">
                                {{ $shift->closed_at ? $shift->closed_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="p-4 text-right text-sm text-gray-900">
                                {{ number_format($shift->opening_balance, 2, ',', '.') }} Kz
                            </td>
                            <td class="p-4 text-right font-bold text-green-600">
                                {{ number_format($shift->total_sales, 2, ',', '.') }} Kz
                            </td>
                            <td class="p-4 text-right font-bold {{ $shift->cash_difference > 0 ? 'text-green-600' : ($shift->cash_difference < 0 ? 'text-red-600' : 'text-gray-600') }}">
                                @if($shift->cash_difference)
                                    {{ $shift->cash_difference > 0 ? '+' : '' }}{{ number_format($shift->cash_difference, 2, ',', '.') }} Kz
                                @else
                                    -
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if($shift->status === 'open')
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                                        <i class="fas fa-circle text-xs mr-1"></i>{{ __('Aberto') }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">
                                        <i class="fas fa-lock text-xs mr-1"></i>{{ __('Fechado') }}
                                    </span>
                                @endif
                            </td>
                            <td class="p-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <button wire:click="viewDetails({{ $shift->id }})"
                                            class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition"
                                            title="{{ __('Ver Detalhes') }}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button wire:click="exportShift({{ $shift->id }})"
                                            class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition"
                                            title="{{ __('PDF A4 do Resumo') }}">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <a href="{{ route('invoicing.pos.export.shift-ticket', $shift->id) }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition inline-flex items-center justify-center"
                                       title="{{ __('Ticket Térmico (80mm) — abre nova aba e imprime') }}">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-12 text-center">
                                <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                                <p class="text-gray-500 font-semibold">{{ __('Nenhum turno encontrado') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        <div class="p-4 border-t border-gray-200">
            {{ $shifts->links() }}
        </div>
    </div>

    {{-- Modal: Detalhes do Turno --}}
    @if($showDetailModal && $selectedShift)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice mr-2"></i>
                    {{-- O número entra por :numero e os dois pontos ficam DENTRO da
                         cadeia: em francês escreve-se "Détails : ", com espaço antes
                         do sinal, e isso só é possível se o tradutor os tiver. --}}
                    {{ __('Detalhes do Turno: :numero', ['numero' => $selectedShift->shift_number]) }}
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                {{-- Informações Gerais --}}
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-xl">
                        <p class="text-xs text-blue-600 font-semibold mb-1">{{ __('Usuário') }}</p>
                        <p class="text-lg font-bold text-blue-900">{{ $selectedShift->user->name }}</p>
                    </div>
                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4 rounded-xl">
                        <p class="text-xs text-purple-600 font-semibold mb-1">{{ __('Status') }}</p>
                        {{-- status_label vem do modelo e ainda está em português;
                             traduzi-lo por variável daria uma chave invisível ao
                             varrimento. Fica para o lote dos modelos. --}}
                        <p class="text-lg font-bold text-purple-900">{{ $selectedShift->status_label }}</p>
                    </div>
                    <div class="bg-gradient-to-r from-green-50 to-green-100 p-4 rounded-xl">
                        <p class="text-xs text-green-600 font-semibold mb-1">{{ __('Abertura') }}</p>
                        <p class="text-lg font-bold text-green-900">{{ $selectedShift->opened_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="bg-gradient-to-r from-orange-50 to-orange-100 p-4 rounded-xl">
                        <p class="text-xs text-orange-600 font-semibold mb-1">{{ __('Fechamento') }}</p>
                        <p class="text-lg font-bold text-orange-900">{{ $selectedShift->closed_at ? $selectedShift->closed_at->format('d/m/Y H:i') : __('Em aberto') }}</p>
                    </div>
                </div>

                {{-- Valores --}}
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-6 mb-6">
                    {{-- Os emojis ficam fora do __(): são decoração igual nas três
                         línguas, e dentro da chave cada tradutor teria de os copiar. --}}
                    <h4 class="font-bold text-indigo-900 mb-4">💰 {{ __('Valores Financeiros') }}</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-indigo-600 mb-1">{{ __('Saldo Inicial') }}</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ number_format($selectedShift->opening_balance, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-xs text-indigo-600 mb-1">{{ __('Total de Vendas') }}</p>
                            <p class="text-2xl font-bold text-green-600">{{ number_format($selectedShift->total_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-xs text-indigo-600 mb-1">{{ __('Dinheiro Esperado') }}</p>
                            <p class="text-2xl font-bold text-blue-600">{{ number_format($selectedShift->expected_cash ?? 0, 2, ',', '.') }} Kz</p>
                        </div>
                        @if($selectedShift->status === 'closed')
                        <div>
                            <p class="text-xs text-indigo-600 mb-1">{{ __('Dinheiro Contado') }}</p>
                            <p class="text-2xl font-bold text-purple-600">{{ number_format($selectedShift->actual_cash ?? 0, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-xs text-indigo-600 mb-1">{{ __('Diferença') }}</p>
                            <p class="text-2xl font-bold {{ $selectedShift->cash_difference > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $selectedShift->cash_difference > 0 ? '+' : '' }}{{ number_format($selectedShift->cash_difference ?? 0, 2, ',', '.') }} Kz
                            </p>
                        </div>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-4 gap-3">
                        <div class="bg-white p-3 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">💵 {{ __('Dinheiro') }}</p>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($selectedShift->cash_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div class="bg-white p-3 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">💳 {{ __('Cartão') }}</p>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($selectedShift->card_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div class="bg-white p-3 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">🏦 {{ __('Transferência') }}</p>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($selectedShift->bank_transfer_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div class="bg-white p-3 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">📋 {{ __('Outros') }}</p>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($selectedShift->other_sales, 2, ',', '.') }} Kz</p>
                        </div>
                    </div>
                </div>

                {{-- Estatísticas --}}
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-purple-50 p-4 rounded-xl">
                        <p class="text-sm text-purple-600 mb-1">{{ __('Total de Faturas') }}</p>
                        <p class="text-3xl font-bold text-purple-900">{{ $selectedShift->total_invoices }}</p>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-xl">
                        <p class="text-sm text-orange-600 mb-1">{{ __('Total de Recibos') }}</p>
                        <p class="text-3xl font-bold text-orange-900">{{ $selectedShift->total_receipts }}</p>
                    </div>
                </div>

                {{-- Transações --}}
                <div class="border-t pt-6">
                    @php
                        $__nTransacoes = $selectedShift->transactions->count();
                    @endphp
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-list text-indigo-600 mr-2"></i>
                        {{-- trans_choice e não "Transações (:n)": com uma transação só,
                             o inglês e o francês querem o singular. O formato com o
                             número entre parêntesis mantém-se nas três línguas. --}}
                        {{ trans_choice('Transação (:n)|Transações (:n)', $__nTransacoes, ['n' => $__nTransacoes]) }}
                    </h4>
                    @if($__nTransacoes > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-left p-3 text-xs font-semibold text-gray-700">{{ __('Tipo') }}</th>
                                        <th class="text-left p-3 text-xs font-semibold text-gray-700">{{ __('Referência') }}</th>
                                        <th class="text-left p-3 text-xs font-semibold text-gray-700">{{ __('Método') }}</th>
                                        <th class="text-right p-3 text-xs font-semibold text-gray-700">{{ __('Valor') }}</th>
                                        <th class="text-right p-3 text-xs font-semibold text-gray-700">{{ __('Data') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedShift->transactions as $transaction)
                                        <tr class="border-b border-gray-100">
                                            <td class="p-3">
                                                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs font-bold">
                                                    {{ $transaction->type_label }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-sm text-gray-700">{{ $transaction->reference_number }}</td>
                                            <td class="p-3 text-sm text-gray-700">{{ $transaction->payment_method_label }}</td>
                                            <td class="p-3 text-right font-bold text-gray-900">{{ number_format($transaction->amount, 2, ',', '.') }} Kz</td>
                                            <td class="p-3 text-right text-sm text-gray-500">{{ $transaction->created_at->format('d/m H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-6 bg-gray-50 rounded-lg">
                            <i class="fas fa-inbox text-gray-300 text-3xl mb-2"></i>
                            <p class="text-gray-500">{{ __('Nenhuma transação registrada') }}</p>
                        </div>
                    @endif
                </div>

                {{-- Notas --}}
                @if($selectedShift->closing_notes || $selectedShift->difference_reason)
                <div class="border-t pt-6 mt-6">
                    <h4 class="font-bold text-gray-900 mb-3">📝 {{ __('Observações') }}</h4>
                    @if($selectedShift->closing_notes)
                        <div class="bg-blue-50 p-4 rounded-lg mb-3">
                            {{-- Os dois pontos vão dentro da cadeia por causa do francês,
                                 que escreve "Notes de clôture :" com espaço antes. --}}
                            <p class="text-sm font-semibold text-blue-900 mb-1">{{ __('Notas de Fechamento:') }}</p>
                            <p class="text-sm text-blue-800">{{ $selectedShift->closing_notes }}</p>
                        </div>
                    @endif
                    @if($selectedShift->difference_reason)
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <p class="text-sm font-semibold text-yellow-900 mb-1">{{ __('Motivo da Diferença:') }}</p>
                            <p class="text-sm text-yellow-800">{{ $selectedShift->difference_reason }}</p>
                        </div>
                    @endif
                </div>
                @endif
            </div>

            <div class="border-t p-6 bg-gray-50 rounded-b-2xl flex justify-end">
                <button wire:click="closeModal"
                        class="px-6 py-3 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700 transition">
                    {{ __('Fechar') }}
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
