<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * TRANSFERIR STOCK — entre armazéns, entre empresas — e o ajuste em lote.
 *
 * Vivia dentro do `WarehouseTransfer` e do `InterCompanyTransfer`. Ao migrar
 * os ecrãs para React, saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE, e que custou caro a aprender:
 *
 *  · `save()` ELOQUENT E NUNCA increment/decrement: só o save dispara o
 *    StockObserver que mantém o agregado do artigo e recalcula o disponível.
 *
 *  · OS QUATRO SALDOS (antes e depois, de cada lado) lêem-se ANTES de mexer
 *    e vão explícitos nas duas pernas do movimento. Deixá-los derivar não
 *    funciona entre empresas: a leitura automática traz o global scope da
 *    empresa activa e a perna do destino ficava sem saldo nenhum.
 *
 *  · A REFERÊNCIA (MOV/AAAA/NNNNNN) é sequencial POR EMPRESA. Entre empresas
 *    são DUAS referências, cada uma na sua sequência; escrever a da origem
 *    nas linhas do destino corrompia a sequência do destino.
 *
 *  · OS LOTES seguem a mercadoria por FEFO — o que expira primeiro sai
 *    primeiro — e nascem no destino com o mesmo número.
 *
 *  · A EMPRESA DESTINO resolve-se a partir das empresas DO UTILIZADOR: um id
 *    arbitrário permitia escrever stock em qualquer empresa da plataforma.
 */
class TransferenciaDeStock
{
    /* ─── Entre armazéns ───────────────────────────────────────────────── */

    /**
     * @param  array $itens  [{product_id, product_name?, product_code?, quantity}]
     * @return array{referencia: string, resumo: array}
     *
     * @throws DomainException
     */
    public function entreArmazens(int $de, int $para, array $itens, ?string $notas, int $tenantId, ?int $userId): array
    {
        $itens = $this->linhasComProduto($itens);

        if (empty($itens)) {
            throw new DomainException(__('Adicione pelo menos um produto à transferência.'));
        }
        if (! $de || ! $para) {
            throw new DomainException(__('Selecione os armazéns de origem e destino.'));
        }
        if ($de === $para) {
            throw new DomainException(__('O destino tem de ser outro armazém.'));
        }

        $origem = Warehouse::where('tenant_id', $tenantId)->find($de);
        $destino = Warehouse::where('tenant_id', $tenantId)->find($para);

        if (! $origem || ! $destino) {
            throw new DomainException(__('Armazém desconhecido nesta empresa.'));
        }

        $resumo = [];
        $referencia = null;

        StockMovement::comLoteReservado($tenantId, function (string $ref) use ($origem, $destino, $itens, $notas, $tenantId, $userId, &$referencia, &$resumo) {
            $referencia = $ref;

            DB::transaction(function () use ($ref, $origem, $destino, $itens, $notas, $tenantId, $userId, &$resumo) {
                // Mantido para o histórico antigo continuar a agrupar; a
                // referência a sério é a $ref.
                $batchId = crc32($ref);

                foreach ($itens as $item) {
                    $qtd = (float) $item['quantity'];

                    if ($qtd <= 0) {
                        throw new DomainException(__('A quantidade deve ser maior que zero.'));
                    }

                    $stockDe = Stock::where('tenant_id', $tenantId)->where('warehouse_id', $origem->id)->where('product_id', $item['product_id'])->first();

                    if (! $stockDe || (float) $stockDe->quantity < $qtd) {
                        throw new DomainException(__('Stock insuficiente para :artigo', ['artigo' => $item['product_name'] ?? ('#' . $item['product_id'])]));
                    }

                    $origemAntes = (float) $stockDe->quantity;
                    $origemDepois = $origemAntes - $qtd;
                    $stockDe->quantity = $origemDepois;
                    $stockDe->save();

                    $stockPara = Stock::firstOrCreate(
                        ['tenant_id' => $tenantId, 'warehouse_id' => $destino->id, 'product_id' => $item['product_id']],
                        ['quantity' => 0]
                    );
                    $destinoAntes = (float) $stockPara->quantity;
                    $destinoDepois = $destinoAntes + $qtd;
                    $stockPara->quantity = $destinoDepois;
                    $stockPara->save();

                    $produto = Product::find($item['product_id']);

                    if ($produto && $produto->track_batches) {
                        $this->lotesEntreArmazens($produto->id, $origem->id, $destino->id, $qtd, $tenantId);
                    }

                    // O stock já foi actualizado à mão: os movimentos registam-se sem voltar a aplicá-lo.
                    StockMovement::semAplicarStock(function () use ($item, $batchId, $ref, $produto, $origem, $destino, $qtd, $origemAntes, $origemDepois, $destinoAntes, $destinoDepois, $notas, $tenantId, $userId) {
                        $comum = [
                            'tenant_id' => $tenantId,
                            'product_id' => $item['product_id'],
                            'type' => 'transfer',
                            'unit_cost' => $produto?->cost,
                            'reference_type' => 'transfer_batch',
                            'reference_id' => $batchId,
                            'batch_reference' => $ref,
                            'from_warehouse_id' => $origem->id,
                            'to_warehouse_id' => $destino->id,
                            'user_id' => $userId,
                        ];

                        StockMovement::create($comum + [
                            'warehouse_id' => $origem->id, 'quantity' => -$qtd,
                            'balance_before' => $origemAntes, 'balance_after' => $origemDepois,
                            'notes' => "Transfer para {$destino->name}. {$notas}",
                        ]);

                        StockMovement::create($comum + [
                            'warehouse_id' => $destino->id, 'quantity' => $qtd,
                            'balance_before' => $destinoAntes, 'balance_after' => $destinoDepois,
                            'notes' => "Transfer de {$origem->name}. {$notas}",
                        ]);
                    });

                    $resumo[] = [
                        'produto' => $item['product_name'] ?? $produto?->name,
                        'codigo' => $item['product_code'] ?? $produto?->code,
                        'quantidade' => $qtd,
                        'origem' => $origem->name,
                        'origem_antes' => $origemAntes,
                        'origem_depois' => $origemDepois,
                        'destino' => $destino->name,
                        'destino_antes' => $destinoAntes,
                        'destino_depois' => $destinoDepois,
                    ];
                }
            });
        });

        return ['referencia' => $referencia, 'resumo' => $resumo];
    }

    /**
     * O ajuste em lote: várias linhas, um sentido (entrada ou saída), um motivo.
     *
     * @return array{referencia: string, resumo: array}
     *
     * @throws DomainException
     */
    public function ajustarEmLote(int $armazemId, string $tipo, string $motivo, array $itens, int $tenantId, ?int $userId): array
    {
        $itens = $this->linhasComProduto($itens);

        if (empty($itens)) {
            throw new DomainException(__('Adicione pelo menos um produto ao ajuste.'));
        }
        if (! $armazemId || trim($motivo) === '') {
            throw new DomainException(__('Selecione o armazém e informe o motivo.'));
        }
        if (! in_array($tipo, ['in', 'out'], true)) {
            throw new DomainException(__('O ajuste é de entrada ou de saída.'));
        }

        $armazem = Warehouse::where('tenant_id', $tenantId)->find($armazemId);

        if (! $armazem) {
            throw new DomainException(__('Armazém desconhecido nesta empresa.'));
        }

        $resumo = [];
        $referencia = null;

        StockMovement::comLoteReservado($tenantId, function (string $ref) use ($armazem, $tipo, $motivo, $itens, $tenantId, $userId, &$referencia, &$resumo) {
            $referencia = $ref;

            DB::transaction(function () use ($ref, $armazem, $tipo, $motivo, $itens, $tenantId, $userId, &$resumo) {
                $batchId = crc32($ref);

                foreach ($itens as $item) {
                    $produto = Product::find($item['product_id']);
                    $qtd = (float) $item['quantity'];

                    if ($qtd <= 0) {
                        throw new DomainException(__('A quantidade deve ser maior que zero.'));
                    }

                    $stock = Stock::firstOrCreate(
                        ['tenant_id' => $tenantId, 'warehouse_id' => $armazem->id, 'product_id' => $item['product_id']],
                        ['quantity' => 0]
                    );

                    $antes = (float) $stock->quantity;

                    if ($tipo === 'in') {
                        $depois = $antes + $qtd;
                    } else {
                        // Num ajuste de saída o saldo valida-se: antes era possível negativar o armazém.
                        if ($antes < $qtd) {
                            throw new DomainException(__('Stock insuficiente para :artigo (disponível: :q)', ['artigo' => $item['product_name'] ?? ('#' . $item['product_id']), 'q' => $antes]));
                        }
                        $depois = $antes - $qtd;
                    }

                    $stock->quantity = $depois;
                    $stock->save();

                    StockMovement::semAplicarStock(function () use ($item, $batchId, $ref, $produto, $armazem, $tipo, $motivo, $qtd, $antes, $depois, $tenantId, $userId) {
                        StockMovement::create([
                            'tenant_id' => $tenantId,
                            'warehouse_id' => $armazem->id,
                            'product_id' => $item['product_id'],
                            'type' => 'adjustment',
                            'quantity' => $tipo === 'in' ? $qtd : -$qtd,
                            // Num ajuste o saldo anterior não se deriva da quantidade — tem de vir de quem o gravou.
                            'balance_before' => $antes,
                            'balance_after' => $depois,
                            'unit_cost' => $produto?->cost,
                            'reference_type' => 'adjustment_batch',
                            'reference_id' => $batchId,
                            'batch_reference' => $ref,
                            'notes' => "Ajuste manual ({$tipo}): {$motivo}",
                            'user_id' => $userId,
                        ]);
                    });

                    $resumo[] = [
                        'produto' => $item['product_name'] ?? $produto?->name,
                        'codigo' => $item['product_code'] ?? $produto?->code,
                        'quantidade' => $qtd,
                        'armazem' => $armazem->name,
                        'antes' => $antes,
                        'depois' => $depois,
                    ];
                }
            });
        });

        return ['referencia' => $referencia, 'resumo' => $resumo];
    }

    /* ─── Entre empresas ───────────────────────────────────────────────── */

    /**
     * @param  array $itens  [{product_id, product_name?, product_code?, quantity, unit_cost?}]
     * @return array{referencia_origem: string, referencia_destino: string, destino_nome: string, resumo: array}
     *
     * @throws DomainException
     */
    public function entreEmpresas(int $deArmazem, int $paraTenant, int $paraArmazem, array $itens, ?string $notas, int $tenantId, User $utilizador): array
    {
        $itens = $this->linhasComProduto($itens);

        if (! $deArmazem) {
            throw new DomainException(__('Selecione o armazém de origem.'));
        }
        if (! $paraTenant) {
            throw new DomainException(__('Selecione a empresa destino.'));
        }
        if (! $paraArmazem) {
            throw new DomainException(__('Selecione o armazém destino.'));
        }
        if (empty($itens)) {
            throw new DomainException(__('Adicione pelo menos um produto.'));
        }
        if (trim((string) $notas) === '') {
            throw new DomainException(__('Informe o motivo da transferência.'));
        }

        // A empresa destino tem de ser DO UTILIZADOR.
        $destino = $utilizador->tenants()->where('tenants.id', $paraTenant)->where('tenants.id', '!=', $tenantId)->first();

        if (! $destino) {
            throw new DomainException(__('Empresa destino inválida ou sem acesso.'));
        }

        $armazemDe = Warehouse::where('tenant_id', $tenantId)->find($deArmazem);
        $armazemPara = Warehouse::withoutGlobalScope('tenant')->where('tenant_id', $destino->id)->find($paraArmazem);

        if (! $armazemDe || ! $armazemPara) {
            throw new DomainException(__('Armazém desconhecido.'));
        }

        // DUAS referências, uma por empresa.
        $refOrigem = null;
        $refDestino = null;

        StockMovement::comLoteReservado($tenantId, function (string $rOrigem) use ($destino, &$refOrigem, &$refDestino) {
            $refOrigem = $rOrigem;
            StockMovement::comLoteReservado($destino->id, function (string $rDestino) use (&$refDestino) {
                $refDestino = $rDestino;
            });
        });

        $batchId = time() . rand(1000, 9999);
        $nomeOrigem = Tenant::find($tenantId)?->name ?? '';
        $resumo = [];

        DB::transaction(function () use ($itens, $destino, $armazemDe, $armazemPara, $notas, $tenantId, $utilizador, $batchId, $nomeOrigem, $refOrigem, $refDestino, &$resumo) {
            foreach ($itens as $item) {
                $produtoOrigem = Product::find($item['product_id']);

                if (! $produtoOrigem) {
                    throw new DomainException(__('Produto não encontrado: :nome', ['nome' => $item['product_name'] ?? $item['product_id']]));
                }

                $qtd = (float) $item['quantity'];
                if ($qtd <= 0) {
                    throw new DomainException(__('A quantidade deve ser maior que zero.'));
                }

                $custo = (float) ($item['unit_cost'] ?? 0);

                // 1. O artigo na empresa destino — o mesmo, ou uma cópia.
                $produtoDestino = $this->artigoNaEmpresa($produtoOrigem, $destino->id);

                // 2. Sai da origem, guardando os saldos.
                $origemAntes = (float) Stock::where('tenant_id', $tenantId)->where('warehouse_id', $armazemDe->id)->where('product_id', $produtoOrigem->id)->value('quantity');

                if ($origemAntes < $qtd) {
                    throw new DomainException(__('Stock insuficiente para :artigo', ['artigo' => $produtoOrigem->name]));
                }

                Stock::removeStock($armazemDe->id, $produtoOrigem->id, $qtd);
                $origemDepois = $origemAntes - $qtd;

                // 3. Entra no destino (com o product_id do destino).
                $stockDestino = Stock::withoutGlobalScope('tenant')
                    ->where('tenant_id', $destino->id)->where('warehouse_id', $armazemPara->id)->where('product_id', $produtoDestino->id)
                    ->first();

                $destinoAntes = (float) ($stockDestino->quantity ?? 0);

                if ($stockDestino) {
                    if ($custo > 0) {
                        $totalCusto = ((float) $stockDestino->quantity * (float) $stockDestino->unit_cost) + ($qtd * $custo);
                        $totalQtd = (float) $stockDestino->quantity + $qtd;
                        $stockDestino->unit_cost = $totalQtd > 0 ? $totalCusto / $totalQtd : $custo;
                    }
                    $stockDestino->quantity = (float) $stockDestino->quantity + $qtd;
                    $stockDestino->save();
                } else {
                    // save() e NÃO saveQuietly(): é o observer que mantém o agregado do artigo no destino.
                    $novo = new Stock();
                    $novo->tenant_id = $destino->id;
                    $novo->warehouse_id = $armazemPara->id;
                    $novo->product_id = $produtoDestino->id;
                    $novo->quantity = $qtd;
                    $novo->reserved_quantity = 0;
                    $novo->unit_cost = $custo;
                    $novo->save();
                }

                $destinoDepois = $destinoAntes + $qtd;

                // 4. Os lotes seguem a mercadoria.
                if ($produtoOrigem->track_batches) {
                    $this->lotesEntreEmpresas($produtoOrigem->id, $produtoDestino->id, $armazemDe->id, $armazemPara->id, $destino->id, $qtd, $tenantId);
                }

                // 5. As duas pernas, com os saldos explícitos.
                StockMovement::semAplicarStock(function () use ($item, $destino, $custo, $batchId, $produtoOrigem, $produtoDestino, $nomeOrigem, $refOrigem, $refDestino, $origemAntes, $origemDepois, $destinoAntes, $destinoDepois, $armazemDe, $armazemPara, $notas, $tenantId, $utilizador, $qtd) {
                    StockMovement::create([
                        'tenant_id' => $tenantId, 'warehouse_id' => $armazemDe->id, 'product_id' => $produtoOrigem->id,
                        'type' => 'transfer', 'quantity' => -$qtd,
                        'balance_before' => $origemAntes, 'balance_after' => $origemDepois,
                        'batch_reference' => $refOrigem, 'from_warehouse_id' => $armazemDe->id, 'to_warehouse_id' => $armazemPara->id,
                        'unit_cost' => $custo, 'reference_type' => 'inter_company', 'reference_id' => $batchId,
                        'user_id' => $utilizador->id,
                        'notes' => $notas . ' (Transferido para: ' . $destino->name . ')',
                    ]);

                    StockMovement::create([
                        'tenant_id' => $destino->id, 'warehouse_id' => $armazemPara->id, 'product_id' => $produtoDestino->id,
                        'type' => 'in', 'quantity' => $qtd,
                        'balance_before' => $destinoAntes, 'balance_after' => $destinoDepois,
                        'batch_reference' => $refDestino, 'to_warehouse_id' => $armazemPara->id, 'from_warehouse_id' => $armazemDe->id,
                        'unit_cost' => $custo, 'reference_type' => 'inter_company', 'reference_id' => $batchId,
                        'user_id' => $utilizador->id,
                        'notes' => $notas . ' (Recebido de: ' . $nomeOrigem . ')',
                    ]);
                });

                $resumo[] = [
                    'produto' => $item['product_name'] ?? $produtoOrigem->name,
                    'codigo' => $item['product_code'] ?? $produtoOrigem->code,
                    'quantidade' => $qtd,
                    'origem_antes' => $origemAntes,
                    'origem_depois' => $origemDepois,
                    'destino_antes' => $destinoAntes,
                    'destino_depois' => $destinoDepois,
                ];
            }
        });

        return [
            'referencia_origem' => $refOrigem,
            'referencia_destino' => $refDestino,
            'destino_nome' => $destino->name,
            'resumo' => $resumo,
        ];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** Deita fora as linhas sem produto — não há nada a aproveitar nelas. */
    private function linhasComProduto(array $itens): array
    {
        return array_values(array_filter($itens, fn ($i) => is_array($i) && ! empty($i['product_id'])));
    }

    /** FEFO: o que expira primeiro sai primeiro, e nasce no destino com o mesmo número. */
    private function lotesEntreArmazens(int $produtoId, int $de, int $para, float $quantidade, int $tenantId): void
    {
        $resta = $quantidade;

        $lotes = ProductBatch::where('tenant_id', $tenantId)->where('product_id', $produtoId)->where('warehouse_id', $de)
            ->where('quantity_available', '>', 0)->orderBy('expiry_date')->orderBy('created_at')->get();

        foreach ($lotes as $lote) {
            if ($resta <= 0) {
                break;
            }

            $tira = min($resta, (float) $lote->quantity_available);
            $lote->quantity_available -= $tira;
            // O save() é obrigatório: o updateStatus() escreve só o estado, por
            // consulta, e o disponível alterado em memória ficava por gravar —
            // o lote de origem nunca descia.
            $lote->save();
            $lote->updateStatus();

            $noDestino = ProductBatch::where('tenant_id', $tenantId)->where('product_id', $produtoId)->where('warehouse_id', $para)
                ->where('batch_number', $lote->batch_number)->first();

            if ($noDestino) {
                $noDestino->quantity += $tira;
                $noDestino->quantity_available += $tira;
                $noDestino->updateStatus();
            } else {
                ProductBatch::create([
                    'tenant_id' => $tenantId, 'product_id' => $produtoId, 'warehouse_id' => $para,
                    'batch_number' => $lote->batch_number, 'manufacturing_date' => $lote->manufacturing_date, 'expiry_date' => $lote->expiry_date,
                    'quantity' => $tira, 'quantity_available' => $tira, 'cost_price' => $lote->cost_price, 'alert_days' => $lote->alert_days,
                    'status' => 'active', 'notes' => 'Transferido de armazém',
                ]);
            }

            $resta -= $tira;
        }
    }

    private function lotesEntreEmpresas(int $produtoOrigemId, int $produtoDestinoId, int $de, int $para, int $tenantDestino, float $quantidade, int $tenantId): void
    {
        $resta = $quantidade;

        $lotes = ProductBatch::where('tenant_id', $tenantId)->where('product_id', $produtoOrigemId)->where('warehouse_id', $de)
            ->where('quantity_available', '>', 0)->orderBy('expiry_date')->orderBy('created_at')->get();

        foreach ($lotes as $lote) {
            if ($resta <= 0) {
                break;
            }

            $tira = min($resta, (float) $lote->quantity_available);
            $lote->quantity_available -= $tira;
            // O save() é obrigatório: o updateStatus() escreve só o estado, por
            // consulta, e o disponível alterado em memória ficava por gravar —
            // o lote de origem nunca descia.
            $lote->save();
            $lote->updateStatus();

            $noDestino = ProductBatch::withoutGlobalScopes()->where('tenant_id', $tenantDestino)->where('product_id', $produtoDestinoId)
                ->where('warehouse_id', $para)->where('batch_number', $lote->batch_number)->first();

            if ($noDestino) {
                $noDestino->quantity += $tira;
                $noDestino->quantity_available += $tira;
                $noDestino->updateStatus();
            } else {
                $novo = new ProductBatch();
                $novo->tenant_id = $tenantDestino;
                $novo->product_id = $produtoDestinoId;
                $novo->warehouse_id = $para;
                $novo->batch_number = $lote->batch_number;
                $novo->manufacturing_date = $lote->manufacturing_date;
                $novo->expiry_date = $lote->expiry_date;
                $novo->quantity = $tira;
                $novo->quantity_available = $tira;
                $novo->cost_price = $lote->cost_price;
                $novo->alert_days = $lote->alert_days;
                $novo->status = 'active';
                $novo->notes = 'Transferência inter-empresas';
                $novo->save();
            }

            $resta -= $tira;
        }
    }

    /** O artigo na empresa destino: o que já lá exista com o mesmo nome, SKU ou código de barras, ou uma cópia. */
    private function artigoNaEmpresa(Product $origem, int $tenantId): Product
    {
        $existente = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($origem) {
                $q->where('name', $origem->name);
                if ($origem->sku) {
                    $q->orWhere('sku', $origem->sku);
                }
                if ($origem->barcode) {
                    $q->orWhere('barcode', $origem->barcode);
                }
            })
            ->first();

        if ($existente) {
            return $existente;
        }

        $novo = new Product();
        $novo->tenant_id = $tenantId;
        $novo->name = $origem->name;
        $novo->description = $origem->description;
        $novo->type = $origem->type ?? 'produto';
        $novo->sku = $origem->sku;
        $novo->barcode = $origem->barcode;
        $novo->price = $origem->price;
        $novo->cost = $origem->cost;
        $novo->tax_type = $origem->tax_type;
        $novo->tax_rate_id = $origem->tax_rate_id;
        $novo->exemption_reason = $origem->exemption_reason;
        $novo->unit = $origem->unit;
        $novo->manage_stock = $origem->manage_stock;
        $novo->track_batches = $origem->track_batches;
        $novo->track_expiry = $origem->track_expiry;
        $novo->is_active = true;
        $novo->featured_image = $origem->featured_image;
        $novo->save();

        return $novo;
    }
}
