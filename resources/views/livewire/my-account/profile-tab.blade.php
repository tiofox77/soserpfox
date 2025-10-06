<div class="space-y-6">
    
    {{-- Header com Avatar --}}
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl shadow-lg p-8 text-white">
        <div class="flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6">
            {{-- Avatar --}}
            <div class="relative">
                @if($currentAvatar)
                    <img src="{{ Storage::url($currentAvatar) }}" alt="Avatar" class="w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover">
                @else
                    <div class="w-32 h-32 rounded-full border-4 border-white shadow-lg bg-white flex items-center justify-center">
                        <i class="fas fa-user text-6xl text-gray-400"></i>
                    </div>
                @endif
                
                {{-- Badge Online --}}
                <div class="absolute bottom-2 right-2 w-6 h-6 bg-green-500 border-4 border-white rounded-full"></div>
            </div>

            {{-- Info --}}
            <div class="flex-1 text-center md:text-left">
                <h2 class="text-3xl font-bold mb-2">{{ auth()->user()->name }}</h2>
                <p class="text-blue-100 mb-1">
                    <i class="fas fa-envelope mr-2"></i>{{ auth()->user()->email }}
                </p>
                @if(auth()->user()->phone)
                    <p class="text-blue-100 mb-3">
                        <i class="fas fa-phone mr-2"></i>{{ auth()->user()->phone }}
                    </p>
                @endif
                
                <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                    <span class="inline-flex items-center px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm">
                        <i class="fas fa-clock mr-2"></i>
                        Último login: {{ auth()->user()->last_login_at ? auth()->user()->last_login_at->diffForHumans() : 'Primeiro acesso' }}
                    </span>
                    @if(auth()->user()->is_super_admin)
                        <span class="inline-flex items-center px-3 py-1 bg-yellow-500 rounded-full text-sm font-semibold">
                            <i class="fas fa-crown mr-2"></i>Super Admin
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Formulário de Edição --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <i class="fas fa-user-edit text-blue-600 mr-3"></i>
            Editar Informações Pessoais
        </h3>

        <form wire:submit.prevent="updateProfile" class="space-y-6">
            {{-- Upload de Avatar --}}
            <div class="border-b border-gray-200 pb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-camera mr-2 text-gray-500"></i>Foto de Perfil
                </label>
                
                <div class="flex items-center space-x-4">
                    @if($currentAvatar)
                        <img src="{{ Storage::url($currentAvatar) }}" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-2 border-gray-200">
                    @else
                        <div class="w-20 h-20 rounded-full bg-gray-200 flex items-center justify-center">
                            <i class="fas fa-user text-3xl text-gray-400"></i>
                        </div>
                    @endif
                    
                    <div class="flex-1">
                        <input type="file" wire:model="userAvatar" accept="image/*" class="hidden" id="avatar-upload">
                        <label for="avatar-upload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold cursor-pointer hover:bg-blue-700 transition">
                            <i class="fas fa-upload mr-2"></i>Escolher Foto
                        </label>
                        
                        @if($currentAvatar)
                            <button type="button" wire:click="removeAvatar" class="ml-2 px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition">
                                <i class="fas fa-trash mr-2"></i>Remover
                            </button>
                        @endif
                        
                        <p class="text-xs text-gray-500 mt-2">PNG, JPG ou GIF (máx. 2MB)</p>
                        
                        @if($userAvatar)
                            <p class="text-sm text-green-600 mt-2">
                                <i class="fas fa-check-circle mr-1"></i>Nova foto selecionada
                            </p>
                        @endif
                    </div>
                </div>
                @error('userAvatar') <span class="text-red-500 text-sm mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
            </div>

            {{-- Nome e Email --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user mr-1 text-gray-500"></i>Nome Completo *
                    </label>
                    <input type="text" 
                           wire:model="userName" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Seu nome completo"
                           required>
                    @error('userName') <span class="text-red-500 text-sm mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1 text-gray-500"></i>Email *
                    </label>
                    <input type="email" 
                           wire:model="userEmail" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="seu@email.com"
                           required>
                    @error('userEmail') <span class="text-red-500 text-sm mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Telefone --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-phone mr-1 text-gray-500"></i>Telefone
                </label>
                <input type="text" 
                       wire:model="userPhone" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="+244 900 000 000">
                @error('userPhone') <span class="text-red-500 text-sm mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
            </div>

            {{-- Biografia --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-align-left mr-1 text-gray-500"></i>Biografia / Sobre
                </label>
                <textarea wire:model="userBio" 
                          rows="4" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Conte um pouco sobre você..."></textarea>
                <p class="text-xs text-gray-500 mt-1">Máximo 500 caracteres</p>
                @error('userBio') <span class="text-red-500 text-sm mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
            </div>

            {{-- Botões --}}
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="window.location.reload()"
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg font-semibold hover:bg-gray-600 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-purple-700 transition shadow-lg"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updateProfile">
                        <i class="fas fa-save mr-2"></i>Salvar Alterações
                    </span>
                    <span wire:loading wire:target="updateProfile">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- Estatísticas Rápidas --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Conta Criada</p>
                    <p class="text-lg font-bold text-gray-900">{{ auth()->user()->created_at->format('d/m/Y') }}</p>
                    <p class="text-xs text-gray-500">{{ auth()->user()->created_at->diffForHumans() }}</p>
                </div>
                <i class="fas fa-calendar-check text-3xl text-blue-500 opacity-50"></i>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Empresas Gerenciadas</p>
                    <p class="text-lg font-bold text-gray-900">{{ auth()->user()->tenants()->count() }}</p>
                    <p class="text-xs text-gray-500">Total de empresas</p>
                </div>
                <i class="fas fa-building text-3xl text-green-500 opacity-50"></i>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Status da Conta</p>
                    <p class="text-lg font-bold text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>Ativa
                    </p>
                    <p class="text-xs text-gray-500">Conta verificada</p>
                </div>
                <i class="fas fa-shield-check text-3xl text-purple-500 opacity-50"></i>
            </div>
        </div>
    </div>

</div>
