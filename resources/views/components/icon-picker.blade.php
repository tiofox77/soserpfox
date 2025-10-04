@props(['model' => 'icon', 'selected' => 'fa-folder'])

<div x-data="{ 
    open: false,
    selected: @entangle($model),
    search: '',
    icons: [
        // Categorias e Pastas
        'fa-folder', 'fa-folder-open', 'fa-folder-tree', 'fa-folders', 'fa-box', 'fa-boxes', 'fa-archive', 
        'fa-layer-group', 'fa-sitemap', 'fa-diagram-project', 'fa-cube', 'fa-cubes',
        // Produtos
        'fa-shopping-cart', 'fa-shopping-bag', 'fa-cart-shopping', 'fa-basket-shopping', 'fa-bag-shopping',
        'fa-store', 'fa-shop', 'fa-warehouse', 'fa-truck', 'fa-box-open', 'fa-dolly', 'fa-pallet',
        // Eletrônicos
        'fa-laptop', 'fa-mobile', 'fa-tablet', 'fa-desktop', 'fa-keyboard', 'fa-mouse', 'fa-headphones',
        'fa-tv', 'fa-camera', 'fa-video', 'fa-gamepad', 'fa-microchip', 'fa-hard-drive', 'fa-memory',
        // Roupas e Moda
        'fa-shirt', 'fa-tshirt', 'fa-vest', 'fa-hat-cowboy', 'fa-user-tie', 'fa-glasses', 'fa-shoe-prints',
        'fa-socks', 'fa-ring', 'fa-gem', 'fa-crown', 'fa-mask', 'fa-hat-wizard',
        // Alimentos e Bebidas
        'fa-utensils', 'fa-pizza-slice', 'fa-hamburger', 'fa-hotdog', 'fa-ice-cream', 'fa-apple-whole',
        'fa-carrot', 'fa-lemon', 'fa-cheese', 'fa-bread-slice', 'fa-cake-candles', 'fa-cookie', 'fa-candy-cane',
        'fa-wine-glass', 'fa-beer', 'fa-martini-glass', 'fa-coffee', 'fa-mug-hot', 'fa-bottle-water',
        // Casa e Móveis
        'fa-house', 'fa-home', 'fa-couch', 'fa-bed', 'fa-chair', 'fa-toilet', 'fa-bath', 'fa-shower',
        'fa-blender', 'fa-microwave', 'fa-fire-burner', 'fa-sink', 'fa-lightbulb', 'fa-fan', 'fa-temperature-high',
        // Ferramentas e Construção
        'fa-hammer', 'fa-wrench', 'fa-screwdriver', 'fa-drill', 'fa-toolbox', 'fa-hard-hat', 
        'fa-ruler', 'fa-paint-roller', 'fa-brush', 'fa-spray-can', 'fa-pen-ruler',
        // Saúde e Medicina
        'fa-heart', 'fa-heart-pulse', 'fa-stethoscope', 'fa-syringe', 'fa-pills', 'fa-prescription-bottle',
        'fa-hospital', 'fa-user-doctor', 'fa-tooth', 'fa-eye', 'fa-hand-holding-heart', 'fa-wheelchair',
        // Esportes e Fitness
        'fa-dumbbell', 'fa-person-running', 'fa-person-biking', 'fa-person-swimming', 'fa-basketball',
        'fa-football', 'fa-baseball', 'fa-volleyball', 'fa-table-tennis', 'fa-golf-ball', 'fa-hockey-puck',
        // Veículos
        'fa-car', 'fa-taxi', 'fa-bus', 'fa-motorcycle', 'fa-bicycle', 'fa-plane', 'fa-helicopter',
        'fa-ship', 'fa-train', 'fa-tractor', 'fa-caravan', 'fa-truck-pickup',
        // Escritório e Negócios
        'fa-briefcase', 'fa-file', 'fa-folder-open', 'fa-pen', 'fa-pencil', 'fa-highlighter',
        'fa-paperclip', 'fa-stapler', 'fa-calculator', 'fa-calendar', 'fa-chart-line', 'fa-chart-pie',
        // Comunicação
        'fa-phone', 'fa-envelope', 'fa-comment', 'fa-message', 'fa-paper-plane', 'fa-inbox',
        'fa-fax', 'fa-voicemail', 'fa-tower-broadcast', 'fa-satellite-dish',
        // Natureza e Animais
        'fa-tree', 'fa-leaf', 'fa-seedling', 'fa-sun', 'fa-moon', 'fa-cloud', 'fa-snowflake',
        'fa-dog', 'fa-cat', 'fa-fish', 'fa-bird', 'fa-horse', 'fa-bug', 'fa-paw',
        // Música e Arte
        'fa-music', 'fa-guitar', 'fa-drum', 'fa-microphone', 'fa-headphones', 'fa-palette',
        'fa-paintbrush', 'fa-image', 'fa-film', 'fa-masks-theater', 'fa-wand-magic-sparkles',
        // Finanças
        'fa-dollar-sign', 'fa-euro-sign', 'fa-sterling-sign', 'fa-yen-sign', 'fa-coins', 'fa-money-bill',
        'fa-credit-card', 'fa-wallet', 'fa-piggy-bank', 'fa-hand-holding-dollar', 'fa-receipt',
        // Segurança
        'fa-lock', 'fa-unlock', 'fa-key', 'fa-shield', 'fa-shield-halved', 'fa-user-shield',
        'fa-fire-extinguisher', 'fa-life-ring', 'fa-lock-open', 'fa-fingerprint',
        // Social e Pessoas
        'fa-user', 'fa-users', 'fa-user-group', 'fa-user-friends', 'fa-user-plus', 'fa-user-check',
        'fa-user-tie', 'fa-user-graduate', 'fa-baby', 'fa-child', 'fa-person', 'fa-face-smile',
        // Símbolos
        'fa-star', 'fa-heart', 'fa-flag', 'fa-bookmark', 'fa-tag', 'fa-tags', 'fa-trophy',
        'fa-award', 'fa-medal', 'fa-certificate', 'fa-ribbon', 'fa-gift', 'fa-bell',
        // Setas e Indicadores  
        'fa-arrow-right', 'fa-arrow-left', 'fa-arrow-up', 'fa-arrow-down', 'fa-circle-arrow-right',
        'fa-check', 'fa-xmark', 'fa-plus', 'fa-minus', 'fa-circle-check', 'fa-circle-xmark',
    ],
    get filteredIcons() {
        if (!this.search) return this.icons;
        return this.icons.filter(icon => icon.toLowerCase().includes(this.search.toLowerCase()));
    }
}" class="relative">
    <!-- Selected Icon Display -->
    <button @click="open = !open" type="button" class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl hover:border-cyan-500 transition flex items-center justify-between bg-white">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-100 to-blue-200 flex items-center justify-center">
                <i :class="selected" class="text-cyan-600 text-xl"></i>
            </div>
            <span class="text-sm font-medium text-gray-700" x-text="selected"></span>
        </div>
        <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="{ 'rotate-180': open }"></i>
    </button>

    <!-- Icon Picker Dropdown -->
    <div x-show="open" 
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="absolute z-50 mt-2 w-full bg-white rounded-2xl shadow-2xl border-2 border-gray-200 overflow-hidden"
         x-cloak>
        
        <!-- Search -->
        <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-cyan-50 to-blue-50">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input x-model="search" 
                       type="text" 
                       placeholder="Pesquisar ícone..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm"
                       @click.stop>
            </div>
        </div>

        <!-- Icons Grid -->
        <div class="p-4 max-h-96 overflow-y-auto">
            <div class="grid grid-cols-6 gap-2">
                <template x-for="icon in filteredIcons" :key="icon">
                    <button type="button"
                            @click="selected = icon; open = false"
                            :class="{ 'bg-cyan-500 text-white': selected === icon, 'bg-gray-100 text-gray-600 hover:bg-gray-200': selected !== icon }"
                            class="w-full aspect-square rounded-lg flex items-center justify-center transition-all transform hover:scale-110"
                            :title="icon">
                        <i :class="icon" class="text-xl"></i>
                    </button>
                </template>
            </div>
            
            <!-- No Results -->
            <div x-show="filteredIcons.length === 0" class="text-center py-8">
                <i class="fas fa-search text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-500 text-sm">Nenhum ícone encontrado</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-3 bg-gray-50 border-t border-gray-200 text-center">
            <p class="text-xs text-gray-500">
                <i class="fab fa-font-awesome mr-1"></i>
                <span x-text="filteredIcons.length"></span> ícones disponíveis
            </p>
        </div>
    </div>
</div>
