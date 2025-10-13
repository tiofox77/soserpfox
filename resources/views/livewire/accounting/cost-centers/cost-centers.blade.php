<div class="p-6">
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-sitemap mr-3"></i>
            Centros de Custo
        </h1>
        <p class="text-orange-100 mt-2">Estrutura hierárquica de centros de custo</p>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Hierarquia</h2>
            <button wire:click="$set('showModal', true)" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                <i class="fas fa-plus mr-2"></i>Novo Centro
            </button>
        </div>

        <div class="space-y-2">
            @foreach($costCenters as $center)
            <div class="p-4 bg-gradient-to-r {{ $center->type === 'revenue' ? 'from-green-50 to-emerald-50' : ($center->type === 'cost' ? 'from-red-50 to-orange-50' : 'from-blue-50 to-cyan-50') }} rounded-lg border-l-4 {{ $center->type === 'revenue' ? 'border-green-600' : ($center->type === 'cost' ? 'border-red-600' : 'border-blue-600') }}">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-circle text-xs {{ $center->type === 'revenue' ? 'text-green-600' : ($center->type === 'cost' ? 'text-red-600' : 'text-blue-600') }}"></i>
                        <div>
                            <p class="font-bold text-gray-900">{{ $center->code }} - {{ $center->name }}</p>
                            <p class="text-sm text-gray-600">Tipo: {{ ucfirst($center->type) }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="edit({{ $center->id }})" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                
                {{-- Children --}}
                @if($center->children && $center->children->count() > 0)
                <div class="ml-8 mt-3 space-y-2">
                    @foreach($center->children as $child)
                    <div class="p-3 bg-white rounded-lg border border-gray-200">
                        <div class="flex justify-between items-center">
                            <p class="text-sm font-semibold text-gray-700"><i class="fas fa-level-up-alt fa-rotate-90 mr-2"></i>{{ $child->code }} - {{ $child->name }}</p>
                            <button wire:click="edit({{ $child->id }})" class="text-blue-600 hover:text-blue-900 text-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    @if($showModal) @include('livewire.accounting.cost-centers.partials.form-modal') @endif
</div>
