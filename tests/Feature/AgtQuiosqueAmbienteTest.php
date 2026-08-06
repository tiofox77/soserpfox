<?php

namespace Tests\Feature;

use App\Helpers\AGTHelper;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\SoftwareSetting;
use App\Services\AGT\QRCodeService;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * O QR e o rodapé impressos seguem o ambiente da empresa.
 *
 * O URL do quiosque estava fixo no de produção. Um documento emitido em
 * homologação levava impresso um QR a apontar para o quiosque real, onde ele
 * não existe — quem o lesse via "factura não encontrada" e concluía que a
 * submissão tinha falhado, quando o problema era o endereço.
 *
 * E o talão lia o número de certificação directamente da definição única, por
 * isso dizia FE/324 (produção) em documentos de homologação.
 */
class AgtQuiosqueAmbienteTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();   // softwareSetting() guarda em cache por uma hora

        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_production', 'FE/324/AGT/2026', 'string');
        Cache::flush();
    }

    private function ambiente(string $ambiente): void
    {
        InvoicingSettings::forTenant($this->tenant->id)->update(['agt_environment' => $ambiente]);
        Cache::flush();
    }

    public function test_homologacao_usa_o_quiosque_de_homologacao(): void
    {
        $this->ambiente('sandbox');

        $this->assertSame(
            'https://quiosqueagt.hml.minfin.gov.ao',
            (new QRCodeService())->quiosqueBase($this->tenant->id)
        );
    }

    public function test_producao_usa_o_quiosque_real(): void
    {
        $this->ambiente('production');

        $this->assertSame(
            'https://quiosqueagt.minfin.gov.ao',
            (new QRCodeService())->quiosqueBase($this->tenant->id)
        );
    }

    public function test_os_dois_enderecos_sao_diferentes(): void
    {
        // A regressão que interessa travar: se voltarem a ser o mesmo, o QR de
        // homologação volta a apontar para o quiosque real.
        $q = new QRCodeService();

        $this->ambiente('sandbox');
        $hml = $q->quiosqueBase($this->tenant->id);

        $this->ambiente('production');
        $prd = $q->quiosqueBase($this->tenant->id);

        $this->assertNotSame($hml, $prd);
    }

    public function test_o_endereco_pode_mudar_sem_tocar_no_codigo(): void
    {
        // Para o dia em que a AGT o mudar.
        SoftwareSetting::set('invoicing', 'agt_kiosk_url_sandbox', 'https://outro.exemplo.ao/', 'string');
        Cache::flush();
        $this->ambiente('sandbox');

        $this->assertSame(
            'https://outro.exemplo.ao',
            (new QRCodeService())->quiosqueBase($this->tenant->id),
            'e sem a barra final, que duplicaria a do caminho'
        );
    }

    public function test_o_rodape_impresso_segue_o_ambiente(): void
    {
        $this->ambiente('sandbox');
        $this->assertSame('FE/351/AGT/2026', AGTHelper::softwareValidationNumber($this->tenant->id));

        $this->ambiente('production');
        $this->assertSame('FE/324/AGT/2026', AGTHelper::softwareValidationNumber($this->tenant->id));
    }

    public function test_sem_numero_do_ambiente_fica_o_valor_unico(): void
    {
        // Enquanto os dois números não estiverem separados — é o caso de
        // produção neste momento — tem de continuar a sair o valor de sempre,
        // em vez de um vazio no rodapé de um documento fiscal.
        SoftwareSetting::where('module', 'invoicing')
            ->whereIn('setting_key', ['saft_software_cert_sandbox', 'saft_software_cert_production'])
            ->delete();
        SoftwareSetting::set('invoicing', 'saft_software_cert', 'FE/324/AGT/2026', 'string');
        Cache::flush();

        $this->ambiente('sandbox');

        $this->assertSame('FE/324/AGT/2026', AGTHelper::softwareValidationNumber($this->tenant->id));
    }
}
