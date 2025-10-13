{{-- Stats Cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600 hover:shadow-xl transition transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Reconciliações</p>
                <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Todas</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-list text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-600 hover:shadow-xl transition transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Pendentes</p>
                <p class="text-3xl font-bold text-gray-900">{{ $stats['pending'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">A processar</p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600 hover:shadow-xl transition transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Reconciliadas</p>
                <p class="text-3xl font-bold text-gray-900">{{ $stats['reconciled'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Confirmadas</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-600 hover:shadow-xl transition transform hover:-translate-y-1">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Diferenças</p>
                <p class="text-3xl font-bold text-gray-900">{{ $stats['differences'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Com divergências</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>
