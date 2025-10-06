<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
            <i class="fas fa-certificate mr-3 text-blue-600"></i>
            Gerador de Documentos AGT Angola
        </h1>
        <p class="text-gray-600 mt-2">Gere documentos de teste conforme Decreto Presidencial 312/18</p>
    </div>

    {{-- Status dos Recursos --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 border-2 {{ $hasClientWithNIF ? 'border-green-200' : 'border-yellow-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-700">Cliente com NIF</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $hasClientWithNIF ? 'Disponível' : 'Será criado' }}</div>
                </div>
                <i class="fas {{ $hasClientWithNIF ? 'fa-check-circle text-green-500' : 'fa-plus-circle text-yellow-500' }} text-2xl"></i>
            </div>
        </div>

        <div class="bg-white rounded-xl p-4 border-2 {{ $hasClientWithoutNIF ? 'border-green-200' : 'border-yellow-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-700">Cliente sem NIF</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $hasClientWithoutNIF ? 'Disponível' : 'Será criado' }}</div>
                </div>
                <i class="fas {{ $hasClientWithoutNIF ? 'fa-check-circle text-green-500' : 'fa-plus-circle text-yellow-500' }} text-2xl"></i>
            </div>
        </div>

        <div class="bg-white rounded-xl p-4 border-2 {{ $hasProducts ? 'border-green-200' : 'border-yellow-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-700">Produtos</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $hasProducts ? 'Disponíveis' : 'Serão criados' }}</div>
                </div>
                <i class="fas {{ $hasProducts ? 'fa-check-circle text-green-500' : 'fa-plus-circle text-yellow-500' }} text-2xl"></i>
            </div>
        </div>

        <div class="bg-white rounded-xl p-4 border-2 {{ $hasWarehouse ? 'border-green-200' : 'border-yellow-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-700">Armazém</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $hasWarehouse ? 'Disponível' : 'Será criado' }}</div>
                </div>
                <i class="fas {{ $hasWarehouse ? 'fa-check-circle text-green-500' : 'fa-plus-circle text-yellow-500' }} text-2xl"></i>
            </div>
        </div>
    </div>

    {{-- Seleção de Documentos --}}
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-900">
                <i class="fas fa-file-alt mr-2 text-purple-600"></i>
                Selecione os Documentos
            </h2>
            <button wire:click="toggleAll" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                {{ count($selectedDocuments) === 17 ? 'Desmarcar' : 'Selecionar' }} Todos
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($documentTypes as $number => $name)
            <label class="flex items-start p-4 border-2 rounded-xl cursor-pointer {{ in_array($number, $selectedDocuments) ? 'bg-blue-50 border-blue-500' : 'border-gray-200' }}">
                <input type="checkbox" wire:model="selectedDocuments" value="{{ $number }}" class="mt-1 w-5 h-5">
                <div class="ml-3 flex-1">
                    <div class="font-semibold">{{ $number }}. {{ $name }}</div>
                </div>
            </label>
            @endforeach
        </div>

        <div class="mt-6 flex justify-between">
            <div class="text-sm">
                <span class="font-semibold">{{ count($selectedDocuments) }}</span> de 17 selecionados
            </div>
            <button wire:click="generateDocuments" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl">
                Gerar Documentos
            </button>
        </div>
    </div>

    {{-- Loading & Logs --}}
    @if($isGenerating || count($logs) > 0)
    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl p-6 mb-6 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                @if($isGenerating)
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mr-3"></div>
                @else
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                @endif
                {{ $isGenerating ? 'Processando...' : 'Concluído!' }}
            </h2>
            <span class="text-sm text-gray-600">{{ $currentStep }}</span>
        </div>
        
        {{-- Progress Bar --}}
        @if($isGenerating)
        <div class="mb-4">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-700 font-semibold">Progresso</span>
                <span class="text-blue-600 font-bold">{{ round($progressPercentage) }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-3 rounded-full transition-all duration-500 shadow-lg" 
                     style="width: {{ $progressPercentage }}%"></div>
            </div>
        </div>
        @endif
        
        {{-- Logs Console --}}
        <div class="bg-gray-900 rounded-xl p-4 max-h-96 overflow-y-auto font-mono text-sm">
            @forelse($logs as $log)
                <div class="flex items-start mb-2 {{ $log['type'] === 'error' ? 'text-red-400' : ($log['type'] === 'success' ? 'text-green-400' : 'text-gray-300') }}">
                    <span class="text-gray-500 mr-3 flex-shrink-0">[{{ $log['time'] }}]</span>
                    <span class="flex-1">{{ $log['message'] }}</span>
                </div>
            @empty
                <div class="text-gray-500 text-center py-4">
                    <i class="fas fa-hourglass-start mr-2"></i>
                    Aguardando início...
                </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Gerados --}}
    @if(count($generatedDocuments) > 0)
    <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-xl font-bold mb-4">Documentos Gerados ({{ count($generatedDocuments) }})</h2>
        <table class="w-full">
            <thead>
                <tr class="border-b-2">
                    <th class="px-4 py-3 text-left">Nº</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Número</th>
                    <th class="px-4 py-3 text-left">Hash</th>
                    <th class="px-4 py-3 text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($generatedDocuments as $doc)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $doc['number'] }}</td>
                    <td class="px-4 py-3">{{ $doc['type'] }}</td>
                    <td class="px-4 py-3">{{ $doc['doc_number'] }}</td>
                    <td class="px-4 py-3"><code>{{ $doc['hash'] }}</code></td>
                    <td class="px-4 py-3 text-center">
                        @if(isset($doc['is_proforma']) && $doc['is_proforma'])
                            {{-- Proforma --}}
                            <a href="{{ route('invoicing.sales.proformas.preview', $doc['id']) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition shadow-sm">
                                <i class="fas fa-eye mr-2"></i>
                                Ver Proforma
                            </a>
                        @elseif(($doc['document_category'] ?? '') === 'credit_note')
                            {{-- Nota de Crédito --}}
                            <a href="{{ route('invoicing.credit-notes.preview', $doc['id']) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition shadow-sm">
                                <i class="fas fa-eye mr-2"></i>
                                Ver NC
                            </a>
                        @elseif(($doc['document_category'] ?? '') === 'debit_note')
                            {{-- Nota de Débito --}}
                            <a href="{{ route('invoicing.debit-notes.preview', $doc['id']) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition shadow-sm">
                                <i class="fas fa-eye mr-2"></i>
                                Ver ND
                            </a>
                        @else
                            {{-- Fatura --}}
                            <a href="{{ route('invoicing.sales.invoices.preview', $doc['id']) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-sm">
                                <i class="fas fa-eye mr-2"></i>
                                Ver Fatura
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
