{{-- Botão Flutuante de Suporte --}}
<div x-data="{ open: false, tab: 'ticket' }" 
     class="fixed bottom-6 right-6 z-50">
    
    {{-- Botão Principal --}}
    <button @click="open = !open"
            class="group relative bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-full p-4 shadow-2xl hover:shadow-purple-500/50 transition-all duration-300 hover:scale-110 focus:outline-none focus:ring-4 focus:ring-purple-300">
        <i class="fas fa-life-ring text-2xl"  x-show="!open"></i>
        <i class="fas fa-times text-2xl" x-show="open" x-cloak></i>
        
        {{-- Tooltip --}}
        <div x-show="!open" 
             class="absolute right-full mr-3 top-1/2 transform -translate-y-1/2 bg-gray-900 text-white text-sm px-3 py-2 rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
            Precisa de ajuda?
            <div class="absolute top-1/2 -right-1 transform -translate-y-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
        </div>
    </button>
    
    {{-- Modal/Panel de Suporte --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-4"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.away="open = false"
         x-cloak
         class="absolute bottom-20 right-0 w-96 bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200">
        
        {{-- Header com Tabs --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-4">
            <h3 class="text-white font-bold text-lg mb-3 flex items-center">
                <i class="fas fa-headset mr-2"></i> Centro de Suporte
            </h3>
            <div class="flex space-x-2">
                <button @click="tab = 'ticket'"
                        :class="tab === 'ticket' ? 'bg-white text-purple-600' : 'bg-purple-500 text-white'"
                        class="flex-1 py-2 px-4 rounded-lg font-semibold transition text-sm">
                    <i class="fas fa-ticket-alt mr-1"></i> Tickets
                </button>
                <button @click="tab = 'features'"
                        :class="tab === 'features' ? 'bg-white text-purple-600' : 'bg-purple-500 text-white'"
                        class="flex-1 py-2 px-4 rounded-lg font-semibold transition text-sm">
                    <i class="fas fa-lightbulb mr-1"></i> Melhorias
                </button>
            </div>
        </div>
        
        {{-- Conteúdo --}}
        <div class="p-4 max-h-96 overflow-y-auto">
            
            {{-- Tab de Tickets --}}
            <div x-show="tab === 'ticket'" x-cloak>
                <p class="text-gray-600 text-sm mb-4">
                    Precisa de ajuda? Abra um ticket e nossa equipe irá atendê-lo!
                </p>
                <a href="{{ route('support.tickets') }}" 
                   class="block w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-center py-3 rounded-xl font-semibold hover:shadow-lg transition">
                    <i class="fas fa-plus mr-2"></i> Abrir Novo Ticket
                </a>
                <a href="{{ route('support.tickets') }}" 
                   class="block w-full mt-2 bg-gray-100 text-gray-700 text-center py-3 rounded-xl font-semibold hover:bg-gray-200 transition">
                    <i class="fas fa-list mr-2"></i> Ver Meus Tickets
                </a>
            </div>
            
            {{-- Tab de Melhorias --}}
            <div x-show="tab === 'features'" x-cloak>
                <p class="text-gray-600 text-sm mb-4">
                    Tem uma ideia para melhorar o sistema? Compartilhe e vote nas sugestões!
                </p>
                <a href="{{ route('support.features') }}" 
                   class="block w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-center py-3 rounded-xl font-semibold hover:shadow-lg transition">
                    <i class="fas fa-lightbulb mr-2"></i> Sugerir Melhoria
                </a>
                <a href="{{ route('support.features') }}" 
                   class="block w-full mt-2 bg-gray-100 text-gray-700 text-center py-3 rounded-xl font-semibold hover:bg-gray-200 transition">
                    <i class="fas fa-fire mr-2"></i> Ver Sugestões Populares
                </a>
            </div>
        </div>
        
        {{-- Footer --}}
        <div class="bg-gray-50 px-4 py-3 text-center text-xs text-gray-500 border-t">
            Equipe de Suporte disponível 24/7
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
</style>
