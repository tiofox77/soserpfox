{{-- Filtro por quem emitiu o documento.

     Só aparece a quem pode ver os documentos de todos
     (`invoicing.documents.all`). A quem só vê os seus, o que aparece é a
     razão por que a lista é curta — os nomes dos colegas são eles próprios
     informação que não lhe compete.

     Uso: <x-filtro-autor :autores="$this->autoresDosDocumentos"
                          :todos="$this->veDocumentosDeTodos" /> --}}
@props([
    'autores' => null,
    'todos' => false,
    'etiqueta' => 'Emitido por',
    {{-- Uma célula da grelha. As listas com grelha de 12 colunas passam a
         sua própria largura (ver as facturas de venda). --}}
    'classe' => '',
])

@if($todos)
    <div class="{{ $classe }}">
        <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __($etiqueta) }}</label>
        <select wire:model.live="autorId"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-purple-500">
            <option value="">{{ __('Todos') }}</option>
            @foreach($autores ?? [] as $autor)
                <option value="{{ $autor->id }}">{{ $autor->name }}</option>
            @endforeach
        </select>
    </div>
@else
    <div class="{{ $classe }} flex items-end">
        <p class="text-[11px] text-gray-500 pb-2 leading-tight">
            <i class="fas fa-user-lock mr-1"></i>{{ __('A mostrar apenas os documentos que emitiu.') }}
        </p>
    </div>
@endif
