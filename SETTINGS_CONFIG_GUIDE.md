# ⚙️ Configurações do Sistema - Guia Completo

## 📋 **Configurações Implementadas**

Todas as configurações da landing page agora são **dinâmicas** e vêm do banco de dados (`system_settings`).

---

## 🎯 **Configurações Disponíveis:**

### 1. **General (Geral)**
| Chave | Descrição | Exemplo |
|-------|-----------|---------|
| `app_name` | Nome da aplicação | SOSERP |
| `app_description` | Descrição curta | Sistema ERP Multi-tenant |
| `app_url` | URL principal | https://soserp.vip |
| `contact_email` | Email de contato | contato@soserp.vip |
| `contact_phone` | Telefone de contato | +244 939 779 902 |

### 2. **Appearance (Aparência)**
| Chave | Descrição | Tipo |
|-------|-----------|------|
| `app_logo` | Logo principal | Imagem (path) |
| `app_favicon` | Favicon | Imagem (path) |
| `primary_color` | Cor primária | #4F46E5 |
| `secondary_color` | Cor secundária | #06B6D4 |

### 3. **SEO**
| Chave | Descrição |
|-------|-----------|
| `seo_title` | Título SEO da página |
| `seo_description` | Meta description |
| `seo_keywords` | Palavras-chave |
| `seo_author` | Autor do site |

### 4. **Schema.org (JSON-LD)**
| Chave | Descrição | Usado em |
|-------|-----------|----------|
| `schema_app_name` | Nome no schema | JSON-LD |
| `schema_app_description` | Descrição no schema | JSON-LD |
| `schema_app_url` | URL no schema | JSON-LD |
| `schema_app_category` | Categoria (BusinessApplication) | JSON-LD |
| `schema_price` | Preço inicial | JSON-LD Offer |
| `schema_currency` | Moeda (AOA) | JSON-LD Offer |
| `schema_region` | Região (Angola) | JSON-LD Offer |
| `schema_rating_value` | Nota média (4.8) | JSON-LD Rating |
| `schema_review_count` | Número de avaliações | JSON-LD Rating |
| `schema_creator_name` | Nome do criador | JSON-LD Creator |
| `schema_creator_url` | URL do criador | JSON-LD Creator |

---

## 🔧 **Como Editar as Configurações:**

### **Método 1: Via Tinker (Terminal)**
```bash
php artisan tinker

# Ver configuração atual
SystemSetting::get('app_name');

# Alterar configuração
SystemSetting::set('app_name', 'NOVO NOME');

# Ver todas as configurações de um grupo
SystemSetting::getByGroup('schema');
```

### **Método 2: Via SQL Direto**
```sql
-- Ver todas as configurações
SELECT * FROM system_settings ORDER BY `group`, `key`;

-- Atualizar uma configuração
UPDATE system_settings 
SET value = 'SOSERP - Sistema Completo' 
WHERE key = 'app_name';

-- Atualizar logo
UPDATE system_settings 
SET value = 'logos/soserp-logo.png' 
WHERE key = 'app_logo';
```

### **Método 3: Via Código PHP**
```php
use App\Models\SystemSetting;

// Alterar nome
SystemSetting::set('app_name', 'SOSERP');

// Alterar descrição do schema
SystemSetting::set('schema_app_description', 'Sistema completo de gestão empresarial');

// Alterar rating
SystemSetting::set('schema_rating_value', '4.9');
SystemSetting::set('schema_review_count', '200');

// Limpar cache
SystemSetting::clearCache();
```

---

## 📸 **Upload de Logo/Favicon:**

### **Upload via Storage:**
```bash
# 1. Colocar arquivo na pasta public/storage/logos/
# 2. Atualizar configuração
php artisan tinker
SystemSetting::set('app_logo', 'logos/soserp-logo.png');
```

### **Upload via Interface (recomendado):**
Criar uma interface de administração em `Super Admin > Configurações`

---

## 🌐 **Como as Configurações Aparecem na Landing Page:**

### **Meta Tags SEO:**
```html
<title>{{ $settings['seo_title'] }}</title>
<meta name="description" content="{{ $settings['seo_description'] }}">
<meta name="keywords" content="{{ $settings['seo_keywords'] }}">
```

### **Open Graph (Facebook/WhatsApp):**
```html
<meta property="og:title" content="{{ $settings['seo_title'] }}">
<meta property="og:image" content="{{ asset('storage/' . $settings['app_logo']) }}">
```

### **JSON-LD Schema:**
```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "{{ $settings['schema_app_name'] }}",
  "description": "{{ $settings['schema_app_description'] }}",
  "url": "{{ $settings['schema_app_url'] }}",
  ...
}
```

### **Logo no Navbar:**
```html
@if(app_logo())
    <img src="{{ app_logo() }}" alt="{{ app_name() }}">
@else
    <span>{{ app_name() }}</span>
@endif
```

---

## 🔍 **Helpers Disponíveis:**

```php
// Pegar qualquer configuração
setting('app_name', 'Default');

// Pegar logo (com URL completo)
app_logo(); // Retorna: /storage/logos/logo.png

// Pegar favicon
app_favicon();

// Pegar nome da app
app_name();
```

---

## ✅ **Exemplo de Configuração Completa:**

```bash
php artisan tinker

# General
SystemSetting::set('app_name', 'SOSERP');
SystemSetting::set('app_description', 'Sistema ERP Completo para Angola');
SystemSetting::set('contact_email', 'suporte@soserp.vip');
SystemSetting::set('contact_phone', '+244 939 779 902');

# SEO
SystemSetting::set('seo_title', 'SOSERP - Sistema de Gestão Empresarial em Angola');
SystemSetting::set('seo_description', 'Plataforma completa para gestão de eventos, inventário, CRM, faturação e contabilidade.');
SystemSetting::set('seo_keywords', 'ERP Angola, Gestão Empresarial, Eventos, Faturação');

# Schema
SystemSetting::set('schema_app_name', 'SOSERP');
SystemSetting::set('schema_app_description', 'Sistema Multi-Tenant para gestão empresarial');
SystemSetting::set('schema_app_url', 'https://soserp.vip');
SystemSetting::set('schema_rating_value', '4.9');
SystemSetting::set('schema_review_count', '200');

# Logo (após upload)
SystemSetting::set('app_logo', 'logos/soserp-logo.png');
SystemSetting::set('app_favicon', 'logos/favicon.png');

# Limpar cache
SystemSetting::clearCache();
```

---

## 🚀 **Cache:**

As configurações são **automaticamente cacheadas por 1 hora**.

**Limpar cache:**
```bash
php artisan tinker
SystemSetting::clearCache();
```

Ou via código:
```php
Cache::flush();
```

---

## 📊 **Verificar Configurações Atuais:**

```bash
php artisan tinker

# Ver todas as configurações
DB::table('system_settings')->orderBy('group')->get(['key', 'value', 'group']);

# Ver apenas grupo schema
SystemSetting::where('group', 'schema')->get(['key', 'value']);
```

---

## ⚠️ **Importante:**

1. ✅ **Logo/Favicon** devem estar em `storage/app/public/logos/`
2. ✅ Link simbólico deve existir: `php artisan storage:link`
3. ✅ Após alterar, limpar cache: `SystemSetting::clearCache()`
4. ✅ JSON-LD usa `@@` em vez de `@` por causa do Blade

---

## 🎨 **Próximos Passos:**

Para facilitar a edição, criar uma interface em:
**Super Admin > Configurações do Sistema**

Com abas:
- 📝 **Geral** (Nome, URL, Contatos)
- 🎨 **Aparência** (Logo, Favicon, Cores)
- 🔍 **SEO** (Título, Descrição, Keywords)
- 📊 **Schema.org** (JSON-LD configurações)
- 📱 **Redes Sociais** (Links para redes)

---

✅ **Sistema totalmente parametrizado! Todas as configurações vêm do banco de dados!** 🎉
