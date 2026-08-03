<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referência do lote de movimentação de stock.
 *
 * O ecrã "Movimentação de Stock" regista várias entradas e saídas de uma vez,
 * mas cada linha ficava solta: não havia como saber que aquelas 12 movimentações
 * foram o MESMO acto, nem como reimprimir o documento depois.
 *
 * Esta coluna agrupa-as. É ela que dá identidade ao documento que se imprime.
 *
 * Não leva índice único — vários movimentos partilham a mesma referência de
 * propósito. O índice é composto com tenant_id porque a referência é sequencial
 * POR EMPRESA: duas empresas têm ambas o MOV/2026/000001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_stock_movements', function (Blueprint $table) {
            // Coluna e índice com guardas SEPARADOS. Juntos, uma execução que
            // criasse a coluna e falhasse a seguir deixava a tabela sem índice
            // para sempre: à segunda passagem a coluna já existia e o índice
            // ficava por criar, em silêncio — e é dele que depende a leitura da
            // última referência do lote.
            if (!Schema::hasColumn('invoicing_stock_movements', 'batch_reference')) {
                $table->string('batch_reference', 30)->nullable()->after('reference_id');
            }

            if (!$this->temIndice('invoicing_stock_movements', 'ism_tenant_batch_idx')) {
                $table->index(['tenant_id', 'batch_reference'], 'ism_tenant_batch_idx');
            }

            // Saldo do armazém DEPOIS deste movimento.
            //
            // Um documento de movimentação sem saldo resultante obriga quem o lê
            // a reconstruir o saldo à mão. E numa reimpressão, mostrar o stock
            // de hoje seria mentira: o documento tem de dizer como as coisas
            // ficaram naquele momento.
            //
            // Anulável porque só é conhecido onde é calculado — os movimentos
            // gerados por vendas e compras deixam-no vazio.
            if (!Schema::hasColumn('invoicing_stock_movements', 'balance_after')) {
                $table->decimal('balance_after', 18, 4)->nullable()->after('quantity');
            }
        });
    }

    private function temIndice(string $tabela, string $indice): bool
    {
        return collect(Schema::getIndexes($tabela))
            ->contains(fn ($i) => ($i['name'] ?? null) === $indice);
    }

    public function down(): void
    {
        Schema::table('invoicing_stock_movements', function (Blueprint $table) {
            if ($this->temIndice('invoicing_stock_movements', 'ism_tenant_batch_idx')) {
                $table->dropIndex('ism_tenant_batch_idx');
            }

            if (Schema::hasColumn('invoicing_stock_movements', 'batch_reference')) {
                $table->dropColumn('batch_reference');
            }

            if (Schema::hasColumn('invoicing_stock_movements', 'balance_after')) {
                $table->dropColumn('balance_after');
            }
        });
    }
};
