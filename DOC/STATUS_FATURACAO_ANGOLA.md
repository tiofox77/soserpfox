# STATUS - Módulo de Faturação Angola

## ✅ IMPLEMENTADO

### **1. Configuração**
- ✅ `config/invoicing.php` - Configuração completa para Angola
  - Moeda: Kwanza (Kz)
  - IVA: 14%
  - Métodos de pagamento angolanos
  - Bancos de Angola

### **2. Migrations Criadas (com prefixo `invoicing_`)**
- ✅ `invoicing_clients` - Tabela de clientes
- ✅ `invoicing_products` - Tabela de produtos/serviços
- ✅ `invoices` - Campos Angola adicionados
- ✅ `users` - Campo tenant_id adicionado

### **3. Models Criados**
- ✅ `Client` - Com relacionamentos e casts
- ✅ `Product` - Com cálculo de preço com IVA
- ✅ `InvoiceItem` - Com métodos de cálculo
- ✅ `Payment` - Com suporte a comprovativos
- ✅ `Invoice` - Atualizado com campos Angola

### **4. Seeder de Teste**
- ✅ `InvoicingTestSeeder` - Cria dados de teste
  - Tenant: Empresa Teste Faturação Angola
  - User: admin@faturacao.ao / password
  - 2 Clientes (Pessoa Jurídica e Física)
  - 4 Produtos/Serviços

## ⚠️ PENDENTE

### **Migrations**
- ❌ `invoicing_items` - Removida temporariamente (problema de FK)
- ❌ `invoicing_payments` - Removida temporariamente

### **Seeder**
- ⚠️ Erro de constraint ao executar (verificar dados duplicados)

## 📋 PRÓXIMOS PASSOS

### **1. Corrigir Seeder**
```bash
# O seeder tem conflito de unique constraint
# Verificar se já existem dados na BD
php artisan db:seed --class=InvoicingTestSeeder
```

### **2. Testar Sistema**
```bash
# Login com credenciais:
Email: admin@faturacao.ao
Password: password
```

### **3. Criar Livewire Components**
- [ ] ClientsComponent (CRUD Clientes)
- [ ] ProductsComponent (CRUD Produtos)
- [ ] InvoicingComponent (CRUD Faturas)
- [ ] PaymentsComponent (Gestão Pagamentos)

### **4. Criar Views**
- [ ] Dashboard de Faturação
- [ ] Lista de Clientes
- [ ] Lista de Produtos
- [ ] Lista de Faturas
- [ ] Detalhes de Fatura (PDF)

### **5. Funcionalidades Especiais**
- [ ] Upload de comprovativo de pagamento
- [ ] Geração de PDF de fatura (normas angolanas)
- [ ] Cálculo automático de IVA (14%)
- [ ] Exportação para AGT
- [ ] Validação de NIF angolano

## 🏗️ ESTRUTURA DE TABELAS

### **invoicing_clients**
```
- id, tenant_id
- type (pessoa_fisica/pessoa_juridica)
- name, nif (unique), email, phone, mobile
- address, city, province, country
- tax_regime, is_iva_subject
- credit_limit, payment_term_days
- is_active, timestamps, soft_deletes
```

### **invoicing_products**
```
- id, tenant_id
- type (produto/servico)
- code (unique), name, description, category
- price, cost (Kwanzas)
- is_iva_subject, iva_rate (14%), iva_reason
- manage_stock, stock_quantity, minimum_stock
- unit, is_active, timestamps, soft_deletes
```

### **invoices** (campos Angola)
```
- client_id
- document_type, series
- nif_emissor, nif_cliente
- payment_method
- iva_amount, iva_rate (14%), tax_regime
- observacoes, hash
- is_exported_agt, exported_agt_at
```

## 🔑 CREDENCIAIS DE TESTE

```
📧 Email: admin@faturacao.ao
🔑 Password: password
🏢 Tenant: Empresa Teste Faturação Angola
📋 NIF: 5000000001
💰 Moeda: Kwanza (Kz)
📊 IVA: 14%
```

## 🚀 COMANDOS ÚTEIS

```bash
# Ver status das migrations
php artisan migrate:status

# Executar migrations pendentes
php artisan migrate

# Executar seeder
php artisan db:seed --class=InvoicingTestSeeder

# Ver tabelas criadas
php artisan db:show

# Limpar cache
php artisan cache:clear
php artisan config:clear
```

## 📝 NOTAS

- Todas as tabelas do módulo têm prefixo `invoicing_`
- Sistema preparado para normas angolanas de faturação
- IVA padrão: 14%
- Moeda: Kwanza (Kz)
- Suporte a comprovativos de pagamento
- Multi-tenant habilitado
