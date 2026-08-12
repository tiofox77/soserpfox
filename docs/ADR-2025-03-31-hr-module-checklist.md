# HR Module — Checklist de Atualização (Doc Dembena ERP)

> **Data:** 2025-03-31 | **Responsável:** Dev | **Escopo:** Módulo RH completo

---

## Legenda
- [ ] Pendente
- [x] Concluído
- [~] Parcial (existe mas precisa atualizar)

---

## 1. MODELS

| # | Item | Ficheiro | Estado | Notas |
|---|------|----------|--------|-------|
| 1.1 | Employee — adicionar campos doc | `app/Models/HR/Employee.php` | [x] | Adicionados: `base_salary`, `food_benefit`, `transport_benefit`, `family_allowance`, `position_subsidy`, `performance_subsidy`, `employment_status` |
| 1.2 | OvertimeRecord (renomear Overtime) | `app/Models/HR/Overtime.php` | [x] | Adicionados: `input_type`, `direct_hours`, `period_type`, `is_night_shift`, `rate`, `amount`, `created_by` |
| 1.3 | SalaryAdvance | `app/Models/HR/SalaryAdvance.php` | [x] | `remaining_installments` é ACESSOR (installments − installments_paid), NÃO coluna. **Bug corrigido 2026-07:** `PayrollCalculatorHelper::loadSalaryAdvances()` filtrava `where('remaining_installments','>',0)` → SQL error (coluna inexistente) sempre que o funcionário tinha adiantamentos → agora `whereColumn('installments_paid','<','installments')`. (O caminho persistido `PayrollService::calculateAdvanceDeduction` usa `status='in_deduction'`+`balance`, já correto.) |
| 1.4 | **SalaryDiscount** | `app/Models/HR/SalaryDiscount.php` | [x] | Criado — modelo + migration + CRUD completo |
| 1.5 | Attendance — adicionar campos | `app/Models/HR/Attendance.php` | [x] | Adicionados: `time_in`, `time_out`, `hourly_rate`, `affects_payroll`, `remarks` |
| 1.6 | **IRTTaxBracket** | `app/Models/HR/IRTTaxBracket.php` | [x] | Criado — escalões IRT Angola com calculateIRT() |
| 1.7 | HRSetting | `app/Models/HR/HRSetting.php` | [x] | OK — key-value com cache |
| 1.8 | Payroll (batch) | `app/Models/HR/Payroll.php` | [x] | Alinhado — tenant(), forTenant scope adicionados |
| 1.9 | PayrollItem (batch item) | `app/Models/HR/PayrollItem.php` | [x] | Adicionados: `night_shift_allowance`, `night_shift_days`, `family_allowance`, `position_subsidy`, `performance_subsidy`, `christmas_subsidy_amount`, `vacation_subsidy_amount`, `discount_deduction`, `present_days`, `late_days`, `total_working_days`, `overtime_amount`, `food_deduction`, `additional_bonus` |

---

## 2. MIGRATIONS

| # | Item | Estado | Notas |
|---|------|--------|-------|
| 2.1 | `create_hr_salary_discounts_table` | [x] | Criado — 2025_03_31_100000 |
| 2.2 | `create_irt_tax_brackets_table` | [x] | Criado — 2025_03_31_100100 |
| 2.3 | `add_payroll_fields_to_hr_employees` | [x] | Criado — 2025_03_31_100200 |
| 2.4 | `add_overtime_record_fields_to_hr_overtime` | [x] | Criado — 2025_03_31_100300 |
| 2.5 | `add_attendance_fields_to_hr_attendances` | [x] | Criado — 2025_03_31_100400 |
| 2.6 | `add_batch_fields_to_hr_payroll_items` | [x] | Criado — 2025_03_31_100500 |

---

## 3. HELPER (Motor Central)

| # | Item | Ficheiro | Estado | Notas |
|---|------|----------|--------|-------|
| 3.1 | **PayrollCalculatorHelper** | `app/Helpers/PayrollCalculatorHelper.php` | [x] | Criado — motor central com Gross, IRT, INSS, Net |
| 3.2 | Registar no composer autoload | `composer.json` | [x] | PSR-4 autoload já cobre App\Helpers |

---

## 4. LIVEWIRE COMPONENTS

| # | Item | Ficheiro Atual | Estado | Notas |
|---|------|----------------|--------|-------|
| 4.1 | **SalaryDiscounts** | `SalaryDiscountManagement.php` | [x] | Criado — CRUD + aprovação + rejeição + pagamentos |
| 4.2 | SalaryAdvances | `SalaryAdvanceManagement.php` | [x] | Verificado — CRUD + aprovação + pagamento + HRSetting alinhado |
| 4.3 | OvertimeRecords | `OvertimeManagement.php` | [x] | OvertimeService atualizado: HRSetting multipliers, input_type, legal limits |
| 4.4 | **OvertimeNightShift** | `OvertimeNightShiftManagement.php` | [x] | Criado — CRUD + aprovação + cálculo subsídio noturno |
| 4.5 | Attendance | `AttendanceManagement.php` | [x] | Import biométrico implementado (ZKTeco + Hikvision) |
| 4.6 | PayrollBatch | `PayrollManagement.php` | [x] | Atualizado — PayrollService usa IRTTaxBracket, novos campos, descontos salariais |
| 4.7 | Individual Payroll | Integrado em PayrollManagement | [x] | Verificado — edit-item-modal atualizado com novos campos |

---

## 5. IMPORTS

| # | Item | Ficheiro | Estado | Notas |
|---|------|----------|--------|-------|
| 5.1 | **AttendanceImport** | `AttendanceManagement.php` (inline) | [x] | Implementado inline no componente — PhpSpreadsheet + ZKTeco/Hikvision |

---

## 6. VIEWS

| # | Item | Estado | Notas |
|---|------|--------|-------|
| 6.1 | Salary Discounts views | [x] | Criado — `discounts/discounts.blade.php` com listagem + modais |
| 6.2 | Overtime Night Shift views | [x] | Criado — `overtime-night-shift/overtime-night-shift.blade.php` |
| 6.3 | Payroll views — edit item modal | [x] | Atualizado — night shift, subsidies, discounts, IRT/INSS alinhado |
| 6.4 | Attendance — import modal update | [x] | Import biométrico funcional |
| 6.5 | HR Settings — garantir todas as keys do doc §11 | [x] | Seeder atualizado com todas as keys |

---

## 7. ROUTES

| # | Item | Estado | Notas |
|---|------|--------|-------|
| 7.1 | `hr.salary-discounts` | [x] | Adicionado em web.php |
| 7.2 | `hr.overtime-night-shift` | [x] | Adicionado em web.php |
| 7.3 | PDF routes (salary discounts) | [x] | Adicionado — SalaryDiscountController + view + route |

---

## 8. SEEDERS

| # | Item | Estado | Notas |
|---|------|--------|-------|
| 8.1 | IRT Tax Brackets seeder | [x] | Criado — `IRTTaxBracketSeeder.php` |
| 8.2 | HR Settings seeder — completar | [x] | Todas as keys do doc §11.2 adicionadas |

---

## 9. SIDEBAR (Layout)

| # | Item | Estado | Notas |
|---|------|--------|-------|
| 9.1 | Link "Descontos Salariais" | [x] | Adicionado — ícone fa-percentage |
| 9.2 | Link "Turno Noturno" | [x] | Adicionado — ícone fa-moon |

---

## Ordem de Implementação Recomendada

1. **Migrations** (2.1-2.6) — criar tabelas/campos primeiro
2. **Models** (1.4, 1.6) — SalaryDiscount, IRTTaxBracket
3. **Models update** (1.1, 1.2, 1.5, 1.9) — adicionar campos
4. **Seeders** (8.1, 8.2) — IRT brackets + HR settings
5. **PayrollCalculatorHelper** (3.1-3.2) — motor central
6. **SalaryDiscounts** completo (4.1 + 6.1 + 7.1) — componente novo
7. **OvertimeNightShift** completo (4.4 + 6.2 + 7.2) — componente novo
8. **AttendanceImport** (5.1) — import biométrico
9. **OvertimeRecords update** (4.3) — multipliers Angola
10. **PayrollBatch update** (4.6) — integrar PayrollCalculatorHelper
11. **Sidebar** (9.1-9.2)

---

## Progresso

| Fase | Total | Feito | % |
|------|-------|-------|---|
| Models | 9 | 9 | 100% |
| Migrations | 6 | 6 | 100% |
| Helper | 2 | 2 | 100% |
| Components | 7 | 7 | 100% |
| Imports | 1 | 1 | 100% |
| Views | 5 | 5 | 100% |
| Routes | 3 | 3 | 100% |
| Seeders | 2 | 2 | 100% |
| Sidebar | 2 | 2 | 100% |
| **TOTAL** | **37** | **37** | **100%** |

---

## Atualizações 2026-07 (correções de produção, testadas + deployed)

O checklist original (mar/2025) estava "97%" mas o motor de cálculo tinha bugs reais, corrigidos e validados via preview/testes com rollback:

- **Motor de payroll consolidado num só** — antes havia 3 cópias divergentes da matemática (`PayrollCalculatorHelper::calculate`, `PayrollService::calculateTaxes`, `PayrollItem::calculate`). Agora `PayrollItem::calculate()` é o motor único; `PayrollService::calculateTaxes()` delega; ambos os caminhos passam por `createPayrollItem()` (idempotente). O `processEmployeePayroll` estava partido (gerava `gross=0`).
- **Presenças/licenças** (`calculateAttendanceData` reescrito dia-a-dia): licença **paga** aprovada agora é paga (não descontada); modelo real = `hr_attendances.status` só `present`/`absent` + `is_late`/`leave_id`, licença via `hr_leaves.paid`. Sem status `late`/`half_day`/`leave` (o helper lia-os → código morto, corrigido).
- **Dedução de atraso** reposta (estava ausente no motor persistido); **origem dos subsídios** (campo do funcionário → senão HRSetting global); **alimentação em espécie** configurável (`food_paid_in_kind`).
- **IRT** (`IRTTaxBracket::calculateIRT`): tabela contínua, `tax_rate` guardado como FRAÇÃO (0.16) → sem `/100`. Ver [[irt-payroll-tax]].
- **Carbon 3 signed-diff** corrigido em vários sítios (férias/licenças davam dias negativos).
- **SalaryAdvance query bug** (1.3) — corrigido (ver acima).
- **NOVO: Integração Folha → Contabilidade** — `PostingService::postPayroll()` gera o lançamento contabilístico balanceado ao aprovar a folha (opt-in por `tenant.accounting_integration_enabled`, protegido por try/catch). Ver módulo Contabilidade.
