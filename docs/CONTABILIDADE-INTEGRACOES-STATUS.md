# 📊 INTEGRAÇÃO CONTABILIDADE - STATUS ATUAL

**Data:** 13 de Janeiro 2025, 15:35  
**Status:** ⚠️ **ESTRUTURA CRIADA - IMPLEMENTAÇÃO PENDENTE**

---

## 🎯 ESTRUTURA EXISTENTE

### ✅ **1. TABELA DE MAPEAMENTOS (100% PRONTA)**

**Tabela:** `accounting_integration_mappings`

**Campos:**
- `tenant_id` - Multi-tenancy
- `event` - Evento que dispara integração
- `journal_id` - Diário contabilístico
- `debit_account_id` - Conta de débito
- `credit_account_id` - Conta de crédito
- `vat_account_id` - Conta de IVA
- `conditions` - Condições JSON
- `auto_post` - Lançar automaticamente (boolean)
- `active` - Ativo (boolean)

**Índices:**
- `tenant_id + event` - Para busca rápida

---

### ✅ **2. MODEL CRIADO (100% FUNCIONAL)**

**File:** `app/Models/Accounting/IntegrationMapping.php`

**Relationships:**
- ✅ `tenant()` - Pertence a Tenant
- ✅ `journal()` - Diário
- ✅ `debitAccount()` - Conta Débito
- ✅ `creditAccount()` - Conta Crédito  
- ✅ `vatAccount()` - Conta IVA

---

### ✅ **3. SEEDER CRIADO (100% FUNCIONAL)**

**File:** `database/seeders/Accounting/IntegrationMappingSeeder.php`

**Mapeamentos Criados:**

#### **FATURAÇÃO → CONTABILIDADE:**
1. **`invoice`** (Fatura de Venda FT/FR)
   - Débito: Clientes (211)
   - Crédito: Vendas (71)
   - IVA: IVA Liquidado (2433)
   - Auto-post: ✅ SIM

#### **TESOURARIA → CONTABILIDADE:**

2. **`receipt_cash`** (Recebimento em Caixa)
   - Débito: Caixa (111)
   - Crédito: Clientes (211)
   - Auto-post: ✅ SIM

3. **`receipt_bank`** (Recebimento em Banco)
   - Débito: Banco (112)
   - Crédito: Clientes (211)
   - Auto-post: ✅ SIM

4. **`payment_bank`** (Pagamento via Banco)
   - Débito: Fornecedores (221)
   - Crédito: Banco (112)
   - Auto-post: ✅ SIM

5. **`payment_cash`** (Pagamento em Caixa)
   - Débito: Fornecedores (221)
   - Crédito: Caixa (111)
   - Auto-post: ✅ SIM

#### **COMPRAS → CONTABILIDADE:**

6. **`purchase`** (Compra)
   - Débito: Compras (61)
   - Crédito: Fornecedores (221)
   - IVA: IVA Dedutível (2432)
   - Auto-post: ✅ SIM

---

## ⚠️ O QUE FALTA IMPLEMENTAR

### **❌ 1. SERVICE DE INTEGRAÇÃO (0%)**

**Precisa criar:** `app/Services/Accounting/IntegrationService.php`

**Métodos necessários:**
```php
class IntegrationService
{
    // Criar lançamento contabilístico a partir de evento
    public function createMoveFromEvent($event, $data);
    
    // Buscar mapeamento para evento
    public function getMappingForEvent($event, $tenantId);
    
    // Criar lançamento de fatura
    public function createMoveFromInvoice($invoice);
    
    // Criar lançamento de recebimento
    public function createMoveFromReceipt($receipt);
    
    // Criar lançamento de pagamento
    public function createMoveFromPayment($payment);
    
    // Criar lançamento de compra
    public function createMoveFromPurchase($purchase);
}
```

### **❌ 2. OBSERVERS (0%)**

**Precisam ser criados:**

```php
// app/Observers/InvoiceObserver.php
class InvoiceObserver
{
    public function created(Invoice $invoice)
    {
        // Criar lançamento contabilístico
        IntegrationService::createMoveFromInvoice($invoice);
    }
}

// app/Observers/ReceiptObserver.php
// app/Observers/PaymentObserver.php
// Etc...
```

### **❌ 3. REGISTRAR OBSERVERS (0%)**

**No AppServiceProvider:**
```php
public function boot()
{
    Invoice::observe(InvoiceObserver::class);
    Receipt::observe(ReceiptObserver::class);
    Payment::observe(PaymentObserver::class);
}
```

### **❌ 4. TELA DE CONFIGURAÇÃO (0%)**

**UI para configurar mapeamentos:**
- Ver mapeamentos atuais
- Editar contas de débito/crédito
- Ativar/desativar auto-post
- Testar integração

---

## 🎯 COMO FUNCIONARÁ (QUANDO IMPLEMENTADO)

### **FLUXO AUTOMÁTICO:**

**1. Fatura Criada (Módulo Faturação):**
```
Cliente cria FT #001 de 100.000 Kz + 14% IVA
↓
Observer detecta criação
↓
IntegrationService busca mapeamento para 'invoice'
↓
Cria lançamento contabilístico automático:
  - Débito: Clientes (211) → 114.000 Kz
  - Crédito: Vendas (71) → 100.000 Kz
  - Crédito: IVA Liquidado (2433) → 14.000 Kz
↓
Lançamento lançado automaticamente (auto_post=true)
```

**2. Recebimento em Caixa (Módulo Tesouraria):**
```
Cliente paga RC #001 de 114.000 Kz em Caixa
↓
Observer detecta criação
↓
IntegrationService busca mapeamento para 'receipt_cash'
↓
Cria lançamento contabilístico:
  - Débito: Caixa (111) → 114.000 Kz
  - Crédito: Clientes (211) → 114.000 Kz
↓
Lançado automaticamente
```

---

## 📋 CHECKLIST DE IMPLEMENTAÇÃO

### **FASE 1 - SERVICE (3-4h):**
- [ ] Criar `IntegrationService.php`
- [ ] Implementar `getMappingForEvent()`
- [ ] Implementar `createMoveFromEvent()`
- [ ] Implementar métodos específicos (Invoice, Receipt, etc)
- [ ] Testes unitários

### **FASE 2 - OBSERVERS (2-3h):**
- [ ] Criar `InvoiceObserver.php`
- [ ] Criar `ReceiptObserver.php`
- [ ] Criar `PaymentObserver.php`
- [ ] Criar `PurchaseObserver.php`
- [ ] Registrar observers no AppServiceProvider

### **FASE 3 - UI CONFIGURAÇÃO (2-3h):**
- [ ] Component `IntegrationSettings`
- [ ] View com lista de mapeamentos
- [ ] Forms para editar mapeamentos
- [ ] Testes de integração
- [ ] Link no menu

### **FASE 4 - TESTES E VALIDAÇÃO (2h):**
- [ ] Testar criação de fatura → lançamento
- [ ] Testar recebimento → lançamento
- [ ] Testar pagamento → lançamento
- [ ] Validar balanceamento contabilístico
- [ ] Documentar casos de uso

---

## 🎯 PRIORIDADE DE IMPLEMENTAÇÃO

**CRÍTICO (Implementar AGORA):**
1. ✅ IntegrationService
2. ✅ InvoiceObserver (Fatura → Contabilidade)
3. ✅ ReceiptObserver (Recebimento → Contabilidade)

**IMPORTANTE (Implementar DEPOIS):**
4. PaymentObserver
5. PurchaseObserver
6. UI de Configuração

---

## 💡 VANTAGENS DA ARQUITETURA ATUAL

**✅ Flexível:**
- Mapeamentos configuráveis por tenant
- Pode desativar integração (active=false)
- Pode desativar auto-post
- Condições JSON para regras complexas

**✅ Escalável:**
- Fácil adicionar novos eventos
- Fácil adicionar novos mapeamentos
- Multi-tenant ready

**✅ Auditável:**
- Cada lançamento sabe de onde veio
- Timestamps de criação
- Possível reverter

---

## 📊 ESTATÍSTICAS

**Implementado:**
- ✅ Estrutura de dados: 100%
- ✅ Models: 100%
- ✅ Seeders: 100%
- ✅ Mapeamentos: 6 eventos prontos

**Pendente:**
- ❌ Service: 0%
- ❌ Observers: 0%
- ❌ UI: 0%
- ❌ Testes: 0%

**Total Geral:** ~30% Completo

---

## 🚀 PRÓXIMOS PASSOS

**1. CRIAR SERVICE (URGENTE):**
```bash
# Criar IntegrationService
php artisan make:service Accounting/IntegrationService
```

**2. IMPLEMENTAR LÓGICA:**
- Buscar mapeamento
- Criar Move + MoveLines
- Validar balanceamento
- Auto-post se configurado

**3. CRIAR OBSERVERS:**
```bash
php artisan make:observer InvoiceObserver --model=Invoice
```

**4. TESTAR:**
- Criar fatura de teste
- Verificar se lançamento foi criado
- Validar contas e valores

---

**RESUMO:** Estrutura 100% pronta, mas **LÓGICA DE INTEGRAÇÃO AUTOMÁTICA NÃO ESTÁ IMPLEMENTADA**. Precisa criar Service + Observers para funcionar.

**Quer que eu implemente agora?** 🚀
