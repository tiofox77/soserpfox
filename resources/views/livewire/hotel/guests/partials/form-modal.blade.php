{{-- Modal Formulario --}}
@if($showModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl m-4 max-h-[90vh] overflow-hidden flex flex-col">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-plus text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ $editingId ? 'Editar Hospede' : 'Novo Hospede' }}</h3>
                        <p class="text-purple-100 text-sm">Preencha os dados do hospede</p>
                    </div>
                </div>
                <button wire:click="$set('showModal', false)" class="text-white/80 hover:text-white p-2 hover:bg-white/20 rounded-lg transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="p-6 overflow-y-auto flex-1">
            <form wire:submit="save" class="space-y-6">
                {{-- Dados Pessoais --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-user text-purple-500"></i> Dados Pessoais
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nome Completo *</label>
                            <input type="text" wire:model="name" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="Nome do hospede">
                            @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                            <input type="email" wire:model="email" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="email@exemplo.com">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Telefone</label>
                            <input type="text" wire:model="phone" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="+244 9XX XXX XXX">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Data de Nascimento</label>
                            <input type="date" wire:model="birth_date" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Genero</label>
                            <select wire:model="gender" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                <option value="">Selecione...</option>
                                <option value="male">Masculino</option>
                                <option value="female">Feminino</option>
                                <option value="other">Outro</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Documentos --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-id-card text-purple-500"></i> Documentos
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo de Documento</label>
                            <select wire:model="document_type" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                <option value="bi">Bilhete de Identidade</option>
                                <option value="passport">Passaporte</option>
                                <option value="other">Outro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Numero do Documento</label>
                            <input type="text" wire:model="document_number" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="Numero">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">NIF</label>
                            <input type="text" wire:model="nif" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="NIF">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nacionalidade</label>
                            <select wire:model="nationality" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                @foreach(\App\Models\Client::PAISES as $pais)
                                    <option value="{{ $pais }}">{{ $pais }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Endereco --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-purple-500"></i> Endereco
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Endereco</label>
                            <input type="text" wire:model="address" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="Rua, numero, bairro">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Cidade</label>
                            <input type="text" wire:model="city" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="Cidade">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Pais</label>
                            <select wire:model="country" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                @foreach(\App\Models\Client::PAISES as $pais)
                                    <option value="{{ $pais }}">{{ $pais }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Status Hotel --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-star text-purple-500"></i> Status no Hotel
                    </h4>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" wire:model="is_vip" class="w-5 h-5 text-yellow-500 rounded border-gray-300 focus:ring-yellow-500">
                            <span class="flex items-center gap-2">
                                <i class="fas fa-crown text-yellow-500"></i>
                                <span class="font-medium text-gray-700">Cliente VIP</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" wire:model="is_blacklisted" class="w-5 h-5 text-red-500 rounded border-gray-300 focus:ring-red-500">
                            <span class="flex items-center gap-2">
                                <i class="fas fa-ban text-red-500"></i>
                                <span class="font-medium text-gray-700">Lista Negra</span>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Notas --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Notas</label>
                    <textarea wire:model="notes" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="Observacoes sobre o hospede..."></textarea>
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 border-t flex justify-end gap-3">
            <button wire:click="$set('showModal', false)" type="button" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-semibold">
                Cancelar
            </button>
            <button wire:click="save" wire:loading.attr="disabled" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar' }}
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
</div>
@endif
