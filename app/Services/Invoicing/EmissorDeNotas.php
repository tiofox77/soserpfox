<?php

namespace App\Services\Invoicing;

use App\Helpers\InvoiceCalculationHelper;
use App\Helpers\SAFTHelper;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\CreditNoteItem;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\DebitNoteItem;
use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Models\Treasury\Transaction;
use App\Services\AGT\AutoSubmissao;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EMITIR NOTAS DE CRÉDITO E DE DÉBITO — num sítio só.
 *
 * Este caminho vivia DENTRO de cada componente Livewire, ~250 linhas quase
 * iguais em `CreditNoteCreate` e `DebitNoteCreate`. Ao migrar os ecrãs para
 * React, a alternativa era um controlador de API que as repetisse uma terceira
 * vez — e uma terceira cópia de um caminho FISCAL é o que a AGT recusa dias
 * depois, quando já não há como desfazer. Saiu tudo para aqui, e os dois
 * Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE, e que nenhum chamador pode saltar:
 *
 *  · O TRAVÃO DO E43, pelo total E por linha. A AGT recusa quando a soma das
 *    notas passa o que a factura ainda tem por anular — e recusa DEPOIS de o
 *    documento ter número, hash e assinatura. Por isso trava-se ANTES de nascer.
 *    Conta o que OUTRAS notas já tiraram: duas parciais passam o total sem que
 *    nenhuma o pareça sozinha. E por linha, porque uma nota pode bater certo no
 *    total e creditar 69 unidades de uma linha que só teve 34 — foi assim que
 *    saiu a NC4226S46906N/000002.
 *
 *  · A REGIÃO, O CÓDIGO SAFT E O MOTIVO DE ISENÇÃO DE CADA LINHA vêm da linha
 *    ORIGINAL da factura. Fixá-los fazia o crédito de uma factura de Cabinda ou
 *    a 7% sair como AO / taxa normal.
 *
 *  · O IEC E O IMPOSTO DE SELO da linha original são copiados, e o IVA
 *    RECALCULADO sobre o líquido acrescido do IEC — sem isso a nota creditava
 *    um IVA menor do que o liquidado e a AGT recusava com «taxContribution não
 *    corresponde ao imposto apurado».
 *
 *  · O HASH assina o `gross_total`, por isso só se gera depois de os totais
 *    estarem fechados — e os totais só fecham depois das linhas de IEC/IS.
 *
 *  · A AGT em SEGUNDO passo, DEPOIS do commit: a nota já existe e uma AGT em
 *    baixo não a pode desfazer nem prender o utilizador.
 *
 * Uma linha entra como um objecto com `id` (artigo ou null), `name`, `price`,
 * `quantity` e `attributes` — é a forma do carrinho que o Livewire já usava, e
 * `linhasDaFactura()` produz a mesma a partir de uma factura, para a API.
 */
class EmissorDeNotas
{
    /* ─── As linhas ───────────────────────────────────────────────────── */

    /**
     * As linhas de uma factura, na forma que o emissor espera.
     *
     * É o `loadInvoiceItems` que o Livewire fazia para o carrinho — aqui para
     * qualquer chamador. Herda da linha original tudo o que a AGT vai comparar.
     */
    public function linhasDaFactura(SalesInvoice $factura): Collection
    {
        $factura->loadMissing('items.product');

        return $factura->items->values()->map(function ($item, $index) use ($factura) {
            $taxa = (float) ($item->tax_rate ?? 0);

            return (object) [
                'id' => $item->product_id,
                'name' => $item->description ?? $item->product_name ?? 'Produto',
                'price' => (float) ($item->unit_price ?? 0),
                'quantity' => (float) ($item->quantity ?? 1),
                'attributes' => [
                    'tax_rate' => $taxa,
                    'tax_type' => $taxa > 0 ? 'iva' : 'isento',
                    'exemption_reason' => $item->tax_exemption_code ?? null,
                    'discount_percent' => (float) ($item->discount_percent ?? 0),
                    'tax_code' => $item->tax_code ?? null,
                    'tax_country_region' => $item->tax_country_region ?? 'AO',
                    'origem_line_type' => get_class($item),
                    'origem_line_id' => $item->id,
                    'reference_invoice_no' => $factura->invoice_number,
                    'reference_item_line_no' => $item->order ?? ($index + 1),
                ],
            ];
        });
    }

    /**
     * As linhas desta nota que pedem mais do que a factura ainda tem.
     *
     * Compara-se por ARTIGO, e pela descrição quando a linha não tem artigo do
     * catálogo. O que já foi creditado conta.
     *
     * @return array<int, string>  uma frase por linha em excesso
     */
    public function excessos(SalesInvoice $factura, Collection $linhas, ?int $exceptoNota = null): array
    {
        $chave = fn ($artigo, $descricao) => $artigo
            ? 'p:' . $artigo
            : 'd:' . mb_strtolower(trim((string) $descricao));

        $naFactura = [];

        foreach ($factura->items as $linha) {
            $k = $chave($linha->product_id, $linha->description);
            $naFactura[$k] = ($naFactura[$k] ?? 0) + (float) $linha->quantity;
        }

        $jaCreditado = [];

        $outras = CreditNote::where('invoice_id', $factura->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($exceptoNota, fn ($q) => $q->where('id', '!=', $exceptoNota))
            ->with('items')
            ->get();

        foreach ($outras as $nota) {
            foreach ($nota->items as $linha) {
                $k = $chave($linha->product_id, $linha->description);
                $jaCreditado[$k] = ($jaCreditado[$k] ?? 0) + (float) $linha->quantity;
            }
        }

        $pedido = [];

        foreach ($linhas as $item) {
            $artigo = is_numeric($item->id) ? (int) $item->id : null;
            $k = $chave($artigo, $item->name);
            $pedido[$k] = ($pedido[$k] ?? 0) + (float) $item->quantity;
        }

        $excessos = [];

        foreach ($pedido as $k => $quantidade) {
            $disponivel = ($naFactura[$k] ?? 0) - ($jaCreditado[$k] ?? 0);

            if ($quantidade > $disponivel + 0.001) {
                $nome = str_starts_with($k, 'p:')
                    ? (Product::find((int) substr($k, 2))?->name ?? __('artigo :id', ['id' => substr($k, 2)]))
                    : substr($k, 2);

                $excessos[] = __(':artigo — pede :pede, a factura só tem :tem por anular', [
                    'artigo' => $nome,
                    'pede' => rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ','),
                    'tem' => rtrim(rtrim(number_format(max(0, $disponivel), 3, ',', '.'), '0'), ','),
                ]);
            }
        }

        return $excessos;
    }

    /* ─── Nota de crédito ─────────────────────────────────────────────── */

    /**
     * @param  array{client_id:int, invoice_id?:int|null, issue_date:string,
     *               reason:string, type:string, notes?:string|null}  $dados
     * @return array{nota: CreditNote, fila: array}
     *
     * @throws DomainException  quando o E43 travaria — ANTES de a nota nascer
     */
    public function emitirCredito(array $dados, Collection $linhas): array
    {
        $tenantId = activeTenantId();
        $factura = $this->facturaDe($dados, $tenantId);

        $totais = InvoiceCalculationHelper::calculateTotals($linhas, 0, 0, 0, false);

        /*
         * O TRAVÃO, antes de o número ser atribuído.
         */
        if ($factura) {
            $excessos = $this->excessos($factura, $linhas);

            if ($excessos !== []) {
                throw new DomainException(__('A factura :factura não tem essas quantidades por anular: :linhas', [
                    'factura' => $factura->invoice_number,
                    'linhas' => implode('; ', $excessos),
                ]));
            }

            $porAnular = $factura->porCreditar();

            if (round((float) $totais['total'], 2) > $porAnular + 0.01) {
                throw new DomainException(__('Esta nota anula :nota, mas a factura :factura só tem :saldo por anular. A AGT recusaria (E43).', [
                    'nota' => number_format((float) $totais['total'], 2, ',', '.'),
                    'factura' => $factura->invoice_number,
                    'saldo' => number_format($porAnular, 2, ',', '.'),
                ]));
            }
        }

        // Expressão obrigatória conforme Art. 12º RJF (Decreto 71/25).
        $expressao = ($dados['type'] ?? 'partial') === 'total' ? 'Anulação' : 'Rectificação';

        $nota = DB::transaction(function () use ($dados, $linhas, $totais, $tenantId, $factura, $expressao) {
            $netTotal = (float) $totais['subtotal_original'];

            $nota = CreditNote::create([
                'tenant_id' => $tenantId,
                'client_id' => $dados['client_id'],
                'invoice_id' => $factura?->id,
                'issue_date' => $dados['issue_date'],
                'system_entry_date' => now(),
                'reason' => $dados['reason'],
                'reason_text' => $expressao,
                'type' => $dados['type'] ?? 'partial',
                'notes' => $dados['notes'] ?? null,
                'subtotal' => $totais['subtotal_original'],
                'net_total' => $netTotal,
                'tax_amount' => $totais['tax_amount'],
                'tax_payable' => $totais['tax_amount'],
                'total' => $totais['total'],
                'gross_total' => $netTotal + (float) $totais['tax_amount'],
                'status' => 'issued',
                'invoice_status' => 'F',
                'source_billing' => 'P',
                'created_by' => auth()->id(),
            ]);

            [$iva, $extra] = $this->criarLinhas($nota, $linhas, $expressao, credito: true);

            // Totais com IEC/IS incluídos — só agora se conhece o imposto total.
            $nota->tax_amount = $iva;
            $nota->tax_payable = $iva + $extra;
            $nota->gross_total = (float) $nota->net_total + $nota->tax_payable;
            $nota->total = $nota->gross_total;
            $nota->save();

            $this->assinar($nota, CreditNote::class, 'credit_note_number', $tenantId);

            // Reverter também a retenção na fonte da factura creditada: sem
            // isto o crédito fica incompleto e a AGT recebe um documento sem
            // a withholdingTaxList que o original declarou.
            if ($factura) {
                $this->copiarRetencoes($factura, $nota, $tenantId);
            }

            return $nota;
        });

        $this->ligarAoTurnoAberto($nota, $tenantId);

        // À AGT em SEGUNDO passo: a nota já está gravada.
        return ['nota' => $nota, 'fila' => AutoSubmissao::enfileirar($nota)];
    }

    /**
     * A DEVOLUÇÃO — na tesouraria E no turno, pelo mesmo valor.
     *
     * Duas coisas estavam erradas, e eram a mesma:
     *
     *  · O TURNO baixava a gaveta e a TESOURARIA não sabia de nada. O fecho de
     *    caixa contava com menos dinheiro e o saldo da caixa continuava alto:
     *    dois números para a mesma gaveta.
     *
     *  · DEVOLVIA-SE SEMPRE, mesmo o que nunca foi pago. Anular uma factura a
     *    prazo que ninguém pagou tirava dinheiro do esperado, e o operador
     *    fechava o turno com um excesso que não era dele.
     *
     * Devolve-se O QUE FOI RECEBIDO e mais nada: o pago da factura, menos o
     * que outras notas da mesma factura já devolveram. Anular uma factura por
     * pagar não mexe num cêntimo.
     *
     * BEST-EFFORT, e depois do commit: a nota já está emitida e assinada, e uma
     * falha a lançar o dinheiro não a pode desfazer.
     */
    private function ligarAoTurnoAberto(CreditNote $nota, int $tenantId): void
    {
        try {
            $devolvido = $this->quantoSeDevolve($nota, $tenantId);

            if ($devolvido <= 0) {
                return;
            }

            $numero = $nota->credit_note_number ?: ('#' . $nota->id);

            app(LancamentoDeDinheiro::class)->lancar($nota, [
                'valor' => $devolvido,
                'forma' => $this->formaDaDevolucao($nota, $tenantId),
                'sentido' => 'expense',
                'categoria' => 'credit_note',
                'data' => $nota->issue_date,
                'invoice_id' => $nota->invoice_id,
                'referencia' => $numero,
                'descricao' => __('Devolução :nota', ['nota' => $numero]),
                'turno' => [
                    'type' => 'credit_note',
                    'reference_number' => $nota->credit_note_number,
                    'metadata' => [
                        'invoice_id' => $nota->invoice_id,
                        'invoice_number' => $nota->invoice?->invoice_number,
                    ],
                ],
            ], auth()->id());
        } catch (\Throwable $e) {
            Log::warning('EmissorDeNotas: falha ao lançar a devolução', [
                'nota' => $nota->credit_note_number,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * O que há mesmo para devolver: o que a factura recebeu, menos o que já
     * foi devolvido por outras notas dela.
     */
    private function quantoSeDevolve(CreditNote $nota, int $tenantId): float
    {
        $factura = $nota->invoice;

        if (! $factura) {
            return 0.0;
        }

        $jaDevolvido = (float) Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('related_type', CreditNote::class)
            ->where('invoice_id', $factura->id)
            ->sum('amount');

        $porDevolver = round((float) $factura->paid_amount - $jaDevolvido, 2);

        return round(max(0, min((float) $nota->total, $porDevolver)), 2);
    }

    /**
     * O MEIO DE PAGAMENTO É O DA VENDA e não uma escolha nova: o dinheiro
     * volta pelo caminho por onde veio. Vem do movimento do turno que registou
     * a venda (o mais fiável — foi esta gaveta que o recebeu), depois do que
     * ficou gravado na factura, e, quando nenhum dos dois se sabe, `other` —
     * que não mexe na contagem da gaveta. Não saber é razão para não tocar no
     * dinheiro esperado, não para adivinhar.
     */
    private function formaDaDevolucao(CreditNote $nota, int $tenantId): string
    {
        $factura = $nota->invoice;
        $turno = PosShift::abertoDe($tenantId, auth()->id());

        $daVenda = ($turno && $factura)
            ? $turno->transactions()->where('type', 'invoice')
                ->where('reference_id', $factura->id)->value('payment_method')
            : null;

        return (string) ($daVenda ?: ($factura?->payment_method ?: 'other'));
    }

    /* ─── Nota de débito ──────────────────────────────────────────────── */

    /**
     * @param  array{client_id:int, invoice_id?:int|null, issue_date:string,
     *               due_date?:string|null, reason:string, notes?:string|null}  $dados
     * @return array{nota: DebitNote, fila: array}
     */
    public function emitirDebito(array $dados, Collection $linhas): array
    {
        $tenantId = activeTenantId();
        $factura = $this->facturaDe($dados, $tenantId);

        $totais = InvoiceCalculationHelper::calculateTotals($linhas, 0, 0, 0, false);

        // A ND acresce ao documento original: é sempre uma rectificação.
        $expressao = 'Rectificação';

        $nota = DB::transaction(function () use ($dados, $linhas, $totais, $tenantId, $factura, $expressao) {
            $nota = DebitNote::create([
                'tenant_id' => $tenantId,
                'client_id' => $dados['client_id'],
                'invoice_id' => $factura?->id,
                'issue_date' => $dados['issue_date'],
                'system_entry_date' => now(),
                'due_date' => $dados['due_date'] ?? null,
                'reason' => $dados['reason'],
                'notes' => $dados['notes'] ?? null,
                'subtotal' => $totais['subtotal_original'],
                'net_total' => $totais['subtotal_original'],
                'tax_amount' => $totais['tax_amount'],
                'tax_payable' => $totais['tax_amount'],
                'total' => $totais['total'],
                'gross_total' => (float) $totais['subtotal_original'] + (float) $totais['tax_amount'],
                'status' => 'issued',
                'invoice_status' => 'F',
                'source_billing' => 'P',
                'created_by' => auth()->id(),
            ]);

            [$iva, $extra, $netLiquido] = $this->criarLinhas($nota, $linhas, $expressao, credito: false);

            // A retenção de uma ND é PROPORCIONAL à base desta nota. Copiar o
            // valor da factura tal e qual fazia uma ND de 10.000 sobre uma
            // factura de 1.000.000 declarar 65.000 de retenção.
            if ($factura) {
                $this->copiarRetencoes($factura, $nota, $tenantId, proporcionalA: $netLiquido);
            }

            // Base = soma das bases das LINHAS, já líquidas de desconto. Antes
            // o net_total era o bruto e o cliente era debitado a mais.
            $nota->net_total = round($netLiquido, 2);
            $nota->tax_amount = $iva;
            $nota->tax_payable = $iva + $extra;
            $nota->gross_total = (float) $nota->net_total + $nota->tax_payable;
            $nota->total = $nota->gross_total;
            $nota->save();

            $this->assinar($nota, DebitNote::class, 'debit_note_number', $tenantId);

            return $nota;
        });

        return ['nota' => $nota, 'fila' => AutoSubmissao::enfileirar($nota)];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function facturaDe(array $dados, int $tenantId): ?SalesInvoice
    {
        if (empty($dados['invoice_id'])) {
            return null;
        }

        return SalesInvoice::where('tenant_id', $tenantId)
            ->with('items')
            ->find($dados['invoice_id']);
    }

    /**
     * As linhas da nota, com o IEC/IS da linha original copiado.
     *
     * @return array{0: float, 1: float, 2: float}  [IVA total, IEC+IS total, base líquida]
     */
    private function criarLinhas($nota, Collection $linhas, string $expressao, bool $credito): array
    {
        $ordem = 0;
        $iva = 0.0;
        $extra = 0.0;
        $netLiquido = 0.0;

        $classeItem = $credito ? CreditNoteItem::class : DebitNoteItem::class;
        $chave = $credito ? 'credit_note_id' : 'debit_note_id';

        foreach ($linhas as $item) {
            $ordem++;
            // NÃO `(array) $item->attributes`: no carrinho do Livewire isso é
            // uma ItemAttributeCollection, e o cast dá as propriedades do
            // OBJECTO (`items`), não os atributos. O helper trata das duas
            // formas — a do carrinho e a dos objectos simples da API.
            $a = InvoiceCalculationHelper::atributos($item);
            $taxa = (float) ($a['tax_rate'] ?? 0);

            $t = InvoiceCalculationHelper::calculateItemTotals(
                $item->price,
                $item->quantity,
                $a['discount_percent'] ?? 0,
                $taxa
            );

            /*
             * `subtotal` do helper JÁ É o líquido da linha (bruto menos o
             * desconto). O código de origem da ND fazia `$netLine - $desconto`
             * — subtraía o desconto DUAS vezes — e o net_total saía abaixo da
             * soma das próprias linhas, com o comentário por cima a dizer o
             * contrário. O ensaio de emissão apanhou-o: 1×1000 e 1×500 a 10%
             * davam 1200 em vez de 1350.
             */
            $netLine = (float) $t['subtotal'];
            $desconto = (float) ($t['discount_amount'] ?? 0);
            $netLiquido += $netLine;

            $linha = $classeItem::create([
                $chave => $nota->id,
                'product_id' => is_numeric($item->id) ? (int) $item->id : null,
                'description' => $item->name,
                'quantity' => $item->quantity,
                'unit' => 'un',
                'unit_price' => $item->price,
                'unit_price_base' => $item->price,
                'discount_percent' => $a['discount_percent'] ?? 0,
                'discount_amount' => $desconto,
                'subtotal' => $netLine,
                'tax_rate' => $taxa,
                'tax_amount' => $t['tax_amount'],
                'total' => $t['total'],
                'order' => $ordem,
                // AGT v1.2 — a NC preenche debitAmount, a ND creditAmount.
                'debit_amount' => $credito ? $netLine : 0,
                'credit_amount' => $credito ? 0 : $netLine,
                'settlement_amount' => $desconto,
                // Região e código SAFT herdados da linha original.
                'tax_country_region' => $a['tax_country_region'] ?? 'AO',
                'tax_code' => ($a['tax_code'] ?? null) ?: ($taxa > 0 ? 'NOR' : 'ISE'),
                // Linha isenta TEM de levar motivo — a AGT rejeita sem código.
                'tax_exemption_code' => $taxa > 0 ? null : Product::normalizeExemptionCode($a['exemption_reason'] ?? null),
                'tax_exemption_reason' => $taxa > 0 ? null : Product::exemptionReasonText($a['exemption_reason'] ?? null),
                'reference_invoice_no' => $a['reference_invoice_no'] ?? null,
                'reference_item_line_no' => $a['reference_item_line_no'] ?? $ordem,
                'reference_reason' => $expressao,
            ]);

            // O IEC e o Imposto de Selo da linha original, proporcionais.
            if (! empty($a['origem_line_id'])) {
                $iecLinha = 0.0;

                $originais = LineTax::where('line_type', $a['origem_line_type'])
                    ->where('line_id', $a['origem_line_id'])
                    ->get();

                foreach ($originais as $orig) {
                    LineTax::create([
                        'tenant_id' => $nota->tenant_id,
                        'line_type' => get_class($linha),
                        'line_id' => $linha->id,
                        'tax_type' => $orig->tax_type,
                        'tax_country_region' => $orig->tax_country_region,
                        'tax_code' => $orig->tax_code,
                        'tax_percentage' => $orig->tax_percentage,
                        'tax_amount' => (float) $orig->tax_amount,
                        'pautal_code' => $orig->pautal_code,
                        'verba_no' => $orig->verba_no,
                        'description' => $orig->description,
                        'tax_exemption_code' => $orig->tax_exemption_code,
                        'tax_exemption_reason' => $orig->tax_exemption_reason,
                    ]);

                    $valor = (float) $orig->tax_amount;
                    $extra += $valor;

                    if ($orig->tax_type === LineTax::TIPO_IEC) {
                        $iecLinha += $valor;
                    }
                }

                // O IVA incide sobre o líquido ACRESCIDO do IEC — igual à
                // factura de origem.
                if ($iecLinha > 0) {
                    // Sobre o líquido (que já tem o desconto) acrescido do IEC.
                    $ivaLinha = round(($netLine + $iecLinha) * $taxa / 100, 2);
                    $linha->tax_amount = $ivaLinha;
                    $linha->total = $netLine + $ivaLinha + $iecLinha;
                    $linha->save();
                }
            }

            $iva += (float) $linha->tax_amount;
        }

        return [$iva, $extra, $netLiquido];
    }

    /**
     * O hash SAFT-AO (Decreto 71/25), encadeado na nota anterior do mesmo tipo.
     *
     * Assina o `gross_total`: só pode ser gerado com os totais fechados.
     */
    private function assinar($nota, string $classe, string $colunaDoNumero, int $tenantId): void
    {
        // O anterior lido SOB BLOQUEIO: ver ElosDaCadeia.
        $anterior = ElosDaCadeia::trancar($classe::where('tenant_id', $tenantId)
            ->where('id', '<', $nota->id)
            ->whereNotNull('saft_hash')
            ->orderByDesc('id'))
            ->first();

        $hash = SAFTHelper::generateHash(
            $nota->issue_date->format('Y-m-d'),
            $nota->system_entry_date->format('Y-m-d H:i:s'),
            $nota->{$colunaDoNumero},
            $nota->gross_total,
            $anterior->saft_hash ?? null
        );

        if ($hash) {
            $nota->update([
                'saft_hash' => $hash,
                'hash' => $hash,
                'hash_previous' => $anterior->saft_hash ?? '',
                'hash_control' => '1',
            ]);
        }
    }

    /**
     * As retenções na fonte da factura, na nota.
     *
     * Na NC copia-se o valor: reverte-se o que foi retido. Na ND é PROPORCIONAL
     * à base da nota — copiar tal e qual declarava a retenção da factura inteira.
     */
    private function copiarRetencoes(SalesInvoice $factura, $nota, int $tenantId, ?float $proporcionalA = null): void
    {
        $retencoes = DB::table('invoicing_withholding_taxes')
            ->where('document_type', SalesInvoice::class)
            ->where('document_id', $factura->id)
            ->get();

        foreach ($retencoes as $ret) {
            $percentagem = (float) ($ret->withholding_tax_percentage ?? 0);

            $valor = $proporcionalA === null
                ? (float) $ret->withholding_tax_amount
                : ($percentagem > 0 ? round($proporcionalA * $percentagem / 100, 2) : 0.0);

            if ($proporcionalA !== null && $valor <= 0) {
                continue;
            }

            DB::table('invoicing_withholding_taxes')->insert([
                'tenant_id' => $tenantId,
                'document_type' => get_class($nota),
                'document_id' => $nota->id,
                'withholding_tax_type' => $ret->withholding_tax_type,
                'withholding_tax_description' => $ret->withholding_tax_description,
                'withholding_tax_percentage' => $percentagem,
                'withholding_tax_amount' => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
