<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige o código de barras de artigos já importados, sem os recriar.
 *
 * Uma importação levou códigos errados: o que estava impresso não era o
 * código do produto mas o envelope GS1-128 à volta dele (AI(01) + GTIN-14),
 * e o "01" da frente foi parar ao código de barras.
 *
 * Reimportar não resolve — a importação liga-se pelo código de barras, por
 * isso um código corrigido cria um artigo NOVO e deixa o errado para trás,
 * com o stock e o histórico agarrados ao errado. Tem de ser uma renomeação
 * no artigo que já existe.
 *
 * SIMULAÇÃO POR OMISSÃO, como a importação: sem --aplicar diz o que faria.
 *
 * O ficheiro é o JSON [{"de":"0108...","para":"8904...","descricao":"..."}].
 *
 *   php artisan artigos:corrigir-codigo --tenant=57 --ficheiro=storage/app/correccao.json
 *   php artisan artigos:corrigir-codigo --tenant=57 --ficheiro=... --aplicar
 */
class CorrigirCodigoDeBarras extends Command
{
    protected $signature = 'artigos:corrigir-codigo
                            {--tenant= : id da empresa}
                            {--ficheiro= : JSON com os pares de/para}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Corrige o código de barras de artigos já importados (simulação por omissão)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $caminho = (string) $this->option('ficheiro');

        if ($caminho !== '' && !is_file($caminho) && is_file(base_path($caminho))) {
            $caminho = base_path($caminho);
        }

        if (!$caminho || !is_file($caminho)) {
            $this->error('Ficheiro não encontrado: ' . $caminho);

            return self::FAILURE;
        }

        $pares = json_decode((string) file_get_contents($caminho), true);

        if (!is_array($pares) || !$pares) {
            $this->error('O ficheiro não tem pares legíveis.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(' Pares no ficheiro: ' . count($pares));
        $this->line($aplicar ? ' MODO: --aplicar — VAI GRAVAR.' : ' MODO: simulação — nada é gravado.');
        $this->line(str_repeat('=', 62));

        // Product não tem scope global de empresa — o filtro por tenant_id é
        // manual. O único scope global que tem é o do soft delete, por isso
        // withoutGlobalScopes() aqui não alargaria a empresa nenhuma: só
        // traria artigos da reciclagem, que não se querem nem ler nem
        // escrever. Renomear um artigo apagado dava um "✓ corrigido" a
        // mentir, com o artigo vivo a ficar com o código errado.
        $vivos = Product::query()
            ->where('tenant_id', $empresa->id)
            ->get(['id', 'barcode', 'code']);

        // Por código de barras, TODAS as linhas — o campo não tem índice
        // único, e o balcão pode ter criado à mão um artigo com o mesmo
        // código. Guardar só uma escondia as outras do relatório.
        $porCodigo = $vivos->groupBy('barcode');

        // O índice único da tabela é (tenant_id, code), não o barcode. É
        // contra este que a gravação rebenta, por isso é este que se testa.
        $codeOcupado = $vivos->pluck('id', 'code');

        $aMudar = [];
        $inexistente = 0;
        $jaCerto = 0;
        $colisao = [];
        $codePreso = [];
        $repetidos = 0;

        foreach ($pares as $par) {
            $de = (string) ($par['de'] ?? '');
            $para = (string) ($par['para'] ?? '');

            if ($de === '' || $para === '' || $de === $para) {
                continue;
            }

            if (!$porCodigo->has($de)) {
                // Nunca foi importado com o código errado; a importação
                // normal cria-o com o certo. Não é problema.
                $porCodigo->has($para) ? $jaCerto++ : $inexistente++;

                continue;
            }

            if ($porCodigo->has($para)) {
                // Já existe outro artigo com o código de destino. Renomear
                // aqui daria dois artigos com o mesmo código; fica de fora
                // e é reportado, para se decidir à mão qual deles vale.
                $colisao[] = $par;

                continue;
            }

            $achados = $porCodigo->get($de);

            if ($achados->count() > 1) {
                $repetidos++;
            }

            foreach ($achados as $artigo) {
                // O code de destino pertence a outro artigo: gravar violava
                // o índice único e rebentava a transacção do lote inteiro.
                $dono = $codeOcupado->get($para);

                if ($dono !== null && $dono !== $artigo->id) {
                    $codePreso[] = $par;

                    continue;
                }

                $aMudar[] = ['id' => $artigo->id, 'de' => $de, 'para' => $para];
            }
        }

        $this->line(sprintf('  a renomear:                 %d', count($aMudar)));
        $this->line(sprintf('  já com o código certo:      %d', $jaCerto));
        $this->line(sprintf('  não existem nesta empresa:  %d', $inexistente));
        $this->line(sprintf('  colisões (destino ocupado): %d', count($colisao)));
        $this->line(sprintf('  código de origem repetido:  %d', $repetidos));
        $this->line(sprintf('  code de destino preso:      %d', count($codePreso)));

        foreach (array_slice(array_merge($colisao, $codePreso), 0, 10) as $c) {
            $this->warn(sprintf('     %s -> %s  %s', $c['de'], $c['para'], $c['descricao'] ?? ''));
        }

        if (!$aplicar) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $feitos = 0;

        foreach (array_chunk($aMudar, 200) as $lote) {
            DB::transaction(function () use ($lote, $empresa, &$feitos) {
                foreach ($lote as $m) {
                    // Também aqui sem withoutGlobalScopes: escrever num
                    // artigo da reciclagem contava-se como corrigido e
                    // deixava o artigo vivo com o código errado.
                    $produto = Product::query()
                        ->where('tenant_id', $empresa->id)
                        ->find($m['id']);

                    if (!$produto) {
                        continue;
                    }

                    $produto->barcode = $m['para'];

                    // A importação pôs o código de barras também no `code` e
                    // no `sku`; onde ficou o errado, corrige-se igual.
                    if ($produto->code === $m['de']) {
                        $produto->code = $m['para'];
                    }

                    if ($produto->sku === $m['de']) {
                        $produto->sku = $m['para'];
                    }

                    $produto->save();
                    $feitos++;
                }
            });

            $this->output->write('.');
        }

        $this->newLine(2);
        $this->info(sprintf('  ✓ %d códigos de barras corrigidos.', $feitos));

        return self::SUCCESS;
    }
}
