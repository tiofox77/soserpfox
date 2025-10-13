<div>
    {{-- Header com Gradient --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-rocket text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Otimização do Sistema</h2>
                    <p class="text-indigo-100 text-sm">Performance e cache</p>
                </div>
            </div>
            <button wire:click="loadData" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg font-semibold transition">
                <i class="fas fa-sync-alt mr-2"></i>Atualizar
            </button>
        </div>
    </div>

    {{-- Ações Rápidas --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <button wire:click="clearOpcache" 
                wire:loading.attr="disabled"
                class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-1 disabled:opacity-50">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-bolt text-3xl"></i>
                <span wire:loading wire:target="clearOpcache" class="text-sm">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </div>
            <h3 class="text-lg font-bold mb-1">Limpar OPcache</h3>
            <p class="text-purple-100 text-sm">Reseta bytecode cache</p>
        </button>

        <button wire:click="clearAllCaches" 
                wire:loading.attr="disabled"
                class="bg-gradient-to-r from-orange-600 to-red-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-1 disabled:opacity-50">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-trash-alt text-3xl"></i>
                <span wire:loading wire:target="clearAllCaches" class="text-sm">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </div>
            <h3 class="text-lg font-bold mb-1">Limpar Todos Caches</h3>
            <p class="text-orange-100 text-sm">Laravel + OPcache</p>
        </button>

        <button wire:click="optimizeSystem" 
                wire:loading.attr="disabled"
                class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-1 disabled:opacity-50">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-tachometer-alt text-3xl"></i>
                <span wire:loading wire:target="optimizeSystem" class="text-sm">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </div>
            <h3 class="text-lg font-bold mb-1">Otimizar Sistema</h3>
            <p class="text-green-100 text-sm">Cache config, routes, views</p>
        </button>

        <button wire:click="openConfigModal" 
                class="bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-cog text-3xl"></i>
            </div>
            <h3 class="text-lg font-bold mb-1">⚙️ Configurações</h3>
            <p class="text-blue-100 text-sm">Gerar .user.ini para cPanel</p>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- OPcache Status --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-bolt text-purple-600 mr-2"></i>
                    OPcache Status
                </h3>
                @if($opcacheStats['available'] ?? false)
                    @if($opcacheStats['enabled'] ?? false)
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">
                            <i class="fas fa-check-circle mr-1"></i>Ativo
                        </span>
                    @else
                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-bold">
                            <i class="fas fa-times-circle mr-1"></i>Inativo
                        </span>
                    @endif
                @else
                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-bold">
                        <i class="fas fa-exclamation-circle mr-1"></i>Não Disponível
                    </span>
                @endif
            </div>

            @if($opcacheStats['available'] ?? false)
                @if($opcacheStats['enabled'] ?? false)
                    {{-- Health Status --}}
                    <div class="mb-6 p-4 rounded-xl {{ $opcacheHealth['status'] === 'success' ? 'bg-green-50 border-2 border-green-200' : ($opcacheHealth['status'] === 'warning' ? 'bg-orange-50 border-2 border-orange-200' : 'bg-blue-50 border-2 border-blue-200') }}">
                        <p class="font-bold {{ $opcacheHealth['status'] === 'success' ? 'text-green-900' : ($opcacheHealth['status'] === 'warning' ? 'text-orange-900' : 'text-blue-900') }} flex items-center mb-2">
                            <i class="fas fa-{{ $opcacheHealth['status'] === 'success' ? 'check-circle' : ($opcacheHealth['status'] === 'warning' ? 'exclamation-triangle' : 'info-circle') }} mr-2"></i>
                            {{ $opcacheHealth['message'] }}
                        </p>
                        
                        @if(!empty($opcacheHealth['issues']))
                            <ul class="text-sm text-red-800 space-y-1 mb-2">
                                @foreach($opcacheHealth['issues'] as $issue)
                                    <li>❌ {{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        
                        @if(!empty($opcacheHealth['warnings']))
                            <ul class="text-sm text-orange-800 space-y-1">
                                @foreach($opcacheHealth['warnings'] as $warning)
                                    <li>⚠️ {{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Estatísticas --}}
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Hit Rate</span>
                                <span class="text-sm font-bold {{ $opcacheStats['stats']['hit_rate'] >= 95 ? 'text-green-600' : ($opcacheStats['stats']['hit_rate'] >= 80 ? 'text-orange-600' : 'text-red-600') }}">
                                    {{ $opcacheStats['stats']['hit_rate'] }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full {{ $opcacheStats['stats']['hit_rate'] >= 95 ? 'bg-green-500' : ($opcacheStats['stats']['hit_rate'] >= 80 ? 'bg-orange-500' : 'bg-red-500') }}" 
                                     style="width: {{ $opcacheStats['stats']['hit_rate'] }}%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl">
                                <p class="text-xs text-blue-600 font-semibold mb-1">Hits</p>
                                <p class="text-2xl font-bold text-blue-900">{{ $opcacheStats['stats']['hits'] }}</p>
                            </div>
                            <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-xl">
                                <p class="text-xs text-red-600 font-semibold mb-1">Misses</p>
                                <p class="text-2xl font-bold text-red-900">{{ $opcacheStats['stats']['misses'] }}</p>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-purple-600 font-semibold mb-1">Scripts em Cache</p>
                                    <p class="text-2xl font-bold text-purple-900">{{ $opcacheStats['stats']['cached_scripts'] }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-purple-600">Máximo</p>
                                    <p class="text-sm font-bold text-purple-700">{{ $opcacheStats['stats']['max_scripts'] }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Memória --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Uso de Memória</span>
                                <span class="text-sm font-bold text-purple-600">
                                    {{ $opcacheStats['memory']['used_mb'] }} MB / {{ $opcacheStats['memory']['total_mb'] }} MB
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500" 
                                     style="width: {{ $opcacheStats['memory']['usage_percentage'] }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $opcacheStats['memory']['usage_percentage'] }}% usado
                                @if($opcacheStats['memory']['wasted_mb'] > 0)
                                    • {{ $opcacheStats['memory']['wasted_mb'] }} MB desperdiçados
                                @endif
                            </p>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-power-off text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-600 font-semibold">OPcache está instalado mas não está ativo</p>
                        <p class="text-gray-500 text-sm mt-2">Ative em php.ini: opcache.enable=1</p>
                    </div>
                @endif
            @else
                <div class="text-center py-8">
                    <i class="fas fa-puzzle-piece text-gray-300 text-5xl mb-4"></i>
                    <p class="text-gray-600 font-semibold">OPcache não está instalado</p>
                    <p class="text-gray-500 text-sm mt-2">Instale a extensão PHP opcache</p>
                </div>
            @endif
        </div>

        {{-- PHP Configuration --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-cog text-indigo-600 mr-2"></i>
                Configuração PHP
            </h3>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl">
                    <div>
                        <p class="text-sm font-semibold text-indigo-900">Versão PHP</p>
                        <p class="text-xs text-indigo-600">php -v</p>
                    </div>
                    <span class="text-lg font-bold text-indigo-700">{{ $phpInfo['version'] }}</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Memory Limit</p>
                        <p class="text-lg font-bold text-gray-900">{{ $phpInfo['memory_limit'] }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Max Execution</p>
                        <p class="text-lg font-bold text-gray-900">{{ $phpInfo['max_execution_time'] }}s</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Upload Max</p>
                        <p class="text-lg font-bold text-gray-900">{{ $phpInfo['upload_max_filesize'] }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Post Max</p>
                        <p class="text-lg font-bold text-gray-900">{{ $phpInfo['post_max_size'] }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl col-span-2">
                        <p class="text-xs text-gray-500 mb-1">Max Input Vars</p>
                        <p class="text-lg font-bold text-gray-900">{{ $phpInfo['max_input_vars'] }}</p>
                    </div>
                </div>
            </div>

            {{-- OPcache Configuration --}}
            @if(($opcacheStats['available'] ?? false) && ($opcacheStats['enabled'] ?? false))
                <div class="mt-6 pt-6 border-t">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-sliders-h text-purple-600 mr-2"></i>
                        Configuração OPcache
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Memory Consumption</span>
                            <span class="font-bold text-gray-900">{{ $opcacheStats['config']['memory_consumption'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Max Files</span>
                            <span class="font-bold text-gray-900">{{ $opcacheStats['config']['max_files'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Validate Timestamps</span>
                            <span class="font-bold {{ $opcacheStats['config']['validate_timestamps'] === 'Sim' ? 'text-green-600' : 'text-orange-600' }}">
                                {{ $opcacheStats['config']['validate_timestamps'] }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Revalidate Frequency</span>
                            <span class="font-bold text-gray-900">{{ $opcacheStats['config']['revalidate_freq'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">CLI Enabled</span>
                            <span class="font-bold text-gray-900">{{ $opcacheStats['config']['cli_enabled'] }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Documentação Rápida --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl p-6 mt-6">
        <h3 class="text-lg font-bold text-blue-900 mb-4 flex items-center">
            <i class="fas fa-book text-blue-600 mr-2"></i>
            Quando Usar Cada Opção
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl p-4">
                <h4 class="font-bold text-purple-900 mb-2">🔥 Limpar OPcache</h4>
                <p class="text-sm text-gray-600">Após deploy ou mudanças no código PHP. Reseta bytecode compilado.</p>
            </div>
            <div class="bg-white rounded-xl p-4">
                <h4 class="font-bold text-orange-900 mb-2">🗑️ Limpar Todos Caches</h4>
                <p class="text-sm text-gray-600">Quando houver problemas ou mudanças em configs/rotas/views.</p>
            </div>
            <div class="bg-white rounded-xl p-4">
                <h4 class="font-bold text-green-900 mb-2">⚡ Otimizar Sistema</h4>
                <p class="text-sm text-gray-600">Antes de ir para produção. Cria cache de config/rotas/views.</p>
            </div>
        </div>
    </div>

    {{-- Modal: Configurações PHP --}}
    @if($showConfigModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-cog mr-2"></i>
                    Configurações PHP para Produção
                </h3>
                <button wire:click="closeConfigModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                {{-- Perfis Rápidos --}}
                <div class="mb-6">
                    <h4 class="font-bold text-gray-900 mb-3">🚀 Perfis de Configuração</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <button wire:click="applyProductionSettings" 
                                class="p-4 border-2 {{ $environment === 'production' ? 'border-green-500 bg-green-50' : 'border-gray-300' }} rounded-xl hover:border-green-400 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="font-bold text-green-900">🏭 Produção (cPanel)</h5>
                                @if($environment === 'production')
                                    <i class="fas fa-check-circle text-green-600"></i>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600">Máxima performance, sem validação</p>
                        </button>
                        <button wire:click="applyDevelopmentSettings" 
                                class="p-4 border-2 {{ $environment === 'development' ? 'border-blue-500 bg-blue-50' : 'border-gray-300' }} rounded-xl hover:border-blue-400 transition">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="font-bold text-blue-900">💻 Desenvolvimento (Local)</h5>
                                @if($environment === 'development')
                                    <i class="fas fa-check-circle text-blue-600"></i>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600">Validação ativa, vê mudanças</p>
                        </button>
                    </div>
                </div>

                {{-- Configurações Detalhadas --}}
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-6 mb-6">
                    <h4 class="font-bold text-indigo-900 mb-4">⚙️ Configurações Detalhadas</h4>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    OPcache Validate Timestamps
                                    <span class="text-xs font-normal text-gray-500">(0=Prod, 1=Dev)</span>
                                </label>
                                <select wire:model="validate_timestamps" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg">
                                    <option value="0">0 - Desabilitado (Produção)</option>
                                    <option value="1">1 - Habilitado (Desenvolvimento)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Revalidate Frequency (segundos)
                                </label>
                                <input type="number" wire:model="revalidate_freq" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Max Input Vars
                                    <span class="text-xs font-normal text-gray-500">(Recomendado: 3000)</span>
                                </label>
                                <input type="number" wire:model="max_input_vars" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Memory Limit
                                </label>
                                <input type="text" wire:model="memory_limit" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg" placeholder="512M">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Max Execution Time (segundos)
                            </label>
                            <input type="number" wire:model="max_execution_time" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg">
                        </div>
                    </div>
                </div>

                {{-- Preview do Arquivo --}}
                <div class="bg-gray-900 text-green-400 p-4 rounded-xl mb-6 font-mono text-sm overflow-x-auto max-h-64">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white font-bold">📄 Preview: .user.ini</span>
                        <span class="text-xs text-gray-400">Ambiente: {{ strtoupper($environment) }}</span>
                    </div>
                    <pre>; === OPCACHE SETTINGS ===
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = {{ $validate_timestamps }}
opcache.revalidate_freq = {{ $revalidate_freq }}

; === PERFORMANCE ===
memory_limit = {{ $memory_limit }}
max_execution_time = {{ $max_execution_time }}
max_input_vars = {{ $max_input_vars }}</pre>
                </div>

                {{-- Instruções --}}
                <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4 mb-6">
                    <h4 class="font-bold text-yellow-900 mb-3 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        📋 Instruções de Uso (cPanel)
                    </h4>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                        <li>Clique em "Download .user.ini" abaixo</li>
                        <li>No cPanel, vá em <strong>File Manager</strong></li>
                        <li>Navegue até a pasta <code class="bg-gray-200 px-2 py-1 rounded">public_html</code></li>
                        <li>Faça upload do arquivo <code class="bg-gray-200 px-2 py-1 rounded">.user.ini</code></li>
                        <li><strong>Aguarde 5 minutos</strong> para o servidor aplicar as mudanças</li>
                        <li>Volte aqui e clique em "Limpar OPcache"</li>
                        <li>Pronto! ✅</li>
                    </ol>
                </div>

                {{-- Ações --}}
                <div class="flex space-x-3">
                    <button wire:click="downloadConfigFile" 
                            class="flex-1 bg-gradient-to-r from-green-600 to-teal-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">
                        <i class="fas fa-download mr-2"></i>Download .user.ini
                    </button>
                    <button wire:click="generateConfigFile" 
                            class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">
                        <i class="fas fa-save mr-2"></i>Gerar e Salvar
                    </button>
                    <button wire:click="closeConfigModal" 
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
