<?php

namespace App\Console\Commands\AGT;

use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza o padrão das séries e preenche ATCUD em falta.
 *
 * 1) SÉRIES — aplica o padrão da casa (SOSFT, SOSRC, SOSNC…) ao código.
 *    Só é seguro quando o código não é usado na numeração já emitida:
 *      • série registada na AGT → o número usa o código da AGT, o series_code
 *        é apenas etiqueta interna ⇒ seguro renomear;
 *      • série sem documentos emitidos ⇒ seguro renomear;
 *      • série NÃO registada e COM documentos ⇒ renomear partiria a numeração
 *        a meio (FT A/000005 seguido de FT SOSFT/000006) ⇒ é ignorada.
 *
 * 2) ATCUD — preenche documentos cuja série já está registada na AGT mas que
 *    ficaram sem ATCUD (emitidos antes de o preenchimento automático existir).
 *    O ATCUD é obrigatório no documento impresso.
 *
 * Idempotente. Nunca altera um ATCUD já gravado nem toca em séries de risco.
 *
 *   php artisan agt:normalize --dry-run
 *   php artisan agt:normalize --tenant=11
 */
class NormalizeSeriesAndAtcud extends Command
{
    protected $signature = 'agt:normalize
                            {--tenant= : Limitar a uma empresa}
                            {--dry-run : Apenas mostra o que seria alterado}
                            {--skip-series : Não mexer nos códigos de série}
                            {--migrar-usadas : Trata também as séries já usadas (ver migrarSeriesUsadas)}';

    protected $description = 'Aplica o padrão SOS às séries e preenche ATCUD em falta';

    /** Documento → coluna do número. */
    private const DOCUMENTOS = [
        'invoicing_sales_invoices' => 'invoice_number',
        'invoicing_receipts'       => 'receipt_number',
        'invoicing_credit_notes'   => 'credit_note_number',
        'invoicing_debit_notes'    => 'debit_note_number',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $this->info('=== Normalização AGT ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');
        $this->newLine();

        $renomeadas = $this->option('skip-series') ? 0 : $this->normalizarSeries($dry, $tenantId);
        $migradas = $this->option('migrar-usadas') ? $this->migrarSeriesUsadas($dry, $tenantId) : 0;
        $atcuds = $this->preencherAtcud($dry, $tenantId);
        $finalizados = $this->finalizarValidados($dry, $tenantId);

        $this->newLine();
        if ($dry) {
            $this->info('(dry-run) Nada foi alterado. Remova --dry-run para aplicar.');
        } else {
            $this->info("   Séries renomeadas para o padrão SOS: {$renomeadas}");
            if ($this->option('migrar-usadas')) {
                $this->info("   Séries já usadas migradas: {$migradas}");
            }
            $this->info("   Documentos com ATCUD preenchido: {$atcuds}");
            $this->info("   Documentos marcados como definitivos: {$finalizados}");
        }

        return self::SUCCESS;
    }

    /**
     * Marca como DEFINITIVOS (invoice_status = 'F') os documentos que a AGT já
     * validou mas ficaram em 'N'.
     *
     * Enquanto estão em 'N' o guarda de editar/apagar não os protege — um
     * documento aceite pelo fisco continua alterável, o que viola a
     * inviolabilidade do Decreto 71/25 — e a integração contabilística, que
     * dispara na transição para 'F', nunca corre para eles.
     */
    protected function finalizarValidados(bool $dry, ?string $tenantId): int
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>3) Documentos validados pela AGT ainda não definitivos</>');

        $total = 0;

        foreach (self::DOCUMENTOS as $tabela => $coluna) {
            if (!Schema::hasTable($tabela) || !Schema::hasColumn($tabela, 'invoice_status')) {
                continue;
            }

            $q = DB::table($tabela)
                ->where('agt_status', 'validated')
                ->where(function ($w) {
                    $w->where('invoice_status', '!=', 'F')->orWhereNull('invoice_status');
                });

            if ($tenantId) {
                $q->where('tenant_id', $tenantId);
            }

            $linhas = $q->get(['id', $coluna . ' as numero', 'invoice_status']);
            if ($linhas->isEmpty()) {
                continue;
            }

            // A data do estado só existe em algumas tabelas (as notas de crédito
            // e débito não a têm) — incluí-la às cegas rebentava o UPDATE.
            $temData = Schema::hasColumn($tabela, 'invoice_status_date');

            $this->line("   <fg=cyan>{$tabela}</>: {$linhas->count()} documento(s)");
            foreach ($linhas as $l) {
                $this->line("      {$l->numero}: <fg=yellow>{$l->invoice_status}</> → <fg=green>F</>");
                if (!$dry) {
                    $campos = ['invoice_status' => 'F'];
                    if ($temData) {
                        $campos['invoice_status_date'] = now();
                    }
                    DB::table($tabela)->where('id', $l->id)->update($campos);
                }
                $total++;
            }
        }

        if ($total === 0) {
            $this->line('   <fg=green>Nenhum documento validado por finalizar.</>');
        }

        return $total;
    }

    /**
     * Trata as séries que normalizarSeries() ignora por já terem documentos
     * emitidos e não estarem registadas na AGT.
     *
     *  • Documento NÃO fiscal (proforma): o código não tem valor legal e a AGT
     *    nunca o vê ⇒ renomeia no sítio, sem consequências.
     *
     *  • Documento FISCAL (FR, FT…): renomear a meio deixaria a série nova a
     *    começar em 001774, e um validador SAFT-AO exige que cada série comece
     *    no seu primeiro documento. A forma correcta de mudar de série é ABRIR
     *    UMA NOVA a começar em 000001 e passá-la a série por omissão. A antiga
     *    fica activa mas deixa de ser a padrão, para o histórico continuar
     *    íntegro e a mudança ser reversível num só campo.
     */
    protected function migrarSeriesUsadas(bool $dry, ?string $tenantId): int
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>1b) Séries já usadas</>');

        $query = InvoicingSeries::query()->orderBy('tenant_id')->orderBy('id');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $tratadas = 0;

        foreach ($query->get() as $serie) {
            $base = InvoicingSeries::defaultSeriesCode($serie->document_type);

            // Já normalizada, registada na AGT ou sem documentos: nada a fazer
            // aqui — normalizarSeries() já tratou desses casos.
            if (str_starts_with((string) $serie->series_code, $base)
                || !empty($serie->agt_series_id)
                || $this->documentosEmitidos($serie) === 0) {
                continue;
            }

            $emitidos = $this->documentosEmitidos($serie);

            if (!$serie->isAGTEligible()) {
                // O código base pode já ter sido tomado por outra série do mesmo
                // tipo — existe UNIQUE (tenant_id, document_type, series_code).
                // Se quem o tem é uma série secundária e vazia, o código limpo
                // pertence a esta (é a padrão): trocam-se.
                if ($serie->is_default && !$dry) {
                    $this->libertarCodigoBase($serie, $base);
                }

                $novo = $this->codigoDisponivel($serie, $base, $this->codigosDoGrupo($serie));

                if ($novo === $serie->series_code) {
                    continue;
                }

                $this->line("   #{$serie->id} t{$serie->tenant_id} <fg=cyan>{$serie->prefix} "
                    . "{$serie->series_code}</> → <fg=green>{$novo}</> "
                    . "(documento não fiscal, {$emitidos} emitido(s) — renomear é seguro)");

                if (!$dry) {
                    $serie->series_code = $novo;
                    if (empty($serie->name) || str_starts_with((string) $serie->name, 'Série ')) {
                        $serie->name = "Série {$novo}";
                    }
                    $serie->save();
                }
                $tratadas++;
                continue;
            }

            // Fiscal: nova série a começar em 1
            if (InvoicingSeries::where('tenant_id', $serie->tenant_id)
                ->where('document_type', $serie->document_type)
                ->where('series_code', $base)->exists()) {
                $this->line("   <fg=yellow>ignorada</> #{$serie->id} t{$serie->tenant_id}: "
                    . "já existe uma série {$base} para este tipo");
                continue;
            }

            $this->line("   #{$serie->id} t{$serie->tenant_id} <fg=cyan>{$serie->prefix} "
                . "{$serie->series_code}</> ({$emitidos} documento(s) fiscais) → "
                . "nova série <fg=green>{$base}</> a começar em 000001, passa a ser a padrão");

            if (!$dry) {
                DB::transaction(function () use ($serie, $base) {
                    InvoicingSeries::create([
                        'tenant_id'      => $serie->tenant_id,
                        'document_type'  => $serie->document_type,
                        'series_code'    => $base,
                        'name'           => "Série {$base}",
                        'prefix'         => $serie->prefix,
                        'include_year'   => $serie->include_year,
                        'next_number'    => 1,
                        'number_padding' => $serie->number_padding ?: 6,
                        'is_default'     => true,
                        'is_active'      => true,
                        'current_year'   => now()->year,
                        'reset_yearly'   => $serie->reset_yearly,
                        'description'    => "Série padrão AGT para {$serie->prefix}"
                            . " (substitui {$serie->series_code})",
                    ]);

                    // A antiga continua activa para consulta, mas deixa de numerar.
                    $serie->is_default = false;
                    $serie->save();
                });
            }
            $tratadas++;
        }

        if ($tratadas === 0) {
            $this->line('   <fg=green>Nenhuma série usada por tratar.</>');
        }

        return $tratadas;
    }

    /** Aplica SOSFT/SOSRC/… onde for seguro. */
    protected function normalizarSeries(bool $dry, ?string $tenantId): int
    {
        $this->line('<fg=white;options=bold>1) Códigos de série</>');

        $query = InvoicingSeries::query()->orderBy('tenant_id')->orderBy('id');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $alteradas = 0;
        $ignoradas = 0;

        // Existe UNIQUE (tenant_id, document_type, series_code): uma empresa com
        // três séries FT não pode ficar com três "SOSFT". A série por omissão
        // leva o código limpo; as outras levam sufixo do código antigo.
        $series = $query->get();
        $codigosPorGrupo = [];
        foreach ($series as $s) {
            $codigosPorGrupo[$s->tenant_id . '|' . $s->document_type][] = $s->series_code;
        }
        $reservados = $codigosPorGrupo;

        foreach ($series as $serie) {
            $grupo = $serie->tenant_id . '|' . $serie->document_type;
            $base  = InvoicingSeries::defaultSeriesCode($serie->document_type);
            $novo  = $this->codigoDisponivel($serie, $base, $reservados[$grupo] ?? []);

            if ($serie->series_code === $novo) {
                continue;
            }

            $registada = !empty($serie->agt_series_id);
            $emitidos  = $this->documentosEmitidos($serie);
            $seguro    = $registada || $emitidos === 0;

            if (!$seguro) {
                $ignoradas++;
                $this->line("   <fg=yellow>ignorada</> #{$serie->id} t{$serie->tenant_id} "
                    . "{$serie->prefix} {$serie->series_code} → {$novo}: "
                    . "{$emitidos} documento(s) emitido(s) e série não registada na AGT "
                    . '(renomear partiria a numeração)');
                continue;
            }

            $motivo = $registada ? 'registada na AGT, código só interno' : 'sem documentos emitidos';
            $this->line("   #{$serie->id} t{$serie->tenant_id} <fg=cyan>{$serie->prefix} "
                . "{$serie->series_code}</> → <fg=green>{$novo}</> ({$motivo})");

            if (!$dry) {
                $serie->series_code = $novo;
                if (empty($serie->name) || str_starts_with((string) $serie->name, 'Série ')) {
                    $serie->name = "Série {$novo}";
                }
                $serie->save();
            }

            // Reservar o código para não colidir com a série seguinte do grupo
            $reservados[$grupo][] = $novo;
            $alteradas++;
        }

        if ($alteradas === 0 && $ignoradas === 0) {
            $this->line('   <fg=green>Todas as séries já seguem o padrão.</>');
        }

        return $alteradas;
    }

    /**
     * Escolhe um código livre dentro do grupo (tenant + tipo de documento).
     *
     * A série por omissão fica com o código limpo (SOSFT). As restantes levam
     * o código antigo como sufixo (SOSFTB, SOSFT02) — o índice único
     * (tenant_id, document_type, series_code) não permite repetições.
     */
    protected function codigoDisponivel(InvoicingSeries $serie, string $base, array $usados): string
    {
        // Já normalizada (SOSFT, SOSFTB, SOSRC02…): não voltar a prefixar, senão
        // uma segunda passagem produzia SOSRCSOSRC02.
        if (str_starts_with((string) $serie->series_code, $base)) {
            return (string) $serie->series_code;
        }

        $livre = fn (string $c) => !in_array($c, $usados, true) || $serie->series_code === $c;

        if ($livre($base)) {
            return $base;
        }

        // Sufixo a partir do código antigo: "B" → SOSFTB, "02" → SOSFT02.
        //
        // Tira-se o "SOS" e o prefixo do tipo antes de o usar: um código já
        // normalizado noutro esquema (SOSPR) concatenado com a base nova
        // (SOSPROV) dava monstros como "SOSPROVSOSPR". Do "SOSPR02" queremos
        // só o "02"; do "B", o "B".
        $sufixo = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $serie->series_code));
        $sufixo = preg_replace('/^SOS/', '', $sufixo);

        foreach (\App\Models\Invoicing\InvoicingSeries::AGT_PREFIXES as $prefixoTipo) {
            $sufixo = preg_replace('/^' . preg_quote($prefixoTipo, '/') . '/', '', $sufixo, 1);
        }

        if ($sufixo !== '' && $livre($base . $sufixo)) {
            return $base . $sufixo;
        }

        for ($i = 2; $i <= 99; $i++) {
            if ($livre($base . $i)) {
                return $base . $i;
            }
        }

        return (string) $serie->series_code;
    }

    /**
     * Liberta o código base para a série por omissão.
     *
     * Se quem o ocupa é uma série secundária e sem documentos emitidos, empurra-a
     * para um código com sufixo. Não mexe em séries com histórico nem registadas
     * na AGT — nesses casos a padrão fica simplesmente com sufixo.
     */
    protected function libertarCodigoBase(InvoicingSeries $serie, string $base): void
    {
        $ocupante = InvoicingSeries::where('tenant_id', $serie->tenant_id)
            ->where('document_type', $serie->document_type)
            ->where('series_code', $base)
            ->whereKeyNot($serie->id)
            ->first();

        if (!$ocupante
            || $ocupante->is_default
            || !empty($ocupante->agt_series_id)
            || $this->documentosEmitidos($ocupante) > 0) {
            return;
        }

        $usados = $this->codigosDoGrupo($ocupante);
        $sufixo = null;
        for ($i = 2; $i <= 99; $i++) {
            if (!in_array($base . $i, $usados, true)) {
                $sufixo = $base . $i;
                break;
            }
        }

        if (!$sufixo) {
            return;
        }

        // Passo intermédio: o UNIQUE não deixa as duas terem o mesmo código,
        // nem sequer por um instante.
        DB::transaction(function () use ($ocupante, $sufixo) {
            $ocupante->series_code = $sufixo;
            if (empty($ocupante->name) || str_starts_with((string) $ocupante->name, 'Série ')) {
                $ocupante->name = "Série {$sufixo}";
            }
            $ocupante->save();
        });

        $this->line("      <fg=gray>libertado {$base}: série #{$ocupante->id} (secundária, sem "
            . "documentos) passou a {$sufixo}</>");
    }

    /** Códigos já ocupados no grupo (mesma empresa, mesmo tipo de documento). */
    protected function codigosDoGrupo(InvoicingSeries $serie): array
    {
        return InvoicingSeries::where('tenant_id', $serie->tenant_id)
            ->where('document_type', $serie->document_type)
            ->whereKeyNot($serie->id)
            ->pluck('series_code')
            ->all();
    }

    /** Documentos já emitidos por esta série. */
    protected function documentosEmitidos(InvoicingSeries $serie): int
    {
        $tabela = match ($serie->document_type) {
            'invoice', 'pos' => 'invoicing_sales_invoices',
            'proforma'       => 'invoicing_sales_proformas',
            'receipt'        => 'invoicing_receipts',
            'credit_note'    => 'invoicing_credit_notes',
            'debit_note'     => 'invoicing_debit_notes',
            'purchase'       => 'invoicing_purchase_invoices',
            'transport'      => 'invoicing_transport_guides',
            default          => null,
        };

        if (!$tabela || !Schema::hasTable($tabela)) {
            // Sem tabela conhecida não há como provar que é seguro: contar como
            // usada, para nunca renomear às cegas.
            return max(1, (int) $serie->next_number - 1);
        }

        return DB::table($tabela)->where('series_id', $serie->id)->count();
    }

    /** Preenche ATCUD em documentos de séries já registadas na AGT. */
    protected function preencherAtcud(bool $dry, ?string $tenantId): int
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>2) ATCUD em falta</>');

        $total = 0;

        foreach (self::DOCUMENTOS as $tabela => $coluna) {
            if (!Schema::hasTable($tabela) || !Schema::hasColumn($tabela, 'atcud')) {
                continue;
            }

            $q = DB::table($tabela . ' as d')
                ->join('invoicing_series as s', 's.id', '=', 'd.series_id')
                ->whereNotNull('s.agt_series_id')
                ->where(function ($w) {
                    $w->whereNull('d.atcud')->orWhere('d.atcud', '');
                })
                ->select("d.id", "d.{$coluna} as numero", 'd.tenant_id',
                    's.id as serie_id', 's.atcud_validation_code');

            if ($tenantId) {
                $q->where('d.tenant_id', $tenantId);
            }

            $linhas = $q->get();
            if ($linhas->isEmpty()) {
                continue;
            }

            $this->line("   <fg=cyan>{$tabela}</>: {$linhas->count()} documento(s)");

            foreach ($linhas as $linha) {
                $serie = InvoicingSeries::find($linha->serie_id);
                if (!$serie) {
                    continue;
                }

                // Sequencial = posição na série (o /000003 do número fiscal).
                $atcud = $serie->generateATCUD(
                    $serie->nextSequentialFromDocumentNumber((string) $linha->numero)
                );

                $this->line("      {$linha->numero} → <fg=green>{$atcud}</>");

                if (!$dry) {
                    DB::table($tabela)->where('id', $linha->id)->update(['atcud' => $atcud]);
                }
                $total++;
            }
        }

        if ($total === 0) {
            $this->line('   <fg=green>Nenhum documento com ATCUD em falta.</>');
        }

        return $total;
    }
}
