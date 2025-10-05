# 📜 Regras de Integração Entre Módulos

## 🎯 Objetivo

Este documento define as **regras obrigatórias** que devem ser seguidas ao trabalhar com integrações entre módulos no SOS ERP.

---

## ⚠️ Regras Críticas (NUNCA QUEBRAR)

### **1. Toda Fatura Paga DEVE Criar Transação no Treasury**

```php
// ✅ CORRETO
if ($invoice->status === 'paid') {
    $this->createTreasuryTransaction($invoice);
}

// ❌ ERRADO
// Criar fatura paga sem transação no Treasury
```

**Por quê?**
- Treasury precisa saber todas as entradas/saídas
- Saldo do caixa depende disso
- Relatórios financeiros ficam errados

---

### **2. Todo Crédito de Transação com Fatura DEVE Criar NC**

```php
// ✅ CORRETO
if ($transaction->invoice_id) {
    $this->createCreditNoteFromTransaction($transaction);
}

// ❌ ERRADO
// Creditar transação com fatura sem criar NC
```

**Por quê?**
- Faturação precisa refletir o crédito
- Documentação fiscal (SAFT-AO)
- Cliente precisa ter documento do crédito

---

### **3. Toda NC Emitida DEVE Atualizar Status da Fatura**

```php
// ✅ CORRETO
if ($creditNote->total >= $invoice->total) {
    $invoice->status = 'credited';
    $invoice->save();
}

// ❌ ERRADO
// Emitir NC sem atualizar fatura
```

**Por quê?**
- Fatura não pode ficar como "paga" se foi creditada
- Relatórios de vendas ficam errados
- Cliente pode cobrar duas vezes

---

### **4. SEMPRE Usar Transações DB para Integrações**

```php
// ✅ CORRETO
DB::beginTransaction();
try {
    $invoice = SalesInvoice::create([...]);
    $transaction = Transaction::create([...]);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// ❌ ERRADO
$invoice = SalesInvoice::create([...]);
$transaction = Transaction::create([...]); // Sem transação
```

**Por quê?**
- Se uma parte falhar, a outra deve ser revertida
- Evita dados inconsistentes
- Integridade referencial

---

### **5. SEMPRE Vincular Registros Entre Módulos**

```php
// ✅ CORRETO
Transaction::create([
    'invoice_id' => $invoice->id, // Vinculado!
    'amount' => $invoice->total,
    // ...
]);

// ❌ ERRADO
Transaction::create([
    'amount' => $invoice->total,
    // Sem invoice_id - não vinculado
]);
```

**Por quê?**
- Rastreabilidade
- Auditoria
- Poder reverter ações

---

## 📋 Checklist Obrigatório

Ao criar ou modificar uma integração, verificar:

### **Antes de Commit:**

- [ ] Usa `DB::beginTransaction()` e `DB::commit()`?
- [ ] Tem `try/catch` com `DB::rollBack()`?
- [ ] Vincula registros com foreign keys?
- [ ] Atualiza status corretamente?
- [ ] Testa cenário de erro (rollback funciona)?
- [ ] Notifica usuário do resultado?
- [ ] Log/Auditoria implementado?
- [ ] Documentação atualizada?

### **Campos Obrigatórios em Transações Treasury:**

```php
Transaction::create([
    'tenant_id' => required,      // ✅ Obrigatório
    'user_id' => required,        // ✅ Obrigatório
    'type' => required,           // ✅ income/expense
    'amount' => required,         // ✅ Valor
    'transaction_date' => required, // ✅ Data
    'status' => required,         // ✅ Status
    
    // VINCULAÇÃO (pelo menos um se houver)
    'invoice_id' => nullable,     // 🔗 Fatura de Venda
    'purchase_invoice_id' => nullable, // 🔗 Fatura de Compra
    
    // RECOMENDADOS
    'payment_method_id' => recommended,
    'account_id' => recommended,
    'description' => recommended,
    'reference' => recommended,
]);
```

---

## 🚫 Antipadrões (NUNCA FAZER)

### **1. Criar Fatura Paga Manualmente sem Treasury**

```php
// ❌ PROIBIDO
$invoice = SalesInvoice::create([
    'status' => 'paid',
    // ... mas não cria transação no Treasury
]);
```

**Consequência:** Saldo do caixa errado, relatórios incorretos.

---

### **2. Deletar Fatura sem Deletar Transação**

```php
// ❌ PROIBIDO
$invoice->delete();
// Transação do Treasury fica órfã
```

**Solução:** Usar `onDelete('set null')` ou soft deletes.

---

### **3. Creditar Transação com Fatura sem Criar NC**

```php
// ❌ PROIBIDO
$creditTransaction = Transaction::create([
    'type' => 'expense',
    'amount' => -$originalTransaction->amount,
]);
// Mas não cria NC na Faturação
```

**Consequência:** Cliente sem documento fiscal do crédito.

---

### **4. Múltiplas Operações sem Transação DB**

```php
// ❌ PROIBIDO
$invoice = SalesInvoice::create([...]);
$item = SalesInvoiceItem::create([...]);
$transaction = Transaction::create([...]);
// Se a 3ª falhar, as 2 primeiras ficam órfãs
```

**Solução:** Sempre usar `DB::beginTransaction()`.

---

## 🔄 Fluxos Obrigatórios

### **Fluxo 1: Venda POS**

```
1. POS → Criar Fatura (FR)
   ├─ Status: paid
   └─ Itens com valores corretos

2. Fatura → Criar Transação Treasury
   ├─ Tipo: income
   ├─ invoice_id: vinculado
   └─ Mesmo valor da fatura

3. Treasury → Atualizar saldo
   └─ Caixa/Conta incrementada

✅ TUDO EM UMA TRANSAÇÃO DB
```

### **Fluxo 2: Crédito/Estorno**

```
1. Treasury → Creditar Transação
   └─ Detecta invoice_id

2. Faturação → Criar NC
   ├─ Vinculada à fatura
   ├─ Copia itens
   └─ Status: issued

3. Fatura → Atualizar status
   └─ Status: credited

4. Treasury → Criar transação estorno
   └─ Tipo: expense

✅ TUDO EM UMA TRANSAÇÃO DB
```

---

## 📊 Validações Obrigatórias

### **Antes de Criar Transação:**

```php
// Validar que fatura existe
if (!$invoice) {
    throw new \Exception('Fatura não encontrada');
}

// Validar que não existe transação duplicada
$exists = Transaction::where('invoice_id', $invoice->id)->exists();
if ($exists) {
    throw new \Exception('Transação já existe para esta fatura');
}

// Validar valor
if ($invoice->total <= 0) {
    throw new \Exception('Valor inválido');
}
```

### **Antes de Criar NC:**

```php
// Validar que fatura existe
if (!$invoice) {
    throw new \Exception('Fatura não encontrada');
}

// Validar que fatura foi paga
if ($invoice->status !== 'paid') {
    throw new \Exception('Apenas faturas pagas podem ser creditadas');
}

// Validar que não existe NC total já
$existingNC = CreditNote::where('invoice_id', $invoice->id)
    ->where('type', 'total')
    ->exists();
if ($existingNC) {
    throw new \Exception('Fatura já foi creditada');
}
```

---

## 🎯 Testes Obrigatórios

Para cada integração, testar:

### **Cenário 1: Sucesso Total**
```php
// Tudo funciona
$invoice = create_invoice();
$transaction = get_transaction_for_invoice($invoice);
assert($transaction->exists());
assert($transaction->amount == $invoice->total);
```

### **Cenário 2: Erro no Meio**
```php
// Simular erro na 2ª operação
DB::beginTransaction();
$invoice = create_invoice(); // ✅ OK
mock_transaction_to_fail();
try {
    create_transaction(); // ❌ Falha
} catch (\Exception $e) {
    DB::rollBack();
}
assert(Invoice::find($invoice->id) === null); // Rollback funcionou
```

### **Cenário 3: Validação de Dados**
```php
// Tentar criar com dados inválidos
try {
    create_invoice(['total' => -100]); // Negativo
    assert(false); // Não deveria chegar aqui
} catch (\Exception $e) {
    assert(true); // Validação funcionou
}
```

---

## 📝 Documentação Obrigatória

Ao criar nova integração, documentar:

### **No Código:**

```php
/**
 * Criar transação no Treasury para fatura paga
 * 
 * INTEGRAÇÃO CRÍTICA: Faturação → Treasury
 * 
 * @param SalesInvoice $invoice Fatura paga
 * @return Transaction Transação criada
 * @throws \Exception Se já existir transação para esta fatura
 */
private function createTreasuryTransaction($invoice)
{
    // Validações
    // Criação
    // Vinculação
}
```

### **No MODULE-INTEGRATIONS.md:**

```markdown
## Nova Integração

### Módulo X → Módulo Y

**Quando:** [Evento que dispara]
**O que:** [O que é criado/atualizado]
**Arquivos:** [Lista de arquivos modificados]
**Validações:** [Checklist de validações]
```

---

## ⚡ Performance

### **Evitar N+1 Queries em Integrações:**

```php
// ❌ RUIM - N+1
foreach ($invoice->items as $item) {
    $product = Product::find($item->product_id); // Query por item
    create_stock_movement($product);
}

// ✅ BOM - 1 Query
$products = Product::whereIn('id', $invoice->items->pluck('product_id'))->get();
foreach ($invoice->items as $item) {
    $product = $products->find($item->product_id);
    create_stock_movement($product);
}
```

---

## 🔐 Segurança

### **Sempre Validar Tenant:**

```php
// ✅ CORRETO
Transaction::create([
    'tenant_id' => activeTenantId(), // Sempre validar!
    'invoice_id' => $invoice->id,
    // ...
]);

// ❌ PERIGO
Transaction::create([
    'invoice_id' => $invoice->id, // Sem tenant_id
]);
```

---

## 📞 Contato para Dúvidas

Antes de modificar integrações críticas:
1. Revisar este documento
2. Verificar MODULE-INTEGRATIONS.md
3. Testar em ambiente de desenvolvimento
4. Documentar mudanças

---

**REGRA DE OURO:**
> "Se não tem certeza se deve criar integração, pergunte.
> Se tem certeza, use transação DB e vincule tudo."

---

**SOS ERP - Integrações Críticas**
Última atualização: 05/10/2025
