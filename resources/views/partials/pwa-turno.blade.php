{{--
    Os modais do turno de caixa — abrir e fechar — partilhados pelo POS de
    balcão e pelo POS de restaurante.

    Estavam só dentro do POS, e o restaurante ficou sem forma de abrir turno
    nenhum: mandava o empregado ir ao outro ecrã e voltar. Aqui está uma vez
    só, porque duas cópias da aritmética da caixa acabam sempre a discordar —
    e a caixa é onde isso custa mais caro.

    Precisa de `...TurnoDoPwa()` no Alpine do ecrã (public/js/pwa-turno.js) e
    de um `formatMoney()` no anfitrião — os dois ecrãs já o têm.
--}}

{{-- ============ MODAL: abrir turno (funciona offline) ============ --}}
<div x-show="showShiftOpen" @click.self="showShiftOpen = false" class="fixed inset-0 bg-black/60 z-[70] flex items-end sm:items-center justify-center" x-transition x-cloak>
    <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl flex flex-col" @click.stop>
        <div class="bg-gradient-to-r from-emerald-600 to-green-700 text-white px-5 py-3 rounded-t-3xl flex items-center justify-between">
            <h3 class="font-bold text-base"><i class="fas fa-lock-open mr-1"></i>{{ __('Abrir Turno') }}</h3>
            <button @click="showShiftOpen = false" class="text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4 space-y-3">
            <div x-show="!turnoOnline" x-cloak class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl px-3 py-2 text-xs">
                <i class="fas fa-wifi-slash mr-1"></i>{{ __('Está offline — o turno abre localmente e sincroniza quando a internet voltar.') }}
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ __('Saldo inicial em caixa (Kz)') }} <span class="text-red-500">*</span></label>
                <input x-model="shiftOpenForm.opening_balance" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0,00"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm text-right font-bold focus:border-emerald-500 focus:outline-none"
                       @keydown.enter="confirmOpenShift()">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ __('Notas') }}</label>
                <input x-model="shiftOpenForm.opening_notes" type="text" maxlength="1000" placeholder="{{ __('Opcional') }}"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-emerald-500 focus:outline-none">
            </div>
        </div>
        <div class="p-3 border-t flex gap-2">
            <button @click="showShiftOpen = false" class="flex-1 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-bold text-sm">{{ __('Cancelar') }}</button>
            <button @click="confirmOpenShift()" :disabled="shiftBusy || shiftOpenForm.opening_balance === ''"
                    class="flex-[2] bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
                <span x-show="!shiftBusy"><i class="fas fa-lock-open mr-1"></i>{{ __('Abrir turno') }}</span>
                <span x-show="shiftBusy"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A abrir…') }}</span>
            </button>
        </div>
    </div>
</div>

{{-- ============ MODAL: fechar turno (funciona offline) ============ --}}
<div x-show="showShiftClose" @click.self="showShiftClose = false" class="fixed inset-0 bg-black/60 z-[70] flex items-end sm:items-center justify-center" x-transition x-cloak>
    <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl flex flex-col max-h-[90vh]" @click.stop>
        <div class="bg-gradient-to-r from-red-600 to-rose-700 text-white px-5 py-3 rounded-t-3xl flex items-center justify-between">
            <h3 class="font-bold text-base"><i class="fas fa-lock mr-1"></i>{{ __('Fechar Turno') }} <span class="opacity-80 text-sm" x-text="shift.number ? '· ' + shift.number : ''"></span></h3>
            <button @click="showShiftClose = false" class="text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4 space-y-3 overflow-y-auto">
            <div x-show="turnoPendentes > 0" class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl px-3 py-2 text-xs">
                {{-- O "documento(s)" nao existe em EN nem em FR: conta pelo __n.
                     E a frase seguinte vai inteira, sem o <strong> a parti-la
                     a meio — negrito dentro de uma cadeia e cadeia impossivel
                     de traduzir. --}}
                <i class="fas fa-clock mr-1"></i><strong x-text="__n(':n documento por sincronizar|:n documentos por sincronizar', turnoPendentes, { n: turnoPendentes })"></strong>
                — <span x-text="__('O fecho fica em fila e só é efetivado no servidor depois de todas as vendas sincronizarem.')"></span>
            </div>
            <div x-show="!turnoOnline" x-cloak class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl px-3 py-2 text-xs">
                <i class="fas fa-wifi-slash mr-1"></i>{{ __('Está offline — o fecho é guardado localmente e sincroniza quando a internet voltar.') }}
            </div>
            <div class="bg-gray-50 rounded-2xl p-3 text-sm space-y-1">
                <p class="flex justify-between"><span class="text-gray-500">{{ __('Saldo inicial') }}</span><strong x-text="formatMoney(shift.opening_balance) + ' Kz'"></strong></p>
                <p class="flex justify-between"><span class="text-gray-500">{{ __('Vendas dinheiro (sincr.)') }}</span><strong x-text="formatMoney(shift.cash_sales) + ' Kz'"></strong></p>
                <p class="flex justify-between" x-show="localCashSinceOpen > 0"><span class="text-gray-500">{{ __('Vendas dinheiro (offline)') }}</span><strong x-text="formatMoney(localCashSinceOpen) + ' Kz'"></strong></p>
                <p class="flex justify-between border-t pt-1 mt-1"><span class="text-gray-600 font-semibold">{{ __('Esperado em caixa') }}</span><strong class="text-emerald-700" x-text="formatMoney(expectedCash) + ' Kz'"></strong></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ __('Dinheiro contado em caixa (Kz)') }} <span class="text-red-500">*</span></label>
                <input x-model="shiftCloseForm.actual_cash" type="number" inputmode="decimal" min="0" step="0.01"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm text-right font-bold focus:border-red-500 focus:outline-none">
                <p class="text-[11px] mt-1" :class="closeDifference === 0 ? 'text-gray-400' : (closeDifference > 0 ? 'text-emerald-600' : 'text-red-600')"
                   x-show="shiftCloseForm.actual_cash !== ''">
                    {{ __('Diferença:') }} <strong x-text="(closeDifference > 0 ? '+' : '') + formatMoney(closeDifference) + ' Kz'"></strong>
                </p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ __('Notas de fecho') }}</label>
                <input x-model="shiftCloseForm.closing_notes" type="text" maxlength="1000" placeholder="{{ __('Opcional') }}"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-red-500 focus:outline-none">
            </div>
            {{-- Última sincronização --}}
            <div x-show="turnoUltimoSync" class="bg-blue-50 rounded-xl px-3 py-2 text-[11px] text-blue-700 flex items-center gap-1">
                <i class="fas fa-clock-rotate-left"></i>
                <span>{{ __('Última sincronização:') }} <strong x-text="turnoUltimoSyncLabel"></strong></span>
                <span x-show="turnoPendentes > 0" class="ml-auto font-bold text-amber-600">{{ __('Valores locais podem não incluir outros dispositivos.') }}</span>
            </div>
        </div>
        <div class="p-3 border-t flex gap-2">
            <button @click="showShiftClose = false" class="flex-1 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-bold text-sm">{{ __('Cancelar') }}</button>
            <button @click="confirmCloseShift()" :disabled="shiftBusy || shiftCloseForm.actual_cash === ''"
                    class="flex-[2] bg-gradient-to-r from-red-500 to-rose-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
                <span x-show="!shiftBusy"><i class="fas fa-lock mr-1"></i>{{ __('Fechar e Imprimir') }}</span>
                <span x-show="shiftBusy"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A fechar…') }}</span>
            </button>
        </div>
    </div>
</div>
