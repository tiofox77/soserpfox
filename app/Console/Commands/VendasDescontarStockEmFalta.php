<?php

namespace App\Console\Commands;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Workshop\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Desconta o stock das vendas que foram feitas sem o descontar.
 *
 * PORQUE É QUE ISTO É PRECISO
 * ---------------------------
 * Enquanto um artigo tinha o "Gerenciar Stock" desligado, vender não gerava
 * movimento nenhum: a venda saía, o dinheiro entrava, e a quantidade ficava
 * onde estava. Depois de o `produtos:gerir-stock` ligar a bandeira, o stock
 * volta a contar — mas a partir de um número que já vinha errado, porque as
 * vendas desse período nunca foram descontadas.
 *
 * Isto vai buscar essas vendas e lança os movimentos em falta, com a data e o
 * documento certos, para que o histórico fique a dizer a verdade.
 *
 * COMO É QUE SE SABE QUE FALTA
 * ----------------------------
 * Por LINHA, não por documento. O observer das vendas trata a factura como um
 * todo — se já houver um movimento a referenciá-la, considera-a descontada e
 * não olha mais. Numa factura com cinco linhas em que três artigos contavam
 * stock e dois não, isso dá "já está" e os dois ficam por descontar para
 * sempre. Aqui compara-se, para cada artigo de cada factura, o que foi vendido
 * com o que já saiu.
 *
 * A CONTAGEM FÍSICA MANDA
 * -----------------------
 * Um movimento de AJUSTE é uma contagem: alguém foi à prateleira, contou, e
 * pôs o sistema a dizer o que lá estava. Nesse instante, tudo o que se tinha
 * vendido até aí já está reflectido — mesmo o que nunca gerou movimento.
 *
 * Por isso só entram aqui as vendas POSTERIORES à última contagem de cada
 * artigo. Sem esta regra, uma farmácia que se mantém em ordem à custa de
 * contagens periódicas veria as vendas antigas descontadas uma segunda vez, e
 * o inventário ficava pior do que estava. Numa base de teste, ignorar isto
 * dava 2488 unidades a descontar a dobrar.
 *
 * O QUE FICA DE FORA, E PORQUÊ
 * ----------------------------
 * · Facturas anuladas e rascunhos — não houve venda.
 * · Facturas vindas de uma Ordem de Serviço: as peças saíram na conclusão da
 *   OS, com `reference_type = 'WorkOrder'`. Como aqui se procura por
 *   referência à FACTURA, pareceriam por descontar e sairiam duas vezes.
 * · Artigos que não contam stock — serviços, e produtos deliberadamente sem
 *   inventário. Se a bandeira ainda está desligada, é porque assim se quer.
 * · Vendas anteriores à última contagem física do artigo (ver acima).
 *
 * O STOCK PODE FICAR NEGATIVO
 * ---------------------------
 * E deve. Vendeu-se mais do que o sistema julgava ter; um negativo é a medida
 * exacta do que falta contar, e escondê-lo em zero apagava a única pista.
 */
class VendasDescontarStockEmFalta extends Command
{
    protected $signature = 'vendas:descontar-stock-em-falta
        {--tenant= : id da empresa (obrigatório para aplicar)}
        {--desde= : data inicial (YYYY-MM-DD). Por omissão, 7 dias atrás}
        {--ate= : data final (YYYY-MM-DD). Por omissão, hoje}
        {--aplicar : grava; sem isto é apenas simulação}
        {--limite=40 : quantas linhas mostrar}
        {--ignorar-contagens : desliga a regra da contagem física — perigoso, ver a nota na classe}';

    protected $description = 'Lança os movimentos de saída das vendas que não descontaram stock';

    public function handle(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $aplicar = (bool) $this->option('aplicar');

        if (!$tenantId) {
            $this->error('Indique --tenant=<id>. Uma empresa de cada vez: isto mexe no inventário.');

            return self::FAILURE;
        }

        $empresa = Tenant::find($tenantId);

        if (!$empresa) {
            $this->error("Não existe a empresa #{$tenantId}.");

            return self::FAILURE;
        }

        $desde = $this->option('desde')
            ? \Carbon\Carbon::parse($this->option('desde'))->startOfDay()
            : now()->subDays(7)->startOfDay();

        $ate = $this->option('ate')
            ? \Carbon\Carbon::parse($this->option('ate'))->endOfDay()
            : now()->endOfDay();

        $this->line("Empresa: <info>{$empresa->name}</info> (#{$empresa->id})");
        $this->line("Período: {$desde->toDateString()} a {$ate->toDateString()}");
        $this->newLine();

        $falhas = $this->emFalta($tenantId, $desde, $ate);

        $this->contas();

        if (empty($falhas)) {
            $this->newLine();
            $this->info('Nada a lançar neste período.');

            return self::SUCCESS;
        }

        $this->newLine();

        $this->mostrar($falhas);

        $unidades = array_sum(array_column($falhas, 'falta'));
        $this->newLine();
        $this->line(sprintf(
            'Linhas por descontar: <comment>%d</comment>  (total %s unidades, em %d factura(s))',
            count($falhas),
            $this->numero($unidades),
            count(array_unique(array_column($falhas, 'factura_id')))
        ));

        $negativos = array_filter($falhas, fn ($f) => $f['depois'] < 0);

        if ($negativos) {
            $this->newLine();
            $this->warn(count($negativos) . ' artigo(s) vão ficar com stock NEGATIVO.');
            $this->line('  Não é um erro: vendeu-se mais do que o sistema julgava ter, e o');
            $this->line('  negativo é a medida exacta do que falta contar. Escondê-lo em zero');
            $this->line('  apagava a única pista que resta.');
        }

        if (!$aplicar) {
            $this->newLine();
            $this->warn('SIMULAÇÃO — nada foi gravado. Acrescente --aplicar para gravar.');

            return self::SUCCESS;
        }

        $lancados = $this->lancar($falhas);

        $this->newLine();
        $this->info("{$lancados} movimento(s) de saída lançados.");
        $this->line('  Ficam no histórico com a data e o documento da venda que os originou,');
        $this->line('  portanto o rastreio do artigo passa a bater certo.');

        return self::SUCCESS;
    }

    // ── o que falta ──────────────────────────────────────────────────────────

    /**
     * Compara, por artigo e por factura, o que foi vendido com o que já saiu.
     *
     * @return array<int, array{factura_id:int, numero:string, data:string, produto_id:int,
     *                          nome:string, vendido:float, ja_saiu:float, falta:float,
     *                          armazem_id:?int, antes:float, depois:float, custo:float}>
     */
    private function emFalta(int $tenantId, $desde, $ate): array
    {
        $facturas = SalesInvoice::with('items')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereBetween('invoice_date', [$desde, $ate])
            ->orderBy('invoice_date')
            ->get();

        if ($facturas->isEmpty()) {
            // As contas têm de existir mesmo quando não há nada: é o
            // relatório que prova que se olhou.
            $this->contas = [
                'facturas' => 0, 'ja_descontadas' => 0, 'por_contagem' => 0,
                'sem_stock_gerido' => 0, 'sem_armazem' => 0, 'da_oficina' => 0,
            ];

            return [];
        }

        // As facturas nascidas de uma Ordem de Serviço: as peças já saíram na
        // conclusão da OS, com outra referência. Sem esta lista, sairiam de
        // novo aqui.
        $daOficina = WorkOrder::withTrashed()
            ->whereIn('invoice_id', $facturas->pluck('id'))
            ->pluck('invoice_id')->unique()->flip();

        // O que já saiu, por factura e artigo.
        $jaSaiu = StockMovement::where('tenant_id', $tenantId)
            ->where('reference_type', SalesInvoice::class)
            ->whereIn('reference_id', $facturas->pluck('id'))
            ->where('type', 'out')
            ->select('reference_id', 'product_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('reference_id', 'product_id')
            ->get()
            ->keyBy(fn ($m) => $m->reference_id . ':' . $m->product_id);

        $produtos = Product::where('tenant_id', $tenantId)->get()->keyBy('id');
        $armazemPorOmissao = $this->armazemPorOmissao($tenantId);
        $ultimaContagem = $this->ultimaContagemPorArtigo($tenantId);
        $saltadosPorContagem = 0;
        $semArmazem = 0;
        $daOficinaSaltadas = 0;
        $semStockGerido = 0;
        $jaDescontadas = 0;

        // Saldo corrente por (armazém, artigo), para se poder mostrar o antes
        // e o depois de várias linhas do mesmo artigo sem ir à base a cada uma.
        $saldos = [];
        $falhas = [];

        foreach ($facturas as $factura) {
            if (isset($daOficina[$factura->id])) {
                $daOficinaSaltadas++;
                continue;
            }

            $armazem = $factura->warehouse_id ?: $armazemPorOmissao;

            if (!$armazem) {
                $semArmazem++;
                continue;   // sem armazém não há onde descontar
            }

            // Várias linhas do mesmo artigo na mesma factura contam como uma.
            $porArtigo = [];

            foreach ($factura->items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                $produto = $produtos[$item->product_id] ?? null;

                // Se ainda não conta stock, é porque assim se quer.
                if (!$produto || !$produto->controlaStock()) {
                    $semStockGerido++;
                    continue;
                }

                $porArtigo[$item->product_id] ??= ['qtd' => 0.0, 'custo' => 0.0];
                $porArtigo[$item->product_id]['qtd'] += (float) $item->quantity;
                $porArtigo[$item->product_id]['custo'] = (float) ($item->unit_price ?? 0);
            }

            foreach ($porArtigo as $produtoId => $linha) {
                // A contagem física manda: se alguém contou este artigo DEPOIS
                // desta venda, o que lá está já a reflecte, tenha havido
                // movimento ou não. Descontá-la agora seria subtrair a dobrar.
                $contagem = $ultimaContagem[$produtoId] ?? null;

                if ($contagem && $factura->invoice_date && $contagem->greaterThan($factura->invoice_date)) {
                    $saltadosPorContagem++;
                    continue;
                }

                $chave = $factura->id . ':' . $produtoId;
                $saiu = (float) ($jaSaiu[$chave]->total ?? 0);
                $falta = round($linha['qtd'] - $saiu, 3);

                if ($falta <= 0) {
                    $jaDescontadas++;
                    continue;
                }

                $chaveSaldo = $armazem . ':' . $produtoId;
                $saldos[$chaveSaldo] ??= $this->saldoActual($tenantId, $armazem, $produtoId);

                $antes = $saldos[$chaveSaldo];
                $depois = round($antes - $falta, 3);
                $saldos[$chaveSaldo] = $depois;

                $falhas[] = [
                    'factura_id' => $factura->id,
                    'numero'     => (string) $factura->invoice_number,
                    'data'       => $factura->invoice_date?->toDateString() ?? '—',
                    'produto_id' => $produtoId,
                    'nome'       => (string) ($produtos[$produtoId]->name ?? '?'),
                    'vendido'    => $linha['qtd'],
                    'ja_saiu'    => $saiu,
                    'falta'      => $falta,
                    'armazem_id' => $armazem,
                    'antes'      => $antes,
                    'depois'     => $depois,
                    'custo'      => $linha['custo'],
                    'user_id'    => $factura->created_by,
                ];
            }
        }

        $this->contas = [
            'facturas'         => $facturas->count(),
            'ja_descontadas'   => $jaDescontadas,
            'por_contagem'     => $saltadosPorContagem,
            'sem_stock_gerido' => $semStockGerido,
            'sem_armazem'      => $semArmazem,
            'da_oficina'       => $daOficinaSaltadas,
        ];

        return $falhas;
    }

    /**
     * Porque é que cada linha NÃO entrou.
     *
     * Um comando que diz "nada em falta" depois de ter saltado tudo por uma
     * razão estrutural — nenhum armazém, artigos sem inventário — está a
     * mentir com a verdade. Estas contas são a prova de que ele olhou.
     */
    private array $contas = [];

    /**
     * A data da última contagem de cada artigo.
     *
     * Um movimento de AJUSTE é alguém que foi à prateleira e contou. A partir
     * desse instante o número está certo, e tudo o que se vendeu antes já lá
     * está reflectido.
     *
     * @return array<int, \Carbon\Carbon>
     */
    private function ultimaContagemPorArtigo(int $tenantId): array
    {
        if ($this->option('ignorar-contagens')) {
            return [];
        }

        return DB::table('invoicing_stock_movements')
            ->where('tenant_id', $tenantId)
            ->where('type', 'adjustment')
            ->select('product_id', DB::raw('MAX(created_at) as ultima'))
            ->groupBy('product_id')
            ->pluck('ultima', 'product_id')
            ->map(fn ($d) => \Carbon\Carbon::parse($d))
            ->all();
    }

    private function saldoActual(int $tenantId, int $armazemId, int $produtoId): float
    {
        return (float) DB::table('invoicing_stocks')
            ->where('tenant_id', $tenantId)
            ->where('warehouse_id', $armazemId)
            ->where('product_id', $produtoId)
            ->value('quantity');
    }

    private function armazemPorOmissao(int $tenantId): ?int
    {
        return Warehouse::where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }

    // ── lançar ───────────────────────────────────────────────────────────────

    /**
     * Grava os movimentos em falta.
     *
     * O stock é ajustado na LINHA do armazém, pelo modelo, para que o
     * StockObserver mantenha o agregado do artigo — nunca por increment() nem
     * por DB::table, que passariam ao lado dos eventos e deixariam o agregado
     * a discordar das linhas.
     *
     * O movimento é criado com `semAplicarStock` porque o débito já foi feito
     * aqui: sem isso, o hook do StockMovement descontava uma segunda vez. É o
     * mesmo par de passos que o SalesInvoiceObserver usa numa venda normal.
     *
     * NÃO se usa `Stock::removeStock`: esse método filtra por `activeTenantId()`,
     * que numa consola é nulo, e recusa-se a deixar o saldo ir a negativo — que
     * é precisamente o que aqui tem de poder acontecer.
     */
    private function lancar(array $falhas): int
    {
        $lancados = 0;

        foreach ($falhas as $f) {
            try {
                DB::transaction(function () use ($f, &$lancados) {
                    $linha = Stock::withoutGlobalScopes()->firstOrCreate(
                        [
                            'tenant_id'    => $this->tenantIdDaFactura($f['factura_id']),
                            'warehouse_id' => $f['armazem_id'],
                            'product_id'   => $f['produto_id'],
                        ],
                        ['quantity' => 0, 'reserved_quantity' => 0]
                    );

                    $linha->quantity = round($linha->quantity - $f['falta'], 3);
                    $linha->save();   // o StockObserver sincroniza o agregado

                    StockMovement::semAplicarStock(function () use ($f, $linha) {
                        StockMovement::create([
                            'tenant_id'      => $linha->tenant_id,
                            'warehouse_id'   => $f['armazem_id'],
                            'product_id'     => $f['produto_id'],
                            'type'           => 'out',
                            'reference_type' => SalesInvoice::class,
                            'reference_id'   => $f['factura_id'],
                            'quantity'       => $f['falta'],
                            'unit_cost'      => $f['custo'],
                            'user_id'        => $f['user_id'],
                            'notes'          => "Venda - Fatura {$f['numero']} (saída em falta, "
                                . 'lançada por vendas:descontar-stock-em-falta)',
                        ]);
                    });

                    $lancados++;
                });
            } catch (\Throwable $e) {
                $this->error("Falhou na factura {$f['numero']}, artigo #{$f['produto_id']}: " . $e->getMessage());
            }
        }

        return $lancados;
    }

    private array $tenantPorFactura = [];

    private function tenantIdDaFactura(int $facturaId): int
    {
        return $this->tenantPorFactura[$facturaId] ??= (int) DB::table('invoicing_sales_invoices')
            ->where('id', $facturaId)->value('tenant_id');
    }

    // ── apresentação ─────────────────────────────────────────────────────────

    private function mostrar(array $falhas): void
    {
        $limite = (int) $this->option('limite');

        $this->table(
            ['factura', 'data', 'artigo', 'vendido', 'já saiu', 'falta', 'stock →'],
            array_map(fn ($f) => [
                mb_strimwidth($f['numero'], 0, 16, '…'),
                $f['data'],
                mb_strimwidth($f['nome'], 0, 34, '…'),
                $this->numero($f['vendido']),
                $this->numero($f['ja_saiu']),
                $this->numero($f['falta']),
                $this->numero($f['antes']) . ' → '
                    . ($f['depois'] < 0 ? "<comment>{$this->numero($f['depois'])}</comment>" : $this->numero($f['depois'])),
            ], array_slice($falhas, 0, $limite))
        );

        if (count($falhas) > $limite) {
            $this->line('… e mais ' . (count($falhas) - $limite) . ' (ver --limite=).');
        }
    }

    /** O que foi visto e porque é que ficou de fora. */
    private function contas(): void
    {
        $c = $this->contas + [
            'facturas' => 0, 'ja_descontadas' => 0, 'por_contagem' => 0,
            'sem_stock_gerido' => 0, 'sem_armazem' => 0, 'da_oficina' => 0,
        ];

        $this->line("Facturas no período: <info>{$c['facturas']}</info>");

        $linhas = [];

        if ($c['ja_descontadas']) {
            $linhas[] = ["já tinham descontado", $c['ja_descontadas']];
        }
        if ($c['por_contagem']) {
            $linhas[] = ['já cobertas por uma contagem física', $c['por_contagem']];
        }
        if ($c['sem_stock_gerido']) {
            $linhas[] = ['artigos sem inventário (serviços ou bandeira desligada)', $c['sem_stock_gerido']];
        }
        if ($c['da_oficina']) {
            $linhas[] = ['facturas de Ordem de Serviço (saíram noutra referência)', $c['da_oficina']];
        }
        if ($c['sem_armazem']) {
            $linhas[] = ['<comment>facturas SEM ARMAZÉM — não há onde descontar</comment>', $c['sem_armazem']];
        }

        if ($linhas) {
            $this->table(['ficaram de fora porque…', 'linhas'], $linhas);
        }

        if ($c['sem_armazem'] > 0) {
            $this->warn('Há facturas sem armazém e a empresa não tem armazém por omissão.');
            $this->line('  Sem isso não há onde lançar a saída. Criar um armazém em');
            $this->line('  Inventário → Armazéns e voltar a correr.');
        }
    }

    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', ''), '0'), ',') ?: '0';
    }
}
