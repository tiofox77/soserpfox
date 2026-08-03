{{-- Modal Seleção de Cliente --}}
@if($showClientModal)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">Selecionar Cliente</h3>
            <div class="flex items-center gap-2">
                <button wire:click="openQuickClientModal" type="button"
                        class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg transition">
                    <i class="fas fa-user-plus"></i> Novo Cliente
                </button>
                <button wire:click="$set('showClientModal', false)"
                        class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <div class="p-6">
            {{-- Busca --}}
            <div class="mb-4 flex gap-2">
                <input type="text" wire:model.live.debounce.300ms="searchClient"
                       placeholder="🔍 Buscar cliente por nome ou NIF..."
                       class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                <button wire:click="openQuickClientModal" type="button"
                        class="px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-semibold transition whitespace-nowrap">
                    <i class="fas fa-plus mr-1"></i> Novo
                </button>
            </div>

            {{-- Lista de Clientes --}}
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($clients as $client)
                <button wire:click="selectClient({{ $client->id }})" 
                        class="w-full p-4 bg-gray-50 hover:bg-indigo-50 rounded-xl text-left transition border-2 border-transparent hover:border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-bold text-gray-900">{{ $client->name }}</p>
                            <p class="text-sm text-gray-600">NIF: {{ $client->nif }}</p>
                            @if($client->phone)
                            <p class="text-xs text-gray-500">Tel: {{ $client->phone }}</p>
                            @endif
                        </div>
                        <i class="fas fa-chevron-right text-indigo-600"></i>
                    </div>
                </button>
                @empty
                <div class="text-center py-8">
                    <i class="fas fa-user-slash text-4xl text-gray-300 mb-2"></i>
                    <p class="text-gray-500">Nenhum cliente encontrado</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif
