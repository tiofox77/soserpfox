<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-ticket-alt mr-3 text-purple-600"></i>
                    Meus Tickets de Suporte
                </h1>
                <p class="text-gray-600 mt-1">Gerencie suas solicitações de ajuda</p>
            </div>
            <button wire:click="openModal" 
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Novo Ticket
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Abertos</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $tickets->where('status', 'open')->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-folder-open text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Em Andamento</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $tickets->where('status', 'in_progress')->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-spinner text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-600">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Resolvidos</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $tickets->where('status', 'resolved')->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-gray-600">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $tickets->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-gray-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Tickets List --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Seus Tickets</h2>
            
            @if($tickets->isEmpty())
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">Nenhum ticket encontrado</p>
                    <p class="text-gray-400 text-sm">Clique em "Novo Ticket" para criar um</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($tickets as $ticket)
                    <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <span class="font-mono text-sm text-gray-600">{{ $ticket->ticket_number }}</span>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-{{ $ticket->priority_color }}-100 text-{{ $ticket->priority_color }}-700">
                                        {{ ucfirst($ticket->priority) }}
                                    </span>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-{{ $ticket->status_color }}-100 text-{{ $ticket->status_color }}-700">
                                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                    </span>
                                </div>
                                <h3 class="font-bold text-gray-900 text-lg mb-2">{{ $ticket->subject }}</h3>
                                <p class="text-gray-600 text-sm line-clamp-2">{{ $ticket->description }}</p>
                                
                                @if($ticket->images && count($ticket->images) > 0)
                                    <div class="flex items-center space-x-2 mt-2">
                                        @foreach(array_slice($ticket->images, 0, 3) as $imagePath)
                                            <img src="{{ Storage::url($imagePath) }}" alt="Anexo" class="w-12 h-12 object-cover rounded border">
                                        @endforeach
                                        @if(count($ticket->images) > 3)
                                            <span class="text-xs text-gray-500">+{{ count($ticket->images) - 3 }} mais</span>
                                        @endif
                                    </div>
                                @endif
                                
                                <div class="flex items-center space-x-4 mt-3 text-xs text-gray-500">
                                    <span><i class="far fa-calendar mr-1"></i> {{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                                    <span><i class="far fa-folder mr-1"></i> {{ ucfirst($ticket->category) }}</span>
                                    @if($ticket->images)
                                        <span><i class="far fa-images mr-1"></i> {{ count($ticket->images) }} imagens</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4">
                                <button class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition text-sm font-semibold">
                                    <i class="fas fa-eye mr-1"></i> Ver Detalhes
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Criar Ticket --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">Novo Ticket de Suporte</h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="createTicket" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Assunto *</label>
                    <input type="text" wire:model="subject" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="Descreva brevemente o problema...">
                    @error('subject') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Prioridade *</label>
                        <select wire:model="priority" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                            <option value="low">Baixa</option>
                            <option value="medium">Média</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Categoria *</label>
                        <select wire:model="category" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                            <option value="technical">Técnico</option>
                            <option value="billing">Faturação</option>
                            <option value="feature">Funcionalidade</option>
                            <option value="bug">Bug/Erro</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Descrição Detalhada *</label>
                    <textarea wire:model="description" rows="6"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                              placeholder="Descreva o problema em detalhes..."></textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-images mr-1"></i> Imagens (Até 5)
                    </label>
                    <input type="file" wire:model="images" multiple accept="image/*"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-xs text-gray-500 mt-1">Máximo 5 imagens, 2MB cada</p>
                    @error('images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    
                    {{-- Preview das imagens --}}
                    @if($images)
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach(array_slice($images, 0, 5) as $image)
                                <div class="relative">
                                    <img src="{{ $image->temporaryUrl() }}" class="w-full h-20 object-cover rounded-lg border-2 border-purple-200">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" wire:click="closeModal"
                            class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i> Enviar Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
