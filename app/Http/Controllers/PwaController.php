<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        $icons = [];
        foreach ($this->sizes as $size) {
            $icons[] = [
                'src'     => url('/pwa/icon-' . $size . '.png?v=' . $version),
                'sizes'   => $size . 'x' . $size,
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        $manifest = [
            'name'             => $appName . ' — Software de Gestão Empresarial',
            'short_name'       => $shortName,
            'description'      => 'Software de gestão 100% angolano: faturação certificada AGT, POS offline, RH, hotel, salão e oficina.',
            'start_url'        => '/invoicing/offline/pos',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'any',
            'theme_color'      => '#1e40af',
            'background_color' => '#f8fafc',
            'lang'             => 'pt-AO',
            'dir'              => 'ltr',
            'categories'       => ['business', 'productivity', 'finance'],
            'icons'            => $icons,
            'shortcuts'        => [
                [
                    'name'       => 'Início',
                    'short_name' => 'Início',
                    'url'        => '/home',
                    'icons'      => [['src' => url('/pwa/icon-96.png?v=' . $version), 'sizes' => '96x96']],
                ],
                [
                    'name'       => 'POS - Ponto de Venda',
                    'short_name' => 'POS',
                    'url'        => '/invoicing/pos',
                    'icons'      => [['src' => url('/pwa/icon-96.png?v=' . $version), 'sizes' => '96x96']],
                ],
                [
                    'name'       => 'Faturação',
                    'short_name' => 'Faturação',
                    'url'        => '/invoicing/dashboard',
                    'icons'      => [['src' => url('/pwa/icon-96.png?v=' . $version), 'sizes' => '96x96']],
                ],
                [
                    'name'       => 'PWA Offline',
                    'short_name' => 'Offline',
                    'url'        => '/invoicing/offline',
                    'icons'      => [['src' => url('/pwa/icon-96.png?v=' . $version), 'sizes' => '96x96']],
                ],
            ],
            'prefer_related_applications' => false,
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');
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
        if (!$logoPath || !is_file($logoPath)) {
            $fallback = public_path('pwa/default/icon-' . $size . 'x' . $size . '.png');
            if (!is_file($fallback)) {
                $fallback = public_path('pwa/default/icon-192x192.png');
            }
            if (is_file($fallback)) {
                return response()->file($fallback, [
                    'Content-Type'  => 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }
            abort(404);
        }

        // Usar cache local em /public/pwa para servir rápido nas próximas
        $cacheFile = public_path('pwa/cache/icon-' . $size . '-' . substr(md5_file($logoPath), 0, 8) . '.png');
        if (!is_dir(dirname($cacheFile))) {
            @mkdir(dirname($cacheFile), 0755, true);
        }

        if (!is_file($cacheFile)) {
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
            'Content-Type'  => 'image/png',
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
        if (!is_file($swPath)) {
            $swPath = public_path('sw.js'); // legado
        }
        if (!is_file($swPath)) {
            abort(404);
        }

        $content = file_get_contents($swPath);
        $version = $this->buildVersion();

        // Substitui a constante CACHE_VERSION para refletir versão de deploy
        $content = preg_replace(
            "/const\s+CACHE_VERSION\s*=\s*['\"][^'\"]+['\"];/",
            "const CACHE_VERSION = 'soserp-" . $version . "';",
            $content,
            1
        );

        return response($content, 200, [
            'Content-Type'           => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control'          => 'no-cache, no-store, must-revalidate',
            'Pragma'                 => 'no-cache',
            'Expires'                => '0',
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
            $full = $publicPath . DIRECTORY_SEPARATOR . $f;
            if (is_file($full)) {
                $report[] = "DEL  {$f} ... " . (@unlink($full) ? 'OK' : 'FALHOU');
            }
        }

        // 2) Mover ícones estáticos legados para pwa/default/
        if (is_dir($pwaPath)) {
            $defaultDir = $pwaPath . DIRECTORY_SEPARATOR . 'default';
            if (!is_dir($defaultDir)) {
                @mkdir($defaultDir, 0755, true);
            }

            $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
            foreach ($sizes as $size) {
                $name = "icon-{$size}x{$size}.png";
                $src = $pwaPath . DIRECTORY_SEPARATOR . $name;
                $dst = $defaultDir . DIRECTORY_SEPARATOR . $name;
                if (is_file($src)) {
                    if (!@rename($src, $dst)) {
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
    protected function buildVersion(): string
    {
        return Cache::remember('pwa.version', 60, function () {
            $parts = [];
            $sw = resource_path('pwa/sw.js');
            if (is_file($sw)) {
                $parts[] = filemtime($sw);
            }
            $logo = function_exists('app_logo_path') ? app_logo_path() : null;
            if ($logo && is_file($logo)) {
                $parts[] = filemtime($logo);
            }
            $parts[] = config('app.version', '1.0');
            return substr(md5(implode('-', $parts)), 0, 10);
        });
    }
}
