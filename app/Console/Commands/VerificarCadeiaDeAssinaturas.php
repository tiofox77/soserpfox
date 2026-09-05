<?php

namespace App\Console\Commands;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A cadeia de assinaturas das facturas, conferida documento a documento.
 *
 * A lei angolana (SAFT-AO) manda que cada documento leve o hash do ANTERIOR
 * da mesma série. É isso que torna a sequência inviolável: mexer num
 * documento do meio parte tudo o que vem a seguir, e nota-se.
 *
 * O que se confere aqui:
 *
 *  1. TODOS TÊM ASSINATURA — um documento sem hash é um documento que a AGT
 *     não aceita.
 *  2. A CADEIA LIGA — o `hash_previous` de cada um é o `hash` do anterior.
 *  3. NÃO HÁ BURACOS na numeração — um número que falta é um documento que
 *     desapareceu, e a AGT pergunta por ele.
 *  4. NÃO HÁ NÚMEROS REPETIDOS — dois documentos com o mesmo número é o
 *     defeito mais caro: duas realidades fiscais para a mesma venda.
 *
 * SÓ LÊ. Não corrige nada — quem corrige é o `agt:renumerar-sos`, e essa é
 * uma decisão que se toma a ver este relatório primeiro.
 */
class VerificarCadeiaDeAssinaturas extends Command
{
    protected $signature = 'agt:verificar-cadeia
                            {--tenant= : Só esta empresa (id)}
                            {--serie= : Só esta série (prefixo do número)}
                            {--detalhe : Mostra cada documento partido, e não só a contagem}';

    protected $description = 'Confere a cadeia de hash das facturas: assinatura, encadeamento, buracos e repetidos (só lê)';

    public function handle(): int
    {
        $empresas = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey((int) $this->option('tenant')))
            ->orderBy('id')
            ->get();

        $problemasTotais = 0;

        foreach ($empresas as $empresa) {
            $problemasTotais += $this->conferirEmpresa($empresa);
        }

        $this->newLine();

        if ($problemasTotais === 0) {
            $this->info('Cadeia intacta em todas as empresas conferidas.');

            return self::SUCCESS;
        }

        $this->error("{$problemasTotais} problema(s) na cadeia. Ver acima.");

        // Devolve falha para isto poder ser usado num arranque ou num guião
        // sem se ter de ler o texto.
        return self::FAILURE;
    }

    private function conferirEmpresa(Tenant $empresa): int
    {
        $facturas = SalesInvoice::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->whereNotNull('invoice_number')
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'invoice_type', 'total', 'hash', 'saft_hash', 'hash_previous', 'atcud', 'created_at']);

        if ($facturas->isEmpty()) {
            return 0;
        }

        $porSerie = $facturas->groupBy(fn ($f) => $this->serieDe($f->invoice_number));

        // A CADEIA É UMA SÓ POR EMPRESA, e percorre-se por ordem de criação.
        //
        // Não é por série, ao contrário do que se supõe à primeira: o
        // ModuleInvoiceService escolhe o anterior com
        // `where('tenant_id')->where('id','<',...)`, sem olhar à série. Uma FT
        // liga-se à FR que veio antes dela e vice-versa. Conferir por série
        // dava dezenas de erros que não existem — foi o que este comando fez
        // na primeira versão.
        $problemas = $this->conferirCadeia($facturas);

        // A NUMERAÇÃO, essa, é por série: buracos e repetidos contam-se dentro
        // de cada uma.
        foreach ($porSerie as $serie => $daSerie) {
            if ($this->option('serie') && $serie !== $this->option('serie')) {
                continue;
            }

            $problemas = array_merge($problemas, $this->conferirNumeracao($serie, $daSerie));
        }

        $this->line('');
        $this->line("<options=bold>#{$empresa->id} {$empresa->name}</> — {$facturas->count()} documento(s), " . count($porSerie) . ' série(s)');

        if (!$problemas) {
            $this->line('   <fg=green>cadeia intacta</>');

            return 0;
        }

        // O ecrã corta, a CONTAGEM não. Um relatório que trunca em silêncio faz
        // parecer que há menos problemas do que há — e é o número que fica na
        // cabeça de quem lê.
        $mostrar = $this->option('detalhe') ? $problemas : array_slice($problemas, 0, 8);

        foreach ($mostrar as $p) {
            $this->line("   <fg=red>{$p}</>");
        }

        if (count($mostrar) < count($problemas)) {
            $this->line('   <fg=yellow>… e mais ' . (count($problemas) - count($mostrar)) . ' (usar --detalhe para ver todos)</>');
        }

        return count($problemas);
    }

    /**
     * A série de um número. "FR FR/000023" → "FR FR"; "FT A/000001" → "FT A".
     * Corta na última barra: o que vem depois é o sequencial.
     */
    private function serieDe(string $numero): string
    {
        $corte = strrpos($numero, '/');

        return $corte === false ? $numero : substr($numero, 0, $corte);
    }

    private function sequencialDe(string $numero): ?int
    {
        $corte = strrpos($numero, '/');

        if ($corte === false) {
            return null;
        }

        $resto = substr($numero, $corte + 1);

        return ctype_digit($resto) ? (int) $resto : null;
    }

    /**
     * O encadeamento: cada documento assinado liga-se ao anterior da empresa.
     *
     * @return array<int,string>
     */
    private function conferirCadeia($facturas): array
    {
        $problemas = [];
        $anterior = null;

        foreach ($facturas as $f) {
            $hash = $f->hash ?: $f->saft_hash;

            if (empty($hash)) {
                $problemas[] = "{$f->invoice_number}: SEM assinatura (hash vazio)";

                // Sem hash não serve de elo: o seguinte liga-se ao último
                // ASSINADO, que é o que o serviço também faz.
                continue;
            }

            if ($anterior) {
                $hashAnterior = $anterior->hash ?: $anterior->saft_hash;

                if (empty($f->hash_previous)) {
                    $problemas[] = "{$f->invoice_number}: sem ligação ao anterior ({$anterior->invoice_number})";
                } elseif ($f->hash_previous !== $hashAnterior) {
                    $problemas[] = "{$f->invoice_number}: a ligação NÃO bate com {$anterior->invoice_number}";
                }
            }

            $anterior = $f;
        }

        return $problemas;
    }

    /**
     * A numeração dentro de uma série: buracos e repetidos.
     *
     * @return array<int,string>
     */
    private function conferirNumeracao(string $serie, $daSerie): array
    {
        $problemas = [];
        $porNumero = [];

        // Ordenar pelo SEQUENCIAL e não pelo id: um documento reposto do
        // offline pode ter id maior e número menor.
        $ordenadas = $daSerie->sortBy(fn ($f) => $this->sequencialDe($f->invoice_number) ?? PHP_INT_MAX)->values();

        $anterior = null;

        foreach ($ordenadas as $f) {
            $n = $this->sequencialDe($f->invoice_number);

            if ($n === null) {
                continue;
            }

            if (isset($porNumero[$n])) {
                $problemas[] = "{$f->invoice_number}: número REPETIDO (ids {$porNumero[$n]} e {$f->id})";
            } else {
                $porNumero[$n] = $f->id;
            }

            if ($anterior !== null && $n > $anterior + 1) {
                $problemas[] = "{$serie}: faltam " . ($n - $anterior - 1) . " número(s) entre {$anterior} e {$n}";
            }

            $anterior = $n;
        }

        return $problemas;
    }
}
