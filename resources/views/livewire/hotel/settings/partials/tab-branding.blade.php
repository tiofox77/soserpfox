<div class="space-y-6">
    {{-- Cores --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-palette mr-1 text-blue-500"></i> Cor Principal
            </label>
            <div class="flex items-center gap-3">
                <input wire:model="primary_color" type="color" 
                       class="w-16 h-12 rounded-xl border-2 border-gray-300 cursor-pointer">
                <input wire:model="primary_color" type="text" 
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="#3b82f6">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-fill-drip mr-1 text-indigo-500"></i> Cor Secundária
            </label>
            <div class="flex items-center gap-3">
                <input wire:model="secondary_color" type="color" 
                       class="w-16 h-12 rounded-xl border-2 border-gray-300 cursor-pointer">
                <input wire:model="secondary_color" type="text" 
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="#6366f1">
            </div>
        </div>
    </div>

    {{-- Preview das cores --}}
    <div class="p-6 rounded-2xl" style="background: linear-gradient(135deg, {{ $primary_color }} 0%, {{ $secondary_color }} 100%);">
        <p class="text-white text-center font-bold text-lg">Preview do Gradiente</p>
    </div>

    {{-- Logo --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-image mr-1 text-blue-500"></i> Logo do Hotel
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
                @if($currentLogo)
                    <img src="{{ $currentLogo }}" alt="Logo" class="max-h-24 mx-auto mb-3 rounded-lg">
                @endif
                <input wire:model="newLogo" type="file" accept="image/*" class="hidden" id="logo-upload">
                <label for="logo-upload" class="cursor-pointer">
                    <div class="text-gray-500">
                        <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                        <p class="text-sm">Clique para carregar ou arraste</p>
                        <p class="text-xs text-gray-400">PNG, JPG até 2MB</p>
                    </div>
                </label>
                @if($newLogo)
                    <p class="text-green-600 text-sm mt-2"><i class="fas fa-check mr-1"></i>Novo logo selecionado</p>
                @endif
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-panorama mr-1 text-blue-500"></i> Imagem de Capa
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
                @if($currentCoverImage)
                    <img src="{{ $currentCoverImage }}" alt="Capa" class="max-h-24 mx-auto mb-3 rounded-lg object-cover">
                @endif
                <input wire:model="newCoverImage" type="file" accept="image/*" class="hidden" id="cover-upload">
                <label for="cover-upload" class="cursor-pointer">
                    <div class="text-gray-500">
                        <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                        <p class="text-sm">Clique para carregar ou arraste</p>
                        <p class="text-xs text-gray-400">PNG, JPG até 5MB</p>
                    </div>
                </label>
                @if($newCoverImage)
                    <p class="text-green-600 text-sm mt-2"><i class="fas fa-check mr-1"></i>Nova capa selecionada</p>
                @endif
            </div>
        </div>
    </div>

    {{-- SEO --}}
    <div class="border-t pt-6">
        <h4 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-search text-blue-500"></i> SEO - Otimização para Motores de Busca
        </h4>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Título da Página</label>
                <input wire:model="meta_title" type="text" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="Hotel Paradise - O Melhor de Luanda">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                <textarea wire:model="meta_description" rows="2"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                          placeholder="Desfrute do conforto e hospitalidade no coração de Luanda..."></textarea>
            </div>
        </div>
    </div>
</div>
