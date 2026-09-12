{{--
    UM ECRÃ EM REACT NO PAINEL DA PLATAFORMA.

    O mesmo que o `react/ecra`, mas para o layout do superadmin — que usa outros
    nomes de secção (`title` e `subtitle` em vez de `page-title` e
    `page-subtitle`). Ver App\Support\EcraReact::plataforma.
--}}
@extends('layouts.superadmin')

@section('title', $titulo ?? __('Plataforma'))
@section('subtitle', $subtitulo ?? '')

@section('content')
    <x-ecra-react :nome="$ecra" :props="$props ?? []" />
@endsection
