@props([
    // O endereço da pré-visualização do documento.
    'url' => null,
    // Ou, em alternativa, um seletor de algo que já está nesta página.
    'elemento' => null,
    // Nome do ficheiro. Se ficar vazio, sai do título da pré-visualização.
    'nome' => null,
    'titulo' => null,
    'icone' => 'fas fa-file-arrow-down',
    'classe' => 'p-2 bg-rose-100 text-rose-600 rounded-lg hover:bg-rose-600 hover:text-white transition transform hover:scale-110',
])

{{--
    Descarregar o documento em PDF, feito no browser.

    O PDF sai da PRÓPRIA pré-visualização, fotografada e embrulhada numa folha
    A4. É por isso que o desenho não pode divergir: não há segundo desenho.

    Este botão ACRESCENTA, não substitui. O olho ao lado continua a abrir a
    pré-visualização num separador, que é de onde se imprime e onde se confere
    o documento antes de o mandar.

    A mecânica está em /js/pdf-do-documento.js, que ouve o clique por delegação
    — as listas são Livewire e trocam as linhas a cada filtro.
--}}
<button type="button"
        @if($url) data-pdf-preview="{{ $url }}" @endif
        @if($elemento) data-pdf-elemento="{{ $elemento }}" @endif
        @if($nome) data-pdf-nome="{{ $nome }}" @endif
        data-pdf-erro="{{ __('Não foi possível gerar o PDF.') }}"
        title="{{ $titulo ?? __('Descarregar PDF') }}"
        {{ $attributes->merge(['class' => $classe]) }}>
    <i class="{{ $icone }}"></i>
    @if(trim($slot) !== '')
        <span class="ml-2">{{ $slot }}</span>
    @endif
</button>
