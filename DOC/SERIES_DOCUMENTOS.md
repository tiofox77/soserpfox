# Sistema de Séries de Documentos

## 📋 Visão Geral

O sistema agora utiliza séries para gerar números de documentos fiscais (proformas, faturas, recibos, notas de crédito/débito) automaticamente, conforme padrões profissionais de faturação.

## 🎯 O Que Foi Implementado

### **1. Gestão de Séries**
- ✅ Interface completa para criar/editar séries
- ✅ Tipos: Faturas, Proformas, Recibos, N. Crédito, N. Débito
- ✅ Numeração automática com incremento
- ✅ Séries padrão por tipo de documento
- ✅ Reset anual opcional

### **2. Integração Automática**
- ✅ Proformas usam série automaticamente
- ✅ Se série padrão existe, é usada automaticamente
- ✅ Fallback para numeração antiga se não houver série

---

## 🚀 Como Configurar Séries

### **Passo 1: Acessar Gestão de Séries**

**Menu:** Faturação → Configurações → Séries de Documentos

Ou acesse diretamente:
```
http://soserp.test/invoicing/series
```

### **Passo 2: Criar Primeira Série**

1. Clique em **"Nova Série"**
2. Preencha os dados:

| Campo | Exemplo | Descrição |
|-------|---------|-----------|
| **Tipo de Documento** | Proforma (PRF) | Tipo de documento a gerar |
| **Prefixo** | PRF | Prefixo do documento (2-3 letras) |
| **Código da Série** | A | Identifica a série (A, B, 01, etc) |
| **Nome da Série** | Vendas Loja | Nome descritivo |
| **Próximo Número** | 1 | Próximo número a gerar |
| **Zeros à Esquerda** | 6 | Quantidade de dígitos (000001) |

3. **Opções:**
   - ☑️ **Incluir ano**: PRF A/2025/000001 (vs PRF A/000001)
   - ☑️ **Reset anual**: Reinicia de 1 a cada ano
   - ☑️ **Série padrão**: Usa automaticamente ao criar documentos
   - ☑️ **Série ativa**: Disponível para uso

4. Clique em **"Salvar"**

### **Passo 3: Resultado**

**Pré-visualização:**
```
PRF A/2025/000001
```

Ao criar próxima proforma:
```
PRF A/2025/000002
```

---

## 📊 Exemplos de Configuração

### **Exemplo 1: Proformas com Ano**
```
Tipo: Proforma (PRF)
Prefixo: PRF
Código: A
Incluir ano: ✓
Próximo nº: 1
Padding: 6

Resultado: PRF A/2025/000001
```

### **Exemplo 2: Faturas sem Ano**
```
Tipo: Fatura (FT)
Prefixo: FT
Código: B
Incluir ano: ✗
Próximo nº: 100
Padding: 5

Resultado: FT B/00100
```

### **Exemplo 3: Múltiplas Séries**

**Vendas Loja:**
```
PRF A/2025/000001
PRF A/2025/000002
...
```

**Vendas Online:**
```
PRF B/2025/000001
PRF B/2025/000002
...
```

---

## 🔧 Como o Sistema Funciona

### **1. Criação Automática de Número**

Quando salva uma proforma:

```
1. Sistema verifica se existe série padrão para "proforma"
2. Se SIM:
   a. Busca série padrão ativa
   b. Gera próximo número (ex: PRF A/2025/000005)
   c. Incrementa contador da série (próximo será 000006)
   d. Salva proforma com número e série associada
   
3. Se NÃO:
   a. Usa método antigo (PF 2025/000001)
```

### **2. Série Padrão**

- Apenas **uma** série pode ser padrão por tipo de documento
- Série padrão é usada **automaticamente** ao criar documentos
- Se criar nova série padrão, a antiga perde o status

### **3. Reset Anual**

Se ativo:
```
31/12/2025: PRF A/2025/005432
01/01/2026: PRF A/2026/000001  ← Reset para 1
```

---

## 📝 Estrutura da Tabela

### **Série**
```sql
invoicing_series:
  - id
  - tenant_id
  - document_type (invoice, proforma, receipt, etc)
  - series_code (A, B, 01, etc)
  - name
  - prefix (FT, PRF, RC, etc)
  - include_year (boolean)
  - next_number
  - number_padding
  - is_default (boolean)
  - is_active (boolean)
  - current_year
  - reset_yearly (boolean)
  - description
```

### **Proforma (Atualizada)**
```sql
invoicing_sales_proformas:
  - id
  - tenant_id
  - series_id  ← NOVO! Relacionamento com série
  - proforma_number
  - ...
```

---

## 🎨 Interface de Séries

### **Lista de Séries:**
```
┌─────────────────────────────────────────────────────────────────┐
│  Tipo     │ Código  │ Nome              │ Preview          │    │
├─────────────────────────────────────────────────────────────────┤
│  Proforma │ A       │ Vendas Loja       │ PRF A/2025/00001 │ ✏️ │
│  (PADRÃO) │         │                   │                  │ 🗑️ │
├─────────────────────────────────────────────────────────────────┤
│  Proforma │ B       │ Vendas Online     │ PRF B/2025/00001 │ ✏️ │
│           │         │                   │                  │ 🗑️ │
└─────────────────────────────────────────────────────────────────┘
```

### **Filtros:**
- 🔍 Pesquisar por nome/código
- 📁 Filtrar por tipo de documento

---

## ✅ Benefícios

### **1. Organização:**
- Séries diferentes para lojas/departamentos
- Numeração sequencial garantida
- Sem duplicação de números

### **2. Conformidade:**
- Padrão AGT Angola
- Rastreabilidade total
- Auditoria facilitada

### **3. Flexibilidade:**
- Criar quantas séries precisar
- Mudar série padrão a qualquer momento
- Reset anual automático

---

## 🔄 Migração de Documentos Antigos

### **Documentos Criados Antes:**
- Mantêm numeração antiga (PF 2025/000001)
- Campo `series_id` = NULL
- Funcionam normalmente

### **Documentos Novos:**
- Usam série configurada
- Campo `series_id` preenchido
- Numeração da série (PRF A/2025/000001)

---

## 🧪 Testes

### **1. Criar Série Padrão:**
```
1. Criar série tipo "Proforma"
2. Marcar como "Padrão" e "Ativa"
3. Salvar
```

### **2. Criar Proforma:**
```
1. Ir em Faturação → Proformas → Nova
2. Preencher dados
3. Salvar
4. ✅ Número será: PRF A/2025/000001
```

### **3. Verificar Incremento:**
```
1. Criar outra proforma
2. ✅ Número será: PRF A/2025/000002
3. ✅ Série incrementa automaticamente
```

---

## 🚨 Troubleshooting

### **Problema: Numeração antiga ainda aparece**

**Causa:** Nenhuma série padrão configurada

**Solução:**
1. Criar série tipo "Proforma"
2. Marcar como ☑️ "Série padrão"
3. Marcar como ☑️ "Série ativa"
4. Salvar

### **Problema: Número duplicado**

**Causa:** Múltiplas séries padrão (não deveria acontecer)

**Solução:**
1. Ir em Séries
2. Desmarcar "Padrão" de todas
3. Marcar apenas uma como padrão

### **Problema: Série não aparece**

**Causa:** Série inativa

**Solução:**
1. Editar série
2. Marcar ☑️ "Série ativa"
3. Salvar

---

## 📊 Relatórios

### **Ver Documentos por Série:**
```sql
SELECT 
    s.name as serie,
    s.series_code,
    COUNT(p.id) as total_proformas,
    MAX(p.proforma_number) as ultimo_numero
FROM invoicing_sales_proformas p
JOIN invoicing_series s ON p.series_id = s.id
GROUP BY s.id, s.name, s.series_code;
```

### **Ver Próximos Números:**
```sql
SELECT 
    document_type,
    series_code,
    name,
    CONCAT(prefix, ' ', series_code, 
           IF(include_year, CONCAT('/', YEAR(NOW())), ''), 
           '/', LPAD(next_number, number_padding, '0')) as proximo_numero
FROM invoicing_series
WHERE is_active = 1
ORDER BY document_type, series_code;
```

---

## 📚 Referências

- **Modelo:** `App\Models\Invoicing\InvoicingSeries`
- **Controller:** `App\Livewire\Invoicing\SeriesManagement`
- **Migration:** `2025_10_04_123433_add_series_id_to_invoicing_sales_proformas_table`
- **Rota:** `/invoicing/series`

---

## 🎯 Próximos Passos

1. ✅ **Configure série padrão** para proformas
2. ⏭️ **Implemente em faturas** (próxima etapa)
3. ⏭️ **Implemente em recibos** (próxima etapa)
4. ⏭️ **Implemente em notas de crédito** (próxima etapa)

---

**Sistema de séries implementado e funcional! Configure suas séries e comece a usar numeração profissional. 📋✅**
