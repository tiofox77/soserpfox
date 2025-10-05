# 📦 MÓDULO DE IMPORTAÇÃO DE MERCADORIAS - ANGOLA
## Planejamento Completo e Especificações Técnicas

---

## 📋 ÍNDICE

1. [Visão Geral](#visão-geral)
2. [Legislação Angolana Aplicável](#legislação-angolana-aplicável)
3. [Arquitetura do Sistema](#arquitetura-do-sistema)
4. [Fluxo Completo de Importação](#fluxo-completo-de-importação)
5. [Documento Único (DU)](#documento-único-du)
6. [Checklist Alfandegário](#checklist-alfandegário)
7. [Documentação Necessária](#documentação-necessária)
8. [Status e Estados](#status-e-estados)
9. [Cálculos e Custos](#cálculos-e-custos)
10. [Interface e UX](#interface-e-ux)
11. [Integrações](#integrações)
12. [Roadmap de Implementação](#roadmap-de-implementação)

---

## 🎯 VISÃO GERAL

### Objetivo
Sistema completo de gestão de importação de mercadorias para Angola, com controle de todas as etapas desde a cotação até a entrada em armazém, incluindo desembaraço alfandegário conforme legislação angolana.

### Características Principais
- ✅ Gestão completa do processo de importação
- ✅ Controle de Documento Único (DU)
- ✅ Timeline visual de acompanhamento
- ✅ Checklist alfandegário automatizado
- ✅ Gestão de documentos obrigatórios
- ✅ Cálculo automático de custos e taxas
- ✅ Integração com compras e inventário
- ✅ Notificações de prazos e pendências

---

## 📜 LEGISLAÇÃO ANGOLANA APLICÁVEL

### 1. Código Aduaneiro Angolano
- **Decreto Presidencial nº 207/18** - Regulamento do Código Aduaneiro
- **Sistema Harmonizado (SH)** - Classificação de mercadorias
- **Documento Único (DU)** - Obrigatório para todas importações

### 2. Impostos e Taxas
- **Direitos Aduaneiros (DA)** - Variável conforme código HS
- **Imposto de Consumo (IC)** - Produtos específicos
- **Imposto de Selo** - 0,3% sobre valor CIF
- **IVA** - 14% sobre valor CIF + DA + IC

### 3. Órgãos Envolvidos
- **AGT (Administração Geral Tributária)** - Emissão de DU
- **Alfândega de Angola** - Desembaraço
- **MSPT (Ministério dos Transportes)** - Licenças
- **MINCO (Ministério do Comércio)** - Autorizações específicas

### 4. Documentos Obrigatórios
1. Fatura Comercial (Commercial Invoice)
2. Packing List (Lista de Embalagem)
3. Bill of Lading (BL) ou AWB
4. Certificado de Origem
5. Documento Único (DU)
6. Comprovativo de Pagamento
7. Licenças específicas (se aplicável)

---

## 🏗️ ARQUITETURA DO SISTEMA

### Estrutura de Dados

#### **Tabela: invoicing_imports**
**Campos Principais:**
- Identificação e Referências
- Dados de Embarque e Transporte
- Documento Único (DU)
- Custos e Taxas
- Despachante Aduaneiro
- Status e Timeline
- Documentação (JSON)
- Checklist (JSON)

#### **Tabela: invoicing_import_items**
**Campos:**
- Produto vinculado
- Código HS (Sistema Harmonizado)
- Quantidade, peso, valor
- País de origem do item

#### **Tabela: invoicing_import_history**
**Campos:**
- Timeline de eventos
- Mudanças de status
- Upload de documentos
- Comentários e observações

### Models e Relacionamentos

```
Import
├─ belongsTo: Supplier
├─ belongsTo: PurchaseInvoice (opcional)
├─ belongsTo: Warehouse
├─ hasMany: ImportItem
├─ hasMany: ImportHistory
└─ belongsTo: User (creator, approver)

ImportItem
├─ belongsTo: Import
└─ belongsTo: Product

ImportHistory
├─ belongsTo: Import
└─ belongsTo: User
```

---

## 🔄 FLUXO COMPLETO DE IMPORTAÇÃO

### **Etapa 1: COTAÇÃO** 📋
**Status:** `quotation`
**Ações:**
- [ ] Solicitar cotação ao fornecedor
- [ ] Receber proforma invoice
- [ ] Calcular custos estimados
- [ ] Aprovar orçamento

**Documentos:**
- Proforma Invoice
- Cotação de Frete
- Cotação de Seguro

---

### **Etapa 2: PEDIDO REALIZADO** 📝
**Status:** `order_placed`
**Ações:**
- [ ] Emitir Purchase Order (PO)
- [ ] Enviar PO ao fornecedor
- [ ] Receber confirmação do fornecedor
- [ ] Definir Incoterm (FOB, CIF, etc)

**Documentos:**
- Purchase Order
- Confirmação do Fornecedor

---

### **Etapa 3: PAGAMENTO PENDENTE** 💰
**Status:** `payment_pending`
**Ações:**
- [ ] Preparar documentação bancária
- [ ] Abrir Carta de Crédito (se aplicável)
- [ ] Processar transferência internacional
- [ ] Aguardar confirmação do fornecedor

**Documentos:**
- Swift de Pagamento
- Carta de Crédito (LC)
- Comprovativo Bancário

---

### **Etapa 4: PAGAMENTO CONFIRMADO** ✅
**Status:** `payment_confirmed`
**Ações:**
- [ ] Confirmar recebimento pelo fornecedor
- [ ] Solicitar data de embarque
- [ ] Contratar frete (se FOB)
- [ ] Contratar seguro

**Documentos:**
- Confirmação de Pagamento
- Booking de Frete
- Apólice de Seguro

---

### **Etapa 5: EM TRÂNSITO** 🚢
**Status:** `in_transit`
**Ações:**
- [ ] Receber Bill of Lading (BL)
- [ ] Receber Fatura Comercial
- [ ] Receber Packing List
- [ ] Receber Certificado de Origem
- [ ] Rastrear contentor
- [ ] Contratar despachante aduaneiro

**Documentos:**
- Bill of Lading (BL/AWB)
- Commercial Invoice
- Packing List
- Certificate of Origin
- Certificado de Seguro

**Tracking:**
- Número do Contentor
- Navio/Voo
- Porto de Origem → Porto Destino
- ETA (Estimated Time of Arrival)

---

### **Etapa 6: DESEMBARAÇO PENDENTE** 🏛️
**Status:** `customs_pending`
**Ações:**
- [ ] Submeter documentação à alfândega
- [ ] Emitir Documento Único (DU)
- [ ] Calcular impostos devidos
- [ ] Pagar taxas alfandegárias
- [ ] Aguardar análise documental

**Documentos:**
- Documento Único (DU)
- Comprovativo de Pagamento de Impostos
- Declaração de Valor Aduaneiro

**Custos Calculados:**
- Direitos Aduaneiros
- Imposto de Consumo
- Imposto de Selo
- Taxa do Despachante

---

### **Etapa 7: INSPEÇÃO ALFANDEGÁRIA** 🔍
**Status:** `customs_inspection`
**Ações:**
- [ ] Acompanhar inspeção física
- [ ] Apresentar documentação complementar
- [ ] Resolver pendências
- [ ] Aguardar liberação

**Possíveis Ações:**
- Inspeção documental
- Inspeção física da mercadoria
- Análise laboratorial (produtos específicos)
- Vistoria sanitária (alimentos/medicamentos)

---

### **Etapa 8: DESEMBARAÇADO** ✅
**Status:** `customs_cleared`
**Ações:**
- [ ] Receber liberação alfandegária
- [ ] Agendar retirada no porto
- [ ] Contratar transporte interno
- [ ] Preparar armazém

**Documentos:**
- Termo de Liberação
- DU Desembaraçado
- Guia de Retirada

---

### **Etapa 9: NO ARMAZÉM** 📦
**Status:** `in_warehouse`
**Ações:**
- [ ] Receber mercadoria no armazém
- [ ] Conferir quantidades
- [ ] Verificar avarias
- [ ] Registrar entrada no sistema
- [ ] Atualizar inventário

**Conferência:**
- Quantidade física vs. documentos
- Estado da mercadoria
- Validade (se aplicável)
- Registro fotográfico

---

### **Etapa 10: CONCLUÍDO** 🎉
**Status:** `completed`
**Ações:**
- [ ] Fechar processo de importação
- [ ] Arquivar documentação
- [ ] Atualizar custo dos produtos
- [ ] Gerar relatório final

**Documentos Finais:**
- Relatório de Importação
- Todos documentos arquivados
- Comprovativo de entrada em stock

---

## 📄 DOCUMENTO ÚNICO (DU)

### O que é o DU?
O **Documento Único (DU)** é o documento alfandegário obrigatório em Angola para todas as operações de importação, exportação e trânsito de mercadorias.

### Informações no DU
1. **Identificação do Importador**
   - Nome/Razão Social
   - NIF
   - Endereço

2. **Identificação da Mercadoria**
   - Descrição detalhada
   - Código HS (6 dígitos mínimo)
   - Quantidade
   - Peso bruto e líquido
   - Valor FOB/CIF
   - País de origem

3. **Dados do Embarque**
   - Porto de embarque
   - Porto de desembarque
   - Navio/Voo
   - Data de embarque
   - Bill of Lading

4. **Cálculo de Impostos**
   - Valor CIF
   - Direitos Aduaneiros (%)
   - Imposto de Consumo (se aplicável)
   - Imposto de Selo (0,3%)
   - IVA (14%)

5. **Despachante**
   - Nome do despachante aduaneiro
   - Licença
   - Data de submissão

### Fluxo do DU
```
1. Preparação → Reunir todos documentos
2. Submissão → Enviar ao despachante
3. Análise → AGT analisa documentação
4. Cálculo → Sistema calcula impostos
5. Pagamento → Pagar guias de impostos
6. Emissão → DU é emitido
7. Desembaraço → Mercadoria liberada
```

---

## ✅ CHECKLIST ALFANDEGÁRIO

### **Antes do Embarque**
- [ ] Proforma Invoice recebida
- [ ] Purchase Order emitido
- [ ] Pagamento confirmado
- [ ] Frete contratado
- [ ] Seguro contratado
- [ ] Despachante contratado

### **Documentos de Embarque**
- [ ] Commercial Invoice (original)
- [ ] Packing List
- [ ] Bill of Lading (BL) ou AWB
- [ ] Certificado de Origem
- [ ] Certificado de Seguro
- [ ] Certificados específicos (se aplicável)

### **Documentos Fiscais Angola**
- [ ] NIF do importador atualizado
- [ ] Certidão negativa de dívidas fiscais
- [ ] Licença de importação (produtos restritos)
- [ ] Autorização MINCO (se aplicável)

### **Processo Alfandegário**
- [ ] Documentação entregue ao despachante
- [ ] DU submetido no sistema AGT
- [ ] Impostos calculados
- [ ] Guias de pagamento emitidas
- [ ] Impostos pagos
- [ ] DU desembaraçado
- [ ] Termo de liberação recebido

### **Retirada e Entrega**
- [ ] Transporte contratado
- [ ] Agenda de retirada confirmada
- [ ] Seguro de transporte interno
- [ ] Armazém preparado
- [ ] Equipe de recebimento alocada

### **Pós-Desembaraço**
- [ ] Mercadoria conferida
- [ ] Entrada registrada no sistema
- [ ] Stock atualizado
- [ ] Documentos arquivados
- [ ] Custos lançados na contabilidade

---

## 📁 DOCUMENTAÇÃO NECESSÁRIA

### **Categoria: Comercial**
1. **Proforma Invoice**
   - Antes do pedido
   - Válida por 30 dias
   
2. **Purchase Order (PO)**
   - Emitido pela empresa
   - Assinado e carimbado

3. **Commercial Invoice**
   - Original do fornecedor
   - Valores em USD normalmente
   - Assinada e carimbada

4. **Packing List**
   - Detalhamento de embalagens
   - Peso bruto e líquido
   - Dimensões

### **Categoria: Transporte**
5. **Bill of Lading (BL)**
   - Marítimo: Ocean BL
   - Aéreo: Air Waybill (AWB)
   - Original ou cópia negociável

6. **Booking Confirmation**
   - Reserva de espaço
   - Dados do contentor

### **Categoria: Seguro**
7. **Apólice de Seguro**
   - Cobertura CIF
   - Vigência durante transporte

8. **Certificado de Seguro**
   - Específico para o embarque

### **Categoria: Origem**
9. **Certificado de Origem**
   - Emitido por câmara de comércio
   - País de origem da mercadoria

10. **Certificados Sanitários**
    - Alimentos
    - Cosméticos
    - Medicamentos

### **Categoria: Alfândega Angola**
11. **Documento Único (DU)**
    - Emitido pela AGT
    - Número único

12. **Comprovativo de Pagamento de Impostos**
    - Direitos Aduaneiros
    - Imposto de Consumo
    - Imposto de Selo
    - IVA

13. **Declaração de Valor Aduaneiro**
    - Se valor > 5.000 USD

14. **Licença de Importação**
    - Produtos controlados
    - Emitida pelo MINCO

### **Categoria: Bancária**
15. **Swift de Pagamento**
    - Comprovativo transferência
    
16. **Carta de Crédito**
    - Se aplicável

---

## 🎨 STATUS E ESTADOS

### Cores e Ícones por Status

| Status | Cor | Ícone | Descrição |
|--------|-----|-------|-----------|
| `quotation` | Cinza | 📋 | Cotação em análise |
| `order_placed` | Azul | 📝 | Pedido confirmado |
| `payment_pending` | Amarelo | ⏳ | Aguardando pagamento |
| `payment_confirmed` | Verde Claro | ✅ | Pagamento confirmado |
| `in_transit` | Azul Oceano | 🚢 | Mercadoria em trânsito |
| `customs_pending` | Laranja | 🏛️ | Aguardando desembaraço |
| `customs_inspection` | Laranja Escuro | 🔍 | Em inspeção alfandegária |
| `customs_cleared` | Verde | ✅ | Desembaraçado |
| `in_warehouse` | Verde Escuro | 📦 | Mercadoria no armazém |
| `completed` | Verde Completo | 🎉 | Processo concluído |
| `cancelled` | Vermelho | ❌ | Importação cancelada |

---

## 💰 CÁLCULOS E CUSTOS

### Fórmulas Utilizadas

#### 1. **Valor CIF**
```
CIF = FOB + Frete + Seguro
```

#### 2. **Direitos Aduaneiros (DA)**
```
DA = CIF × Taxa_DA_do_Produto(%)
```
*Taxa varia conforme código HS (5% a 50%)*

#### 3. **Imposto de Consumo (IC)**
```
IC = (CIF + DA) × Taxa_IC(%)
```
*Apenas produtos específicos (bebidas, tabaco, etc)*

#### 4. **Imposto de Selo**
```
Imposto_Selo = CIF × 0,3%
```

#### 5. **IVA (14%)**
```
IVA = (CIF + DA + IC) × 14%
```

#### 6. **Total de Impostos**
```
Total_Impostos = DA + IC + Imposto_Selo + IVA
```

#### 7. **Custo Total da Importação**
```
Custo_Total = CIF + Total_Impostos + Taxa_Despachante + Outras_Despesas
```

### Exemplo Prático

**Produto:** Computadores  
**Código HS:** 8471.30.00 (DA = 10%)

```
FOB:           50.000,00 USD
Frete:          3.000,00 USD
Seguro:           500,00 USD
─────────────────────────────
CIF:           53.500,00 USD

Direitos Aduaneiros (10%):    5.350,00 USD
Imposto Selo (0,3%):            160,50 USD
IVA (14% sobre 58.850):       8.239,00 USD
─────────────────────────────
Total Impostos:              13.749,50 USD

Taxa Despachante:             1.000,00 USD
Transporte Interno:             500,00 USD
Outras Despesas:                250,00 USD
─────────────────────────────
CUSTO TOTAL:                 68.999,50 USD
```

---

## 🎨 INTERFACE E UX

### Dashboard de Importações

**Cards de Estatísticas:**
- Total de Importações Ativas
- Em Trânsito
- Pendentes Desembaraço
- Valor Total em Processo

### Listagem

**Colunas:**
1. Número da Importação
2. Fornecedor
3. Valor CIF
4. Status (com badge colorido)
5. ETA (Data Prevista)
6. Ações

**Filtros:**
- Status
- Fornecedor
- Data de Pedido (range)
- Porto de Destino

### Timeline Visual

```
📋 Cotação ─────> 📝 Pedido ─────> 💰 Pagamento ─────> 🚢 Trânsito
                                                           │
📦 Armazém <───── ✅ Desembaraçado <───── 🔍 Inspeção <────┘
```

**Para cada etapa:**
- ✅ Verde: Concluída
- 🟡 Amarelo: Em andamento
- ⚪ Cinza: Pendente

### Modal de Detalhes

**Tabs:**
1. **Informações Gerais**
2. **Documentos** (upload/download)
3. **Custos e Taxas**
4. **Timeline** (histórico completo)
5. **Checklist**
6. **DU - Documento Único**

---

## 🔗 INTEGRAÇÕES

### 1. **Compras → Importação**
```
Purchase Invoice (Compra Internacional)
    ↓
Botão "Criar Importação"
    ↓
Importação criada com dados da fatura
    ├─ Fornecedor
    ├─ Produtos
    ├─ Valores
    └─ Referência vinculada
```

### 2. **Importação → Inventário**
```
Status: completed
    ↓
Entrada Automática no Armazém
    ├─ Atualiza Stock
    ├─ Registra Movimento
    ├─ Atualiza Custo do Produto
    └─ Vincula à Importação
```

### 3. **Importação → Treasury**
```
Pagamentos de Impostos
    ↓
Cria Transações no Treasury
    ├─ Direitos Aduaneiros
    ├─ Imposto de Consumo
    ├─ Imposto de Selo
    ├─ Taxa Despachante
    └─ Tipo: expense
```

### 4. **Importação → Notificações**
```
Eventos Importantes:
├─ DU aprovado
├─ Impostos pagos
├─ Mercadoria liberada
├─ Prazo próximo
└─ Pendência detectada
```

---

## 🗺️ ROADMAP DE IMPLEMENTAÇÃO

### **FASE 1: Estrutura Base** ✅ CONCLUÍDA
- [x] Migration completa (3 tabelas)
- [x] Models (Import, ImportItem, ImportHistory)
- [x] Documento de planejamento

### **FASE 2: CRUD Básico** ⏳ PRÓXIMA
- [ ] Componente Livewire: Imports.php
- [ ] View: listagem de importações
- [ ] Modal: criar/editar importação
- [ ] Formulário com wizard (3 etapas)

### **FASE 3: Timeline e Status**
- [ ] Sistema de mudança de status
- [ ] Timeline visual
- [ ] Histórico de eventos
- [ ] Notificações de mudanças

### **FASE 4: Documentos**
- [ ] Upload de documentos
- [ ] Categorização automática
- [ ] Download de documentos
- [ ] Preview de PDFs

### **FASE 5: DU - Documento Único**
- [ ] Formulário específico para DU
- [ ] Cálculo automático de impostos
- [ ] Validação de campos obrigatórios
- [ ] Geração de PDF do DU

### **FASE 6: Custos e Cálculos**
- [ ] Formulário de custos
- [ ] Cálculo automático CIF
- [ ] Cálculo de impostos por código HS
- [ ] Total de custo da importação
- [ ] Integração com custos de produtos

### **FASE 7: Checklist**
- [ ] Checklist interativo
- [ ] Marcação de itens
- [ ] Progresso visual
- [ ] Alertas de pendências

### **FASE 8: Integrações**
- [ ] Botão em Compras → Criar Importação
- [ ] Importação concluída → Entrada em Stock
- [ ] Pagamentos → Transações Treasury
- [ ] Dashboard com métricas

### **FASE 9: Relatórios**
- [ ] Relatório de importações
- [ ] Relatório de custos
- [ ] Relatório de impostos pagos
- [ ] Exportação para Excel

### **FASE 10: Melhorias**
- [ ] Rastreamento de contentor (API)
- [ ] Notificações por email
- [ ] Alertas de prazos
- [ ] Integração com despachante (API)

---

## 📊 MÉTRICAS E KPIs

### Dashboard Executivo
- Valor total em importações ativas
- Tempo médio de desembaraço
- Custo médio de importação
- Taxa média de impostos (%)
- Número de inspeções alfandegárias

### Relatórios Analíticos
- Importações por fornecedor
- Importações por país de origem
- Custos por tipo (frete, impostos, etc)
- Lead time por status
- Performance do despachante

---

## 🔐 SEGURANÇA E CONFORMIDADE

### Auditoria
- Log completo de todas ações
- Histórico de mudanças
- Usuário responsável por cada ação
- Timestamp de cada evento

### Permissões
- `import.view` - Ver importações
- `import.create` - Criar importação
- `import.edit` - Editar importação
- `import.delete` - Eliminar importação
- `import.approve` - Aprovar importação
- `import.upload_documents` - Carregar documentos
- `import.manage_du` - Gerir DU

### Backup
- Documentos armazenados em storage seguro
- Backup automático de anexos
- Versionamento de documentos

---

## 📝 NOTAS FINAIS

Este módulo é **fundamental** para empresas que:
- Importam mercadorias regularmente
- Precisam controlar custos de importação
- Querem conformidade com legislação angolana
- Necessitam rastreabilidade completa

**Complexidade:** Alta  
**Tempo Estimado:** 3-4 semanas  
**Prioridade:** Média-Alta  
**Dependências:** Compras, Inventário, Treasury

---

**Versão:** 1.0  
**Data:** 05/10/2025  
**Status:** Planejamento Completo ✅  
**Próximo Passo:** Implementação FASE 2 (CRUD Básico)
