# MÓDULO TESOURARIA - DOCUMENTAÇÃO

## 📊 Visão Geral

O módulo de Tesouraria é responsável pela gestão financeira completa da empresa, incluindo controle de caixas, contas bancárias, movimentos financeiros, transferências e reconciliações bancárias.

---

## 🗂️ Estrutura do Módulo

### Models (7)
```
app/Models/Treasury/
├── PaymentMethod.php     - Métodos de pagamento
├── Bank.php             - Bancos
├── Account.php          - Contas bancárias
├── CashRegister.php     - Caixas
├── Transaction.php      - Movimentos financeiros
├── Transfer.php         - Transferências
└── Reconciliation.php   - Reconciliações bancárias
```

### Livewire Components (5)
```
app/Livewire/Treasury/
├── PaymentMethods.php
├── Banks.php
├── Accounts.php
├── CashRegisters.php
└── Transactions.php
```

### Views
```
resources/views/livewire/treasury/
├── payment-methods.blade.php
├── banks.blade.php
├── accounts.blade.php
├── cash-registers.blade.php
└── transactions.blade.php
```

### Database Tables (7)
```
treasury_payment_methods
treasury_banks
treasury_accounts
treasury_cash_registers
treasury_transactions
treasury_transfers
treasury_reconciliations
```

---

## 📋 Funcionalidades

### 1. Métodos de Pagamento
- **Tipos:** Dinheiro, Transferência Bancária, Multicaixa, TPA, MB Way, Cheque
- **Taxas:** Percentual e fixa
- **Configuração:** Ícone, cor, ordenação
- **Validação:** Requer ou não conta bancária

### 2. Bancos
- **Cadastro:** Nome, código, SWIFT
- **Dados:** Logo, website, telefone
- **País:** Configurável (padrão Angola)
- **Status:** Ativo/Inativo

### 3. Contas Bancárias
- **Informações:** Nome, número, IBAN
- **Moedas:** AOA, USD, EUR
- **Tipos:** Corrente, Poupança, Investimento
- **Saldos:** Inicial e atual (automático)
- **Gestor:** Nome, telefone, email
- **Conta Padrão:** Marcação de conta principal

### 4. Caixas (Cash Registers)
- **Abertura/Fechamento:** Controle de caixa diário
- **Saldos:** Abertura, atual, esperado
- **Responsável:** Vinculado a usuário
- **Status:** Aberto/Fechado
- **Notas:** Abertura e fechamento

### 5. Transações (Movimentos Financeiros)
- **Tipos:** Entrada, Saída, Transferência
- **Categorias:** Venda, Compra, Salário, Aluguel, etc
- **Integração:** Faturas e Compras
- **Origem:** Conta bancária ou Caixa
- **Reconciliação:** Marcação de conciliado
- **Anexos:** Comprovantes

### 6. Transferências
- **Origem/Destino:** Entre contas ou caixas
- **Taxas:** Taxa de transferência
- **Status:** Pendente, Concluído, Cancelado
- **Referência:** Código externo
- **Anexos:** Comprovantes

### 7. Reconciliação Bancária
- **Período:** Data início e fim
- **Comparação:** Extrato vs Sistema
- **Diferenças:** Cálculo automático
- **Progresso:** Total, reconciliado, pendente
- **Status:** Em progresso, Concluído, Cancelado
- **Upload:** Arquivo do extrato

---

## 🔗 Integrações

### Com Módulo Faturação
- ✅ Faturas geram movimento de **entrada**
- ✅ Compras geram movimento de **saída**
- ✅ Recibos vinculados a faturas
- ✅ Notas de crédito estornam valores

### Automações
- Atualização automática de saldos
- Numeração automática de documentos
- Cálculo de diferenças em reconciliações
- Validações de limites e disponibilidade

---

## 📊 Fluxos de Trabalho

### Fluxo de Caixa Diário
```
1. Abrir Caixa → Define saldo inicial
2. Registrar Movimentos → Entradas e saídas
3. Fechar Caixa → Compara esperado vs real
4. Gerar Relatório → Conferência
```

### Fluxo de Pagamento de Fatura
```
1. Fatura criada (módulo faturação)
2. Gerar Transação automática (tipo: income)
3. Vincular método de pagamento
4. Atualizar saldo da conta/caixa
5. Marcar fatura como paga
```

### Fluxo de Reconciliação
```
1. Upload do extrato bancário
2. Sistema lista transações do período
3. Comparar extrato vs transações
4. Marcar transações como reconciliadas
5. Identificar diferenças
6. Finalizar reconciliação
```

---

## 🎨 Design Pattern

### Multi-Tenant
- ✅ Todas as tabelas com `tenant_id`
- ✅ Trait `BelongsToTenant` aplicado
- ✅ Isolamento automático de dados
- ✅ Scopes globais ativos

### Relacionamentos
```php
Account
  ├─ belongsTo: Tenant, Bank
  ├─ hasMany: Transactions, Reconciliations
  
Transaction
  ├─ belongsTo: Tenant, User, Account, CashRegister
  ├─ belongsTo: PaymentMethod, Invoice, Purchase
  
Transfer
  ├─ belongsTo: Tenant, User
  ├─ belongsTo: fromAccount, toAccount
  └─ belongsTo: fromCashRegister, toCashRegister
```

---

## 🛡️ Segurança

### Validações
- Saldo suficiente para saídas
- Conta/Caixa deve estar ativa
- Usuário com permissão apropriada
- Datas válidas (não futuras para fechamento)

### Permissões (Spatie)
```
treasury.view
treasury.create
treasury.edit
treasury.delete
treasury.reconcile
treasury.manage_all
```

---

## 📈 Relatórios Disponíveis

1. **Extrato de Conta** - Movimentos por período
2. **Fluxo de Caixa** - Entradas vs Saídas
3. **DRE** - Demonstração de Resultados
4. **Conciliações Pendentes** - Status de reconciliações
5. **Movimentos por Categoria** - Análise de gastos
6. **Projeções** - Contas a receber e pagar

---

## 🚀 Roadmap Futuro

- [ ] Dashboard com gráficos (Chart.js)
- [ ] Exportação para Excel/PDF
- [ ] Importação de extratos bancários (OFX/CSV)
- [ ] API REST para integração externa
- [ ] Notificações de baixo saldo
- [ ] Alertas de contas a pagar/receber
- [ ] Multi-moeda com conversão automática
- [ ] Orçamento vs Real
- [ ] Centro de custos

---

**Última atualização:** 03 de Outubro de 2025  
**Versão:** 1.0.0  
**Status:** Models e Migrations completos, Components em desenvolvimento
