# 🔧 Empréstimo de Equipamentos para Técnicos

## ✅ Status: IMPLEMENTADO

---

## 🎯 O QUE FOI IMPLEMENTADO

Sistema de empréstimo de equipamentos agora suporta **DUAS opções**:
1. ✅ Emprestar para **Clientes** (com valor de aluguel)
2. ✅ Emprestar para **Técnicos** (sem custo)

---

## 📋 **ALTERAÇÕES REALIZADAS:**

### **1. ✅ Migration - Nova Coluna**
**Arquivo:** `database/migrations/2025_10_09_220153_add_borrowed_to_technician_to_equipment.php`

```php
Schema::table('events_equipments_manager', function (Blueprint $table) {
    $table->unsignedBigInteger('borrowed_to_technician_id')->nullable();
    $table->foreign('borrowed_to_technician_id')
          ->references('id')
          ->on('events_technicians')
          ->onDelete('set null');
});
```

**Benefícios:**
- ✅ Foreign key com técnicos
- ✅ Nullable (permite empréstimo só para cliente)
- ✅ ON DELETE SET NULL (segurança)

---

### **2. ✅ Model Equipment**
**Arquivo:** `app/Models/Equipment.php`

**Adicionado ao $fillable:**
```php
'borrowed_to_technician_id',
```

**Novo Relacionamento:**
```php
public function borrowedToTechnician(): BelongsTo
{
    return $this->belongsTo(\App\Models\Events\Technician::class, 'borrowed_to_technician_id');
}
```

---

### **3. ✅ Component EquipmentManager**
**Arquivo:** `app/Livewire/Events/Equipment/EquipmentManager.php`

**Novas Propriedades:**
```php
public $borrow_type = 'client'; // 'client' ou 'technician'
public $borrowed_to_client_id = '';
public $borrowed_to_technician_id = '';
```

**Lógica de Empréstimo Atualizada:**
```php
public function saveBorrow()
{
    // Validação condicional baseada no tipo
    if ($this->borrow_type === 'client') {
        // Valida cliente + preço de aluguel
        $this->validate([
            'borrowed_to_client_id' => 'required|exists:invoicing_clients,id',
            'rental_price_per_day' => 'nullable|numeric|min:0',
        ]);
    } else {
        // Valida apenas técnico (sem preço)
        $this->validate([
            'borrowed_to_technician_id' => 'required|exists:events_technicians,id',
        ]);
    }
    
    // Atualiza baseado no tipo
    if ($this->borrow_type === 'client') {
        $updateData['borrowed_to_client_id'] = $this->borrowed_to_client_id;
        $updateData['borrowed_to_technician_id'] = null;
        $updateData['rental_price_per_day'] = $this->rental_price_per_day;
    } else {
        $updateData['borrowed_to_technician_id'] = $this->borrowed_to_technician_id;
        $updateData['borrowed_to_client_id'] = null;
        $updateData['rental_price_per_day'] = null; // Técnicos não pagam
    }
}
```

**Render Atualizado:**
```php
public function render()
{
    $clients = Client::where('tenant_id', activeTenantId())->get();
    $technicians = \App\Models\Events\Technician::where('tenant_id', activeTenantId())->get();
    
    return view('...', compact('equipment', 'clients', 'technicians', ...));
}
```

---

## 🔄 **COMO FUNCIONA:**

### **Fluxo de Empréstimo:**

```
Usuário clica "Emprestar"
  ↓
Modal abre com 2 opções:
  ├─ Cliente (com aluguel)
  └─ Técnico (sem custo)
  ↓
Seleciona tipo
  ↓
┌─────────────┬──────────────┐
│  CLIENTE    │   TÉCNICO    │
├─────────────┼──────────────┤
│ Select      │ Select       │
│ Cliente     │ Técnico      │
│             │              │
│ Input       │ (sem input)  │
│ Valor/dia   │              │
└─────────────┴──────────────┘
  ↓
Preenche datas
  ↓
Salva
  ↓
Equipment atualizado:
  - status = 'emprestado'
  - borrowed_to_client_id OU borrowed_to_technician_id
  - borrow_date
  - return_due_date
  - rental_price_per_day (só cliente)
  ↓
Histórico criado
  ↓
✅ Equipamento emprestado!
```

---

## 📊 **ESTRUTURA DO BANCO:**

```sql
events_equipments_manager
  ├─ id
  ├─ status (ENUM)
  ├─ borrowed_to_client_id (FK → invoicing_clients) ← Empréstimo para cliente
  ├─ borrowed_to_technician_id (FK → events_technicians) ← NOVO! Empréstimo para técnico
  ├─ borrow_date
  ├─ return_due_date
  ├─ actual_return_date
  └─ rental_price_per_day (só para clientes)
```

---

## 🎨 **INTERFACE (MODAL):**

```
┌──────────────────────────────────────┐
│  📦 Emprestar Equipamento            │
├──────────────────────────────────────┤
│                                      │
│  Emprestar para:                     │
│  ⚪ Cliente    ⚫ Técnico            │
│                                      │
│  [Se Cliente]                        │
│  ┌────────────────────────┐         │
│  │ Selecione o cliente ▼  │         │
│  └────────────────────────┘         │
│  ┌────────────────────────┐         │
│  │ Valor/dia: R$ _______  │         │
│  └────────────────────────┘         │
│                                      │
│  [Se Técnico]                        │
│  ┌────────────────────────┐         │
│  │ Selecione o técnico ▼  │         │
│  └────────────────────────┘         │
│  (Sem custo)                         │
│                                      │
│  Data Empréstimo: [____/____/____]  │
│  Data Devolução:  [____/____/____]  │
│                                      │
│  Observações:                        │
│  ┌────────────────────────┐         │
│  │                        │         │
│  └────────────────────────┘         │
│                                      │
│  [Cancelar]  [💾 Emprestar]         │
└──────────────────────────────────────┘
```

---

## 🧪 **COMO TESTAR:**

### **Teste 1: Emprestar para Cliente**
```
1. Acesse Equipamentos
2. Clique "Emprestar" em um equipamento disponível
3. Selecione "Cliente"
4. Escolha um cliente
5. Digite valor/dia (ex: 50.00)
6. Preencha datas
7. Salve
8. ✅ Status = "emprestado"
9. ✅ borrowed_to_client_id preenchido
10. ✅ rental_price_per_day = 50.00
```

### **Teste 2: Emprestar para Técnico**
```
1. Acesse Equipamentos
2. Clique "Emprestar" em um equipamento disponível
3. Selecione "Técnico"
4. Escolha um técnico
5. Preencha datas (sem valor)
6. Salve
7. ✅ Status = "emprestado"
8. ✅ borrowed_to_technician_id preenchido
9. ✅ rental_price_per_day = NULL
10. ✅ borrowed_to_client_id = NULL
```

### **Teste 3: Devolver Equipamento**
```
1. Equipamento emprestado (cliente ou técnico)
2. Clique "Devolver"
3. ✅ Status = "disponivel"
4. ✅ actual_return_date = data atual
5. ✅ IDs mantidos para histórico
```

---

## 📝 **VALIDAÇÕES:**

| Campo | Tipo Cliente | Tipo Técnico |
|-------|-------------|--------------|
| **borrowed_to_client_id** | ✅ Obrigatório | ❌ NULL |
| **borrowed_to_technician_id** | ❌ NULL | ✅ Obrigatório |
| **borrow_date** | ✅ Obrigatório | ✅ Obrigatório |
| **return_due_date** | ✅ Obrigatório | ✅ Obrigatório |
| **rental_price_per_day** | ⚠️ Opcional | ❌ NULL |

---

## 🎯 **REGRAS DE NEGÓCIO:**

| Regra | Descrição |
|-------|-----------|
| **Exclusividade** | Equipamento só pode ser emprestado para 1 pessoa (cliente OU técnico) |
| **Custo** | Clientes pagam aluguel, técnicos não |
| **Histórico** | Todas as transações são registradas |
| **Devolução** | Pode devolver a qualquer momento |
| **Status** | Automaticamente muda para "emprestado" |

---

## 📈 **BENEFÍCIOS:**

| Benefício | Descrição |
|-----------|-----------|
| ✅ **Flexibilidade** | 2 tipos de empréstimo no mesmo sistema |
| ✅ **Rastreamento** | Sabe quem está com cada equipamento |
| ✅ **Controle Financeiro** | Só clientes pagam aluguel |
| ✅ **Histórico** | Registra todas as movimentações |
| ✅ **Relatórios** | Pode gerar relatórios por tipo |

---

## 🔍 **QUERIES ÚTEIS:**

### **Equipamentos emprestados para clientes:**
```php
Equipment::where('status', 'emprestado')
    ->whereNotNull('borrowed_to_client_id')
    ->with('borrowedToClient')
    ->get();
```

### **Equipamentos emprestados para técnicos:**
```php
Equipment::where('status', 'emprestado')
    ->whereNotNull('borrowed_to_technician_id')
    ->with('borrowedToTechnician')
    ->get();
```

### **Todos emprestados:**
```php
Equipment::where('status', 'emprestado')
    ->with(['borrowedToClient', 'borrowedToTechnician'])
    ->get();
```

### **Receita de aluguel (só clientes):**
```php
Equipment::where('status', 'emprestado')
    ->whereNotNull('rental_price_per_day')
    ->sum('rental_price_per_day');
```

---

## 🎉 **RESULTADO FINAL:**

**✅ Sistema completo de empréstimo de equipamentos!**

| Recurso | Status |
|---------|--------|
| ✅ Emprestar para Cliente | Implementado |
| ✅ Emprestar para Técnico | **NOVO** |
| ✅ Validação por tipo | Implementado |
| ✅ Preço só para cliente | Implementado |
| ✅ Histórico | Implementado |
| ✅ Devolução | Implementado |
| ✅ Relacionamentos | Implementados |
| ✅ Migration | Executada |

**Pronto para uso em produção!** 🚀✨

---

**Data:** 2025-10-09  
**Versão:** 1.0  
**Desenvolvido por:** Cascade AI
