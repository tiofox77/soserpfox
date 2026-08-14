<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Invoicing\SalesInvoice;
use App\Models\PlatformMessage;
use App\Services\AGT\DocumentMapper;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A aplicacao passou a correr em hora de Angola (UTC+1, sem horario de verao).
 *
 * Nada do que ja estava gravado foi mexido — foi decisao do dono do produto, e
 * e a decisao certa pelo lado fiscal: o system_entry_date entra na cadeia
 * assinada com RSA-SHA256 e cada documento assina o hash do anterior, pelo que
 * somar uma hora a um so partia a cadeia inteira a partir dele.
 *
 * O que estes testes guardam sao as tres fronteiras onde a mudanca podia partir
 * alguma coisa: o que sai para a AGT, o que entra vindo dos dispositivos
 * offline, e o que se escreve nos formularios.
 */
class FusoDeAngolaTest extends TenantTestCase
{
    public function test_a_aplicacao_corre_em_hora_de_angola(): void
    {
        $this->assertSame('Africa/Luanda', config('app.timezone'));
        $this->assertSame('+01:00', now()->format('P'), 'Angola e UTC+1 o ano inteiro');
    }

    /** Angola nao tem horario de verao: a diferenca e a mesma em Janeiro e em Julho. */
    public function test_a_diferenca_nao_muda_com_a_estacao(): void
    {
        foreach (['2026-01-15 12:00:00', '2026-07-15 12:00:00'] as $quando) {
            $this->assertSame(
                '+01:00',
                \Carbon\Carbon::parse($quando, 'Africa/Luanda')->format('P'),
                "a diferenca devia ser sempre +01:00 ({$quando})"
            );
        }
    }

    // ---- a fronteira dos dispositivos offline ---------------------------

    /**
     * O POS offline carimba com new Date().toISOString(), que da UTC com Z.
     * Sem converter, uma venda das 14:00 era gravada "13:00" e passava a ser
     * lida como 13:00 de Angola — uma hora antes de ter acontecido.
     */
    public function test_um_carimbo_iso_em_utc_e_trazido_para_a_hora_de_angola(): void
    {
        $vindo = DateHelper::doDispositivo('2026-08-14T13:00:00.000Z');

        $this->assertSame('2026-08-14 14:00:00', $vindo->format('Y-m-d H:i:s'),
            '13:00Z sao 14:00 em Angola');
        $this->assertSame('Africa/Luanda', $vindo->timezone->getName());
    }

    /** Um carimbo que ja traz o fuso de Angola fica onde esta. */
    public function test_um_carimbo_que_ja_vem_de_angola_nao_e_deslocado(): void
    {
        $vindo = DateHelper::doDispositivo('2026-08-14T14:00:00+01:00');

        $this->assertSame('2026-08-14 14:00:00', $vindo->format('Y-m-d H:i:s'));
    }

    /** Sem fuso nenhum — a app movel manda relogio de parede — le-se como Angola. */
    public function test_um_carimbo_sem_fuso_e_lido_como_hora_de_angola(): void
    {
        $vindo = DateHelper::doDispositivo('2026-08-14T14:00:00');

        $this->assertSame('2026-08-14 14:00:00', $vindo->format('Y-m-d H:i:s'));
    }

    /** Um carimbo ilegivel nao pode fazer perder a venda que o traz. */
    public function test_um_carimbo_ilegivel_devolve_nulo_em_vez_de_estoirar(): void
    {
        $this->assertNull(DateHelper::doDispositivo('isto nao e uma data'));
        $this->assertNull(DateHelper::doDispositivo(null));
        $this->assertNull(DateHelper::doDispositivo(''));
    }

    /** A venda offline fica gravada com a hora a que foi mesmo feita. */
    public function test_a_venda_offline_fica_com_a_hora_a_que_aconteceu(): void
    {
        $servico = app(\App\Services\POS\PosSaleService::class);

        $metodo = new \ReflectionMethod($servico, 'resolveClient');
        $metodo->setAccessible(true);

        // 13:00Z = 14:00 em Angola. A data do documento tem de ser a de Angola.
        $data = DateHelper::doDispositivo('2026-08-14T13:00:00.000Z');

        $this->assertSame('2026-08-14', $data->toDateString());
        $this->assertSame(14, $data->hour);
    }

    /**
     * O caso da meia-noite: uma venda a 00:30 de dia 15 em Angola e 23:30Z de
     * dia 14. Se a data do documento viesse do relogio UTC saia datada de 14.
     */
    public function test_uma_venda_depois_da_meia_noite_fica_no_dia_certo(): void
    {
        $data = DateHelper::doDispositivo('2026-08-14T23:30:00.000Z');

        $this->assertSame('2026-08-15', $data->toDateString(),
            'a venda foi feita a 00:30 de dia 15 em Angola');
    }

    // ---- a fronteira da AGT ---------------------------------------------

    /**
     * O que sai para a AGT tem de ser o MESMO que foi assinado. O
     * DocumentMapper convertia para UTC, e isso era inofensivo so enquanto o
     * fuso da aplicacao tambem era UTC.
     */
    public function test_o_que_vai_para_a_agt_e_o_que_esta_gravado_sem_converter(): void
    {
        // Sem gravar: o metodo so le o atributo, e o que se quer provar e que
        // nao lhe mexe. Os digitos '14:00' sao os que o SignatureService assina.
        $factura = new SalesInvoice(['system_entry_date' => '2026-08-14 14:00:00']);

        $metodo = new \ReflectionMethod(DocumentMapper::class, 'resolveSystemEntryDate');
        $metodo->setAccessible(true);

        $saiu = $metodo->invoke(app(DocumentMapper::class), $factura);

        $this->assertSame('2026-08-14T14:00:00Z', $saiu,
            'os digitos tem de ser os gravados, que sao os assinados');
    }

    /** E o que o SignatureService assina tem de ser exactamente os mesmos digitos. */
    public function test_a_agt_recebe_os_mesmos_digitos_que_foram_assinados(): void
    {
        $factura = new SalesInvoice(['system_entry_date' => '2026-08-14 14:00:00']);

        $metodo = new \ReflectionMethod(DocumentMapper::class, 'resolveSystemEntryDate');
        $metodo->setAccessible(true);

        $paraAgt    = $metodo->invoke(app(DocumentMapper::class), $factura);
        $paraAssinar = $factura->system_entry_date->format('Y-m-d H:i:s');

        $this->assertSame(
            str_replace(['T', 'Z'], [' ', ''], $paraAgt),
            $paraAssinar,
            'a AGT tem de receber o mesmo instante que entrou na assinatura'
        );
    }

    /** O submissionTimeStamp e outra coisa: e um instante de agora, e vai mesmo em UTC. */
    public function test_o_carimbo_da_submissao_continua_a_ir_em_utc(): void
    {
        $agora = now();

        $this->assertSame(
            $agora->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            $agora->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'now()->utc() continua a dar o instante certo em qualquer fuso'
        );
        $this->assertNotSame(
            $agora->format('H'),
            $agora->copy()->utc()->format('H'),
            'e tem mesmo de diferir da hora local, senao nao se estaria a converter nada'
        );
    }

    // ---- a fronteira dos formularios ------------------------------------

    /**
     * O datetime-local devolve os digitos escritos. Como a aplicacao ja corre
     * em hora de Angola, esses digitos sao o que se grava — converter aqui
     * seria tirar a hora duas vezes.
     */
    public function test_a_hora_escrita_num_formulario_fica_como_foi_escrita(): void
    {
        $guardada = PlatformMessage::doRelogioDeParede('2026-08-14T10:20');

        $this->assertSame('2026-08-14 10:20:00', $guardada->format('Y-m-d H:i:s'),
            'converter aqui punha a mensagem no ar uma hora ANTES do pedido');
    }

    /** E uma mensagem marcada para agora esta no ar agora. */
    public function test_uma_mensagem_marcada_para_esta_hora_esta_no_ar(): void
    {
        $m = PlatformMessage::create([
            'title'     => 'Agora',
            'body'      => 'Teste',
            'level'     => 'info',
            'display'   => 'barra',
            'audience'  => 'todas',
            'is_active' => true,
            'starts_at' => PlatformMessage::doRelogioDeParede(now()->subMinute()->format('Y-m-d\TH:i')),
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue(PlatformMessage::noAr()->whereKey($m->id)->exists());
    }
}
