<?php

namespace App\Console\Commands;

use App\Http\Controllers\PwaController;
use App\Models\PwaDevice;
use Illuminate\Console\Command;

/**
 * O inventário dos aparelhos com PWA, em texto.
 *
 * O ecrã do super admin mostra o mesmo, e melhor. Isto existe para se poder
 * perguntar sem abrir sessão — e sobretudo para se ver DEPOIS DE UM DEPLOY se
 * os aparelhos apanharam a versão nova, que é a pergunta que motivou tudo isto.
 *
 * SÓ LÊ.
 */
class AparelhosComPwa extends Command
{
    protected $signature = 'pwa:aparelhos
                            {--atrasados : Só os que não têm a última versão}
                            {--tenant= : Só esta empresa}';

    protected $description = 'Lista os aparelhos que correm o PWA, com a versão que cada um tem (só lê)';

    public function handle(): int
    {
        $actual = app(PwaController::class)->buildVersion();

        $this->newLine();
        $this->line('<options=bold>APARELHOS COM PWA</>');
        $this->line('Versão servida agora: <fg=cyan>' . $actual . '</>');
        $this->newLine();

        $aparelhos = PwaDevice::query()
            ->with(['tenant:id,name', 'user:id,name'])
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', (int) $this->option('tenant')))
            ->when($this->option('atrasados'), fn ($q) => $q->where(function ($w) use ($actual) {
                $w->whereNull('app_version')->orWhere('app_version', '!=', $actual);
            }))
            ->orderByDesc('last_seen_at')
            ->limit(200)
            ->get();

        if ($aparelhos->isEmpty()) {
            $this->info($this->option('atrasados')
                ? 'Nenhum aparelho atrasado — todos têm a última versão.'
                : 'Ainda nenhum aparelho se identificou.');

            return self::SUCCESS;
        }

        $linhas = $aparelhos->map(function (PwaDevice $a) use ($actual) {
            $atrasado = $a->app_version !== $actual;

            return [
                $a->tenant?->name ?? '(apagada)',
                mb_substr($a->device_uuid, 0, 12),
                ($atrasado ? '⚠ ' : '  ') . ($a->app_version ?: 'não diz'),
                $a->standalone ? 'instalado' : 'browser',
                $a->last_seen_at?->diffForHumans() ?? '—',
                $a->syncs,
            ];
        })->all();

        $this->table(['Empresa', 'Aparelho', 'Versão', 'Como', 'Visto', 'Sincs'], $linhas);

        $atrasados = $aparelhos->filter(fn ($a) => $a->app_version !== $actual)->count();

        $this->newLine();

        if ($atrasados > 0) {
            // Cada aparelho atrasado é uma correcção que não chegou a quem
            // vende. É o número que interessa, e por isso vai em vermelho.
            $this->error("{$atrasados} aparelho(s) sem a última versão.");

            return self::FAILURE;
        }

        $this->info('Todos os aparelhos têm a última versão.');

        return self::SUCCESS;
    }
}
