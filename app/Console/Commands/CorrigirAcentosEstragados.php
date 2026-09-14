<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\AcentosEstragados;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * REPARA OS ACENTOS ESTRAGADOS NAS FICHAS — «├üCIDO F├ôLICO» → «ÁCIDO FÓLICO».
 *
 * A importação da farmácia (base antiga SMA) trouxe os nomes lidos na página
 * de código do DOS. A regra de reparação está em App\Support\AcentosEstragados,
 * e só toca em sequências com a forma exacta de uma letra estragada.
 *
 * POR OMISSÃO SÓ MOSTRA: quantas fichas mudam em cada tabela e empresa, uns
 * exemplos «antes → depois», e o que ficou por reparar (para alguém olhar à
 * mão). Grava apenas com --aplicar, ficha a ficha pelo modelo — o registo de
 * actividade dos artigos e a trilha de auditoria ficam com o antes e o depois.
 *
 * O QUE NUNCA TOCA: os documentos emitidos. A linha de uma factura guarda o
 * nome do artigo como estava no dia, assinado e comunicado à AGT — mudá-lo
 * partia a cadeia de assinaturas. Nem o `slug` das categorias e marcas, que é
 * morada.
 */
class CorrigirAcentosEstragados extends Command
{
    protected $signature = 'acentos:corrigir
        {--empresa= : Só esta empresa (id); por omissão, todas}
        {--tabelas=produtos,categorias,marcas,armazens,fornecedores,clientes : Que fichas ver}
        {--exemplos=20 : Quantos exemplos «antes → depois» mostrar}
        {--aplicar : Grava as correcções (sem isto, só mostra)}';

    protected $description = 'Repara os nomes com acentos estragados pela importação (├ü → Á); só mostra, sem --aplicar';

    /** @var array<string, array{0: class-string<Model>, 1: string[]}> */
    private const TABELAS = [
        'produtos' => [Product::class, ['name', 'description']],
        'categorias' => [Category::class, ['name', 'description']],
        'marcas' => [Brand::class, ['name', 'description']],
        'armazens' => [Warehouse::class, ['name', 'description', 'location', 'address', 'city']],
        'fornecedores' => [Supplier::class, ['name', 'address', 'city', 'province', 'notes']],
        'clientes' => [Client::class, ['name', 'address', 'city', 'province', 'notes']],
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $empresa = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;
        $quantosExemplos = max(0, (int) $this->option('exemplos'));
        $pedidas = array_filter(array_map('trim', explode(',', (string) $this->option('tabelas'))));

        $desconhecidas = array_diff($pedidas, array_keys(self::TABELAS));
        if ($desconhecidas !== []) {
            $this->error('Tabelas desconhecidas: ' . implode(', ', $desconhecidas) . '. Há: ' . implode(', ', array_keys(self::TABELAS)));

            return self::INVALID;
        }

        $this->info($aplicar ? 'A CORRIGIR (grava).' : 'SIMULAÇÃO — nada é gravado. Para gravar: --aplicar');
        $this->line('Empresa: ' . ($empresa ?? 'todas'));
        $this->newLine();

        $resumo = [];
        $exemplos = [];
        $porOlhar = [];
        $falhas = [];
        $totalMudam = 0;

        foreach ($pedidas as $nome) {
            [$classe, $colunas] = self::TABELAS[$nome];
            /** @var Model $modelo */
            $modelo = new $classe();
            $colunas = array_values(array_filter($colunas, fn ($c) => Schema::hasColumn($modelo->getTable(), $c)));

            if ($colunas === []) {
                continue;
            }

            $consulta = $classe::query()->withoutGlobalScopes()
                ->select(array_merge([$modelo->getKeyName(), 'tenant_id'], $colunas))
                ->when($empresa !== null, fn ($q) => $q->where('tenant_id', $empresa));

            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($classe), true)) {
                $consulta->withTrashed();
            }

            $consulta->chunkById(500, function ($linhas) use ($nome, $colunas, $aplicar, $quantosExemplos, $classe, &$resumo, &$exemplos, &$porOlhar, &$falhas, &$totalMudam) {
                foreach ($linhas as $linha) {
                    $mudancas = [];

                    foreach ($colunas as $coluna) {
                        $antes = $linha->getAttribute($coluna);

                        if (! is_string($antes) || $antes === '') {
                            continue;
                        }

                        $depois = AcentosEstragados::reparar($antes);

                        if ($depois !== $antes) {
                            $mudancas[$coluna] = [$antes, $depois];
                        }

                        if (AcentosEstragados::precisaDeOlhos($depois)) {
                            $porOlhar[] = [$nome, $linha->tenant_id, $linha->getKey(), $coluna, mb_strimwidth($depois, 0, 70, '…')];
                        }
                    }

                    if ($mudancas === []) {
                        continue;
                    }

                    $chave = $nome . '|' . $linha->tenant_id;
                    $resumo[$chave] = ($resumo[$chave] ?? 0) + 1;
                    $totalMudam++;

                    foreach ($mudancas as $coluna => [$antes, $depois]) {
                        if (count($exemplos) < $quantosExemplos) {
                            $exemplos[] = [$nome, $linha->tenant_id, $linha->getKey(), $coluna, mb_strimwidth($antes, 0, 50, '…'), mb_strimwidth($depois, 0, 50, '…')];
                        }
                    }

                    if (! $aplicar) {
                        continue;
                    }

                    try {
                        // Pelo modelo inteiro (e não pela linha parcial da consulta):
                        // os observadores e a trilha de auditoria vêem o antes e o depois.
                        $ficha = $classe::query()->withoutGlobalScopes()
                            ->when(method_exists($classe, 'withTrashed'), fn ($q) => $q->withTrashed())
                            ->find($linha->getKey());

                        if (! $ficha) {
                            continue;
                        }

                        foreach ($mudancas as $coluna => [, $depois]) {
                            $ficha->setAttribute($coluna, $depois);
                        }

                        $ficha->save();
                    } catch (\Throwable $e) {
                        $falhas[] = [$nome, $linha->tenant_id, $linha->getKey(), mb_strimwidth($e->getMessage(), 0, 90, '…')];
                    }
                }
            });
        }

        if ($totalMudam === 0) {
            $this->info('Não há acentos estragados nas fichas escolhidas.');
        } else {
            $this->table(['Fichas', 'Empresa', 'A corrigir'], collect($resumo)->map(function ($n, $chave) {
                [$tabela, $tenant] = explode('|', $chave);

                return [$tabela, $tenant, $n];
            })->values()->all());

            $this->line(($aplicar ? 'Corrigidas: ' : 'Mudariam: ') . $totalMudam . ' ficha(s).');
        }

        if ($exemplos !== []) {
            $this->newLine();
            $this->line('Exemplos:');
            $this->table(['Fichas', 'Empresa', 'Id', 'Campo', 'Antes', 'Depois'], $exemplos);
        }

        if ($porOlhar !== []) {
            $this->newLine();
            $this->warn(count($porOlhar) . ' campo(s) com restos que não se sabem reparar sozinhos — ver à mão:');
            $this->table(['Fichas', 'Empresa', 'Id', 'Campo', 'Como fica'], array_slice($porOlhar, 0, 50));
        }

        if ($falhas !== []) {
            $this->newLine();
            $this->error(count($falhas) . ' ficha(s) não gravaram:');
            $this->table(['Fichas', 'Empresa', 'Id', 'Erro'], array_slice($falhas, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
