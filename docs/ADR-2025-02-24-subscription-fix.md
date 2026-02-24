---
Data: 2025-02-24
Responsável: Equipa SOS ERP
Escopo: Sistema de Subscrições, Planos, Módulos e Multi-Tenant
Status: A IMPLEMENTAR
---

# ADR: Correção do Sistema de Subscrições Multi-Empresa

## 1. PROBLEMA IDENTIFICADO

**Caso real:** Utilizador `carlosfox1782@gmail.com` com plano "Fox Friendly" (`max_companies=2`) tem 2 empresas. Só consegue usar "GUR Distribuição"; ao trocar para a outra empresa recebe erro "sem plano".

**Causa raiz:** A subscription está vinculada ao TENANT, mas o plano define `max_companies` por UTILIZADOR. Quando o admin aprova um pedido, a subscription só é criada para 1 tenant — os outros tenants do mesmo utilizador ficam sem subscription.

---

## 2. DIAGRAMA ACTUAL (COM DEFEITO)

```
USER (carlosfox1782@gmail.com)
 ├── Tenant A (GUR Distribuição)
 │    └── Subscription ✅ (Fox Friendly, active)
 │         └── Modules ✅ (sincronizados)
 │
 └── Tenant B (outra empresa)
      └── Subscription ❌ (NENHUMA ou expirada)
           └── Modules ❌ (não sincronizados)

CheckSubscription Middleware:
  $tenant = $user->activeTenant()  ← Tenant B
  $subscription = $tenant->subscriptions()->active()  ← NULL
  → REDIRECT subscription-expired ❌
```

## 3. BUGS ENCONTRADOS (12)

### CRÍTICOS (bloqueiam utilizador)

**BUG-01: OrderObserver.processApproval() só cria subscription para 1 tenant**
- Ficheiro: `app/Observers/OrderObserver.php:57-181`
- O pedido tem `tenant_id` de apenas 1 empresa
- Ao aprovar, só esse tenant recebe subscription ativa
- Outros tenants do mesmo user ficam sem subscription
- **Impacto:** Utilizador perde acesso às outras empresas

**BUG-02: CheckSubscription middleware verifica subscription por tenant, não por user**
- Ficheiro: `app/Http/Middleware/CheckSubscription.php:88-129`
- Faz `$tenant->subscriptions()->active()` apenas no tenant actual
- Devia verificar se o USER tem subscription activa em QUALQUER tenant
- **Impacto:** Bloqueia acesso ao trocar de empresa

**BUG-03: MyAccount::createCompany() replica subscription de forma frágil**
- Ficheiro: `app/Livewire/MyAccount.php:236-257`
- Cria subscription independente com datas próprias (não sincronizada)
- Se o tenant actual não tem subscription, a nova empresa fica sem
- Período da subscription replicada não coincide com a original
- **Impacto:** Subscriptions dessincronizadas entre empresas

### IMPORTANTES (lógica inconsistente)

**BUG-04: getMaxCompaniesLimit() só consulta tenant activo**
- Ficheiro: `app/Models/User.php:214-233`
- Se o tenant activo não tem subscription, retorna 1 (default)
- Deveria procurar em TODOS os tenants do user
- **Impacto:** Pode bloquear criação de empresa mesmo com plano válido

**BUG-05: processUpgrade() cria subscription PENDING imediatamente**
- Ficheiro: `app/Livewire/MyAccount.php:757-765`
- Cria subscription com status 'pending' antes do admin aprovar
- OrderObserver também cria/activa subscription na aprovação
- Resulta em subscriptions duplicadas
- **Impacto:** Dados sujos, confusão na verificação

**BUG-06: Plan.hasModule() usa campo JSON, mas módulos estão em pivot**
- Ficheiro: `app/Models/Plan.php:122-125`
- `hasModule()` verifica `$this->included_modules` (JSON array)
- Mas `modules()` usa tabela pivot `plan_module`
- Podem estar dessincronizados
- **Impacto:** Módulo pode estar no plano mas não ser detectado

**BUG-07: TenantSwitcher bloqueia por índice de collection**
- Ficheiro: `app/Livewire/TenantSwitcher.php:50-69`
- Usa `$tenants->search()` que retorna índice da collection
- Ordem depende da query, não da criação
- Pode bloquear a empresa errada
- **Impacto:** Utilizador bloqueado de empresa válida

### MENORES (robustez)

**BUG-08: Subscription sem user_id — não há rastreabilidade**
- Tabela `subscriptions` só tem `tenant_id`
- Impossível saber "todas as subscriptions deste user" sem joins
- **Impacto:** Dificulta consultas e validações cross-tenant

**BUG-09: CheckTenantModule usa request attribute que Livewire não tem**
- Ficheiro: `app/Http/Middleware/CheckTenantModule.php:26`
- `$request->attributes->get('tenant')` — setado por IdentifyTenant
- IdentifyTenant ignora requests Livewire (linha 28)
- **Impacto:** Módulos não são verificados em requests Livewire

**BUG-10: IdentifyTenant ignora Livewire completamente**
- Ficheiro: `app/Http/Middleware/IdentifyTenant.php:28`
- Livewire requests não passam por IdentifyTenant
- Tenant não é setado no `$request->attributes`
- **Impacto:** CheckTenantModule falha para Livewire

**BUG-11: Excesso de logs em produção**
- CheckSubscription e IdentifyTenant logam TODAS as requests
- Inclui URLs, emails, IDs em cada request
- **Impacto:** Logs enormes, performance degradada

**BUG-12: Subscription::renew() não propaga para outros tenants**
- Ficheiro: `app/Models/Subscription.php:109-123`
- Renova apenas a subscription actual
- Outros tenants do user não são renovados
- **Impacto:** Dessincronização após renovação

---

## 4. SOLUÇÃO PROPOSTA

### Princípio: Subscription é do USER, propagada para os TENANTS

```
USER (carlosfox1782@gmail.com)
 ├── Subscription MASTER ← (plano Fox Friendly)
 │
 ├── Tenant A (GUR Distribuição)
 │    └── Subscription ✅ (clone, synced)
 │         └── Modules ✅
 │
 └── Tenant B (outra empresa)
      └── Subscription ✅ (clone, synced)
           └── Modules ✅
```

### Abordagem: Mínimo de alterações estruturais

Em vez de mudar o schema (adicionar `user_id` na `subscriptions`), resolve-se com lógica:
1. Quando uma subscription é activada → propagar para TODOS os tenants do user
2. CheckSubscription → verificar se user tem subscription em QUALQUER tenant
3. Sincronizar módulos em TODOS os tenants do user ao aprovar pedido

---

## 5. ROADMAP DE IMPLEMENTAÇÃO

### FASE 1: Correção Urgente (BUG-01, BUG-02, BUG-03)
> Desbloquear utilizadores afectados

**1.1 — Corrigir CheckSubscription middleware**
```
Ficheiro: app/Http/Middleware/CheckSubscription.php
Alteração: Em vez de verificar subscription apenas do tenant actual,
           verificar se o USER tem subscription activa em QUALQUER tenant.
           Se sim, e o tenant actual não tem, propagar automaticamente.
```
Lógica:
```php
// ANTES (errado):
$subscription = $tenant->subscriptions()->active()->first();

// DEPOIS (correcto):
$subscription = $tenant->subscriptions()->active()->first();

if (!$subscription) {
    // Procurar subscription activa em QUALQUER tenant do user
    $subscription = $this->findUserActiveSubscription($user);
    
    if ($subscription) {
        // Propagar para o tenant actual
        $this->propagateSubscription($tenant, $subscription);
    }
}
```

**1.2 — Corrigir OrderObserver.processApproval()**
```
Ficheiro: app/Observers/OrderObserver.php
Alteração: Após activar subscription no tenant do pedido,
           propagar para TODOS os outros tenants do user.
```
Lógica:
```php
// Após criar/activar subscription no tenant do pedido:
$user = $order->user;
$otherTenants = $user->tenants()->where('tenants.id', '!=', $tenant->id)->get();

foreach ($otherTenants as $otherTenant) {
    $this->propagateSubscription($otherTenant, $newSubscription, $newPlan);
}
```

**1.3 — Corrigir MyAccount::createCompany()**
```
Ficheiro: app/Livewire/MyAccount.php
Alteração: Ao criar empresa, propagar subscription com as MESMAS datas
           da subscription original (não criar datas independentes).
```
Lógica:
```php
// Replicar com mesmas datas:
$tenant->subscriptions()->create([
    'plan_id'              => $currentSubscription->plan_id,
    'status'               => $currentSubscription->status,
    'billing_cycle'        => $currentSubscription->billing_cycle,
    'amount'               => $currentSubscription->amount,
    'current_period_start' => $currentSubscription->current_period_start,
    'current_period_end'   => $currentSubscription->current_period_end,
    'ends_at'              => $currentSubscription->ends_at,
]);
```

### FASE 2: Lógica de Propagação Centralizada (BUG-04, BUG-05, BUG-12)

**2.1 — Criar método propagateSubscriptionToUserTenants() no User model**
```
Ficheiro: app/Models/User.php
Método central reutilizável:
- Recebe subscription source
- Propaga para todos os tenants do user (até max_companies)
- Sincroniza módulos em cada tenant
```

**2.2 — Corrigir getMaxCompaniesLimit()**
```
Ficheiro: app/Models/User.php
Alteração: Procurar plano activo em QUALQUER tenant do user.
```

**2.3 — Corrigir processUpgrade() para não criar subscription pending**
```
Ficheiro: app/Livewire/MyAccount.php
Alteração: Criar apenas o Order com status pending.
           NÃO criar subscription até o admin aprovar.
```

**2.4 — Corrigir Subscription::renew() para propagar**
```
Ficheiro: app/Models/Subscription.php
Alteração: Após renovar, propagar renovação para outros tenants do user.
```

### FASE 3: Correcções de Módulos (BUG-06, BUG-09, BUG-10)

**3.1 — Corrigir Plan.hasModule()**
```
Ficheiro: app/Models/Plan.php
Alteração: Usar a relação modules() (pivot) em vez de included_modules (JSON).
```

**3.2 — Corrigir IdentifyTenant para não ignorar Livewire**
```
Ficheiro: app/Http/Middleware/IdentifyTenant.php
Alteração: Remover skip de Livewire ou usar abordagem alternativa
           para setar tenant em requests Livewire.
```

**3.3 — Corrigir CheckTenantModule para Livewire**
```
Ficheiro: app/Http/Middleware/CheckTenantModule.php
Alteração: Se $request->attributes->get('tenant') é null,
           usar activeTenant() como fallback.
```

### FASE 4: Robustez (BUG-07, BUG-08, BUG-11)

**4.1 — Corrigir TenantSwitcher para não usar índice**
```
Ficheiro: app/Livewire/TenantSwitcher.php
Alteração: Verificar por subscription do tenant em vez de índice.
           Se tenant tem subscription activa → permitir acesso.
```

**4.2 — Adicionar user_id à tabela subscriptions (migration)**
```
Migration: add_user_id_to_subscriptions_table
Coluna: user_id (nullable, foreign key)
Benefício: Consultas directas "subscriptions do user X".
```

**4.3 — Reduzir logs em produção**
```
Ficheiros: CheckSubscription, IdentifyTenant, CheckTenantActive
Alteração: Usar Log::debug() em vez de Log::info()
           ou verificar config('app.debug') antes de logar.
```

### FASE 5: Comando de Reparação de Dados
> Corrigir utilizadores já afectados

**5.1 — Criar artisan command: subscription:sync-all-users**
```
Para cada user com subscription activa:
  1. Identificar plano e subscription source
  2. Para cada tenant do user (até max_companies):
     - Se não tem subscription → criar clone
     - Se tem subscription expirada → renovar com mesmas datas
     - Sincronizar módulos do plano
  3. Log de alterações
```

**5.2 — Fix imediato para carlosfox1782@gmail.com**
```
php artisan tinker
>>> $user = User::where('email','carlosfox1782@gmail.com')->first();
>>> $source = $user->tenants->first()->activeSubscription;
>>> // Propagar para todos os tenants sem subscription
```

---

## 6. ORDEM DE EXECUÇÃO

| Prioridade | Fase | Ficheiros | Esforço |
|:---:|:---:|:---|:---:|
| P0 | 5.2 | tinker (fix imediato) | 5 min |
| P0 | 1.1 | CheckSubscription.php | 30 min |
| P0 | 1.2 | OrderObserver.php | 30 min |
| P0 | 1.3 | MyAccount.php | 20 min |
| P1 | 2.1 | User.php | 30 min |
| P1 | 2.2 | User.php | 10 min |
| P1 | 2.3 | MyAccount.php | 15 min |
| P1 | 2.4 | Subscription.php | 15 min |
| P2 | 3.1 | Plan.php | 10 min |
| P2 | 3.2 | IdentifyTenant.php | 20 min |
| P2 | 3.3 | CheckTenantModule.php | 10 min |
| P3 | 4.1 | TenantSwitcher.php | 15 min |
| P3 | 4.2 | migration | 10 min |
| P3 | 4.3 | middlewares | 15 min |
| P3 | 5.1 | artisan command | 30 min |

**Total estimado: ~4.5 horas**

---

## 7. TESTES DE VALIDAÇÃO

Após cada fase, verificar:

```
1. User com 2 empresas e 1 plano → ambas acedem normalmente
2. Trocar de empresa → funciona sem "sem plano"
3. Aprovar pedido → subscription propagada para todas as empresas
4. Criar nova empresa → subscription replicada correctamente
5. Expirar subscription → todas as empresas bloqueadas
6. Renovar subscription → todas as empresas reactivadas
7. Upgrade de plano → módulos sincronizados em todas as empresas
8. Downgrade → módulos removidos em todas as empresas
```
