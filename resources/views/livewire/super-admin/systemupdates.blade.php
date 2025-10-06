<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-cloud-download-alt text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Atualizações do Sistema</h2>
                    <p class="text-blue-100 text-sm mt-1">Gerenciar updates via GitHub Releases</p>
                </div>
            </div>
            <button wire:click="fetchReleases" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg font-semibold transition">
                <span wire:loading.remove wire:target="fetchReleases">
                    <i class="fas fa-sync mr-2"></i>Atualizar Lista
                </span>
                <span wire:loading wire:target="fetchReleases">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Carregando...
                </span>
            </button>
        </div>
    </div>

    {{-- Card Destaque: Comandos do Sistema --}}
    <div class="mb-6">
        <a href="{{ route('superadmin.system-commands') }}" class="block bg-gradient-to-r from-green-500 via-teal-500 to-blue-500 rounded-2xl shadow-2xl p-6 hover:shadow-3xl transform hover:-translate-y-1 transition-all duration-300">
            <div class="flex items-center justify-between text-white">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center animate-pulse">
                        <i class="fas fa-terminal text-4xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold mb-1">🚀 Comandos do Sistema</h3>
                        <p class="text-white/90">Execute comandos artisan através da interface web com logs em tempo real</p>
                        <div class="flex gap-2 mt-2">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-semibold">Sincronizar Módulos</span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-semibold">Migrations</span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-semibold">Cache</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <i class="fas fa-chevron-right text-3xl opacity-50"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Versão Atual --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Informações do Sistema
                </h3>

                <div class="space-y-4">
                    {{-- Versão Atual --}}
                    <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-4 text-white">
                        <p class="text-sm text-blue-100 mb-1">Versão Atual</p>
                        <p class="text-4xl font-bold">v{{ $currentVersion }}</p>
                    </div>

                    {{-- Stats --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <i class="fas fa-code-branch text-2xl text-blue-600 mb-1"></i>
                            <p class="text-2xl font-bold text-gray-800">{{ count($releases) }}</p>
                            <p class="text-xs text-gray-600">Releases</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <i class="fas fa-arrow-up text-2xl text-green-600 mb-1"></i>
                            <p class="text-2xl font-bold text-gray-800">
                                {{ collect($releases)->where('is_newer', true)->count() }}
                            </p>
                            <p class="text-xs text-gray-600">Disponíveis</p>
                        </div>
                    </div>

                    {{-- Repositório --}}
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-600 mb-1">Repositório GitHub</p>
                        <a href="https://github.com/tiofox77/soserpfox" 
                           target="_blank"
                           class="text-sm text-blue-600 hover:text-blue-800 font-mono break-all">
                            tiofox77/soserpfox
                            <i class="fas fa-external-link-alt text-xs ml-1"></i>
                        </a>
                    </div>

                    {{-- Avisos --}}
                    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-3">
                        <p class="text-xs font-bold text-yellow-800 mb-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Importante
                        </p>
                        <ul class="text-xs text-yellow-700 space-y-1">
                            <li>• Backup automático antes de atualizar</li>
                            <li>• Requer permissão de Super Admin</li>
                            <li>• Processo pode demorar alguns minutos</li>
                            <li>• Não feche a janela durante atualização</li>
                        </ul>
                    </div>

                    {{-- Tipos de Ação --}}
                    <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-3">
                        <p class="text-xs font-bold text-blue-800 mb-2">
                            <i class="fas fa-info-circle mr-1"></i>Tipos de Atualização
                        </p>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-start gap-2">
                                <span class="px-2 py-0.5 bg-green-500 text-white rounded text-[10px] font-bold">NOVA</span>
                                <span class="text-blue-700">Instalar versão mais recente</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="px-2 py-0.5 bg-orange-500 text-white rounded text-[10px] font-bold">ANTIGA</span>
                                <span class="text-blue-700">Reverter para versão anterior</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="px-2 py-0.5 bg-purple-500 text-white rounded text-[10px] font-bold">ATUAL</span>
                                <span class="text-blue-700">Reinstalar mesma versão</span>
                            </div>
                        </div>
                    </div>

                    {{-- Como criar releases --}}
                    <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-3">
                        <p class="text-xs font-bold text-blue-800 mb-2">
                            <i class="fas fa-info-circle mr-1"></i>Como criar releases no GitHub
                        </p>
                        <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                            <li>Acesse seu repositório no GitHub</li>
                            <li>Clique em "Releases" → "Create a new release"</li>
                            <li>Defina uma tag (ex: v5.0.1, v5.1.0)</li>
                            <li>Adicione título e descrição (changelog)</li>
                            <li>Click em "Publish release"</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Log de Atualização --}}
            @if(!empty($updateLog) && !$updateInProgress)
            <div class="mt-6 bg-gray-900 rounded-2xl shadow-lg p-4">
                <h4 class="text-sm font-bold text-white mb-3 flex items-center">
                    <i class="fas fa-terminal mr-2"></i>Último Log de Atualização
                </h4>
                <div class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach($updateLog as $log)
                    <div class="flex items-start gap-2 text-xs font-mono">
                        <span class="text-gray-500">[{{ $log['time'] }}]</span>
                        <span class="{{ $log['type'] === 'error' ? 'text-red-400' : ($log['type'] === 'success' ? 'text-green-400' : 'text-gray-300') }}">
                            {{ $log['message'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Lista de Releases --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Releases Disponíveis
                </h3>

                @if($loading)
                <div class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-600 mb-4"></i>
                    <p class="text-gray-600">Buscando releases do GitHub...</p>
                    <p class="text-xs text-gray-500 mt-2">Aguarde, pode levar até 30 segundos...</p>
                </div>
                @elseif(empty($releases))
                <div class="text-center py-12">
                    <i class="fas fa-cloud-upload-alt text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-700 font-bold text-lg mb-2">Nenhuma release encontrada</p>
                    <p class="text-sm text-gray-500 mb-4">Clique em "Atualizar Lista" para buscar releases do GitHub</p>
                    
                    <div class="max-w-md mx-auto text-left bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mt-4">
                        <p class="text-sm font-bold text-blue-800 mb-2">
                            <i class="fas fa-lightbulb mr-1"></i>Primeira vez? Crie uma release no GitHub:
                        </p>
                        <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                            <li>Vá para: <a href="https://github.com/tiofox77/soserpfox/releases/new" target="_blank" class="underline">github.com/tiofox77/soserpfox/releases/new</a></li>
                            <li>Tag: <code class="bg-blue-200 px-1 rounded">v5.0.0</code></li>
                            <li>Título: <code class="bg-blue-200 px-1 rounded">SOS ERP v5.0.0</code></li>
                            <li>Descrição: Liste as melhorias e correções</li>
                            <li>Publique e volte aqui para buscar!</li>
                        </ol>
                    </div>
                </div>
                @else
                <div class="space-y-4">
                    @foreach($releases as $release)
                    <div class="border-2 {{ $release['is_newer'] ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-gray-50' }} rounded-xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg font-bold text-gray-800">{{ $release['name'] ?? $release['tag_name'] }}</span>
                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs rounded-full font-bold">
                                        {{ $release['tag_name'] }}
                                    </span>
                                    @if($release['is_newer'])
                                    <span class="px-2 py-1 bg-green-500 text-white text-xs rounded-full font-bold animate-pulse">
                                        ⬆️ NOVA
                                    </span>
                                    @elseif($release['tag_name'] === $currentVersion)
                                    <span class="px-2 py-1 bg-purple-500 text-white text-xs rounded-full font-bold">
                                        ✓ ATUAL
                                    </span>
                                    @else
                                    <span class="px-2 py-1 bg-orange-500 text-white text-xs rounded-full font-bold">
                                        ⬇️ ANTIGA
                                    </span>
                                    @endif
                                    @if($release['prerelease'])
                                    <span class="px-2 py-1 bg-yellow-500 text-white text-xs rounded-full font-bold">
                                        <i class="fas fa-flask mr-1"></i>Beta
                                    </span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ \Carbon\Carbon::parse($release['published_at'])->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                @if($release['is_newer'])
                                <button wire:click="installUpdate('{{ $release['tag_name'] }}')" 
                                        wire:loading.attr="disabled"
                                        wire:target="installUpdate"
                                        class="px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg font-bold text-sm shadow-lg transition">
                                    <span wire:loading.remove wire:target="installUpdate">
                                        <i class="fas fa-download mr-1"></i>Instalar
                                    </span>
                                    <span wire:loading wire:target="installUpdate">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Instalando...
                                    </span>
                                </button>
                                @elseif($release['tag_name'] !== $currentVersion)
                                <button wire:click="installUpdate('{{ $release['tag_name'] }}')" 
                                        wire:loading.attr="disabled"
                                        wire:target="installUpdate"
                                        onclick="return confirm('Tem certeza? Esta versão é mais antiga que a atual!')"
                                        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-bold text-sm shadow-lg transition">
                                    <span wire:loading.remove wire:target="installUpdate">
                                        <i class="fas fa-undo mr-1"></i>Reverter
                                    </span>
                                    <span wire:loading wire:target="installUpdate">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Revertendo...
                                    </span>
                                </button>
                                @else
                                <button wire:click="installUpdate('{{ $release['tag_name'] }}')" 
                                        wire:loading.attr="disabled"
                                        wire:target="installUpdate"
                                        onclick="return confirm('Reinstalar esta versão?')"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold text-sm shadow-lg transition">
                                    <span wire:loading.remove wire:target="installUpdate">
                                        <i class="fas fa-sync mr-1"></i>Reinstalar
                                    </span>
                                    <span wire:loading wire:target="installUpdate">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Reinstalando...
                                    </span>
                                </button>
                                @endif
                                <a href="https://github.com/tiofox77/soserpfox/releases/tag/{{ $release['tag_name'] }}" 
                                   target="_blank"
                                   class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-bold text-sm transition">
                                    <i class="fas fa-external-link-alt mr-1"></i>Ver
                                </a>
                            </div>
                        </div>

                        {{-- Changelog --}}
                        @if($release['body'])
                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                            <p class="text-xs font-bold text-gray-700 mb-2">
                                <i class="fas fa-list-ul mr-1"></i>Changelog:
                            </p>
                            <div class="text-xs text-gray-600 prose prose-sm max-w-none">
                                {!! nl2br(e(substr($release['body'], 0, 500))) !!}
                                @if(strlen($release['body']) > 500)
                                <span class="text-blue-600">... (ver mais no GitHub)</span>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal de Progresso (se atualizando) --}}
    @if($updateInProgress)
    <div class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-3xl w-full p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
                    Atualizando Sistema
                </h3>
                <span class="text-sm text-gray-600">{{ $currentStep }}</span>
            </div>
            
            {{-- Progress Bar --}}
            <div class="mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-700 font-semibold">Progresso da Atualização</span>
                    <span class="text-blue-600 font-bold">{{ round($progressPercentage) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-blue-500 via-cyan-500 to-green-500 h-4 rounded-full transition-all duration-500 shadow-lg" 
                         style="width: {{ $progressPercentage }}%"></div>
                </div>
            </div>

            {{-- Warning --}}
            <div class="bg-yellow-50 border-2 border-yellow-300 rounded-xl p-4 mb-4">
                <p class="text-sm font-bold text-yellow-800 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Importante: Não feche esta janela durante a atualização!
                </p>
            </div>

            {{-- Console Log --}}
            <div class="bg-gray-900 rounded-xl p-4 max-h-80 overflow-y-auto shadow-inner" x-ref="logContainer">
                @forelse($updateLog as $log)
                    <div class="flex items-start mb-2 {{ $log['type'] === 'error' ? 'text-red-400' : ($log['type'] === 'success' ? 'text-green-400' : 'text-gray-300') }}">
                        <span class="text-gray-500 mr-3 flex-shrink-0 font-mono text-xs">[{{ $log['time'] }}]</span>
                        <span class="flex-1 font-mono text-sm">{{ $log['message'] }}</span>
                    </div>
                @empty
                    <div class="text-gray-500 text-center py-4">
                        <i class="fas fa-hourglass-start mr-2"></i>
                        Aguardando início da atualização...
                    </div>
                @endforelse
            </div>
            
            {{-- Auto scroll script --}}
            <script>
                document.addEventListener('livewire:updated', () => {
                    const container = document.querySelector('[x-ref="logContainer"]');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
            </script>
        </div>
    </div>
    @endif
</div>
