# Módulo de Faturação - Angola (Kwanzas)

## ✅ Estrutura Implementada

### **1. Configuração**
- ✅ `config/invoicing.php` - Configurações gerais de faturação para Angola
  - Moeda: Kwanza (Kz)
  - IVA: 14%
  - Métodos de pagamento angolanos
  - Bancos de Angola
  - Regimes fiscais

### **2. Migrations Criadas**

#### **Tabela: clients** ✅
```php
- tenant_id (FK)
- type (pessoa_fisica, pessoa_juridica)
- name, nif, email, phone, mobile
- address, city, province, postal_code, country
- tax_regime, is_iva_subject
- credit_limit, payment_term_days
- is_active, timestamps, soft_deletes
```

#### **Tabela: products** ✅
```php
- tenant_id (FK)
- type (produto, servico)
- code, name, description, category
- price, cost (em Kwanzas)
- is_iva_subject, iva_rate (14%), iva_reason
- manage_stock, stock_quantity, minimum_stock
- unit (UN, HR, KG)
- is_active, timestamps, soft_deletes
```

#### **Tabela: invoice_items** ✅
```php
- invoice_id (FK), product_id (FK)
- order, code, name, description
- quantity, unit
- unit_price, discount, discount_percentage
- subtotal (sem IVA)
- is_iva_subject, iva_rate, iva_amount, iva_reason
- total (com IVA)
- timestamps
```

#### **Tabela: payments** ✅
```php
- invoice_id (FK), tenant_id (FK), user_id (FK)
- payment_method (transferencia, multicaixa, tpa, etc)
- reference_number, amount, payment_date
- bank_name, bank_account, bank_iban
- proof_file, proof_original_name (comprovativo)
- notes, status (pending, verified, rejected)
- verified_by, verified_at, rejection_reason
- timestamps, soft_deletes
```

#### **Atualização: invoices** ✅
```php
Campos adicionados:
- document_type (fatura, fatura_recibo, recibo, etc)
- series, nif_emissor, nif_cliente
- payment_method
- iva_amount, iva_rate (14%), tax_regime
- observacoes, hash (AGT)
- is_exported_agt, exported_agt_at
```

---

## 📦 Models Criados

- ✅ `app/Models/Client.php`
- ✅ `app/Models/Product.php`
- ✅ `app/Models/InvoiceItem.php`
- ✅ `app/Models/Payment.php`

---

## 🔄 Próximos Passos

### **1. Completar Models**

#### **Client Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'type', 'name', 'nif', 'email', 'phone', 'mobile',
        'address', 'city', 'province', 'postal_code', 'country',
        'tax_regime', 'is_iva_subject', 'credit_limit', 'payment_term_days',
        'website', 'notes', 'is_active'
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
    ];

    public function tenant() {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }
}
```

#### **Product Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'type', 'code', 'name', 'description', 'category',
        'price', 'cost', 'is_iva_subject', 'iva_rate', 'iva_reason',
        'manage_stock', 'stock_quantity', 'minimum_stock', 'unit', 'is_active'
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'iva_rate' => 'decimal:2',
    ];

    public function tenant() {
        return $this->belongsTo(Tenant::class);
    }

    public function invoiceItems() {
        return $this->hasMany(InvoiceItem::class);
    }
    
    public function getPriceWithIvaAttribute() {
        if ($this->is_iva_subject) {
            return $this->price * (1 + ($this->iva_rate / 100));
        }
        return $this->price;
    }
}
```

#### **InvoiceItem Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'order', 'code', 'name', 'description',
        'quantity', 'unit', 'unit_price', 'discount', 'discount_percentage',
        'subtotal', 'is_iva_subject', 'iva_rate', 'iva_amount', 'iva_reason', 'total'
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva_rate' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function invoice() {
        return $this->belongsTo(Invoice::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }
    
    // Calcular subtotal
    public function calculateSubtotal() {
        return ($this->quantity * $this->unit_price) - $this->discount;
    }
    
    // Calcular IVA
    public function calculateIva() {
        if ($this->is_iva_subject) {
            return $this->subtotal * ($this->iva_rate / 100);
        }
        return 0;
    }
    
    // Calcular total
    public function calculateTotal() {
        return $this->subtotal + $this->iva_amount;
    }
}
```

#### **Payment Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_id', 'tenant_id', 'user_id', 'payment_method', 'reference_number',
        'amount', 'payment_date', 'bank_name', 'bank_account', 'bank_iban',
        'proof_file', 'proof_original_name', 'notes', 'status',
        'verified_by', 'verified_at', 'rejection_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function invoice() {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant() {
        return $this->belongsTo(Tenant::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy() {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
```

### **2. Atualizar Invoice Model**
```php
// Adicionar no Invoice.php

protected $fillable = [
    // ... existentes
    'document_type', 'series', 'nif_emissor', 'nif_cliente',
    'payment_method', 'iva_amount', 'iva_rate', 'tax_regime',
    'observacoes', 'hash', 'is_exported_agt', 'exported_agt_at'
];

protected $casts = [
    // ... existentes
    'iva_amount' => 'decimal:2',
    'iva_rate' => 'decimal:2',
    'is_exported_agt' => 'boolean',
    'exported_agt_at' => 'datetime',
];

public function client() {
    return $this->belongsTo(Client::class);
}

public function items() {
    return $this->hasMany(InvoiceItem::class)->orderBy('order');
}

public function payments() {
    return $this->hasMany(Payment::class);
}

public function getTotalPaidAttribute() {
    return $this->payments()->where('status', 'verified')->sum('amount');
}

public function getRemainingBalanceAttribute() {
    return $this->total - $this->total_paid;
}
```

---

## 🎯 Seeder de Teste

### **Criar TestDataSeeder**
```bash
php artisan make:seeder InvoicingTestSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Tenant, User, Client, Product, Invoice, InvoiceItem, Payment};
use Illuminate\Support\Str;

class InvoicingTestSeeder extends Seeder
{
    public function run()
    {
        // 1. Criar Tenant de teste
        $tenant = Tenant::create([
            'name' => 'Empresa Teste Faturação',
            'slug' => 'empresa-teste',
            'email' => 'faturacao@teste.ao',
            'company_name' => 'Empresa Teste Lda',
            'nif' => '5000000000',
            'phone' => '+244 923 456 789',
            'is_active' => true,
        ]);

        // 2. Criar usuário vinculado ao tenant
        $user = User::create([
            'name' => 'Admin Faturação',
            'email' => 'admin@faturacao.ao',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
        ]);

        // 3. Criar clientes
        $clients = [];
        $clients[] = Client::create([
            'tenant_id' => $tenant->id,
            'type' => 'pessoa_juridica',
            'name' => 'Empresa Cliente Angola Lda',
            'nif' => '5000123456',
            'email' => 'cliente1@empresa.ao',
            'phone' => '+244 222 123 456',
            'address' => 'Rua da Independência, 123',
            'city' => 'Luanda',
            'province' => 'Luanda',
            'country' => 'Angola',
            'tax_regime' => 'geral',
            'is_iva_subject' => true,
            'credit_limit' => 500000,
            'payment_term_days' => 30,
            'is_active' => true,
        ]);

        // 4. Criar produtos/serviços
        $products = [];
        $products[] = Product::create([
            'tenant_id' => $tenant->id,
            'type' => 'servico',
            'code' => 'SERV-001',
            'name' => 'Implementação ERP',
            'description' => 'Serviço de implementação de sistema ERP',
            'category' => 'Software',
            'price' => 250000.00, // 250.000 Kz
            'cost' => 100000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'unit' => 'HR',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'tenant_id' => $tenant->id,
            'type' => 'servico',
            'code' => 'SERV-002',
            'name' => 'Suporte Técnico Mensal',
            'description' => 'Suporte técnico e manutenção mensal',
            'category' => 'Software',
            'price' => 50000.00, // 50.000 Kz
            'cost' => 20000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'unit' => 'MÊS',
            'is_active' => true,
        ]);

        // 5. Criar fatura de teste
        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'client_id' => $clients[0]->id,
            'invoice_number' => 'FT2025/001',
            'document_type' => 'fatura',
            'series' => '2025',
            'nif_emissor' => '5000000000',
            'nif_cliente' => $clients[0]->nif,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'description' => 'Fatura de serviços de implementação',
            'tax_regime' => 'geral',
            'iva_rate' => 14.00,
            'status' => 'pending',
            'payment_method' => 'transferencia_bancaria',
        ]);

        // 6. Adicionar itens à fatura
        $subtotal = 0;
        $ivaTotal = 0;

        $item1 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $products[0]->id,
            'order' => 1,
            'code' => $products[0]->code,
            'name' => $products[0]->name,
            'description' => $products[0]->description,
            'quantity' => 40, // 40 horas
            'unit' => 'HR',
            'unit_price' => 250000.00,
            'discount' => 0,
            'discount_percentage' => 0,
            'subtotal' => 40 * 250000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'iva_amount' => (40 * 250000.00) * 0.14,
            'total' => (40 * 250000.00) * 1.14,
        ]);

        $item2 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $products[1]->id,
            'order' => 2,
            'code' => $products[1]->code,
            'name' => $products[1]->name,
            'description' => $products[1]->description,
            'quantity' => 1,
            'unit' => 'MÊS',
            'unit_price' => 50000.00,
            'discount' => 0,
            'discount_percentage' => 0,
            'subtotal' => 50000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'iva_amount' => 50000.00 * 0.14,
            'total' => 50000.00 * 1.14,
        ]);

        // 7. Atualizar totais da fatura
        $subtotal = $item1->subtotal + $item2->subtotal;
        $ivaTotal = $item1->iva_amount + $item2->iva_amount;
        $total = $item1->total + $item2->total;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax' => 0,
            'iva_amount' => $ivaTotal,
            'total' => $total,
        ]);

        $this->command->info('✅ Seeder completo!');
        $this->command->info('📧 Email: admin@faturacao.ao');
        $this->command->info('🔑 Password: password');
        $this->command->info('💰 Total da fatura: ' . number_format($total, 2) . ' Kz');
    }
}
```

---

## 🚀 Executar Migrations e Seeder

```bash
# Executar migrations
php artisan migrate

# Executar seeder de teste
php artisan db:seed --class=InvoicingTestSeeder

# Ou criar tudo de novo
php artisan migrate:fresh --seed
```

---

## 📋 Próximas Implementações

1. ✅ Migrations e Models
2. ⚠️ Seeders de teste
3. ⚠️ Livewire Components
4. ⚠️ Views de faturação
5. ⚠️ Sistema de upload de comprovativos
6. ⚠️ Validação de NIF angolano
7. ⚠️ Geração de PDF de fatura
8. ⚠️ Exportação para AGT
9. ⚠️ Dashboard de faturação
10. ⚠️ Relatórios fiscais

---

## 💡 Notas Importantes

- **Moeda**: Todos os valores em Kwanzas (Kz)
- **IVA**: Taxa padrão de 14%
- **NIF**: Validação específica para Angola (10 dígitos)
- **Comprovativos**: Upload obrigatório para transferências
- **AGT**: Sistema preparado para exportação futura
- **Multi-tenant**: Todo separado por tenant

---

## 🔐 Credenciais de Teste

Após rodar o seeder:
- **Email**: admin@faturacao.ao
- **Password**: password
- **Tenant**: Empresa Teste Faturação
- **Cliente**: Empresa Cliente Angola Lda
- **Fatura**: FT2025/001 (10.050.000 Kz)
