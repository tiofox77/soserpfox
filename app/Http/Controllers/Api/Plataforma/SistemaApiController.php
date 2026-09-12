<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * AS DEFINIÇÕES DO SISTEMA — o nome, a imagem, o SEO e a página pública.
 *
 * O QUE MUDOU DE SUBSTÂNCIA:
 *
 *  · NADA SE GRAVAVA VALIDADO. Um email de contacto sem @, um endereço canónico
 *    sem esquema, uma avaliação de «cinco estrelas e meia» — tudo ia direito
 *    para a página pública e para o JSON-LD que o Google lê.
 *  · CADA GRAVAÇÃO ESVAZIAVA A CACHE INTEIRA da aplicação (`Cache::flush()`),
 *    não só a das definições: limitadores de pedidos, bloqueios, tudo. `set()`
 *    já esquece a chave que muda — é quanto basta.
 *  · DEZ INTERRUPTORES NÃO ESTÃO LIGADOS A NADA. O modo de manutenção, fechar o
 *    registo, obrigar a verificar o email, as duas cores, a versão e as quatro
 *    redes sociais gravam-se e nenhuma parte do sistema os lê. O ecrã antigo
 *    deixava crer que desligar o registo fechava o registo. Continuam no
 *    formulário, mas o ecrã diz que não têm efeito (`SEM_EFEITO`).
 */
class SistemaApiController extends Controller
{
    /**
     * Chaves que se gravam e que nenhuma parte do sistema lê — verificado por
     * varrimento do código em 2026-09-12. Quando uma passar a ser usada, sai
     * daqui.
     */
    public const SEM_EFEITO = [
        'maintenance_mode', 'enable_registration', 'enable_email_verification',
        'primary_color', 'secondary_color', 'app_version',
        'facebook_url', 'instagram_url', 'twitter_url', 'linkedin_url',
    ];

    /** @var array<string, array{0: string|null, 1: array<int, string>}> chave => [omissão, regras] */
    private const GRUPOS = [
        'geral' => [
            'app_name' => ['SOS ERP', ['required', 'string', 'max:120']],
            'app_description' => [null, ['nullable', 'string', 'max:500']],
            'app_version' => ['5.0.0', ['nullable', 'string', 'max:20']],
            'app_url' => [null, ['nullable', 'url', 'max:255']],
            'contact_email' => [null, ['nullable', 'email', 'max:255']],
            'contact_phone' => [null, ['nullable', 'string', 'max:40']],
        ],
        'aparencia' => [
            'primary_color' => ['#4F46E5', ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']],
            'secondary_color' => ['#06B6D4', ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']],
        ],
        'seo' => [
            'seo_title' => [null, ['nullable', 'string', 'max:120']],
            'seo_description' => [null, ['nullable', 'string', 'max:320']],
            'seo_keywords' => [null, ['nullable', 'string', 'max:500']],
            'seo_author' => [null, ['nullable', 'string', 'max:120']],
            'seo_canonical_url' => [null, ['nullable', 'url', 'max:255']],
            'seo_robots' => ['index, follow', ['nullable', 'string', 'max:60']],
            'google_analytics_id' => [null, ['nullable', 'string', 'max:40']],
            'gtm_id' => [null, ['nullable', 'string', 'max:40']],
            'facebook_pixel_id' => [null, ['nullable', 'string', 'max:40']],
            'google_site_verification' => [null, ['nullable', 'string', 'max:120']],
            'bing_site_verification' => [null, ['nullable', 'string', 'max:120']],
        ],
        'funcionalidades' => [
            'enable_registration' => ['true', ['boolean']],
            'enable_email_verification' => ['false', ['boolean']],
            'maintenance_mode' => ['false', ['boolean']],
        ],
        'social' => [
            'facebook_url' => [null, ['nullable', 'url', 'max:255']],
            'instagram_url' => [null, ['nullable', 'url', 'max:255']],
            'twitter_url' => [null, ['nullable', 'url', 'max:255']],
            'linkedin_url' => [null, ['nullable', 'url', 'max:255']],
        ],
        'schema' => [
            'schema_app_name' => ['SOSERP', ['nullable', 'string', 'max:120']],
            'schema_app_description' => [null, ['nullable', 'string', 'max:500']],
            'schema_app_url' => ['https://soserp.vip', ['nullable', 'url', 'max:255']],
            'schema_app_category' => ['BusinessApplication', ['nullable', 'string', 'max:60']],
            'schema_price' => ['0', ['nullable', 'numeric', 'min:0']],
            'schema_currency' => ['AOA', ['nullable', 'string', 'size:3']],
            'schema_region' => ['Angola', ['nullable', 'string', 'max:60']],
            // SEM OMISSÃO: com 4,8 e 150 pré-preenchidos, qualquer gravação
            // voltava a plantar uma avaliação que ninguém recolheu — e o Google
            // transforma-a em estrelas no resultado de pesquisa.
            'schema_rating_value' => [null, ['nullable', 'numeric', 'min:1', 'max:5']],
            'schema_review_count' => [null, ['nullable', 'integer', 'min:1']],
            'schema_creator_name' => ['SOSERP', ['nullable', 'string', 'max:120']],
            'schema_creator_url' => ['https://soserp.vip', ['nullable', 'url', 'max:255']],
        ],
    ];

    private const IMAGENS = ['app_logo', 'app_favicon', 'seo_og_image'];

    public function index(): JsonResponse
    {
        $valores = [];

        foreach (self::GRUPOS as $grupo => $chaves) {
            foreach ($chaves as $chave => [$omissao]) {
                $valor = SystemSetting::get($chave, $omissao);
                $valores[$chave] = $grupo === 'funcionalidades' ? $valor === 'true' || $valor === true || $valor === '1' : ($valor ?? '');
            }
        }

        return response()->json([
            'valores' => $valores,
            'imagens' => collect(self::IMAGENS)->mapWithKeys(fn ($c) => [
                $c => ($caminho = SystemSetting::get($c)) ? $this->endereco($caminho) : null,
            ]),
            'sem_efeito' => self::SEM_EFEITO,
            'auditoria' => $this->auditoria(),
        ]);
    }

    public function guardar(Request $request, string $grupo): JsonResponse
    {
        abort_unless(array_key_exists($grupo, self::GRUPOS), 404);

        $regras = collect(self::GRUPOS[$grupo])->map(fn ($c) => $c[1])->all();

        // A AVALIAÇÃO E O NÚMERO DE AVALIAÇÕES VÃO JUNTOS: um sem o outro é um
        // JSON-LD que o Google recusa.
        if ($grupo === 'schema') {
            $regras['schema_rating_value'][] = 'required_with:schema_review_count';
            $regras['schema_review_count'][] = 'required_with:schema_rating_value';
        }

        $d = $request->validate($regras);

        foreach (array_keys(self::GRUPOS[$grupo]) as $chave) {
            $valor = $d[$chave] ?? null;

            if ($grupo === 'funcionalidades') {
                $valor = ($valor ?? false) ? 'true' : 'false';
            }

            SystemSetting::set($chave, $valor);
        }

        return response()->json(['message' => __('Definições guardadas.')]);
    }

    public function enviarImagem(Request $request, string $chave): JsonResponse
    {
        abort_unless(in_array($chave, self::IMAGENS, true), 404);

        $request->validate([
            'ficheiro' => ['required', 'file', 'max:2048', $chave === 'app_favicon'
                ? 'mimes:png,ico,svg,jpg,jpeg,webp'
                : 'mimes:png,jpg,jpeg,webp,svg'],
        ], [], ['ficheiro' => __('imagem')]);

        $antiga = SystemSetting::get($chave);
        $caminho = $request->file('ficheiro')->store('settings', 'public');

        if (! $caminho) {
            throw ValidationException::withMessages(['ficheiro' => __('A imagem não foi guardada.')]);
        }

        SystemSetting::set($chave, $caminho);

        // A ANTIGA SÓ SAI DEPOIS DE A NOVA ESTAR GRAVADA: ao contrário, uma
        // falha a meio deixava o sistema sem logótipo nenhum.
        if ($antiga && $antiga !== $caminho) {
            Storage::disk('public')->delete($antiga);
        }

        return response()->json([
            'message' => __('Imagem actualizada.'),
            'url' => $this->endereco($caminho),
        ]);
    }

    /**
     * O endereço de uma imagem, SEM o anfitrião. `Storage::url()` põe à frente o
     * APP_URL, e quando o sistema é aberto por outro nome (um domínio novo, a
     * máquina local) o browser recusa a imagem por não ser da mesma origem.
     */
    private function endereco(string $caminho): string
    {
        return (string) parse_url(Storage::disk('public')->url($caminho), PHP_URL_PATH);
    }

    /** O que a página pública tem, lido dos próprios ficheiros. */
    private function auditoria(): array
    {
        $ficheiros = [];

        foreach (['sitemap' => 'sitemap.xml', 'robots' => 'robots.txt', 'manifest' => 'manifest.json'] as $nome => $ficheiro) {
            $caminho = public_path($ficheiro);
            $existe = is_file($caminho);

            $ficheiros[$nome] = [
                'ficheiro' => $ficheiro,
                'url' => url('/'.$ficheiro),
                'existe' => $existe,
                'tamanho' => $existe ? filesize($caminho) : 0,
                'alterado' => $existe ? Carbon::createFromTimestamp(filemtime($caminho))->format('d/m/Y H:i') : null,
                'amostra' => $existe ? mb_substr((string) @file_get_contents($caminho), 0, 600) : null,
            ];
        }

        $ficheiros['sitemap']['enderecos'] = 0;

        if ($ficheiros['sitemap']['existe']) {
            $xml = @simplexml_load_file(public_path('sitemap.xml'));
            $ficheiros['sitemap']['enderecos'] = $xml ? count($xml->url ?? []) : 0;
        }

        $pagina = resource_path('views/landing/home.blade.php');
        $verificacoes = [];
        $esquemas = [];

        if (is_file($pagina)) {
            $html = (string) file_get_contents($pagina);
            $tem = fn (string $padrao) => (bool) preg_match($padrao, $html);

            $verificacoes = [
                ['chave' => 'title', 'rotulo' => __('Etiqueta <title>'), 'ok' => $tem('/<title[^>]*>[^<]+<\/title>/i')],
                ['chave' => 'description', 'rotulo' => __('Meta description'), 'ok' => $tem('/<meta\s+name=["\']description["\']/i')],
                ['chave' => 'keywords', 'rotulo' => __('Meta keywords'), 'ok' => $tem('/<meta\s+name=["\']keywords["\']/i')],
                ['chave' => 'canonical', 'rotulo' => __('Endereço canónico'), 'ok' => $tem('/<link\s+rel=["\']canonical["\']/i')],
                ['chave' => 'og_title', 'rotulo' => 'og:title', 'ok' => $tem('/property=["\']og:title["\']/i')],
                ['chave' => 'og_description', 'rotulo' => 'og:description', 'ok' => $tem('/property=["\']og:description["\']/i')],
                ['chave' => 'og_image', 'rotulo' => 'og:image', 'ok' => $tem('/property=["\']og:image["\']/i')],
                ['chave' => 'twitter_card', 'rotulo' => 'twitter:card', 'ok' => $tem('/name=["\']twitter:card["\']/i')],
                ['chave' => 'geo_region', 'rotulo' => 'geo.region', 'ok' => $tem('/name=["\']geo\.region["\']/i')],
                ['chave' => 'geo_placename', 'rotulo' => 'geo.placename', 'ok' => $tem('/name=["\']geo\.placename["\']/i')],
                ['chave' => 'geo_position', 'rotulo' => 'geo.position', 'ok' => $tem('/name=["\']geo\.position["\']/i')],
                ['chave' => 'favicon', 'rotulo' => __('Ícone do separador'), 'ok' => $tem('/<link\s+rel=["\'](?:icon|shortcut icon)["\']/i')],
                ['chave' => 'apple_touch_icon', 'rotulo' => 'apple-touch-icon', 'ok' => $tem('/<link\s+rel=["\']apple-touch-icon["\']/i')],
                ['chave' => 'theme_color', 'rotulo' => 'theme-color', 'ok' => $tem('/<meta\s+name=["\']theme-color["\']/i')],
                ['chave' => 'manifest', 'rotulo' => __('Ligação ao manifesto'), 'ok' => $tem('/<link\s+rel=["\']manifest["\']/i')],
                ['chave' => 'hreflang', 'rotulo' => 'hreflang', 'ok' => preg_match_all('/<link\s+rel=["\']alternate["\']\s+hreflang=/i', $html) > 0],
            ];

            preg_match_all('/<script\s+type=["\']application\/ld\+json["\'][^>]*>([\s\S]*?)<\/script>/i', $html, $jm);

            foreach ($jm[1] ?? [] as $json) {
                if (preg_match('/"@type"\s*:\s*"([^"]+)"/', $json, $tm)) {
                    $esquemas[] = $tm[1];
                }
            }
        }

        return [
            'ficheiros' => $ficheiros,
            'verificacoes' => $verificacoes,
            'esquemas' => array_values(array_unique($esquemas)),
        ];
    }
}
