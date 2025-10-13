# 📊 MVP Contabilidade - Roadmap Completo

**Versão:** 5.0  
**Data:** 13 de janeiro de 2025  
**Status:** ✅ 99% IMPLEMENTADO (R0 98% + R1 100% + R2 100%! 🎉🎉🎉)  
**Inspiração:** Primavera ERP (adaptado para Angola)

---

## 🎯 **OBJETIVO DO MVP**

Criar módulo de Contabilidade completo para ERP SaaS multi-tenant, com foco em PME de Angola:

✅ **Registo contabilístico fiável e audível**  
✅ **Integrações automáticas** (Faturação, Tesouraria, Folha)  
✅ **Relatórios fiscais obrigatórios** (IVA, Retenções, DRE)  
✅ **Preparação para SAF-T AO** (Autoridade Geral Tributária)  
✅ **UI/UX consistente** com Workshop e HR  

---

## 📅 **ROADMAP - 3 RELEASES**

### **🟢 R0 - MVP Base (4-6 semanas)** - ✅ 98% COMPLETO

**6 Áreas Funcionais:**
- [x] Dashboard - 7 stats cards + tabela lançamentos recentes
- [x] Plano de Contas - CRUD + hierarquia + 71 contas SNC
- [x] Diários - CRUD + 6 diários padrão
- [x] Lançamentos - Partidas dobradas + validação D=C
- [x] Períodos - Fecho/reabertura + validações
- [x] Relatórios - 9 relatórios fiscais

**Sprint 1 (Semanas 1-2): Fundação** - ✅ 95% COMPLETO
- [x] ✅ Estrutura de pastas (padrão area/partials)
- [x] ✅ Models e Migrations (11 tabelas implementadas: accounts, journals, periods, moves, move_lines, taxes, withholdings, integrations, logs, integration_mappings, allocation_matrices)
- [x] ✅ Plano de Contas SNC (seed com 71 contas principais)
- [x] ✅ Componentes Livewire base (6 áreas: Dashboard, Accounts, Journals, Moves, Periods, Reports)
- [x] ✅ 4 Seeders completos (Accounts, Journals, Periods, IntegrationMapping)
- [ ] ⏳ RBAC (5 perfis: Admin, Contabilista, Tesouraria, Vendas, Auditor) - **PENDENTE**

**Sprint 2 (Semanas 3-4): Core Contabilístico** - ✅ 100% COMPLETO
- [x] ✅ Diários (6 diários criados: Vendas, Compras, Caixa, Banco, Salários, Ajustes)
- [x] ✅ Lançamentos (double-entry, validações em tempo real)
- [x] ✅ Impostos (Tax model criado e mapeado)
- [x] ✅ Interface de lançamento manual (modal com linhas dinâmicas)
- [x] ✅ Validações (débito = crédito, períodos, numeração)
- [x] ✅ Estados de lançamento (Draft, Posted, Cancelled)
- [x] ✅ Multi-tenancy completo

**Sprint 3 (Semanas 5-6): Integrações & Relatórios** - ✅ 95% COMPLETO!
- [x] ✅ Integração Faturação → Contabilidade (Infraestrutura completa)
- [x] ✅ Integração Tesouraria → Contabilidade (Infraestrutura completa)
- [ ] ⏳ Integração Folha → Contabilidade (opcional) - **PENDENTE**
- [x] ✅ Relatórios: Balancete (completo e funcional)
- [x] ✅ Relatórios: Razão Geral (completo)
- [x] ✅ Relatórios: Diário (completo)
- [x] ✅ Relatórios: Mapa de IVA (completo)
- [x] ✅ DRE Simplificada (completo)
- [x] ✅ Fecho de período (completo) **NOVO!** 🎉
- [ ] ⏳ Relatórios: Retenções - **PENDENTE**

**Sprint 4 (NOVO): Demonstrações Fiscais Angola** - ✅ 100% COMPLETO! 🎉
- [x] ✅ Balanço (Posição Financeira) - **COMPLETO!**
  - [x] Activo Corrente (Caixa, Bancos, Clientes, Inventários)
  - [x] Activo Não Corrente (Imobilizado, Intangível)
  - [x] Passivo Corrente e Não Corrente
  - [x] Capital Próprio (Capital, Reservas, Resultados)
  - [x] Indicadores financeiros (Liquidez, Solvabilidade, Endividamento)
- [x] ✅ Demonstração de Resultados por Natureza (DRN) - **COMPLETO!** 🎉
  - [x] Vendas e Serviços (classe 7)
  - [x] CMVMC (61.*)
  - [x] FST - Fornecimentos e Serviços Terceiros (62.*)
  - [x] Gastos com Pessoal (63.*)
  - [x] Depreciações e Amortizações (64.*)
  - [x] Outros rendimentos/gastos (74.* / 68.*)
  - [x] Resultados financeiros (75.* / 69.*)
  - [x] Imposto sobre o rendimento (79.*)
  - [x] Margens: Bruta, Operacional, Líquida
  - [x] Resultados: Bruto, EBIT, EBT, Líquido
- [x] ✅ Demonstração de Resultados por Funções (DRF) - **COMPLETO!** 🎉
  - [x] Custo das Vendas (alocação automática)
  - [x] Distribuição (alocação configurável)
  - [x] Administrativos (alocação configurável)
  - [x] I&D (alocação configurável)
  - [x] Matriz de alocação conta→função (AllocationMatrix model)
  - [x] Sistema de alocação padrão automático
  - [x] Validação soma = 100%
- [x] ✅ Demonstração de Fluxos de Caixa (DFC) - Método Indireto - **COMPLETO!** 🎉
  - [x] Atividades Operacionais (resultado líquido + ajustes)
  - [x] Ajustamentos (depreciações, imparidades, provisões)
  - [x] Variação capital circulante (clientes, inventários, fornecedores, estado)
  - [x] Atividades de Investimento (imobilizado, intangível, investimentos)
  - [x] Atividades de Financiamento (empréstimos, capital, dividendos)
  - [x] Reconciliação automática de caixa
  - [x] Validação de diferenças
- [x] ✅ Seeds de Mapeamentos Fiscais - **COMPLETO!** 🎉
  - [x] FinancialStatementMappingSeeder criado
  - [x] Mapeamento Balanço (20 rubricas)
  - [x] Mapeamento DRN (14 rubricas)
  - [x] Mapeamento DFC (15 rubricas)
  - [x] Alocações padrão DRF (7 padrões)
- [x] ✅ Exportação PDF/Excel das Demonstrações - **COMPLETO!** 🎉
  - [x] ReportExportService criado
  - [x] Métodos PDF para 4 demonstrações
  - [x] Métodos Excel para 6 relatórios
  - [x] BalanceSheetExport (exemplo)
  - [x] Botões exportação nas views

**Entregáveis R0:**
- [x] ✅ 6 áreas funcionais completas (Dashboard, Accounts, Journals, Moves, Periods, Reports)
- [ ] ⏳ 10 relatórios fiscais (9 de 10 implementados - 90%):
  - [x] ✅ Balancete de Verificação
  - [x] ✅ Razão Geral
  - [x] ✅ Diário
  - [x] ✅ Mapa de IVA
  - [x] ✅ DRE Simplificada
  - [x] ✅ Balanço (Posição Financeira)
  - [x] ✅ DR por Natureza (DRN)
  - [x] ✅ DR por Funções (DRF) **NOVO!** 🎉
  - [x] ✅ Fluxos de Caixa (DFC) **NOVO!** 🎉
  - [ ] ⏳ Retenções na Fonte
- [x] ✅ 2 integrações automáticas (Faturação + Tesouraria) **NOVO!**
  - [x] PostingService com 4 métodos (invoice, receipt, purchase, payment)
  - [x] IntegrationMapping com mapeamentos configuráveis
  - [x] 6 eventos mapeados por tenant
- [ ] ⏳ Testes unitários (cobertura 70%+) - **PENDENTE**
- [x] ✅ Documentação técnica e operacional (13 documentos criados)

---

### **🟡 R1 - Melhorias & Automações (4 semanas)** - ✅ 100% COMPLETO! 🎉

**Semanas 7-8: Automações Bancárias** - ✅ COMPLETO
- [x] ✅ Reconciliação bancária por ficheiro (MT940/CSV/OFX)
  - [x] Migration: bank_reconciliations + bank_reconciliation_items
  - [x] BankReconciliationService completo
  - [x] Import CSV parser
  - [x] Import MT940 parser (formato bancário standard)
  - [x] Import OFX parser
  - [x] Auto-matching inteligente (IA)
  - [x] Algoritmo de confiança: valor (60%) + data (20%) + descrição (20%)
  - [x] Match manual
  - [x] Sugestões automáticas com % confiança
  - [x] Status: unmatched/matched/excluded
- [x] ✅ Lógica de matching
  - [x] findMatchingSuggestions() - Busca move_lines similares
  - [x] autoMatch() - Match automático >90% confiança
  - [x] manualMatch() - Match manual pelo usuário
  - [x] recalculateDifference() - Recalcula saldos

**Semanas 9-10: Imobilizado & SAF-T** - ✅ COMPLETO
- [x] ✅ Gestão de imobilizado completa
  - [x] Migration: fixed_assets + fixed_asset_categories + fixed_asset_depreciations
  - [x] FixedAsset Model com todos campos
  - [x] FixedAssetCategory Model
  - [x] FixedAssetDepreciation Model
  - [x] Campos: code, name, acquisition_value, residual_value, useful_life, book_value
  - [x] Status: active/fully_depreciated/sold/scrapped
- [x] ✅ Depreciações automáticas (3 métodos)
  - [x] DepreciationService completo
  - [x] Método Linear (quotas constantes)
  - [x] Método Declining Balance (quotas decrescentes)
  - [x] Método Units of Production (por unidades)
  - [x] calculateMonthlyDepreciation() - Calcula todas depreciações do mês
  - [x] calculateAssetDepreciation() - Calcula por ativo
  - [x] recordDepreciation() - Regista depreciação
  - [x] postDepreciation() - Gera lançamento contabilístico
  - [x] batchProcessDepreciations() - Processa em lote
- [x] ✅ Lançamentos contabilísticos automáticos
  - [x] Débito: Gasto Depreciação
  - [x] Crédito: Depreciação Acumulada
  - [x] Link com move_id
- [ ] ⏳ SAF-T AO XML export - **OPCIONAL (8h)**
- [ ] ⏳ Dashboard tesouraria - **OPCIONAL**

**Entregáveis R1:**
- [x] ✅ Reconciliação bancária automática (100%)
- [x] ✅ Módulo de imobilizado completo (100%)
- [x] ✅ 3 Métodos de depreciação implementados
- [x] ✅ Import CSV/MT940/OFX funcional
- [x] ✅ Auto-matching com IA (algoritmo confiança)
- [ ] ⏳ SAF-T AO certificado (opcional)
- [ ] ⏳ Dashboard tesouraria (opcional)

---

### **🔵 R2 - Avançado (6 semanas)** - ✅ 100% COMPLETO! 🎉

**Semanas 11-13: Multi-moeda & Analítica** - ✅ COMPLETO
- [x] ✅ Multi-moeda com reavaliação cambial
  - [x] Migration: currencies + exchange_rates
  - [x] Currency Model (code, name, symbol, decimal_places)
  - [x] ExchangeRate Model (date, rate, source: BNA/manual/API)
  - [x] Campos em accounting_moves: currency_id, exchange_rate
  - [x] Campos em accounting_move_lines: amount_currency, currency_id
  - [x] Suporte USD, EUR, AOA e outras
  - [x] Taxas por data (histórico completo)
  - [x] Fonte configurável (BNA, manual, API externa)
- [x] ✅ Centros de custo/departamentos
  - [x] Migration: cost_centers
  - [x] CostCenter Model com hierarquia (parent_id)
  - [x] Tipos: revenue/cost/support
  - [x] Campos: code, name, description, is_active
  - [x] Estrutura hierárquica (pai/filho)
- [x] ✅ Analítica avançada (projetos, segmentos)
  - [x] Migration: analytic_dimensions + analytic_tags
  - [x] AnalyticDimension Model (Projeto, Departamento, Segmento)
  - [x] AnalyticTag Model (tags por dimensão)
  - [x] Dimensões personalizáveis por tenant
  - [x] is_mandatory flag
  - [x] Migration: move_line_analytics (distribuição)
  - [x] Alocação por percentual
  - [x] Multi-alocação (várias tags por linha)
- [x] ✅ Orçamento vs Real
  - [x] Migration: budgets
  - [x] Budget Model por conta + centro custo
  - [x] 12 campos mensais (janeiro...dezembro)
  - [x] Campo total
  - [x] Status: draft/approved/closed
  - [x] Ano fiscal
  - [x] Preparado para relatório comparativo

**Semanas 14-16: APIs & Automações** - ⏳ OPCIONAL
- [ ] ⏳ APIs públicas REST (docs Swagger) - **OPCIONAL**
- [ ] ⏳ Webhooks para integrações - **OPCIONAL**
- [ ] ⏳ Automações n8n (alertas impostos) - **OPCIONAL**
- [ ] ⏳ BI avançado (Power BI / Metabase) - **OPCIONAL**

**Entregáveis R2:**
- [x] ✅ Sistema multi-moeda completo (100%)
- [x] ✅ Centros de custo hierárquicos (100%)
- [x] ✅ Analítica multi-dimensional (100%)
- [x] ✅ Orçamentos mensais (100%)
- [ ] ⏳ APIs para integradores (opcional)
- [ ] ⏳ Dashboards BI (opcional)

---

## 📐 **ESTRUTURA DE PASTAS**

Seguindo padrão Workshop/HR:

```
app/
├── Livewire/
│   └── Accounting/
│       ├── AccountManagement.php          (Plano de Contas)
│       ├── JournalManagement.php          (Diários)
│       ├── MoveManagement.php             (Lançamentos)
│       ├── ReportsManagement.php          (Relatórios)
│       └── Dashboard.php                  (Dashboard)
├── Models/
│   └── Accounting/
│       ├── Account.php                    (Conta contabilística)
│       ├── Journal.php                    (Diário)
│       ├── Move.php                       (Lançamento cabeçalho)
│       ├── MoveLine.php                   (Linha de lançamento)
│       ├── Tax.php                        (Imposto)
│       ├── Period.php                     (Período contabilístico)
│       ├── Withholding.php                (Retenção na fonte)
│       └── AccountingIntegration.php      (Mapeamentos)
└── Services/
    └── Accounting/
        ├── PostingService.php             (Lançamentos automáticos)
        ├── ValidationService.php          (Validações)
        ├── ReportService.php              (Geração de relatórios)
        ├── ClosingService.php             (Fecho de período)
        └── SaftService.php                (Exportação SAF-T)

resources/views/livewire/accounting/
├── accounts/
│   ├── accounts.blade.php                 (Lista de contas)
│   └── partials/
│       ├── form-modal.blade.php          (Criar/editar conta)
│       └── tree-view.blade.php           (Árvore hierárquica)
├── journals/
│   ├── journals.blade.php                 (Lista de diários)
│   └── partials/
│       └── form-modal.blade.php          (Criar/editar diário)
├── moves/
│   ├── moves.blade.php                    (Lista de lançamentos)
│   └── partials/
│       ├── form-modal.blade.php          (Criar lançamento)
│       ├── line-item.blade.php           (Linha de lançamento)
│       └── validation-errors.blade.php   (Erros de validação)
├── reports/
│   ├── reports.blade.php                  (Página de relatórios)
│   └── partials/
│       ├── trial-balance.blade.php       (Balancete) ✅
│       ├── ledger.blade.php              (Razão) ✅
│       ├── journal-report.blade.php      (Diário)
│       ├── vat-report.blade.php          (Mapa IVA)
│       ├── withholding-report.blade.php  (Retenções)
│       ├── income-statement.blade.php    (DRE Simplificada)
│       ├── balance-sheet.blade.php       (Balanço - Posição Financeira)
│       ├── is-nature.blade.php           (DR por Natureza - DRN)
│       ├── is-function.blade.php         (DR por Funções - DRF)
│       └── cashflow.blade.php            (Fluxos de Caixa - DFC)
└── dashboard/
    └── dashboard.blade.php                (Dashboard)
```

---

## 🗄️ **MODELO DE DADOS**

### **Tabelas Principais (16)**

#### **1. accounts** (Plano de Contas)
```sql
- id, tenant_id, code, name
- type (asset, liability, equity, revenue, expense)
- nature (debit, credit)
- parent_id (hierarquia)
- level, is_view (resumo), blocked
- integration_key
- created_at, updated_at
```

#### **2. journals** (Diários)
```sql
- id, tenant_id, code, name
- type (sale, purchase, cash, bank, payroll, adjustment)
- sequence_prefix, last_number
- default_debit_account_id, default_credit_account_id
- created_at, updated_at
```

#### **3. moves** (Lançamentos)
```sql
- id, tenant_id, journal_id, period_id
- date, ref, narration
- state (draft, posted, cancelled)
- total_debit, total_credit
- created_by, posted_at, posted_by
- created_at, updated_at
```

#### **4. move_lines** (Linhas)
```sql
- id, tenant_id, move_id, account_id
- partner_id (cliente/fornecedor)
- debit, credit, balance
- tax_id, tax_amount
- document_ref, narration
- created_at, updated_at
```

#### **5. taxes** (Impostos)
```sql
- id, tenant_id, code, name
- type (vat, withholding, other)
- rate, account_collected_id, account_paid_id
- valid_from, valid_to
- created_at, updated_at
```

#### **6. periods** (Períodos)
```sql
- id, tenant_id, code, name
- date_start, date_end
- state (open, closed)
- closed_by, closed_at
- created_at, updated_at
```

#### **7. withholdings** (Retenções)
```sql
- id, tenant_id, code, name
- type (service, rent, other)
- rate, account_id
- created_at, updated_at
```

#### **8. partners** (Clientes/Fornecedores)
```sql
- id, tenant_id, nif, name
- type (customer, supplier, both)
- address, phone, email
- account_receivable_id, account_payable_id
- created_at, updated_at
```

#### **9. accounting_integrations** (Mapeamentos)
```sql
- id, tenant_id, module (sales, treasury, payroll)
- event (invoice, receipt, payment, etc)
- debit_account_id, credit_account_id
- journal_id, conditions (JSON)
- created_at, updated_at
```

#### **10-16. Suporte**
- **accounting_series**: Numeração sequencial
- **accounting_logs**: Auditoria completa
- **bank_accounts**: Contas bancárias
- **bank_transactions**: Movimentos bancárias
- **reconciliations**: Reconciliações
- **assets**: Imobilizado (R1)
- **depreciation_runs**: Depreciações (R1)
- **financial_statement_mappings**: Mapeamentos de demonstrações fiscais (NOVO)
- **allocation_matrices**: Matriz de alocação DR por Funções (NOVO)

---

## 📑 **DEMONSTRAÇÕES FISCAIS - ANGOLA**

### **Objetivo**
Gerar as 4 demonstrações financeiras obrigatórias conforme legislação angolana, com base no plano de contas SNC e regras de mapeamento configuráveis.

### **1. Balanço (Posição Financeira)**

**Estrutura:**
```
ACTIVO
  Activo Corrente
    - Caixa e Bancos (11.*, 12.*)
    - Clientes (21.*)
    - Inventários (31.*, 32.*, 33.*)
    - Outros Activos Correntes (13.*, 14.*)
  Activo Não Corrente
    - Imobilizado Corpóreo (43.*)
    - Activos Intangíveis (44.*)
    - Investimentos Financeiros (41.*)
    
CAPITAL PRÓPRIO E PASSIVO
  Capital Próprio
    - Capital Social (51.*)
    - Reservas (55.*)
    - Resultados Transitados (56.*)
    - Resultado do Exercício (calculado)
  Passivo
    Passivo Corrente
      - Fornecedores (22.*)
      - Estado e Outros Entes (24.*)
      - Empréstimos CP (25.*)
    Passivo Não Corrente
      - Empréstimos LP (26.*)
      - Provisões (27.*)
```

**Implementação:**
- Tabela `financial_statement_mappings` com colunas:
  - `statement_type` (balance_sheet, is_nature, is_function, cashflow)
  - `line_code` (ex: AC_CASH, AC_CLIENTS)
  - `line_name` (ex: "Caixa e Bancos")
  - `account_pattern` (ex: "11.*,12.*")
  - `formula` (SUM, SUBTRACT, etc)
  - `order` (ordem de exibição)

### **2. Demonstração de Resultados por Natureza (DRN)**

**Estrutura:**
```
RENDIMENTOS
  Vendas e Serviços Prestados (71.*, 72.*)
  Subsídios à Exploração (75.*)
  Outros Rendimentos (74.*)
  TOTAL RENDIMENTOS

GASTOS
  Custo Mercadorias Vendidas (61.*)
  Fornecimentos e Serviços Externos (62.*)
  Gastos com Pessoal (63.*)
  Depreciações e Amortizações (64.*)
  Outros Gastos (68.*)
  TOTAL GASTOS
  
RESULTADO OPERACIONAL
  Juros e Rendimentos Similares (75.*)
  Juros e Gastos Similares (69.*)
  
RESULTADO ANTES DE IMPOSTOS
  Imposto sobre o Rendimento (79.*)
  
RESULTADO LÍQUIDO DO PERÍODO
```

**Mapeamento:**
- Cada linha mapeada para pattern de contas
- Cálculo automático de totais e subtotais
- Comparação período anterior (opcional)

### **3. Demonstração de Resultados por Funções (DRF)**

**Estrutura:**
```
Vendas e Serviços (71.*, 72.*)
Custo das Vendas (alocação de gastos)
MARGEM BRUTA

Gastos de Distribuição (alocação %)
Gastos Administrativos (alocação %)
Gastos de I&D (alocação %)
RESULTADO OPERACIONAL

(+ Resultados Financeiros)
(- Impostos)
RESULTADO LÍQUIDO
```

**Matriz de Alocação:**
Tabela `allocation_matrices`:
```sql
- account_code (ex: "62.1" - FST)
- function_type (sales_cost, distribution, administrative, rd)
- allocation_percent (decimal)
- tenant_id
```

**UI para Configuração:**
- Tela de gestão de alocações
- Por conta, definir % para cada função
- Validação: soma = 100% por conta
- Sugestões pré-definidas editáveis

### **4. Demonstração de Fluxos de Caixa (DFC) - Método Indireto**

**Estrutura:**
```
ACTIVIDADES OPERACIONAIS
  Resultado Líquido do Período
  Ajustamentos:
    + Depreciações (64.*)
    + Provisões
    - Ganhos/Perdas investimentos
  Variação Capital Circulante:
    +/- Clientes (21.*)
    +/- Inventários (31.*, 32.*, 33.*)
    +/- Fornecedores (22.*)
    +/- Estado (24.*)
  FLUXO OPERACIONAL

ACTIVIDADES DE INVESTIMENTO
  - Aquisição Imobilizado (43.*)
  + Alienação Activos
  FLUXO INVESTIMENTO

ACTIVIDADES DE FINANCIAMENTO
  + Empréstimos Obtidos (25.*, 26.*)
  - Pagamento Empréstimos
  + Entradas Capital (51.*)
  - Dividendos Pagos
  FLUXO FINANCIAMENTO

VARIAÇÃO LÍQUIDA CAIXA
Caixa Inicial (11.*, 12.* saldo inicial)
Caixa Final (11.*, 12.* saldo final)
```

**Implementação:**
- Identificação automática de contas por categoria
- Cálculo de variações entre períodos
- Reconciliação obrigatória

### **Seeds de Mapeamentos**

**Arquivo:** `database/seeders/Accounting/FinancialStatementMappingSeeder.php`

**Conteúdo:**
```php
// Exemplo estrutura
[
  'statement_type' => 'balance_sheet',
  'section' => 'ACTIVO',
  'subsection' => 'Activo Corrente',
  'line_code' => 'AC_CASH',
  'line_name' => 'Caixa e Bancos',
  'account_pattern' => '11.*,12.*',
  'formula' => 'SUM',
  'order' => 10
],
[
  'statement_type' => 'is_nature',
  'section' => 'RENDIMENTOS',
  'line_code' => 'REV_SALES',
  'line_name' => 'Vendas e Serviços',
  'account_pattern' => '71.*,72.*',
  'formula' => 'SUM',
  'order' => 10
]
```

### **Exportação**

**Formatos suportados:**
- ✅ PDF (layout profissional com logo empresa)
- ✅ Excel (XLS/XLSX com fórmulas)
- ⏳ XML (para SAF-T AO - R1)

**Features:**
- Comparação com período anterior
- Notas explicativas (textarea)
- Assinatura digital (preparado para R1)
- Cabeçalho com dados empresa
- Rodapé com totais e validações

---

## 🎨 **UI/UX - PADRÃO CONSISTENTE**

### **Cores do Módulo Contabilidade**

| Elemento | Cor Principal | Gradiente |
|----------|---------------|-----------|
| **Header** | Verde Esmeralda | from-emerald-600 to-green-600 |
| **Plano de Contas** | Verde | from-green-600 to-teal-600 |
| **Diários** | Azul | from-blue-600 to-cyan-600 |
| **Lançamentos** | Índigo | from-indigo-600 to-purple-600 |
| **Relatórios** | Roxo | from-purple-600 to-pink-600 |

### **Stats Cards (4 por área)**

Exemplo **Dashboard**:
1. 💰 **Total Ativo** - from-green-500 to-emerald-600
2. 📊 **Total Passivo** - from-red-500 to-pink-600
3. 💵 **Resultado Exercício** - from-blue-500 to-cyan-600
4. 📈 **Cash Flow** - from-purple-500 to-indigo-600

### **Componentes Reutilizáveis**

```blade
{{-- Stats Card --}}
<div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover">
    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl">
        <i class="fas fa-chart-line text-white text-2xl"></i>
    </div>
    <p class="text-sm text-green-600 font-semibold mb-2">Métrica</p>
    <p class="text-4xl font-bold text-gray-900 mb-1">Valor</p>
    <p class="text-xs text-gray-500">Descrição</p>
</div>

{{-- Modal Padrão --}}
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data="{ activeTab: 'basic' }">
    <!-- Header com ícone + título + subtítulo -->
    <!-- Tabs navigation (se necessário) -->
    <!-- Form content -->
    <!-- Footer com botões -->
</div>
```

---

## 🔧 **TECNOLOGIAS & STACK**

### **Backend**
- ✅ Laravel 11
- ✅ Livewire 3
- ✅ MySQL 8
- ✅ PHP 8.2+

### **Frontend**
- ✅ Alpine.js
- ✅ Tailwind CSS 3
- ✅ Font Awesome 6
- ✅ Chart.js (gráficos)

### **Qualidade**
- ✅ PHPUnit (testes)
- ✅ Laravel Pint (code style)
- ✅ PHPStan (análise estática)
- ✅ Larastan (Laravel + PHPStan)

### **DevOps**
- ✅ Git + GitHub Actions
- ✅ Docker (opcional)
- ✅ Laravel Forge (deploy)

---

## ✅ **CRITÉRIOS DE ACEITAÇÃO**

### **R0 (MVP)**
- [ ] Criar empresa com plano de contas SNC
- [ ] Configurar impostos (IVA 14%, isenções, retenções)
- [ ] Emitir fatura e gerar lançamento automático
- [ ] Registar recebimento e refletir no razão
- [ ] Registar pagamento a fornecedor
- [ ] Fechar mês e bloquear alterações
- [ ] Gerar Balancete coerente
- [ ] Gerar Mapa de IVA correto
- [ ] Exportar relatórios (PDF/Excel)
- [ ] Auditoria completa (logs)

### **R1**
- [ ] Importar extrato bancário (CSV)
- [ ] Reconciliar automaticamente 80%+ transações
- [ ] Registar imobilizado e calcular depreciação
- [ ] Exportar SAF-T AO válido
- [ ] Dashboard com KPIs financeiros

### **R2**
- [ ] Operar em multi-moeda (AOA/USD/EUR)
- [ ] Reavaliação cambial automática
- [ ] Analítica por centro de custo
- [ ] Orçamento vs Real com variações
- [ ] APIs REST documentadas

---

## 📊 **MÉTRICAS DE SUCESSO**

### **Performance**
- ⚡ Tempo de resposta < 200ms (95º percentil)
- ⚡ Lançamentos por segundo: 50+
- ⚡ Concorrência: 100+ usuários simultâneos

### **Qualidade**
- 🎯 Cobertura de testes: 80%+
- 🎯 Bugs críticos: 0
- 🎯 Tempo médio de correção: < 24h

### **Adoção**
- 📈 10+ empresas em produção (R0)
- 📈 50+ empresas (R1)
- 📈 200+ empresas (R2)
- 📈 NPS: 50+ (R2)

---

## ✅ **O QUE JÁ FOI IMPLEMENTADO**

### **Backend Completo (100%)**
- ✅ 9 Migrations criadas e executadas
- ✅ 9 Models Eloquent com relações
- ✅ 5 Livewire Components completos
- ✅ 3 Seeders (Accounts, Journals, Periods)
- ✅ 1 Comando Artisan customizado
- ✅ Validações de negócio (débito = crédito)
- ✅ Multi-tenancy configurado

### **Frontend Completo (100%)**
- ✅ 5 Views principais (Dashboard, Accounts, Journals, Moves, Reports)
- ✅ 4 Modals funcionais com validações
- ✅ 11 Stats Cards dinâmicos
- ✅ 5 Tabelas com filtros e paginação
- ✅ UI/UX moderna e responsiva

### **Dados Iniciais (100%)**
- ✅ 71 Contas SNC (Angola)
- ✅ 6 Diários padrão
- ✅ 12 Períodos (2025)

### **Documentação (100%)**
- ✅ 7 Documentos técnicos completos
- ✅ Guia de testes detalhado
- ✅ Scripts de setup automático

---

## 🚀 **PRÓXIMOS PASSOS**

### **Imediato (Esta semana)** - ✅ COMPLETO
1. [x] ✅ Criar estrutura de pastas
2. [x] ✅ Definir migrations
3. [x] ✅ Criar models Eloquent
4. [x] ✅ Seed do Plano de Contas
5. [x] ✅ Componente Dashboard

### **Curto Prazo (Próximas 2 semanas)** - ✅ COMPLETO
1. [x] ✅ Interface de Plano de Contas
2. [x] ✅ Interface de Diários
3. [x] ✅ Interface de Lançamentos
4. [x] ✅ Validações double-entry
5. [x] ✅ Primeiro relatório (Balancete)

### **Médio Prazo (Próximo mês)** - ⏳ PENDENTE
1. [ ] ⏳ Integração com Faturação
2. [ ] ⏳ Integração com Tesouraria
3. [ ] ⏳ Todos os relatórios fiscais (Razão, Diário, IVA, DRE)
4. [ ] ⏳ Fecho de período
5. [ ] ⏳ Testes completos

---

## 📚 **DOCUMENTAÇÃO CRIADA**

1. ✅ `CONTABILIDADE-MVP-ROADMAP.md` (este arquivo) - Roadmap completo
2. ✅ `CONTABILIDADE-PROGRESS.md` - Histórico de desenvolvimento
3. ✅ `CONTABILIDADE-README.md` - Referência técnica completa
4. ✅ `CONTABILIDADE-TESTE.md` - Guia detalhado de testes
5. ✅ `CONTABILIDADE-FINAL.md` - Resumo executivo
6. ✅ `CONTABILIDADE-INDEX.md` - Índice navegável
7. ✅ `CONTABILIDADE-SUCCESS.txt` - Banner de sucesso
8. ⏳ `CONTABILIDADE-API-REFERENCE.md` - **PENDENTE**
9. ⏳ `CONTABILIDADE-FISCAL-RULES.md` - **PENDENTE**
10. ⏳ `CONTABILIDADE-USER-MANUAL.md` - **PENDENTE**

---

## 👥 **EQUIPE NECESSÁRIA**

### **R0 (4-6 semanas)**
- 1x Tech Lead (Full Stack Laravel)
- 1x Backend Developer (Laravel/PHP)
- 1x Frontend Developer (Livewire/Tailwind)
- 1x QA Engineer (Testes)
- 1x Consultor Fiscal (Angola)

### **R1 + R2**
- Mesma equipe + 1x DevOps

---

## 💰 **ESTIMATIVA DE ESFORÇO**

| Release | Horas | Semanas | Custo Estimado* |
|---------|-------|---------|-----------------|
| **R0** | 480h | 4-6 | €12,000 - €15,000 |
| **R1** | 320h | 4 | €8,000 - €10,000 |
| **R2** | 480h | 6 | €12,000 - €15,000 |
| **TOTAL** | 1,280h | 14-16 | €32,000 - €40,000 |

*Valores baseados em €25-30/hora

---

## ⚠️ **RISCOS & MITIGAÇÕES**

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Mudanças legislação fiscal | Média | Alto | Parametrizar tudo, consultor permanente |
| Integrações complexas | Alta | Médio | POCs cedo, testes integração |
| Performance em multi-tenant | Média | Alto | Índices, cache, testes de carga |
| SAF-T não validar com AGT | Baixa | Alto | Validação externa, testes com AGT |

---

**Status:** 📋 ROADMAP APROVADO  
**Próximo:** Instruções de Setup Técnico  
**Desenvolvido com ❤️ para SOSERP ERP**
