<!-- Modal Criar Empresa -->
@if($showCreateCompanyModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm" wire:click="closeCreateCompanyModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" wire:click.stop>
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white">
                    <i class="fas fa-plus-circle mr-2"></i>Criar Nova Empresa
                </h2>
                <button wire:click="closeCreateCompanyModal" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Body -->
        <form wire:submit.prevent="createCompany" class="p-6 space-y-4">
            <!-- Nome da Empresa -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-building text-blue-500 mr-1"></i>Nome da Empresa *
                </label>
                <input type="text" wire:model="newCompanyName" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                       placeholder="Ex: Minha Nova Empresa Lda">
                @error('newCompanyName') 
                    <span class="text-red-500 text-sm mt-1 block">
                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                    </span>
                @enderror
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <!-- NIF -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-id-card text-blue-500 mr-1"></i>NIF *
                    </label>
                    <input type="text" wire:model="newCompanyNif" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                           placeholder="Ex: 500012345">
                    @error('newCompanyNif') 
                        <span class="text-red-500 text-sm mt-1 block">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </span>
                    @enderror
                </div>
                
                <!-- Regime Fiscal -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file-contract text-blue-500 mr-1"></i>Regime Fiscal *
                    </label>
                    <select wire:model="newCompanyRegime" 
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
                        <option value="regime_geral">Regime Geral de IVA</option>
                        <option value="regime_simplificado">Regime Simplificado</option>
                        <option value="regime_isencao">Regime de Isenção</option>
                        <option value="regime_nao_sujeicao">Regime de Não Sujeição</option>
                        <option value="regime_misto">Regime Misto</option>
                    </select>
                    @error('newCompanyRegime') 
                        <span class="text-red-500 text-sm mt-1 block">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </span>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>Conforme SAFT-AO 2025
                    </p>
                </div>
            </div>
            
            <!-- Endereço -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>Endereço
                </label>
                <input type="text" wire:model="newCompanyAddress" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                       placeholder="Ex: Rua da Independência, Luanda">
                @error('newCompanyAddress') 
                    <span class="text-red-500 text-sm mt-1 block">
                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                    </span>
                @enderror
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <!-- Telefone -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-phone text-blue-500 mr-1"></i>Telefone
                    </label>
                    <input type="text" wire:model="newCompanyPhone" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                           placeholder="+244 923 000 000">
                    @error('newCompanyPhone') 
                        <span class="text-red-500 text-sm mt-1 block">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </span>
                    @enderror
                </div>
                
                <!-- Email -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-blue-500 mr-1"></i>Email
                    </label>
                    <input type="email" wire:model="newCompanyEmail" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                           placeholder="empresa@exemplo.ao">
                    @error('newCompanyEmail') 
                        <span class="text-red-500 text-sm mt-1 block">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </span>
                    @enderror
                </div>
            </div>
            
            <!-- Aviso -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-1"></i>
                    <div class="text-sm text-blue-700">
                        <strong>Importante:</strong>
                        <p class="mt-1">Você será adicionado como <strong>Administrador</strong> desta nova empresa e poderá gerenciá-la completamente.</p>
                        <p class="mt-1">Você está usando <strong>{{ $currentCount }}</strong> de <strong>{{ $maxAllowed >= 999 ? 'ilimitadas' : $maxAllowed }}</strong> empresas permitidas no seu plano.</p>
                    </div>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="flex space-x-3 pt-4">
                <button type="button" wire:click="closeCreateCompanyModal"
                        class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit"
                        class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition shadow-lg">
                    <i class="fas fa-check mr-2"></i>Criar Empresa
                </button>
            </div>
        </form>
    </div>
</div>
@endif
