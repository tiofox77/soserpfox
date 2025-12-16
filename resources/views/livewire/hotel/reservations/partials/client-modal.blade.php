{{-- Modal de Novo Cliente --}}
@if($showClientModal)
<div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeClientModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 m-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-user-plus text-blue-600"></i>
                Novo Cliente
            </h3>
            <button type="button" wire:click="closeClientModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form wire:submit="saveClient" class="space-y-4">
            {{-- Nome --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-user mr-1 text-blue-500"></i>Nome Completo *
                </label>
                <input wire:model="client_name" type="text" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                       placeholder="Nome do cliente...">
                @error('client_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Telefone e Email --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-phone mr-1 text-green-500"></i>Telefone
                    </label>
                    <input wire:model="client_phone" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                           placeholder="+244 9XX XXX XXX">
                    @error('client_phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-envelope mr-1 text-purple-500"></i>Email
                    </label>
                    <input wire:model="client_email" type="email" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                           placeholder="email@exemplo.com">
                    @error('client_email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- NIF --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-id-card mr-1 text-orange-500"></i>NIF (Contribuinte)
                </label>
                <input wire:model="client_nif" type="text" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                       placeholder="Número de Identificação Fiscal">
                @error('client_nif') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Endereço --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-map-marker-alt mr-1 text-red-500"></i>Endereço
                </label>
                <input wire:model="client_address" type="text" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                       placeholder="Rua, número, bairro...">
            </div>

            {{-- Cidade e Província --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-city mr-1 text-cyan-500"></i>Cidade
                    </label>
                    <input wire:model="client_city" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm"
                           placeholder="Cidade">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-map mr-1 text-indigo-500"></i>Província
                    </label>
                    <select wire:model="client_province" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                        @foreach(\App\Models\Client::PROVINCIAS_ANGOLA as $provincia)
                            <option value="{{ $provincia }}">{{ $provincia }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Botões --}}
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" wire:click="closeClientModal" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                    Cancelar
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveClient"
                        class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveClient">
                        <i class="fas fa-save mr-2"></i>Salvar Cliente
                    </span>
                    <span wire:loading wire:target="saveClient">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif
