<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deixa UMA só série marcada como padrão por tipo de documento — mas apenas
 * quando a escolha não é uma escolha.
 *
 * Sete sítios do código gravam `is_default => true` ao criar uma série e nenhum
 * verifica se já existe uma padrão para aquele tipo. O resultado, medido em
 * produção, são tipos de documento com duas ou mais padrão na mesma empresa.
 * Qual delas é usada depende da ordem que a base de dados devolver
 * (getDefaultSeries faz `->first()` sem ordenação nenhuma) — ou seja, é
 * imprevisível, e é essa série que decide o número do próximo documento fiscal.
 *
 * SÓ CORRIGE O CASO ÓBVIO: uma série com documentos emitidos e todas as outras
 * por estrear. Aí não há decisão a tomar — a que já numerou fica, as outras são
 * desmarcadas, e nenhuma numeração muda de sítio.
 *
 * Tudo o resto fica para decisão humana, e isso não é timidez:
 *
 *   · duas ou mais com documentos emitidos — desmarcar uma muda por qual série
 *     sai o próximo documento fiscal. Quem tem centenas de facturas numa série
 *     e dezenas noutra sabe porquê; o comando não sabe;
 *   · todas por estrear — não há sinal nenhum que permita escolher. Escolher à
 *     sorte seria repetir, com outra cara, o problema que se veio corrigir.
 *
 * POR OMISSÃO SIMULA. Sem --aplicar nada é gravado: isto corre em produção
 * sobre numeração fiscal, e o comportamento por omissão tem de ser o que não
 * estraga nada se for escrito por engano.
 *
 * Uso:
 *   php artisan series:corrigir-padrao                  (simulação)
 *   php artisan series:corrigir-padrao --tenant=17
 *   php artisan series:corrigir-padrao --aplicar
 */
class CorrigirSeriesPadrao extends Command
{
    protected $signature = 'series:corrigir-padrao
                            {--tenant= : id, slug, nome ou parte do nome da empresa}
                            {--aplicar : grava as correcções (sem isto é só simulação)}
                            {--tipo= : com --fica, o tipo de documento a resolver (ex.: pos)}
                            {--fica= : código da série que fica padrão nesse tipo; as outras são desmarcadas}';

    protected $description = 'Deixa uma só série padrão por tipo quando a escolha é óbvia (simulação por omissão)';

    /** Onde vive o número de cada tipo de documento. */
    private const TABELAS = [
        'invoice'     => ['invoicing_sales_invoices',    'invoice_number'],
        'pos'         => ['invoicing_sales_invoices',    'invoice_number'],
        'proforma'    => ['invoicing_sales_proformas',   'proforma_number'],
        'receipt'     => ['invoicing_receipts',          'receipt_number'],
        'credit_note' => ['invoicing_credit_notes',      'credit_note_number'],
        'debit_note'  => ['invoicing_debit_notes',       'debit_note_number'],
        'advance'     => ['invoicing_advances',          'advance_number'],
        'purchase'    => ['invoicing_purchase_invoices', 'invoice_number'],
        'transport'   => ['invoicing_transport_guides',  'guide_number'],
    ];

    private int $corrigidos = 0;
    private int $desmarcadas = 0;
    private int $empatados = 0;
    private int $todasPorEstrear = 0;

    public function handle(): int
    {
        $empresas = $this->empresas();

        if ($empresas->isEmpty()) {
            $this->error('Nenhuma empresa encontrada com esse critério.');

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('aplicar');

        $this->newLine();
        $this->line($aplicar
            ? 'MODO: --aplicar — as correcções vão ser GRAVADAS.'
            : 'MODO: simulação — nada é gravado. Repita com --aplicar para gravar.');

        if ($this->option('fica')) {
            return $this->resolverAMao($empresas, $aplicar);
        }

        foreach ($empresas as $empresa) {
            $this->tratar($empresa, $aplicar);
        }

        $this->resumo($aplicar);

        return self::SUCCESS;
    }

    /**
     * O empate resolvido por quem manda, nomeando a série que fica.
     *
     * Não há aqui inferência nenhuma, e é essa a intenção: os casos que chegam
     * a esta via são precisamente aqueles em que DUAS séries emitiram documentos
     * fiscais, e escolher entre elas é decisão de quem gere o negócio. O comando
     * limita-se a executá-la — e a recusar-se se o que lhe disserem não bater
     * certo com o que está na base.
     *
     *   php artisan series:corrigir-padrao --tenant=19 --tipo=pos --fica=A --aplicar
     */
    private function resolverAMao($empresas, bool $aplicar): int
    {
        $tipo   = (string) $this->option('tipo');
        $codigo = (string) $this->option('fica');

        if ($tipo === '') {
            $this->error('--fica precisa de --tipo (ex.: --tipo=pos).');

            return self::FAILURE;
        }

        if ($empresas->count() !== 1) {
            $this->error(sprintf(
                '--fica precisa de uma empresa exacta: --tenant apanhou %d.',
                $empresas->count()
            ));

            return self::FAILURE;
        }

        $empresa = $empresas->first();

        $doTipo = InvoicingSeries::where('tenant_id', $empresa->id)
            ->where('document_type', $tipo)
            ->where('is_default', true)
            ->orderBy('id')
            ->get();

        if ($doTipo->count() < 2) {
            $this->error(sprintf(
                'A empresa #%d não tem empate em "%s": %d série(s) padrão. Nada a resolver.',
                $empresa->id,
                $tipo,
                $doTipo->count()
            ));

            return self::FAILURE;
        }

        $fica = $doTipo->firstWhere('series_code', $codigo);

        if (!$fica) {
            $this->error(sprintf(
                'Nenhuma série padrão de "%s" na empresa #%d tem o código "%s". Há: %s',
                $tipo,
                $empresa->id,
                $codigo,
                $doTipo->pluck('series_code')->implode(', ')
            ));

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info("EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line("  {$tipo}: fica padrão a série {$codigo}, por indicação expressa.");

        $emitidos = $doTipo->mapWithKeys(fn ($s) => [$s->id => $this->documentosEmitidos($s)]);

        $this->mostrar($fica, $emitidos[$fica->id], '✓', 'fica padrão');

        foreach ($doTipo->where('id', '!=', $fica->id) as $s) {
            // O aviso é para ficar registado: estas séries emitiram documentos
            // fiscais e continuam a existir. Desmarcá-las não mexe em nenhum
            // deles — o is_default só decide por qual sai o PRÓXIMO.
            $this->mostrar($s, $emitidos[$s->id], $aplicar ? '✓' : '·', 'desmarcada');

            if ($aplicar) {
                $s->is_default = false;
                $s->saveQuietly();
            }

            $this->desmarcadas++;
        }

        $this->corrigidos++;

        $this->resumo($aplicar);

        return self::SUCCESS;
    }

    private function empresas()
    {
        $filtro = $this->option('tenant');

        if (!$filtro) {
            return Tenant::orderBy('id')->get();
        }

        if (ctype_digit((string) $filtro)) {
            return Tenant::where('id', (int) $filtro)->get();
        }

        return Tenant::where('slug', 'like', "%{$filtro}%")
            ->orWhere('name', 'like', "%{$filtro}%")
            ->orderBy('id')
            ->get();
    }

    private function tratar(Tenant $empresa, bool $aplicar): void
    {
        // Só as marcadas como padrão. Um tipo com UMA padrão e dez normais não
        // é ambíguo, e um tipo sem padrão nenhuma é outro problema — o
        // series:diagnostico assinala-o e este comando não o inventa.
        //
        // Sem filtro por is_active de propósito: uma série inactiva marcada como
        // padrão continua a competir pelo lugar de padrão, e é o que a contagem
        // de produção mediu.
        $padroes = InvoicingSeries::where('tenant_id', $empresa->id)
            ->where('is_default', true)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get()
            ->groupBy('document_type')
            ->filter(fn ($doTipo) => $doTipo->count() > 1);

        if ($padroes->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info("EMPRESA #{$empresa->id} — {$empresa->name}");

        foreach ($padroes as $tipo => $doTipo) {
            $this->tratarTipo((string) $tipo, $doTipo, $aplicar);
        }
    }

    private function tratarTipo(string $tipo, $doTipo, bool $aplicar): void
    {
        // Contar UMA vez e reutilizar: a contagem faz uma consulta por série, e
        // a decisão e o que se mostra a seguir têm de olhar para o mesmo número.
        $emitidos = $doTipo->mapWithKeys(fn ($s) => [$s->id => $this->documentosEmitidos($s)]);

        // Basta o contador ter andado para a série contar como em uso, mesmo
        // que não se encontre documento nenhum. É de propósito, e a razão é
        // desagradável: um zero em `gravados` tanto quer dizer "não emitiu"
        // como "emitiu e não sabemos ver".
        //
        // Houve uma versão deste comando com uma opção --por-documentos, que
        // decidia só por `gravados`. Parecia acertada — o contador anda sem
        // produzir documento, e havia séries com o contador em 384 e nada
        // gravado. Mas a leitura do número estava ancorada no primeiro bloco,
        // que era 'SOS' fixo até 6 de Agosto de 2026, e o series_id só existe
        // nas linhas posteriores a Outubro de 2025. Uma série veterana dava
        // zero por cegueira, e a opção mandava desmarcá-la — a que tem os
        // documentos todos comunicados à AGT. A leitura do número já foi
        // corrigida; a opção não volta, porque o zero continua a não ser
        // prova: antes de Outubro de 2025 o número não trazia sequer a série.
        $comDocumentos = $doTipo->filter(fn ($s) => $emitidos[$s->id]['total'] > 0);

        $decidivel = $comDocumentos->count() === 1;

        if ($decidivel) {
            $this->corrigir($tipo, $doTipo, $comDocumentos->first(), $emitidos, $aplicar);

            return;
        }

        $this->deixarParaHumano($tipo, $doTipo, $comDocumentos->count(), $emitidos);
    }

    /**
     * Uma com documentos, todas as outras a zero: a escolha já está feita.
     *
     * Desmarcar uma série por estrear não mexe em número nenhum — não há
     * documento que tenha saído por ela, e o próximo continua a sair pela que
     * já vai a meio.
     */
    private function corrigir(string $tipo, $doTipo, InvoicingSeries $fica, $emitidos, bool $aplicar): void
    {
        $desmarcar = $doTipo->where('id', '!=', $fica->id);

        $this->line(sprintf(
            '  %s: %d séries padrão → fica só %s',
            $tipo,
            $doTipo->count(),
            $fica->series_code
        ));

        if ($aplicar) {
            // Uma transacção por TIPO e não por série: metade das séries
            // desmarcadas deixava o tipo na mesma ambiguidade que se veio
            // resolver, e sem sinal nenhum de que ficou a meio.
            DB::transaction(function () use ($desmarcar) {
                foreach ($desmarcar as $s) {
                    $s->update(['is_default' => false]);
                }
            });
        }

        $this->mostrar($fica, $emitidos[$fica->id], '✓', 'fica padrão');

        foreach ($desmarcar as $s) {
            $this->mostrar($s, $emitidos[$s->id], $aplicar ? '✓' : '·', 'desmarcada');
        }

        $this->corrigidos++;
        $this->desmarcadas += $desmarcar->count();
    }

    /**
     * Os casos em que o comando não escolhe, e diz porque não.
     *
     * A lista das séries com o que cada uma já emitiu vai a seguir de propósito:
     * é o que permite a quem lê tomar a decisão que o comando não toma.
     */
    private function deixarParaHumano(string $tipo, $doTipo, int $quantasComDocumentos, $emitidos): void
    {
        if ($quantasComDocumentos === 0) {
            $this->todasPorEstrear++;
            $motivo = 'todas por estrear — não há sinal nenhum que permita escolher qual fica.';
        } else {
            $this->empatados++;
            $motivo = sprintf(
                '%d séries já emitiram documentos — desmarcar uma muda por qual série sai o próximo '
                    . 'documento fiscal, e essa é decisão de quem gere o negócio.',
                $quantasComDocumentos
            );
        }

        $this->warn(sprintf(
            '  %s: %d séries padrão — DECISÃO HUMANA: %s',
            $tipo,
            $doTipo->count(),
            $motivo
        ));

        foreach ($doTipo->sortByDesc(fn ($s) => $emitidos[$s->id]['total']) as $s) {
            $this->mostrar($s, $emitidos[$s->id], '!', '');
        }

        $this->formasDoNumero($doTipo->first());
    }

    /**
     * Que números saíram mesmo, agrupados pela forma, com a série a que estão
     * ligados. É a prova em bruto, e é o que permite decidir sem acreditar na
     * contagem: o `gravados` é uma leitura, isto é o que lá está.
     *
     * Os dígitos são substituídos por '#' para as milhares de linhas colapsarem
     * nas poucas formas que existem.
     */
    private function formasDoNumero(?InvoicingSeries $s): void
    {
        [$tabela, $coluna] = self::TABELAS[$s->document_type ?? ''] ?? [null, null];

        if (!$s || !$tabela || !Schema::hasTable($tabela) || !Schema::hasColumn($tabela, $coluna)) {
            return;
        }

        $ligacao = Schema::hasColumn($tabela, 'series_id')
            ? 'GROUP_CONCAT(DISTINCT COALESCE(series_id, 0) ORDER BY series_id)'
            : "'—'";

        $formas = DB::table($tabela)
            ->where('tenant_id', $s->tenant_id)
            ->selectRaw("REGEXP_REPLACE({$coluna}, '[0-9]+', '#') AS forma")
            ->selectRaw('COUNT(*) AS quantos')
            ->selectRaw("{$ligacao} AS series")
            ->groupBy('forma')
            ->orderByDesc('quantos')
            ->limit(8)
            ->get();

        if ($formas->isEmpty()) {
            return;
        }

        $this->line('       números realmente gravados (série 0 = sem series_id):');

        foreach ($formas as $f) {
            $this->line(sprintf('         %-28s %6d   série: %s', $f->forma, $f->quantos, $f->series));
        }
    }

    private function mostrar(InvoicingSeries $s, array $emitidos, string $marca, string $nota): void
    {
        // O contador e os números realmente gravados só aparecem separados
        // quando divergem: em todas as outras linhas seria ruído, e é
        // justamente a divergência que interessa a quem tem de decidir.
        $detalhe = $emitidos['contador'] === $emitidos['gravados']
            ? ''
            : sprintf('  (contador: %d, gravados: %d)', $emitidos['contador'], $emitidos['gravados']);

        $this->line(sprintf(
            '      %s %-14s emitidos: %-6d próximo: %-6d AGT: %-8s %s%s',
            $marca,
            $s->series_code,
            $emitidos['total'],
            (int) $s->next_number,
            $s->agt_series_id ?: '—',
            $nota ? '← ' . $nota : '',
            $detalhe
        ));
    }

    /**
     * Quantos documentos já saíram por esta série.
     *
     * O contador `next_number` sozinho não chega: com `reset_yearly` ligado — e
     * é o valor por omissão em todas as séries criadas pela casa — ele volta a 1
     * em Janeiro. Uma série com centenas de facturas do ano passado passaria por
     * "nunca estreada" e seria desmarcada por este comando, que é exactamente o
     * que ele não pode fazer. Conta-se também pelos números gravados, e fica o
     * MAIOR dos dois: qualquer um deles a dizer que houve documentos chega para
     * a série não ser tocada.
     */
    private function documentosEmitidos(InvoicingSeries $s): array
    {
        $contador = max(0, (int) $s->next_number - 1);
        $gravados = 0;

        [$tabela, $coluna] = self::TABELAS[$s->document_type] ?? [null, null];

        if ($tabela && Schema::hasTable($tabela) && Schema::hasColumn($tabela, $coluna)) {
            // Sem filtrar `deleted_at`: um documento apagado depois na aplicação
            // já saiu com aquele número, e o que se está a perguntar é se esta
            // série chegou a numerar alguma coisa.
            $temColunaSerie = Schema::hasColumn($tabela, 'series_id');
            $identidades    = $this->identidadesDaSerie($s);

            $gravados = (int) DB::table($tabela)
                ->where('tenant_id', $s->tenant_id)
                ->where(function ($q) use ($coluna, $identidades, $temColunaSerie, $s) {
                    // A ligação pelo series_id é a resposta certa: diz de que
                    // série o documento saiu, sem depender da forma do número.
                    // A leitura do número ficou como segunda via, e só para as
                    // linhas antigas que ainda não têm series_id gravado —
                    // contá-las às duas maneiras somava o mesmo duas vezes.
                    if ($temColunaSerie) {
                        $q->where('series_id', $s->getKey());

                        $q->orWhere(function ($antigas) use ($coluna, $identidades) {
                            $antigas->whereNull('series_id')
                                ->whereIn($this->segundoBloco($coluna), $identidades);
                        });

                        return;
                    }

                    $q->whereIn($this->segundoBloco($coluna), $identidades);
                })
                ->count();
        }

        return [
            'contador' => $contador,
            'gravados' => $gravados,
            'total'    => max($contador, $gravados),
        ];
    }

    /**
     * O SEGUNDO bloco do número, que é onde vive a identidade da série.
     *
     * Recorta até ao primeiro espaço a seguir ao primeiro, e depois corta na
     * barra. Aguenta as três formas que o formatNumber já teve:
     *
     *     "FR SOSFR 2025/000123"  → SOSFR      (até 2025-12-16)
     *     "SOS FR/000123"         → FR         (3 a 6 de Agosto de 2026)
     *     "FR FR/000123"          → FR         (desde 0e4cdab, 2026-08-06)
     *     "FR FR7626S6286N/000100"→ FR7626S6286N
     *
     * O primeiro bloco NÃO entra: era 'SOS' fixo até 6 de Agosto e passou a ser
     * o prefixo do tipo. Ancorar o padrão nele — como estava — fazia com que
     * nenhum documento anterior a essa data batesse certo, e a série aparecia a
     * zero. Numa série que já emitiu, esse zero é o engano perigoso: é ele que
     * autoriza a desmarcá-la.
     */
    private function segundoBloco(string $coluna): \Illuminate\Database\Query\Expression
    {
        return DB::raw(
            "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX({$coluna}, ' ', 2), ' ', -1), '/', 1)"
        );
    }

    /**
     * Por que nomes esta série já se apresentou no segundo bloco do número.
     *
     * São três porque a regra mudou com o tempo — e porque o agt_series_id pode
     * ter sido atribuído DEPOIS de a série já ter emitido, o que parte o número
     * ao meio: os antigos ficam com o código, os novos com o código AGT.
     */
    private function identidadesDaSerie(InvoicingSeries $s): array
    {
        $codigo = (string) $s->series_code;

        return array_values(array_unique(array_filter([
            $s->agt_series_id,
            $codigo,
            preg_replace('/^SOS/', '', $codigo),
        ], fn ($v) => $v !== null && $v !== '')));
    }

    private function resumo(bool $aplicar): void
    {
        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info('RESUMO');

        $this->line(sprintf(
            '  %s: %d tipo(s) de documento, %d série(s) desmarcada(s).',
            $aplicar ? 'corrigidos' : 'a corrigir (simulação)',
            $this->corrigidos,
            $this->desmarcadas
        ));

        $humanos = $this->empatados + $this->todasPorEstrear;

        $this->line("  deixados para decisão humana: {$humanos}");

        if ($this->empatados) {
            $this->line(
                "    · {$this->empatados} com duas ou mais séries a emitir documentos — desmarcar uma "
                    . 'muda por qual série sai o próximo documento fiscal.'
            );
        }

        if ($this->todasPorEstrear) {
            $this->line(
                "    · {$this->todasPorEstrear} com todas as séries por estrear — não há sinal nenhum "
                    . 'que permita escolher.'
            );
        }

        if ($humanos) {
            $this->newLine();
            $this->line('  Os que ficam para decisão humana resolvem-se no ecrã das séries,');
            $this->line('  desmarcando à mão as que não devem ser padrão.');
        }

        if (!$aplicar && $this->corrigidos > 0) {
            $this->newLine();
            $this->warn('Nada foi gravado. Repita com --aplicar.');
        }
    }
}
