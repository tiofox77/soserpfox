<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-amber-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-truck text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">{{ __('Guias de Transporte / Remessa') }}</h1>
                    <p class="text-orange-100 text-sm">{{ __('Documentos de transporte de mercadorias (GT / GR)') }}</p>
                </div>
            </div>
            <button wire:click="create" class="px-4 py-2 bg-white text-orange-600 rounded-lg hover:bg-orange-50 font-semibold text-sm">
                <i class="fas fa-plus mr-1"></i>{{ __('Nova Guia') }}
            </button>
        </div>
    </div>

    <div x-data="{ msg: '', type: 'success' }"
         x-on:success.window="type='success'; msg = $event.detail.message; setTimeout(() => msg = '', 5000)"
         x-on:error.window="type='error'; msg = $event.detail.message; setTimeout(() => msg = '', 8000)">
        <template x-if="msg">
            <div class="mb-4 px-4 py-3 rounded-xl flex items-center border"
                 :class="type==='error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800'">
                <i class="fas mr-2" :class="type==='error' ? 'fa-exclamation-circle' : 'fa-check-circle'"></i><span x-text="msg"></span>
            </div>
        </template>
    </div>

    <div class="mb-4">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Pesquisar por nº...') }}"
               class="w-full md:w-80 px-4 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Nº') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Tipo') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Data') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Cliente') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Viatura') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">AGT</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($guides as $g)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $g->guide_number }}</td>
                            <td class="px-6 py-3 text-sm"><span class="px-2 py-1 rounded-full text-xs font-semibold {{ $g->type === 'GT' ? 'bg-orange-100 text-orange-700' : 'bg-amber-100 text-amber-700' }}">{{ $g->typeLabel() }}</span></td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ optional($g->issue_date)->format('d/m/Y') }}</td>
                            <td class="px-6 py-3 text-sm text-gray-900">{{ $g->client->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $g->vehicle_plate ?: '—' }}</td>
                            <td class="px-6 py-3 text-center">
                                @php
                                    $agtBadge = match($g->agt_status) {
                                        'submitted', 'validated', 'accepted' => ['bg-green-100 text-green-700', 'Comunicada'],
                                        'rejected' => ['bg-red-100 text-red-700', 'Rejeitada'],
                                        default => ['bg-gray-100 text-gray-600', 'Pendente'],
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $agtBadge[0] }}">{{ $agtBadge[1] }}</span>
                            </td>
                            <td class="px-6 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('invoicing.transport-guides.pdf', $g->id) }}" target="_blank" class="text-blue-500 hover:text-blue-700 mr-3" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                @if(!in_array($g->agt_status, ['submitted','validated','accepted']))
                                    <button wire:click="submitAgt({{ $g->id }})" wire:loading.attr="disabled" class="text-emerald-600 hover:text-emerald-800 mr-3" title="{{ __('Comunicar à AGT') }}"><i class="fas fa-paper-plane"></i></button>
                                @endif
                                <button wire:click="confirmDelete({{ $g->id }})" class="text-red-500 hover:text-red-700" title="{{ __('Anular') }}"><i class="fas fa-ban"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400"><i class="fas fa-truck text-4xl mb-2"></i><p class="text-sm">{{ __('Nenhuma guia registada') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($guides->hasPages())<div class="px-6 py-4 border-t">{{ $guides->links() }}</div>@endif
    </div>

    {{-- Modal Criar --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="backdrop-filter: blur(2px);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b flex items-center justify-between z-10">
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-truck mr-2 text-orange-600"></i>{{ __('Nova Guia') }}</h3>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Tipo *') }}</label>
                        <select wire:model="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="GT">{{ __('Guia de Transporte') }}</option>
                            <option value="GR">{{ __('Guia de Remessa') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Fatura de origem (opcional)') }}</label>
                        <select wire:model.live="sourceInvoiceId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">{{ __('— Sem fatura —') }}</option>
                            @foreach($invoices as $inv)<option value="{{ $inv->id }}">{{ $inv->invoice_number }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Cliente *') }}</label>
                        <select wire:model="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">{{ __('Selecionar...') }}</option>
                            @foreach($clients as $cl)<option value="{{ $cl->id }}">{{ $cl->name }}</option>@endforeach
                        </select>
                        @error('client_id')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Data de emissão *') }}</label>
                        <input wire:model="issue_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Data/hora de carga') }}</label>
                        <input wire:model="loading_datetime" type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Matrícula da viatura') }}</label>
                        <input wire:model="vehicle_plate" type="text" placeholder="LD-00-00-AA" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Motorista') }}</label>
                        <input wire:model="driver_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Doc. motorista') }}</label>
                        <input wire:model="driver_document" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Local de carga') }}</label>
                        <input wire:model="load_address" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Local de descarga') }}</label>
                        <input wire:model="unload_address" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>

                {{-- Itens --}}
                <div class="border-t pt-4">
                    <div class="flex items-end gap-2 mb-3">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Adicionar produto') }}</label>
                            <select wire:model="productToAdd" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">{{ __('Selecionar produto...') }}</option>
                                @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <button wire:click="addProduct" type="button" class="px-3 py-2 bg-orange-600 text-white rounded-lg text-sm font-semibold hover:bg-orange-700"><i class="fas fa-plus"></i></button>
                    </div>
                    @error('items')<p class="text-red-500 text-xs mb-2">{{ $message }}</p>@enderror
                    <div class="border rounded-lg overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Descrição') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-500 uppercase w-28">{{ __('Qtd.') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-500 uppercase w-20">{{ __('Un.') }}</th>
                                <th class="w-10"></th>
                            </tr></thead>
                            <tbody class="divide-y">
                                @forelse($items as $i => $item)
                                    <tr>
                                        <td class="px-3 py-2"><input wire:model="items.{{ $i }}.description" type="text" class="w-full px-2 py-1 border border-gray-200 rounded text-sm"></td>
                                        <td class="px-3 py-2"><input wire:model="items.{{ $i }}.quantity" type="number" step="0.001" min="0" class="w-full px-2 py-1 border border-gray-200 rounded text-sm text-right"></td>
                                        <td class="px-3 py-2"><input wire:model="items.{{ $i }}.unit" type="text" class="w-full px-2 py-1 border border-gray-200 rounded text-sm"></td>
                                        <td class="px-3 py-2 text-center"><button wire:click="removeItem({{ $i }})" type="button" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-400 text-sm">{{ __('Sem itens. Escolha uma fatura de origem ou adicione produtos.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Observações') }}</label>
                    <textarea wire:model="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                </div>
            </div>
            <div class="sticky bottom-0 bg-white px-6 py-4 border-t flex justify-end gap-2">
                <button wire:click="closeModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold">{{ __('Cancelar') }}</button>
                <button wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-semibold hover:bg-orange-700">
                    <span wire:loading.remove wire:target="save"><i class="fas fa-check mr-1"></i>{{ __('Registar Guia') }}</span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A guardar...') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Anular --}}
    @if($showDeleteModal && $guideToDelete)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="backdrop-filter: blur(2px);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-ban text-2xl text-red-500"></i></div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Anular guia {{ $guideToDelete->guide_number }}?</h3>
            <p class="text-gray-600 text-sm mb-6">{{ __('A guia será marcada como anulada e removida da lista.') }}</p>
            <div class="flex justify-center gap-2">
                <button wire:click="closeModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold">{{ __('Cancelar') }}</button>
                <button wire:click="deleteGuide" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">{{ __('Anular') }}</button>
            </div>
        </div>
    </div>
    @endif
</div>
