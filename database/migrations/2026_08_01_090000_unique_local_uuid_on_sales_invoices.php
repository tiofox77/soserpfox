<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * (tenant_id, local_uuid) passa a ÚNICO.
 *
 * O local_uuid é a chave de deduplicação das vendas sincronizadas do POS
 * offline (PWA). O PosSaleService verifica se já existe antes de gravar, mas
 * essa verificação tem janela de corrida: dois pedidos com o mesmo uuid — o que
 * acontece quando a rede oscila e o cliente reenvia — passam ambos pelo first()
 * antes de qualquer um inserir. Resultado: factura duplicada, stock descontado
 * duas vezes e entrada de tesouraria a dobrar.
 *
 * Com o índice único a corrida passa a dar erro de base de dados, que o serviço
 * apanha e trata como "já sincronizada".
 *
 * O índice não-único anterior é substituído (serve as mesmas consultas).
 * NULL não conflita em índices únicos do MySQL, pelo que as vendas que não vêm
 * do PWA (local_uuid nulo) não são afectadas.
 */
return new class extends Migration
{
    private const TABELA = 'invoicing_sales_invoices';
    private const INDICE_ANTIGO = 'invoicing_sales_invoices_tenant_id_local_uuid_index';
    private const INDICE_NOVO = 'invoicing_sales_invoices_tenant_local_uuid_unique';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABELA) || !Schema::hasColumn(self::TABELA, 'local_uuid')) {
            return;
        }

        // Duplicados pré-existentes impediriam a criação do índice. Se houver,
        // não mexer — exige decisão humana sobre qual das facturas vale.
        $duplicados = DB::table(self::TABELA)
            ->whereNotNull('local_uuid')
            ->whereNull('deleted_at')
            ->select('tenant_id', 'local_uuid')
            ->groupBy('tenant_id', 'local_uuid')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicados > 0) {
            DB::statement("SELECT 'migração ignorada: existem local_uuid duplicados'");
            return;
        }

        if ($this->indiceExiste(self::INDICE_NOVO)) {
            return;
        }

        DB::statement('ALTER TABLE `' . self::TABELA . '` ADD UNIQUE `' . self::INDICE_NOVO . '` (`tenant_id`, `local_uuid`)');

        if ($this->indiceExiste(self::INDICE_ANTIGO)) {
            DB::statement('ALTER TABLE `' . self::TABELA . '` DROP INDEX `' . self::INDICE_ANTIGO . '`');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABELA)) {
            return;
        }

        if (!$this->indiceExiste(self::INDICE_ANTIGO)) {
            DB::statement('ALTER TABLE `' . self::TABELA . '` ADD INDEX `' . self::INDICE_ANTIGO . '` (`tenant_id`, `local_uuid`)');
        }

        if ($this->indiceExiste(self::INDICE_NOVO)) {
            DB::statement('ALTER TABLE `' . self::TABELA . '` DROP INDEX `' . self::INDICE_NOVO . '`');
        }
    }

    private function indiceExiste(string $nome): bool
    {
        return collect(DB::select('SHOW INDEX FROM `' . self::TABELA . '`'))
            ->contains(fn ($i) => $i->Key_name === $nome);
    }
};
