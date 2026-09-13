<?php

namespace Tests\Feature;

use App\Models\Treasury\Transaction;
use Tests\TenantTestCase;

/**
 * O número da transação de tesouraria não pode duplicar.
 *
 * O bug real (erro 1062 no índice único por tenant): cada ecrã fazia
 * `orderBy('id')->first()` + `substr(-4)+1`. Quando a última linha vinha de um
 * gerador uniqid (POS, factura, restaurante), o `substr(-4)` dava lixo, o
 * contador reiniciava e chocava com um número já existente.
 */
class NumeroTransacaoTest extends TenantTestCase
{
    private function tx(string $numero): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'transaction_number' => $numero,
            'type' => 'income',
            'amount' => 100,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'status' => 'completed',
        ]);
    }

    public function test_uniqid_nao_envenena_a_sequencia(): void
    {
        $ano = date('Y');
        $this->tx("TRX-{$ano}-0005");
        // A mais recente por id é uma uniqid — era isto que reiniciava o contador.
        $this->tx('TRX-' . strtoupper('abc12345'));

        $this->assertSame("TRX-{$ano}-0006", Transaction::gerarNumero($this->tenant->id));
    }

    public function test_criar_nao_duplica_quando_o_numero_ja_existe(): void
    {
        $ano = date('Y');
        $this->tx("TRX-{$ano}-0001");

        $nova = Transaction::criar([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 50,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $this->assertSame("TRX-{$ano}-0002", $nova->transaction_number);
    }

    /**
     * ERRO #77 EM PRODUÇÃO: dois postos a vender ao mesmo tempo.
     *
     * Dentro da transacção da venda a leitura é uma fotografia: o número que o
     * outro posto gravou não se vê, e as seis tentativas davam o mesmo. Aqui a
     * fotografia simula-se com um gerador que nunca vê a linha que já existe.
     */
    public function test_o_movimento_passa_mesmo_quando_a_leitura_esta_atrasada(): void
    {
        $ano = date('Y');
        $this->tx("TRX-{$ano}-0001");

        $atrasado = new class extends \App\Services\Treasury\TreasuryMovementService {
            protected function numeroDaTentativa(int $tenantId, int $tentativa): string
            {
                // O maior que a fotografia vê é nenhum: começa em 0001.
                return 'TRX-' . date('Y') . '-' . str_pad((string) (1 + $tentativa), 4, '0', STR_PAD_LEFT);
            }
        };

        $t = $atrasado->post($this->dados());

        $this->assertSame("TRX-{$ano}-0002", $t->transaction_number);
    }

    public function test_se_todas_as_tentativas_chocam_o_recurso_final_grava_na_mesma(): void
    {
        $ano = date('Y');
        $this->tx("TRX-{$ano}-0001");

        $cego = new class extends \App\Services\Treasury\TreasuryMovementService {
            protected function numeroDaTentativa(int $tenantId, int $tentativa): string
            {
                return 'TRX-' . date('Y') . '-0001';
            }
        };

        $t = $cego->post($this->dados());

        $this->assertStringStartsWith("TRX-{$ano}-", $t->transaction_number);
        $this->assertNotSame("TRX-{$ano}-0001", $t->transaction_number);
    }

    public function test_o_desvio_anda_casas_para_a_frente(): void
    {
        $ano = date('Y');
        $this->tx("TRX-{$ano}-0007");

        $this->assertSame("TRX-{$ano}-0010", Transaction::gerarNumero($this->tenant->id, 'TRX', 2));
    }

    private function dados(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 50,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'status' => 'pending',
        ];
    }

    public function test_criar_gera_sequencial_sem_colisao(): void
    {
        $ano = date('Y');
        $numeros = [];
        for ($i = 0; $i < 3; $i++) {
            $numeros[] = Transaction::criar([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'type' => 'income',
                'amount' => 10,
                'currency' => 'AOA',
                'transaction_date' => now()->toDateString(),
                'status' => 'completed',
            ])->transaction_number;
        }

        $this->assertSame(["TRX-{$ano}-0001", "TRX-{$ano}-0002", "TRX-{$ano}-0003"], $numeros);
        $this->assertSame($numeros, array_unique($numeros), 'nenhum numero pode repetir');
    }

    public function test_o_indice_unico_por_tenant_existe(): void
    {
        // Guarda: a correcção depende do índice único (tenant_id, transaction_number).
        $this->tx('TRX-DUP-1');
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->tx('TRX-DUP-1');
    }
}
