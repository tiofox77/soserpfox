<!DOCTYPE html>
{{--
    O TALÃO DE 80 mm COMO PÁGINA.

    Existe para o balcão em React poder mostrá-lo dentro de um `iframe` e
    mandá-lo imprimir sem abrir separador nenhum. O corpo é a MESMA parcial
    que o modal do POS em Livewire inclui — o talão desenha-se num sítio só.

    CARREGA O TAILWIND E O FONT AWESOME, e isso não é enfeite: o corpo do
    talão está escrito com as classes deles (`flex justify-between`,
    `text-right`, `border-t`…). Sem essa folha, as colunas do QTD/PREÇO/
    SUBTOTAL encostam-se todas à esquerda e o RESUMO FISCAL perde a coluna
    dos valores — foi o que aconteceu à primeira, e um talão com outra forma
    é um documento fiscal diferente do que a AGT certificou.

    São os MESMOS ficheiros locais que o layout da aplicação usa. Nada vem de
    fora: um talão que dependesse da internet era um talão que falta quando a
    loja fica sem rede.

    `?imprimir=1` manda a janela imprimir assim que as imagens carregarem — o
    logótipo e o QR.
--}}
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>

    {{-- As MESMAS folhas do layout da aplicação, para o papel sair igual. --}}
    <script src="/vendor/js/tailwind.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">

    @php
        // 80 mm (balcão) ou 58 mm (máquinas portáteis como a Sunmi V2s).
        $largura = (int) ($largura ?? 80) === 58 ? 58 : 80;
    @endphp
    <style>
        /* O PAPEL. A largura é a da bobine; o comprimento é o que for. */
        @page { size: {{ $largura }}mm auto; margin: 0; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }

        @media print {
            html, body { background: #fff; }

            /* Na impressora a folha ocupa a bobine inteira e perde a sombra. */
            #ticket-print {
                width: {{ $largura }}mm !important;
                max-width: {{ $largura }}mm !important;
                margin: 0 !important;
                /* 58 mm de rolo imprimem 48 mm: 5 mm de cada lado. */
                padding: {{ $largura === 58 ? '3mm 5mm' : '4mm' }} !important;
                box-shadow: none !important;
                overflow: visible !important;
            }
        }

        /*
         * O ROLO DE 58 mm (28/09/2026). Nos 48 mm impressos, uma rubrica e o
         * seu valor nem sempre cabem na mesma linha: o valor desce para a de
         * baixo, encostado à direita, em vez de se sobrepor ao texto.
         */
        .papel-58 .flex.justify-between { flex-wrap: wrap; column-gap: 6px; }
        .papel-58 .flex.justify-between > span:last-child { margin-left: auto; text-align: right; }
        .papel-58 .text-xs { font-size: 11px; line-height: 1.3; }

        @media screen {
            #ticket-print {
                box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
                margin-top: 12px !important;
                margin-bottom: 12px !important;
            }
        }
    </style>
</head>
<body>
    {{-- O MESMO invólucro do modal do POS, com as mesmas medidas: 480px de
         largura, 16px de folga, Ubuntu a 14px e tinta preta. Mudar qualquer
         um destes números dava um talão diferente do que já se imprime. --}}
    {{-- A 58 mm o invólucro já tem a largura do rolo no ecrã: o que se vê é
         o que a máquina imprime. --}}
    <div id="ticket-print" class="ticket-thermal papel-{{ $largura }}" style="width: {{ $largura === 58 ? '58mm' : '480px' }}; max-width: 100%; margin: 0 auto; padding: {{ $largura === 58 ? '3mm 5mm' : '16px' }}; background: #fff; font-family: 'Ubuntu', sans-serif; font-size: {{ $largura === 58 ? '11px' : '14px' }}; color: #000;">
        @include('pdf.invoicing._talao-corpo', ['invoice' => $invoice, 'largura' => $largura])
    </div>

    @if(request()->boolean('imprimir'))
    <script>
        /*
         * IMPRIMIR SÓ DEPOIS DAS IMAGENS.
         *
         * O logótipo e o QR da AGT são imagens, e o `window.print()` não
         * espera por elas: abrir a caixa de impressão cedo demais dava um
         * talão com o cabeçalho em branco.
         */
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    </script>
    @endif
</body>
</html>
