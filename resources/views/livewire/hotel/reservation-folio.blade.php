<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice-dollar text-3xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('hotel.reservations') }}" class="text-purple-200 hover:text-white">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h1 class="text-2xl md:text-3xl font-bold">Folio — {{ $reservation->reservation_number }}</h1>
                    </div>
                    <p class="text-purple-100 text-sm mt-1">
                        <i class="fas fa-user mr-1"></i>
                        {{ $reservation->guest?->name ?? $reservation->client?->name ?? 'Hóspede' }}
                        @if($reservation->room)
                            <span class="mx-2">·</span>
                            <i class="fas fa-door-open mr-1"></i>Quarto {{ $reservation->room->room_number }}
                        @endif
                        <span class="mx-2">·</span>
                        <i class="fas fa-calendar mr-1"></i>
                        {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}
                        →
                        {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}
                    </p>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button wire:click="openAdd" class="px-4 py-2.5 bg-white text-purple-700 hover:bg-purple-50 rounded-xl font-semibold text-sm shadow">
                    <i class="fas fa-plus mr-2"></i>Adicionar Consumo
                </button>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
            <div class="text-xs text-gray-500 uppercase font-bold">Hospedagem</div>
            <div class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($reservation->subtotal, 0, ',', '.') }} Kz</div>
            <div class="text-xs text-gray-400">{{ $reservation->nights }} noites × {{ number_format($reservation->room_rate, 0, ',', '.') }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
            <div class="text-xs text-gray-500 uppercase font-bold">Consumos (Extras)</div>
            <div class="text-2xl font-bold text-orange-600 mt-1">{{ number_format($reservation->extras_total, 0, ',', '.') }} Kz</div>
            <div class="text-xs text-gray-400">{{ $extras->count() }} linha(s)</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
            <div class="text-xs text-gray-500 uppercase font-bold">Total</div>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($reservation->total, 0, ',', '.') }} Kz</div>
            <div class="text-xs text-gray-400">Inclui IVA</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
            <div class="text-xs text-gray-500 uppercase font-bold">Saldo Devido</div>
            <div class="text-2xl font-bold {{ $reservation->balance_due > 0 ? 'text-red-600' : 'text-green-600' }} mt-1">
                {{ number_format(max(0, $reservation->balance_due), 0, ',', '.') }} Kz
            </div>
            <div class="text-xs text-gray-400">
                Pago: {{ number_format($reservation->paid_amount, 0, ',', '.') }} Kz
            </div>
        </div>
    </div>

    {{-- Quick Add by Category --}}
    <div class="bg-white rounded-2xl shadow-lg p-5 border border-gray-100">
        <h3 class="text-sm font-bold text-gray-600 uppercase mb-3">Adicionar Rapidamente</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            @foreach($categories as $key => $cat)
                <button wire:click="openAdd('{{ $key }}')"
                        class="flex flex-col items-center p-3 rounded-xl border-2 border-gray-100 hover:border-{{ $cat['color'] }}-400 hover:bg-{{ $cat['color'] }}-50 transition group">
                    <i class="fas {{ $cat['icon'] }} text-2xl text-{{ $cat['color'] }}-500 mb-2"></i>
                    <span class="text-xs font-semibold text-gray-700">{{ $cat['label'] }}</span>
                    @if(isset($byCategory[$key]))
                        <span class="mt-1 text-[10px] text-gray-400">
                            {{ $byCategory[$key]->count }} · {{ number_format($byCategory[$key]->total, 0, ',', '.') }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Charges Table --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-slate-50 border-b flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-list mr-2 text-purple-600"></i>Lançamentos do Folio</h3>
            <select wire:model.live="filterCategory" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg">
                <option value="">Todas as categorias</option>
                @foreach($categories as $key => $cat)
                    <option value="{{ $key }}">{{ $cat['label'] }}</option>
                @endforeach
            </select>
        </div>

        @if($extras->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-700">
                        <th class="px-4 py-3 text-left font-bold">Categoria</th>
                        <th class="px-4 py-3 text-left font-bold">Descrição</th>
                        <th class="px-4 py-3 text-center font-bold">Qtd</th>
                        <th class="px-4 py-3 text-right font-bold">Preço</th>
                        <th class="px-4 py-3 text-right font-bold">Total</th>
                        <th class="px-4 py-3 text-left font-bold">Data</th>
                        <th class="px-4 py-3 text-left font-bold">Lançado por</th>
                        <th class="px-4 py-3 text-center font-bold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($extras as $item)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-lg bg-{{ $item->category_color }}-100 text-{{ $item->category_color }}-700 text-xs font-bold">
                                <i class="fas {{ $item->category_icon }} mr-1"></i>{{ $item->category_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-800">
                            {{ $item->description }}
                            @if($item->notes)
                                <div class="text-xs text-gray-400 italic mt-0.5">{{ $item->notes }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-700">{{ number_format($item->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $item->charged_at?->format('d/m H:i') ?? $item->date?->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $item->chargedByUser?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="deleteCharge({{ $item->id }})"
                                    wire:confirm="Remover este consumo?"
                                    class="text-red-500 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-12 text-center">
            <i class="fas fa-file-invoice-dollar text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500">Sem consumos lançados neste folio.</p>
            <button wire:click="openAdd" class="mt-4 px-4 py-2 bg-purple-600 text-white rounded-xl font-semibold text-sm hover:bg-purple-700">
                <i class="fas fa-plus mr-2"></i>Adicionar Primeiro Consumo
            </button>
        </div>
        @endif
    </div>

    {{-- Modal: Add Charge --}}
    @if($showAddModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeAdd">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-t-2xl flex items-center justify-between">
                <h3 class="text-lg font-bold"><i class="fas fa-plus-circle mr-2"></i>Adicionar Consumo</h3>
                <button wire:click="closeAdd" class="text-white/70 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <form wire:submit.prevent="addCharge" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Categoria *</label>
                    <select wire:model="category" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                        @foreach($categories as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                    @error('category') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Descrição *</label>
                    <input type="text" wire:model="description"
                           placeholder="Ex: Água mineral 500ml, Sandwich, Toalha extra..."
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                    @error('description') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Quantidade *</label>
                        <input type="number" step="0.01" min="0.01" wire:model.live="quantity"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                        @error('quantity') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Preço Unitário (Kz) *</label>
                        <input type="number" step="0.01" min="0" wire:model.live="unitPrice"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                        @error('unitPrice') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-700 uppercase">Total</span>
                        <span class="text-xl font-bold text-emerald-700">{{ number_format(($quantity ?: 0) * ($unitPrice ?: 0), 2, ',', '.') }} Kz</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Observações</label>
                    <textarea wire:model="notes" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm"></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" wire:click="closeAdd"
                            class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl font-semibold text-sm hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold text-sm hover:shadow-lg">
                        <i class="fas fa-save mr-1"></i>Lançar Consumo
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
