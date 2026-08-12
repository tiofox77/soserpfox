<?php

namespace Tests\Feature;

use App\Listeners\RegistarEmailEnviado;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * Todo o correio que sai fica registado.
 *
 * O ecrã /superadmin/email-logs existia e mostrava dois registos: os envios de
 * teste do ecrã de SMTP. Tudo o resto — a aprovação de um pedido, a recusa, as
 * boas-vindas, os avisos de stock, as notificações agendadas, a reposição de
 * palavra-passe — saía sem deixar rasto. Quando um cliente dizia "não recebi
 * nada", não havia como saber se o email tinha chegado a sair.
 *
 * Registar em cada sítio que envia é uma lista que nunca fica completa: basta
 * alguém acrescentar um Mail::send e o registo volta a ter buracos. Isto vive
 * nos eventos do próprio Laravel, por onde passa obrigatoriamente todo o
 * correio, venha de onde vier — e é isso que estes testes fixam.
 */
class RegistoDeEmailsTest extends TenantTestCase
{
    public function test_um_email_qualquer_fica_registado(): void
    {
        $antes = EmailLog::count();

        Mail::raw('O corpo da mensagem.', function ($m) {
            $m->to('cliente@exemplo.ao', 'Cliente')->subject('Assunto de teste');
        });

        $this->assertSame($antes + 1, EmailLog::count());

        $registo = EmailLog::latest('id')->first();
        $this->assertSame('cliente@exemplo.ao', $registo->to_email);
        $this->assertSame('Assunto de teste', $registo->subject);
        $this->assertSame('sent', $registo->status);
        $this->assertNotNull($registo->sent_at);
    }

    /** O excerto do corpo é o que permite perceber que email era. */
    public function test_guarda_se_um_excerto_do_corpo(): void
    {
        Mail::raw('A sua subscrição do plano Business foi activada.', function ($m) {
            $m->to('cliente@exemplo.ao')->subject('Plano activado');
        });

        $this->assertStringContainsString(
            'plano Business foi activada',
            EmailLog::latest('id')->first()->body_preview
        );
    }

    /** Um envio para vários destinatários regista o primeiro, e não rebenta. */
    public function test_varios_destinatarios_nao_rebentam_o_registo(): void
    {
        Mail::raw('x', function ($m) {
            $m->to(['um@exemplo.ao', 'dois@exemplo.ao'])->subject('Para dois');
        });

        $this->assertSame('um@exemplo.ao', EmailLog::latest('id')->first()->to_email);
    }

    /** Uma falha de envio também deixa rasto — com o erro. */
    public function test_uma_falha_fica_registada_com_o_motivo(): void
    {
        RegistarEmailEnviado::falhou(
            'ninguem@exemplo.ao',
            'Aviso de stock',
            'Connection could not be established with host smtp.exemplo.ao',
            $this->tenant->id
        );

        $registo = EmailLog::latest('id')->first();
        $this->assertSame('failed', $registo->status);
        $this->assertSame('ninguem@exemplo.ao', $registo->to_email);
        $this->assertStringContainsString('Connection could not be established', $registo->error_message);
        $this->assertNotNull($registo->failed_at);
    }

    /** O registo não pode nunca impedir o email de sair. */
    public function test_o_registo_nao_trava_o_envio(): void
    {
        // Com a tabela fora de alcance, o envio segue e fica só um aviso no log.
        \Schema::rename('email_logs', 'email_logs_escondida');

        try {
            Mail::raw('x', function ($m) {
                $m->to('cliente@exemplo.ao')->subject('Tem de sair na mesma');
            });

            $this->assertTrue(true, 'O envio não pode depender do registo.');
        } finally {
            \Schema::rename('email_logs_escondida', 'email_logs');
        }
    }

    /** Um email do sistema fica ligado à empresa activa. */
    public function test_o_email_fica_ligado_a_empresa(): void
    {
        Mail::raw('x', function ($m) {
            $m->to('cliente@exemplo.ao')->subject('Da empresa');
        });

        $this->assertSame($this->tenant->id, EmailLog::latest('id')->first()->tenant_id);
    }
}
