<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnóstico das séries documentais de uma empresa.
 *
 * As séries são a peça onde um erro é caro e silencioso: o número sai errado,
 * o documento é emitido na mesma, a AGT recusa-o depois, e a factura já está
 * na mão do cliente. Nenhuma das verificações abaixo é hipotética — todas
 * correspondem a formas concretas de a numeração se estragar.
 *
 * SÓ LÊ. Não corrige nada, não escreve nada, e não mostra dados de clientes —
 * apenas configuração e contagens.
 *
 * Uso:
 *   php artisan series:diagnostico
 *   php artisan series:diagnostico --tenant=farmacia
 *   php artisan series:diagnostico --tenant=12
 */
class DiagnosticoDeSeries extends Command
{
    protected $signature = 'series:diagnostico
                            {--tenant= : id, slug, nome ou parte do nome da empresa}';

    protected $description = 'Verifica as séries documentais à procura de erros e conflitos (só leitura)';

    /** Onde vive o número de cada tipo de documento. */
    private const TABELAS = [
        'invoice'     => ['invoicing_sales_invoices',   'invoice_number'],
        'pos'         => ['invoicing_sales_invoices',   'invoice_number'],
        'proforma'    => ['invoicing_sales_proformas',  'proforma_number'],
        'receipt'     => ['invoicing_receipts',         'receipt_number'],
        'credit_note' => ['invoicing_credit_notes',     'credit_note_number'],
        'debit_note'  => ['invoicing_debit_notes',      'debit_note_number'],
        'advance'     => ['invoicing_advances',         'advance_number'],
        'purchase'    => ['invoicing_purchase_invoices', 'purchase_number'],
    ];

    private int $problemas = 0;

    public function handle(): int
    {
        $empresas = $this->empresas();

        if ($empresas->isEmpty()) {
            $this->error('Nenhuma empresa encontrada com esse critério.');

            return self::FAILURE;
        }

        foreach ($empresas as $empresa) {
            $this->diagnosticar($empresa);
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));

        if ($this->problemas === 0) {
            $this->info('Nada a assinalar.');
        } else {
            $this->warn("{$this->problemas} ponto(s) a corrigir.");
        }

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

    private function diagnosticar(Tenant $empresa): void
    {
        $series = InvoicingSeries::where('tenant_id', $empresa->id)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info("EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line("séries: {$series->count()}");

        if ($series->isEmpty()) {
            $this->aviso('Sem séries nenhumas: nenhum documento pode ser emitido.');

            return;
        }

        $this->prefixosDivergentes($series);
        $this->padraoPorTipo($series, $empresa);
        $this->codigosRepetidos($series);
        $this->numeracaoDessincronizada($series, $empresa);
        $this->porRegistarNaAgt($series, $empresa);
    }

    /**
     * O primeiro token do número TEM de ser o tipo de documento da AGT.
     *
     * A coluna `prefix` é texto livre no formulário (required|max:10) e é ela
     * que entra no número — mas o ecrã mostra o prefixo canónico do catálogo.
     * Quando divergem, o ecrã diz uma coisa e o documento sai com outra.
     *
     * Não é cosmético: a AGT lê o primeiro token para classificar o documento.
     * Está medido neste projecto — 25 documentos com um primeiro token errado
     * foram os 25 recusados com E32, e os 5 com o token certo passaram todos.
     */
    private function prefixosDivergentes($series): void
    {
        foreach ($series as $s) {
            $canonico = InvoicingSeries::AGT_PREFIXES[$s->document_type] ?? null;

            if (!$canonico) {
                $this->aviso("série #{$s->id} tem document_type '{$s->document_type}', que não está no catálogo AGT.");
                continue;
            }

            if ((string) $s->prefix !== $canonico) {
                $this->aviso(sprintf(
                    "série #%d (%s, %s): prefixo gravado '%s' mas o da AGT é '%s'. "
                        . "O número sai '%s' — o ecrã mostra '%s'.",
                    $s->id,
                    $s->document_type,
                    $s->series_code,
                    $s->prefix,
                    $canonico,
                    $s->previewNextNumber(),
                    $canonico
                ));
            }
        }
    }

    /**
     * Uma série padrão por tipo — nem zero, nem duas.
     *
     * E o conflito que mais custa: a padrão está por estrear enquanto outra já
     * vai adiantada. Tudo o que se emitir a seguir passa a sair pela padrão,
     * numa numeração paralela — duas séries do mesmo tipo a andar ao mesmo
     * tempo, o que a AGT não perdoa num SAFT.
     */
    private function padraoPorTipo($series, Tenant $empresa): void
    {
        foreach ($series->groupBy('document_type') as $tipo => $doTipo) {
            $padroes = $doTipo->where('is_default', true);

            if ($padroes->count() > 1) {
                $this->aviso(sprintf(
                    "%s: %d séries marcadas como padrão (%s). Só uma pode ser.",
                    $tipo,
                    $padroes->count(),
                    $padroes->pluck('series_code')->join(', ')
                ));
            }

            if ($padroes->isEmpty() && $doTipo->count() > 0) {
                $this->aviso("{$tipo}: nenhuma série marcada como padrão.");
            }

            if ($doTipo->count() < 2) {
                continue;
            }

            $padrao = $padroes->first();

            if (!$padrao) {
                continue;
            }

            $emUso = $doTipo->where('id', '!=', $padrao->id)
                ->filter(fn ($s) => (int) $s->next_number > 1);

            if ($padrao->next_number <= 1 && $emUso->isNotEmpty()) {
                $this->aviso(sprintf(
                    "%s: a série padrão '%s' está por estrear (próximo %d), mas '%s' já vai no %d. "
                        . "O que se emitir a seguir sai pela padrão e começa uma segunda numeração em paralelo.",
                    $tipo,
                    $padrao->series_code,
                    $padrao->next_number,
                    $emUso->first()->series_code,
                    $emUso->first()->next_number
                ));
            }
        }
    }

    /** Dois códigos iguais no mesmo tipo tornam o número ambíguo. */
    private function codigosRepetidos($series): void
    {
        foreach ($series->groupBy('document_type') as $tipo => $doTipo) {
            $repetidos = $doTipo->groupBy('series_code')->filter(fn ($g) => $g->count() > 1);

            foreach ($repetidos as $codigo => $g) {
                $this->aviso("{$tipo}: o código de série '{$codigo}' aparece {$g->count()} vezes.");
            }
        }
    }

    /**
     * O próximo número tem de estar à frente do último emitido.
     *
     * Se ficar atrás, a próxima emissão tenta um número que já existe. O índice
     * único apanha-a e o documento falha — mas só na hora de gravar, com o
     * cliente à espera.
     */
    private function numeracaoDessincronizada($series, Tenant $empresa): void
    {
        foreach ($series as $s) {
            [$tabela, $coluna] = self::TABELAS[$s->document_type] ?? [null, null];

            if (!$tabela || !Schema::hasTable($tabela) || !Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            // O número emitido tem a forma "FT FT/000123": o que interessa é a
            // parte depois da barra, e só das linhas desta série.
            $serie = $s->agt_series_id ?: preg_replace('/^SOS/', '', (string) $s->series_code);
            $inicio = trim(($s->prefix ?: '') . ' ' . $serie) . '/';

            $maior = DB::table($tabela)
                ->where('tenant_id', $empresa->id)
                ->where($coluna, 'like', $inicio . '%')
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$coluna}, '/', -1) AS UNSIGNED)) as maior")
                ->value('maior');

            if ($maior === null) {
                continue;
            }

            if ((int) $s->next_number <= (int) $maior) {
                $this->aviso(sprintf(
                    "série #%d (%s, %s): próximo número é %d mas já foi emitido o %d. "
                        . "A emissão seguinte colide com um documento existente.",
                    $s->id,
                    $s->document_type,
                    $s->series_code,
                    $s->next_number,
                    $maior
                ));
            }
        }
    }

    /**
     * Uma série sem código da AGT não devia ter documentos emitidos.
     *
     * O agt_series_id é o elo com o portal. Sem ele, os documentos vão com o
     * nosso código interno, que a AGT não conhece.
     */
    private function porRegistarNaAgt($series, Tenant $empresa): void
    {
        foreach ($series as $s) {
            if ($s->agt_series_id) {
                continue;
            }

            if ((int) $s->next_number <= 1) {
                continue;   // por estrear: ainda vai a tempo de ser registada
            }

            $this->aviso(sprintf(
                "série #%d (%s, %s): sem código AGT registado, mas já emitiu %d documento(s).",
                $s->id,
                $s->document_type,
                $s->series_code,
                (int) $s->next_number - 1
            ));
        }
    }

    private function aviso(string $texto): void
    {
        $this->problemas++;
        $this->warn('  ⚠  ' . $texto);
    }
}
