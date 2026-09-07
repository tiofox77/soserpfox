<!DOCTYPE html>
{{--
    O TALÃO DE 80 mm COMO PÁGINA.

    Existe para o balcão em React poder mostrá-lo dentro de um `iframe` — e
    poder mandá-lo imprimir sem o operador ter de abrir separador nenhum. O
    corpo é a MESMA parcial que o modal do POS em Livewire inclui: o talão
    desenha-se num sítio só.

    `?imprimir=1` manda a janela imprimir assim que as imagens carregarem (o
    logótipo e o QR). Sem esperar por elas, o Chrome abre a caixa de impressão
    com um talão sem cabeçalho.
--}}
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>

    <style>
        /* O PAPEL. 80 mm é a largura da bobine; o comprimento é o que for. */
        @page { size: 80mm auto; margin: 0; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: 'Ubuntu', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        .folha {
            width: 480px;
            max-width: 100%;
            margin: 0 auto;
            padding: 16px;
            background: #fff;
            font-size: 14px;
            color: #000;
        }

        /* As classes utilitárias que o corpo do talão usa. O talão não pode
           depender do Tailwind: é uma página que se abre sozinha, e uma folha
           de estilos que falhe deixa o documento fiscal sem forma. */
        .font-bold { font-weight: 700; }
        .font-mono { font-family: ui-monospace, 'Courier New', monospace; }
        .text-center { text-align: center; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .break-all { word-break: break-all; }
        .text-\[8px\] { font-size: 8px; }

        @media print {
            html, body { background: #fff; }
            .folha { width: 80mm; max-width: 80mm; margin: 0; padding: 4mm; box-shadow: none; }
            .nao-imprime { display: none !important; }
        }

        @media screen {
            .folha { box-shadow: 0 10px 30px rgba(15, 23, 42, .12); margin-top: 12px; margin-bottom: 12px; }
        }
    </style>
</head>
<body>
    <div class="folha">
        @include('pdf.invoicing._talao-corpo', ['invoice' => $invoice])
    </div>

    @if(request()->boolean('imprimir'))
    <script>
        /*
         * IMPRIMIR SÓ DEPOIS DAS IMAGENS.
         *
         * O logótipo e o QR da AGT são imagens, e o `window.print()` não
         * espera por elas: abrir a caixa de impressão cedo demais dava um
         * talão com o cabeçalho em branco. Espera-se pelo `load` da janela e
         * ainda um instante, que é o que chega para o desenho assentar.
         */
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
    @endif
</body>
</html>
