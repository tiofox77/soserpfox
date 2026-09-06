{{--
    A PÁGINA QUE SERVE UM ECRÃ EM REACT.

    É uma página normal do layout de sempre: menu, cabeçalho e permissões
    continuam a vir do Laravel. Só o miolo é React — ver App\Support\EcraReact.
--}}
@extends('layouts.app')

@section('page-title', $titulo ?? __('Facturação'))
@section('page-subtitle', $subtitulo ?? '')

@section('content')
    <x-ecra-react :nome="$ecra" :props="$props ?? []" />
@endsection
