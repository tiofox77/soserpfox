{{-- Modal Edit Event --}}
@if($showEditModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);">
    
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto">
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-teal-600 px-4 sm:px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-lg sm:text-xl font-bold text-white flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Editar Evento
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
                        <h4 class="font-bold text-red-800 mb-2 text-sm sm:text-base">Corrija os erros:</h4>
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
                    <i class="fas fa-signature text-green-600 mr-2"></i>
                    Nome do Evento *
                </label>
                <input type="text" wire:model="editName" 
                       class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('editName') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="Ex: Conferência Anual 2025">
                @error('editName')
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
                    <input type="datetime-local" wire:model="editStartDate" 
                           step="900"
                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('editStartDate') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-green-500">
                    @error('editStartDate')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-calendar-check text-red-600 mr-2"></i>
                        Data/Hora Fim *
                    </label>
                    <input type="datetime-local" wire:model="editEndDate" 
                           step="900"
                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('editEndDate') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-green-500">
                    @error('editEndDate')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Tipo, Status e Participantes --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-tag text-blue-600 mr-2"></i>
                        Tipo *
                    </label>
                    <select wire:model="editType" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('editType') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="">Selecione</option>
                        @foreach($eventTypes as $eventType)
                            <option value="{{ $eventType->id }}">{{ $eventType->icon }} {{ $eventType->name }}</option>
                        @endforeach
                    </select>
                    @error('editType')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-flag text-purple-600 mr-2"></i>
                        Status *
                    </label>
                    <select wire:model="editStatus" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 @error('editStatus') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="orcamento">📄 Orçamento</option>
                        <option value="confirmado">✅ Confirmado</option>
                        <option value="em_montagem">🔨 Em Montagem</option>
                        <option value="em_andamento">▶️ Em Andamento</option>
                        <option value="concluido">🏆 Concluído</option>
                        <option value="cancelado">❌ Cancelado</option>
                    </select>
                    @error('editStatus')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-users text-orange-600 mr-2"></i>
                        Participantes
                    </label>
                    <input type="number" wire:model="editAttendees" 
                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                           placeholder="100" min="1">
                </div>
            </div>

            {{-- Cliente e Local --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-user text-indigo-600 mr-2"></i>
                        Cliente
                    </label>
                    <select wire:model="editClientId" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="">Sem cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                        Local
                    </label>
                    <select wire:model="editVenueId" 
                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="">Sem local</option>
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
                <textarea wire:model="editDescription" 
                          rows="3"
                          class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                          placeholder="Descreva os detalhes do evento..."></textarea>
            </div>

            {{-- Botões de Ação --}}
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 pt-4 border-t">
                <button wire:click="updateEvent" 
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        class="w-full sm:flex-1 bg-gradient-to-r from-green-600 to-teal-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg font-bold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center text-sm sm:text-base">
                    <span wire:loading.remove wire:target="updateEvent">
                        <i class="fas fa-save mr-2"></i>
                        Salvar Alterações
                    </span>
                    <span wire:loading wire:target="updateEvent">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Salvando...
                    </span>
                </button>
                <button wire:click="closeModal" 
                        wire:loading.attr="disabled"
                        wire:target="updateEvent"
                        class="w-full sm:w-auto px-4 sm:px-6 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition text-sm sm:text-base">
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
