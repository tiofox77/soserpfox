<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeImportModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-file-excel text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Importar Presenças de Biométrico</h3>
                    <p class="text-green-100 text-sm">ZKTeco ou Hikvision - Formato Excel</p>
                </div>
            </div>
            <button wire:click="closeImportModal" 
                    class="text-white hover:text-green-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <form wire:submit.prevent="processImport">
            <div class="p-6">
                {{-- Instruções --}}
                <div class="mb-6 bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 text-2xl mr-3 mt-0.5"></i>
                        <div>
                            <p class="font-bold text-blue-900 mb-2">Como Importar:</p>
                            <ol class="text-blue-700 text-sm space-y-1 list-decimal list-inside">
                                <li>Exporte o relatório de presenças do seu biométrico (ZKTeco ou Hikvision)</li>
                                <li>O arquivo deve estar em formato <strong>Excel (.xlsx ou .xls)</strong></li>
                                <li>Selecione o arquivo abaixo e clique em "Processar"</li>
                                <li>O sistema validará e importará os registros automaticamente</li>
                            </ol>
                        </div>
                    </div>
                </div>

                {{-- Sistema Biométrico --}}
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-fingerprint mr-1 text-green-600"></i>
                        Selecione o Sistema Biométrico
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="relative">
                            <input type="radio" 
                                   wire:model="biometricSystem" 
                                   value="zkteco" 
                                   class="peer sr-only">
                            <div class="peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:ring-2 peer-checked:ring-green-500 border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition-all hover:border-green-300">
                                <div class="flex items-center justify-center mb-2">
                                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-fingerprint text-white text-3xl"></i>
                                    </div>
                                </div>
                                <p class="text-center font-bold text-gray-900">ZKTeco</p>
                                <p class="text-center text-xs text-gray-500 mt-1">Relógio de Ponto ZK</p>
                            </div>
                        </label>

                        <label class="relative">
                            <input type="radio" 
                                   wire:model="biometricSystem" 
                                   value="hikvision" 
                                   class="peer sr-only">
                            <div class="peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:ring-2 peer-checked:ring-green-500 border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition-all hover:border-green-300">
                                <div class="flex items-center justify-center mb-2">
                                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-camera text-white text-3xl"></i>
                                    </div>
                                </div>
                                <p class="text-center font-bold text-gray-900">Hikvision</p>
                                <p class="text-center text-xs text-gray-500 mt-1">Terminal Facial</p>
                            </div>
                        </label>
                    </div>
                    @error('biometricSystem')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Upload Area --}}
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-upload mr-1 text-green-600"></i>
                        Arquivo Excel de Presenças <span class="text-red-500">*</span>
                    </label>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-green-500 transition-all"
                         x-data="{ uploading: false, progress: 0 }"
                         x-on:livewire-upload-start="uploading = true"
                         x-on:livewire-upload-finish="uploading = false"
                         x-on:livewire-upload-error="uploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                        
                        @if($importFile)
                            {{-- Arquivo Selecionado --}}
                            <div class="text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                                    <i class="fas fa-file-excel text-green-600 text-3xl"></i>
                                </div>
                                <p class="text-sm font-semibold text-gray-900 mb-1">{{ $importFile->getClientOriginalName() }}</p>
                                <p class="text-xs text-gray-500 mb-4">{{ number_format($importFile->getSize() / 1024, 2) }} KB</p>
                                <button type="button" 
                                        wire:click="$set('importFile', null)"
                                        class="text-sm text-red-600 hover:text-red-700 font-semibold">
                                    <i class="fas fa-times mr-1"></i>Remover arquivo
                                </button>
                            </div>
                        @else
                            {{-- Upload Area --}}
                            <div>
                                <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                                    <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-sm font-semibold text-gray-900 mb-1">
                                    Clique para selecionar ou arraste o arquivo
                                </p>
                                <p class="text-xs text-gray-500 mb-4">
                                    Formatos aceitos: .xlsx, .xls (Máximo 5MB)
                                </p>
                                <label class="inline-flex items-center px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold cursor-pointer transition-all shadow-md hover:shadow-lg">
                                    <i class="fas fa-folder-open mr-2"></i>
                                    Selecionar Arquivo
                                    <input type="file" 
                                           wire:model="importFile" 
                                           accept=".xlsx,.xls"
                                           class="hidden">
                                </label>
                            </div>
                        @endif

                        {{-- Progress Bar --}}
                        <div x-show="uploading" class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div class="bg-gradient-to-r from-green-500 to-emerald-600 h-2 rounded-full transition-all duration-300"
                                     :style="`width: ${progress}%`"></div>
                            </div>
                            <p class="text-xs text-gray-600 mt-2">
                                <span x-text="progress"></span>% enviado
                            </p>
                        </div>
                    </div>
                    @error('importFile')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Formatos Suportados --}}
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mr-3 mt-0.5"></i>
                        <div>
                            <p class="font-semibold text-yellow-900 text-sm mb-1">Formatos de Colunas Esperados:</p>
                            <div class="text-yellow-700 text-xs space-y-1">
                                <p><strong>ZKTeco:</strong> Nº Funcionário | Nome | Data | Hora Entrada | Hora Saída</p>
                                <p><strong>Hikvision:</strong> Employee ID | Name | Date | Check In | Check Out</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    A implementação será finalizada em breve
                </div>
                <div class="flex items-center space-x-3">
                    <button type="button" 
                            wire:click="closeImportModal"
                            class="px-6 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit"
                            :disabled="!$wire.importFile"
                            class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-cog mr-2"></i>Processar Importação
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
