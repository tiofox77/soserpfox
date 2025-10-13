<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-circle text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Meu Perfil</h2>
                    <p class="text-purple-100 text-sm">Gerencie suas informações pessoais</p>
                </div>
            </div>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mb-6 bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-4 rounded-2xl shadow-lg flex items-center">
            <i class="fas fa-check-circle text-2xl mr-3"></i>
            <span class="font-semibold">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 stagger-animation">
        {{-- Informações do Perfil --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-user text-white text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Informações Pessoais</h2>
            </div>
            
            <form wire:submit.prevent="updateProfile">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                        <i class="fas fa-user mr-1"></i>Nome Completo
                    </label>
                    <input wire:model="name" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    @error('name') <span class="text-red-500 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                        <i class="fas fa-envelope mr-1"></i>Email
                    </label>
                    <input wire:model="email" type="email" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    @error('email') <span class="text-red-500 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                        <i class="fas fa-phone mr-1"></i>Telefone
                    </label>
                    <input wire:model="phone" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    @error('phone') <span class="text-red-500 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-3 px-4 rounded-xl font-semibold hover:shadow-lg transition-all transform hover:scale-[1.02]">
                    <i class="fas fa-save mr-2"></i>Atualizar Perfil
                </button>
            </form>
        </div>

        {{-- Alteração de Senha --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-lock text-white text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Alterar Senha</h2>
            </div>
            
            <form wire:submit.prevent="updatePassword">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                        <i class="fas fa-key mr-1"></i>Senha Atual
                    </label>
                    <input wire:model="current_password" type="password" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    @error('current_password') <span class="text-red-500 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                        <i class="fas fa-lock mr-1"></i>Nova Senha
                    </label>
                    <input wire:model="new_password" type="password" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    @error('new_password') <span class="text-red-500 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                        <i class="fas fa-check-double mr-1"></i>Confirmar Nova Senha
                    </label>
                    <input wire:model="new_password_confirmation" type="password" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    @error('new_password_confirmation') <span class="text-red-500 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-cyan-600 text-white py-3 px-4 rounded-xl font-semibold hover:shadow-lg transition-all transform hover:scale-[1.02]">
                    <i class="fas fa-shield-alt mr-2"></i>Alterar Senha
                </button>
            </form>
        </div>
    </div>
</div>
