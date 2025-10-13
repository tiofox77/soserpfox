<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-calendar-alt mr-3 text-yellow-600"></i>
                    Gestão de Períodos Contabilísticos
                </h1>
                <p class="text-gray-600 mt-1">Fechar e reabrir períodos mensais</p>
            </div>
            <div>
                <select wire:model.live="year" class="px-4 py-2 border border-gray-300 rounded-lg">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif
    
    @if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">
        <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
    </div>
    @endif

    {{-- Stats Cards --}}
    @include('livewire.accounting.periods.partials.stats-cards')

    {{-- Periods Table --}}
    @include('livewire.accounting.periods.partials.periods-table')

    {{-- Close Modal --}}
    @if($showCloseModal)
    @include('livewire.accounting.periods.partials.close-modal')
    @endif

    {{-- Reopen Modal --}}
    @if($showReopenModal)
    @include('livewire.accounting.periods.partials.reopen-modal')
    @endif
</div>
