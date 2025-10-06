<div class="space-y-6">
    
    {{-- Informações de Segurança --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                <i class="fas fa-shield-alt text-2xl text-red-600"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Segurança da Conta</h2>
                <p class="text-sm text-gray-600">Gerencie sua senha e configurações de segurança</p>
            </div>
        </div>

        {{-- Status de Segurança --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-700 font-semibold mb-1">Último Login</p>
                        <p class="text-lg font-bold text-green-900">
                            @if(auth()->user()->last_login_at)
                                {{ auth()->user()->last_login_at->diffForHumans() }}
                            @else
                                Primeira vez
                            @endif
                        </p>
                        @if(auth()->user()->last_login_at)
                            <p class="text-xs text-green-600 mt-1">
                                {{ auth()->user()->last_login_at->format('d/m/Y às H:i') }}
                            </p>
                        @endif
                    </div>
                    <i class="fas fa-clock text-3xl text-green-600 opacity-50"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-700 font-semibold mb-1">Senha Alterada</p>
                        <p class="text-lg font-bold text-blue-900">
                            @if(auth()->user()->last_password_changed)
                                {{ auth()->user()->last_password_changed->diffForHumans() }}
                            @else
                                Nunca alterada
                            @endif
                        </p>
                        @if(auth()->user()->last_password_changed)
                            <p class="text-xs text-blue-600 mt-1">
                                {{ auth()->user()->last_password_changed->format('d/m/Y às H:i') }}
                            </p>
                        @endif
                    </div>
                    <i class="fas fa-key text-3xl text-blue-600 opacity-50"></i>
                </div>
            </div>
        </div>

        {{-- Alterar Senha --}}
        <div class="border-t border-gray-200 pt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Alterar Senha</h3>
                    <p class="text-sm text-gray-600">Mantenha sua conta segura com uma senha forte</p>
                </div>
                <button wire:click="toggleChangePasswordForm" 
                        class="px-4 py-2 {{ $showChangePasswordForm ? 'bg-gray-500 hover:bg-gray-600' : 'bg-blue-600 hover:bg-blue-700' }} text-white rounded-lg font-semibold transition">
                    <i class="fas fa-{{ $showChangePasswordForm ? 'times' : 'key' }} mr-2"></i>
                    {{ $showChangePasswordForm ? 'Cancelar' : 'Alterar Senha' }}
                </button>
            </div>

            @if($showChangePasswordForm)
                <form wire:submit.prevent="changePassword" class="space-y-4">
                    {{-- Senha Atual --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock mr-1 text-gray-500"></i>Senha Atual *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   wire:model="currentPassword" 
                                   class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Digite sua senha atual"
                                   required>
                            <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        @error('currentPassword') 
                            <span class="text-red-500 text-sm mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span> 
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Nova Senha --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-key mr-1 text-gray-500"></i>Nova Senha *
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       wire:model="newPassword" 
                                       class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Mínimo 8 caracteres"
                                       required>
                                <i class="fas fa-key absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            </div>
                            @error('newPassword') 
                                <span class="text-red-500 text-sm mt-1 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </span> 
                            @enderror
                        </div>

                        {{-- Confirmar Senha --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-check-circle mr-1 text-gray-500"></i>Confirmar Nova Senha *
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       wire:model="confirmPassword" 
                                       class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Digite novamente"
                                       required>
                                <i class="fas fa-check-circle absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            </div>
                            @error('confirmPassword') 
                                <span class="text-red-500 text-sm mt-1 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </span> 
                            @enderror
                        </div>
                    </div>

                    {{-- Dicas de Senha Forte --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>Dicas para uma senha forte:
                        </h4>
                        <ul class="text-xs text-blue-800 space-y-1">
                            <li>✓ Mínimo de 8 caracteres</li>
                            <li>✓ Combine letras maiúsculas e minúsculas</li>
                            <li>✓ Inclua números e símbolos</li>
                            <li>✓ Evite informações pessoais óbvias</li>
                            <li>✓ Não use a mesma senha em vários serviços</li>
                        </ul>
                    </div>

                    {{-- Botões --}}
                    <div class="flex items-center justify-end space-x-3 pt-4">
                        <button type="button" 
                                wire:click="toggleChangePasswordForm"
                                class="px-6 py-3 bg-gray-500 text-white rounded-lg font-semibold hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" 
                                class="px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="changePassword">
                                <i class="fas fa-save mr-2"></i>Salvar Nova Senha
                            </span>
                            <span wire:loading wire:target="changePassword">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                            </span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Avisos de Segurança --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>Recomendações de Segurança
        </h3>
        
        <div class="space-y-3">
            <div class="flex items-start p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <i class="fas fa-shield-alt text-yellow-600 text-xl mr-3 mt-0.5"></i>
                <div>
                    <h4 class="font-semibold text-yellow-900 mb-1">Altere sua senha regularmente</h4>
                    <p class="text-sm text-yellow-800">Recomendamos alterar sua senha a cada 3-6 meses para manter sua conta segura.</p>
                </div>
            </div>

            <div class="flex items-start p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <i class="fas fa-user-shield text-blue-600 text-xl mr-3 mt-0.5"></i>
                <div>
                    <h4 class="font-semibold text-blue-900 mb-1">Não compartilhe sua senha</h4>
                    <p class="text-sm text-blue-800">Nunca compartilhe suas credenciais com terceiros, incluindo suporte técnico.</p>
                </div>
            </div>

            <div class="flex items-start p-4 bg-purple-50 border border-purple-200 rounded-lg">
                <i class="fas fa-laptop text-purple-600 text-xl mr-3 mt-0.5"></i>
                <div>
                    <h4 class="font-semibold text-purple-900 mb-1">Use dispositivos confiáveis</h4>
                    <p class="text-sm text-purple-800">Acesse sua conta apenas de computadores e redes que você confia.</p>
                </div>
            </div>

            <div class="flex items-start p-4 bg-red-50 border border-red-200 rounded-lg">
                <i class="fas fa-sign-out-alt text-red-600 text-xl mr-3 mt-0.5"></i>
                <div>
                    <h4 class="font-semibold text-red-900 mb-1">Faça logout ao sair</h4>
                    <p class="text-sm text-red-800">Sempre termine sua sessão ao usar computadores compartilhados ou públicos.</p>
                </div>
            </div>
        </div>
    </div>

</div>
