<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\ExecutarArtisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * OS COMANDOS DO SISTEMA — artisan e seeders pela interface.
 *
 * O que o componente deixava passar:
 *
 *  · O SEEDER VINHA DO BROWSER. `selectedSeeder` era uma propriedade pública
 *    do Livewire e ia direita a `db:seed --class=`: qualquer classe, e não só
 *    as da lista. Agora tem de ser uma das que a lista mostra.
 *  · OS PARÂMETROS TAMBÉM: o plano, o módulo e a empresa escolhidos passam a
 *    ser validados contra a base antes de irem para o comando.
 *  · O RESULTADO DO SEEDER lia `Artisan::output()` depois de passar um buffer
 *    próprio, que o deixa vazio — dizia sempre «executado sem output».
 *  · A SAÍDA DEIXA DE SER HTML montado no servidor: vão linhas com o tipo, e o
 *    ecrã desenha-as.
 */
class ComandosApiController extends Controller
{
    private const HISTORICO = 'logs/command_history.json';

    /** Seeders que não aparecem: dados de ensaio e ficheiros que não são seeders. */
    private const EXCLUIDOS = ['EventTestSeeder', 'InvoicingTestSeeder', 'MultiTenantTestSeeder', 'WorkshopTestDataSeeder', 'imported_accounts'];

    public static function comandos(): array
    {
        return [
            'cache_clear' => ['name' => 'Limpar Todo Cache', 'description' => 'Limpar cache, config, routes e views de uma só vez', 'command' => 'optimize:clear', 'icon' => 'fa-broom', 'color' => 'laranja', 'group' => 'Cache & Performance', 'params' => []],
            'optimize' => ['name' => 'Otimizar Aplicação', 'description' => 'Cachear config, routes e views para máxima performance', 'command' => 'optimize', 'icon' => 'fa-bolt', 'color' => 'aviso', 'group' => 'Cache & Performance', 'params' => []],
            'config_clear' => ['name' => 'Limpar Cache de Config', 'description' => 'Remover cache de configurações (usar após alterar .env)', 'command' => 'config:clear', 'icon' => 'fa-eraser', 'color' => 'neutra', 'group' => 'Cache & Performance', 'params' => []],
            'view_clear' => ['name' => 'Limpar Cache de Views', 'description' => 'Remover views compiladas (usar após alterar templates)', 'command' => 'view:clear', 'icon' => 'fa-eye-slash', 'color' => 'neutra', 'group' => 'Cache & Performance', 'params' => []],

            'migrate' => ['name' => 'Executar Migrations', 'description' => 'Executar migrations pendentes do banco de dados', 'command' => 'migrate', 'icon' => 'fa-database', 'color' => 'perigo', 'group' => 'Base de Dados', 'params' => [
                'force' => ['label' => 'Forçar execução (produção)', 'type' => 'checkbox'],
            ]],
            'migrate_status' => ['name' => 'Status das Migrations', 'description' => 'Ver quais migrations foram executadas e quais estão pendentes', 'command' => 'migrate:status', 'icon' => 'fa-list-check', 'color' => 'neutra', 'group' => 'Base de Dados', 'params' => []],
            'seeders_status' => ['name' => 'Status dos Seeders', 'description' => 'Ver quais seeders foram executados e quais estão pendentes', 'command' => 'seeders:status', 'icon' => 'fa-seedling', 'color' => 'bom', 'group' => 'Base de Dados', 'params' => []],

            'sync_modules' => ['name' => 'Sincronizar Módulos dos Planos', 'description' => 'Sincronizar módulos de um plano com os tenants que têm subscription ativa', 'command' => 'plan:sync-modules', 'icon' => 'fa-arrows-rotate', 'color' => 'primaria', 'group' => 'Módulos & Planos', 'params' => [
                'plan_id' => ['label' => 'ID do Plano (opcional)', 'type' => 'select', 'options' => 'plans'],
                'module' => ['label' => 'Slug do Módulo (opcional)', 'type' => 'select', 'options' => 'modules'],
                'all' => ['label' => 'Sincronizar todos os planos', 'type' => 'checkbox'],
            ]],
            'attach_module_tenant' => ['name' => 'Vincular Módulo ao Tenant', 'description' => 'Vincular um módulo específico a um tenant ou a todos', 'command' => 'module:attach', 'icon' => 'fa-link', 'color' => 'bom', 'group' => 'Módulos & Planos', 'params' => [
                'module_slug' => ['label' => 'Slug do Módulo', 'type' => 'select', 'options' => 'modules', 'required' => true],
                'tenant_id' => ['label' => 'ID do Tenant (deixe vazio para todos)', 'type' => 'select', 'options' => 'tenants'],
            ]],
            'attach_module_plan' => ['name' => 'Vincular Módulo ao Plano', 'description' => 'Vincular um módulo específico a um plano ou a todos', 'command' => 'module:attach-plan', 'icon' => 'fa-layer-group', 'color' => 'roxo', 'group' => 'Módulos & Planos', 'params' => [
                'module_slug' => ['label' => 'Slug do Módulo', 'type' => 'select', 'options' => 'modules', 'required' => true],
                'plan_id' => ['label' => 'ID do Plano (deixe vazio para todos)', 'type' => 'select', 'options' => 'plans'],
            ]],

            'fix_roles_permissions' => ['name' => 'Corrigir Roles e Permissões', 'description' => 'Corrige roles, permissões e utilizadores sem role em todos os tenants', 'command' => 'users:fix-roles-permissions', 'icon' => 'fa-user-shield', 'color' => 'teal', 'group' => 'Segurança', 'params' => [
                'dry-run' => ['label' => 'Modo Teste (Dry-Run) - Simula sem fazer alterações', 'type' => 'checkbox'],
            ]],

            'storage_link' => ['name' => 'Criar Link do Storage', 'description' => 'Criar link simbólico public/storage → storage/app/public', 'command' => 'storage:link', 'icon' => 'fa-folder-open', 'color' => 'primaria', 'group' => 'Sistema', 'params' => []],

            'patch_deploy' => ['name' => 'Aplicar Patch de Deploy', 'description' => 'Executar pipeline completo: migrations, seeders, permissões, cache (v1.3.0)', 'command' => 'patch:deploy', 'icon' => 'fa-rocket', 'color' => 'rosa', 'group' => 'Deploy', 'params' => [
                'force' => ['label' => 'Forçar (sem confirmação)', 'type' => 'checkbox'],
                'dry-run' => ['label' => 'Modo Teste (Dry-Run) — Simula sem alterar nada', 'type' => 'checkbox'],
            ]],
            'seeders_run_pending' => ['name' => 'Executar Seeders Pendentes', 'description' => 'Executar apenas os seeders que ainda não foram registados em seeder_logs', 'command' => 'seeders:status', 'icon' => 'fa-seedling', 'color' => 'bom', 'group' => 'Deploy', 'params' => [
                'run' => ['label' => 'Executar pendentes', 'type' => 'hidden', 'default' => true],
            ]],
        ];
    }

    public function index(): JsonResponse
    {
        $seeders = $this->seeders();
        $executados = collect($seeders)->where('executado', true)->count();

        return response()->json([
            'comandos' => collect(self::comandos())->map(fn ($c, $chave) => [
                'chave' => $chave,
                'nome' => __($c['name']),
                'descricao' => __($c['description']),
                'comando' => $c['command'],
                'icone' => $c['icon'],
                'cor' => $c['color'],
                'grupo' => __($c['group']),
                'parametros' => collect($c['params'])
                    ->reject(fn ($p) => $p['type'] === 'hidden')
                    ->map(fn ($p, $nome) => ['nome' => $nome, 'rotulo' => __($p['label']), 'tipo' => $p['type'], 'opcoes' => $p['options'] ?? null, 'obrigatorio' => (bool) ($p['required'] ?? false)])
                    ->values(),
            ])->values(),
            'opcoes' => [
                'plans' => Plan::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => "{$p->name} (#{$p->id})"]),
                'modules' => Module::orderBy('name')->get(['slug', 'name'])->map(fn ($m) => ['valor' => $m->slug, 'rotulo' => "{$m->name} ({$m->slug})"]),
                'tenants' => Tenant::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => "{$t->name} (#{$t->id})"]),
            ],
            'seeders' => $seeders,
            'numeros_dos_seeders' => ['total' => count($seeders), 'executados' => $executados, 'pendentes' => count($seeders) - $executados],
            'historico' => $this->historico(),
        ]);
    }

    public function correr(Request $request, string $chave, ExecutarArtisan $artisan): JsonResponse
    {
        $definicao = self::comandos()[$chave] ?? abort(404);
        $parametros = $this->parametros($request, $definicao);

        // Sem terminal interactivo na web: um comando que pergunta «tem a
        // certeza?» ficava à espera para sempre.
        if (isset($definicao['params']['force'])) {
            $parametros['--force'] = true;
        }

        $linhas = [
            ['tipo' => 'info', 'texto' => __('Iniciando comando: :nome', ['nome' => __($definicao['name'])])],
            ['tipo' => 'info', 'texto' => 'php artisan '.$definicao['command'].$this->comoTexto($parametros)],
            ['tipo' => 'separador', 'texto' => ''],
        ];

        try {
            $r = $artisan->correr($definicao['command'], $parametros);

            if ($r['saida'] !== '') {
                $linhas[] = ['tipo' => 'saida', 'texto' => $r['saida']];
            }

            $ok = $r['codigo'] === 0;
            $linhas[] = ['tipo' => 'separador', 'texto' => ''];
            $linhas[] = $ok
                ? ['tipo' => 'sucesso', 'texto' => __('Comando executado com sucesso!')]
                : ['tipo' => 'erro', 'texto' => __('O comando terminou com o código :codigo.', ['codigo' => $r['codigo']])];

            $this->guardarNoHistorico($chave, __($definicao['name']), $ok, $r['saida']);
        } catch (\Throwable $e) {
            Log::error('Comandos do sistema: falhou.', ['comando' => $definicao['command'], 'erro' => $e->getMessage()]);

            $linhas[] = ['tipo' => 'erro', 'texto' => __('ERRO: :erro', ['erro' => $e->getMessage()])];
            $this->guardarNoHistorico($chave, __($definicao['name']), false, $e->getMessage());
            $ok = false;
        }

        return response()->json(['ok' => $ok, 'linhas' => $linhas, 'historico' => $this->historico()]);
    }

    public function semear(Request $request, ExecutarArtisan $artisan): JsonResponse
    {
        $dados = $request->validate(['seeder' => ['required', 'string', 'max:255']]);

        // SÓ UM DOS DA LISTA. O nome da classe vinha do browser e ia direito ao
        // db:seed: qualquer classe carregável servia.
        $seeder = collect($this->seeders())->firstWhere('namespace', $dados['seeder']);

        if (! $seeder) {
            throw ValidationException::withMessages(['seeder' => __('Esse seeder não está na lista.')]);
        }

        $linhas = [
            ['tipo' => 'info', 'texto' => __('Iniciando Seeder: :nome', ['nome' => $seeder['classe']])],
            ['tipo' => 'info', 'texto' => 'php artisan db:seed --class='.$seeder['namespace']],
            ['tipo' => 'separador', 'texto' => ''],
        ];
        $nome = __('Seeder: :nome', ['nome' => $seeder['nome']]);

        try {
            $r = $artisan->correr('db:seed', ['--class' => $seeder['namespace'], '--force' => true]);

            $linhas[] = ['tipo' => 'saida', 'texto' => $r['saida'] !== '' ? $r['saida'] : __('Seeder executado sem output (sucesso silencioso)')];
            $linhas[] = ['tipo' => 'separador', 'texto' => ''];
            $linhas[] = ['tipo' => 'sucesso', 'texto' => __('Seeder executado com sucesso!')];
            $this->guardarNoHistorico('seeder_'.$seeder['classe'], $nome, true, $r['saida'] ?: 'Executado com sucesso');
            $ok = true;
        } catch (\Throwable $e) {
            Log::error('Comandos do sistema: o seeder falhou.', ['seeder' => $seeder['namespace'], 'erro' => $e->getMessage()]);

            $linhas[] = ['tipo' => 'erro', 'texto' => __('ERRO: :erro', ['erro' => $e->getMessage()])];
            $this->guardarNoHistorico('seeder_'.$seeder['classe'], $nome, false, $e->getMessage());
            $ok = false;
        }

        return response()->json(['ok' => $ok, 'linhas' => $linhas, 'historico' => $this->historico()]);
    }

    public function limparHistorico(): JsonResponse
    {
        File::delete(storage_path(self::HISTORICO));

        return response()->json(['message' => __('Histórico limpo com sucesso!')]);
    }

    private function parametros(Request $request, array $definicao): array
    {
        $regras = [];

        foreach ($definicao['params'] as $nome => $p) {
            $campo = 'parametros.'.$nome;

            $regras[$campo] = match ($p['type']) {
                'checkbox' => ['nullable', 'boolean'],
                'hidden' => ['prohibited'],
                default => [($p['required'] ?? false) ? 'required' : 'nullable', match ($p['options'] ?? null) {
                    'plans' => 'exists:plans,id',
                    'modules' => 'exists:modules,slug',
                    'tenants' => 'exists:tenants,id',
                    default => 'string',
                }],
            };
        }

        $validos = $request->validate($regras)['parametros'] ?? [];
        $parametros = [];

        foreach ($definicao['params'] as $nome => $p) {
            $valor = $validos[$nome] ?? null;

            if ($p['type'] === 'hidden') {
                if ($p['default'] ?? false) {
                    $parametros["--{$nome}"] = true;
                }
            } elseif ($p['type'] === 'checkbox') {
                if ($valor) {
                    $parametros["--{$nome}"] = true;
                }
            } elseif ($valor !== null && $valor !== '') {
                $parametros[$nome] = $valor;
            }
        }

        return $parametros;
    }

    private function comoTexto(array $parametros): string
    {
        return collect($parametros)->map(fn ($v, $k) => $v === true ? " {$k}" : (str_starts_with($k, '--') ? " {$k}={$v}" : " {$v}"))->implode('');
    }

    private function seeders(): array
    {
        $raiz = database_path('seeders');

        if (! File::isDirectory($raiz)) {
            return [];
        }

        $registados = Schema::hasTable('seeder_logs') ? DB::table('seeder_logs')->pluck('executed_at', 'seeder')->all() : [];
        $lista = [];

        $juntar = function (string $ficheiro, ?string $pasta) use (&$lista, $registados) {
            if (! str_ends_with($ficheiro, '.php')) {
                return;
            }

            $classe = substr(basename($ficheiro), 0, -4);

            if ($classe === 'DatabaseSeeder' && $pasta === null) {
                return;
            }

            if (in_array($classe, self::EXCLUIDOS, true) || str_contains($classe, 'Test')) {
                return;
            }

            $quando = ($pasta ? ($registados[$pasta.'\\'.$classe] ?? null) : null) ?? ($registados[$classe] ?? null);

            $lista[] = [
                'classe' => $classe,
                'namespace' => 'Database\\Seeders\\'.($pasta ? $pasta.'\\' : '').$classe,
                'nome' => preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('Seeder', '', $classe)),
                'categoria' => $pasta ?? 'Geral',
                'executado' => $quando !== null,
                'executado_em' => $quando,
            ];
        };

        foreach (File::files($raiz) as $f) {
            $juntar($f->getFilename(), null);
        }

        foreach (File::directories($raiz) as $pasta) {
            foreach (File::files($pasta) as $f) {
                $juntar($f->getFilename(), basename($pasta));
            }
        }

        usort($lista, fn ($a, $b) => [$a['categoria'], $a['nome']] <=> [$b['categoria'], $b['nome']]);

        return $lista;
    }

    private function historico(): array
    {
        $ficheiro = storage_path(self::HISTORICO);

        if (! File::exists($ficheiro)) {
            return [];
        }

        return array_reverse(json_decode(File::get($ficheiro), true) ?? []);
    }

    private function guardarNoHistorico(string $chave, string $nome, bool $ok, ?string $saida): void
    {
        try {
            $ficheiro = storage_path(self::HISTORICO);
            $historico = File::exists($ficheiro) ? (json_decode(File::get($ficheiro), true) ?? []) : [];

            $historico[] = [
                'command_key' => $chave,
                'command_name' => $nome,
                'success' => $ok,
                'output' => mb_substr((string) $saida, 0, 1000),
                'executed_by' => auth()->user()?->name ?? 'Sistema',
                'executed_at' => now()->toDateTimeString(),
            ];

            File::put($ficheiro, json_encode(array_slice($historico, -50), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('Falha ao salvar histórico de comandos: '.$e->getMessage());
        }
    }
}
