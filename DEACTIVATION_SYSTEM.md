# Sistema de Desativação de Tenant - Completo

## ✅ Implementado

### 1. **Migration - Campos de Desativação**
```php
Schema::table('tenants', function (Blueprint $table) {
    $table->text('deactivation_reason')->nullable();
    $table->timestamp('deactivated_at')->nullable();
    $table->unsignedBigInteger('deactivated_by')->nullable();
    $table->foreign('deactivated_by')->references('id')->on('users');
});
```

### 2. **Model Tenant - Campos Atualizados**
```php
protected $fillable = [
    ...
    'deactivation_reason',
    'deactivated_at',
    'deactivated_by',
];

protected $casts = [
    ...
    'deactivated_at' => 'datetime',
];
```

### 3. **Backend - Livewire SuperAdmin/Tenants.php**

#### Propriedades Adicionadas:
```php
public $showDeactivationModal = false;
public $deactivatingTenantId = null;
public $deactivatingTenantName = '';
public $deactivationReason = '';
```

#### Métodos Implementados:

**toggleStatus($id)** - Intercepta ação:
- Se ativo → Abre modal para motivo
- Se inativo → Reativa direto

**confirmDeactivation()** - Desativa com motivo:
```php
- Valida motivo (mínimo 10 caracteres)
- Salva: deactivation_reason, deactivated_at, deactivated_by
- Atualiza is_active = false
- Mostra alerta com quantidade de usuários afetados
```

**activateTenant($id)** - Reativa tenant:
```php
- is_active = true
- Limpa: deactivation_reason, deactivated_at, deactivated_by
```

### 4. **Frontend - Modal de Desativação**
**Arquivo:** `resources/views/livewire/super-admin/tenants/partials/deactivation-modal.blade.php`

**Características:**
- ⚠️ Alert visual com consequências
- 📝 Campo de texto para motivo (textarea)
- ✅ Validação: mínimo 10 caracteres
- 🎨 Design vermelho/laranja para urgência
- 💬 Mensagem ao usuário: "Este motivo será exibido aos usuários"

### 5. **Middleware - CheckTenantActive**
**Arquivo:** `app/Http/Middleware/CheckTenantActive.php`

**Fluxo:**
```
1. Verifica se usuário é Super Admin → Passa direto
2. Busca tenant do usuário
3. Se tenant inativo:
   - Salva dados na sessão:
     * tenant_deactivated = true
     * tenant_name
     * deactivation_reason
     * deactivated_at
   - Faz logout do usuário
   - Redireciona para: route('tenant.deactivated')
```

**Registrado em:** `bootstrap/app.php`
```php
$middleware->append(\App\Http\Middleware\CheckTenantActive::class);
```

### 6. **Página de Bloqueio - Tenant Desativado**
**Arquivo:** `resources/views/auth/tenant-deactivated.blade.php`

**Conteúdo:**
- 🚫 Header vermelho/laranja com ícone de bloqueio
- 🏢 Nome do tenant desativado
- 📅 Data/hora da desativação
- 💬 Motivo da desativação (informado pelo Super Admin)
- ℹ️ O que aconteceu (lista de consequências)
- 💡 O que fazer agora (orientações)
- 📧 Botão para contatar suporte
- 🔙 Botão para voltar ao login

**Rota:** `routes/web.php`
```php
Route::get('/tenant-deactivated', function () {
    return view('auth.tenant-deactivated');
})->name('tenant.deactivated');
```

### 7. **Validação no Model Tenant**
```php
public function hasModule($moduleSlug)
{
    // Se tenant inativo → Sem acesso a módulos
    if (!$this->is_active) {
        return false;
    }
    ...
}

public function canAccess()
{
    return $this->is_active && $this->hasActiveSubscription();
}
```

---

## 🎯 Fluxo Completo

### Quando Super Admin DESATIVA:

```
1. Super Admin clica "Desativar" no tenant
   ↓
2. Modal abre solicitando MOTIVO
   ↓
3. Super Admin digita motivo (mín. 10 caracteres)
   ↓
4. Clica "Desativar Tenant"
   ↓
5. Sistema salva:
   - is_active = false
   - deactivation_reason = "motivo informado"
   - deactivated_at = now()
   - deactivated_by = auth()->id()
   ↓
6. Alerta exibido: "⚠️ X usuários perderam acesso"
```

### Quando Usuário Tenta Acessar:

```
1. Usuário faz login / navega no sistema
   ↓
2. Middleware CheckTenantActive intercepta
   ↓
3. Verifica: tenant is_active?
   ↓
4. Se INATIVO:
   - Salva dados na sessão
   - Faz logout
   - Redireciona → /tenant-deactivated
   ↓
5. Página exibe:
   - Nome do tenant
   - Motivo da desativação
   - Data/hora
   - Orientações
```

### Quando Super Admin REATIVA:

```
1. Super Admin clica "Ativar" no tenant
   ↓
2. Sistema atualiza (sem modal):
   - is_active = true
   - deactivation_reason = null
   - deactivated_at = null
   - deactivated_by = null
   ↓
3. Sucesso: "✓ Tenant reativado!"
   ↓
4. Usuários podem acessar normalmente
```

---

## 🧪 Como Testar

### 1. Desativar Tenant:
```
1. Acesse: http://soserp.test/superadmin/tenants
2. Clique no botão "Desativar" de um tenant ativo
3. Digite motivo: "Teste de desativação do sistema"
4. Confirme
5. Verifique alerta de sucesso
```

### 2. Tentar Acessar como Usuário:
```
1. Faça logout do Super Admin
2. Faça login com usuário do tenant desativado
3. Deve ser redirecionado para: /tenant-deactivated
4. Verifique se motivo aparece
```

### 3. Reativar Tenant:
```
1. Volte como Super Admin
2. Clique em "Ativar" no tenant
3. Usuários podem acessar novamente
```

### 4. Verificar Módulos:
```
// Console/Tinker
$tenant = Tenant::find(1);
$tenant->is_active = false;
$tenant->hasModule('invoicing'); // Deve retornar FALSE
```

---

## 📦 Arquivos Criados/Modificados

### Criados:
- ✅ `database/migrations/*_add_deactivation_info_to_tenants_table.php`
- ✅ `app/Http/Middleware/CheckTenantActive.php`
- ✅ `resources/views/livewire/super-admin/tenants/partials/deactivation-modal.blade.php`
- ✅ `resources/views/auth/tenant-deactivated.blade.php`

### Modificados:
- ✅ `app/Models/Tenant.php` (fillable, casts, hasModule)
- ✅ `app/Livewire/SuperAdmin/Tenants.php` (métodos de desativação)
- ✅ `resources/views/livewire/super-admin/tenants/tenants.blade.php` (include modal)
- ✅ `bootstrap/app.php` (registro do middleware)
- ✅ `routes/web.php` (rota tenant.deactivated)

---

## 🔐 Segurança

- ✅ Super Admin nunca é bloqueado
- ✅ Motivo obrigatório (mínimo 10 caracteres)
- ✅ Registro de quem desativou (deactivated_by)
- ✅ Registro de quando (deactivated_at)
- ✅ Logout automático de usuários afetados
- ✅ Bloqueio de todos os módulos
- ✅ Mensagem clara ao usuário final

---

## 📝 Banco de Dados

### Verificar Dados:
```sql
SELECT id, name, is_active, deactivation_reason, deactivated_at, deactivated_by 
FROM tenants;
```

### Desativar Manualmente (Teste):
```sql
UPDATE tenants 
SET is_active = 0, 
    deactivation_reason = 'Teste manual',
    deactivated_at = NOW(),
    deactivated_by = 1
WHERE id = 1;
```

---

## ✨ Sistema 100% Funcional!

Tudo pronto para usar! 🎉
