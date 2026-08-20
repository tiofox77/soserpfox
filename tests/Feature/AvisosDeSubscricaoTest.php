<?php

namespace Tests\Feature;

use App\Mail\TemplateMail;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\SmtpSetting;
use App\Models\Subscription;
use App\Services\Billing\AvisosDeSubscricao;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * Os avisos de facturação ao CLIENTE, por email e por SMS.
 *
 * O ciclo emitia a factura e cortava o acesso sem nunca dizer nada a ninguém:
 * o cliente descobria que tinha uma conta por pagar quando o sistema deixava
 * de abrir.
 *
 * O que estes testes protegem, por ordem de importância:
 *   1. nada sai duas vezes — a varredura corre a cada hora e a condição que a
 *      dispara continua verdadeira até alguém pagar;
 *   2. o SMS só sai com o seu próprio interruptor ligado — custa dinheiro;
 *   3. a mensagem sai pela PLATAFORMA e nomeia a empresa certa.
 */
class AvisosDeSubscricaoTest extends TenantTestCase
{
    /** Os SMS que teriam ido para a operadora. */
    public static array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$sms = [];
        \Cache::flush();
        $this->withoutDefer();

        Mail::fake();

        // Sem SMTP por omissão, o EmailTemplate::sendEmail rebenta com
        // "Nenhuma configuração SMTP encontrada" e todos os testes falhariam
        // pela razão errada.
        SmtpSetting::create([
            'tenant_id' => null, 'host' => 'smtp.exemplo.ao', 'port' => 587,
            'username' => 'avisos@soserp.vip', 'password' => 'x', 'encryption' => 'tls',
            'from_email' => 'avisos@soserp.vip', 'from_name' => 'SOS ERP',
            'is_default' => true, 'is_active' => true,
        ]);

        (new \Database\Seeders\AvisosDeSubscricaoTemplatesSeeder())->run();

        $this->app->instance(SmsService::class, new class extends SmsService {
            public function send($recipient, $message, $type = null, $userId = null, $tenantId = null)
            {
                AvisosDeSubscricaoTest::$sms[] = [
                    'para' => $recipient, 'texto' => $message, 'tipo' => $type, 'tenant' => $tenantId,
                ];

                return ['success' => true, 'log_id' => null];
            }
        });

        config([
            'billing.avisos_ao_cliente' => true,
            'billing.avisos_sms'        => true,
        ]);

        // O responsável tem de ter contactos para haver a quem avisar.
        $this->user->forceFill(['email' => 'dono@cliente.ao', 'phone' => '923456789'])->save();
    }

    private function servico(): AvisosDeSubscricao
    {
        return app(AvisosDeSubscricao::class);
    }

    private function plano(array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Empresarial', 'slug' => 'emp-' . uniqid(),
            'price_monthly' => 25000, 'price_yearly' => 250000,
            'max_users' => 10, 'is_active' => true,
        ], $extra));
    }

    /** Subscrição activa que acaba daqui a $dias. */
    private function subscricao(int $dias, array $extra = []): Subscription
    {
        $this->tenant->subscriptions()->update(['status' => 'cancelled']);
        $fim = now()->addDays($dias);

        return $this->tenant->subscriptions()->create(array_merge([
            'plan_id'              => $this->plano()->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'amount'               => 25000,
            'current_period_start' => $fim->copy()->subMonth(),
            'current_period_end'   => $fim,
            'ends_at'              => $fim,
        ], $extra));
    }

    private function factura(Subscription $sub, $vencimento, string $estado = 'pending'): Invoice
    {
        return Invoice::create([
            'tenant_id'       => $this->tenant->id,
            'subscription_id' => $sub->id,
            'invoice_number'  => Invoice::generateInvoiceNumber(),
            'description'     => 'Renovação',
            'invoice_date'    => now()->subDays(2),
            'due_date'        => $vencimento,
            'subtotal'        => 25000, 'tax' => 0, 'total' => 25000,
            'status'          => $estado,
        ]);
    }

    private function registados(?string $canal = null): int
    {
        $q = DB::table('avisos_de_subscricao');

        if ($canal) {
            $q->where('canal', $canal);
        }

        return $q->count();
    }

    // ── (a) a factura foi emitida ────────────────────────────────────────────

    public function test_emitir_a_factura_avisa_o_cliente_por_email_e_sms(): void
    {
        $sub = $this->subscricao(4);

        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();

        Mail::assertSent(TemplateMail::class, fn ($m) =>
            $m->templateSlug === 'subscricao_factura_emitida'
            && $m->hasTo('dono@cliente.ao'));

        $this->assertCount(1, self::$sms);
        $this->assertStringContainsString('SOSERP', self::$sms[0]['texto']);
        $this->assertSame('+244923456789', self::$sms[0]['para']);
    }

    public function test_o_sms_sai_pelas_credenciais_da_plataforma_e_nao_pelas_do_cliente(): void
    {
        $sub = $this->subscricao(4);

        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();

        // tenantId nulo: com o id da empresa, o SMS que lhe cobra a subscrição
        // saía da conta e do remetente dela.
        $this->assertNull(self::$sms[0]['tenant']);
    }

    // ── (b) e (c) a varredura ────────────────────────────────────────────────

    public function test_avisa_a_factura_a_vencer_nos_dias_configurados(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $r = $this->servico()->varrer();

        $this->assertSame(1, $r['email']);
        Mail::assertSent(TemplateMail::class, fn ($m) => $m->templateSlug === 'subscricao_factura_a_vencer');
    }

    /**
     * A contagem de dias tem de chegar à mensagem.
     *
     * Chegou a sair sempre "0 dia(s)": os dados eram unidos com `+`, que em PHP
     * mantém o valor da ESQUERDA, e do lado esquerdo estava um `'dias' => 0`.
     * O aviso saía, a memória gravava, e o cliente lia uma frase absurda.
     */
    public function test_a_mensagem_diz_quantos_dias_faltam_mesmo(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->servico()->varrer();

        Mail::assertSent(TemplateMail::class, function ($m) {
            $texto = app(\App\Models\EmailTemplate::class)
                ->newQuery()->where('slug', $m->templateSlug)->first()
                ->render($m->data)['body_text'];

            return str_contains($texto, '3 dia(s)');
        });

        $this->assertStringContainsString('3 dia(s)', self::$sms[0]['texto']);
    }

    public function test_o_aviso_de_vencida_diz_ha_quantos_dias(): void
    {
        $sub = $this->subscricao(-1);
        $this->factura($sub, now()->subDays(3));

        $this->servico()->varrer();

        Mail::assertSent(TemplateMail::class, fn ($m) => ($m->data['dias'] ?? null) == 3);
    }

    public function test_nao_avisa_num_dia_que_nao_esta_na_lista(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(5));   // 5 não está em [3, 1]

        $r = $this->servico()->varrer();

        $this->assertSame(0, $r['email'], 'dias exactos, não um intervalo — senão avisa todos os dias');
    }

    public function test_avisa_a_factura_vencida(): void
    {
        $sub = $this->subscricao(-1);
        $this->factura($sub, now()->subDays(3));

        $r = $this->servico()->varrer();

        $this->assertSame(1, $r['email']);
        Mail::assertSent(TemplateMail::class, fn ($m) => $m->templateSlug === 'subscricao_factura_vencida');
    }

    public function test_uma_factura_paga_nao_gera_aviso_nenhum(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3), 'paid');

        $r = $this->servico()->varrer();

        $this->assertSame(0, $r['email']);
    }

    // ── (e) período a acabar sem factura ─────────────────────────────────────

    public function test_avisa_o_periodo_de_teste_a_acabar_que_nao_tem_factura(): void
    {
        $this->subscricao(3, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3)]);

        $r = $this->servico()->varrer();

        $this->assertSame(1, $r['email']);
        Mail::assertSent(TemplateMail::class, fn ($m) => $m->templateSlug === 'subscricao_plano_a_expirar');
    }

    public function test_quem_ja_tem_factura_por_vencer_nao_leva_tambem_o_aviso_do_periodo(): void
    {
        $sub = $this->subscricao(3);
        $this->factura($sub, now()->addDays(3));

        $r = $this->servico()->varrer();

        // Um aviso, não dois sobre a mesma coisa no mesmo dia.
        $this->assertSame(1, $r['email']);
        Mail::assertNotSent(TemplateMail::class, fn ($m) => $m->templateSlug === 'subscricao_plano_a_expirar');
    }

    // ── nada sai duas vezes ──────────────────────────────────────────────────

    public function test_a_varredura_nao_repete_o_mesmo_aviso(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->servico()->varrer();
        $segunda = $this->servico()->varrer();
        $terceira = $this->servico()->varrer();

        $this->assertSame(0, $segunda['email']);
        $this->assertSame(0, $terceira['email']);
        Mail::assertSentCount(1);
    }

    public function test_emitir_a_factura_duas_vezes_nao_avisa_duas_vezes(): void
    {
        $this->subscricao(4);

        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();
        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();

        Mail::assertSentCount(1);
        $this->assertCount(1, self::$sms);
    }

    public function test_a_memoria_fica_gravada_com_a_empresa_e_a_referencia(): void
    {
        $sub = $this->subscricao(10);
        $factura = $this->factura($sub, now()->addDays(3));

        $this->servico()->varrer();

        $linha = DB::table('avisos_de_subscricao')->where('canal', 'email')->first();

        $this->assertSame($this->tenant->id, (int) $linha->tenant_id);
        $this->assertSame('factura_a_vencer', $linha->aviso);
        $this->assertSame("factura:{$factura->id}", $linha->referencia);
        $this->assertSame('sent', $linha->status);
    }

    // ── o interruptor do canal pago ──────────────────────────────────────────

    public function test_com_o_sms_desligado_sai_email_e_nao_sai_sms(): void
    {
        config(['billing.avisos_sms' => false]);
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $r = $this->servico()->varrer();

        $this->assertSame(1, $r['email']);
        $this->assertSame(0, $r['sms']);
        $this->assertCount(0, self::$sms, 'o canal pago tem interruptor próprio');
        $this->assertSame(0, $this->registados('sms'), 'nem sequer reserva o lugar');
    }

    // ── modo de leitura ──────────────────────────────────────────────────────

    public function test_so_ver_nao_envia_nem_deixa_rasto(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $r = $this->servico()->varrer(soVer: true);

        $this->assertSame(1, $r['email']);
        Mail::assertNothingSent();
        $this->assertCount(0, self::$sms);
        // Sem isto, ver o que sairia impedia que saísse mesmo.
        $this->assertSame(0, $this->registados());
    }

    public function test_depois_de_so_ver_a_passagem_a_serio_ainda_envia(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->servico()->varrer(soVer: true);
        $r = app(AvisosDeSubscricao::class)->varrer();

        $this->assertSame(1, $r['email']);
        Mail::assertSentCount(1);
    }

    // ── sem contacto ─────────────────────────────────────────────────────────

    public function test_sem_email_nem_telefone_nao_rebenta_e_fica_contado(): void
    {
        $this->user->forceFill(['email' => 'nao-e-um-email', 'phone' => null])->save();
        $this->tenant->forceFill(['email' => null, 'phone' => null])->save();

        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $r = $this->servico()->varrer();

        $this->assertSame(0, $r['email']);
        $this->assertSame(1, $r['sem_contacto']);
    }

    public function test_um_telefone_impossivel_de_marcar_nao_e_enviado(): void
    {
        // Oito dígitos: não é um número angolano e o fornecedor cobrava na mesma.
        $this->user->forceFill(['phone' => '92345678'])->save();
        $this->tenant->forceFill(['phone' => null])->save();

        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $r = $this->servico()->varrer();

        $this->assertSame(1, $r['email'], 'o email sai à mesma');
        $this->assertCount(0, self::$sms);
    }

    public function test_sem_telefone_no_utilizador_usa_o_da_empresa(): void
    {
        $this->user->forceFill(['phone' => null])->save();
        $this->tenant->forceFill(['phone' => '244912345678'])->save();

        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->servico()->varrer();

        $this->assertCount(1, self::$sms);
        $this->assertSame('+244912345678', self::$sms[0]['para']);
    }

    // ── (d) o pagamento ──────────────────────────────────────────────────────

    public function test_pagar_a_factura_avisa_que_o_plano_foi_renovado(): void
    {
        $sub = $this->subscricao(4);
        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();
        Mail::fake();               // esquece o aviso da emissão
        self::$sms = [];

        $factura = Invoice::where('subscription_id', $sub->id)->first();
        $factura->markAsPaid('bank_transfer', 'REF-1');

        Mail::assertSent(TemplateMail::class, fn ($m) => $m->templateSlug === 'subscricao_renovada');
    }

    public function test_uma_factura_que_nao_estende_nada_nao_anuncia_renovacao(): void
    {
        // A factura inicial de uma subscrição: o período já estava aberto.
        $sub = $this->subscricao(25);
        $factura = $this->factura($sub, now()->addDays(8));
        Mail::fake();

        $factura->markAsPaid();

        Mail::assertNotSent(TemplateMail::class, fn ($m) => $m->templateSlug === 'subscricao_renovada');
    }

    // ── tecto ────────────────────────────────────────────────────────────────

    public function test_o_tecto_da_passagem_e_respeitado_e_fica_assinalado(): void
    {
        config(['billing.avisos_max_por_passagem' => 1]);

        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));
        $this->factura($sub, now()->addDays(1));

        $r = $this->servico()->varrer();

        $this->assertSame(1, $r['email']);
        $this->assertTrue($r['tecto_atingido']);
    }

    // ── o comando e o middleware ─────────────────────────────────────────────

    public function test_o_comando_corre(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->artisan('subscricoes:avisos')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_o_comando_em_so_ver_nao_envia(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->artisan('subscricoes:avisos --so-ver')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_desligado_o_trafego_nao_avisa_ninguem(): void
    {
        config(['billing.avisos_ao_cliente' => false]);
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->correrOMiddleware();

        Mail::assertNothingSent();
    }

    public function test_ligado_o_trafego_avisa(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->correrOMiddleware();

        Mail::assertSentCount(1);
    }

    /**
     * Uma avaria de base de dados não pode passar por "já estava tudo enviado".
     *
     * O reservar() apanhava \Throwable e contava TUDO como repetido. Com a
     * migração por correr em produção, cada reserva rebentava, o resumo dizia
     * "0 email(s), 25 repetido(s)" e quem o lesse concluía que estava tratado.
     * Nenhum cliente era avisado e ninguém ficava a saber.
     *
     * A avaria é simulada sem DDL de propósito: um `rename` de tabela faz
     * commit implícito em MySQL e leva a isolação do teste seguinte com ele.
     */
    public function test_uma_avaria_na_memoria_nao_passa_por_ja_enviado(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->memoriaAvariada();

        $r = $this->servico()->varrer();

        $this->assertSame(0, $r['email']);
        $this->assertTrue($r['avariou'], 'a avaria tem de ser visível no resumo');
        $this->assertSame(0, $r['repetidos'], 'uma avaria não é um repetido');
        $this->assertGreaterThan(0, $r['falhados']);
        Mail::assertNothingSent();
    }

    public function test_o_comando_devolve_erro_quando_a_memoria_avaria(): void
    {
        $sub = $this->subscricao(10);
        $this->factura($sub, now()->addDays(3));

        $this->memoriaAvariada();

        // Código de saída diferente de zero: um resumo a zeros por avaria
        // lê-se exactamente como um resumo a zeros por não haver nada a fazer.
        $this->artisan('subscricoes:avisos')->assertFailed();
    }

    /** A tabela da memória deixa de responder, como se não existisse. */
    private function memoriaAvariada(): void
    {
        DB::partialMock()
            ->shouldReceive('table')
            ->with('avisos_de_subscricao')
            ->andThrow(new \RuntimeException(
                "SQLSTATE[42S02]: Base table or view not found: 'avisos_de_subscricao'"
            ));
    }
    private function correrOMiddleware(): void
    {
        \Cache::forget('plataforma:avisos-subscricao');
        $this->actingAs($this->user);

        app(\App\Http\Middleware\AvisarSubscricoes::class)->terminate(
            \Illuminate\Http\Request::create('/dashboard', 'GET'),
            new \Illuminate\Http\Response()
        );
    }
}
