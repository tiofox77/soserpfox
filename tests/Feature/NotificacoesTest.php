<?php

namespace Tests\Feature;

use App\Livewire\Notifications;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Avisos do sino de notificações.
 *
 * Dois deles estavam a olhar para o sítio errado: o de baixo stock lia uma
 * coluna que está a zero em toda a base, e o de produtos expirados escondia
 * tudo o que tivesse expirado há mais de uma semana — precisamente o caso
 * mais grave.
 */
class NotificacoesTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function avisos(): array
    {
        $c = Livewire::test(Notifications::class);

        return collect($c->instance()->systemNotifications ?? [])->all()
            ?: collect($c->viewData('notifications') ?? [])->all();
    }

    private function temAviso(string $titulo): bool
    {
        $html = Livewire::test(Notifications::class)->html();

        return str_contains($html, $titulo);
    }

    public function test_o_aviso_de_baixo_stock_usa_o_minimo_do_produto(): void
    {
        // A consulta antiga usava invoicing_stocks.minimum_quantity, que está a
        // ZERO nas 56.662 linhas da base: o aviso nunca disparou uma única vez,
        // com 19.165 linhas abaixo do mínimo real.
        $p = $this->produtoComStock(2);
        $p->update(['stock_min' => 10]);

        $this->assertTrue($this->temAviso('Baixo Stock'), 'dois em stock com mínimo de dez tem de avisar');
    }

    public function test_sem_minimo_definido_nao_ha_aviso(): void
    {
        $p = $this->produtoComStock(2);
        $p->update(['stock_min' => 0]);

        $this->assertFalse($this->temAviso('Baixo Stock'), 'sem mínimo não há nada para avisar');
    }

    public function test_um_artigo_inactivo_nao_gera_aviso_de_stock(): void
    {
        $p = $this->produtoComStock(1);
        $p->update(['stock_min' => 10, 'is_active' => false]);

        $this->assertFalse($this->temAviso('Baixo Stock'));
    }

    public function test_um_lote_expirado_ha_muito_continua_a_avisar(): void
    {
        // A janela de sete dias escondia o pior caso: um lote que expirou há um
        // mês e continua na prateleira é MAIS urgente do que um de ontem.
        $p = $this->produtoComStock(0);

        ProductBatch::create([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $p->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'L' . strtoupper(substr(uniqid(), -6)),
            'quantity'           => 5,
            'quantity_available' => 5,
            'expiry_date'        => now()->subMonths(2)->toDateString(),
            'status'             => 'active',
        ]);

        $this->assertTrue($this->temAviso('Produtos Expirados'));
    }

    public function test_um_lote_expirado_sem_stock_nao_incomoda(): void
    {
        $p = $this->produtoComStock(0);

        ProductBatch::create([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $p->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'L' . strtoupper(substr(uniqid(), -6)),
            'quantity'           => 5,
            'quantity_available' => 0,
            'expiry_date'        => now()->subMonths(2)->toDateString(),
            'status'             => 'active',
        ]);

        $this->assertFalse($this->temAviso('Produtos Expirados'), 'o que já saiu não volta a incomodar');
    }

    public function test_os_avisos_de_uma_empresa_nao_aparecem_na_outra(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $artigoAlheio = \App\Models\Product::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio ' . uniqid(),
            'code' => 'AL' . strtoupper(substr(uniqid(), -6)),
            'type' => 'produto', 'price' => 100, 'cost' => 50, 'unit' => 'UN',
            'stock_min' => 100, 'manage_stock' => true, 'is_active' => true,
        ]);

        $armazemAlheio = \App\Models\Invoicing\Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $outra->id)->first();

        Stock::withoutGlobalScopes()->create([
            'tenant_id'    => $outra->id,
            'warehouse_id' => $armazemAlheio->id,
            'product_id'   => $artigoAlheio->id,
            'quantity'     => 1,
            'unit_cost'    => 50,
        ]);

        $this->assertFalse($this->temAviso('Baixo Stock'), 'o stock baixo é da outra empresa');
    }

    public function test_o_fornecedor_de_sms_nao_suportado_e_recusado(): void
    {
        // O `provider` era escrito no log e mais nada: o payload montado é o da
        // D7, e escolher outro fornecedor mandava-lhe esse payload na mesma. O
        // que se via era um erro obscuro do outro lado.
        \App\Models\SmsSetting::create([
            'tenant_id' => $this->tenant->id,
            'provider'  => 'nexmo',
            'api_url'   => 'https://exemplo.invalido/send',
            'api_token' => 'x',
            'sender_id' => 'SOS',
            'is_active' => true,
        ]);

        $resultado = (new \App\Services\SmsService())
            ->send('+244923000000', 'teste', null, null, $this->tenant->id);

        $this->assertFalse($resultado['success'] ?? true, 'um fornecedor não suportado não pode passar em silêncio');
        $this->assertStringContainsString('não é suportado', $resultado['error'] ?? '');
    }
}
