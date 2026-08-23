<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-right-left text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Transferências entre Contas</h1>
                    <p class="text-cyan-100 text-sm">Mover fundos entre contas bancárias e caixas</p>
                </div>
            </div>
            <button wire:click="create" class="px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50 font-semibold text-sm">
                <i class="fas fa-plus mr-1"></i>Nova Transferência
            </button>
        </div>
    </div>

    {{-- Flash --}}
    <div x-data="{ msg: '' }" x-on:success.window="msg = $event.detail.message; setTimeout(() => msg = '', 4000)">
        <template x-if="msg">
            <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center">
                <i class="fas fa-check-circle mr-2"></i><span x-text="msg"></span>
            </div>
        </template>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-5 border border-cyan-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Transferências</p>
            <p class="text-3xl font-bold text-cyan-600">{{ $stats['count'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-blue-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Valor Total Movido</p>
            <p class="text-3xl font-bold text-blue-600">{{ number_format($stats['total'], 2, ',', '.') }} Kz</p>
        </div>
    </div>

    {{-- Pesquisa --}}
    <div class="mb-4">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar por nº, descrição ou referência..."
               class="w-full md:w-96 px-4 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nº</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">De</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Para</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Taxa</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($transfers as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $t->transfer_number }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ optional($t->transfer_date)->format('d/m/Y') }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700">
                                <i class="fas {{ $t->from_account_id ? 'fa-building-columns text-blue-500' : 'fa-cash-register text-amber-500' }} mr-1"></i>
                                {{ $t->fromAccount->account_name ?? $t->fromCashRegister->name ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-700">
                                <i class="fas {{ $t->to_account_id ? 'fa-building-columns text-blue-500' : 'fa-cash-register text-amber-500' }} mr-1"></i>
                                {{ $t->toAccount->account_name ?? $t->toCashRegister->name ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900">{{ number_format($t->amount, 2, ',', '.') }} Kz</td>
                            <td class="px-6 py-3 text-sm text-right text-gray-500">{{ $t->fee > 0 ? number_format($t->fee, 2, ',', '.') : '—' }}</td>
                            <td class="px-6 py-3 text-center">
                                <button wire:click="confirmDelete({{ $t->id }})" class="text-red-500 hover:text-red-700" title="Anular">
                                    <i class="fas fa-rotate-left"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400"><i class="fas fa-right-left text-4xl mb-2"></i><p class="text-sm">Nenhuma transferência registada</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
            <div class="px-6 py-4 border-t">{{ $transfers->links() }}</div>
        @endif
    </div>

    {{-- Modal Criar --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="backdrop-filter: blur(2px);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" wire:click.stop>
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-right-left mr-2 text-blue-600"></i>Nova Transferência</h3>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">De *</label>
                        <select wire:model="fromSelection" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Selecionar origem...</option>
                            @if($accounts->count())
                                <optgroup label="Contas Bancárias">
                                    @foreach($accounts as $a)<option value="account:{{ $a->id }}">{{ $a->account_name }} ({{ number_format($a->current_balance, 2, ',', '.') }} Kz)</option>@endforeach
                                </optgroup>
                            @endif
                            @if($cashRegisters->count())
                                <optgroup label="Caixas">
                                    @foreach($cashRegisters as $c)<option value="cash:{{ $c->id }}">{{ $c->name }} ({{ number_format($c->current_balance, 2, ',', '.') }} Kz)</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                        @error('fromSelection')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Para *</label>
                        <select wire:model="toSelection" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Selecionar destino...</option>
                            @if($accounts->count())
                                <optgroup label="Contas Bancárias">
                                    @foreach($accounts as $a)<option value="account:{{ $a->id }}">{{ $a->account_name }}</option>@endforeach
                                </optgroup>
                            @endif
                            @if($cashRegisters->count())
                                <optgroup label="Caixas">
                                    @foreach($cashRegisters as $c)<option value="cash:{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                        @error('toSelection')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Valor (Kz) *</label>
                        <input wire:model.blur="amount" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('amount')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Taxa (Kz)</label>
                        <input wire:model="fee" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('fee')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Data *</label>
                    <input wire:model="transfer_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    @error('transfer_date')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Descrição</label>
                    <input wire:model="description" type="text" placeholder="Motivo da transferência..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end gap-2">
                <button wire:click="closeModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold">Cancelar</button>
                <button wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                    <span wire:loading.remove wire:target="save"><i class="fas fa-check mr-1"></i>Registar</span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-1"></i>A guardar...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Anular --}}
    @if($showDeleteModal && $transferToDelete)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="backdrop-filter: blur(2px);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-rotate-left text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Anular transferência {{ $transferToDelete->transfer_number }}?</h3>
            <p class="text-gray-600 text-sm mb-6">Os saldos das contas serão revertidos e as transações associadas removidas.</p>
            <div class="flex justify-center gap-2">
                <button wire:click="closeModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold">Cancelar</button>
                <button wire:click="deleteTransfer" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Anular e reverter</button>
            </div>
        </div>
    </div>
    @endif
</div>
