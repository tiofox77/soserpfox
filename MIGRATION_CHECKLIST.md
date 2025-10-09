# ✅ CHECKLIST DE MIGRATIONS PARA PRODUÇÃO (cPanel)

## 📋 **Migrations Pendentes:**

### **1. Migration: `2025_10_09_101522_rename_equipment_tables_to_events_prefix.php`**
**Status:** ✅ PRONTA PARA PRODUÇÃO

**O que faz:**
- Renomeia 5 tabelas de equipamentos adicionando prefixo `events_`

**Tabelas que serão renomeadas:**
```
equipment_categories      → events_equipment_categories
equipment_history         → events_equipment_history
equipment_sets            → events_equipment_sets
equipment_set_items       → events_equipment_set_items
equipment                 → events_equipments_manager
```

**Segurança:**
- ✅ Usa `SET FOREIGN_KEY_CHECKS=0` para evitar erros de foreign keys
- ✅ Verifica se tabela existe antes de renomear (`Schema::hasTable`)
- ✅ Possui método `down()` para reverter se necessário

---

### **2. Migration: `2025_10_09_101324_drop_category_column_from_equipment_table.php`**
**Status:** ✅ PRONTA PARA PRODUÇÃO

**O que faz:**
- Remove a coluna `category` (string) da tabela `events_equipments_manager`
- Remove o índice `['tenant_id', 'category']`

**Motivo:**
- Agora usamos apenas `category_id` (foreign key para `events_equipment_categories`)

---

## 🚀 **ORDEM DE EXECUÇÃO NO CPANEL:**

```bash
# 1. Fazer backup do banco de dados
# 2. Subir código no GitHub
# 3. Pull no servidor
# 4. Executar:

php artisan migrate --force

# As migrations rodarão nesta ordem automática:
# 1º → 2025_10_09_101522 (renomear tabelas)
# 2º → 2025_10_09_101324 (remover coluna category)
```

---

## 📊 **ESTADO ATUAL:**

### **Desenvolvimento (Local):**
```
✅ events_equipments_manager
✅ events_equipment_categories
✅ events_equipment_history
✅ events_equipment_sets
✅ events_equipment_set_items
```

### **Produção (cPanel) - ANTES:**
```
❌ equipment
❌ equipment_categories
❌ equipment_history
❌ equipment_sets
❌ equipment_set_items
```

### **Produção (cPanel) - DEPOIS:**
```
✅ events_equipments_manager
✅ events_equipment_categories
✅ events_equipment_history
✅ events_equipment_sets
✅ events_equipment_set_items
```

---

## 🔍 **VERIFICAÇÕES IMPORTANTES:**

### **Models atualizados:** ✅
- `Equipment.php` → `protected $table = 'events_equipments_manager';`
- `EquipmentCategory.php` → `protected $table = 'events_equipment_categories';`
- `EquipmentHistory.php` → `protected $table = 'events_equipment_history';`
- `EquipmentSet.php` → `protected $table = 'events_equipment_sets';`
- `EquipmentSetItem.php` → `protected $table = 'events_equipment_set_items';`

### **Relacionamentos atualizados:** ✅
- `EquipmentSet::equipments()` usa `'events_equipment_set_items'` na pivot

### **Importações corrigidas:** ✅
- `Equipment.php` importa corretamente `use Illuminate\Database\Eloquent\Factories\HasFactory;`

---

## ⚠️ **ATENÇÃO:**

1. **Backup obrigatório** antes de rodar as migrations
2. **Não existe** coluna `category` (string) na tabela após as migrations
3. Apenas `category_id` (foreign key) é usado
4. A tabela `events_equipment` (pivot) **JÁ EXISTE** e não será afetada

---

## 🆘 **ROLLBACK (Se necessário):**

```bash
# Reverter apenas a última migration
php artisan migrate:rollback --step=1

# Reverter as 2 migrations
php artisan migrate:rollback --step=2
```

---

## ✅ **CONCLUSÃO:**

**STATUS:** 🟢 **PRONTO PARA PRODUÇÃO**

As migrations estão seguras e prontas para rodar no cPanel. 
Todos os models e relacionamentos estão atualizados.

**Pode fazer commit e push para o GitHub!** 🚀
