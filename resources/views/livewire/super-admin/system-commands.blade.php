<div x-data="{ activeTab: 'commands' }" class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-terminal mr-3"></i>
                    Comandos & Seeders
                </h2>
                <p class="text-indigo-100 mt-1 text-sm">Execute comandos artisan e seeders atraves da interface</p>
            </div>
            <div class="flex gap-2">
                @if($output)
                    <button wire:click="clearOutput" class="px-4 py-2 bg-white/20 text-white rounded-xl hover:bg-white/30 transition text-sm">
                        <i class="fas fa-eraser mr-1"></i>Limpar Output
                    </button>
                @endif
                @if(!empty($executionHistory))
                    <button wire:click="clearHistory" wire:confirm="Tem certeza que deseja limpar o historico?" class="px-4 py-2 bg-red-500/80 text-white rounded-xl hover:bg-red-500 transition text-sm">
                        <i class="fas fa-trash mr-1"></i>Limpar Historico
                    </button>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-2 mt-5">
            <button @click="activeTab = 'commands'"
                    :class="activeTab === 'commands' ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/20 text-white hover:bg-white/30'"
                    class="px-5 py-2.5 rounded-xl font-semibold text-sm transition">
                <i class="fas fa-terminal mr-2"></i>Comandos
            </button>
            <button @click="activeTab = 'seeders'"
                    :class="activeTab === 'seeders' ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/20 text-white hover:bg-white/30'"
                    class="px-5 py-2.5 rounded-xl font-semibold text-sm transition">
                <i class="fas fa-seedling mr-2"></i>Seeders
                <span class="ml-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">{{ count($availableSeeders) }}</span>
            </button>
            <button @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/20 text-white hover:bg-white/30'"
                    class="px-5 py-2.5 rounded-xl font-semibold text-sm transition">
                <i class="fas fa-history mr-2"></i>Historico
                @if(!empty($executionHistory))
                    <span class="ml-1 px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full">{{ count($executionHistory) }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- Output Terminal (always visible when has content) --}}
    @if($output)
        <div class="bg-gray-900 rounded-2xl shadow-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-terminal mr-2 text-green-400"></i>
                    Terminal Output
                </h3>
                <button wire:click="clearOutput" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="bg-black rounded-xl p-4 font-mono text-sm overflow-auto" style="max-height: 400px;">
                <div class="text-green-400">{!! $output !!}</div>
            </div>
        </div>
    @endif

    {{-- TAB: COMANDOS --}}
    <div x-show="activeTab === 'commands'" x-cloak>
        @php
            $groupedCommands = collect($commands)->groupBy('group', preserveKeys: true);
        @endphp

        @foreach($groupedCommands as $groupName => $groupCommands)
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-700 mb-3 flex items-center">
                    @switch($groupName)
                        @case('Cache & Performance')
                            <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                            @break
                        @case('Base de Dados')
                            <i class="fas fa-database mr-2 text-red-500"></i>
                            @break
                        @case('Módulos & Planos')
                            <i class="fas fa-puzzle-piece mr-2 text-blue-500"></i>
                            @break
                        @case('Segurança')
                            <i class="fas fa-shield-alt mr-2 text-teal-500"></i>
                            @break
                        @case('Deploy')
                            <i class="fas fa-rocket mr-2 text-rose-500"></i>
                            @break
                        @default
                            <i class="fas fa-cog mr-2 text-gray-500"></i>
                    @endswitch
                    {{ $groupName }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($groupCommands as $key => $command)
                        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-5 hover:shadow-lg transition">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 rounded-xl bg-{{ $command['color'] }}-100 flex items-center justify-center mr-3 shrink-0">
                                    <i class="fas fa-{{ $command['icon'] }} text-{{ $command['color'] }}-600"></i>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-gray-800 text-sm">{{ $command['name'] }}</h4>
                                    <code class="text-[10px] text-gray-400">{{ $command['command'] }}</code>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mb-3 leading-relaxed">{{ $command['description'] }}</p>

                            @if(!empty($command['params']) && count(array_filter($command['params'], fn($p) => ($p['type'] ?? '') !== 'hidden')) > 0)
                                <div class="space-y-2 mb-3 p-3 bg-gray-50 rounded-xl">
                                    @foreach($command['params'] as $paramKey => $param)
                                        @if(($param['type'] ?? '') === 'hidden') @continue @endif
                                        <div>
                                            <label class="text-[10px] text-gray-500 font-semibold uppercase">{{ $param['label'] }}</label>
                                            @if($param['type'] === 'select')
                                                <select wire:model.defer="commandParams.{{ $key }}.{{ $paramKey }}"
                                                        class="w-full text-xs border-gray-200 rounded-lg mt-0.5 py-1.5">
                                                    <option value="">-- Selecione --</option>
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
                                                <label class="flex items-center mt-1">
                                                    <input type="checkbox" wire:model.defer="commandParams.{{ $key }}.{{ $paramKey }}"
                                                           class="rounded border-gray-300 text-{{ $command['color'] }}-600 mr-2">
                                                    <span class="text-xs text-gray-600">Activar</span>
                                                </label>
                                            @else
                                                <input type="text" wire:model.defer="commandParams.{{ $key }}.{{ $paramKey }}"
                                                       class="w-full text-xs border-gray-200 rounded-lg mt-0.5 py-1.5"
                                                       placeholder="{{ $param['label'] }}">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <button wire:click="runCommand('{{ $key }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="runCommand('{{ $key }}')"
                                    class="w-full px-4 py-2 bg-{{ $command['color'] }}-600 text-white rounded-xl hover:bg-{{ $command['color'] }}-700 transition text-sm font-semibold disabled:opacity-50">
                                <span wire:loading.remove wire:target="runCommand('{{ $key }}')">
                                    <i class="fas fa-play mr-1"></i>Executar
                                </span>
                                <span wire:loading wire:target="runCommand('{{ $key }}')">
                                    <i class="fas fa-spinner fa-spin mr-1"></i>A executar...
                                </span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- TAB: SEEDERS --}}
    <div x-show="activeTab === 'seeders'" x-cloak>
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-seedling mr-2 text-green-600"></i>
                    Seeders do Sistema
                </h3>
                <span class="text-sm text-gray-500">{{ count($availableSeeders) }} seeders encontrados</span>
            </div>

            {{-- Status Banner --}}
            <div class="grid grid-cols-3 gap-4 mb-5">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-blue-700">{{ $seederStats['total'] }}</p>
                    <p class="text-xs text-blue-600 font-semibold">Total</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-green-700">{{ $seederStats['executed'] }}</p>
                    <p class="text-xs text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>Executados</p>
                </div>
                <div class="{{ $seederStats['pending'] > 0 ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200' }} border rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold {{ $seederStats['pending'] > 0 ? 'text-yellow-700' : 'text-gray-400' }}">{{ $seederStats['pending'] }}</p>
                    <p class="text-xs {{ $seederStats['pending'] > 0 ? 'text-yellow-600' : 'text-gray-400' }} font-semibold"><i class="fas fa-clock mr-1"></i>Pendentes</p>
                </div>
            </div>

            {{-- Category Filter --}}
            <div class="flex flex-wrap gap-2 mb-5">
                @foreach($seederCategories as $catKey => $catLabel)
                    <button wire:click="$set('selectedSeederCategory', '{{ $catKey }}')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition
                            {{ $selectedSeederCategory === $catKey ? 'bg-green-600 text-white shadow' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        @if($catKey === 'all')
                            <i class="fas fa-globe mr-1"></i>
                        @elseif($catKey === 'Geral')
                            <i class="fas fa-folder mr-1"></i>
                        @else
                            <i class="fas fa-folder-open mr-1"></i>
                        @endif
                        {{ $catLabel }}
                        <span class="ml-1 opacity-70">
                            ({{ $catKey === 'all' ? count($availableSeeders) : count(array_filter($availableSeeders, fn($s) => $s['category'] === $catKey)) }})
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Seeder Selection & Run --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Selecionar Seeder</label>
                    <select wire:model="selectedSeeder" class="w-full px-4 py-2.5 text-sm border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-400 focus:border-green-400">
                        <option value="">-- Escolha um seeder para executar --</option>
                        @php $currentCat = ''; @endphp
                        @foreach($this->filteredSeeders as $seeder)
                            @if($seeder['category'] !== $currentCat)
                                @if($currentCat !== '')
                                    </optgroup>
                                @endif
                                <optgroup label="{{ $seeder['category'] }}">
                                @php $currentCat = $seeder['category']; @endphp
                            @endif
                            <option value="{{ $seeder['namespace'] }}">{{ $seeder['name'] }} ({{ $seeder['class'] }})</option>
                        @endforeach
                        @if($currentCat !== '')
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div class="flex items-end">
                    <button wire:click="runSeeder"
                            wire:loading.attr="disabled"
                            wire:target="runSeeder"
                            @if(!$selectedSeeder) disabled @endif
                            class="w-full px-6 py-2.5 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition disabled:opacity-40 disabled:cursor-not-allowed text-sm">
                        <span wire:loading.remove wire:target="runSeeder">
                            <i class="fas fa-play mr-2"></i>Executar
                        </span>
                        <span wire:loading wire:target="runSeeder">
                            <i class="fas fa-spinner fa-spin mr-2"></i>A executar...
                        </span>
                    </button>
                </div>
            </div>

            {{-- Seeder List Table --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Categoria</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Seeder</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Classe</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-32">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-28">Accao</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->filteredSeeders as $seeder)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-1 text-[10px] font-semibold rounded-lg
                                        {{ $seeder['category'] === 'Geral' ? 'bg-blue-100 text-blue-700' :
                                           ($seeder['category'] === 'Accounting' ? 'bg-emerald-100 text-emerald-700' :
                                           ($seeder['category'] === 'HR' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700')) }}">
                                        {{ $seeder['category'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ $seeder['name'] }}</td>
                                <td class="px-4 py-2.5">
                                    <code class="text-xs text-gray-500">{{ $seeder['class'] }}</code>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if($seeder['executed'])
                                        <span class="inline-flex items-center px-2.5 py-1 text-[10px] font-bold rounded-full bg-green-100 text-green-700" title="{{ $seeder['executed_at'] }}">
                                            <i class="fas fa-check-circle mr-1"></i>Executado
                                        </span>
                                        <p class="text-[9px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($seeder['executed_at'])->format('d/m H:i') }}</p>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-[10px] font-bold rounded-full bg-yellow-100 text-yellow-700">
                                            <i class="fas fa-clock mr-1"></i>Pendente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button wire:click="$set('selectedSeeder', '{{ $seeder['namespace'] }}')"
                                            class="px-3 py-1 text-xs font-semibold rounded-lg transition
                                            {{ $selectedSeeder === $seeder['namespace'] ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-green-100 hover:text-green-700' }}">
                                        @if($selectedSeeder === $seeder['namespace'])
                                            <i class="fas fa-check mr-1"></i>Selecionado
                                        @else
                                            <i class="fas fa-hand-pointer mr-1"></i>Selecionar
                                        @endif
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p>Nenhum seeder nesta categoria</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(count($availableSeeders) === 0)
                <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl">
                    <p class="font-semibold text-sm">Nenhum seeder encontrado</p>
                    <p class="text-xs mt-1">Crie seeders na pasta database/seeders/</p>
                </div>
            @endif
        </div>
    </div>

    {{-- TAB: HISTORICO --}}
    <div x-show="activeTab === 'history'" x-cloak>
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-history mr-2 text-purple-600"></i>
                Historico de Execucoes
            </h3>

            @if(empty($executionHistory))
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-inbox text-5xl mb-3"></i>
                    <p class="text-lg font-medium">Nenhuma execucao registrada</p>
                    <p class="text-sm">Comece executando um comando ou seeder</p>
                </div>
            @else
                <div class="space-y-3 max-h-[600px] overflow-y-auto">
                    @foreach($executionHistory as $item)
                        <div class="border-l-4 {{ $item['success'] ? 'border-green-500' : 'border-red-500' }} bg-gray-50 p-4 rounded-r-xl">
                            <div class="flex items-start justify-between mb-1">
                                <h4 class="font-semibold text-sm text-gray-800">{{ $item['command_name'] }}</h4>
                                @if($item['success'])
                                    <span class="text-green-600 text-xs font-semibold">
                                        <i class="fas fa-check-circle"></i> Sucesso
                                    </span>
                                @else
                                    <span class="text-red-600 text-xs font-semibold">
                                        <i class="fas fa-times-circle"></i> Erro
                                    </span>
                                @endif
                            </div>

                            <div class="text-xs text-gray-500 mb-2">
                                <i class="fas fa-user mr-1"></i>{{ $item['executed_by'] }}
                                <span class="mx-2">|</span>
                                <i class="fas fa-clock mr-1"></i>{{ \Carbon\Carbon::parse($item['executed_at'])->diffForHumans() }}
                                <span class="mx-2">|</span>
                                {{ \Carbon\Carbon::parse($item['executed_at'])->format('d/m/Y H:i') }}
                            </div>

                            @if($item['output'])
                                <details class="text-xs">
                                    <summary class="cursor-pointer text-blue-600 hover:text-blue-800 font-semibold">
                                        <i class="fas fa-chevron-right mr-1"></i>Ver output
                                    </summary>
                                    <pre class="mt-2 p-3 bg-gray-900 text-green-400 rounded-lg text-xs overflow-auto max-h-40">{{ $item['output'] }}</pre>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
