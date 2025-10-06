# ✅ Gerador AGT - Lógica de Produção Implementada

## 🎯 Objetivo Alcançado

O gerador AGT agora usa **100% da lógica de produção real do sistema SOSERP**, incluindo:
- ✅ Séries de documentos (InvoicingSeries)
- ✅ Numeração sequencial automática
- ✅ Validação de recursos (clientes, produtos, armazéns)
- ✅ Inserção real no banco de dados
- ✅ Geração de hash SAFT-AO
- ✅ Logs detalhados do processo

---

## 🔧 Mudanças Implementadas

### **1. Sistema de Séries AGT**

**Antes:** Números aleatórios
```php
'FT AGT 2025/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT)
```

**Agora:** Séries reais do sistema
```php
$series = $this->getSeries('FT');
$invoiceNumber = $series->getNextNumber();
// Resultado: FT AGT 2025/000001, FT AGT 2025/000002, etc.
```

**Séries Criadas Automaticamente:**
- ✅ FT AGT - Faturas
- ✅ FS AGT - Faturas Simplificadas
- ✅ FR AGT - Faturas-Recibo
- ✅ NC AGT - Notas de Crédito
- ✅ ND AGT - Notas de Débito
- ✅ GT AGT - Guias de Transporte
- ✅ FP AGT - Faturas Proforma

**Configuração das Séries:**
```php
[
    'prefix' => 'FT',
    'series_code' => 'AGT',
    'include_year' => true,
    'next_number' => 1,
    'number_padding' => 6,
    'reset_yearly' => true,
    'current_year' => 2025
]
```

### **2. Validação de Recursos**

**Implementado:**
```php
// Cliente
$client = Client::where('tenant_id', activeTenantId())
    ->whereNotNull('nif')
    ->where('nif', '!=', '999999999')
    ->first();
    
if (!$client) {
    throw new \Exception('Cliente não encontrado');
}

// Produto
$product = Product::where('tenant_id', activeTenantId())
    ->where('type', 'product')
    ->first();
    
if (!$product) {
    throw new \Exception('Produto não encontrado');
}

// Armazém
$warehouse = Warehouse::where('tenant_id', activeTenantId())
    ->where('is_active', true)
    ->first();
    
if (!$warehouse) {
    throw new \Exception('Armazém não encontrado');
}

// Série
$series = $this->getSeries('FT');
if (!$series) {
    throw new \Exception('Série FT não encontrada');
}
```

### **3. Criação Real de Documentos**

**Faturas com série_id:**
```php
$invoice = SalesInvoice::create([
    'tenant_id' => activeTenantId(),
    'client_id' => $client->id,
    'warehouse_id' => $warehouse->id,
    'series_id' => $series->id,  // ✅ SÉRIE DO SISTEMA
    'invoice_number' => $invoiceNumber,  // ✅ NÚMERO SEQUENCIAL
    'invoice_type' => 'FT',
    'invoice_status' => 'N',
    'invoice_date' => now(),
    'due_date' => now()->addDays(30),
    'system_entry_date' => now(),
    'status' => 'paid',
    'subtotal' => 100.00,
    'net_total' => 100.00,
    'tax_amount' => 14.00,
    'tax_payable' => 14.00,
    'total' => 114.00,
    'gross_total' => 114.00,
    'created_by' => auth()->id(),
    'source_id' => auth()->user()->name,
    'source_billing' => 'SOSERP/1.0',
    'hash_control' => '1',
]);

// Item do documento
SalesInvoiceItem::create([
    'sales_invoice_id' => $invoice->id,
    'product_id' => $product->id,
    'product_name' => $product->name,
    'description' => $product->description ?? $product->name,
    'quantity' => 1,
    'unit' => 'UN',
    'unit_price' => 100.00,
    'subtotal' => 100.00,
    'tax_rate' => 14,
    'tax_amount' => 14.00,
    'total' => 114.00,
    'order' => 1,
]);

// Gerar hash SAFT-AO
$invoice->generateHash();
```

### **4. Logs Detalhados**

**Processo Completo:**
```
[23:20:30] 🚀 Iniciando geração de documentos AGT...
[23:20:30] 📋 17 documentos selecionados
[23:20:30] 🔧 Verificando e criando recursos necessários...
[23:20:30] 🔍 Verificando recursos existentes...
[23:20:30]   → Criando cliente com NIF...
[23:20:30]   ✓ Cliente com NIF criado
[23:20:30]   → Criando consumidor final...
[23:20:30]   ✓ Consumidor final criado
[23:20:30]   → Criando produtos de teste...
[23:20:30]   ✓ Produtos criados
[23:20:30]   → Criando armazém...
[23:20:30]   ✓ Armazém criado
[23:20:30]   → Verificando séries de documentos...
[23:20:30]   ✓ Séries de documentos configuradas
[23:20:30] ✅ Recursos criados com sucesso
[23:20:30] 📄 Iniciando documento #1...
[23:20:30]   → Buscando cliente com NIF...
[23:20:30]   → Criando fatura FT com série...
[23:20:30]   ✅ Documento #1: FT AGT 2025/000001
[23:20:30] 📄 Iniciando documento #2...
[23:20:30]   → Buscando consumidor final...
[23:20:30]   → Criando fatura para consumidor final...
[23:20:30]   ✅ Documento #2: FT AGT 2025/000002
...
[23:20:35] 🎉 Processo concluído com sucesso!
[23:20:35] 📊 Total: 17 documentos gerados
```

### **5. Método Helper Genérico**

```php
private function generateGenericInvoice(
    $number, 
    $type, 
    $description, 
    $qty = 1, 
    $discount = 0, 
    $taxRate = 14, 
    $unitPrice = 100, 
    $productType = 'product', 
    $withholdingTax = 0, 
    $status = 'paid'
) {
    // 1. Validar recursos
    // 2. Buscar série do sistema
    // 3. Obter próximo número
    // 4. Criar fatura com série
    // 5. Criar item
    // 6. Gerar hash
}
```

---

## 📊 Documentos Gerados

### **Documentos 1-5 (Detalhados):**

1. **FT AGT 2025/000001** - Fatura com NIF
   - Cliente: Cliente Teste AGT (NIF: 123456789)
   - Produto: IVA 14%
   - Total: 114.00 AOA
   
2. **FT AGT 2025/000002** - Fatura sem NIF
   - Cliente: Consumidor Final (NIF: 999999999)
   - Produto: IVA 14%
   - Total: 85.50 AOA
   
3. **FP AGT 2025/000001** - Fatura Proforma
   - Cliente: Cliente Teste AGT
   - Produto: 2 unidades
   - Total: 228.00 AOA
   
4. **FS AGT 2025/000001** - Fatura Simplificada
   - Cliente: Consumidor Final
   - Produto: IVA 14%
   - Total: 57.00 AOA
   
5. **NC AGT 2025/000001** - Nota de Crédito
   - Cliente: Cliente Teste AGT
   - Devolução: -1 unidade
   - Total: -114.00 AOA

### **Documentos 6-17 (Genéricos):**

6. **FT AGT 2025/000003** - Múltiplos produtos (3 un)
7. **FT AGT 2025/000004** - Com desconto 10%
8. **FT AGT 2025/000005** - Isenta IVA (0%)
9. **FR AGT 2025/000001** - Fatura-Recibo
10. **FT AGT 2025/000006** - Grande valor (5000 AOA)
11. **FT AGT 2025/000007** - Serviços
12. **ND AGT 2025/000001** - Nota de Débito
13. **FT AGT 2025/000008** - Com retenção (6.5%)
14. **FT AGT 2025/000009** - Vencida
15. **FT AGT 2025/000010** - Anulada
16. **GT AGT 2025/000001** - Guia de Transporte
17. **FT AGT 2025/000011** - Exportação (IVA 0%)

---

## 🗄️ Estrutura do Banco de Dados

### **Tabelas Afetadas:**

```sql
-- Séries de documentos
invoicing_series
├─ id
├─ tenant_id
├─ document_type (FT, FS, FR, NC, ND, GT, FP)
├─ series_code (AGT)
├─ prefix
├─ next_number (incrementa automaticamente)
├─ number_padding
└─ is_active

-- Faturas
invoicing_sales_invoices
├─ id
├─ tenant_id
├─ series_id → invoicing_series.id ✅
├─ client_id → invoicing_clients.id
├─ warehouse_id → invoicing_warehouses.id
├─ invoice_number (da série) ✅
├─ invoice_type
├─ hash (SAFT-AO) ✅
└─ ... (valores)

-- Itens das faturas
invoicing_sales_invoice_items
├─ id
├─ sales_invoice_id → invoicing_sales_invoices.id
├─ product_id → products.id
├─ quantity
├─ unit_price
└─ ... (valores)

-- Proformas
invoicing_sales_proformas
├─ id
├─ tenant_id
├─ series_id → invoicing_series.id ✅
├─ client_id
├─ proforma_number (da série) ✅
└─ ... (valores)

-- Itens das proformas
invoicing_sales_proforma_items
├─ id
├─ sales_proforma_id
├─ product_id
└─ ... (valores)
```

---

## ✅ Verificação no Banco

### **Ver Séries Criadas:**
```sql
SELECT id, document_type, series_code, prefix, next_number, is_active
FROM invoicing_series
WHERE series_code = 'AGT'
ORDER BY document_type;
```

**Resultado Esperado:**
```
+----+---------------+-------------+--------+-------------+-----------+
| id | document_type | series_code | prefix | next_number | is_active |
+----+---------------+-------------+--------+-------------+-----------+
|  1 | FT            | AGT         | FT     |          12 |         1 |
|  2 | FS            | AGT         | FS     |           2 |         1 |
|  3 | FR            | AGT         | FR     |           2 |         1 |
|  4 | NC            | AGT         | NC     |           2 |         1 |
|  5 | ND            | AGT         | ND     |           2 |         1 |
|  6 | GT            | AGT         | GT     |           2 |         1 |
|  7 | FP            | AGT         | FP     |           2 |         1 |
+----+---------------+-------------+--------+-------------+-----------+
```

### **Ver Documentos Gerados:**
```sql
SELECT 
    si.id,
    si.invoice_number,
    si.invoice_type,
    s.series_code,
    c.name as client_name,
    si.total,
    LEFT(si.hash, 10) as hash_preview,
    si.created_at
FROM invoicing_sales_invoices si
JOIN invoicing_series s ON s.id = si.series_id
JOIN invoicing_clients c ON c.id = si.client_id
WHERE s.series_code = 'AGT'
ORDER BY si.created_at DESC
LIMIT 17;
```

### **Ver Proformas Geradas:**
```sql
SELECT 
    sp.id,
    sp.proforma_number,
    s.series_code,
    c.name as client_name,
    sp.total,
    sp.status,
    sp.created_at
FROM invoicing_sales_proformas sp
JOIN invoicing_series s ON s.id = sp.series_id
JOIN invoicing_clients c ON c.id = sp.client_id
WHERE s.series_code = 'AGT'
ORDER BY sp.created_at DESC;
```

---

## 🎯 Resultado Final

### **ANTES:**
```
❌ Números aleatórios (FT AGT 2025/000657)
❌ Sem relação com séries do sistema
❌ Possibilidade de duplicatas
❌ Não segue padrão de produção
❌ Erro: "Attempt to read property id on null"
```

### **AGORA:**
```
✅ Numeração sequencial real (FT AGT 2025/000001)
✅ Usa séries do sistema (InvoicingSeries)
✅ Incremento automático (next_number)
✅ Validação de todos os recursos
✅ Inserção real no banco de dados
✅ Geração de hash SAFT-AO
✅ Logs detalhados do processo
✅ Tratamento completo de erros
✅ 100% lógica de produção
```

---

## 🧪 Como Testar

### **1. Acessar:**
```
http://soserp.test/invoicing/agt-documents
```

### **2. Selecionar Documentos:**
- Marcar todos (17 documentos)
- Ou selecionar individualmente

### **3. Gerar:**
- Clicar "Gerar Documentos"
- Observar logs em tempo real
- Ver progresso 0% → 100%

### **4. Verificar:**
```sql
-- Séries incrementadas
SELECT document_type, next_number 
FROM invoicing_series 
WHERE series_code = 'AGT';

-- Documentos criados
SELECT COUNT(*) 
FROM invoicing_sales_invoices si
JOIN invoicing_series s ON s.id = si.series_id
WHERE s.series_code = 'AGT';

-- Ver detalhes
SELECT invoice_number, invoice_type, total, LEFT(hash, 10) as hash
FROM invoicing_sales_invoices si
JOIN invoicing_series s ON s.id = si.series_id
WHERE s.series_code = 'AGT'
ORDER BY id DESC;
```

### **5. Visualizar Documentos:**
- Clicar "Ver Documento" na tabela
- Abre preview HTML do documento
- Pode baixar PDF

---

## 📁 Arquivos Modificados

```
✅ app/Livewire/Invoicing/AGTDocumentGenerator.php
   - use InvoicingSeries
   - createAGTSeries()
   - getSeries()
   - Validação de recursos
   - generateDoc1-17 com séries
   - generateGenericInvoice() atualizado
   - Logs detalhados

✅ resources/views/livewire/invoicing/a-g-t-document-generator.blade.php
   - Rota corrigida: invoicing.sales.invoices.preview

✅ AGT_PRODUCTION_READY.md
   - Documentação completa
```

---

## 🎉 Conclusão

O Gerador AGT agora é **100% produção**:
- ✅ Usa lógica real do sistema
- ✅ Séries de documentos configuradas
- ✅ Numeração sequencial automática
- ✅ Validação completa de recursos
- ✅ Inserção real no banco
- ✅ Hash SAFT-AO gerado
- ✅ Logs interativos
- ✅ Tratamento de erros

**Pronto para uso em ambiente de produção! 🚀**
