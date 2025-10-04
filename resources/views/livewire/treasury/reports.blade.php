<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center">
            <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                <i class="fas fa-file-invoice-dollar text-3xl"></i>
            </div>
            <div>
                <h2 class="text-3xl font-bold">Relatórios Financeiros</h2>
                <p class="text-purple-100 text-sm mt-1">Análises detalhadas e demonstrativos</p>
            </div>
        </div>
    </div>

    {{-- Report Type Tabs --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="flex flex-wrap gap-3 mb-6">
            <button wire:click="$set('reportType', 'cash_flow')" 
                    class="px-6 py-3 rounded-lg font-bold transition {{ $reportType === 'cash_flow' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <i class="fas fa-chart-line mr-2"></i>Fluxo de Caixa
            </button>
            <button wire:click="$set('reportType', 'dre')" 
                    class="px-6 py-3 rounded-lg font-bold transition {{ $reportType === 'dre' ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <i class="fas fa-calculator mr-2"></i>DRE
            </button>
            <button wire:click="$set('reportType', 'receivables')" 
                    class="px-6 py-3 rounded-lg font-bold transition {{ $reportType === 'receivables' ? 'bg-gradient-to-r from-orange-600 to-amber-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <i class="fas fa-hand-holding-usd mr-2"></i>Contas a Receber
            </button>
            <button wire:click="$set('reportType', 'payables')" 
                    class="px-6 py-3 rounded-lg font-bold transition {{ $reportType === 'payables' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <i class="fas fa-file-invoice mr-2"></i>Contas a Pagar
            </button>
        </div>

        {{-- Filters --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Período</label>
                <select wire:model.live="period" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="today">Hoje</option>
                    <option value="week">Esta Semana</option>
                    <option value="month">Este Mês</option>
                    <option value="year">Este Ano</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Data Início</label>
                <input type="date" wire:model.live="startDate" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Data Fim</label>
                <input type="date" wire:model.live="endDate" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="flex items-end">
                <button wire:click="$refresh" class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-bold transition">
                    <i class="fas fa-sync-alt mr-2"></i>Atualizar
                </button>
            </div>
        </div>
    </div>

    {{-- Report Content --}}
    @if($reportType === 'cash_flow')
        @include('livewire.treasury.reports.cash-flow')
    @elseif($reportType === 'dre')
        @include('livewire.treasury.reports.dre')
    @elseif($reportType === 'receivables')
        @include('livewire.treasury.reports.receivables')
    @elseif($reportType === 'payables')
        @include('livewire.treasury.reports.payables')
    @endif
</div>
