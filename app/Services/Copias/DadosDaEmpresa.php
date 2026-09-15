<?php

namespace App\Services\Copias;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * OS DADOS DE UMA EMPRESA — exportar para um ficheiro e repor a partir dele.
 *
 * O FICHEIRO (.jsonl.gz, uma linha JSON por registo, comprimido):
 *   {"soserp":"copia-empresa","versao":1,"tenant_id":17,"criada_em":"…","esquema":"<última migração>"}
 *   {"tabela":"invoicing_products","colunas":["id","tenant_id",…],"binarias":[…]}
 *   [1,17,"Paracetamol",…]            ← uma linha por registo, na ordem das colunas
 *   …
 *   {"fim":true,"linhas":12345,"assinatura":"<HMAC-SHA256>"}
 *
 * A ASSINATURA é um HMAC de todo o conteúdo com uma chave que sai da APP_KEY e
 * do id da empresa. Um ficheiro alterado à mão, ou gerado para OUTRA empresa, é
 * recusado ao repor: sem isto, quem carregasse um ficheiro montado podia enfiar
 * linhas com ids de facturas de outra empresa na base partilhada.
 *
 * REPOR é uma transacção: apagam-se os dados actuais da empresa (as filhas
 * primeiro) e metem-se os da cópia. Só as colunas que existem nos dois lados —
 * uma cópia de antes de uma migração repõe-se na mesma, com as colunas novas no
 * valor por omissão. E recusa-se quando a empresa emitiu documentos fiscais
 * DEPOIS da cópia: apagá-los seria destruir documentos já entregues a clientes
 * e comunicados à AGT.
 */
class DadosDaEmpresa
{
    private const BLOCO = 1000;

    /** Os documentos que, emitidos depois da cópia, impedem o restauro. */
    public const FISCAIS = [
        'invoicing_sales_invoices' => 'Facturas e facturas-recibo',
        'invoicing_credit_notes' => 'Notas de crédito',
        'invoicing_debit_notes' => 'Notas de débito',
        'invoicing_receipts' => 'Recibos',
        'invoicing_transport_guides' => 'Guias de transporte',
    ];

    /* ════════ Exportar ════════ */

    public function exportar(int $tenantId, string $destino): array
    {
        Tenant::findOrFail($tenantId);
        $gz = gzopen($destino, 'wb6');
        if (! $gz) {
            throw new RuntimeException('Não foi possível criar o ficheiro da cópia.');
        }

        $hmac = hash_init('sha256', HASH_HMAC, self::chave($tenantId));
        $escrever = function (array $linha) use ($gz, $hmac) {
            $json = json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
            hash_update($hmac, $json);
            gzwrite($gz, $json);
        };

        $escrever([
            'soserp' => 'copia-empresa', 'versao' => 1, 'tenant_id' => $tenantId,
            'criada_em' => now()->toIso8601String(), 'esquema' => DB::table('migrations')->max('migration'),
        ]);

        $resumo = [];
        $total = 0;

        foreach (MapaDaEmpresa::todas() as $tabela) {
            $tipos = MapaDaEmpresa::colunas($tabela);
            $colunas = array_keys($tipos);
            $binarias = array_values(array_filter($colunas, fn ($c) => in_array($tipos[$c], ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary'], true)));

            $consulta = MapaDaEmpresa::consulta($tabela, $tenantId);
            if (! $consulta->exists()) {
                continue;
            }

            $escrever(['tabela' => $tabela, 'colunas' => $colunas, 'binarias' => $binarias]);
            $n = 0;
            $temId = in_array('id', $colunas, true);

            $emitir = function ($linhas) use ($escrever, $colunas, $binarias, &$n) {
                foreach ($linhas as $l) {
                    $l = (array) $l;
                    foreach ($binarias as $b) {
                        $l[$b] = $l[$b] === null ? null : base64_encode($l[$b]);
                    }
                    $escrever(array_map(fn ($c) => $l[$c] ?? null, $colunas));
                    $n++;
                }
            };

            if ($temId) {
                $consulta->orderBy('id')->chunkById(self::BLOCO, $emitir, 'id');
            } else {
                $pos = 0;
                do {
                    $bloco = (clone $consulta)->offset($pos)->limit(self::BLOCO)->get();
                    $emitir($bloco);
                    $pos += self::BLOCO;
                } while ($bloco->count() === self::BLOCO);
            }

            $resumo[$tabela] = $n;
            $total += $n;
        }

        $assinatura = hash_final($hmac);
        gzwrite($gz, json_encode(['fim' => true, 'linhas' => $total, 'assinatura' => $assinatura]) . "\n");
        gzclose($gz);

        return ['tabelas' => count($resumo), 'linhas' => $total, 'por_tabela' => $resumo];
    }

    /* ════════ Ler e conferir ════════ */

    /** Confere o cabeçalho, a assinatura e a empresa, sem repor nada. */
    public function conferir(string $ficheiro, int $tenantId): array
    {
        $cabecalho = null;
        $fim = null;
        $hmac = hash_init('sha256', HASH_HMAC, self::chave($tenantId));
        $tabelas = [];
        $atual = null;

        $this->ler($ficheiro, function (string $cru, $linha) use (&$cabecalho, &$fim, $hmac, &$tabelas, &$atual) {
            if (is_array($linha) && isset($linha['fim'])) {
                $fim = $linha;

                return;
            }
            hash_update($hmac, $cru);
            if ($cabecalho === null) {
                $cabecalho = $linha;
            } elseif (is_array($linha) && isset($linha['tabela'])) {
                $atual = $linha['tabela'];
                $tabelas[$atual] = 0;
            } elseif ($atual !== null) {
                $tabelas[$atual]++;
            }
        });

        if (($cabecalho['soserp'] ?? null) !== 'copia-empresa') {
            throw new RuntimeException(__('O ficheiro não é uma cópia dos dados de uma empresa do SOSERP.'));
        }
        if ((int) ($cabecalho['tenant_id'] ?? 0) !== $tenantId) {
            throw new RuntimeException(__('Esta cópia é de outra empresa.'));
        }
        if (! $fim || ! hash_equals(hash_final($hmac), (string) ($fim['assinatura'] ?? ''))) {
            throw new RuntimeException(__('A cópia está incompleta ou foi alterada — a assinatura não confere.'));
        }

        return ['cabecalho' => $cabecalho, 'tabelas' => $tabelas, 'linhas' => (int) $fim['linhas']];
    }

    /**
     * Os documentos fiscais emitidos depois da cópia. Com algum, não se repõe.
     *
     * @return array<string, int>
     */
    public function fiscaisDepoisDe(int $tenantId, Carbon $criadaEm): array
    {
        $encontrados = [];
        foreach (self::FISCAIS as $tabela => $rotulo) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            $q = DB::table($tabela)->where('tenant_id', $tenantId)->where('created_at', '>', $criadaEm);
            if (Schema::hasColumn($tabela, 'status')) {
                $q->where('status', '<>', 'draft');
            }
            if (Schema::hasColumn($tabela, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            if ($n = $q->count()) {
                $encontrados[__($rotulo)] = $n;
            }
        }

        if (Schema::hasTable('agt_submissions') && ($n = DB::table('agt_submissions')->where('tenant_id', $tenantId)->where('created_at', '>', $criadaEm)->count())) {
            $encontrados[__('Comunicações à AGT')] = $n;
        }

        return $encontrados;
    }

    /* ════════ Repor ════════ */

    public function repor(string $ficheiro, int $tenantId): array
    {
        $conferido = $this->conferir($ficheiro, $tenantId);
        $criadaEm = Carbon::parse($conferido['cabecalho']['criada_em']);

        if ($fiscais = $this->fiscaisDepoisDe($tenantId, $criadaEm)) {
            throw new RuntimeException(__('Não é possível repor esta cópia: depois dela foram emitidos documentos fiscais (:lista). Apagá-los destruiria documentos já entregues e comunicados. Escolha uma cópia mais recente.', [
                'lista' => collect($fiscais)->map(fn ($n, $r) => "{$r}: {$n}")->implode('; '),
            ]));
        }

        MapaDaEmpresa::esquecer();
        $mapa = MapaDaEmpresa::tabelas();
        $noAmbito = array_flip(MapaDaEmpresa::todas());
        $colunasActuais = [];
        $pessoas = DB::table('users')->pluck('id')->flip();
        $resumo = ['apagadas' => 0, 'repostas' => 0, 'ignoradas' => 0, 'tabelas_desconhecidas' => []];

        DB::transaction(function () use ($ficheiro, $tenantId, $mapa, $noAmbito, &$colunasActuais, $pessoas, &$resumo) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                // 1. Fora com o que há: as filhas antes das mães (a consulta da
                //    filha precisa de ver as mães que ainda lá estão).
                foreach (array_reverse(array_keys($mapa['filhas'])) as $tabela) {
                    $resumo['apagadas'] += MapaDaEmpresa::consulta($tabela, $tenantId)->delete();
                }
                foreach ($mapa['directas'] as $tabela) {
                    $resumo['apagadas'] += DB::table($tabela)->where('tenant_id', $tenantId)->delete();
                }

                // 2. Dentro com a cópia, em blocos.
                $tabela = null;
                $colunas = [];
                $binarias = [];
                $bloco = [];
                $idsDasMaes = [];

                $despejar = function () use (&$bloco, &$tabela, &$resumo) {
                    if ($bloco && $tabela) {
                        DB::table($tabela)->insert($bloco);
                        $resumo['repostas'] += count($bloco);
                    }
                    $bloco = [];
                };

                $this->ler($ficheiro, function (string $cru, $linha, int $n) use (&$tabela, &$colunas, &$binarias, &$bloco, &$colunasActuais, $noAmbito, $mapa, $tenantId, $pessoas, &$resumo, $despejar, &$idsDasMaes) {
                    if ($n === 0 || (is_array($linha) && isset($linha['fim']))) {
                        return;
                    }

                    if (is_array($linha) && isset($linha['tabela'])) {
                        $despejar();
                        $tabela = $linha['tabela'];
                        if (! isset($noAmbito[$tabela]) || ! Schema::hasTable($tabela)) {
                            $resumo['tabelas_desconhecidas'][] = $tabela;
                            $tabela = null;

                            return;
                        }
                        $colunasActuais[$tabela] ??= array_keys(MapaDaEmpresa::colunas($tabela));
                        $colunas = $linha['colunas'];
                        $binarias = array_flip($linha['binarias'] ?? []);

                        return;
                    }

                    if ($tabela === null) {
                        return;
                    }

                    $registo = [];
                    foreach ($colunas as $i => $c) {
                        if (in_array($c, $colunasActuais[$tabela], true)) {
                            $v = $linha[$i] ?? null;
                            $registo[$c] = isset($binarias[$c]) && $v !== null ? base64_decode($v) : $v;
                        }
                    }

                    // A empresa é SEMPRE esta: nas directas força-se o tenant_id;
                    // nas filhas, a mãe tem de ter vindo na cópia.
                    if (in_array($tabela, $mapa['directas'], true)) {
                        $registo['tenant_id'] = $tenantId;
                        if (isset($registo['id'])) {
                            $idsDasMaes[$tabela][$registo['id']] = true;
                        }
                    } else {
                        $f = $mapa['filhas'][$tabela];
                        if (! isset($idsDasMaes[$f['pai']][$registo[$f['coluna']] ?? null])) {
                            $resumo['ignoradas']++;

                            return;
                        }
                        if (isset($registo['id'])) {
                            $idsDasMaes[$tabela][$registo['id']] = true;
                        }
                    }

                    // Papéis de pessoas que já não existem ficam de fora.
                    if (isset(MapaDaEmpresa::COM_PESSOAS[$tabela]) && ! isset($pessoas[$registo[MapaDaEmpresa::COM_PESSOAS[$tabela]] ?? 0])) {
                        $resumo['ignoradas']++;

                        return;
                    }

                    $bloco[] = $registo;
                    if (count($bloco) >= 500) {
                        $despejar();
                    }
                });

                $despejar();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        // As permissões ficam em cache no Spatie; o stock e o resto lêem-se da base.
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
        }

        return $resumo + ['copia_de' => $criadaEm->toIso8601String()];
    }

    /* ════════ Peças ════════ */

    /** Lê o .jsonl.gz linha a linha: fn(string $cru, mixed $json, int $numero). */
    private function ler(string $ficheiro, callable $fn): void
    {
        $gz = @gzopen($ficheiro, 'rb');
        if (! $gz) {
            throw new RuntimeException(__('Não foi possível abrir a cópia.'));
        }

        $n = 0;
        try {
            while (($cru = gzgets($gz)) !== false) {
                if ($cru === "\n" || $cru === '') {
                    continue;
                }
                $json = json_decode($cru, true);
                if ($json === null && trim($cru) !== 'null') {
                    throw new RuntimeException(__('A cópia está corrompida (linha :n).', ['n' => $n + 1]));
                }
                $fn($cru, $json, $n);
                $n++;
            }
        } finally {
            gzclose($gz);
        }
    }

    private static function chave(int $tenantId): string
    {
        return hash_hmac('sha256', 'copia-empresa:' . $tenantId, (string) config('app.key'), true);
    }
}
