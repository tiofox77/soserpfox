# 🔗 Integrações Entre Módulos - SOS ERP

## 📋 Visão Geral

O SOS ERP é um sistema **modular e integrado**. Embora os módulos sejam separados, **certas áreas devem estar sempre interligadas** para manter a consistência dos dados e automatizar processos.

---

## 🔄 Integrações Críticas (Sempre Ativas)

### **1. Faturação ↔️ Treasury (Tesouraria)** ✅ IMPLEMENTADO

#### **Direção: Faturação → Treasury**

**Quando:** Venda POS ou Fatura de Venda é paga

**O que acontece:**
```
Fatura Criada/Paga
    ↓
Cria automaticamente Transação no Treasury
    ├─ Tipo: Entrada (income)
    ├─ Valor: Total da fatura
    ├─ Método: Método de pagamento usado
    ├─ Referência: Número da fatura
    └─ Vinculada: invoice_id
```

**Arquivos envolvidos:**
- `app/Livewire/POS/POSSystem.php` - Método `createTreasuryTransaction()`
- `app/Models/Treasury/Transaction.php` - Relacionamento `salesInvoice()`

#### **Direção: Treasury → Faturação**

**Quando:** Transação do Treasury é creditada (estornada)

**O que acontece:**
```
Creditar Transação com Fatura Associada
    ↓
Cria automaticamente Nota de Crédito
    ├─ Vinculada à fatura original
    ├─ Copia todos os itens
    ├─ Status: Emitida
    └─ Atualiza status da fatura para 'credited'
```

**Arquivos envolvidos:**
- `app/Livewire/Treasury/Transactions.php` - Método `createCreditNoteFromTransaction()`
- `app/Models/Invoicing/CreditNote.php` - Método `updateInvoiceBalance()`

---

### **2. Faturação ↔️ Inventário/Stock** ⚠️ A IMPLEMENTAR

#### **Direção: Faturação → Inventário**

**Quando:** Fatura de Venda é emitida

**O que deve acontecer:**
```
Fatura Emitida
    ↓
Reduz automaticamente Stock
    ├─ Por cada item da fatura
    ├─ Quantidade vendida
    ├─ Warehouse de origem
    └─ Movimento de stock registrado
```

**Arquivos a modificar:**
- `app/Livewire/Invoicing/Sales/InvoiceCreate.php`
- `app/Models/Invoicing/Stock.php`
- `app/Models/Invoicing/StockMovement.php`

#### **Direção: Faturação → Inventário (NC)**

**Quando:** Nota de Crédito é emitida

**O que deve acontecer:**
```
NC Emitida (devolução)
    ↓
Aumenta automaticamente Stock
    ├─ Devolve itens ao warehouse
    ├─ Movimento de stock registrado
    └─ Tipo: Devolução de venda
```

---

### **3. Compras ↔️ Treasury** ⚠️ A IMPLEMENTAR

#### **Direção: Compras → Treasury**

**Quando:** Fatura de Compra é paga

**O que deve acontecer:**
```
Fatura de Compra Paga
    ↓
Cria automaticamente Transação no Treasury
    ├─ Tipo: Saída (expense)
    ├─ Valor: Total da fatura
    ├─ Método: Método de pagamento usado
    ├─ Referência: Número da fatura
    └─ Vinculada: purchase_invoice_id
```

---

### **4. Compras ↔️ Inventário** ⚠️ A IMPLEMENTAR

#### **Direção: Compras → Inventário**

**Quando:** Fatura de Compra é recebida

**O que deve acontecer:**
```
Fatura de Compra Recebida
    ↓
Aumenta automaticamente Stock
    ├─ Por cada item da fatura
    ├─ Quantidade comprada
    ├─ Warehouse de destino
    ├─ Atualiza preço de custo
    └─ Movimento de stock registrado
```

---

### **5. POS ↔️ Faturação ↔️ Treasury** ✅ IMPLEMENTADO

**Fluxo completo:**
```
POS: Venda
    ↓
Faturação: Cria Fatura (FR)
    ├─ Série: FR A 2025/000001
    ├─ Status: paid
    └─ Itens com IVA
    ↓
Treasury: Cria Transação
    ├─ Entrada
    ├─ Vinculada à fatura
    └─ Atualiza saldo
```

**Se houver estorno:**
```
Treasury: Creditar Transação
    ↓
Faturação: Cria NC automaticamente
    ├─ NC/2025/0001
    ├─ Vinculada à fatura
    └─ Status fatura: credited
    ↓
Treasury: Transação de estorno
    └─ Saldo atualizado
```

---

## 📊 Matriz de Integrações

| Origem | Destino | Ação | Status |
|--------|---------|------|--------|
| POS | Faturação | Criar Fatura | ✅ Implementado |
| Faturação (Venda) | Treasury | Criar Transação | ✅ Implementado |
| Treasury | Faturação | Criar NC | ✅ Implementado |
| Faturação (NC) | Treasury | Reverter Transação | ✅ Automático |
| Faturação (Venda) | Inventário | Reduzir Stock | ⚠️ A Implementar |
| Faturação (NC) | Inventário | Devolver Stock | ⚠️ A Implementar |
| Compras | Inventário | Aumentar Stock | ⚠️ A Implementar |
| Compras | Treasury | Criar Transação Saída | ⚠️ A Implementar |
| Inventário | Contabilidade | Movimentos | ⚠️ Futuro |

---

## 🔧 Princípios de Integração

### **1. Automação Total**
- ✅ Integrações devem ser **automáticas**
- ✅ Usuário não precisa criar manualmente em cada módulo
- ✅ Sistema cuida da propagação de dados

### **2. Transações Atômicas**
```php
DB::beginTransaction();
try {
    // 1. Criar na origem
    $invoice = SalesInvoice::create([...]);
    
    // 2. Propagar para destino
    $transaction = Transaction::create([...]);
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Tudo ou nada
}
```

### **3. Rastreabilidade**
- ✅ Vincular registros entre módulos
- ✅ `invoice_id`, `purchase_invoice_id`, etc.
- ✅ Histórico completo de ações

### **4. Reversibilidade**
- ✅ Toda ação deve ser reversível
- ✅ NC reverte fatura
- ✅ Estorno reverte transação
- ✅ Devolução reverte stock

---

## 📝 Checklist de Nova Integração

Ao criar uma nova integração entre módulos, verificar:

- [ ] **Evento claro** - Quando a integração deve acontecer?
- [ ] **Transação DB** - Usar `DB::beginTransaction()`
- [ ] **Validação** - Verificar pré-condições
- [ ] **Criação automática** - Criar registros vinculados
- [ ] **Vinculação** - Foreign keys para rastreamento
- [ ] **Atualização de status** - Manter estados sincronizados
- [ ] **Reversão** - Permitir desfazer a ação
- [ ] **Log** - Registrar para auditoria
- [ ] **Notificação** - Informar usuário do que aconteceu
- [ ] **Testes** - Verificar cenários de sucesso e erro

---

## 🎯 Exemplo Prático: Nova Integração

### **Cenário: Fatura de Venda → Inventário**

```php
// Em SalesInvoiceCreate.php

public function save()
{
    DB::beginTransaction();
    
    try {
        // 1. Criar fatura
        $invoice = SalesInvoice::create([...]);
        
        // 2. Criar itens da fatura
        foreach ($this->items as $item) {
            SalesInvoiceItem::create([...]);
        }
        
        // 3. INTEGRAÇÃO: Reduzir stock automaticamente
        $this->updateInventory($invoice);
        
        // 4. INTEGRAÇÃO: Criar transação Treasury (se paga)
        if ($invoice->status === 'paid') {
            $this->createTreasuryTransaction($invoice);
        }
        
        DB::commit();
        
        $this->dispatch('success', [
            'message' => 'Fatura criada! Stock atualizado. Transação registrada.'
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        $this->dispatch('error', ['message' => $e->getMessage()]);
    }
}

private function updateInventory($invoice)
{
    foreach ($invoice->items as $item) {
        // Reduzir stock
        Stock::where('product_id', $item->product_id)
            ->where('warehouse_id', $invoice->warehouse_id)
            ->decrement('quantity', $item->quantity);
        
        // Registrar movimento
        StockMovement::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $invoice->warehouse_id,
            'type' => 'out',
            'quantity' => $item->quantity,
            'reference' => $invoice->invoice_number,
            'reference_type' => 'sales_invoice',
            'reference_id' => $invoice->id,
        ]);
    }
}
```

---

## 🚨 Avisos Importantes

### **1. Nunca Quebrar Integrações**
- ❌ Não deletar campos de vinculação (invoice_id, etc.)
- ❌ Não remover métodos de integração
- ❌ Não desabilitar propagação automática

### **2. Sempre Usar Transações DB**
```php
// ✅ CORRETO
DB::beginTransaction();
try {
    // Múltiplas operações
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
}

// ❌ ERRADO
// Operações sem transação
// Risco de inconsistência
```

### **3. Testar Cenários de Erro**
- ✅ E se a fatura for criada mas o stock falhar?
- ✅ E se a transação do Treasury falhar?
- ✅ Sistema deve reverter tudo (rollback)

---

## 📚 Arquivos Chave

### **Modelos com Relacionamentos:**
```
app/Models/
├── Invoicing/
│   ├── SalesInvoice.php      → treasuryTransactions()
│   ├── CreditNote.php         → invoice(), updateInvoiceBalance()
│   └── Stock.php              → movements()
└── Treasury/
    └── Transaction.php        → salesInvoice(), purchaseInvoice()
```

### **Livewire com Integrações:**
```
app/Livewire/
├── POS/
│   └── POSSystem.php          → createTreasuryTransaction()
├── Treasury/
│   └── Transactions.php       → createCreditNoteFromTransaction()
└── Invoicing/
    └── Sales/
        └── InvoiceCreate.php  → [A adicionar] updateInventory()
```

---

## 🔄 Próximas Integrações a Implementar

1. **Faturação Venda → Inventário** (Alta Prioridade)
2. **Compras → Treasury** (Alta Prioridade)
3. **Compras → Inventário** (Média Prioridade)
4. **NC → Inventário** (Média Prioridade)
5. **Recibos → Treasury** (Baixa Prioridade)

---

## ✅ Integrações Implementadas

- ✅ **POS → Faturação** (FR - Fatura-Recibo)
- ✅ **Faturação → Treasury** (Transação de entrada)
- ✅ **Treasury → Faturação** (NC automática ao creditar)
- ✅ **NC → Fatura** (Atualização de status para 'credited')

---

**SOS ERP - Sistema Modular Integrado**
Versão 1.0 - 2025

**Princípio:** Módulos separados, dados integrados, processos automatizados.
