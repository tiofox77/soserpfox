{{--
    O CORPO DE UM MODELO DE EMAIL DENTRO DO LAYOUT — como DADO, nunca como código.

    O `EmailTemplate::wrapInLayout` escrevia o corpo (com o nome da empresa, o
    telefone e o resto já lá dentro) num ficheiro `.blade.php` e compilava-o.
    Um registo com o nome «Loja {{ system('id') }}» corria código no servidor
    antes de qualquer pagamento (auditoria de segurança de 2026-09-13). Esta
    vista é fixa: o conteúdo entra como variável e sai tal e qual.
--}}
@extends('emails.layout')

@section('content')
{!! $conteudo !!}
@endsection
