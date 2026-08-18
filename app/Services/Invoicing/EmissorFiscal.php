<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * O que torna um documento FISCAL, num sítio só.
 *
 * Numerar pela série certa, selar com o hash encadeado e comunicar à AGT são
 * as três coisas que todos os documentos fiscais precisam e que nenhum pode
 * fazer à sua maneira. Até aqui só o POS as sabia fazer, no meio do seu
 * próprio código de venda — e cada tipo de documento novo que precisasse
 * disto acabaria com a sua cópia.
 *
 * Cópias da numeração divergem. E quando a numeração de uma série diverge, a
 * AGT recusa os documentos e o que já foi entregue ao cliente só se corrige
 * com notas de crédito.
 *
 * O QUE ESTE SERVIÇO NÃO FAZ, de propósito: calcular totais, dar baixa de
 * stock, lançar tesouraria, ligar a turnos. Isso é de cada documento e de
 * cada módulo — aqui fica só o que é fisco.
 */
class EmissorFiscal
{
    /**
     * O próximo número da série de emissão deste tipo de documento.
     *
     * @return array{serie: InvoicingSeries, numero: string}
     */
    public function numerar(int $tenantId, string $tipo): array
    {
        $serie = InvoicingSeries::getIssuanceSeries($tenantId, $tipo);

        return [
            'serie'  => $serie,
            'numero' => $serie->getNextNumber(),
        ];
    }

    /**
     * Sela o documento: hash encadeado e, se a empresa o quiser, comunicação.
     *
     * OS ERROS NÃO SOBEM, e isso é deliberado. Um documento já gravado, com
     * número atribuído, não pode ser desfeito porque a AGT não respondeu — a
     * venda aconteceu, o cliente levou o talão, e o número já saiu da série.
     * Fica registado e retenta-se depois; rebentar aqui é perder a venda.
     */
    public function selar(Model $documento, int $tenantId): void
    {
        $this->assinar($documento);
        $this->comunicar($documento, $tenantId);
    }

    /** O hash SAFT-AO, que encadeia este documento no anterior. */
    public function assinar(Model $documento): void
    {
        try {
            if (method_exists($documento, 'generateHash')) {
                $documento->generateHash();
            }
        } catch (\Throwable $e) {
            Log::error('EmissorFiscal: erro ao gerar hash', [
                'documento' => $documento->getKey(),
                'tipo'      => $documento::class,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * À AGT — mas NUNCA durante a venda.
     *
     * O submitToAGT() faz a chamada de rede na hora. No POS isso é o operador
     * a esperar pela AGT com o cliente à frente, e uma AGT lenta ou em baixo
     * transforma-se em vendas que parecem encravadas.
     *
     * O que se faz aqui é DEIXAR EM FILA: cria-se a submissão pendente e o
     * DespacharAgtPendentes trata dela em `terminate`, depois de a resposta já
     * ter seguido para o ecrã. O documento fica gravado e numerado de
     * qualquer maneira; a comunicação é assíncrona por natureza e o sistema já
     * a sabe retomar.
     */
    public function comunicar(Model $documento, int $tenantId): void
    {
        try {
            $definicoes = InvoicingSettings::forTenant($tenantId);

            if (empty($definicoes->agt_auto_submit)) {
                return;
            }

            $actual = $documento->fresh();

            if (!$actual) {
                return;
            }

            // Já em fila ou já tratada: não se duplica.
            $jaExiste = \App\Models\AGT\AGTSubmission::where('tenant_id', $tenantId)
                ->where('document_type', $actual::class)
                ->where('document_id', $actual->getKey())
                ->exists();

            if ($jaExiste) {
                return;
            }

            \App\Models\AGT\AGTSubmission::create([
                'tenant_id'       => $tenantId,
                'document_type'   => $actual::class,
                'document_id'     => $actual->getKey(),
                'document_number' => $actual->invoice_number
                    ?? $actual->receipt_number
                    ?? $actual->credit_note_number
                    ?? (string) $actual->getKey(),
                // O código do tipo é obrigatório: é ele que diz à AGT que
                // documento é. Vem do próprio documento quando existe.
                'document_type_code' => $actual->invoice_type
                    ?? $actual->document_type
                    ?? 'FT',
                'agt_environment' => \App\Services\AGT\AGTKeyStore::ambiente($tenantId),
                'status'          => \App\Models\AGT\AGTSubmission::STATUS_PENDING,
                'retry_count'     => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('EmissorFiscal: erro ao pôr em fila para a AGT', [
                'documento' => $documento->getKey(),
                'tipo'      => $documento::class,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
