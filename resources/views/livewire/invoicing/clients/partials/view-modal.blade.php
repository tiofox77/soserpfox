@if($showViewModal && $viewingClient)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ tab: 'info' }" x-cloak>
    <div class="fixed inset-0 bg-black bg-opacity-60 transition-opacity" wire:click="closeViewModal"></div>

    <div class="flex items-start sm:items-center justify-center min-h-screen p-2 sm:p-4 text-center">
        <div class="relative w-full bg-white rounded-2xl text-left shadow-2xl transform transition-all my-4 sm:my-8 sm:max-w-7xl max-h-[94vh] overflow-y-auto">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 px-4 sm:px-6 py-4 sm:py-5 text-white sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-14 h-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl font-bold mr-4">
                            {{ strtoupper(substr($viewingClient['name'] ?? '?', 0, 2)) }}
                        </div>
                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold leading-tight">{{ $viewingClient['name'] ?? '' }}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm text-white/90 mt-1">
                                <span><i class="fas fa-id-card mr-1"></i>NIF: {{ $viewingClient['nif'] ?? '-' }}</span>
                                @if(!empty($viewingClient['type']))
                                    <span class="inline-flex items-center px-2 py-0.5 bg-white/20 rounded-full">
                                        <i class="fas {{ $viewingClient['type'] === 'pessoa_juridica' ? 'fa-building' : 'fa-user' }} mr-1"></i>
                                        {{ $viewingClient['type'] === 'pessoa_juridica' ? 'Empresa' : 'Pessoa Física' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white/80 hover:text-white transition" title="{{ __('Fechar') }}">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="bg-gray-50 border-b border-gray-200 px-2 sm:px-6">
                <nav class="flex flex-wrap gap-1 -mb-px overflow-x-auto" aria-label="Tabs">
                    <button @click="tab = 'info'" :class="tab === 'info' ? 'border-purple-600 text-purple-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-user-circle mr-1.5"></i>{{ __('Info Cliente') }}
                    </button>
                    <button @click="tab = 'detalhes'" :class="tab === 'detalhes' ? 'border-purple-600 text-purple-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-chart-pie mr-1.5"></i>{{ __('Detalhes') }}
                    </button>
                    <button @click="tab = 'extrato'" :class="tab === 'extrato' ? 'border-purple-600 text-purple-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-list-alt mr-1.5"></i>{{ __('Extrato') }}
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">{{ count($clientInvoices) }}</span>
                    </button>
                    <button @click="tab = 'produtos'" :class="tab === 'produtos' ? 'border-purple-600 text-purple-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-star mr-1.5"></i>{{ __('Produtos') }}
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-purple-100 text-purple-700">{{ count($clientTopProducts) }}</span>
                    </button>
                    <button @click="tab = 'frequencia'" :class="tab === 'frequencia' ? 'border-purple-600 text-purple-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-4 border-b-2 font-semibold text-sm transition">
                        <i class="fas fa-chart-bar mr-1.5"></i>{{ __('Frequência') }}
                    </button>
                </nav>
            </div>

            {{-- Body --}}
            <div class="p-4 sm:p-6 max-h-[70vh] overflow-y-auto bg-gray-50">

                {{-- TAB: INFO CLIENTE --}}
                <div x-show="tab === 'info'" x-transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl shadow p-5">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-purple-500"></i>{{ __('Identificação') }}
                            </h4>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Nome:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['name'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('NIF:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['nif'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Tipo:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ ($viewingClient['type'] ?? '') === 'pessoa_juridica' ? 'Pessoa Jurídica' : 'Pessoa Física' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('ID:') }}</dt><dd class="font-mono text-gray-700 text-right">{{ $viewingClient['id'] ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-xl shadow p-5">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-address-book mr-2 text-blue-500"></i>{{ __('Contactos') }}
                            </h4>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Email:') }}</dt><dd class="font-semibold text-gray-900 text-right break-all">{{ $viewingClient['email'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Telefone:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['phone'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Celular:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['mobile'] ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-xl shadow p-5 md:col-span-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>{{ __('Localização') }}
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('País:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['country'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Província:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['province'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Cidade:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['city'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Cód. Postal:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['postal_code'] ?? '—' }}</dd></div>
                                <div class="sm:col-span-2 flex justify-between"><dt class="text-gray-500">{{ __('Endereço:') }}</dt><dd class="font-semibold text-gray-900 text-right">{{ $viewingClient['address'] ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-xl shadow p-5 md:col-span-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center">
                                <i class="fas fa-calendar mr-2 text-green-500"></i>{{ __('Datas Chave') }}
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('Cadastrado em') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ !empty($viewingClient['created_at']) ? \Carbon\Carbon::parse($viewingClient['created_at'])->format('d/m/Y') : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('Primeira Compra') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ $clientStats['first_purchase'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('Última Compra') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ $clientStats['last_purchase'] ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- TAB: DETALHES (estatísticas) --}}
                <div x-show="tab === 'detalhes'" x-transition>
                    {{-- Um total parcial apresentado como total da casa seria pior
                         do que não mostrar nada. Quem só vê os seus documentos vê
                         aqui as SUAS contas com este cliente, e fica dito. --}}
                    @if(soVeOSeu())
                        <p class="mb-3 text-xs text-gray-500">
                            <i class="fas fa-user-lock mr-1"></i>{{ __('Contas do que você facturou a este cliente.') }}
                        </p>
                    @endif
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-blue-600 uppercase">{{ __('Faturas') }}</span>
                                <i class="fas fa-file-invoice text-blue-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $clientStats['total_invoices'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Documentos emitidos') }}</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-green-600 uppercase">{{ __('Faturado') }}</span>
                                <i class="fas fa-coins text-green-500"></i>
                            </div>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($clientStats['total_revenue'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Valor total bruto') }}</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-emerald-600 uppercase">{{ __('Pago') }}</span>
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            </div>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($clientStats['total_paid'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Total recebido') }}</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-red-600 uppercase">{{ __('Em Dívida') }}</span>
                                <i class="fas fa-exclamation-triangle text-red-500"></i>
                            </div>
                            <p class="text-xl font-bold text-red-700">{{ number_format($clientStats['total_pending'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Valor pendente') }}</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-purple-600 uppercase">{{ __('Ticket Médio') }}</span>
                                <i class="fas fa-chart-line text-purple-500"></i>
                            </div>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($clientStats['avg_ticket'] ?? 0, 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Por fatura') }}</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-orange-600 uppercase">{{ __('Frequência') }}</span>
                                <i class="fas fa-calendar-alt text-orange-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $clientStats['avg_days_between'] ?? 0 }}<span class="text-sm text-gray-500"> {{ __('dias') }}</span></p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Média entre compras') }}</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-pink-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-pink-600 uppercase">{{ __('Notas Crédito') }}</span>
                                <i class="fas fa-file-invoice-dollar text-pink-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $clientStats['credit_notes_count'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ number_format($clientStats['credit_notes_total'] ?? 0, 2, ',', '.') }} Kz</p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-cyan-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-cyan-600 uppercase">{{ __('Recibos') }}</span>
                                <i class="fas fa-receipt text-cyan-500"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900">{{ $clientStats['receipts_count'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ number_format($clientStats['receipts_total'] ?? 0, 2, ',', '.') }} Kz</p>
                        </div>
                    </div>
                </div>

                {{-- TAB: EXTRATO --}}
                <div x-show="tab === 'extrato'" x-transition>
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <div class="px-5 py-3 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-gray-200 flex items-center justify-between">
                            <h4 class="font-bold text-gray-800 flex items-center text-sm">
                                <i class="fas fa-list-alt mr-2 text-blue-600"></i>{{ __('Últimas 20 Faturas') }}
                            </h4>
                            <span class="text-xs text-gray-500">{{ count($clientInvoices) }} de {{ $clientStats['total_invoices'] ?? 0 }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs sm:text-sm">
                                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="px-3 py-2 text-left">{{ __('Nº Fatura') }}</th>
                                        <th class="px-3 py-2 text-left">{{ __('Data') }}</th>
                                        <th class="px-3 py-2 text-left hidden sm:table-cell">{{ __('Vencimento') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                                        <th class="px-3 py-2 text-right hidden md:table-cell">{{ __('Pago') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Saldo') }}</th>
                                        <th class="px-3 py-2 text-center">{{ __('Status') }}</th>
                                        <th class="px-3 py-2 text-center">{{ __('Ação') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($clientInvoices as $inv)
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
                                        <tr class="hover:bg-blue-50">
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
                                                <a href="{{ route('invoicing.sales.invoices.preview', $inv['id']) }}" target="_blank" class="text-blue-600 hover:text-blue-800" title="{{ __('Ver Fatura') }}">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                <x-pdf-descarregar :url="route('invoicing.sales.invoices.preview', $inv['id'])" />
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Sem faturas registadas') }}</td></tr>
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
                                <i class="fas fa-star mr-2 text-purple-600"></i>{{ __('Top 10 Produtos Mais Comprados') }}
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs sm:text-sm">
                                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">{{ __('Produto') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Qtd') }}</th>
                                        <th class="px-3 py-2 text-right hidden sm:table-cell">{{ __('Faturas') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($clientTopProducts as $idx => $p)
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
                                        <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Sem produtos comprados') }}</td></tr>
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
                                <i class="fas fa-chart-bar mr-2 text-orange-600"></i>{{ __('Frequência de Compra (Últimos 12 meses)') }}
                            </h4>
                        </div>
                        <div class="p-5">
                            @php
                                $maxCount = collect($clientPurchaseFrequency)->max('count') ?: 1;
                            @endphp
                            @forelse($clientPurchaseFrequency as $row)
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
                                <p class="text-center text-gray-400 italic py-8">{{ __('Sem dados de frequência') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="bg-gray-100 px-6 py-3 flex items-center justify-end gap-2 border-t border-gray-200">
                @can('invoicing.clients.edit')
                <button wire:click="edit({{ $viewingClient['id'] ?? 0 }})" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-edit mr-1"></i>{{ __('Editar Cliente') }}
                </button>
                @endcan
                <button wire:click="closeViewModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-times mr-1"></i>{{ __('Fechar') }}
                </button>
            </div>

        </div>
    </div>
</div>
@endif
