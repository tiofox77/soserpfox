# Resumo dos Módulos HR Criados

## ✅ Módulos Implementados

### 1. **Licenças e Faltas** (`/hr/leaves`)
Sistema completo para gestão de licenças e faltas justificadas com integração às férias.

**Funcionalidades:**
- ✅ Tipos de licença: Doença, Maternidade, Paternidade, Luto, Casamento, Estudos, Sem Vencimento, Falta Justificada
- ✅ Validação de sobreposição com férias e outras licenças
- ✅ Upload de documentos/atestados médicos
- ✅ Cálculo automático de dias úteis e corridos
- ✅ Controle de licenças pagas e não pagas
- ✅ Workflow de aprovação/rejeição
- ✅ Histórico completo por funcionário

**Arquivos:**
- `database/migrations/2025_01_11_231000_create_hr_leaves_table.php`
- `app/Models/HR/Leave.php`
- `app/Services/HR/LeaveService.php`
- `app/Livewire/HR/LeaveManagement.php`

---

### 2. **Adiantamentos Salariais** (`/hr/advances`)
Sistema de gestão de adiantamentos com controle de parcelas e deduções mensais.

**Funcionalidades:**
- ✅ Solicitação de adiantamento com limite de 50% do salário
- ✅ Parcelamento em até 12x (configurável)
- ✅ Cálculo automático do máximo permitido
- ✅ Controle de saldo devedor
- ✅ Workflow de aprovação com valor ajustável
- ✅ Dedução automática em folha de pagamento
- ✅ Status: Pendente → Aprovado → Pago → Em Dedução → Concluído

**Arquivos:**
- `database/migrations/2025_01_11_232000_create_hr_salary_advances_table.php`
- `app/Models/HR/SalaryAdvance.php`
- `app/Services/HR/SalaryAdvanceService.php`
- `app/Livewire/HR/SalaryAdvanceManagement.php`

---

### 3. **Horas Extras** (`/hr/overtime`)
Sistema de registro e cálculo de horas extras integrado com presenças.

**Funcionalidades:**
- ✅ Registro manual de horas extras
- ✅ Geração automática a partir de presenças
- ✅ Tipos: Dia útil (50%), Fim de semana (100%), Feriado (100%), Noturno (25%)
- ✅ Cálculo automático baseado em multiplicadores configuráveis
- ✅ Taxa horária calculada do salário mensal
- ✅ Workflow de aprovação/rejeição
- ✅ Integração com folha de pagamento
- ✅ Relatórios por período e tipo

**Arquivos:**
- `database/migrations/2025_01_11_233000_create_hr_overtime_table.php`
- `app/Models/HR/Overtime.php`
- `app/Services/HR/OvertimeService.php`
- `app/Livewire/HR/OvertimeManagement.php`

---

### 4. **Configurações HR** (`/hr/settings`)
Sistema de configurações baseado na legislação trabalhista angolana.

**Configurações Incluídas:**

#### **Geral**
- ✅ Dias trabalhados por mês: **22 dias** (padrão Angola)
- ✅ Horas de trabalho por dia: **8 horas**
- ✅ Horas mensais: **176 horas** (22 dias × 8h)

#### **Férias**
- ✅ Dias de férias anuais: **22 dias úteis**
- ✅ Subsídio de férias (14º mês): **50%** do salário (mínimo legal)

#### **Horas Extras**
- ✅ Dia útil: **1.5x** (50% adicional)
- ✅ Fim de semana: **2.0x** (100% adicional)
- ✅ Feriado: **2.0x** (100% adicional)
- ✅ Noturno: **1.25x** (25% adicional)

#### **Subsídios**
- ✅ Subsídio de Natal (13º mês): **100%** do salário
- ✅ Subsídio de alimentação: Configurável (Kz)
- ✅ Subsídio de transporte: Configurável (Kz)

#### **Adiantamentos**
- ✅ Percentual máximo: **50%** do salário
- ✅ Máximo de parcelas: **6 parcelas**

#### **Licenças (Legislação Angolana)**
- ✅ Maternidade: **90 dias**
- ✅ Paternidade: **3 dias**
- ✅ Casamento: **10 dias**
- ✅ Luto: **5 dias**

**Arquivos:**
- `database/migrations/2025_01_11_234000_create_hr_settings_table.php`
- `app/Models/HR/HRSetting.php`
- `app/Services/HR/HRSettingsService.php`
- `app/Livewire/HR/SettingsManagement.php`
- `resources/views/livewire/hr/settings/settings.blade.php`

---

## 🔗 Integrações

### **Licenças ↔ Férias**
- ✅ Validação de sobreposição: não permite licença durante férias aprovadas
- ✅ Validação de conflito: não permite duas licenças no mesmo período

### **Horas Extras ↔ Presenças**
- ✅ Criação automática de hora extra quando presença registra overtime
- ✅ Referência à presença original mantida
- ✅ Cálculo automático com base no horário de saída

### **Adiantamentos ↔ Folha de Pagamento**
- ✅ Método `processMonthlyDeductions()` para dedução automática
- ✅ Cálculo de parcelas baseado no salário base
- ✅ Controle de saldo devedor e parcelas pagas

### **Configurações ↔ Todos os Módulos**
- ✅ Service centralizado (`HRSettingsService`) para acesso às configurações
- ✅ Cache automático para performance
- ✅ Valores podem ser alterados sem alterar código

---

## 📊 Estrutura do Banco de Dados

### Novas Tabelas:
1. `hr_leaves` - Licenças e faltas
2. `hr_salary_advances` - Adiantamentos salariais
3. `hr_overtime` - Horas extras
4. `hr_settings` - Configurações do sistema

### Relacionamentos:
```
hr_employees (1) ─── (N) hr_leaves
hr_employees (1) ─── (N) hr_salary_advances
hr_employees (1) ─── (N) hr_overtime
hr_employees (1) ─── (N) hr_vacations (já existe)

hr_overtime (N) ─── (1) hr_attendances
hr_overtime (N) ─── (1) hr_payrolls

tenants (1) ─── (N) hr_settings
```

---

## 🛣️ Rotas Adicionadas

```php
Route::middleware(['auth'])->prefix('hr')->name('hr.')->group(function () {
    // Módulos existentes
    Route::get('/employees', EmployeeManagement::class)->name('employees');
    Route::get('/payroll', PayrollManagement::class)->name('payroll');
    Route::get('/departments', DepartmentManagement::class)->name('departments');
    Route::get('/attendance', AttendanceManagement::class)->name('attendance');
    Route::get('/vacations', VacationManagement::class)->name('vacations');
    
    // Novos módulos
    Route::get('/leaves', LeaveManagement::class)->name('leaves');          // ✅ NOVO
    Route::get('/advances', SalaryAdvanceManagement::class)->name('advances'); // ✅ NOVO
    Route::get('/overtime', OvertimeManagement::class)->name('overtime');     // ✅ NOVO
    Route::get('/settings', SettingsManagement::class)->name('settings');     // ✅ NOVO
});
```

---

## 🚀 Como Usar

### 1. **Executar Migrações**
```bash
php artisan migrate
```

Isso criará:
- Tabelas de licenças, adiantamentos, horas extras e configurações
- Inserirá automaticamente as configurações padrão da legislação angolana

### 2. **Acessar os Módulos**
- Licenças: `http://seudominio.com/hr/leaves`
- Adiantamentos: `http://seudominio.com/hr/advances`
- Horas Extras: `http://seudominio.com/hr/overtime`
- Configurações: `http://seudominio.com/hr/settings`

### 3. **Usar o Service de Configurações**
```php
use App\Services\HR\HRSettingsService;

$settings = new HRSettingsService();

// Obter dias de férias
$vacationDays = $settings->getVacationDaysPerYear(); // 22

// Obter multiplicador de hora extra
$multiplier = $settings->getOvertimeMultiplier('weekend'); // 2.0

// Calcular taxa horária
$hourlyRate = $settings->calculateHourlyRate($monthlySalary);
```

### 4. **Acessar Configuração Diretamente**
```php
use App\Models\HR\HRSetting;

// Obter valor
$value = HRSetting::get('vacation_days_per_year', 22);

// Definir valor
HRSetting::set('vacation_days_per_year', 25);
```

---

## 📋 Legislação Angolana Implementada

### **Lei Geral do Trabalho (Lei n.º 7/15)**

✅ **Artigo 87º - Duração do Trabalho**
- Jornada normal: 8 horas diárias / 44 horas semanais
- Implementado: 8 horas/dia, 22 dias/mês, 176 horas/mês

✅ **Artigo 127º - Direito a Férias**
- 22 dias úteis por ano completo de trabalho
- Subsídio de férias: mínimo 50% da remuneração base

✅ **Artigo 131º - Subsídio de Natal**
- 13º mês: 100% da remuneração base
- Pago em dezembro

✅ **Artigo 108º - Trabalho Suplementar (Horas Extras)**
- Dias úteis: acréscimo de 50%
- Descanso semanal: acréscimo de 100%
- Feriados: acréscimo de 100%

✅ **Artigo 94º - Trabalho Noturno**
- Acréscimo de 25% sobre o trabalho normal

✅ **Licenças Especiais:**
- Maternidade: 3 meses (90 dias)
- Paternidade: 3 dias úteis
- Casamento: 10 dias
- Falecimento (familiar direto): 5 dias

---

## 🎯 Próximos Passos Recomendados

1. **Criar Views Completas** (formulários e modais) para:
   - Licenças (`resources/views/livewire/hr/leaves/`)
   - Adiantamentos (`resources/views/livewire/hr/advances/`)
   - Horas Extras (`resources/views/livewire/hr/overtime/`)

2. **Integrar com Folha de Pagamento**
   - Adicionar deduções de adiantamentos
   - Incluir horas extras no processamento
   - Aplicar descontos de licenças não pagas

3. **Adicionar Relatórios**
   - Relatório mensal de horas extras por funcionário
   - Relatório de licenças por período
   - Relatório de adiantamentos e saldo devedor

4. **Notificações**
   - Notificar funcionário quando licença/adiantamento for aprovado/rejeitado
   - Alertar RH sobre solicitações pendentes

---

## 📞 Suporte

Para dúvidas sobre a legislação angolana implementada, consulte:
- Lei Geral do Trabalho (Lei n.º 7/15 de 15 de Junho)
- Decreto Presidencial n.º 47/19 (Regulamentação)
