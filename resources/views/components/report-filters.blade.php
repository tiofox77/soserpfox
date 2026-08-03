@props(['color' => 'indigo', 'title' => 'Filtros', 'extra' => null])

<div class="bg-white rounded-2xl shadow-md p-5 mb-6 border-l-4 border-{{ $color }}-500">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-800 flex items-center">
            <i class="fas fa-filter mr-2 text-{{ $color }}-600"></i>{{ $title }}
        </h3>
        <button onclick="window.print()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
            <i class="fas fa-print mr-1"></i>Imprimir
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Período</label>
            <select wire:model.live="period" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-{{ $color }}-500">
                <option value="today">Hoje</option>
                <option value="week">Esta Semana</option>
                <option value="month">Este Mês</option>
                <option value="quarter">Este Trimestre</option>
                <option value="year">Este Ano</option>
                <option value="custom">Personalizado</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">De</label>
            <input wire:model.live="dateFrom" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-{{ $color }}-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Até</label>
            <input wire:model.live="dateTo" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-{{ $color }}-500">
        </div>
        @if($extra)
            <div>{{ $extra }}</div>
        @endif
    </div>
</div>
