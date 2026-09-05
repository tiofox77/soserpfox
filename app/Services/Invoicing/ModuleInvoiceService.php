<?php

namespace App\Services\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Emissor ÚNICO de facturas para os módulos de negócio (oficina, hotel, salão,
 * eventos, ...).
 *
 * Porque existe
 * -------------
 * Cada módulo tinha a sua própria cópia da lógica fiscal e todas divergiam da
 * facturação. Auditoria de 2026-08: a oficina fixava IVA a 14% (ilegal num
 * tenant isento), aplicava retenção de IRT também às PEÇAS, ignorava os
 * descontos de linha no total do documento (cliente pagava a mais) e nunca
 * preenchia net_total/tax_payable/gross_total — que têm default 0.00, pelo que
 * o `??` dos serviços da AGT nunca caía no fallback e o documento seria
 * assinado e submetido com totais a ZERO.
 *
 * Regras que este serviço garante, uma vez só (Decreto Presidencial 71/25):
 *  - imposto por linha resolvido pelo TaxResolver (regime da empresa, isenções,
 *    taxas reduzidas), NUNCA por uma constante;
 *  - linha isenta leva sempre código e motivo de isenção;
 *  - retenção de IRT só sobre a MÃO-DE-OBRA/serviços, nunca sobre bens;
 *  - descontos de linha entram no total do documento;
 *  - netTotal + taxPayable = grossTotal;
 *  - hash SAFT-AO encadeado e submissão AGT que nunca bloqueia a gravação.
 *
 * Os módulos passam a descrever O QUE vendem; a fiscalidade é decidida aqui.
 */
class ModuleInvoiceService
{
    /** Retenção na fonte sobre prestação de serviços (IRT). */
    public const TAXA_IRT_SERVICOS = 0.065;

    /**
     * Emite uma factura a partir das linhas de um módulo.
     *
     * @param array $dados {
     *   tenant_id:  int,
     *   client_id:  int,
     *   lines:      array<int, array{
     *                   product_id?: int|null, name: string, quantity: float,
     *                   unit_price: float, discount_percent?: float,
     *                   is_service?: bool, unit?: string
     *               }>,
     *   warehouse_id?:        int|null,
     *   invoice_type?:        string,   // FT (por omissão) ou FR
     *   status?:              string,   // draft|sent|paid — por omissão 'sent'
     *   discount_commercial?: float,
     *   discount_financial?:  float,
     *   retencao_irt?:        bool,     // adquirente retém 6,5% sobre serviços
     *   notes?:               string|null,
     *   origem_modulo?:       string|null,  // 'hotel' | 'oficina' | 'salao'
     *   origem?:              string|null,  // p.ex. "OS-00012" / "RES-000123"
     * }
     */
    public function emitir(array $dados): SalesInvoice
    {
        $tenantId = (int) $dados['tenant_id'];
        $linhas   = array_values($dados['lines'] ?? []);

        if (empty($linhas)) {
            throw new \InvalidArgumentException('Não é possível faturar um documento sem linhas.');
        }

        $client = Client::where('tenant_id', $tenantId)->find($dados['client_id'] ?? null);
        if (!$client) {
            throw new \InvalidArgumentException('Cliente não encontrado nesta empresa.');
        }

        $warehouse = !empty($dados['warehouse_id'])
            ? Warehouse::where('tenant_id', $tenantId)->find($dados['warehouse_id'])
            : Warehouse::getDefault($tenantId);

        if (!$warehouse) {
            throw new \InvalidArgumentException('Nenhum armazém configurado para esta empresa.');
        }

        $status = $dados['status'] ?? 'sent';

        $invoice = DB::transaction(function () use ($dados, $tenantId, $linhas, $client, $warehouse, $status) {
            $invoice = SalesInvoice::create([
                'tenant_id'           => $tenantId,
                'client_id'           => $client->id,
                'warehouse_id'        => $warehouse->id,
                'invoice_type'        => $dados['invoice_type'] ?? 'FT',
                'invoice_date'        => now(),
                'due_date'            => now()->addDays(30),
                'status'              => $status,
                'discount_amount'     => 0,
                'discount_commercial' => (float) ($dados['discount_commercial'] ?? 0),
                'discount_financial'  => (float) ($dados['discount_financial'] ?? 0),
                'notes'               => $dados['notes'] ?? null,
                'created_by'          => auth()->id(),

                // Origem PERSISTIDA, não só registada no log: é o que permite
                // encontrar mais tarde todas as facturas de uma reserva ou de
                // uma OS. Sem isto, o único vínculo era um `invoice_id` 1:1 que
                // ficava sobrescrito e perdia os documentos anteriores.
                'source_module'       => $dados['origem_modulo'] ?? null,
                'source_reference'    => $dados['origem'] ?? null,
            ]);

            $regiao = TaxResolver::regionForClient($client);

            $subtotalBruto    = 0.0;  // soma das linhas ANTES de desconto
            $descontosLinha   = 0.0;  // soma dos descontos de linha
            $impostoTotal     = 0.0;
            $baseServicos     = 0.0;  // só serviços — base da retenção de IRT
            $ordem            = 0;

            foreach ($linhas as $linha) {
                $quantidade = (float) ($linha['quantity'] ?? 0);
                $preco      = (float) ($linha['unit_price'] ?? 0);
                $descPct    = (float) ($linha['discount_percent'] ?? 0);

                $bruto          = $preco * $quantidade;
                $descontoValor  = $bruto * ($descPct / 100);
                $baseTributavel = $bruto - $descontoValor;

                // VÍNCULO AO CATÁLOGO — garantido aqui, para todos os módulos.
                //
                // A linha do documento tem de apontar para um artigo, tal como
                // aponta para um cliente: é do artigo que sai o regime de
                // imposto e o código de isenção que a AGT valida. Módulos que
                // vendiam por descrição livre (mão-de-obra da oficina, extras do
                // hotel) faziam o mesmo serviço sair com impostos diferentes
                // conforme o ecrã por onde foi vendido.
                $productId = $linha['product_id'] ?? null;
                if (!$productId) {
                    $productId = $this->produtoDoCatalogo(
                        $tenantId,
                        $linha['name'],
                        $preco,
                        !empty($linha['is_service']) ? 'service' : 'product'
                    )->id;
                }

                // Imposto pela fonte única: honra isenção do produto, taxa
                // reduzida e regime da empresa. Antes era `$taxRate = 14`.
                $tx       = TaxResolver::forProductId($productId, $tenantId);
                $taxa     = (float) $tx['rate'];
                $imposto  = $baseTributavel * ($taxa / 100);

                $subtotalBruto  += $bruto;
                $descontosLinha += $descontoValor;
                $impostoTotal   += $imposto;

                // Base da retenção: SÓ serviços. A oficina retinha 6,5% também
                // sobre as peças — a retenção é sobre prestação de serviços,
                // não sobre venda de bens.
                if (!empty($linha['is_service'])) {
                    $baseServicos += $baseTributavel;
                }

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id'       => $productId,
                    'product_name'     => $linha['name'],
                    'quantity'         => $quantidade,
                    'unit'             => $linha['unit'] ?? 'UN',
                    'unit_price'       => $preco,
                    'discount_percent' => $descPct,
                    'discount_amount'  => $descontoValor,
                    'subtotal'         => $bruto,
                    'tax_rate'         => $taxa,
                    'tax_amount'       => $imposto,
                    'total'            => $baseTributavel + $imposto,
                    'order'            => ++$ordem,

                    'tax_country_region'   => $regiao,
                    'tax_code'             => $taxa > 0 ? ($tx['tax_code'] ?: 'NOR') : 'ISE',
                    // Linha isenta SEM motivo é recusada pela AGT.
                    'tax_exemption_code'   => $taxa > 0 ? null : $tx['exemption_code'],
                    'tax_exemption_reason' => $taxa > 0 ? null : $tx['exemption_reason'],
                ]);
            }

            // Descontos de LINHA entram no total do documento — de outro modo o
            // desconto reduzia só o imposto da linha e o cliente pagava a mais.
            $descontoComercial = $descontosLinha
                + (float) $invoice->discount_commercial
                + (float) $invoice->discount_amount;

            $incidencia = $subtotalBruto - $descontoComercial - (float) $invoice->discount_financial;

            // Retenção na fonte (IRT): EXPLÍCITA, nunca automática.
            //
            // Quem retém é o adquirente, e só quando está obrigado a isso — uma
            // empresa que contrata serviços. Um hóspede ou um cliente
            // particular não retém nada. Aplicá-la a todo o documento com
            // serviços fazia, por exemplo, um sinal de 20.000 Kz pago por um
            // hóspede sair como 18.859,65 a pagar: o documento não batia com o
            // dinheiro entregue.
            $irt = 0.0;

            if (!empty($dados['retencao_irt']) && $baseServicos > 0) {
                // Proporcional: se houver descontos de documento, a base de
                // retenção encolhe na mesma medida da base tributável.
                $baseIrt = $baseServicos;
                $baseAntesDocumento = $subtotalBruto - $descontosLinha;
                if ($baseAntesDocumento > 0 && $incidencia < $baseAntesDocumento) {
                    $baseIrt = $baseServicos * ($incidencia / $baseAntesDocumento);
                }
                $irt = round($baseIrt * self::TAXA_IRT_SERVICOS, 2);
            }

            $invoice->subtotal   = $subtotalBruto;
            $invoice->tax_amount = $impostoTotal;
            $invoice->irt_amount = $irt;
            $invoice->total      = $incidencia + $impostoTotal - $irt;

            // Campos SAFT-AO. Têm default 0.00 (não NULL): se não forem
            // preenchidos, o `??` dos serviços AGT/QR nunca cai no fallback e o
            // documento é assinado com totais a zero.
            $invoice->net_total           = $incidencia;
            $invoice->tax_payable         = $impostoTotal;
            $invoice->gross_total         = $incidencia + $impostoTotal;
            $invoice->system_entry_date   = now();
            $invoice->invoice_status      = ($status === 'draft') ? 'N' : 'F';
            $invoice->invoice_status_date = now();
            $invoice->source_id           = auth()->id() ?? 'SYSTEM';
            $invoice->source_billing      = 'P';
            $invoice->is_service          = $baseServicos > 0;
            $invoice->save();

            // Hash SAFT-AO encadeado (depois dos totais fechados: o hash cobre o
            // total do documento).
            $anterior = SalesInvoice::where('tenant_id', $tenantId)
                ->where('id', '<', $invoice->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();

            $hash = \App\Helpers\SAFTHelper::generateHash(
                $invoice->invoice_date->format('Y-m-d'),
                $invoice->created_at->format('Y-m-d H:i:s'),
                $invoice->invoice_number,
                $invoice->total,
                $anterior->saft_hash ?? null
            );

            if ($hash) {
                // GRAVAR O ELO, e não só o hash.
                //
                // Guardar apenas o `saft_hash` deixava a factura com o hash
                // certo mas sem dizer a que documento se ligava. Parecia
                // inofensivo — a cadeia estava lá, calculada. Não estava:
                // quando estas facturas iam à AGT, o SignatureService
                // RECALCULAVA o hash a partir do `hash_previous` guardado, que
                // era vazio, e sobrepunha o correcto. A cadeia partia-se na
                // transmissão, que é o pior momento possível para partir.
                //
                // Isto afectava tudo o que passa por aqui: restaurante, hotel,
                // salão, oficina e as vendas repostas pelo PWA offline.
                $invoice->hash          = $hash;
                $invoice->saft_hash     = $hash;
                $invoice->hash_previous = $anterior->saft_hash ?? '';
                $invoice->hash_control  = '1';
                $invoice->save();
            }

            // ── Baixa de stock ──
            //
            // Tem de ser explícita, DEPOIS de as linhas existirem. O
            // SalesInvoiceObserver desconta no evento `created` da factura, mas
            // nessa altura `$invoice->items` ainda está vazio — a colecção é
            // criada a seguir. Resultado: as facturas emitidas por aqui
            // (restaurante, hotel, salão) vendiam sem nunca tocar no stock.
            //
            // A guarda de idempotência do observer trata dos casos em que o
            // stock já saiu por outra via — a oficina desconta ao concluir a OS.
            if (in_array($status, ['sent', 'paid'], true)) {
                $invoice->load('items');
                app(\App\Observers\SalesInvoiceObserver::class)->reduceStock($invoice);
            }

            Log::info('Factura de módulo emitida', [
                'origem'         => $dados['origem'] ?? null,
                'invoice_number' => $invoice->invoice_number,
                'tenant_id'      => $tenantId,
                'total'          => $invoice->total,
            ]);

            return $invoice->fresh('items');
        });

        // ── Comunicação à AGT, só depois do commit MAIS EXTERIOR ──
        //
        // Sair da transacção deste serviço não chega: os chamadores abrem a
        // sua própria (Checkout::processCheckout e WorkOrder::convertToInvoice
        // fazem beginTransaction antes de emitir), pelo que a nossa é aninhada
        // e o "commit" dela é só um savepoint. Comunicar aqui significaria, num
        // rollback do chamador, ter enviado à AGT um documento que deixou de
        // existir na base — sem sequer forma de o anular por nota de crédito,
        // porque não há documento nenhum.
        //
        // DB::afterCommit resolve as duas situações: com transacção pendente
        // adia até ao commit de nível 0 e descarta o callback num rollback; sem
        // transacção activa executa de imediato, como antes.
        //
        // Bónus: a chamada de rede (até 60 s de timeout) deixa de decorrer com
        // locks de escrita abertos sobre reservas, quartos e facturação.
        DB::afterCommit(fn () => $this->comunicarAGT($invoice));

        return $invoice;
    }

    /**
     * Submete o documento à AGT, se a empresa tiver a submissão automática
     * ligada e as chaves configuradas.
     *
     * Nunca lança e nunca impede a gravação: a factura já está no
     * commit. Uma AGT em baixo não pode travar o atendimento — é a mesma regra
     * do resto do sistema (ver InvoiceCreate e CreditNoteCreate).
     */
    protected function comunicarAGT(SalesInvoice $invoice): void
    {
        try {
            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant($invoice->tenant_id);

            if (empty($settings->agt_auto_submit)) {
                return;
            }

            // ENFILEIRA, não envia: o atendimento não espera pela AGT. O
            // DespacharAgtPendentes envia à boleia do tráfego.
            \App\Services\AGT\AutoSubmissao::enfileirar($invoice);
        } catch (\Throwable $e) {
            Log::error('Falha ao comunicar factura de módulo à AGT', [
                'invoice_number' => $invoice->invoice_number,
                'tenant_id'      => $invoice->tenant_id,
                'erro'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Produto do catálogo para um item de módulo que ainda não tem nenhum.
     *
     * A AGT exige que a linha aponte para um artigo do catálogo — é o mesmo
     * vínculo que já existe para os clientes. Sem isto, cada módulo inventava a
     * sua descrição e o mesmo serviço saía com impostos diferentes conforme o
     * ecrã por onde foi vendido.
     */
    public function produtoDoCatalogo(int $tenantId, string $nome, float $preco, string $tipo = 'service'): Product
    {
        // A coluna é ENUM('produto','servico') — em português e sem acento.
        // Gravar 'service' truncava a coluna (SQL 1265) e a criação do artigo
        // rebentava, levando atrás a factura inteira.
        $tipoBd = in_array($tipo, ['service', 'servico'], true) ? 'servico' : 'produto';

        // O artigo tem de nascer com REGIME FISCAL, como qualquer outro criado
        // na faturação. Sem `tax_type`/`tax_rate_id` ele só funcionava por
        // acidente — o TaxResolver recorria ao imposto por omissão da empresa —
        // mas ficava incompleto no catálogo: aparecia sem IVA na listagem e
        // obrigava a preencher tudo à mão na primeira edição.
        $impostoOmissao = TaxResolver::defaultTax($tenantId);
        $regime = TaxResolver::forProduct(null, $tenantId);
        $isento = ($regime['rate'] ?? 0) <= 0;

        return Product::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name'      => $nome,
            ],
            [
                'sku'          => ($tipoBd === 'servico' ? 'SRV-' : 'ART-') . \Illuminate\Support\Str::slug($nome),
                'type'         => $tipoBd,
                'price'        => $preco,
                'unit'         => 'UN',
                'is_active'    => true,
                // Serviços não gerem stock; peças gerem, mas só passam a ter
                // linhas quando alguém der entrada.
                'manage_stock' => $tipoBd === 'produto',
                'tax_type'     => $isento ? 'isento' : 'iva',
                'tax_rate_id'  => $isento ? null : $impostoOmissao?->id,
                // Um artigo isento SEM motivo faz a AGT recusar a linha.
                'exemption_reason' => $isento ? ($regime['exemption_code'] ?? null) : null,
                'description'  => 'Criado automaticamente a partir de um módulo de negócio',
            ]
        );
    }
}
