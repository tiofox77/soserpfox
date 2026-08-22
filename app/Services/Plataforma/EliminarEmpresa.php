<?php

namespace App\Services\Plataforma;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Apagar uma empresa da base de dados, a sério e para sempre.
 *
 * A REGRA, e é fiscal e não técnica: só se apaga uma empresa que NUNCA
 * comunicou nada à AGT. Se comunicou, existe um registo do lado da autoridade
 * tributária que não desaparece por se apagar aqui — e quando alguém vier
 * pedir contas, o dono do sistema fica sem nada para mostrar. Uma empresa que
 * nunca comunicou não tem essa pegada: aí é lixo, e lixo apaga-se.
 *
 * Note-se que ter facturas NÃO impede. Uma empresa pode ter emitido documentos
 * localmente e nunca os ter enviado — não estão nos registos da AGT, e a
 * decisão de os apagar é do dono do negócio.
 *
 * TUDO O QUE É DELA VAI JUNTO. São 143 tabelas com tenant_id, e apagar só a
 * linha da empresa deixava-as todas para trás: stock, movimentos, clientes,
 * documentos — invisíveis, a ocupar espaço, e prontos a reaparecer no dia em
 * que alguém reutilizasse o id. Vai tudo numa transacção: ou some tudo, ou não
 * some nada.
 */
class EliminarEmpresa
{
    /**
     * Houve alguma comunicação à AGT em nome desta empresa?
     *
     * Três sítios, e basta um: as submissões, o registo de comunicações, e a
     * marca deixada nos próprios documentos. Procura-se nos três porque
     * qualquer um deles a sozinho pode ter sido limpo, e o que está em causa é
     * uma decisão irreversível — o custo de olhar a mais é uma consulta.
     */
    public function comunicouAAgt(Tenant $empresa): bool
    {
        foreach (['agt_submissions', 'agt_communication_logs'] as $tabela) {
            if ($this->temLinhas($tabela, $empresa->id)) {
                return true;
            }
        }

        $documentos = [
            'invoicing_sales_invoices',
            'invoicing_credit_notes',
            'invoicing_debit_notes',
            'invoicing_transport_guides',
            'invoicing_receipts',
            'invoicing_purchase_invoices',
        ];

        foreach ($documentos as $tabela) {
            if (!Schema::hasTable($tabela) || !Schema::hasColumn($tabela, 'agt_submitted_at')) {
                continue;
            }

            $existe = DB::table($tabela)
                ->where('tenant_id', $empresa->id)
                ->whereNotNull('agt_submitted_at')
                ->exists();

            if ($existe) {
                return true;
            }
        }

        return false;
    }

    /** O que se perde, para quem decide poder ver antes de decidir. */
    public function oQueSePerde(Tenant $empresa): array
    {
        $conta = function (string $tabela) use ($empresa) {
            return Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'tenant_id')
                ? DB::table($tabela)->where('tenant_id', $empresa->id)->count()
                : 0;
        };

        return [
            'facturas'    => $conta('invoicing_sales_invoices'),
            'artigos'     => $conta('invoicing_products'),
            'clientes'    => $conta('invoicing_clients'),
            'movimentos'  => $conta('invoicing_stock_movements'),
            'utilizadores' => $conta('tenant_user'),
        ];
    }

    /**
     * Apaga a empresa e tudo o que é dela.
     *
     * @throws \DomainException quando a empresa comunicou à AGT
     */
    public function eliminar(Tenant $empresa): int
    {
        if ($this->comunicouAAgt($empresa)) {
            throw new \DomainException(
                'Esta empresa já comunicou documentos à AGT e não pode ser apagada. '
                . 'Suspenda-a — sai da lista e os documentos ficam.'
            );
        }

        $id = $empresa->id;
        $nome = $empresa->name;
        $linhas = 0;

        DB::transaction(function () use ($id, &$linhas) {
            // As chaves estrangeiras ficam de lado durante a limpeza: a ordem
            // entre 143 tabelas com dependências cruzadas não é resolúvel a
            // olho, e uma ordem errada faz isto falhar a meio. Dentro da
            // transacção, e reposto no fim aconteça o que acontecer.
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                // Um utilizador pode pertencer a várias empresas. `users.tenant_id`
                // guarda apenas a empresa principal; apagá-lo por essa coluna
                // destruía também o login nas restantes empresas do pivô.
                // Antes da limpeza, muda a empresa principal dos utilizadores
                // partilhados para uma das empresas que vai continuar a existir.
                if (Schema::hasTable('users') && Schema::hasTable('tenant_user')) {
                    $partilhados = DB::table('users')->where('tenant_id', $id)->pluck('id');

                    foreach ($partilhados as $userId) {
                        $outroTenant = DB::table('tenant_user')
                            ->where('user_id', $userId)
                            ->where('tenant_id', '<>', $id)
                            ->whereExists(fn ($q) => $q->selectRaw('1')->from('tenants')
                                ->whereColumn('tenants.id', 'tenant_user.tenant_id')
                                ->whereNull('tenants.deleted_at'))
                            ->orderBy('tenant_id')
                            ->value('tenant_id');

                        if ($outroTenant) {
                            DB::table('users')->where('id', $userId)->update([
                                'tenant_id' => $outroTenant,
                                'is_active' => true,
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                foreach ($this->tabelasDaEmpresa() as $tabela) {
                    $linhas += DB::table($tabela)->where('tenant_id', $id)->delete();
                }

                DB::table('tenants')->where('id', $id)->delete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        Log::warning('Empresa apagada em definitivo.', [
            'tenant_id' => $id,
            'nome'      => $nome,
            'linhas'    => $linhas,
            'por'       => auth()->id(),
        ]);

        return $linhas;
    }

    /** Todas as tabelas com uma coluna tenant_id, lidas do esquema. */
    private function tabelasDaEmpresa(): array
    {
        // Do information_schema e não de uma lista escrita à mão: uma lista
        // fixa envelhece em silêncio, e o que fica por apagar são os dados de
        // uma empresa que o dono julga já não existir.
        return collect(DB::select(
            'SELECT TABLE_NAME AS nome FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ? AND TABLE_NAME <> ?',
            ['tenant_id', 'tenants']
        ))->pluck('nome')->all();
    }

    private function temLinhas(string $tabela, int $tenantId): bool
    {
        return Schema::hasTable($tabela)
            && Schema::hasColumn($tabela, 'tenant_id')
            && DB::table($tabela)->where('tenant_id', $tenantId)->exists();
    }
}
