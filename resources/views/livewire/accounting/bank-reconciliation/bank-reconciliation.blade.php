<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-exchange-alt mr-3"></i>
            Reconciliação Bancária
        </h1>
        <p class="text-blue-100 mt-2">Import e matching automático de extratos bancários</p>
    </div>

    {{-- Stats Cards --}}
    @include('livewire.accounting.bank-reconciliation.partials.stats-cards')

    {{-- Messages --}}
    @if(session()->has('success'))
    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded">
        <p class="text-green-800">{{ session('success') }}</p>
    </div>
    @endif

    @if(session()->has('error'))
    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded">
        <p class="text-red-800">{{ session('error') }}</p>
    </div>
    @endif

    {{-- Import Form --}}
    @include('livewire.accounting.bank-reconciliation.partials.import-form')

    {{-- Reconciliations List --}}
    @include('livewire.accounting.bank-reconciliation.partials.reconciliations-table')
</div>
