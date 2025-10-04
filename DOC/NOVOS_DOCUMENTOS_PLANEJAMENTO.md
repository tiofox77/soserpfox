# Planejamento: Novos Documentos Fiscais

## 📋 Visão Geral

Criar 4 novos tipos de documentos fiscais conforme AGT Angola:
1. **Nota de Débito** - Ajuste positivo (aumenta valor da fatura)
2. **Nota de Crédito** - Devolução/desconto (reduz valor da fatura)
3. **Recibo** - Comprovante de pagamento
4. **Adiantamento** - Pagamento antecipado

---

## 🎯 1. NOTA DE CRÉDITO (Prioridade Alta)

### **Descrição:**
Documento emitido para corrigir faturas, conceder descontos ou registrar devoluções de mercadoria.

### **Campos Principais:**
- `credit_note_number` - Número sequencial (NC/2025/001)
- `original_invoice_id` - FK para fatura original
- `issue_date` - Data de emissão
- `reason` - Motivo (devolução, desconto, correção)
- `type` - Tipo (total, parcial)
- `items[]` - Produtos/valores creditados
- `total` - Valor total do crédito
- `status` - draft, issued, cancelled

### **Regras de Negócio:**
- ✅ Deve referenciar uma fatura emitida
- ✅ Valor não pode exceder valor da fatura original
- ✅ Atualiza saldo da fatura (fatura.total - credit_note.total)
- ✅ Pode ter múltiplas NC para mesma fatura
- ✅ AGT: Deve ser comunicada à AGT

### **Fluxo:**
```
Fatura Emitida → Cliente devolve produto → Emitir NC → Fatura fica com saldo ajustado
```

---

## 💰 2. NOTA DE DÉBITO (Prioridade Alta)

### **Descrição:**
Documento para cobrar valores adicionais relacionados a uma fatura já emitida.

### **Campos Principais:**
- `debit_note_number` - Número sequencial (ND/2025/001)
- `original_invoice_id` - FK para fatura original
- `issue_date` - Data de emissão
- `reason` - Motivo (juros, acréscimos, correção)
- `items[]` - Valores/serviços adicionais
- `total` - Valor total do débito
- `status` - draft, issued, paid, cancelled

### **Regras de Negócio:**
- ✅ Deve referenciar uma fatura emitida
- ✅ Aumenta o valor devido (fatura.total + debit_note.total)
- ✅ Pode ter múltiplas ND para mesma fatura
- ✅ AGT: Deve ser comunicada à AGT

### **Fluxo:**
```
Fatura Emitida → Necessário cobrar juros/acréscimo → Emitir ND → Valor total aumenta
```

---

## 🧾 3. RECIBO (Prioridade Alta)

### **Descrição:**
Comprovante de pagamento de faturas. Documento legal que confirma quitação.

### **Campos Principais:**
- `receipt_number` - Número sequencial (REC/2025/001)
- `invoice_id` - FK para fatura paga (opcional)
- `payment_date` - Data do pagamento
- `payment_method` - Método (dinheiro, transferência, etc)
- `amount_paid` - Valor pago
- `reference` - Referência bancária/comprovante
- `notes` - Observações
- `status` - issued, cancelled

### **Regras de Negócio:**
- ✅ Pode ser emitido para fatura específica ou genérico
- ✅ Atualiza status da fatura (paid ou partially_paid)
- ✅ Múltiplos recibos = pagamento parcial
- ✅ Valor recibos = valor fatura = fatura quitada
- ✅ AGT: Recibo é documento fiscal obrigatório

### **Fluxo:**
```
Fatura Pendente → Cliente paga (total ou parcial) → Emitir Recibo → Fatura marcada como paga
```

---

## 💵 4. ADIANTAMENTO (Prioridade Média)

### **Descrição:**
Pagamento antecipado antes da emissão da fatura. Cria crédito para cliente.

### **Campos Principais:**
- `advance_number` - Número sequencial (ADV/2025/001)
- `client_id` / `supplier_id` - Cliente/Fornecedor
- `payment_date` - Data do pagamento
- `amount` - Valor adiantado
- `payment_method` - Método de pagamento
- `purpose` - Finalidade do adiantamento
- `used_amount` - Valor já utilizado
- `remaining_amount` - Saldo disponível
- `status` - available, partially_used, fully_used, refunded

### **Regras de Negócio:**
- ✅ Cria crédito disponível para cliente/fornecedor
- ✅ Ao emitir fatura, pode abater do adiantamento
- ✅ Saldo restante fica disponível para futuras compras
- ✅ Pode ser reembolsado se não utilizado
- ✅ AGT: Deve ser registrado mas não é fatura

### **Fluxo:**
```
Cliente paga adiantado → Registrar Adiantamento → Ao vender, abater do saldo → Emitir fatura com desconto
```

---

## 📊 Estrutura de Banco de Dados

### **Tabelas a Criar:**

```sql
-- 1. Notas de Crédito
invoicing_credit_notes
- id, tenant_id
- credit_note_number (unique)
- invoice_id (FK)
- client_id (FK)
- issue_date, reason, type
- subtotal, tax_amount, total
- status, saft_hash
- created_by, timestamps, soft_deletes

invoicing_credit_note_items
- id, credit_note_id
- product_id, description, quantity
- unit_price, subtotal, tax_rate, tax_amount, total
- timestamps

-- 2. Notas de Débito
invoicing_debit_notes
- id, tenant_id
- debit_note_number (unique)
- invoice_id (FK)
- client_id (FK)
- issue_date, reason
- subtotal, tax_amount, total
- status, saft_hash
- created_by, timestamps, soft_deletes

invoicing_debit_note_items
- (mesma estrutura de credit_note_items)

-- 3. Recibos
invoicing_receipts
- id, tenant_id
- receipt_number (unique)
- invoice_id (FK - nullable)
- client_id / supplier_id (FK)
- payment_date, payment_method
- amount_paid, reference, notes
- status, saft_hash
- created_by, timestamps, soft_deletes

-- 4. Adiantamentos
invoicing_advances
- id, tenant_id
- advance_number (unique)
- client_id / supplier_id (FK)
- type (sale/purchase)
- payment_date, amount, payment_method
- purpose, used_amount, remaining_amount
- status
- created_by, timestamps, soft_deletes

invoicing_advance_usages
- id, advance_id
- invoice_id (FK)
- amount_used, usage_date
- timestamps
```

---

## 🎨 Componentes e Views

### **Estrutura por Documento:**

```
app/Livewire/Invoicing/CreditNotes/
├── CreditNotes.php (listagem)
└── CreditNoteCreate.php (criar/editar)

app/Livewire/Invoicing/DebitNotes/
├── DebitNotes.php
└── DebitNoteCreate.php

app/Livewire/Invoicing/Receipts/
├── Receipts.php
└── ReceiptCreate.php

app/Livewire/Invoicing/Advances/
├── Advances.php
└── AdvanceCreate.php

resources/views/livewire/invoicing/
├── notas-credito/ (lista + modais)
├── notas-debito/ (lista + modais)
├── recibos/ (lista + modais)
└── adiantamentos/ (lista + modais)
```

---

## 🎨 Cores e Ícones (Tema Visual)

| Documento | Cor Principal | Ícone | Descrição |
|-----------|---------------|-------|-----------|
| **Nota de Crédito** | Verde (`green-600`) | `fa-file-circle-minus` | Reduz valor |
| **Nota de Débito** | Vermelho (`red-600`) | `fa-file-circle-plus` | Aumenta valor |
| **Recibo** | Azul (`blue-600`) | `fa-receipt` | Comprovante |
| **Adiantamento** | Amarelo (`yellow-600`) | `fa-hand-holding-dollar` | Pagamento antecipado |

---

## 📋 Menu Organizado (Proposta)

```
📄 Documentos
  ├── 📝 Proformas Venda (roxo)
  ├── 📄 Faturas Venda (índigo)
  ├── 📝 Proformas Compra (laranja)
  ├── 📄 Faturas Compra (vermelho)
  │
  ├── ➖ Notas de Crédito (verde) ⭐ NOVO
  ├── ➕ Notas de Débito (vermelho) ⭐ NOVO
  ├── 🧾 Recibos (azul) ⭐ NOVO
  └── 💵 Adiantamentos (amarelo) ⭐ NOVO
```

---

## 🔄 Ordem de Implementação Recomendada

### **Fase 1: Recibos (URGENTE)**
Sistema de pagamentos é fundamental!
1. ✅ Migration + Model
2. ✅ Componente Livewire
3. ✅ Views
4. ✅ PDF
5. ✅ Integração com Faturas

### **Fase 2: Nota de Crédito**
Devoluções são comuns
1. ✅ Migration + Model
2. ✅ Componente Livewire
3. ✅ Views
4. ✅ PDF
5. ✅ Integração com Faturas

### **Fase 3: Nota de Débito**
Menos usado, mas importante
1. ✅ Migration + Model
2. ✅ Componente Livewire
3. ✅ Views
4. ✅ PDF

### **Fase 4: Adiantamentos**
Opcional, mas útil
1. ✅ Migration + Model
2. ✅ Componente Livewire
3. ✅ Views
4. ✅ Sistema de abatimento

---

## 🧮 Cálculos

### **Nota de Crédito/Débito:**
- ✅ Usa `InvoiceCalculationHelper::calculateTotals()`
- ✅ Mesma lógica AGT Angola
- ✅ Pode ter IVA

### **Recibo:**
- ✅ Apenas valor pago (sem cálculos complexos)
- ✅ Atualiza saldo da fatura

### **Adiantamento:**
- ✅ Apenas valor e saldo disponível
- ✅ Não tem IVA (apenas crédito)

---

## ✅ Checklist de Criação (Por Documento)

- [ ] Migration (tabela principal + items se aplicável)
- [ ] Model (com relacionamentos e scopes)
- [ ] Seeder (dados iniciais se necessário)
- [ ] Componente Livewire (listagem)
- [ ] Componente Livewire (criar/editar)
- [ ] View listagem
- [ ] View create/edit
- [ ] Modais (delete, view, history)
- [ ] Controller PDF
- [ ] Template PDF
- [ ] Rotas (5 rotas padrão)
- [ ] Item no menu
- [ ] Testes básicos

---

## 📝 Exemplo de Rota Padrão

```php
// Notas de Crédito
Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
    Route::get('/', CreditNotes::class)->name('index');
    Route::get('/create', CreditNoteCreate::class)->name('create');
    Route::get('/{id}/edit', CreditNoteCreate::class)->name('edit');
    Route::get('/{id}/pdf', [CreditNoteController::class, 'generatePdf'])->name('pdf');
    Route::get('/{id}/preview', [CreditNoteController::class, 'previewHtml'])->name('preview');
});
```

---

## 🎯 Próximos Passos Imediatos

1. **Decidir prioridade**: Qual documento criar primeiro?
2. **Criar migrations**: Estrutura de BD
3. **Criar models**: Com relacionamentos
4. **Criar componentes**: Reutilizar lógica existente
5. **Criar views**: Copiar e adaptar templates
6. **Criar PDFs**: Templates AGT Angola
7. **Testar**: Fluxo completo

---

**Criado em:** 04/10/2025  
**Status:** Planejamento  
**Progresso:** 0% (Pronto para implementar)

---

## ❓ Perguntas para o Usuário:

1. **Qual documento criar primeiro?** (Sugestão: Recibos)
2. **Recibos deve funcionar para Vendas E Compras?** (Sugestão: Sim)
3. **Adiantamentos deve ter controle de saldo?** (Sugestão: Sim)
4. **Notas de Crédito/Débito devem ter items ou valor único?** (Sugestão: Items para mais detalhes)
