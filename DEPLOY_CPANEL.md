# 🚀 Guia de Deploy - cPanel

## 📋 Pré-requisitos
- PHP 8.1 ou superior
- Composer instalado no servidor
- Acesso SSH (recomendado) ou Terminal do cPanel

---

## 🔧 Passo 1: Upload dos Arquivos

### Estrutura no cPanel:
```
/home/soserp/
├── public_html/          ← Raiz pública (Document Root)
│   ├── .htaccess         ← Arquivo de rewrite (usar .htaccess.cpanel)
│   └── (Laravel ficará FORA, no nível acima)
│
├── soserp/              ← Código Laravel (FORA do public_html)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/          ← Conteúdo vai para public_html
│   │   ├── index.php
│   │   ├── css/
│   │   ├── js/
│   │   └── storage/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── .env
│   └── composer.json
```

---

## ⚙️ Passo 2: Configurar .htaccess

### Copie o arquivo `.htaccess.cpanel` para `/home/soserp/public_html/.htaccess`

**Via Terminal/SSH:**
```bash
cd /home/soserp
cp soserp/.htaccess.cpanel public_html/.htaccess
```

**Via File Manager:**
1. Abra o File Manager no cPanel
2. Navegue até `/home/soserp/soserp/`
3. Copie o arquivo `.htaccess.cpanel`
4. Cole em `/home/soserp/public_html/` e renomeie para `.htaccess`

---

## 📁 Passo 3: Mover Conteúdo de public/

### Mover arquivos da pasta public do Laravel para public_html:

**Via Terminal/SSH:**
```bash
cd /home/soserp
# Criar backup
cp -r public_html public_html.backup

# Copiar conteúdo de public/
cp -r soserp/public/* public_html/
cp soserp/public/.htaccess public_html/.htaccess.original

# O .htaccess da raiz substitui o do Laravel
```

---

## 🔗 Passo 4: Atualizar index.php

### Editar `/home/soserp/public_html/index.php`

**Localizar:**
```php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
```

**Substituir por:**
```php
require __DIR__.'/../soserp/vendor/autoload.php';
$app = require_once __DIR__.'/../soserp/bootstrap/app.php';
```

---

## 🔐 Passo 5: Configurar .env

### Editar `/home/soserp/soserp/.env`

```env
APP_NAME=SOSERP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=soserp_db
DB_USERNAME=soserp_user
DB_PASSWORD=SUA_SENHA_SEGURA

# Cache & Session (use 'file' se não tiver Redis)
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Mail (configurar SMTP)
MAIL_MAILER=smtp
MAIL_HOST=mail.seudominio.com
MAIL_PORT=587
MAIL_USERNAME=noreply@seudominio.com
MAIL_PASSWORD=sua_senha_email
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 📦 Passo 6: Instalar Dependências

**Via Terminal/SSH:**
```bash
cd /home/soserp/soserp
composer install --optimize-autoloader --no-dev
```

---

## 🗄️ Passo 7: Configurar Database

```bash
cd /home/soserp/soserp

# Rodar migrations
php artisan migrate --force

# Rodar seeders (se necessário)
php artisan db:seed --force

# Limpar cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🔗 Passo 8: Criar Symlink do Storage

**IMPORTANTE:** O storage precisa estar acessível publicamente.

**Via Terminal/SSH:**
```bash
cd /home/soserp/public_html
ln -s /home/soserp/soserp/storage/app/public storage
```

**Verificar:**
```bash
ls -la /home/soserp/public_html/storage
# Deve mostrar: storage -> /home/soserp/soserp/storage/app/public
```

---

## 🔒 Passo 9: Permissões

```bash
cd /home/soserp/soserp

# Storage e cache precisam ser writeable
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Se o servidor usar www-data ou apache
chown -R $USER:www-data storage
chown -R $USER:www-data bootstrap/cache
```

---

## ✅ Passo 10: Testar

1. Acesse: `https://seudominio.com`
   - Deve carregar a landing page

2. Acesse: `https://seudominio.com/login`
   - Deve carregar a página de login

3. Acesse: `https://seudominio.com/storage/settings/alguma-imagem.png`
   - Deve carregar a imagem

---

## 🐛 Troubleshooting

### Problema: 500 Internal Server Error
**Solução:**
```bash
# Verificar logs do Laravel
tail -f /home/soserp/soserp/storage/logs/laravel.log

# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Problema: Imagens não carregam (404)
**Solução:**
```bash
# Verificar symlink
ls -la /home/soserp/public_html/storage

# Recriar symlink
rm /home/soserp/public_html/storage
ln -s /home/soserp/soserp/storage/app/public /home/soserp/public_html/storage

# Verificar permissões
chmod -R 775 /home/soserp/soserp/storage/app/public
```

### Problema: CSS/JS não carregam
**Solução:**
```bash
# Verificar se os arquivos estão em public_html
ls -la /home/soserp/public_html/css
ls -la /home/soserp/public_html/js

# Recompilar assets (se usar Vite/Mix)
npm run build
```

### Problema: Rotas não funcionam (404)
**Solução:**
1. Verificar se `.htaccess` está em `/home/soserp/public_html/`
2. Verificar se mod_rewrite está ativado (pedir ao suporte do cPanel)
3. Verificar se `AllowOverride All` está configurado

---

## 📊 Performance

### Depois do deploy, otimizar:
```bash
cd /home/soserp/soserp

# Cache de configurações
php artisan config:cache

# Cache de rotas
php artisan route:cache

# Cache de views
php artisan view:cache

# Otimizar autoload
composer dump-autoload --optimize
```

---

## 🔄 Atualizações Futuras

```bash
cd /home/soserp/soserp

# Pull do Git
git pull origin main

# Atualizar dependências
composer install --optimize-autoload --no-dev

# Rodar migrations
php artisan migrate --force

# Limpar e recriar cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📝 Notas Importantes

1. **Segurança:** NUNCA coloque a pasta Laravel completa dentro de `public_html`
2. **Permissões:** Sempre use 775 para pastas e 664 para arquivos
3. **Cache:** Sempre limpe o cache após mudanças no `.env` ou rotas
4. **Symlink:** Verifique se o symlink `storage` existe e está correto
5. **Database:** Faça backups regulares do banco de dados

---

## 🆘 Suporte

Se encontrar problemas, verifique:
- Logs: `/home/soserp/soserp/storage/logs/laravel.log`
- Logs do Apache: Painel de controle do cPanel → Error Log
- PHP Version: Certifique-se de usar PHP 8.1+

---

**✅ Deploy concluído com sucesso!**
