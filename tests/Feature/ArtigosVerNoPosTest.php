<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

/**
 * `artigos:ver --pos` diz se um artigo aparece no balcão, e porquê — com a
 * regra do próprio POS (Tecstore, 25/09/2026: telemóveis que não apareciam).
 */
class ArtigosVerNoPosTest extends TenantTestCase
{
    public function test_diz_porque_um_artigo_nao_aparece_no_pos(): void
    {
        $armazem = getOrCreateDefaultWarehouse()->id;
        $criar = fn (string $nome, array $mais = []) => Product::create(array_merge([
            'tenant_id' => $this->tenant->id, 'name' => $nome, 'code' => 'T-' . uniqid(), 'type' => 'produto',
            'price' => 100, 'cost' => 50, 'unit' => 'UN', 'manage_stock' => true, 'is_active' => true,
        ], $mais));

        $telemovel = $criar('Tecno POP2 Ensaio');
        $fone = $criar('Fone Tecno Ensaio');
        Stock::updateOrCreate(['tenant_id' => $this->tenant->id, 'warehouse_id' => $armazem, 'product_id' => $fone->id], ['quantity' => 95]);

        Artisan::call('artigos:ver', ['--tenant' => $this->tenant->id, '--procura' => 'Tecno', '--pos' => true]);
        $saida = Artisan::output();

        $this->assertMatchesRegularExpression('/Tecno POP2 Ensaio.*NÃO — sem stock no armazém do balcão \(0\)/u', $saida);
        $this->assertMatchesRegularExpression('/Fone Tecno Ensaio.*\| sim\s*\|/u', $saida);
    }
}
