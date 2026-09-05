<?php

namespace App\Livewire\Concerns;

use App\Models\Invoicing\InvoicingSettings;

/**
 * Em que papel sai a venda: talão de 80 mm ou factura A4.
 *
 * ISTO VIVE AQUI, E NÃO EM CADA COMPONENTE, POR UM MOTIVO CONCRETO. O modal de
 * impressão (`livewire.pos.partials.print-modal`) é PARTILHADO por três ecrãs:
 * o POS, o POS do salão (que herda dele) e o relatório de vendas do POS.
 * Quando o selector do papel entrou, entrou só no POS — e o relatório passou a
 * rebentar com «Undefined variable $formatoImpressao» assim que alguém abria o
 * talão de uma venda em /invoicing/pos/reports.
 *
 * Quem incluir aquele partial usa este trait. É a única forma de a próxima
 * pessoa não repetir o mesmo erro, e é a mesma regra que já se aplica ao
 * PinDeTurno, à Geografia e ao TaxResolver: uma implementação, não duas.
 *
 * O TALÃO É O QUE VEM PRIMEIRO. Quem está ao balcão imprime talão; a factura
 * em A4 é a excepção, para a venda a uma empresa. Um valor que não se
 * reconheça cai no talão, nunca em A4.
 */
trait FormatoDeImpressao
{
    /** 'talao' | 'a4' — o papel em que a venda está a ser mostrada. */
    public $formatoImpressao = 'talao';

    /**
     * Troca o papel SÓ DESTA impressão.
     *
     * A configuração da empresa fica como está: há sempre a venda que precisa
     * do outro papel, e obrigar a mudar as definições para imprimir uma
     * factura é obrigar a mudá-las outra vez a seguir.
     */
    public function trocarFormatoImpressao(string $formato): void
    {
        $this->formatoImpressao = $formato === 'a4' ? 'a4' : 'talao';
    }

    /**
     * O papel que a empresa configurou, para o modal abrir já no dela.
     *
     * Chamar ao abrir o modal — nunca no `mount()`: a configuração pode mudar
     * entre duas vendas e o operador não recarrega a página do balcão.
     */
    protected function formatoConfigurado(): string
    {
        $configurado = InvoicingSettings::forTenant(activeTenantId())?->pos_formato_impressao;

        return $configurado === 'a4' ? 'a4' : 'talao';
    }
}
