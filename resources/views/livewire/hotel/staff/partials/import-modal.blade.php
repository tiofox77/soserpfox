{{-- Modal Importar do RH --}}
@if($showImportModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showImportModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 m-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-file-import text-blue-600"></i>
                Importar do RH
            </h3>
            <button wire:click="$set('showImportModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <p class="text-sm text-gray-600 mb-4">Selecione os funcionarios do RH que deseja adicionar a equipa do hotel:</p>

        <div class="flex-1 overflow-y-auto max-h-[50vh] border rounded-xl">
            @if($this->hrEmployees->count() > 0)
                <table class="w-full">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 w-10">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600">Funcionario</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600">Departamento</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600">Cargo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($this->hrEmployees as $employee)
                        <tr class="hover:bg-blue-50 transition">
                            <td class="px-4 py-3">
                                <input type="checkbox" wire:model.live="selectedEmployees" value="{{ $employee->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                                        {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $employee->full_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $employee->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm text-gray-600">{{ $employee->department->name ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm text-gray-600">{{ $employee->position->name ?? '-' }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-3xl text-gray-400"></i>
                    </div>
                    <h4 class="font-bold text-gray-700 mb-1">Nenhum funcionario disponivel</h4>
                    <p class="text-sm text-gray-500">Todos os funcionarios do RH ja foram importados ou nao existem funcionarios ativos.</p>
                </div>
            @endif
        </div>

        @if($this->hrEmployees->count() > 0)
        <div class="mt-4 p-3 bg-blue-50 rounded-xl flex items-center justify-between">
            <span class="text-sm text-blue-700">
                <i class="fas fa-info-circle mr-1"></i>
                {{ count($selectedEmployees) }} funcionario(s) selecionado(s)
            </span>
        </div>
        @endif

        <div class="mt-6 flex justify-end gap-3">
            <button wire:click="$set('showImportModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                Cancelar
            </button>
            @if($this->hrEmployees->count() > 0)
            <button wire:click="importFromHR" wire:loading.attr="disabled" class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="importFromHR">
                    <i class="fas fa-file-import mr-2"></i>Importar Selecionados
                </span>
                <span wire:loading wire:target="importFromHR">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Importando...
                </span>
            </button>
            @endif
        </div>
    </div>
</div>
@endif
