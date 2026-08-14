<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repõe o prefixo do catálogo AGT nas séries que o têm errado.
 *
 * O prefixo é o PRIMEIRO token do número do documento ("FT SOSFT/000123") e é
 * por ele que a AGT classifica o documento. Está medido neste projecto: 25
 * documentos com o primeiro token errado foram os 25 recusados com E32, e os 5
 * com o token certo passaram todos. Como o campo é texto livre no formulário
 * (required|max:10), escreveram-se 'PRF' e 'PP' onde o catálogo diz 'PR'.
 *
 * POR OMISSÃO SIMULA. Sem --aplicar nada é gravado — este comando corre em
 * produção sobre numeração fiscal, e o valor por omissão tem de ser o que não
 * estraga nada se for escrito por engano.
 *
 * Uso:
 *   php artisan series:corrigir-prefixos                      (simulação)
 *   php artisan series:corrigir-prefixos --tenant=17
 *   php artisan series:corrigir-prefixos --aplicar
 *   php artisan series:corrigir-prefixos --aplicar --forcar    (inclui as estreadas)
 */
class CorrigirPrefixosDeSeries extends Command
{
    protected $signature = 'series:corrigir-prefixos
                            {--tenant= : id, slug, nome ou parte do nome da empresa}
                            {--aplicar : grava as correcções (sem isto é só simulação)}
                            {--forcar : inclui as séries que já emitiram documentos}';

    protected $description = 'Repõe o prefixo AGT nas séries que o têm errado (simulação por omissão)';

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

    private int $corrigidas = 0;
    private int $bloqueadas = 0;
    private int $internas = 0;
    private int $desconhecidas = 0;
    private int $jaCertas = 0;

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

        foreach ($empresas as $empresa) {
            $this->tratar($empresa, $aplicar);
        }

        $this->resumo($aplicar);

        return self::SUCCESS;
    }

    private function empresas()
    {
        $filtro = $this->option('tenant');

        if (!$filtro) {
            // Sem filtro são as empresas VIVAS — as apagadas ficam de fora
            // porque não voltam a emitir nada, e é o mesmo universo que o
            // series:diagnostico mostra. Uma contagem feita em SQL cru sobre a
            // tabela toda dá mais séries do que as que aparecem aqui.
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
        $series = InvoicingSeries::where('tenant_id', $empresa->id)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get();

        $porEstrear = [];
        $estreadas  = [];
        $ignoradas  = [];

        foreach ($series as $s) {
            // prefixoDe() e não o array AGT_PREFIXES lido à mão: este comando
            // repõe o prefixo, e tem de o ir buscar exactamente onde o resto do
            // produto o vai buscar — senão repõe o valor de uma cópia.
            $canonico = InvoicingSeries::prefixoDe($s->document_type);

            if (!$canonico) {
                // Um tipo interno (proforma de compra) é legítimo e não tem
                // prefixo de catálogo fiscal: não se lhe toca.
                if (InvoicingSeries::tipoInterno($s->document_type)) {
                    $this->internas++;
                    $ignoradas[] = sprintf(
                        "série #%d (%s, %s): tipo interno, não se comunica à AGT.",
                        $s->id,
                        $s->document_type,
                        $s->series_code
                    );
                } else {
                    // Hoje não acontece: a coluna é um enum e todos os valores
                    // que ela aceita ou estão no catálogo ou são internos. Fica
                    // como rede para quando o enum crescer — um tipo novo na
                    // migração e esquecido no catálogo aparece aqui em vez de
                    // sair com o prefixo que calhar.
                    $this->desconhecidas++;
                    $ignoradas[] = sprintf(
                        "série #%d (%s, %s): tipo fora do catálogo AGT — corrigir à mão, não se adivinha o prefixo.",
                        $s->id,
                        $s->document_type,
                        $s->series_code
                    );
                }

                continue;
            }

            if ((string) $s->prefix === $canonico) {
                $this->jaCertas++;
                continue;
            }

            $emitidos = $this->documentosEmitidos($s);

            if ($emitidos > 0) {
                $estreadas[] = [$s, $canonico, $emitidos];
                continue;
            }

            $porEstrear[] = [$s, $canonico];
        }

        if (!$porEstrear && !$estreadas && !$ignoradas) {
            return;
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info("EMPRESA #{$empresa->id} — {$empresa->name}");

        $this->corrigirPorEstrear($porEstrear, $aplicar);
        $this->listarEstreadas($estreadas, $aplicar);

        if ($ignoradas) {
            $this->line('  ignoradas:');
            foreach ($ignoradas as $linha) {
                $this->line('    ·  ' . $linha);
            }
        }
    }

    /** Séries que nunca numeraram nada: mudar o prefixo não deixa rasto atrás. */
    private function corrigirPorEstrear(array $lista, bool $aplicar): void
    {
        if (!$lista) {
            return;
        }

        $this->line('  por estrear (correcção sem consequências):');

        foreach ($lista as [$s, $canonico]) {
            $antigo = (string) $s->prefix;
            $antes  = $s->previewNextNumber();

            if ($aplicar) {
                // Cada série é uma escrita independente: se uma falhar, as que
                // já ficaram certas não têm de voltar atrás com ela.
                DB::transaction(fn () => $s->update(['prefix' => $canonico]));
                $s->refresh();
            } else {
                // Só para mostrar o resultado — o objecto em memória não é
                // gravado. A simulação não pode tocar na base de dados.
                $s->prefix = $canonico;
            }

            $this->corrigidas++;

            $this->line(sprintf(
                "    %s série #%d (%s, %s): '%s' → '%s'   %s → %s",
                $aplicar ? '✓' : '·',
                $s->id,
                $s->document_type,
                $s->series_code,
                $antigo,
                $canonico,
                $antes,
                $s->previewNextNumber()
            ));
        }
    }

    /**
     * Séries que já emitiram: não se corrigem sozinhas.
     *
     * Mudar o prefixo a meio deixa a mesma série com números de duas formas
     * diferentes — os que já estão na mão dos clientes com o prefixo antigo, e
     * os seguintes com o novo. Isso é decisão de quem gere a empresa, não de um
     * comando: por isso ficam listadas à parte, com o número de documentos que
     * já saiu, e só passam com --forcar.
     */
    private function listarEstreadas(array $lista, bool $aplicar): void
    {
        if (!$lista) {
            return;
        }

        $forcar = (bool) $this->option('forcar');

        $this->line(match (true) {
            $forcar && $aplicar => '  já estreadas (--forcar: corrigidas na mesma):',
            $forcar             => '  já estreadas (--forcar: entrariam na correcção):',
            default             => '  já estreadas (NÃO corrigidas — precisam de --forcar):',
        });

        foreach ($lista as [$s, $canonico, $emitidos]) {
            $antigo = (string) $s->prefix;
            $antes  = $s->previewNextNumber();

            if ($forcar && $aplicar) {
                DB::transaction(fn () => $s->update(['prefix' => $canonico]));
                $s->refresh();
            } elseif ($forcar) {
                $s->prefix = $canonico;
            }

            if ($forcar) {
                $this->corrigidas++;
            } else {
                $this->bloqueadas++;
            }

            $this->warn(sprintf(
                "    %s série #%d (%s, %s): '%s' → '%s' — %d documento(s) já emitido(s) com '%s'.%s",
                $forcar ? ($aplicar ? '✓' : '·') : '!',
                $s->id,
                $s->document_type,
                $s->series_code,
                $antigo,
                $canonico,
                $emitidos,
                $antigo,
                $s->agt_series_id ? "  Registada na AGT como {$s->agt_series_id}." : ''
            ));

            if ($forcar) {
                $this->line("        a numeração passa de {$antes} para {$s->previewNextNumber()}.");
            }
        }
    }

    /**
     * Quantos documentos já saíram por esta série com o prefixo que lá está.
     *
     * O contador `next_number` não chega: com reset_yearly ligado ele volta a 1
     * em Janeiro, e uma série com 500 documentos do ano passado passaria por
     * "nunca estreada". Conta-se pelos números realmente gravados, e o contador
     * fica como recurso para quando a tabela não está mapeada.
     */
    private function documentosEmitidos(InvoicingSeries $s): int
    {
        $peloContador = max(0, (int) $s->next_number - 1);

        [$tabela, $coluna] = self::TABELAS[$s->document_type] ?? [null, null];

        if (!$tabela || !Schema::hasTable($tabela) || !Schema::hasColumn($tabela, $coluna)) {
            return $peloContador;
        }

        // O número tem a forma "PRF PR/000007": interessa tudo o que comece
        // pelo prefixo actual e por esta série.
        $serie  = $s->agt_series_id ?: preg_replace('/^SOS/', '', (string) $s->series_code);
        $inicio = trim(((string) $s->prefix) . ' ' . $serie) . '/';

        $contados = DB::table($tabela)
            ->where('tenant_id', $s->tenant_id)
            // Um documento apagado depois na aplicação continua a ter saído
            // para o cliente com aquele prefixo: conta na mesma.
            ->where($coluna, 'like', addcslashes($inicio, '%_\\') . '%')
            ->count();

        return max($peloContador, $contados);
    }

    private function resumo(bool $aplicar): void
    {
        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info('RESUMO');

        $this->line(sprintf(
            '  %s: %d',
            $aplicar ? 'corrigidas' : 'a corrigir (simulação)',
            $this->corrigidas
        ));

        if ($this->bloqueadas) {
            $this->line("  saltadas por já terem emitido documentos: {$this->bloqueadas}  → repita com --forcar se for mesmo para mudar");
        }

        if ($this->internas) {
            $this->line("  saltadas por serem tipos internos (não vão à AGT): {$this->internas}");
        }

        if ($this->desconhecidas) {
            $this->line("  saltadas por terem tipo fora do catálogo: {$this->desconhecidas}");
        }

        $this->line("  já com o prefixo certo: {$this->jaCertas}");

        if (!$aplicar && $this->corrigidas > 0) {
            $this->newLine();
            $this->warn('Nada foi gravado. Repita com --aplicar.');
        }
    }
}
