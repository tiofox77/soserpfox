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

        $jaExistem = Product::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->whereIn('barcode', array_column($linhas, 'codigo_barras'))
            ->pluck('id', 'barcode');

        $this->line(sprintf('  artigos novos:        %d', count($linhas) - $jaExistem->count()));
        $this->line(sprintf('  artigos a actualizar: %d', $jaExistem->count()));
        $this->line(sprintf('  com stock a lançar:   %d', count(array_filter($linhas, fn ($l) => $l['quantidade'] > 0))));

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
        foreach (array_chunk($linhas, 200) as $lote) {
            DB::transaction(function () use ($lote, $empresa, $armazem, &$criados, &$actualizados, &$comStock) {
                foreach ($lote as $l) {
                    $produto = Product::withoutGlobalScopes()->firstOrNew([
                        'tenant_id' => $empresa->id,
                        'barcode'   => $l['codigo_barras'],
                    ]);

                    $novo = !$produto->exists;

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

                    $produto->save();

                    $novo ? $criados++ : $actualizados++;

                    if ($l['quantidade'] <= 0) {
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
        $this->info(sprintf('  ✓ %d artigos criados, %d actualizados, %d com stock no armazém "%s".',
            $criados, $actualizados, $comStock, $armazem->name));

        return self::SUCCESS;
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
