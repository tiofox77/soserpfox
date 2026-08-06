<?php

namespace App\Console\Commands\AGT;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tira o "SOS" do início dos números já gravados.
 *
 * A AGT recusa todo o documento cujo número não comece pelo tipo:
 *
 *   E32 — Código de série mal construído (SOS FR7626S6286N/000045).
 *
 * Medido: 25 documentos com "SOS" → 25 recusados; 5 com o tipo → 5 validados.
 * O gerador já foi corrigido, mas os documentos JÁ EMITIDOS têm o número
 * gravado e continuariam a ser recusados para sempre.
 *
 * ATENÇÃO — isto renumera documentos fiscais.
 *
 * Faz sentido em homologação, onde os documentos são de teste. Em produção,
 * renumerar um documento já entregue a um cliente não é uma correcção técnica,
 * é uma questão fiscal: se algum documento com "SOS" chegou a ser aceite pela
 * AGT ou entregue a um cliente, o caminho não é este — é anulá-lo e emitir de
 * novo. O comando recusa-se a correr em empresas cujo ambiente activo seja
 * produção, a não ser com --forcar-producao.
 *
 * A cadeia de hash SAF-T inclui o número do documento e encadeia-se no hash
 * anterior. Mudar um número invalida o hash desse documento E o de todos os
 * seguintes, por isso a cadeia é refeita por ordem de emissão.
 */
class RenumerarDocumentosSos extends Command
{
    protected $signature = 'agt:renumerar-sos
                            {--tenant= : Só esta empresa}
                            {--aplicar : Grava mesmo. Sem isto apenas simula}
                            {--refazer-hash : Não renumera; só refaz a cadeia de hash, por ordem de emissão}
                            {--forcar-producao : Deixa correr em empresas com ambiente de produção}';

    protected $description = 'Corrige números que começam por SOS (a AGT recusa-os com E32) e refaz a cadeia de hash';

    /** Documentos com número próprio e cadeia de hash. */
    private const MODELOS = [
        SalesInvoice::class => 'invoice_number',
        CreditNote::class   => 'credit_note_number',
        DebitNote::class    => 'debit_note_number',
    ];

    public function handle(): int
    {
        $aplicar  = (bool) $this->option('aplicar');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info('=== Renumeração de documentos com prefixo SOS ===');
        $this->line($aplicar ? '  MODO REAL — os documentos vão ser alterados' : '  simulação (use --aplicar para gravar)');
        $this->newLine();

        $empresas = $this->empresasAbrangidas($tenantId);

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa a tratar.');
            return self::SUCCESS;
        }

        $totalDocs = 0;

        foreach ($empresas as $empresa) {
            $totalDocs += $this->tratarEmpresa((int) $empresa, $aplicar);
        }

        $this->newLine();
        $this->info("Documentos {$this->verbo($aplicar)}: {$totalDocs}");

        if (!$aplicar && $totalDocs > 0) {
            $this->newLine();
            $this->warn('Nada foi alterado. Repita com --aplicar quando confirmar os números acima.');
        }

        return self::SUCCESS;
    }

    private function verbo(bool $aplicar): string
    {
        return $aplicar ? 'corrigidos' : 'a corrigir';
    }

    /** Empresas a tratar, excluindo as que emitem em produção. */
    private function empresasAbrangidas(?int $tenantId)
    {
        // A refazer a cadeia, o critério não é ter "SOS" — já não tem.
        if ($this->option('refazer-hash') && $tenantId) {
            return collect([$tenantId])->filter(fn ($id) => $this->podeTratar((int) $id))->values();
        }

        $empresas = collect();

        foreach (self::MODELOS as $modelo => $coluna) {
            $q = $modelo::withoutGlobalScopes()->where($coluna, 'like', 'SOS %');
            if ($tenantId) {
                $q->where('tenant_id', $tenantId);
            }
            $empresas = $empresas->merge($q->distinct()->pluck('tenant_id'));
        }

        return $empresas->unique()->filter()->values()
            ->filter(fn ($id) => $this->podeTratar((int) $id))
            ->values();
    }

    private function podeTratar(int $tenantId): bool
    {
        $ambiente = \App\Services\AGT\AGTProducerStore::ambienteDaEmpresa($tenantId);

        if ($ambiente === 'production' && !$this->option('forcar-producao')) {
            $this->warn("  empresa {$tenantId} ignorada: emite em PRODUÇÃO. "
                . 'Renumerar documentos fiscais já emitidos é decisão fiscal, não técnica. '
                . 'Use --forcar-producao apenas se tiver a certeza.');

            return false;
        }

        return true;
    }

    /**
     * Refaz a cadeia de hash de uma empresa, por ordem de emissão.
     *
     * Necessário depois de renumerar: o hash cobre o número do documento, e
     * encadeia-se no anterior — mudar um número invalida esse hash e todos os
     * que se lhe seguem.
     */
    private function refazerCadeia(int $tenantId, bool $aplicar): int
    {
        $this->line("<info>Empresa {$tenantId}</info> — refazer cadeia de hash");
        $feitos = 0;

        foreach (self::MODELOS as $modelo => $coluna) {
            $docs = $modelo::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('hash')
                ->orderBy('id')
                ->get();

            foreach ($docs as $doc) {
                $feitos++;

                if (!$aplicar || !method_exists($doc, 'generateHashAndSign')) {
                    continue;
                }

                $doc->generateHashAndSign();
                $doc->saveQuietly();
            }

            if ($docs->count()) {
                $this->line('    ' . class_basename($modelo) . ': ' . $docs->count() . ' documento(s)');
            }
        }

        return $feitos;
    }

    private function tratarEmpresa(int $tenantId, bool $aplicar): int
    {
        if ($this->option('refazer-hash')) {
            return $this->refazerCadeia($tenantId, $aplicar);
        }

        $this->line("<info>Empresa {$tenantId}</info>");
        $corrigidos = 0;

        foreach (self::MODELOS as $modelo => $coluna) {
            $docs = $modelo::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where($coluna, 'like', 'SOS %')
                // Ordem de emissão: a cadeia de hash tem de ser refeita assim.
                ->orderBy('id')
                ->get();

            foreach ($docs as $doc) {
                $antigo = (string) $doc->{$coluna};
                $novo   = $this->numeroCorrigido($doc, $antigo);

                if ($novo === null || $novo === $antigo) {
                    $this->line("    <comment>{$antigo}</comment> — sem série conhecida, deixado como está");
                    continue;
                }

                $this->line("    {$antigo}  →  {$novo}");
                $corrigidos++;

                if (!$aplicar) {
                    continue;
                }

                DB::transaction(function () use ($doc, $coluna, $antigo, $novo) {
                    $doc->{$coluna} = $novo;
                    $doc->saveQuietly();

                    // A cadeia SAF-T inclui o número: sem refazer, o hash fica a
                    // atestar um número que já não é o do documento.
                    if (method_exists($doc, 'generateHashAndSign')) {
                        $doc->generateHashAndSign();
                        $doc->saveQuietly();
                    }

                    // As submissões guardam o número por extenso.
                    DB::table('agt_submissions')
                        ->where('tenant_id', $doc->tenant_id)
                        ->where('document_number', $antigo)
                        ->update(['document_number' => $novo]);
                });
            }
        }

        return $corrigidos;
    }

    /**
     * Troca o primeiro token pelo prefixo da série do documento.
     *
     * Usa a série a que o documento pertence, não uma regra sobre o texto: é
     * ela que sabe o tipo. Sem série identificável, não se adivinha.
     */
    private function numeroCorrigido($doc, string $antigo): ?string
    {
        $resto = preg_replace('/^SOS\s+/', '', $antigo);

        if ($resto === $antigo) {
            return null;
        }

        $serie = $doc->series ?? null;

        if (!$serie && !empty($doc->series_id)) {
            $serie = \App\Models\Invoicing\InvoicingSeries::withoutGlobalScopes()->find($doc->series_id);
        }

        $prefixo = $serie?->prefix;

        if (!$prefixo) {
            // Recurso: o código da série da AGT começa pelo tipo (FR7626S…).
            if (preg_match('/^([A-Z]{2})/', $resto, $m)) {
                $prefixo = $m[1];
            }
        }

        return $prefixo ? "{$prefixo} {$resto}" : null;
    }
}
