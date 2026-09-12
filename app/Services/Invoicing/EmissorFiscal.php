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
     * Enviar o documento à AGT é uma chamada de rede. Feita na hora, no POS, é
     * o operador a esperar pela AGT com o cliente à frente — e uma AGT lenta ou
     * em baixo transforma-se em vendas que parecem encravadas.
     *
     * O que se faz aqui é DEIXAR EM FILA: cria-se a submissão pendente e o
     * DespacharAgtPendentes trata dela em `terminate`, depois de a resposta já
     * ter seguido para o ecrã. O documento fica gravado e numerado de
     * qualquer maneira; a comunicação é assíncrona por natureza e o sistema já
     * a sabe retomar.
     */
    public function comunicar(Model $documento, int $tenantId): void
    {
        /*
         * UMA PORTA SÓ PARA A FILA DA AGT.
         *
         * Isto era uma SEGUNDA implementação do enfileiramento, ao lado do
         * `AutoSubmissao::enfileirar()` que o resto da facturação usa — e as
         * duas já não diziam o mesmo: esta olhava para a EXISTÊNCIA de uma
         * submissão, e uma submissão REJEITADA bloqueava o reenvio para sempre;
         * a outra olha para o ESTADO, e uma rejeitada volta à fila.
         *
         * É a armadilha de sempre: duas cópias da mesma regra acabam
         * diferentes, e a que está errada é a que ninguém está a olhar.
         */
        \App\Services\AGT\AutoSubmissao::enfileirar($documento, $tenantId);
    }
}
