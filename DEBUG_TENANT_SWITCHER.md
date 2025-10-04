# 🔍 DEBUG: TENANT SWITCHER - ANÁLISE DE LOGS

## 📊 COMO TESTAR E ANALISAR

### **1. Limpar Logs Anteriores**
```bash
# Windows (PowerShell)
cd c:\laragon2\www\soserp
Remove-Item storage\logs\laravel.log -Force -ErrorAction SilentlyContinue
New-Item storage\logs\laravel.log -ItemType File
```

### **2. Testar a Troca de Empresa**
1. Acesse: `http://soserp.test/home`
2. Clique no botão de TenantSwitcher (empresa ativa)
3. Clique em outra empresa para trocar
4. Observe se aparece erro 405

### **3. Ver os Logs em Tempo Real**
```bash
# Windows (PowerShell) - Abra um novo terminal
cd c:\laragon2\www\soserp
Get-Content storage\logs\laravel.log -Wait -Tail 50
```

---

## 📋 O QUE PROCURAR NOS LOGS

### **Logs Esperados (SUCESSO):**

```
[2025-10-03 09:00:00] local.INFO: TenantSwitcher: Iniciando troca de tenant
{"from":1,"to":2,"user_id":1}

[2025-10-03 09:00:00] local.INFO: TenantSwitcher: Troca bem-sucedida
{"new_tenant":2,"session":2}

[2025-10-03 09:00:00] local.INFO: TenantSwitcher: Despachando evento para reload

[2025-10-03 09:00:00] local.INFO: IdentifyTenant Middleware
{"url":"http://soserp.test/home","path":"home","method":"GET","is_livewire":false}

[2025-10-03 09:00:01] local.INFO: IdentifyTenant Middleware
{"url":"http://soserp.test/livewire/update","path":"livewire/update","method":"POST","is_livewire":true}

[2025-10-03 09:00:01] local.INFO: IdentifyTenant: Ignorando rota Livewire
```

### **Logs com Problema (ERRO 405):**

```
[2025-10-03 09:00:00] local.INFO: TenantSwitcher: Iniciando troca de tenant
{"from":1,"to":2,"user_id":1}

[2025-10-03 09:00:00] local.INFO: TenantSwitcher: Troca bem-sucedida
{"new_tenant":2,"session":2}

[2025-10-03 09:00:00] local.INFO: IdentifyTenant Middleware
{"url":"http://soserp.test/livewire/update","path":"livewire/update","method":"GET","is_livewire":true}
                                                                      ^^^^ PROBLEMA AQUI!

[2025-10-03 09:00:00] local.ERROR: Method Not Allowed
```

---

## 🔧 SOLUÇÕES APLICADAS

### **Solução 1: Middleware com Logs** ✅
```php
// app/Http/Middleware/IdentifyTenant.php
- Adicionado logs detalhados
- Ignora rotas 'livewire/*'
- Ignora 'livewire-update'
```

### **Solução 2: JavaScript Reload** ✅
```php
// app/Livewire/TenantSwitcher.php
- Removido $this->redirect()
- Despachando evento 'tenant-switched-reload'
- JavaScript faz window.location.reload() com delay
```

### **Solução 3: Alpine.js Listener** ✅
```blade
// resources/views/livewire/tenant-switcher.blade.php
@tenant-switched-reload.window="setTimeout(() => window.location.reload(), 500)"
```

---

## 🎯 ANÁLISE DE POSSÍVEIS CAUSAS

| Causa Provável | Sintoma | Como Identificar no Log |
|----------------|---------|-------------------------|
| **Middleware interceptando Livewire** | Erro 405 | Ver `method":"GET"` em `/livewire/update` |
| **Redirect do Livewire incorreto** | Erro 405 após troca | Ver "Tentando redirect" seguido de erro |
| **Cache de rota** | Comportamento estranho | Limpar cache com `php artisan route:clear` |
| **Sessão não sendo salva** | Troca mas não persiste | Session ID diferente nos logs |

---

## 🚀 COMANDOS ÚTEIS

### **Limpar Todos os Caches:**
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan optimize:clear
```

### **Ver Logs Filtrados:**
```bash
# Apenas logs do TenantSwitcher
Get-Content storage\logs\laravel.log | Select-String "TenantSwitcher"

# Apenas logs do Middleware
Get-Content storage\logs\laravel.log | Select-String "IdentifyTenant"

# Apenas erros
Get-Content storage\logs\laravel.log | Select-String "ERROR"
```

### **Ver Sessão Atual:**
```bash
php artisan tinker
>>> auth()->user()->activeTenantId()
>>> session('active_tenant_id')
>>> auth()->user()->tenants()->pluck('id', 'name')
```

---

## 📊 RESULTADO ESPERADO

### **✅ SUCESSO (Sem Erro 405):**
1. Clicar para trocar empresa
2. Ver toast de sucesso verde
3. Página recarrega suavemente (500ms delay)
4. Nova empresa ativa aparece no botão
5. Dados da nova empresa carregam
6. **SEM erro 405 no console ou logs**

### **❌ PROBLEMA (Com Erro 405):**
1. Clicar para trocar empresa
2. Erro 405 aparece
3. Mas empresa troca mesmo assim
4. Logs mostram `method":"GET"` em rota POST

---

## 🐛 SE O ERRO PERSISTIR

### **Verificar:**
1. ✅ Cache limpo?
2. ✅ Middleware tem a exceção `livewire/*`?
3. ✅ TenantSwitcher despacha evento?
4. ✅ Alpine.js está escutando evento?
5. ✅ JavaScript reload acontece?

### **Teste Alternativo (Sem Livewire):**
Adicionar botão HTML puro para testar:
```blade
<form method="POST" action="{{ route('tenant.switch', $tenant->id) }}">
    @csrf
    <button type="submit">Trocar para {{ $tenant->name }}</button>
</form>
```

---

**Data:** 03 de Outubro de 2025  
**Versão:** 3.6.1 (Debug)  
**Status:** 🔍 Em Análise com Logs Detalhados
