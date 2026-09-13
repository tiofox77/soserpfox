<?php

namespace App\Http\Controllers;

use App\Support\CasaPublica;
use App\Support\DadosEstruturados;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * O SITEMAP — só as páginas que se podem indexar, e com datas verdadeiras.
 *
 * Era um ficheiro escrito à mão em `public/`: levava o /login e o /register
 * (que são `noindex` — o Google recebia duas ordens contrárias), deixava de
 * fora a Privacidade e os Termos, e marcava metade das páginas com a data do
 * dia, que é um falso sinal de frescura. E as páginas públicas das empresas —
 * cartas, marcações, reservas — não entravam.
 *
 * Regras:
 *  - só páginas com `index` e o seu endereço canónico, sem query string;
 *  - `lastmod` só quando há uma data REAL (a da última alteração da casa); nas
 *    páginas do produto omite-se, em vez de inventar;
 *  - as casas públicas saem da MESMA regra que as páginas usam (CasaPublica):
 *    empresa desactivada ou sem módulo não aparece.
 */
class SitemapController extends Controller
{
    public const CHAVE = 'sitemap.xml.v2';

    public function index(): Response
    {
        $xml = Cache::remember(self::CHAVE, now()->addHours(6), fn () => view('sitemap', ['urls' => $this->urls()])->render());

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** @return list<array{loc: string, lastmod: ?string, changefreq: string, priority: string, imagem: ?string, titulo: ?string}> */
    public function urls(): array
    {
        $raiz = DadosEstruturados::raiz();
        $urls = [];

        $urls[] = $this->url($raiz . '/', 'weekly', '1.0', asset('brand/soserp-og-1200x630.png'), 'SOSERP — Software de Gestão Empresarial em Angola');
        $urls[] = $this->url($raiz . '/modulos', 'weekly', '0.9');

        // Os módulos saem da lista que as páginas desenham: um slug que dá 404
        // não pode estar no sitemap.
        $modulos = (fn () => array_keys($this->modules))->call(new ModulePagesController());

        foreach ($modulos as $slug) {
            $urls[] = $this->url($raiz . '/modulos/' . $slug, 'weekly', '0.9');
        }

        $urls[] = $this->url($raiz . '/privacidade', 'yearly', '0.3');
        $urls[] = $this->url($raiz . '/termos', 'yearly', '0.3');

        foreach (CasaPublica::indexaveis() as $casa) {
            $urls[] = $this->url($casa['loc'], 'weekly', '0.6', $casa['imagem'], $casa['titulo'], $casa['lastmod']);
        }

        return $urls;
    }

    private function url(string $loc, string $freq, string $prio, ?string $imagem = null, ?string $titulo = null, ?\DateTimeInterface $lastmod = null): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod?->format(DATE_ATOM),
            'changefreq' => $freq,
            'priority' => $prio,
            'imagem' => $imagem,
            'titulo' => $titulo,
        ];
    }
}
