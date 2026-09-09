<?php

namespace Tests\Feature;

use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Treasury\PaymentMethod;
use Tests\TenantTestCase;

/**
 * Hotel: adiantamentos, dedução no check-out e máquina de estados.
 *
 * O caso central é a dupla tributação: sinal faturado + estadia faturada
 * inteira no check-out davam ao hóspede ~1,5x a estadia em documentos fiscais,
 * já assinados e comunicados à AGT.
 */
class HotelReservationTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/hotel/reservas';
    private const FECHO = '/api/v1/invoicing/react/hotel/fecho';

    private RoomType $tipoQuarto;
    private PaymentMethod $metodo;

    protected function setUp(): void
    {
        parent::setUp();

        // O ECRA DAS RESERVAS E REACT: fala-se com ele por HTTP, e a porta
        // exige o modulo e a permissao — o `Livewire::test` de antes nao
        // passava por nenhum dos dois.
        $this->comModulo('hotel');
        $this->comPermissoes(
            'hotel.reservations.view', 'hotel.reservations.create', 'hotel.reservations.edit',
            'hotel.checkout.manage'
        );

        $this->tipoQuarto = RoomType::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Duplo',
            'code'       => 'DUP',
            'base_price' => 30000,
            'capacity'   => 2,
            'is_active'  => true,
        ]);

        $this->metodo = PaymentMethod::where('tenant_id', $this->tenant->id)->where('is_active', true)->first()
            ?? PaymentMethod::create([
                'tenant_id' => $this->tenant->id,
                'name'      => 'Dinheiro',
                'code'      => 'CASH',
                'is_active' => true,
            ]);
    }

    /** Reserva de 2 noites x 30.000 = 60.000 de base. */
    private function reserva(string $estado = 'checked_in', ?Room $quarto = null): Reservation
    {
        return Reservation::create([
            'tenant_id'          => $this->tenant->id,
            'reservation_number' => 'R' . strtoupper(substr(uniqid(), -9)),
            'client_id'          => $this->cliente->id,
            'room_type_id'       => $this->tipoQuarto->id,
            'room_id'            => $quarto?->id,
            'check_in_date'      => now()->subDays(2),
            'check_out_date'     => now(),
            'nights'             => 2,
            'room_rate'          => 30000,
            'status'             => $estado,
            'payment_status'     => 'pending',
            'paid_amount'        => 0,
        ]);
    }

    private function quarto(): Room
    {
        return Room::create([
            'tenant_id'    => $this->tenant->id,
            'room_type_id' => $this->tipoQuarto->id,
            'number'       => (string) random_int(100, 999),
            'floor'        => 1,
            'status'       => 'available',
            'is_active'    => true,
        ]);
    }

    private function pagarSinal(Reservation $r, float $valor): void
    {
        $this->postJson(self::API . "/{$r->id}/receber", [
            'valor' => $valor,
            'meio' => $this->metodo->id,
            'facturar' => true,
        ])->assertOk();
    }

    /**
     * FECHAR A ESTADA pela porta de sempre — agora HTTP.
     *
     * O `pagamento` e o saldo, como o ecra propunha por omissao.
     */
    private function fecharEstada(Reservation $r, bool $facturar = true, array $extras = []): \Illuminate\Testing\TestResponse
    {
        $conta = $this->getJson(self::FECHO . "/{$r->id}")->json('conta');

        return $this->postJson(self::FECHO . "/{$r->id}/fechar", [
            'extras' => $extras,
            'pagamento' => $conta['por_receber'] ?? 0,
            'meio' => $this->metodo->id,
            'facturar' => $facturar,
        ]);
    }

    public function test_sinal_parcial_e_abatido_na_fatura_final(): void
    {
        $r = $this->reserva();
        $this->pagarSinal($r, 22800);   // 20.000 de base + IVA

        $this->fecharEstada($r)->assertOk();

        $facturas = $r->fresh()->invoices()->get();

        $this->assertCount(2, $facturas);
        $this->assertEqualsWithDelta(60000, $facturas->sum(fn ($f) => (float) $f->net_total), 0.05,
            'A base total faturada não pode exceder a estadia');
        $this->assertEqualsWithDelta(8400, $facturas->sum(fn ($f) => (float) $f->tax_payable), 0.05,
            'IVA a dobrar é a falha fiscal que isto previne');
    }

    public function test_a_fatura_final_nunca_leva_linhas_negativas(): void
    {
        $r = $this->reserva();
        $this->pagarSinal($r, 22800);   // 20.000 de base + IVA

        $this->fecharEstada($r)->assertOk();

        foreach ($r->fresh()->invoices()->get() as $factura) {
            $this->assertGreaterThan(0, (float) $factura->total, 'documento de total não positivo');

            foreach ($factura->items as $linha) {
                $this->assertGreaterThanOrEqual(0, (float) $linha->unit_price,
                    'a AGT não prevê linha de valor negativo numa FT — a rectificação faz-se por NC/ND');
                $this->assertGreaterThanOrEqual(0, (float) $linha->total);
                $this->assertGreaterThanOrEqual(0, (float) $linha->quantity);
            }
        }
    }

    public function test_a_linha_parcialmente_coberta_entra_so_pelo_que_falta(): void
    {
        $r = $this->reserva();
        // Sinal de 20.000 de base contra alojamento de 60.000: sobram 40.000.
        $this->pagarSinal($r, 22800);

        $this->fecharEstada($r)->assertOk();

        $final = $r->fresh()->invoices()->get()
            ->sortByDesc('id')
            ->first();

        $this->assertEqualsWithDelta(40000, (float) $final->net_total, 0.05,
            'o documento final cobre só o remanescente');

        $this->assertEqualsWithDelta(
            (float) $final->net_total,
            $final->items->sum(fn ($i) => (float) $i->subtotal - (float) $i->discount_amount),
            0.05,
            'a soma das linhas tem de bater com a base do documento'
        );
    }

    public function test_sinal_que_cobre_tudo_nao_gera_segunda_fatura(): void
    {
        $r = $this->reserva();
        $this->pagarSinal($r, 68400);   // 60.000 de base + IVA

        $this->fecharEstada($r)->assertOk();

        $this->assertCount(1, $r->fresh()->invoices()->get(),
            'Emitir uma FT de total zero ou negativo é pior do que não emitir');
    }

    public function test_o_ecra_avisa_e_bloqueia_quando_ja_esta_tudo_faturado(): void
    {
        $r = $this->reserva();
        $this->pagarSinal($r, 68400);

        $conta = $this->getJson(self::FECHO . "/{$r->id}")->assertOk()->json('conta');

        $this->assertTrue($conta['ja_facturada'], 'Não propor emitir mais um documento');
        $this->assertEqualsWithDelta(60000, $conta['ja_facturado'], 0.05);
        $this->assertNotEmpty($conta['facturas'], 'o ecrã diz QUAL documento reimprimir');
    }

    public function test_juntar_consumo_reabre_a_emissao_da_fatura(): void
    {
        $r = $this->reserva();
        $this->pagarSinal($r, 68400);

        $this->assertTrue($this->getJson(self::FECHO . "/{$r->id}")->json('conta.ja_facturada'));

        $this->postJson(self::FECHO . "/{$r->id}/consumos", [
            'category' => 'minibar',
            'description' => 'Minibar',
            'quantity' => 1,
            'unit_price' => 5000,
        ])->assertCreated();

        $this->assertFalse($this->getJson(self::FECHO . "/{$r->id}")->json('conta.ja_facturada'),
            'O consumo novo não pode sair sem factura');
    }

    public function test_pagamento_acima_do_saldo_e_recusado(): void
    {
        $r = $this->reserva();

        $this->postJson(self::API . "/{$r->id}/receber", [
            'valor' => 999999,
            'meio' => $this->metodo->id,
            'facturar' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('valor');

        $this->assertEquals(0, $r->fresh()->paid_amount);
    }

    public function test_pagamento_sem_fatura_nao_apaga_a_ligacao_existente(): void
    {
        $r = $this->reserva();
        $this->pagarSinal($r, 11400);

        $idFactura = $r->fresh()->invoice_id;
        $this->assertNotNull($idFactura);

        $this->postJson(self::API . "/{$r->id}/receber", [
            'valor' => 5000,
            'meio' => $this->metodo->id,
            'facturar' => false,
        ])->assertOk();

        $this->assertSame($idFactura, $r->fresh()->invoice_id);
    }

    public function test_consumos_do_folio_sao_faturados_no_checkout(): void
    {
        $r = $this->reserva();

        \App\Models\Hotel\ReservationItem::create([
            'reservation_id' => $r->id,
            'type'           => 'minibar',
            'category'       => 'minibar',
            'description'    => 'Cerveja',
            'quantity'       => 2,
            'unit_price'     => 1500,
            'date'           => now()->toDateString(),
            'charged_at'     => now(),
        ]);

        $this->fecharEstada($r)->assertOk();

        $factura = $r->fresh()->invoices()->first();

        $this->assertNotNull($factura);
        $this->assertTrue(
            $factura->items->contains(fn ($i) => str_contains($i->product_name, 'Cerveja')),
            'O minibar lançado no folio tem de ir na fatura'
        );
    }

    public function test_checkout_repetido_e_recusado(): void
    {
        $r = $this->reserva('checked_out');
        $r->update(['actual_check_out' => now()]);

        $antes = \App\Models\Invoicing\SalesInvoice::where('tenant_id', $this->tenant->id)->count();

        $this->fecharEstada($r)->assertStatus(422);

        $this->assertSame($antes, \App\Models\Invoicing\SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_double_booking_e_bloqueado(): void
    {
        $quarto = $this->quarto();

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'R' . strtoupper(substr(uniqid(), -9)),
            'client_id' => $this->cliente->id,
            'room_type_id' => $this->tipoQuarto->id,
            'room_id' => $quarto->id,
            'check_in_date' => now()->addDays(5),
            'check_out_date' => now()->addDays(8),
            'nights' => 3, 'room_rate' => 30000,
            'status' => 'confirmed', 'payment_status' => 'pending',
        ]);

        $this->postJson(self::API, [
            'client_id' => $this->cliente->id,
            'room_type_id' => $this->tipoQuarto->id,
            'room_id' => $quarto->id,
            'check_in_date' => now()->addDays(6)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'room_rate' => 30000,
            'adults' => 1, 'children' => 0, 'extra_beds' => 0,
            'source' => 'direct', 'discount' => 0, 'paid_amount' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('room_id');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("transicoesInvalidas")]
    public function test_transicoes_invalidas_sao_recusadas(string $estado, string $metodo): void
    {
        $this->expectException(\DomainException::class);
        $this->reserva($estado)->{$metodo}();
    }

    public static function transicoesInvalidas(): array
    {
        return [
            'cancelada nao pode sair'        => ['cancelled', 'checkOut'],
            'quem ja saiu nao volta a entrar' => ['checked_out', 'checkIn'],
            'no-show nao se confirma'        => ['no_show', 'confirm'],
            'quem ja saiu nao se cancela'    => ['checked_out', 'cancel'],
        ];
    }

    public function test_nao_compareceu_liberta_o_quarto(): void
    {
        $quarto = $this->quarto();
        $r = $this->reserva('confirmed', $quarto);
        $quarto->update(['status' => 'occupied']);

        $this->postJson(self::API . "/{$r->id}/estado", ['accao' => 'nao-compareceu'])->assertOk();

        $this->assertSame('no_show', $r->fresh()->status);
        $this->assertSame('available', $quarto->fresh()->status,
            'Reserva fantasma não pode continuar a bloquear o quarto');
    }

    public function test_desconto_exagerado_nao_marca_a_reserva_como_paga(): void
    {
        $r = new Reservation([
            'tenant_id' => $this->tenant->id,
            'room_rate' => 10000, 'nights' => 1,
            'discount'  => 999999, 'paid_amount' => 0,
        ]);

        $r->calculateTotals();

        $this->assertGreaterThanOrEqual(0, (float) $r->total);
        $this->assertSame('pending', $r->payment_status);
    }

    public function test_estado_de_pagamento_desce_quando_o_total_sobe(): void
    {
        $r = new Reservation([
            'tenant_id' => $this->tenant->id,
            'room_rate' => 10000, 'nights' => 1, 'discount' => 0, 'paid_amount' => 11400,
        ]);

        $r->calculateTotals();
        $this->assertSame('paid', $r->payment_status);

        $r->room_rate = 20000;          // equivale a acrescentar consumo
        $r->calculateTotals();

        $this->assertSame('partial', $r->payment_status, 'O estado só subia, nunca descia');
    }

    public function test_folio_fecha_depois_do_checkout(): void
    {
        $r = $this->reserva('checked_out');

        $this->postJson(self::FECHO . "/{$r->id}/consumos", [
            'category' => 'minibar',
            'description' => 'Cerveja',
            'quantity' => 1,
            'unit_price' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('description');

        $this->assertSame(0, \App\Models\Hotel\ReservationItem::where('reservation_id', $r->id)->count(),
            'Um hóspede que já saiu não acumula consumos novos');
    }
}
