<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-pink-500 to-purple-500 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-calendar-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Agendamentos</h2>
                    <p class="text-pink-100 text-sm">Gestão de agendamentos do salão</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- View Mode Toggle -->
                <div class="flex items-center gap-1 bg-white/20 rounded-xl p-1">
                    <button wire:click="setViewMode('list')" class="px-4 py-2 rounded-lg font-semibold transition {{ $viewMode === 'list' ? 'bg-white text-pink-600' : 'text-white hover:bg-white/20' }}">
                        <i class="fas fa-list mr-2"></i>Lista
                    </button>
                    <button wire:click="setViewMode('calendar')" class="px-4 py-2 rounded-lg font-semibold transition {{ $viewMode === 'calendar' ? 'bg-white text-pink-600' : 'text-white hover:bg-white/20' }}">
                        <i class="fas fa-calendar-alt mr-2"></i>Calendário
                    </button>
                </div>
                <button wire:click="openModal" class="bg-white text-pink-600 hover:bg-pink-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-plus mr-2"></i>Novo Agendamento
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        <!-- Hoje -->
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-pink-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg shadow-pink-500/30">
                    <i class="fas fa-calendar-day text-white"></i>
                </div>
                <p class="text-xs text-pink-600 font-semibold uppercase">Hoje</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $totalToday }}</p>
        </div>

        <!-- Pendentes -->
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-clock text-white"></i>
                </div>
                <p class="text-xs text-blue-600 font-semibold uppercase">Pendentes</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $totalPending }}</p>
        </div>

        <!-- Online -->
        <div wire:click="$set('sourceFilter', 'online')" 
             class="bg-white rounded-2xl shadow-lg p-5 border cursor-pointer transition hover:shadow-xl {{ $sourceFilter === 'online' ? 'border-purple-500 ring-2 ring-purple-500' : 'border-purple-100' }}">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <i class="fas fa-globe text-white"></i>
                </div>
                <p class="text-xs text-purple-600 font-semibold uppercase">Online</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $totalOnline }}</p>
            <p class="text-xs text-gray-400 mt-1">Agendados pelo site</p>
        </div>

        <!-- Sistema -->
        <div wire:click="$set('sourceFilter', 'system')" 
             class="bg-white rounded-2xl shadow-lg p-5 border cursor-pointer transition hover:shadow-xl {{ $sourceFilter === 'system' ? 'border-cyan-500 ring-2 ring-cyan-500' : 'border-cyan-100' }}">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-teal-600 rounded-xl flex items-center justify-center shadow-lg shadow-cyan-500/30">
                    <i class="fas fa-desktop text-white"></i>
                </div>
                <p class="text-xs text-cyan-600 font-semibold uppercase">Sistema</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $totalSystem }}</p>
            <p class="text-xs text-gray-400 mt-1">Agendados internamente</p>
        </div>

        <!-- Concluídos Mês -->
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30">
                    <i class="fas fa-check-circle text-white"></i>
                </div>
                <p class="text-xs text-green-600 font-semibold uppercase">Concluídos</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $totalCompletedMonth }}</p>
        </div>

        <!-- Receita Mês -->
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-amber-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/30">
                    <i class="fas fa-coins text-white"></i>
                </div>
                <p class="text-xs text-amber-600 font-semibold uppercase">Receita</p>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totalRevenue, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-1">Kz este mês</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-pink-600"></i>
                Filtros
            </h3>
            @if($search || $statusFilter || $dateFilter || $professionalFilter || $sourceFilter)
                <button wire:click="clearFilters" class="text-sm text-pink-600 hover:text-pink-700 font-semibold">
                    <i class="fas fa-times mr-1"></i>Limpar Filtros
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Nome do cliente..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-flag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Salon\Appointment::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Data -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar mr-1"></i>Período
                </label>
                <select wire:model.live="dateFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    <option value="today">Hoje</option>
                    <option value="tomorrow">Amanhã</option>
                    <option value="week">Próximos 7 dias</option>
                </select>
            </div>

            <!-- Profissional -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user-tie mr-1"></i>Profissional
                </label>
                <select wire:model.live="professionalFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach($professionals as $prof)
                        <option value="{{ $prof->id }}">{{ $prof->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Fonte -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-share-alt mr-1"></i>Origem
                </label>
                <select wire:model.live="sourceFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    <option value="online">🌐 Online (Site/App)</option>
                    <option value="system">🖥️ Sistema (Interno)</option>
                    <optgroup label="Específico">
                        @foreach($sources as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
        </div>
    </div>

    @if($viewMode === 'calendar')
        <!-- Calendar View -->
        @include('livewire.salon.appointments.partials.calendar')
    @else
        <!-- List View -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-pink-600"></i>
                    Lista de Agendamentos
                </h3>
                <div class="flex items-center gap-4">
                    <select wire:model.live="perPage" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-pink-500">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm text-gray-600 font-semibold">
                        <i class="fas fa-calendar-check mr-1"></i>{{ $appointments->total() }} Total
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-2 flex items-center">
                <i class="fas fa-calendar mr-2 text-pink-500"></i>Data/Hora
            </div>
            <div class="col-span-3 flex items-center">
                <i class="fas fa-user mr-2 text-blue-500"></i>Cliente
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-user-tie mr-2 text-purple-500"></i>Profissional
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-spa mr-2 text-green-500"></i>Serviços
            </div>
            <div class="col-span-1 flex items-center">
                <i class="fas fa-coins mr-2 text-yellow-500"></i>Valor
            </div>
            <div class="col-span-1 flex items-center">
                <i class="fas fa-flag mr-2 text-cyan-500"></i>Status
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($appointments as $appointment)
                <div class="group grid grid-cols-1 md:grid-cols-12 gap-4 px-6 py-4 hover:bg-pink-50 transition-all duration-300 items-center">
                    <!-- Data/Hora -->
                    <div class="col-span-2 flex items-center gap-3">
                        <div class="text-center bg-gradient-to-br from-pink-500 to-purple-600 text-white rounded-xl p-2 min-w-[60px] shadow-lg">
                            <p class="text-lg font-bold">{{ $appointment->date->format('d') }}</p>
                            <p class="text-xs uppercase">{{ $appointment->date->locale('pt')->shortMonthName }}</p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }}</p>
                            <p class="text-xs text-gray-500">{{ $appointment->total_duration }}min</p>
                        </div>
                    </div>
                    
                    <!-- Cliente -->
                    <div class="col-span-3 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            {{ strtoupper(substr($appointment->client->name ?? 'C', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $appointment->client->name ?? 'Cliente Avulso' }}</p>
                            <p class="text-xs text-gray-500">{{ $appointment->client->phone ?? '' }}</p>
                        </div>
                    </div>
                    
                    <!-- Profissional -->
                    <div class="col-span-2">
                        <p class="text-sm font-semibold text-gray-700">{{ $appointment->professional->display_name }}</p>
                        <p class="text-xs text-gray-500">{{ $appointment->professional->specialization ?? '' }}</p>
                    </div>
                    
                    <!-- Serviços -->
                    <div class="col-span-2">
                        <div class="flex flex-wrap gap-1">
                            @foreach($appointment->services->take(2) as $svc)
                                <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs font-semibold">{{ Str::limit($svc->service->name, 12) }}</span>
                            @endforeach
                            @if($appointment->services->count() > 2)
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">+{{ $appointment->services->count() - 2 }}</span>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Valor -->
                    <div class="col-span-1">
                        <p class="font-bold text-pink-600">{{ number_format($appointment->total, 0, ',', '.') }}</p>
                        <p class="text-xs {{ $appointment->payment_status === 'paid' ? 'text-green-600' : 'text-orange-600' }}">
                            {{ $appointment->payment_status === 'paid' ? 'Pago' : 'Pendente' }}
                        </p>
                    </div>
                    
                    <!-- Status + Fonte -->
                    <div class="col-span-1">
                        @php
                            $statusColors = [
                                'scheduled' => 'bg-blue-100 text-blue-700',
                                'confirmed' => 'bg-indigo-100 text-indigo-700',
                                'arrived' => 'bg-purple-100 text-purple-700',
                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                'no_show' => 'bg-gray-100 text-gray-700',
                            ];
                            $sourceColors = [
                                'walk_in' => 'bg-gray-50 text-gray-600 border-gray-200',
                                'phone' => 'bg-blue-50 text-blue-600 border-blue-200',
                                'whatsapp' => 'bg-green-50 text-green-600 border-green-200',
                                'website' => 'bg-purple-50 text-purple-600 border-purple-200',
                                'app' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                                'instagram' => 'bg-pink-50 text-pink-600 border-pink-200',
                                'system' => 'bg-cyan-50 text-cyan-600 border-cyan-200',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold {{ $statusColors[$appointment->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $appointment->status_label }}
                        </span>
                        {{-- Badge de Fonte --}}
                        <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[10px] font-medium {{ $sourceColors[$appointment->source] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}"
                              title="{{ $appointment->is_online_booking ? 'Agendamento Online' : 'Agendamento pelo Sistema' }}">
                            <i class="{{ $appointment->source_icon }}"></i>
                            {{ $appointment->source_label }}
                        </span>
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="view({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Ver">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        
                        @if($appointment->status === 'scheduled')
                            <button wire:click="confirm({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Confirmar">
                                <i class="fas fa-check text-xs"></i>
                            </button>
                        @endif

                        @if($appointment->status === 'confirmed')
                            <button wire:click="markArrived({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Chegou">
                                <i class="fas fa-user-check text-xs"></i>
                            </button>
                        @endif

                        @if(in_array($appointment->status, ['confirmed', 'arrived']))
                            <button wire:click="start({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Iniciar">
                                <i class="fas fa-play text-xs"></i>
                            </button>
                        @endif

                        @if($appointment->status === 'in_progress')
                            <button wire:click="complete({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Concluir">
                                <i class="fas fa-check-circle text-xs"></i>
                            </button>
                        @endif

                        @if(in_array($appointment->status, ['scheduled', 'confirmed']))
                            <button wire:click="openModal({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button wire:click="openDeleteModal({{ $appointment->id }})" class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Cancelar">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-alt text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum agendamento encontrado</h3>
                    <p class="text-gray-500 mb-4">Crie um novo agendamento para começar</p>
                </div>
            @endforelse
        </div>

        @if($appointments->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $appointments->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- Modals -->
    @include('livewire.salon.appointments.partials.form-modal')
    @include('livewire.salon.appointments.partials.view-modal')
    @include('livewire.salon.appointments.partials.delete-modal')
    @include('livewire.salon.appointments.partials.client-modal')

    <!-- Toastr Notifications -->
    @include('livewire.salon.partials.toastr-notifications')
</div>
