<?php

namespace App\Services\Tenants;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Limpa tudo o que pertence a uma empresa que está a ser eliminada.
 *
 * A cascata escrita à mão no modelo tratava OITO tabelas. Medido nesta base,
 * 142 têm `tenant_id` — as outras 134 ficavam com linhas a apontar para uma
 * empresa que já não existe. Invisíveis, porque os filtros por empresa nunca
 * mais as devolvem, e lá para sempre.
 *
 * PORQUE É DERIVADO DO ESQUEMA E NÃO ESCRITO À MÃO
 * ------------------------------------------------
 * Uma lista de 142 nomes num ficheiro fica desactualizada na primeira migração
 * que acrescente uma tabela — e ninguém dá por isso, porque o sintoma é
 * silencioso. A lista é lida do próprio esquema: toda a tabela com uma coluna
 * `tenant_id` entra, e uma tabela nova entra sozinha no dia em que nascer.
 *
 * A ORDEM
 * -------
 * Há chaves estrangeiras entre estas tabelas, e apagar pela ordem errada
 * falha. Em vez de desligar a verificação de integridade — que esconderia
 * exactamente os erros que interessa ver — faz-se por PASSAGENS: tenta-se
 * apagar de todas, as que falharem por dependência ficam para a passagem
 * seguinte, e repete-se enquanto houver progresso. Converge em poucas
 * passagens e nunca deixa a base sem verificação.
 */
class EliminacaoDeEmpresa
{
    /**
     * Tabelas que NÃO se limpam, e porquê.
     */
    private const NAO_LIMPAR = [
        // A própria empresa: quem apaga é o Eloquent, e apagá-la aqui deixava
        // o modelo a operar sobre uma linha que já não existe.
        'tenants',

        // Append-only e encadeada por hash. É o registo de que a empresa
        // existiu e do que lá se fez — precisamente o que não se deita fora
        // quando se apaga alguma coisa. Uma empresa só é apagável sem
        // actividade, portanto são poucas linhas de provisionamento.
        'audit_trail',
        'audit_trail_archive',

        // Tabelas do próprio Laravel, que têm tenant_id por acaso ou não são
        // dados de negócio.
        'migrations',
        'jobs',
        'failed_jobs',
        'sessions',
        'cache',
        'cache_locks',
    ];

    /** Quantas passagens antes de desistir. Cinco chega para qualquer cadeia real. */
    private const MAX_PASSAGENS = 8;

    /**
     * Todas as tabelas com coluna `tenant_id`, menos as que não se limpam.
     *
     * @return array<int, string>
     */
    public static function tabelasDaEmpresa(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $tabelas = [];

        foreach (DB::select('SHOW TABLES') as $linha) {
            $tabela = array_values((array) $linha)[0];

            if (in_array($tabela, self::NAO_LIMPAR, true)) {
                continue;
            }

            if (Schema::hasColumn($tabela, 'tenant_id')) {
                $tabelas[] = $tabela;
            }
        }

        sort($tabelas);

        return $cache = $tabelas;
    }

    /**
     * Apaga as linhas desta empresa em todas as tabelas.
     *
     * NÃO abre transacção: é chamado de dentro da que o modelo já abriu, e
     * abrir outra aqui daria um savepoint que confunde a reversão.
     *
     * @return array{apagadas:array<string,int>, por_apagar:array<string,string>, passagens:int}
     */
    public static function limpar(int $tenantId): array
    {
        $porFazer  = self::tabelasDaEmpresa();
        $apagadas  = [];
        $ultimoErro = [];
        $passagem  = 0;

        while (!empty($porFazer) && $passagem < self::MAX_PASSAGENS) {
            $passagem++;
            $ficaramParaDepois = [];
            $houveProgresso    = false;

            foreach ($porFazer as $tabela) {
                try {
                    $n = DB::table($tabela)->where('tenant_id', $tenantId)->delete();

                    if ($n > 0) {
                        $apagadas[$tabela] = ($apagadas[$tabela] ?? 0) + $n;
                    }

                    $houveProgresso = true;
                    unset($ultimoErro[$tabela]);
                } catch (\Throwable $e) {
                    // Quase sempre uma dependência que ainda não foi limpa.
                    // Fica para a passagem seguinte.
                    $ficaramParaDepois[]   = $tabela;
                    $ultimoErro[$tabela]   = $e->getMessage();
                }
            }

            // Sem progresso nenhum numa passagem inteira, mais passagens não
            // vão adiantar — para-se e diz-se o que ficou.
            if (!$houveProgresso) {
                break;
            }

            $porFazer = $ficaramParaDepois;
        }

        $porApagar = [];

        foreach ($porFazer as $tabela) {
            $porApagar[$tabela] = $ultimoErro[$tabela] ?? 'não foi possível apagar';
        }

        if (!empty($porApagar)) {
            // Não se lança: a empresa já foi limpa quase toda, e rebentar aqui
            // desfazia tudo e deixava o problema por resolver. Fica registado
            // com o nome das tabelas e o motivo, que é o que permite arranjá-lo.
            Log::warning('Eliminação de empresa: tabelas por limpar', [
                'tenant_id' => $tenantId,
                'tabelas'   => $porApagar,
            ]);
        }

        Log::info('Eliminação de empresa: limpeza concluída', [
            'tenant_id'      => $tenantId,
            'tabelas_limpas' => count($apagadas),
            'linhas'         => array_sum($apagadas),
            'passagens'      => $passagem,
            'por_limpar'     => count($porApagar),
        ]);

        return [
            'apagadas'   => $apagadas,
            'por_apagar' => $porApagar,
            'passagens'  => $passagem,
        ];
    }

    /**
     * Quantas linhas esta empresa tem espalhadas pelas tabelas.
     *
     * Para se ver o que uma eliminação apagaria, sem apagar nada.
     *
     * @return array<string,int>
     */
    public static function contar(int $tenantId): array
    {
        $contagem = [];

        foreach (self::tabelasDaEmpresa() as $tabela) {
            try {
                $n = DB::table($tabela)->where('tenant_id', $tenantId)->count();

                if ($n > 0) {
                    $contagem[$tabela] = $n;
                }
            } catch (\Throwable) {
                // Tabela ilegível: não conta, e não estraga a contagem das outras.
            }
        }

        arsort($contagem);

        return $contagem;
    }
}
