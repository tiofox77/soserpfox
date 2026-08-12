@extends('errors.layout')

@section('emoji', '🔒')
@section('codigo', '401')
@section('titulo', 'Precisa de entrar')

@section('mensagem')
        <p>Esta página é só para quem tem sessão iniciada. A sua sessão pode ter terminado por inactividade.</p>
@endsection

@section('accao')
            <a class="botao principal" href="{{ route('login') }}">Entrar</a>
            <a class="botao secundario" href="{{ url('/') }}">Voltar ao início</a>
@endsection
