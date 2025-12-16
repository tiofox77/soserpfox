<div class="p-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center">
                <i class="fas fa-sign-out-alt text-white text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Check-out com Faturação</h1>
                <p class="text-gray-500 dark:text-gray-400">Finalize estadias e emita faturas</p>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl flex items-center gap-2">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-700 rounded-xl flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Em Atraso</p>
                    <p class="text-2xl font-bold text-red-600">{{ $overdueCheckouts->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Hoje</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $todayCheckouts->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-orange-500"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Próximos</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $futureCheckouts->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar text-blue-500"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Search --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-6">
        <div class="relative">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar por reserva, hóspede ou quarto..."
                   class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-orange-500 focus:outline-none dark:bg-gray-700">
        </div>
    </div>

    {{-- Overdue Checkouts --}}
    @if($overdueCheckouts->count() > 0)
    <div class="mb-6">
        <h2 class="text-lg font-bold text-red-600 mb-3 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i> Check-outs em Atraso
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($overdueCheckouts as $res)
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-red-200 dark:border-red-800 hover:shadow-lg transition cursor-pointer"
                 wire:click="openCheckout({{ $res->id }})">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-white">{{ $res->guest?->name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $res->reservation_number }}</p>
                    </div>
                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                        {{ \Carbon\Carbon::parse($res->check_out_date)->diffForHumans() }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2 text-gray-500">
                        <i class="fas fa-door-open"></i>
                        <span>{{ $res->room?->room_number ?? 'N/A' }}</span>
                    </div>
                    <p class="font-bold text-orange-600">{{ number_format($res->total ?? 0, 0, ',', '.') }} Kz</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Today's Checkouts --}}
    @if($todayCheckouts->count() > 0)
    <div class="mb-6">
        <h2 class="text-lg font-bold text-orange-600 mb-3 flex items-center gap-2">
            <i class="fas fa-clock"></i> Check-outs de Hoje
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($todayCheckouts as $res)
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition cursor-pointer"
                 wire:click="openCheckout({{ $res->id }})">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-white">{{ $res->guest?->name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $res->reservation_number }}</p>
                    </div>
                    <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">Hoje</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2 text-gray-500">
                        <i class="fas fa-door-open"></i>
                        <span>{{ $res->room?->room_number ?? 'N/A' }}</span>
                    </div>
                    <p class="font-bold text-orange-600">{{ number_format($res->total ?? 0, 0, ',', '.') }} Kz</p>
                </div>
                <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                    <span>{{ $res->nights ?? 1 }} noites</span>
                    <span class="px-2 py-1 rounded {{ $res->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $res->payment_status === 'paid' ? 'Pago' : ($res->payment_status === 'partial' ? 'Parcial' : 'Pendente') }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Future Checkouts --}}
    @if($futureCheckouts->count() > 0)
    <div class="mb-6">
        <h2 class="text-lg font-bold text-blue-600 mb-3 flex items-center gap-2">
            <i class="fas fa-calendar"></i> Próximos Check-outs
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($futureCheckouts->take(6) as $res)
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition cursor-pointer"
                 wire:click="openCheckout({{ $res->id }})">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-white">{{ $res->guest?->name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $res->reservation_number }}</p>
                    </div>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                        {{ \Carbon\Carbon::parse($res->check_out_date)->format('d/m') }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2 text-gray-500">
                        <i class="fas fa-door-open"></i>
                        <span>{{ $res->room?->room_number ?? 'N/A' }}</span>
                    </div>
                    <p class="font-bold text-orange-600">{{ number_format($res->total ?? 0, 0, ',', '.') }} Kz</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Empty State --}}
    @if($totalReservations === 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center">
        <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-bed text-4xl text-gray-400"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Nenhum hóspede para check-out</h3>
        <p class="text-gray-500">Não há reservas com status "checked-in" no momento.</p>
    </div>
    @endif

    {{-- Checkout Modal --}}
    @if($showCheckoutModal && $reservation)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="closeModal"></div>
            
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                @if(!$checkoutComplete)
                {{-- Header --}}
                <div class="bg-gradient-to-r from-orange-500 to-red-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 text-white">
                            <i class="fas fa-sign-out-alt text-2xl"></i>
                            <div>
                                <h2 class="text-xl font-bold">Check-out</h2>
                                <p class="text-orange-100 text-sm">{{ $reservation->reservation_number }}</p>
                            </div>
                        </div>
                        <button wire:click="closeModal" class="text-white/80 hover:text-white">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                    {{-- Guest & Room Info --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                <i class="fas fa-user text-orange-500"></i> Hóspede
                            </h3>
                            <p class="font-bold text-gray-800 dark:text-white">{{ $reservation->guest?->name ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-500">{{ $reservation->guest?->phone ?? '' }}</p>
                            <p class="text-sm text-gray-500">{{ $reservation->guest?->email ?? '' }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                <i class="fas fa-bed text-orange-500"></i> Quarto
                            </h3>
                            <p class="font-bold text-gray-800 dark:text-white">{{ $reservation->room?->room_number ?? 'N/A' }} - {{ $reservation->roomType?->name ?? '' }}</p>
                            <p class="text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}
                            </p>
                            <p class="text-sm text-gray-500">{{ $reservation->nights ?? 1 }} noites</p>
                        </div>
                    </div>

                    {{-- Resumo da Conta --}}
                    <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-xl mb-6">
                        <div class="p-4 border-b dark:border-gray-700">
                            <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <i class="fas fa-receipt text-orange-500"></i> Resumo da Conta
                            </h3>
                        </div>
                        <div class="p-4">
                            {{-- Hospedagem --}}
                            <div class="flex items-center justify-between py-2 border-b dark:border-gray-700">
                                <div>
                                    <p class="font-medium">Hospedagem - {{ $reservation->roomType?->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $reservation->nights ?? 1 }} noites x {{ number_format($reservation->room_rate ?? 0, 0, ',', '.') }} Kz</p>
                                </div>
                                <p class="font-bold">{{ number_format($roomTotal, 0, ',', '.') }} Kz</p>
                            </div>

                            {{-- Extras --}}
                            @foreach($extras as $index => $extra)
                            <div class="flex items-center justify-between py-2 border-b dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <button wire:click="removeExtra({{ $index }})" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                    <div>
                                        <p class="font-medium">{{ $extra['description'] }}</p>
                                        <p class="text-sm text-gray-500">{{ $extra['quantity'] }} x {{ number_format($extra['unit_price'], 0, ',', '.') }} Kz</p>
                                    </div>
                                </div>
                                <p class="font-bold">{{ number_format($extra['total'], 0, ',', '.') }} Kz</p>
                            </div>
                            @endforeach

                            {{-- Adicionar Extra --}}
                            <div class="py-3 border-b dark:border-gray-700">
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Adicionar Consumo Extra:</p>
                                <div class="flex gap-2">
                                    <input type="text" wire:model="newExtraDescription" placeholder="Descrição" 
                                           class="flex-1 px-3 py-2 border rounded-lg text-sm dark:bg-gray-700 dark:border-gray-600">
                                    <input type="number" wire:model="newExtraQuantity" placeholder="Qtd" 
                                           class="w-16 px-2 py-2 border rounded-lg text-sm dark:bg-gray-700 dark:border-gray-600">
                                    <input type="number" wire:model="newExtraAmount" placeholder="Valor" 
                                           class="w-28 px-2 py-2 border rounded-lg text-sm dark:bg-gray-700 dark:border-gray-600">
                                    <button wire:click="addExtra" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Totais --}}
                            <div class="pt-4 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Subtotal Quarto:</span>
                                    <span>{{ number_format($roomTotal, 0, ',', '.') }} Kz</span>
                                </div>
                                @if($extrasTotal > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Subtotal Extras:</span>
                                    <span>{{ number_format($extrasTotal, 0, ',', '.') }} Kz</span>
                                </div>
                                @endif
                                @if($discountAmount > 0)
                                <div class="flex justify-between text-sm text-green-600">
                                    <span>Desconto:</span>
                                    <span>-{{ number_format($discountAmount, 0, ',', '.') }} Kz</span>
                                </div>
                                @endif
                                <div class="flex justify-between text-lg font-bold pt-2 border-t dark:border-gray-700">
                                    <span>TOTAL:</span>
                                    <span class="text-orange-600">{{ number_format($grandTotal, 0, ',', '.') }} Kz</span>
                                </div>
                                <div class="flex justify-between text-sm text-green-600">
                                    <span>Já pago:</span>
                                    <span>{{ number_format($paidAmount, 0, ',', '.') }} Kz</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold {{ $balanceDue > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    <span>Saldo a pagar:</span>
                                    <span>{{ number_format($balanceDue, 0, ',', '.') }} Kz</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Pagamento --}}
                    @if($balanceDue > 0)
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-6">
                        <h3 class="font-bold text-amber-700 dark:text-amber-300 mb-3 flex items-center gap-2">
                            <i class="fas fa-credit-card"></i> Pagamento
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-amber-700 dark:text-amber-300 mb-1">Valor a Pagar</label>
                                <input type="number" wire:model="paymentAmount" 
                                       class="w-full px-3 py-2 border border-amber-300 rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-amber-700 dark:text-amber-300 mb-1">Método</label>
                                <select wire:model="paymentMethodId" class="w-full px-3 py-2 border border-amber-300 rounded-lg dark:bg-gray-700 dark:border-gray-600">
                                    <option value="">Selecione...</option>
                                    @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Opções de Fatura --}}
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                        <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                            <i class="fas fa-file-invoice text-orange-500"></i> Faturação
                        </h3>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="generateInvoice" class="w-5 h-5 rounded text-orange-500">
                                <span class="font-medium">Gerar Fatura</span>
                            </label>
                            @if($generateInvoice)
                            <label class="flex items-center gap-3 cursor-pointer ml-8">
                                <input type="checkbox" wire:model="sendEmail" class="w-5 h-5 rounded text-orange-500">
                                <span>Enviar por email ao hóspede</span>
                            </label>
                            <div class="ml-8">
                                <label class="block text-sm font-medium mb-1">Notas da Fatura</label>
                                <textarea wire:model="invoiceNotes" rows="2" 
                                          class="w-full px-3 py-2 border rounded-lg resize-none dark:bg-gray-700 dark:border-gray-600" 
                                          placeholder="Observações adicionais..."></textarea>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex items-center justify-between">
                    <button wire:click="closeModal" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold hover:bg-gray-300 transition">
                        Cancelar
                    </button>
                    <button wire:click="processCheckout" wire:loading.attr="disabled" 
                            class="px-8 py-3 bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-xl font-bold hover:from-orange-600 hover:to-red-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="processCheckout">
                            <i class="fas fa-check-circle mr-2"></i> Finalizar Check-out
                        </span>
                        <span wire:loading wire:target="processCheckout">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Processando...
                        </span>
                    </button>
                </div>
                @else
                {{-- Success State --}}
                <div class="p-8 text-center">
                    <div class="w-24 h-24 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check text-5xl text-green-500"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Check-out Concluído!</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">O hóspede foi finalizado e o quarto está liberado.</p>
                    
                    @if($generatedInvoice)
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 max-w-sm mx-auto mb-6">
                        <p class="text-green-700 dark:text-green-300 font-medium">
                            <i class="fas fa-file-invoice mr-2"></i> Fatura Gerada: {{ $generatedInvoice->invoice_number }}
                        </p>
                    </div>
                    @endif

                    <div class="flex gap-4 justify-center">
                        <button wire:click="newCheckout" class="px-6 py-3 bg-orange-600 text-white rounded-xl font-bold hover:bg-orange-700 transition">
                            <i class="fas fa-redo mr-2"></i> Novo Check-out
                        </button>
                        <a href="{{ route('hotel.reservations') }}" class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-bold hover:bg-gray-200 transition">
                            <i class="fas fa-list mr-2"></i> Ver Reservas
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
