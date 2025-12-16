{{-- Modal de Formulário --}}
@if($showModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 m-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-bed text-indigo-600"></i>
                {{ $editingId ? 'Editar Tipo de Quarto' : 'Novo Tipo de Quarto' }}
            </h3>
            <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form wire:submit="save" class="space-y-5">
            {{-- Nome e Código --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-tag mr-1 text-indigo-500"></i>Nome *
                    </label>
                    <input wire:model.live.debounce.300ms="name" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" 
                           placeholder="Ex: Suite Deluxe">
                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-barcode mr-1 text-purple-500"></i>Código <span class="text-xs text-gray-400">(auto)</span>
                    </label>
                    <input wire:model="code" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm bg-gray-50" 
                           placeholder="Gerado automaticamente">
                </div>
            </div>

            {{-- Descrição --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-align-left mr-1 text-blue-500"></i>Descrição
                </label>
                <textarea wire:model="description" rows="2" 
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" 
                          placeholder="Descrição do tipo de quarto..."></textarea>
            </div>

            {{-- Preços --}}
            <div class="bg-green-50 rounded-xl p-4">
                <h4 class="font-bold text-green-700 mb-3 flex items-center">
                    <i class="fas fa-coins mr-2"></i>Preços
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Preço Base/Noite (Kz) *</label>
                        <input wire:model="base_price" type="number" step="0.01" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 text-sm">
                        @error('base_price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Preço Fim de Semana (Kz)</label>
                        <input wire:model="weekend_price" type="number" step="0.01" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 text-sm">
                    </div>
                </div>
            </div>

            {{-- Capacidade --}}
            <div class="bg-blue-50 rounded-xl p-4">
                <h4 class="font-bold text-blue-700 mb-3 flex items-center">
                    <i class="fas fa-users mr-2"></i>Capacidade
                </h4>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Pessoas *</label>
                        <input wire:model="capacity" type="number" min="1" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Camas Extra</label>
                        <input wire:model="extra_bed_capacity" type="number" min="0" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Preço Cama Extra</label>
                        <input wire:model="extra_bed_price" type="number" step="0.01" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>
            </div>

            {{-- Comodidades --}}
            <div class="bg-purple-50 rounded-xl p-4">
                <h4 class="font-bold text-purple-700 mb-3 flex items-center">
                    <i class="fas fa-concierge-bell mr-2"></i>Comodidades
                </h4>
                @php
                    $amenityIcons = [
                        'wifi' => 'fas fa-wifi text-blue-500',
                        'ac' => 'fas fa-snowflake text-cyan-500',
                        'tv' => 'fas fa-tv text-gray-600',
                        'minibar' => 'fas fa-wine-bottle text-purple-500',
                        'safe' => 'fas fa-lock text-yellow-600',
                        'balcony' => 'fas fa-door-open text-green-500',
                        'sea_view' => 'fas fa-water text-blue-400',
                        'bathtub' => 'fas fa-bath text-indigo-500',
                        'shower' => 'fas fa-shower text-blue-500',
                        'hair_dryer' => 'fas fa-wind text-pink-500',
                        'iron' => 'fas fa-tshirt text-gray-500',
                        'desk' => 'fas fa-desktop text-slate-600',
                        'phone' => 'fas fa-phone text-green-600',
                        'room_service' => 'fas fa-concierge-bell text-amber-500',
                        'breakfast' => 'fas fa-coffee text-orange-500',
                    ];
                @endphp
                <div class="grid grid-cols-3 gap-3">
                    @foreach($availableAmenities as $key => $label)
                        <label class="flex items-center gap-2 text-sm p-2.5 bg-white rounded-lg hover:bg-purple-100 transition cursor-pointer border border-transparent hover:border-purple-200">
                            <input wire:model="amenities" type="checkbox" value="{{ $key }}" 
                                   class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <i class="{{ $amenityIcons[$key] ?? 'fas fa-check text-purple-500' }}"></i>
                            <span class="text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Imagens --}}
            <div class="bg-amber-50 rounded-xl p-4">
                <h4 class="font-bold text-amber-700 mb-3 flex items-center">
                    <i class="fas fa-images mr-2"></i>Imagens
                </h4>
                
                {{-- Imagem de Destaque --}}
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-star mr-1 text-yellow-500"></i>Imagem de Destaque
                    </label>
                    
                    <div class="flex items-start gap-4">
                        {{-- Preview --}}
                        <div class="w-32 h-24 bg-gray-100 rounded-xl overflow-hidden flex items-center justify-center border-2 border-dashed border-gray-300">
                            @if($featured_image)
                                <img src="{{ $featured_image->temporaryUrl() }}" class="w-full h-full object-cover">
                            @elseif($existing_featured_image)
                                <img src="{{ asset('storage/' . $existing_featured_image) }}" class="w-full h-full object-cover">
                            @else
                                <i class="fas fa-image text-3xl text-gray-300"></i>
                            @endif
                        </div>
                        
                        <div class="flex-1">
                            <input type="file" wire:model="featured_image" accept="image/*" 
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200">
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG ou GIF. Máx 2MB</p>
                            
                            @if($featured_image || $existing_featured_image)
                                <button type="button" wire:click="removeFeaturedImage" 
                                        class="mt-2 text-xs text-red-600 hover:text-red-700">
                                    <i class="fas fa-trash mr-1"></i>Remover imagem
                                </button>
                            @endif
                        </div>
                    </div>
                    
                    <div wire:loading wire:target="featured_image" class="text-sm text-amber-600 mt-2">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Carregando...
                    </div>
                    @error('featured_image') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Galeria --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-th mr-1 text-amber-500"></i>Galeria de Imagens
                    </label>
                    
                    {{-- Imagens existentes --}}
                    @if($existing_gallery && count($existing_gallery) > 0)
                        <div class="grid grid-cols-4 gap-2 mb-3">
                            @foreach($existing_gallery as $index => $img)
                                <div class="relative group">
                                    <img src="{{ asset('storage/' . $img) }}" class="w-full h-20 object-cover rounded-lg">
                                    <button type="button" wire:click="removeGalleryImage({{ $index }})" 
                                            class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Novas imagens --}}
                    @if($gallery && count($gallery) > 0)
                        <div class="grid grid-cols-4 gap-2 mb-3">
                            @foreach($gallery as $index => $img)
                                <div class="relative group">
                                    <img src="{{ $img->temporaryUrl() }}" class="w-full h-20 object-cover rounded-lg border-2 border-green-400">
                                    <button type="button" wire:click="removeNewGalleryImage({{ $index }})" 
                                            class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    
                    <input type="file" wire:model="gallery" accept="image/*" multiple
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200">
                    <p class="text-xs text-gray-500 mt-1">Pode selecionar múltiplas imagens. Máx 2MB cada</p>
                    
                    <div wire:loading wire:target="gallery" class="text-sm text-amber-600 mt-2">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Carregando...
                    </div>
                    @error('gallery.*') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Ativo --}}
            <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                <input wire:model="is_active" type="checkbox" 
                       class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label class="text-sm font-medium text-gray-700">Tipo de quarto ativo e disponível para reservas</label>
            </div>

            {{-- Botões --}}
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" wire:click="$set('showModal', false)" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                    Cancelar
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar' }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif
