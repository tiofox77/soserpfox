@extends('errors.layout')

@section('emoji', '🛠️')
@section('codigo', '500')
@section('robots', 'noindex, nofollow')
@section('titulo', 'Alguma coisa correu mal do nosso lado')

@section('mensagem')
        <p>Não foi nada que tenha feito. Houve uma falha a processar o seu pedido e a ocorrência ficou registada — vamos vê-la.</p>
        <p>Os seus dados estão seguros. Tente de novo dentro de momentos.</p>
@endsection
