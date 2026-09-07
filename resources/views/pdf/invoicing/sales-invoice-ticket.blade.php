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

    <style>
        /* O PAPEL. 80 mm é a largura da bobine; o comprimento é o que for. */
        @page { size: 80mm auto; margin: 0; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }

        @media print {
            html, body { background: #fff; }

            /* Na impressora a folha ocupa a bobine inteira e perde a sombra. */
            #ticket-print {
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 4mm !important;
                box-shadow: none !important;
                overflow: visible !important;
            }
        }

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
    <div id="ticket-print" class="ticket-thermal" style="width: 480px; max-width: 100%; margin: 0 auto; padding: 16px; background: #fff; font-family: 'Ubuntu', sans-serif; font-size: 14px; color: #000;">
        @include('pdf.invoicing._talao-corpo', ['invoice' => $invoice])
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
