<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Services\OpcacheService;
use App\Services\Plataforma\ExecutarArtisan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * AS ACTUALIZAÇÕES DO SISTEMA PELAS RELEASES DO GITHUB.
 *
 * O que o componente deixava passar:
 *
 *  · A VERSÃO A INSTALAR VINHA DO BROWSER e entrava tal e qual no endereço de
 *    descarga e no caminho do ficheiro (`storage/updates/{versao}.zip`). Agora
 *    tem de ser a etiqueta de uma release que o GitHub devolve, e só com os
 *    caracteres de uma etiqueta.
 *  · A LISTA ERA ESTADO DO COMPONENTE; agora é pedida ao carregar no botão,
 *    como antes — não se vai ao GitHub só por abrir a página.
 */
class ActualizacoesApiController extends Controller
{
    private const REPOSITORIO = 'tiofox77/soserpfox';

    private const PROTEGIDOS = [
        '.env', 'storage/logs', 'storage/framework/sessions', 'storage/framework/views',
        'storage/framework/cache', 'storage/app/public', 'vendor', '.git', '.gitignore', 'node_modules',
    ];

    private array $registo = [];

    public function index(): JsonResponse
    {
        return response()->json([
            'versao_actual' => $this->versaoActual(),
            'repositorio' => self::REPOSITORIO,
        ]);
    }

    public function releases(): JsonResponse
    {
        try {
            $releases = $this->buscarReleases();
        } catch (ConnectionException $e) {
            Log::warning('GitHub API timeout', ['error' => $e->getMessage()]);

            return response()->json(['message' => __('Sem conexão com GitHub. Verifique sua internet ou tente mais tarde.'), 'releases' => []], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'releases' => []], 422);
        }

        return response()->json([
            'releases' => $releases,
            'message' => $releases === [] ? __('Nenhuma release encontrada no repositório') : __(':n releases encontradas!', ['n' => count($releases)]),
        ]);
    }

    public function instalar(Request $request, ExecutarArtisan $artisan, OpcacheService $opcache): JsonResponse
    {
        $dados = $request->validate([
            'versao' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\-]*$/'],
        ], ['versao.regex' => __('A versão só pode ter letras, números, ponto, hífen e sublinhado.')]);

        $versao = $dados['versao'];

        try {
            $existe = collect($this->buscarReleases())->contains('tag_name', $versao);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Não foi possível confirmar a versão no GitHub: :erro', ['erro' => $e->getMessage()])], 422);
        }

        if (! $existe) {
            throw ValidationException::withMessages(['versao' => __('Essa versão não é uma release do repositório.')]);
        }

        try {
            $this->anotar('🚀 '.__('Iniciando atualização do sistema para v:versao', ['versao' => $versao]));
            $this->anotar('📦 '.__('Criando backup de segurança...'));
            $this->criarBackup();
            $this->anotar('✅ '.__('Backup criado com sucesso'), 'success');

            $this->anotar('⬇️ '.__('Baixando versão :versao do GitHub...', ['versao' => $versao]));
            $zip = $this->descarregar($versao);
            $this->anotar('✅ '.__('Download concluído'), 'success');

            $this->anotar('📂 '.__('Extraindo arquivos da atualização...'));
            $this->extrair($zip);
            $this->anotar('✅ '.__('Arquivos extraídos e copiados'), 'success');

            $this->anotar('🔧 '.__('Executando migrations do banco de dados...'));
            $artisan->correr('migrate', ['--force' => true]);
            $this->anotar('✅ '.__('Migrations executadas com sucesso'), 'success');

            $this->anotar('🧹 '.__('Limpando cache do sistema...'));
            foreach (['optimize:clear', 'view:clear', 'route:clear', 'config:clear'] as $comando) {
                $artisan->correr($comando);
                $this->anotar("  → {$comando}");
            }

            if ($opcache->isEnabled()) {
                $opcache->clear()
                    ? $this->anotar('  → '.__('OPcache limpo com sucesso'), 'success')
                    : $this->anotar('  → '.__('OPcache: falha ao limpar'), 'warning');
            } else {
                $this->anotar('  → '.__('OPcache não está ativo'));
            }

            $this->anotar('⚡ '.__('Otimizando sistema para produção...'));
            try {
                foreach (['config:cache', 'route:cache', 'view:cache'] as $comando) {
                    $artisan->correr($comando);
                    $this->anotar("  → {$comando}", 'success');
                }
            } catch (\Throwable $e) {
                $this->anotar('  → '.__('Erro ao otimizar: :erro', ['erro' => $e->getMessage()]), 'warning');
            }

            File::put(base_path('version.txt'), $versao);
            $this->anotar('✅ '.__('Versão atualizada: :versao', ['versao' => $versao]), 'success');
            $this->anotar('🎉 '.__('Atualização concluída com sucesso!'), 'success');
            $this->anotar('💡 '.__('Recarregue a página (F5 ou Ctrl+R) para ver as mudanças'));
        } catch (\Throwable $e) {
            Log::error('Actualização do sistema falhou.', ['versao' => $versao, 'erro' => $e->getMessage()]);
            $this->anotar('❌ '.__('ERRO: :erro', ['erro' => $e->getMessage()]), 'error');
            $this->anotar('💡 '.__('Dica: Verifique os logs para mais detalhes'), 'error');

            return response()->json(['ok' => false, 'registo' => $this->registo, 'message' => __('Erro na atualização: :erro', ['erro' => $e->getMessage()])], 422);
        }

        return response()->json([
            'ok' => true,
            'registo' => $this->registo,
            'versao_actual' => $versao,
            'message' => __('Sistema atualizado para v:versao! Recarregue a página!', ['versao' => $versao]),
        ]);
    }

    private function versaoActual(): string
    {
        $ficheiro = base_path('version.txt');

        return File::exists($ficheiro) ? trim(File::get($ficheiro)) : '5.0.0';
    }

    private function buscarReleases(): array
    {
        $resposta = Http::timeout(30)->retry(3, 100, throw: false)
            ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'SOS-ERP-System'])
            ->get('https://api.github.com/repos/'.self::REPOSITORIO.'/releases');

        if (! $resposta->successful()) {
            throw new \RuntimeException(__('Erro ao buscar releases: HTTP :codigo', ['codigo' => $resposta->status()]));
        }

        $actual = $this->versaoActual();

        return collect($resposta->json() ?? [])->map(fn ($r) => [
            'tag_name' => $r['tag_name'] ?? 'unknown',
            'name' => $r['name'] ?? $r['tag_name'] ?? 'Release',
            'body' => $r['body'] ?? '',
            'published_at' => $r['published_at'] ?? null,
            'prerelease' => (bool) ($r['prerelease'] ?? false),
            'is_newer' => version_compare(ltrim($r['tag_name'] ?? '0.0.0', 'v'), ltrim($actual, 'v'), '>'),
            'is_current' => ltrim($r['tag_name'] ?? '', 'v') === ltrim($actual, 'v'),
        ])->values()->all();
    }

    private function criarBackup(): void
    {
        $pasta = storage_path('backups');
        File::ensureDirectoryExists($pasta);

        $zip = new ZipArchive();

        if ($zip->open($pasta.'/backup_'.date('Y-m-d_H-i-s').'.zip', ZipArchive::CREATE) !== true) {
            throw new \RuntimeException(__('Não foi possível criar o backup.'));
        }

        foreach (File::allFiles(base_path('app')) as $f) {
            $zip->addFile($f->getRealPath(), str_replace(base_path().DIRECTORY_SEPARATOR, '', $f->getRealPath()));
        }

        $zip->close();
    }

    private function descarregar(string $versao): string
    {
        $resposta = Http::timeout(120)->get('https://github.com/'.self::REPOSITORIO.'/archive/refs/tags/'.rawurlencode($versao).'.zip');

        if (! $resposta->successful()) {
            throw new \RuntimeException(__('Falha ao baixar release'));
        }

        File::ensureDirectoryExists(storage_path('updates'));
        $caminho = storage_path('updates/'.$versao.'.zip');
        File::put($caminho, $resposta->body());

        return $caminho;
    }

    private function extrair(string $caminhoDoZip): void
    {
        $zip = new ZipArchive();

        if ($zip->open($caminhoDoZip) !== true) {
            throw new \RuntimeException(__('Falha ao extrair arquivo ZIP'));
        }

        $destino = storage_path('updates/extracted');
        File::deleteDirectory($destino);
        File::makeDirectory($destino, 0755, true);
        $zip->extractTo($destino);
        $zip->close();

        $origem = File::directories($destino)[0] ?? $destino;

        foreach (File::allFiles($origem) as $f) {
            $relativo = str_replace($origem.DIRECTORY_SEPARATOR, '', $f->getRealPath());

            if (collect(self::PROTEGIDOS)->contains(fn ($p) => str_starts_with(str_replace('\\', '/', $relativo), $p))) {
                continue;
            }

            $alvo = base_path($relativo);
            File::ensureDirectoryExists(dirname($alvo));
            File::copy($f->getRealPath(), $alvo);
        }

        $this->anotar('📦 '.__('Atualizando dependências Composer...'));

        if (File::exists(base_path('composer.json'))) {
            exec('cd '.escapeshellarg(base_path()).' && composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1', $saida, $codigo);
            $codigo === 0
                ? $this->anotar('✅ '.__('Composer atualizado'), 'success')
                : $this->anotar('⚠️ '.__('Aviso: Erro ao atualizar Composer'), 'warning');
        }

        File::deleteDirectory($destino);
        File::delete($caminhoDoZip);
    }

    private function anotar(string $mensagem, string $tipo = 'info'): void
    {
        $this->registo[] = ['hora' => now()->format('H:i:s'), 'mensagem' => $mensagem, 'tipo' => $tipo];
    }
}
