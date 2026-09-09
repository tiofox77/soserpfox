<?php

namespace Tests\Feature\Salon;

use App\Models\Salon\Appointment;
use App\Models\Salon\Professional;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\ServiceCategory;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O SALÃO DE UMA EMPRESA NÃO SE ALCANÇA DE OUTRA.
 *
 * O defeito estava provado desde antes: `quickComplete($id)` no painel fazia
 * `Appointment::find($id)` sem filtro, e bastava mandar o id da marcação de um
 * concorrente para a dar por concluída e paga. Foi corrigido à mão nesse
 * método — e ficaram os outros: vinte e três `find()` em oito componentes.
 *
 * Este ficheiro guarda a correcção de fundo: o escopo de empresa nos modelos.
 *
 * E GUARDA TAMBÉM O QUE O ESCOPO NÃO PODE PARTIR — a página pública de
 * marcação. Lá o SLUG é que escolhe a empresa, e quem a abre pode não ter
 * sessão nenhuma ou estar com outra empresa activa. Duas coisas ficaram
 * deliberadamente fora do escopo:
 *
 *  · `SalonSettings::getBySlug()` — senão a página do vizinho dava 404;
 *  · `generateUniqueSlug()` — o slug é a morada pública e tem de ser único no
 *    MUNDO, não por empresa. Com o escopo a filtrar, dois salões chegavam ao
 *    mesmo «joana» e o segundo roubava a morada do primeiro.
 */
class SalaoNaoAtravessaEmpresasTest extends TenantTestCase
{
    private Tenant $outra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('salon');

        $this->outra = Tenant::create([
            'name' => 'Salão do Lado',
            'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    private function alheio(string $classe, array $campos)
    {
        return $classe::withoutGlobalScopes()->create($campos + ['tenant_id' => $this->outra->id]);
    }

    private function marcacaoAlheia(): Appointment
    {
        $cliente = \App\Models\Client::create([
            'tenant_id' => $this->outra->id,
            'name' => 'Cliente do Lado',
        ]);

        $profissional = $this->alheio(Professional::class, ['name' => 'Profissional do Lado']);

        return $this->alheio(Appointment::class, [
            'client_id' => $cliente->id,
            'professional_id' => $profissional->id,
            'date' => today(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'subtotal' => 5000,
            'total' => 5000,
        ]);
    }

    /* ─── O escopo ────────────────────────────────────────────────────── */

    public function test_uma_marcacao_de_outra_empresa_nao_se_le(): void
    {
        $alheia = $this->marcacaoAlheia();

        $this->assertNull(Appointment::find($alheia->id));
        $this->assertSame(0, Appointment::count());
        $this->assertNotNull(Appointment::withoutGlobalScopes()->find($alheia->id), 'o escopo esconde, não apaga');
    }

    /**
     * O CASO QUE JÁ ERA CONHECIDO — e que agora fica fechado no modelo.
     *
     * `quickComplete($id)` recebia o id do browser e dava por concluída e paga
     * a marcação de outra empresa.
     */
    public function test_as_accoes_rapidas_do_painel_nao_tocam_noutra_empresa(): void
    {
        $alheia = $this->marcacaoAlheia();

        $painel = new \App\Livewire\Salon\Dashboard();
        $painel->quickComplete($alheia->id);

        $this->assertSame('scheduled', $alheia->fresh()->status, 'a marcação é de outra empresa: não se toca');
    }

    public function test_um_profissional_e_uma_categoria_de_outra_empresa_tambem_nao(): void
    {
        $profissional = $this->alheio(Professional::class, ['name' => 'Profissional do Lado']);
        $categoria = $this->alheio(ServiceCategory::class, ['name' => 'Categoria do Lado', 'slug' => 'cat-lado']);

        $this->assertNull(Professional::find($profissional->id));
        $this->assertNull(ServiceCategory::find($categoria->id));
    }

    /**
     * A PROPRIEDADE PÚBLICA DO LIVEWIRE, outra vez.
     *
     * `editingId` vem do browser: sem escopo, `save()` reescrevia a ficha de um
     * profissional de outro salão sem passar pelo `edit()` que filtra.
     */
    public function test_gravar_um_profissional_com_o_id_de_outra_empresa_nao_lhe_toca(): void
    {
        $alheio = $this->alheio(Professional::class, ['name' => 'Profissional do Lado']);

        $componente = Livewire::test(\App\Livewire\Salon\ProfessionalManagement::class)
            ->set('editingId', $alheio->id)
            ->set('name', 'Roubado');

        try {
            $componente->call('save');
        } catch (\Throwable $e) {
            // Um erro é aceitável; escrever na casa do vizinho não é.
        }

        $this->assertSame('Profissional do Lado', $alheio->fresh()->name);
    }

    public function test_o_que_e_desta_empresa_continua_a_ver_se(): void
    {
        $meu = Professional::create(['name' => 'Profissional da Casa']);

        $this->assertSame($this->tenant->id, $meu->tenant_id, 'o escopo preenche a empresa ao criar');
        $this->assertNotNull(Professional::find($meu->id));
    }

    /* ─── O que o escopo não pode partir ──────────────────────────────── */

    /**
     * A PÁGINA PÚBLICA ABRE MESMO COM OUTRA EMPRESA ACTIVA.
     *
     * É o caso que o escopo partiria em silêncio: quem está com a sua empresa
     * aberta e clica no link do salão do lado tem de ver o salão do lado.
     */
    public function test_a_pagina_publica_abre_com_outra_empresa_activa(): void
    {
        $definicoes = $this->alheio(SalonSettings::class, [
            'salon_name' => 'Salão do Lado',
            'booking_slug' => 'salao-do-lado-' . uniqid(),
            'online_booking_enabled' => true,
        ]);

        // Com sessão aberta NA MINHA empresa — que é o caso que partia.
        $this->assertNotNull(
            SalonSettings::getBySlug($definicoes->booking_slug),
            'a morada pública é do slug, não da empresa activa'
        );
    }

    /**
     * O SLUG É ÚNICO NO MUNDO, e não por empresa.
     *
     * Duas moradas públicas iguais é a segunda a levar as marcações da
     * primeira.
     */
    public function test_o_slug_publico_nao_se_repete_entre_empresas(): void
    {
        $this->alheio(SalonSettings::class, [
            'salon_name' => 'Joana',
            'booking_slug' => 'joana',
            'online_booking_enabled' => true,
        ]);

        $meu = SalonSettings::generateUniqueSlug('Joana');

        $this->assertNotSame('joana', $meu, 'o slug de outra empresa também conta');
        $this->assertSame('joana-1', $meu);
    }

    /* ─── As dez rotas ────────────────────────────────────────────────── */

    public function test_cada_rota_do_salao_pede_a_sua_permissao(): void
    {
        $rotas = [
            'salon.dashboard' => 'salon.dashboard.view',
            'salon.appointments' => 'salon.appointments.view',
            'salon.services' => 'salon.services.view',
            'salon.services.categories' => 'salon.categories.view',
            'salon.professionals' => 'salon.professionals.view',
            'salon.clients' => 'salon.clients.view',
            'salon.products' => 'salon.products.view',
            'salon.pos' => 'salon.pos.access',
            'salon.reports.time' => 'salon.reports.view',
            'salon.settings' => 'salon.settings.view',
        ];

        foreach (array_keys($rotas) as $rota) {
            $this->get(route($rota))->assertForbidden();
        }

        $this->comPermissoes(...array_values($rotas));

        foreach (array_keys($rotas) as $rota) {
            // O POS do salão manda abrir turno antes de vender — é o
            // comportamento de sempre, e não a guarda a recusar. O que aqui se
            // mede é que a permissão deixou passar.
            if ($rota === 'salon.pos') {
                $this->get(route($rota))->assertRedirect(route('invoicing.pos.shifts'));

                continue;
            }

            $this->get(route($rota))->assertOk();
        }
    }
}
