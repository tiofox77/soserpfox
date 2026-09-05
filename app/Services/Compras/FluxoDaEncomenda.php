<?php

namespace App\Services\Compras;

use App\Models\Compras\Encomenda;
use App\Models\Compras\EncomendaItem;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\TaxResolver;
use Illuminate\Support\Facades\DB;

/**
 * O caminho de uma encomenda: rascunho → enviada → recebida → facturada.
 *
 * Duas regras mandam aqui:
 *
 * 1. **Encomendar não é receber.** O stock só se mexe na recepção, e só na
 *    medida do que chegou — uma encomenda enviada e nunca entregue não pode
 *    inflacionar armazém nenhum.
 * 2. **O stock entra UMA vez.** Como a mercadoria já entrou na recepção, a
 *    factura gerada a seguir nasce marcada com `stock_ja_entrou` para o
 *    observer das compras não dar entrada outra vez.
 */
class FluxoDaEncomenda
{
    public function criar(int $tenantId, ?int $userId, array $dados, array $linhas): Encomenda
    {
        $limpas = $this->linhasValidas($linhas, $tenantId);

        if ($limpas === []) {
            throw new \InvalidArgumentException('Uma encomenda precisa de pelo menos um artigo com quantidade.');
        }

        $this->fornecedorDaCasa($dados['supplier_id'] ?? null, $tenantId);

        return DB::transaction(function () use ($tenantId, $userId, $dados, $limpas) {
            $enc = Encomenda::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $dados['supplier_id'],
                'warehouse_id' => $dados['warehouse_id'] ?? null,
                'requisicao_id' => $dados['requisicao_id'] ?? null,
                'data_encomenda' => $dados['data_encomenda'] ?? now()->toDateString(),
                'entrega_prevista' => $dados['entrega_prevista'] ?? null,
                'notas' => $dados['notas'] ?? null,
                'condicoes' => $dados['condicoes'] ?? null,
                'estado' => 'rascunho',
                'created_by' => $userId,
            ]);

            $this->gravarLinhas($enc, $limpas);
            $enc->recalcularTotais();

            return $enc->fresh('itens');
        });
    }

    public function actualizar(Encomenda $enc, int $tenantId, array $dados, array $linhas): Encomenda
    {
        $this->minha($enc, $tenantId);

        if (! $enc->podeEditar()) {
            throw new \InvalidArgumentException('Só um rascunho se edita. Esta encomenda já saiu.');
        }

        $limpas = $this->linhasValidas($linhas, $tenantId);

        if ($limpas === []) {
            throw new \InvalidArgumentException('Uma encomenda precisa de pelo menos um artigo com quantidade.');
        }

        $this->fornecedorDaCasa($dados['supplier_id'] ?? null, $tenantId);

        return DB::transaction(function () use ($enc, $dados, $limpas) {
            $enc->update([
                'supplier_id' => $dados['supplier_id'],
                'warehouse_id' => $dados['warehouse_id'] ?? $enc->warehouse_id,
                'data_encomenda' => $dados['data_encomenda'] ?? $enc->data_encomenda,
                'entrega_prevista' => $dados['entrega_prevista'] ?? null,
                'notas' => $dados['notas'] ?? null,
                'condicoes' => $dados['condicoes'] ?? null,
            ]);

            $enc->itens()->delete();
            $this->gravarLinhas($enc, $limpas);
            $enc->recalcularTotais();

            return $enc->fresh('itens');
        });
    }

    /**
     * Nascer de uma requisição aprovada.
     *
     * Só passam as linhas que ainda faltam encomendar, e o que fica encomendado
     * marca-se na requisição — encomendar metade deixa a outra metade à vista.
     */
    public function daRequisicao(Requisicao $req, int $tenantId, ?int $userId, int $supplierId, array $dados = []): Encomenda
    {
        if ((int) $req->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Requisição de outra empresa.');
        }

        if (! in_array($req->estado, ['aprovada', 'encomendada'], true)) {
            throw new \InvalidArgumentException('Só uma requisição aprovada dá origem a encomendas.');
        }

        $req->load('itens');
        $pendentes = $req->itens->filter(fn ($i) => $i->porEncomendar() > 0);

        if ($pendentes->isEmpty()) {
            throw new \InvalidArgumentException('Já está tudo encomendado nesta requisição.');
        }

        $linhas = $pendentes->map(fn ($i) => [
            'product_id' => $i->product_id,
            'descricao' => $i->descricao,
            'quantidade' => $i->porEncomendar(),
            'preco_unitario' => $i->custo_estimado ?? 0,
            'desconto_percent' => 0,
            'unidade' => $i->unidade,
        ])->values()->all();

        return DB::transaction(function () use ($req, $tenantId, $userId, $supplierId, $dados, $linhas, $pendentes) {
            $enc = $this->criar($tenantId, $userId, [
                'supplier_id' => $supplierId,
                'warehouse_id' => $dados['warehouse_id'] ?? $req->warehouse_id,
                'requisicao_id' => $req->id,
                'entrega_prevista' => $dados['entrega_prevista'] ?? $req->necessaria_em?->toDateString(),
                'notas' => $dados['notas'] ?? null,
            ], $linhas);

            foreach ($pendentes as $item) {
                $item->update([
                    'quantidade_encomendada' => (float) $item->quantidade_encomendada + $item->porEncomendar(),
                ]);
            }

            $req->update(['estado' => 'encomendada']);

            return $enc;
        });
    }

    public function enviar(Encomenda $enc, int $tenantId): Encomenda
    {
        $this->minha($enc, $tenantId);

        if ($enc->estado !== 'rascunho') {
            throw new \InvalidArgumentException('Só se envia um rascunho.');
        }

        if ($enc->itens()->count() === 0) {
            throw new \InvalidArgumentException('Não se envia uma encomenda sem artigos.');
        }

        $enc->update(['estado' => 'enviada']);

        return $enc;
    }

    public function confirmar(Encomenda $enc, int $tenantId): Encomenda
    {
        $this->minha($enc, $tenantId);

        if ($enc->estado !== 'enviada') {
            throw new \InvalidArgumentException('Só se confirma uma encomenda enviada.');
        }

        $enc->update(['estado' => 'confirmada']);

        return $enc;
    }

    /**
     * Receber mercadoria — É AQUI que o stock entra.
     *
     * `$quantidades` é [id da linha => quantidade que chegou agora]. Aceita
     * parcelas: recebe-se o que veio, e a encomenda fica «recebida em parte»
     * até ao resto chegar. Só linhas com artigo do catálogo movem stock — uma
     * linha de texto livre não tem onde entrar.
     *
     * A entrada faz-se pelo caminho canónico da casa: cria-se o movimento
     * `in` e o hook do movimento é que aplica (`Stock::addStock`), com o custo
     * médio ponderado e o `StockObserver` a acertar o agregado do artigo. Esse
     * caminho resolve a empresa pela SESSÃO — daí a guarda logo no início: fora
     * da sessão da empresa certa, isto tem de falhar alto e não escrever nada.
     *
     * @param  array<int, mixed>  $quantidades
     */
    public function receber(Encomenda $enc, int $tenantId, ?int $userId, array $quantidades): Encomenda
    {
        $this->minha($enc, $tenantId);

        if (! in_array($enc->estado, Encomenda::ABERTOS, true)) {
            throw new \InvalidArgumentException('Esta encomenda não está à espera de mercadoria.');
        }

        if (activeTenantId() !== $tenantId) {
            throw new \InvalidArgumentException('A recepção tem de correr na sessão da empresa da encomenda.');
        }

        if (! $userId) {
            throw new \InvalidArgumentException('A recepção tem de ficar em nome de alguém.');
        }

        if (! $enc->warehouse_id) {
            throw new \InvalidArgumentException('Escolha o armazém de entrada antes de receber.');
        }

        $enc->load('itens');
        $aReceber = [];

        foreach ($enc->itens as $item) {
            $qtd = (float) ($quantidades[$item->id] ?? 0);

            if ($qtd <= 0) {
                continue;
            }

            if ($qtd > $item->porReceber() + 0.0001) {
                $falta = rtrim(rtrim(number_format($item->porReceber(), 3, ',', '.'), '0'), ',');

                throw new \InvalidArgumentException(
                    "Está a receber mais «{$item->descricao}» do que foi encomendado (faltam {$falta})."
                );
            }

            $aReceber[] = [$item, $qtd];
        }

        if ($aReceber === []) {
            throw new \InvalidArgumentException('Indique o que chegou — nenhuma linha tem quantidade.');
        }

        return DB::transaction(function () use ($enc, $tenantId, $userId, $aReceber) {
            foreach ($aReceber as [$item, $qtd]) {
                if ($item->product_id) {
                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'warehouse_id' => $enc->warehouse_id,
                        'product_id' => $item->product_id,
                        'type' => StockMovement::TYPE_IN,
                        'quantity' => $qtd,
                        'unit_cost' => $this->custoUnitario($item),
                        'total_cost' => round($qtd * $this->custoUnitario($item), 2),
                        'reference_type' => Encomenda::class,
                        'reference_id' => $enc->id,
                        'user_id' => $userId,
                        'notes' => "Recepção da encomenda {$enc->numero}",
                    ]);

                    $this->actualizarCusto($item, $tenantId);
                }

                $item->update([
                    'quantidade_recebida' => (float) $item->quantidade_recebida + $qtd,
                ]);
            }

            $enc->load('itens');
            $porReceber = $enc->itens->sum(fn ($i) => $i->porReceber());
            $enc->update(['estado' => $porReceber > 0.0001 ? 'parcial' : 'recebida']);

            return $enc->fresh('itens');
        });
    }

    /**
     * Cancelar não apaga nada. Uma encomenda com mercadoria já recebida não se
     * cancela — o que entrou no armazém entrou, e desfazer isso é uma
     * devolução, não um clique.
     */
    public function cancelar(Encomenda $enc, int $tenantId): Encomenda
    {
        $this->minha($enc, $tenantId);

        if ($enc->estado === 'cancelada') {
            throw new \InvalidArgumentException('Esta encomenda já está cancelada.');
        }

        if ($enc->itens()->sum('quantidade_recebida') > 0) {
            throw new \InvalidArgumentException('Já entrou mercadoria desta encomenda. Trate a devolução em vez de cancelar.');
        }

        $enc->update(['estado' => 'cancelada']);

        return $enc;
    }

    /**
     * Gerar a factura de compra do que JÁ CHEGOU.
     *
     * Nasce como rascunho, para quem compra conferir contra o papel do
     * fornecedor antes de a assumir. E nasce marcada com `stock_ja_entrou`:
     * a mercadoria entrou na recepção, e sem esta marca o observer das compras
     * dava entrada outra vez quando a factura saísse de rascunho.
     *
     * A TAXA é a de HOJE, resolvida pelo TaxResolver — nunca a que ficou
     * gravada na encomenda. Mesma regra da conversão do orçamento.
     */
    public function facturar(Encomenda $enc, int $tenantId, ?int $userId): PurchaseInvoice
    {
        $this->minha($enc, $tenantId);

        if (! $enc->podeFacturar()) {
            throw new \InvalidArgumentException(
                $enc->purchase_invoice_id
                    ? 'Esta encomenda já foi facturada.'
                    : 'Só se factura o que já chegou. Registe primeiro a recepção.'
            );
        }

        $enc->load('itens');

        return DB::transaction(function () use ($enc, $tenantId, $userId) {
            $factura = PurchaseInvoice::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $enc->supplier_id,
                'warehouse_id' => $enc->warehouse_id,
                'stock_ja_entrou' => true,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'status' => 'draft',
                'currency' => $enc->moeda,
                'exchange_rate' => $enc->cambio,
                'notes' => trim("Gerada da encomenda {$enc->numero}.\n".(string) $enc->notas),
                'created_by' => $userId,
            ]);

            $ordem = 0;

            foreach ($enc->itens as $item) {
                $recebida = (float) $item->quantidade_recebida;

                if ($recebida <= 0) {
                    continue;
                }

                $tx = $item->product_id
                    ? TaxResolver::forProductId($item->product_id, $tenantId)
                    : ['rate' => 0.0, 'tax_code' => 'ISE', 'exemption_code' => null, 'exemption_reason' => null];

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $factura->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->descricao,
                    'description' => $item->descricao,
                    'quantity' => $recebida,
                    'unit' => $item->unidade ?: 'UN',
                    'unit_price' => $item->preco_unitario,
                    'discount_percent' => $item->desconto_percent,
                    'tax_rate_id' => $item->tax_rate_id,
                    'tax_rate' => (float) $tx['rate'],
                    'tax_country_region' => 'AO',
                    'order' => $ordem++,
                ]);
            }

            // Os totais do cabeçalho saem das linhas já gravadas, com a taxa de
            // hoje — nunca se copiam os da encomenda.
            $factura->load('items');
            $subtotal = (float) $factura->items->sum('subtotal');
            $desconto = (float) $factura->items->sum('discount_amount');
            $imposto = (float) $factura->items->sum('tax_amount');
            $liquido = $subtotal - $desconto;

            $factura->update([
                'subtotal' => $subtotal,
                'net_total' => $liquido,
                'discount_amount' => $desconto,
                'tax_amount' => $imposto,
                'tax_payable' => $imposto,
                'total' => $liquido + $imposto,
                'gross_total' => $liquido + $imposto,
            ]);

            $enc->update(['purchase_invoice_id' => $factura->id]);

            return $factura;
        });
    }

    // ─────────────────────────────────────────────────────────────────────

    /** Custo unitário da linha, líquido de desconto e SEM imposto. */
    private function custoUnitario(EncomendaItem $item): float
    {
        $bruto = (float) $item->preco_unitario;

        return round($bruto * (1 - (float) $item->desconto_percent / 100), 2);
    }

    /**
     * O preço a que se comprou passa a ser o custo do artigo — a mesma regra
     * de `ActualizarCustoDeCompra`, aplicada aqui porque no circuito novo é a
     * recepção, e não a factura, o momento em que a mercadoria entra.
     */
    private function actualizarCusto(EncomendaItem $item, int $tenantId): void
    {
        $custo = $this->custoUnitario($item);

        if ($custo <= 0) {
            return;
        }

        Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $item->product_id)
            ->where('type', '!=', 'servico')
            ->update(['cost' => $custo]);
    }

    /**
     * Descarta linhas vazias e resolve a taxa de cada uma.
     *
     * A taxa NUNCA se aceita do ecrã: resolve-se sempre pelo TaxResolver a
     * partir do artigo, que é a fonte única da casa. Uma linha sem artigo do
     * catálogo fica isenta — não há de onde tirar taxa.
     */
    private function linhasValidas(array $linhas, int $tenantId): array
    {
        $limpas = [];

        foreach ($linhas as $linha) {
            $descricao = trim((string) ($linha['descricao'] ?? ''));
            $quantidade = (float) ($linha['quantidade'] ?? 0);

            if ($descricao === '' || $quantidade <= 0) {
                continue;
            }

            $productId = $linha['product_id'] ?? null;
            $tx = $productId
                ? TaxResolver::forProductId($productId, $tenantId)
                : ['rate' => 0.0];

            // A unidade vem do artigo quando não vier do ecrã: a linha da
            // factura de compra exige-a, e uma linha sem unidade fazia a
            // facturação rebentar já depois de a mercadoria ter entrado.
            $unidade = $linha['unidade'] ?? null;
            if (! $unidade && $productId) {
                // withoutGlobalScopes + tenant explícito: o serviço tem de
                // funcionar fora de uma sessão (comando, fila), onde o scope
                // de empresa filtraria por null e não devolvia nada.
                $unidade = Product::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)->whereKey($productId)->value('unit');
            }

            $limpas[] = [
                'product_id' => $productId,
                'descricao' => $descricao,
                'quantidade' => $quantidade,
                'preco_unitario' => (float) ($linha['preco_unitario'] ?? 0),
                'desconto_percent' => (float) ($linha['desconto_percent'] ?? 0),
                'tax_rate' => (float) $tx['rate'],
                'unidade' => $unidade ?: 'UN',
            ];
        }

        return $limpas;
    }

    private function gravarLinhas(Encomenda $enc, array $linhas): void
    {
        foreach ($linhas as $i => $linha) {
            EncomendaItem::create($linha + [
                'encomenda_id' => $enc->id,
                'ordem' => $i,
            ]);
        }
    }

    /** O Supplier não tem scope de empresa — o filtro é à mão, sempre. */
    private function fornecedorDaCasa($supplierId, int $tenantId): void
    {
        if (! $supplierId) {
            throw new \InvalidArgumentException('Escolha o fornecedor.');
        }

        $existe = Supplier::where('tenant_id', $tenantId)->whereKey($supplierId)->exists();

        if (! $existe) {
            throw new \InvalidArgumentException('Fornecedor desconhecido nesta empresa.');
        }
    }

    private function minha(Encomenda $enc, int $tenantId): void
    {
        if ((int) $enc->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Encomenda de outra empresa.');
        }
    }
}
