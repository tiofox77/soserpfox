# 🏢 SISTEMA DE VERIFICAÇÃO MULTI-EMPRESA

## 📋 VISÃO GERAL

O SOS ERP permite que usuários gerenciem múltiplas empresas baseado no **plano de assinatura** contratado.

---

## 🎯 COMO FUNCIONA A VERIFICAÇÃO

### **1. Campo `max_companies` nos Planos**

A tabela `plans` possui o campo `max_companies` que define quantas empresas um usuário pode gerenciar:

```sql
CREATE TABLE plans (
    id BIGINT,
    name VARCHAR(255),
    max_users INT DEFAULT 5,
    max_companies INT DEFAULT 1,  ← NOVO CAMPO
    max_storage_mb INT DEFAULT 1000,
    ...
);
```

### **2. Planos e Limites**

| Plano | Preço Mensal | Max Usuários | Max Empresas | Objetivo |
|-------|--------------|--------------|--------------|----------|
| **Starter** | 29,90 AOA | 3 | **1** | Pequenas empresas (mono-empresa) |
| **Professional** | 79,90 AOA | 10 | **3** | Contadores e consultores |
| **Business** | 149,90 AOA | 50 | **10** | Escritórios de contabilidade |
| **Enterprise** | 299,90 AOA | 999 | **999** | Grandes organizações |

---

## 🔍 FLUXO DE VERIFICAÇÃO

### **Passo 1: Usuário tenta adicionar uma empresa**

```php
// Super Admin adiciona usuário ao tenant via /superadmin/tenants
$user->tenants()->attach($tenantId, [
    'role_id' => $roleId,
    'is_active' => true,
    'joined_at' => now(),
]);
```

### **Passo 2: Sistema verifica o limite**

```php
// app/Models/User.php

public function canAddMoreCompanies()
{
    // 1. Super Admin = ilimitado
    if ($this->is_super_admin) {
        return true;
    }
    
    // 2. Pegar tenant ativo do usuário
    $activeTenant = $this->activeTenant();
    
    // 3. Pegar subscription ativa do tenant
    $subscription = $activeTenant->activeSubscription;
    
    // 4. Pegar limite do plano
    $maxAllowed = $subscription->plan->max_companies ?? 1;
    
    // 5. Contar empresas atuais do usuário
    $currentCount = $this->tenants()->count();
    
    // 6. Verificar se pode adicionar
    return $currentCount < $maxAllowed;
}
```

### **Passo 3: Decisão**

```
SE current_count < max_allowed:
    ✅ Permitir adicionar empresa
    
SE current_count >= max_allowed:
    ❌ Bloquear e mostrar mensagem de upgrade
```

---

## 📊 EXEMPLOS PRÁTICOS

### **Exemplo 1: Plano Starter (1 empresa)**

```
João - Plano Starter (max_companies = 1)
├─ Empresa A ← JÁ PERTENCE
│
└─ ❌ Tentar adicionar Empresa B
   └─ BLOQUEADO: "Seu plano permite apenas 1 empresa. Faça upgrade!"
```

### **Exemplo 2: Plano Professional (3 empresas)**

```
Maria - Plano Professional (max_companies = 3)
├─ Empresa A ✅
├─ Empresa B ✅
├─ Empresa C ✅
│
└─ ❌ Tentar adicionar Empresa D
   └─ BLOQUEADO: "Limite de 3 empresas atingido. Faça upgrade!"
```

### **Exemplo 3: Plano Enterprise (999 empresas)**

```
Pedro - Plano Enterprise (max_companies = 999)
├─ Empresa A ✅
├─ Empresa B ✅
├─ Empresa C ✅
├─ ... (até 999)
│
└─ ✅ Pode adicionar mais 996 empresas
```

---

## 🔐 FUNÇÕES HELPER

### **canSwitchTenants()**

Verifica se o usuário pode ver o TenantSwitcher (tem 2+ empresas):

```php
// app/Helpers/TenantHelper.php

function canSwitchTenants()
{
    if (!auth()->check()) {
        return false;
    }
    
    return auth()->user()->tenants()->count() > 1;
}
```

**Uso:**
```blade
@if(canSwitchTenants())
    <livewire:tenant-switcher />
@endif
```

### **getMaxCompaniesLimit()**

Retorna o limite de empresas do usuário:

```php
// app/Models/User.php

public function getMaxCompaniesLimit()
{
    // Super Admin = ilimitado
    if ($this->is_super_admin) {
        return PHP_INT_MAX;
    }
    
    // Pegar do plano
    $subscription = $this->activeTenant()->activeSubscription;
    return $subscription->plan->max_companies ?? 1;
}
```

### **canAddMoreCompanies()**

Verifica se pode adicionar mais empresas:

```php
// app/Models/User.php

public function canAddMoreCompanies()
{
    $currentCount = $this->tenants()->count();
    $maxAllowed = $this->getMaxCompaniesLimit();
    
    return $currentCount < $maxAllowed;
}
```

---

## 🛠️ IMPLEMENTAÇÃO NO SUPER ADMIN

### **Na tela de adicionar usuário ao tenant:**

```php
// app/Livewire/SuperAdmin/Tenants.php

public function addUserToTenant()
{
    // ... validações
    
    // Verificar se o usuário pode ter mais empresas
    if (!$user->canAddMoreCompanies()) {
        $maxAllowed = $user->getMaxCompaniesLimit();
        
        $this->dispatch('error', message: 
            "Este usuário já atingiu o limite de {$maxAllowed} empresa(s) do seu plano. " .
            "Faça upgrade do plano para adicionar mais empresas."
        );
        return;
    }
    
    // Adicionar usuário ao tenant
    DB::table('tenant_user')->insert([...]);
}
```

---

## 📈 UPGRADE DE PLANO

### **Fluxo de Upgrade:**

```
1. Usuário atinge limite de empresas
2. Sistema mostra mensagem de upgrade
3. Super Admin vai em /superadmin/billing
4. Altera plano da empresa principal do usuário
5. Subscription atualizada com novo plano
6. Novo limite entra em vigor imediatamente
```

### **Exemplo:**

```sql
-- Antes: Plano Starter (max_companies = 1)
UPDATE subscriptions 
SET plan_id = 2  -- Professional (max_companies = 3)
WHERE tenant_id = 1 AND status = 'active';

-- Agora o usuário pode ter até 3 empresas
```

---

## ✅ COMANDOS PARA APLICAR AS MUDANÇAS

### **1. Rodar a Migration:**
```bash
php artisan migrate
```

### **2. Atualizar os Planos:**
```bash
php artisan db:seed --class=PlanSeeder
```

### **3. Verificar:**
```bash
php artisan tinker

>>> $user = User::find(1);
>>> $user->getMaxCompaniesLimit();
=> 3  // Se estiver no plano Professional

>>> $user->canAddMoreCompanies();
=> true  // Se tiver menos de 3 empresas
```

---

## 🎯 CASOS DE USO

### **Caso 1: Escritório de Contabilidade**

```
Escritório XYZ - Plano Business (max_companies = 10)

Contador João:
├─ Cliente A (Empresa A)
├─ Cliente B (Empresa B)
├─ Cliente C (Empresa C)
├─ ... até 10 clientes

Cada cliente = 1 empresa
João alterna entre empresas usando TenantSwitcher
```

### **Caso 2: Empresa com Filiais**

```
Grupo ABC - Plano Professional (max_companies = 3)

Gestor Maria:
├─ Matriz Luanda
├─ Filial Benguela
├─ Filial Huambo

Maria vê dados separados de cada filial
```

### **Caso 3: Pequena Empresa**

```
Loja ABC - Plano Starter (max_companies = 1)

Dono Pedro:
└─ Loja ABC (única empresa)

Pedro não vê TenantSwitcher (só tem 1 empresa)
Se precisar de mais, deve fazer upgrade
```

---

## 🚀 MELHORIAS FUTURAS

### **Fase 1 (Atual):**
- ✅ Campo `max_companies` nos planos
- ✅ Verificação no User Model
- ✅ Seeders atualizados

### **Fase 2 (Próxima):**
- [ ] Validação no SuperAdmin ao adicionar usuário
- [ ] Mensagem de upgrade no UI
- [ ] Página de comparação de planos
- [ ] Fluxo de upgrade automático

### **Fase 3 (Futuro):**
- [ ] Notificação quando atingir 80% do limite
- [ ] Dashboard de uso (X/Y empresas)
- [ ] Relatório de utilização por plano
- [ ] API para verificar limites

---

## 📝 RESUMO

| Item | Descrição |
|------|-----------|
| **Campo** | `plans.max_companies` |
| **Tipos de Verificação** | Super Admin (ilimitado) / Por Plano (limitado) |
| **Função Principal** | `User::canAddMoreCompanies()` |
| **Helper** | `canSwitchTenants()` |
| **Upgrade** | Via alteração de `subscriptions.plan_id` |
| **Status** | ✅ Implementado |

---

**Data:** 03 de Outubro de 2025  
**Versão:** 3.7.0  
**Autor:** SOS ERP Team
