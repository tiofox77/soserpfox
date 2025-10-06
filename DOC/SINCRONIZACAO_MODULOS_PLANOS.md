# 🔄 Sincronização Automática de Módulos: Planos → Tenants

## 📋 **Visão Geral**

Quando um **Super Admin** adiciona um novo módulo a um plano existente, **TODOS os tenants** que possuem **subscription ativa** desse plano recebem automaticamente o módulo.

---

## 🎯 **Problema Resolvido**

### **Antes (Problema):**
```
1. Super Admin cria plano "Premium" com módulos: Faturação, RH
2. Tenant A assina o plano "Premium"
3. Super Admin adiciona módulo "Eventos" ao plano "Premium"
4. ❌ Tenant A NÃO tem acesso ao módulo "Eventos" automaticamente
5. ❌ Super Admin precisa vincular manualmente
```

### **Agora (Solução):**
```
1. Super Admin cria plano "Premium" com módulos: Faturação, RH
2. Tenant A assina o plano "Premium"
3. Super Admin adiciona módulo "Eventos" ao plano "Premium"
4. ✅ Tenant A RECEBE automaticamente o módulo "Eventos"
5. ✅ Sincronização acontece em tempo real
```

---

## 🏗️ **Arquitetura da Solução**

### **Tabelas Envolvidas:**

```
┌──────────────┐        ┌─────────────────┐        ┌──────────────┐
│    plans     │◄──────→│   plan_module   │◄──────→│   modules    │
│              │        │   (pivot)       │        │              │
└──────┬───────┘        └─────────────────┘        └──────┬───────┘
       │                                                    │
       │ hasMany                                            │
       │ subscriptions                                      │
       │                                                    │
       ▼                                                    │
┌──────────────┐                                           │
│subscriptions │                                           │
│              │                                           │
└──────┬───────┘                                           │
       │                                                    │
       │ belongsTo                                          │
       │ tenant                                             │
       │                                                    │
       ▼                                                    │
┌──────────────┐        ┌─────────────────┐               │
│   tenants    │◄──────→│  tenant_module  │◄──────────────┘
│              │        │   (pivot)       │
└──────────────┘        └─────────────────┘
```

---

## 🔧 **Componentes Criados**

### **1. Métodos no Model `Plan`**

#### **`syncModulesToTenants()`**
Sincroniza **TODOS** os módulos do plano com todos os tenants que têm subscription ativa.

```php
$plan = Plan::find(1);
$count = $plan->syncModulesToTenants();
// Retorna: número de tenants sincronizados
```

**SQL Executado:**
```sql
-- 1. Buscar módulos do plano
SELECT modules.id 
FROM modules
INNER JOIN plan_module ON modules.id = plan_module.module_id
WHERE plan_module.plan_id = 1;

-- 2. Buscar tenants com subscription ativa
SELECT tenants.* 
FROM tenants
INNER JOIN subscriptions ON tenants.id = subscriptions.tenant_id
WHERE subscriptions.plan_id = 1 
  AND subscriptions.status = 'active';

-- 3. Para cada tenant, vincular módulos
INSERT INTO tenant_module (tenant_id, module_id, is_active, activated_at, created_at, updated_at)
VALUES (?, ?, 1, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();
```

#### **`syncModuleToTenants($moduleId)`**
Sincroniza **UM** módulo específico com todos os tenants.

```php
$plan = Plan::find(1);
$count = $plan->syncModuleToTenants(10); // ID do módulo "eventos"
// Retorna: número de tenants sincronizados
```

---

### **2. Componente Livewire Atualizado**

**Arquivo:** `app/Livewire/SuperAdmin/Plans.php`

#### **Lógica no Método `save()`:**

```php
public function save()
{
    // ... validação e preparação de dados ...

    if ($this->editingPlanId) {
        $plan = Plan::find($this->editingPlanId);
        
        // 1. Capturar módulos ANTES da atualização
        $oldModuleIds = $plan->modules->pluck('id')->toArray();
        
        // 2. Atualizar plano e sincronizar módulos
        $plan->update($data);
        $plan->modules()->sync($this->selectedModules);
        
        // 3. Detectar NOVOS módulos adicionados
        $newModuleIds = array_diff($this->selectedModules, $oldModuleIds);
        
        // 4. Sincronizar APENAS os novos módulos com os tenants
        if (!empty($newModuleIds)) {
            $syncedCount = 0;
            foreach ($newModuleIds as $moduleId) {
                $syncedCount += $plan->syncModuleToTenants($moduleId);
            }
            
            \Log::info("Novos módulos sincronizados: {$syncedCount} tenant(s)");
        }
    }
}
```

**Fluxo:**
1. ✅ Detecta quais módulos foram adicionados
2. ✅ Sincroniza APENAS os novos módulos
3. ✅ Não remove módulos existentes dos tenants
4. ✅ Registra a operação no log

---

### **3. Comando Artisan**

**Arquivo:** `app/Console/Commands/SyncPlanModulesToTenants.php`

#### **Uso:**

```bash
# Sincronizar todos os módulos de um plano específico
php artisan plan:sync-modules 1

# Sincronizar apenas um módulo específico de um plano
php artisan plan:sync-modules 1 --module=eventos

# Sincronizar todos os planos ativos
php artisan plan:sync-modules --all

# Sincronizar um módulo específico em todos os planos
php artisan plan:sync-modules --all --module=eventos
```

#### **Exemplo de Output:**

```
🔄 Sincronizando 3 plano(s)...

📦 Plano: Básico
   ✅ 5 tenant(s) sincronizado(s)

📦 Plano: Premium
   ✅ 12 tenant(s) sincronizado(s)

📦 Plano: Empresarial
   ✅ 8 tenant(s) sincronizado(s)

✅ Total: 25 tenant(s) sincronizados em 3 plano(s)
```

---

## 🚀 **Como Funciona na Prática**

### **Cenário 1: Super Admin Adiciona Módulo ao Plano**

```
1. Super Admin acessa: Super Admin → Planos
2. Clica em "Editar" no plano "Premium"
3. Marca o checkbox do módulo "Gestão de Eventos"
4. Clica em "Salvar"

5. 🔄 AUTOMÁTICO: Sistema detecta novo módulo
6. 🔄 AUTOMÁTICO: Busca todos os tenants com subscription ativa do plano "Premium"
7. 🔄 AUTOMÁTICO: Adiciona o módulo "Gestão de Eventos" a cada tenant
8. ✅ Tenants agora veem o menu "📅 Eventos" na sidebar
```

### **Cenário 2: Sincronização Manual**

Se por algum motivo a sincronização automática falhar, use o comando:

```bash
php artisan plan:sync-modules 1 --module=eventos
```

---

## 📊 **Exemplo de Dados**

### **Estado Inicial:**

**Tabela `plan_module`:**
| plan_id | module_id | Módulo |
|---------|-----------|--------|
| 1 | 1 | Faturação |
| 1 | 2 | RH |

**Tabela `subscriptions`:**
| id | tenant_id | plan_id | status |
|----|-----------|---------|--------|
| 1 | 10 | 1 | active |
| 2 | 20 | 1 | active |

**Tabela `tenant_module` (Tenant 10):**
| tenant_id | module_id | is_active |
|-----------|-----------|-----------|
| 10 | 1 | 1 |
| 10 | 2 | 1 |

---

### **Super Admin Adiciona Módulo "Eventos" (ID 10) ao Plano 1:**

**1. Atualiza `plan_module`:**
| plan_id | module_id | Módulo |
|---------|-----------|--------|
| 1 | 1 | Faturação |
| 1 | 2 | RH |
| 1 | **10** | **Eventos** ← NOVO! |

**2. Detecta novos módulos:**
```php
$newModuleIds = [10]; // eventos
```

**3. Sincroniza com tenants:**

**Tabela `tenant_module` (Tenant 10):**
| tenant_id | module_id | is_active | activated_at |
|-----------|-----------|-----------|--------------|
| 10 | 1 | 1 | 2025-01-01 |
| 10 | 2 | 1 | 2025-01-01 |
| 10 | **10** | **1** | **2025-10-06** ← ADICIONADO! |

**Tabela `tenant_module` (Tenant 20):**
| tenant_id | module_id | is_active | activated_at |
|-----------|-----------|-----------|--------------|
| 20 | 1 | 1 | 2025-01-01 |
| 20 | 2 | 1 | 2025-01-01 |
| 20 | **10** | **1** | **2025-10-06** ← ADICIONADO! |

---

## 🔒 **Regras de Segurança**

### **Apenas Tenants com Subscription Ativa:**

```php
$tenants = $plan->subscriptions()
    ->where('status', 'active') // ← IMPORTANTE!
    ->with('tenant')
    ->get()
    ->pluck('tenant')
    ->filter();
```

**Statuses de Subscription:**
- ✅ `active` - Sincroniza
- ❌ `inactive` - NÃO sincroniza
- ❌ `cancelled` - NÃO sincroniza
- ❌ `expired` - NÃO sincroniza

### **Módulo Deve Estar no Plano:**

```php
if (!$this->modules()->where('modules.id', $moduleId)->exists()) {
    return 0; // Não sincroniza
}
```

### **Não Remove Módulos Existentes:**

O método usa `syncWithoutDetaching()`, que:
- ✅ Adiciona novos módulos
- ✅ Atualiza módulos existentes
- ❌ NÃO remove módulos antigos

---

## 📝 **Logs**

Todas as operações são registradas em `storage/logs/laravel.log`:

```
[2025-10-06 12:30:15] INFO: Novos módulos do plano 'Premium' sincronizados automaticamente com 15 tenant(s)
[2025-10-06 12:30:15] INFO: Módulos do plano 'Premium' sincronizados com tenant 'Empresa XYZ' (ID: 10)
[2025-10-06 12:30:15] INFO: Módulos do plano 'Premium' sincronizados com tenant 'Loja ABC' (ID: 20)
```

---

## 🛠️ **Troubleshooting**

### **Problema: Módulo não aparece no tenant após adicionar ao plano**

**Verificações:**

1. **Subscription está ativa?**
```sql
SELECT * FROM subscriptions WHERE tenant_id = ? AND status = 'active';
```

2. **Módulo está no plano?**
```sql
SELECT * FROM plan_module WHERE plan_id = ? AND module_id = ?;
```

3. **Módulo está vinculado ao tenant?**
```sql
SELECT * FROM tenant_module WHERE tenant_id = ? AND module_id = ?;
```

**Solução:**
```bash
# Forçar sincronização
php artisan plan:sync-modules [plan_id]
```

---

## ✅ **Checklist de Testes**

- [ ] Criar plano com 2 módulos
- [ ] Criar tenant com subscription ativa
- [ ] Verificar que tenant tem os 2 módulos
- [ ] Adicionar 3º módulo ao plano
- [ ] Verificar que tenant recebeu o 3º módulo automaticamente
- [ ] Verificar logs em `storage/logs/laravel.log`
- [ ] Testar comando `php artisan plan:sync-modules`

---

## 🎯 **Resumo**

| Ação | Automático? | Como Funciona |
|------|-------------|---------------|
| **Criar plano** | ✅ | Sincroniza módulos com tenants existentes |
| **Adicionar módulo ao plano** | ✅ | Sincroniza APENAS novos módulos com tenants |
| **Remover módulo do plano** | ❌ | NÃO remove do tenant (proteção) |
| **Tenant assina plano** | ❌ | Precisa ser feito manualmente ou por webhook |

---

## 📚 **Arquivos Modificados**

1. ✅ `app/Models/Plan.php` - Métodos de sincronização
2. ✅ `app/Livewire/SuperAdmin/Plans.php` - Lógica automática no save()
3. ✅ `app/Console/Commands/SyncPlanModulesToTenants.php` - Comando artisan
4. ✅ `app/Observers/PlanObserver.php` - Observer (opcional)

---

**✨ Sincronização automática implementada com sucesso! ✨**
