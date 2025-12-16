{{-- Modal Formulario --}}
@if($showModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 m-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-user-tie text-teal-600"></i>
                {{ $editingId ? 'Editar Funcionario' : 'Novo Funcionario' }}
            </h3>
            <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form wire:submit="save" class="space-y-5">
            {{-- Foto e Nome --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Foto</label>
                    <div class="w-24 h-24 bg-gray-100 rounded-xl overflow-hidden flex items-center justify-center border-2 border-dashed border-gray-300 relative">
                        @if($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                        @elseif($existing_photo)
                            <img src="{{ asset('storage/' . $existing_photo) }}" class="w-full h-full object-cover">
                        @else
                            <i class="fas fa-user text-4xl text-gray-300"></i>
                        @endif
                        <input type="file" wire:model="photo" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>
                </div>
                <div class="flex-1 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-user mr-1 text-teal-500"></i>Nome *</label>
                        <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" placeholder="Nome completo">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-briefcase mr-1 text-blue-500"></i>Cargo *</label>
                            <select wire:model="position" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                                @foreach($positions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-building mr-1 text-purple-500"></i>Departamento *</label>
                            <select wire:model="department" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                                @foreach($departments as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contacto --}}
            <div class="bg-blue-50 rounded-xl p-4">
                <h4 class="font-bold text-blue-700 mb-3 flex items-center"><i class="fas fa-address-card mr-2"></i>Contacto</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
                        <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm" placeholder="email@exemplo.com">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Telefone</label>
                        <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm" placeholder="+244 9XX XXX XXX">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Documento (BI/NIF)</label>
                        <input wire:model="document" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Endereco</label>
                        <input wire:model="address" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>
            </div>

            {{-- Datas --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-birthday-cake mr-1 text-pink-500"></i>Data Nascimento</label>
                    <input wire:model="birth_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-calendar-check mr-1 text-green-500"></i>Data Contratacao</label>
                    <input wire:model="hire_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                </div>
            </div>

            {{-- Horario --}}
            <div class="bg-green-50 rounded-xl p-4">
                <h4 class="font-bold text-green-700 mb-3 flex items-center"><i class="fas fa-clock mr-2"></i>Horario de Trabalho</h4>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Entrada</label>
                        <input wire:model="work_start" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Saida</label>
                        <input wire:model="work_end" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Dias de Trabalho</label>
                    <div class="flex gap-2">
                        @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'] as $i => $day)
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="checkbox" wire:model="working_days" value="{{ $i }}" class="hidden peer">
                            <span class="block py-2 rounded-lg text-xs font-bold peer-checked:bg-green-500 peer-checked:text-white bg-gray-100 text-gray-600 transition">{{ $day }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Salario --}}
            <div class="bg-amber-50 rounded-xl p-4">
                <h4 class="font-bold text-amber-700 mb-3 flex items-center"><i class="fas fa-coins mr-2"></i>Remuneracao</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Salario Mensal (Kz)</label>
                        <input wire:model="monthly_salary" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Taxa Hora (Kz)</label>
                        <input wire:model="hourly_rate" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                </div>
            </div>

            {{-- Notas --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-sticky-note mr-1 text-yellow-500"></i>Notas</label>
                <textarea wire:model="notes" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" placeholder="Observacoes..."></textarea>
            </div>

            {{-- Ativo --}}
            <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                <input wire:model="is_active" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                <label class="text-sm font-medium text-gray-700">Funcionario ativo</label>
            </div>

            {{-- Botoes --}}
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar' }}</span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif
