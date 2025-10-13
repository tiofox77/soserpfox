<div class="p-6">
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-coins mr-3"></i>
            Gestão de Moedas
        </h1>
        <p class="text-green-100 mt-2">Multi-moeda e taxas de câmbio</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Moedas</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $currencies->count() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Cadastradas</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-coins text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Taxas Câmbio</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $exchangeRates->count() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Registadas</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-emerald-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Moedas Ativas</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $currencies->where('is_active', true)->count() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Em uso</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Última Taxa</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $exchangeRates->first()->rate ?? '-' }}</p>
                    <p class="text-xs text-gray-500 mt-1">Mais recente</p>
                </div>
                <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-cyan-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Currencies --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900"><i class="fas fa-money-bill-wave mr-2"></i>Moedas</h2>
                <button wire:click="$set('showCurrencyModal', true)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Nova
                </button>
            </div>
            <div class="p-6">
                @foreach($currencies as $currency)
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-3 hover:bg-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center text-white font-bold">
                            {{ $currency->code }}
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ $currency->name }}</p>
                            <p class="text-sm text-gray-600">{{ $currency->symbol }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="editCurrency({{ $currency->id }})" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Exchange Rates --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900"><i class="fas fa-exchange-alt mr-2"></i>Taxas Câmbio</h2>
                <button wire:click="$set('showRateModal', true)" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Nova
                </button>
            </div>
            <div class="p-6">
                @foreach($exchangeRates as $rate)
                <div class="p-4 bg-gray-50 rounded-lg mb-3">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-gray-900">{{ $rate->currencyFrom->code }} → {{ $rate->currencyTo->code }}</p>
                            <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($rate->date)->format('d/m/Y') }}</p>
                        </div>
                        <p class="text-xl font-bold text-blue-600">{{ number_format($rate->rate, 4) }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    @if($showCurrencyModal) @include('livewire.accounting.currencies.partials.currency-modal') @endif
    @if($showRateModal) @include('livewire.accounting.currencies.partials.rate-modal') @endif
</div>
