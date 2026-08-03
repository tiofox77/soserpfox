<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\StockManagement;
use App\Models\AuditTrail;
use App\Models\Invoicing\StockMovement;
use App\Services\Audit\AuditRecorder;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * SONDA TEMPORÁRIA — apagar depois da revisão.
 */
class SondaAuditoriaLoteTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit')->comModulo('invoicing');
    }

    public function test_sonda_linha_falhada_apaga_auditoria_das_anteriores(): void
    {
        $a    = $this->produtoComStock(20);
        $b    = $this->produtoComStock(20);
        $seco = $this->produtoComStock(1);
        $d    = $this->produtoComStock(20);

        $recorder = app(AuditRecorder::class);

        // Esvaziar o ruído do setup para a contagem final ser só do lote.
        $recorder->despejar();
        $antes = AuditTrail::count();

        $c = Livewire::test(StockManagement::class)
            ->call('openEntryModal')
            ->set('entryWarehouseId', $this->armazem->id)
            ->set('entryNotes', 'Sonda');

        foreach ([$a, $b, $seco, $d] as $p) {
            $c->call('addEntryItem', $p->id);
        }

        $itens = $c->get('entryItems');
        $itens[0]['quantity'] = 5;   $itens[0]['op'] = 'add';
        $itens[1]['quantity'] = 3;   $itens[1]['op'] = 'add';
        $itens[2]['quantity'] = 999; $itens[2]['op'] = 'sub';   // vai falhar
        $itens[3]['quantity'] = 7;   $itens[3]['op'] = 'add';

        $c->set('entryItems', $itens)->call('saveEntry');

        $ref = $c->get('batchReference');

        // Em produção isto acontece no commit da transacção exterior.
        $recorder->despejar();

        $movimentos = StockMovement::doLote($ref)->get();

        $auditados = AuditTrail::where('auditable_type', StockMovement::class)
            ->whereIn('auditable_id', $movimentos->pluck('id'))
            ->where('event', 'created')
            ->count();

        fwrite(STDERR, "\n=== SONDA ===\n");
        fwrite(STDERR, 'batchOk: ' . $c->get('batchOk') . "\n");
        fwrite(STDERR, 'movimentos gravados no lote: ' . $movimentos->count() . ' (ids ' . $movimentos->pluck('id')->implode(', ') . ")\n");
        fwrite(STDERR, 'linhas de auditoria para esses movimentos: ' . $auditados . "\n");
        fwrite(STDERR, 'total audit_trail novo: ' . (AuditTrail::count() - $antes) . "\n");
        fwrite(STDERR, "=============\n");

        $this->assertSame(3, $movimentos->count(), 'três linhas boas têm de ficar gravadas');
        $this->assertSame(
            3,
            $auditados,
            'cada movimento gravado tem de ter a sua linha na trilha'
        );
    }
}
