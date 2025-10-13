# 🔐 Criação Automática de Roles Multi-Níveis

## 📋 Visão Geral

Quando um novo tenant (empresa) é criado no sistema, as **roles multi-níveis** são criadas **automaticamente** com suas respectivas permissões.

---

## 🎯 Roles Criadas Automaticamente

| Role | Descrição | Permissões |
|------|-----------|------------|
| **Super Admin** | Administrador completo | TODAS as permissões |
| **Admin** | Administrador da empresa | Todas EXCETO gestão de sistema |
| **Gestor** | Gerente/Supervisor | View, Create, Edit (sem Delete) |
| **Utilizador** | Usuário básico | Apenas View |

---

## 🔄 Quando as Roles São Criadas

### 1. **Registro de Nova Empresa** (`RegisterWizard`)
Quando um usuário se registra pela primeira vez:
```php
// app/Livewire/Auth/RegisterWizard.php
$tenant = Tenant::create([...]);

// Roles criadas automaticamente
createDefaultRolesForTenant($tenant->id);
```

### 2. **Adicionar Nova Empresa** (`MyAccount`)
Quando um usuário adiciona uma nova empresa à sua conta:
```php
// app/Livewire/MyAccount.php
$tenant = Tenant::create([...]);

// Roles criadas automaticamente
createDefaultRolesForTenant($tenant->id);

// Usuário recebe role "Super Admin" na nova empresa
```

### 3. **Super Admin Cria Tenant** (`SuperAdmin/Tenants`)
Quando o Super Admin cria um tenant manualmente:
```php
// app/Livewire/SuperAdmin/Tenants.php
$tenant = Tenant::create([...]);

// Roles criadas automaticamente
createDefaultRolesForTenant($tenant->id);
```

---

## 🛠️ Função Helper

### `createDefaultRolesForTenant($tenantId)`

**Localização:** `app/Helpers/RoleHelper.php`

**O que faz:**
1. Define o `tenant_id` para o Spatie Permission
2. Busca todas as permissões globais
3. Cria 4 roles com permissões específicas:
   - **Super Admin**: Todas as permissões
   - **Admin**: Todas exceto `system.*`
   - **Gestor**: Apenas `.view`, `.create`, `.edit`
   - **Utilizador**: Apenas `.view`
4. Sincroniza permissões com cada role
5. Loga todo o processo
6. Limpa cache de permissões

**Exemplo de uso:**
```php
use App\Models\Tenant;

$tenant = Tenant::create([
    'name' => 'Nova Empresa',
    'nif' => '123456789',
    // ...
]);

// Criar roles automaticamente
createDefaultRolesForTenant($tenant->id);
```

---

## 📊 Distribuição de Permissões

### Super Admin (100%)
```
✅ Todas as permissões do sistema
✅ Gestão de sistema (system.*)
✅ Gestão de usuários
✅ Gestão de módulos
✅ Todas as operações (view, create, edit, delete)
```

### Admin (~90%)
```
✅ Todas EXCETO system.*
✅ Gestão de usuários
✅ Gestão de empresas
✅ Todas as operações (view, create, edit, delete)
❌ Gestão de sistema
```

### Gestor (~60%)
```
✅ View em todos os módulos
✅ Create em todos os módulos
✅ Edit em todos os módulos
❌ Delete em todos os módulos
❌ Gestão de sistema
```

### Utilizador (~30%)
```
✅ View em todos os módulos
❌ Create
❌ Edit
❌ Delete
❌ Gestão
```

---

## 🔍 Verificar Roles de um Tenant

### Via Interface
1. Acesse: **Super Admin** → **Utilizadores** → **Roles & Permissões**
2. Filtre por empresa
3. Veja todas as roles e permissões

### Via Script
```bash
php scripts/test-role-creation-for-tenant.php
```

### Via Código
```php
use Spatie\Permission\Models\Role;

// Configurar tenant
setPermissionsTeamId($tenantId);

// Buscar roles
$roles = Role::where('tenant_id', $tenantId)->get();

foreach ($roles as $role) {
    echo "{$role->name}: {$role->permissions->count()} permissões\n";
}
```

---

## 🧪 Testar Criação

### Script de Teste
```bash
php scripts/test-role-creation-for-tenant.php
```

**Output esperado:**
```
✅ Tenant encontrado: Gur Distribuição (ID: 1)

📊 Roles do Tenant:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  • Super Admin
    ID: 1
    Permissões: 150
    Guard: web
    Tenant: 1

  • Admin
    ID: 2
    Permissões: 135
    Guard: web
    Tenant: 1

  • Gestor
    ID: 3
    Permissões: 90
    Guard: web
    Tenant: 1

  • Utilizador
    ID: 4
    Permissões: 45
    Guard: web
    Tenant: 1

✅ Todas as roles esperadas existem!
```

---

## 🔧 Estrutura de Arquivos

```
app/
├── Helpers/
│   └── RoleHelper.php              # Função createDefaultRolesForTenant()
├── Livewire/
│   ├── Auth/
│   │   └── RegisterWizard.php      # Usa a função no registro
│   ├── MyAccount.php               # Usa ao adicionar empresa
│   └── SuperAdmin/
│       └── Tenants.php             # Usa ao criar tenant

scripts/
└── test-role-creation-for-tenant.php  # Script de teste

docs/
└── ROLES-AUTO-CREATION.md             # Esta documentação
```

---

## 📝 Logs Gerados

### Criação de Roles
```
[2025-01-11 19:45:23] Criando roles padrão para tenant
    tenant_id: 1

[2025-01-11 19:45:23] Role 'Super Admin' criada
    tenant_id: 1
    permissions_count: 150

[2025-01-11 19:45:24] Role 'Admin' criada
    tenant_id: 1
    permissions_count: 135

[2025-01-11 19:45:24] Role 'Gestor' criada
    tenant_id: 1
    permissions_count: 90

[2025-01-11 19:45:24] Role 'Utilizador' criada
    tenant_id: 1
    permissions_count: 45

[2025-01-11 19:45:24] Todas as roles padrão criadas para tenant
    tenant_id: 1
    roles: ['Super Admin', 'Admin', 'Gestor', 'Utilizador']
```

---

## ⚠️ Importante

1. **Não deletar manualmente** as roles padrão
2. **Não modificar permissões** das roles padrão (crie novas roles personalizadas)
3. **Sempre usar `setPermissionsTeamId()`** antes de trabalhar com roles
4. **Cache de permissões** é limpo automaticamente após criação
5. **Logs completos** são gerados em `storage/logs/laravel.log`

---

## 🚀 Benefícios

✅ **Automático**: Sem necessidade de criar roles manualmente  
✅ **Consistente**: Todas as empresas têm as mesmas roles  
✅ **Seguro**: Permissões bem definidas por nível  
✅ **Rastreável**: Logs completos de todas operações  
✅ **Escalável**: Fácil adicionar novas roles no futuro  
✅ **Multi-tenant**: Isolamento completo entre empresas  

---

## 🔄 Fluxo Completo

```
1. Usuário cria nova empresa
   ↓
2. Tenant::create([...])
   ↓
3. createDefaultRolesForTenant($tenant->id)
   ↓
4. setPermissionsTeamId($tenant->id)
   ↓
5. Buscar todas permissões
   ↓
6. Para cada role:
   - Criar role com tenant_id
   - Filtrar permissões apropriadas
   - Sincronizar permissões
   - Logar criação
   ↓
7. Limpar cache
   ↓
8. Atribuir "Super Admin" ao criador
   ↓
9. ✅ Pronto!
```

---

## 📞 Suporte

Para problemas ou dúvidas:
1. Verificar logs: `storage/logs/laravel.log`
2. Executar script de teste
3. Verificar no painel de Roles & Permissões

---

**Última atualização:** 11/01/2025  
**Versão:** 1.0
