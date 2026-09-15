<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Rules\NifDeEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * O NIF DE UMA EMPRESA — pelo endereço de manutenção, com os travões do ecrã.
 *
 * Existe para as contas de testes (`empresas:conta-de-teste`), que nascem sem
 * NIF e ficavam presas no «preencha o NIF da empresa» à entrada. Numa empresa
 * a sério o NIF muda-se no ecrã de dados da empresa, por quem pode.
 *
 * TRAVÕES:
 *  · o mesmo formato do registo (`NifDeEmpresa`: 9 ou 10 dígitos, começa por 5);
 *  · um NIF que outra empresa já tem não se repete;
 *  · uma empresa que JÁ EMITIU documentos fiscais não muda de NIF por aqui — o
 *    NIF vai impresso e assinado em cada um, e trocá-lo por baixo deixava o
 *    papel e a base a dizerem coisas diferentes.
 *
 * Simulação por omissão.
 */
class NifDaEmpresa extends Command
{
    protected $signature = 'empresas:nif
                            {--tenant= : id da empresa}
                            {--nif= : o NIF novo}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Define o NIF de uma empresa que ainda não emitiu documentos fiscais (simulação por omissão)';

    /** As tabelas dos documentos que levam o NIF da empresa assinado. */
    private const FISCAIS = [
        'invoicing_sales_invoices' => "status NOT IN ('draft')",
        'invoicing_credit_notes' => "status NOT IN ('draft')",
        'invoicing_debit_notes' => "status NOT IN ('draft')",
        'invoicing_receipts' => '1 = 1',
    ];

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (! $empresa) {
            $this->error('Empresa não encontrada. Use --tenant=<id>.');

            return self::FAILURE;
        }

        $nif = preg_replace('/\s+/', '', (string) $this->option('nif'));
        $validacao = Validator::make(['nif' => $nif], ['nif' => [new NifDeEmpresa()]]);

        if ($validacao->fails()) {
            $this->error($validacao->errors()->first('nif'));

            return self::FAILURE;
        }

        $this->line("<options=bold>EMPRESA #{$empresa->id} — {$empresa->name}</>");
        $this->line('  NIF actual: ' . ($empresa->nif ?: '(nenhum)'));
        $this->line("  NIF novo:   {$nif}");

        if ($outra = Tenant::where('nif', $nif)->whereKeyNot($empresa->id)->first()) {
            $this->error("A empresa #{$outra->id} ({$outra->name}) já tem este NIF. Nada foi gravado.");

            return self::FAILURE;
        }

        $emitidos = 0;
        foreach (self::FISCAIS as $tabela => $condicao) {
            $emitidos += (int) DB::table($tabela)->where('tenant_id', $empresa->id)->whereRaw($condicao)->count();
        }

        if ($emitidos > 0) {
            $this->error("A empresa já emitiu {$emitidos} documento(s) fiscal(is) com o NIF actual. Mude-o no ecrã de dados da empresa, se for mesmo preciso. Nada foi gravado.");

            return self::FAILURE;
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->warn('SIMULAÇÃO. Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $empresa->forceFill(['nif' => $nif])->save();

        $this->newLine();
        $this->info("NIF da empresa #{$empresa->id} gravado: {$nif}.");

        return self::SUCCESS;
    }
}
