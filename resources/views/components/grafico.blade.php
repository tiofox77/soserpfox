@props([
    'titulo',
    'subtitulo' => null,
    'id',
    'altura' => 280,
    // Quando não há nada para desenhar, um gráfico vazio é pior do que uma
    // frase: parece avariado. Quem chama diz o que dizer nesse caso.
    'vazio' => false,
    'mensagemVazia' => null,
])

{{--
    O cartão de um gráfico.

    Um sítio só para o título, a altura e o estado vazio — seis painéis a
    repetir este markup davam seis alturas diferentes e seis maneiras de não
    ter dados.

    A ALTURA É FIXA E EM PIXELS, de propósito. O Chart.js redimensiona-se ao
    contentor; num contentor sem altura definida ele cresce a cada
    redesenho — o gráfico ia esticando pela página abaixo a cada actualização
    do Livewire até a página ter três metros.
--}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="font-black text-slate-800">{{ $titulo }}</h3>
            @if($subtitulo)
                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitulo }}</p>
            @endif
        </div>
        {{ $acoes ?? '' }}
    </div>

    @if($vazio)
        <div class="grid place-items-center text-center" style="height: {{ $altura }}px">
            <div>
                <i class="fas fa-chart-simple mb-3 block text-4xl text-slate-200"></i>
                <p class="text-sm text-slate-400">
                    {{ $mensagemVazia ?: __('Ainda não há dados para este período.') }}
                </p>
            </div>
        </div>
    @else
        <div class="mt-4" style="height: {{ $altura }}px">
            <canvas id="{{ $id }}"></canvas>
        </div>
    @endif
</div>
