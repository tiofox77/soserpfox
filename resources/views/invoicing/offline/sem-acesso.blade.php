@extends('layouts.pwa', ['title' => __('Sem acesso')])

@section('content')
{{-- O ecrã a que este utilizador não tem direito.

     Existe para não ser um 403 em branco. Quem está ao balcão não distingue
     "não tem permissão" de "a aplicação partiu-se", e a chamada ao suporte é a
     mesma nos dois casos — com a diferença de que uma delas se resolve em dez
     segundos, se alguém souber o que pedir.

     O menu de baixo continua lá: o caminho de volta faz parte da explicação. --}}
<div class="py-10 px-4">
    <div class="max-w-md mx-auto bg-white rounded-2xl shadow-sm p-8 text-center">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 flex items-center justify-center mb-4">
            <i class="fas fa-lock text-2xl text-amber-600"></i>
        </div>

        <h1 class="text-lg font-bold text-slate-800">
            {{ __('Não tem acesso a :area', ['area' => __($etiqueta)]) }}
        </h1>

        @if($modulo)
            <p class="text-sm text-slate-500 mt-2">
                {{ __('Esta área pertence a um módulo que a sua empresa não tem activo.') }}
            </p>
        @else
            <p class="text-sm text-slate-500 mt-2">
                {{ __('A sua conta não tem permissão para esta área, ou a empresa desligou-a nas definições da aplicação móvel.') }}
            </p>
        @endif

        @if($permissao)
            {{-- O nome exacto da permissão. É o que o administrador procura na
                 lista de papéis — sem ele, a conversa fica em "não me deixa
                 entrar" e ninguém sabe onde carregar. --}}
            <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ __('Permissão em falta') }}</p>
                <code class="text-xs font-mono text-slate-700 break-all">{{ $permissao }}</code>
            </div>
        @endif

        <p class="text-xs text-slate-400 mt-4">
            {{ __('Peça ao administrador da empresa.') }}
        </p>

        <a href="{{ route('invoicing.offline.index') }}"
           class="inline-block mt-6 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl text-sm font-bold transition">
            <i class="fas fa-arrow-left mr-1.5"></i>{{ __('Voltar ao início') }}
        </a>
    </div>
</div>
@endsection
