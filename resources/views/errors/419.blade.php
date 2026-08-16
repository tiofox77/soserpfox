@extends('errors.layout')

@section('emoji', '⏳')
@section('codigo', '419')
@section('titulo', 'A página esteve parada demasiado tempo')

@php
    // Um 419 tem duas causas, e o que a pessoa deve fazer é diferente em cada
    // uma. Se a sessão caiu, recarregar não resolve nada — é preciso entrar de
    // novo. Se a sessão está de pé e só o formulário caducou, o que serve é
    // voltar à página onde ela estava.
    $temSessao = auth()->check();

    // A página anterior, e só se for deste sítio e não for o próprio erro:
    // um `Referer` de fora, ou a apontar para aqui, mandava a pessoa outra vez
    // para onde já está.
    $anterior = url()->previous();
    $daCasa = $anterior
        && str_starts_with($anterior, url('/'))
        && !str_contains($anterior, '/login')
        && $anterior !== url()->current();
@endphp

@section('mensagem')
        <p>
            @if($temSessao)
                Por segurança, os formulários caducam ao fim de algum tempo. Nada se perdeu do lado do sistema — volte à página e submeta outra vez.
            @else
                A sua sessão terminou por inactividade. Entre de novo para continuar de onde estava. Nada se perdeu do lado do sistema.
            @endif
        </p>
@endsection

@section('accao')
    {{-- Não `javascript:location.reload()`, que era o que estava aqui: numa
         página de erro 419 o pedido que falhou foi quase sempre a submissão de
         um formulário, e recarregar volta a mandar essa mesma submissão — ou
         faz o browser perguntar se quer reenviar os dados. Além disso, com a
         sessão caída o destino certo nem é a página: é a entrada. --}}
    @if($temSessao)
            <a class="botao principal" href="{{ $daCasa ? $anterior : url('/') }}">Voltar à página</a>
            <a class="botao secundario" href="{{ url('/') }}">Voltar ao início</a>
    @else
            <a class="botao principal" href="{{ route('login') }}">Entrar de novo</a>
            <a class="botao secundario" href="{{ url('/') }}">Voltar ao início</a>
    @endif
@endsection
