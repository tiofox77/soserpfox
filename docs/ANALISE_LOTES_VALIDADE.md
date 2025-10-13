# 📦 ANÁLISE COMPLETA: SISTEMA DE LOTES E VALIDADE

## ✅ O QUE JÁ ESTÁ IMPLEMENTADO

### **1. Database (Tables & Migrations)**
✅ **Tabela `invoicing_product_batches`** - Criada
- Campos: batch_number, manufacturing_date, expiry_date, quantity, quantity_available, cost_price, status, alert_days
- Relacionamentos: product_id, warehouse_id, purchase_invoice_id

✅ **Tabela `invoicing_purchase_invoice_items`** - Com campos de lote
- Campos: batch_number, manufacturing_date, expiry_date, alert_days

✅ **Tabela `batch_allocations`** - Para controle de consumo FIFO

---

### **2. Models**
✅ **ProductBatch Model** - Completo
- Scopes: `active()`, `expiringSoon()`, `expired()`
- Accessors: `days_until_expiry`, `is_expired`, `is_expiring_soon`, `status_color`, `status_label`
- Métodos: `updateStatus()`, `decreaseQuantity()`, `increaseQuantity()`

✅ **BatchAllocation Model** - Para rastreamento de vendas

---

### **3. Componentes Livewire**

✅ **ProductBatches.php** - Gestão de Lotes
- CRUD completo de lotes
- Filtros: produto, armazém, status
- Stats: lotes ativos, expirando, expirados
- **LOCALIZAÇÃO:** `/faturacao/lotes`

✅ **Purchases/InvoiceCreate.php** - Fatura de Compras
- Método `updateBatchData()` - Atualiza dados de lote no carrinho
- Salva: batch_number, manufacturing_date, expiry_date, alert_days
- ✅ **JÁ IMPLEMENTADO!**

✅ **Purchases/ProformaCreate.php** - Proforma de Compras
- (Precisa verificar se também tem suporte a lotes)

---

## ❌ O QUE FALTA IMPLEMENTAR

### **1. Model Product**
❌ **Campos na tabela `invoicing_products`:**
- `track_batches` (boolean) - Indica se produto rastreia lotes
- `track_expiry` (boolean) - Indica se produto rastreia validade
- `require_batch_on_purchase` (boolean) - Obriga lote na compra
- `require_batch_on_sale` (boolean) - Obriga lote na venda

❌ **Migration necessária:**
```php
$table->boolean('track_batches')->default(false);
$table->boolean('track_expiry')->default(false);
$table->boolean('require_batch_on_purchase')->default(false);
$table->boolean('require_batch_on_sale')->default(false);
```

---

### **2. Ficha de Produto**
❌ **Products.php - Modal de criação/edição**
- Adicionar seção "Controle de Lotes e Validade"
- Checkboxes:
  - [ ] Rastrear por lotes
  - [ ] Controlar validade
  - [ ] Exigir lote na compra
  - [ ] Exigir lote na venda

---

### **3. Proforma de Compras**
❌ **ProformaCreate.php**
- Verificar se tem método `updateBatchData()`
- Se não tiver, implementar igual ao InvoiceCreate

❌ **View da Proforma**
- Modal/seção para informar lote ao adicionar produto
- Campos: Nº Lote, Data Fabricação, Data Validade, Dias Alerta

---

### **4. Fatura de Compras - Observer**
❌ **PurchaseInvoiceObserver ou lógica de finalização**
- Ao finalizar/aprovar fatura de compra:
  1. Verificar se produto tem `track_batches = true`
  2. Se sim, criar/atualizar registro em `ProductBatch`
  3. Atualizar `quantity_available`
  4. Atualizar stock do produto

❌ **Criar método `createOrUpdateBatch()` em PurchaseInvoiceObserver:**
```php
foreach ($invoice->items as $item) {
    if ($item->product->track_batches && $item->batch_number) {
        ProductBatch::updateOrCreate([
            'tenant_id' => $invoice->tenant_id,
            'product_id' => $item->product_id,
            'batch_number' => $item->batch_number,
        ], [
            'warehouse_id' => $invoice->warehouse_id,
            'manufacturing_date' => $item->manufacturing_date,
            'expiry_date' => $item->expiry_date,
            'quantity' => DB::raw('quantity + ' . $item->quantity),
            'quantity_available' => DB::raw('quantity_available + ' . $item->quantity),
            'cost_price' => $item->unit_price,
            'alert_days' => $item->alert_days,
            'purchase_invoice_id' => $invoice->id,
            'supplier_name' => $invoice->supplier->name,
        ]);
    }
}
```

---

### **5. Vendas - Consumo FIFO**
❌ **SalesInvoiceObserver - Lógica FIFO**
- Ao vender produto com lotes:
  1. Buscar lotes disponíveis (FIFO: mais antigo primeiro)
  2. Consumir quantidade dos lotes
  3. Registrar em `batch_allocations`
  4. Atualizar `quantity_available` dos lotes

❌ **Método `allocateBatches()` necessário:**
```php
protected function allocateBatches($saleItem, $quantityNeeded)
{
    $batches = ProductBatch::where('product_id', $saleItem->product_id)
        ->where('tenant_id', $saleItem->tenant_id)
        ->where('warehouse_id', $saleItem->warehouse_id)
        ->active()
        ->orderBy('expiry_date', 'asc') // FIFO
        ->get();
    
    $remaining = $quantityNeeded;
    
    foreach ($batches as $batch) {
        if ($remaining <= 0) break;
        
        $toAllocate = min($remaining, $batch->quantity_available);
        
        // Criar registro de alocação
        BatchAllocation::create([
            'tenant_id' => $saleItem->tenant_id,
            'product_batch_id' => $batch->id,
            'sales_invoice_id' => $saleItem->invoice_id,
            'sales_invoice_item_id' => $saleItem->id,
            'quantity' => $toAllocate,
        ]);
        
        // Reduzir disponibilidade
        $batch->decreaseQuantity($toAllocate);
        
        $remaining -= $toAllocate;
    }
    
    if ($remaining > 0) {
        throw new \Exception("Quantidade insuficiente em lote para o produto {$saleItem->product->name}");
    }
}
```

---

### **6. POS (Point of Sale)**
❌ **Verificar se POS suporta produtos com lotes**
- Se produto tem `require_batch_on_sale = true`:
  - Mostrar lotes disponíveis
  - Permitir selecionar lote específico
  - Aplicar FIFO automaticamente

---

### **7. Gestão de Stock**
❌ **StockManagement.php**
- Ao exibir stock, mostrar:
  - Total geral
  - Detalhamento por lote
  - Lotes expirando
  - Lotes expirados

❌ **Dashboard de Stock**
- Card: "Lotes Expirando" (próximos 30 dias)
- Card: "Lotes Expirados"
- Card: "Produtos com Stock Crítico por Lote"

---

### **8. Validações e Regras**
❌ **Validação ao adicionar produto em venda:**
```php
if ($product->track_batches && $product->require_batch_on_sale) {
    $availableBatches = ProductBatch::where('product_id', $product->id)
        ->active()
        ->sum('quantity_available');
    
    if ($availableBatches < $requestedQuantity) {
        throw new \Exception("Quantidade insuficiente em lotes válidos");
    }
}
```

❌ **Alertas automáticos:**
- Command `php artisan batches:check-expiry`
- Notificar lotes expirando em X dias
- Enviar email/notificação para gestores

---

### **9. Relatórios**
❌ **Relatório de Lotes:**
- Lotes por produto
- Lotes por validade
- Lotes consumidos (histórico)
- Perdas por vencimento

❌ **Rastreabilidade:**
- Ver de qual lote saiu cada venda
- Ver histórico completo de um lote

---

## 📋 CHECKLIST DE IMPLEMENTAÇÃO

### **FASE 1: Preparação (Database)**
- [ ] Criar migration `add_batch_tracking_fields_to_products_table`
- [ ] Adicionar campos no Model Product
- [ ] Executar migration

### **FASE 2: Ficha de Produto**
- [ ] Adicionar campos no formulário Products.php
- [ ] Adicionar seção na view do modal
- [ ] Testar criação de produto com lotes ativado

### **FASE 3: Compras**
- [ ] Verificar ProformaCreate.php
- [ ] Implementar observer para criar ProductBatch ao finalizar compra
- [ ] Testar fluxo completo de compra com lote

### **FASE 4: Vendas (FIFO)**
- [ ] Implementar `allocateBatches()` em SalesInvoiceObserver
- [ ] Criar BatchAllocation ao vender
- [ ] Testar consumo FIFO

### **FASE 5: POS**
- [ ] Adaptar POS para suportar lotes
- [ ] Permitir seleção de lote (se necessário)
- [ ] Aplicar FIFO automaticamente

### **FASE 6: Gestão e Relatórios**
- [ ] Dashboard com alertas de vencimento
- [ ] Relatórios de lotes
- [ ] Command para verificar validade

---

## 🎯 PRIORIDADES

### **CRÍTICO (Implementar AGORA):**
1. ✅ Campos na tabela products (track_batches, etc)
2. ✅ Observer para criar ProductBatch ao finalizar compra
3. ✅ FIFO ao vender (SalesInvoiceObserver)

### **IMPORTANTE (Implementar em seguida):**
4. Ficha de produto com checkboxes
5. Validações ao vender produto com lote
6. Dashboard com alertas

### **DESEJÁVEL (Implementar depois):**
7. POS com suporte a lotes
8. Relatórios avançados
9. Command de verificação automática

---

## 🔍 RESUMO DA SITUAÇÃO ATUAL

| Área | Status | Observações |
|------|--------|-------------|
| **Database** | ✅ 80% | Faltam campos no products |
| **Models** | ✅ 100% | ProductBatch completo |
| **Gestão Manual de Lotes** | ✅ 100% | Interface pronta |
| **Compras - Dados Lote** | ✅ 100% | InvoiceCreate pronto |
| **Compras - Criar Batch** | ❌ 0% | Falta Observer |
| **Vendas - FIFO** | ❌ 0% | Falta implementar |
| **POS** | ❌ 0% | Não suporta lotes |
| **Relatórios** | ❌ 0% | Não implementado |
| **Alertas** | ❌ 0% | Não implementado |

---

## 💡 CONCLUSÃO

**O SISTEMA TEM UMA BOA BASE:**
- ✅ Tabelas criadas
- ✅ Models funcionais
- ✅ Interface de gestão manual
- ✅ Compras capturam dados de lote

**FALTA A LÓGICA AUTOMÁTICA:**
- ❌ Observer para criar ProductBatch automaticamente
- ❌ FIFO ao vender
- ❌ Validações e alertas
- ❌ Integração com POS

**PRÓXIMO PASSO RECOMENDADO:**
Implementar o Observer de Compras para que ao finalizar uma fatura de compra, crie/atualize automaticamente os lotes.
