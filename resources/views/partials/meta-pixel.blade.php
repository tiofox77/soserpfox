@php($metaPixelId = \App\Models\SystemSetting::get('facebook_pixel_id'))
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

            @if(session('meta_registration_completed'))
                // Só depois de a inscrição estar gravada (flash de uma requisição).
                // O eventID é determinístico por empresa: recarregar ou reenviar
                // repete o mesmo id e a Meta deduplica.
                fbq('track', 'CompleteRegistration', {
                    content_name: @json(session('meta_registration_completed.plan')),
                    status: @json(session('meta_registration_completed.status')),
                    currency: 'USD',
                    value: 0
                }, {
                    eventID: @json(session('meta_registration_completed.event_id'))
                });
            @endif
        }
    </script>

@endif
