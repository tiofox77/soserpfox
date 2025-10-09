# 🔍 GUIA DE DIAGNÓSTICO - EMAIL DE BOAS-VINDAS

## ❌ PROBLEMA
Usuários não estão recebendo o email de boas-vindas após o registro.

---

## 🧪 COMO TESTAR

### **Método 1: Script Automático** (Recomendado)
```bash
php test-register-email.php
```

Este script irá:
1. ✅ Verificar configurações de email
2. ✅ Verificar template 'welcome'
3. ✅ Enviar email de teste simples
4. ✅ Criar usuário e tenant de teste
5. ✅ Enviar email de boas-vindas completo
6. ✅ Mostrar logs detalhados

### **Método 2: Manual via Tinker**
```bash
php artisan tinker
```

```php
// Testar email simples
Mail::raw('Teste', fn($m) => $m->to('tiofox2019@gmail.com')->subject('Teste'));

// Testar email de boas-vindas
$user = User::where('email', 'tiofox2019@gmail.com')->first();
$tenant = Tenant::find($user->tenant_id);

$emailData = [
    'user_name' => $user->name,
    'user_email' => $user->email,
    'tenant_name' => $tenant->name,
    'login_url' => url('/login'),
    'trial_days' => 30,
    'support_email' => config('mail.from.address'),
];

Mail::to($user->email)->send(new \App\Mail\TemplateMail('welcome', $emailData, $tenant->id));
```

---

## 🔧 POSSÍVEIS CAUSAS E SOLUÇÕES

### **1. Configurações SMTP Incorretas**

#### Verificar `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com          # ou seu servidor SMTP
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-senha-app        # ⚠️ Use senha de app, não a senha normal!
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=seu-email@gmail.com
MAIL_FROM_NAME="SOSERP"
```

#### **Gmail - Senha de App:**
1. Acesse: https://myaccount.google.com/apppasswords
2. Crie uma "Senha de app" para o SOSERP
3. Use essa senha no `MAIL_PASSWORD`

#### **Testar configurações:**
```bash
php artisan config:clear
php artisan cache:clear
```

---

### **2. Template 'welcome' Não Existe ou Está Inativo**

#### Verificar no banco:
```sql
SELECT * FROM email_templates WHERE slug = 'welcome';
```

#### Criar template (se não existir):
```bash
php artisan db:seed --class=EmailTemplateSeeder
```

#### Ativar template:
```php
php artisan tinker
EmailTemplate::where('slug', 'welcome')->update(['is_active' => true]);
```

---

### **3. Email Cai no SPAM**

#### Verificar:
- ✅ Pasta de SPAM/Lixo Eletrônico
- ✅ Pasta de Promoções (Gmail)
- ✅ Filtros de email

#### Melhorar reputação:
1. Configure SPF, DKIM e DMARC no DNS
2. Use servidor SMTP confiável
3. Evite palavras "spam" no assunto
4. Adicione remetente aos contatos

---

### **4. Firewall/Porta Bloqueada**

#### Testar conexão SMTP:
```bash
# Windows (PowerShell)
Test-NetConnection smtp.gmail.com -Port 587

# Linux/Mac
telnet smtp.gmail.com 587
```

#### Portas comuns:
- **587** - TLS (recomendado)
- **465** - SSL
- **25** - Não criptografado (evitar)

---

### **5. Limite de Envio Atingido**

#### Gmail:
- **Gratuito:** 500 emails/dia
- **Workspace:** 2000 emails/dia

#### Solução:
Use serviço profissional:
- Mailgun
- SendGrid
- Amazon SES
- Postmark

---

### **6. Email de Teste em Modo QUEUE**

#### Verificar `.env`:
```env
QUEUE_CONNECTION=sync  # ✅ Envio imediato
# QUEUE_CONNECTION=database  # ⚠️ Precisa processar fila
```

#### Se estiver usando fila, processar:
```bash
php artisan queue:work
```

---

## 📊 LOGS E DEBUGGING

### **1. Ativar Log de Emails**

Adicione no `.env`:
```env
MAIL_LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### **2. Ver logs em tempo real:**
```bash
tail -f storage/logs/laravel.log
```

### **3. Verificar últimos emails enviados:**
```bash
php artisan tinker
```
```php
// Ver últimos logs
DB::table('jobs')->latest()->take(10)->get();
DB::table('failed_jobs')->latest()->take(10)->get();
```

---

## 🧪 TESTE COMPLETO PASSO A PASSO

### **Passo 1: Verificar .env**
```bash
cat .env | grep MAIL
```

### **Passo 2: Limpar caches**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### **Passo 3: Testar SMTP**
```bash
php test-register-email.php
```

### **Passo 4: Verificar email**
- Caixa de entrada
- Pasta SPAM
- Pasta Promoções

### **Passo 5: Ver logs**
```bash
cat storage/logs/laravel.log | grep -i mail
```

---

## ✅ CHECKLIST DE VERIFICAÇÃO

- [ ] Configurações SMTP corretas no `.env`
- [ ] Senha de app configurada (Gmail)
- [ ] Template 'welcome' existe e está ativo
- [ ] Porta SMTP (587) não está bloqueada
- [ ] Email não está no SPAM
- [ ] Logs não mostram erros
- [ ] `QUEUE_CONNECTION=sync` (ou fila processando)
- [ ] Limite de envio não atingido

---

## 🆘 CONFIGURAÇÃO ALTERNATIVA - MAILTRAP (Desenvolvimento)

Para testes em desenvolvimento, use Mailtrap:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=seu-username-mailtrap
MAIL_PASSWORD=sua-senha-mailtrap
MAIL_ENCRYPTION=tls
```

**Vantagens:**
- ✅ Captura todos os emails
- ✅ Não envia para caixas reais
- ✅ Interface web para visualizar
- ✅ Gratuito para testes

---

## 📧 CÓDIGO DO EMAIL NO SISTEMA

### **Local onde o email é enviado:**
`app/Livewire/Auth/RegisterWizard.php` - Linha ~622

```php
\Illuminate\Support\Facades\Mail::to($user->email)
    ->send(new \App\Mail\TemplateMail('welcome', $emailData, $tenant->id));
```

### **Classe do email:**
`app/Mail/TemplateMail.php`

### **Template no banco:**
Tabela: `email_templates`  
Slug: `welcome`

---

## 🔄 FLUXO COMPLETO DO EMAIL

1. **Usuário se registra** → `RegisterWizard::completeRegistration()`
2. **Sistema cria usuário e tenant**
3. **Busca template 'welcome'** no banco
4. **Prepara dados** (`$emailData`)
5. **Envia email** via `TemplateMail`
6. **SMTP processa** e envia
7. **Usuário recebe** (ou vai para SPAM)

---

## 💡 SOLUÇÃO RÁPIDA

Se nada funcionar, use esta configuração de emergência:

### **Gmail Relay (Simples):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tiofox2019@gmail.com
MAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx  # Senha de app!
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tiofox2019@gmail.com
MAIL_FROM_NAME="SOSERP"
QUEUE_CONNECTION=sync
```

**Depois:**
```bash
php artisan config:clear
php test-register-email.php
```

---

## 📞 SUPORTE

Se o problema persistir:
1. Execute: `php test-register-email.php`
2. Copie toda a saída do script
3. Verifique os logs: `storage/logs/laravel.log`
4. Entre em contato com as informações acima

---

**Última atualização:** 09/10/2025  
**Autor:** Sistema SOSERP
