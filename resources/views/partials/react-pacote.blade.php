{{--
    O PACOTE DOS ECRÃS EM REACT.

    Estava escrito dentro do `layouts/app.blade.php`, e por isso os ecrãs React
    só montavam nesse layout. O painel da plataforma tem layout próprio
    (`layouts/superadmin`) e precisa exactamente do mesmo bloco: duas cópias
    divergiriam na primeira mudança — foi o que aconteceu ao `?v=` e ao
    dicionário.

    O nome vem do manifesto e leva hash — e SEM `?v=` por cima. Os pedaços
    importam a entrada por caminho relativo e sem query; com um nome fixo mais
    query, o browser via dois módulos e carregava o React duas vezes. Ver
    App\Support\PacoteReact.

    Null quando ainda não foi construído, que é um estado normal numa instalação
    acabada de clonar: os ecrãs React não montam e o resto da aplicação abre na
    mesma.
--}}
@php($pacoteReact = \App\Support\PacoteReact::caminho())

@if($pacoteReact)
    {{-- A língua dos ecrãs em React. O dicionário só se anuncia a quem precisa
         dele: em português a chave já é a frase. --}}
    <script>
        window.__reactLingua = @json(app()->getLocale());
        @if(\App\Support\DicionarioDoReact::precisa())
        window.__reactDicionarioUrl = @json(route('react.traducoes', ['marca' => \App\Support\DicionarioDoReact::marca()]));
        @endif
    </script>
    <script type="module" src="{{ $pacoteReact }}" defer></script>
@endif
