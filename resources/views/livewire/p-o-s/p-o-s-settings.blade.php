<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-cog mr-3 text-indigo-600"></i>
                    Configurações do POS
                </h1>
                <p class="text-gray-600 mt-2">Configure o comportamento do Ponto de Venda</p>
            </div>
        </div>
    </div>

    {{-- Configurações --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        {{-- Som e Notificações --}}
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-volume-up mr-2 text-indigo-600"></i>
                Som e Notificações
            </h2>
            
            <div class="space-y-4">
                {{-- Ativar/Desativar Som --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex-1">
                        <label class="flex items-center cursor-pointer">
                            <div class="mr-3">
                                <i class="fas fa-bell text-2xl text-indigo-600"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">Sons do POS</p>
                                <p class="text-sm text-gray-600">Tocar som ao adicionar/remover produtos</p>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="pos-sound-toggle" class="sr-only peer" checked>
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>

                {{-- Testar Som --}}
                <button onclick="testPosSound()" 
                        class="w-full px-4 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold hover:from-indigo-700 hover:to-purple-700 transition shadow-lg">
                    <i class="fas fa-play mr-2"></i>Testar Som
                </button>

                {{-- Informação --}}
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        Os sons ajudam a confirmar ações no POS. Você pode desativar se preferir trabalhar em silêncio.
                    </p>
                </div>
            </div>
        </div>

        {{-- Aparência --}}
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-palette mr-2 text-indigo-600"></i>
                Aparência
            </h2>
            
            <div class="space-y-4">
                {{-- Grid de Produtos --}}
                <div class="p-4 bg-gray-50 rounded-xl">
                    <label class="block mb-2">
                        <span class="font-bold text-gray-900">Colunas do Grid</span>
                        <p class="text-sm text-gray-600">Produtos por linha (Desktop)</p>
                    </label>
                    <select class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="4">4 colunas</option>
                        <option value="5" selected>5 colunas (Padrão)</option>
                        <option value="6">6 colunas</option>
                        <option value="8">8 colunas</option>
                    </select>
                </div>

                {{-- Modo Compacto --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div>
                        <p class="font-bold text-gray-900">Modo Compacto</p>
                        <p class="text-sm text-gray-600">Reduz espaçamentos para caber mais produtos</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Impressão --}}
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-print mr-2 text-indigo-600"></i>
                Impressão
            </h2>
            
            <div class="space-y-4">
                {{-- Auto Impressão --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div>
                        <p class="font-bold text-gray-900">Impressão Automática</p>
                        <p class="text-sm text-gray-600">Imprimir ticket após concluir venda</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                {{-- Tamanho do Papel --}}
                <div class="p-4 bg-gray-50 rounded-xl">
                    <label class="block mb-2">
                        <span class="font-bold text-gray-900">Tamanho do Papel</span>
                        <p class="text-sm text-gray-600">Formato da impressora térmica</p>
                    </label>
                    <select class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="80mm" selected>80mm (Padrão)</option>
                        <option value="58mm">58mm</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Comportamento --}}
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-sliders-h mr-2 text-indigo-600"></i>
                Comportamento
            </h2>
            
            <div class="space-y-4">
                {{-- Cliente Padrão --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div>
                        <p class="font-bold text-gray-900">Cliente Padrão</p>
                        <p class="text-sm text-gray-600">Sempre iniciar com Consumidor Final</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked>
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                {{-- Confirmar Remoção --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div>
                        <p class="font-bold text-gray-900">Confirmar Remoção</p>
                        <p class="text-sm text-gray-600">Pedir confirmação ao remover produtos</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Carregar configuração de som do localStorage
document.addEventListener('DOMContentLoaded', function() {
    const soundToggle = document.getElementById('pos-sound-toggle');
    const soundEnabled = localStorage.getItem('pos_sound_enabled') !== 'false';
    soundToggle.checked = soundEnabled;
    
    // Salvar quando mudar
    soundToggle.addEventListener('change', function() {
        localStorage.setItem('pos_sound_enabled', this.checked);
        
        if (window.toastr) {
            toastr.success(this.checked ? 'Sons ativados!' : 'Sons desativados!');
        }
    });
});

// Função para testar som
function testPosSound() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    // Som de teste (beep duplo)
    oscillator.frequency.value = 800;
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.1);
    
    // Segundo beep
    setTimeout(() => {
        const oscillator2 = audioContext.createOscillator();
        const gainNode2 = audioContext.createGain();
        
        oscillator2.connect(gainNode2);
        gainNode2.connect(audioContext.destination);
        
        oscillator2.frequency.value = 1000;
        gainNode2.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        oscillator2.start(audioContext.currentTime);
        oscillator2.stop(audioContext.currentTime + 0.1);
    }, 150);
}
</script>
