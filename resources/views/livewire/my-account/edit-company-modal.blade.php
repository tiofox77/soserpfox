<!-- Modal Editar Empresa -->
@if($showEditCompanyModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm" wire:click="closeEditCompanyModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" wire:click.stop>
        <!-- Header -->
        <div class="bg-gradient-to-r from-yellow-600 to-orange-600 px-6 py-4 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white">
                    <i class="fas fa-edit mr-2"></i>Editar Empresa
                </h2>
                <button wire:click="closeEditCompanyModal" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Body -->
        <form wire:submit.prevent="updateCompany" class="p-6 space-y-4">
            <!-- Nome da Empresa -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-building text-blue-500 mr-1"></i>Nome da Empresa *
                </label>
                <input type="text" wire:model="editCompanyName" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                       placeholder="Ex: Minha Empresa Lda">
                @error('editCompanyName') 
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
                    <input type="text" wire:model="editCompanyNif" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                           placeholder="Ex: 500012345">
                    @error('editCompanyNif') 
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
                    <select wire:model="editCompanyRegime" 
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
                        <option value="regime_geral">Regime Geral de IVA</option>
                        <option value="regime_simplificado">Regime Simplificado</option>
                        <option value="regime_isencao">Regime de Isenção</option>
                        <option value="regime_nao_sujeicao">Regime de Não Sujeição</option>
                        <option value="regime_misto">Regime Misto</option>
                    </select>
                    @error('editCompanyRegime') 
                        <span class="text-red-500 text-sm mt-1 block">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </span>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>Conforme SAFT-AO 2025
                    </p>
                </div>
            </div>
            
            <!-- Logo da Empresa -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-image text-blue-500 mr-1"></i>Logo da Empresa
                </label>
                
                @if($currentLogo)
                    <div class="mb-3 p-4 bg-gray-50 rounded-xl border-2 border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="{{ asset('storage/' . $currentLogo) }}" 
                                     alt="Logo atual" 
                                     class="h-16 w-16 object-contain rounded-lg border-2 border-gray-300">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Logo Atual</p>
                                    <p class="text-xs text-gray-500">Clique em remover para excluir</p>
                                </div>
                            </div>
                            <button type="button" wire:click="removeLogo" 
                                    class="px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-trash mr-1"></i>Remover
                            </button>
                        </div>
                    </div>
                @endif
                
                <div class="flex items-center justify-center w-full" wire:loading.remove wire:target="editCompanyLogo">
                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                            <p class="mb-2 text-sm text-gray-700">
                                <span class="font-semibold">Clique para fazer upload</span> ou arraste e solte
                            </p>
                            <p class="text-xs text-gray-500">PNG, JPG, GIF, SVG até 2MB</p>
                        </div>
                        <input type="file" wire:model="editCompanyLogo" class="hidden" accept="image/*">
                    </label>
                </div>
                
                <!-- Loading durante upload -->
                <div wire:loading wire:target="editCompanyLogo" class="flex flex-col items-center justify-center w-full h-32 border-2 border-blue-300 border-dashed rounded-xl bg-blue-50">
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-2"></i>
                    <p class="text-sm font-semibold text-blue-700">Fazendo upload...</p>
                </div>
                
                @if($editCompanyLogo)
                    <div class="mt-3 p-4 bg-green-50 border-2 border-green-200 rounded-xl">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <span class="text-sm font-semibold text-green-700">Nova logo selecionada</span>
                            </div>
                            <button type="button" wire:click="$set('editCompanyLogo', null)" 
                                    class="text-green-600 hover:text-green-800 font-bold">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Preview da nova imagem -->
                        <div class="flex items-center space-x-4 bg-white p-3 rounded-lg border border-green-200">
                            <img src="{{ $editCompanyLogo->temporaryUrl() }}" 
                                 alt="Preview da nova logo" 
                                 class="h-20 w-20 object-contain rounded-lg border-2 border-green-300">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-900">{{ $editCompanyLogo->getClientOriginalName() }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Tamanho: {{ number_format($editCompanyLogo->getSize() / 1024, 2) }} KB
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
                
                @error('editCompanyLogo') 
                    <span class="text-red-500 text-sm mt-1 block">
                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                    </span>
                @enderror
            </div>
            
            <!-- Endereço -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>Endereço
                </label>
                <input type="text" wire:model="editCompanyAddress" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                       placeholder="Ex: Rua da Independência, Luanda">
                @error('editCompanyAddress') 
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
                    <input type="text" wire:model="editCompanyPhone" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                           placeholder="+244 923 000 000">
                    @error('editCompanyPhone') 
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
                    <input type="email" wire:model="editCompanyEmail" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                           placeholder="empresa@exemplo.ao">
                    @error('editCompanyEmail') 
                        <span class="text-red-500 text-sm mt-1 block">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </span>
                    @enderror
                </div>
            </div>
            
            <!-- Aviso -->
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-yellow-500 text-xl mr-3 mt-1"></i>
                    <div class="text-sm text-yellow-700">
                        <strong>Atenção:</strong>
                        <p class="mt-1">As alterações serão aplicadas imediatamente e refletirão em todos os documentos futuros.</p>
                    </div>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="flex space-x-3 pt-4">
                <button type="button" wire:click="closeEditCompanyModal"
                        class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit"
                        class="flex-1 px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white rounded-xl font-semibold transition shadow-lg">
                    <i class="fas fa-save mr-2"></i>Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>
@endif
