<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @php
        $canonical = $settings['seo_canonical_url'] ?? $settings['schema_app_url'] ?? 'https://soserp.vip';
        $appName = $settings['app_name'] ?? 'SOS ERP';
        $seoTitle = $settings['seo_title'] ?? 'SOS ERP — Software de Gestão Empresarial em Angola | Faturação Certificada AGT';
        // Os sectores entram na descrição e nas palavras-chave porque é assim
        // que um lojista pesquisa: escreve "software para farmácia", não "ERP".
        // O ?? mantém-se — o que estiver nas definições manda sempre, isto é só
        // o que se mostra a quem nunca lá mexeu.
        // "todas as 18 províncias" saiu daqui: a divisão administrativa do país
        // mudou e um número fixo numa promessa de cobertura envelhece sozinho.
        // O que se promete é servir o país inteiro, não contar províncias.
        $seoDesc = $settings['seo_description'] ?? 'Software de gestão 100% angolano: faturação certificada pela AGT, POS, stock e RH com IRT/INSS. Perfis para farmácia, loja de roupa, cosmética e mercearia, além de hotelaria, salão e oficina. Atende Luanda, Benguela, Huíla, Cabinda e todo o território nacional. Comece grátis hoje.';
        $seoKw = $settings['seo_keywords'] ?? 'ERP Angola, software gestão Angola, faturação AGT, faturação certificada Angola, sistema gestão Luanda, ERP Luanda, software contabilidade Angola, POS Angola, gestão RH Angola, folha pagamento Angola, IRT INSS, software hotel Angola, gestão salão beleza Luanda, oficina auto Angola, sistema multi-empresa, ERP em kwanzas, SAFT-AO, ERP cloud Angola, gestão empresarial Benguela, software Cabinda, Huíla gestão, Huambo software, sistema POS Talatona, software Lobito, software para farmácia em Angola, programa de facturação para loja de roupa, software para loja de cosmética, programa para mercearia e minimercado, gestão de lotes e validade, controlo de prazos de validade, software para boutique Luanda, POS para minimercado Angola';
        $brandLogo = asset('brand/soserp-logo-square-512.png');
        $ogImage = !empty($settings['seo_og_image']) ? asset('storage/' . $settings['seo_og_image']) : asset('brand/soserp-og-1200x630.png');
        $favicon = asset('favicon.ico');
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    <meta name="keywords" content="{{ $seoKw }}">
    <meta name="author" content="{{ $settings['seo_author'] ?? $settings['schema_creator_name'] ?? 'SOSERP — Softec Angola' }}">
    <meta name="robots" content="{{ $settings['seo_robots'] ?? 'index, follow' }}, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="{{ $settings['seo_robots'] ?? 'index, follow' }}">
    <meta name="bingbot" content="index, follow">
    <meta name="rating" content="general">
    <meta name="revisit-after" content="3 days">
    <meta name="distribution" content="global">
    <meta name="copyright" content="© {{ date('Y') }} {{ $appName }} — Softec Angola">
    <meta name="msapplication-TileColor" content="#2563eb">
    @if(!empty($settings['google_site_verification']))
    <meta name="google-site-verification" content="{{ $settings['google_site_verification'] }}">
    @endif
    @if(!empty($settings['bing_site_verification']))
    <meta name="msvalidate.01" content="{{ $settings['bing_site_verification'] }}">
    @endif

    {{-- Geo Tags — Angola (Luanda, Talatona) --}}
    <meta name="language" content="Portuguese">
    <meta http-equiv="content-language" content="pt-AO">
    <meta name="geo.region" content="AO">
    <meta name="geo.country" content="Angola">
    <meta name="geo.placename" content="Luanda, Talatona, Angola">
    <meta name="geo.position" content="-8.838333;13.234444">
    <meta name="ICBM" content="-8.838333, 13.234444">

    {{-- hreflang --}}
    <link rel="alternate" hreflang="pt-AO" href="{{ $canonical }}">
    <link rel="alternate" hreflang="pt-PT" href="{{ $canonical }}">
    <link rel="alternate" hreflang="pt" href="{{ $canonical }}">
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">

    {{-- Open Graph — Facebook, WhatsApp, LinkedIn --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDesc }}">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:locale" content="pt_AO">
    <meta property="og:locale:alternate" content="pt_PT">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $appName }} — Software de Gestão Empresarial em Angola">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDesc }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $appName }} dashboard">
    <meta name="twitter:site" content="@soserp_angola">

    @include('partials.favicon')

    <link rel="canonical" href="{{ $canonical }}">
    {{-- O manifest é servido pelo PwaController; o manifest.json estático foi
         apagado e este link dava 404 em todas as visitas à página inicial. --}}
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <link rel="sitemap" type="application/xml" href="/sitemap.xml">
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- v=2: o recolector passou a medir o tempo em página e a registar
         pesquisas. Sem subir a versão, os browsers serviam o antigo da cache. --}}
    <script src="{{ asset('js/sos-tracker.js') }}?v=2" defer></script>

    {{-- JSON-LD: Organization + LocalBusiness Angola --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Organization",
        "@id": "{{ $canonical }}#organization",
        "name": "{{ $appName }}",
        "alternateName": ["SOSERP", "SOS ERP Angola", "Softec Angola"],
        "url": "{{ $canonical }}",
        "logo": {
            "@type": "ImageObject",
            "url": "{{ $brandLogo }}",
            "contentUrl": "{{ $brandLogo }}",
            "width": 512,
            "height": 512
        },
        "image": "{{ $ogImage }}",
        "description": "{{ $seoDesc }}",
        "foundingDate": "2024",
        "founders": [{"@type": "Person", "name": "Softec Angola"}],
        "slogan": "Software de gestão 100% angolano, certificado AGT",
        "areaServed": [
            {"@type": "Country", "name": "Angola"},
            {"@type": "AdministrativeArea", "name": "Luanda"},
            {"@type": "AdministrativeArea", "name": "Benguela"},
            {"@type": "AdministrativeArea", "name": "Huíla"},
            {"@type": "AdministrativeArea", "name": "Huambo"},
            {"@type": "AdministrativeArea", "name": "Cabinda"},
            {"@type": "AdministrativeArea", "name": "Bié"},
            {"@type": "AdministrativeArea", "name": "Cuanza Sul"},
            {"@type": "AdministrativeArea", "name": "Cuanza Norte"},
            {"@type": "AdministrativeArea", "name": "Cunene"},
            {"@type": "AdministrativeArea", "name": "Lunda Norte"},
            {"@type": "AdministrativeArea", "name": "Lunda Sul"},
            {"@type": "AdministrativeArea", "name": "Malanje"},
            {"@type": "AdministrativeArea", "name": "Moxico"},
            {"@type": "AdministrativeArea", "name": "Namibe"},
            {"@type": "AdministrativeArea", "name": "Uíge"},
            {"@type": "AdministrativeArea", "name": "Zaire"},
            {"@type": "AdministrativeArea", "name": "Bengo"},
            {"@type": "AdministrativeArea", "name": "Cuando Cubango"}
        ],
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Talatona, Rua Principal",
            "addressLocality": "Luanda",
            "addressRegion": "Luanda",
            "postalCode": "0000",
            "addressCountry": "AO"
        },
        "geo": {"@type": "GeoCoordinates", "latitude": -8.838333, "longitude": 13.234444},
        "contactPoint": [{
            "@type": "ContactPoint",
            "telephone": "+244-939-779-902",
            "email": "comercial@soserp.vip",
            "contactType": "sales",
            "areaServed": "AO",
            "availableLanguage": ["Portuguese", "pt-AO"]
        }, {
            "@type": "ContactPoint",
            "telephone": "+244-939-779-902",
            "email": "suporte@soserp.vip",
            "contactType": "customer support",
            "areaServed": "AO",
            "availableLanguage": ["Portuguese"]
        }],
        "sameAs": [
            "https://www.facebook.com/soserp",
            "https://www.linkedin.com/company/soserp",
            "https://www.instagram.com/soserp_angola"
        ]
    }
    </script>

    {{-- JSON-LD: SoftwareApplication --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "@id": "{{ $canonical }}#software",
        "name": "{{ $appName }}",
        "operatingSystem": "Web, Windows, macOS, Linux, Android, iOS",
        "applicationCategory": "BusinessApplication",
        "applicationSubCategory": "Enterprise Resource Planning",
        "softwareVersion": "2.0",
        "inLanguage": "pt-AO",
        "url": "{{ $canonical }}",
        "image": "{{ $ogImage }}",
        "description": "ERP completo certificado pela AGT Angola: faturação eletrónica, SAFT-AO, POS offline, RH com IRT/INSS, hotelaria, salão de beleza e oficina auto.",
        "publisher": {"@id": "{{ $canonical }}#organization"},
        {{-- Os planos saem da tabela, como já saem na FAQ e na secção #planos.
             Estavam aqui "Starter 15000, Business 35000, Enterprise 75000" —
             os mesmos números falsos que já tinham sido retirados da FAQ, mas
             que continuavam a ser servidos ao Google neste bloco. --}}
        @if($plans->isNotEmpty())
        "offers": [
@foreach($plans as $planoSchema)
            {"@type": "Offer", "name": "{{ $planoSchema->name }}", "price": "{{ (int) $planoSchema->price_monthly }}", "priceCurrency": "AOA", "priceValidUntil": "{{ date('Y-12-31') }}", "availability": "https://schema.org/InStock", "url": "{{ $canonical }}#planos"}@if(!$loop->last),@endif

@endforeach
        ],
        @endif
        {{-- Não há aqui "aggregateRating": estava declarada uma média de 4,8 em
             127 avaliações e o sistema não tem sequer onde as recolher. O
             Google mostra estrelas com base nisto, e são estrelas que ninguém
             pode provar. Volta quando existirem avaliações reais para contar. --}}
        {{-- Público-alvo declarado por sector: é o que responde à pesquisa
             "isto serve para a minha farmácia?" antes de alguém abrir a
             página. Só entram sectores que têm mesmo perfil ou módulo. --}}
        "audience": [
            {"@type": "BusinessAudience", "audienceType": "Farmácias e parafarmácias"},
            {"@type": "BusinessAudience", "audienceType": "Lojas de roupa, calçado e boutiques"},
            {"@type": "BusinessAudience", "audienceType": "Lojas de cosmética e perfumaria"},
            {"@type": "BusinessAudience", "audienceType": "Mercearias, minimercados e supermercados"},
            {"@type": "BusinessAudience", "audienceType": "Hotéis e alojamentos"},
            {"@type": "BusinessAudience", "audienceType": "Salões de beleza"},
            {"@type": "BusinessAudience", "audienceType": "Oficinas auto"},
            {"@type": "BusinessAudience", "audienceType": "Restaurantes"}
        ],
        "featureList": [
            "Faturação Certificada AGT ({{ \App\Helpers\AGTHelper::softwareValidationNumber() }})",
            "SAFT-AO mensal automático",
            "POS funciona offline",
            "Folha de pagamento angolana (IRT + INSS)",
            "Multi-empresa e multi-utilizador",
            "Gestão de stock e inventário",
            "Perfis de negócio: farmácia, vestuário, cosmética e mercearia",
            "Farmácia: aviso de receita médica e de psicotrópico no POS",
            "Lotes e validades com saída FIFO pela data de expiração",
            "Cosmética: meses após abertura (PAO) e lista INCI",
            "Mercearia: conservação, alergénios e país de origem",
            "Hotelaria: booking engine, channel manager",
            "Salão de Beleza: agendamento e comissões",
            "Oficina Auto: ordens de reparação",
            "Servidores em Angola — baixa latência"
        ]
    }
    </script>

    {{-- JSON-LD: WebSite com SearchAction --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "WebSite",
        "@id": "{{ $canonical }}#website",
        "url": "{{ $canonical }}",
        "name": "{{ $appName }}",
        "description": "{{ $seoDesc }}",
        "inLanguage": "pt-AO",
        "publisher": {"@id": "{{ $canonical }}#organization"},
        "potentialAction": {
            "@type": "SearchAction",
            "target": {"@type": "EntryPoint", "urlTemplate": "{{ $canonical }}/?q={search_term_string}"},
            "query-input": "required name=search_term_string"
        }
    }
    </script>

    {{-- JSON-LD: FAQ (rich snippet) --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {"@type": "Question", "name": "O software é certificado pela AGT Angola?",
             "acceptedAnswer": {"@type": "Answer", "text": "Sim. SOSERP tem certificação oficial da Administração Geral Tributária de Angola ({{ \App\Helpers\AGTHelper::softwareValidationNumber() }}) para faturação eletrónica e geração de SAFT-AO."}},
            {"@type": "Question", "name": "Funciona em todo o território de Angola?",
             {{-- Sem contagem de províncias, pela razão explicada no $seoDesc. --}}
             "acceptedAnswer": {"@type": "Answer", "text": "Sim. Atendemos todo o território nacional — Luanda, Benguela, Huíla, Huambo, Cabinda, Cuanza Norte, Cuanza Sul, Bié, Cunene, Lunda Norte, Lunda Sul, Malanje, Moxico, Namibe, Uíge, Zaire, Bengo e as restantes províncias. Servidores em Luanda garantem baixa latência."}},
            {"@type": "Question", "name": "Posso emitir faturas mesmo sem internet?",
             "acceptedAnswer": {"@type": "Answer", "text": "Sim. O POS SOSERP funciona 100% offline e sincroniza automaticamente quando recupera ligação à internet — ideal para Angola onde a conectividade pode falhar."}},
            {{-- Os preços saem dos planos, não da cabeça de quem escreveu isto.
                 Estava aqui "15.000 Kz (Starter), 35.000 (Business), 75.000
                 (Enterprise)" — números que nunca existiram em plano nenhum, e
                 que o Google mostrava nos resultados de pesquisa. --}}
            @php
                $planoDeEntrada = $plans->where('price_monthly', '>', 0)->sortBy('price_monthly')->first();
                $planoDeTopo    = $plans->sortByDesc('price_monthly')->first();
                $diasDeTeste    = (int) ($planoDeEntrada->trial_days ?? 14);
            @endphp
            {"@type": "Question", "name": "Quanto custa o SOSERP em Kwanzas?",
             "acceptedAnswer": {"@type": "Answer", "text": "Os planos começam em {{ number_format($planoDeEntrada->price_monthly ?? 0, 0, ',', '.') }} Kz/mês e vão até {{ number_format($planoDeTopo->price_monthly ?? 0, 0, ',', '.') }} Kz/mês. Todos incluem {{ $diasDeTeste }} dias gratuitos, sem cartão de crédito."}},
            {"@type": "Question", "name": "Calcula IRT e INSS automaticamente?",
             "acceptedAnswer": {"@type": "Answer", "text": "Sim. O módulo de RH calcula automaticamente o Imposto sobre o Rendimento do Trabalho (IRT) e contribuições para o INSS conforme a legislação angolana atualizada."}},
            {"@type": "Question", "name": "É possível gerir várias empresas com uma só conta?",
             "acceptedAnswer": {"@type": "Answer", "text": "Sim. SOSERP é multi-empresa nativo. Pode gerir holdings, grupos empresariais e franquias com utilizadores e permissões granulares."}},
            {"@type": "Question", "name": "Serve para farmácia, loja de roupa, cosmética ou mercearia?",
             "acceptedAnswer": {"@type": "Answer", "text": "Sim. Nas definições de faturação liga o perfil do seu negócio e a ficha do produto passa a mostrar os campos desse sector: receita médica, substância activa e n.º ARMED na farmácia; tamanho, cor e composição no vestuário; meses após abertura (PAO), lista INCI e conteúdo líquido na cosmética; conservação, alergénios e país de origem na mercearia. Pode ligar mais do que um perfil ao mesmo tempo — uma mercearia com balcão de farmácia é as duas coisas."}}
        ]
    }
    </script>
    
    @if(!empty($settings['google_analytics_id']))
    <!-- Google Analytics (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $settings['google_analytics_id'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $settings['google_analytics_id'] }}');
    </script>
    @endif
    
    @if(!empty($settings['gtm_id']))
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $settings['gtm_id'] }}');</script>
    @endif
    
    @if(!empty($settings['facebook_pixel_id']))
    <!-- Facebook Pixel -->
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ $settings['facebook_pixel_id'] }}');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $settings['facebook_pixel_id'] }}&ev=PageView&noscript=1"/></noscript>
    @endif
    
    <!-- Preconnect para Performance -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "SoftwareApplication",
      "name": "{{ $settings['schema_app_name'] ?? 'SOSERP' }}",
      "description": "{{ $settings['schema_app_description'] ?? 'Sistema de Gestão Empresarial Multi-Tenant para empresas em Angola' }}",
      "url": "{{ $settings['schema_app_url'] ?? 'https://soserp.vip' }}",
      "applicationCategory": "{{ $settings['schema_app_category'] ?? 'BusinessApplication' }}",
      "operatingSystem": "Web",
      @if($settings['app_logo'])
      "image": "{{ asset('storage/' . $settings['app_logo']) }}",
      @endif
      "offers": {
        "@@type": "Offer",
        "price": "{{ $settings['schema_price'] ?? '0' }}",
        "priceCurrency": "{{ $settings['schema_currency'] ?? 'AOA' }}",
        "availability": "https://schema.org/InStock",
        "eligibleRegion": {
          "@@type": "Place",
          "name": "{{ $settings['schema_region'] ?? 'Angola' }}"
        }
      },
      {{-- Só sai se alguém tiver mesmo escrito uma nota e um número de
           avaliações nas definições. Vinha com 4,8 em 150 avaliações por
           omissão — ninguém as contou, e uma estrela inventada no resultado
           de pesquisa é pior do que resultado nenhum. --}}
      @if(filled($settings['schema_rating_value'] ?? null) && filled($settings['schema_review_count'] ?? null))
      "aggregateRating": {
        "@@type": "AggregateRating",
        "ratingValue": "{{ $settings['schema_rating_value'] }}",
        "reviewCount": "{{ $settings['schema_review_count'] }}"
      },
      @endif
      "creator": {
        "@@type": "Organization",
        "name": "{{ $settings['schema_creator_name'] ?? 'SOSERP' }}",
        "url": "{{ $settings['schema_creator_url'] ?? 'https://soserp.vip' }}"
      }
    }
    </script>
    
    <!-- Prevenir FOUC: Força tamanhos de imagem antes de qualquer script -->
    <style>
        /* Crítico: carrega ANTES de qualquer framework */
        img[src*="/storage/"] {
            max-height: 80px !important;
            object-fit: contain !important;
        }
        nav img {
            max-height: 80px !important;
            height: 80px !important;
            width: auto !important;
        }
        footer img {
            max-height: 3rem !important;
            height: 3rem !important;
            width: auto !important;
        }
    </style>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-white">
    
    <!-- Navigation -->
    <nav class="bg-white shadow-sm fixed w-full top-0 z-50 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center" style="padding: 10px 0;">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="height: 80px; max-height: 80px;" class="w-auto object-contain">
                        @else
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">{{ app_name() }}</span>
                        @endif
                    </div>
                    <div class="hidden lg:ml-8 lg:flex lg:space-x-1 xl:space-x-3">
                        <a href="#recursos" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Recursos</a>
                        <a href="#sectores" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Sectores</a>
                        <a href="#certificacao" class="text-green-700 hover:text-green-600 px-2 py-2 text-sm font-medium transition inline-flex items-center gap-1 whitespace-nowrap">
                            <i class="fas fa-shield-alt text-xs"></i> Certificação AGT
                        </a>
                        <a href="#modulos" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Módulos</a>
                        <a href="#planos" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Planos</a>
                        <a href="#roadmap" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Roadmap</a>
                        <a href="#contacto" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Contacto</a>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('client.login') }}" class="hidden xl:inline-flex items-center text-purple-700 hover:text-purple-800 text-sm font-medium transition px-2 py-2 whitespace-nowrap" title="Portal do Cliente">
                        <i class="fas fa-users mr-1"></i>Área Cliente
                    </a>
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center text-gray-700 hover:text-blue-600 text-sm font-medium transition px-2 py-2 whitespace-nowrap">
                        <i class="fas fa-sign-in-alt mr-1"></i>Entrar
                    </a>
                    <a href="{{ route('register') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl text-sm font-semibold hover:shadow-lg transition px-4 py-2.5 whitespace-nowrap inline-flex items-center">
                        <i class="fas fa-rocket mr-2"></i>Começar Grátis
                    </a>

                    {{-- Hamburger mobile --}}
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="lg:hidden text-gray-700 hover:text-blue-600 ml-1 p-2">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div id="mobile-menu" class="hidden lg:hidden border-t border-gray-200 py-3">
                <div class="flex flex-col gap-1 text-sm font-medium">
                    <a href="#recursos" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Recursos</a>
                    <a href="#sectores" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Sectores</a>
                    <a href="#certificacao" class="text-green-700 hover:bg-green-50 px-3 py-2 rounded-lg"><i class="fas fa-shield-alt text-xs mr-1"></i>Certificação AGT</a>
                    <a href="#modulos" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Módulos</a>
                    <a href="#planos" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Planos</a>
                    <a href="#roadmap" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Roadmap</a>
                    <a href="#contacto" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Contacto</a>
                    <a href="{{ route('client.login') }}" class="text-purple-700 hover:bg-purple-50 px-3 py-2 rounded-lg"><i class="fas fa-users mr-1"></i>Área Cliente</a>
                    <a href="{{ route('login') }}" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg"><i class="fas fa-sign-in-alt mr-1"></i>Entrar</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pb-20 bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50" style="padding-top: 120px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <a href="#certificacao" class="inline-flex items-center gap-2 px-4 py-2 bg-green-100 hover:bg-green-200 text-green-800 rounded-full text-sm font-bold mb-4 border border-green-300 transition shadow-sm">
                        <i class="fas fa-shield-check"></i>
                        <span>Certificado AGT Angola — {{ \App\Helpers\AGTHelper::softwareValidationNumber() }}</span>
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <h1 class="text-5xl md:text-6xl font-bold text-gray-900 leading-tight mb-6">
                        Gestão Empresarial
                        <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Completa</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8">
                        Sistema completo de gestão empresarial multi-empresa com faturação, inventário, RH e muito mais. 
                        Tudo que sua empresa precisa em um só lugar.
                    </p>
                    <div class="flex space-x-4">
                        <a href="{{ route('register') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:shadow-xl transition">
                            <i class="fas fa-rocket mr-2"></i>Começar Agora
                        </a>
                        <a href="#planos" class="bg-white text-gray-900 px-8 py-4 rounded-xl text-lg font-semibold border-2 border-gray-200 hover:border-blue-600 transition">
                            <i class="fas fa-crown mr-2"></i>Ver Planos
                        </a>
                    </div>
                    <div class="mt-8 flex items-center space-x-6 text-sm text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            14 dias grátis
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Sem cartão de crédito
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Cancele quando quiser
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-3xl opacity-10 blur-3xl"></div>
                    <div class="relative bg-white rounded-3xl shadow-2xl p-4">
                        <!-- SVG Dashboard Preview -->
                        <svg viewBox="0 0 800 500" class="rounded-xl shadow-lg w-full h-auto" xmlns="http://www.w3.org/2000/svg">
                            <!-- Background -->
                            <rect width="800" height="500" fill="#F9FAFB"/>
                            
                            <!-- Sidebar -->
                            <rect width="200" height="500" fill="url(#sidebarGradient)"/>
                            <defs>
                                <linearGradient id="sidebarGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:#1E3A8A;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#1E40AF;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            
                            <!-- Logo Sidebar -->
                            <circle cx="100" cy="30" r="15" fill="#FBBF24"/>
                            <text x="100" y="65" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="middle" font-weight="bold">SOSERP</text>
                            
                            <!-- Menu Items -->
                            <rect x="15" y="100" width="170" height="35" rx="8" fill="#2563EB" opacity="0.3"/>
                            <circle cx="35" cy="117.5" r="8" fill="#60A5FA"/>
                            <text x="55" y="122" font-family="Arial, sans-serif" font-size="11" fill="white">Dashboard</text>
                            
                            <circle cx="35" cy="157.5" r="8" fill="#A78BFA"/>
                            <text x="55" y="162" font-family="Arial, sans-serif" font-size="11" fill="#93C5FD">Faturação</text>
                            
                            <circle cx="35" cy="197.5" r="8" fill="#34D399"/>
                            <text x="55" y="202" font-family="Arial, sans-serif" font-size="11" fill="#93C5FD">Tesouraria</text>
                            
                            <circle cx="35" cy="237.5" r="8" fill="#F59E0B"/>
                            <text x="55" y="242" font-family="Arial, sans-serif" font-size="11" fill="#93C5FD">Clientes</text>
                            
                            <!-- Header -->
                            <rect x="200" y="0" width="600" height="60" fill="white"/>
                            <line x1="200" y1="60" x2="800" y2="60" stroke="#E5E7EB" stroke-width="1"/>
                            <text x="220" y="35" font-family="Arial, sans-serif" font-size="18" fill="#111827" font-weight="bold">Início</text>
                            
                            <!-- User Icon -->
                            <circle cx="760" cy="30" r="15" fill="#3B82F6"/>
                            <text x="760" y="35" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="middle" font-weight="bold">CF</text>
                            
                            <!-- Main Content Area -->
                            <rect x="200" y="60" width="600" height="440" fill="#F9FAFB"/>
                            
                            <!-- Stats Cards -->
                            <rect x="220" y="80" width="170" height="90" rx="12" fill="white" filter="url(#shadow)"/>
                            <defs>
                                <filter id="shadow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feDropShadow dx="0" dy="2" stdDeviation="4" flood-opacity="0.1"/>
                                </filter>
                            </defs>
                            <circle cx="245" cy="105" r="12" fill="#DBEAFE"/>
                            <path d="M 245 100 L 245 110 M 240 105 L 250 105" stroke="#3B82F6" stroke-width="2" stroke-linecap="round"/>
                            <text x="265" y="108" font-family="Arial, sans-serif" font-size="11" fill="#6B7280">Receitas Hoje</text>
                            <text x="245" y="140" font-family="Arial, sans-serif" font-size="20" fill="#111827" font-weight="bold">45.850</text>
                            <text x="320" y="140" font-family="Arial, sans-serif" font-size="12" fill="#10B981">+12%</text>
                            <text x="245" y="158" font-family="Arial, sans-serif" font-size="10" fill="#9CA3AF">Kz</text>
                            
                            <rect x="410" y="80" width="170" height="90" rx="12" fill="white" filter="url(#shadow)"/>
                            <circle cx="435" cy="105" r="12" fill="#D1FAE5"/>
                            <path d="M 430 105 L 435 110 L 442 100" stroke="#10B981" stroke-width="2" fill="none" stroke-linecap="round"/>
                            <text x="455" y="108" font-family="Arial, sans-serif" font-size="11" fill="#6B7280">Faturas Hoje</text>
                            <text x="435" y="140" font-family="Arial, sans-serif" font-size="20" fill="#111827" font-weight="bold">23</text>
                            <text x="470" y="140" font-family="Arial, sans-serif" font-size="12" fill="#10B981">+8%</text>
                            
                            <rect x="600" y="80" width="170" height="90" rx="12" fill="white" filter="url(#shadow)"/>
                            <circle cx="625" cy="105" r="12" fill="#FEF3C7"/>
                            <rect x="620" y="100" width="10" height="10" fill="none" stroke="#F59E0B" stroke-width="2"/>
                            <text x="645" y="108" font-family="Arial, sans-serif" font-size="11" fill="#6B7280">Clientes</text>
                            <text x="625" y="140" font-family="Arial, sans-serif" font-size="20" fill="#111827" font-weight="bold">187</text>
                            
                            <!-- Chart Area -->
                            <rect x="220" y="190" width="360" height="280" rx="12" fill="white" filter="url(#shadow)"/>
                            <text x="240" y="215" font-family="Arial, sans-serif" font-size="13" fill="#111827" font-weight="bold">Vendas dos Últimos 7 Dias</text>
                            
                            <!-- Simple Bar Chart -->
                            <rect x="250" y="390" width="30" height="50" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="290" y="370" width="30" height="70" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="330" y="350" width="30" height="90" rx="4" fill="#8B5CF6" opacity="0.8"/>
                            <rect x="370" y="330" width="30" height="110" rx="4" fill="#8B5CF6"/>
                            <rect x="410" y="360" width="30" height="80" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="450" y="380" width="30" height="60" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="490" y="370" width="30" height="70" rx="4" fill="#3B82F6" opacity="0.7"/>
                            
                            <!-- Chart Labels -->
                            <text x="260" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Seg</text>
                            <text x="300" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Ter</text>
                            <text x="340" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Qua</text>
                            <text x="380" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Qui</text>
                            <text x="420" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Sex</text>
                            <text x="460" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Sáb</text>
                            <text x="500" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Dom</text>
                            
                            <!-- Recent Invoices Table -->
                            <rect x="600" y="190" width="170" height="280" rx="12" fill="white" filter="url(#shadow)"/>
                            <text x="620" y="215" font-family="Arial, sans-serif" font-size="12" fill="#111827" font-weight="bold">Últimas Faturas</text>
                            
                            <!-- Table Rows -->
                            <rect x="615" y="230" width="140" height="35" rx="6" fill="#F3F4F6"/>
                            <text x="625" y="245" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/001</text>
                            <text x="625" y="258" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">12.500 Kz</text>
                            
                            <rect x="615" y="275" width="140" height="35" rx="6" fill="#F9FAFB"/>
                            <text x="625" y="290" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/002</text>
                            <text x="625" y="303" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">8.750 Kz</text>
                            
                            <rect x="615" y="320" width="140" height="35" rx="6" fill="#F3F4F6"/>
                            <text x="625" y="335" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/003</text>
                            <text x="625" y="348" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">15.200 Kz</text>
                            
                            <rect x="615" y="365" width="140" height="35" rx="6" fill="#F9FAFB"/>
                            <text x="625" y="380" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/004</text>
                            <text x="625" y="393" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">22.100 Kz</text>
                            
                            <!-- Status Badges -->
                            <circle cx="740" cy="248" r="3" fill="#10B981"/>
                            <circle cx="740" cy="293" r="3" fill="#10B981"/>
                            <circle cx="740" cy="338" r="3" fill="#F59E0B"/>
                            <circle cx="740" cy="383" r="3" fill="#EF4444"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-12 bg-white border-y border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Quatro factos que se provam, e não quatro números redondos.
                 Estava aqui "500+ Empresas Ativas", "99.9% Uptime" e "100%
                 Satisfação": ninguém contou as empresas, ninguém mede o uptime
                 e satisfação a 100% não existe em lado nenhum. Uma página que
                 se quer levada a sério não pode abrir com três invenções. --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-3xl md:text-4xl font-bold text-blue-600 mb-2">AGT</div>
                    <div class="text-gray-600">Faturação certificada<br><span class="text-xs text-gray-500">{{ \App\Helpers\AGTHelper::softwareValidationNumber() }}</span></div>
                </div>
                <div class="text-center">
                    <div class="text-3xl md:text-4xl font-bold text-purple-600 mb-2">SAFT-AO</div>
                    <div class="text-gray-600">Ficheiro mensal gerado pelo sistema</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl md:text-4xl font-bold text-pink-600 mb-2">Offline</div>
                    <div class="text-gray-600">O POS continua a vender sem internet</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl md:text-4xl font-bold text-green-600 mb-2">IRT + INSS</div>
                    <div class="text-gray-600">Folha de pagamento angolana</div>
                </div>
            </div>
        </div>
    </section>

    <!-- AGT Certification Section -->
    <section id="certificacao" class="py-20 bg-gradient-to-br from-emerald-50 via-green-50 to-teal-50 relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute top-10 left-10 w-72 h-72 bg-green-500 rounded-full filter blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-emerald-400 rounded-full filter blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-bold mb-4 border border-green-200">
                    <i class="fas fa-shield-alt"></i> CERTIFICADO OFICIALMENTE
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    Software <span class="text-green-600">Validado pela AGT Angola</span>
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    O SOS ERP é um software de facturação electrónica oficialmente certificado pela
                    Administração Geral Tributária (AGT) de Angola — Decreto Presidencial n.º 71/25.
                </p>
            </div>

            <!-- Certificate Card -->
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-3xl shadow-2xl border-2 border-green-200 overflow-hidden relative">
                    <!-- Top accent -->
                    <div class="h-2 bg-gradient-to-r from-green-500 via-emerald-500 to-teal-500"></div>

                    <div class="p-8 md:p-12">
                        <!-- Header -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-certificate text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Selo de Garantia</p>
                                    <p class="text-sm font-semibold text-gray-700">Testes de certificação</p>
                                </div>
                            </div>

                            {{-- Centro: QR OFICIAL do certificado emitido pela AGT.
                                 Substituiu um ícone genérico de "visto" — este é
                                 verificável: quem lê o código chega à validação
                                 na AGT. Imagem tal e qual a do certificado, com
                                 o logótipo da AGT ao centro; regenerá-la com a
                                 nossa biblioteca perderia essa marca. --}}
                            <div class="flex flex-col items-center gap-2">
                                <div class="relative">
                                    <div class="absolute -inset-1 bg-gradient-to-br from-green-400 to-emerald-600 rounded-2xl opacity-40 blur"></div>
                                    <img src="{{ asset('images/agt/certificado-qr.jpg') }}"
                                         alt="QR code de verificação do certificado AGT {{ \App\Helpers\AGTHelper::softwareValidationNumber() }}"
                                         width="600" height="600" loading="lazy"
                                         class="relative w-28 h-28 md:w-32 md:h-32 rounded-xl bg-white p-1 shadow-lg shadow-green-500/30">
                                </div>
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                                    Verificar na AGT
                                </span>
                            </div>

                            <div class="text-right">
                                <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">ID do Certificado</p>
                                <p class="text-lg font-mono font-bold text-gray-900">{{ \App\Helpers\AGTHelper::softwareValidationNumber() }}</p>
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="text-center mb-8">
                            <h3 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">Certificado oficial de Software</h3>
                            <p class="text-gray-600">Aprovado com distinção nos testes obrigatórios.</p>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-200 pt-8">
                            <div class="text-center md:border-r border-gray-200">
                                <p class="text-xs uppercase font-bold text-gray-500 tracking-wider mb-2">Software</p>
                                <p class="text-base md:text-lg font-bold text-gray-900">SOS ERP — SOLUÇÕES EMPRESARIAIS</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs uppercase font-bold text-gray-500 tracking-wider mb-2">Versão certificada</p>
                                {{-- 1.0.0 é o que consta do certificado emitido. --}}
                                <p class="text-2xl font-bold text-green-600">1.0.0</p>
                                <p class="text-xs text-gray-500 mt-1">Certificado em 31/07/2026</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trust Badges -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
                    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-4">
                            <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">Facturação Electrónica</h4>
                        <p class="text-sm text-gray-600">Submissão automática à AGT em tempo real, conforme schema v1.2 oficial.</p>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mb-4">
                            <i class="fas fa-fingerprint text-purple-600 text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">Assinatura Digital JWS</h4>
                        <p class="text-sm text-gray-600">Cada documento é assinado digitalmente com RSA-256 e validado pela AGT.</p>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center mb-4">
                            <i class="fas fa-balance-scale text-amber-600 text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">Conformidade Legal</h4>
                        <p class="text-sm text-gray-600">100% conforme RJF (Decreto 71/25) e regulamentação fiscal angolana.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="recursos" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Recursos Poderosos</h2>
                <p class="text-xl text-gray-600">Tudo que você precisa para gerenciar seu negócio com eficiência</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-blue-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-file-invoice text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Faturação Completa</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Emita faturas, recibos e orçamentos profissionais em segundos. Controle total das suas vendas.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Faturas personalizadas</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Controle de pagamentos</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Relatórios detalhados</li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-purple-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-purple-400 to-purple-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-building text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Multi-Empresa</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Gerencie múltiplas empresas em uma única conta. Troque entre elas com um clique.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Dados isolados</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Troca rápida</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Gestão centralizada</li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-pink-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-pink-400 to-pink-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-users-cog text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Gestão de Utilizadores</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Controle total de permissões e acessos. Cada utilizador com suas funções específicas.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Permissões multi-nível</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Acesso seguro</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Auditoria completa</li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-green-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-cubes text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Inventário Inteligente</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Controle de stock em tempo real. Nunca mais fique sem produtos em stock.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Alertas de stock</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Códigos de barras</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Múltiplos armazéns</li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-yellow-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-pie text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Relatórios & Analytics</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Dashboards intuitivos e relatórios detalhados para tomadas de decisão estratégicas.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Gráficos em tempo real</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Exportação PDF/Excel</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>KPIs personalizados</li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-red-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-red-400 to-red-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Segurança Avançada</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Seus dados protegidos com criptografia de ponta. Backups automáticos diários.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>SSL/TLS</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Backup automático</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>2FA disponível</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Sectores Section -->
    <section id="sectores" class="py-20 bg-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-12">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-teal-500 to-indigo-600 text-white text-sm font-bold rounded-full mb-4">
                    <i class="fas fa-store mr-2"></i>{{ __('PARA O SEU SECTOR') }}
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    {{ __('Software para farmácia, loja de roupa, cosmética e mercearia em Angola') }}
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    {{ __('É o mesmo sistema certificado pela AGT — o que muda é o que aparece no ecrã. Diga com o que trabalha e a ficha do produto, os filtros e a lista de stock passam a falar a língua do seu balcão.') }}
                </p>
            </div>

            @php
                // Cada cartão diz o que o sistema faz POR aquele negócio, e não
                // que campos tem: quem procura software não compra colunas de
                // base de dados, compra deixar de vender um psicotrópico por
                // engano ou de deitar fora uma prateleira fora de prazo.
                //
                // Os títulos são a frase que o lojista escreve na pesquisa —
                // "facturação" com c num deles de propósito: a página usa
                // "faturação" em todo o lado e as duas grafias trazem visitas
                // diferentes.
                $sectorCards = [
                    [
                        'eyebrow' => __('Farmácia'),
                        'title'   => __('Software para farmácia em Angola'),
                        'icon'    => 'fa-pills',
                        'from'    => '#0d9488',
                        'to'      => '#0891b2',
                        'items'   => [
                            ['icon' => 'fa-shield-halved', 't' => __('Avisa no balcão antes de vender'),
                             'd' => __('assinala o artigo que exige receita e pede confirmação antes de vender um psicotrópico — vender por engano tem consequência legal.')],
                            ['icon' => 'fa-magnifying-glass', 't' => __('Procura pela substância activa'),
                             'd' => __('quem chega com uma receita de paracetamol não sabe se a caixa diz Ben-u-ron ou Panadol.')],
                            ['icon' => 'fa-layer-group', 't' => __('Lotes com saída FIFO pela validade'),
                             'd' => __('sai primeiro o que expira antes, sem ninguém ter de andar a ver datas na prateleira.')],
                            ['icon' => 'fa-calendar-day', 't' => __('Relatório de validade'),
                             'd' => __('mostra o que está a chegar ao fim a tempo de abater, em vez de o descobrir na contagem.')],
                            ['icon' => 'fa-flask', 't' => __('Dosagem, forma farmacêutica e n.º ARMED'),
                             'd' => __('na ficha do artigo, ao lado do preço e do stock.')],
                        ],
                    ],
                    [
                        'eyebrow' => __('Vestuário e boutique'),
                        'title'   => __('Programa de facturação para loja de roupa'),
                        'icon'    => 'fa-shirt',
                        'from'    => '#4f46e5',
                        'to'      => '#7c3aed',
                        'items'   => [
                            ['icon' => 'fa-tag', 't' => __('Tamanho, cor, género e composição'),
                             'd' => __('na ficha do artigo, para distinguir duas peças que se chamam exactamente igual.')],
                            ['icon' => 'fa-filter', 't' => __('Filtros de tamanho e de cor na lista'),
                             'd' => __('com os valores reais do seu catálogo: ninguém se lembra de como escreveu "azul-marinho" da última vez.')],
                            ['icon' => 'fa-cash-register', 't' => __('Procurar pelo tamanho no POS'),
                             'd' => __('"t-shirt" sozinho não chega para escolher a linha certa; "t-shirt M" chega.')],
                        ],
                    ],
                    [
                        'eyebrow' => __('Cosmética'),
                        'title'   => __('Gestão de loja de cosmética e perfumaria'),
                        'icon'    => 'fa-pump-soap',
                        'from'    => '#db2777',
                        'to'      => '#c026d3',
                        'items'   => [
                            ['icon' => 'fa-clock-rotate-left', 't' => __('Meses após abertura (PAO)'),
                             'd' => __('é o frasco aberto com "12M" no rótulo: quanto tempo dura depois de aberto, que não é o prazo de validade por abrir. A loja precisa dos dois.')],
                            ['icon' => 'fa-list-ul', 't' => __('Lista INCI na ficha'),
                             'd' => __('é o que permite responder ao balcão a "isto tem parabenos?" sem ir buscar a embalagem.')],
                            ['icon' => 'fa-droplet', 't' => __('Conteúdo líquido e tom'),
                             'd' => __('50 ml e 200 ml do mesmo creme deixam de ser a mesma linha na lista.')],
                            ['icon' => 'fa-layer-group', 't' => __('Validades e lotes, como na farmácia'),
                             'd' => __('sai primeiro o que expira antes, e o relatório de validade avisa a tempo.')],
                        ],
                    ],
                    [
                        'eyebrow' => __('Mercearia'),
                        'title'   => __('Programa para mercearia e minimercado'),
                        'icon'    => 'fa-basket-shopping',
                        'from'    => '#ca8a04',
                        'to'      => '#16a34a',
                        'items'   => [
                            ['icon' => 'fa-temperature-low', 't' => __('Conservação à vista na lista de stock'),
                             'd' => __('ambiente, refrigerado ou congelado, onde quem arruma a mercadoria olha — e não escondido dentro da ficha.')],
                            ['icon' => 'fa-triangle-exclamation', 't' => __('Alergénios e país de origem'),
                             'd' => __('o que o rótulo alimentar obriga a ter e o cliente pergunta ao balcão.')],
                            ['icon' => 'fa-droplet', 't' => __('Conteúdo líquido'),
                             'd' => __('"Leite" em duas linhas só se distingue por 1 L e 200 ml.')],
                            ['icon' => 'fa-calendar-day', 't' => __('Validades'),
                             'd' => __('para abater o que está a chegar ao fim antes de estragar.')],
                        ],
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($sectorCards as $sc)
                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-2xl border border-gray-100 overflow-hidden transition-all hover:-translate-y-1">
                        <div class="p-6 text-white relative overflow-hidden" style="background: linear-gradient(135deg, {{ $sc['from'] }}, {{ $sc['to'] }});">
                            <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/10 rounded-full"></div>
                            <i class="fas {{ $sc['icon'] }} text-4xl mb-3 relative"></i>
                            <div class="relative text-xs font-bold uppercase tracking-widest opacity-90">{{ $sc['eyebrow'] }}</div>
                            <h3 class="text-2xl font-bold relative">{{ $sc['title'] }}</h3>
                        </div>
                        <div class="p-6">
                            <ul class="space-y-3 text-sm text-gray-700">
                                @foreach($sc['items'] as $item)
                                    <li class="flex items-start">
                                        <i class="fas {{ $item['icon'] }} mt-1 mr-3" style="color: {{ $sc['from'] }};"></i>
                                        <span><strong>{{ $item['t'] }}</strong> — {{ $item['d'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            {{-- Dito em todos os cartões e sem tom de desculpa: o
                                 perfil manda apenas no que aparece por omissão,
                                 e a maioria das empresas não liga nenhum. --}}
                            <p class="mt-5 pt-4 border-t border-gray-100 text-xs text-gray-500 flex items-start">
                                <i class="fas fa-sliders mt-0.5 mr-2 text-gray-400"></i>
                                <span>{{ __('Liga-se num interruptor nas definições de faturação. Quem não trabalha com isto não vê estes campos.') }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 bg-gradient-to-r from-gray-900 to-gray-700 text-white rounded-2xl p-6 md:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h3 class="text-2xl font-bold mb-2">{{ __('Não é um sistema diferente por sector') }}</h3>
                    <p class="text-sm md:text-base opacity-90 max-w-3xl">
                        {{ __('É o mesmo ERP, a mesma faturação certificada pela AGT e o mesmo POS offline. E os perfis somam-se: uma mercearia com balcão de farmácia liga os dois e fica com os campos dos dois.') }}
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold rounded-xl hover:shadow-lg transition whitespace-nowrap">
                        <i class="fas fa-rocket mr-2"></i>{{ __('Começar Grátis') }}
                    </a>
                    <a href="#modulos" class="inline-flex items-center justify-center px-6 py-3 border border-white/30 text-white font-semibold rounded-xl hover:bg-white/10 transition whitespace-nowrap">
                        {{ __('Ver módulos') }}<i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Events Module Highlight Section -->
    <section class="py-20 bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 relative overflow-hidden">
        <!-- Background Animation -->
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-10 left-10 w-72 h-72 bg-purple-500 rounded-full filter blur-3xl animate-pulse"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-pink-500 rounded-full filter blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-pink-500 to-purple-600 text-white font-bold rounded-full mb-6 shadow-2xl animate-bounce">
                    <i class="fas fa-star mr-3 text-yellow-300"></i>
                    MÓDULO DESTAQUE
                    <i class="fas fa-star ml-3 text-yellow-300"></i>
                </div>
                <h2 class="text-5xl md:text-6xl font-extrabold text-white mb-6">
                    <i class="fas fa-calendar-days mr-4"></i>Gestão de Eventos
                </h2>
                <p class="text-2xl text-purple-200 max-w-3xl mx-auto leading-relaxed">
                    Organize eventos profissionais com controle total de equipamentos, equipes, orçamentos e muito mais!
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Left Side - Image/Icon -->
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500 to-pink-500 rounded-3xl blur-2xl opacity-50 animate-pulse"></div>
                    <div class="relative bg-white/10 backdrop-blur-lg rounded-3xl p-12 border border-white/20 shadow-2xl">
                        <div class="text-center">
                            <div class="w-48 h-48 mx-auto bg-gradient-to-br from-purple-500 via-pink-500 to-orange-500 rounded-full flex items-center justify-center mb-8 shadow-2xl animate-pulse">
                                <i class="fas fa-calendar-check text-white text-8xl"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-4">Tudo em Um Só Lugar</h3>
                            <p class="text-purple-200 text-lg">
                                Gerencie desde pequenas reuniões até grandes eventos corporativos com total profissionalismo
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Features -->
                <div class="space-y-6">
                    <!-- Feature 1 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-calendar-alt text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📅 Calendário Inteligente</h4>
                                <p class="text-purple-200">Visualize todos os eventos em um calendário interativo. Filtros avançados, arrastar e soltar, visão mensal/semanal/diária.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-boxes text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📦 Gestão de Equipamentos</h4>
                                <p class="text-purple-200">Controle completo de equipamentos: som, luz, palco, decoração. Rastreamento com QR Code, kits pré-definidos, histórico de uso.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-users text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">👥 Gestão de Equipes</h4>
                                <p class="text-purple-200">Aloque técnicos, fotógrafos, segurança e equipe. Controle de disponibilidade, turnos e remuneração por evento.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📍 Locais & Venues</h4>
                                <p class="text-purple-200">Cadastre salões, espaços e locais. Capacidade, disponibilidade, galeria de fotos, contatos e muito mais.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 5 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-red-500 to-rose-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">💰 Orçamentos & Faturação</h4>
                                <p class="text-purple-200">Crie orçamentos detalhados, controle custos vs. receita, emita faturas automaticamente e acompanhe pagamentos.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 6 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-indigo-500 to-blue-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📊 Relatórios Avançados</h4>
                                <p class="text-purple-200">Dashboards com KPIs, relatórios de lucratividade, análise de equipamentos mais usados, performance da equipe.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="text-center mt-16">
                <a href="/register" class="inline-flex items-center px-10 py-5 bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 text-white text-xl font-bold rounded-full shadow-2xl hover:shadow-pink-500/50 hover:scale-110 transition-all duration-300">
                    <i class="fas fa-rocket mr-3"></i>
                    Experimentar Gestão de Eventos Grátis
                    <i class="fas fa-arrow-right ml-3"></i>
                </a>
                <p class="text-purple-200 mt-4 text-sm">✨ 14 dias grátis • Sem cartão de crédito • Cancele quando quiser</p>
            </div>
        </div>
    </section>

    <!-- Módulos Section -->
    <section id="modulos" class="py-20 bg-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-12">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-emerald-500 to-blue-600 text-white text-sm font-bold rounded-full mb-4">
                    <i class="fas fa-cubes mr-2"></i>MÓDULOS
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Uma solução para cada negócio</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Pacotes especializados por setor. Conhece tudo o que cada módulo oferece.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                    $moduleCards = [
                        // Módulos em destaque (aparecem primeiro, com badge)
                        ['slug' => 'eventos', 'name' => 'Gestão de Eventos', 'icon' => 'fa-calendar-days', 'desc' => 'Equipamentos (som, LEDs, streaming), equipas técnicas, calendário interativo, orçamentos e checklists.', 'from' => '#7c3aed', 'to' => '#db2777', 'featured' => true],
                        ['slug' => 'rh', 'name' => 'Recursos Humanos', 'icon' => 'fa-users', 'desc' => 'Folha de pagamento angolana, INSS e IRT automáticos, ficha do trabalhador completa.', 'from' => '#7c3aed', 'to' => '#2563eb', 'featured' => true],
                        ['slug' => 'oficina', 'name' => 'Oficina Auto', 'icon' => 'fa-wrench', 'desc' => 'Ordens de reparação, orçamentos, peças, mecânicos e histórico por viatura.', 'from' => '#ea580c', 'to' => '#854d0e', 'featured' => true],
                        // Restantes módulos
                        // 'sector_note': só a faturação a leva, porque é o único
                        // módulo cujo catálogo muda de forma consoante o negócio.
                        ['slug' => 'vendas', 'name' => 'Vendas & Faturação', 'icon' => 'fa-cash-register', 'desc' => 'POS, faturação certificada AGT, gestão de clientes e produtos. Funciona offline.', 'from' => '#ea580c', 'to' => '#dc2626', 'sector_note' => true],
                        ['slug' => 'restaurant', 'name' => 'Gestão de Restaurante', 'icon' => 'fa-utensils', 'desc' => 'Sala e mesas, comandas digitais, cozinha/KDS, reservas, fichas técnicas, stock e faturação AGT integrada.', 'from' => '#f97316', 'to' => '#b91c1c', 'featured' => true],
                        ['slug' => 'hotel', 'name' => 'Gestão de Hotel', 'icon' => 'fa-hotel', 'desc' => 'Booking engine, channel manager, check-in/out, housekeeping e analytics.', 'from' => '#0891b2', 'to' => '#2563eb'],
                        ['slug' => 'salao', 'name' => 'Salão de Beleza', 'icon' => 'fa-spa', 'desc' => 'Agendamento online, comissões automáticas, fidelização e lembretes por SMS.', 'from' => '#db2777', 'to' => '#9333ea'],
                    ];
                @endphp

                @foreach($moduleCards as $mc)
                    @php $isFeatured = $mc['featured'] ?? false; @endphp
                    {{-- O cartão é uma <div> e não uma <a>: a nota do sector tem
                         link próprio, e um <a> dentro de outro <a> faz o browser
                         fechar o primeiro a meio e partir o cartão em dois. O
                         link do módulo passou para o fim, a cobrir o cartão
                         inteiro, que continua clicável em qualquer ponto. --}}
                    <div class="group relative bg-white rounded-2xl shadow-lg hover:shadow-2xl overflow-hidden transition-all hover:-translate-y-1 {{ $isFeatured ? 'ring-2 ring-amber-400 ring-offset-2' : 'border border-gray-100' }}">
                        @if($isFeatured)
                            <span class="absolute top-3 right-3 z-10 inline-flex items-center gap-1 px-2.5 py-1 bg-amber-400 text-amber-950 text-[11px] font-extrabold rounded-full shadow">
                                <i class="fas fa-star"></i> DESTAQUE
                            </span>
                        @endif
                        <div class="p-6 text-white relative overflow-hidden" style="background: linear-gradient(135deg, {{ $mc['from'] }}, {{ $mc['to'] }});">
                            <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/10 rounded-full"></div>
                            <i class="fas {{ $mc['icon'] }} text-4xl mb-3 relative"></i>
                            <h3 class="text-xl font-bold relative">{{ $mc['name'] }}</h3>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-600 mb-4 min-h-[60px]">{{ $mc['desc'] }}</p>
                            @if(!empty($mc['sector_note']))
                                {{-- z-20 para ficar acima do link que cobre o
                                     cartão: sem isto o clique ia parar ao módulo
                                     e nunca à secção dos sectores. --}}
                                <a href="#sectores" class="relative z-20 flex items-start gap-2 text-xs font-semibold text-teal-700 hover:text-teal-800 mb-4">
                                    <i class="fas fa-store mt-0.5"></i>
                                    <span>{{ __('Adapta-se ao sector — farmácia, roupa, cosmética ou mercearia.') }} <span class="underline">{{ __('Ver como') }}</span></span>
                                </a>
                            @endif
                            <div class="flex items-center justify-between text-sm font-bold pt-3 border-t border-gray-100 group-hover:gap-2 transition-all" style="color: {{ $mc['from'] }};">
                                <span>Saber mais</span>
                                <i class="fas fa-arrow-right group-hover:translate-x-1 transition"></i>
                            </div>
                        </div>
                        <a href="/modulos/{{ $mc['slug'] }}" class="absolute inset-0 z-10" aria-label="{{ $mc['name'] }}"></a>
                    </div>
                @endforeach

                {{-- Card "Ver todos" --}}
                <a href="/modulos" class="group bg-gradient-to-br from-gray-900 to-gray-700 text-white rounded-2xl shadow-lg hover:shadow-2xl overflow-hidden transition-all hover:-translate-y-1 flex flex-col items-center justify-center p-8 text-center">
                    <i class="fas fa-th-large text-4xl mb-3 opacity-80"></i>
                    <h3 class="text-xl font-bold mb-2">Ver Todos os Módulos</h3>
                    <p class="text-sm opacity-80 mb-3">Compara funcionalidades e preços lado a lado</p>
                    <span class="inline-flex items-center gap-2 text-sm font-bold">
                        Explorar <i class="fas fa-arrow-right group-hover:translate-x-1 transition"></i>
                    </span>
                </a>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="planos" class="py-20 bg-gradient-to-br from-gray-50 to-blue-50 relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-500 rounded-full filter blur-3xl"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4 animate-pulse">
                    <i class="fas fa-crown mr-2"></i>PRICING
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Planos Para Todos os Tamanhos</h2>
                <p class="text-xl text-gray-600">Escolha o plano ideal para o seu negócio e comece hoje mesmo!</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($plans as $plan)
                    @php
                        $isFox = str_contains(strtolower($plan->slug), 'fox');
                        $isStarter = str_contains(strtolower($plan->slug), 'starter');
                        $isProfessional = str_contains(strtolower($plan->slug), 'professional');
                        $isEnterprise = str_contains(strtolower($plan->slug), 'enterprise');
                        $isRestaurant = str_contains(strtolower($plan->slug), 'restaurante') || str_contains(strtolower($plan->slug), 'restaurant');
                        
                        // Define icon and colors
                        if ($isFox) {
                            $icon = '🦊';
                            $gradient = 'from-orange-500 via-red-500 to-pink-500';
                            $borderColor = 'border-orange-500';
                            $iconBg = 'bg-gradient-to-br from-orange-400 to-red-500';
                            $badgeColor = 'bg-orange-500';
                        } elseif ($isRestaurant) {
                            $icon = 'fa-utensils';
                            $gradient = 'from-orange-500 to-red-700';
                            $borderColor = 'border-orange-500';
                            $iconBg = 'bg-gradient-to-br from-orange-400 to-red-600';
                            $badgeColor = 'bg-orange-500';
                        } elseif ($isStarter) {
                            $icon = 'fa-rocket';
                            $gradient = 'from-green-500 to-emerald-600';
                            $borderColor = 'border-green-500';
                            $iconBg = 'bg-gradient-to-br from-green-400 to-emerald-500';
                            $badgeColor = 'bg-green-500';
                        } elseif ($isProfessional) {
                            $icon = 'fa-briefcase';
                            $gradient = 'from-blue-500 to-indigo-600';
                            $borderColor = 'border-blue-500';
                            $iconBg = 'bg-gradient-to-br from-blue-400 to-indigo-500';
                            $badgeColor = 'bg-blue-500';
                        } else {
                            $icon = 'fa-crown';
                            $gradient = 'from-purple-500 to-pink-600';
                            $borderColor = 'border-purple-500';
                            $iconBg = 'bg-gradient-to-br from-purple-400 to-pink-500';
                            $badgeColor = 'bg-purple-500';
                        }
                    @endphp
                    
                    <div class="group relative bg-white rounded-3xl shadow-xl p-8 hover:shadow-2xl transition-all duration-300 border-2 {{ $plan->is_featured ? $borderColor : 'border-gray-200' }}" 
                         style="animation: fadeInUp 0.6s ease-out {{ $loop->index * 0.1 }}s both;"
                         x-data="{ showDetails: false }">
                        
                        <!-- Badge Top -->
                        @if($plan->is_featured)
                            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                                <div class="bg-gradient-to-r {{ $gradient }} text-white text-xs font-bold px-4 py-2 rounded-full shadow-lg animate-bounce">
                                    <i class="fas fa-star mr-1"></i>{{ $isFox ? 'PROMO ESPECIAL!' : 'POPULAR' }}
                                </div>
                            </div>
                        @endif
                        
                        <!-- Icon -->
                        <div class="mb-6 relative">
                            <div class="w-16 h-16 {{ $iconBg }} rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-105 transition-all duration-300 mx-auto">
                                @if($isFox)
                                    <span class="text-3xl">{{ $icon }}</span>
                                @else
                                    <i class="fas {{ $icon }} text-white text-2xl"></i>
                                @endif
                            </div>
                            @if($isFox)
                                <div class="absolute -top-2 -right-2 text-2xl animate-pulse">✨</div>
                            @endif
                        </div>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 text-center">{{ $plan->name }}</h3>
                        <p class="text-gray-600 text-sm mb-6 text-center min-h-[40px]">{{ Str::limit($plan->description, 60) }}</p>
                        
                        <!-- Price -->
                        <div class="mb-6 text-center">
                            @if($plan->price_monthly == 0)
                            <div>
                                <span class="text-5xl font-bold bg-gradient-to-r {{ $gradient }} bg-clip-text text-transparent">GRÁTIS</span>
                                <p class="text-sm text-gray-500 mt-2">{{ $plan->trial_days }} dias</p>
                            </div>
                            @else
                            <div>
                                <span class="text-5xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0) }}</span>
                                <span class="text-gray-600 text-lg"> Kz</span>
                                <p class="text-sm text-gray-500 mt-1">/mês</p>
                            </div>
                            @endif
                        </div>

                        {{-- O botão leva o PLANO consigo.
                             Ligava a /register sem mais nada, e quem carregasse
                             em "Começar Agora" no cartão do Business era
                             recebido com a pergunta "qual plano quer?" — a
                             escolha que acabara de fazer. --}}
                        <a href="{{ route('register', ['plan' => $plan->slug]) }}" class="block w-full text-center bg-gradient-to-r {{ $gradient }} text-white px-6 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 mb-6">
                            <span class="inline-flex items-center">
                            @if($isFox)
                                🦊 Começar GRÁTIS
                            @else
                                <i class="fas fa-rocket mr-2"></i>Começar Agora
                            @endif
                            </span>
                        </a>

                        <!-- Features List -->
                        <div class="border-t border-gray-200 pt-6">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-4">Recursos Incluídos</p>
                            <ul class="space-y-3 text-sm">
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-users text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ $plan->max_users >= 999 ? '999+' : $plan->max_users }}</strong> Utilizadores</span>
                                </li>
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-building text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ $plan->max_companies >= 999 ? '50+' : $plan->max_companies }}</strong> Empresas</span>
                                </li>
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-database text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ number_format($plan->max_storage_mb / 1000, 0) }}GB</strong> Storage</span>
                                </li>
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-gift text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ $plan->trial_days }}</strong> dias grátis</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Modules List -->
                        <div class="border-t border-gray-200 pt-6 mt-6" x-show="!showDetails">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-4">Módulos Incluídos</p>
                            <ul class="space-y-2 text-sm">
                                @if($plan->included_modules && is_array($plan->included_modules))
                                    @php
                                        $modulesLimit = $isFox ? 5 : 3;
                                        $moduleNames = [
                                            'invoicing' => '📄 Faturação',
                                            'treasury' => '💰 Tesouraria',
                                            'rh' => '👥 Recursos Humanos',
                                            'contabilidade' => '📊 Contabilidade',
                                            'oficina' => '🔧 Gestão de Oficina',
                                            'hotel' => '🏨 Gestão de Hotel',
                                            'restaurant' => '🍽️ Gestão de Restaurante',
                                            'salon' => '💇 Salão de Beleza',
                                            'crm' => '🤝 CRM',
                                            'inventario' => '📦 Inventário',
                                            'compras' => '🛒 Compras',
                                            'projetos' => '📋 Projetos'
                                        ];
                                    @endphp
                                    @foreach(array_slice($plan->included_modules, 0, $modulesLimit) as $module)
                                        <li class="flex items-center text-gray-600">
                                            <i class="fas fa-check text-{{ $isFox ? 'orange' : ($isStarter ? 'green' : ($isProfessional ? 'blue' : 'purple')) }}-500 mr-2"></i>
                                            <span>{{ $moduleNames[$module] ?? ucfirst($module) }}</span>
                                        </li>
                                    @endforeach
                                    @if(count($plan->included_modules) > $modulesLimit)
                                        <li class="text-gray-500 text-xs italic">
                                            + {{ count($plan->included_modules) - $modulesLimit }} módulos adicionais
                                        </li>
                                    @endif
                                @else
                                    <li class="text-gray-500 text-xs">Módulos básicos incluídos</li>
                                @endif
                            </ul>
                        </div>
                        
                        <!-- Expanded Details -->
                        <div class="border-t border-gray-200 pt-6 mt-6" x-show="showDetails" x-collapse>
                            <p class="text-xs font-bold text-gray-500 uppercase mb-4">Todos os Módulos</p>
                            <ul class="space-y-2 text-sm max-h-64 overflow-y-auto pr-2">
                                @if($plan->included_modules && is_array($plan->included_modules))
                                    @php
                                        $moduleNames = [
                                            'invoicing' => '📄 Faturação',
                                            'treasury' => '💰 Tesouraria',
                                            'rh' => '👥 Recursos Humanos',
                                            'contabilidade' => '📊 Contabilidade',
                                            'oficina' => '🔧 Gestão de Oficina',
                                            'hotel' => '🏨 Gestão de Hotel',
                                            'restaurant' => '🍽️ Gestão de Restaurante',
                                            'salon' => '💇 Salão de Beleza',
                                            'crm' => '🤝 CRM',
                                            'inventario' => '📦 Inventário',
                                            'compras' => '🛒 Compras',
                                            'projetos' => '📋 Projetos'
                                        ];
                                    @endphp
                                    @foreach($plan->included_modules as $module)
                                        <li class="flex items-center text-gray-600">
                                            <i class="fas fa-check text-{{ $isFox ? 'orange' : ($isStarter ? 'green' : ($isProfessional ? 'blue' : 'purple')) }}-500 mr-2"></i>
                                            <span>{{ $moduleNames[$module] ?? ucfirst($module) }}</span>
                                        </li>
                                    @endforeach
                                @endif
                                
                                @if($plan->features && is_array($plan->features))
                                    <li class="pt-4 mt-4 border-t border-gray-200">
                                        <p class="text-xs font-bold text-gray-500 uppercase mb-3">Features Adicionais</p>
                                    </li>
                                    @foreach($plan->features as $feature)
                                        <li class="flex items-start text-gray-600">
                                            <i class="fas fa-star text-yellow-500 mr-2 mt-1"></i>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                        </div>
                        
                        <!-- Ver Mais Button -->
                        @php
                            $buttonColor = $isFox ? 'orange' : ($isStarter ? 'green' : ($isProfessional ? 'blue' : 'purple'));
                        @endphp
                        <button @click="showDetails = !showDetails" class="w-full mt-6 px-4 py-2 border-2 border-{{ $buttonColor }}-500 text-{{ $buttonColor }}-600 rounded-lg font-semibold hover:bg-{{ $buttonColor }}-50 transition text-sm">
                            <span x-show="!showDetails">
                                <i class="fas fa-chevron-down mr-2"></i>Ver Todos os Detalhes
                            </span>
                            <span x-show="showDetails">
                                <i class="fas fa-chevron-up mr-2"></i>Ver Menos
                            </span>
                        </button>
                    </div>
                @endforeach
            </div>
            
            <!-- Trust Badge -->
            <div class="mt-16 text-center">
                <p class="text-sm text-gray-600 mb-4">✅ Sem compromisso • ✅ Cancele quando quiser • ✅ Suporte incluído</p>
                <div class="flex justify-center items-center space-x-8 text-gray-400">
                    <div class="flex items-center">
                        <i class="fas fa-lock text-2xl mr-2"></i>
                        <span class="text-sm">Pagamento Seguro</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-2xl mr-2"></i>
                        <span class="text-sm">Dados Protegidos</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-headset text-2xl mr-2"></i>
                        <span class="text-sm">Suporte 24/7</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Roadmap Section -->
    <section id="roadmap" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4">
                    <i class="fas fa-road mr-2"></i>v6.2.0
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    Roadmap do Projeto
                </h2>
                <p class="text-xl text-gray-600">
                    Acompanhe o desenvolvimento e as próximas funcionalidades
                </p>
            </div>

            <!-- Timeline -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                <!-- Concluído -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-t-4 border-green-500">
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                Concluído
                            </h3>
                            <span class="px-3 py-1 bg-green-500 text-white rounded-full text-sm font-bold">100%</span>
                        </div>
                        <p class="text-gray-600">Funcionalidades implementadas</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Multi-Tenancy</p>
                                <p class="text-xs text-gray-600">Sistema multi-empresa completo</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Faturação AGT</p>
                                <p class="text-xs text-gray-600">Vendas, Compras, Proformas, Recibos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">SAFT-AO Compliant</p>
                                <p class="text-xs text-gray-600">RSA-SHA256, QR Code, ATCUD, JWS</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Contabilidade</p>
                                <p class="text-xs text-gray-600">Plano de contas, Diários, Lançamentos, Orçamentos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Recursos Humanos</p>
                                <p class="text-xs text-gray-600">Folha, Presenças, Turnos, Férias, Horas Extra</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Tesouraria</p>
                                <p class="text-xs text-gray-600">Caixa, Bancos, Reconciliação</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">POS (Ponto de Venda)</p>
                                <p class="text-xs text-gray-600">Turnos, Impressão, Integração Faturação</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Gestão Hoteleira</p>
                                <p class="text-xs text-gray-600">Reservas, Quartos, Check-in/out, Housekeeping</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Salão de Beleza</p>
                                <p class="text-xs text-gray-600">Agendamentos, Profissionais, Serviços, POS</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Oficina / Workshop</p>
                                <p class="text-xs text-gray-600">Veículos, Ordens de Serviço, Mecânicos, Peças</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Módulo de Eventos</p>
                                <p class="text-xs text-gray-600">Calendário, Equipamentos, SETS, QR Code</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Gestão de Compras</p>
                                <p class="text-xs text-gray-600">Fornecedores, Facturas, Proformas</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Inventário & Stock</p>
                                <p class="text-xs text-gray-600">Armazéns, Transferências, Lotes, Validade</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Notas de Crédito/Débito</p>
                                <p class="text-xs text-gray-600">Conforme Decreto 71/25</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Portal do Cliente</p>
                                <p class="text-xs text-gray-600">Facturas, Proformas, Extracto, Eventos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Relatórios Financeiros</p>
                                <p class="text-xs text-gray-600">DRE, Fluxo de Caixa, Balancete, Razão</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">SEO & Analytics</p>
                                <p class="text-xs text-gray-600">Google Analytics, GTM, Facebook Pixel, Schema.org</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Gestão de Usuários</p>
                                <p class="text-xs text-gray-600">Roles & Permissions com Spatie</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Planos & Assinaturas</p>
                                <p class="text-xs text-gray-600">Billing, Módulos dinâmicos por plano</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Notificações</p>
                                <p class="text-xs text-gray-600">Email, SMS, WhatsApp, Dashboard</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Suporte / Tickets</p>
                                <p class="text-xs text-gray-600">Sistema de suporte integrado</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Booking Online</p>
                                <p class="text-xs text-gray-600">Reservas online Hotel & Salão</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Em Desenvolvimento -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-t-4 border-yellow-500">
                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-spinner fa-pulse text-yellow-500 mr-3"></i>
                                Em Desenvolvimento
                            </h3>
                            <span class="px-3 py-1 bg-yellow-500 text-white rounded-full text-sm font-bold">60%</span>
                        </div>
                        <p class="text-gray-600">Funcionalidades em construção</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Integrações Bancárias</p>
                                <p class="text-xs text-gray-600">Multicaixa Express, BAI, BFA</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 40%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">E-commerce</p>
                                <p class="text-xs text-gray-600">Loja online integrada com stock</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 25%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">API REST Pública</p>
                                <p class="text-xs text-gray-600">Integrações externas com OAuth 2.0</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 30%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">CRM</p>
                                <p class="text-xs text-gray-600">Gestão de leads e pipeline de vendas</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 20%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Planejado -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-t-4 border-blue-500">
                    <div class="bg-gradient-to-br from-blue-50 to-cyan-50 p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-lightbulb text-blue-500 mr-3"></i>
                                Planejado
                            </h3>
                            <span class="px-3 py-1 bg-blue-500 text-white rounded-full text-sm font-bold">2026</span>
                        </div>
                        <p class="text-gray-600">Próximas funcionalidades</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Mobile App</p>
                                <p class="text-xs text-gray-600">Android & iOS (React Native)</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">BI & Dashboards Avançados</p>
                                <p class="text-xs text-gray-600">Business Intelligence com gráficos interactivos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Gestão de Projectos</p>
                                <p class="text-xs text-gray-600">Tarefas, Kanban e Timesheets</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Inteligência Artificial</p>
                                <p class="text-xs text-gray-600">Previsões de vendas e análise preditiva</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Marketplace de Módulos</p>
                                <p class="text-xs text-gray-600">Plugins e extensões de terceiros</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-check-circle text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">22</p>
                    <p class="text-sm opacity-90">Concluído</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-spinner text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">4</p>
                    <p class="text-sm opacity-90">Em Desenvolvimento</p>
                </div>
                <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-lightbulb text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">5</p>
                    <p class="text-sm opacity-90">Planejado</p>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-chart-line text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">90%</p>
                    <p class="text-sm opacity-90">Progresso Total</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-white mb-6">Pronto para Transformar Seu Negócio?</h2>
            <p class="text-xl text-blue-100 mb-8">
                Junte-se a centenas de empresas que já confiam no SOSERP para gerenciar suas operações diárias.
            </p>
            <a href="{{ route('register') }}" class="bg-white text-purple-600 px-8 py-4 rounded-xl text-lg font-semibold hover:shadow-2xl transition inline-block">
                <i class="fas fa-rocket mr-2"></i>Começar Gratuitamente
            </a>
            <p class="text-blue-100 text-sm mt-4">
                Sem cartão de crédito • 14 dias grátis • Cancele quando quiser
            </p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contacto" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
                <!-- Contact Info -->
                <div>
                    <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4">
                        <i class="fas fa-envelope mr-2"></i>CONTACTO
                    </span>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Entre em Contacto</h2>
                    <p class="text-xl text-gray-600 mb-8">
                        Tem dúvidas? Nossa equipa está pronta para ajudar você a começar hoje mesmo!
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-envelope text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Email</h3>
                                <p class="text-gray-600">suporte@soserp.vip</p>
                                <p class="text-gray-600">comercial@soserp.vip</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-100 to-green-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-phone text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Telefone</h3>
                                <p class="text-gray-600">+244 939 729 902</p>
                                <p class="text-gray-600">+244 942 705 533</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Endereço</h3>
                                <p class="text-gray-600">Luanda, Angola</p>
                                <p class="text-gray-600">Talatona, Rua Principal</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-100 to-orange-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-clock text-orange-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Horário de Atendimento</h3>
                                <p class="text-gray-600">Segunda - Sexta: 8h - 18h</p>
                                <p class="text-gray-600">Sábado: 9h - 13h</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-3xl p-8 shadow-xl">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Envie uma Mensagem</h3>
                    
                    <form id="contactForm" class="space-y-6" x-data="{ submitting: false, success: false, error: '' }" @submit.prevent="submitForm">
                        <div x-show="success" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl" x-transition>
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>Mensagem enviada com sucesso! Entraremos em contato em breve.</span>
                        </div>
                        
                        <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl" x-transition>
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span x-text="error"></span>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user mr-2"></i>Nome Completo *
                            </label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="Seu nome completo">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email *
                            </label>
                            <input type="email" name="email" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="seu@email.com">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone mr-2"></i>Telefone
                            </label>
                            <input type="tel" name="phone"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="+244 939 729 902">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-briefcase mr-2"></i>Empresa
                            </label>
                            <input type="text" name="company"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="Nome da sua empresa">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-comment-alt mr-2"></i>Mensagem *
                            </label>
                            <textarea name="message" required rows="4"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"
                                      placeholder="Como podemos ajudar você?"></textarea>
                        </div>
                        
                        <button type="submit" 
                                :disabled="submitting"
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-xl font-bold hover:shadow-2xl transition-all duration-300 disabled:opacity-50">
                            <span x-show="!submitting">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar Mensagem
                            </span>
                            <span x-show="submitting">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                            </span>
                        </button>
                        
                        <p class="text-xs text-gray-500 text-center">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Seus dados estão seguros. Não compartilhamos informações com terceiros.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8 mb-8">
                <div>
                    <div class="flex items-center mb-4">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="max-height: 3rem;" class="h-12 w-auto object-contain">
                        @else
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-chart-line text-white text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-white">{{ app_name() }}</span>
                        @endif
                    </div>
                    <p class="text-sm">Sistema de Gestão Empresarial completo e moderno para empresas angolanas.</p>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Produto</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#recursos" class="hover:text-white">Recursos</a></li>
                        <li><a href="/modulos" class="hover:text-white">Módulos</a></li>
                        <li><a href="/modulos/vendas" class="hover:text-white">— Vendas</a></li>
                        <li><a href="/modulos/rh" class="hover:text-white">— RH</a></li>
                        <li><a href="/modulos/hotel" class="hover:text-white">— Hotel</a></li>
                        <li><a href="/modulos/salao" class="hover:text-white">— Salão</a></li>
                        <li><a href="/modulos/oficina" class="hover:text-white">— Oficina</a></li>
                        <li><a href="#planos" class="hover:text-white">Planos</a></li>
                        <li><a href="#roadmap" class="hover:text-white">Roadmap</a></li>
                        <li><a href="https://docs.soserp.vip" target="_blank" class="hover:text-white">Documentação</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Empresa</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#sobre" class="hover:text-white">Sobre</a></li>
                        <li><a href="#contacto" class="hover:text-white">Contacto</a></li>
                        <li><a href="#contacto" class="hover:text-white">Suporte</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Legal</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white">Termos de Uso</a></li>
                        <li><a href="#" class="hover:text-white">Privacidade</a></li>
                        <li><a href="#" class="hover:text-white">Cookies</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Contacto</h3>
                    <ul class="space-y-3 text-sm">
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-envelope mt-1 mr-2 text-blue-400"></i>
                                <div>
                                    <a href="mailto:suporte@soserp.vip" class="hover:text-white block">suporte@soserp.vip</a>
                                    <a href="mailto:comercial@soserp.vip" class="hover:text-white block">comercial@soserp.vip</a>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-phone mt-1 mr-2 text-blue-400"></i>
                                <a href="tel:+244939779902" class="hover:text-white">+244 939 779 902</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-map-marker-alt mt-1 mr-2 text-blue-400"></i>
                                <div>
                                    <p>Luanda, Angola</p>
                                    <p>Talatona, Rua Principal</p>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-clock mt-1 mr-2 text-blue-400"></i>
                                <div>
                                    <p>Segunda - Sexta: 8h - 18h</p>
                                    <p>Sábado: 9h - 13h</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 text-center text-sm">
                <p>&copy; 2025 SOSERP. Todos os direitos reservados.</p>
                <p class="mt-2">
                    Desenvolvido por 
                    <a href="https://softecangola.net" target="_blank" class="text-blue-400 hover:text-blue-300 font-semibold">
                        Softec Angola
                    </a>
                </p>
            </div>
        </div>
    </footer>

    <script>
        function submitForm() {
            const form = document.getElementById('contactForm');
            const formData = new FormData(form);
            
            this.submitting = true;
            this.success = false;
            this.error = '';
            
            fetch('{{ route("contact.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.submitting = false;
                
                if (data.success) {
                    this.success = true;
                    form.reset();
                    
                    // Esconder mensagem de sucesso após 5 segundos
                    setTimeout(() => {
                        this.success = false;
                    }, 5000);
                } else {
                    this.error = data.message || 'Erro ao enviar mensagem. Por favor, tente novamente.';
                    
                    // Se houver erros de validação
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0];
                        this.error = Array.isArray(firstError) ? firstError[0] : firstError;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.submitting = false;
                this.error = 'Erro ao enviar mensagem. Por favor, tente novamente.';
            });
        }
    </script>

</body>
</html>
