{{--
    Página que os clientes veem enquanto o sistema está em manutenção.

    CSS embutido de propósito: esta página tem de aparecer bem mesmo quando o
    que está em baixo é precisamente o resto. Sem ficheiros externos, sem CDN,
    sem base de dados — nada aqui depende de nada.
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @isset($exception)
        @if($exception->getHeaders()['Retry-After'] ?? null)
            <meta http-equiv="refresh" content="{{ $exception->getHeaders()['Retry-After'] }}">
        @endif
    @endisset
    <title>Manutenção · SOS ERP</title>
    @include('partials.favicon')
    <style>
        *{ box-sizing:border-box; margin:0; padding:0; }
        body{
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,#0f172a 0%,#1e293b 55%,#134e4a 100%);
            color:#e2e8f0; padding:24px; line-height:1.6;
        }
        .cartao{
            max-width:520px; width:100%; text-align:center;
            background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.10);
            border-radius:20px; padding:40px 32px; backdrop-filter:blur(8px);
        }
        .icone{
            width:76px; height:76px; margin:0 auto 22px; border-radius:20px;
            background:linear-gradient(135deg,#059669,#10b981);
            display:flex; align-items:center; justify-content:center; font-size:34px;
        }
        h1{ font-size:24px; font-weight:700; color:#fff; margin-bottom:12px; }
        p{ color:#94a3b8; font-size:15px; margin-bottom:14px; }
        .nota{
            margin-top:24px; padding-top:20px; border-top:1px solid rgba(255,255,255,.10);
            font-size:13px; color:#64748b;
        }
        .marca{ margin-top:8px; font-weight:700; color:#10b981; letter-spacing:.5px; }
        a{ color:#34d399; text-decoration:none; }
        a:hover{ text-decoration:underline; }
    </style>
</head>
<body>
    <div class="cartao">
        <div class="icone">🔧</div>

        <h1>Estamos em manutenção</h1>

        <p>
            O sistema está a ser actualizado neste momento. É uma paragem curta e
            planeada — os seus dados estão seguros e nada se perde.
        </p>

        <p>Volte dentro de alguns minutos.</p>

        <div class="nota">
            Se precisar de ajuda urgente, fale connosco por
            <a href="mailto:geral@soserp.vip">geral@soserp.vip</a>.
            <div class="marca">SOS ERP</div>
        </div>
    </div>
</body>
</html>
