<?php

namespace Tests\Feature\Plataforma;

use App\Models\SmtpSetting;
use App\Models\SoftwareSetting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * AS DEFINIÇÕES DA PLATAFORMA — correio, WhatsApp, chaves do SAF-T, sistema e
 * software. Os ecrãs passaram a React; o que se prova aqui é a API.
 */
class DefinicoesDaPlataformaTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react';

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dono = User::create(['name' => 'Dono', 'email' => 'dono_'.uniqid().'@exemplo.ao', 'password' => bcrypt('x')]);
        $this->dono->forceFill(['is_super_admin' => true])->save();
    }

    public static function moradas(): array
    {
        return [
            'correio' => ['/correio'],
            'sms' => ['/sms'],
            'whatsapp' => ['/whatsapp'],
            'chaves do SAF-T' => ['/chaves-saft'],
            'sistema' => ['/sistema'],
            'software' => ['/software'],
        ];
    }

    /** @dataProvider moradas */
    public function test_um_utilizador_de_empresa_nao_entra(string $morada): void
    {
        $this->getJson(self::API.$morada)->assertForbidden();
    }

    /** @dataProvider moradas */
    public function test_o_dono_entra(string $morada): void
    {
        Storage::fake('local');

        $this->actingAs($this->dono)->getJson(self::API.$morada)->assertOk();
    }

    /* ─── Correio ─────────────────────────────────────────────────────── */

    private function smtp(array $troca = []): SmtpSetting
    {
        return SmtpSetting::create(array_merge([
            'tenant_id' => null, 'host' => 'smtp.exemplo.ao', 'port' => 587, 'username' => 'u',
            'password' => 'segredo-antigo', 'encryption' => 'tls', 'from_email' => 'a@exemplo.ao',
            'from_name' => 'Plataforma', 'is_default' => true, 'is_active' => true,
        ], $troca));
    }

    /**
     * EDITAR OBRIGAVA A REESCREVER A PALAVRA-PASSE: o formulário esvaziava-a e
     * a validação exigia-a. Vazio é «manter».
     */
    public function test_editar_o_servidor_sem_palavra_passe_mantem_a_que_estava(): void
    {
        $s = $this->smtp();

        $this->actingAs($this->dono)->putJson(self::API."/correio/{$s->id}", [
            'host' => 'smtp.novo.ao', 'port' => 465, 'username' => 'u', 'password' => '',
            'encryption' => 'ssl', 'from_email' => 'a@exemplo.ao', 'from_name' => 'Plataforma',
            'is_default' => true, 'is_active' => true,
        ])->assertOk();

        $s->refresh();
        $this->assertSame('smtp.novo.ao', $s->host);
        $this->assertSame('segredo-antigo', $s->password);
    }

    public function test_criar_um_servidor_exige_a_palavra_passe(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/correio', [
            'host' => 'smtp.exemplo.ao', 'port' => 587, 'username' => 'u', 'password' => '',
            'encryption' => 'tls', 'from_email' => 'a@exemplo.ao', 'from_name' => 'X',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_palavra_passe_nunca_vai_para_o_browser(): void
    {
        $s = $this->smtp();

        $resposta = $this->actingAs($this->dono)->getJson(self::API."/correio/{$s->id}")->assertOk();

        $this->assertStringNotContainsString('segredo-antigo', $resposta->getContent());
        $this->assertTrue($resposta->json('ficha.tem_password'));
    }

    /**
     * SÓ UMA PADRÃO NO MESMO ÂMBITO — e a de uma empresa não é tocada quando a
     * da plataforma muda.
     */
    public function test_so_um_servidor_padrao_por_ambito(): void
    {
        $antiga = $this->smtp();
        $daEmpresa = $this->smtp(['tenant_id' => $this->tenant->id, 'host' => 'smtp.empresa.ao']);
        $nova = $this->smtp(['host' => 'smtp.nova.ao', 'is_default' => false]);

        $this->actingAs($this->dono)->postJson(self::API."/correio/{$nova->id}/padrao")->assertOk();

        $this->assertFalse((bool) $antiga->fresh()->is_default);
        $this->assertTrue((bool) $nova->fresh()->is_default);
        $this->assertTrue((bool) $daEmpresa->fresh()->is_default, 'a padrão da empresa é da empresa');
    }

    public function test_o_unico_servidor_activo_da_plataforma_nao_se_apaga(): void
    {
        $s = $this->smtp();

        $this->actingAs($this->dono)->deleteJson(self::API."/correio/{$s->id}")->assertStatus(422);

        $this->assertNotNull($s->fresh());
    }

    /* ─── WhatsApp ────────────────────────────────────────────────────── */

    /** O TOKEN DA TWILIO viajava para a página numa propriedade pública. */
    public function test_o_token_da_twilio_nao_vai_para_o_browser_e_vazio_mantem_o(): void
    {
        WhatsAppSetting::getSettings()->update([
            'twilio_account_sid' => 'AC123', 'twilio_auth_token' => 'token-secreto-twilio',
            'whatsapp_from_number' => '+14155238886',
        ]);

        $resposta = $this->actingAs($this->dono)->getJson(self::API.'/whatsapp')->assertOk();

        $this->assertStringNotContainsString('token-secreto-twilio', $resposta->getContent());
        $this->assertTrue($resposta->json('configuracao.token_guardado'));

        $this->actingAs($this->dono)->putJson(self::API.'/whatsapp', [
            'twilio_account_sid' => 'AC123', 'twilio_auth_token' => '', 'whatsapp_from_number' => '+14155238886',
            'is_enabled' => true, 'is_sandbox' => true, 'templates' => [], 'notification_settings' => ['payslip_ready' => true],
        ])->assertOk();

        $s = WhatsAppSetting::getSettings()->fresh();
        $this->assertSame('token-secreto-twilio', $s->twilio_auth_token);
        $this->assertTrue($s->notification_settings['payslip_ready']);
    }

    public function test_ligar_o_whatsapp_sem_credenciais_e_recusado(): void
    {
        WhatsAppSetting::getSettings()->update(['twilio_account_sid' => null, 'twilio_auth_token' => null]);

        $this->actingAs($this->dono)->putJson(self::API.'/whatsapp', [
            'is_enabled' => true, 'is_sandbox' => true, 'templates' => [], 'notification_settings' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('is_enabled');
    }

    /* ─── Chaves do SAF-T ─────────────────────────────────────────────── */

    public function test_gerar_as_chaves_e_depois_nao_as_gerar_por_cima(): void
    {
        Storage::fake('local');

        $this->actingAs($this->dono)->postJson(self::API.'/chaves-saft/gerar')->assertCreated();

        Storage::disk('local')->assertExists(['saft/private_key.pem', 'saft/public_key.pem', 'saft/metadata.json']);

        // GERAR QUANDO JÁ EXISTEM era regenerar sem cópia nenhuma.
        $this->actingAs($this->dono)->postJson(self::API.'/chaves-saft/gerar')->assertStatus(422);
    }

    public function test_regenerar_pede_a_palavra_e_guarda_a_copia(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('saft/private_key.pem', 'privada-antiga');
        Storage::disk('local')->put('saft/public_key.pem', 'publica-antiga');

        $this->actingAs($this->dono)->postJson(self::API.'/chaves-saft/regenerar', ['confirmacao' => 'sim'])
            ->assertStatus(422)->assertJsonValidationErrors('confirmacao');

        $this->assertSame('privada-antiga', Storage::disk('local')->get('saft/private_key.pem'), 'sem a palavra, nada muda');

        $this->actingAs($this->dono)->postJson(self::API.'/chaves-saft/regenerar', ['confirmacao' => 'REGENERAR'])->assertOk();

        $copias = Storage::disk('local')->directories('saft/backups');
        $this->assertCount(1, $copias);
        $this->assertSame('privada-antiga', Storage::disk('local')->get($copias[0].'/private_key.pem'));
        $this->assertNotSame('privada-antiga', Storage::disk('local')->get('saft/private_key.pem'));
    }

    public function test_a_chave_publica_nao_vai_inteira_para_o_ecra(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('saft/public_key.pem', '-----BEGIN PUBLIC KEY-----conteudo-----END PUBLIC KEY-----');
        Storage::disk('local')->put('saft/private_key.pem', '-----BEGIN PRIVATE KEY-----segredo-----END PRIVATE KEY-----');

        $resposta = $this->actingAs($this->dono)->getJson(self::API.'/chaves-saft')->assertOk();

        $this->assertStringNotContainsString('BEGIN', $resposta->getContent());
        $this->assertNotEmpty($resposta->json('publica.impressao'));
    }

    public function test_a_descarga_da_chave_privada_e_so_do_dono(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('saft/private_key.pem', 'segredo');

        $this->get('/superadmin/saft-configuration/descarregar/privada/pem')->assertForbidden();
        $this->actingAs($this->dono)->get('/superadmin/saft-configuration/descarregar/privada/pem')->assertOk();
        $this->actingAs($this->dono)->get('/superadmin/saft-configuration/descarregar/inventada/pem')->assertNotFound();
    }

    /* ─── Sistema ─────────────────────────────────────────────────────── */

    public function test_as_definicoes_do_sistema_validam_antes_de_gravar(): void
    {
        $this->actingAs($this->dono)->putJson(self::API.'/sistema/geral', [
            'app_name' => 'SOS ERP', 'contact_email' => 'sem-arroba', 'app_url' => 'nao-e-endereco',
        ])->assertStatus(422)->assertJsonValidationErrors(['contact_email', 'app_url']);
    }

    /** A avaliação sem o número de avaliações é um JSON-LD que o Google recusa. */
    public function test_a_avaliacao_e_o_numero_de_avaliacoes_vao_juntos(): void
    {
        $this->actingAs($this->dono)->putJson(self::API.'/sistema/schema', ['schema_rating_value' => 4.8])
            ->assertStatus(422)->assertJsonValidationErrors('schema_review_count');
    }

    public function test_os_interruptores_gravam_se_como_texto_e_os_sem_efeito_sao_ditos(): void
    {
        $this->actingAs($this->dono)->putJson(self::API.'/sistema/funcionalidades', [
            'enable_registration' => false, 'maintenance_mode' => true,
        ])->assertOk();

        $this->assertSame('false', SystemSetting::get('enable_registration'));
        $this->assertSame('true', SystemSetting::get('maintenance_mode'));

        $json = $this->actingAs($this->dono)->getJson(self::API.'/sistema')->assertOk()->json();

        $this->assertContains('maintenance_mode', $json['sem_efeito']);
        $this->assertFalse($json['valores']['enable_registration']);
    }

    public function test_trocar_o_logotipo_apaga_o_antigo_so_depois_de_gravar_o_novo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/antigo.png', 'x');
        SystemSetting::set('app_logo', 'settings/antigo.png');

        $this->actingAs($this->dono)->postJson(self::API.'/sistema/imagens/app_logo', [
            'ficheiro' => UploadedFile::fake()->image('novo.png', 200, 60),
        ])->assertOk();

        $novo = SystemSetting::get('app_logo');
        $this->assertNotSame('settings/antigo.png', $novo);
        Storage::disk('public')->assertExists($novo);
        Storage::disk('public')->assertMissing('settings/antigo.png');
    }

    public function test_uma_imagem_de_chave_inventada_nao_se_grava(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/sistema/imagens/contact_email', [
            'ficheiro' => UploadedFile::fake()->image('x.png'),
        ])->assertNotFound();
    }

    /* ─── Software ────────────────────────────────────────────────────── */

    public function test_os_bloqueios_de_apagar_gravam_um_a_um(): void
    {
        $this->actingAs($this->dono)->putJson(self::API.'/software/bloqueios', [
            'block_delete_sales_invoice' => true,
            'block_delete_debit_note' => true,
            'block_delete_credit_note' => false,
        ])->assertOk();

        $this->assertTrue((bool) SoftwareSetting::get('invoicing', 'block_delete_sales_invoice', false));
        $this->assertTrue((bool) SoftwareSetting::get('invoicing', 'block_delete_debit_note', false));
        // A nota de débito lia a chave da de crédito: são interruptores distintos.
        $this->assertFalse((bool) SoftwareSetting::get('invoicing', 'block_delete_credit_note', false));
    }

    /** A consola da AGT só lê: uma operação que submeta não existe. */
    public function test_a_consola_da_agt_so_aceita_operacoes_de_leitura(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/software/agt/operacao', [
            'empresa' => $this->tenant->id, 'ambiente' => 'sandbox', 'operacao' => 'registarFactura',
        ])->assertStatus(422)->assertJsonValidationErrors('operacao');
    }
}
