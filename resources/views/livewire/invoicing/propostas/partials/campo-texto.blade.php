{{-- Campo de texto de uma linha de um bloco. `.change` e não `.blur`: o valor
     tem de chegar ao servidor mal se sai do campo, senão a pré-visualização
     mostra o texto antigo e parece que a edição não pegou. --}}
<div>
    <label class="block text-xs text-gray-600 mb-1">{{ $rotulo }}</label>
    <input type="text" value="{{ $valor }}"
           wire:change="actualizarCampo('{{ $campo }}', $event.target.value)"
           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
</div>
