<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-percent mr-3 text-green-600"></i>
                    Gestão de Impostos (IVA)
                </h1>
                <p class="text-gray-600 mt-2">Configure os impostos conforme legislação angolana</p>
            </div>
            <button wire:click="openCreateModal" 
                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-xl font-bold transition shadow-lg">
                <i class="fas fa-plus mr-2"></i>
                Novo Imposto
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Pesquisar</label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                       placeholder="🔍 Buscar por nome ou código...">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Filtrar por Tipo</label>
                <select wire:model.live="filterType" 
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
                    <option value="">Todos os tipos</option>
                    <option value="iva">IVA</option>
                    <option value="irt">IRT</option>
                    <option value="other">Outro</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Taxes List --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gradient-to-r from-green-600 to-green-700 text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-code mr-1"></i>Código
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-tag mr-1"></i>Nome
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-percentage mr-1"></i>Taxa
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-file-code mr-1"></i>SAFT
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-toggle-on mr-1"></i>Status
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-cog mr-1"></i>Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($taxes as $tax)
                    <tr class="hover:bg-green-50 transition-all duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-lg font-bold text-green-600">{{ $tax->code }}</span>
                            @if($tax->is_default)
                                <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-[10px] font-bold rounded">PADRÃO</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-gray-900">{{ $tax->name }}</div>
                            @if($tax->description)
                                <div class="text-xs text-gray-500">{{ Str::limit($tax->description, 50) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-lg font-bold rounded">
                                {{ $tax->rate }}%
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded">
                                {{ $tax->saft_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($tax->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i>Ativo
                                </span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i>Inativo
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center space-x-2">
                                <button wire:click="editTax({{ $tax->id }})" 
                                        class="p-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-percent text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg font-semibold">Nenhum imposto encontrado</p>
                                <p class="text-gray-400 text-sm mt-2">Execute o seeder para criar os impostos de Angola</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($taxes->hasPages())
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $taxes->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Create/Edit --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas {{ $isEdit ? 'fa-edit' : 'fa-plus' }} mr-2"></i>
                    {{ $isEdit ? 'Editar Imposto' : 'Novo Imposto' }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="save" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Código *</label>
                        <input type="text" wire:model="code" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                               placeholder="Ex: IVA14">
                        @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Taxa (%) *</label>
                        <input type="number" step="0.01" wire:model="rate" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
                        @error('rate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome *</label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                           placeholder="Ex: IVA 14% Normal">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                    <textarea wire:model="description" rows="2"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo SAFT *</label>
                        <select wire:model="saft_type" 
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
                            <option value="NOR">NOR - Normal</option>
                            <option value="RED">RED - Reduzida</option>
                            <option value="ISE">ISE - Isento</option>
                            <option value="NS">NS - Não Sujeito</option>
                            <option value="OUT">OUT - Outro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo *</label>
                        <select wire:model="type" 
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
                            <option value="iva">IVA</option>
                            <option value="irt">IRT</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2 p-4 bg-gray-50 rounded-xl">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_default" 
                               class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <span class="ml-3 text-sm font-semibold text-gray-700">Imposto padrão</span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" 
                               class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <span class="ml-3 text-sm font-semibold text-gray-700">Ativo</span>
                    </label>
                </div>

                <div class="flex space-x-3 pt-4">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <x-loading-button 
                        action="saveTax" 
                        icon="save" 
                        color="green"
                        class="flex-1 px-6 py-3">
                        Salvar
                    </x-loading-button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Toastr Notifications --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Ação realizada';
                
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "timeOut": "3000",
                    };
                    
                    toastr[type](message);
                }
            });
        });
    </script>
</div>
