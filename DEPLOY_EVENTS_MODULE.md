# 🚀 Deploy do Módulo de Eventos no cPanel

## ⚠️ Problema Conhecido

A migration `2025_10_06_124400_create_events_tables` pode falhar se:
- As tabelas já foram parcialmente criadas
- Há cache do schema
- A migration foi interrompida no meio

---

## ✅ Solução Rápida

### **Opção 1: Migration Automática (Recomendado)**

A migration já está protegida com:
- Verificação de tabelas existentes
- Try-catch em todas as operações
- Log de erros

```bash
cd /home/soserp/soserp
php artisan migrate --force
```

**Se ainda der erro**, passe para a **Opção 2**.

---

### **Opção 2: Marcar Como Executada (Se tudo já foi criado)**

Se as tabelas já existem no banco, apenas marque a migration como executada:

```bash
cd /home/soserp/soserp

# Verificar se as tabelas existem
mysql -u usuario -p nome_banco -e "SHOW TABLES LIKE 'events_%';"

# Se todas as 6 tabelas existirem, marcar migration como executada
php artisan migrate:rollback --pretend
```

**Ou manualmente no banco:**

```sql
INSERT INTO migrations (migration, batch) 
VALUES ('2025_10_06_124400_create_events_tables', 
        (SELECT MAX(batch) FROM migrations AS m) + 1);
```

---

### **Opção 3: Limpar e Recriar (CUIDADO!)**

⚠️ **ATENÇÃO**: Isso apaga TODOS os dados das tabelas de eventos!

```bash
cd /home/soserp/soserp

# Entrar no MySQL
mysql -u usuario -p nome_banco

# No MySQL:
DROP TABLE IF EXISTS events_checklists;
DROP TABLE IF EXISTS events_event_staff;
DROP TABLE IF EXISTS events_event_equipment;
DROP TABLE IF EXISTS events_events;
DROP TABLE IF EXISTS events_equipment;
DROP TABLE IF EXISTS events_venues;

# Remover registro da migration
DELETE FROM migrations WHERE migration = '2025_10_06_124400_create_events_tables';

# Sair do MySQL
exit;

# Rodar migration novamente
php artisan migrate --force
```

---

## 🔍 Verificar Tabelas Criadas

```bash
mysql -u usuario -p nome_banco -e "
SELECT table_name, create_time 
FROM information_schema.tables 
WHERE table_schema = 'nome_banco' 
AND table_name LIKE 'events_%' 
ORDER BY table_name;
"
```

**Deve retornar 6 tabelas:**
1. ✅ `events_checklists`
2. ✅ `events_equipment`
3. ✅ `events_event_equipment`
4. ✅ `events_event_staff`
5. ✅ `events_events`
6. ✅ `events_venues`

---

## 📝 Verificar Status das Migrations

```bash
php artisan migrate:status | grep events
```

**Deve mostrar:**
```
[XX] Ran  2025_10_06_124400_create_events_tables
```

---

## 🐛 Debug de Problemas

### **Ver Logs:**
```bash
tail -f storage/logs/laravel.log
```

### **Ver Erros de Migration:**
Procure por:
- `Migration events_venues:`
- `Migration events_equipment:`
- `Migration events_events:`
- etc.

### **Verificar Estrutura de uma Tabela:**
```bash
mysql -u usuario -p nome_banco -e "DESCRIBE events_events;"
```

---

## 🔒 Permissões

Certifique-se de que o usuário do MySQL tem permissões:
```sql
GRANT ALL PRIVILEGES ON nome_banco.* TO 'usuario'@'localhost';
FLUSH PRIVILEGES;
```

---

## ✅ Após Deploy com Sucesso

1. **Atualizar o Seeder de Módulos:**
```bash
php artisan db:seed --class=ModuleSeeder
```

2. **Limpar Cache:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

3. **Otimizar:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. **Verificar no Sistema:**
   - Login como Super Admin
   - Ir em **Super Admin → Módulos**
   - Verificar se "Gestão de Eventos" aparece
   - Ativar o módulo
   - Vincular aos planos

5. **Testar:**
   - Login como usuário do tenant
   - Verificar se o menu "📅 Eventos" aparece
   - Acessar Dashboard de Eventos

---

## 🆘 Se Nada Funcionar

Entre em contato com o suporte e forneça:

1. **Output do comando:**
```bash
php artisan migrate --force 2>&1 | tee migration_error.log
```

2. **Lista de tabelas:**
```bash
mysql -u usuario -p nome_banco -e "SHOW TABLES;" > tables.txt
```

3. **Status das migrations:**
```bash
php artisan migrate:status > migrations_status.txt
```

4. **Últimas linhas do log:**
```bash
tail -n 100 storage/logs/laravel.log > laravel_log.txt
```

---

## 📚 Estrutura das Tabelas

### `events_venues` - Locais de Eventos
- id, tenant_id, name, address, city, phone, contact_person, capacity, notes, is_active

### `events_equipment` - Equipamentos
- id, tenant_id, name, code, category, specifications, daily_price, quantity, quantity_available, status, notes

### `events_events` - Eventos
- id, tenant_id, client_id, venue_id, event_number, name, description, type, start_date, end_date, setup_start, teardown_end, expected_attendees, total_value, status, notes, responsible_user_id

### `events_event_equipment` - Equipamentos por Evento
- id, event_id, equipment_id, quantity, unit_price, total_price, days, notes

### `events_event_staff` - Equipe Técnica
- id, event_id, user_id, role, assigned_start, assigned_end, cost

### `events_checklists` - Checklists
- id, event_id, task, description, status, assigned_to, due_date, completed_at, order

---

**✨ Boa sorte no deploy! ✨**
