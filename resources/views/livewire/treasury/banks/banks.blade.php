<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-university text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Bancos</h2>
                    <p class="text-blue-100 text-sm">Cadastro de bancos disponíveis</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Banco
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-university text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Total Bancos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $banks->total() }}</p>
            <p class="text-xs text-gray-500">Bancos cadastrados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $activeCount }}</p>
            <p class="text-xs text-gray-500">Bancos ativos</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <input wire:model.live="search" type="text" placeholder="Pesquisar banco..." 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            </div>
            <div>
                <select wire:model.live="perPage" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500">
                    <option value="10">10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Banco</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">SWIFT</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">País</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($banks as $bank)
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($bank->logo_url)
                                        <img src="{{ $bank->logo_url }}" alt="{{ $bank->name }}" class="w-10 h-10 rounded-lg mr-3 object-contain">
                                    @else
                                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-university text-blue-600"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $bank->name }}</p>
                                        @if($bank->website)
                                            <a href="{{ $bank->website }}" target="_blank" class="text-xs text-blue-500 hover:underline">
                                                {{ $bank->website }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">
                                    {{ $bank->code }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $bank->swift_code ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $bank->country }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $bank->id }})" 
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                                        {{ $bank->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $bank->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="edit({{ $bank->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $bank->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="fas fa-university text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhum banco encontrado</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $banks->links() }}
        </div>
    </div>

    @include('livewire.treasury.banks.partials.form-modal')
    @include('livewire.treasury.banks.partials.delete-modal')
</div>
