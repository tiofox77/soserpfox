<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Tenant;
use App\Services\Invoicing\SeriesCatalog;
use Illuminate\Console\Command;

/**
 * Aplica o esquema canónico de séries a todas as empresas.
 *
 * O que faz, por empresa e por tipo de documento:
 *   1. Já existe série canónica com o código certo → nada.
 *   2. Existe série do tipo, sem documentos e sem registo AGT → renomeia.
 *   3. Existe série do tipo mas JÁ USADA ou registada na AGT → NÃO TOCA, e
 *      cria a canónica ao lado apenas se ainda não houver nenhuma com o código.
 *   4. Não existe série do tipo → cria a canónica.
 *
 * O ponto 3 é o que torna isto seguro: o código da série faz parte do número
 * que já saiu para o cliente e foi comunicado à AGT. Renomeá-lo quebraria a
 * correspondência com o que a autoridade tem registado.
 */
class SeriesApplyCanonicalCommand extends Command
{
    protected $signature = 'series:canonical
                            {--dry-run : Mostra o que faria, sem gravar}
                            {--tenant= : Limitar a uma empresa}';

    protected $description = 'Aplica o esquema canónico de séries (SOSFR, SOSFT, SOSNC, ...) a todas as empresas';

    public function handle(): int
    {
        $simulacao = (bool) $this->option('dry-run');

        if ($simulacao) {
            $this->warn('MODO SIMULAÇÃO — nada será gravado.');
        }

        $empresas = Tenant::when($this->option('tenant'), fn ($q) => $q->where('id', $this->option('tenant')))
            ->orderBy('id')
            ->get();

        $criadas = $renomeadas = $intocaveis = $jaCertas = 0;

        foreach ($empresas as $empresa) {
            $this->line("<fg=cyan>Empresa {$empresa->id}</> — {$empresa->name}");

            foreach (SeriesCatalog::canonico() as $canonica) {
                $resultado = $this->aplicar($empresa->id, $canonica, $simulacao);

                match ($resultado['accao']) {
                    'criada'     => $criadas++,
                    'renomeada'  => $renomeadas++,
                    'intocavel'  => $intocaveis++,
                    default      => $jaCertas++,
                };

                if ($resultado['accao'] !== 'nada') {
                    $this->line('   ' . $resultado['mensagem']);
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d · %s %d · já corretas %d · intocáveis %d',
            $simulacao ? 'Criaria' : 'Criadas', $criadas,
            $simulacao ? 'renomearia' : 'renomeadas', $renomeadas,
            $jaCertas, $intocaveis
        ));

        if ($intocaveis > 0) {
            $this->newLine();
            $this->warn(
                'As intocáveis já emitiram documentos ou estão registadas na AGT. '
                . 'O código faz parte do número comunicado — não se renomeia.'
            );
        }

        return self::SUCCESS;
    }

    /** @return array{accao: string, mensagem: string} */
    private function aplicar(int $tenantId, array $canonica, bool $simulacao): array
    {
        // 1. Já existe com o código certo?
        $comCodigoCerto = InvoicingSeries::where('tenant_id', $tenantId)
            ->where('series_code', $canonica['code'])
            ->first();

        if ($comCodigoCerto) {
            return ['accao' => 'nada', 'mensagem' => ''];
        }

        // 2/3. Existe alguma série deste tipo de documento?
        $doTipo = InvoicingSeries::where('tenant_id', $tenantId)
            ->where('document_type', $canonica['document_type'])
            ->orderBy('id')
            ->get();

        $renomeavel = $doTipo->first(fn ($s) => SeriesCatalog::podeRenomear($s));

        if ($renomeavel) {
            $antigo = $renomeavel->series_code;

            if (!$simulacao) {
                $renomeavel->update([
                    'series_code' => $canonica['code'],
                    'name'        => $canonica['name'],
                    'prefix'      => $canonica['prefix'],
                    'is_default'  => true,
                ]);
            }

            return [
                'accao'    => 'renomeada',
                'mensagem' => "<fg=yellow>renomeada</> {$antigo} → {$canonica['code']} ({$canonica['document_type']})",
            ];
        }

        // Há séries do tipo, mas todas em uso: criar a canónica ao lado.
        if ($doTipo->isNotEmpty()) {
            $emUso = $doTipo->map(fn ($s) => $s->series_code . ' (' . SeriesCatalog::documentosEmitidos($s) . ' doc)')->implode(', ');

            $this->line("   <fg=red>intocável</> {$canonica['document_type']}: {$emUso}");

            // Não criar uma segunda série por omissão: duas default do mesmo
            // tipo tornariam a escolha do getIssuanceSeries arbitrária.
            return [
                'accao'    => 'intocavel',
                'mensagem' => '',
            ];
        }

        // 4. Não há nenhuma: criar.
        if (!$simulacao) {
            InvoicingSeries::create([
                'tenant_id'     => $tenantId,
                'series_code'   => $canonica['code'],
                'name'          => $canonica['name'],
                'prefix'        => $canonica['prefix'],
                'document_type' => $canonica['document_type'],
                'next_number'   => 1,
                'is_default'    => true,
                'is_active'     => true,
            ]);
        }

        return [
            'accao'    => 'criada',
            'mensagem' => "<fg=green>criada</> {$canonica['code']} ({$canonica['document_type']})",
        ];
    }
}
