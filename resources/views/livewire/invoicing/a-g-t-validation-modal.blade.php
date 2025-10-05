<div>
    @if($showModal && $document)
    {{-- Modal Conformidade AGT --}}
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:key="agt-modal">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-certificate mr-2"></i>
                    Validação AGT Angola - {{ $document->invoice_number }}
                </h3>
                <button wire:click="close" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-6">
                {{-- Status de Validação --}}
                @if(!empty($validation['errors']))
                <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4">
                    <h4 class="font-bold text-red-900 mb-2 flex items-center">
                        <i class="fas fa-times-circle mr-2"></i>
                        Erros ({{ count($validation['errors']) }})
                    </h4>
                    <ul class="text-sm text-red-800 space-y-1">
                        @foreach($validation['errors'] as $error)
                        <li><i class="fas fa-exclamation-triangle mr-2"></i>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @else
                <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4">
                    <h4 class="font-bold text-green-900 mb-2 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Documento Conforme AGT
                    </h4>
                </div>
                @endif
                
                {{-- Checklist --}}
                <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                    <h4 class="font-bold text-blue-900 mb-3 flex items-center">
                        <i class="fas fa-tasks mr-2"></i>
                        Checklist de Conformidade
                    </h4>
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer p-2 rounded hover:bg-blue-100">
                            <input type="checkbox" wire:model="checks.hash" class="w-5 h-5 mr-3">
                            <span class="text-sm">Hash presente: <code>{{ substr($document->hash ?? '', 0, 4) }}</code></span>
                        </label>
                        <label class="flex items-center cursor-pointer p-2 rounded hover:bg-blue-100">
                            <input type="checkbox" wire:model="checks.footer_message" class="w-5 h-5 mr-3">
                            <span class="text-sm">Mensagem AGT visível</span>
                        </label>
                        <label class="flex items-center cursor-pointer p-2 rounded hover:bg-blue-100">
                            <input type="checkbox" wire:model="checks.period" class="w-5 h-5 mr-3">
                            <span class="text-sm">Período: {{ $document->invoice_date->format('Y-m') }}</span>
                        </label>
                        <label class="flex items-center cursor-pointer p-2 rounded hover:bg-blue-100">
                            <input type="checkbox" wire:model="checks.totals" class="w-5 h-5 mr-3">
                            <span class="text-sm">Totais corretos</span>
                        </label>
                        <label class="flex items-center cursor-pointer p-2 rounded hover:bg-blue-100">
                            <input type="checkbox" wire:model="checks.client" class="w-5 h-5 mr-3">
                            <span class="text-sm">Cliente identificado</span>
                        </label>
                    </div>
                </div>
                
                {{-- Categoria --}}
                <div>
                    <label class="block font-semibold mb-2">Categoria de Teste AGT</label>
                    <select wire:model="agt_category" class="w-full px-4 py-3 border-2 rounded-xl">
                        <option value="">Selecione...</option>
                        @foreach($testCategories as $code => $name)
                            <option value="{{ $code }}">{{ $code }}. {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                
                {{-- Observações --}}
                <div>
                    <label class="block font-semibold mb-2">Observações</label>
                    <textarea wire:model="agt_notes" rows="3" class="w-full px-4 py-3 border-2 rounded-xl"></textarea>
                </div>
            </div>
            
            {{-- Actions --}}
            <div class="px-6 pb-6 flex gap-3">
                <button wire:click="close" class="flex-1 px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-xl">
                    Cancelar
                </button>
                <button wire:click="markAsCompliant" class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl">
                    <i class="fas fa-check-circle mr-2"></i>
                    Marcar como Conforme
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
