# Menu Sidebar - Todos os Módulos do Sistema

## 📋 Resumo Completo

Todos os módulos do sistema foram adicionados ao sidebar com os **slugs corretos** para evitar erros futuros.

---

## 🎯 Módulos Implementados

### **1. Faturação** (`invoicing`) ✅
**Ícone:** 💰 `fa-file-invoice-dollar` (Yellow)  
**Condição:** `hasActiveModule('invoicing')`  
**Status:** Totalmente implementado  

**Submenu:**
- Dashboard
- POS - Ponto de Venda
- Turnos de Caixa
- Histórico de Turnos
- Relatórios POS
- Clientes
- Fornecedores
- Produtos
- Categorias
- Marcas
- Documentos (Proformas, Faturas, Recibos, etc.)
- Armazéns
- Gestão Stock
- Impostos (IVA)
- Séries de Documentos
- Gerador SAFT-AO
- Configurações

---

### **2. Tesouraria** (`invoicing`) ✅
**Ícone:** 💵 `fa-coins` (Green)  
**Condição:** `hasActiveModule('invoicing')` *(integrado com Faturação)*  
**Status:** Totalmente implementado  

**Submenu:**
- Relatórios
- Contas Bancárias
- Transações
- Transferências
- Métodos de Pagamento
- Bancos
- Caixas

---

### **3. Gestão de Eventos** (`eventos`) ✅
**Ícone:** 📅 `fa-calendar-alt` (Pink)  
**Condição:** `hasActiveModule('eventos')`  
**Status:** Totalmente implementado  

**Submenu:**
- Dashboard
- Calendário
- Relatórios
- Equipamentos
- Locais
- Tipos de Eventos
- Técnicos

---

### **4. Recursos Humanos** (`rh`) ✅
**Ícone:** 👔 `fa-user-tie` (Cyan)  
**Condição:** `hasActiveModule('rh')` ⚠️ **Corrigido de `hr` para `rh`**  
**Status:** Totalmente implementado  

**Submenu:**
- Funcionários
- Departamentos
- Presenças
- Férias
- Licenças e Faltas
- Horas Extras
- Folha de Pagamento
- Adiantamentos
- Configurações RH

---

### **5. Contabilidade** (`contabilidade`) ✅ **NOVO**
**Ícone:** 🧮 `fa-calculator` (Indigo)  
**Condição:** `hasActiveModule('contabilidade')`  
**Status:** Menu criado (aguarda implementação)  

**Submenu:**
- Dashboard
- Plano de Contas
- Lançamentos
- Relatórios

---

### **6. Gestão de Oficina** (`oficina`) ✅ **NOVO**
**Ícone:** 🔧 `fa-wrench` (Orange)  
**Condição:** `hasActiveModule('oficina')`  
**Status:** Menu criado (aguarda implementação)  

**Submenu:**
- Dashboard
- Veículos
- Ordens de Reparação
- Agendamentos

---

### **7. CRM** (`crm`) ✅ **NOVO**
**Ícone:** ✅ `fa-user-check` (Teal)  
**Condição:** `hasActiveModule('crm')`  
**Status:** Menu criado (aguarda implementação)  

**Submenu:**
- Dashboard
- Leads
- Oportunidades
- Funil de Vendas

---

### **8. Inventário** (`inventario`) ✅ **NOVO**
**Ícone:** 📦 `fa-boxes` (Amber)  
**Condição:** `hasActiveModule('inventario')`  
**Status:** Menu criado (aguarda implementação)  

**Submenu:**
- Dashboard
- Armazéns
- Movimentos
- Contagem de Stock

---

### **9. Compras** (`compras`) ✅ **NOVO**
**Ícone:** 🛒 `fa-shopping-cart` (Lime)  
**Condição:** `hasActiveModule('compras')`  
**Status:** Menu criado (aguarda implementação)  

**Submenu:**
- Dashboard
- Fornecedores
- Requisições
- Encomendas

---

### **10. Projetos** (`projetos`) ✅ **NOVO**
**Ícone:** 📊 `fa-project-diagram` (Violet)  
**Condição:** `hasActiveModule('projetos')`  
**Status:** Menu criado (aguarda implementação)  

**Submenu:**
- Dashboard
- Projetos
- Tarefas
- Timesheet

---

## 🎨 Paleta de Cores por Módulo

| Módulo | Cor Principal | Classe Tailwind |
|--------|--------------|----------------|
| **Faturação** | Amarelo | `text-yellow-400` |
| **Tesouraria** | Verde | `text-green-400` |
| **Eventos** | Rosa | `text-pink-400` |
| **Recursos Humanos** | Ciano | `text-cyan-400` |
| **Contabilidade** | Índigo | `text-indigo-400` |
| **Oficina** | Laranja | `text-orange-400` |
| **CRM** | Azul-esverdeado | `text-teal-400` |
| **Inventário** | Âmbar | `text-amber-400` |
| **Compras** | Lima | `text-lime-400` |
| **Projetos** | Violeta | `text-violet-400` |

---

## 📊 Tabela de Verificação de Slugs

| Módulo no DB (`modules` table) | Slug no Código | Status | Corrigido |
|--------------------------------|----------------|--------|-----------|
| Invoicing | `invoicing` | ✅ Correto | - |
| Recursos Humanos | `rh` | ✅ Correto | ✅ Sim (era `hr`) |
| Contabilidade | `contabilidade` | ✅ Correto | - |
| Gestão de Oficina | `oficina` | ✅ Correto | - |
| CRM | `crm` | ✅ Correto | - |
| Inventário | `inventario` | ✅ Correto | - |
| Compras | `compras` | ✅ Correto | - |
| Projetos | `projetos` | ✅ Correto | - |
| Gestão de Eventos | `eventos` | ✅ Correto | - |

---

## 🔍 Como Verificar Módulo Ativo

Todos os menus seguem o padrão:

```php
@if(!auth()->user()->isSuperAdmin() && auth()->user()->hasActiveModule('slug_do_modulo'))
    <!-- Menu do Módulo -->
@endif
```

### **Fluxo de Verificação:**

1. **Usuário NÃO pode ser Super Admin**
2. **Verificar na tabela `module_tenant`:**
   - `tenant_id` = tenant ativo do usuário
   - `module_id` = ID do módulo com o slug correspondente
   - `is_active` = `true`

---

## 🚀 Ordem dos Menus no Sidebar

1. **Início** (rota home)
2. **Utilizadores** (apenas Super Admin)
3. **Faturação** (`invoicing`)
4. **Tesouraria** (`invoicing`)
5. **Eventos** (`eventos`)
6. **Recursos Humanos** (`rh`)
7. **Contabilidade** (`contabilidade`)
8. **Oficina** (`oficina`)
9. **CRM** (`crm`)
10. **Inventário** (`inventario`)
11. **Compras** (`compras`)
12. **Projetos** (`projetos`)
13. **Super Admin** (apenas Super Admin)

---

## ⚠️ Importante: Sincronização de Módulos

Se um tenant não vê módulos que deveria ter acesso, verificar:

1. **Tabela `module_tenant`:** Deve ter registro com `is_active = 1`
2. **Plano ativo:** Verificar se o plano inclui o módulo
3. **Comando de sincronização:** `php artisan tenant:resync-modules`

---

## 📝 Exemplos de Rotas Esperadas

### **Contabilidade:**
- `contabilidade.dashboard`
- `contabilidade.plano-contas`
- `contabilidade.lancamentos`
- `contabilidade.relatorios`

### **Oficina:**
- `oficina.dashboard`
- `oficina.veiculos`
- `oficina.ordens-reparacao`
- `oficina.agendamentos`

### **CRM:**
- `crm.dashboard`
- `crm.leads`
- `crm.oportunidades`
- `crm.funil-vendas`

### **Inventário:**
- `inventario.dashboard`
- `inventario.armazens`
- `inventario.movimentos`
- `inventario.contagem`

### **Compras:**
- `compras.dashboard`
- `compras.fornecedores`
- `compras.requisicoes`
- `compras.encomendas`

### **Projetos:**
- `projetos.dashboard`
- `projetos.lista`
- `projetos.tarefas`
- `projetos.timesheet`

---

## ✅ Checklist de Implementação

- ✅ **Faturação** - 100% Implementado
- ✅ **Tesouraria** - 100% Implementado
- ✅ **Eventos** - 100% Implementado
- ✅ **Recursos Humanos** - 100% Implementado
- ✅ **Contabilidade** - Menu criado (0% funcionalidades)
- ✅ **Oficina** - Menu criado (0% funcionalidades)
- ✅ **CRM** - Menu criado (0% funcionalidades)
- ✅ **Inventário** - Menu criado (0% funcionalidades)
- ✅ **Compras** - Menu criado (0% funcionalidades)
- ✅ **Projetos** - Menu criado (0% funcionalidades)

---

## 🦊 Plano Fox Friendly

O **Plano Fox Friendly** tem acesso a **TODOS os 9 módulos**:
- `invoicing`
- `rh`
- `contabilidade`
- `oficina`
- `crm`
- `inventario`
- `compras`
- `projetos`
- `eventos`

**Benefícios:**
- 🎁 6 meses GRÁTIS (180 dias)
- 👥 999 utilizadores
- 🏢 50 empresas
- 💾 100GB de armazenamento

---

## 🔧 Manutenção Futura

Ao adicionar um novo módulo:

1. **Adicionar no `ModuleSeeder.php`**
2. **Criar menu no `app.blade.php`** com slug correto
3. **Criar rotas em `web.php`**
4. **Criar Livewire components**
5. **Adicionar no plano desejado**
6. **Executar:** `php artisan db:seed --class=ModuleSeeder`
7. **Sincronizar:** `php artisan tenant:resync-modules`

---

**Última atualização:** 12 de outubro de 2025  
**Responsável:** Sistema Fox 🦊
