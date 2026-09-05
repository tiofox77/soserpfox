<?php

namespace App\Services\Pwa;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesProformaItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Treasury\Account;
use App\Models\User;
use Carbon\Carbon;

/**
 * O MOLDE de um documento: o próprio modelo de impressão do servidor,
 * renderizado com MARCAS no lugar dos valores, para o aparelho o preencher
 * sem rede.
 *
 * PORQUE EXISTE. O papel do documento sem rede era um segundo desenho,
 * escrito em JavaScript, e não parecia o da pré-visualização do servidor.
 * Copiar o desenho para o aparelho resolvia hoje e divergia amanhã: duas
 * cópias do mesmo papel acabam sempre diferentes. Em vez disso o servidor
 * renderiza o SEU modelo — `pdf.invoicing.sales-invoice` e
 * `pdf.invoicing.proforma`, os mesmos do PDF — com um documento sintético
 * cujos valores são números e textos que não aparecem em mais lado nenhum,
 * e troca-os por marcas `%%ASSIM%%`. O aparelho guarda esse HTML na
 * sincronização e, sem rede, substitui as marcas pelos valores do documento.
 * O desenho vive num sítio só.
 *
 * O que fica REAL no molde, porque é da empresa e não do documento: o
 * logótipo, o nome, o NIF, a morada, o regime, as contas bancárias, o número
 * do certificado. O que fica em marca: tudo o que muda de documento para
 * documento.
 *
 * Os números mágicos são escolhidos para que nenhum apareça formatado em
 * mais do que um sítio com significados diferentes — e o ensaio confirma
 * que nenhum sobra por substituir.
 */
final class MoldeDoDocumento
{
    public const TIPOS = ['FT', 'FR', 'proforma'];

    /** Os valores do documento sintético, cada um único depois de formatado. */
    private const DOC = [
        'SUBTOTAL'        => 9000001.37,
        'DESC_COMERCIAL'  => 600001.23,
        'DESC_FINANCEIRO' => 700001.29,
        'IVA'             => 5000001.41,
        'TOTAL'           => 6000001.43,
        'RETENCAO'        => 800001.31,
        'RECEBIDO'        => 6100001.47,
    ];

    /** A primeira linha é a que o aparelho copia; as outras só existem para o resumo. */
    private const LINHAS = [
        ['qtd' => 30007, 'preco' => 1000003.07, 'imposto' => 2000005.11, 'total' => 3000007.13, 'subtotal' => 4000009.17, 'desconto' => 500001.19, 'taxa' => 73],
        ['qtd' => 40009, 'preco' => 1000011.09, 'imposto' => 2000015.17, 'total' => 3000017.19, 'subtotal' => 4000019.21, 'desconto' => 500011.23, 'taxa' => 73],
        ['qtd' => 50011, 'preco' => 1000021.11, 'imposto' => 0,          'total' => 3000027.23, 'subtotal' => 4500031.37, 'desconto' => 500021.33, 'taxa' => 0],
    ];

    private const DESCONTO_PCT = 71;
    private const ID = 990000001;

    public function render(string $tipo, Tenant $tenant): string
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException("Tipo de documento sem molde: {$tipo}");
        }

        $contas = Account::with('bank')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();

        $semQr = ['image' => null, 'atcud' => null, 'data' => ''];

        $html = $tipo === 'proforma'
            ? view('pdf.invoicing.proforma', ['proforma' => $this->proformaSintetica($tenant), 'tenant' => $tenant, 'bankAccounts' => $contas, 'qrCode' => $semQr])->render()
            : view('pdf.invoicing.sales-invoice', ['invoice' => $this->facturaSintetica($tenant, $tipo), 'tenant' => $tenant, 'bankAccounts' => $contas, 'qrCode' => $semQr])->render();

        // O auto-print do modelo (window.print ao abrir) não pode viajar: o
        // aparelho é que decide quando imprime.
        $html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);

        return strtr($html, $this->marcas($tipo));
    }

    /** As marcas que ficaram por substituir — o ensaio exige lista vazia. */
    public function sobras(string $html): array
    {
        $sobras = [];

        foreach (array_keys($this->marcas('FR')) as $magico) {
            if (str_contains($html, $magico)) {
                $sobras[] = $magico;
            }
        }

        return $sobras;
    }

    // ── O documento sintético ─────────────────────────────────────────

    private function facturaSintetica(Tenant $tenant, string $tipo): SalesInvoice
    {
        $doc = new SalesInvoice();
        $doc->forceFill(array_merge($this->camposComuns($tenant), [
            'invoice_type'   => $tipo,
            'invoice_number' => '%%NUMERO%%',
            'invoice_date'   => Carbon::create(2001, 2, 3),
            'due_date'       => Carbon::create(2006, 7, 8),
            'status'         => 'pending',
            'paid_amount'    => self::DOC['RECEBIDO'],
            'payment_method' => 'pagamento_token',
            'saft_hash'      => str_repeat('K', 32) . str_repeat('J', 24) . 'ZQ9X',
        ]));

        $this->relacoes($doc, array_map(fn ($l) => $this->item(new SalesInvoiceItem(), $l), self::LINHAS));

        return $doc;
    }

    private function proformaSintetica(Tenant $tenant): SalesProforma
    {
        $doc = new SalesProforma();
        $doc->forceFill(array_merge($this->camposComuns($tenant), [
            'proforma_number' => '%%NUMERO%%',
            'proforma_date'   => Carbon::create(2001, 2, 3),
            'valid_until'     => Carbon::create(2006, 7, 8),
            'status'          => 'draft',
        ]));

        $this->relacoes($doc, array_map(fn ($l) => $this->item(new SalesProformaItem(), $l), self::LINHAS));

        return $doc;
    }

    private function camposComuns(Tenant $tenant): array
    {
        return [
            'id'                 => self::ID,
            'tenant_id'          => $tenant->id,
            'created_at'         => Carbon::create(2001, 2, 3, 4, 5, 0),
            'reference'          => '%%REFERENCIA%%',
            'subtotal'           => self::DOC['SUBTOTAL'],
            'discount_commercial'=> self::DOC['DESC_COMERCIAL'],
            'discount_financial' => self::DOC['DESC_FINANCEIRO'],
            'tax_amount'         => self::DOC['IVA'],
            'total'              => self::DOC['TOTAL'],
            'irt_amount'         => self::DOC['RETENCAO'],
            'currency'           => 'AOA',
            'notes'              => '%%NOTAS%%',
            'delivery_date'      => Carbon::create(2009, 10, 11),
            'delivery_location'  => '%%ENTREGA_LOCAL%%',
        ];
    }

    private function relacoes($doc, array $itens): void
    {
        $doc->setRelation('client', (new Client())->forceFill(['name' => '%%CLIENTE_NOME%%', 'nif' => '%%CLIENTE_NIF%%']));
        $doc->setRelation('creator', (new User())->forceFill(['name' => '%%OPERADOR%%']));
        $doc->setRelation('creditNotes', collect());
        $doc->setRelation('series', null);
        $doc->setRelation('items', collect($itens));
    }

    private function item($item, array $l)
    {
        $primeira = $l === self::LINHAS[0];

        $item->forceFill([
            'product_name'         => $primeira ? '%%ITEM_NOME%%' : 'LINHA-AUXILIAR',
            'description'          => $primeira ? '%%ITEM_DESCRICAO%%' : null,
            'quantity'             => $l['qtd'],
            'unit_price'           => $l['preco'],
            'discount_percent'     => self::DESCONTO_PCT,
            'discount_amount'      => $l['desconto'],
            'subtotal'             => $l['subtotal'],
            'tax_rate'             => $l['taxa'],
            'tax_amount'           => $l['imposto'],
            'total'                => $l['total'],
            'tax_exemption_code'   => $l['taxa'] > 0 ? null : 'MXQ',
            'tax_exemption_reason' => $l['taxa'] > 0 ? null : '%%ISENCAO_MOTIVO%%',
        ]);
        $item->setRelation('product', (new Product())->forceFill(['code' => $primeira ? '%%ITEM_CODIGO%%' : 'AUX']));

        return $item;
    }

    // ── As marcas ────────────────────────────────────────────────────

    /** formatado como o modelo formata → marca. Ordenado do mais longo para o mais curto. */
    private function marcas(string $tipo): array
    {
        $f2 = fn ($v) => number_format($v, 2, ',', '.');
        $l1 = self::LINHAS[0];

        $baseTaxada  = 0;
        $impostoTaxa = 0;
        $baseIsenta  = 0;
        foreach (self::LINHAS as $l) {
            if ($l['taxa'] > 0) {
                $baseTaxada  += $l['subtotal'] - $l['desconto'];
                $impostoTaxa += $l['imposto'];
            } else {
                $baseIsenta += $l['subtotal'] - $l['desconto'];
            }
        }

        $aPagar = self::DOC['TOTAL'] - self::DOC['RETENCAO'];

        $mapa = [
            numberToWords($aPagar, 'AOA')                       => '%%EXTENSO%%',
            $f2($l1['qtd'] * $l1['preco'])                      => '%%ITEM_BRUTO%%',
            $f2(self::DOC['SUBTOTAL'])                          => '%%SUBTOTAL%%',
            $f2(self::DOC['DESC_COMERCIAL'])                    => '%%DESC_COMERCIAL%%',
            $f2(self::DOC['DESC_FINANCEIRO'])                   => '%%DESC_FINANCEIRO%%',
            $f2(self::DOC['IVA'])                               => '%%IVA%%',
            $f2(self::DOC['TOTAL'])                             => '%%TOTAL%%',
            $f2(self::DOC['RETENCAO'])                          => '%%RETENCAO%%',
            $f2(self::DOC['RECEBIDO'])                          => '%%RECEBIDO%%',
            $f2($aPagar)                                        => '%%A_PAGAR%%',
            $f2($baseTaxada)                                    => '%%IMPOSTO_BASE%%',
            $f2($impostoTaxa)                                   => '%%IMPOSTO_VALOR%%',
            $f2($baseIsenta)                                    => '%%ISENTO_BASE%%',
            $f2($l1['preco'])                                   => '%%ITEM_PRECO%%',
            $f2($l1['imposto'])                                 => '%%ITEM_IMPOSTO%%',
            $f2($l1['total'])                                   => '%%ITEM_TOTAL%%',
            number_format($l1['qtd'], 0, ',', '.')              => '%%ITEM_QTD%%',
            self::DESCONTO_PCT . '%'                            => '%%ITEM_DESCONTO%%',
            $l1['taxa'] . '%'                                   => '%%TAXA%%',
            '03/02/2001'                                        => '%%DATA%%',
            '08/07/2006'                                        => '%%VENCIMENTO%%',
            '11/10/2009'                                        => '%%ENTREGA_DATA%%',
            '04:05'                                             => '%%HORA%%',
            'PAGAMENTO_TOKEN'                                   => '%%PAGAMENTO%%',
            'MXQ'                                               => '%%ISENCAO_CODIGO%%',
            'ZQ9X'                                              => '%%HASH4%%',
            str_repeat('K', 32)                                 => '%%HASH32%%',
            (string) self::ID                                   => '%%ID%%',
            Carbon::create(2001, 2, 3, 4, 5, 0)->copy()->setTimezone('UTC')->format('Y/m/d H:i') . ' UTC' => '%%ASSINATURA_DATA%%',
        ];

        uksort($mapa, fn ($a, $b) => strlen($b) <=> strlen($a));

        return $mapa;
    }
}
