<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use Tests\TenantTestCase;

/**
 * O getDefaultSeries() e chamado no proprio render do POS. Se nao convergir,
 * a primeira venda passa e todas as seguintes estoiram com 1062 no ecra.
 */
class SeriePadraoConvergeTest extends TenantTestCase
{
    private function limpar(string $tipo): void
    {
        InvoicingSeries::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('document_type', $tipo)->delete();
    }

    private function criar(string $tipo, string $codigo, bool $padrao, bool $activa): InvoicingSeries
    {
        return InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'document_type' => $tipo,
            'series_code' => $codigo, 'name' => $codigo, 'prefix' => 'FT',
            'next_number' => 1, 'number_padding' => 6,
            'is_default' => $padrao, 'is_active' => $activa,
        ]);
    }

    public function test_com_a_unica_serie_desactivada_reaproveita_em_vez_de_estoirar(): void
    {
        $this->limpar('invoice');
        $existente = $this->criar('invoice', 'SOSFT', true, false);

        $a = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');
        $b = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');

        $this->assertSame($existente->id, $a->id, 'devia reaproveitar a serie que ja existe');
        $this->assertSame($a->id, $b->id, 'nao converge');
        $this->assertSame(1, InvoicingSeries::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('document_type', 'invoice')->count(),
            'abriu uma segunda numeracao do mesmo tipo');
    }

    public function test_com_padrao_desactivada_mas_outra_activa_usa_a_activa(): void
    {
        $this->limpar('invoice');
        $this->criar('invoice', 'SOSFT', true, false);
        $activa = $this->criar('invoice', 'FT2', false, true);

        $a = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');
        $b = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');

        $this->assertSame($activa->id, $a->id);
        $this->assertSame($a->id, $b->id, 'nao converge');
    }

    public function test_sem_serie_nenhuma_cria_uma_so_e_estabiliza(): void
    {
        $this->limpar('invoice');

        $a = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');
        $b = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');
        $c = InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice');

        $this->assertNotNull($a);
        $this->assertTrue((bool) $a->is_default, 'a primeira serie do tipo tem de nascer padrao');
        $this->assertSame($a->id, $b->id);
        $this->assertSame($b->id, $c->id);
        $this->assertSame(1, InvoicingSeries::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('document_type', 'invoice')->count());
    }

    public function test_criar_duas_vezes_seguidas_nao_bate_no_unique(): void
    {
        $this->limpar('invoice');

        $a = InvoicingSeries::createDefaultSeries($this->tenant->id, 'invoice');
        $b = InvoicingSeries::createDefaultSeries($this->tenant->id, 'invoice');

        $this->assertSame($a->id, $b->id, 'a segunda chamada devia devolver a mesma linha');
    }

    public function test_a_padrao_continua_a_ganhar_a_uma_mais_antiga(): void
    {
        $this->limpar('invoice');
        $velha  = $this->criar('invoice', 'FT0', false, true);
        $padrao = $this->criar('invoice', 'SOSFT', true, true);

        $this->assertSame($padrao->id, InvoicingSeries::getDefaultSeries($this->tenant->id, 'invoice')->id);
        $this->assertNotSame($velha->id, $padrao->id);
    }
}
