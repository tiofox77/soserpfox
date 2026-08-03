<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Minhas Faturas</h2>
                    <p class="text-green-100 text-sm">Visualize e gerencie suas faturas</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-green-600"></i>
                Filtros Avançados
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Número da fatura..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-tag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todos os status</option>
                    <option value="pending">⏳ Pendente</option>
                    <option value="partially_paid">💰 Parcialmente Paga</option>
                    <option value="paid">✅ Paga</option>
                    <option value="overdue">⚠️ Atrasada</option>
                    <option value="credited">↩️ Creditada</option>
                    <option value="cancelled">❌ Cancelada</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Faturas --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $invoice->invoice_number }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ optional($invoice->invoice_date)->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap font-semibold">{{ number_format($invoice->total ?? 0, 2, ',', '.') }} Kz</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $isOverdue = in_array($invoice->status, ['pending','overdue'], true) && $invoice->due_date && $invoice->due_date->isPast(); @endphp
                            @if($invoice->status === 'paid')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paga</span>
                            @elseif($invoice->status === 'partially_paid')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Parcial</span>
                            @elseif($invoice->status === 'overdue' || $isOverdue)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Atrasada</span>
                            @elseif($invoice->status === 'pending')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Pendente</span>
                            @elseif($invoice->status === 'credited')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Creditada</span>
                            @elseif($invoice->status === 'cancelled')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-700">Cancelada</span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ $invoice->status_label ?? ucfirst($invoice->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $balance = ($invoice->total ?? 0) - ($invoice->paid_amount ?? 0); @endphp
                            <span class="text-xs font-semibold {{ $balance > 0.009 ? 'text-red-600' : 'text-green-600' }}">
                                Saldo: {{ number_format($balance, 2, ',', '.') }} Kz
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            Nenhuma fatura encontrada
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="px-6 py-4">
            {{ $invoices->links() }}
        </div>
    </div>
</div>
