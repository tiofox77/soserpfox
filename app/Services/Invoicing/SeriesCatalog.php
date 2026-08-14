<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\InvoicingSeries;

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
     * O `prefix` NÃO se escreve aqui — é derivado de InvoicingSeries::AGT_PREFIXES.
     *
     * Estava escrito à mão e tinha divergido: a proforma dizia 'PP' onde o
     * catálogo AGT diz 'PR'. Não era só uma discrepância de tabela, porque este
     * valor é gravado directamente na coluna `prefix` por dois caminhos — o
     * provisionar() de cada empresa nova e o series:canonical, que reescreve o
     * prefixo de séries já existentes. Daí vieram as 6 séries 'PP' do
     * diagnóstico, e daí voltariam: o series:canonical repunha o 'PP' a seguir
     * ao series:corrigir-prefixos ter posto 'PR'. Como a AGT lê o primeiro
     * token do número para classificar o documento, isso é a diferença entre
     * ser aceite e ser recusado com E32.
     *
     * @return array<int, array{code: string, document_type: string, name: string, prefix: string}>
     */
    public static function canonico(): array
    {
        $esquema = [
            [
                'code'          => 'SOSFR',
                'document_type' => 'pos',
                'name'          => 'Faturas-Recibo',
            ],
            [
                'code'          => 'SOSFT',
                'document_type' => 'invoice',
                'name'          => 'Faturas de Venda',
            ],
            [
                'code'          => 'SOSNC',
                'document_type' => 'credit_note',
                'name'          => 'Notas de Crédito',
            ],
            [
                'code'          => 'SOSND',
                'document_type' => 'debit_note',
                'name'          => 'Notas de Débito',
            ],
            [
                'code'          => 'SOSPROV',
                'document_type' => 'proforma',
                'name'          => 'Proformas de Venda',
            ],
            [
                // SOSFC e não SOSFTC: esse já é uma série de VENDA em produção,
                // com documentos emitidos e registo AGT.
                'code'          => 'SOSFC',
                'document_type' => 'purchase',
                'name'          => 'Faturas de Compra',
            ],
            [
                'code'          => 'SOSPROC',
                'document_type' => 'purchase_proforma',
                'name'          => 'Proformas de Compra',
                // Tipo interno: nunca vai à AGT, logo não tem prefixo de
                // catálogo fiscal e o da casa é o único que existe.
                'prefixo_interno' => 'PC',
            ],
            [
                'code'          => 'SOSRC',
                'document_type' => 'receipt',
                'name'          => 'Recibos',
            ],
        ];

        foreach ($esquema as $i => $serie) {
            $esquema[$i]['prefix'] = InvoicingSeries::prefixoDe($serie['document_type'])
                ?? ($serie['prefixo_interno'] ?? 'DOC');

            unset($esquema[$i]['prefixo_interno']);
        }

        return $esquema;
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
                // O guarda acima é pelo CÓDIGO, não pelo tipo: numa empresa que
                // já tenha uma série daquele tipo com outro código (as antigas
                // 'A', '01'), a canónica era criada ao lado e nascia padrão
                // também. Duas padrão do mesmo tipo e a numeração fiscal a
                // depender da ordem do SELECT.
                'is_default'    => InvoicingSeries::deveNascerPadrao($tenantId, $serie['document_type']),
                'is_active'     => true,
            ]);

            $criadas++;
        }

        return $criadas;
    }

    /**
     * Documento interno: existe, é legítimo, e nunca se comunica à AGT.
     *
     * O series:diagnostico e o series:corrigir-prefixos já procuravam este
     * método (method_exists) e, por ele não existir, caíam cada um na sua cópia
     * local da lista. Delega no catálogo dos tipos para haver uma definição só:
     * quando um tipo interno novo aparecer, acrescenta-se em TIPOS_INTERNOS e os
     * dois comandos passam a conhecê-lo sem serem editados.
     */
    public static function ehTipoInterno(string $documentType): bool
    {
        return InvoicingSeries::tipoInterno($documentType);
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
