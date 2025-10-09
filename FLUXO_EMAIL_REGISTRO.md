# 📧 FLUXO COMPLETO - EMAIL DE BOAS-VINDAS NO REGISTRO

## 🔄 PASSO A PASSO

### **1. Usuário completa o registro**
📍 **Arquivo:** `app/Livewire/Auth/RegisterWizard.php`  
📍 **Método:** `completeRegistration()` (linha ~622)

```php
\Illuminate\Support\Facades\Mail::to($user->email)
    ->send(new \App\Mail\TemplateMail('welcome', $emailData, $tenant->id));
```

**Dados enviados:**
```php
$emailData = [
    'user_name' => $user->name,           // Nome do usuário
    'tenant_name' => $tenant->name,       // Nome da empresa
    'app_name' => config('app.name'),     // Nome do app
    'login_url' => route('login'),        // URL de login
];
```

---

### **2. Sistema chama a classe TemplateMail**
📍 **Arquivo:** `app/Mail/TemplateMail.php`  
📍 **Método:** `build()` (linha 36)

---

### **3. BUSCA O TEMPLATE 'welcome'**
📍 **Local:** Linha 39 do `TemplateMail.php`

```php
$template = EmailTemplate::bySlug($this->templateSlug)->active()->first();
```

#### **O QUE ISSO SIGNIFICA:**

**Query SQL executada:**
```sql
SELECT * FROM email_templates 
WHERE slug = 'welcome' 
AND is_active = 1 
LIMIT 1;
```

#### **Estrutura da tabela `email_templates`:**
```
- id
- slug: 'welcome'
- name: 'Email de Boas-Vindas'
- subject: 'Bem-vindo ao {{app_name}}!'
- body_html: '<html>...'
- body_text: 'Texto simples...'
- is_active: 1 (true/false)
- variables: ['user_name', 'tenant_name', 'login_url']
- created_at
- updated_at
```

#### **❌ Se o template não existir:**
```php
throw new \Exception("Template de email 'welcome' não encontrado.");
```

**Solução:** Executar seeder
```bash
php artisan db:seed --class=EmailTemplateSeeder
```

---

### **4. BUSCA CONFIGURAÇÕES SMTP**
📍 **Local:** Linha 46 do `TemplateMail.php`

```php
$smtpSetting = SmtpSetting::getForTenant($this->tenantId);
```

#### **O QUE ISSO SIGNIFICA:**

**Query SQL executada:**
```sql
SELECT * FROM smtp_settings 
WHERE tenant_id = ? 
AND is_active = 1 
LIMIT 1;
```

#### **Estrutura da tabela `smtp_settings`:**
```
- id
- tenant_id
- name: 'Gmail SMTP'
- host: 'smtp.gmail.com'
- port: 587
- username: 'tiofox2019@gmail.com'
- password: 'xxxx-xxxx-xxxx-xxxx' (criptografado)
- encryption: 'tls'
- from_email: 'tiofox2019@gmail.com'
- from_name: 'SOSERP'
- is_active: 1
- is_default: 0
- created_at
- updated_at
```

---

### **5. DECISÃO: Qual configuração usar?**

#### **Opção A: TEM configuração SMTP no banco (para o tenant)**
📍 **Local:** Linha 59-69 do `TemplateMail.php`

```php
if ($smtpSetting) {
    \Log::info('📧 Usando SMTP personalizado do banco');
    $smtpSetting->configure(); // Aplica as configurações do banco
}
```

**Exemplo de log:**
```
📧 Configurando SMTP personalizado para envio de email
- template: welcome
- smtp_host: smtp.gmail.com
- smtp_port: 587
- smtp_encryption: tls
- from_email: tiofox2019@gmail.com
```

#### **Opção B: NÃO tem configuração SMTP no banco**
📍 **Local:** Linha 48-57 do `TemplateMail.php`

```php
if (!$smtpSetting) {
    \Log::warning('⚠️ Usando configuração padrão do .env');
    // Usa as configurações do arquivo .env
}
```

**Busca no `.env`:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tiofox2019@gmail.com
MAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tiofox2019@gmail.com
MAIL_FROM_NAME="SOSERP"
```

**Exemplo de log:**
```
⚠️ Nenhuma configuração SMTP encontrada para tenant
- tenant_id: 1
- template: welcome
- default_mailer: log  ← ❌ PROBLEMA!
- default_host: (vazio)
```

---

### **6. RENDERIZA O TEMPLATE**
📍 **Local:** Linha 73 do `TemplateMail.php`

```php
$rendered = $template->render($this->data);
```

**O que acontece:**
1. Pega o HTML do template
2. Substitui as variáveis: `{{user_name}}`, `{{tenant_name}}`, etc.
3. Gera assunto e corpo do email

**Resultado:**
```php
[
    'subject' => 'Bem-vindo ao SOSERP!',
    'body_html' => '<html>Olá Teste TioFox...</html>',
    'body_text' => 'Olá Teste TioFox...'
]
```

---

### **7. ENVIA O EMAIL**
📍 **Local:** Linha 80-82 do `TemplateMail.php`

```php
return $this->subject($rendered['subject'])
    ->html($rendered['body_html'])
    ->text('emails.text', [...]);
```

**O Laravel então:**
- Conecta ao servidor SMTP
- Envia o email
- Registra nos logs

---

## 🎯 RESUMO DAS FONTES

### **Template de Boas-Vindas:**
```
1. Banco de dados → email_templates
2. Slug: 'welcome'
3. Condição: is_active = 1
```

### **Configurações SMTP (em ordem de prioridade):**

#### **1ª Opção - SMTP do Tenant** (Banco de Dados)
```
Tabela: smtp_settings
Condição: tenant_id = X AND is_active = 1
```

#### **2ª Opção - SMTP Padrão** (Arquivo .env)
```
Arquivo: .env
Variáveis:
- MAIL_MAILER
- MAIL_HOST
- MAIL_PORT
- MAIL_USERNAME
- MAIL_PASSWORD
- MAIL_ENCRYPTION
- MAIL_FROM_ADDRESS
```

---

## ❌ PROBLEMA ATUAL NO SEU SISTEMA

### **O que o teste mostrou:**
```
Driver: log          ← ❌ ERRADO! Deveria ser 'smtp'
Host: (vazio)        ← ❌ Sem servidor SMTP
Username: (vazio)    ← ❌ Sem credenciais
```

### **O que está acontecendo:**
1. ❌ Não há configuração SMTP na tabela `smtp_settings` para o tenant
2. ❌ O arquivo `.env` está com `MAIL_MAILER=log`
3. ❌ Emails são gravados em arquivo ao invés de serem enviados

---

## ✅ SOLUÇÕES

### **Solução 1: Configurar SMTP no .env** (Rápido)

Edite o `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tiofox2019@gmail.com
MAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx  # Senha de app do Gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tiofox2019@gmail.com
MAIL_FROM_NAME="SOSERP"
```

Depois:
```bash
php artisan config:clear
php test-register-email.php
```

---

### **Solução 2: Criar SMTP no Banco** (Melhor para multi-tenant)

```php
php artisan tinker
```

```php
\App\Models\SmtpSetting::create([
    'tenant_id' => 1, // ou null para global
    'name' => 'Gmail SMTP',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'tiofox2019@gmail.com',
    'password' => 'xxxx-xxxx-xxxx-xxxx', // Senha de app
    'encryption' => 'tls',
    'from_email' => 'tiofox2019@gmail.com',
    'from_name' => 'SOSERP',
    'is_active' => true,
    'is_default' => true,
]);
```

---

## 🔍 VERIFICAR SE ESTÁ FUNCIONANDO

### **1. Ver configurações atuais:**
```bash
php artisan tinker
```
```php
config('mail.mailers.smtp');
\App\Models\SmtpSetting::where('is_active', true)->get();
```

### **2. Ver template de boas-vindas:**
```php
\App\Models\EmailTemplate::where('slug', 'welcome')->first();
```

### **3. Testar envio:**
```bash
php test-register-email.php
```

---

## 📊 FLUXOGRAMA VISUAL

```
┌─────────────────────────┐
│   Usuário se Registra   │
└────────────┬────────────┘
             ↓
┌─────────────────────────────────────────┐
│  RegisterWizard::completeRegistration() │
│  Chama: TemplateMail('welcome', ...)   │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│      TemplateMail::build()              │
└────────────┬────────────────────────────┘
             ↓
      ┌──────┴──────┐
      ↓             ↓
┌──────────┐   ┌──────────────┐
│ TEMPLATE │   │ CONFIGURAÇÕES│
│          │   │     SMTP     │
└────┬─────┘   └──────┬───────┘
     ↓                ↓
┌─────────┐    ┌─────────────┐
│Banco de │    │  1. Banco   │
│Dados:   │    │  2. .env    │
│email_   │    │             │
│templates│    │             │
└────┬────┘    └──────┬──────┘
     ↓                ↓
     └────────┬───────┘
              ↓
    ┌─────────────────┐
    │ Renderiza Email │
    └────────┬────────┘
             ↓
    ┌─────────────────┐
    │   Envia SMTP    │
    └────────┬────────┘
             ↓
    ┌─────────────────┐
    │ Email Recebido! │
    └─────────────────┘
```

---

**Status Atual:** ❌ `MAIL_MAILER=log` → Emails não são enviados!  
**Solução:** Configurar SMTP no `.env` ou banco de dados
