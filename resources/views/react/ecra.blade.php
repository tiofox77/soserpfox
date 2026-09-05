{{--
    A PÁGINA QUE SERVE UM ECRÃ EM REACT.

    É uma página normal do layout de sempre: menu, cabeçalho e permissões
    continuam a vir do Laravel. Só o miolo é React.

    Enquanto a migração durar, cada ecrã tem DUAS moradas — a de sempre, com o
    Livewire, e esta. Assim comparam-se lado a lado e a antiga nunca deixa de
    estar lá. Quando o novo estiver provado, é a rota de sempre que passa a
    apontar para aqui e a outra desaparece.
--}}
@extends('layouts.app')

@section('page-title', $titulo ?? __('Facturação'))
@section('page-subtitle', $subtitulo ?? '')

@section('content')
    <x-ecra-react :nome="$ecra" :props="$props ?? []" />
@endsection
