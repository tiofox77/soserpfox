{{-- O AVISO DE COOKIES (RGPD / ePrivacy / LGPD / Lei 22/11).
     Uma inclusão por página pública. Os scripts de terceiros da página ficam
     como type="text/plain" data-consentimento="…" e é o consentimento.js que os
     acorda. `$silencioso` = não mostrar o aviso (dentro do ERP), mas activar o
     que já foi aceite e deixar abrir as preferências. --}}
@php
    $configDoConsentimento = [
        'versao' => config('privacidade.versao'),
        'cookie' => config('privacidade.cookie'),
        'dias' => (int) config('privacidade.cookie_dias', 180),
        'endpoint' => route('privacidade.consentimento'),
        'politica' => route('legal.privacidade'),
        'paginaCookies' => route('legal.cookies'),
        'silencioso' => (bool) ($silencioso ?? false),
        'categorias' => collect(config('privacidade.categorias'))->map(fn ($c) => [
            'nome' => __($c['nome']),
            'descricao' => __($c['descricao']),
            'cookies' => collect($c['cookies'])->map(fn ($k) => ['nome' => $k['nome'], 'finalidade' => __($k['finalidade']), 'duracao' => __($k['duracao'])])->all(),
        ])->all(),
        'textos' => [
            'titulo' => __('A sua privacidade, a sua escolha'),
            'frase' => __('Usamos cookies necessários para o site funcionar. Com a sua autorização, usamos também cookies de estatísticas (páginas vistas, cidade aproximada pelo IP) e de marketing (Meta e Google). Pode mudar de ideias a qualquer momento.'),
            'politica' => __('Política de Privacidade'),
            'cookies' => __('Cookies'),
            'aceitar' => __('Aceitar tudo'),
            'recusar' => __('Só os necessários'),
            'escolher' => __('Escolher'),
            'preferencias' => __('Preferências de privacidade'),
            'explica' => __('Escolha o que autoriza. Os necessários não se desligam: sem eles não é possível entrar nem enviar formulários.'),
            'guardar' => __('Guardar escolha'),
            'fechar' => __('Fechar'),
            'sempre' => __('Sempre activos'),
            'verCookies' => __('Ver os cookies'),
            'nome' => __('Nome'),
            'finalidade' => __('Para quê'),
            'duracao' => __('Duração'),
        ],
    ];
@endphp
<script type="application/json" id="sos-consentimento-config">@json($configDoConsentimento)</script>
<script src="{{ asset('js/consentimento.js') }}?v=1" defer></script>
