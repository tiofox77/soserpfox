<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\Invoicing\EmissorFiscal;
use Tests\TenantTestCase;

/**
 * O emissor fiscal: numerar, selar, comunicar.
 *
 * O que interessa provar é que os erros NÃO sobem. Um documento já gravado,
 * com número atribuído, não pode ser desfeito porque a AGT não respondeu — a
 * venda aconteceu, o cliente levou o talão, e o número já saiu da série.
 */
class EmissorFiscalTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function emissor(): EmissorFiscal
    {
        return app(EmissorFiscal::class);
    }

    /** Um documento que rebenta em tudo o que lhe pedirem. */
    private function documentoQueRebenta(): object
    {
        return new class extends \Illuminate\Database\Eloquent\Model {
            public $incrementing = false;
            protected $keyType = 'string';
            protected $attributes = ['id' => 'x'];

            public function generateHash()
            {
                throw new \RuntimeException('hash rebentou');
            }

            public function submitToAGT()
            {
                throw new \RuntimeException('AGT em baixo');
            }

            public function fresh($with = [])
            {
                return $this;
            }
        };
    }

    public function test_numerar_da_serie_e_numero(): void
    {
        $r = $this->emissor()->numerar($this->tenant->id, 'pos');

        $this->assertNotNull($r['serie']);
        $this->assertNotEmpty($r['numero']);
    }

    public function test_dois_numeros_seguidos_nunca_se_repetem(): void
    {
        // Dois documentos com o mesmo número seriam recusados pela AGT e
        // impossíveis de explicar ao fisco.
        $um = $this->emissor()->numerar($this->tenant->id, 'pos')['numero'];
        $dois = $this->emissor()->numerar($this->tenant->id, 'pos')['numero'];

        $this->assertNotSame($um, $dois);
    }

    public function test_um_hash_que_rebenta_nao_derruba_a_emissao(): void
    {
        $this->emissor()->assinar($this->documentoQueRebenta());

        // Chegar aqui é o teste: não subiu excepção nenhuma.
        $this->assertTrue(true);
    }

    public function test_a_agt_em_baixo_nao_derruba_a_emissao(): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['agt_auto_submit' => true]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $this->emissor()->selar($this->documentoQueRebenta(), $this->tenant->id);

        $this->assertTrue(true);
    }

    public function test_sem_comunicacao_automatica_nao_se_comunica(): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['agt_auto_submit' => false]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $tocado = false;

        $doc = new class extends \Illuminate\Database\Eloquent\Model {
            public static $chamou = false;
            public $incrementing = false;
            protected $keyType = 'string';
            protected $attributes = ['id' => 'y'];

            public function submitToAGT()
            {
                static::$chamou = true;
            }

            public function fresh($with = [])
            {
                return $this;
            }
        };

        $this->emissor()->comunicar($doc, $this->tenant->id);

        $this->assertFalse($doc::$chamou, 'a empresa não pediu comunicação automática');
    }
}
