{{-- Modal Visualização --}}
@if($showViewModal && $viewingDocumentType)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showViewModal') }" x-show="show" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="$wire.closeViewModal()"></div>
        
        <div class="inline-block w-full max-w-3xl bg-white shadow-2xl rounded-2xl relative z-10">
            {{-- Header --}}
            <div class="px-6 py-4 bg-gradient-to-r from-cyan-600 to-cyan-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white">Detalhes do Tipo de Documento</h3>
                    <button wire:click="closeViewModal" class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <span class="text-xs font-medium text-gray-500">Código</span>
                        <p class="text-lg font-semibold text-gray-900">{{ $viewingDocumentType->code }}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <span class="text-xs font-medium text-gray-500">Diário</span>
                        <p class="text-lg font-semibold text-gray-900">{{ $viewingDocumentType->journal->name ?? '-' }}</p>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-xl">
                    <span class="text-xs font-medium text-gray-500">Descrição</span>
                    <p class="text-lg text-gray-900">{{ $viewingDocumentType->description }}</p>
                </div>

                <div class="grid grid-cols-4 gap-2">
                    <div class="bg-gray-50 p-3 rounded-xl text-center">
                        <span class="text-xs text-gray-500">Recapitulativos</span>
                        <p class="mt-1"><span class="px-2 py-1 rounded-full text-xs font-medium {{ $viewingDocumentType->recapitulativos ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800' }}">{{ $viewingDocumentType->recapitulativos ? 'Sim' : 'Não' }}</span></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-xl text-center">
                        <span class="text-xs text-gray-500">Retenção Fonte</span>
                        <p class="mt-1"><span class="px-2 py-1 rounded-full text-xs font-medium {{ $viewingDocumentType->retencao_fonte ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-800' }}">{{ $viewingDocumentType->retencao_fonte ? 'Sim' : 'Não' }}</span></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-xl text-center">
                        <span class="text-xs text-gray-500">Bal. Financeira</span>
                        <p class="mt-1"><span class="px-2 py-1 rounded-full text-xs font-medium {{ $viewingDocumentType->bal_financeira ? 'bg-purple-100 text-purple-800' : 'bg-gray-200 text-gray-800' }}">{{ $viewingDocumentType->bal_financeira ? 'Sim' : 'Não' }}</span></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-xl text-center">
                        <span class="text-xs text-gray-500">Bal. Analítica</span>
                        <p class="mt-1"><span class="px-2 py-1 rounded-full text-xs font-medium {{ $viewingDocumentType->bal_analitica ? 'bg-orange-100 text-orange-800' : 'bg-gray-200 text-gray-800' }}">{{ $viewingDocumentType->bal_analitica ? 'Sim' : 'Não' }}</span></p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 flex justify-end">
                <button wire:click="closeViewModal" class="px-6 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-semibold rounded-xl">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
