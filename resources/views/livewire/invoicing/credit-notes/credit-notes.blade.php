<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-circle-minus mr-3 text-green-600"></i>
                    Notas de Crédito
                </h2>
                <p class="text-gray-600 mt-1">Devoluções, descontos e correções</p>
            </div>
            <a href="{{ route('invoicing.credit-notes.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Nota de Crédito
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-circle-minus text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium">Rascunho</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['draft'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-edit text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-200 text-xs font-medium">Emitidas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['issued'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-teal-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="text-teal-200 text-xs">AOA</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-coins text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="text" wire:model.live="search" placeholder="Pesquisar..." class="rounded-lg border-gray-300">
            <select wire:model.live="filterStatus" class="rounded-lg border-gray-300">
                <option value="">Todos os Status</option>
                <option value="draft">Rascunho</option>
                <option value="issued">Emitida</option>
                <option value="cancelled">Cancelada</option>
            </select>
            <select wire:model.live="filterReason" class="rounded-lg border-gray-300">
                <option value="">Todos os Motivos</option>
                <option value="return">Devolução</option>
                <option value="discount">Desconto</option>
                <option value="correction">Correção</option>
                <option value="other">Outro</option>
            </select>
            <input type="date" wire:model.live="filterDateFrom" class="rounded-lg border-gray-300">
            <input type="date" wire:model.live="filterDateTo" class="rounded-lg border-gray-300">
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 text-center text-gray-500">
            Sistema de Notas de Crédito em desenvolvimento...
        </div>
    </div>
</div>
