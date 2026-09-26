@php
    $metaPixelId = \App\Models\SystemSetting::get('facebook_pixel_id');

    /*
     * A INSCRIÇÃO CONCLUÍDA, para o CompleteRegistration (26/09/2026).
     *
     * Fica na sessão desde que a empresa nasce (RegistarEmpresa) até uma página
     * com o pixel e COM consentimento de marketing a usar — aí sai da sessão, e
     * recarregar já não repete nada. Sem consentimento fica guardada (a pessoa
     * pode aceitar mais à frente) e vai no script à espera dele; com mais de 24
     * horas deixa de valer. O eventID é determinístico por empresa: se chegar
     * a sair duas vezes, a Meta deduplica.
     */
    $inscricao = session('meta_registration_completed');
    if ($inscricao && (now()->timestamp - (int) ($inscricao['em'] ?? now()->timestamp)) > 86400) {
        session()->forget('meta_registration_completed');
        $inscricao = null;
    }
    if ($inscricao && !empty($metaPixelId) && \App\Services\Privacidade\Consentimentos::permite('marketing')) {
        session()->forget('meta_registration_completed');
    }
@endphp
@if(!empty($metaPixelId))
    <!-- Meta Pixel: cadastro e conversão — só com consentimento de marketing
         (acordado por partials/consentimento; sem ele não sai nada para a Meta).
         A guarda `__sosMetaPixel` evita init/PageView repetidos se este bloco
         aparecer duas vezes na mesma página. -->
    <script type="text/plain" data-consentimento="marketing">
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
        (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');

        if (!window.__sosMetaPixel) {
            window.__sosMetaPixel = true;
            fbq('init', @json((string) $metaPixelId));
            fbq('track', 'PageView');

            @if($inscricao)
                // Só depois de a empresa estar gravada. O eventID é
                // determinístico por empresa: a Meta deduplica repetições.
                fbq('track', 'CompleteRegistration', {
                    content_name: @json($inscricao['plan'] ?? null),
                    status: @json($inscricao['status'] ?? null),
                    currency: 'USD',
                    value: 0
                }, {
                    eventID: @json($inscricao['event_id'] ?? null)
                });
            @endif
        }
    </script>

@endif
