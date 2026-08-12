<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * A cadeia completa: modelo activo → selecção de registos → envio.
 *
 * Os testes do serviço de envio provavam que uma notificação sai. Não provavam
 * o caminho até lá — e era aí que estavam os defeitos que restavam, incluindo
 * o pior de todos.
 */
class NotificacoesDespachoTest extends TenantTestCase
{
    private function comEmailConfigurado(): void
    {
        TenantNotificationSetting::getForTenant($this->tenant->id)->update([
            'email_enabled' => true,
            'smtp_host'     => 'smtp.exemplo.ao',
            'smtp_port'     => 587,
            'smtp_username' => 'avisos@exemplo.ao',
            'smtp_password' => 'senha',
            'from_email'    => 'avisos@exemplo.ao',
        ]);
    }

    private function modelo(array $dados): NotificationTemplate
    {
        return NotificationTemplate::create(array_merge([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Aviso',
            'slug'          => 'aviso-' . uniqid(),
            'module'        => 'events',
            'email_enabled' => true,
            'email_subject' => 'Aviso sobre {{name}}',
            'email_body'    => 'O evento {{name}} está a chegar.',
            'is_active'     => true,
        ], $dados));
    }

    /** Um evento daqui a $minutos, com contacto de email. */
    private function evento(int $minutos, string $email = 'organizador@exemplo.ao'): int
    {
        static $n = 0;
        $n++;

        $colunas = [
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Casamento Silva',
            'event_number' => 'EV-' . uniqid() . '-' . $n,
            'start_date'   => now()->addMinutes($minutos),
            'end_date'     => now()->addMinutes($minutos + 120),
            'status'       => 'confirmado',
            'created_at'   => now(),
            'updated_at'   => now(),
        ];

        // O contacto de um evento é o CLIENTE, por `client_id` — não há
        // colunas de email no próprio evento.
        $colunas['client_id'] = \App\Models\Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente do Evento',
            'email'     => $email,
            'nif'       => (string) random_int(100000000, 199999999),
            'type'      => 'pessoa_fisica',
            'is_active' => true,
        ])->id;

        return DB::table('events_events')->insertGetId($colunas);
    }

    private function despachar(): void
    {
        Artisan::call('notifications:send-scheduled', ['--tenant' => $this->tenant->id]);
    }

    public function test_um_gatilho_desconhecido_nao_notifica_toda_a_gente(): void
    {
        // O DEFEITO MAIS PERIGOSO DE TODOS, e o que quase escapou.
        //
        // O `switch` que escolhe os registos não tinha `default`. Um gatilho
        // que o código não sabe filtrar — como `status_changed`, que dez dos
        // modelos activos deste sistema usam — caía sem filtro nenhum e a
        // consulta devolvia a TABELA INTEIRA.
        //
        // Enquanto o email era um TODO e o SMS ia parar ao WhatsApp, isso não
        // se via. No momento em que os canais passassem a funcionar — que é
        // exactamente o que eu tinha acabado de fazer — uma única passagem
        // mandava um aviso a toda a gente da tabela.
        Mail::fake();
        $this->comEmailConfigurado();

        for ($i = 0; $i < 5; $i++) {
            $this->evento(60 * 24 * 30, "pessoa{$i}@exemplo.ao");
        }

        $this->modelo(['trigger_event' => 'status_changed']);

        $this->despachar();

        Mail::assertNothingSent();
    }

    public function test_um_lembrete_dentro_do_prazo_e_enviado(): void
    {
        // E o contrário tem de continuar a acontecer: o aviso legítimo sai.
        Mail::fake();
        $this->comEmailConfigurado();

        $this->evento(30);   // daqui a 30 minutos

        $this->modelo([
            'trigger_event'         => 'date_approaching',
            'notify_before_minutes' => 60,
        ]);

        $this->despachar();

        Mail::assertSent(\App\Mail\NotificacaoDeModelo::class);
    }

    public function test_um_lembrete_fora_do_prazo_nao_e_enviado(): void
    {
        Mail::fake();
        $this->comEmailConfigurado();

        $this->evento(60 * 24 * 10);   // daqui a dez dias

        $this->modelo([
            'trigger_event'         => 'date_approaching',
            'notify_before_minutes' => 60,
        ]);

        $this->despachar();

        Mail::assertNothingSent();
    }

    public function test_a_janela_apanha_o_que_a_antiga_deixava_escapar(): void
    {
        // A janela antiga eram trinta minutos a começar no instante exacto do
        // aviso: `[agora + antecedência, +30min]`. Um evento daqui a 45 minutos,
        // com aviso de 60, caía FORA — e como a janela avança com o relógio,
        // nunca mais era apanhado.
        Mail::fake();
        $this->comEmailConfigurado();

        $this->evento(45);

        $this->modelo([
            'trigger_event'         => 'date_approaching',
            'notify_before_minutes' => 60,
        ]);

        $this->despachar();

        Mail::assertSent(\App\Mail\NotificacaoDeModelo::class);
    }

    public function test_despachar_duas_vezes_nao_repete_o_aviso(): void
    {
        // A condição para o disparo pelo tráfego: com uma tranca de dez
        // minutos, isto corre muitas vezes por dia.
        Mail::fake();
        $this->comEmailConfigurado();

        $this->evento(30);

        $this->modelo([
            'trigger_event'         => 'date_approaching',
            'notify_before_minutes' => 60,
        ]);

        $this->despachar();
        $this->despachar();
        $this->despachar();

        Mail::assertSentCount(1);
    }

    public function test_uma_tabela_inexistente_nao_rebenta_o_despacho(): void
    {
        // `hr` apontava para `employees`, que nunca existiu (é `hr_employees`),
        // e `tasks`, `projects` e `crm_leads` também não existem. Doze modelos
        // rebentavam a cada passagem, com o erro enterrado no log.
        Mail::fake();
        $this->comEmailConfigurado();

        $this->modelo(['module' => 'tasks', 'trigger_event' => 'created']);
        $this->modelo(['module' => 'crm', 'trigger_event' => 'created']);

        $this->despachar();

        // Chegar aqui já é o teste: não lançou.
        $this->assertTrue(true);
    }

    public function test_o_modulo_de_rh_aponta_para_a_tabela_certa(): void
    {
        $comando = new \App\Console\Commands\SendScheduledNotifications();

        $metodo = new \ReflectionMethod($comando, 'getTableName');
        $metodo->setAccessible(true);

        $tabela = $metodo->invoke($comando, 'hr');

        $this->assertSame('hr_employees', $tabela);
        $this->assertTrue(Schema::hasTable($tabela), 'a tabela do módulo de RH tem de existir');
    }

    public function test_ha_tecto_de_registos_por_passagem(): void
    {
        // Isto corre à boleia do pedido de quem está a trabalhar. Mesmo com a
        // selecção certa, um erro de dados não pode virar mil emails.
        Mail::fake();
        $this->comEmailConfigurado();

        for ($i = 0; $i < 60; $i++) {
            $this->evento(30, "pessoa{$i}@exemplo.ao");
        }

        $this->modelo([
            'trigger_event'         => 'date_approaching',
            'notify_before_minutes' => 60,
        ]);

        $this->despachar();

        $linhas = DB::table('notification_sends')->where('tenant_id', $this->tenant->id)->get();

        $this->assertLessThanOrEqual(50, $linhas->count(), 'o tecto por passagem tem de valer');
        $this->assertGreaterThan(
            0,
            $linhas->count(),
            'nada saiu; registos em notification_sends: ' . $linhas->pluck('error')->implode(' | ')
        );
    }

    public function test_o_despacho_ignora_empresas_sem_o_modulo(): void
    {
        // O middleware só despacha empresas com o módulo de notificações
        // ligado — perguntá-lo evita varrer modelos de quem não o usa.
        $middleware = new \App\Http\Middleware\DespacharNotificacoes();

        $metodo = new \ReflectionMethod($middleware, 'temModulo');
        $metodo->setAccessible(true);

        \Illuminate\Support\Facades\Cache::flush();

        $this->assertFalse(
            $metodo->invoke($middleware, $this->tenant->id),
            'sem o módulo ligado não há nada a despachar'
        );
    }
}
