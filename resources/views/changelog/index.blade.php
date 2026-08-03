@extends('layouts.app')

@section('content')
@php
    $typeBadge = [
        'major' => ['label' => 'MAJOR', 'class' => 'bg-red-100 text-red-700 border-red-300'],
        'minor' => ['label' => 'MINOR', 'class' => 'bg-blue-100 text-blue-700 border-blue-300'],
        'patch' => ['label' => 'PATCH', 'class' => 'bg-emerald-100 text-emerald-700 border-emerald-300'],
    ];
    $sectionMeta = [
        'features'     => ['title' => 'Novas Funcionalidades', 'icon' => 'fa-rocket',            'class' => 'text-emerald-700', 'bg' => 'bg-emerald-50',   'border' => 'border-emerald-200'],
        'improvements' => ['title' => 'Melhorias',              'icon' => 'fa-arrow-trend-up',   'class' => 'text-blue-700',    'bg' => 'bg-blue-50',      'border' => 'border-blue-200'],
        'fixes'        => ['title' => 'Correções',              'icon' => 'fa-bug-slash',        'class' => 'text-amber-700',   'bg' => 'bg-amber-50',     'border' => 'border-amber-200'],
        'security'     => ['title' => 'Segurança',              'icon' => 'fa-shield-halved',    'class' => 'text-red-700',     'bg' => 'bg-red-50',       'border' => 'border-red-200'],
    ];
@endphp

<div class="max-w-5xl mx-auto p-4 lg:p-8">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-purple-600 text-white rounded-2xl shadow-xl p-6 lg:p-8 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-rocket text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold leading-tight">Atualizações do Sistema</h1>
                        <p class="text-blue-100 text-sm">Histórico de versões e novidades do SOS ERP</p>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wider text-blue-100">Versão Actual</p>
                <p class="text-3xl font-extrabold mt-1">v{{ $currentVersion }}</p>
                @if(!empty($releases[0]['date']))
                    <p class="text-xs text-blue-100 mt-1">
                        <i class="far fa-calendar mr-1"></i>
                        {{ \Carbon\Carbon::parse($releases[0]['date'])->isoFormat('D [de] MMMM [de] YYYY') }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="relative">
        {{-- Linha vertical --}}
        <div class="hidden md:block absolute left-6 top-0 bottom-0 w-0.5 bg-gradient-to-b from-indigo-400 via-blue-300 to-gray-200"></div>

        @foreach($releases as $i => $release)
            @php
                $isCurrent = $release['version'] === $currentVersion;
                $type = $release['type'] ?? 'patch';
                $badge = $typeBadge[$type] ?? $typeBadge['patch'];
            @endphp

            <div class="relative md:pl-16 mb-8">
                {{-- Marcador --}}
                <div class="hidden md:flex absolute left-0 top-2 w-12 h-12 rounded-full items-center justify-center shadow-md
                            {{ $isCurrent ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-white border-2 border-indigo-300 text-indigo-600' }}">
                    <i class="fas {{ $isCurrent ? 'fa-star' : 'fa-tag' }}"></i>
                </div>

                <div class="bg-white rounded-2xl shadow-md hover:shadow-lg transition border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-xl font-bold text-gray-900">v{{ $release['version'] }}</span>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full border {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            @if($isCurrent)
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-500 text-white">
                                    <i class="fas fa-check-circle mr-1"></i> Em produção
                                </span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-500">
                            <i class="far fa-calendar mr-1"></i>
                            {{ \Carbon\Carbon::parse($release['date'])->isoFormat('D MMM YYYY') }}
                        </div>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        @if(!empty($release['title']))
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $release['title'] }}
                            </h3>
                        @endif

                        @foreach(['features', 'improvements', 'fixes', 'security'] as $key)
                            @php $items = $release[$key] ?? []; @endphp
                            @if(!empty($items))
                                @php $meta = $sectionMeta[$key]; @endphp
                                <div class="rounded-xl border {{ $meta['border'] }} {{ $meta['bg'] }} p-4">
                                    <p class="font-bold {{ $meta['class'] }} text-sm mb-2 flex items-center">
                                        <i class="fas {{ $meta['icon'] }} mr-2"></i> {{ $meta['title'] }}
                                    </p>
                                    <ul class="space-y-1.5">
                                        @foreach($items as $item)
                                            <li class="flex items-start gap-2 text-sm text-gray-700">
                                                <i class="fas fa-check-circle {{ $meta['class'] }} mt-0.5 text-xs"></i>
                                                <span>{{ $item }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Nota sobre actualização do PWA já instalado --}}
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mt-8 text-sm text-amber-900">
        <p class="font-bold mb-2 flex items-center">
            <i class="fas fa-mobile-alt mr-2"></i> O PWA já estava instalado e os ícones não mudaram?
        </p>
        <ul class="space-y-1.5 ml-4 list-disc">
            <li>O <strong>conteúdo da app</strong> actualiza-se automaticamente — quando há nova versão aparece um aviso "Nova versão disponível" no canto inferior direito (basta clicar em <em>Atualizar agora</em>).</li>
            <li>Os <strong>ícones do atalho</strong> são guardados pelo sistema operativo (Android/iOS/Windows) no momento da instalação e <strong>não actualizam sozinhos</strong>.</li>
            <li>Para ver os novos ícones: <strong>desinstalar o atalho</strong> (manter premido → Desinstalar) e <strong>reinstalar</strong> a partir do botão "Instalar" do navegador.</li>
            <li>A versão actual está sempre visível no <strong>header do PWA</strong> e neste cabeçalho — confirme que coincide com <code class="bg-amber-100 px-1.5 rounded">v{{ $currentVersion }}</code>.</li>
        </ul>
    </div>

    <div class="text-center text-xs text-gray-500 mt-8 pb-8">
        <i class="fas fa-info-circle mr-1"></i>
        Sugestões ou problemas? Use o botão de suporte no canto inferior direito.
    </div>
</div>
@endsection
