@if($showShiftRequired)
<div class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="shift-required-title">
    <section class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 p-7 text-center text-white">
            <div class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-white/20 ring-8 ring-white/10"><i class="fas fa-cash-register text-4xl"></i></div>
            <p class="mt-5 text-xs font-black uppercase tracking-widest text-amber-100">Operação protegida</p>
            <h2 id="shift-required-title" class="mt-1 text-2xl font-black">Abra o turno primeiro</h2>
        </div>
        <div class="p-6">
            <p class="text-center leading-relaxed text-slate-600">Para receber ou emitir uma fatura do Restaurante, o utilizador precisa ter o seu próprio turno e caixa abertos.</p>
            <div class="mt-5 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><i class="fas fa-circle-info mr-2 text-blue-600"></i>O turno identifica o operador, controla o dinheiro recebido e permite conferir o caixa no fecho.</div>
            <a href="{{route('restaurant.shifts')}}" class="mt-5 flex w-full items-center justify-center rounded-2xl bg-slate-900 p-4 text-lg font-black text-white shadow-lg hover:bg-blue-950"><i class="fas fa-lock-open mr-2 text-orange-300"></i>Abrir turno e caixa</a>
            <button type="button" wire:click="$set('showShiftRequired',false)" class="mt-2 w-full p-3 font-bold text-slate-500">Voltar à comanda</button>
        </div>
    </section>
</div>
@endif
