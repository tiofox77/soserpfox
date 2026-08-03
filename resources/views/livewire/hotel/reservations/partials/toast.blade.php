{{-- Toast Notifications --}}
{{--
  x-cloak: sem ele o Alpine só esconde o elemento depois de arrancar, e o toast
  pisca visível a cada carregamento da página.

  Trata também o tipo 'warning': cancelar uma reserva já faturada avisa que
  ficam documentos por regularizar, e essa mensagem saía a VERDE, como se
  estivesse tudo bem.
--}}
<div x-data="{ show: false, message: '', type: 'success' }"
     x-cloak
     x-on:notify.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, type === 'success' ? 4000 : 9000)"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-y-2"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-y-0"
     x-transition:leave-end="opacity-0 transform translate-y-2"
     class="fixed bottom-4 right-4 z-50 max-w-md">
    <div :class="{
            'bg-red-500': type === 'error',
            'bg-amber-500': type === 'warning',
            'bg-green-500': type !== 'error' && type !== 'warning'
         }"
         class="px-6 py-3 rounded-xl text-white shadow-lg flex items-start gap-2">
        <i class="mt-0.5"
           :class="{
              'fas fa-exclamation-circle': type === 'error',
              'fas fa-triangle-exclamation': type === 'warning',
              'fas fa-check-circle': type !== 'error' && type !== 'warning'
           }"></i>
        <span x-text="message"></span>

        {{-- Avisos ficam mais tempo e podem fechar-se à mão: a mensagem das
             faturas por regularizar é longa e o utilizador precisa de a ler. --}}
        <button x-show="type !== 'success'" x-on:click="show = false"
                class="ml-2 opacity-70 hover:opacity-100" title="Fechar">
            <i class="fas fa-xmark"></i>
        </button>
    </div>
</div>
