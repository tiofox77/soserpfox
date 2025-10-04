<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Clientes</h2>
                    <p class="text-green-100 text-sm">Gerir clientes</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-green-600 hover:bg-green-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Cliente
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
            <input wire:model.live="search" type="text" placeholder="Pesquisar clientes..." 
                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
        </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-green-600"></i>
                Lista de Clientes ({{ $clients->total() }})
            </h3>
        </div>
        
        <div class="divide-y divide-gray-100">
            @forelse($clients as $client)
                <div class="group p-6 hover:bg-green-50 transition-all">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="text-lg font-bold text-gray-900 mb-1">{{ $client->name }}</h4>
                            <div class="flex flex-wrap gap-3 text-sm text-gray-600">
                                <span><i class="fas fa-id-card text-green-600 mr-1"></i>NIF: {{ $client->nif }}</span>
                                @if($client->email)
                                <span><i class="fas fa-envelope text-blue-600 mr-1"></i>{{ $client->email }}</span>
                                @endif
                                @if($client->phone)
                                <span><i class="fas fa-phone text-purple-600 mr-1"></i>{{ $client->phone }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="edit({{ $client->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition">
                                <i class="fas fa-edit mr-1"></i>Editar
                            </button>
                            <button wire:click="delete({{ $client->id }})" wire:confirm="Tem certeza?" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition">
                                <i class="fas fa-trash mr-1"></i>Excluir
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum cliente encontrado</h3>
                    <p class="text-gray-500 mb-4">Crie um novo cliente para começar</p>
                </div>
            @endforelse
        </div>

        @if($clients->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $clients->links() }}
            </div>
        @endif
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-user mr-3"></i>{{ $editingClientId ? 'Editar' : 'Novo' }} Cliente
                            </h3>
                            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <form wire:submit.prevent="save" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo *</label>
                                <select wire:model="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                    <option value="pessoa_juridica">Pessoa Jurídica</option>
                                    <option value="pessoa_fisica">Pessoa Física</option>
                                </select>
                                @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome *</label>
                                <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">NIF *</label>
                                <input wire:model="nif" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('nif') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone</label>
                                <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Celular</label>
                                <input wire:model="mobile" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Endereço</label>
                                <input wire:model="address" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Cidade</label>
                                <input wire:model="city" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Código Postal</label>
                                <input wire:model="postal_code" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl font-semibold hover:from-green-700 hover:to-emerald-700 shadow-lg hover:shadow-xl transition">
                                <i class="fas {{ $editingClientId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingClientId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
