@extends('errors.layout')

@section('emoji', '💳')
@section('codigo', '402')
@section('titulo', 'Subscrição por regularizar')

@section('mensagem')
        <p>O acesso a esta área depende de uma subscrição activa. Se já efectuou o pagamento, pode levar algumas horas até ser validado.</p>
@endsection

@section('accao')
            <a class="botao principal" href="{{ url('/minha-conta?tab=plan') }}">Ver o meu plano</a>
@endsection
