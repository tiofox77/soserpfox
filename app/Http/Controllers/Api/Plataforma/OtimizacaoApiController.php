<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Services\OpcacheService;
use App\Services\Plataforma\ExecutarArtisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A OPTIMIZAÇÃO DO SISTEMA — o OPcache, os caches do Laravel e o `.user.ini`.
 *
 * Duas correcções em relação ao componente:
 *
 *  · AS DEFINIÇÕES ACTUAIS mostravam `validate_timestamps = 1` num servidor
 *    que o tinha a 0: `(int) ini_get(...) ?: 1` troca o zero verdadeiro pelo
 *    valor por omissão. Era precisamente em produção, onde se desliga, que o
 *    ecrã mentia.
 *  · O `.USER.INI` sai de UMA função, usada pela descarga e pela gravação, e
 *    os valores são validados antes de irem parar a um ficheiro que o PHP do
 *    servidor lê.
 */
class OtimizacaoApiController extends Controller
{
    public const PERFIS = [
        'production' => ['validate_timestamps' => 0, 'revalidate_freq' => 0, 'max_input_vars' => 3000, 'memory_limit' => '512M', 'max_execution_time' => 360],
        'development' => ['validate_timestamps' => 1, 'revalidate_freq' => 2, 'max_input_vars' => 1000, 'memory_limit' => '512M', 'max_execution_time' => 300],
    ];

    public function __construct(private OpcacheService $opcache) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'opcache' => $this->opcache->getFormattedStats(),
            'saude' => $this->opcache->getHealthStatus(),
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars'),
            ],
            'actuais' => [
                'environment' => app()->environment('production') ? 'production' : 'development',
                'validate_timestamps' => $this->iniInteiro('opcache.validate_timestamps', 1),
                'revalidate_freq' => $this->iniInteiro('opcache.revalidate_freq', 2),
                'max_input_vars' => $this->iniInteiro('max_input_vars', 1000),
                'memory_limit' => ini_get('memory_limit') ?: '512M',
                'max_execution_time' => $this->iniInteiro('max_execution_time', 300),
            ],
            'perfis' => self::PERFIS,
        ]);
    }

    public function limparOpcache(): JsonResponse
    {
        if (! $this->opcache->clear()) {
            return response()->json(['message' => __('Erro ao limpar OPcache. Verifique se está ativo.')], 422);
        }

        return response()->json(['message' => __('OPcache limpo com sucesso!')]);
    }

    public function limparCaches(ExecutarArtisan $artisan): JsonResponse
    {
        try {
            foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $comando) {
                $artisan->correr($comando);
            }

            if ($this->opcache->isEnabled()) {
                $this->opcache->clear();
            }
        } catch (\Throwable $e) {
            Log::error('Optimização: limpar os caches falhou.', ['erro' => $e->getMessage()]);

            return response()->json(['message' => __('Erro ao limpar caches: :erro', ['erro' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => __('Todos os caches limpos com sucesso!')]);
    }

    public function otimizar(ExecutarArtisan $artisan): JsonResponse
    {
        try {
            foreach (['config:cache', 'route:cache', 'view:cache'] as $comando) {
                $artisan->correr($comando);
            }
        } catch (\Throwable $e) {
            Log::error('Optimização: optimizar falhou.', ['erro' => $e->getMessage()]);

            return response()->json(['message' => __('Erro ao otimizar: :erro', ['erro' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => __('Sistema otimizado com sucesso!')]);
    }

    /** Grava o `.user.ini` na raiz do projecto. */
    public function gerarIni(Request $request): JsonResponse
    {
        $conteudo = self::ficheiroIni($this->validarIni($request));

        try {
            file_put_contents(base_path('.user.ini'), $conteudo);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Erro ao gerar arquivo: :erro', ['erro' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => __('Arquivo .user.ini gerado com sucesso! Faça upload para o cPanel.')]);
    }

    /** A descarga é um ficheiro, e por isso uma rota de página. */
    public function descarregarIni(Request $request): StreamedResponse
    {
        $conteudo = self::ficheiroIni($this->validarIni($request));

        return response()->streamDownload(fn () => print($conteudo), '.user.ini', ['Content-Type' => 'text/plain']);
    }

    private function validarIni(Request $request): array
    {
        return $request->validate([
            'environment' => ['required', 'in:production,development'],
            'validate_timestamps' => ['required', 'in:0,1'],
            'revalidate_freq' => ['required', 'integer', 'min:0', 'max:3600'],
            'max_input_vars' => ['required', 'integer', 'min:100', 'max:100000'],
            // Só o formato que o PHP entende: um número com K, M ou G, ou -1.
            'memory_limit' => ['required', 'regex:/^(-1|\d{1,5}[KMG]?)$/i'],
            'max_execution_time' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);
    }

    public static function ficheiroIni(array $c): string
    {
        $quando = now()->format('Y-m-d H:i:s');
        $memoria = strtoupper((string) $c['memory_limit']);

        return <<<INI
; ============================================
; Configurações PHP - SOSERP
; Gerado em: {$quando}
; Ambiente: {$c['environment']}
; ============================================

; === PERFORMANCE & MEMORY ===
memory_limit = {$memoria}
max_execution_time = {$c['max_execution_time']}
max_input_vars = {$c['max_input_vars']}
max_input_time = 300

; === UPLOADS ===
upload_max_filesize = 64M
post_max_size = 64M

; === OPCACHE SETTINGS ===
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = {$c['validate_timestamps']}
opcache.revalidate_freq = {$c['revalidate_freq']}
opcache.save_comments = 1
opcache.fast_shutdown = 1

; === SESSION ===
session.gc_maxlifetime = 1440
session.cookie_lifetime = 0

; === ERROR REPORTING ===
display_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; ============================================
; INSTRUÇÕES DE USO (cPanel):
; 1. Faça upload deste arquivo para a pasta public_html
; 2. Nome do arquivo: .user.ini
; 3. Aguarde 5 minutos para aplicar
; 4. Limpe o OPcache no painel
; ============================================
INI;
    }

    private function iniInteiro(string $chave, int $omissao): int
    {
        $valor = ini_get($chave);

        return $valor === false || $valor === '' ? $omissao : (int) $valor;
    }
}
