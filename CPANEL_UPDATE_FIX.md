# 🚨 Guia Rápido: Resolver Erro de Atualização no cPanel

## ❌ Erro Encontrado:
```
SQLSTATE[HY000]: General error: 1291 Column 'document_type' has duplicated value 'GT' in ENUM
```

---

## ✅ Solução Rápida (3 Comandos)

### **1. Acesse o Terminal SSH do cPanel**
```bash
cd /home/soserp/soserp
```

### **2. Corrija as Migrations**
```bash
php artisan migrations:fix
```

### **3. Execute as Migrations Novamente**
```bash
php artisan migrate --force
```

---

## 🛠️ Soluções Alternativas

### **Opção A: Corrigir Apenas ENUMs**
```bash
php artisan migrations:fix --enum
php artisan migrate --force
```

### **Opção B: Apenas Verificar (sem alterar)**
```bash
php artisan migrations:fix --check
```

### **Opção C: Remover Migrations Duplicadas**
```bash
php artisan migrations:fix --duplicates
php artisan migrate --force
```

---

## 🔍 Verificar Status das Migrations

### Ver quais migrations estão pendentes:
```bash
php artisan migrate:status
```

### Ver SQL que será executado (sem executar):
```bash
php artisan migrate --pretend
```

---

## 🗄️ Solução Manual no MySQL

Se os comandos acima não funcionarem, execute diretamente no banco de dados:

### **1. Acesse o phpMyAdmin ou MySQL CLI**

### **2. Execute este SQL:**
```sql
-- Corrigir ENUM duplicado na tabela invoicing_series
ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM(
    'invoice', 
    'proforma', 
    'receipt', 
    'credit_note', 
    'debit_note', 
    'pos', 
    'purchase', 
    'advance',
    'FT',
    'FS',
    'FR',
    'NC',
    'ND',
    'GT',
    'FP',
    'VD',
    'GR',
    'GC',
    'RC'
) COMMENT 'Tipo de documento';
```

### **3. Depois execute:**
```bash
php artisan migrate --force
```

---

## 📋 Checklist de Problemas Comuns

### ✅ Problema: "Table already exists"
**Solução:**
```bash
# Marcar a migration como executada sem rodar novamente
php artisan migrate:status
# Identificar o nome da migration problemática
# Adicionar manualmente na tabela migrations
```

### ✅ Problema: "Column already exists"
**Solução:**
```bash
# O sistema agora verifica automaticamente
php artisan migrations:fix
php artisan migrate --force
```

### ✅ Problema: "ENUM has duplicate value"
**Solução:**
```bash
php artisan migrations:fix --enum
php artisan migrate --force
```

### ✅ Problema: "Access denied"
**Solução:**
```bash
# Verificar permissões do usuário do banco
# Verificar o arquivo .env
cat .env | grep DB_
```

---

## 🔒 Rollback de Emergência

Se algo der muito errado e você precisar voltar:

### **1. Fazer backup do banco ANTES:**
```bash
mysqldump -u usuario -p nome_banco > backup_antes_update.sql
```

### **2. Reverter última migration:**
```bash
php artisan migrate:rollback --step=1
```

### **3. Reverter todas as migrations do último batch:**
```bash
php artisan migrate:rollback
```

### **4. Restaurar backup:**
```bash
mysql -u usuario -p nome_banco < backup_antes_update.sql
```

---

## 📝 Log de Erros

### Ver logs do Laravel:
```bash
tail -f storage/logs/laravel.log
```

### Ver últimas 50 linhas:
```bash
tail -n 50 storage/logs/laravel.log
```

### Limpar logs:
```bash
> storage/logs/laravel.log
```

---

## 🆘 Contato de Suporte

Se nenhuma das soluções acima funcionar:

1. **Copie o erro completo** do log
2. **Tire print do erro** na tela
3. **Verifique a versão** do PHP e MySQL:
   ```bash
   php -v
   mysql --version
   ```
4. **Contate o suporte técnico** com essas informações

---

## ✅ Após Resolver

### Verificar se tudo está funcionando:

1. **Login no sistema**
   - Acesse: `https://seudominio.com/login`
   - Faça login com suas credenciais

2. **Verificar migrations**
   ```bash
   php artisan migrate:status
   ```

3. **Limpar cache**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **Otimizar**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 🎉 Sucesso!

Se você chegou até aqui e tudo funcionou:

```bash
✅ Migrations executadas com sucesso
✅ Sistema atualizado para v1.0.1
✅ Banco de dados atualizado
✅ Cache limpo e otimizado
```

**O sistema está pronto para uso! 🚀**

---

## 📚 Documentação Adicional

- [Boas Práticas de Migration](DOC/MIGRATION_BEST_PRACTICES.md)
- [Deploy no cPanel](DEPLOY_CPANEL.md)
- [Guia de Atualização](UPDATE_GUIDE.md)
