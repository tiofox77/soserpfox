<?php

namespace Tests\Feature\Seguranca;

use App\Models\HR\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * O BI, O PASSAPORTE E O REGISTO CRIMINAL NÃO TÊM ENDEREÇO PÚBLICO.
 *
 * Viviam em /storage/tenants/{empresa}/employees/{id}/documentos/bi.pdf —
 * adivinhável e aberto a quem não tinha sessão (auditoria de 2026-09-13). O
 * public/.htaccess recusa esse caminho; a ficha aponta para a API, que pede
 * `employees.view`.
 */
class DocumentosDoRhNaoSaoPublicosTest extends TenantTestCase
{
    public function test_o_documento_abre_pela_api_com_permissao_e_nunca_pelo_storage(): void
    {
        Storage::fake('public');
        $this->comModulo('rh');
        $this->comPermissoes('employees.view', 'employees.edit');

        $e = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_number' => 'SEG-' . uniqid(),
            'first_name' => 'Ana', 'last_name' => 'Doc', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active',
        ]);

        $this->post('/api/v1/invoicing/react/rh/funcionarios/' . $e->id . '/documentos/bi', [
            'ficheiro' => UploadedFile::fake()->create('bi.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $url = collect($this->getJson('/api/v1/invoicing/react/rh/funcionarios/' . $e->id)->json('documento.documentos'))
            ->firstWhere('chave', 'bi')['url'];

        $this->assertStringNotContainsString('/storage/', $url, 'a ficha não aponta para o endereço público');
        $this->get($url)->assertOk();

        // Sem a permissão de ver funcionários, não abre.
        $outro = \App\Models\User::create(['name' => 'Sem RH', 'email' => 'semrh' . uniqid() . '@empresa.ao', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true]);
        $outro->tenants()->syncWithoutDetaching([$this->tenant->id]);
        $this->actingAs($outro)->get($url, ['Accept' => 'application/json'])->assertForbidden();

        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('RewriteRule ^storage/tenants/[0-9]+/(employees/[0-9]+/documentos|rh)/ - [NC,F,L]', $htaccess);
    }
}
