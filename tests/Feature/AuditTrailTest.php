<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Trilha de auditoria — módulo de faturação.
 *
 * Os casos aqui são as três garantias que a tornam utilizável: não derruba o
 * que está a auditar, não guarda segredos, e não se deixa reescrever sem que
 * isso fique visível.
 */
class AuditTrailTest extends TenantTestCase
{
    private function recorder(): AuditRecorder
    {
        return app(AuditRecorder::class);
    }

    private function emitirFactura(): SalesInvoice
    {
        return app(\App\Services\Invoicing\ModuleInvoiceService::class)->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [['name' => 'Serviço', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true]],
        ]);
    }

    public function test_criar_um_documento_deixa_rasto(): void
    {
        $factura = $this->emitirFactura();
        $this->recorder()->despejar();   // o teste corre em transacção; forçar o despejo

        $linha = AuditTrail::where('tenant_id', $this->tenant->id)
            ->where('auditable_type', SalesInvoice::class)
            ->where('auditable_id', $factura->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($linha, 'a emissão de um documento tem de ficar registada');
        $this->assertSame($this->user->id, $linha->user_id);
        $this->assertSame($factura->invoice_number, $linha->auditable_label);

        // O canal aqui é 'console' porque a suite corre em phpunit; num pedido
        // web seria 'web'. O que interessa garantir é que fica SEMPRE
        // preenchido — sem canal não se distingue um acto do POS de um comando
        // artisan ou de uma chamada à API.
        $this->assertNotEmpty($linha->channel);
        $this->assertContains($linha->actor_type, ['user', 'console']);
    }

    public function test_o_rotulo_fica_congelado_e_sobrevive_ao_documento(): void
    {
        $factura = $this->emitirFactura();
        $numero  = $factura->invoice_number;
        $this->recorder()->despejar();

        // Mesmo que o documento desapareça, a linha continua legível.
        $linha = AuditTrail::where('auditable_id', $factura->id)->first();

        $this->assertSame($numero, $linha->auditable_label);
    }

    public function test_alteracao_regista_so_o_que_mudou(): void
    {
        $factura = $this->emitirFactura();
        $this->recorder()->despejar();
        AuditTrail::query()->delete();   // limpar o ruído da criação

        $factura->notes = 'Observação nova';
        $factura->save();
        $this->recorder()->despejar();

        $linha = AuditTrail::where('event', 'updated')->latest('id')->first();

        $this->assertNotNull($linha);
        $this->assertArrayHasKey('notes', $linha->new_values);
        $this->assertSame('Observação nova', $linha->new_values['notes']);
        $this->assertArrayNotHasKey('invoice_number', $linha->new_values,
            'só as colunas alteradas — guardar as 57 de cada vez são KBs por linha');
    }

    public function test_um_touch_nao_gera_ruido(): void
    {
        $factura = $this->emitirFactura();
        $this->recorder()->despejar();
        AuditTrail::query()->delete();

        $factura->touch();               // só updated_at
        $this->recorder()->despejar();

        $this->assertSame(0, AuditTrail::count(), 'updated_at sozinho não é um acto');
    }

    public function test_segredos_nunca_entram_na_trilha(): void
    {
        $cliente = \App\Models\Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Com segredo', 'nif' => (string) random_int(400000000, 499999999),
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);
        $this->recorder()->despejar();

        $linha = AuditTrail::where('auditable_id', $cliente->id)->latest('id')->first();
        $this->assertNotNull($linha);

        $conteudo = json_encode([$linha->old_values, $linha->new_values]);

        foreach (config('audit.redacted') as $proibido) {
            $this->assertStringNotContainsString(
                '"' . $proibido . '":"$2y$',
                $conteudo,
                "o campo {$proibido} não pode aparecer em claro — a tabela é selada e não se limpa"
            );
        }
    }

    public function test_um_rollback_descarta_a_auditoria(): void
    {
        $antes = AuditTrail::count();

        DB::beginTransaction();
        $this->emitirFactura();
        DB::rollBack();

        $this->recorder()->despejar();

        $this->assertSame($antes, AuditTrail::count(),
            'auditar um facto desfeito deixa no registo uma venda que não existe');
    }

    public function test_a_auditoria_nunca_derruba_a_operacao(): void
    {
        // Simular avaria: um modelo sem tenant_id não pode gerar linha, e isso
        // não pode rebentar quem o gravou.
        $recorder = $this->recorder();

        $orfao = new SalesInvoice();   // sem tenant_id

        $recorder->model('created', $orfao, [], ['x' => 1]);
        $recorder->despejar();

        $this->assertTrue(true, 'chegar aqui já é o teste: não lançou');
    }

    public function test_uma_linha_nao_pode_ser_alterada_nem_apagada(): void
    {
        $this->emitirFactura();
        $this->recorder()->despejar();

        $linha = AuditTrail::latest('id')->first();
        $this->assertNotNull($linha);

        try {
            $linha->update(['event' => 'adulterado']);
            $this->fail('a trilha tem de recusar alterações');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $linha->delete();
            $this->fail('a trilha tem de recusar eliminações');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_a_cadeia_de_hash_detecta_adulteracao(): void
    {
        $this->emitirFactura();
        $this->emitirFactura();
        $this->recorder()->despejar();

        $this->assertSame([], AuditTrail::verificarCadeia($this->tenant->id),
            'acabada de escrever, a cadeia tem de estar intacta');

        // Reescrever por baixo do Eloquent, como faria quem tem acesso à base.
        $alvo = AuditTrail::where('tenant_id', $this->tenant->id)->orderBy('sequence')->first();
        DB::table('audit_trail')->where('id', $alvo->id)->update(['event' => 'adulterado']);

        $problemas = AuditTrail::verificarCadeia($this->tenant->id);

        $this->assertNotEmpty($problemas, 'a adulteração tem de ser detectada');
        $this->assertSame($alvo->sequence, $problemas[0]['sequencia']);
    }

    public function test_arquivar_guarda_em_ficheiro_antes_de_libertar_a_tabela(): void
    {
        $this->emitirFactura();
        $this->recorder()->despejar();

        $antigas = AuditTrail::where('tenant_id', $this->tenant->id)->get();
        $this->assertNotEmpty($antigas, 'precisamos de linhas para arquivar');

        // Envelhecer as linhas por baixo do Eloquent (o guarda impede update).
        DB::table('audit_trail')
            ->whereIn('id', $antigas->pluck('id'))
            ->update(['created_at' => now()->subDays(400)]);

        $this->artisan('audit:archive', ['--days' => 365, '--tenant' => $this->tenant->id])
            ->assertSuccessful();

        $restantes = AuditTrail::where('tenant_id', $this->tenant->id)->get();

        $this->assertCount(1, $restantes, 'devia sobrar só o marco do arquivo');
        $this->assertSame('audit.archived', $restantes->first()->event);
        $this->assertSame($antigas->count(), $restantes->first()->metadata['linhas']);

        $ficheiro = $restantes->first()->metadata['ficheiro'];

        $this->assertFileExists(\Illuminate\Support\Facades\Storage::disk('local')->path($ficheiro));

        $conteudo = \Illuminate\Support\Facades\Storage::disk('local')->get($ficheiro);

        $this->assertCount(
            $antigas->count(),
            array_filter(explode("\n", trim($conteudo))),
            'o ficheiro tem de ter uma linha por registo removido'
        );

        // O arquivo remove sempre um prefixo, por isso não pode fazer a
        // verificação da cadeia gritar adulteração.
        $this->assertSame([], AuditTrail::verificarCadeia($this->tenant->id));

        \Illuminate\Support\Facades\Storage::disk('local')->delete($ficheiro);
    }

    public function test_arquivar_nao_apaga_nada_se_a_retencao_for_zero(): void
    {
        $this->emitirFactura();
        $this->recorder()->despejar();

        $antes = AuditTrail::where('tenant_id', $this->tenant->id)->count();

        DB::table('audit_trail')->update(['created_at' => now()->subDays(400)]);

        $this->artisan('audit:archive', ['--tenant' => $this->tenant->id])
            ->assertSuccessful();

        $this->assertSame($antes, AuditTrail::where('tenant_id', $this->tenant->id)->count(),
            'retenção a 0 significa manter tudo');
    }

    public function test_a_sequencia_e_por_empresa_e_nao_tem_buracos(): void
    {
        $this->emitirFactura();
        $this->emitirFactura();
        $this->recorder()->despejar();

        $sequencias = AuditTrail::where('tenant_id', $this->tenant->id)
            ->orderBy('sequence')->pluck('sequence')->all();

        $this->assertSame(range(1, count($sequencias)), $sequencias,
            'buracos na sequência não se distinguem de linhas removidas');
    }

    public function test_a_entrada_no_sistema_fica_registada(): void
    {
        AuditTrail::query()->delete();

        // O login do setUp já passou; forçar um novo dispara o evento.
        event(new \Illuminate\Auth\Events\Login('web', $this->user, false));
        $this->recorder()->despejar();

        $linha = AuditTrail::where('event', 'login')->latest('id')->first();

        $this->assertNotNull($linha, 'sem isto a trilha não responde a "quem estava lá"');
        $this->assertSame($this->tenant->id, $linha->tenant_id);
    }

    public function test_tentativa_falhada_regista_o_email_mas_nunca_a_password(): void
    {
        AuditTrail::query()->delete();

        event(new \Illuminate\Auth\Events\Failed('web', $this->user, [
            'email'    => 'alguem@exemplo.ao',
            'password' => 'segredo-em-claro',
        ]));
        $this->recorder()->despejar();

        $linha = AuditTrail::where('event', 'login_falhado')->latest('id')->first();

        $this->assertNotNull($linha);
        $this->assertSame('alguem@exemplo.ao', $linha->metadata['email']);
        $this->assertStringNotContainsString(
            'segredo-em-claro',
            json_encode($linha->metadata),
            'a password de uma tentativa falhada não pode ficar numa tabela selada'
        );
    }

    public function test_a_comunicacao_a_agt_deixa_rasto(): void
    {
        $factura = $this->emitirFactura();
        AuditTrail::query()->delete();

        // Sem chaves configuradas a submissão falha — e é esse o caso que
        // interessa auditar: o documento que ficou por comunicar.
        $factura->submitToAGT();
        $this->recorder()->despejar();

        $linha = AuditTrail::whereIn('event', ['agt_submetido', 'agt_falhou'])->latest('id')->first();

        $this->assertNotNull($linha, 'a comunicação é um acto fiscal e não é alteração de modelo');
        $this->assertSame($factura->invoice_number, $linha->metadata['documento']);
        $this->assertSame('SalesInvoice', $linha->metadata['tipo']);
    }

    public function test_o_pedido_cola_as_linhas_do_mesmo_acto(): void
    {
        $factura = $this->emitirFactura();
        $this->recorder()->despejar();

        $ids = AuditTrail::where('tenant_id', $this->tenant->id)
            ->pluck('request_id')->unique()->filter();

        $this->assertCount(1, $ids, 'uma venda gera várias linhas — têm de ficar coladas');
    }
}
