{{-- O painel dos avisos da plataforma, no ecrã de entrada.

     Não se desenha quando não há nada: uma caixa vazia a dizer "sem avisos"
     em todos os dias em que não há avisos — que são quase todos — só ocupa o
     ecrã e ensina a não olhar para ali. --}}
{{-- A raiz existe SEMPRE, mesmo sem avisos: um componente Livewire sem
     elemento de raiz rebenta com RootTagMissingFromViewException, e como isto
     vive no ecrã de entrada, o ecrã ia abaixo em todos os dias em que não
     houvesse aviso nenhum — que são quase todos. --}}
<div>
@if($avisos->isNotEmpty())
    <div class="mb-6 bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
                <i class="fas fa-bullhorn text-white"></i>
            </div>
            <div class="min-w-0">
                <h3 class="text-white font-bold leading-tight">{{ __('Avisos da plataforma') }}</h3>
                <p class="text-indigo-100 text-xs">{{ __('Comunicações da equipa SOS ERP') }}</p>
            </div>
            <span class="ml-auto text-xs font-bold text-white bg-white/20 rounded-full px-3 py-1 shrink-0">
                {{ $avisos->count() }}
            </span>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach($avisos as $aviso)
                @php
                    $e  = $aviso->estilo();
                    $ja = isset($dispensados[$aviso->id]);
                @endphp
                <div class="px-6 py-4 flex items-start gap-4 {{ $ja ? 'bg-gray-50/60' : '' }}"
                     wire:key="aviso-{{ $aviso->id }}">
                    <div class="w-9 h-9 rounded-xl bg-{{ $e['cor'] }}-100 flex items-center justify-center shrink-0 {{ $ja ? 'opacity-60' : '' }}">
                        <i class="fas {{ $e['icone'] }} text-{{ $e['cor'] }}-600"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-bold text-gray-900 {{ $ja ? 'text-gray-600' : '' }}">{{ $aviso->title }}</p>
                            @if($ja)
                                <span class="text-[11px] font-semibold text-gray-500 bg-gray-200 rounded-full px-2 py-0.5">
                                    {{ __('Lido') }}
                                </span>
                            @endif
                        </div>

                        <p class="text-sm text-gray-700 whitespace-pre-line mt-1 {{ $ja ? 'text-gray-500' : '' }}">{{ $aviso->body }}</p>

                        <div class="flex items-center gap-4 mt-2 flex-wrap">
                            @if($aviso->link_url)
                                <a href="{{ $aviso->link_url }}" target="_blank" rel="noopener"
                                   class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 underline">
                                    {{ $aviso->link_label ?: __('Saber mais') }}
                                </a>
                            @endif

                            {{-- A data no relógio de parede de Angola: a base guarda em UTC e
                                 mostrar isso punha aqui uma hora que não é a de ninguém. --}}
                            @if($aviso->ends_at)
                                <span class="text-xs text-gray-400">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ __('até') }} {{ $aviso->noRelogioDeParede('ends_at')->format('d/m/Y H:i') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
</div>
