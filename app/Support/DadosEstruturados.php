<?php

namespace App\Support;

use App\Helpers\AGTHelper;
use App\Models\Plan;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * OS DADOS ESTRUTURADOS (JSON-LD) DAS PÁGINAS PÚBLICAS — um sítio só.
 *
 * O que estava partido, e porque é que vive aqui:
 *
 *  • A PÁGINA INICIAL TINHA DUAS SoftwareApplication, uma com os planos da
 *    tabela e outra com um preço único das definições (0 Kz). O Google via
 *    dois softwares com o mesmo nome e duas ofertas que se contradiziam.
 *  • «Softec Angola» ERA UMA PESSOA FUNDADORA de uma organização chamada
 *    SOSERP. É ao contrário: a Softec Angola é a EMPRESA (é ela que aparece
 *    nos Termos e na Política de Privacidade), e o SOSERP é o software dela.
 *  • O JSON era escrito à mão no Blade, com `{{ }}`: uma aspa numa descrição
 *    saía `&quot;` e o bloco deixava de ser JSON válido. Aqui é `json_encode`.
 *  • As páginas dos módulos não tinham JSON-LD nenhum.
 *
 * Tudo sai num `@graph` com identificadores estáveis: `#organization`,
 * `#website` e `#software` são os mesmos em todas as páginas, e cada página
 * acrescenta o que é seu (a página, as migalhas, a oferta, as perguntas).
 */
final class DadosEstruturados
{
    /** A empresa que faz o SOSERP — a mesma dos Termos e da Política de Privacidade. */
    public const EMPRESA = 'Softec Angola';
    public const EMPRESA_URL = 'https://softecangola.net';
    public const SOFTWARE = 'SOSERP';

    public static function raiz(): string
    {
        return rtrim((string) (SystemSetting::get('seo_canonical_url') ?: SystemSetting::get('schema_app_url') ?: 'https://soserp.vip'), '/');
    }

    public static function id(string $fragmento): string
    {
        return self::raiz() . '/#' . $fragmento;
    }

    /** O nome de um plano sem o emoji da montra: «📊 Pacote Vendas» → «Pacote Vendas». */
    public static function semEmoji(?string $texto): string
    {
        $limpo = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', '', (string) $texto);

        return trim(preg_replace('/\s+/u', ' ', $limpo));
    }

    /** O `<script>` pronto a pôr no `<head>`. `JSON_HEX_TAG` impede um `</script>` de fechar o bloco. */
    public static function script(array $grafo): HtmlString
    {
        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($grafo))],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT,
        );

        return new HtmlString("<script type=\"application/ld+json\">\n{$json}\n</script>");
    }

    // ─── As peças que se repetem em todas as páginas ─────────────────────

    public static function organizacao(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => self::id('organization'),
            'name' => self::EMPRESA,
            'url' => self::EMPRESA_URL,
            'brand' => [
                '@type' => 'Brand',
                'name' => self::SOFTWARE,
                'logo' => asset('brand/soserp-logo-square-512.png'),
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Talatona, Rua Principal',
                'addressLocality' => 'Luanda',
                'addressRegion' => 'Luanda',
                'addressCountry' => 'AO',
            ],
            'areaServed' => ['@type' => 'Country', 'name' => 'Angola'],
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'sales',
                    'telephone' => '+244-939-729-902',
                    'email' => 'comercial@soserp.vip',
                    'areaServed' => 'AO',
                    'availableLanguage' => ['pt'],
                ],
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'telephone' => '+244-939-729-902',
                    'email' => 'suporte@soserp.vip',
                    'areaServed' => 'AO',
                    'availableLanguage' => ['pt'],
                ],
            ],
            'sameAs' => [
                'https://www.facebook.com/soserp',
                'https://www.linkedin.com/company/soserp',
                'https://www.instagram.com/soserp_angola',
            ],
        ];
    }

    public static function site(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::id('website'),
            'url' => self::raiz() . '/',
            'name' => self::SOFTWARE,
            'inLanguage' => 'pt-AO',
            'publisher' => ['@id' => self::id('organization')],
        ];
    }

    /**
     * O SOFTWARE — uma entidade só, com o mesmo `@id` em todas as páginas.
     *
     * Na página inicial vai inteiro (as ofertas de todos os planos públicos, a
     * lista de funcionalidades, o público); nas outras vai só a identidade, e
     * a página diz a oferta que é sua.
     *
     * @param  array<int, array>|null  $ofertas
     */
    public static function software(bool $completo, ?array $ofertas = null): array
    {
        $software = [
            '@type' => 'SoftwareApplication',
            '@id' => self::id('software'),
            'name' => self::SOFTWARE,
            'alternateName' => 'SOS ERP',
            'url' => self::raiz() . '/',
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'ERP',
            'operatingSystem' => 'Web',
            'inLanguage' => ['pt-AO', 'en', 'fr'],
            'publisher' => ['@id' => self::id('organization')],
            'creator' => ['@id' => self::id('organization')],
            'image' => asset('brand/soserp-og-1200x630.png'),
        ];

        if ($ofertas) {
            $software['offers'] = count($ofertas) === 1 ? $ofertas[0] : self::conjuntoDeOfertas($ofertas);
        }

        if (! $completo) {
            return $software;
        }

        $software['description'] = (string) (SystemSetting::get('schema_app_description')
            ?: 'Software de gestão empresarial para Angola, certificado pela AGT: faturação eletrónica e SAFT-AO, POS que vende sem internet, stock, tesouraria, contabilidade, RH com IRT e INSS, e módulos para restaurante, hotel, salão e oficina.');

        $software['featureList'] = [
            'Faturação certificada pela AGT (' . AGTHelper::softwareValidationNumber() . ')',
            'SAFT-AO gerado pelo sistema',
            'POS que vende sem internet (PWA)',
            'Stock por armazém, lotes e prazos de validade',
            'Tesouraria e contabilidade',
            'Processamento salarial com IRT e INSS',
            'Multi-empresa e multi-utilizador com permissões',
            'Perfis de negócio: farmácia, vestuário, cosmética e mercearia',
            'Restaurante: sala, comandas, cozinha (KDS) e carta digital por QR',
            'Hotel: reservas, check-in e reservas online',
            'Salão de beleza: marcações e marcação online',
            'Oficina auto: ordens de reparação',
        ];

        $software['audience'] = array_map(fn ($t) => ['@type' => 'BusinessAudience', 'audienceType' => $t], [
            'Farmácias e parafarmácias',
            'Lojas de roupa, calçado e boutiques',
            'Lojas de cosmética e perfumaria',
            'Mercearias, minimercados e supermercados',
            'Restaurantes, bares e cafés',
            'Hotéis e alojamentos',
            'Salões de beleza',
            'Oficinas auto',
        ]);

        // Só com avaliações que alguém tenha mesmo contado e escrito nas definições.
        $nota = SystemSetting::get('schema_rating_value');
        $avaliacoes = SystemSetting::get('schema_review_count');
        if (filled($nota) && filled($avaliacoes)) {
            $software['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $nota,
                'reviewCount' => (string) $avaliacoes,
            ];
        }

        return $software;
    }

    // ─── As ofertas saem dos planos ──────────────────────────────────────

    /** Uma oferta por plano, com o preço mensal e — se houver — o anual. */
    public static function oferta(Plan $plano, string $url): array
    {
        $precos = [[
            '@type' => 'UnitPriceSpecification',
            'price' => round((float) $plano->price_monthly, 2),
            'priceCurrency' => 'AOA',
            'billingDuration' => 'P1M',
        ]];

        if ((float) $plano->getRawOriginal('price_yearly') > 0) {
            $precos[] = [
                '@type' => 'UnitPriceSpecification',
                'price' => round((float) $plano->getRawOriginal('price_yearly'), 2),
                'priceCurrency' => 'AOA',
                'billingDuration' => 'P1Y',
            ];
        }

        $oferta = [
            '@type' => 'Offer',
            'name' => self::semEmoji($plano->name),
            'price' => round((float) $plano->price_monthly, 2),
            'priceCurrency' => 'AOA',
            'priceSpecification' => $precos,
            'availability' => 'https://schema.org/InStock',
            'eligibleRegion' => ['@type' => 'Country', 'name' => 'Angola'],
            'url' => $url,
            'seller' => ['@id' => self::id('organization')],
        ];

        if (filled($plano->description)) {
            $oferta['description'] = self::semEmoji($plano->description);
        }

        return $oferta;
    }

    /** @param  array<int, array>  $ofertas */
    private static function conjuntoDeOfertas(array $ofertas): array
    {
        $precos = array_column($ofertas, 'price');

        return [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'AOA',
            'lowPrice' => min($precos),
            'highPrice' => max($precos),
            'offerCount' => count($ofertas),
            'offers' => $ofertas,
        ];
    }

    /** As ofertas da página inicial: os planos públicos, com a ligação do registo de cada um. */
    public static function ofertasDosPlanos(Collection $planos): array
    {
        return $planos
            ->map(fn (Plan $p) => self::oferta($p, route('register', ['plan' => $p->slug])))
            ->values()
            ->all();
    }

    // ─── O que é de cada página ──────────────────────────────────────────

    /** @param  array<int, array{0:string,1:string}>  $migalhas  [nome, url] */
    public static function pagina(string $url, string $nome, string $descricao, ?array $migalhas = null, array $extra = []): array
    {
        $pagina = [
            '@type' => 'WebPage',
            '@id' => $url . '#webpage',
            'url' => $url,
            'name' => $nome,
            'description' => $descricao,
            'inLanguage' => 'pt-AO',
            'isPartOf' => ['@id' => self::id('website')],
            'about' => ['@id' => self::id('software')],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => asset('brand/soserp-og-1200x630.png'), 'width' => 1200, 'height' => 630],
        ];

        if ($migalhas) {
            $pagina['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
        }

        return $pagina + $extra;
    }

    /** @param  array<int, array{0:string,1:string}>  $migalhas  [nome, url] */
    public static function migalhas(string $url, array $migalhas): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => array_map(fn ($m, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $m[0],
                'item' => $m[1],
            ], $migalhas, array_keys($migalhas)),
        ];
    }

    /**
     * As perguntas frequentes — as MESMAS que a página mostra.
     *
     * Um FAQPage cujas perguntas não estão à vista vai contra as regras do
     * Google; a página inicial tinha exactamente isso. Quem chama desenha a
     * lista e passa-a aqui, e as duas coisas não se desencontram.
     *
     * @param  array<int, array{0:string,1:string}>  $perguntas
     */
    public static function perguntas(string $url, array $perguntas): ?array
    {
        if (! $perguntas) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $url . '#faq',
            'isPartOf' => ['@id' => $url . '#webpage'],
            'mainEntity' => array_map(fn ($p) => [
                '@type' => 'Question',
                'name' => $p[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p[1]],
            ], $perguntas),
        ];
    }

    /** As funcionalidades de um módulo, como lista — o que a página mostra em cartões. */
    public static function funcionalidades(string $url, string $nome, array $funcionalidades): array
    {
        return [
            '@type' => 'ItemList',
            '@id' => $url . '#funcionalidades',
            'name' => $nome,
            'itemListElement' => array_map(fn ($f, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $f['title'],
                'description' => $f['desc'],
            ], $funcionalidades, array_keys($funcionalidades)),
        ];
    }

    // ─── A página inicial ────────────────────────────────────────────────

    /**
     * As perguntas da página inicial. Os preços saem dos planos; o número de
     * certificação sai da AGT.
     *
     * @return array<int, array{0:string,1:string}>
     */
    public static function perguntasDaPaginaInicial(Collection $planos): array
    {
        $pagos = $planos->filter(fn (Plan $p) => (float) $p->price_monthly > 0)->sortBy('price_monthly');
        $entrada = $pagos->first();
        $topo = $pagos->last();

        $perguntas = [
            ['O SOSERP é certificado pela AGT?',
                'Sim. O SOSERP é software de faturação certificado pela Administração Geral Tributária de Angola (' . AGTHelper::softwareValidationNumber() . '): emite faturas com assinatura e numeração por série e gera o ficheiro SAFT-AO.'],
            ['Funciona em todo o território de Angola?',
                'Sim. O SOSERP funciona no navegador, em qualquer província, e o suporte é feito em português por telefone, WhatsApp e email.'],
            ['Posso emitir faturas sem internet?',
                'Sim. O POS instala-se como aplicação no telemóvel, tablet ou computador e continua a vender sem rede; os documentos sincronizam quando a ligação volta.'],
        ];

        if ($entrada) {
            $dias = (int) ($entrada->trial_days ?: 14);
            $perguntas[] = ['Quanto custa o SOSERP?',
                $entrada->is($topo)
                    ? 'O plano ' . self::semEmoji($entrada->name) . ' custa ' . number_format((float) $entrada->price_monthly, 0, ',', '.') . " Kz por mês, com {$dias} dias grátis para experimentar."
                    : 'Os planos pagos vão de ' . number_format((float) $entrada->price_monthly, 0, ',', '.') . ' Kz a ' . number_format((float) $topo->price_monthly, 0, ',', '.') . " Kz por mês. Pode experimentar {$dias} dias grátis, sem cartão."];
        }

        return array_merge($perguntas, [
            ['Calcula o IRT e o INSS automaticamente?',
                'Sim. O processamento salarial aplica a tabela de IRT angolana e as contribuições para o INSS (3% do trabalhador e 8% da entidade empregadora) em cada recibo.'],
            ['Posso gerir várias empresas com uma só conta?',
                'Sim. Um utilizador pode pertencer a várias empresas e trocar entre elas, com permissões diferentes em cada uma.'],
            ['Serve para farmácia, loja de roupa, cosmética ou mercearia?',
                'Sim. Nas definições de faturação liga o perfil do seu negócio e a ficha do produto passa a mostrar os campos desse sector: receita médica e substância activa na farmácia; tamanho, cor e composição no vestuário; prazo após abertura e lista INCI na cosmética; conservação, alergénios e país de origem na mercearia.'],
        ]);
    }
}
