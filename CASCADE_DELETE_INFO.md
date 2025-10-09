# 🗑️ Exclusão em Cascata de Tenants

## 📋 O que acontece ao deletar um tenant?

Quando você deleta um tenant (empresa), o sistema automaticamente executa uma **exclusão em cascata** de todos os dados relacionados.

## ✅ Dados que são DELETADOS automaticamente:

### 1. 👥 **Usuários**
- Remove todas as roles do usuário dentro deste tenant
- Remove o vínculo tenant_user
- Se o usuário não pertence a nenhum outro tenant, **deleta completamente o usuário**
- Se o usuário pertence a outros tenants, **mantém o usuário** mas remove apenas o vínculo com este tenant

### 2. 🔐 **Roles & Permissões**
- Deleta todas as roles específicas deste tenant
- Remove todas as permissões vinculadas às roles

### 3. 📋 **Subscriptions**
- Deleta todas as assinaturas do tenant
- Remove histórico de planos

### 4. 📦 **Orders** (Pedidos)
- Deleta todos os pedidos/compras do tenant
- Remove comprovantes de pagamento

### 5. 🧾 **Invoices** (Faturas)
- Deleta todas as faturas emitidas
- Remove histórico financeiro

### 6. 🧩 **Módulos**
- Remove todos os vínculos com módulos
- Desativa todos os módulos ativos

### 7. 📁 **Categorias de Equipamentos**
- Deleta todas as categorias criadas pelo tenant

### 8. 💳 **Métodos de Pagamento**
- Deleta métodos de pagamento customizados do tenant

### 9. 📅 **Eventos** (se existir)
- Deleta todos os eventos criados
- Remove agenda completa

### 10. 📦 **Equipamentos** (se existir)
- Deleta todo o inventário de equipamentos
- Remove histórico de uso

### 11. 📨 **Convites Pendentes**
- Deleta todos os convites de usuários ainda não aceitos

---

## 🛡️ Tipos de Exclusão

### 1️⃣ **Soft Delete** (Padrão)
```bash
# Via Interface (Super Admin > Tenants > Deletar)
# Ou via comando:
php artisan tenant:delete {id}
```

- Marca o tenant como deletado (campo `deleted_at`)
- **Executa toda a cascata de exclusão**
- Pode ser recuperado se necessário (restauração)

### 2️⃣ **Force Delete** (Permanente)
```bash
php artisan tenant:delete {id} --force
```

- **EXCLUSÃO PERMANENTE** - não pode ser recuperada
- Remove completamente do banco de dados
- **Também executa cascata de exclusão**

---

## 📊 Como deletar um tenant

### Via Interface Web:
1. Acesse: `Super Admin > Tenants`
2. Clique em **"Deletar"** no tenant desejado
3. Confirme a exclusão
4. ✅ Sistema executa cascata automaticamente

### Via Comando (Terminal):
```bash
# Soft delete (recomendado)
php artisan tenant:delete 5

# Force delete (permanente)
php artisan tenant:delete 5 --force
```

---

## 📝 Logs

Toda exclusão é registrada em:
```
storage/logs/laravel.log
```

Exemplo de log:
```
🗑️ INICIANDO EXCLUSÃO EM CASCATA DO TENANT
   tenant_id: 5
   tenant_name: Empresa XYZ
   deleted_by: 1

   👤 Usuário deletado: user1@example.com
   👤 Usuário deletado: user2@example.com
   🔐 3 roles deletadas
   📋 2 subscriptions deletadas
   📦 1 orders deletadas
   🧾 5 invoices deletadas
   🧩 Módulos desvinculados
   📁 9 categorias de equipamentos deletadas
   💳 7 métodos de pagamento deletados
   📅 12 eventos deletados
   📦 45 equipamentos deletados
   📨 2 convites deletados

✅ EXCLUSÃO EM CASCATA CONCLUÍDA COM SUCESSO
```

---

## ⚠️ IMPORTANTE

### ❌ **Esta ação é IRREVERSÍVEL quando feita com --force!**
### ⚠️ **Sempre faça backup antes de deletar tenants importantes**
### 📋 **Verifique os logs após cada exclusão**

---

## 🔧 Código Responsável

Arquivo: `app/Models/Tenant.php`
Método: `boot() -> static::deleting()`

---

## 🆘 Recuperar Tenant (apenas soft delete)

```bash
# Listar tenants deletados
php artisan tinker
>>> Tenant::onlyTrashed()->get()

# Restaurar tenant
>>> Tenant::withTrashed()->find(5)->restore()
```

**⚠️ ATENÇÃO:** A restauração do tenant **NÃO restaura** os dados deletados em cascata!
Uma vez que os dados foram deletados pela cascata, eles não podem ser recuperados.

---

✅ **Sistema configurado para exclusão segura e automática!**
