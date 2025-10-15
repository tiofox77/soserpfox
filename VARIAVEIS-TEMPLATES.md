# 📋 Variáveis de Templates por Módulo

Este documento lista todas as variáveis disponíveis para uso em templates de notificação SMS e Email.

---

## 📅 **EVENTOS** (`events`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ event }}` | Nome do Evento | `name` |
| `{{ date }}` | Data de Início | `start_date` |
| `{{ end_date }}` | Data de Término | `end_date` |
| `{{ local }}` | Local do Evento | `venue.name` |
| `{{ cliente }}` | Nome do Cliente | `client.name` |
| `{{ responsavel }}` | Responsável | `responsible.name` |
| `{{ tipo }}` | Tipo de Evento | `type.name` |
| `{{ participantes }}` | Número de Participantes | `expected_attendees` |
| `{{ valor }}` | Valor Total | `total_value` |
| `{{ status }}` | Status | `status` |
| `{{ fase }}` | Fase | `phase` |

**Exemplo de Mensagem:**
```
Lembrete: Evento {{ event }} no dia {{ date }} em {{ local }}.
Cliente: {{ cliente }}
Responsável: {{ responsavel }}
```

---

## 👥 **RECURSOS HUMANOS** (`hr`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ funcionario }}` | Nome do Funcionário | `name` |
| `{{ cargo }}` | Cargo | `position` |
| `{{ departamento }}` | Departamento | `department.name` |
| `{{ data_admissao }}` | Data de Admissão | `hire_date` |
| `{{ salario }}` | Salário | `salary` |
| `{{ email }}` | Email | `email` |
| `{{ telefone }}` | Telefone | `phone` |
| `{{ licenca_inicio }}` | Início da Licença | `start_date` |
| `{{ licenca_fim }}` | Fim da Licença | `end_date` |
| `{{ tipo_licenca }}` | Tipo de Licença | `leave_type` |

**Exemplo de Mensagem:**
```
Olá {{ funcionario }},

Sua licença foi aprovada!
Tipo: {{ tipo_licenca }}
Período: {{ licenca_inicio }} a {{ licenca_fim }}
```

---

## 📆 **CALENDÁRIO** (`calendar`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ titulo }}` | Título do Evento | `title` |
| `{{ data_inicio }}` | Data de Início | `start_date` |
| `{{ data_fim }}` | Data de Término | `end_date` |
| `{{ descricao }}` | Descrição | `description` |
| `{{ local }}` | Local | `location` |
| `{{ organizador }}` | Organizador | `organizer.name` |

**Exemplo de Mensagem:**
```
📅 Lembrete de Agenda

{{ titulo }}
Data: {{ data_inicio }} até {{ data_fim }}
Local: {{ local }}
Organizador: {{ organizador }}
```

---

## 💰 **FINANCEIRO** (`finance`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ documento }}` | Número do Documento | `document_number` |
| `{{ cliente }}` | Cliente/Fornecedor | `partner.name` |
| `{{ valor }}` | Valor | `total_amount` |
| `{{ data_emissao }}` | Data de Emissão | `issue_date` |
| `{{ data_vencimento }}` | Data de Vencimento | `due_date` |
| `{{ status }}` | Status | `status` |
| `{{ descricao }}` | Descrição | `description` |
| `{{ metodo_pagamento }}` | Método de Pagamento | `payment_method` |

**Exemplo de Mensagem:**
```
💰 Lembrete de Pagamento

Documento: {{ documento }}
Cliente: {{ cliente }}
Valor: {{ valor }}
Vencimento: {{ data_vencimento }}
Status: {{ status }}
```

---

## 🤝 **CRM** (`crm`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ cliente }}` | Nome do Cliente | `name` |
| `{{ empresa }}` | Empresa | `company` |
| `{{ email }}` | Email | `email` |
| `{{ telefone }}` | Telefone | `phone` |
| `{{ responsavel }}` | Responsável | `assigned_to.name` |
| `{{ status }}` | Status | `status` |
| `{{ oportunidade }}` | Valor da Oportunidade | `opportunity_value` |
| `{{ proxima_acao }}` | Próxima Ação | `next_action` |

**Exemplo de Mensagem:**
```
🤝 Acompanhamento de Cliente

Cliente: {{ cliente }}
Empresa: {{ empresa }}
Responsável: {{ responsavel }}
Oportunidade: {{ oportunidade }}
Próxima Ação: {{ proxima_acao }}
```

---

## 📊 **PROJETOS** (`projects`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ projeto }}` | Nome do Projeto | `name` |
| `{{ cliente }}` | Cliente | `client.name` |
| `{{ gerente }}` | Gerente do Projeto | `manager.name` |
| `{{ data_inicio }}` | Data de Início | `start_date` |
| `{{ data_fim }}` | Data de Término | `end_date` |
| `{{ orcamento }}` | Orçamento | `budget` |
| `{{ status }}` | Status | `status` |
| `{{ progresso }}` | Progresso (%) | `progress` |

**Exemplo de Mensagem:**
```
📊 Atualização de Projeto

Projeto: {{ projeto }}
Cliente: {{ cliente }}
Gerente: {{ gerente }}
Progresso: {{ progresso }}%
Status: {{ status }}
```

---

## ✅ **TAREFAS** (`tasks`)

| Variável | Descrição | Campo Sistema |
|----------|-----------|---------------|
| `{{ tarefa }}` | Título da Tarefa | `title` |
| `{{ descricao }}` | Descrição | `description` |
| `{{ responsavel }}` | Responsável | `assigned_to.name` |
| `{{ data_vencimento }}` | Data de Vencimento | `due_date` |
| `{{ prioridade }}` | Prioridade | `priority` |
| `{{ status }}` | Status | `status` |
| `{{ projeto }}` | Projeto | `project.name` |

**Exemplo de Mensagem:**
```
✅ Lembrete de Tarefa

{{ tarefa }}
Responsável: {{ responsavel }}
Vencimento: {{ data_vencimento }}
Prioridade: {{ prioridade }}
Projeto: {{ projeto }}
```

---

## 🎯 **COMO USAR**

### 1. **Acesse** `/notifications/templates`

### 2. **Selecione o Módulo**
   - Escolha o módulo (Eventos, RH, etc.)
   - As variáveis disponíveis aparecerão automaticamente

### 3. **Escreva o Corpo da Mensagem**
   - SMS: Campo "Corpo da Mensagem SMS"
   - Email: Campos "Assunto" e "Corpo do Email"

### 4. **Insira as Variáveis**
   - Clique no ícone 📋 para copiar
   - Cole no texto: `{{ variavel }}`

### 5. **Salve e Teste!**

---

## ✨ **FORMATAÇÃO AUTOMÁTICA**

### **Datas**
Todas as datas são formatadas automaticamente para `dd/mm/yyyy`:
- `{{ date }}` → `15/10/2025`
- `{{ data_vencimento }}` → `20/12/2025`

### **Valores NULL**
Valores vazios são convertidos para string vazia (não aparecem como "null")

---

## 📝 **EXEMPLOS COMPLETOS**

### **Template de Evento (SMS)**
```
📅 LEMBRETE DE EVENTO

Evento: {{ event }}
Data: {{ date }}
Local: {{ local }}
Cliente: {{ cliente }}

Montagem 1 dia antes!
```

### **Template de Licença (Email)**
```
Assunto: Aprovação de Licença - {{ tipo_licenca }}

Olá {{ funcionario }},

Sua solicitação de {{ tipo_licenca }} foi aprovada!

Período: {{ licenca_inicio }} a {{ licenca_fim }}
Departamento: {{ departamento }}

Qualquer dúvida, entre em contato com o RH.

Atenciosamente,
Equipe RH
```

---

**🎉 Sistema totalmente parametrizável e pronto para uso!**
