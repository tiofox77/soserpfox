<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use Tests\TenantTestCase;

/**
 * O número do documento tem de começar pelo TIPO.
 *
 * Começou por "SOS", por decisão de marca. A AGT recusa:
 *
 *   E32 — Código de série mal construído (SOS FR7626S6286N/000045).
 *
 * Medido contra a AGT de homologação, consultando o desfecho real de
 * submissões já entregues:
 *
 *   25 documentos começados por "SOS"  → 25 recusados, todos com E32
 *    5 documentos começados pelo tipo  →  5 validados
 *
 * Sem excepções, e com a MESMA série nos dois grupos — FR FR7626S6286N/000001
 * passou, SOS FR7626S6286N/000045 não — o que isola o primeiro token como a
 * única diferença entre passar e não passar.
 *
 * Os exemplos aqui são os cinco números que a AGT aceitou de facto.
 */
class NumeroDocumentoAgtTest extends TenantTestCase
{
    private function serie(string $tipo, string $prefixo, ?string $codigoAgt): InvoicingSeries
    {
        // updateOrCreate: a empresa de teste já traz séries criadas, e o par
        // (empresa, tipo, código) é único.
        //
        // Sem `is_default`: o que aqui se mede é o FORMATO do número, e a
        // empresa de teste já traz uma série de facturas marcada como padrão.
        // Marcar esta também deixava duas padrão do mesmo tipo — o defeito que
        // o índice único de `padrao_unico` passou a recusar, e que fazia estes
        // testes rebentar com 1062 mal o índice existisse.
        return InvoicingSeries::updateOrCreate(
            [
                'tenant_id'     => $this->tenant->id,
                'document_type' => $tipo,
                'series_code'   => 'SOS' . $prefixo,
            ],
            [
                'name'           => 'Série ' . $prefixo,
                'prefix'         => $prefixo,
                'agt_series_id'  => $codigoAgt,
                'next_number'    => 1,
                'number_padding' => 6,
                'is_active'      => true,
            ]
        );
    }

    /** Os cinco formatos que a AGT validou, tal e qual. */
    public static function aceitesPelaAgt(): array
    {
        return [
            'factura'         => ['invoice',     'FT', 'FT7626S9155N', 3,  'FT FT7626S9155N/000003'],
            'factura-recibo'  => ['pos',         'FR', 'FR7626S6286N', 1,  'FR FR7626S6286N/000001'],
            'recibo'          => ['receipt',     'RC', 'RC7626S2202N', 3,  'RC RC7626S2202N/000003'],
            'nota de crédito' => ['credit_note', 'NC', 'NC7626S7057N', 3,  'NC NC7626S7057N/000003'],
            'nota de débito'  => ['debit_note',  'ND', 'ND7626S2257N', 3,  'ND ND7626S2257N/000003'],
        ];
    }

    /**
     * @dataProvider aceitesPelaAgt
     */
    public function test_gera_exactamente_o_que_a_agt_aceitou(string $tipo, string $prefixo, string $codigoAgt, int $numero, string $esperado): void
    {
        $serie = $this->serie($tipo, $prefixo, $codigoAgt);

        $this->assertSame($esperado, $serie->formatNumber($numero));
    }

    public function test_nunca_comeca_por_sos(): void
    {
        // A regressão que interessa travar: qualquer volta ao prefixo de marca
        // volta a fazer a AGT recusar todos os documentos com E32.
        $serie = $this->serie('invoice', 'FT', 'FT7626S9153N');

        $this->assertStringStartsNotWith('SOS', $serie->formatNumber(122));
        $this->assertStringStartsWith('FT ', $serie->formatNumber(122));
    }

    public function test_o_primeiro_token_e_o_tipo_e_nao_o_codigo_da_serie(): void
    {
        // O código da série da AGT já começa pelo tipo, por isso o número
        // repete-o: "FR FR7626S6286N/...". A repetição é da AGT — os códigos
        // são atribuídos por ela — e é assim que os documentos passam.
        $serie = $this->serie('pos', 'FR', 'FR7626S6286N');
        $numero = $serie->formatNumber(46);

        [$tipo, $resto] = explode(' ', $numero, 2);

        $this->assertSame('FR', $tipo);
        $this->assertStringStartsWith('FR7626S6286N/', $resto);
    }

    public function test_sem_codigo_da_agt_usa_o_nosso_sem_o_sos(): void
    {
        // Séries ainda não registadas (proformas, por exemplo) não vão à AGT,
        // mas o número não pode ficar com o SOS a meio.
        $serie = $this->serie('proforma', 'PRF', null);

        $this->assertSame('PRF PRF/000001', $serie->formatNumber(1));
    }
}
