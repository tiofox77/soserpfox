# 🔧 TROUBLESHOOTING - NOTIFICAÇÕES DE EVENTOS

## ❌ **PROBLEMA: Notificações não enviadas ao criar evento**

---

## ✅ **CORREÇÕES APLICADAS:**

### **1. Caminho do Model corrigido** ✅
- **Antes:** `\App\Models\Event::class` ❌
- **Depois:** `\App\Models\Events\Event::class` ✅

### **2. Nome da tabela corrigido** ✅
- **Antes:** `events` ❌
- **Depois:** `events_events` ✅

### **3. Tabela de técnicos corrigida** ✅
- **Antes:** `event_technicians` ❌
- **Depois:** `events_event_staff` ✅

---

## 🎯 **COMO O SISTEMA FUNCIONA:**

### **Fluxo de Notificação Automática:**

```
1. Usuário cria evento em /events/calendar
   ↓
2. Adiciona técnicos ao evento
   ↓
3. EventObserver detecta criação
   ↓
4. Busca técnicos vinculados (events_event_staff)
   ↓
5. Verifica se notificação está ativa
   ↓
6. Normaliza telefones dos técnicos
   ↓
7. Envia WhatsApp para cada técnico ✅
```

---

## ⚠️ **REQUISITOS PARA FUNCIONAR:**

### **1. Evento deve ter técnicos vinculados** ⭐
A notificação **SÓ é enviada** se houver técnicos vinculados ao evento!

**Como adicionar técnicos ao evento:**
- Ao criar evento, adicione na seção "Equipe/Staff"
- Ou edite evento existente e adicione técnicos

**Verificar se evento tem técnicos:**
```sql
SELECT * FROM events_event_staff WHERE event_id = 8;
```

Se retornar vazio = **Nenhum técnico vinculado** ❌

### **2. Notificação deve estar ativa**
Acesse: `/notifications/settings` → WhatsApp → **☑️ Evento Criado**

### **3. Técnicos devem ter telefone cadastrado**
Os técnicos devem ter o campo `phone` preenchido na tabela `users`:
```sql
SELECT id, name, phone FROM users WHERE id IN (
    SELECT user_id FROM events_event_staff WHERE event_id = 8
);
```

### **4. WhatsApp deve estar configurado**
- Account SID
- Auth Token  
- From Number

---

## 🧪 **TESTE RÁPIDO:**

Execute o script de teste:
```bash
php test-event-notification.php
```

**Resultado esperado:**
```
🧪 TESTE DE NOTIFICAÇÃO DE EVENTO
====================================

📅 Evento encontrado: Meu Evento
   ID: 8
   Criado em: 2025-10-14 14:12:26

👥 Técnicos vinculados: 2

   - João Silva
     Email: joao@email.com
     Phone: 939729902

   - Maria Santos
     Email: maria@email.com
     Phone: 923456789

⚙️ Configurações:
   WhatsApp Ativado: SIM ✅
   Evento Criado Ativo: SIM ✅
   Account SID: AC58d8740b...
   From Number: +15558740135

📤 Enviando notificação de teste...

✅ Notificação enviada com sucesso!
📱 Verifique o WhatsApp dos técnicos.
```

---

## 📋 **CHECKLIST DE VERIFICAÇÃO:**

### **Antes de criar evento:**
- [ ] WhatsApp configurado em `/notifications/settings`
- [ ] Notificação "Evento Criado" ativada (WhatsApp)
- [ ] Técnicos cadastrados com telefone

### **Ao criar evento:**
- [ ] **Adicionar técnicos ao evento** (campo Staff/Equipe)
- [ ] Salvar evento

### **Depois de criar:**
- [ ] Verificar logs: `storage/logs/laravel.log`
- [ ] Verificar se técnicos receberam WhatsApp
- [ ] Se não receberam, executar teste: `php test-event-notification.php`

---

## 🔍 **DIAGNÓSTICO DE PROBLEMAS:**

### **Problema 1: "Nenhum técnico vinculado"**

**Sintoma:**
```
👥 Técnicos vinculados: 0
⚠️ PROBLEMA: Nenhum técnico vinculado ao evento!
```

**Solução:**
1. Edite o evento
2. Adicione técnicos na seção "Equipe" ou "Staff"
3. Salve
4. Teste novamente

**SQL para adicionar técnico manualmente:**
```sql
INSERT INTO events_event_staff (event_id, user_id, role, created_at, updated_at)
VALUES (8, 1, 'Técnico', NOW(), NOW());
```

### **Problema 2: "WhatsApp não está ativado"**

**Sintoma:**
```
⚙️ Configurações:
   WhatsApp Ativado: NÃO ❌
```

**Solução:**
1. Acesse `/notifications/settings`
2. Aba "WhatsApp"
3. Preencha credenciais
4. Ative o checkbox "Ativar Notificações por WhatsApp"
5. Salve

### **Problema 3: "Evento Criado não está ativo"**

**Sintoma:**
```
   Evento Criado Ativo: NÃO ❌
⚠️ PROBLEMA: Notificação 'Evento Criado' não está ativa!
```

**Solução:**
1. Acesse `/notifications/settings`
2. Aba "WhatsApp"
3. Role até "Tipos de Notificação"
4. Ative ☑️ **Evento Criado**
5. Salve

### **Problema 4: "Técnico sem telefone"**

**Sintoma:**
```
   - João Silva
     Email: joao@email.com
     Phone: N/A
```

**Solução:**
```sql
UPDATE users 
SET phone = '939729902' 
WHERE id = 1;
```

### **Problema 5: Observer não dispara**

**Verificar se Observer está registrado:**
```php
// app/Providers/AppServiceProvider.php
if (class_exists(\App\Models\Events\Event::class)) {
    \App\Models\Events\Event::observe(EventObserver::class);
}
```

**Limpar cache:**
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## 🎯 **TESTE MANUAL (SEM CRIAR EVENTO):**

Se quiser testar sem criar evento real:

```bash
php test-event-notification.php
```

O script:
1. ✅ Busca último evento criado
2. ✅ Verifica técnicos vinculados
3. ✅ Verifica configurações
4. ✅ Envia notificação de teste
5. ✅ Mostra resultado

---

## 📊 **ESTRUTURA DO BANCO DE DADOS:**

### **Tabela: events_events**
```sql
CREATE TABLE events_events (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT,
    name VARCHAR(255),
    description TEXT,
    start_date DATETIME,
    end_date DATETIME,
    venue_id BIGINT,
    status VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Tabela: events_event_staff**
```sql
CREATE TABLE events_event_staff (
    id BIGINT PRIMARY KEY,
    event_id BIGINT, -- FK para events_events
    user_id BIGINT,  -- FK para users
    role VARCHAR(100),
    assigned_start DATETIME,
    assigned_end DATETIME,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Tabela: users**
```sql
-- Campo necessário:
phone VARCHAR(20) -- Ex: 939729902 ou +244939729902
```

---

## ✅ **EXEMPLO COMPLETO:**

### **1. Criar evento com técnicos:**

```php
// No controller de eventos
$event = Event::create([
    'tenant_id' => auth()->user()->activeTenant()->id,
    'name' => 'Conferência Tech 2025',
    'description' => 'Grande evento de tecnologia',
    'start_date' => '2025-10-20 09:00:00',
    'end_date' => '2025-10-20 18:00:00',
    'venue_id' => 1,
    'status' => 'planned',
]);

// Adicionar técnicos
$event->staff()->attach([
    1 => ['role' => 'Técnico de Som'],
    2 => ['role' => 'Técnico de Luz'],
    3 => ['role' => 'Operador de Câmera'],
]);

// Observer dispara automaticamente! ✅
// Técnicos recebem WhatsApp imediatamente! ✅
```

### **2. Resultado esperado:**

**Técnico 1 recebe:**
```
🎉 Novo Evento Criado!

📅 Evento: Conferência Tech 2025
📍 Local: Auditório Principal
🗓️ Data: 20/10/2025 09:00
👤 Organizador: João Silva
```

**Técnico 2 recebe:**
```
[mesma mensagem]
```

**Técnico 3 recebe:**
```
[mesma mensagem]
```

---

## 🎊 **RESUMO:**

### **✅ O QUE FOI CORRIGIDO:**
1. ✅ Caminho do Model
2. ✅ Nome da tabela (events_events)
3. ✅ Tabela de técnicos (events_event_staff)
4. ✅ Observer registrado corretamente

### **⚠️ O QUE VOCÊ PRECISA FAZER:**
1. ⭐ **ADICIONAR TÉCNICOS AO EVENTO** (obrigatório!)
2. ✅ Ativar notificação em `/notifications/settings`
3. ✅ Certificar que técnicos têm telefone
4. ✅ Testar: `php test-event-notification.php`

---

## 📱 **PRÓXIMOS PASSOS:**

1. **Edite o evento ID 8** (ou crie novo)
2. **Adicione 2-3 técnicos** com telefones válidos
3. **Execute o teste:** `php test-event-notification.php`
4. **Verifique WhatsApp** dos técnicos

**Se tudo estiver OK, eles receberão a notificação! 🎉**

---

**Documentação adicional:**
- `SISTEMA-NOTIFICACOES-COMPLETO.md` - Sistema completo
- `IMMEDIATE-NOTIFICATIONS-GUIDE.md` - Notificações imediatas
- `PHONE-NORMALIZATION-GUIDE.md` - Telefones

**Problemas? Execute:** `php test-event-notification.php` para diagnóstico completo!
