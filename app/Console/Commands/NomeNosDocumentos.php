<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Qual dos nomes da empresa sai impresso nos documentos.
 *
 * Sem --usar apenas LISTA: quem tem os dois nomes diferentes, e portanto quem
 * nota a diferença. Serve para ver o impacto antes de mexer.
 *
 *   php artisan empresas:nome-documentos
 *   php artisan empresas:nome-documentos --tenant=57 --usar=comercial --aplicar
 */
class NomeNosDocumentos extends Command
{
    protected $signature = 'empresas:nome-documentos
                            {--tenant= : id da empresa (sem isto, lista todas)}
                            {--usar= : social ou comercial}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Mostra ou define que nome da empresa sai nos documentos';

    public function handle(): int
    {
        $usar = $this->option('usar');

        if ($usar !== null && !in_array($usar, [InvoicingSettings::NOME_SOCIAL, InvoicingSettings::NOME_COMERCIAL], true)) {
            $this->error('--usar tem de ser "social" ou "comercial".');

            return self::FAILURE;
        }

        if ($usar === null) {
            return $this->listar();
        }

        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $antes = $empresa->nomeParaDocumentos();

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id}");
        $this->line('  nome comercial:      ' . ($empresa->name ?: '(vazio)'));
        $this->line('  designação social:   ' . ($empresa->company_name ?: '(vazio)'));
        $this->line('  sai hoje:            ' . $antes);

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $empresa->id],
            ['nome_nos_documentos' => $usar]
        );

        InvoicingSettings::esquecerMemoria($empresa->id);

        $this->newLine();
        $this->info('  ✓ passa a sair: ' . $empresa->fresh()->nomeParaDocumentos());

        return self::SUCCESS;
    }

    /** Quem tem os dois nomes diferentes — só esses notam a escolha. */
    private function listar(): int
    {
        $linhas = [];
        $iguais = 0;
        $semSocial = 0;

        foreach (Tenant::orderBy('id')->get(['id', 'name', 'company_name']) as $e) {
            $comercial = trim((string) $e->name);
            $social = trim((string) $e->company_name);

            if ($social === '') {
                $semSocial++;

                continue;
            }

            if (mb_strtolower($social) === mb_strtolower($comercial)) {
                $iguais++;

                continue;
            }

            $escolha = optional(InvoicingSettings::forTenant($e->id))->nome_nos_documentos
                ?: InvoicingSettings::NOME_SOCIAL;

            $linhas[] = [$e->id, $comercial, $social, $escolha, $e->nomeParaDocumentos()];
        }

        $this->newLine();
        $this->line('  empresas sem designação social (nada muda para elas): ' . $semSocial);
        $this->line('  empresas com os dois nomes iguais (nada muda):        ' . $iguais);
        $this->line('  empresas onde a escolha se nota:                      ' . count($linhas));
        $this->newLine();

        if ($linhas) {
            $this->table(['id', 'nome comercial', 'designação social', 'escolha', 'sai nos documentos'], $linhas);
        }

        return self::SUCCESS;
    }
}
