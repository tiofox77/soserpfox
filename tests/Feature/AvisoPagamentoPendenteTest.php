<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\SoftwareSetting;
use App\Services\Billing\AvisoDePagamentoPendente;
use App\Services\SmsService;
use Tests\TenantTestCase;

/**
 * O SMS que avisa que há um pagamento à espera de aprovação.
 *
 * O cliente transfere, anexa o comprovativo e fica à espera. Do outro lado, o
 * pedido cai numa lista que só é vista por quem se lembrar de abrir
 * /superadmin/billing — e entretanto há um cliente a pagar e sem acesso.
 *
 * SMS e não email de propósito: um email pendurado numa caixa de entrada não
 * acorda ninguém ao fim de semana.
 */
class AvisoPagamentoPendenteTest extends TenantTestCase
{
    /**
     * Os SMS enviados durante o teste, em vez de irem para a operadora.
     *
     * Estático porque o duplo do serviço é construído uma vez e os testes
     * limpam a lista pelo meio: uma propriedade capturada por referência
     * deixava de apontar para o mesmo sítio à primeira reatribuição.
     */
    public static array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$enviados = [];

        $this->app->instance(SmsService::class, new class extends SmsService {
            public function send($recipient, $message, $type = null, $userId = null, $tenantId = null)
            {
                AvisoPagamentoPendenteTest::$enviados[] = [
                    'para' => $recipient, 'texto' => $message, 'tipo' => $type,
                ];

                return true;
            }
        });

        // O aviso vai com defer(), que num teste não tem resposta HTTP a seguir.
        // withoutDefer() faz os callbacks correr de imediato.
        $this->withoutDefer();

        // Cada teste parte sem memória do anterior — o aviso não se repete.
        \Cache::flush();
    }

    private function plano(): Plan
    {
        return Plan::create([
            'name' => 'Business', 'slug' => 'business-' . uniqid(), 'description' => 'x',
            'price_monthly' => 44900, 'price_yearly' => 449000, 'trial_days' => 0,
            'max_users' => 20, 'max_companies' => 3, 'is_active' => true, 'order' => 1,
        ]);
    }

    private function pedido(array $extra = []): Order
    {
        return Order::create(array_merge([
            'tenant_id'      => $this->tenant->id,
            'user_id'        => $this->user->id,
            'plan_id'        => $this->plano()->id,
            'amount'         => 44900,
            'billing_cycle'  => 'monthly',
            'status'         => 'pending',
            'payment_method' => 'bank_transfer',
        ], $extra));
    }

    public function test_o_numero_por_omissao_e_o_que_foi_pedido(): void
    {
        $this->assertSame('939729902', AvisoDePagamentoPendente::numero());
    }

    public function test_um_pedido_pendente_avisa_por_sms(): void
    {
        $this->pedido();

        $this->assertCount(1, self::$enviados);
        $this->assertSame('939729902', self::$enviados[0]['para']);
        $this->assertSame('pagamento_pendente', self::$enviados[0]['tipo']);
        $this->assertStringContainsString($this->tenant->name, self::$enviados[0]['texto']);
        $this->assertStringContainsString('44.900', self::$enviados[0]['texto']);
    }

    /** Um pedido já aprovado não tem nada à espera. */
    public function test_um_pedido_ja_aprovado_nao_avisa(): void
    {
        $this->pedido(['status' => 'approved']);

        $this->assertCount(0, self::$enviados);
    }

    /** O comprovativo é o momento em que há mesmo trabalho para fazer. */
    public function test_anexar_o_comprovativo_avisa_com_a_ligacao_para_o_ecra(): void
    {
        $pedido = $this->pedido();
        self::$enviados = [];

        $pedido->update(['payment_proof' => 'payment-proofs/abc.pdf']);

        $this->assertCount(1, self::$enviados);
        $this->assertStringContainsString('AGUARDA', mb_strtoupper(self::$enviados[0]['texto']));
        $this->assertStringContainsString('superadmin/billing', self::$enviados[0]['texto']);
    }

    /** Duas gravações seguidas não mandam dois SMS. */
    public function test_nao_se_avisa_duas_vezes_pela_mesma_coisa(): void
    {
        $pedido = $this->pedido();
        self::$enviados = [];

        $pedido->update(['payment_proof' => 'payment-proofs/abc.pdf']);
        $pedido->update(['payment_proof' => 'payment-proofs/abc.pdf', 'notes' => 'x']);
        $pedido->update(['payment_proof' => 'payment-proofs/outro.pdf']);

        $this->assertCount(1, self::$enviados, 'Um pedido, um aviso.');
    }

    /** Desligado é desligado. */
    public function test_com_o_aviso_desligado_nao_sai_nada(): void
    {
        SoftwareSetting::set('billing', 'aviso_sms_ativo', false, 'boolean');

        try {
            $this->pedido();
            $this->assertCount(0, self::$enviados);
        } finally {
            SoftwareSetting::set('billing', 'aviso_sms_ativo', true, 'boolean');
        }
    }

    /** O número muda na base, sem deploy. */
    public function test_o_numero_pode_ser_mudado_nas_definicoes(): void
    {
        SoftwareSetting::set('billing', 'aviso_sms_numero', '923000111', 'string');

        try {
            $this->pedido();
            $this->assertSame('923000111', self::$enviados[0]['para']);
        } finally {
            SoftwareSetting::set('billing', 'aviso_sms_numero', '939729902', 'string');
        }
    }

    /**
     * O que não pode acontecer de maneira nenhuma.
     *
     * Quem está a pagar não pode perder a compra porque a operadora está em
     * baixo.
     */
    public function test_uma_falha_do_sms_nao_estraga_o_pagamento(): void
    {
        $rebenta = new class extends SmsService {
            public function send($recipient, $message, $type = null, $userId = null, $tenantId = null)
            {
                throw new \RuntimeException('A operadora está em baixo.');
            }
        };

        $this->app->instance(SmsService::class, $rebenta);

        $pedido = $this->pedido();

        $this->assertNotNull(Order::find($pedido->id), 'O pedido tem de ficar gravado.');
        $this->assertSame('pending', $pedido->refresh()->status);
    }

    /** Um nome de empresa comprido não come o SMS todo. */
    public function test_um_nome_comprido_e_cortado(): void
    {
        $this->tenant->update([
            'name' => 'Sociedade Comercial de Importação e Exportação do Kwanza Sul, Limitada',
        ]);

        $this->pedido();

        $this->assertLessThanOrEqual(160, mb_strlen(self::$enviados[0]['texto']));
    }
}
