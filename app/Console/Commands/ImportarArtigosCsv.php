<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa artigos de um CSV para uma empresa, com armazém e stock.
 *
 * SIMULAÇÃO POR OMISSÃO. Sem --aplicar diz o que faria e não escreve nada. É
 * uma importação de milhares de linhas na base de um cliente: ver primeiro
 * custa um minuto, e desfazer à mão custa uma tarde.
 *
 * IDEMPOTENTE pelo código de barras. Correr duas vezes não duplica artigos —
 * actualiza os que já lá estão. Numa importação grande é normal ter de repetir
 * (uma ligação que cai, um lote que falha), e um comando que duplica ao
 * repetir é um comando que não se pode usar.
 *
 * O STOCK É ESCRITO PELO MODELO, nunca por SQL directo: há um observador que
 * mantém o agregado do produto a partir das linhas de stock, e escrever à
 * bruta deixava o total do artigo a mentir em relação aos armazéns.
 *
 *   php artisan artigos:importar --tenant=57 --armazem=Loja --ficheiro=storage/app/import.csv
 *   php artisan artigos:importar --tenant=57 --armazem=Loja --ficheiro=... --aplicar
 */
class ImportarArtigosCsv extends Command
{
    protected $signature = 'artigos:importar
                            {--tenant= : id da empresa}
                            {--armazem=Loja : nome do armazém que recebe o stock}
                            {--ficheiro= : caminho do CSV}
                            {--so-stock : só lança stock; não cria artigos nem toca em preços}
                            {--sincronizar : a folha manda; põe a quantidade exacta, zeros incluídos}
                            {--verificar : só compara a folha com a base e diz o que difere}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Importa artigos de um CSV para uma empresa, com armazém e stock (simulação por omissão)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $caminho = (string) $this->option('ficheiro');

        // Um caminho relativo resolve-se contra a raiz do projecto. Corrido
        // pela linha de comandos o directorio actual ja e esse, mas por HTTP
        // nao e — e o mesmo comando que funcionava na consola dizia que o
        // ficheiro nao existia.
        if ($caminho !== '' && !is_file($caminho) && is_file(base_path($caminho))) {
            $caminho = base_path($caminho);
        }

        if (!$caminho || !is_file($caminho)) {
            $this->error('Ficheiro não encontrado: ' . $caminho);

            return self::FAILURE;
        }

        $linhas = $this->lerCsv($caminho);

        if (!$linhas) {
            $this->error('O CSV não tem linhas legíveis.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(' Armazém: ' . $this->option('armazem') . '   Linhas no ficheiro: ' . count($linhas));
        $this->line($aplicar ? ' MODO: --aplicar — VAI GRAVAR.' : ' MODO: simulação — nada é gravado.');
        $this->line(str_repeat('=', 62));

        // Sem withoutGlobalScopes: o Product não tem scope global de empresa
        // (o filtro por tenant_id é este), e o único que tem é o do soft
        // delete. Destapá-lo faria a importação encontrar artigos da
        // reciclagem e escrever neles em vez de nos que estão no catálogo.
        $jaExistem = Product::query()
            ->where('tenant_id', $empresa->id)
            ->whereIn('barcode', array_column($linhas, 'codigo_barras'))
            ->pluck('id', 'barcode');

        if ($this->option('verificar')) {
            return $this->verificar($linhas, $empresa);
        }

        // Sincronizar é o caso de uma contagem nova do mesmo armazém: a folha
        // manda, e uma quantidade que desceu a zero TEM de descer a zero. O
        // --so-stock simples ignora as linhas a zero — serve para lançar um
        // armazém novo, onde zero quer dizer "não há nada a lançar", e aqui
        // quer dizer o contrário: "vendeu-se tudo".
        $sincronizar = (bool) $this->option('sincronizar');
        $soStock = $sincronizar || (bool) $this->option('so-stock');
        $comStockNoFicheiro = count(array_filter($linhas, fn ($l) => $l['quantidade'] > 0));

        if ($sincronizar) {
            $this->line(sprintf('  artigos encontrados:  %d', $jaExistem->count()));
            $this->line(sprintf('  não existem (ignorados): %d', count($linhas) - $jaExistem->count()));
            $this->line(sprintf('  com quantidade > 0:   %d', $comStockNoFicheiro));

            // Quantas linhas de stock deste armazém a folha manda pôr a zero.
            // É o número que interessa ver ANTES: é o único que apaga stock.
            $armazemActual = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $empresa->id)
                ->where('name', trim((string) $this->option('armazem')))
                ->first();

            $this->line(sprintf('  a pôr a zero:         %d',
                $armazemActual ? $this->aZerar($linhas, $empresa, $armazemActual) : 0));
        } elseif ($soStock) {
            // Só stock: o catálogo e os preços são os que já lá estão. Serve
            // para lançar um segundo armazém sem que a folha nova mande nos
            // preços que alguém já afinou no sistema.
            $this->line(sprintf('  artigos encontrados:  %d', $jaExistem->count()));
            $this->line(sprintf('  não existem (ignorados): %d', count($linhas) - $jaExistem->count()));
            $this->line(sprintf('  com stock a lançar:   %d', $comStockNoFicheiro));
        } else {
            $this->line(sprintf('  artigos novos:        %d', count($linhas) - $jaExistem->count()));
            $this->line(sprintf('  artigos a actualizar: %d', $jaExistem->count()));
            $this->line(sprintf('  com stock a lançar:   %d', $comStockNoFicheiro));
        }

        if (!$aplicar) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $armazem = $this->armazem($empresa);
        $criados = 0;
        $actualizados = 0;
        $comStock = 0;

        // Em lotes e não tudo numa transacção: com milhares de linhas, uma
        // transacção única mantém a tabela trancada durante todo o processo e
        // pára a loja. Cada lote fecha-se sozinho, e repetir o comando retoma
        // de onde ficou — é idempotente.
        $ignorados = 0;
        $zerados = 0;

        foreach (array_chunk($linhas, 200) as $lote) {
            DB::transaction(function () use ($lote, $empresa, $armazem, $soStock, $sincronizar, &$criados, &$actualizados, &$comStock, &$ignorados, &$zerados) {
                foreach ($lote as $l) {
                    $produto = Product::query()->firstOrNew([
                        'tenant_id' => $empresa->id,
                        'barcode'   => $l['codigo_barras'],
                    ]);

                    $novo = !$produto->exists;

                    if ($soStock) {
                        if ($novo) {
                            $ignorados++;

                            continue;
                        }

                        $actualizados++;
                    } else {
                        $produto->name = $l['descricao'];
                        $produto->cost = $l['preco_compra'];
                        $produto->price = $l['preco_venda'];
                        $produto->tenant_id = $empresa->id;

                        // O `code` é obrigatório e único por empresa; sem outro
                        // critério, o código de barras serve-lhe de valor.
                        if (empty($produto->code)) {
                            $produto->code = $l['codigo_barras'];
                        }

                        if (empty($produto->sku)) {
                            $produto->sku = $l['codigo_barras'];
                        }

                        // Um artigo novo nasce com o regime fiscal da empresa.
                        // Sem isto, uma empresa em não sujeição ficava com o
                        // catálogo todo a liquidar IVA — a folha importada não
                        // traz regime nenhum, e o valor por omissão da tabela é
                        // "iva". Só nos artigos NOVOS: num que já existe, a
                        // isenção pode ter sido decidida artigo a artigo.
                        if ($novo) {
                            $this->aplicarRegime($produto, $empresa);
                        }

                        $produto->save();

                        $novo ? $criados++ : $actualizados++;
                    }

                    // A sincronizar, zero é um valor e não uma ausência: se a
                    // folha diz zero e há linha de stock, ela vai a zero.
                    // Criar uma linha nova a zero não serve para nada.
                    if ($l['quantidade'] <= 0 && !$sincronizar) {
                        continue;
                    }

                    if ($l['quantidade'] <= 0 && $sincronizar) {
                        $linha = Stock::withoutGlobalScopes()
                            ->where('tenant_id', $empresa->id)
                            ->where('warehouse_id', $armazem->id)
                            ->where('product_id', $produto->id)
                            ->first();

                        if ($linha && (float) $linha->quantity != 0.0) {
                            $linha->quantity = 0;
                            $linha->save();
                            $zerados++;
                        }

                        continue;
                    }

                    // Pelo modelo, para o observador manter o agregado certo.
                    $stock = Stock::withoutGlobalScopes()->firstOrNew([
                        'tenant_id'    => $empresa->id,
                        'warehouse_id' => $armazem->id,
                        'product_id'   => $produto->id,
                    ]);

                    $stock->tenant_id = $empresa->id;
                    $stock->warehouse_id = $armazem->id;
                    $stock->product_id = $produto->id;
                    $stock->quantity = $l['quantidade'];
                    $stock->unit_cost = $l['preco_compra'];
                    $stock->save();

                    $comStock++;
                }
            });

            $this->output->write('.');
        }

        $this->newLine(2);

        if ($sincronizar) {
            $this->info(sprintf('  ✓ %d linhas com quantidade nova e %d postas a zero no armazém "%s". %d ignorados por não existirem.',
                $comStock, $zerados, $armazem->name, $ignorados));
        } elseif ($soStock) {
            $this->info(sprintf('  ✓ %d linhas de stock no armazém "%s". %d artigos ignorados por não existirem.',
                $comStock, $armazem->name, $ignorados));
        } else {
            $this->info(sprintf('  ✓ %d artigos criados, %d actualizados, %d com stock no armazém "%s".',
                $criados, $actualizados, $comStock, $armazem->name));
        }

        return self::SUCCESS;
    }

    /**
     * Põe no artigo o regime fiscal da empresa.
     *
     * A mesma regra do TaxRegimeSyncer, que é quem manda: numa empresa em não
     * sujeição o artigo sai isento com o motivo obrigatório; nas outras leva a
     * taxa por omissão da empresa.
     */
    private function aplicarRegime(Product $produto, Tenant $empresa): void
    {
        $meta = $empresa->regimeMeta();

        if (!empty($meta['exempt'])) {
            $produto->tax_type = 'isento';
            $produto->tax_rate_id = null;
            $produto->exemption_reason = $meta['exemption_code'];

            return;
        }

        $produto->tax_type = 'iva';
        $produto->exemption_reason = null;
        $produto->tax_rate_id = $this->taxaPorOmissao($empresa, $meta);
    }

    /** A taxa da empresa para o regime; null se ainda não estiver semeada. */
    private function taxaPorOmissao(Tenant $empresa, array $meta): ?int
    {
        static $cache = [];

        return $cache[$empresa->id] ??= \App\Models\Invoicing\Tax::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('code', $meta['tax_code'])
            ->value('id');
    }

    /**
     * Compara a folha com o que está na base. Só lê.
     *
     * Depois de uma importação o comando diz o que FEZ. Isto diz o que ESTÁ,
     * que não é a mesma coisa — uma linha que falhou em silêncio só aparece
     * numa contagem feita do outro lado.
     */
    private function verificar(array $linhas, Tenant $empresa): int
    {
        $armazem = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('name', trim((string) $this->option('armazem')))
            ->first();

        if (!$armazem) {
            $this->error('Armazém não encontrado: ' . $this->option('armazem'));

            return self::FAILURE;
        }

        $produtos = Product::query()
            ->where('tenant_id', $empresa->id)
            ->whereIn('barcode', array_column($linhas, 'codigo_barras'))
            ->pluck('id', 'barcode');

        $stock = Stock::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('warehouse_id', $armazem->id)
            ->whereIn('product_id', $produtos->values())
            ->pluck('quantity', 'product_id');

        $batem = 0;
        $semArtigo = 0;
        $diferencas = [];

        foreach ($linhas as $l) {
            $id = $produtos->get($l['codigo_barras']);

            if (!$id) {
                $semArtigo++;

                continue;
            }

            // Sem linha de stock e a folha a dizer zero é o mesmo que zero.
            $naBase = (float) ($stock->get($id) ?? 0);

            if (abs($naBase - (float) $l['quantidade']) < 0.001) {
                $batem++;

                continue;
            }

            $diferencas[] = [$l['codigo_barras'], mb_substr($l['descricao'], 0, 32), $l['quantidade'], $naBase];
        }

        $this->newLine();
        $this->line(sprintf('  batem certo:        %d', $batem));
        $this->line(sprintf('  diferentes:         %d', count($diferencas)));
        $this->line(sprintf('  sem artigo na base: %d', $semArtigo));
        $this->newLine();

        if ($diferencas) {
            $this->table(
                ['código', 'descrição', 'na folha', 'na base'],
                array_slice($diferencas, 0, 25)
            );

            if (count($diferencas) > 25) {
                $this->warn('  (mostradas as primeiras 25 de ' . count($diferencas) . ')');
            }

            return self::FAILURE;
        }

        $this->info('  ✓ A base diz exactamente o que a folha diz.');

        return self::SUCCESS;
    }

    /**
     * Quantas linhas de stock deste armazém a folha manda pôr a zero.
     *
     * Só conta as que TÊM alguma coisa agora: uma linha já a zero não é uma
     * baixa, e contá-la dava um número assustador na simulação sem nada por
     * trás.
     */
    private function aZerar(array $linhas, Tenant $empresa, Warehouse $armazem): int
    {
        $codigos = array_column(array_filter($linhas, fn ($l) => $l['quantidade'] <= 0), 'codigo_barras');

        if (!$codigos) {
            return 0;
        }

        $ids = Product::query()
            ->where('tenant_id', $empresa->id)
            ->whereIn('barcode', $codigos)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return Stock::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('warehouse_id', $armazem->id)
            ->whereIn('product_id', $ids)
            ->where('quantity', '<>', 0)
            ->count();
    }

    /** O armazém, criado se não existir. */
    private function armazem(Tenant $empresa): Warehouse
    {
        $nome = trim((string) $this->option('armazem'));

        $armazem = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('name', $nome)
            ->first();

        if ($armazem) {
            $this->line("  armazém \"{$nome}\" já existia (#{$armazem->id}).");

            return $armazem;
        }

        $armazem = Warehouse::create([
            'tenant_id' => $empresa->id,
            'name'      => $nome,
            'code'      => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nome) ?: 'LOJA', 0, 10)),
            'is_active' => true,
        ]);

        $this->info("  armazém \"{$nome}\" criado (#{$armazem->id}).");

        return $armazem;
    }

    /** @return array<int,array{codigo_barras:string,descricao:string,preco_compra:float,preco_venda:float,quantidade:float}> */
    private function lerCsv(string $caminho): array
    {
        $f = fopen($caminho, 'r');
        $cabecalho = fgetcsv($f);
        $linhas = [];

        while (($l = fgetcsv($f)) !== false) {
            $r = @array_combine($cabecalho, $l);

            if (!$r || empty($r['codigo_barras']) || empty($r['descricao'])) {
                continue;
            }

            $linhas[] = [
                'codigo_barras' => trim($r['codigo_barras']),
                'descricao'     => trim($r['descricao']),
                'preco_compra'  => (float) $r['preco_compra'],
                'preco_venda'   => (float) $r['preco_venda'],
                'quantidade'    => (float) $r['quantidade'],
            ];
        }

        fclose($f);

        return $linhas;
    }
}
