<?php

namespace Tests\Feature\Hotel;

use App\Models\Hotel\Package;
use App\Models\Hotel\PromoCode;
use App\Models\Hotel\RoomType;
use Tests\TenantTestCase;

/**
 * OS PACOTES E OS CÓDIGOS PROMOCIONAIS — duas listas na mesma morada.
 *
 * Eram duas abas dentro do mesmo componente Livewire. São duas listas com a
 * forma de sempre: dois catálogos, e o ecrã genérico põe-lhes os separadores.
 *
 * O QUE ISTO OBRIGOU O ECRÃ GENÉRICO A APRENDER, e que serve daqui para a
 * frente a qualquer catálogo:
 *
 *  · UM `multi` COM AS OPÇÕES DE UMA REFERÊNCIA — «a que tipos de quarto se
 *    aplica» é uma lista que muda com os dados;
 *  · `etiquetas` — uma lista ESCRITA À MÃO, porque cada casa inclui no pacote
 *    o que quer e escrever «Transfer do aeroporto» não pode obrigar a mexer no
 *    código.
 */
class PacotesEPromocoesTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    private function tipo(string $nome = 'Duplo'): RoomType
    {
        return RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome,
            'code' => 'T-' . substr(uniqid(), -4), 'base_price' => 20000,
            'capacity' => 2, 'extra_bed_capacity' => 0, 'extra_bed_price' => 0, 'is_active' => true,
        ]);
    }

    /* ─── Pacotes ─────────────────────────────────────────────────────── */

    public function test_criar_um_pacote_grava_os_servicos_e_os_tipos(): void
    {
        $this->comPermissoes('hotel.packages.view', 'hotel.packages.create');

        $tipo = $this->tipo();

        $this->postJson(self::API . '/pacotes', [
            'name' => 'Fim-de-semana romântico',
            'type' => 'romantic',
            'price' => 95000,
            'min_nights' => 2,
            'included_services' => ['Pequeno-almoço', 'Transfer do aeroporto'],
            'room_type_ids' => [$tipo->id],
        ])->assertCreated();

        $p = Package::first();

        $this->assertSame(['Pequeno-almoço', 'Transfer do aeroporto'], $p->included_services);
        $this->assertSame([$tipo->id], $p->room_type_ids);
        // O `slug` é o que o site de reservas põe no endereço, e a coluna
        // existia sem ninguém a preencher.
        $this->assertSame('fim-de-semana-romantico', $p->slug);
    }

    /** Um pacote que acaba antes de começar nunca aparece, e ninguém percebe porquê. */
    public function test_a_validade_nao_acaba_antes_de_comecar(): void
    {
        $this->comPermissoes('hotel.packages.view', 'hotel.packages.create');

        $this->postJson(self::API . '/pacotes', [
            'name' => 'Impossível', 'type' => 'other', 'min_nights' => 1,
            'valid_from' => '2026-12-01', 'valid_until' => '2026-11-01',
        ])->assertStatus(422)->assertJsonValidationErrors('valid_until');
    }

    public function test_o_maximo_de_noites_nao_e_menor_do_que_o_minimo(): void
    {
        $this->comPermissoes('hotel.packages.view', 'hotel.packages.create');

        $this->postJson(self::API . '/pacotes', [
            'name' => 'Impossível', 'type' => 'other', 'min_nights' => 5, 'max_nights' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('max_nights');
    }

    /**
     * OS TIPOS DE QUARTO VÊM DOS DADOS.
     *
     * É uma lista que muda com a casa, e não uma escrita no esquema: sem isto
     * o campo chegava ao ecrã sem opção nenhuma.
     */
    public function test_o_campo_dos_tipos_de_quarto_traz_as_opcoes_dos_dados(): void
    {
        $this->comPermissoes('hotel.packages.view');

        $tipo = $this->tipo('Suite do Ensaio');

        $o = $this->getJson(self::API . '/pacotes/opcoes')->assertOk();

        $campo = collect($o->json('campos'))->firstWhere('chave', 'room_type_ids');
        $this->assertSame('multi', $campo['tipo']);
        $this->assertSame([['valor' => (string) $tipo->id, 'rotulo' => 'Suite do Ensaio']], $campo['opcoes']);

        // E a COLUNA da tabela também, senão a lista mostrava o número cru.
        $coluna = collect($o->json('colunas'))->firstWhere('chave', 'included_services');
        $this->assertSame('etiquetas', $coluna['formato']);
    }

    /* ─── Códigos promocionais ────────────────────────────────────────── */

    public function test_o_codigo_sobe_em_maiusculas_e_nao_se_repete(): void
    {
        $this->comPermissoes('hotel.packages.view', 'hotel.packages.create');

        $this->postJson(self::API . '/codigos-promocionais', [
            'code' => 'verao26', 'name' => 'Verão 2026',
            'discount_type' => 'percentage', 'discount_value' => 15,
        ])->assertCreated();

        $this->assertSame('VERAO26', PromoCode::first()->code);

        $this->postJson(self::API . '/codigos-promocionais', [
            'code' => 'VERAO26', 'name' => 'Outro',
            'discount_type' => 'percentage', 'discount_value' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /**
     * UMA PERCENTAGEM ACIMA DE 100 é uma estadia de graça com troco.
     *
     * A regra `numeric|min:0` do ecrã de sempre deixava passar 500%.
     */
    public function test_uma_percentagem_acima_de_cem_e_recusada(): void
    {
        $this->comPermissoes('hotel.packages.view', 'hotel.packages.create');

        $this->postJson(self::API . '/codigos-promocionais', [
            'code' => 'GRATIS', 'name' => 'Demasiado',
            'discount_type' => 'percentage', 'discount_value' => 500,
        ])->assertStatus(422)->assertJsonValidationErrors('discount_value');

        // Mas um valor FIXO de 500 é só 500 Kz de desconto — e passa.
        $this->postJson(self::API . '/codigos-promocionais', [
            'code' => 'CINCO', 'name' => 'Cinco centos',
            'discount_type' => 'fixed', 'discount_value' => 500,
        ])->assertCreated();
    }

    /**
     * UM CÓDIGO JÁ USADO NÃO SE APAGA — as reservas que o usaram ficariam com
     * um desconto sem explicação.
     */
    public function test_um_codigo_ja_usado_nao_se_apaga(): void
    {
        $this->comPermissoes('hotel.packages.view', 'hotel.packages.delete');

        $c = PromoCode::create([
            'tenant_id' => $this->tenant->id, 'code' => 'USADO', 'name' => 'Já usado',
            'discount_type' => 'percentage', 'discount_value' => 10, 'times_used' => 3,
        ]);

        $this->deleteJson(self::API . "/codigos-promocionais/{$c->id}")->assertStatus(422);

        $c->update(['times_used' => 0]);
        $this->deleteJson(self::API . "/codigos-promocionais/{$c->id}")->assertOk();
    }

    /* ─── A morada ────────────────────────────────────────────────────── */

    /** A morada abre com as DUAS listas, em separadores. */
    public function test_a_morada_dos_pacotes_traz_as_duas_listas(): void
    {
        $this->comPermissoes('hotel.packages.view');

        $html = $this->get('/hotel/packages')->assertOk()->getContent();

        $this->assertStringContainsString('data-ecra="facturacao/catalogo"', $html);
        $this->assertStringContainsString('pacotes', $html);
        $this->assertStringContainsString('codigos-promocionais', $html);
    }

    public function test_as_duas_listas_pedem_a_permissao_dos_pacotes(): void
    {
        $this->getJson(self::API . '/pacotes')->assertForbidden();
        $this->getJson(self::API . '/codigos-promocionais')->assertForbidden();
    }
}
