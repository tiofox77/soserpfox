<div>
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-tags text-green-500"></i>
                Gestão de Tarifas
            </h1>
            <p class="text-gray-500 dark:text-gray-400">Preços dinâmicos por época, dia da semana e datas especiais</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="openSeasonModal" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Nova Temporada
            </button>
            <button wire:click="openWeekdayModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-calendar-week"></i> Dia da Semana
            </button>
            <button wire:click="openSpecialModal" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition flex items-center gap-2">
                <i class="fas fa-star"></i> Data Especial
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center gap-2">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex -mb-px">
                <button wire:click="setTab('seasons')" 
                        class="px-6 py-4 text-sm font-medium border-b-2 transition
                               {{ $activeTab === 'seasons' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-sun mr-2"></i>Temporadas
                </button>
                <button wire:click="setTab('calendar')" 
                        class="px-6 py-4 text-sm font-medium border-b-2 transition
                               {{ $activeTab === 'calendar' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-calendar-alt mr-2"></i>Calendário de Preços
                </button>
                <button wire:click="setTab('special')" 
                        class="px-6 py-4 text-sm font-medium border-b-2 transition
                               {{ $activeTab === 'special' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-star mr-2"></i>Datas Especiais
                </button>
            </nav>
        </div>

        <div class="p-6">
            {{-- Tab: Temporadas --}}
            @if($activeTab === 'seasons')
            <div>
                {{-- Filtros --}}
                <div class="flex gap-4 mb-6">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar..."
                           class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 w-64">
                    <select wire:model.live="filterActive" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                        <option value="">Todos</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                    </select>
                </div>

                {{-- Lista de Temporadas --}}
                <div class="space-y-4">
                    @forelse($seasons as $season)
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border-l-4" style="border-color: {{ $season->color }}">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold" style="background: {{ $season->color }}">
                                <i class="fas fa-sun"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                    {{ $season->name }}
                                    @if(!$season->is_active)
                                    <span class="px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full text-xs">Inactiva</span>
                                    @endif
                                </h3>
                                <p class="text-sm text-gray-500">
                                    {{ $season->start_date->format('d/m/Y') }} - {{ $season->end_date->format('d/m/Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-2xl font-bold {{ $season->price_modifier >= 1 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $season->modifier_percentage }}
                                </p>
                                <p class="text-xs text-gray-500">Modificador</p>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="toggleSeasonActive({{ $season->id }})" 
                                        class="p-2 rounded-lg {{ $season->is_active ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }} hover:opacity-75">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button wire:click="openSeasonModal({{ $season->id }})" class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="deleteSeason({{ $season->id }})" wire:confirm="Tem certeza?" class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-sun text-4xl mb-4 opacity-50"></i>
                        <p>Nenhuma temporada configurada</p>
                        <button wire:click="openSeasonModal" class="mt-4 text-indigo-600 hover:underline">Criar primeira temporada</button>
                    </div>
                    @endforelse
                </div>

                {{ $seasons->links() }}
            </div>
            @endif

            {{-- Tab: Calendário de Preços --}}
            @if($activeTab === 'calendar')
            <div>
                <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Preços do Mês Atual</h3>
                
                @foreach($calendarData as $roomTypeId => $data)
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-bold text-gray-800 dark:text-white">{{ $data['name'] }}</span>
                        <span class="text-sm text-gray-500">(Base: {{ number_format($data['base_price'], 0, ',', '.') }} Kz)</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $day)
                        <div class="text-center text-xs text-gray-500 font-medium py-1">{{ $day }}</div>
                        @endforeach
                        
                        @php
                            $firstDay = now()->startOfMonth();
                            $padding = $firstDay->dayOfWeek;
                        @endphp
                        
                        @for($i = 0; $i < $padding; $i++)
                        <div></div>
                        @endfor
                        
                        @foreach($data['prices'] as $date => $price)
                        @php
                            $isToday = $date === now()->format('Y-m-d');
                            $diff = $price - $data['base_price'];
                            $color = $diff > 0 ? 'bg-red-50 text-red-700' : ($diff < 0 ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-700');
                        @endphp
                        <div class="p-2 rounded-lg text-center {{ $color }} {{ $isToday ? 'ring-2 ring-indigo-500' : '' }}">
                            <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($date)->format('d') }}</p>
                            <p class="font-bold text-sm">{{ number_format($price/1000, 0) }}k</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach

                <div class="flex gap-4 mt-4 text-sm">
                    <span class="flex items-center gap-1"><span class="w-4 h-4 bg-red-50 rounded"></span> Acima do base</span>
                    <span class="flex items-center gap-1"><span class="w-4 h-4 bg-gray-50 rounded border"></span> Preço base</span>
                    <span class="flex items-center gap-1"><span class="w-4 h-4 bg-green-50 rounded"></span> Abaixo do base</span>
                </div>
            </div>
            @endif

            {{-- Tab: Datas Especiais --}}
            @if($activeTab === 'special')
            <div>
                <div class="space-y-3">
                    @forelse($specialRates as $rate)
                    <div class="flex items-center justify-between p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-800 rounded-full flex items-center justify-center">
                                <i class="fas fa-star text-amber-600"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 dark:text-white">{{ \Carbon\Carbon::parse($rate->date)->format('d/m/Y') }}</p>
                                <p class="text-sm text-gray-500">{{ $rate->reason ?: 'Sem motivo' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <p class="text-xl font-bold text-amber-600">{{ number_format($rate->price, 0, ',', '.') }} Kz</p>
                            <button wire:click="deleteSpecialRate({{ $rate->id }})" wire:confirm="Eliminar?" class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-star text-4xl mb-4 opacity-50"></i>
                        <p>Nenhuma data especial configurada</p>
                        <button wire:click="openSpecialModal" class="mt-4 text-amber-600 hover:underline">Adicionar data especial</button>
                    </div>
                    @endforelse
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Modal: Temporada --}}
    @if($showSeasonModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg mx-4">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                    {{ $editingSeasonId ? 'Editar Temporada' : 'Nova Temporada' }}
                </h3>
                <button wire:click="$set('showSeasonModal', false)" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-1">Nome</label>
                        <input type="text" wire:model="seasonName" placeholder="Ex: Época Alta" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        @error('seasonName')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Data Início</label>
                        <input type="date" wire:model="seasonStartDate" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Data Fim</label>
                        <input type="date" wire:model="seasonEndDate" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo de Modificador</label>
                        <select wire:model="seasonModifierType" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            <option value="multiplier">Multiplicador (ex: 1.5 = +50%)</option>
                            <option value="percentage">Percentagem (ex: 20 = +20%)</option>
                            <option value="fixed">Valor Fixo (Kz)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Valor</label>
                        <input type="number" step="0.01" wire:model="seasonModifier" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cor</label>
                        <input type="color" wire:model="seasonColor" class="w-full h-10 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Prioridade</label>
                        <input type="number" wire:model="seasonPriority" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-1">Descrição</label>
                        <textarea wire:model="seasonDescription" rows="2" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="seasonIsActive" class="rounded">
                            <span>Activa</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t dark:border-gray-700 flex justify-end gap-2">
                <button wire:click="$set('showSeasonModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button wire:click="saveSeason" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Guardar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Dia da Semana --}}
    @if($showWeekdayModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Tarifas por Dia da Semana</h3>
                <button wire:click="$set('showWeekdayModal', false)" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tipo de Quarto</label>
                    <select wire:model.live="selectedRoomTypeId" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        <option value="">Selecione...</option>
                        @foreach($roomTypes as $rt)
                        <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                @if($selectedRoomTypeId)
                <div class="space-y-2">
                    @php $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']; @endphp
                    @foreach($days as $i => $day)
                    <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <span class="font-medium">{{ $day }}</span>
                        <div class="flex items-center gap-2">
                            <input type="number" step="0.01" wire:model="weekdayRates.{{ $i }}" class="w-24 px-3 py-1 border rounded-lg text-center dark:bg-gray-700 dark:border-gray-600">
                            <span class="text-sm text-gray-500">x</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-info-circle"></i> Use 1.0 para preço normal, 1.2 para +20%, 0.8 para -20%
                </p>
                @endif
            </div>
            <div class="px-6 py-4 border-t dark:border-gray-700 flex justify-end gap-2">
                <button wire:click="$set('showWeekdayModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button wire:click="saveWeekdayRates" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Data Especial --}}
    @if($showSpecialModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Tarifa para Data Especial</h3>
                <button wire:click="$set('showSpecialModal', false)" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Data</label>
                    <input type="date" wire:model="specialDate" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tipo de Quarto (opcional)</label>
                    <select wire:model="specialRoomTypeId" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        <option value="">Todos os quartos</option>
                        @foreach($roomTypes as $rt)
                        <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Preço Fixo (Kz)</label>
                    <input type="number" wire:model="specialPrice" placeholder="Ex: 25000" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Motivo</label>
                    <input type="text" wire:model="specialReason" placeholder="Ex: Réveillon, Feriado" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                </div>
            </div>
            <div class="px-6 py-4 border-t dark:border-gray-700 flex justify-end gap-2">
                <button wire:click="$set('showSpecialModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button wire:click="saveSpecialRate" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700">Guardar</button>
            </div>
        </div>
    </div>
    @endif
</div>
