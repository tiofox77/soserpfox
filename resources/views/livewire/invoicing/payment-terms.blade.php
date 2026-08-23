<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-calendar-check mr-3 text-indigo-600"></i>
                {{ __('Condições de Pagamento') }}
            </h2>
            <p class="text-gray-600 mt-1">{{ __('Pronto pagamento, 15 dias, 30 dias, depósito… — o cliente escolhe uma e o vencimento sai daqui.') }}</p>
        </div>
        <button wire:click="novo"
                class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl font-bold transition-all duration-300 shadow-lg hover:scale-105 active:scale-95">
            <i class="fas fa-plus mr-2"></i>{{ __('Nova Condição') }}
        </button>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Nome') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Dias') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Padrão') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Estado') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($terms as $term)
                    <tr class="hover:bg-indigo-50/40 transition">
                        <td class="px-6 py-4 font-semibold text-gray-900">{{ $term->name }}</td>
                        <td class="px-6 py-4 text-center text-gray-700">
                            {{ $term->days }} {{ trans_choice('dia|dias', $term->days) }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($term->is_default)
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">
                                    <i class="fas fa-star mr-1"></i>{{ __('Padrão') }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="alternarActiva({{ $term->id }})"
                                    class="px-2 py-0.5 text-xs font-bold rounded-full {{ $term->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $term->is_active ? __('Activa') : __('Inactiva') }}
                            </button>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="editar({{ $term->id }})"
                                        class="p-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition" title="{{ __('Editar') }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="eliminar({{ $term->id }})"
                                        wire:confirm="{{ __('Eliminar esta condição? Os clientes que a usam ficam sem condição.') }}"
                                        class="p-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition" title="{{ __('Eliminar') }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            {{ __('Ainda não há condições de pagamento.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-lg font-bold text-white">
                    <i class="fas fa-calendar-check mr-2"></i>{{ $editingId ? __('Editar Condição') : __('Nova Condição') }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form wire:submit.prevent="guardar" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Nome') }} *</label>
                    <input type="text" wire:model="name" placeholder="{{ __('Ex.: Pagamento a 45 dias') }}"
                           class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Dias até ao vencimento') }}</label>
                    <input type="number" min="0" step="1" wire:model="days"
                           class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <p class="text-xs text-gray-400 mt-1">{{ __('0 = pronto pagamento / à vista.') }}</p>
                    @error('days') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_default" class="w-5 h-5 text-indigo-600 rounded">
                    <span class="text-sm font-semibold text-gray-700">{{ __('Usar como condição padrão dos novos clientes') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="w-5 h-5 text-green-600 rounded">
                    <span class="text-sm font-semibold text-gray-700">{{ __('Activa') }}</span>
                </label>
                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="px-5 py-2.5 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100">{{ __('Cancelar') }}</button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold hover:scale-105 transition">
                        <i class="fas fa-save mr-2"></i>{{ __('Guardar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const d = event[0] || event;
                if (typeof toastr !== 'undefined') {
                    toastr[d.type || 'info'](d.message || '');
                }
            });
        });
    </script>
</div>
