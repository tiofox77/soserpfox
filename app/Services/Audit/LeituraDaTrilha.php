<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Torna a trilha de auditoria legível por uma pessoa.
 *
 * O ecrã mostrava o que está gravado, tal e qual: `warehouse_id: 12`,
 * `product_id: 8779`, `type: "out"`. É fiel — e não se lê. Quem consulta a
 * auditoria quer saber o que aconteceu, não os números que a base usa para
 * arrumar as coisas.
 *
 * TUDO AQUI É DO LADO DA LEITURA, de propósito, por duas razões:
 *
 *  1. A tabela é append-only e encadeada por hash. Enriquecer o que já lá está
 *     obrigaria a reescrever linhas — e reescrever linhas parte a cadeia, que é
 *     a única coisa que dá valor à trilha.
 *
 *  2. Resolvido na leitura, os registos ANTIGOS também passam a ler-se. Se
 *     fosse na escrita, só melhorava daqui para a frente e os meses já
 *     gravados continuavam ilegíveis.
 *
 * O preço é que os nomes são os de HOJE: um armazém renomeado aparece com o
 * nome novo num registo antigo. Por isso o identificador vai sempre junto —
 * "SALA DE VENDAS (#12)" — e é ele, não o nome, que ancora o registo.
 */
class LeituraDaTrilha
{
    /**
     * Colunas de referência que sabemos resolver.
     *
     * coluna => [modelo, colunas de rótulo (a primeira não vazia ganha), rótulo humano]
     *
     * A tabela é pedida ao MODELO e não escrita à mão. Escrevi-a à mão na
     * primeira versão e meti-me em erro em quatro das vinte: `products` em vez
     * de `invoicing_products`, `clients`, `suppliers`, `invoicing_stock`. Como
     * a resolução degrada em silêncio, o efeito não era um erro — era o artigo
     * continuar a aparecer como "#20810" e ninguém perceber porquê.
     *
     * Uma coluna `_id` que não esteja aqui fica como número. É melhor mostrar
     * o número do que adivinhar a tabela pelo nome e mostrar o registo errado.
     */
    private const REFERENCIAS = [
        'tenant_id'           => [\App\Models\Tenant::class,                     ['company_name', 'name'], 'Empresa'],
        'context_tenant_id'   => [\App\Models\Tenant::class,                     ['company_name', 'name'], 'Empresa activa'],
        'warehouse_id'        => [\App\Models\Invoicing\Warehouse::class,        ['name'],                 'Armazém'],
        'from_warehouse_id'   => [\App\Models\Invoicing\Warehouse::class,        ['name'],                 'Armazém de origem'],
        'to_warehouse_id'     => [\App\Models\Invoicing\Warehouse::class,        ['name'],                 'Armazém de destino'],
        'product_id'          => [\App\Models\Product::class,                    ['name'],                 'Artigo'],
        'user_id'             => [\App\Models\User::class,                       ['name'],                 'Utilizador'],
        'created_by'          => [\App\Models\User::class,                       ['name'],                 'Criado por'],
        'updated_by'          => [\App\Models\User::class,                       ['name'],                 'Alterado por'],
        'client_id'           => [\App\Models\Client::class,                     ['name'],                 'Cliente'],
        'supplier_id'         => [\App\Models\Supplier::class,                   ['name'],                 'Fornecedor'],
        'category_id'         => [\App\Models\Category::class,                   ['name'],                 'Categoria'],
        'brand_id'            => [\App\Models\Brand::class,                      ['name'],                 'Marca'],
        'tax_id'              => [\App\Models\Invoicing\Tax::class,              ['name'],                 'Imposto'],
        'series_id'           => [\App\Models\Invoicing\InvoicingSeries::class,  ['name', 'series_code'],  'Série'],
        'sales_invoice_id'    => [\App\Models\Invoicing\SalesInvoice::class,     ['invoice_number'],       'Factura'],
        'credit_note_id'      => [\App\Models\Invoicing\CreditNote::class,       ['credit_note_number'],   'Nota de crédito'],
        'debit_note_id'       => [\App\Models\Invoicing\DebitNote::class,        ['debit_note_number'],    'Nota de débito'],
        'purchase_invoice_id' => [\App\Models\Invoicing\PurchaseInvoice::class,  ['invoice_number'],       'Factura de compra'],
        'sales_proforma_id'   => [\App\Models\Invoicing\SalesProforma::class,    ['proforma_number'],      'Proforma'],
        'receipt_id'          => [\App\Models\Invoicing\Receipt::class,          ['receipt_number'],       'Recibo'],
        'batch_id'            => [\App\Models\Invoicing\ProductBatch::class,     ['batch_number'],         'Lote'],
        'pos_shift_id'        => [\App\Models\Invoicing\PosShift::class,         ['shift_number'],         'Turno de caixa'],
        'payment_method_id'   => [\App\Models\Treasury\PaymentMethod::class,     ['name'],                 'Forma de pagamento'],
    ];

    /** Nomes de campo em português. O que não estiver aqui fica como está. */
    private const CAMPOS = [
        'quantity'            => 'Quantidade',
        'available_quantity'  => 'Quantidade disponível',
        'reserved_quantity'   => 'Quantidade reservada',
        'balance_before'      => 'Saldo anterior',
        'balance_after'       => 'Saldo depois',
        'unit_cost'           => 'Custo unitário',
        'total_cost'          => 'Custo total',
        'unit_price'          => 'Preço unitário',
        'price'               => 'Preço',
        'cost'                => 'Custo',
        'subtotal'            => 'Subtotal',
        'total'               => 'Total',
        'gross_total'         => 'Total ilíquido',
        'net_total'           => 'Total líquido',
        'discount'            => 'Desconto',
        'discount_amount'     => 'Valor do desconto',
        'tax_amount'          => 'Valor do imposto',
        'tax_rate'            => 'Taxa de imposto',
        'tax_percentage'      => 'Percentagem de imposto',
        'type'                => 'Tipo',
        'status'              => 'Estado',
        'invoice_status'      => 'Estado fiscal',
        'payment_status'      => 'Estado do pagamento',
        'notes'               => 'Observações',
        'description'         => 'Descrição',
        'reference_type'      => 'Origem',
        'reference_id'        => 'Referência de origem',
        'batch_reference'     => 'Referência do lote',
        'invoice_number'      => 'Número da factura',
        'invoice_date'        => 'Data da factura',
        'due_date'            => 'Data de vencimento',
        'name'                => 'Nome',
        'code'                => 'Código',
        'barcode'             => 'Código de barras',
        'sku'                 => 'Referência',
        'nif'                 => 'NIF',
        'email'               => 'Email',
        'phone'               => 'Telefone',
        'address'             => 'Endereço',
        'is_active'           => 'Activo',
        'stock_quantity'      => 'Stock',
        'min_stock'           => 'Stock mínimo',
        'manage_stock'        => 'Gere stock',
        'expiry_date'         => 'Data de validade',
        'paid_amount'         => 'Valor pago',
        'amount'              => 'Valor',
        'rate'                => 'Taxa',
    ];

    /** Valores de enumeração em português. */
    private const VALORES = [
        'type' => [
            'in'         => 'entrada',
            'out'        => 'saída',
            'transfer'   => 'transferência',
            'adjustment' => 'ajuste',
        ],
        'status' => [
            'draft'     => 'rascunho',
            'sent'      => 'emitido',
            'paid'      => 'pago',
            'cancelled' => 'anulado',
            'pending'   => 'pendente',
        ],
    ];

    /** Rótulos já resolvidos: "tabela:id" => "nome" (ou false se não existir). */
    private array $cache = [];

    /**
     * Colunas de rótulo que cada tabela tem de facto.
     *
     * Estático porque o esquema não muda dentro do processo, e `hasColumn` é
     * uma ida à base por chamada: sem esta cache eram mais consultas de
     * inspecção do que de dados.
     */
    private static array $colunasDaTabela = [];

    /**
     * Resolve de uma vez os rótulos de que a página inteira precisa.
     *
     * Sem isto era uma consulta por campo e por linha — 25 registos com 12
     * campos davam centenas de consultas por render. Aqui são tantas quantas as
     * tabelas envolvidas, tipicamente três ou quatro.
     */
    public function preparar(Collection $registos): static
    {
        $porColuna = [];

        foreach ($registos as $registo) {
            foreach ($this->camposDeReferencia($registo) as $coluna => $ids) {
                $tabela = $this->tabelaDe($coluna);

                foreach ($ids as $id) {
                    if ($tabela && !isset($this->cache["{$tabela}:{$id}"])) {
                        $porColuna[$coluna][$id] = $id;
                    }
                }
            }
        }

        foreach ($porColuna as $coluna => $ids) {
            $this->carregar($coluna, array_values($ids));
        }

        return $this;
    }

    /**
     * A frase do que aconteceu, para a linha da listagem.
     *
     * É o que substitui "created · StockMovement": diz a operação em palavras,
     * com o artigo, o sítio e — quando existe — o saldo antes e depois.
     */
    public function frase(AuditTrail $registo): string
    {
        $valores = array_merge((array) $registo->old_values, (array) $registo->new_values);
        $modelo  = class_basename((string) $registo->auditable_type);

        $frase = match ($modelo) {
            'StockMovement' => $this->fraseMovimento($registo, $valores),
            'Stock'         => $this->fraseStock($registo),
            default         => null,
        };

        if ($frase !== null) {
            return $frase;
        }

        // Genérica: o acto, o tipo de registo e o seu rótulo congelado.
        $acto = $this->nomeDoActo($registo->event, $modelo);

        return $registo->auditable_label
            ? "{$acto} · {$registo->auditable_label}"
            : $acto;
    }

    /**
     * Os campos do detalhe, com nome em português e referências resolvidas.
     *
     * @return array<int, array{campo: string, rotulo: string, antes: ?string, depois: ?string, referencia: bool}>
     */
    public function campos(AuditTrail $registo): array
    {
        $antes  = (array) $registo->old_values;
        $depois = (array) $registo->new_values;

        $colunas = array_unique(array_merge(array_keys($antes), array_keys($depois)));

        $linhas = [];

        foreach ($colunas as $coluna) {
            // `id` num `created` é ruído: o identificador já está no cabeçalho
            // e "null → 12572" não diz nada a ninguém.
            if ($coluna === 'id' && $registo->event === 'created') {
                continue;
            }

            $linhas[] = [
                'campo'      => $coluna,
                'rotulo'     => $this->rotuloDoCampo($coluna),
                'antes'      => array_key_exists($coluna, $antes) ? $this->valor($coluna, $antes[$coluna]) : null,
                'depois'     => array_key_exists($coluna, $depois) ? $this->valor($coluna, $depois[$coluna]) : null,
                'referencia' => isset(self::REFERENCIAS[$coluna]),
            ];
        }

        // As referências primeiro: são elas que dizem de que artigo e de que
        // armazém se fala, e é isso que se procura ao abrir o detalhe.
        usort($linhas, fn ($a, $b) => ($b['referencia'] <=> $a['referencia']));

        return $linhas;
    }

    /** Um valor pronto a mostrar: referência resolvida, enumeração traduzida. */
    public function valor(string $coluna, mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($valor)) {
            return $valor ? 'sim' : 'não';
        }

        if (isset(self::REFERENCIAS[$coluna]) && is_numeric($valor)) {
            return $this->rotuloDaReferencia($coluna, (int) $valor);
        }

        if (isset(self::VALORES[$coluna][$valor])) {
            return self::VALORES[$coluna][$valor];
        }

        // Nomes de classe: "App\Models\Invoicing\SalesInvoice" → "Factura".
        if (is_string($valor) && str_starts_with($valor, 'App\\Models\\')) {
            return $this->nomeDoModelo(class_basename($valor));
        }

        return (string) $valor;
    }

    /** "SALA DE VENDAS (#12)", ou "#12" se o registo já não existir. */
    public function rotuloDaReferencia(string $coluna, int $id): string
    {
        $tabela = $this->tabelaDe($coluna);

        if (!$tabela) {
            return "#{$id}";
        }

        if (!array_key_exists("{$tabela}:{$id}", $this->cache)) {
            // Não estava no lote preparado: resolver à cabeça. Acontece no
            // detalhe de uma linha só, onde uma consulta a mais não pesa.
            $this->carregar($coluna, [$id]);
        }

        $nome = $this->cache["{$tabela}:{$id}"] ?? false;

        // O identificador vai sempre junto: o nome é o de hoje e o registo é do
        // passado, portanto é o número que ancora o facto.
        return $nome ? "{$nome} (#{$id})" : "#{$id}";
    }

    /** A tabela do modelo por trás de uma coluna de referência. */
    private function tabelaDe(string $coluna): ?string
    {
        if (!isset(self::REFERENCIAS[$coluna])) {
            return null;
        }

        try {
            return (new (self::REFERENCIAS[$coluna][0]))->getTable();
        } catch (\Throwable) {
            return null;
        }
    }

    public function rotuloDoCampo(string $coluna): string
    {
        if (isset(self::REFERENCIAS[$coluna])) {
            return self::REFERENCIAS[$coluna][2];
        }

        return self::CAMPOS[$coluna] ?? ucfirst(str_replace('_', ' ', $coluna));
    }

    // ── frases por tipo de registo ───────────────────────────────────────────

    /**
     * "Saída de 1 un · SEGURO-72HORAS · SALA DE VENDAS · saldo 7 → 6"
     *
     * É o registo mais frequente da trilha e era o menos legível: seis
     * identificadores e um `type: "out"`.
     */
    private function fraseMovimento(AuditTrail $registo, array $v): ?string
    {
        if ($registo->event !== 'created') {
            return null;   // um movimento alterado é anómalo: mostrar o diff cru
        }

        $tipo = self::VALORES['type'][$v['type'] ?? ''] ?? ($v['type'] ?? 'movimento');
        $qtd  = isset($v['quantity']) ? $this->quantidade($v['quantity']) : null;

        $partes = [ucfirst($tipo) . ($qtd !== null ? " de {$qtd}" : '')];

        if (!empty($v['product_id'])) {
            $partes[] = $this->rotuloDaReferencia('product_id', (int) $v['product_id']);
        }

        if (!empty($v['warehouse_id'])) {
            $partes[] = 'em ' . $this->rotuloDaReferencia('warehouse_id', (int) $v['warehouse_id']);
        }

        // O saldo é o que transforma o registo em prova: sem ele sabe-se que
        // saiu 1, não se sabe de quanto para quanto.
        if (isset($v['balance_before'], $v['balance_after'])) {
            $partes[] = 'saldo ' . $this->quantidade($v['balance_before']) . ' → ' . $this->quantidade($v['balance_after']);
        }

        return implode(' · ', $partes);
    }

    /** "Stock passou de 7 para 6 · SEGURO-72HORAS · SALA DE VENDAS" */
    private function fraseStock(AuditTrail $registo): ?string
    {
        $antes  = (array) $registo->old_values;
        $depois = (array) $registo->new_values;

        if (!isset($depois['quantity'])) {
            return null;
        }

        $de   = isset($antes['quantity']) ? $this->quantidade($antes['quantity']) : '?';
        $para = $this->quantidade($depois['quantity']);

        $partes = ["Stock passou de {$de} para {$para}"];

        // A linha de stock não traz o artigo no diff (só mudou a quantidade):
        // vai-se buscá-lo ao próprio registo, pelo identificador auditado.
        $linha = $this->linhaDeStock((int) $registo->auditable_id);

        if ($linha) {
            $partes[] = $this->rotuloDaReferencia('product_id', (int) $linha->product_id);
            $partes[] = 'em ' . $this->rotuloDaReferencia('warehouse_id', (int) $linha->warehouse_id);
        }

        return implode(' · ', $partes);
    }

    /**
     * O artigo e o armazém de uma linha de stock, para dar contexto ao diff.
     *
     * Em try/catch como tudo o que aqui vai à base: este é um ecrã de consulta
     * e nenhuma falha de enriquecimento pode impedir alguém de ver a trilha.
     * Escrevi o nome da tabela à mão e estava errado — o ecrã deixou de abrir.
     */
    private function linhaDeStock(int $id): ?object
    {
        try {
            return DB::table((new \App\Models\Invoicing\Stock)->getTable())
                ->select('product_id', 'warehouse_id')
                ->where('id', $id)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    // ── auxiliares ───────────────────────────────────────────────────────────

    /** As referências que este registo usa, agrupadas por coluna. */
    private function camposDeReferencia(AuditTrail $registo): array
    {
        $valores = array_merge((array) $registo->old_values, (array) $registo->new_values);

        $encontradas = [];

        foreach ($valores as $coluna => $valor) {
            if (isset(self::REFERENCIAS[$coluna]) && is_numeric($valor)) {
                $encontradas[$coluna][] = (int) $valor;
            }
        }

        return $encontradas;
    }

    /** Traz os rótulos de uma coluna de referência para a cache, numa só consulta. */
    private function carregar(string $coluna, array $ids): void
    {
        $tabela = $this->tabelaDe($coluna);

        if (!$tabela || empty($ids)) {
            return;
        }

        try {
            $existentes = $this->colunasDeRotulo($tabela, self::REFERENCIAS[$coluna][1]);

            if (!empty($existentes)) {
                $linhas = DB::table($tabela)
                    ->select(array_merge(['id'], $existentes))
                    ->whereIn('id', $ids)
                    ->get();

                foreach ($linhas as $linha) {
                    foreach ($existentes as $c) {
                        if (!empty($linha->{$c})) {
                            $this->cache["{$tabela}:{$linha->id}"] = (string) $linha->{$c};
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Tabela por migrar, coluna renomeada, o que for. A trilha continua
            // a mostrar-se com os números — degradar é melhor do que rebentar
            // um ecrã de consulta.
        }

        // Os que não vieram ficam marcados como resolvidos-e-inexistentes, para
        // não voltarem a ser procurados linha a linha.
        foreach ($ids as $id) {
            $this->cache["{$tabela}:{$id}"] ??= false;
        }
    }

    /**
     * Das colunas candidatas, as que a tabela tem de facto.
     *
     * O esquema variou ao longo do tempo e nem todas as tabelas têm todas —
     * `company_name` só existe em `tenants`. O resultado fica em cache estática
     * porque `hasColumn` é uma ida à base por chamada.
     */
    private function colunasDeRotulo(string $tabela, array $candidatas): array
    {
        $chave = $tabela . '|' . implode(',', $candidatas);

        return self::$colunasDaTabela[$chave] ??= array_values(array_filter(
            $candidatas,
            fn ($c) => \Illuminate\Support\Facades\Schema::hasColumn($tabela, $c)
        ));
    }

    /** "12" e não "12,0000"; "1,5" mantém a casa que tem. */
    private function quantidade(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    }

    private function nomeDoActo(string $evento, string $modelo): string
    {
        $acto = match ($evento) {
            'created'       => 'Criou',
            'updated'       => 'Alterou',
            'deleted'       => 'Eliminou',
            'force_deleted' => 'Eliminou definitivamente',
            'restored'      => 'Repôs',
            default         => $evento,
        };

        return $acto . ' ' . $this->nomeDoModelo($modelo);
    }

    /** "SalesInvoiceItem" → "linha de factura". */
    public function nomeDoModelo(string $modelo): string
    {
        return match ($modelo) {
            'SalesInvoice'         => 'factura',
            'SalesInvoiceItem'     => 'linha de factura',
            'CreditNote'           => 'nota de crédito',
            'CreditNoteItem'       => 'linha de nota de crédito',
            'DebitNote'            => 'nota de débito',
            'DebitNoteItem'        => 'linha de nota de débito',
            'Receipt'              => 'recibo',
            'SalesProforma'        => 'proforma de venda',
            'SalesProformaItem'    => 'linha de proforma',
            'PurchaseInvoice'      => 'factura de compra',
            'PurchaseInvoiceItem'  => 'linha de factura de compra',
            'PurchaseProforma'     => 'proforma de compra',
            'TransportGuide'       => 'guia de transporte',
            'TransportGuideItem'   => 'linha de guia',
            'Advance'              => 'adiantamento',
            'InvoicingSeries'      => 'série de facturação',
            'InvoicingSettings'    => 'definições de facturação',
            'Tax'                  => 'imposto',
            'Warehouse'            => 'armazém',
            'Product'              => 'artigo',
            'Client'               => 'cliente',
            'LineTax'              => 'imposto de linha',
            'Stock'                => 'linha de stock',
            'StockMovement'        => 'movimento de stock',
            'ProductBatch'         => 'lote de artigo',
            'Transaction'          => 'movimento de tesouraria',
            'PosShift'             => 'turno de caixa',
            'PosShiftTransaction'  => 'movimento de caixa',
            'Import'               => 'importação',
            'ImportItem'           => 'linha de importação',
            'User'                 => 'utilizador',
            default                => $modelo,
        };
    }
}
