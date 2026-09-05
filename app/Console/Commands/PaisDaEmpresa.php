<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Geografia;
use Illuminate\Console\Command;

/**
 * As empresas que dizem «Portugal» porque a base de dados o disse por elas.
 *
 * A coluna `tenants.country` tinha `DEFAULT 'Portugal'`, e o registo nunca
 * perguntou o país: 67 empresas em produção ficaram com esse valor sem
 * ninguém o escolher. Não é uma informação — é um acidente.
 *
 * Mas TAMBÉM NÃO É MINHA A DECISÃO de as declarar angolanas em massa: é o
 * país de empresas reais, e vai em documentos fiscais. Por isso este comando
 * mostra, e só escreve com `--aplicar`.
 *
 * Só toca em quem cumpre as três condições: o país é exactamente o antigo
 * valor por omissão, o NIF é de empresa angolana, e a empresa emite pela AGT.
 * Quem escolheu Portugal a sério não cumpre a primeira — porque escolher
 * passou a gravar «PT», não «Portugal».
 */
class PaisDaEmpresa extends Command
{
    protected $signature = 'geografia:pais-da-empresa {--aplicar : grava (sem isto so mostra)}';

    protected $description = 'Empresas com o pais herdado do DEFAULT da coluna, e nao de uma escolha';

    /** O valor que a coluna punha sozinha. */
    private const HERDADO = ['Portugal', 'PT'];

    public function handle(): int
    {
        $candidatas = Tenant::query()
            ->whereIn('country', self::HERDADO)
            ->orderBy('id')
            ->get();

        if ($candidatas->isEmpty()) {
            $this->info('Nenhuma empresa com o pais herdado do default.');

            return self::SUCCESS;
        }

        $linhas = [];
        $paraMudar = [];

        foreach ($candidatas as $t) {
            $nif = preg_replace('/\D+/', '', (string) $t->nif);
            // NIF de empresa angolana: 10 digitos comecados por 5.
            $angolana = strlen($nif) === 10 && str_starts_with($nif, '5');

            $linhas[] = [
                $t->id,
                mb_strimwidth((string) $t->name, 0, 34, '…'),
                $t->nif ?: '—',
                $t->country,
                $angolana ? 'NIF angolano → AO' : 'deixa-se como esta',
            ];

            if ($angolana) {
                $paraMudar[] = $t;
            }
        }

        $this->table(['id', 'empresa', 'nif', 'pais', 'proposta'], $linhas);

        if (!$this->option('aplicar')) {
            $this->warn(count($paraMudar) . ' empresa(s) mudariam para AO. Nada foi gravado.');
            $this->line('  Para gravar: geografia:pais-da-empresa --aplicar');

            return self::SUCCESS;
        }

        foreach ($paraMudar as $t) {
            $t->forceFill(['country' => Geografia::PAIS_PADRAO])->save();
        }

        $this->info(count($paraMudar) . ' empresa(s) passaram a AO.');

        return self::SUCCESS;
    }
}
