<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Uma só série padrão por (empresa, tipo de documento) — imposto pela base de dados.
 *
 * Sete sítios do código gravavam `is_default = true` ao criar uma série sem
 * verificarem se já existia outra padrão daquele tipo. Em produção mediram-se 8
 * casos com duas ou mais: qual delas a emissão usa depende da ordem que o MySQL
 * devolver, e é ela que decide o número do próximo documento fiscal. O código já
 * foi fechado (InvoicingSeries::tornarPadrao / deveNascerPadrao); isto fecha o
 * estado, para que nem um INSERT à mão nem um script futuro o consigam repor.
 *
 * COMO: coluna VIRTUAL `padrao_unico` que vale "<tenant>-<tipo>" quando a série é
 * padrão e NULL quando não é, com índice ÚNICO por cima. Num índice único do
 * MySQL vários NULL convivem, portanto as não-padrão não se estorvam umas às
 * outras — é o truque que permite exigir unicidade só sobre uma parte das linhas.
 * A coluna é virtual: não ocupa espaço, é calculada na leitura.
 *
 * PORQUE NÃO REBENTA: antes de criar o índice contam-se as violações que já
 * existem. Havendo alguma, o índice NÃO é criado, fica registado no log o que
 * falta resolver, e a migração termina em sucesso. Uma migração que estoira a
 * meio numa actualização de produção deixa o esquema em meio termo e obriga a
 * intervenção manual — e neste momento há 4 casos ambíguos (séries empatadas ou
 * ambas em uso) cuja resolução é decisão do dono do negócio, não nossa.
 *
 * RE-EXECUTÁVEL, e é para ser re-executada: quando os casos ambíguos estiverem
 * resolvidos, corre-se outra vez e aí o índice entra. Como o Laravel só corre
 * cada migração uma vez, o caminho é desfazer e refazer — a coluna é derivada,
 * largá-la não perde dado nenhum:
 *
 *     php artisan migrate:rollback --path=database/migrations/2026_08_14_100000_serie_padrao_unica_por_tipo.php
 *     php artisan migrate
 *
 * Pelo --path e não por `--step=1`: o `--step` desfaz a ÚLTIMA migração
 * corrida, que entretanto já pode ser outra — a resolução dos casos ambíguos
 * depende de uma decisão do dono do negócio e pode demorar semanas, com
 * deploys pelo meio. O --path nomeia esta e só esta.
 *
 * Correr uma segunda vez com o índice já criado também é inofensivo: detecta-o e
 * não faz nada.
 *
 * Só MySQL: a sintaxe da coluna gerada e o comportamento dos NULL no índice único
 * são dele. Noutro motor a migração não faz nada em vez de estoirar.
 */
return new class extends Migration
{
    private const TABELA = 'invoicing_series';
    private const COLUNA = 'padrao_unico';
    private const INDICE = 'invoicing_series_padrao_unico_unique';

    public function up(): void
    {
        if (!$this->aplicavel()) {
            return;
        }

        // A coluna entra sempre, mesmo com violações pendentes: é só uma leitura
        // derivada de is_default, não impõe nada, e deixa a re-execução barata.
        if (!Schema::hasColumn(self::TABELA, self::COLUNA)) {
            DB::statement(
                'ALTER TABLE ' . self::TABELA . ' ADD COLUMN ' . self::COLUNA . " VARCHAR(64)
                 GENERATED ALWAYS AS (
                     IF(is_default = 1, CONCAT(tenant_id, '-', document_type), NULL)
                 ) VIRTUAL"
            );
        }

        if ($this->indiceExiste()) {
            return;
        }

        $violacoes = $this->violacoes();

        if ($violacoes->isNotEmpty()) {
            $this->avisar($violacoes);

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX ' . self::INDICE . ' ON ' . self::TABELA . ' (' . self::COLUNA . ')'
        );
    }

    public function down(): void
    {
        if (!$this->aplicavel()) {
            return;
        }

        // O índice PRIMEIRO: o MySQL não deixa largar uma coluna que um índice
        // ainda usa, e a migração pode ter parado antes de o criar — daí o teste
        // em cada passo, em vez de assumir que ambos existem.
        if ($this->indiceExiste()) {
            DB::statement('DROP INDEX ' . self::INDICE . ' ON ' . self::TABELA);
        }

        if (Schema::hasColumn(self::TABELA, self::COLUNA)) {
            DB::statement('ALTER TABLE ' . self::TABELA . ' DROP COLUMN ' . self::COLUNA);
        }
    }

    /** Empresas/tipos com mais do que uma série marcada como padrão. */
    private function violacoes(): \Illuminate\Support\Collection
    {
        return DB::table(self::TABELA)
            ->select('tenant_id', 'document_type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('GROUP_CONCAT(series_code ORDER BY id SEPARATOR ", ") as codigos')
            ->where('is_default', 1)
            ->groupBy('tenant_id', 'document_type')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('tenant_id')
            ->get();
    }

    /**
     * Deixa registado o que falta resolver, com os códigos concretos: sem eles a
     * mensagem obriga a ir procurar à mão qual das séries é qual.
     */
    private function avisar(\Illuminate\Support\Collection $violacoes): void
    {
        $linhas = $violacoes->map(fn ($v) => sprintf(
            'empresa %d · %s · %d padrão (%s)',
            $v->tenant_id,
            $v->document_type,
            $v->total,
            $v->codigos
        ))->all();

        $aviso = 'Série padrão duplicada: índice único NÃO criado. '
            . 'Resolver e voltar a correr a migração (rollback + migrate). '
            . count($linhas) . ' caso(s): ' . implode(' | ', $linhas);

        Log::warning($aviso);

        if (app()->runningInConsole()) {
            echo PHP_EOL . '  ' . $aviso . PHP_EOL;
        }
    }

    private function indiceExiste(): bool
    {
        $linha = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [self::TABELA, self::INDICE]
        );

        return (int) ($linha->total ?? 0) > 0;
    }

    private function aplicavel(): bool
    {
        return DB::connection()->getDriverName() === 'mysql'
            && Schema::hasTable(self::TABELA);
    }
};
