{{--
    UM ECRÃ EM REACT NO PORTAL DO CLIENTE.

    O mesmo que o `react/ecra`, dentro do layout do portal (guard `client`).
    Ver App\Support\EcraReact::cliente.
--}}
@extends('layouts.client')

@section('title', $titulo ?? __('Portal do Cliente'))

@section('content')
    <x-ecra-react :nome="$ecra" :props="$props ?? []" />
@endsection
