<div class="p-6">
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-blue-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-calculator mr-3"></i>
            Gestão de Orçamentos
        </h1>
        <p class="text-indigo-100 mt-2">Planeamento orçamental e comparativo vs real</p>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-4">
                <select wire:model.live="selectedYear" class="px-4 py-2 border rounded-lg">
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
            </div>
            <button wire:click="$set('showModal', true)" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i>Novo Orçamento
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-indigo-50 to-blue-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Conta</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Centro Custo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Orçamento</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Realizado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Variação</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($budgets as $budget)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">{{ $budget->account->code }} - {{ $budget->account->name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $budget->costCenter->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format($budget->total, 2) }} Kz</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format($budget->actual ?? 0, 2) }} Kz</td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold {{ ($budget->actual - $budget->total) > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ number_format(($budget->actual ?? 0) - $budget->total, 2) }} Kz
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($budget->status === 'approved')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Aprovado</span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Rascunho</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="edit({{ $budget->id }})" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($showModal) @include('livewire.accounting.budgets.partials.form-modal') @endif
</div>
