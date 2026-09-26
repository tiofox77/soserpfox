<?php

namespace App\Services\Invoicing;

use App\Helpers\InvoiceCalculationHelper;
use App\Helpers\SAFTHelper;
use App\Models\Client;
use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Product;
use App\Models\Treasury\PaymentMethod;
use App\Observers\SalesInvoiceObserver;
use App\Services\AGT\AutoSubmissao;
use App\Services\POS\DocumentosNoTurno;
use App\Services\Treasury\TreasuryMovementService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EMITIR UMA FACTURA DE VENDA (FT ou FR) — num sítio só.
 *
 * Este caminho vivia dentro do `Sales\InvoiceCreate`, ~490 linhas. Ao migrar
 * o ecrã para React, a alternativa era repeti-las num controlador — uma
 * segunda cópia do caminho fiscal mais delicado da casa. Saiu tudo para aqui;
 * o Livewire e a API chamam o mesmo, e é a única forma de os dois emitirem o
 * mesmo documento.
 *
 * O QUE ESTE CAMINHO GARANTE, por ordem, e porquê cada passo está onde está:
 *
 *  · OS DESCONTOS COMERCIAIS GLOBAIS DESCEM ÀS LINHAS antes do IVA. Enquanto
 *    não desciam, o resumo do ecrã e o documento gravado apuravam impostos
 *    diferentes e a soma das linhas não fechava com o cabeçalho — que é o
 *    que a AGT valida. O financeiro NÃO desce: é acerto de pronto pagamento,
 *    sai do total já depois do imposto.
 *
 *  · O IEC ENTRA NA BASE DO IVA; o Imposto de Selo não. Calcular o IVA só
 *    sobre o líquido dava um taxContribution que a AGT recusava.
 *
 *  · A REGIÃO, O CÓDIGO SAFT E O MOTIVO DE ISENÇÃO de cada linha vêm do
 *    `TaxResolver` e da província do adquirente — nunca fixos. Fixar 'NOR'
 *    fazia as taxas reduzidas irem declaradas como normais; fixar 'AO' fazia
 *    Cabinda perder o regime próprio.
 *
 *  · net_total É A SOMA DOS LÍQUIDOS DAS LINHAS, tax_payable inclui TODOS os
 *    impostos, e gross_total = net + tax — senão a AGT recusa o documento.
 *
 *  · A FACTURA-RECIBO NASCE PAGA: vale como factura E recibo, fica liquidada
 *    e o valor entra logo na tesouraria. Best-effort na tesouraria: uma falha
 *    aí não pode anular um documento fiscal já numerado e assinado.
 *
 *  · O HASH assina o gross_total e encadeia na factura anterior.
 *
 *  · A BAIXA DE STOCK É AQUI e não no observer do `created`: nesse momento
 *    ainda não há linhas. O `reduceStock()` é idempotente.
 *
 *  · A AGT EM SEGUNDO PASSO, DEPOIS DO COMMIT: só se enfileira.
 *
 * O `save()` do Livewire foi reduzido a: validar, ler o carrinho, chamar isto,
 * dizer o que aconteceu.
 */
class EmissorDeFacturas
{
    public const DESCRICOES_RETENCAO = [
        'IRT' => 'Imposto sobre o Rendimento do Trabalho',
        'II'  => 'Imposto Industrial',
        'IS'  => 'Imposto de Selo',
        'IVA' => 'IVA cativado',
        'IP'  => 'Imposto Predial',
        'IAC' => 'Imposto sobre a Aplicação de Capitais',
        'OU'  => 'Outras retenções',
    ];

    /**
     * @param  array $dados  cabeçalho: client_id, warehouse_id, invoice_date, due_date,
     *                       delivery_date, delivery_location, invoice_type (FT|FR),
     *                       series_id, is_service, discount_amount, discount_commercial,
     *                       discount_financial, notes, terms, tax_country_region,
     *                       withholding_type, withholding_percentage, withholding_amount,
     *                       payment_method, status (draft|…)
     * @param  Collection $linhas  objectos com id, name, price, quantity, attributes
     *                       (description, unit, discount_percent, tax_rate, exemption_reason)
     * @param  array $iecPorLinha  [id da linha => código pautal]
     * @param  array $isPorLinha   [id da linha => verba]
     * @param  SalesInvoice|null $existente  a editar (rascunho); null cria
     * @return array{factura: SalesInvoice, fila: array}
     *
     * @throws DomainException  o que o ecrã diz à pessoa e não grava
     */
    public function emitir(
        array $dados,
        Collection $linhas,
        array $iecPorLinha = [],
        array $isPorLinha = [],
        ?SalesInvoice $existente = null
    ): array {
        $tenantId = activeTenantId();
        $status = $dados['status'] ?? 'draft';
        $eEdicao = $existente !== null;

        if ($linhas->isEmpty()) {
            throw new DomainException(__('Adicione pelo menos um produto à fatura.'));
        }

        if ($eEdicao) {
            if ($existente->status === 'converted') {
                throw new DomainException(__('Não é possível editar uma fatura já convertida.'));
            }

            // Decreto 71/25: documento finalizado (com hash) não se edita —
            // só se rectifica por nota de crédito.
            if ($existente->invoice_status === 'F' && ! empty($existente->saft_hash)) {
                throw new DomainException(__('Esta fatura já foi finalizada e não pode ser editada. Utilize uma Nota de Crédito para rectificação (Decreto 71/25).'));
            }

            if (in_array($existente->status, ['paid', 'cancelled', 'credited'], true)) {
                throw new DomainException(__('Não é possível editar uma fatura com estado: :estado', ['estado' => $existente->status_label]));
            }
        }

        $factura = DB::transaction(function () use ($dados, $linhas, $iecPorLinha, $isPorLinha, $existente, $eEdicao, $status, $tenantId) {
            if ($eEdicao) {
                $factura = $existente;
                $factura->items()->delete();
            } else {
                $factura = new SalesInvoice();
                $factura->tenant_id = $tenantId;
                $factura->created_by = auth()->id();
                // O tipo define a série (a FR usa a sequência do POS) e só se
                // fixa na criação: mudar o tipo de um documento já numerado
                // quebrava a sequência.
                $factura->invoice_type = strtoupper((string) ($dados['invoice_type'] ?? 'FT')) === 'FR' ? 'FR' : 'FT';
                $factura->series_id = ($dados['series_id'] ?? null) ?: null;
            }

            $factura->client_id = $dados['client_id'];
            $factura->warehouse_id = $dados['warehouse_id'] ?? null;
            $factura->invoice_date = $dados['invoice_date'];
            $factura->due_date = $dados['due_date'] ?? null;
            $factura->delivery_date = ($dados['delivery_date'] ?? null) ?: null;
            $factura->delivery_location = ($dados['delivery_location'] ?? null) ?: null;
            $factura->status = $status;
            $factura->is_service = (bool) ($dados['is_service'] ?? false);
            $factura->discount_amount = (float) ($dados['discount_amount'] ?? 0);
            $factura->discount_commercial = (float) ($dados['discount_commercial'] ?? 0);
            $factura->discount_financial = (float) ($dados['discount_financial'] ?? 0);
            $factura->notes = $dados['notes'] ?? null;
            $factura->terms = $dados['terms'] ?? null;
            $factura->save();

            $totais = $this->criarLinhas($factura, $linhas, $iecPorLinha, $isPorLinha, $dados, $tenantId);

            $this->fecharTotais($factura, $totais, $dados, $status);

            $this->gravarRetencao($factura, $dados, $tenantId);

            $this->assinar($factura, $tenantId);

            // A baixa de stock TEM de ser aqui: no `created` do observer ainda
            // não existia nenhuma linha. O reduceStock() é idempotente.
            if ($status !== 'draft') {
                $factura->load('items');
                app(SalesInvoiceObserver::class)->reduceStock($factura);
            }

            // Fatura-Recibo: a entrada na tesouraria. Best-effort — uma falha
            // aqui não pode anular o documento fiscal já numerado e assinado.
            if ($factura->invoice_type === 'FR' && $status !== 'draft') {
                try {
                    $this->entradaNaTesouraria($factura, $dados, $tenantId);
                } catch (\Throwable $e) {
                    Log::error('EmissorDeFacturas: falha ao criar transação de tesouraria da Fatura-Recibo', [
                        'invoice' => $factura->invoice_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // No FECHO DE TURNO de quem a emitiu, se a empresa o quiser: a FR
            // conta pela forma de pagamento, a FT sai como a prazo. Ver
            // DocumentosNoTurno (26/09/2026).
            if ($status !== 'draft') {
                if ($factura->invoice_type === 'FR') {
                    DocumentosNoTurno::facturaRecibo($factura, (string) (($dados['payment_method'] ?? null) ?: 'cash'), auth()->id());
                } else {
                    DocumentosNoTurno::aPrazo($factura, 'FT', (string) $factura->invoice_number, (float) $factura->total, auth()->id());
                }
            }

            return $factura;
        });

        // A AGT é o SEGUNDO passo: só se enfileira, depois do commit.
        $fila = $eEdicao
            ? ['enfileirado' => false, 'jaEnviado' => false, 'erro' => null]
            : AutoSubmissao::enfileirar($factura);

        return ['factura' => $factura, 'fila' => $fila];
    }

    /**
     * As linhas de um pedido (API, comando) na forma que `emitir()` espera,
     * com a TAXA resolvida aqui — nunca do pedido. O preço pode vir do
     * pedido (é o preço de venda desta factura), a taxa não.
     *
     * @param  array<int, array{product_id?: int|null, description?: string|null, quantity: float|int|string, price: float|int|string, discount_percent?: float|int|string|null, iec?: string|null, is?: string|null}>  $pedidas
     * @return array{0: Collection, 1: array, 2: array, 3: bool}  [linhas, IEC por id, IS por id, há físicos]
     */
    public static function linhasDoPedido(array $pedidas): array
    {
        $tenantId = activeTenantId();
        $iec = [];
        $is = [];
        $temFisicos = false;

        $linhas = collect($pedidas)->values()->map(function (array $p, int $i) use ($tenantId, &$iec, &$is, &$temFisicos) {
            $artigo = ! empty($p['product_id'])
                ? Product::where('tenant_id', $tenantId)->find($p['product_id'])
                : null;

            if ($artigo && ($artigo->type ?? 'produto') !== 'servico') {
                $temFisicos = true;
            }

            $imposto = TaxResolver::forProduct($artigo, $tenantId);

            // O emissor indexa o IEC/IS pelo `id` da linha; uma linha livre
            // recebe um id próprio para não colidir com artigos.
            $id = $artigo?->id ?? ('livre_' . $i);

            if (! empty($p['iec'])) {
                $iec[$id] = $p['iec'];
            }
            if (! empty($p['is'])) {
                $is[$id] = $p['is'];
            }

            return (object) [
                'id' => $id,
                'name' => $artigo?->name ?? ($p['description'] ?? ''),
                'price' => round((float) $p['price'], 2),
                'quantity' => (float) $p['quantity'],
                'attributes' => [
                    'description' => $p['description'] ?? null,
                    'unit' => $artigo?->unit ?? 'UN',
                    'discount_percent' => (float) ($p['discount_percent'] ?? 0),
                    'tax_rate' => (float) $imposto['rate'],
                    'exemption_reason' => $imposto['exemption_code'],
                ],
            ];
        });

        return [$linhas, $iec, $is, $temFisicos];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * As linhas, com os descontos globais distribuídos, o IEC/IS e os campos
     * SAFT. Devolve os acumulados que o cabeçalho precisa.
     *
     * @return array{subtotal: float, iva: float, descontos: float, extras: float}
     */
    private function criarLinhas(SalesInvoice $factura, Collection $linhas, array $iecPorLinha, array $isPorLinha, array $dados, int $tenantId): array
    {
        $subtotal = 0.0;
        $iva = 0.0;
        $descontos = 0.0;
        $extras = 0.0;
        $ordem = 0;

        // Os descontos comerciais GLOBAIS descem às linhas, proporcionais ao
        // peso de cada uma no líquido. O financeiro não entra aqui.
        $descontosGlobais = (float) ($dados['discount_commercial'] ?? 0) + (float) ($dados['discount_amount'] ?? 0);
        $liquidoDasLinhas = 0.0;

        foreach ($linhas as $l) {
            $a = InvoiceCalculationHelper::atributos($l);
            $bruto = (float) $l->price * (float) $l->quantity;
            $liquidoDasLinhas += $bruto - ($bruto * ((float) ($a['discount_percent'] ?? 0) / 100));
        }

        // Nunca descontar mais do que existe.
        $descontosGlobais = max(0.0, min($descontosGlobais, $liquidoDasLinhas));

        $regiaoEscolhida = $dados['tax_country_region'] ?? null;
        $regiao = in_array($regiaoEscolhida, ['AO', 'AO-CAB'], true)
            ? $regiaoEscolhida
            : TaxResolver::regionForClient($factura->client ?? Client::find($dados['client_id']));

        foreach ($linhas as $item) {
            $a = InvoiceCalculationHelper::atributos($item);

            $bruto = (float) $item->price * (float) $item->quantity;
            $pct = (float) ($a['discount_percent'] ?? 0);
            $desconto = $bruto * ($pct / 100);

            $liquido = $bruto - $desconto;

            if ($descontosGlobais > 0 && $liquidoDasLinhas > 0) {
                $desconto += $descontosGlobais * ($liquido / $liquidoDasLinhas);
                // Gravar a percentagem EFECTIVA: o saving() do modelo reescreve
                // discount_amount a partir de discount_percent.
                $pct = $bruto > 0 ? ($desconto / $bruto) * 100 : 0;
            }

            $aposDesconto = $bruto - $desconto;

            // IEC entra na base do IVA; o IS não.
            $extrasLinha = ImpostosDaLinha::calcular(
                (float) $item->price * (float) $item->quantity,
                $iecPorLinha[$item->id] ?? null,
                $isPorLinha[$item->id] ?? null
            );

            $iecLinha = 0.0;
            foreach ($extrasLinha as $ex) {
                if ($ex['tax_type'] === 'IEC') {
                    $iecLinha += (float) $ex['tax_amount'];
                }
            }

            $taxa = (float) ($a['tax_rate'] ?? 0);
            $baseIva = $aposDesconto + $iecLinha;
            $ivaLinha = $baseIva * ($taxa / 100);
            $totalExtras = array_sum(array_column($extrasLinha, 'tax_amount'));

            $subtotal += $bruto;
            $iva += $ivaLinha;
            $descontos += $desconto;

            $resolvido = TaxResolver::forProductId($item->id, $tenantId);

            $linha = SalesInvoiceItem::create([
                'sales_invoice_id' => $factura->id,
                'product_id' => is_numeric($item->id) ? (int) $item->id : null,
                'product_name' => $item->name,
                'description' => $a['description'] ?? null,
                'quantity' => $item->quantity,
                'unit' => $a['unit'] ?? 'UN',
                'unit_price' => $item->price,
                'discount_percent' => $pct,
                'discount_amount' => $desconto,
                'subtotal' => $bruto,
                'tax_rate' => $taxa,
                'tax_amount' => $ivaLinha,
                'total' => $aposDesconto + $ivaLinha + $totalExtras,
                'order' => ++$ordem,
                'tax_country_region' => $regiao,
                'tax_code' => $taxa > 0 ? ($resolvido['tax_code'] ?: 'NOR') : 'ISE',
                'tax_exemption_code' => $taxa > 0 ? null
                    : (Product::normalizeExemptionCode($a['exemption_reason'] ?? null) ?: $resolvido['exemption_code']),
                'tax_exemption_reason' => $taxa > 0 ? null
                    : (Product::exemptionReasonText($a['exemption_reason'] ?? null) ?: $resolvido['exemption_reason']),
            ]);

            // Impostos extra da linha. Recriam-se sempre: numa edição os antigos
            // deixam de valer.
            LineTax::where('line_type', get_class($linha))->where('line_id', $linha->id)->delete();

            foreach ($extrasLinha as $extra) {
                LineTax::create(array_merge($extra, [
                    'tenant_id' => $tenantId,
                    'line_type' => get_class($linha),
                    'line_id' => $linha->id,
                    'tax_country_region' => $linha->tax_country_region ?: 'AO',
                    // No Imposto de Selo o código é a VERBA; 'NOR' é de IVA e a
                    // AGT recusava a combinação.
                    'tax_code' => $extra['tax_type'] === 'IS' ? (string) $extra['verba_no'] : 'NOR',
                ]));
                $extras += (float) $extra['tax_amount'];
            }

            // Regravar a linha: o IEC só existe depois das linhas de imposto e o
            // modelo precisa dele para apurar o IVA sobre a base acrescida.
            if (! empty($extrasLinha)) {
                $linha->save();
                $linha->refresh();
                $iva += ((float) $linha->tax_amount - $ivaLinha);
            }
        }

        return ['subtotal' => $subtotal, 'iva' => $iva, 'descontos' => $descontos, 'extras' => $extras];
    }

    private function fecharTotais(SalesInvoice $factura, array $t, array $dados, string $status): void
    {
        // $t['descontos'] JÁ inclui a quota dos descontos globais distribuída.
        $aposComercial = $t['subtotal'] - $t['descontos'];
        $incidenciaIva = $aposComercial;

        // Retenção: a explícita tem prioridade; o IRT automático de 6,5% sobre
        // serviços mantém-se quando nada é indicado.
        $retencaoExplicita = (float) ($dados['withholding_amount'] ?? 0);
        $irt = $retencaoExplicita > 0
            ? $retencaoExplicita
            : ($factura->is_service ? $incidenciaIva * 0.065 : 0);

        $impostoTotal = $t['iva'] + $t['extras'];
        $totalFinal = $incidenciaIva + $impostoTotal - (float) ($factura->discount_financial ?? 0) - $irt;

        $factura->subtotal = $t['subtotal'];
        $factura->tax_amount = $t['iva'];
        $factura->irt_amount = $irt;
        $factura->total = $totalFinal;

        // SAFT-AO: net_total é a soma dos líquidos das linhas; tax_payable
        // inclui TODOS os impostos; gross_total = net + tax.
        $factura->net_total = $aposComercial;
        $factura->tax_payable = $impostoTotal;
        $factura->gross_total = $factura->net_total + $impostoTotal;
        $factura->system_entry_date = $factura->system_entry_date ?? now();
        $factura->invoice_status = $status === 'draft' ? 'N' : 'F';
        $factura->invoice_status_date = now();
        $factura->source_id = auth()->id() ?? 'SYSTEM';
        $factura->source_billing = 'P';

        // Fatura-Recibo: paga no acto de emissão.
        if ($factura->invoice_type === 'FR' && $status !== 'draft') {
            $factura->status = 'paid';
            $factura->paid_amount = $factura->total;
            $factura->payment_method = ($dados['payment_method'] ?? null) ?: 'cash';
        }

        $factura->save();
    }

    /** Retenção na fonte — recria-se sempre, para uma edição não deixar a antiga. */
    private function gravarRetencao(SalesInvoice $factura, array $dados, int $tenantId): void
    {
        DB::table('invoicing_withholding_taxes')
            ->where('document_type', get_class($factura))
            ->where('document_id', $factura->id)
            ->delete();

        // Só tipos da lista da AGT: um valor fora dela recusa o payload inteiro.
        $tipo = $dados['withholding_type'] ?? null;

        if ($tipo && array_key_exists($tipo, self::DESCRICOES_RETENCAO) && (float) ($dados['withholding_amount'] ?? 0) > 0) {
            DB::table('invoicing_withholding_taxes')->insert([
                'tenant_id' => $tenantId,
                'document_type' => get_class($factura),
                'document_id' => $factura->id,
                'withholding_tax_type' => $tipo,
                'withholding_tax_description' => self::DESCRICOES_RETENCAO[$tipo],
                'withholding_tax_percentage' => (float) ($dados['withholding_percentage'] ?? 0),
                'withholding_tax_amount' => (float) $dados['withholding_amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** O hash SAFT-AO, encadeado na factura anterior com hash. */
    private function assinar(SalesInvoice $factura, int $tenantId): void
    {
        // O anterior lido SOB BLOQUEIO: ver ElosDaCadeia.
        $anterior = ElosDaCadeia::trancar(SalesInvoice::where('tenant_id', $tenantId)
            ->where('id', '<', $factura->id)
            ->whereNotNull('saft_hash')
            ->orderByDesc('id'))
            ->first();

        $hash = SAFTHelper::generateHash(
            $factura->invoice_date->format('Y-m-d'),
            $factura->system_entry_date->format('Y-m-d H:i:s'),
            $factura->invoice_number,
            $factura->gross_total,
            $anterior->saft_hash ?? null
        );

        if ($hash) {
            $factura->saft_hash = $hash;
            $factura->hash = $hash;
            $factura->hash_previous = $anterior->saft_hash ?? '';
            $factura->hash_control = '1';
            $factura->save();
        }
    }

    /** A entrada na tesouraria da Fatura-Recibo — a mesma lógica do POS. */
    private function entradaNaTesouraria(SalesInvoice $factura, array $dados, int $tenantId): void
    {
        $code = strtolower((string) (($dados['payment_method'] ?? null) ?: 'cash'));

        $metodo = PaymentMethod::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(code) = ?', [$code])
            ->first();

        if (! $metodo) {
            throw new \RuntimeException('Método de pagamento sem correspondência na Tesouraria.');
        }

        $porTipo = [
            'cash' => 'cash', 'card' => 'card', 'bank_transfer' => 'bank_transfer',
            'digital_wallet' => 'digital_payment', 'check' => 'bank_transfer',
        ];

        $categoria = $porTipo[$metodo->type] ?? 'cash';

        $servico = app(TreasuryMovementService::class);
        $destino = $servico->destination($metodo, $tenantId, userId: auth()->id());

        $servico->post([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'account_id' => $destino['account_id'],
            'cash_register_id' => $destino['cash_register_id'],
            'payment_method_id' => $metodo->id,
            'invoice_id' => $factura->id,
            // A origem do dinheiro, por extenso — `invoice_id` também é usado
            // por recibos e notas. Ver a mesma nota no `PosSaleService`.
            'related_type' => SalesInvoice::class,
            'related_id' => $factura->id,
            'transaction_number' => 'TRX-' . strtoupper(uniqid()),
            'type' => 'income',
            'category' => $categoria,
            'amount' => $factura->total,
            'currency' => 'AOA',
            // O DIA DO DOCUMENTO. Uma FR passada com data de ontem punha o
            // dinheiro na tesouraria hoje, e os dois mapas não batiam.
            'transaction_date' => $factura->invoice_date ?: now(),
            'reference' => $factura->invoice_number,
            'description' => 'Fatura-Recibo: ' . $factura->invoice_number
                . ($factura->client ? ' - Cliente: ' . $factura->client->name : ''),
            'notes' => $dados['notes'] ?? null,
            'status' => 'completed',
            'is_reconciled' => false,
        ]);
    }
}
