{{--
    Cabeçalho comum aos ecrãs de criar documento.

    Os quatro — facturas e proformas, de venda e de compra — tinham o mesmo
    bloco copiado, e a cópia já tinha divergido: a página de FACTURAS DE VENDA
    dizia "Crie orçamentos de compras de Clientees", texto vindo da proforma de
    compra, com erro de escrita incluído. Ninguém dá por isso porque cada
    página se lê sozinha.

    Parâmetros:
      $titulo    obrigatório  — "Nova Factura de Venda"
      $subtitulo obrigatório  — a linha de descrição
      $icone     opcional     — classe FontAwesome (fa-file-invoice)
      $cor       opcional     — cor Tailwind do ícone (purple, blue, emerald…)
      $voltar    obrigatório  — URL do botão de voltar
--}}
@php
    $__icone = $icone ?? 'fa-file-invoice';
    $__cor   = $cor ?? 'purple';
@endphp

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas {{ $__icone }} mr-3 text-{{ $__cor }}-600"></i>
                {{ $titulo }}
            </h2>
            <p class="text-gray-600 mt-1 text-sm sm:text-base">{{ $subtitulo }}</p>
        </div>

        <a href="{{ $voltar }}"
           x-data="{ loading: false }" @click="loading = true"
           :class="loading && 'opacity-70 pointer-events-none scale-95'"
           class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition-all duration-300 hover:scale-105 active:scale-95 text-center whitespace-nowrap">
            <span x-show="!loading"><i class="fas fa-arrow-left mr-2"></i>{{ __('Voltar') }}</span>
            <span x-show="loading" x-cloak><i class="fas fa-spinner fa-spin mr-2"></i>{{ __('Voltando...') }}</span>
        </a>
    </div>
</div>
