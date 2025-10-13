<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-code text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Executar Scripts</h2>
                    <p class="text-cyan-100 text-sm">Gerencie e execute scripts do sistema</p>
                </div>
            </div>
            <div class="flex space-x-2">
                <button wire:click="refreshScripts" 
                        class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-sync-alt mr-2"></i>Atualizar
                </button>
                <button wire:click="toggleLogs" 
                        class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-file-alt mr-2"></i>{{ $showLogs ? 'Ocultar' : 'Ver' }} Logs
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        {{-- Lista de Scripts --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-folder-open mr-2 text-cyan-600"></i>
                        Scripts Disponíveis ({{ count($scripts) }})
                    </h3>
                </div>

                <div class="p-4 max-h-[70vh] overflow-y-auto">
                    @forelse($scripts as $script)
                        <div class="mb-3 border-2 rounded-xl p-4 transition-all
                                    {{ $selectedScript === $script['name'] ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200 hover:border-cyan-300 hover:bg-gray-50' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900 flex items-center">
                                        <i class="fas fa-file-code mr-2 text-cyan-600"></i>
                                        {{ $script['name'] }}
                                    </h4>
                                    <p class="text-xs text-gray-600 mt-1">{{ $script['description'] }}</p>
                                    <div class="flex items-center space-x-3 mt-2 text-xs text-gray-500">
                                        <span><i class="fas fa-weight mr-1"></i>{{ $script['size'] }}</span>
                                        <span><i class="fas fa-clock mr-1"></i>{{ $script['modified_human'] }}</span>
                                    </div>
                                </div>
                                <button wire:click="runScript('{{ $script['name'] }}')" 
                                        wire:loading.attr="disabled"
                                        wire:target="runScript"
                                        class="ml-3 bg-gradient-to-r from-cyan-600 to-blue-600 text-white px-4 py-2 rounded-lg hover:shadow-lg transition disabled:opacity-50">
                                    <span wire:loading.remove wire:target="runScript('{{ $script['name'] }}')">
                                        <i class="fas fa-play mr-1"></i>Executar
                                    </span>
                                    <span wire:loading wire:target="runScript('{{ $script['name'] }}')">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Executando...
                                    </span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <i class="fas fa-folder-open text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500">Nenhum script encontrado na pasta <code class="bg-gray-100 px-2 py-1 rounded">scripts/</code></p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Output do Script --}}
        <div class="col-span-12 lg:col-span-7">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-terminal mr-2 text-cyan-600"></i>
                        Output do Script
                        @if($selectedScript)
                            <span class="ml-2 text-sm font-normal text-gray-600">({{ $selectedScript }})</span>
                        @endif
                    </h3>
                    @if($output)
                        <button wire:click="clearOutput" 
                                class="text-sm text-red-600 hover:text-red-700 font-semibold">
                            <i class="fas fa-times-circle mr-1"></i>Limpar
                        </button>
                    @endif
                </div>

                <div class="p-6">
                    @if($running)
                        <div class="flex items-center justify-center py-12">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin text-cyan-600 text-4xl mb-4"></i>
                                <p class="text-gray-600 font-semibold">Executando script...</p>
                            </div>
                        </div>
                    @elseif($output)
                        <div class="bg-gray-900 rounded-xl p-4 overflow-x-auto">
                            <pre class="text-green-400 font-mono text-sm whitespace-pre-wrap">{{ $output }}</pre>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <i class="fas fa-code text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500">Selecione um script para ver o output aqui</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Logs CLI --}}
    @if($showLogs)
        <div class="mt-6 bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-file-alt mr-2 text-cyan-600"></i>
                    Logs do Sistema (Últimas 100 linhas)
                </h3>
                <div class="flex space-x-2">
                    <button wire:click="loadRecentLogs" 
                            class="text-sm text-cyan-600 hover:text-cyan-700 font-semibold">
                        <i class="fas fa-sync-alt mr-1"></i>Atualizar
                    </button>
                    <button wire:click="clearLogs" 
                            class="text-sm text-red-600 hover:text-red-700 font-semibold"
                            onclick="return confirm('Tem certeza que deseja limpar todos os logs?')">
                        <i class="fas fa-trash mr-1"></i>Limpar Logs
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="bg-gray-900 rounded-xl p-4 overflow-x-auto max-h-[400px] overflow-y-auto">
                    @if(count($logs) > 0)
                        @foreach($logs as $log)
                            @if(trim($log))
                                <div class="text-green-400 font-mono text-xs mb-1 hover:bg-gray-800 px-2 py-1 rounded">
                                    {{ $log }}
                                </div>
                            @endif
                        @endforeach
                    @else
                        <p class="text-gray-500 text-center py-8">Nenhum log disponível</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
