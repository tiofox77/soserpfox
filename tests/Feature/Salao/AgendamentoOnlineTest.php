<?php

namespace Tests\Feature\Salao;

use App\Models\Salon\Appointment;
use App\Models\Salon\Client;
use App\Models\Salon\Professional;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\Service;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * A PÁGINA PÚBLICA DO SALÃO EM REACT — a montra, os horários e a marcação.
 *
 * Abre-se sem sessão. Os ensaios que importam são os que o Livewire deixava
 * passar: a hora que não é verificada ao marcar, os ids soltos de serviço e
 * profissional, e entrar numa ficha só com o telefone.
 */
class AgendamentoOnlineTest extends TenantTestCase
{
    private SalonSettings $definicoes;
    private Professional $ana;
    private Service $corte;
    private string $dia;

    protected function setUp(): void
    {
        parent::setUp();

        // A página pública só abre a uma empresa com o módulo (CasaPublica).
        $this->comModulo('salon');

        $this->definicoes = SalonSettings::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'booking_slug' => 'salao-teste-'.uniqid(),
                'salon_name' => 'Salão da Ana',
                'online_booking_enabled' => true,
                'require_confirmation' => true,
                'working_days' => [1, 2, 3, 4, 5, 6, 7],
                'opening_time' => '09:00',
                'closing_time' => '12:00',
                'slot_interval' => 60,
                'min_advance_booking_hours' => 0,
                'max_advance_booking_days' => 10,
                'salon_whatsapp' => '+244 923 000 111',
            ],
        );

        $this->ana = Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'is_active' => true,
            'accepts_online_booking' => true, 'working_days' => [1, 2, 3, 4, 5, 6, 7],
            // O horário da pessoa manda sobre o do salão, como sempre mandou.
            'work_start' => '09:00', 'work_end' => '12:00',
        ]);

        $this->corte = Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 5000, 'is_active' => true]);
        $this->corte->updateSalonData(['duration' => 60, 'online_booking' => true]);

        $this->dia = now()->addDays(2)->toDateString();

        auth()->logout();
    }

    private function url(string $caminho = ''): string
    {
        return '/api/publico/salao/'.$this->definicoes->booking_slug.$caminho;
    }

    private function props(TestResponse $r): array
    {
        $r->assertOk()->assertSee('data-ecra="salao/agendar"', false);
        preg_match('/data-props="([^"]*)"/', $r->getContent(), $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    private function horarios(array $servicos = null): array
    {
        return $this->getJson($this->url('/horarios?').http_build_query([
            'profissional' => $this->ana->id, 'data' => $this->dia, 'servicos' => $servicos ?? [$this->corte->id],
        ]))->assertOk()->json('horarios');
    }

    private function marcacao(array $troca = []): array
    {
        return array_merge([
            'servicos' => [$this->corte->id], 'profissional' => $this->ana->id, 'data' => $this->dia, 'hora' => '10:00',
            'nome' => 'Joana Mateus', 'telefone' => '923456789', 'email' => '',
        ], $troca);
    }

    public function test_a_montra_abre_sem_sessao_com_os_servicos_online(): void
    {
        $escondido = Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Só no balcão', 'price' => 100, 'is_active' => true]);
        $escondido->updateSalonData(['duration' => 30, 'online_booking' => false]);

        $props = $this->props($this->get('/agendar/'.$this->definicoes->booking_slug));

        $this->assertSame('Salão da Ana', $props['casa']['nome']);
        $this->assertSame('244923000111', $props['casa']['whatsapp']);
        $this->assertSame(['Corte'], array_column($props['servicos'], 'nome'));
        $this->assertSame([$this->ana->id], array_column($props['profissionais'], 'id'));
        $this->assertContains($this->dia, $props['datas']);
    }

    public function test_um_salao_sem_marcacao_online_recusa_e_um_slug_errado_nao_existe(): void
    {
        $this->get('/agendar/nao-existe-este-salao')->assertNotFound();

        $this->definicoes->update(['online_booking_enabled' => false]);
        $this->get('/agendar/'.$this->definicoes->booking_slug)->assertForbidden();
    }

    public function test_os_horarios_respeitam_o_horario_e_as_marcacoes_que_ja_existem(): void
    {
        // 09:00–12:00, serviços de 60 minutos, de hora a hora.
        $this->assertSame(['09:00', '10:00', '11:00'], $this->horarios());

        Appointment::create([
            'tenant_id' => $this->tenant->id, 'professional_id' => $this->ana->id, 'date' => $this->dia,
            'client_id' => Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Da Agenda', 'phone' => '923000555', 'type' => 'pessoa_fisica'])->id,
            'start_time' => '10:00', 'end_time' => '11:00', 'total_duration' => 60, 'subtotal' => 0, 'total' => 0,
            'status' => 'confirmed', 'source' => 'phone',
        ]);

        $this->assertSame(['09:00', '11:00'], $this->horarios());
    }

    public function test_a_reserva_rapida_marca_e_cria_a_ficha(): void
    {
        $r = $this->postJson($this->url('/marcar'), $this->marcacao())->assertCreated();

        $m = Appointment::withoutGlobalScopes()->where('appointment_number', $r->json('numero'))->firstOrFail();
        $this->assertSame($this->tenant->id, $m->tenant_id);
        $this->assertSame('website', $m->source);
        $this->assertSame('scheduled', $m->status);
        $this->assertEqualsWithDelta(5000, (float) $m->total, 0.01);
        $this->assertSame('11:00', \Carbon\Carbon::parse($m->end_time)->format('H:i'));
        $this->assertSame('Joana Mateus', Client::find($m->client_id)->name);
    }

    public function test_sem_exigir_confirmacao_a_marcacao_nasce_confirmada(): void
    {
        $this->definicoes->update(['require_confirmation' => false]);

        $this->postJson($this->url('/marcar'), $this->marcacao())->assertCreated()->assertJsonPath('estado', 'confirmed');
    }

    /** A hora vinha do browser e ninguém a conferia: 03:00, ou por cima de outra cliente. */
    public function test_uma_hora_fora_dos_horarios_livres_e_recusada(): void
    {
        $this->postJson($this->url('/marcar'), $this->marcacao(['hora' => '03:00']))
            ->assertStatus(422)->assertJsonValidationErrors('hora');

        $this->postJson($this->url('/marcar'), $this->marcacao())->assertCreated();
        // A mesma hora, outra cliente: já não está livre.
        $this->postJson($this->url('/marcar'), $this->marcacao(['telefone' => '923999888', 'nome' => 'Outra Cliente']))
            ->assertStatus(422)->assertJsonValidationErrors('hora');

        $this->assertSame(1, Appointment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_um_servico_fora_da_marcacao_online_ou_de_outra_casa_e_recusado(): void
    {
        $balcao = Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Balcão', 'price' => 100, 'is_active' => true]);
        $balcao->updateSalonData(['duration' => 30, 'online_booking' => false]);

        $vizinha = Tenant::create(['name' => 'Vizinha', 'slug' => 'vz-'.uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'vz'.uniqid().'@ex.ao', 'is_active' => true]);
        $alheio = Service::create(['tenant_id' => $vizinha->id, 'name' => 'Alheio', 'price' => 1, 'is_active' => true]);
        $alheio->updateSalonData(['duration' => 60, 'online_booking' => true]);

        foreach ([$balcao->id, $alheio->id] as $id) {
            $this->postJson($this->url('/marcar'), $this->marcacao(['servicos' => [$id]]))
                ->assertStatus(422)->assertJsonValidationErrors('servicos');
        }
    }

    public function test_um_profissional_que_nao_aceita_marcacoes_online_e_recusado(): void
    {
        $this->ana->update(['accepts_online_booking' => false]);

        $this->postJson($this->url('/marcar'), $this->marcacao())->assertStatus(422)->assertJsonValidationErrors('profissional');
    }

    /** Entrar só com o telefone mostrava a ficha e as marcações de quem tivesse aquele número. */
    public function test_entrar_pede_a_senha(): void
    {
        $semSenha = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Sem Senha', 'phone' => '923111000', 'type' => 'pessoa_fisica']);

        $this->postJson($this->url('/entrar'), ['telefone' => '923111000'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson($this->url('/entrar'), ['telefone' => '923111000', 'password' => 'qualquer'])->assertStatus(422)->assertJsonValidationErrors('telefone');

        $semSenha->updateSalonData(['password' => Hash::make('segredo')]);
        RateLimiter::clear('salao-entrar:'.$this->tenant->id.':923111000|127.0.0.1');

        $this->postJson($this->url('/entrar'), ['telefone' => '923111000', 'password' => 'errada'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson($this->url('/entrar'), ['telefone' => '923 111 000', 'password' => 'segredo'])->assertOk()->assertJsonPath('cliente.nome', 'Sem Senha');
    }

    public function test_entrar_trava_depois_de_cinco_tentativas(): void
    {
        $c = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Rita', 'phone' => '923222000', 'type' => 'pessoa_fisica']);
        $c->updateSalonData(['password' => Hash::make('segredo')]);
        RateLimiter::clear('salao-entrar:'.$this->tenant->id.':923222000|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->url('/entrar'), ['telefone' => '923222000', 'password' => 'errada'])->assertStatus(422);
        }

        $this->postJson($this->url('/entrar'), ['telefone' => '923222000', 'password' => 'segredo'])
            ->assertStatus(422)->assertJsonPath('errors.telefone.0', fn ($m) => str_contains($m, 'Demasiadas tentativas'));
    }

    /** Quem entrou marca em nome da sua ficha — a identidade vem da sessão, não do browser. */
    public function test_registar_entra_e_marca_na_propria_ficha(): void
    {
        $this->postJson($this->url('/registar'), [
            'nome' => 'Carla Nova', 'telefone' => '923333000', 'email' => 'carla@exemplo.ao', 'password' => 'abcd', 'password_confirmation' => 'abcd',
        ])->assertCreated()->assertJsonPath('cliente.nome', 'Carla Nova');

        // Sem nome nem telefone no pedido: é a ficha de quem entrou.
        $r = $this->postJson($this->url('/marcar'), $this->marcacao(['nome' => null, 'telefone' => null]))->assertCreated();

        $m = Appointment::withoutGlobalScopes()->where('appointment_number', $r->json('numero'))->firstOrFail();
        $this->assertSame('Carla Nova', Client::find($m->client_id)->name);

        // A página, reaberta, já sabe quem é.
        $this->assertSame('Carla Nova', $this->props($this->get('/agendar/'.$this->definicoes->booking_slug))['cliente']['nome']);

        $this->postJson($this->url('/sair'))->assertOk();
        $this->assertNull($this->props($this->get('/agendar/'.$this->definicoes->booking_slug))['cliente']);
    }

    public function test_registar_com_um_telefone_que_ja_existe_manda_entrar(): void
    {
        Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Já Cá', 'phone' => '923444000', 'type' => 'pessoa_fisica']);

        $this->postJson($this->url('/registar'), ['nome' => 'Outra', 'telefone' => '923444000', 'password' => 'abcd', 'password_confirmation' => 'abcd'])
            ->assertStatus(422)->assertJsonValidationErrors('telefone');
    }
}
