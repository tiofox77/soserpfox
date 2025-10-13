<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-terminal mr-3 text-indigo-600"></i>
                    Comandos do Sistema
                </h2>
                <p class="text-gray-600 mt-1">Execute comandos artisan através da interface</p>
            </div>
            <div class="flex gap-2">
                @if($output)
                    <button wire:click="clearOutput" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                        <i class="fas fa-eraser mr-2"></i>Limpar Output
                    </button>
                @endif
                @if(!empty($executionHistory))
                    <button wire:click="clearHistory" wire:confirm="Tem certeza que deseja limpar o histórico?" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-trash mr-2"></i>Limpar Histórico
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Seção de Seeders --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-teal-600 rounded-lg shadow-lg p-6 text-white">
        <h3 class="text-2xl font-bold mb-4 flex items-center">
            <i class="fas fa-seedling mr-3"></i>
            Executar Seeders
        </h3>
        <p class="text-green-100 mb-4">Selecione e execute um seeder do banco de dados</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Seeder Disponível</label>
                <select wire:model="selectedSeeder" class="w-full px-4 py-2 text-gray-900 border-2 border-green-300 rounded-lg focus:ring-2 focus:ring-green-400">
                    <option value="">Selecione um seeder...</option>
                    @foreach($availableSeeders as $seeder)
                        <option value="{{ $seeder['class'] }}">{{ $seeder['name'] }} ({{ $seeder['class'] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="runSeeder" 
                        wire:loading.attr="disabled"
                        wire:target="runSeeder"
                        class="w-full px-6 py-2 bg-white text-green-600 font-bold rounded-lg hover:bg-green-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="runSeeder">
                        <i class="fas fa-play mr-2"></i>Executar Seeder
                    </span>
                    <span wire:loading wire:target="runSeeder">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Executando...
                    </span>
                </button>
            </div>
        </div>
        
        @if(count($availableSeeders) === 0)
            <div class="mt-4 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 rounded">
                <p class="font-semibold">⚠️ Nenhum seeder encontrado</p>
                <p class="text-sm">Crie seeders na pasta database/seeders/</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Lista de Comandos --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Comandos Disponíveis
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($commands as $key => $command)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition cursor-pointer bg-{{ $command['color'] }}-50"
                             wire:click="$set('selectedCommand', '{{ $key }}')">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-{{ $command['color'] }}-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-{{ $command['icon'] }} text-{{ $command['color'] }}-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">{{ $command['name'] }}</h4>
                                        <code class="text-xs text-gray-500">{{ $command['command'] }}</code>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">{{ $command['description'] }}</p>
                            
                            @if(!empty($command['params']))
                                <div class="mb-3">
                                    <p class="text-xs text-gray-500 font-semibold mb-2">Parâmetros:</p>
                                    @foreach($command['params'] as $paramKey => $param)
                                        <div class="mb-2">
                                            <label class="text-xs text-gray-600">{{ $param['label'] }}</label>
                                            
                                            @if($param['type'] === 'select')
                                                <select wire:model.defer="commandParams.{{ $key }}.{{ $paramKey }}" 
                                                        class="w-full text-sm border-gray-300 rounded-md">
                                                    <option value="">Selecione...</option>
                                                    @if($param['options'] === 'plans')
                                                        @foreach($plans as $plan)
                                                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                                        @endforeach
                                                    @elseif($param['options'] === 'modules')
                                                        @foreach($modules as $module)
                                                            <option value="{{ $module->slug }}">{{ $module->name }}</option>
                                                        @endforeach
                                                    @elseif($param['options'] === 'tenants')
                                                        @foreach($tenants as $tenant)
                                                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            @elseif($param['type'] === 'checkbox')
                                                <label class="flex items-center">
                                                    <input type="checkbox" wire:model.defer="commandParams.{{ $key }}.{{ $paramKey }}"
                                                           class="rounded border-gray-300 text-{{ $command['color'] }}-600 mr-2">
                                                    <span class="text-xs text-gray-600">Ativar</span>
                                                </label>
                                            @else
                                                <input type="text" wire:model.defer="commandParams.{{ $key }}.{{ $paramKey }}"
                                                       class="w-full text-sm border-gray-300 rounded-md"
                                                       placeholder="{{ $param['label'] }}">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            
                            <button wire:click="runCommand('{{ $key }}')" 
                                    wire:loading.attr="disabled"
                                    wire:target="runCommand('{{ $key }}')"
                                    class="w-full px-4 py-2 bg-{{ $command['color'] }}-600 text-white rounded-lg hover:bg-{{ $command['color'] }}-700 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="runCommand('{{ $key }}')">
                                    <i class="fas fa-play mr-2"></i>Executar
                                </span>
                                <span wire:loading wire:target="runCommand('{{ $key }}')">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Executando...
                                </span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Output CLI --}}
            @if($output)
                <div class="bg-gray-900 rounded-lg shadow-md p-6 mt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-terminal mr-2 text-green-400"></i>
                            Terminal Output
                        </h3>
                        <button wire:click="clearOutput" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="bg-black rounded-lg p-4 font-mono text-sm overflow-auto max-h-96" style="max-height: 500px;">
                        <div class="text-green-400">
                            {!! $output !!}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Histórico de Execuções --}}
        <div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-history mr-2 text-purple-600"></i>
                    Histórico de Execuções
                </h3>

                @if(empty($executionHistory))
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>Nenhuma execução registrada</p>
                    </div>
                @else
                    <div class="space-y-3 max-h-[600px] overflow-y-auto">
                        @foreach($executionHistory as $item)
                            <div class="border-l-4 {{ $item['success'] ? 'border-green-500' : 'border-red-500' }} bg-gray-50 p-3 rounded-r-lg">
                                <div class="flex items-start justify-between mb-1">
                                    <h4 class="font-semibold text-sm text-gray-800">{{ $item['command_name'] }}</h4>
                                    @if($item['success'])
                                        <span class="text-green-600 text-xs">
                                            <i class="fas fa-check-circle"></i> Sucesso
                                        </span>
                                    @else
                                        <span class="text-red-600 text-xs">
                                            <i class="fas fa-times-circle"></i> Erro
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="text-xs text-gray-600 mb-2">
                                    <i class="fas fa-user mr-1"></i>{{ $item['executed_by'] }}
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-clock mr-1"></i>{{ \Carbon\Carbon::parse($item['executed_at'])->diffForHumans() }}
                                </div>
                                
                                @if($item['output'])
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-blue-600 hover:text-blue-800">
                                            Ver output
                                        </summary>
                                        <pre class="mt-2 p-2 bg-gray-900 text-green-400 rounded text-xs overflow-auto">{{ $item['output'] }}</pre>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Info Card --}}
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-md p-6 mt-6 text-white">
                <h3 class="text-lg font-bold mb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Informações
                </h3>
                <ul class="text-sm space-y-2">
                    <li><i class="fas fa-check mr-2"></i>Comandos executam em tempo real</li>
                    <li><i class="fas fa-check mr-2"></i>Output é exibido no terminal</li>
                    <li><i class="fas fa-check mr-2"></i>Histórico guarda últimas 50 execuções</li>
                    <li><i class="fas fa-check mr-2"></i>Logs salvos em storage/logs</li>
                </ul>
            </div>
        </div>
    </div>
</div>
