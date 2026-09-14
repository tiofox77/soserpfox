<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Tests\TenantTestCase;

class CorrigirAcentosEstragadosTest extends TenantTestCase
{
    private function artigo(string $nome, ?int $tenantId = null): Product
    {
        $tenantId ??= $this->tenant->id;
        $categoria = Category::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Geral'], ['is_active' => true]);

        return Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'name' => $nome, 'type' => 'produto', 'price' => 100, 'unit' => 'un',
            'category_id' => $categoria->id, 'tax_type' => 'isento', 'exemption_reason' => 'M04', 'is_active' => true,
        ]);
    }

    /** Sem --aplicar mostra o que mudaria e não grava nada. @test */
    public function por_omissao_so_mostra(): void
    {
        $a = $this->artigo('├üCIDO F├ôLICO 5MG');

        $this->artisan('acentos:corrigir', ['--empresa' => $this->tenant->id])
            ->expectsOutputToContain('SIMULAÇÃO')
            ->expectsOutputToContain('ÁCIDO FÓLICO 5MG')
            ->assertSuccessful();

        $this->assertSame('├üCIDO F├ôLICO 5MG', $a->fresh()->name);
    }

    /** Com --aplicar corrige só a empresa pedida, e deixa o que já estava certo. @test */
    public function aplicar_corrige_so_a_empresa_pedida(): void
    {
        $estragado = $this->artigo('├ügua Oxigenada 20V 250ml Velvet');
        $certo = $this->artigo('Álcool etílico 70%');
        $categoria = Category::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'ANTI-INFLAMAT├ôRIOS', 'is_active' => true]);

        $outra = Tenant::create(['name' => 'Casa B', 'slug' => 'casa-b-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'b' . uniqid() . '@b.ao', 'is_active' => true]);
        $deOutra = $this->artigo('├ücido de outra', $outra->id);

        $this->artisan('acentos:corrigir', ['--empresa' => $this->tenant->id, '--aplicar' => true])
            ->expectsOutputToContain('Corrigidas: 2')
            ->assertSuccessful();

        $this->assertSame('Água Oxigenada 20V 250ml Velvet', $estragado->fresh()->name);
        $this->assertSame('Álcool etílico 70%', $certo->fresh()->name);
        $this->assertSame('ANTI-INFLAMATÓRIOS', $categoria->fresh()->name);
        $this->assertSame('├ücido de outra', Product::withoutGlobalScopes()->find($deOutra->id)->name, 'a outra empresa não se toca');
    }

    /** O que sobra por reparar é listado para alguém olhar. @test */
    public function lista_o_que_fica_por_olhar(): void
    {
        $this->artigo('ÁLCOOL ├ 96');

        $this->artisan('acentos:corrigir', ['--empresa' => $this->tenant->id])
            ->expectsOutputToContain('ver à mão')
            ->assertSuccessful();
    }

    /** @test */
    public function recusa_tabelas_que_nao_conhece(): void
    {
        $this->artisan('acentos:corrigir', ['--tabelas' => 'facturas'])->assertFailed();
    }
}
