<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

/**
 * `empresas:nif` (26/09/2026): o número do BI chega num ficheiro privado — não
 * vai no endereço de manutenção — e um NIF vazio já não apaga o da empresa.
 */
class NifDaEmpresaComandoTest extends TenantTestCase
{
    public function test_um_nif_vazio_nao_apaga_o_da_empresa(): void
    {
        $antes = $this->tenant->nif;

        Artisan::call('empresas:nif', ['--tenant' => $this->tenant->id, '--aplicar' => true]);

        $this->assertStringContainsString('Indique o NIF novo', Artisan::output());
        $this->assertSame($antes, $this->tenant->fresh()->nif);
    }

    public function test_o_bi_de_um_empresario_em_nome_individual_grava_se_por_ficheiro(): void
    {
        @mkdir(storage_path('app/privado'), 0775, true);
        $nome = 'nif-' . uniqid() . '.json';
        file_put_contents(storage_path('app/privado/' . $nome), json_encode(['nif' => '006378572BA048']));

        Artisan::call('empresas:nif', ['--tenant' => $this->tenant->id, '--ficheiro' => $nome, '--aplicar' => true]);

        $this->assertSame('006378572BA048', $this->tenant->fresh()->nif);
        $this->assertFileDoesNotExist(storage_path('app/privado/' . $nome), 'o dado pessoal não fica no servidor');
    }

    public function test_um_bi_mal_escrito_e_recusado(): void
    {
        $antes = $this->tenant->nif;

        Artisan::call('empresas:nif', ['--tenant' => $this->tenant->id, '--nif' => '0063785572BA048', '--aplicar' => true]);

        $this->assertStringContainsString('nove dígitos, duas letras e três dígitos', Artisan::output());
        $this->assertSame($antes, $this->tenant->fresh()->nif);
    }
}
