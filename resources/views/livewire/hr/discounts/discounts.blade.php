<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-percentage mr-3 text-red-600"></i>
                    Descontos Salariais
                </h2>
                <p class="text-gray-600 mt-1">Gestão de descontos e deduções salariais dos funcionários</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex bg-gray-100 rounded-xl p-1">
                    <button wire:click="setViewType('list')" 
                            class="px-4 py-2 rounded-lg transition {{ $viewType === 'list' ? 'bg-white text-blue-600 shadow' : 'text-gray-600 hover:text-gray-900' }}">
                        <i class="fas fa-list"></i>
                    </button>
                    <button wire:click="setViewType('grid')" 
                            class="px-4 py-2 rounded-lg transition {{ $viewType === 'grid' ? 'bg-white text-blue-600 shadow' : 'text-gray-600 hover:text-gray-900' }}">
                        <i class="fas fa-th"></i>
                    </button>
                </div>
                <button wire:click="create" 
                        class="px-6 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>Novo Desconto
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-invoice text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Pendentes</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['pending'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Aprovados</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['approved'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-indigo-200 text-xs font-medium">Concluídos</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['completed'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-flag-checkered text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-coins text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar..."
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>
            <select wire:model.live="employeeFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                <option value="">Todos os Funcionários</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                <option value="">Todos os Estados</option>
                <option value="pending">Pendente</option>
                <option value="approved">Aprovado</option>
                <option value="rejected">Rejeitado</option>
                <option value="completed">Concluído</option>
            </select>
            <select wire:model.live="yearFilter" class="border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500">
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Funcionário</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Prestações</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor/Mês</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($discounts as $discount)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-xs font-bold mr-3">
                                        {{ strtoupper(substr($discount->employee->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($discount->employee->last_name ?? '', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $discount->employee->full_name ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">{{ $discount->employee->employee_number ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $discount->discount_type_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $discount->request_date->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                {{ number_format($discount->amount, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <span class="text-gray-700">{{ $discount->installments - $discount->remaining_installments }}/{{ $discount->installments }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">
                                {{ number_format($discount->installment_amount, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                {!! $discount->status_badge !!}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button wire:click="viewDetails({{ $discount->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Detalhes">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                    @if($discount->status === 'pending')
                                        <button wire:click="edit({{ $discount->id }})" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg" title="Editar">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <button wire:click="openApprovalModal({{ $discount->id }}, 'approve')" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="Aprovar">
                                            <i class="fas fa-check text-sm"></i>
                                        </button>
                                        <button wire:click="openApprovalModal({{ $discount->id }}, 'reject')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Rejeitar">
                                            <i class="fas fa-times text-sm"></i>
                                        </button>
                                        <button wire:click="delete({{ $discount->id }})" wire:confirm="Tem certeza que deseja eliminar este desconto?" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Eliminar">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    @endif
                                    @if($discount->status === 'approved' && $discount->remaining_installments > 0)
                                        <button wire:click="registerPayment({{ $discount->id }})" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="Registar Pagamento">
                                            <i class="fas fa-money-check-alt text-sm"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3 block text-gray-300"></i>
                                Nenhum desconto salarial encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $discounts->links() }}
        </div>
    </div>

    {{-- Form Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-percentage mr-2 text-red-600"></i>
                    {{ $editMode ? 'Editar Desconto' : 'Novo Desconto Salarial' }}
                </h3>
                <button wire:click="closeModal" class="p-2 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Funcionário *</label>
                        <select wire:model.live="employee_id" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500">
                            <option value="">Selecionar...</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_number }})</option>
                            @endforeach
                        </select>
                        @error('employee_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Desconto *</label>
                        <select wire:model="discount_type" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500">
                            <option value="">Selecionar...</option>
                            <option value="damages">Danos/Avarias</option>
                            <option value="loan">Empréstimo</option>
                            <option value="union_fee">Quota Sindical</option>
                            <option value="disciplinary">Disciplinar</option>
                            <option value="other">Outro</option>
                        </select>
                        @error('discount_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data do Pedido *</label>
                        <input wire:model="request_date" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500">
                        @error('request_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor Total (Kz) *</label>
                        <input wire:model.live="amount" type="number" step="0.01" min="1" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500">
                        @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nº Prestações *</label>
                        <input wire:model.live="installments" type="number" min="1" max="24" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500">
                        @error('installments') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                @if($installmentAmount > 0)
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-red-700 font-medium">Valor por Prestação:</span>
                            <span class="text-lg font-bold text-red-800">{{ number_format($installmentAmount, 2, ',', '.') }} Kz/mês</span>
                        </div>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo *</label>
                    <textarea wire:model="reason" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500" placeholder="Descreva o motivo do desconto..."></textarea>
                    @error('reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea wire:model="notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500" placeholder="Notas adicionais..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Documento Assinado</label>
                    <input wire:model="signed_document" type="file" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500">
                    @error('signed_document') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Cancelar</button>
                <button wire:click="save" class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 text-white rounded-xl font-bold hover:from-red-700 hover:to-rose-700 transition">
                    <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Guardar' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Details Modal --}}
    @if($showDetailsModal && $selectedDiscount)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>Detalhes do Desconto
                </h3>
                <button wire:click="closeModal" class="p-2 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Funcionário</p>
                        <p class="font-semibold">{{ $selectedDiscount->employee->full_name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Tipo</p>
                        <p class="font-semibold">{{ $selectedDiscount->discount_type_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Data do Pedido</p>
                        <p class="font-semibold">{{ $selectedDiscount->request_date->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Estado</p>
                        <p>{!! $selectedDiscount->status_badge !!}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Valor Total</p>
                        <p class="font-bold text-lg text-red-700">{{ number_format($selectedDiscount->amount, 2, ',', '.') }} Kz</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Prestações</p>
                        <p class="font-semibold">{{ $selectedDiscount->installments - $selectedDiscount->remaining_installments }}/{{ $selectedDiscount->installments }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Valor/Mês</p>
                        <p class="font-semibold">{{ number_format($selectedDiscount->installment_amount, 2, ',', '.') }} Kz</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Restantes</p>
                        <p class="font-semibold">{{ $selectedDiscount->remaining_installments }}</p>
                    </div>
                </div>
                @if($selectedDiscount->reason)
                    <div>
                        <p class="text-xs text-gray-500">Motivo</p>
                        <p class="text-sm text-gray-700">{{ $selectedDiscount->reason }}</p>
                    </div>
                @endif
                @if($selectedDiscount->approvedBy)
                    <div>
                        <p class="text-xs text-gray-500">Aprovado por</p>
                        <p class="text-sm">{{ $selectedDiscount->approvedBy->name }} — {{ $selectedDiscount->approved_at?->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
                @if($selectedDiscount->rejection_reason)
                    <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                        <p class="text-xs text-red-500 font-medium">Motivo da Rejeição</p>
                        <p class="text-sm text-red-700">{{ $selectedDiscount->rejection_reason }}</p>
                    </div>
                @endif
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Fechar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Approval Modal --}}
    @if($showApprovalModal && $selectedDiscount)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-check-circle mr-2 text-green-600"></i>Aprovar Desconto
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-gray-700">Confirma a aprovação do desconto de <strong>{{ number_format($selectedDiscount->amount, 2, ',', '.') }} Kz</strong> para <strong>{{ $selectedDiscount->employee->full_name ?? 'N/A' }}</strong>?</p>
                <p class="text-sm text-gray-500">O valor de <strong>{{ number_format($selectedDiscount->installment_amount, 2, ',', '.') }} Kz</strong> será deduzido mensalmente durante {{ $selectedDiscount->installments }} meses.</p>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Cancelar</button>
                <button wire:click="processApproval" class="px-6 py-2.5 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Aprovar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Rejection Modal --}}
    @if($showRejectionModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-times-circle mr-2 text-red-600"></i>Rejeitar Desconto
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo da Rejeição *</label>
                    <textarea wire:model="rejection_reason" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-red-500" placeholder="Descreva o motivo da rejeição..."></textarea>
                    @error('rejection_reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                <button wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">Cancelar</button>
                <button wire:click="processApproval" class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition">
                    <i class="fas fa-times mr-2"></i>Rejeitar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
