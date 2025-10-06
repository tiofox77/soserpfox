# 📚 Boas Práticas para Migrations no SOSERP

## 🎯 Objetivo
Este documento define as melhores práticas para criar migrations seguras que funcionem tanto em ambientes novos quanto em atualizações de produção.

---

## ⚠️ Problema Comum

Quando um sistema já está em produção e você adiciona uma nova migration, pode ocorrer:

1. **Erro de coluna duplicada** - Tentando adicionar uma coluna que já existe
2. **Erro de tabela duplicada** - Tentando criar uma tabela que já existe
3. **Erro de ENUM duplicado** - Valores duplicados no ENUM
4. **Erro de índice duplicado** - Tentando criar um índice que já existe

---

## ✅ Solução: Migrations Defensivas

### 1. **Sempre Verificar se Existe Antes de Criar**

#### ❌ ERRADO:
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('phone')->nullable();
    });
}
```

#### ✅ CORRETO:
```php
use App\Helpers\MigrationHelper;

public function up(): void
{
    MigrationHelper::ifColumnNotExists('users', 'phone', function() {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });
    });
}
```

---

### 2. **Verificar Tabela Antes de Modificar**

#### ❌ ERRADO:
```php
public function up(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->decimal('cost_price', 15, 2)->after('price');
    });
}
```

#### ✅ CORRETO:
```php
use App\Helpers\MigrationHelper;

public function up(): void
{
    MigrationHelper::ifTableExists('products', function() {
        if (!Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('cost_price', 15, 2)->after('price');
            });
        }
    });
}
```

---

### 3. **Atualizar ENUM de Forma Segura**

#### ❌ ERRADO:
```php
public function up(): void
{
    DB::statement("ALTER TABLE invoicing_series 
        MODIFY COLUMN document_type ENUM('invoice', 'proforma', 'GT', 'GT') 
        COMMENT 'Tipo de documento'");
}
```
**Problema:** Valor 'GT' duplicado!

#### ✅ CORRETO:
```php
use App\Helpers\MigrationHelper;

public function up(): void
{
    MigrationHelper::updateEnumSafely(
        table: 'invoicing_series',
        column: 'document_type',
        newValues: ['invoice', 'proforma', 'receipt', 'credit_note', 'GT', 'FT', 'NC'],
        comment: 'Tipo de documento'
    );
}
```

---

### 4. **Criar Tabelas de Forma Segura**

#### ❌ ERRADO:
```php
public function up(): void
{
    Schema::create('invoicing_settings', function (Blueprint $table) {
        $table->id();
        // ...
    });
}
```

#### ✅ CORRETO:
```php
public function up(): void
{
    if (!Schema::hasTable('invoicing_settings')) {
        Schema::create('invoicing_settings', function (Blueprint $table) {
            $table->id();
            // ...
        });
    }
}
```

---

### 5. **Usar Try-Catch para Migrations Críticas**

```php
use App\Helpers\MigrationHelper;

public function up(): void
{
    MigrationHelper::runSafely(function() {
        // Código da migration
        Schema::table('users', function (Blueprint $table) {
            $table->string('new_field')->nullable();
        });
    }, 'AddNewFieldToUsers');
}
```

---

## 🛠️ Helper de Migration

O sistema fornece um helper `MigrationHelper` com os seguintes métodos:

### **Métodos Disponíveis:**

```php
// Executar de forma segura (ignora erros)
MigrationHelper::runSafely(callable $callback, string $migrationName);

// Executar se tabela existe
MigrationHelper::ifTableExists(string $table, callable $callback);

// Executar se coluna existe
MigrationHelper::ifColumnExists(string $table, string $column, callable $callback);

// Executar se coluna NÃO existe
MigrationHelper::ifColumnNotExists(string $table, string $column, callable $callback);

// Atualizar ENUM de forma segura
MigrationHelper::updateEnumSafely(string $table, string $column, array $newValues, string $comment);

// Adicionar índice de forma segura
MigrationHelper::addIndexSafely(string $table, $columns, string $indexName);

// Remover índice de forma segura
MigrationHelper::dropIndexSafely(string $table, string $indexName);
```

---

## 📋 Checklist para Novas Migrations

Antes de criar uma migration, pergunte:

- [ ] A tabela pode já existir?
- [ ] A coluna pode já existir?
- [ ] O índice pode já existir?
- [ ] O ENUM pode ter valores duplicados?
- [ ] Esta migration vai rodar em produção com dados existentes?
- [ ] Usei o `MigrationHelper` para operações críticas?
- [ ] Testei a migration em um banco vazio?
- [ ] Testei a migration em um banco com a estrutura antiga?

---

## 🚀 Exemplo Completo de Migration Segura

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Helpers\MigrationHelper;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Verificar se tabela existe antes de modificar
        MigrationHelper::ifTableExists('products', function() {
            
            // 2. Adicionar coluna somente se não existir
            MigrationHelper::ifColumnNotExists('products', 'featured', function() {
                Schema::table('products', function (Blueprint $table) {
                    $table->boolean('featured')->default(false)->after('active');
                });
            });

            // 3. Adicionar índice de forma segura
            MigrationHelper::addIndexSafely('products', ['featured', 'active']);
        });
        
        // 4. Atualizar ENUM de forma segura
        MigrationHelper::updateEnumSafely(
            table: 'products',
            column: 'status',
            newValues: ['active', 'inactive', 'discontinued', 'out_of_stock'],
            comment: 'Status do produto'
        );
    }

    public function down(): void
    {
        MigrationHelper::ifTableExists('products', function() {
            MigrationHelper::dropIndexSafely('products', 'products_featured_active_index');
            
            MigrationHelper::ifColumnExists('products', 'featured', function() {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropColumn('featured');
                });
            });
        });
    }
};
```

---

## 🐛 Debugging de Migrations

### Ver últimas migrations executadas:
```bash
php artisan migrate:status
```

### Ver SQL que será executado (sem executar):
```bash
php artisan migrate --pretend
```

### Fazer rollback da última migration:
```bash
php artisan migrate:rollback
```

### Fazer rollback de um batch específico:
```bash
php artisan migrate:rollback --batch=3
```

### Resetar todas as migrations:
```bash
php artisan migrate:reset
```

### Resetar e rodar novamente:
```bash
php artisan migrate:fresh
```

---

## 📝 Logs de Migration

Todas as operações do `MigrationHelper` são registradas em:
```
storage/logs/laravel.log
```

Procure por:
- `Migration {name} skipped`
- `Table {table} does not exist`
- `Column {table}.{column} already exists`
- `ENUM {table}.{column} already up to date`

---

## 🆘 Resolvendo Erros Comuns

### Erro: "Column already exists"
**Solução:** Use `MigrationHelper::ifColumnNotExists()`

### Erro: "SQLSTATE[42S01]: Base table already exists"
**Solução:** Use `if (!Schema::hasTable())`

### Erro: "Duplicate entry in ENUM"
**Solução:** Use `MigrationHelper::updateEnumSafely()`

### Erro: "Duplicate key name"
**Solução:** Use `MigrationHelper::addIndexSafely()`

---

## ✅ Resumo

1. **SEMPRE** verifique se existe antes de criar
2. **SEMPRE** use try-catch para operações críticas
3. **SEMPRE** teste em banco vazio E banco com dados
4. **SEMPRE** use o `MigrationHelper` para operações complexas
5. **NUNCA** assuma que o banco está vazio
6. **NUNCA** crie valores duplicados em ENUM
7. **NUNCA** faça migrations destrutivas sem backup

---

**💡 Lembre-se:** Migrations bem feitas = Atualizações sem dor de cabeça! 🎉
