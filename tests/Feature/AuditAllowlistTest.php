<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * A ALLOWLIST da trilha de auditoria — quem lá está e quem lá falta.
 *
 * Estes ensaios não testam o gravador nem o comportamento (isso é o
 * AuditTrailTest e o AuditCoberturaTest) — testam a LISTA: que continua a
 * cobrir todas as áreas, e que o que lá está consegue mesmo escrever.
 *
 * A regressão que isto apanha é a silenciosa: alguém acrescenta um modelo à
 * lista, o `class_exists` no AppServiceProvider descarta-o em silêncio, e o
 * ficheiro passa a prometer uma cobertura que não existe.
 */
class AuditAllowlistTest extends TenantTestCase
{
    /** Todas as classes da lista existem — senão o registo é descartado em silêncio. */
    public function test_a_lista_nao_tem_classes_fantasma(): void
    {
        $fantasmas = array_values(array_filter(
            config('audit.models'),
            fn ($classe) => ! class_exists($classe)
        ));

        $this->assertSame([], $fantasmas, 'o AppServiceProvider descarta estas em silêncio');
    }

    /** Nada se audita duas vezes: um observer duplicado grava a linha a dobrar. */
    public function test_a_lista_nao_tem_repetidos(): void
    {
        $modelos = config('audit.models');

        $this->assertSame(count($modelos), count(array_unique($modelos)));
    }

    /**
     * Auditar o próprio registo de auditoria é recursão.
     *
     * @test
     */
    public function a_trilha_nao_se_audita_a_si_propria(): void
    {
        $this->assertNotContains(AuditTrail::class, config('audit.models'));
        $this->assertNotContains(\App\Models\ErroDoSistema::class, config('audit.models'));
    }

    /**
     * TODAS AS ÁREAS DE NEGÓCIO ESTÃO COBERTAS.
     *
     * Este é o ensaio que dá sentido ao alargamento: se alguém criar um módulo
     * novo e não o puser na trilha, isto acusa — em vez de se descobrir quando
     * for preciso saber quem mexeu.
     */
    public function test_todas_as_areas_de_negocio_estao_cobertas(): void
    {
        $areas = [
            'Invoicing', 'Accounting', 'HR', 'Hotel', 'Salon', 'Workshop',
            'Events', 'CRM', 'Treasury', 'Restaurant', 'Compras', 'Projetos',
            'Support', 'AGT',
        ];

        $lista = config('audit.models');

        foreach ($areas as $area) {
            $daArea = array_filter($lista, fn ($c) => str_starts_with($c, 'App\\Models\\'.$area.'\\'));

            $this->assertNotEmpty($daArea, "a área {$area} não tem um único modelo auditado");
        }
    }

    /**
     * O que está na lista consegue mesmo escrever.
     *
     * A trilha exige empresa (`audit_trail.tenant_id` é NOT NULL). Um modelo
     * sem `tenant_id` e sem pai por onde subir cai na empresa activa — e fora
     * de um pedido web isso é null, e a linha perde-se sem aviso. Aqui
     * confirma-se que cada modelo da lista tem um caminho para a empresa.
     */
    public function test_cada_modelo_da_lista_tem_caminho_para_a_empresa(): void
    {
        // Os nomes das relações que o AuditRecorder tenta para subir ao pai.
        $pais = [
            'invoice', 'creditNote', 'debitNote', 'proforma', 'guide', 'document', 'order',
            'purchaseInvoice', 'transportGuide', 'quote', 'salesQuote',
            'encomenda', 'requisicao', 'projeto', 'tarefa',
            'workOrder', 'payroll', 'reservation', 'appointment', 'event', 'team',
            'bankReconciliation', 'reconciliation', 'ticket', 'featureRequest',
            'equipmentSet', 'import', 'stockCount', 'recipe',
        ];

        $orfaos = [];

        foreach (config('audit.models') as $classe) {
            // O Tenant é a própria empresa — o recorder trata-o como caso próprio.
            if ($classe === Tenant::class) {
                continue;
            }

            /** @var Model $modelo */
            $modelo = new $classe;

            if (Schema::hasColumn($modelo->getTable(), 'tenant_id')) {
                continue;
            }

            $temPai = false;
            foreach ($pais as $relacao) {
                if (method_exists($modelo, $relacao)) {
                    $temPai = true;
                    break;
                }
            }

            if (! $temPai) {
                $orfaos[] = $classe;
            }
        }

        $this->assertSame([], $orfaos,
            'sem tenant_id nem pai, a linha cai na empresa activa e perde-se fora da web');
    }

    /**
     * Mudar a empresa deixa rasto.
     *
     * O Tenant não tem coluna `tenant_id` — é ele a empresa. Sem o caso
     * próprio no recorder, mudar o NIF ou o regime fiscal de uma empresa não
     * deixava rasto nenhum, que é das alterações com mais consequência que há.
     *
     * @test
     */
    public function mudar_a_empresa_deixa_rasto(): void
    {
        AuditTrail::where('tenant_id', $this->tenant->id)->delete();

        $this->tenant->update(['name' => 'Nome Trocado, Lda']);

        $linha = AuditTrail::where('tenant_id', $this->tenant->id)
            ->where('auditable_type', Tenant::class)
            ->where('auditable_id', $this->tenant->id)
            ->latest('id')->first();

        $this->assertNotNull($linha, 'alterar a empresa tem de deixar linha na trilha');
        $this->assertSame('updated', $linha->event);
        $this->assertSame('Nome Trocado, Lda', $linha->new_values['name'] ?? null);
    }
}
