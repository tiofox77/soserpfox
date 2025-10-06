# 🔍 Como Funciona o Sistema de Módulos

## 📊 **Estrutura de Tabelas**

O sistema de módulos usa um relacionamento **Many-to-Many** entre 3 tabelas principais:

```
┌─────────────┐         ┌──────────────────┐         ┌─────────────┐
│   tenants   │────────→│  tenant_module   │←────────│   modules   │
│             │         │   (PIVOT TABLE)  │         │             │
└─────────────┘         └──────────────────┘         └─────────────┘
```

---

## 📋 **1. Tabela `modules`** (Catálogo de Módulos)

Contém todos os módulos disponíveis no sistema.

### Estrutura:
```sql
CREATE TABLE modules (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),           -- "Gestão de Eventos"
    slug VARCHAR(255) UNIQUE,    -- "eventos"
    description TEXT,
    icon VARCHAR(255),           -- "calendar-alt"
    is_core BOOLEAN,             -- false
    is_active BOOLEAN,           -- true (módulo disponível globalmente)
    order INT,                   -- 9
    dependencies JSON,           -- ["invoicing"]
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Exemplo de Registro:
```json
{
    "id": 10,
    "name": "Gestão de Eventos",
    "slug": "eventos",
    "description": "Gestão de eventos, montagem de salas, equipamentos",
    "icon": "calendar-alt",
    "is_core": false,
    "is_active": true,
    "order": 9,
    "dependencies": ["invoicing"]
}
```

---

## 📋 **2. Tabela `tenants`** (Empresas)

Contém todas as empresas/tenants do sistema.

### Estrutura:
```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    nif VARCHAR(255),
    is_active BOOLEAN,
    subscription_id BIGINT,
    -- ... outros campos
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 📋 **3. Tabela `tenant_module`** (PIVOT - Relacionamento)

**Esta é a tabela que o comando `AttachModuleToTenant` mexe!**

### Estrutura Completa:
```sql
CREATE TABLE tenant_module (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT,              -- FK para tenants
    module_id BIGINT,              -- FK para modules
    is_active BOOLEAN DEFAULT true, -- ⭐ CAMPO IMPORTANTE!
    settings JSON,                 -- Configurações específicas
    activated_at TIMESTAMP,        -- Quando foi ativado
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(tenant_id, module_id)   -- Um tenant não pode ter o mesmo módulo 2x
);
```

### Índices:
- **PRIMARY KEY**: `id`
- **FOREIGN KEY**: `tenant_id` → `tenants(id)` ON DELETE CASCADE
- **FOREIGN KEY**: `module_id` → `modules(id)` ON DELETE CASCADE
- **UNIQUE**: `(tenant_id, module_id)`

---

## 🔧 **O que o Comando `AttachModuleToTenant` Faz:**

### **Linha por Linha:**

```php
// 1. Busca o módulo pelo slug na tabela 'modules'
$module = Module::where('slug', 'eventos')->first();
// SQL: SELECT * FROM modules WHERE slug = 'eventos' LIMIT 1;

// 2. Busca o tenant (se especificado)
$tenant = Tenant::find($tenantId);
// SQL: SELECT * FROM tenants WHERE id = ? LIMIT 1;

// 3. Vincula o módulo ao tenant usando a PIVOT TABLE
$tenant->modules()->syncWithoutDetaching([
    $module->id => ['is_active' => true]
]);
```

### **O que o `syncWithoutDetaching` Faz:**

Executa **INSERT ou UPDATE** na tabela `tenant_module`:

```sql
-- Se NÃO existir, INSERE:
INSERT INTO tenant_module (tenant_id, module_id, is_active, activated_at, created_at, updated_at)
VALUES (1, 10, 1, NOW(), NOW(), NOW());

-- Se JÁ existir, ATUALIZA:
UPDATE tenant_module 
SET is_active = 1, updated_at = NOW()
WHERE tenant_id = 1 AND module_id = 10;
```

**Importante:** `syncWithoutDetaching` NÃO remove registros existentes, apenas adiciona/atualiza.

---

## 📊 **Exemplo de Dados Reais:**

### **Tabela `modules`:**
| id | name | slug | is_active |
|----|------|------|-----------|
| 1 | Faturação | invoicing | 1 |
| 2 | Recursos Humanos | rh | 1 |
| 10 | Gestão de Eventos | eventos | 1 |

### **Tabela `tenants`:**
| id | name | is_active |
|----|------|-----------|
| 1 | Empresa XYZ | 1 |
| 2 | Loja ABC | 1 |

### **Tabela `tenant_module` (Após `module:attach eventos`):**
| id | tenant_id | module_id | is_active | activated_at |
|----|-----------|-----------|-----------|--------------|
| 1 | 1 | 1 | 1 | 2025-01-01 |
| 2 | 1 | 2 | 1 | 2025-01-01 |
| 3 | 1 | 10 | 1 | 2025-10-06 ← **NOVO!** |
| 4 | 2 | 1 | 1 | 2025-01-01 |
| 5 | 2 | 10 | 1 | 2025-10-06 ← **NOVO!** |

---

## 🔍 **Como é Lido o `active_modules`:**

### **Código no `HomeController.php`:**

```php
$debug['active_modules'] = $activeTenant->modules()
    ->wherePivot('is_active', true)
    ->pluck('name', 'slug')
    ->toArray();
```

### **SQL Equivalente:**

```sql
SELECT modules.slug, modules.name
FROM modules
INNER JOIN tenant_module ON modules.id = tenant_module.module_id
WHERE tenant_module.tenant_id = 1          -- Tenant atual
  AND tenant_module.is_active = 1          -- Apenas ativos
ORDER BY modules.order;
```

### **Resultado:**

```json
{
    "invoicing": "Faturação",
    "rh": "Recursos Humanos",
    "contabilidade": "Contabilidade",
    "oficina": "Gestão de Oficina",
    "crm": "CRM",
    "inventario": "Inventário",
    "compras": "Compras",
    "projetos": "Projetos",
    "eventos": "Gestão de Eventos"  ← ✅ AGORA APARECE!
}
```

---

## 🎯 **Resumo das Tabelas Afetadas:**

| Tabela | Operação | Quando |
|--------|----------|--------|
| **`modules`** | SELECT | Buscar o módulo pelo slug |
| **`tenants`** | SELECT | Buscar o(s) tenant(s) |
| **`tenant_module`** | INSERT ou UPDATE | Vincular módulo ao tenant |

### **Colunas Afetadas em `tenant_module`:**

1. ✅ `tenant_id` - ID do tenant
2. ✅ `module_id` - ID do módulo (10 = eventos)
3. ✅ `is_active` - **TRUE** (ativado)
4. ✅ `activated_at` - Data/hora de ativação
5. ✅ `created_at` - Data de criação do registro
6. ✅ `updated_at` - Última atualização

---

## 📚 **Fluxo Completo do Sistema:**

```
1. SEEDER cria módulo na tabela 'modules'
   ↓
2. Comando 'module:attach' vincula módulo ao tenant na 'tenant_module'
   ↓
3. Método 'hasActiveModule()' verifica se existe em 'tenant_module' com is_active=true
   ↓
4. Menu lateral aparece se hasActiveModule('eventos') retorna true
   ↓
5. Usuário acessa o módulo
```

---

## 🔐 **Verificações de Segurança:**

### **1. Tenant Ativo:**
```php
if (!$this->is_active) {
    return false; // Tenant inativo não tem acesso a nenhum módulo
}
```

### **2. Módulo Vinculado:**
```php
$this->modules()
    ->where('slug', $moduleSlug)
    ->wherePivot('is_active', true)
    ->exists();
```

### **3. Subscription Ativa:**
```php
$this->hasActiveSubscription();
```

---

## 🛠️ **Comandos Úteis:**

### **Vincular módulo a um tenant específico:**
```bash
php artisan module:attach eventos 1
```

### **Vincular módulo a TODOS os tenants:**
```bash
php artisan module:attach eventos
```

### **Verificar vínculos no banco:**
```sql
SELECT 
    t.name AS tenant,
    m.name AS module,
    tm.is_active,
    tm.activated_at
FROM tenant_module tm
INNER JOIN tenants t ON t.id = tm.tenant_id
INNER JOIN modules m ON m.id = tm.module_id
WHERE m.slug = 'eventos';
```

---

## 📊 **Diagrama de Relacionamento:**

```
┌─────────────────────────────────────────────────────────────┐
│                      TENANT (Empresa)                       │
│  - id: 1                                                    │
│  - name: "Empresa XYZ"                                      │
│  - is_active: true                                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ hasMany (tenant_module)
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              TENANT_MODULE (Pivot Table)                    │
│  - id: 3                                                    │
│  - tenant_id: 1          ←─── FK                            │
│  - module_id: 10         ←─── FK                            │
│  - is_active: true       ←─── ⭐ CAMPO CHAVE                │
│  - activated_at: 2025-10-06                                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ belongsTo (modules)
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   MODULE (Catálogo)                         │
│  - id: 10                                                   │
│  - name: "Gestão de Eventos"                                │
│  - slug: "eventos"                                          │
│  - is_active: true                                          │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ **Conclusão:**

O comando `AttachModuleToTenant` faz um **INSERT/UPDATE** na tabela **`tenant_module`** com:
- `tenant_id` = ID do tenant
- `module_id` = ID do módulo
- `is_active` = **true**

Isso faz com que o método `hasActiveModule('eventos')` retorne **true**, permitindo que o menu apareça e o usuário acesse o módulo.

**A resposta ao seu debug mostra que TODOS os 9 módulos estão ativos para o seu tenant, incluindo o "eventos"!** ✅
