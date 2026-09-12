<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * EXECUTAR SCRIPTS — os ficheiros PHP da pasta `scripts/`, e o fim do log.
 *
 * O que o componente deixava passar:
 *
 *  · O NOME DO SCRIPT VINHA DO BROWSER e ia para `require base_path('scripts/'
 *    . $nome)`: com `../` corria qualquer ficheiro PHP do servidor. Agora tem
 *    de ser um dos que a lista mostra.
 *  · O LOG ERA LIDO INTEIRO para mostrar as últimas cem linhas. Um
 *    `laravel.log` de produção passa facilmente as centenas de megas, e a
 *    página morria por falta de memória. Lê-se só o fim do ficheiro.
 */
class ScriptsApiController extends Controller
{
    private const LINHAS_DO_LOG = 100;

    /** Quanto do fim do ficheiro se lê para achar as últimas linhas. */
    private const BYTES_DO_FIM = 256 * 1024;

    public function index(): JsonResponse
    {
        return response()->json([
            'scripts' => $this->scripts(),
            'log' => $this->fimDoLog(),
        ]);
    }

    public function log(): JsonResponse
    {
        return response()->json(['log' => $this->fimDoLog()]);
    }

    public function correr(Request $request): JsonResponse
    {
        $dados = $request->validate(['script' => ['required', 'string', 'max:255']]);

        $script = collect($this->scripts())->firstWhere('nome', $dados['script']);

        if (! $script) {
            throw ValidationException::withMessages(['script' => __('Script não encontrado!')]);
        }

        $caminho = base_path('scripts/'.$script['nome']);

        Log::info("📜 Executando script: {$script['nome']}", ['user' => auth()->user()?->name, 'user_id' => auth()->id()]);

        $inicio = microtime(true);
        $nivel = ob_get_level();
        ob_start();

        try {
            (static function (string $__ficheiro) {
                require $__ficheiro;
            })($caminho);

            $saida = (string) ob_get_clean();
            $tempo = round(microtime(true) - $inicio, 2);

            Log::info("✅ Script executado com sucesso: {$script['nome']}", ['execution_time' => $tempo.'s', 'output_length' => strlen($saida)]);

            return response()->json([
                'ok' => true,
                'saida' => $saida,
                'message' => __('Script executado com sucesso em :tempo s', ['tempo' => $tempo]),
                'log' => $this->fimDoLog(),
            ]);
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivel) {
                ob_end_clean();
            }

            Log::error("❌ Erro ao executar script: {$script['nome']}", ['error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'saida' => __('Erro ao executar script:')."\n\n".$e->getMessage(),
                'message' => __('Erro ao executar script!'),
                'log' => $this->fimDoLog(),
            ]);
        }
    }

    public function limparLog(): JsonResponse
    {
        $ficheiro = storage_path('logs/laravel.log');

        if (File::exists($ficheiro)) {
            File::put($ficheiro, '');
        }

        return response()->json(['message' => __('Logs limpos com sucesso!')]);
    }

    private function scripts(): array
    {
        $pasta = base_path('scripts');

        if (! File::isDirectory($pasta)) {
            return [];
        }

        // Só PHP: a página corre-os por `require`. Os ftp_*.ps1, os .json e os
        // .md da mesma pasta ficam de fora.
        return collect(File::files($pasta))
            ->filter(fn ($f) => strtolower($f->getExtension()) === 'php')
            ->map(fn ($f) => [
                'nome' => $f->getFilename(),
                'tamanho' => $f->getSize(),
                'modificado_em' => date('c', $f->getMTime()),
                'descricao' => $this->descricao($f->getPathname()),
            ])
            ->sortBy('nome')
            ->values()
            ->all();
    }

    private function descricao(string $caminho): ?string
    {
        $inicio = (string) file_get_contents($caminho, false, null, 0, 4096);

        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\s*\n/s', $inicio, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/^<\?php\s*\n\s*\/\/\s*(.+)/m', $inicio, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** As últimas linhas do log, das mais recentes para as mais antigas. */
    private function fimDoLog(): array
    {
        $ficheiro = storage_path('logs/laravel.log');

        if (! File::exists($ficheiro) || filesize($ficheiro) === 0) {
            return [];
        }

        $tamanho = filesize($ficheiro);
        $h = fopen($ficheiro, 'rb');
        fseek($h, max(0, $tamanho - self::BYTES_DO_FIM));
        $fim = (string) stream_get_contents($h);
        fclose($h);

        $linhas = preg_split('/\r?\n/', $fim);

        // A primeira linha pode ter ficado cortada a meio pelo salto.
        if ($tamanho > self::BYTES_DO_FIM) {
            array_shift($linhas);
        }

        $linhas = array_values(array_filter($linhas, fn ($l) => $l !== ''));

        return array_map(
            fn ($l) => mb_strimwidth($l, 0, 2000, '…'),
            array_reverse(array_slice($linhas, -self::LINHAS_DO_LOG))
        );
    }
}
