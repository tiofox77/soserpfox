{{-- Modal de Upload de Anexos --}}
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="closeUploadModal"></div>

        <div class="inline-block w-full max-w-3xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-cloud-upload-alt text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Upload de Anexos</h3>
                        <p class="text-green-100 text-sm">Adicionar fotos e documentos à OS</p>
                    </div>
                </div>
                <button wire:click="closeUploadModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Content --}}
            <div class="p-6 space-y-6">
                {{-- Categoria --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-folder mr-2 text-green-600"></i>Categoria *
                    </label>
                    <select wire:model="uploadCategory" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        <option value="photo_before">📷 Foto Antes</option>
                        <option value="photo_after">📸 Foto Depois</option>
                        <option value="photo_damage">⚠️ Foto de Dano</option>
                        <option value="document">📄 Documento</option>
                        <option value="invoice">🧾 Fatura</option>
                        <option value="other">📎 Outro</option>
                    </select>
                </div>

                {{-- Upload Area --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-upload mr-2 text-green-600"></i>Arquivos *
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-green-500 transition-all">
                        <input type="file" wire:model="uploadFiles" multiple 
                               class="hidden" id="file-upload" accept="image/*,application/pdf,.doc,.docx">
                        <label for="file-upload" class="cursor-pointer">
                            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-cloud-upload-alt text-3xl"></i>
                            </div>
                            <p class="text-gray-700 font-semibold mb-2">Clique para selecionar arquivos</p>
                            <p class="text-sm text-gray-500">ou arraste e solte aqui</p>
                            <p class="text-xs text-gray-400 mt-2">Máximo 10MB por arquivo</p>
                        </label>
                    </div>
                    
                    @error('uploadFiles.*') 
                        <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span> 
                    @enderror
                    
                    {{-- Preview dos arquivos selecionados --}}
                    @if($uploadFiles)
                        <div class="mt-4 space-y-2">
                            @foreach($uploadFiles as $index => $file)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-file"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $file->getClientOriginalName() }}</p>
                                            <p class="text-xs text-gray-500">{{ number_format($file->getSize() / 1024, 2) }} KB</p>
                                        </div>
                                    </div>
                                    <button type="button" wire:click="$set('uploadFiles.{{ $index }}', null)" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Descrição (Opcional) --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-comment mr-2 text-green-600"></i>Descrição
                        <span class="text-xs text-gray-500 normal-case ml-2">(opcional)</span>
                    </label>
                    <textarea wire:model="uploadDescription" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                              placeholder="Adicione uma descrição para os arquivos..."></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <button wire:click="closeUploadModal" 
                        class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition-all">
                    Cancelar
                </button>
                
                <button wire:click="uploadAttachments" 
                        wire:loading.attr="disabled"
                        class="px-6 py-2 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white rounded-xl font-semibold transition-all flex items-center shadow-lg disabled:opacity-50">
                    <span wire:loading.remove wire:target="uploadAttachments">
                        <i class="fas fa-upload mr-2"></i>Fazer Upload
                    </span>
                    <span wire:loading wire:target="uploadAttachments">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
