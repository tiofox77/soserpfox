# 📦 Sincronização Automática: Planos ↔ Tenants

## 🎯 Como funciona a atualização de módulos em um plano

Quando você edita um plano e adiciona/remove módulos, o sistema **AUTOMATICAMENTE** sincroniza com todos os tenants que têm subscription ativa.

---

## 📊 Tabelas Envolvidas

### 1. **`plan_module`** - Módulos do Plano
Relacionamento: **Plano ↔ Módulos**
```sql
| plan_id | module_id |
|---------|-----------|
|    2    |     1     |  ← Plano "Professional" tem Faturação
|    2    |     3     |  ← Plano "Professional" tem RH
|    2    |     7     |  ← Plano "Professional" tem Eventos
```

### 2. **`tenant_module`** - Módulos do Tenant
Relacionamento: **Tenant ↔ Módulos**
```sql
| tenant_id | module_id | is_active | activated_at        |
|-----------|-----------|-----------|---------------------|
|    5      |     1     |   true    | 2025-10-01 10:00:00 |
|    5      |     3     |   true    | 2025-10-01 10:00:00 |
|    5      |     7     |   true    | 2025-10-09 09:30:00 | ← NOVO!
```

---

## 🔄 Fluxo de Sincronização

### **Cenário 1: Adicionar Módulo ao Plano**

```
1. Super Admin edita plano "Professional"
2. Adiciona módulo "Eventos" (ID: 7)
3. Clica em "Salvar"

↓ Sistema detecta automaticamente ↓

4. Compara: 
   - Módulos ANTES: [1, 3]
   - Módulos DEPOIS: [1, 3, 7]
   - ADICIONADOS: [7] ✅

5. Busca todos os tenants com subscription ativa no plano "Professional"
   - Tenant "Empresa A" (ID: 5) ✅
   - Tenant "Empresa B" (ID: 8) ✅
   - Tenant "Empresa C" (ID: 12) ✅

6. ADICIONA módulo "Eventos" aos 3 tenants
   - INSERT na tabela tenant_module
   - is_active = true
   - activated_at = agora

✅ RESULTADO: 3 empresas receberam automaticamente o módulo "Eventos"!
```

### **Cenário 2: Remover Módulo do Plano**

```
1. Super Admin edita plano "Professional"
2. DESMARCA módulo "RH" (ID: 3)
3. Clica em "Salvar"

↓ Sistema detecta automaticamente ↓

4. Compara:
   - Módulos ANTES: [1, 3, 7]
   - Módulos DEPOIS: [1, 7]
   - REMOVIDOS: [3] ❌

5. Busca todos os tenants com subscription ativa no plano "Professional"
   - Tenant "Empresa A" (ID: 5) ✅
   - Tenant "Empresa B" (ID: 8) ✅
   - Tenant "Empresa C" (ID: 12) ✅

6. REMOVE módulo "RH" dos 3 tenants
   - DELETE na tabela tenant_module
   - WHERE tenant_id IN (5, 8, 12)
   - AND module_id = 3

✅ RESULTADO: 3 empresas perderam o módulo "RH" automaticamente!
```

### **Cenário 3: Adicionar E Remover ao mesmo tempo**

```
1. Super Admin edita plano "Professional"
2. Adiciona: "Eventos" (ID: 7)
3. Remove: "RH" (ID: 3)
4. Clica em "Salvar"

↓ Sistema faz AMBOS ↓

ADICIONADOS: [7]
→ 3 tenants RECEBERAM "Eventos" ✅

REMOVIDOS: [3]
→ 3 tenants PERDERAM "RH" ❌

✅ RESULTADO: Sincronização completa!
```

---

## 💻 Código Responsável

### **Arquivo:** `app/Livewire/SuperAdmin/Plans.php`

**Método:** `save()` - Linhas 107-151

```php
// Pegar módulos ANTES da atualização
$oldModuleIds = $plan->modules->pluck('id')->toArray();

// Atualizar plano
$plan->update($data);
$plan->modules()->sync($this->selectedModules); // ← Atualiza plan_module

// Detectar ADIÇÕES
$addedModuleIds = array_diff($this->selectedModules, $oldModuleIds);

// Detectar REMOÇÕES
$removedModuleIds = array_diff($oldModuleIds, $this->selectedModules);

// Sincronizar com tenants
foreach ($addedModuleIds as $moduleId) {
    $plan->addModuleToTenants($moduleId); // ← Adiciona em tenant_module
}

foreach ($removedModuleIds as $moduleId) {
    $plan->removeModuleFromTenants($moduleId); // ← Remove de tenant_module
}
```

---

## 📋 Métodos no Model Plan

### **Arquivo:** `app/Models/Plan.php`

### 1. `addModuleToTenants($moduleId)` - Linha 246
**O que faz:**
- Busca todos os tenants com subscription ativa neste plano
- Adiciona o módulo a cada tenant
- Marca como ativo e registra data de ativação

```php
$tenant->modules()->syncWithoutDetaching([
    $moduleId => [
        'is_active' => true,
        'activated_at' => now(),
    ]
]);
```

### 2. `removeModuleFromTenants($moduleId)` - Linha 296
**O que faz:**
- Busca todos os tenants com subscription ativa neste plano
- Remove o módulo de cada tenant

```php
$tenant->modules()->detach($moduleId);
```

---

## 📝 Logs Gerados

### **Arquivo:** `storage/logs/laravel.log`

**Exemplo ao adicionar módulo:**
```
✅ Módulo 'Eventos' adicionado ao tenant 'Empresa A' (Plano: Professional)
✅ Módulo 'Eventos' adicionado ao tenant 'Empresa B' (Plano: Professional)
✅ Módulo 'Eventos' adicionado ao tenant 'Empresa C' (Plano: Professional)

📦 SINCRONIZAÇÃO AUTOMÁTICA - Plano 'Professional':
   - 3 tenant(s) receberam 1 novo(s) módulo(s)
```

**Exemplo ao remover módulo:**
```
❌ Módulo 'RH' removido do tenant 'Empresa A' (Plano: Professional)
❌ Módulo 'RH' removido do tenant 'Empresa B' (Plano: Professional)
❌ Módulo 'RH' removido do tenant 'Empresa C' (Plano: Professional)

📦 SINCRONIZAÇÃO AUTOMÁTICA - Plano 'Professional':
   - 3 tenant(s) perderam 1 módulo(s)
```

---

## 🔍 Verificar Sincronização

### Via Tinker:
```bash
php artisan tinker

# Ver módulos de um plano
$plan = Plan::find(2);
$plan->modules->pluck('name', 'id');

# Ver módulos de um tenant
$tenant = Tenant::find(5);
$tenant->modules->pluck('name', 'id');

# Ver tenants que têm um módulo específico
$module = Module::find(7);
$module->tenants()->where('is_active', true)->get();
```

### Via SQL direto:
```sql
-- Ver módulos do plano
SELECT p.name as plano, m.name as modulo
FROM plan_module pm
JOIN plans p ON p.id = pm.plan_id
JOIN modules m ON m.id = pm.module_id
WHERE p.id = 2;

-- Ver módulos do tenant
SELECT t.name as tenant, m.name as modulo, tm.is_active, tm.activated_at
FROM tenant_module tm
JOIN tenants t ON t.id = tm.tenant_id
JOIN modules m ON m.id = tm.module_id
WHERE t.id = 5;
```

---

## ⚠️ Importante

### ✅ **O que É sincronizado:**
- Módulos **ADICIONADOS** ao plano → Todos os tenants RECEBEM
- Módulos **REMOVIDOS** do plano → Todos os tenants PERDEM

### ⚠️ **Quais tenants são afetados:**
- Apenas tenants com **subscription ativa** no plano
- Status da subscription: `'active'`

### 🛡️ **Proteções:**
- `syncWithoutDetaching()` → Não remove outros módulos ao adicionar
- `detach()` → Remove apenas o módulo específico
- Logs completos de cada operação
- Contadores para rastrear quantos tenants foram afetados

---

## 🚀 Testando

### 1. Via Interface Web:
1. Acesse: `http://soserp.test/superadmin/plans`
2. Clique em "Editar" em algum plano
3. Adicione ou remova módulos
4. Clique em "Salvar"
5. Verifique os logs: `storage/logs/laravel.log`

### 2. Verificar resultado:
```bash
php artisan tinker

# Verificar tenant antes
$tenant = Tenant::find(5);
$tenant->modules->pluck('name');

# Editar plano via interface...

# Verificar tenant depois (reload)
$tenant->refresh();
$tenant->modules->pluck('name');
```

---

✅ **Sistema totalmente sincronizado! Qualquer alteração no plano reflete AUTOMATICAMENTE em todos os tenants!** 🎉
