{{--
    UM ECRÃ EM REACT NO PORTAL DO REVENDEDOR (guard `revendedor`).
    Ver App\Support\EcraReact::revendedor.
--}}
@extends('layouts.revendedor')

@section('title', $titulo ?? __('Portal do Revendedor'))

@section('content')
    <x-ecra-react :nome="$ecra" :props="$props ?? []" />
@endsection
