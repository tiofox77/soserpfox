<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Controlador PWA — manifest dinâmico, ícones gerados a partir do logo do sistema
 * e service worker com versão atrelada ao deploy (auto-update).
 */
class PwaController extends Controller
{
    /**
     * Tamanhos de ícone usados no manifest.
     */
    protected array $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

    /**
     * Retorna o manifest.webmanifest com ícones apontando para a rota dinâmica.
     */
    public function manifest()
    {
        $version = $this->buildVersion();
        $appName = function_exists('app_name') ? app_name() : config('app.name', 'SOS ERP');
        $shortName = mb_substr($appName, 0, 12);

        // DOIS CONJUNTOS DE ÍCONES, e não um a fazer de dois.
        //
        // Isto declarava tudo como `purpose: "any maskable"` — uma PROMESSA ao
        // sistema operativo de que o desenho tem margem de segurança e pode
        // ser recortado. Não tinha: o crachá do SOS toca as quatro bordas.
        // O Android acreditava, aplicava a máscara adaptativa do lançador, e o
        // anel azul saía cortado dos quatro lados no ecrã de quem instalou.
        //
        //   · `any`      — o crachá inteiro, para onde não há máscara
        //                  (separador do browser, atalhos do ambiente de
        //                  trabalho, iOS).
        //   · `maskable` — o mesmo crachá a 72% sobre fundo opaco, gerado pelo
        //                  `pwa:icones-mascaraveis`. O que a máscara corta é
        //                  fundo, e o logótipo chega inteiro.
        // AS DUAS ROTAS SÃO DINÂMICAS. `icon-{n}.png` e `icon-maskable-{n}.png`
        // passam pelo controlador e usam o logótipo DA EMPRESA quando ela tem
        // um quadrado; os ficheiros estáticos em /pwa são só o recurso de
        // último caso. Apontar o manifesto aos estáticos tirava a cada empresa
        // o seu próprio ícone — foi o que quase aconteceu aqui.
        $icons = [];

        foreach ($this->sizes as $size) {
            $icons[] = [
                'src' => url('/pwa/icon-'.$size.'.png?v='.$version),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
                'purpose' => 'any',
            ];
        }

        foreach ($this->sizes as $size) {
            $icons[] = [
                'src' => url('/pwa/icon-maskable-'.$size.'.png?v='.$version),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        $manifest = [
            // A IDENTIDADE DA APLICAÇÃO, e não estava aqui.
            //
            // Sem `id`, o navegador identifica a aplicação instalada pelo
            // `start_url`. Mudar esse endereço — coisa que já aconteceu neste
            // produto — fazia nascer uma aplicação NOVA: a que o operador
            // tinha no ecrã principal ficava órfã, a apontar para uma versão
            // que já não recebe actualizações, e ele não tinha como saber.
            //
            // Fixo e independente do start_url, de propósito.
            'id' => '/sos-erp-pwa',

            'name' => $appName.' — Software de Gestão Empresarial',
            'short_name' => $shortName,
            'description' => 'Software de gestão 100% angolano: faturação certificada AGT, POS offline, RH, hotel, salão e oficina.',
            'start_url' => '/invoicing/offline/pos',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => pwa_theme_color(),
            'background_color' => '#f8fafc',
            'lang' => 'pt-AO',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity', 'finance'],
            'icons' => $icons,
            'shortcuts' => [
                [
                    'name' => 'Início',
                    'short_name' => 'Início',
                    'url' => '/home',
                    'icons' => [['src' => url('/pwa/icon-96.png?v='.$version), 'sizes' => '96x96']],
                ],
                [
                    'name' => 'POS - Ponto de Venda',
                    'short_name' => 'POS',
                    'url' => '/invoicing/pos',
                    'icons' => [['src' => url('/pwa/icon-96.png?v='.$version), 'sizes' => '96x96']],
                ],
                [
                    'name' => 'Faturação',
                    'short_name' => 'Faturação',
                    'url' => '/invoicing/dashboard',
                    'icons' => [['src' => url('/pwa/icon-96.png?v='.$version), 'sizes' => '96x96']],
                ],
                [
                    'name' => 'PWA Offline',
                    'short_name' => 'Offline',
                    'url' => '/invoicing/offline',
                    'icons' => [['src' => url('/pwa/icon-96.png?v='.$version), 'sizes' => '96x96']],
                ],
            ],
            'prefer_related_applications' => false,
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * O ícone MASCARÁVEL — o que o Android recorta à máscara do lançador.
     *
     * A diferença para o `icon()` é uma margem, e é ela que separa um ícone
     * bonito de um ícone cortado: a zona segura da máscara é um círculo com
     * 80% do lado, e tudo o que ficar fora dela pode desaparecer. Aqui o
     * desenho vai a 72% sobre fundo opaco — o que a máscara corta é fundo.
     *
     * O fundo tem de ser OPACO. Com transparência, o lançador de alguns
     * telemóveis põe preto por baixo e o ícone sai como uma silhueta.
     */
    public function iconeMascaravel(int $size)
    {
        $size = max(16, min(1024, $size));

        $origem = $this->origemDoIcone();

        if (! $origem) {
            abort(404);
        }

        $cache = public_path('pwa/cache/maskable-'.$size.'-'.substr(md5_file($origem), 0, 8).'.png');

        if (! is_dir(dirname($cache))) {
            @mkdir(dirname($cache), 0755, true);
        }

        if (! is_file($cache)) {
            try {
                $interior = (int) round($size * 0.72);

                $tela = Image::create($size, $size)->fill('#ffffff');
                $tela->place(Image::read($origem)->scaleDown($interior, $interior), 'center');
                $tela->toPng()->save($cache);
            } catch (\Throwable $e) {
                report($e);

                // Sem conseguir compor, serve-se o mascarável estático gerado
                // pelo `pwa:icones-mascaraveis`. Um ícone certo de outra fonte
                // vale mais do que um 404 que impede a instalação.
                $estatico = public_path('pwa/icon-maskable-'.$size.'x'.$size.'.png');

                if (is_file($estatico)) {
                    return response()->file($estatico, ['Content-Type' => 'image/png']);
                }

                abort(404);
            }
        }

        return response()->file($cache, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * De onde sai o desenho do ícone: o logótipo da empresa se for quadrado,
     * senão o crachá da marca.
     */
    private function origemDoIcone(): ?string
    {
        $logo = function_exists('app_logo_path') ? app_logo_path() : null;

        if ($logo && is_file($logo)) {
            $medidas = @getimagesize($logo);

            if ($medidas && $medidas[1] > 0) {
                $proporcao = $medidas[0] / $medidas[1];

                // Um logótipo largo não dá um ícone de telemóvel: encolhido ao
                // meio de um quadrado fica ilegível.
                if ($proporcao >= 0.8 && $proporcao <= 1.25) {
                    return $logo;
                }
            }
        }

        $marca = public_path('pwa/icon-512x512.png');

        return is_file($marca) ? $marca : null;
    }

    /**
     * Serve um ícone PNG para o tamanho indicado.
     * Usa o logo da aplicação (system_setting `app_logo`); se não existir,
     * recorre aos PNGs estáticos pré-gerados em /public/pwa.
     */
    public function icon(int $size)
    {
        $size = max(16, min(1024, $size));

        $logoPath = function_exists('app_logo_path') ? app_logo_path() : null;

        // Um logótipo largo não dá um ícone de telemóvel. O da SOSERP é
        // 822x412 — emblema à esquerda, "SOS ERP" à direita — e o que ia
        // parar ao ecrã inicial era o banner encolhido ao meio de um quadrado
        // branco, ilegível. Nesses casos usa-se o ícone da marca.
        if ($logoPath && is_file($logoPath)) {
            $medidas = @getimagesize($logoPath);

            if ($medidas && $medidas[1] > 0) {
                $proporcao = $medidas[0] / $medidas[1];

                if ($proporcao < 0.8 || $proporcao > 1.25) {
                    $logoPath = null;
                }
            }
        }

        // Sem logo do sistema → fallback para o PNG default mais próximo
        if (! $logoPath || ! is_file($logoPath)) {
            $fallback = public_path('pwa/default/icon-'.$size.'x'.$size.'.png');
            if (! is_file($fallback)) {
                $fallback = public_path('pwa/default/icon-192x192.png');
            }
            if (is_file($fallback)) {
                return response()->file($fallback, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }
            abort(404);
        }

        // Usar cache local em /public/pwa para servir rápido nas próximas
        $cacheFile = public_path('pwa/cache/icon-'.$size.'-'.substr(md5_file($logoPath), 0, 8).'.png');
        if (! is_dir(dirname($cacheFile))) {
            @mkdir(dirname($cacheFile), 0755, true);
        }

        if (! is_file($cacheFile)) {
            try {
                $img = Image::read($logoPath)
                    ->scaleDown($size, $size);

                // Garantir canvas quadrado com fundo branco (compatível PWA)
                $canvas = Image::create($size, $size)->fill('#ffffff');
                $canvas->place(
                    $img,
                    'center'
                );
                $canvas->toPng()->save($cacheFile);
            } catch (\Throwable $e) {
                report($e);
                $fallback = public_path('pwa/icon-192x192.png');
                if (is_file($fallback)) {
                    return response()->file($fallback);
                }
                abort(500, 'Não foi possível gerar o ícone');
            }
        }

        return response()->file($cacheFile, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Serve o sw.js com versão dinâmica injectada — assim, sempre que o ficheiro
     * sw.js mude (ou o deploy mude), o browser detecta diff e atualiza o SW.
     */
    public function serviceWorker()
    {
        $swPath = resource_path('pwa/sw.js');
        if (! is_file($swPath)) {
            $swPath = public_path('sw.js'); // legado
        }
        if (! is_file($swPath)) {
            abort(404);
        }

        $content = file_get_contents($swPath);
        $version = $this->buildVersion();

        // Substitui a constante CACHE_VERSION para refletir versão de deploy
        $content = preg_replace(
            "/const\s+CACHE_VERSION\s*=\s*['\"][^'\"]+['\"];/",
            "const CACHE_VERSION = 'soserp-".$version."';",
            $content,
            1
        );

        // E A VERSÃO DOS FICHEIROS QUE ELE PRÉ-GUARDA.
        //
        // O `?v=` estava escrito à mão em três sítios (o sw.js, o layout e a
        // entrada) e tinha de ser subido à unha a cada alteração do motor. Quem
        // se esquecesse deixava o URL igual — e um URL igual é servido da cache
        // para sempre, mesmo com o ficheiro novo no servidor.
        //
        // Aconteceu, e viu-se num Android: produção tinha o motor novo, o
        // telemóvel corria o antigo, e a correcção deployada não chegou a quem
        // vende. Agora o número vem da versão do deploy, que muda sozinha
        // quando os bytes mudam.
        $content = preg_replace(
            "/(\/js\/[a-z0-9\-\.]+\.js)\?v=[^'\"]+/i",
            '$1?v='.$version,
            $content
        );

        // O PACOTE DO PWA, pelo nome de hoje. Leva hash e muda a cada
        // construção, portanto não pode estar escrito no sw.js — e se não
        // estiver na lista, a primeira abertura sem rede não tem aplicação
        // nenhuma para correr. O sw.js declara a lista vazia; enche-se aqui.
        $pacote = \App\Support\PacoteReact::doPwa();
        $content = preg_replace(
            '/const\s+PACOTE_DO_PWA\s*=\s*\[\s*\];/',
            'const PACOTE_DO_PWA = '.json_encode($pacote ? [$pacote] : [], JSON_UNESCAPED_SLASHES).';',
            $content,
            1
        );

        return response($content, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Remove os ficheiros estáticos PWA legados (manifest.json, sw.js, ícones)
     * para que as rotas dinâmicas Laravel sejam usadas. Idempotente.
     *
     * @return array Lista de ações executadas.
     */
    public static function performCleanup(): array
    {
        $report = [];
        $publicPath = public_path();
        $pwaPath = public_path('pwa');

        // 1) Apagar manifest.json e sw.js estáticos
        foreach (['manifest.json', 'sw.js'] as $f) {
            $full = $publicPath.DIRECTORY_SEPARATOR.$f;
            if (is_file($full)) {
                $report[] = "DEL  {$f} ... ".(@unlink($full) ? 'OK' : 'FALHOU');
            }
        }

        // 2) Mover ícones estáticos legados para pwa/default/
        if (is_dir($pwaPath)) {
            $defaultDir = $pwaPath.DIRECTORY_SEPARATOR.'default';
            if (! is_dir($defaultDir)) {
                @mkdir($defaultDir, 0755, true);
            }

            $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
            foreach ($sizes as $size) {
                $name = "icon-{$size}x{$size}.png";
                $src = $pwaPath.DIRECTORY_SEPARATOR.$name;
                $dst = $defaultDir.DIRECTORY_SEPARATOR.$name;
                if (is_file($src)) {
                    if (! @rename($src, $dst)) {
                        @copy($src, $dst);
                        @unlink($src);
                    }
                    $report[] = "MOVE {$name} -> pwa/default/";
                }
            }
        }

        // 3) Invalidar cache de versão para forçar regeneração
        try {
            Cache::forget('pwa.version');
        } catch (\Throwable $e) {
            // ignore
        }

        return $report;
    }

    /**
     * Verifica se há ficheiros estáticos PWA legados que devem ser removidos.
     * Check muito leve — apenas testa existência de 2 ficheiros.
     */
    public static function needsCleanup(): bool
    {
        return is_file(public_path('manifest.json'))
            || is_file(public_path('sw.js'));
    }

    /**
     * Versão usada para o cache do SW e para invalidar ícones/manifest.
     * Baseada no mtime do sw.js + mtime do logo do sistema (se existir).
     */
    /**
     * A versão como quem a lê a entende: a data e a hora da última alteração.
     *
     * O buildVersion é um md5 — serve para o service worker comparar, mas a
     * ninguém diz nada. "b0243ac3a1" não responde à única pergunta que se
     * faz ao olhar para ali: já tenho a correcção de hoje, ou não?
     *
     * Sai da MESMA lista de ficheiros vigiados, por isso muda exactamente
     * quando a outra muda — as duas nunca podem discordar.
     */
    /**
     * O número da versão que se lê no cabeçalho: `2.0.37`.
     *
     * SÉRIE.BUILD, com a série (`2.0`) escrita à mão em config/pwa.php quando
     * há uma mudança grande, e o BUILD a subir SOZINHO sempre que algum
     * ficheiro do PWA muda.
     *
     * PORQUE NÃO É ESCRITO À MÃO. Estava aqui o `changelog.current`, que é a
     * versão da release e se actualiza a mão: dizia `2026.08.19.1` num
     * aparelho a correr código do dia 27. Quem olha para o cabeçalho quer
     * responder a uma pergunta só — «isto mudou desde ontem?» — e um número
     * que alguém se esqueceu de subir responde mal. Este sobe sozinho ou não
     * sobe de todo.
     *
     * A contagem vive num ficheiro em storage/, que atravessa os deploys por
     * FTP. Se desaparecer, recomeça em 1: é um número para COMPARAR com o de
     * ontem, não um registo histórico.
     */
    public function numeroDeVersao(): string
    {
        return Cache::remember('pwa.versao.numero', 60, function () {
            $serie = (string) config('pwa.serie', '2.0');
            $assinatura = $this->buildVersion();
            $ficheiro = config('pwa.ficheiro_versao') ?: storage_path('app/pwa-versao.json');

            $estado = ['assinatura' => null, 'build' => 0];

            if (is_file($ficheiro)) {
                $lido = json_decode((string) @file_get_contents($ficheiro), true);
                if (is_array($lido)) {
                    $estado = array_merge($estado, $lido);
                }
            }

            if (($estado['assinatura'] ?? null) !== $assinatura) {
                $estado = ['assinatura' => $assinatura, 'build' => ((int) $estado['build']) + 1];

                // Escrita atómica: dois pedidos ao mesmo tempo depois de um
                // deploy podiam deixar o ficheiro a meio, e a versão seguinte
                // recomeçava do zero.
                $temporario = $ficheiro.'.'.getmypid().'.tmp';

                try {
                    @file_put_contents($temporario, json_encode($estado, JSON_PRETTY_PRINT));
                    @rename($temporario, $ficheiro);
                } catch (\Throwable $e) {
                    @unlink($temporario);
                }
            }

            return $serie.'.'.max(1, (int) $estado['build']);
        });
    }

    public function buildLabel(): string
    {
        return Cache::remember('pwa.version.label', 60, function () {
            $ultima = 0;

            foreach ($this->ficheirosVigiados() as $ficheiro) {
                $ultima = max($ultima, filemtime($ficheiro));
            }

            if (! $ultima) {
                return config('changelog.current', '1.0');
            }

            // No fuso de Angola: é a hora a que quem usa isto vive.
            return \Carbon\Carbon::createFromTimestamp($ultima, config('app.timezone'))
                ->format('d/m/Y H:i');
        });
    }

    public function buildVersion(): string
    {
        return Cache::remember('pwa.version', 60, fn () => $this->assinaturaDe($this->ficheirosVigiados()));
    }

    /**
     * A assinatura de um conjunto de ficheiros — PELO CONTEÚDO, NÃO PELA DATA.
     *
     * Isto lia `filemtime()`, e em produção a versão NÃO MUDOU depois de um
     * deploy que trocou o motor do PWA inteiro. Os aparelhos ficaram com o
     * código antigo — o service worker não se deu por actualizado, e uma
     * correcção deployada não chegou a quem vende. Foi apanhado a testar num
     * Android: o ficheiro em produção tinha a alteração e o telemóvel
     * continuava a correr a versão de antes.
     *
     * Porquê a data falha: depende do que o FTP escreve, do relógio do
     * servidor e do caminho que o `public_path()` resolve. Nenhuma dessas
     * coisas é o que interessa saber — o que interessa é se os BYTES mudaram,
     * e isso o md5 responde sem depender de nada.
     *
     * Recebe a lista em vez de a ir buscar para poder ser ensaiada com
     * ficheiros temporários: um ensaio que mexesse nos ficheiros verdadeiros
     * do PWA, com a suite a correr em 26 processos, mexia-lhes por baixo dos
     * pés aos outros.
     */
    public function assinaturaDe(array $ficheiros): string
    {
        $partes = [];

        foreach ($ficheiros as $ficheiro) {
            if (is_file($ficheiro)) {
                $partes[] = md5_file($ficheiro);
            }
        }

        $partes[] = config('app.version', '1.0');

        return substr(md5(implode('-', $partes)), 0, 10);
    }

    /**
     * Tudo aquilo cujos BYTES fazem a versão do PWA mudar.
     *
     * Está separado do `buildVersion()` por uma razão: é uma lista, e uma
     * lista esquece-se. Aqui pode ser conferida por um ensaio — o
     * `ManifestoDoPwaTest` exige que todo o `.js` que a página carrega esteja
     * cá dentro. Enterrada dentro de uma closure de cache, não podia.
     */
    /**
     * Os .js e .css que uma página do PWA carrega, sem o `?v=`.
     *
     * Está aqui, público e estático, para o `ficheirosVigiados()` e os ensaios
     * usarem A MESMA extracção. Cada um com a sua expressão regular era como
     * ter duas listas: divergiam, e a divergência não dava erro nenhum.
     */
    public static function assetsDoLayout(string $html): array
    {
        preg_match_all('/(?:src|href)="(\/[^"?]+\.(?:js|css))(?:\?[^"]*)?"/i', $html, $m);

        return array_values(array_unique($m[1]));
    }

    public function ficheirosVigiados(): array
    {
        // Tudo o que compõe a aplicação offline, e não só o service worker.
        //
        // Antes olhava para o sw.js e para o logótipo, e mais nada. Um
        // deploy que mudasse o motor do PWA ou as páginas do modo offline
        // deixava esta versão IGUAL — o service worker não se dava por
        // actualizado, os aparelhos ficavam com o que tinham, e não havia
        // como saber que versão cada um estava a correr. Aconteceu: num só
        // dia saíram alterações ao motor e às vistas sem esta linha mexer.
        $layout = resource_path('views/pwa/ecra.blade.php');

        $vigiados = [
            resource_path('pwa/sw.js'),
            $layout,
        ];

        // OS .js SAEM DO PRÓPRIO LAYOUT, e não de uma lista escrita aqui.
        //
        // Esta lista tinha só o `pwa-invoicing.js`. Entretanto o layout
        // passou a carregar o `pos-offline-ticket.js` e o `pwa-turno.js`
        // — e nenhum deles entrava na conta. Uma correcção só ao turno
        // deixava esta versão IGUAL: o `?v=` não mudava, o service worker
        // não se dava por actualizado, e a correcção não chegava a quem
        // vende. É exactamente a avaria que este mecanismo existe para
        // evitar, repetida um nível acima.
        //
        // Uma lista à mão esquece-se pelas mesmas razões que um `?v=` à
        // mão se esquece. Por isso vai-se buscar ao sítio onde não se pode
        // esquecer: se o ficheiro é carregado pela página, conta.
        if (is_file($layout)) {
            foreach (self::assetsDoLayout(file_get_contents($layout)) as $src) {
                $vigiados[] = public_path(ltrim($src, '/'));
            }
        }

        // E O PACOTE DO PWA — o motor, o papel e os onze ecrãs, num ficheiro
        // só. O nome leva hash, portanto um deploy que mude uma vírgula num
        // ecrã muda o ficheiro, e com ele esta versão.
        $pacote = \App\Support\PacoteReact::ficheiroDoPwa();

        if ($pacote) {
            $vigiados[] = $pacote;
        }

        // E o logótipo da empresa: entra nos ícones do manifesto, e por
        // isso também no que o aparelho tem em cache.
        $logo = function_exists('app_logo_path') ? app_logo_path() : null;

        if ($logo) {
            $vigiados[] = $logo;
        }

        // Só o que existe mesmo, sem repetidos, e por ordem estável: a versão
        // é um md5 de md5s, e trocar a ordem trocava a versão sem nada ter
        // mudado — os aparelhos actualizavam-se à toa.
        return array_values(array_filter(array_unique($vigiados), 'is_file'));
    }
}
