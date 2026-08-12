@extends('errors.layout')

@section('emoji', '⛔')
@section('codigo', '403')
@section('titulo', 'Sem permissão para isto')

@section('mensagem')
        <p>A sua conta não tem permissão para aceder a esta área. Não é um erro do sistema — é uma porta que está fechada para o seu perfil.</p>
        <p>Se precisa de lá entrar, peça ao administrador da sua empresa para lhe dar o acesso.</p>
@endsection
