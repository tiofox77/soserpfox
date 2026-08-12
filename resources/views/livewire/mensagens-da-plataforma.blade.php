{{-- As mensagens do dono da plataforma, como as vê quem usa o sistema.

     Duas formas, e a diferença importa: a barra fica no topo e deixa
     trabalhar; o pop-up interrompe. Escolher errado é a diferença entre
     avisar e chatear. --}}
<div>
    @php
        $barras = $mensagens->where('display', 'barra');
        $popup  = $mensagens->firstWhere('display', 'popup');
    @endphp

    {{-- Barras: empilham-se no topo, uma por mensagem. --}}
    @foreach($barras as $m)
        @php $e = $m->estilo(); @endphp
        <div wire:key="barra-{{ $m->id }}"
             class="mb-3 rounded-xl border-l-4 border-{{ $e['cor'] }}-500 bg-{{ $e['cor'] }}-50 px-4 py-3 flex items-start gap-3">
            <i class="fas {{ $e['icone'] }} text-{{ $e['cor'] }}-600 mt-0.5"></i>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-{{ $e['cor'] }}-900">{{ $m->title }}</p>
                <p class="text-sm text-{{ $e['cor'] }}-800 whitespace-pre-line">{{ $m->body }}</p>
                @if($m->link_url)
                    <a href="{{ $m->link_url }}" target="_blank" rel="noopener"
                       class="inline-block mt-1 text-sm font-semibold text-{{ $e['cor'] }}-700 underline">
                        {{ $m->link_label ?: 'Saber mais' }}
                    </a>
                @endif
            </div>
            @if($m->dismissible)
                <button wire:click="dispensar({{ $m->id }})"
                        class="text-{{ $e['cor'] }}-500 hover:text-{{ $e['cor'] }}-800 transition shrink-0"
                        title="Dispensar">
                    <i class="fas fa-times"></i>
                </button>
            @endif
        </div>
    @endforeach

    {{-- Pop-up: um de cada vez. Duas caixas a interromper ao mesmo tempo não
         se leem — leem-se ambas a fechar. --}}
    @if($popup)
        @php $e = $popup->estilo(); @endphp
        <div class="fixed inset-0 z-[60] overflow-y-auto" wire:key="popup-{{ $popup->id }}"
             @if($popup->dismissible) @keydown.escape.window="$wire.dispensar({{ $popup->id }})" @endif>
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                     @if($popup->dismissible) wire:click="dispensar({{ $popup->id }})" @endif></div>

                <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden">
                    <div class="bg-{{ $e['cor'] }}-600 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas {{ $e['icone'] }}"></i>
                            {{ $popup->title }}
                        </h3>
                        @if($popup->dismissible)
                            <button wire:click="dispensar({{ $popup->id }})"
                                    class="text-white/80 hover:text-white transition" title="Fechar">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        @endif
                    </div>

                    <div class="p-6">
                        <p class="text-gray-700 whitespace-pre-line leading-relaxed">{{ $popup->body }}</p>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
                        @if($popup->link_url)
                            <a href="{{ $popup->link_url }}" target="_blank" rel="noopener"
                               class="px-5 py-2.5 bg-{{ $e['cor'] }}-600 hover:bg-{{ $e['cor'] }}-700 text-white rounded-xl font-semibold transition">
                                {{ $popup->link_label ?: 'Saber mais' }}
                            </a>
                        @endif
                        @if($popup->dismissible)
                            <button wire:click="dispensar({{ $popup->id }})"
                                    class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-100 transition">
                                Entendido
                            </button>
                        @else
                            {{-- Uma mensagem que não se dispensa fica. É para
                                 as que exigem que alguém faça alguma coisa. --}}
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-lock mr-1"></i>Esta mensagem não pode ser dispensada.
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
