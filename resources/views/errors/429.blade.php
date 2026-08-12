@extends('errors.layout')

@section('emoji', '🚦')
@section('codigo', '429')
@section('titulo', 'Pedidos a mais, demasiado depressa')

@section('mensagem')
        <p>Recebemos muitos pedidos seguidos desta ligação e travámos os seguintes para proteger o sistema.</p>
        <p>Espere um minuto e tente outra vez.</p>
@endsection
