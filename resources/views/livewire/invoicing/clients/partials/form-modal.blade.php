@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-start sm:items-center justify-center min-h-screen p-2 sm:p-4 text-center">
            <div class="relative w-full bg-white rounded-2xl text-left shadow-2xl transform transition-all my-4 sm:my-8 sm:max-w-5xl max-h-[94vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-4 sm:px-6 py-3 sm:py-4 sticky top-0 z-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-user mr-2 sm:mr-3"></i>{{ $editingClientId ? 'Editar' : 'Novo' }} Cliente
                        </h3>
                        <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <form wire:submit.prevent="save" class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag text-green-500 mr-2"></i>{{ __('Tipo *') }}
                            </label>
                            <select wire:model.live="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                <option value="pessoa_juridica">{{ __('Pessoa Jurídica') }}</option>
                                <option value="pessoa_fisica">{{ __('Pessoa Física') }}</option>
                            </select>
                            @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-signature text-green-500 mr-2"></i>{{ __('Nome *') }}
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-id-card text-blue-500 mr-2"></i>{{ $type === 'pessoa_fisica' ? __('NIF ou B.I. *') : __('NIF *') }}
                            </label>
                            <div class="flex space-x-2">
                                <input wire:model="nif" type="text" placeholder="{{ $type === 'pessoa_fisica' ? __('Ex: 025824504LA054') : __('Ex: 5000000000') }}" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                <button type="button" wire:click="lookupNIF" class="px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl transition flex items-center font-semibold shadow-md" title="{{ __('Buscar dados do NIF') }}">
                                    <i class="fas fa-search mr-2"></i>{{ __('Buscar') }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $type === 'pessoa_fisica'
                                    ? __('Informe o NIF ou o número completo do B.I. angolano.')
                                    : __('Digite o NIF e clique em "Buscar" para preencher automaticamente') }}
                            </p>
                            @error('nif') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image text-cyan-500 mr-2"></i>{{ __('Logo') }}
                            </label>
                            
                            @if($currentLogo)
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <img src="{{ Storage::url($currentLogo) }}" alt="{{ __('Logo atual') }}" class="h-20 w-20 object-cover rounded-lg shadow-md">
                                    <p class="text-xs text-gray-500 mt-2">{{ __('Logo atual') }}</p>
                                </div>
                            @endif
                            
                            <input wire:model="logo" type="file" accept="image/*" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100">
                            <p class="text-xs text-gray-500 mt-1">{{ __('Máximo 2MB - PNG, JPG, GIF') }}</p>
                            @error('logo') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            
                            @if($logo)
                                <div class="mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                    <p class="text-xs text-green-700 flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>{{ __('Nova imagem selecionada') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-purple-500 mr-2"></i>{{ __('Email') }}
                            </label>
                            <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone text-orange-500 mr-2"></i>{{ __('Telefone') }}
                            </label>
                            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-mobile-alt text-pink-500 mr-2"></i>{{ __('Celular') }}
                            </label>
                            <input wire:model="mobile" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                        </div>
                        
                        <div class="md:col-span-2">
                            {{-- O MESMO bloco de morada de toda a aplicação.
                                 Estavam aqui SETE países escritos à mão dentro
                                 da vista e a província vinha de uma segunda
                                 cópia da lista, no modelo. Agora vem tudo de
                                 App\Support\Geografia: 256 países ISO, as 21
                                 províncias da reforma de 2024 e os municípios. --}}
                            <x-morada
                                :pais="$country" :provincia="$province" :municipio="$municipality"
                                :bairro="$neighbourhood" :cidade="$city" :codigo-postal="$postal_code"
                                :morada="$address" estilo="modal" />
                        </div>
                    </div>

                    {{-- Condição de pagamento (catálogo por empresa, gerível) --}}
                    <div class="mt-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-check text-indigo-500 mr-2"></i>{{ __('Condição de Pagamento') }}
                        </label>
                        <select wire:model="payment_term_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                            <option value="">{{ __('— Sem condição —') }}</option>
                            @foreach($paymentTerms as $term)
                                <option value="{{ $term->id }}">{{ $term->name }}@if($term->days > 0) ({{ $term->days }} {{ __('dias') }})@endif</option>
                            @endforeach
                        </select>
                        @can('invoicing.settings.view')
                        <a href="{{ route('invoicing.payment-terms') }}" target="_blank" class="mt-1 inline-block text-xs text-indigo-600 hover:underline">
                            <i class="fas fa-gear mr-1"></i>{{ __('Gerir condições de pagamento') }}
                        </a>
                        @endcan
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                        </button>
                        <x-loading-button 
                            action="save" 
                            icon="{{ $editingClientId ? 'save' : 'plus' }}" 
                            color="green"
                            class="px-6 py-2.5">
                            {{ $editingClientId ? 'Atualizar' : 'Criar' }}
                        </x-loading-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
