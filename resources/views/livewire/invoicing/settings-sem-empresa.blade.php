{{-- O ecrã de definições quando não há empresa activa.

     As definições de facturação são de uma empresa: moeda, séries, impostos,
     comportamento do POS. Sem empresa escolhida não há o que editar, e mostrar
     o formulário na mesma daria a impressão de que se estava a configurar
     alguma coisa. --}}
<div class="container mx-auto px-4 py-6">
    <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-purple-600 to-indigo-600 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
                <i class="fas fa-building text-white text-lg"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-white font-bold text-lg leading-tight">
                    {{ __('Configurações de Faturação') }}
                </h1>
                <p class="text-purple-100 text-xs">{{ __('Nenhuma empresa activa') }}</p>
            </div>
        </div>

        <div class="p-6">
            <p class="text-gray-700 leading-relaxed">
                {{ __('Estas configurações pertencem a uma empresa — moeda, séries, impostos e o comportamento do ponto de venda. Escolha primeiro a empresa com que quer trabalhar, no selector no topo da página.') }}
            </p>

            <p class="text-sm text-gray-500 mt-4">
                {{ __('Se a sua conta ainda não está ligada a nenhuma empresa, peça a quem administra o sistema que o associe a uma.') }}
            </p>

            <a href="{{ url('/home') }}"
               class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-arrow-left"></i>
                {{ __('Voltar ao início') }}
            </a>
        </div>
    </div>
</div>
