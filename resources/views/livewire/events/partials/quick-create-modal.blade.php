{{-- Modal Quick Create Event --}}
@if($showQuickCreateModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4 animate-fade-in-up"
     style="backdrop-filter: blur(4px);"
     x-data="{ show: @entangle('showQuickCreateModal') }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto animate-scale-in"
         @click.away="$wire.closeModal()"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 sm:px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-lg sm:text-xl font-bold text-white flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>
                <span class="hidden sm:inline">Criar Evento Rápido</span>
                <span class="sm:hidden">Novo Evento</span>
            </h3>
            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        {{-- Body --}}
        <div class="p-4 sm:p-6 space-y-4 max-h-[calc(100vh-12rem)] overflow-y-auto">
            
            {{-- Alertas de Validação --}}
            @if($errors->any())
            <div class="bg-red-50 border-2 border-red-200 rounded-lg p-3 sm:p-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-2 sm:mr-3 mt-1 flex-shrink-0"></i>
                    <div class="flex-1">
                        <h4 class="font-bold text-red-800 mb-2 text-sm sm:text-base">Preencha os campos obrigatórios:</h4>
                        <ul class="space-y-1">
                            @foreach($errors->all() as $error)
                            <li class="text-xs sm:text-sm text-red-700">• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif
            
            {{-- Nome do Evento --}}
            <div>
                <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-signature text-purple-600 mr-2"></i>
                    Nome do Evento *
                </label>
                <input type="text" wire:model="quickName" 
                       class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('quickName') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="Ex: Conferência Anual 2025">
                @error('quickName')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Data/Hora Início e Fim --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-calendar-day text-green-600 mr-2"></i>
                        Data/Hora Início *
                    </label>
                    <input type="datetime-local" wire:model="quickStartDate" 
                           step="900"
                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('quickStartDate') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-purple-500">
                    @error('quickStartDate')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-calendar-check text-red-600 mr-2"></i>
                        Data/Hora Fim *
                    </label>
                    <input type="datetime-local" wire:model="quickEndDate" 
                           step="900"
                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('quickEndDate') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-purple-500">
                    @error('quickEndDate')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Tipo e Participantes --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center justify-between">
                        <span class="flex items-center">
                            <i class="fas fa-tag text-blue-600 mr-2"></i>
                            Tipo de Evento *
                        </span>
                        <button type="button"
                                wire:click="openQuickEventTypeModal"
                                class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-md hover:bg-blue-200 transition">
                            <i class="fas fa-plus mr-1"></i>Novo
                        </button>
                    </label>
                    <select wire:model="quickType" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('quickType') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Selecione o tipo</option>
                        @foreach($eventTypes as $eventType)
                            <option value="{{ $eventType->id }}">{{ $eventType->icon }} {{ $eventType->name }}</option>
                        @endforeach
                    </select>
                    @error('quickType')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-users text-orange-600 mr-2"></i>
                        Participantes
                    </label>
                    <input type="number" wire:model="quickAttendees" 
                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="Ex: 100" min="1">
                </div>
            </div>

            {{-- Cliente e Local --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                {{-- Cliente --}}
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center justify-between">
                        <span class="flex items-center">
                            <i class="fas fa-user text-indigo-600 mr-2"></i>
                            Cliente
                        </span>
                        <button type="button" 
                                wire:click="openQuickClientModal"
                                class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-md hover:bg-indigo-200 transition">
                            <i class="fas fa-plus mr-1"></i>Novo
                        </button>
                    </label>
                    <select wire:model="quickClientId" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Selecione um cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Local do Evento --}}
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center justify-between">
                        <span class="flex items-center">
                            <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                            Local do Evento
                        </span>
                        <button type="button"
                                wire:click="openQuickVenueModal"
                                class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-md hover:bg-red-200 transition">
                            <i class="fas fa-plus mr-1"></i>Novo
                        </button>
                    </label>
                    <select wire:model="quickVenueId" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Selecione um local</option>
                        @foreach($venues as $venue)
                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Descrição --}}
            <div>
                <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-align-left text-gray-600 mr-2"></i>
                    Descrição
                </label>
                <textarea wire:model="quickDescription" 
                          rows="3"
                          class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                          placeholder="Descreva os detalhes do evento..."></textarea>
            </div>

            {{-- Botões de Ação --}}
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 pt-4 border-t">
                <button wire:click="saveQuickEvent" 
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        class="w-full sm:flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg font-bold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center text-sm sm:text-base">
                    <span wire:loading.remove wire:target="saveQuickEvent">
                        <i class="fas fa-save mr-2"></i>
                        Criar Evento
                    </span>
                    <span wire:loading wire:target="saveQuickEvent">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Criando...
                    </span>
                </button>
                <button wire:click="closeModal" 
                        wire:loading.attr="disabled"
                        wire:target="saveQuickEvent"
                        class="w-full sm:w-auto px-4 sm:px-6 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition text-sm sm:text-base">
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    
    {{-- Mini Modal: Criar Cliente Rápido --}}
    @if($showQuickClientModal ?? false)
    <div class="fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-5 py-3 flex items-center justify-between rounded-t-xl">
                <h4 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-user-plus mr-2"></i>
                    Novo Cliente
                </h4>
                <button wire:click="closeQuickClientModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Nome Completo / Razão Social *
                    </label>
                    <input type="text" wire:model="newClientName" 
                           class="w-full px-3 py-2 border-2 @error('newClientName') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="Ex: João Silva ou Empresa Lda">
                    @error('newClientName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">NIF *</label>
                        <input type="text" wire:model="newClientNif" 
                               class="w-full px-3 py-2 border-2 @error('newClientNif') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="5000000000">
                        @error('newClientNif') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">País *</label>
                        <select wire:model="newClientCountry" 
                                class="w-full px-3 py-2 border-2 @error('newClientCountry') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="Angola">🇦🇴 Angola</option>
                            <option value="Portugal">🇵🇹 Portugal</option>
                            <option value="Brasil">🇧🇷 Brasil</option>
                            <option value="Outro">🌍 Outro</option>
                        </select>
                        @error('newClientCountry') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" wire:model="newClientEmail" 
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="joao@empresa.com">
                    @error('newClientEmail') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Telefone</label>
                    <input type="text" wire:model="newClientPhone" 
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="+244 939 779 902">
                </div>
                <div class="flex gap-2 pt-3">
                    <button wire:click="saveQuickClient" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">
                        <span wire:loading.remove wire:target="saveQuickClient">
                            <i class="fas fa-save mr-1"></i>Salvar
                        </span>
                        <span wire:loading wire:target="saveQuickClient">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Salvando...
                        </span>
                    </button>
                    <button wire:click="closeQuickClientModal" 
                            class="px-4 py-2 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    {{-- Mini Modal: Criar Local Rápido --}}
    @if($showQuickVenueModal ?? false)
    <div class="fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="bg-gradient-to-r from-red-600 to-red-700 px-5 py-3 flex items-center justify-between rounded-t-xl">
                <h4 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Novo Local
                </h4>
                <button wire:click="closeQuickVenueModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nome do Local *</label>
                    <input type="text" wire:model="newVenueName" 
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                           placeholder="Ex: Centro de Convenções">
                    @error('newVenueName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Endereço</label>
                    <input type="text" wire:model="newVenueAddress" 
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                           placeholder="Rua, Cidade">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Capacidade</label>
                    <input type="number" wire:model="newVenueCapacity" 
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                           placeholder="Ex: 500" min="1">
                </div>
                <div class="flex gap-2 pt-3">
                    <button wire:click="saveQuickVenue" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700 transition">
                        <span wire:loading.remove wire:target="saveQuickVenue">
                            <i class="fas fa-save mr-1"></i>Salvar
                        </span>
                        <span wire:loading wire:target="saveQuickVenue">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Salvando...
                        </span>
                    </button>
                    <button wire:click="closeQuickVenueModal" 
                            class="px-4 py-2 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    {{-- Mini Modal: Criar Tipo de Evento Rápido --}}
    @if($showQuickEventTypeModal ?? false)
    <div class="fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-3 flex items-center justify-between rounded-t-xl">
                <h4 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-tag mr-2"></i>
                    Novo Tipo de Evento
                </h4>
                <button wire:click="closeQuickEventTypeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nome do Tipo *</label>
                    <input type="text" wire:model="newEventTypeName" 
                           class="w-full px-3 py-2 border-2 @error('newEventTypeName') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: Festa de Aniversário">
                    @error('newEventTypeName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Ícone *</label>
                        <select wire:model="newEventTypeIcon" 
                                class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="🏢">🏢 Corporativo</option>
                            <option value="💍">💍 Casamento</option>
                            <option value="🎤">🎤 Conferência</option>
                            <option value="🎸">🎸 Show</option>
                            <option value="📹">📹 Streaming</option>
                            <option value="🎉">🎉 Festa</option>
                            <option value="🎓">🎓 Formatura</option>
                            <option value="🎂">🎂 Aniversário</option>
                            <option value="🎭">🎭 Teatro</option>
                            <option value="🏆">🏆 Premiação</option>
                            <option value="📚">📚 Workshop</option>
                            <option value="🎨">🎨 Cultural</option>
                            <option value="⚽">⚽ Esportivo</option>
                            <option value="🎪">🎪 Festival</option>
                            <option value="📌">📌 Outros</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Cor *</label>
                        <input type="color" wire:model="newEventTypeColor" 
                               class="w-full h-[42px] border-2 border-gray-300 rounded-lg cursor-pointer">
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl" 
                         style="background-color: {{ $newEventTypeColor }}20; border: 2px solid {{ $newEventTypeColor }}">
                        {{ $newEventTypeIcon }}
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 font-semibold">Preview:</p>
                        <p class="font-bold" style="color: {{ $newEventTypeColor }}">
                            {{ $newEventTypeIcon }} {{ $newEventTypeName ?: 'Nome do tipo' }}
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-2 pt-3">
                    <button wire:click="saveQuickEventType" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition">
                        <span wire:loading.remove wire:target="saveQuickEventType">
                            <i class="fas fa-save mr-1"></i>Salvar
                        </span>
                        <span wire:loading wire:target="saveQuickEventType">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Salvando...
                        </span>
                    </button>
                    <button wire:click="closeQuickEventTypeModal" 
                            class="px-4 py-2 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif
