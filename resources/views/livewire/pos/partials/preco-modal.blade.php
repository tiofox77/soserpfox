{{--
    O preço que se decide no balcão.

    Há artigos cujo preço não está na ficha — um trabalho em inox feito à
    medida, um serviço combinado com o cliente. Quem vende escreve-o aqui, na
    hora, e o artigo entra no carrinho com esse valor.

    A primeira versão usava a caixa do navegador. Funcionava, mas é o único
    sítio do POS que não se parece com o POS: sem o nome do artigo em destaque,
    sem a máscara de dinheiro, e com dois botões cinzentos do sistema operativo.
--}}
@if($showPrecoModal)
<div class="fixed inset-0 bg-black/50 z-[70] flex items-center justify-center p-4"
     x-data
     x-on:keydown.escape.window="$wire.fecharPrecoModal()">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-hand-holding-dollar"></i> {{ __('Preço desta venda') }}
            </h3>
            <button wire:click="fecharPrecoModal" type="button" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form x-on:submit.prevent="$wire.confirmarPrecoNoPos($refs.preco.value)" class="p-6 space-y-5">

            {{-- O artigo por inteiro: nomes de trabalhos à medida são longos e
                 é por eles que o operador confirma que escolheu o certo. --}}
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">{{ __('Artigo') }}</p>
                <p class="text-sm font-bold text-gray-900 leading-snug">{{ $precoModalNome }}</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    {{ __('Preço unitário') }} <span class="text-red-500">*</span>
                </label>

                <div class="relative">
                    <x-moeda-input :value="$precoModalValor"
                        x-ref="preco"
                        autofocus
                        x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                        placeholder="0"
                        class="w-full pl-4 pr-16 py-4 text-2xl font-bold text-right border-2 border-amber-300 rounded-xl focus:border-amber-500 focus:ring-2 focus:ring-amber-200" />

                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400">Kz</span>
                </div>

                @error('precoModalValor')
                    <p class="text-xs text-red-600 mt-2"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button" wire:click="fecharPrecoModal"
                        class="flex-1 px-4 py-3 rounded-xl font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition">
                    {{ __('Cancelar') }}
                </button>

                <button type="submit"
                        class="flex-1 px-4 py-3 rounded-xl font-bold text-white bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 transition shadow-lg">
                    <i class="fas fa-cart-plus mr-2"></i>{{ __('Adicionar') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif
