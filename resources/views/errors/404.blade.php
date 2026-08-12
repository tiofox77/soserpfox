@extends('errors.layout')

@section('emoji', '🔍')
@section('codigo', '404')
@section('titulo', 'Esta página não existe')

@section('mensagem')
        <p>O endereço que abriu não corresponde a nenhuma página. Pode ter sido escrito com um erro, ou o que estava aqui já não está.</p>
@endsection
