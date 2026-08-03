@if($showViewModal && $viewingSupplier)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ tab: 'info' }" x-cloak>
    <div class="fixed inset-0 bg-black bg-opacity-60 transition-opacity" wire:click="closeViewModal"></div>

    <div class="flex items-start sm:items-center justify-center min-h-screen p-2 sm:p-4 text-center">
        <div class="relative w-full bg-white rounded-2xl text-left shadow-2xl transform transition-all my-4 sm:my-8 sm:max-w-7xl max-h-[94vh] overflow-y-auto">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-orange-600 via-red-600 to-pink-600 px-4 sm:px-6 py-4 sm:py-5 text-white sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-14 h-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl font-bold mr-4">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold leading-tight">{{ $viewingSupplier['name'] ?? '' }}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm text-white/90 mt-1">
                                <span><i class="fas fa-id-card mr-1"></i>NIF: {{ $viewingSupplier['nif'] ?? '-' }}</span>
                                @if(!empty($viewingSupplier['type']))
                                    <span class="inline-flex items-center px-2 py-0.5 bg-white/20 rounded-full">
                                        <i class="fas {{ $viewingSupplier['type'] === 'pessoa_juridica' ? 'fa-building' : 'fa-user' }} mr-1"></i>
                                        {{ $viewingSupplier['type'] === 'pessoa_juridica' ? 'Empresa' : 'Pessoa Física' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white/80 hover:text-white transition" title="Fechar">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="bg-gray-50 border-b border-gray-200 px-2 sm:px-6">
                <nav class="flex flex-wrap gap-1 -mb-px overflow-x-auto" aria-label="Tabs">
                    <button @click="tab = 'info'" :class="tab === 'info' ? 'border-orange-600 text-orange-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-truck mr-1.5"></i>Info Fornecedor
                    </button>
                    <button @click="tab = 'detalhes'" :class="tab === 'detalhes' ? 'border-orange-600 text-orange-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-chart-pie mr-1.5"></i>Detalhes
                    </button>
                    <button @click="tab = 'extrato'" :class="tab === 'extrato' ? 'border-orange-600 text-orange-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-list-alt mr-1.5"></i>Extrato
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">{{ count($supplierInvoices) }}</span>
                    </button>
                    <button @click="tab = 'produtos'" :class="tab === 'produtos' ? 'border-orange-600 text-orange-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-box mr-1.5"></i>Produtos
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-purple-100 text-purple-700">{{ count($supplierTopProducts) }}</span>
                    </button>
                    <button @click="tab = 'frequencia'" :class="tab === 'frequencia' ? 'border-orange-600 text-orange-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-chart-bar mr-1.5"></i>Frequência
                    </button>
                </nav>
            </div>

            {{-- Body --}}
            <div class="p-4 sm:p-6 max-h-[70vh] overflow-y-auto bg-gray-50">

                {{-- TAB: INFO FORNECEDOR --}}
                <div x-show="tab === 'info'" x-transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl shadow p-5">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-truck mr-2 text-orange-500"></i>Identificação
                            </h4>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Nome:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['name'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">NIF:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['nif'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Tipo:</dt><dd class="font-semibold text-gray-900 text-right">{{ ($viewingSupplier['type'] ?? '') === 'pessoa_juridica' ? 'Pessoa Jurídica' : 'Pessoa Física' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">ID:</dt><dd class="font-mono text-gray-700 text-right">{{ $viewingSupplier['id'] ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-xl shadow p-5">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-address-book mr-2 text-blue-500"></i>Contactos
                            </h4>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Email:</dt><dd class="font-semibold text-gray-900 text-right break-all">{{ $viewingSupplier['email'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Telefone:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['phone'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Celular:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['mobile'] ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-xl shadow p-5 md:col-span-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>Localização
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">País:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['country'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Província:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['province'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Cidade:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['city'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Cód. Postal:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['postal_code'] ?? '—' }}</dd></div>
                                <div class="sm:col-span-2 flex justify-between"><dt class="text-gray-500">Endereço:</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingSupplier['address'] ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-xl shadow p-5 md:col-span-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-calendar mr-2 text-green-500"></i>Datas Chave
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500">Cadastrado em</dt>
                                    <dd class="font-semibold text-gray-900">{{ !empty($viewingSupplier['created_at']) ? \Carbon\Carbon::parse($viewingSupplier['created_at'])->format('d/m/Y') : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">Primeira Compra</dt>
                                    <dd class="font-semibold text-gray-900">{{ $supplierStats['first_purchase'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">Última Compra</dt>
                                    <dd class="font-semibold text-gray-900">{{ $supplierStats['last_purchase'] ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- TAB: DETALHES --}}
                <div x-show="tab === 'detalhes'" x-transition>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-blue-600 uppercase">Faturas</span>
                                <i class="fas fa-file-invoice text-blue-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $supplierStats['total_invoices'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Compras registadas</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-orange-600 uppercase">Comprado</span>
                                <i class="fas fa-shopping-cart text-orange-500"></i>
                            </div>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($supplierStats['total_revenue'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">Valor total bruto</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-emerald-600 uppercase">Pago</span>
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            </div>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($supplierStats['total_paid'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">Total pago</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-red-600 uppercase">Em Dívida</span>
                                <i class="fas fa-exclamation-triangle text-red-500"></i>
                            </div>
                            <p class="text-xl font-bold text-red-700">{{ number_format($supplierStats['total_pending'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">Valor pendente</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-purple-600 uppercase">Ticket Médio</span>
                                <i class="fas fa-chart-line text-purple-500"></i>
                            </div>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($supplierStats['avg_ticket'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">Por fatura</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-amber-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-amber-600 uppercase">Frequência</span>
                                <i class="fas fa-calendar-alt text-amber-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $supplierStats['avg_days_between'] ?? 0 }}<span class="text-sm text-gray-500"> dias</span></p>
                            <p class="text-xs text-gray-500 mt-1">Média entre compras</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-cyan-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-cyan-600 uppercase">Pagamentos</span>
                                <i class="fas fa-receipt text-cyan-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $supplierStats['receipts_count'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ number_format($supplierStats['receipts_total'] ?? 0, 2, ',', '.') }} Kz</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-pink-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-pink-600 uppercase">Produtos</span>
                                <i class="fas fa-box text-pink-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ count($supplierTopProducts) }}</p>
                            <p class="text-xs text-gray-500 mt-1">Distintos comprados</p>
                        </div>
                    </div>
                </div>

                {{-- TAB: EXTRATO --}}
                <div x-show="tab === 'extrato'" x-transition>
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <div class="px-5 py-3 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-gray-200 flex items-center justify-between">
                            <h4 class="font-bold text-gray-800 flex items-center text-sm">
                                <i class="fas fa-list-alt mr-2 text-blue-600"></i>Últimas 20 Faturas de Compra
                            </h4>
                            <span class="text-xs text-gray-500">{{ count($supplierInvoices) }} de {{ $supplierStats['total_invoices'] ?? 0 }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs sm:text-sm">
                                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Nº Fatura</th>
                                        <th class="px-3 py-2 text-left">Data</th>
                                        <th class="px-3 py-2 text-left hidden sm:table-cell">Vencimento</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                        <th class="px-3 py-2 text-right hidden md:table-cell">Pago</th>
                                        <th class="px-3 py-2 text-right">Saldo</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                        <th class="px-3 py-2 text-center">Ação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($supplierInvoices as $inv)
                                        @php
                                            $statusColor = match($inv['status'] ?? '') {
                                                'paid' => 'green', 'partially_paid' => 'blue', 'pending' => 'yellow',
                                                'overdue' => 'red', 'cancelled' => 'gray', 'draft' => 'gray', default => 'gray',
                                            };
                                            $statusLabel = match($inv['status'] ?? '') {
                                                'paid' => 'Pago', 'partially_paid' => 'Parcial', 'pending' => 'Pendente',
                                                'overdue' => 'Atrasado', 'cancelled' => 'Cancelado', 'draft' => 'Rascunho',
                                                default => ucfirst($inv['status'] ?? '-'),
                                            };
                                        @endphp
                                        <tr class="hover:bg-orange-50">
                                            <td class="px-3 py-2 font-bold text-gray-900">{{ $inv['invoice_number'] }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $inv['invoice_date'] }}</td>
                                            <td class="px-3 py-2 text-gray-600 hidden sm:table-cell">{{ $inv['due_date'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ number_format($inv['total'], 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right text-emerald-700 hidden md:table-cell">{{ number_format($inv['paid_amount'], 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right font-bold {{ $inv['balance'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($inv['balance'], 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700">{{ $statusLabel }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <a href="{{ route('invoicing.purchases.invoices.preview', $inv['id']) }}" target="_blank" class="text-orange-600 hover:text-orange-800" title="Ver Fatura">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">Sem faturas registadas</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB: PRODUTOS --}}
                <div x-show="tab === 'produtos'" x-transition>
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <div class="px-5 py-3 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-200">
                            <h4 class="font-bold text-gray-800 flex items-center text-sm">
                                <i class="fas fa-box mr-2 text-purple-600"></i>Top 10 Produtos Comprados a Este Fornecedor
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs sm:text-sm">
                                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">Produto</th>
                                        <th class="px-3 py-2 text-right">Qtd</th>
                                        <th class="px-3 py-2 text-right hidden sm:table-cell">Faturas</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($supplierTopProducts as $idx => $p)
                                        <tr class="hover:bg-purple-50">
                                            <td class="px-3 py-2 text-gray-500">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-2">
                                                <p class="font-semibold text-gray-900">{{ $p['name'] ?? 'Produto removido' }}</p>
                                                @if(!empty($p['sku']))
                                                    <p class="text-xs text-gray-500">{{ $p['sku'] }}</p>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right font-bold text-purple-700">{{ number_format($p['total_qty'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right text-gray-600 hidden sm:table-cell">{{ $p['invoices_count'] ?? 0 }}</td>
                                            <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ number_format($p['total_value'] ?? 0, 2, ',', '.') }} Kz</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400 italic">Sem produtos comprados</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB: FREQUÊNCIA --}}
                <div x-show="tab === 'frequencia'" x-transition>
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <div class="px-5 py-3 bg-gradient-to-r from-orange-50 to-amber-50 border-b border-gray-200">
                            <h4 class="font-bold text-gray-800 flex items-center text-sm">
                                <i class="fas fa-chart-bar mr-2 text-orange-600"></i>Frequência de Compra (Últimos 12 meses)
                            </h4>
                        </div>
                        <div class="p-5">
                            @php
                                $maxCount = collect($supplierPurchaseFrequency)->max('count') ?: 1;
                            @endphp
                            @forelse($supplierPurchaseFrequency as $row)
                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse(($row['period'] ?? '0000-01') . '-01')->translatedFormat('M Y') }}</span>
                                        <span class="text-gray-500">{{ $row['count'] ?? 0 }} fatura(s) · <strong>{{ number_format($row['total'] ?? 0, 2, ',', '.') }} Kz</strong></span>
                                    </div>
                                    <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-orange-400 to-amber-500" style="width: {{ ($row['count'] ?? 0) / $maxCount * 100 }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-gray-400 italic py-8">Sem dados de frequência</p>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="bg-gray-100 px-6 py-3 flex items-center justify-end gap-2 border-t border-gray-200">
                <button wire:click="edit({{ $viewingSupplier['id'] ?? 0 }})" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-edit mr-1"></i>Editar Fornecedor
                </button>
                <button wire:click="closeViewModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-times mr-1"></i>Fechar
                </button>
            </div>

        </div>
    </div>
</div>
@endif
