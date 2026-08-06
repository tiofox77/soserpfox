<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * O hash anterior é o do documento ANTERIOR, não o do último da série.
 *
 * getPreviousHash() pegava no documento de maior id da série — incluindo os
 * emitidos DEPOIS deste. Num documento novo passava despercebido (é ele o
 * maior e ainda não tem hash), mas ao voltar a assinar um documento já
 * existente a cadeia partia-se: documentos seguidos ficavam todos a apontar
 * para o mesmo anterior, e o encadeamento SAF-T deixa de provar sequência.
 *
 * Descoberto ao refazer a cadeia depois de renumerar os documentos que
 * começavam por "SOS".
 */
class CadeiaDeHashTest extends TenantTestCase
{
    private function factura(string $numero, string $hash): SalesInvoice
    {
        $f = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'invoice_number' => $numero,
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'status'         => 'sent',
            'subtotal'       => 1000,
            'total'          => 1140,
        ]);

        $f->forceFill(['hash' => $hash])->saveQuietly();

        return $f->fresh();
    }

    public function test_o_anterior_e_o_de_id_menor(): void
    {
        $a = $this->factura('FT S/000001', 'hash-A');
        $b = $this->factura('FT S/000002', 'hash-B');
        $c = $this->factura('FT S/000003', 'hash-C');

        // A pergunta é feita a partir do DO MEIO: o anterior tem de ser o A,
        // não o C, que é o de maior id.
        $this->assertSame('hash-A', $b->getPreviousHash());
        $this->assertSame('hash-B', $c->getPreviousHash());
    }

    public function test_o_primeiro_nao_tem_anterior(): void
    {
        $a = $this->factura('FT S/000001', 'hash-A');

        $this->assertSame('', $a->getPreviousHash());
    }

    public function test_reassinar_o_do_meio_nao_puxa_o_do_fim(): void
    {
        // O caso concreto que partia a cadeia: três documentos seguidos
        // ficavam todos com o mesmo hash_previous.
        $this->factura('FT S/000001', 'hash-A');
        $b = $this->factura('FT S/000002', 'hash-B');
        $c = $this->factura('FT S/000003', 'hash-C');

        $this->assertNotSame(
            $b->getPreviousHash(),
            $c->getPreviousHash(),
            'dois documentos seguidos não podem partilhar o mesmo anterior'
        );
    }

    public function test_um_documento_ainda_por_gravar_olha_para_o_ultimo(): void
    {
        // Ao criar, o correcto É o último da série — ainda não há id para
        // comparar, e é esse o comportamento de sempre.
        $this->factura('FT S/000001', 'hash-A');
        $this->factura('FT S/000002', 'hash-B');

        $nova = new SalesInvoice(['tenant_id' => $this->tenant->id]);
        $nova->tenant_id = $this->tenant->id;

        $this->assertSame('hash-B', $nova->getPreviousHash());
    }
}
