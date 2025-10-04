<div class="p-6">
    {{-- Header Vermelho --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-circle-plus mr-3 text-red-600"></i>
                    Notas de Débito
                </h2>
                <p class="text-gray-600 mt-1">Juros, multas e cobranças adicionais</p>
            </div>
            <a href="{{ route('invoicing.debit-notes.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Nota de Débito
            </a>
        </div>
    </div>

    {{-- Stats Cards Vermelhos --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-circle-plus text-xl"></i>
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

        <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-rose-200 text-xs font-medium">Emitidas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['issued'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="text-orange-200 text-xs">AOA</p>
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
                <option value="paid">Paga</option>
                <option value="cancelled">Cancelada</option>
            </select>
            <select wire:model.live="filterReason" class="rounded-lg border-gray-300">
                <option value="">Todos os Motivos</option>
                <option value="interest">Juros</option>
                <option value="penalty">Multa</option>
                <option value="additional_charge">Cobrança Adicional</option>
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
            Sistema de Notas de Débito
        </div>
    </div>

    <style>
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scale-in { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .animate-fade-in { animation: fade-in 0.2s ease-out; }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
    </style>
</div>
