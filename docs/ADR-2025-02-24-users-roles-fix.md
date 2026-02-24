---
Data: 2025-02-24
Responsável: Equipa SOS ERP
Escopo: Gestão de Utilizadores (/users) e Roles/Permissões (/users/roles-permissions)
Status: A IMPLEMENTAR
---

# ADR: Correção de Utilizadores, Roles e Permissões

## 1. CONTEXTO

Análise das páginas `/users` (UserManagement) e `/users/roles-permissions` (RolesAndPermissions).
Rotas protegidas por middleware `superadmin`, mas a lógica interna apresenta inconsistências
entre modelo multi-tenant (many-to-many `tenant_user`) e queries legadas (directo `tenant_id`).

---

## 2. BUGS ENCONTRADOS (14)

### CRÍTICOS

**BUG-U01: RolesAndPermissions lista users por `tenant_id` directo (ignora pivot)**
- Ficheiro: `app/Livewire/Users/RolesAndPermissions.php:57`
- Código: `User::with('roles')->where('tenant_id', $tenantId)->get()`
- O campo `users.tenant_id` é o tenant "padrão" do user, NÃO o tenant activo
- Deveria usar: `whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))`
- **Impacto:** Tab "Atribuir Roles" pode não listar todos os users do tenant ou listar users errados

**BUG-U02: assignRoles() busca user por `tenant_id` directo**
- Ficheiro: `app/Livewire/Users/RolesAndPermissions.php:280-282`
- Código: `User::where('id', $this->selectedUser)->where('tenant_id', $tenantId)->firstOrFail()`
- Se o user tem `tenant_id` diferente do tenant activo (mas pertence via pivot), falha com 404
- **Impacto:** Impossível atribuir roles a users que têm outro `tenant_id` padrão

**BUG-U03: toggleStatus() sem verificação de permissão/tenant**
- Ficheiro: `app/Livewire/Users/UserManagement.php:285-291`
- Qualquer user autenticado pode activar/desactivar qualquer outro user
- Sem verificar se o target user pertence ao mesmo tenant
- Sem verificar se quem executa tem permissão para isso
- **Impacto:** Escalação de privilégio, user pode desactivar admins

**BUG-U04: delete() faz forceDelete sem soft-delete**
- Ficheiro: `app/Livewire/Users/UserManagement.php:318-348`
- Usa `$user->forceDelete()` directamente
- Model User tem `SoftDeletes` trait mas é ignorado
- Dados do user são perdidos permanentemente
- Verifica apenas 3 tabelas para documentos: `invoicing_sales_invoices`, `invoicing_sales_proformas`, `invoicing_purchase_invoices`
- Não verifica: HR records, stock movements, journal entries, etc.
- **Impacto:** Perda de dados irreversível; integridade referencial quebrada

**BUG-U05: Rotas /users exigem `superadmin` mas lógica interna trata non-super-admin**
- Ficheiro: `routes/web.php:42` — middleware `['auth', 'superadmin']`
- Mas `UserManagement.php` tem `when(!$currentUser->is_super_admin, ...)` (linhas 455-459)
- E a view tem `@if(!auth()->user()->is_super_admin)` (linha 87)
- Contradição: se o middleware já bloqueia non-super-admin, o código condicional nunca executa
- **Impacto:** Se a intenção for que Admins do tenant também gerem users, o middleware bloqueia-os

### IMPORTANTES

**BUG-U06: toggleTenant() define role padrão como `Role::first()` global**
- Ficheiro: `app/Livewire/Users/UserManagement.php:258`
- `Role::first()` pode retornar role de OUTRO tenant
- Deveria filtrar: `Role::where('tenant_id', $tenantId)->first()`
- **Impacto:** User pode receber role de outro tenant

**BUG-U07: selectAllTenants() define role padrão global**
- Ficheiro: `app/Livewire/Users/UserManagement.php:274`
- Mesmo problema do BUG-U06: `Role::first()` sem filtro de tenant
- **Impacto:** Role errado atribuído

**BUG-U08: syncUserTenants() força `tenant_id` para primeiro tenant seleccionado**
- Ficheiro: `app/Livewire/Users/UserManagement.php:228`
- `$user->update(['tenant_id' => $this->selectedTenants[0]])`
- Se `selectedTenants` é reordenado (ex: após array_diff), o "primeiro" pode mudar
- O `tenant_id` do user deveria manter-se estável (tenant original de registo)
- **Impacto:** Muda o tenant padrão do user inesperadamente

**BUG-U09: savePermission() não define `guard_name`**
- Ficheiro: `app/Livewire/Users/RolesAndPermissions.php:245-248`
- `Permission::create(['name' => ..., 'description' => ...])` — falta `guard_name`
- Spatie Permission usa default 'web' mas é boa prática ser explícito
- Também não valida formato (deveria ser `modulo.acção`)
- **Impacto:** Permissão pode ser criada com guard errado ou formato inconsistente

**BUG-U10: Convite (sendInvitation) mostra roles de TODOS os tenants no select**
- Ficheiro: `user-management.blade.php:455-458`
- O select de role para convite lista `$roles` sem filtrar por tenant activo
- Se user tem múltiplos tenants, vê roles de todos
- **Impacto:** Admin pode atribuir role de outro tenant ao convidado

**BUG-U11: View faz queries directas no Blade (N+1)**
- Ficheiro: `user-management.blade.php:14-19`
- `$tenant = \App\Models\Tenant::find(activeTenantId())` directamente no Blade
- Executa query em cada render (pode causar N+1 com Livewire)
- Duplicado na linha 244-250 dentro do modal
- **Impacto:** Performance degradada; queries duplicadas

### MENORES

**BUG-U12: Delete modal no roles-and-permissions sempre chama `deleteRole()`**
- Ficheiro: `roles-and-permissions.blade.php:293`
- O botão de delete chama `deleteRole()` independente do `$deletingType`
- Se `$deletingType === 'permission'`, deveria chamar método diferente
- Mas não existe `deletePermission()` — funcionalidade incompleta
- **Impacto:** Não é possível eliminar permissões (apenas roles)

**BUG-U13: Roles modal não fecha ao clicar no backdrop**
- Ficheiro: `roles-and-permissions.blade.php:152`
- Modal de roles não tem `wire:click.self` no backdrop
- User tem de clicar no X para fechar
- **Impacto:** UX inconsistente com outros modais do sistema

**BUG-U14: Limite de users verificado apenas no tenant activo**
- Ficheiro: `user-management.blade.php:15-18` e `UserManagement.php:106-116`
- `$tenant->canAddUser()` verifica users do tenant actual
- Mas se user é partilhado entre tenants, conta como 1 user em cada
- O plano define `max_users` global mas verificação é por tenant
- **Impacto:** Pode permitir mais users que o plano permite (cross-tenant)

---

## 3. ROADMAP DE IMPLEMENTAÇÃO

### FASE 1: Correcções Críticas de Segurança

**1.1 — Corrigir RolesAndPermissions: usar pivot em vez de tenant_id directo**
```
Ficheiro: app/Livewire/Users/RolesAndPermissions.php

render() linha 57:
  ANTES:  User::with('roles')->where('tenant_id', $tenantId)->get()
  DEPOIS: User::with('roles')
              ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))
              ->get()

assignRoles() linha 280-282:
  ANTES:  User::where('id', ...)->where('tenant_id', $tenantId)->firstOrFail()
  DEPOIS: User::where('id', ...)
              ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))
              ->firstOrFail()
```

**1.2 — Corrigir toggleStatus: adicionar verificação de permissão e tenant**
```
Ficheiro: app/Livewire/Users/UserManagement.php

toggleStatus():
  - Verificar se target user pertence ao tenant activo
  - Não permitir desactivar Super Admin
  - Não permitir desactivar a si próprio
```

**1.3 — Corrigir delete: usar softDelete em vez de forceDelete**
```
Ficheiro: app/Livewire/Users/UserManagement.php

delete():
  - Usar $user->delete() (soft delete) em vez de forceDelete()
  - Expandir verificação de documentos para incluir TODAS as tabelas relevantes
  - Manter user no sistema mas marcado como eliminado
```

**1.4 — Decidir middleware: superadmin-only ou admin-do-tenant**
```
Ficheiro: routes/web.php

OPÇÃO A (Super Admin only): Manter middleware, remover código condicional non-super-admin
OPÇÃO B (Admin do tenant): Trocar 'superadmin' por 'permission:users.manage'
  → Requer: criar permissão 'users.manage'
  → UserManagement já tem lógica para filtrar por tenant
```

### FASE 2: Correcções de Lógica

**2.1 — Corrigir toggleTenant/selectAllTenants: filtrar roles por tenant**
```
Ficheiro: app/Livewire/Users/UserManagement.php

toggleTenant():
  ANTES:  Role::first()
  DEPOIS: Role::where('tenant_id', $tenantId)->first()

selectAllTenants():
  Para cada tenant, buscar role padrão DAQUELE tenant
```

**2.2 — Corrigir syncUserTenants: não forçar tenant_id**
```
Ficheiro: app/Livewire/Users/UserManagement.php

Remover ou condicionar:
  $user->update(['tenant_id' => $this->selectedTenants[0]])

Regra: só definir tenant_id se user for NOVO (sem tenant_id prévio)
```

**2.3 — Corrigir convite: filtrar roles por tenant activo**
```
Ficheiro: user-management.blade.php (select do convite)

ANTES:  @foreach($roles as $role)
DEPOIS: @foreach($roles->where('tenant_id', activeTenantId()) as $role)
```

**2.4 — Corrigir savePermission: adicionar guard_name e validação de formato**
```
Ficheiro: app/Livewire/Users/RolesAndPermissions.php

Permission::create([
    'name' => $this->permissionName,
    'guard_name' => 'web',
    'description' => $this->permissionDescription,
]);

Validação: 'permissionName' => ['required', 'regex:/^[a-z]+\.[a-z_]+$/', 'unique:permissions,name']
```

### FASE 3: Performance e UX

**3.1 — Mover queries do Blade para o componente**
```
Ficheiro: app/Livewire/Users/UserManagement.php

No render(), passar $currentUsers, $maxUsers, $canAdd como variáveis
Em vez de fazer queries directamente no Blade
```

**3.2 — Corrigir delete modal em roles-and-permissions**
```
Ficheiro: roles-and-permissions.blade.php

O botão de confirmar deve verificar $deletingType:
  @if($deletingType === 'role')
      wire:click="deleteRole"
  @else
      wire:click="deletePermission"
  @endif

Implementar deletePermission() no componente
```

**3.3 — Adicionar backdrop click nos modais de roles**
```
Ficheiro: roles-and-permissions.blade.php

Adicionar wire:click.self ou @click.self nos backdrops dos modais
```

**3.4 — Limite de users cross-tenant**
```
Ficheiro: app/Models/Tenant.php

canAddUser() deve considerar users ÚNICOS (não contar duplicados cross-tenant)
Ou: Definir claramente se max_users é por tenant ou por plano/user
```

---

## 4. ORDEM DE EXECUÇÃO

| Prioridade | Item | Ficheiros | Esforço |
|:---:|:---:|:---|:---:|
| P0 | 1.1 | RolesAndPermissions.php | 15 min |
| P0 | 1.2 | UserManagement.php | 15 min |
| P0 | 1.3 | UserManagement.php | 20 min |
| P0 | 1.4 | routes/web.php + views | 10 min |
| P1 | 2.1 | UserManagement.php | 10 min |
| P1 | 2.2 | UserManagement.php | 10 min |
| P1 | 2.3 | user-management.blade.php | 5 min |
| P1 | 2.4 | RolesAndPermissions.php | 10 min |
| P2 | 3.1 | UserManagement.php + view | 15 min |
| P2 | 3.2 | RolesAndPermissions + view | 15 min |
| P2 | 3.3 | roles-and-permissions.blade.php | 5 min |
| P2 | 3.4 | Tenant.php | 20 min |

**Total estimado: ~2.5 horas**

---

## 5. TESTES DE VALIDAÇÃO

```
1. RolesAndPermissions:
   - User com tenant_id=A pertence ao tenant B via pivot → aparece na lista de B
   - Atribuir role a user multi-tenant → funciona sem 404

2. UserManagement:
   - toggleStatus verifica pertença ao tenant
   - Não permite desactivar Super Admin
   - Delete usa soft-delete, dados mantidos
   - Criar user → role atribuído é do tenant correcto (não global)

3. Convites:
   - Select de role mostra apenas roles do tenant activo
   - Convite atribui role correcto ao aceitar

4. Segurança:
   - Non-super-admin não acede /users (se opção A)
   - Ou: Admin do tenant gere apenas seus users (se opção B)
```
