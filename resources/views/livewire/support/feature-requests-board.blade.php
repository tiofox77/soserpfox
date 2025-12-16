<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-lightbulb mr-3 text-indigo-600"></i>
                Quadro de Melhorias
            </h1>
            <p class="text-gray-600 mt-1">Vote e sugira melhorias para o sistema</p>
        </div>
        <button wire:click="openModal" 
                class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl hover:shadow-lg transition flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Sugerir Melhoria
        </button>
    </div>

    <div class="mb-6 flex space-x-3">
        <button wire:click="$set('filter', 'popular')" 
                class="px-4 py-2 rounded-lg font-semibold {{ $filter === 'popular' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-fire mr-1"></i> Mais Votadas
        </button>
        <button wire:click="$set('filter', 'recent')" 
                class="px-4 py-2 rounded-lg font-semibold {{ $filter === 'recent' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-clock mr-1"></i> Recentes
        </button>
        <button wire:click="$set('filter', 'my')" 
                class="px-4 py-2 rounded-lg font-semibold {{ $filter === 'my' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-user mr-1"></i> Minhas Sugestões
        </button>
    </div>

    <div class="space-y-4">
        @forelse($requests as $request)
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
            <div class="flex">
                <div class="flex flex-col items-center mr-6">
                    <button wire:click="toggleVote({{ $request->id }})" 
                            class="w-12 h-12 rounded-full {{ $request->hasVoted(auth()->id()) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-indigo-100' }} flex items-center justify-center transition">
                        <i class="fas fa-arrow-up text-xl"></i>
                    </button>
                    <span class="mt-2 font-bold text-lg text-gray-900">{{ $request->votes_count }}</span>
                    <span class="text-xs text-gray-500">votos</span>
                </div>
                
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-2">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-{{ $request->status_color }}-100 text-{{ $request->status_color }}-700">
                            {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                        </span>
                        <span class="text-xs text-gray-500">
                            <i class="far fa-user mr-1"></i> {{ $request->user->name }}
                        </span>
                        <span class="text-xs text-gray-500">
                            <i class="far fa-clock mr-1"></i> {{ $request->created_at->diffForHumans() }}
                        </span>
                    </div>
                    
                    <h3 class="font-bold text-xl text-gray-900 mb-2">{{ $request->title }}</h3>
                    <p class="text-gray-600 mb-3">{{ $request->description }}</p>
                    
                    @if($request->images && count($request->images) > 0)
                        <div class="flex items-center space-x-2 mb-3">
                            @foreach(array_slice($request->images, 0, 4) as $imagePath)
                                <img src="{{ Storage::url($imagePath) }}" alt="Anexo" class="w-16 h-16 object-cover rounded-lg border-2 border-indigo-200">
                            @endforeach
                            @if(count($request->images) > 4)
                                <span class="text-sm text-gray-500 font-semibold">+{{ count($request->images) - 4 }}</span>
                            @endif
                        </div>
                    @endif
                    
                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                        <span><i class="far fa-comment mr-1"></i> {{ $request->comments->count() }} comentários</span>
                        @if($request->images)
                            <span><i class="far fa-images mr-1"></i> {{ count($request->images) }} imagens</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">Nenhuma sugestão ainda</p>
            <p class="text-gray-400 text-sm">Seja o primeiro a sugerir uma melhoria!</p>
        </div>
        @endforelse
    </div>

    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">Sugerir Melhoria</h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="createRequest" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Título da Sugestão *</label>
                    <input type="text" wire:model="title" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500"
                           placeholder="Ex: Adicionar exportação para PDF...">
                    @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Descrição Detalhada *</label>
                    <textarea wire:model="description" rows="6"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500"
                              placeholder="Descreva sua ideia em detalhes..."></textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-images mr-1"></i> Imagens (Até 5)
                    </label>
                    <input type="file" wire:model="images" multiple accept="image/*"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Máximo 5 imagens, 2MB cada</p>
                    @error('images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    
                    {{-- Preview das imagens --}}
                    @if($images)
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach(array_slice($images, 0, 5) as $image)
                                <div class="relative">
                                    <img src="{{ $image->temporaryUrl() }}" class="w-full h-20 object-cover rounded-lg border-2 border-indigo-200">
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
                            class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl hover:shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i> Enviar Sugestão
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
