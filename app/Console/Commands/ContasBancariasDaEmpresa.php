<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Treasury\Account;
use App\Models\Treasury\Bank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * As coordenadas bancárias que saem nos documentos.
 *
 * O QUE ISTO ESCREVE. Uma conta de tesouraria por banco, marcada para aparecer
 * nas facturas (`show_on_invoice`) e pela ordem que se quiser. Os modelos dos
 * documentos já trazem o quadro «DADOS BANCÁRIOS»; o que faltava eram as
 * contas.
 *
 * O IBAN É A CHAVE. É ele que identifica a conta em qualquer banco, e é por ele
 * que se decide se uma conta já existe — correr isto duas vezes não duplica
 * coordenadas na factura. Os espaços que se escrevem por hábito
 * (AO06 0040 0000 …) são só para ler: guarda-se sem eles.
 *
 * BANCO QUE NÃO EXISTE É CRIADO. O catálogo de bancos é da plataforma e não da
 * empresa; faltar um banco não pode impedir uma empresa de pôr a sua conta na
 * factura.
 *
 * A SECO por omissão.
 */
class ContasBancariasDaEmpresa extends Command
{
    protected $signature = 'empresas:contas-bancarias
                            {--tenant= : id da empresa}
                            {--dados= : lista de contas em JSON codificado em base64}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Cria as contas bancárias de uma empresa e põe-nas nos documentos';

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada. Use --tenant=<id>.');

            return self::FAILURE;
        }

        $contas = $this->lista();

        if ($contas === null) {
            return self::FAILURE;
        }

        $this->line(str_repeat('=', 70));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line($this->option('aplicar') ? ' MODO: --aplicar — VAI GRAVAR.' : ' MODO: simulação.');
        $this->line(str_repeat('=', 70));

        $jaLaEstao = Account::where('tenant_id', $empresa->id)->get();

        if ($jaLaEstao->isNotEmpty()) {
            $this->line(' Contas que a empresa já tem:');
            foreach ($jaLaEstao as $c) {
                $this->line(sprintf('   #%-5d %-34s %s', $c->id, $c->account_name, $c->iban));
            }
            $this->newLine();
        }

        $criadas = 0;
        $existiam = 0;
        $bancosNovos = [];

        DB::beginTransaction();

        try {
            foreach ($contas as $i => $c) {
                $nomeBanco = trim((string) ($c['banco'] ?? ''));
                $codigo = strtoupper(trim((string) ($c['codigo'] ?? '')));
                $conta = trim((string) ($c['conta'] ?? ''));
                $iban = strtoupper(preg_replace('/\s+/', '', (string) ($c['iban'] ?? '')));
                $ordem = (int) ($c['ordem'] ?? ($i + 1));

                if ($nomeBanco === '' || $iban === '') {
                    $this->warn("  linha " . ($i + 1) . ": sem banco ou sem IBAN — ignorada");
                    continue;
                }

                $banco = $this->banco($codigo, $nomeBanco, $bancosNovos);

                if (Account::where('tenant_id', $empresa->id)->where('iban', $iban)->exists()) {
                    $this->line(sprintf('  = %-38s %s   (já existia)', $nomeBanco, $iban));
                    $existiam++;
                    continue;
                }

                $registo = new Account([
                    'bank_id'               => $banco->id,
                    'account_name'          => $nomeBanco,
                    'account_number'        => $conta,
                    'iban'                  => $iban,
                    'currency'              => 'AOA',
                    'account_type'          => 'corrente',
                    'initial_balance'       => 0,
                    'current_balance'       => 0,
                    'is_active'             => true,
                    // É para isto que servem: aparecerem no papel.
                    'show_on_invoice'       => true,
                    'invoice_display_order' => $ordem,
                ]);

                $registo->tenant_id = $empresa->id;
                $registo->save();

                $this->line(sprintf('  + %-38s %s   ordem %d', $nomeBanco, $iban, $ordem));
                $criadas++;
            }

            $this->newLine();
            $this->line(sprintf('  a criar: %d   já existiam: %d   bancos novos no catálogo: %d',
                $criadas, $existiam, count($bancosNovos)));

            if ($bancosNovos) {
                $this->line('  ' . implode(', ', $bancosNovos));
            }

            /*
             * OS DOCUMENTOS MOSTRAM QUATRO.
             *
             * Os controladores dos documentos limitam a quatro contas. Uma
             * quinta fica gravada e nunca aparece no papel — e isso descobre-se
             * ao olhar para uma factura, não aqui.
             */
            $noPapel = Account::where('tenant_id', $empresa->id)
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->count();

            if ($noPapel > 4) {
                $this->warn("  ATENÇÃO: {$noPapel} contas marcadas para o papel, e os documentos só mostram 4.");
            }

            if ($this->option('aplicar')) {
                DB::commit();
                $this->newLine();
                $this->info("Gravado: {$criadas} contas na empresa #{$empresa->id}.");
            } else {
                DB::rollBack();
                $this->newLine();
                $this->warn('SIMULAÇÃO — nada gravado. Repita com --aplicar.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nada foi gravado. ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** A lista, do bloco base64 (os nomes dos bancos têm espaços). */
    private function lista(): ?array
    {
        $bloco = $this->option('dados');

        if (!$bloco) {
            $this->error('Falta --dados com a lista das contas em JSON base64.');

            return null;
        }

        $json = json_decode((string) base64_decode($bloco, true), true);

        if (!is_array($json) || !$json) {
            $this->error('O --dados não é um JSON válido em base64.');

            return null;
        }

        return $json;
    }

    /** O banco do catálogo — pelo código, pelo nome, ou criado se faltar. */
    private function banco(string $codigo, string $nome, array &$novos): Bank
    {
        if ($codigo !== '') {
            $b = Bank::where('code', $codigo)->first();
            if ($b) return $b;
        }

        $b = Bank::where('name', 'like', '%' . $nome . '%')->first();
        if ($b) return $b;

        $b = Bank::create([
            'name'      => $nome,
            'code'      => $codigo ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nome), 0, 6)),
            'country'   => 'Angola',
            'is_active' => true,
        ]);

        $novos[] = $b->name . ' (' . $b->code . ')';

        return $b;
    }
}
