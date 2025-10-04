# Resolução do Erro 419 - Page Expired

## 🐛 Problema

Erro **419 Page Expired** ao tentar fazer login ou submeter formulários.

## 🔍 Causa

Este erro ocorre quando o token CSRF (Cross-Site Request Forgery) expira ou não está sendo enviado corretamente.

## ✅ Soluções

### **Solução 1: Limpar Todos os Caches (RECOMENDADA)**

Execute no terminal:

```bash
cd c:\laragon2\www\soserp
php artisan optimize:clear
```

Isso limpa:
- Cache de configuração
- Cache de rotas
- Cache de views
- Cache da aplicação
- Cache de eventos

### **Solução 2: Limpar Cache do Navegador**

1. **Chrome/Edge:**
   - Pressione `Ctrl + Shift + Delete`
   - Selecione "Cookies e outros dados do site"
   - Selecione "Imagens e arquivos em cache"
   - Clique em "Limpar dados"

2. **Firefox:**
   - Pressione `Ctrl + Shift + Delete`
   - Selecione "Cookies" e "Cache"
   - Clique em "Limpar agora"

3. **Forçar atualização:**
   - Pressione `Ctrl + F5` ou `Ctrl + Shift + R`

### **Solução 3: Verificar Permissões de Pastas**

```bash
# Certifique-se que estas pastas têm permissões de escrita:
storage/
storage/framework/
storage/framework/sessions/
storage/logs/
bootstrap/cache/
```

### **Solução 4: Recriar Tabela de Sessões** (se usando database)

```bash
php artisan session:table
php artisan migrate
```

### **Solução 5: Verificar Configurações do .env**

Adicione/verifique estas linhas no arquivo `.env`:

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

## 🔧 Comandos Úteis

### Limpar Caches Individualmente:

```bash
# Cache de configuração
php artisan config:clear

# Cache de rotas
php artisan route:clear

# Cache de views
php artisan view:clear

# Cache da aplicação
php artisan cache:clear
```

### Verificar Sessões:

```sql
-- Ver sessões ativas
SELECT * FROM sessions ORDER BY last_activity DESC LIMIT 10;

-- Limpar sessões antigas
DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY));
```

## 🚀 Solução Rápida (Copiar e Colar)

```bash
cd c:\laragon2\www\soserp
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

Depois:
1. Feche o navegador completamente
2. Limpe cookies/cache do navegador
3. Abra novamente e teste

## 🔍 Debug Adicional

Se o problema persistir, verifique:

### 1. Logs do Laravel:
```bash
tail -f storage/logs/laravel.log
```

### 2. Verificar se sessões estão sendo criadas:
```sql
SELECT COUNT(*) FROM sessions;
```

### 3. Testar em modo anônimo:
- Abra janela anônima/privada do navegador
- Tente fazer login

### 4. Verificar APP_KEY:
```bash
# Se APP_KEY estiver vazio, gere uma nova:
php artisan key:generate
```

⚠️ **ATENÇÃO:** Gerar nova APP_KEY invalida sessões e senhas criptografadas!

## 📋 Checklist de Verificação

- [ ] Cache limpo com `php artisan optimize:clear`
- [ ] Cache do navegador limpo
- [ ] Permissões de pastas corretas
- [ ] Arquivo `.env` configurado
- [ ] APP_KEY presente no `.env`
- [ ] Tabela `sessions` existe (se usando database)
- [ ] Token `@csrf` presente nos formulários
- [ ] Cookies habilitados no navegador

## 🌐 Configurações de Produção

Para ambientes de produção com HTTPS:

```env
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.seudominio.com
```

## 💡 Prevenção

Para evitar este erro no futuro:

1. **Não** cache views em desenvolvimento
2. Limpe cache após mudanças em configurações
3. Configure SESSION_LIFETIME adequadamente
4. Use `php artisan optimize` apenas em produção
5. Mantenha logs limpos e monitorados

## 📚 Referências

- [Laravel Sessions](https://laravel.com/docs/11.x/session)
- [Laravel CSRF Protection](https://laravel.com/docs/11.x/csrf)
- [HTTP Cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies)
