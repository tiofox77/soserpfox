# Correção de Módulos - 03/10/2025

## Problema Identificado

Os planos estavam configurados com slug **`"faturacao"`** mas o módulo real na tabela `modules` tem slug **`"invoicing"`**.

### Módulos Existentes no Sistema:

| ID | Nome | Slug |
|----|------|------|
| 1 | Faturação | **invoicing** |
| 2 | Recursos Humanos | rh |
| 3 | Contabilidade | contabilidade |
| 4 | Gestão de Oficina | oficina |
| 5 | CRM | crm |
| 6 | Inventário | inventario |
| 7 | Compras | compras |
| 8 | Projetos | projetos |

## Correções Aplicadas

### 1. Planos Atualizados:

**Plano Starter (ID: 1)**
- Antes: `["faturacao"]`
- Depois: `["invoicing"]` ✅

**Plano Professional (ID: 2)**
- Antes: `["faturacao", "rh", "contabilidade"]`
- Depois: `["invoicing", "rh", "contabilidade"]` ✅

**Plano Business (ID: 3)**
- Antes: `["faturacao", "rh", "contabilidade", "oficina", "crm", "inventario", "compras", "projetos"]`
- Depois: `["invoicing", "rh", "contabilidade", "oficina", "crm", "inventario", "compras", "projetos"]` ✅

**Plano Enterprise (ID: 4)**
- Antes: `["faturacao", "rh", "contabilidade", "oficina", "crm", "inventario", "compras", "projetos"]`
- Depois: `["invoicing", "rh", "contabilidade", "oficina", "crm", "inventario", "compras", "projetos"]` ✅

### 2. Módulo Ativado Manualmente

Para o tenant ID 25 (infranet) do usuário carlosfox1782@gmail.com:
- Módulo "Faturação" (invoicing) ativado manualmente
- Status: ✅ ATIVO

## Lógica de Ativação de Módulos

### No Registro (RegisterWizard.php):
```php
// Ativa módulos do plano automaticamente
foreach ($plan->included_modules as $moduleSlug) {
    $module = Module::where('slug', $moduleSlug)->first();
    if ($module) {
        $tenant->modules()->attach($module->id, [
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }
}
```

### Na Aprovação (Billing.php):
```php
// Ao aprovar pedido, ativa módulos novamente (caso não tenham sido ativados)
foreach ($plan->included_modules as $moduleSlug) {
    $module = Module::where('slug', $moduleSlug)->first();
    if ($module) {
        if (!$tenant->modules()->where('module_id', $module->id)->exists()) {
            $tenant->modules()->attach($module->id, [
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }
    }
}
```

## Resultado

✅ Todos os planos agora usam slugs corretos
✅ Módulos são ativados automaticamente no registro
✅ Módulos são ativados na aprovação (se ainda não estiverem)
✅ Logs detalhados de cada módulo ativado
