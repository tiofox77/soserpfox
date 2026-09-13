<?php

namespace Tests\Feature;

use App\Models\Salon\Appointment;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * Os painéis dos módulos: tesouraria, RH, salão, oficina e hotel.
 *
 * A pergunta foi «estão a funcionar sem bugs». Não estavam. O que este
 * ficheiro guarda é o que se corrigiu, com o motivo à frente de cada caso.
 */
class PaineisDosModulosTest extends TenantTestCase
{
    /**
     * NENHUM PAINEL EM BLADE DESENHA GRÁFICOS — os painéis são React.
     *
     * Eram nove painéis em Blade com gráficos em <canvas>, todos com o mesmo
     * defeito: pela barra lateral (a navegação do Livewire) o gráfico ficava em
     * branco, porque o componente trocava o <canvas> depois do desenho. Nasceu
     * para isso um sítio único de «quando desenhar» (`partials/graficos`), com a
     * escada de desenhos. Os painéis passaram a React — lá os gráficos são SVG e
     * não há canvas nenhum a trocar — e, sem Livewire, o partial saiu também.
     *
     * Fica o travão: um painel novo em Blade a desenhar num <canvas> é um passo
     * para trás, e voltava a precisar de tudo o que se apagou.
     *
     * @test
     */
    public function nenhum_painel_em_blade_desenha_graficos(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/partials/graficos.blade.php'));

        $comCanvas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $v) {
            if (str_ends_with($v->getFilename(), '.blade.php') && preg_match('/partials\.graficos|new Chart\(/', file_get_contents($v->getPathname()))) {
                $comCanvas[] = $v->getFilename();
            }
        }

        $this->assertSame([], $comCanvas, 'os gráficos dos painéis são componentes React');
    }

    /**
     * O CHART.JS É DA CASA E NÃO DE UM CDN.
     *
     * A tesouraria puxava-o de `cdn.jsdelivr.net`. A versão on-premise corre
     * sem internet, e quando o CDN falha o painel fica com um quadrado branco
     * sem aviso nenhum.
     *
     * A LISTA FIXA DESAPARECEU, e de propósito. Era a enumeração dos painéis
     * que ainda tinham Blade, e cada módulo que passava a React obrigava a
     * apagar lá um nome — até ao dia em que ficou vazia e o ensaio passou a
     * não medir nada. Agora varre-se o que existe: o que interessa não é
     * quais os painéis, é que NENHUM deles vá buscar um script à internet.
     *
     * O varrimento apanhou logo um que a lista não tinha: o painel dos
     * equipamentos dos eventos.
     *
     * @test
     */
    public function nenhum_painel_vai_buscar_o_chart_js_a_um_cdn(): void
    {
        /*
         * JÁ NÃO HÁ PAINÉIS EM BLADE — o último, o do superadmin, passou a
         * React. O varrimento alargou-se a TODAS as vistas e a todo o código
         * dos ecrãs: o que interessa continua a ser que nada vá buscar o
         * Chart.js à internet, e um `assertNotEmpty` sobre uma pasta vazia
         * deixava de medir o que quer que fosse.
         */
        $paineis = array_merge(
            glob(resource_path('views/*.blade.php')),
            glob(resource_path('views/*/*.blade.php')),
            glob(resource_path('views/*/*/*.blade.php')),
            glob(resource_path('views/*/*/*/*.blade.php')),
            glob(resource_path('js/ecras/*/*.tsx')),
            glob(resource_path('js/ui/*.tsx')),
        );

        $this->assertNotEmpty($paineis, 'o varrimento tem de encontrar ficheiros');

        // O que se procura é o CHART.JS num CDN. Alargado a todas as vistas, o
        // varrimento apanha a página pública, que carrega o Alpine do jsDelivr —
        // é outra conversa (a página pública só abre com internet).
        foreach ($paineis as $vista) {
            $this->assertDoesNotMatchRegularExpression(
                '#(cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com|unpkg\.com)/[^"\']*chart#i',
                file_get_contents($vista),
                basename(dirname($vista)).'/'.basename($vista).': o Chart.js tem de ser o local'
            );
        }
    }

    /**
     * UMA MARCAÇÃO DE OUTRA EMPRESA NÃO SE MEXE DAQUI.
     *
     * As acções rápidas do painel do salão faziam `Appointment::find($id)` com
     * o id vindo do browser — e os modelos do salão não têm escopo global de
     * empresa. Bastava chamar `quickComplete` com o id de outra empresa para
     * dar por concluída e paga a marcação de um concorrente.
     *
     * O PAINEL É AGORA REACT e o componente Livewire desapareceu, mas o botão
     * continua a existir: o ecrã manda o id para a porta do estado. É essa que
     * se prende aqui — e ela procura por `forTenant()`, pelo que a marcação
     * alheia nem existe.
     *
     * @test
     */
    public function as_accoes_rapidas_do_salao_nao_tocam_noutra_empresa(): void
    {
        $outra = Tenant::create([
            'name'      => 'Salão do Lado',
            'email'     => uniqid() . '@exemplo.ao',
            'nif'       => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);

        $clienteAlheio = \App\Models\Client::create([
            'tenant_id' => $outra->id,
            'name'      => 'Cliente do Lado',
        ]);

        $profAlheio = \App\Models\Salon\Professional::create([
            'tenant_id' => $outra->id,
            'name'      => 'Profissional do Lado',
        ]);

        $alheia = Appointment::create([
            'tenant_id'  => $outra->id,
            'client_id'       => $clienteAlheio->id,
            'professional_id' => $profAlheio->id,
            'date'       => today(),
            'start_time' => '10:00',
            'end_time'   => '11:00',
            'status'     => 'scheduled',
            'subtotal'   => 5000,
            'total'      => 5000,
        ]);

        $this->comModulo('salon');
        $this->comPermissoes('salon.dashboard.view', 'salon.appointments.edit');

        $this->postJson("/api/v1/invoicing/react/salao/marcacoes/{$alheia->id}/estado", [
            'estado' => 'completed',
        ])->assertNotFound();

        $this->assertSame('scheduled', $alheia->fresh()->status,
            'a marcação é de outra empresa: não se toca');
    }

    /**
     * O PAINEL DA OFICINA INCLUI O DIA DE HOJE.
     *
     * `received_at` é DATETIME e o intervalo vinha em datas secas — o limite
     * de cima ficava na meia-noite do último dia. Resultado: uma oficina não
     * via no painel nada do que entrou hoje.
     *
     * @test
     */
    public function o_painel_da_oficina_conta_o_dia_inteiro(): void
    {
        /*
         * ISTO LIA O CÓDIGO-FONTE do componente Livewire à procura de
         * `endOfDay()`. Com o painel em React o ficheiro deixou de existir — e
         * um ensaio que procura texto num ficheiro morre com o ficheiro sem
         * dizer nada sobre o que o utilizador vê. Agora pergunta-se ao painel.
         */
        $this->comModulo('oficina');
        $this->comPermissoes('workshop.dashboard.view');

        $viatura = \App\Models\Workshop\Vehicle::create([
            'plate' => 'LD-88-88-ZZ', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux',
        ]);

        \App\Models\Workshop\WorkOrder::create([
            'order_number' => 'OS-' . substr(uniqid(), -6),
            'vehicle_id' => $viatura->id,
            // Às três da tarde de hoje: com a data seca no limite de cima, o
            // «até hoje» acabava à meia-noite e esta ordem não contava.
            'received_at' => now()->startOfDay()->addHours(15),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
        ]);

        $hoje = now()->toDateString();

        $this->getJson("/api/v1/invoicing/react/oficina/painel?de={$hoje}&ate={$hoje}")
            ->assertOk()
            ->assertJsonPath('cartoes.ordens', 1);
    }

    /**
     * AS COMPRAS EM RASCUNHO NÃO SÃO DÍVIDA.
     *
     * As vendas já só contavam as definitivas; as compras entravam todas, e um
     * rascunho de factura de fornecedor inflava o «Comprado» e o «A Pagar».
     *
     * @test
     */
    public function a_tesouraria_ignora_compras_em_rascunho(): void
    {
        /*
         * ISTO LIA O CÓDIGO-FONTE do componente Livewire à procura de uma
         * linha. Com o painel em React o ficheiro deixou de existir — e um
         * ensaio que procura texto num ficheiro morre com o ficheiro sem
         * dizer nada sobre o que o utilizador vê. Agora pergunta-se ao
         * painel, que é o que interessa.
         */
        $this->comModulo('treasury');
        $this->comPermissoes('treasury.transactions.view');

        $fornecedor = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fornecedor dos ensaios',
        ]);

        $compra = fn (string $estado, float $total) => \App\Models\Invoicing\PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $fornecedor->id,
            'invoice_number' => 'FC-' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->toDateString(),
            'status' => $estado,
            'subtotal' => $total, 'tax_amount' => 0, 'total' => $total, 'paid_amount' => 0,
        ]);

        $compra('pending', 8000);
        $compra('draft', 5000);
        $compra('cancelled', 3000);

        $painel = $this->getJson('/api/v1/invoicing/react/tesouraria/painel?periodo=year')->assertOk();

        $this->assertEqualsWithDelta(8000, $painel->json('facturacao.comprado'), 0.01,
            'um rascunho de compra não é dinheiro comprado');
        $this->assertEqualsWithDelta(8000, $painel->json('facturacao.a_pagar'), 0.01,
            'nem dinheiro a pagar');

        // E A LINHA DOS 7 DIAS é uma consulta agrupada, não catorze dentro de
        // um ciclo: sete pontos, com os dias vazios a zero.
        $this->assertCount(7, $painel->json('grafico.dias'));
        $this->assertCount(7, $painel->json('grafico.entradas'));
    }

    /**
     * O NOME DO MÊS SEGUE A LÍNGUA DE QUEM VÊ, e não um 'pt_BR' escrito à mão.
     *
     * O painel do RH era Livewire e passou para React; a regra mudou de
     * ficheiro mas não de valor — hoje mede-se nas portas que devolvem nomes
     * de meses e de dias ao ecrã.
     *
     * @test
     */
    public function o_rh_nao_fala_brasileiro(): void
    {
        $portas = [
            'Http/Controllers/Api/Hr/PainelApiController.php',
            'Http/Controllers/Api/Hr/RelatoriosApiController.php',
            'Http/Controllers/Api/Hr/FolhaApiController.php',
        ];

        foreach ($portas as $porta) {
            $fonte = file_get_contents(app_path($porta));

            $this->assertStringNotContainsString("locale('pt_BR')", $fonte, $porta);
            $this->assertStringContainsString("locale(app()->getLocale())", $fonte, $porta);
        }
    }
}