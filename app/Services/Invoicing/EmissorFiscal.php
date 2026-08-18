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

    /** À AGT, só se a empresa tiver a comunicação automática ligada. */
    public function comunicar(Model $documento, int $tenantId): void
    {
        try {
            $definicoes = InvoicingSettings::forTenant($tenantId);

            if (empty($definicoes->agt_auto_submit)) {
                return;
            }

            // fresh(): a colecção de linhas deste objecto foi lida na criação,
            // antes de existirem linhas, e o Eloquent guardou-a vazia. Sem isto
            // o documento seguia para a AGT com os totais e ZERO linhas.
            $actual = $documento->fresh();

            if ($actual && method_exists($actual, 'submitToAGT')) {
                $actual->submitToAGT();
            }
        } catch (\Throwable $e) {
            Log::error('EmissorFiscal: erro ao submeter à AGT', [
                'documento' => $documento->getKey(),
                'tipo'      => $documento::class,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
