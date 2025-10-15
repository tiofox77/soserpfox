<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-hand-holding-usd mr-3 text-blue-600"></i>
                    Adiantamentos Salariais
                </h2>
                <p class="text-gray-600 mt-1">Gestão de adiantamentos de salário para funcionários</p>
            </div>
            <button wire:click="create" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Novo Adiantamento
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg animate-fade-in">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $advances->total() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Pendentes</p>
                    <p class="text-2xl font-bold mt-1">{{ $advances->where('status', 'pending')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Aprovados</p>
                    <p class="text-2xl font-bold mt-1">{{ $advances->where('status', 'approved')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-200 text-xs font-medium">Pagos</p>
                    <p class="text-2xl font-bold mt-1">{{ $advances->where('status', 'paid')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-xs font-medium">Rejeitados</p>
                    <p class="text-2xl font-bold mt-1">{{ $advances->where('status', 'rejected')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-times-circle text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="🔍 Pesquisar nº ou funcionário..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">🏷️ Todos Status</option>
                    <option value="pending">Pendente</option>
                    <option value="approved">Aprovado</option>
                    <option value="rejected">Rejeitado</option>
                    <option value="paid">Pago</option>
                    <option value="completed">Completado</option>
                </select>
            </div>
            <div>
                <select wire:model.live="employeeFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">👤 Todos Funcionários</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Grid de Adiantamentos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($advances as $advance)
            <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                <!-- Header do Card -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold">{{ $advance->employee->full_name }}</h3>
                            <p class="text-sm text-blue-100">{{ $advance->advance_number }}</p>
                        </div>
                        @if($advance->status === 'pending')
                            <span class="px-3 py-1 bg-yellow-500 rounded-full text-xs font-semibold animate-pulse">Pendente</span>
                        @elseif($advance->status === 'approved')
                            <span class="px-3 py-1 bg-green-500 rounded-full text-xs font-semibold">✓ Aprovado</span>
                        @elseif($advance->status === 'rejected')
                            <span class="px-3 py-1 bg-red-500 rounded-full text-xs font-semibold">✗ Rejeitado</span>
                        @elseif($advance->status === 'paid')
                            <span class="px-3 py-1 bg-emerald-500 rounded-full text-xs font-semibold">💰 Pago</span>
                        @else
                            <span class="px-3 py-1 bg-gray-500 rounded-full text-xs font-semibold">Completado</span>
                        @endif
                    </div>
                </div>

                <!-- Corpo do Card -->
                <div class="p-5">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b">
                            <span class="text-sm text-gray-600 flex items-center">
                                <i class="fas fa-money-bill-wave mr-2 text-green-600"></i> Valor Solicitado:
                            </span>
                            <span class="font-bold text-green-600">{{ number_format($advance->requested_amount, 2, ',', '.') }} Kz</span>
                        </div>
                        @if($advance->status === 'approved' || $advance->status === 'paid' || $advance->status === 'completed')
                            <div class="flex items-center justify-between pb-2 border-b">
                                <span class="text-sm text-gray-600 flex items-center">
                                    <i class="fas fa-check-double mr-2 text-blue-600"></i> Valor Aprovado:
                                </span>
                                <span class="font-bold text-blue-600">{{ number_format($advance->approved_amount, 2, ',', '.') }} Kz</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between pb-2 border-b">
                            <span class="text-sm text-gray-600 flex items-center">
                                <i class="fas fa-calendar-alt mr-2 text-purple-600"></i> Parcelas:
                            </span>
                            <span class="font-semibold text-gray-800">{{ $advance->installments }}x</span>
                        </div>
                        @if($advance->status === 'approved' || $advance->status === 'paid' || $advance->status === 'completed')
                            <div class="flex items-center justify-between pb-2 border-b">
                                <span class="text-sm text-gray-600 flex items-center">
                                    <i class="fas fa-calculator mr-2 text-orange-600"></i> Por Parcela:
                                </span>
                                <span class="font-semibold text-orange-600">{{ number_format($advance->installment_amount, 2, ',', '.') }} Kz</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between pt-2 bg-gray-50 p-3 rounded-lg">
                            <span class="text-xs text-gray-600">
                                <i class="fas fa-calendar mr-1"></i> {{ $advance->created_at->format('d/m/Y') }}
                            </span>
                            @if($advance->approved_by)
                                <span class="text-xs text-gray-600">
                                    <i class="fas fa-user-check mr-1"></i> {{ $advance->approvedBy->name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Ações -->
                    <div class="mt-5 space-y-2">
                        @if($advance->status === 'pending')
                            <div class="flex gap-2">
                                <button wire:click="openApprovalModal({{ $advance->id }}, 'approve')" 
                                        class="flex-1 px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-semibold transition transform hover:scale-105 shadow">
                                    <i class="fas fa-check mr-1"></i>Aprovar
                                </button>
                                <button wire:click="openApprovalModal({{ $advance->id }}, 'reject')" 
                                        class="flex-1 px-4 py-2 bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 text-white rounded-lg font-semibold transition transform hover:scale-105 shadow">
                                    <i class="fas fa-times mr-1"></i>Rejeitar
                                </button>
                            </div>
                        @elseif($advance->status === 'approved')
                            <button wire:click="markAsPaid({{ $advance->id }})" 
                                    class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-lg font-semibold transition transform hover:scale-105 shadow">
                                <i class="fas fa-money-bill-wave mr-2"></i>Marcar como Pago
                            </button>
                        @endif
                        
                        <button wire:click="viewDetails({{ $advance->id }})" 
                                class="w-full px-4 py-2 bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 rounded-lg font-semibold transition">
                            <i class="fas fa-eye mr-2"></i>Ver Detalhes
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3">
                <div class="text-center py-12 bg-white rounded-xl shadow-md">
                    <i class="fas fa-hand-holding-usd text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-600 mb-2">Nenhum adiantamento encontrado</h3>
                    <p class="text-gray-500 mb-4">Crie um novo adiantamento salarial para começar</p>
                    <button wire:click="create" 
                            class="px-6 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg font-semibold transition">
                        <i class="fas fa-plus mr-2"></i>Novo Adiantamento
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $advances->links() }}
    </div>

    {{-- Modals --}}
    @include('livewire.hr.advances.partials.form-modal')
    @include('livewire.hr.advances.partials.approval-modal')
    @include('livewire.hr.advances.partials.rejection-modal')
    @include('livewire.hr.advances.partials.details-modal')
    
    {{-- Animações --}}
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fade-in 0.5s ease-out;
        }
        
        @keyframes slide-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .grid > div {
            animation: slide-up 0.4s ease-out;
            animation-fill-mode: both;
        }
        
        .grid > div:nth-child(1) { animation-delay: 0.1s; }
        .grid > div:nth-child(2) { animation-delay: 0.2s; }
        .grid > div:nth-child(3) { animation-delay: 0.3s; }
        .grid > div:nth-child(4) { animation-delay: 0.4s; }
        .grid > div:nth-child(5) { animation-delay: 0.5s; }
        .grid > div:nth-child(6) { animation-delay: 0.6s; }
    </style>
</div>
