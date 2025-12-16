@if($showImportModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeImportModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-users-cog mr-3"></i>Importar de RH
                    </h3>
                    <button wire:click="closeImportModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <p class="text-indigo-100 text-sm mt-1">Selecione funcionários do módulo RH para importar como profissionais</p>
            </div>

            <div class="p-6">
                @php
                    $availableEmployees = $this->getAvailableEmployees();
                @endphp

                @if($availableEmployees->count() > 0)
                    <!-- Lista de Funcionários -->
                    <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" 
                                                   wire:click="toggleAllEmployees"
                                                   {{ count($selectedEmployees) === $availableEmployees->count() ? 'checked' : '' }}
                                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                            <span>Todos</span>
                                        </label>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Nome</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Cargo</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Contacto</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($availableEmployees as $employee)
                                    <tr class="hover:bg-indigo-50 transition cursor-pointer">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" 
                                                   wire:model.live="selectedEmployees" 
                                                   value="{{ $employee->id }}"
                                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm">
                                                    {{ strtoupper(substr($employee->first_name ?? $employee->full_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-900">{{ $employee->full_name }}</p>
                                                    <p class="text-xs text-gray-500">{{ $employee->email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-semibold">
                                                {{ $employee->position?->name ?? 'N/D' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm text-gray-600">{{ $employee->phone ?? 'N/D' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Info -->
                    <div class="mt-4 p-3 bg-indigo-50 rounded-xl">
                        <p class="text-sm text-indigo-700 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span><strong>{{ count($selectedEmployees) }}</strong> funcionário(s) selecionado(s) de <strong>{{ $availableEmployees->count() }}</strong> disponível(eis)</span>
                        </p>
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users-slash text-gray-400 text-3xl"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-900 mb-2">Nenhum funcionário disponível</h4>
                        <p class="text-gray-500 text-sm">Todos os funcionários do RH já foram importados ou não há funcionários cadastrados.</p>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button wire:click="closeImportModal" class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-100 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                @if($availableEmployees->count() > 0)
                    <button wire:click="importSelected" 
                            class="px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-xl font-semibold hover:from-indigo-600 hover:to-purple-600 shadow-lg transition disabled:opacity-50"
                            {{ count($selectedEmployees) === 0 ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="importSelected">
                            <i class="fas fa-file-import mr-2"></i>Importar Selecionados
                        </span>
                        <span wire:loading wire:target="importSelected">
                            <i class="fas fa-spinner fa-spin mr-2"></i>A importar...
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
