{{--
    O TAILWIND DAS PÁGINAS PÚBLICAS, JÁ COMPILADO (tailwind.publico.config.cjs).

    Era um compilador de 407 KB a correr no browser de cada visita. A versão na
    morada muda quando o ficheiro muda: o browser pode guardá-lo sem medo.
--}}
@php($cssPublico = public_path('css/publico.css'))
<link rel="stylesheet" href="{{ asset('css/publico.css') }}?v={{ is_file($cssPublico) ? filemtime($cssPublico) : '1' }}">
