# 🗑️ Regras de Exclusão de Empresas (Tenants)

## 📋 Visão Geral

O sistema implementa regras rigorosas para exclusão de empresas, com validações automáticas e exclusão permanente (hard delete) da base de dados.

---

## ⚖️ Regras Legais

### **Empresas COM Faturas**
❌ **NÃO PODEM ser deletadas**

**Motivo:**
- Legislação fiscal angolana
- Obrigações de auditoria
- Rastreabilidade de documentos

**Mensagem ao usuário:**
```
Não é possível excluir uma empresa que já tem faturas emitidas.
(X fatura(s) emitida(s))
```

### **Empresas SEM Faturas**
✅ **PODEM ser deletadas**

**Condições:**
- Nenhuma fatura emitida
- Nenhuma nota proforma convertida
- Usuário tem permissão de Admin

**Tipo de exclusão:**
- **HARD DELETE** (Permanente da BD)
- Não usa soft delete
- Todos dados são removidos em cascata

---

## 🔐 Validações Implementadas

### 1. **Verificação de Faturas**
```php
$canDelete = $tenant->canBeDeleted();

if (!$canDelete['can_delete']) {
    // Bloquear exclusão
    return $canDelete['reason'];
}
```

### 2. **Validação de Permissões**
- Apenas **Admin** da empresa pode deletar
- Não pode deletar a **única empresa**
- Recomenda-se não deletar a **empresa ativa**

### 3. **Validação em Cascata**
Antes de deletar, o sistema verifica:
- ✅ Faturas (`invoices`)
- ✅ Faturas de Venda (`sales_invoices`)
- ✅ Clientes (aviso, não bloqueia)
- ✅ Eventos, Equipamentos, etc.

---

## 🛠️ Implementação Técnica

### **Método `canBeDeleted()` no Model**

```php
// app/Models/Tenant.php

public function canBeDeleted()
{
    // Verificar faturas regulares
    if ($this->invoices()->exists()) {
        return [
            'can_delete' => false,
            'reason' => 'Não é possível excluir...',
            'invoices_count' => $this->invoices()->count(),
        ];
    }
    
    // Verificar faturas de venda
    if (class_exists('\App\Models\Invoicing\SalesInvoice')) {
        $count = SalesInvoice::where('tenant_id', $this->id)->count();
        if ($count > 0) {
            return [
                'can_delete' => false,
                'reason' => 'Não é possível excluir...',
                'invoices_count' => $count,
            ];
        }
    }
    
    return [
        'can_delete' => true,
        'reason' => null,
    ];
}
```

### **Exclusão no Livewire**

```php
// app/Livewire/MyAccount.php

public function deleteCompany()
{
    $tenant = Tenant::find($this->companyToDelete);
    
    // Validar se pode deletar
    $canDelete = $tenant->canBeDeleted();
    
    if (!$canDelete['can_delete']) {
        return error($canDelete['reason']);
    }
    
    // Deletar PERMANENTEMENTE
    $tenant->forceDelete(); // Hard delete
    
    // Trocar para outra empresa se deletou a ativa
    if (activeTenantId() == $this->companyToDelete) {
        $user->switchTenant($firstTenant->id);
    }
}
```

---

## 🗂️ Exclusão em Cascata

Quando um tenant é deletado, o sistema **automaticamente** remove:

### **1. Usuários**
- Remove roles do tenant
- Desvincula da pivot table
- Delete completo se não tiver outros tenants

### **2. Roles e Permissões**
- Todas roles do tenant
- Vínculos com permissões

### **3. Subscriptions**
- Planos ativos
- Histórico de pagamentos

### **4. Orders**
- Pedidos de upgrade
- Histórico de compras

### **5. Faturas**
- ⚠️ Só deleta se **NÃO HOUVER** faturas (bloqueio prévio)

### **6. Módulos**
- Desvincula módulos atribuídos

### **7. Categorias**
- Categorias de equipamentos
- Métodos de pagamento personalizados

### **8. Eventos e Equipamentos**
- Todos eventos cadastrados
- Todos equipamentos

### **9. Convites Pendentes**
- Convites de usuários pendentes

**Localização:** `app/Models/Tenant.php` → evento `deleting`

---

## 🔧 Correção do Slug Duplicado

### **Problema:**
```
SQLSTATE[23000]: Integrity constraint violation: 
1062 Duplicate entry 'fox292933939' for key 'tenants_slug_unique'
```

### **Causa:**
Quando múltiplas empresas tinham o mesmo nome, o slug gerado era idêntico.

### **Solução Implementada:**

```php
// app/Models/Tenant.php

static::creating(function ($tenant) {
    if (empty($tenant->slug)) {
        $baseSlug = Str::slug($tenant->name);
        $slug = $baseSlug;
        $counter = 1;
        
        // Garantir slug único
        while (self::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $tenant->slug = $slug;
    }
});
```

### **Resultado:**
```
Empresa: fox292933939
Slugs gerados:
  1. fox292933939
  2. fox292933939-1
  3. fox292933939-2
  ...
```

---

## 🧪 Testes

### **Script de Teste**
```bash
php scripts/test-tenant-slug-unique.php
```

**Testa:**
- ✅ Geração de slugs únicos
- ✅ Criação com nomes duplicados
- ✅ Verificação de exclusão (canBeDeleted)
- ✅ Regras de bloqueio

### **Resultado Esperado:**
```
✅ Tenant criado com sucesso!
   ID: 123
   Nome: fox292933939
   Slug: fox292933939-2

✅ Este tenant PODE ser deletado
   Não possui faturas emitidas
```

---

## 📊 Fluxo de Exclusão

```
1. Usuário clica "Eliminar Empresa"
   ↓
2. Sistema verifica permissões
   - É admin?
   - Tem mais de 1 empresa?
   ↓
3. Sistema verifica faturas
   - canBeDeleted()?
   ↓
4a. TEM faturas
   ❌ BLOQUEAR
   "Não é possível excluir..."
   
4b. NÃO TEM faturas
   ✅ Permitir exclusão
   ↓
5. Modal de confirmação
   "Todos os dados serão perdidos
    PERMANENTEMENTE da base de dados"
   ↓
6. Usuário confirma
   ↓
7. forceDelete()
   - Evento deleting acionado
   - Cascata executa
   - Logs gerados
   ↓
8. Troca empresa ativa (se necessário)
   ↓
9. ✅ Sucesso!
   "Empresa eliminada permanentemente"
```

---

## 🚨 Avisos Importantes

### **Para Desenvolvedores:**

1. **NUNCA** use `$tenant->delete()` 
   - Use `$tenant->forceDelete()` para hard delete

2. **SEMPRE** valide com `canBeDeleted()`
   - Antes de qualquer exclusão

3. **LOGS** são essenciais
   - Todo delete é logado
   - Rastreabilidade completa

4. **Cascata** é automática
   - Evento `deleting` no boot()
   - Não precisa deletar manualmente

### **Para Usuários:**

1. **Exclusão é PERMANENTE**
   - Não há como recuperar
   - Backup antes se necessário

2. **Empresas com faturas NÃO podem ser deletadas**
   - Lei fiscal
   - Mesmo que inativas

3. **Todos os dados são perdidos**
   - Clientes, eventos, equipamentos
   - Histórico completo

---

## 📝 Changelog

| Data | Versão | Alteração |
|------|--------|-----------|
| 11/01/2025 | 1.0 | Implementação inicial |
|  | | - Slug único |
|  | | - Hard delete |
|  | | - Validação de faturas |
|  | | - Cascata completa |

---

## 📞 Suporte

Para problemas:
1. Verificar logs: `storage/logs/laravel.log`
2. Executar script de teste
3. Verificar status das faturas

---

**Última atualização:** 11/01/2025  
**Versão:** 1.0
