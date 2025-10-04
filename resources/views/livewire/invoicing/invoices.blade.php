<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Faturas</h2>
                    <p class="text-blue-100 text-sm">Gerir faturas e documentos</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Fatura
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center space-x-4">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input wire:model.live="search" type="text" placeholder="Pesquisar faturas..." 
                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-filter text-gray-400"></i>
                </div>
                <select wire:model.live="statusFilter" class="pl-11 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white">
                    <option value="">Todos os status</option>
                    <option value="draft">Rascunho</option>
                    <option value="sent">Enviada</option>
                    <option value="paid">Paga</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-blue-600"></i>
                Lista de Faturas ({{ $invoices->total() }})
            </h3>
        </div>
        
        <div class="divide-y divide-gray-100 stagger-animation">
            @forelse($invoices as $invoice)
                <div class="group p-6 hover:bg-blue-50 transition-all duration-300 card-hover">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $invoice->status === 'paid' ? 'from-green-500 to-emerald-600' : ($invoice->status === 'sent' ? 'from-blue-500 to-cyan-600' : ($invoice->status === 'cancelled' ? 'from-red-500 to-red-600' : 'from-gray-500 to-gray-600')) }} flex items-center justify-center shadow-lg flex-shrink-0">
                                <i class="fas fa-file-invoice text-white text-lg"></i>
                            </div>
                            
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <h4 class="text-lg font-bold text-gray-900 mr-3">{{ $invoice->invoice_number }}</h4>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                                        {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : 
                                           ($invoice->status === 'sent' ? 'bg-blue-100 text-blue-700' : 
                                           ($invoice->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $invoice->status === 'paid' ? 'bg-green-500' : ($invoice->status === 'sent' ? 'bg-blue-500' : ($invoice->status === 'cancelled' ? 'bg-red-500' : 'bg-gray-500')) }} mr-1.5"></span>
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-2 text-sm">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center">
                                            <i class="fas fa-user text-purple-600 text-xs"></i>
                                        </span>
                                        <div>
                                            <p class="text-xs text-gray-500 font-medium">Cliente</p>
                                            <p class="text-sm text-gray-900 font-semibold">{{ $invoice->client->name ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center">
                                            <i class="fas fa-calendar text-green-600 text-xs"></i>
                                        </span>
                                        <div>
                                            <p class="text-xs text-gray-500 font-medium">Data</p>
                                            <p class="text-sm text-gray-900">{{ $invoice->invoice_date->format('d/m/Y') }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <span class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center">
                                            <i class="fas fa-money-bill-wave text-orange-600 text-xs"></i>
                                        </span>
                                        <div>
                                            <p class="text-xs text-gray-500 font-medium">Total</p>
                                            <p class="text-sm text-gray-900 font-bold">{{ number_format($invoice->total, 2) }} Kz</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="edit({{ $invoice->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition shadow-md hover:shadow-lg">
                                <i class="fas fa-edit mr-1"></i>Editar
                            </button>
                            <button wire:click="delete({{ $invoice->id }})" wire:confirm="Tem certeza?" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition shadow-md hover:shadow-lg">
                                <i class="fas fa-trash mr-1"></i>Excluir
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma fatura encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova fatura para começar</p>
                </div>
            @endforelse
        </div>

        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-file-invoice mr-3"></i>{{ $editingInvoiceId ? 'Editar' : 'Nova' }} Fatura
                            </h3>
                            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <form wire:submit.prevent="save" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-purple-500 mr-2"></i>Cliente *
                                </label>
                                <select wire:model="client_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                    <option value="">Selecione um cliente</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }} - {{ $client->nif }}</option>
                                    @endforeach
                                </select>
                                @error('client_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag text-blue-500 mr-2"></i>Número *
                                </label>
                                <input wire:model="invoice_number" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                @error('invoice_number') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-flag text-orange-500 mr-2"></i>Status *
                                </label>
                                <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                    <option value="draft">Rascunho</option>
                                    <option value="sent">Enviada</option>
                                    <option value="paid">Paga</option>
                                    <option value="cancelled">Cancelada</option>
                                </select>
                                @error('status') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar text-green-500 mr-2"></i>Data Emissão *
                                </label>
                                <input wire:model="invoice_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('invoice_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar-times text-red-500 mr-2"></i>Data Vencimento *
                                </label>
                                <input wire:model="due_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                                @error('due_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-sticky-note text-gray-500 mr-2"></i>Notas
                                </label>
                                <textarea wire:model="notes" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-cyan-700 shadow-lg hover:shadow-xl transition">
                                <i class="fas {{ $editingInvoiceId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingInvoiceId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
