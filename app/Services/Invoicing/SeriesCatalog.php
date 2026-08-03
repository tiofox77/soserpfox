<?php

namespace App\Services\Invoicing;

/**
 * Catálogo canónico das séries de documentos.
 *
 * Fonte ÚNICA: o seeder das empresas novas e o comando que normaliza as
 * existentes leem daqui. Antes o esquema vivia espalhado — o seeder gravava
 * `A`/`B`/`01` com um prefixo e o `agt:normalize` compunha `SOS`+prefixo+letra
 * depois, o que produzia códigos como `SOSFTC` (3.ª série de VENDA) que se
 * confundem com o que se esperaria ser fatura de compra.
 *
 * NOTA sobre `SOSFTC`: está ocupado. No tenant 11, em produção, é uma série de
 * venda com documentos emitidos e registada na AGT (FT7626S9155N). A fatura de
 * compra usa `SOSFC`.
 */
class SeriesCatalog
{
    /**
     * O esquema acordado. A ordem é a de apresentação.
     *
     * @return array<int, array{code: string, document_type: string, name: string, prefix: string}>
     */
    public static function canonico(): array
    {
        return [
            [
                'code'          => 'SOSFR',
                'document_type' => 'pos',
                'name'          => 'Faturas-Recibo',
                'prefix'        => 'FR',
            ],
            [
                'code'          => 'SOSFT',
                'document_type' => 'invoice',
                'name'          => 'Faturas de Venda',
                'prefix'        => 'FT',
            ],
            [
                'code'          => 'SOSNC',
                'document_type' => 'credit_note',
                'name'          => 'Notas de Crédito',
                'prefix'        => 'NC',
            ],
            [
                'code'          => 'SOSND',
                'document_type' => 'debit_note',
                'name'          => 'Notas de Débito',
                'prefix'        => 'ND',
            ],
            [
                'code'          => 'SOSPROV',
                'document_type' => 'proforma',
                'name'          => 'Proformas de Venda',
                'prefix'        => 'PP',
            ],
            [
                // SOSFC e não SOSFTC: esse já é uma série de VENDA em produção,
                // com documentos emitidos e registo AGT.
                'code'          => 'SOSFC',
                'document_type' => 'purchase',
                'name'          => 'Faturas de Compra',
                'prefix'        => 'FC',
            ],
            [
                'code'          => 'SOSPROC',
                'document_type' => 'purchase_proforma',
                'name'          => 'Proformas de Compra',
                'prefix'        => 'PC',
            ],
            [
                'code'          => 'SOSRC',
                'document_type' => 'receipt',
                'name'          => 'Recibos',
                'prefix'        => 'RC',
            ],
        ];
    }

    /**
     * Cria as séries canónicas em falta numa empresa.
     *
     * Idempotente: só cria o que não existir com aquele código. Chamado no
     * provisionamento de uma empresa nova — antes disto o provisionamento
     * criava plano de contas, diários, impostos e centros de custo, mas
     * NENHUMA série, e a empresa ficava sem conseguir numerar documentos.
     *
     * @return int Quantas séries foram criadas.
     */
    public static function provisionar(int $tenantId): int
    {
        $criadas = 0;

        foreach (static::canonico() as $serie) {
            $jaExiste = \App\Models\Invoicing\InvoicingSeries::where('tenant_id', $tenantId)
                ->where('series_code', $serie['code'])
                ->exists();

            if ($jaExiste) {
                continue;
            }

            \App\Models\Invoicing\InvoicingSeries::create([
                'tenant_id'     => $tenantId,
                'series_code'   => $serie['code'],
                'name'          => $serie['name'],
                'prefix'        => $serie['prefix'],
                'document_type' => $serie['document_type'],
                'next_number'   => 1,
                'is_default'    => true,
                'is_active'     => true,
            ]);

            $criadas++;
        }

        return $criadas;
    }

    /** O documento canónico deste tipo, ou null se o tipo não estiver no catálogo. */
    public static function paraTipo(string $documentType): ?array
    {
        foreach (static::canonico() as $serie) {
            if ($serie['document_type'] === $documentType) {
                return $serie;
            }
        }

        return null;
    }

    /**
     * Uma série pode ser renomeada para o padrão?
     *
     * NÃO pode quando já emitiu documentos ou está registada na AGT: o código
     * faz parte do número que já saiu para o cliente e foi comunicado. Mudá-lo
     * quebraria a correspondência com o que a AGT tem registado.
     */
    public static function podeRenomear(\App\Models\Invoicing\InvoicingSeries $serie): bool
    {
        if (!empty($serie->agt_series_id)) {
            return false;
        }

        return static::documentosEmitidos($serie) === 0;
    }

    /** Quantos documentos já saíram desta série, em qualquer tabela. */
    public static function documentosEmitidos(\App\Models\Invoicing\InvoicingSeries $serie): int
    {
        $tabelas = [
            'invoicing_sales_invoices',
            'invoicing_credit_notes',
            'invoicing_debit_notes',
            'invoicing_receipts',
            'invoicing_proformas',
            'invoicing_transport_guides',
        ];

        $total = 0;

        foreach ($tabelas as $tabela) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($tabela)
                || !\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'series_id')) {
                continue;
            }

            $total += \Illuminate\Support\Facades\DB::table($tabela)
                ->where('series_id', $serie->id)
                ->count();
        }

        // O contador também denuncia uso: uma série que já numerou tem
        // next_number > 1 mesmo que o documento tenha sido apagado depois.
        if ($total === 0 && (int) $serie->next_number > 1) {
            return (int) $serie->next_number - 1;
        }

        return $total;
    }
}
