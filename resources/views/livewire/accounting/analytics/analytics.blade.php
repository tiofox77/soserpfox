<div class="p-6">
    <div class="mb-6 bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-chart-pie mr-3"></i>
            Analítica Avançada
        </h1>
        <p class="text-pink-100 mt-2">Dimensões e tags analíticas personalizadas</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Dimensions --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-pink-50 to-purple-50 border-b flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900"><i class="fas fa-layer-group mr-2"></i>Dimensões</h2>
                <button wire:click="$set('showDimensionModal', true)" class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700">
                    <i class="fas fa-plus mr-2"></i>Nova
                </button>
            </div>
            <div class="p-6">
                @foreach($dimensions as $dimension)
                <div class="p-4 bg-gray-50 rounded-lg mb-3 hover:bg-gray-100 cursor-pointer" wire:click="selectDimension({{ $dimension->id }})">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-gray-900">{{ $dimension->name }}</p>
                            <p class="text-sm text-gray-600">{{ $dimension->code }} 
                                @if($dimension->is_mandatory) <span class="text-red-600">*</span> @endif
                            </p>
                        </div>
                        <div class="text-gray-600">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Tags --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-indigo-50 border-b flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900"><i class="fas fa-tags mr-2"></i>Tags</h2>
                <button wire:click="$set('showTagModal', true)" @if(!$selectedDimensionId) disabled @endif 
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50">
                    <i class="fas fa-plus mr-2"></i>Nova
                </button>
            </div>
            <div class="p-6">
                @if($selectedDimensionId)
                    @foreach($tags as $tag)
                    <div class="inline-block m-1">
                        <span class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-full text-sm font-semibold">
                            {{ $tag->code }}
                            <i class="fas fa-edit ml-2 cursor-pointer" wire:click="editTag({{ $tag->id }})"></i>
                        </span>
                    </div>
                    @endforeach
                @else
                    <p class="text-center text-gray-500 py-8">Selecione uma dimensão para ver as tags</p>
                @endif
            </div>
        </div>
    </div>

    @if($showDimensionModal) @include('livewire.accounting.analytics.partials.dimension-modal') @endif
    @if($showTagModal) @include('livewire.accounting.analytics.partials.tag-modal') @endif
</div>
