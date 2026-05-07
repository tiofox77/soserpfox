<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-moon mr-3 text-indigo-600"></i>
                    Horas Extra — Turno Noturno
                </h2>
                <p class="text-gray-600 mt-1">Gestão de subsídio noturno (Art. 102º — Lei nº 7/15)</p>
            </div>
            <button wire:click="create"
                    class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Novo Registo
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-indigo-200 text-xs font-medium">Total Registos</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full"><i class="fas fa-moon text-xl"></i></div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Pendentes</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['pending'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full"><i class="fas fa-clock text-xl"></i></div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Aprovados</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['approved'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full"><i class="fas fa-check-circle text-xl"></i></div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Total Dias</p>
                    <p class="text-2xl font-bold mt-1">{{ (int)$stats['total_days'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full"><i class="fas fa-calendar-day text-xl"></i></div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full"><i class="fas fa-coins text-xl"></i></div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar..."
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <select wire:model.live="employeeFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                <option value="">Todos os Funcionários</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                <option value="">Todos os Estados</option>
                <option value="pending">Pendente</option>
                <option value="approved">Aprovado</option>
                <option value="rejected">Rejeitado</option>
                <option value="paid">Pago</option>
            </select>
            <select wire:model.live="monthFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                <option value="">Todos os Meses</option>
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                @endforeach
            </select>
            <select wire:model.live="yearFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                @for($y = date('Y'); $y >= date('Y') - 3; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nº</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Funcionário</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Dias Noturno</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $record->overtime_number ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-xs font-bold mr-3">
                                        {{ strtoupper(substr($record->employee->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($record->employee->last_name ?? '', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $record->employee->full_name ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">{{ $record->employee->employee_number ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $record->date->format('d/m/Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-semibold">{{ (int)($record->direct_hours ?? 0) }} dias</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                {{ number_format($record->amount ?? $record->total_amount ?? 0, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">{!! $record->status_badge !!}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button wire:click="viewDetails({{ $record->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Detalhes">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                    @if($record->status === 'pending')
                                        <button wire:click="edit({{ $record->id }})" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg" title="Editar">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <button wire:click="approve({{ $record->id }})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="Aprovar">
                                            <i class="fas fa-check text-sm"></i>
                                        </button>
                                        <button wire:click="openRejectionModal({{ $record->id }})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Rejeitar">
                                            <i class="fas fa-times text-sm"></i>
                                        </button>
                                        <button wire:click="delete({{ $record->id }})" wire:confirm="Tem certeza?" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Eliminar">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-moon text-4xl mb-3 block text-gray-300"></i>
                                Nenhum registo de turno noturno encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $records->links() }}</div>
    </div>

    {{-- Form Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-moon mr-2 text-indigo-600"></i>
                    {{ $editMode ? 'Editar Turno Noturno' : 'Novo Turno Noturno' }}
                </h3>
                <button wire:click="closeModal" class="p-2 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Funcionário *</label>
                    <select wire:model.live="employee_id" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        <option value="">Selecionar...</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_number }})</option>
                        @endforeach
                    </select>
                    @error('employee_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mês de Referência *</label>
                        <input wire:model.live="date" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dias Trabalhados à Noite *</label>
                        <input wire:model.live="night_days" type="number" min="1" max="31" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        @error('night_days') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($calculatedAmount > 0)
                    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-700">Taxa Diária:</span>
                            <span class="font-semibold">{{ number_format($dailyRate, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-700">Adicional Noturno:</span>
                            <span class="font-semibold">{{ $nightPercentage }}%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-700">Fórmula:</span>
                            <span class="font-semibold text-xs">{{ number_format($dailyRate, 2, ',', '.') }} × {{ $night_days }} × {{ $nightPercentage }}%</span>
                        </div>
                        <hr class="border-indigo-200">
                        <div class="flex justify-between">
                            <span class="text-indigo-800 font-bold">Subsídio Noturno:</span>
                            <span class="text-lg font-bold text-indigo-900">{{ number_format($calculatedAmount, 2, ',', '.') }} Kz</span>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                    <textarea wire:model="description" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" placeholder="Descrição opcional..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea wire:model="notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" placeholder="Notas adicionais..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Cancelar</button>
                <button wire:click="save" class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold hover:from-indigo-700 hover:to-purple-700 transition">
                    <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Guardar' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Details Modal --}}
    @if($showDetailsModal && $selectedOvertime)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-info-circle mr-2 text-blue-600"></i>Detalhes</h3>
                <button wire:click="closeModal" class="p-2 hover:bg-gray-100 rounded-lg"><i class="fas fa-times text-gray-500"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Funcionário</p>
                        <p class="font-semibold">{{ $selectedOvertime->employee->full_name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Nº</p>
                        <p class="font-semibold">{{ $selectedOvertime->overtime_number ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Data</p>
                        <p class="font-semibold">{{ $selectedOvertime->date->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Estado</p>
                        <p>{!! $selectedOvertime->status_badge !!}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Dias Noturno</p>
                        <p class="font-bold text-lg">{{ (int)($selectedOvertime->direct_hours ?? 0) }} dias</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Valor</p>
                        <p class="font-bold text-lg text-indigo-700">{{ number_format($selectedOvertime->amount ?? $selectedOvertime->total_amount ?? 0, 2, ',', '.') }} Kz</p>
                    </div>
                </div>
                @if($selectedOvertime->description)
                    <div><p class="text-xs text-gray-500">Descrição</p><p class="text-sm">{{ $selectedOvertime->description }}</p></div>
                @endif
                @if($selectedOvertime->approvedBy)
                    <div><p class="text-xs text-gray-500">Aprovado por</p><p class="text-sm">{{ $selectedOvertime->approvedBy->name }} — {{ $selectedOvertime->approved_at?->format('d/m/Y H:i') }}</p></div>
                @endif
                @if($selectedOvertime->rejection_reason)
                    <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                        <p class="text-xs text-red-500 font-medium">Motivo da Rejeição</p>
                        <p class="text-sm text-red-700">{{ $selectedOvertime->rejection_reason }}</p>
                    </div>
                @endif
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Fechar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Rejection Modal --}}
    @if($showRejectionModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-times-circle mr-2 text-red-600"></i>Rejeitar</h3>
            </div>
            <div class="p-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo da Rejeição *</label>
                <textarea wire:model="rejection_reason" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500" placeholder="Descreva o motivo..."></textarea>
                @error('rejection_reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Cancelar</button>
                <button wire:click="reject" class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition">
                    <i class="fas fa-times mr-2"></i>Rejeitar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
