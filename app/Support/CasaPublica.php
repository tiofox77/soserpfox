<?php

namespace App\Support;

use App\Models\Hotel\HotelSettings;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Salon\SalonSettings;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * AS PÁGINAS PÚBLICAS DAS EMPRESAS — a carta do restaurante, a marcação do
 * salão e as reservas do hotel. UMA definição só de «está aberta».
 *
 * As três portas perguntavam só pelo interruptor da própria página. Uma empresa
 * desactivada, ou que deixou de ter o módulo, continuava com a carta no ar, a
 * receber pedidos e marcações que ninguém ia ver — e o Google a indexá-la. A
 * regra passa a ser a mesma nas páginas, nas APIs delas e no sitemap:
 *
 *   interruptor da página ligado  +  empresa activa  +  módulo activo.
 *
 * O `hasModule` já responde «não» a uma empresa inactiva.
 */
final class CasaPublica
{
    /** @var array<string, bool> */
    private static array $vistas = [];

    public static function aberta(?int $tenantId, string $modulo): bool
    {
        if (! $tenantId) {
            return false;
        }

        return self::$vistas["{$tenantId}:{$modulo}"] ??= (bool) Tenant::find($tenantId)?->hasModule($modulo);
    }

    /**
     * UMA PÁGINA QUE NINGUÉM PREENCHEU NÃO SE INDEXA.
     *
     * A marcação do salão e as reservas do hotel nascem LIGADAS, com o nome por
     * omissão: em produção o sitemap levava um «Meu Hotel» sem uma linha de
     * texto. Página fina é o que o Google castiga no site inteiro. Conta como
     * preenchida a casa que escreveu a sua descrição; até lá a página abre (há
     * quem a partilhe à mão) mas diz `noindex, follow` e fica fora do sitemap.
     * A carta do restaurante não precisa: publicá-la já é um acto deliberado,
     * e os pratos são o conteúdo.
     */
    public static function temConteudo(?string ...$textos): bool
    {
        foreach ($textos as $t) {
            if (filled(trim((string) $t))) {
                return true;
            }
        }

        return false;
    }

    /** Para os ensaios: a memória é por pedido, e um ensaio faz vários. */
    public static function esquecer(): void
    {
        self::$vistas = [];
    }

    /**
     * As páginas que se podem indexar, com a data da última alteração real.
     *
     * @return Collection<int, array{loc: string, lastmod: ?\DateTimeInterface, imagem: ?string, titulo: string}>
     */
    public static function indexaveis(): Collection
    {
        $paginas = collect();

        RestaurantSettings::withoutGlobalScopes()
            ->where('online_menu_enabled', true)->whereNotNull('menu_slug')
            ->get(['tenant_id', 'menu_slug', 'menu_title', 'menu_cover', 'menu_logo', 'updated_at'])
            ->filter(fn ($d) => self::aberta((int) $d->tenant_id, 'restaurant'))
            ->each(fn ($d) => $paginas->push([
                'loc' => DadosEstruturados::raiz() . '/menu/' . $d->menu_slug,
                'lastmod' => $d->updated_at,
                'imagem' => self::imagem($d->menu_cover ?: $d->menu_logo),
                'titulo' => $d->menu_title ?: (string) Tenant::find($d->tenant_id)?->name,
            ]));

        SalonSettings::withoutGlobalScopes()
            ->where('online_booking_enabled', true)->whereNotNull('booking_slug')
            ->get()
            ->filter(fn ($d) => self::aberta((int) $d->tenant_id, 'salon') && self::temConteudo($d->salon_description, $d->welcome_message))
            ->each(fn ($d) => $paginas->push([
                'loc' => DadosEstruturados::raiz() . '/agendar/' . $d->booking_slug,
                'lastmod' => $d->updated_at,
                'imagem' => self::imagem($d->cover_url ?: $d->logo_url),
                'titulo' => (string) ($d->salon_name ?: Tenant::find($d->tenant_id)?->name),
            ]));

        HotelSettings::withoutGlobalScopes()
            ->where('online_booking_enabled', true)->whereNotNull('booking_slug')
            ->get()
            ->filter(fn ($d) => self::aberta((int) $d->tenant_id, 'hotel') && self::temConteudo($d->meta_description, $d->hotel_description))
            ->each(fn ($d) => $paginas->push([
                'loc' => DadosEstruturados::raiz() . '/hotel/booking/' . $d->booking_slug,
                'lastmod' => $d->updated_at,
                'imagem' => self::imagem($d->cover_url ?: $d->logo_url),
                'titulo' => (string) ($d->hotel_name ?: Tenant::find($d->tenant_id)?->name),
            ]));

        return $paginas->values();
    }

    /**
     * OS DADOS ESTRUTURADOS DA CASA — Restaurant, BeautySalon ou Hotel.
     *
     * Só o que a casa pôs à vista: nome, descrição, imagem, contacto público e
     * morada. Nunca `aggregateRating` (não há avaliações na página) e nunca um
     * campo vazio — `array_filter` tira-os, que um `"telephone": null` é pior
     * do que a ausência.
     *
     * @param  array{nome: string, descricao?: ?string, imagem?: ?string, telefone?: ?string, email?: ?string, morada?: ?string, cidade?: ?string, pais?: ?string, redes?: array<int, ?string>, mapa?: ?string, menu?: ?string}  $c
     */
    public static function dadosEstruturados(string $tipo, string $url, int $tenantId, array $c): \Illuminate\Support\HtmlString
    {
        $empresa = Tenant::find($tenantId);

        $morada = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => ($c['morada'] ?? null) ?: $empresa?->address,
            'addressLocality' => ($c['cidade'] ?? null) ?: ($empresa?->city ?: $empresa?->municipality),
            'addressRegion' => $empresa?->province ?: null,
            // O país da FICHA DA EMPRESA não serve: a coluna nasceu com DEFAULT
            // 'Portugal' e 67 empresas angolanas ficaram «PT». Só o que a casa
            // escreveu na página; senão Angola.
            'addressCountry' => Geografia::normalizarPais($c['pais'] ?? null) ?: 'AO',
        ]);

        $casa = array_filter([
            '@type' => $tipo,
            '@id' => $url . '#casa',
            'name' => $c['nome'],
            'description' => filled($c['descricao'] ?? null) ? trim($c['descricao']) : null,
            'url' => $url,
            'image' => $c['imagem'] ?? null,
            'telephone' => $c['telefone'] ?? null,
            'email' => $c['email'] ?? null,
            // `sameAs` e `hasMap` são endereços: um «@salao» escrito à mão não é.
            'sameAs' => array_values(array_filter($c['redes'] ?? [], fn ($u) => filter_var($u, FILTER_VALIDATE_URL))) ?: null,
            'hasMap' => filter_var($c['mapa'] ?? null, FILTER_VALIDATE_URL) ?: null,
            'hasMenu' => $c['menu'] ?? null,
            'currenciesAccepted' => 'AOA',
            // Uma morada que só tem o país não diz nada a ninguém.
            'address' => count($morada) > 2 ? $morada : null,
        ]);

        return DadosEstruturados::script([$casa]);
    }

    /** Um caminho gravado («restaurant/capa.jpg») não é um endereço; um http já é. */
    private static function imagem(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        if (str_starts_with($caminho, 'http')) {
            return $caminho;
        }

        // Os acessores `cover_url` já devolvem «/storage/…»; o caminho cru não.
        $caminho = ltrim($caminho, '/');

        return url(str_starts_with($caminho, 'storage/') ? $caminho : 'storage/' . $caminho);
    }
}
