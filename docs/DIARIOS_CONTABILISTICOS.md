# Diários Contabilísticos Padrão

## 📋 Visão Geral

O SOSERP possui **13 diários contabilísticos padrão** que cobrem todas as operações contabilísticas típicas de uma empresa em Angola.

## 🗂️ Lista de Diários

### **Diários Principais**

#### 01 - Diário Geral (DG-)
- **Tipo**: General
- **Uso**: Lançamentos contabilísticos gerais que não se enquadram nos outros diários específicos
- **Prefixo**: DG-0001, DG-0002, etc.

#### 02 - Diário de Caixa (CX-)
- **Tipo**: Cash
- **Uso**: Todos os movimentos de caixa (entradas e saídas)
- **Contas padrão**: Conta 11 (Caixa)
- **Prefixo**: CX-0001, CX-0002, etc.

#### 03 - Diário de Bancos (BC-)
- **Tipo**: Bank
- **Uso**: Movimentos bancários (depósitos, transferências, cheques)
- **Contas padrão**: Conta 12 (Depósitos Bancários)
- **Prefixo**: BC-0001, BC-0002, etc.

#### 04 - Diário de Vendas (VD-)
- **Tipo**: Sale
- **Uso**: Faturação de vendas e prestação de serviços
- **Contas padrão**: Conta 21 (Clientes)
- **Prefixo**: VD-0001, VD-0002, etc.

#### 05 - Diário de Compras (CP-)
- **Tipo**: Purchase
- **Uso**: Registo de compras e aquisições
- **Contas padrão**: Conta 31 (Fornecedores)
- **Prefixo**: CP-0001, CP-0002, etc.

---

### **Diários de Controle e Gestão**

#### 06 - Diário de Salários e Ordenados (SAL-)
- **Tipo**: Payroll
- **Uso**: Processamento de folhas de pagamento
- **Inclui**: Salários, INSS, IRT, subsídios
- **Prefixo**: SAL-0001, SAL-0002, etc.

#### 07 - Diário de IVA (IVA-)
- **Tipo**: Tax
- **Uso**: Apuramento e regularização do IVA
- **Inclui**: IVA liquidado, IVA dedutível, IVA a pagar/recuperar
- **Prefixo**: IVA-0001, IVA-0002, etc.

#### 08 - Diário de Depreciações e Amortizações (DEP-)
- **Tipo**: Depreciation
- **Uso**: Registo mensal/anual de depreciações
- **Inclui**: Imobilizado corpóreo e incorpóreo
- **Prefixo**: DEP-0001, DEP-0002, etc.

---

### **Diários Especiais**

#### 09 - Diário de Operações Diversas (OD-)
- **Tipo**: Miscellaneous
- **Uso**: Operações variadas que não se enquadram nos diários anteriores
- **Exemplos**: Provisões, imparidades, transferências internas
- **Prefixo**: OD-0001, OD-0002, etc.

#### 10 - Diário de Ajustes e Correções (AJ-)
- **Tipo**: Adjustment
- **Uso**: Correção de erros contabilísticos
- **Nota**: Requer autorização e justificação
- **Prefixo**: AJ-0001, AJ-0002, etc.

#### 11 - Diário de Regularização (REG-)
- **Tipo**: Regularization
- **Uso**: Regularizações de fim de período
- **Exemplos**: Acréscimos, diferimentos, reclassificações
- **Prefixo**: REG-0001, REG-0002, etc.

#### 12 - Diário de Abertura (ABT-)
- **Tipo**: Opening
- **Uso**: Lançamento de saldos iniciais no início do exercício
- **Frequência**: Uma vez por ano
- **Prefixo**: ABT-0001, ABT-0002, etc.

#### 13 - Diário de Encerramento (ENC-)
- **Tipo**: Closing
- **Uso**: Fecho de contas no fim do exercício
- **Inclui**: Apuramento de resultados, transferências para balanço
- **Prefixo**: ENC-0001, ENC-0002, etc.

---

## 🔧 Instalação/Atualização

### Primeira Instalação
```bash
php artisan db:seed --class=Database\Seeders\Accounting\JournalSeeder
```

### Atualizar Diários Existentes
```bash
# Windows
update_journals.bat

# Linux/Mac
php artisan db:seed --class=Database\Seeders\Accounting\JournalSeeder
```

> **Nota**: O seeder preserva diários que já têm lançamentos contabilísticos.

---

## ⚙️ Funcionalidades

### Sequenciamento Automático
Cada diário mantém sua própria sequência:
- `last_number`: Último número usado
- `sequence_prefix`: Prefixo do documento (ex: VD-, CX-)

### Contas Padrão
Alguns diários têm contas pré-configuradas:
- **Caixa (02)**: Conta 11
- **Banco (03)**: Conta 12
- **Vendas (04)**: Conta 21 (Clientes)
- **Compras (05)**: Conta 31 (Fornecedores)

### Tipos de Diário
- `general`: Geral
- `cash`: Caixa
- `bank`: Banco
- `sale`: Vendas
- `purchase`: Compras
- `payroll`: Salários
- `tax`: Impostos
- `depreciation`: Depreciações
- `miscellaneous`: Diversos
- `adjustment`: Ajustes
- `regularization`: Regularizações
- `opening`: Abertura
- `closing`: Encerramento

---

## 📊 Estrutura da Tabela

```sql
accounting_journals:
- id
- tenant_id
- code (01, 02, 03...)
- name
- type
- sequence_prefix (DG-, CX-, BC-...)
- last_number
- default_debit_account_id
- default_credit_account_id
- active
- created_at
- updated_at
```

---

## 🎯 Boas Práticas

### ✅ Fazer
- Usar o diário apropriado para cada tipo de operação
- Manter a sequência cronológica dos lançamentos
- Documentar lançamentos no Diário de Ajustes
- Fazer backup antes de encerramento de exercício

### ❌ Evitar
- Misturar tipos de operações em diários errados
- Deletar diários com lançamentos
- Alterar códigos de diários em uso
- Usar Diário Geral para tudo

---

## 🔄 Integração Automática

Os diários são usados automaticamente pelos módulos:

- **Faturação** → Diário 04 (Vendas)
- **Compras** → Diário 05 (Compras)
- **Caixa/POS** → Diário 02 (Caixa)
- **Bancos** → Diário 03 (Bancos)
- **RH/Folha** → Diário 06 (Salários)

---

## 📞 Suporte

Para questões sobre diários contabilísticos:
- Consulte o PGC-NIRF (Plano Geral de Contabilidade angolano)
- Contate o suporte técnico SOSERP
