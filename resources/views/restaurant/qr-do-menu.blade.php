<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('QR da carta') }} — {{ $definicoes->menu_title ?: __('Restaurante') }}</title>

    {{-- ESTILO EMBUTIDO, e nada de fora.
         Isto é para IMPRIMIR: uma folha que espera por um ficheiro CSS de um
         servidor sai com o desenho todo desfeito quando o diálogo de impressão
         abre primeiro. Aqui não há nada por que esperar. --}}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 24px;
        }

        .barra {
            max-width: 900px; margin: 0 auto 24px; display: flex; gap: 12px;
            align-items: center; justify-content: space-between; flex-wrap: wrap;
        }

        .barra h1 { font-size: 20px; }
        .barra p { color: #64748b; font-size: 13px; margin-top: 4px; }

        .botao {
            background: #ea580c; color: #fff; border: 0; border-radius: 12px;
            padding: 11px 22px; font-size: 14px; font-weight: 700; cursor: pointer;
        }

        .grelha {
            max-width: 900px; margin: 0 auto;
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px;
        }

        .cartao {
            background: #fff; border: 2px dashed #cbd5e1; border-radius: 18px;
            padding: 22px; text-align: center;
            /* Um cartão cortado a meio por uma quebra de página é papel
               deitado fora. Isto mantém cada um inteiro. */
            break-inside: avoid; page-break-inside: avoid;
        }

        .cartao h2 { font-size: 22px; margin-bottom: 2px; }
        .cartao .zona { color: #64748b; font-size: 12px; margin-bottom: 14px; }
        .cartao img { width: 100%; max-width: 260px; height: auto; }
        .cartao .url { color: #94a3b8; font-size: 10px; margin-top: 10px; word-break: break-all; }
        .cartao .instrucao { margin-top: 12px; font-size: 13px; font-weight: 600; color: #334155; }

        @media print {
            body { background: #fff; padding: 0; }
            .barra { display: none; }
            .grelha { gap: 0; }
            .cartao { border-radius: 0; margin: 0; padding: 28px 18px; }
        }
    </style>
</head>
<body>

    <div class="barra">
        <div>
            <h1>{{ __('QR da carta') }}</h1>
            <p>{{ __('Corte pelos tracejados e cole em cada mesa. O cliente aponta a câmara e a carta abre já com a mesa identificada.') }}</p>
        </div>
        <button class="botao" onclick="window.print()">{{ __('Imprimir') }}</button>
    </div>

    <div class="grelha">
        @foreach($cartoes as $cartao)
            <div class="cartao">
                <h2>{{ $cartao['titulo'] }}</h2>
                <p class="zona">{{ $cartao['subtitulo'] }}</p>
                <img src="{{ $cartao['imagem'] }}" alt="{{ $cartao['titulo'] }}">
                <p class="instrucao">{{ __('Aponte a câmara do telemóvel') }}</p>
                <p class="url">{{ $cartao['url'] }}</p>
            </div>
        @endforeach
    </div>

</body>
</html>
