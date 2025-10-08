# 🔧 CORREÇÃO DE ROLES E PERMISSÕES - cPANEL

Este documento explica como corrigir roles e permissões de usuários diretamente no cPanel.

---

## 📋 **O QUE ESTE COMANDO FAZ:**

1. ✅ Cria roles padrão para todos os tenants (Super Admin, Admin, Gestor, Utilizador)
2. ✅ Sincroniza permissões corretas para cada role
3. ✅ Corrige usuários sem roles
4. ✅ Remove roles antigas/duplicadas sem usuários
5. ✅ Protege o super admin do sistema (ID 1)

---

## 🚀 **COMO EXECUTAR NO cPANEL:**

### **Passo 1: Acessar Terminal SSH**
1. Acesse o cPanel
2. Vá em "Terminal" ou "SSH Access"
3. Conecte ao servidor

### **Passo 2: Navegar até o diretório do projeto**
```bash
cd /home/seu-usuario/public_html
# OU
cd /home/seu-usuario/soserp
```

### **Passo 3: MODO TESTE (recomendado primeiro)**
Execute para VER o que seria corrigido, SEM fazer alterações:

```bash
php artisan users:fix-roles-permissions --dry-run
```

**Saída esperada:**
```
🔧 CORREÇÃO DE ROLES E PERMISSÕES DE USUÁRIOS
============================================

⚠️  MODO DRY-RUN: Nenhuma alteração será feita!

📋 Passo 1: Verificando roles padrão dos tenants...
   Tenants encontrados: 5
   ✅ Roles padrão verificadas para todos os tenants

👥 Passo 2: Corrigindo usuários sem roles...
   Usuários encontrados: 15
   ✅ Usuários sem roles corrigidos

🔐 Passo 3: Corrigindo roles sem permissões...
   ✅ Permissões sincronizadas

🗑️  Passo 4: Limpando roles antigas...
   Roles antigas encontradas: 3
   🗑️  Removida role 'super-admin' (sem usuários)
   ✅ Limpeza concluída

═══════════════════════════════════════════════
📊 ESTATÍSTICAS
═══════════════════════════════════════════════

+---------------------------+-----------+
| Métrica                   | Quantidade|
+---------------------------+-----------+
| Usuários verificados      | 15        |
| Usuários corrigidos       | 3         |
| Roles criadas             | 8         |
| Permissões sincronizadas  | 12        |
| Erros                     | 0         |
+---------------------------+-----------+

💡 Para aplicar as correções, execute sem --dry-run:
   php artisan users:fix-roles-permissions
```

### **Passo 4: APLICAR CORREÇÕES**
Se estiver tudo OK no teste, execute para APLICAR as correções:

```bash
php artisan users:fix-roles-permissions
```

---

## ⚙️ **ESTRUTURA DE ROLES CRIADA:**

| Role | Permissões | Quem Recebe |
|------|------------|-------------|
| **Super Admin** | 115 (TODAS) | Donos de tenant |
| **Admin** | ~100 (exceto system.*) | Administradores |
| **Gestor** | ~77 (view, create, edit) | Gestores |
| **Utilizador** | ~38 (apenas view) | Usuários normais |

---

## 🔒 **PROTEÇÕES:**

✅ **Usuário ID 1** (super admin do sistema) NUNCA é alterado
✅ **Roles com usuários ativos** NÃO são deletadas
✅ **Modo dry-run** permite testar antes de aplicar
✅ **Estatísticas detalhadas** mostram o que foi feito

---

## 🐛 **PROBLEMAS COMUNS:**

### **Erro: "Command not found"**
```bash
# Use o caminho completo do PHP:
/usr/bin/php artisan users:fix-roles-permissions
```

### **Erro: "Permission denied"**
```bash
# Ajuste permissões:
chmod +x artisan
```

### **Erro: "Class not found"**
```bash
# Limpe cache:
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

---

## 📊 **QUANDO EXECUTAR:**

Execute este comando quando:
- ✅ Usuários reclamam de falta de permissões
- ✅ Após migração/atualização do sistema
- ✅ Após adicionar novos tenants
- ✅ Periodicamente (mensal) para manutenção

---

## 💾 **BACKUP RECOMENDADO:**

**ANTES** de executar, faça backup do banco:

```bash
# Via cPanel: Backup Manager
# OU via mysqldump:
mysqldump -u usuario -p database_name > backup_antes_correcao.sql
```

---

## 📝 **LOG DE EXECUÇÃO:**

O comando registra tudo em:
```
storage/logs/laravel.log
```

Para ver o log:
```bash
tail -100 storage/logs/laravel.log
```

---

## ✅ **VERIFICAÇÃO PÓS-EXECUÇÃO:**

Após executar, teste:

1. Login com usuário normal
2. Verificar menu de navegação
3. Tentar acessar funcionalidades
4. Verificar mensagens de erro

Se houver problemas:
```bash
# Execute novamente:
php artisan users:fix-roles-permissions

# Limpe cache:
php artisan cache:clear
```

---

## 🆘 **SUPORTE:**

Em caso de problemas:
1. Execute com `--dry-run` primeiro
2. Verifique os logs em `storage/logs/laravel.log`
3. Faça backup do banco antes de aplicar
4. Documente os erros para suporte

---

**✅ Script seguro e testado! Pode executar com confiança.**
