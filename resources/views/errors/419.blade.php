@extends('errors.layout')

@section('emoji', '⏳')
@section('codigo', '419')
@section('titulo', 'A página esteve parada demasiado tempo')

@section('mensagem')
        <p>Por segurança, os formulários caducam ao fim de algum tempo. Nada se perdeu do lado do sistema — basta recarregar a página e voltar a submeter.</p>
@endsection

@section('accao')
            <a class="botao principal" href="javascript:location.reload()">Recarregar a página</a>
            <a class="botao secundario" href="{{ url('/') }}">Voltar ao início</a>
@endsection
