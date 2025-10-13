<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    {{-- Total de Períodos --}}
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-sm uppercase tracking-wide">Total de Períodos</p>
                <p class="text-4xl font-bold mt-2">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-4">
                <i class="fas fa-calendar text-3xl"></i>
            </div>
        </div>
    </div>

    {{-- Períodos Abertos --}}
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-green-100 text-sm uppercase tracking-wide">Períodos Abertos</p>
                <p class="text-4xl font-bold mt-2">{{ $stats['open'] }}</p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-4">
                <i class="fas fa-lock-open text-3xl"></i>
            </div>
        </div>
    </div>

    {{-- Períodos Fechados --}}
    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-red-100 text-sm uppercase tracking-wide">Períodos Fechados</p>
                <p class="text-4xl font-bold mt-2">{{ $stats['closed'] }}</p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-4">
                <i class="fas fa-lock text-3xl"></i>
            </div>
        </div>
    </div>
</div>
