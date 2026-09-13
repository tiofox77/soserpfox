{{--
    UMA PÁGINA PÚBLICA EM REACT.

    Não é o layout da aplicação: aqui não há menu, nem barra lateral, nem
    sessão. É a página que um hóspede abre de um cartaz ou de uma ligação do
    Instagram, e a primeira coisa que ela tem de fazer é abrir depressa num
    telemóvel com rede fraca.

    O CABEÇALHO É DO SERVIDOR de propósito: o título, a descrição e a imagem
    que o WhatsApp e o Facebook mostram têm de estar no HTML antes de o
    JavaScript correr. Um `og:title` escrito pelo React chega tarde para quem
    interessa — o robô que lê a página já se foi embora.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descricao }}">
    {{-- O CANÓNICO É O ENDEREÇO DA CASA, sem a mesa e sem query string: a
         carta da mesa 7 é a mesma carta, e um `?utm=` do Instagram não é outra
         página. --}}
    <meta name="robots" content="{{ $robots ?? null ?: 'index, follow, max-image-preview:large' }}">
    <link rel="canonical" href="{{ $canonico ?? url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:url" content="{{ $canonico ?? url()->current() }}">
    @if($imagem)
    <meta property="og:image" content="{{ $imagem }}">
    @endif
    <meta name="twitter:card" content="{{ $imagem ? 'summary_large_image' : 'summary' }}">

    @include('partials.favicon')

    {{ $dadosEstruturados ?? '' }}

    <script src="/vendor/js/tailwind.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">

    {{--
        AS ANIMAÇÕES QUE OS ECRÃS EM REACT USAM.

        São as mesmas regras que os ecrãs de dentro têm — `entra` em cascata,
        `card-hover` — mas em ponto pequeno: o bloco do layout da aplicação tem
        430 linhas, quase todas sobre a barra lateral, que aqui não existe.
        Trazê-lo inteiro para uma página pública era mandar ao telemóvel de um
        hóspede o CSS de um menu que ele nunca vai ver.

        E QUEM PEDIU MENOS MOVIMENTO NÃO O LEVA: há quem sinta náuseas com
        interfaces que saltam, e o sistema operativo tem uma definição para o
        dizer.
    --}}
    <style>
        @keyframes entradaSuave {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0);   }
        }

        .entra {
            animation: entradaSuave .22s ease-out both;
            animation-delay: calc(var(--i, 0) * 22ms);
        }

        @keyframes aparecer {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .animate-fade-in { animation: aparecer .3s ease-out both; }

        .card-hover {
            transition: transform .4s cubic-bezier(.4, 0, .2, 1), box-shadow .4s cubic-bezier(.4, 0, .2, 1);
        }

        .card-hover:hover { transform: translateY(-4px); }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
                scroll-behavior: auto !important;
            }

            .entra { animation: none; opacity: 1; transform: none; }
        }
    </style>

    @php($pacoteReact = \App\Support\PacoteReact::caminho())
    @if($pacoteReact)
        <script>
            window.__reactLingua = @json(app()->getLocale());
            @if(\App\Support\DicionarioDoReact::precisa())
            window.__reactDicionarioUrl = @json(route('react.traducoes', ['marca' => \App\Support\DicionarioDoReact::marca()]));
            @endif
        </script>
        <script type="module" src="{{ $pacoteReact }}" defer></script>
    @endif
</head>
<body class="bg-white">
    <x-ecra-react :nome="$ecra" :props="$props ?? []" class="min-h-screen" />
</body>
</html>
