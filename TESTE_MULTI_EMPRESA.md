# 🧪 GUIA DE TESTE - SISTEMA MULTI-EMPRESA

## ✅ 1. VERIFICAR PLANOS

```bash
php artisan tinker
```

```php
// Importar o modelo
use App\Models\Plan;

// Ver todos os planos com max_companies
Plan::all(['name', 'max_companies']);

// Ver planos em formato tabela
Plan::all()->map(function($p) { 
    return [
        'Nome' => $p->name, 
        'Max Empresas' => $p->max_companies,
        'Max Usuários' => $p->max_users,
        'Preço Mensal' => $p->price_monthly
    ]; 
});

// Saída esperada:
// [
//   ["Nome" => "Starter", "Max Empresas" => 1, ...],
//   ["Nome" => "Professional", "Max Empresas" => 3, ...],
//   ["Nome" => "Business", "Max Empresas" => 10, ...],
//   ["Nome" => "Enterprise", "Max Empresas" => 999, ...]
// ]
```

---

## ✅ 2. VERIFICAR USUÁRIO E LIMITES

```php
use App\Models\User;

// Pegar um usuário teste
$user = User::where('email', 'teste@multitenant.com')->first();

// Ver quantas empresas o usuário tem
$user->tenants()->count();

// Ver limite de empresas do usuário
$user->getMaxCompaniesLimit();

// Verificar se pode adicionar mais empresas
$user->canAddMoreCompanies();  // true ou false

// Ver detalhes
echo "Empresas atuais: " . $user->tenants()->count() . "\n";
echo "Limite do plano: " . $user->getMaxCompaniesLimit() . "\n";
echo "Pode adicionar? " . ($user->canAddMoreCompanies() ? 'SIM' : 'NÃO') . "\n";
```

---

## ✅ 3. VERIFICAR TENANT E SUBSCRIPTION

```php
use App\Models\Tenant;

// Pegar um tenant
$tenant = Tenant::first();

// Ver subscription ativa
$subscription = $tenant->activeSubscription;

// Ver plano da subscription
if ($subscription) {
    $plan = $subscription->plan;
    echo "Plano: " . $plan->name . "\n";
    echo "Max Empresas: " . $plan->max_companies . "\n";
}
```

---

## ✅ 4. TESTAR HELPER FUNCTIONS

```php
// Verificar tenant ativo
activeTenantId();

// Verificar se pode trocar de tenant
canSwitchTenants();  // true se tiver 2+ empresas

// Ver tenant ativo
activeTenant();
```

---

## ✅ 5. TESTE COMPLETO - CENÁRIO REAL

```php
use App\Models\User;
use App\Models\Tenant;

// 1. Pegar usuário
$user = User::where('email', 'teste@multitenant.com')->first();

// 2. Ver empresas atuais
echo "=== EMPRESAS DO USUÁRIO ===\n";
foreach ($user->tenants as $tenant) {
    echo "- {$tenant->name} (ID: {$tenant->id})\n";
}

// 3. Ver limite
echo "\n=== LIMITE DO PLANO ===\n";
$activeTenant = $user->activeTenant();
$subscription = $activeTenant->activeSubscription;
$plan = $subscription ? $subscription->plan : null;

if ($plan) {
    echo "Plano atual: {$plan->name}\n";
    echo "Max empresas: {$plan->max_companies}\n";
    echo "Empresas atuais: " . $user->tenants()->count() . "\n";
    echo "Pode adicionar? " . ($user->canAddMoreCompanies() ? 'SIM' : 'NÃO') . "\n";
}

// 4. Simular adição de empresa (sem executar)
if ($user->canAddMoreCompanies()) {
    echo "\n✅ Este usuário pode ser adicionado a mais empresas!\n";
} else {
    $max = $user->getMaxCompaniesLimit();
    echo "\n❌ Limite atingido! Este usuário já tem {$max} empresa(s).\n";
    echo "   Faça upgrade do plano para adicionar mais.\n";
}
```

---

## ✅ 6. TESTE SUPER ADMIN

```php
use App\Models\User;

// Super Admin sempre pode adicionar empresas
$admin = User::where('is_super_admin', true)->first();

echo "Super Admin: {$admin->name}\n";
echo "Limite: " . $admin->getMaxCompaniesLimit() . "\n";  // PHP_INT_MAX
echo "Pode adicionar? " . ($admin->canAddMoreCompanies() ? 'SIM' : 'NÃO') . "\n";  // sempre SIM
```

---

## ✅ 7. ATUALIZAR PLANO (UPGRADE)

```php
use App\Models\Subscription;
use App\Models\Plan;

// 1. Pegar subscription de um tenant
$subscription = Subscription::where('status', 'active')->first();

// 2. Ver plano atual
echo "Plano atual: " . $subscription->plan->name . "\n";
echo "Max empresas: " . $subscription->plan->max_companies . "\n";

// 3. Fazer upgrade para Professional (3 empresas)
$newPlan = Plan::where('slug', 'professional')->first();
$subscription->plan_id = $newPlan->id;
$subscription->save();

echo "\n✅ Upgrade realizado!\n";
echo "Novo plano: " . $subscription->plan->name . "\n";
echo "Novo limite: " . $subscription->plan->max_companies . " empresas\n";
```

---

## 🎯 RESULTADOS ESPERADOS

### **Plano Starter (1 empresa):**
```
Empresas atuais: 1
Limite do plano: 1
Pode adicionar? NÃO
```

### **Plano Professional (3 empresas):**
```
Empresas atuais: 2
Limite do plano: 3
Pode adicionar? SIM
```

### **Plano Business (10 empresas):**
```
Empresas atuais: 5
Limite do plano: 10
Pode adicionar? SIM
```

### **Super Admin:**
```
Empresas atuais: 5
Limite do plano: 9223372036854775807  (PHP_INT_MAX)
Pode adicionar? SIM
```

---

## 🐛 COMANDOS DE DEBUG

```php
// Ver estrutura da tabela plans
\DB::select("PRAGMA table_info(plans)");

// Contar planos
Plan::count();

// Ver todas as subscriptions
use App\Models\Subscription;
Subscription::with('plan', 'tenant')->get();

// Ver todos os usuários e suas empresas
User::with('tenants')->get()->map(function($u) {
    return [
        'user' => $u->email,
        'empresas' => $u->tenants->pluck('name'),
        'total' => $u->tenants->count()
    ];
});
```

---

## ✅ COMANDOS CORRETOS PARA COPIAR NO TINKER

**NÃO copie as setas `=>` ou arrays diretamente!**

**CORRETO:**
```php
Plan::all()->pluck('max_companies', 'name')
```

**ERRADO:**
```php
=> [  // ❌ Não cole isso!
     1 => "Starter",
   ]
```

---

**Data:** 03 de Outubro de 2025  
**Versão:** 3.7.1  
**Status:** ✅ Testado e Funcionando
