<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        {{-- Logo e Título --}}
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                @if(app_logo())
                    <img src="{{ app_logo() }}" alt="{{ app_name() }}" class="h-20 w-auto object-contain">
                @else
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-users text-white text-3xl"></i>
                    </div>
                @endif
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Portal do Cliente</h2>
            <p class="text-gray-600">Acesse suas faturas e eventos</p>
        </div>

        {{-- Card de Login --}}
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <form wire:submit.prevent="login" class="space-y-6">
                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-blue-500 mr-2"></i>Email
                    </label>
                    <input wire:model="email" 
                           id="email" 
                           type="email" 
                           required
                           autofocus
                           placeholder="seu@email.com"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Senha --}}
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-purple-500 mr-2"></i>Senha
                    </label>
                    <input wire:model="password" 
                           id="password" 
                           type="password" 
                           required
                           placeholder="••••••••"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Lembrar-me --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input wire:model="remember" 
                               id="remember" 
                               type="checkbox"
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Lembrar-me
                        </label>
                    </div>

                    <a href="{{ route('client.forgot-password') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Esqueceu a senha?
                    </a>
                </div>

                {{-- Botão de Login --}}
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove>
                        <i class="fas fa-sign-in-alt mr-2"></i>Entrar no Portal
                    </span>
                    <span wire:loading>
                        <i class="fas fa-spinner fa-spin mr-2"></i>Entrando...
                    </span>
                </button>
            </form>

            {{-- Separador --}}
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-center text-sm text-gray-600">
                    Ainda não tem acesso?
                    <br>
                    <span class="text-gray-500 text-xs">Entre em contato com nossa equipe</span>
                </p>
            </div>
        </div>

        {{-- Voltar --}}
        <div class="text-center mt-6">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Voltar para login de usuários
            </a>
        </div>
    </div>
</div>
