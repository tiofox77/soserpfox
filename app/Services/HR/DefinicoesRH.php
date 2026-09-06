<?php

namespace App\Services\HR;

use App\Models\HR\HRSetting;
use Illuminate\Support\Facades\DB;

/**
 * As definições de RH de uma empresa, e a garantia de que existem.
 *
 * O ECRÃ ESTAVA VAZIO E O MOTIVO ERA ESTE. O catálogo vivia dentro do
 * HRSettingsSeeder com `$tenantId = 1;` escrito à mão e um comentário
 * "Ajustar conforme necessário". Nunca foi ajustado: só a empresa 1 tinha
 * configurações de RH, e as restantes com o módulo activo abriam
 * /hr/settings e não viam absolutamente nada — sem lista, sem aviso, sem
 * explicação. Nada aparecia porque nada existia.
 *
 * E não havia como corrigir a partir do ecrã: ele só sabe editar linhas que já
 * existem. Criá-las era trabalho de consola, por empresa.
 *
 * O catálogo é agora o mesmo para todas: cada empresa recebe a sua cópia, que
 * depois edita à vontade. `garantirPara()` é idempotente e NÃO toca no que já
 * lá está — corrigir um valor não pode ser desfeito por uma visita ao ecrã.
 *
 * Os valores são os da legislação laboral angolana. Não são conselho legal nem
 * substituem a conferência de quem faz salários; são o ponto de partida para
 * uma empresa não começar com o ecrã em branco.
 */
class DefinicoesRH
{
    /**
     * Chaves que mudaram de nome: antiga => nova.
     *
     * Sem isto, uma renomeação deixa um campo fantasma no ecrã: o antigo
     * continua gravado, continua a aparecer, e edita-se sem efeito nenhum
     * porque já ninguém o lê. Com a mesma etiqueta do novo, ao lado dele.
     *
     * A antiga é DESACTIVADA, nunca apagada: o valor que a empresa lá tinha
     * fica guardado, e desfaz-se pondo `is_active` a 1.
     */
    private const RENOMEADAS = [
        // O catálogo tinha `salary_advance_max_percentage`; o ecrã de
        // adiantamentos, o SalaryAdvanceService e o HRSettingsService lêem
        // todos `max_salary_advance_percentage`.
        'salary_advance_max_percentage' => 'max_salary_advance_percentage',

        // O mesmo facto em dois formatos: "22:00" em texto e 22 em número. A
        // que o PayrollCalculatorHelper lê é a numérica.
        'night_shift_start' => 'night_shift_start_hour',
        'night_shift_end'   => 'night_shift_end_hour',

        // Duas etiquetas para o limite diário de horas extra, e com valores
        // DIFERENTES: "Máximo de Horas Extras por Dia: 4" ao lado de "Limite
        // Diário Horas Extras: 2". Quem lê o ecrã não sabe qual vale; vale a
        // segunda.
        'overtime_max_hours_day' => 'overtime_daily_limit',

        // Subsídio de férias: duas entradas com o mesmo valor, uma lida.
        'vacation_subsidy_rate' => 'vacation_subsidy_percentage',

        // Subsídio de Natal: existiam duas chaves activas e contraditórias
        // (100% em benefits e 50% em payroll). O motor salarial sempre lê a
        // chave canónica abaixo; as antigas ficam preservadas mas ocultas.
        'christmas_bonus_rate'       => 'christmas_subsidy_percentage',
        'christmas_bonus_percentage' => 'christmas_subsidy_percentage',
    ];

    /**
     * Definições que ficam no ecrã mas que NENHUM cálculo lê ainda.
     *
     * Não são erro: descrevem regras reais da empresa (aviso prévio, período
     * de experiência, dia de pagamento) que o sistema guarda e ainda não usa.
     * Mas mostrá-las como campos vulgares é mentir — edita-se, grava, e não
     * acontece nada. O ecrã marca-as, e é por isso que esta lista existe.
     *
     * Sai daqui à medida que o cálculo passe a lê-las.
     */
    public const INFORMATIVAS = [
        'overtime_max_hours_week',
        'vacation_proportional',
        'vacation_advance_days',
        'payroll_cutoff_day',
        'payroll_payment_day',
        'probation_period_days',
        'termination_notice_days',
    ];

    /**
     * Cria as definições em falta de uma empresa.
     *
     * NÃO altera as que já existem: uma empresa que baixou o INSS ou mudou o
     * subsídio não pode ver isso revertido só por alguém ter aberto o ecrã. Só
     * acrescenta o que falta — o que também é o que faz um catálogo novo
     * chegar às empresas antigas.
     *
     * @return int quantas foram criadas
     */
    public static function garantirPara(int $tenantId): int
    {
        $existentes = HRSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->pluck('key')
            ->flip();

        $novas = [];
        $agora = now();

        foreach (self::catalogo() as $definicao) {
            if (isset($existentes[$definicao['key']])) {
                continue;
            }

            $novas[] = $definicao + [
                'tenant_id'  => $tenantId,
                'is_active'  => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        if (!empty($novas)) {
            // insert em bloco e não 59 saves: isto corre no mount() do ecrã, e
            // uma ida à base por definição fazia a primeira visita demorar.
            DB::table((new HRSetting)->getTable())->insert($novas);
        }

        self::desactivarRenomeadas($tenantId);

        return count($novas);
    }

    /**
     * Esconde as chaves antigas cujo nome novo já existe.
     *
     * Só desactiva, e só quando a nova está lá: se por alguma razão a nova não
     * tiver sido criada, a antiga fica visível — mais vale um campo com o nome
     * errado do que nenhum.
     */
    private static function desactivarRenomeadas(int $tenantId): void
    {
        $tabela = (new HRSetting)->getTable();

        foreach (self::RENOMEADAS as $antiga => $nova) {
            $temNova = DB::table($tabela)
                ->where('tenant_id', $tenantId)
                ->where('key', $nova)
                ->exists();

            if (!$temNova) {
                continue;
            }

            DB::table($tabela)
                ->where('tenant_id', $tenantId)
                ->where('key', $antiga)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    /** Uma empresa já tem definições de RH? */
    public static function temDefinicoes(int $tenantId): bool
    {
        return HRSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * O catálogo, sem empresa nenhuma.
     *
     * Os valores vêm da legislação laboral angolana. `value` é o valor inicial
     * e `default_value` é para onde o botão "Restaurar padrões" volta.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            [
                'category' => 'worktime',
                'key' => 'working_days_per_month',
                'label' => 'Dias de Trabalho por Mês',
                'description' => 'Número médio de dias úteis de trabalho por mês',
                'value' => '22',
                'default_value' => '22',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:20|max:26',
                'display_order' => 1,
            ],
            [
                'category' => 'worktime',
                'key' => 'working_hours_per_day',
                'label' => 'Horas de Trabalho por Dia',
                'description' => 'Carga horária diária normal de trabalho',
                'value' => '8',
                'default_value' => '8',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:6|max:10',
                'display_order' => 2,
            ],
            [
                'category' => 'worktime',
                'key' => 'working_hours_per_week',
                'label' => 'Horas de Trabalho por Semana',
                'description' => 'Carga horária semanal normal de trabalho',
                'value' => '44',
                'default_value' => '44',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:40|max:48',
                'display_order' => 3,
            ],
            [
                // Decide se a entrada «Turnos» aparece no menu de RH. Vinha de
                // uma migração de 2025 e faltava aqui: as empresas nascidas
                // depois nunca a viam no ecrã e o menu lia o valor escrito
                // no PHP.
                'category' => 'worktime',
                'key' => 'uses_shifts',
                'label' => 'Trabalha por Turnos?',
                'description' => 'Ativar se a empresa opera com sistema de turnos',
                'value' => '0',
                'default_value' => '0',
                'value_type' => 'boolean',
                'validation_rules' => 'boolean',
                'display_order' => 4,
            ],
            [
                'category' => 'worktime',
                'key' => 'work_on_saturday',
                'label' => 'Trabalha ao Sábado',
                'description' => 'A empresa trabalha ao sábado? (1 = Sim, 0 = Não)',
                'value' => '0',
                'default_value' => '0',
                'value_type' => 'boolean',
                'validation_rules' => 'boolean',
                'display_order' => 6,
            ],
            [
                'category' => 'worktime',
                'key' => 'saturday_working_hours',
                'label' => 'Horas de Trabalho ao Sábado',
                'description' => 'Carga horária do sábado (se aplicável). Ex: 4 = meio período, 8 = dia inteiro',
                'value' => '4',
                'default_value' => '4',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:1|max:8',
                'display_order' => 7,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_weekday_rate',
                'label' => 'Taxa Hora Extra - Dias Úteis',
                'description' => 'Percentual adicional para horas extras em dias úteis (Lei Angolana: 50%)',
                'value' => '50',
                'default_value' => '50',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:50|max:100',
                'display_order' => 10,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_weekend_rate',
                'label' => 'Taxa Hora Extra - Fins de Semana',
                'description' => 'Percentual adicional para horas extras aos fins de semana (Lei Angolana: 100%)',
                'value' => '100',
                'default_value' => '100',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:100|max:200',
                'display_order' => 11,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_holiday_rate',
                'label' => 'Taxa Hora Extra - Feriados',
                'description' => 'Percentual adicional para horas extras em feriados (Lei Angolana: 100%)',
                'value' => '100',
                'default_value' => '100',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:100|max:200',
                'display_order' => 12,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_night_rate',
                'label' => 'Taxa Trabalho Noturno',
                'description' => 'Percentual adicional para trabalho noturno (Lei Angolana: 25%)',
                'value' => '25',
                'default_value' => '25',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:25|max:50',
                'display_order' => 13,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_max_hours_week',
                'label' => 'Máximo de Horas Extras por Semana',
                'description' => 'Limite semanal de horas extras permitidas',
                'value' => '12',
                'default_value' => '12',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:8|max:20',
                'display_order' => 15,
            ],
            [
                'category' => 'vacation',
                'key' => 'vacation_days_per_year',
                'label' => 'Dias de Férias por Ano',
                'description' => 'Dias úteis de férias por ano completo (Lei Angolana: 22 dias úteis)',
                'value' => '22',
                'default_value' => '22',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:22|max:30',
                'display_order' => 20,
            ],
            [
                'category' => 'vacation',
                'key' => 'vacation_proportional',
                'label' => 'Férias Proporcionais',
                'description' => 'Calcular férias proporcionais aos meses trabalhados',
                'value' => '1',
                'default_value' => '1',
                'value_type' => 'boolean',
                'validation_rules' => 'boolean',
                'display_order' => 22,
            ],
            [
                'category' => 'vacation',
                'key' => 'vacation_advance_days',
                'label' => 'Dias de Antecedência para Pagamento',
                'description' => 'Dias de antecedência para pagamento do subsídio de férias',
                'value' => '15',
                'default_value' => '15',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:7|max:30',
                'display_order' => 23,
            ],
            [
                'category' => 'leave',
                'key' => 'maternity_leave_days',
                'label' => 'Licença Maternidade (dias)',
                'description' => 'Dias de licença maternidade (Lei Angolana: 90 dias)',
                'value' => '90',
                'default_value' => '90',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:90|max:120',
                'display_order' => 30,
            ],
            [
                'category' => 'leave',
                'key' => 'paternity_leave_days',
                'label' => 'Licença Paternidade (dias)',
                'description' => 'Dias de licença paternidade (Lei Angolana: 3 dias)',
                'value' => '3',
                'default_value' => '3',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:3|max:10',
                'display_order' => 31,
            ],
            [
                'category' => 'leave',
                'key' => 'marriage_leave_days',
                'label' => 'Licença Casamento (dias)',
                'description' => 'Dias de licença para casamento (Lei Angolana: 10 dias)',
                'value' => '10',
                'default_value' => '10',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:5|max:15',
                'display_order' => 32,
            ],
            [
                'category' => 'leave',
                'key' => 'bereavement_leave_days',
                'label' => 'Licença Luto Familiar (dias)',
                'description' => 'Dias de licença por falecimento de familiar direto (Lei Angolana: 5 dias)',
                'value' => '5',
                'default_value' => '5',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:3|max:10',
                'display_order' => 33,
            ],
            [
                'category' => 'benefits',
                // `max_salary_advance_percentage` e não `salary_advance_max_percentage`.
                // O catálogo tinha o segundo, que NINGUÉM lê: o ecrã de
                // adiantamentos, o SalaryAdvanceService e o HRSettingsService
                // lêem todos o primeiro. O campo aparecia nas configurações,
                // editava-se, e o limite dos adiantamentos continuava nos 50%
                // escritos no PHP.
                'key' => 'max_salary_advance_percentage',
                'label' => 'Percentual Máximo de Adiantamento',
                'description' => 'Percentual máximo do salário que pode ser adiantado (pode ser acima de 100%)',
                'value' => '50',
                'default_value' => '50',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:0|max:500',
                'display_order' => 39,
            ],
            // ── Multiplicadores lidos pelo HRSettingsService ──
            //
            // `getOvertimeMultiplier()` lê estas quatro e, quando não existem,
            // devolve 1.5 para TUDO — dia útil, fim-de-semana, feriado e noite
            // pagos ao mesmo. Só a empresa 1 as tinha (de uma versão antiga do
            // seeder); as restantes ficavam com o 1,5 escrito no PHP sem que
            // ninguém pudesse ver ou mudar o valor.
            //
            // Os valores são os que a empresa 1 tem gravados desde 2025, que é
            // a única referência real que existe no sistema.
            //
            // ATENÇÃO: convivem com `overtime_weekday_rate` e companhia, que
            // exprimem o mesmo em percentagem de acréscimo (50 = +50%) e são
            // lidas por OUTROS serviços. Unificá-las mudaria o que as pessoas
            // recebem, e por isso não o fiz sozinho — ver a nota no fim da
            // classe.
            [
                'category' => 'overtime',
                'key' => 'overtime_weekday_multiplier',
                'label' => 'Multiplicador Hora Extra - Dia Útil',
                'description' => 'Multiplicador aplicado à hora normal em dia útil (1,5 = hora e meia)',
                'value' => '1.5',
                'default_value' => '1.5',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 25,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_weekend_multiplier',
                'label' => 'Multiplicador Hora Extra - Fim de Semana',
                'description' => 'Multiplicador aplicado à hora normal ao fim-de-semana',
                'value' => '2.0',
                'default_value' => '2.0',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 26,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_holiday_multiplier',
                'label' => 'Multiplicador Hora Extra - Feriado',
                'description' => 'Multiplicador aplicado à hora normal em feriado',
                'value' => '2.0',
                'default_value' => '2.0',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 27,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_night_multiplier',
                'label' => 'Multiplicador Hora Extra - Nocturno',
                'description' => 'Multiplicador aplicado à hora normal em trabalho nocturno',
                'value' => '1.25',
                'default_value' => '1.25',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:2',
                'display_order' => 28,
            ],
            [
                'category' => 'benefits',
                'key' => 'max_advance_installments',
                'label' => 'Máximo de Prestações do Adiantamento',
                'description' => 'Em quantas prestações um adiantamento pode ser descontado',
                'value' => '6',
                'default_value' => '6',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:1|max:12',
                'display_order' => 40,
            ],
            [
                'category' => 'benefits',
                'key' => 'christmas_bonus_percentage',
                'label' => 'Percentual do Subsídio de Natal',
                'description' => 'Percentual do salário base pago como subsídio de Natal',
                'value' => '100',
                'default_value' => '100',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:0|max:200',
                'display_order' => 41,
            ],
            [
                'category' => 'benefits',
                'key' => 'food_paid_in_kind',
                'label' => 'Subsídio de Alimentação em Espécie',
                'description' => 'A alimentação é fornecida pela empresa em vez de paga em dinheiro',
                'value' => '0',
                'default_value' => '0',
                'value_type' => 'boolean',
                'validation_rules' => null,
                'display_order' => 42,
            ],
            [
                'category' => 'worktime',
                'key' => 'monthly_working_hours',
                'label' => 'Horas de Trabalho por Mês',
                'description' => 'Total de horas normais de trabalho num mês',
                'value' => '176',
                'default_value' => '176',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:120|max:240',
                'display_order' => 8,
            ],
            [
                'category' => 'payroll',
                'key' => 'assume_present_without_attendance',
                'label' => 'Assumir Presença sem Registo de Ponto',
                'description' => 'Sem marcação de ponto, o empregado conta como presente no processamento',
                'value' => '1',
                'default_value' => '1',
                'value_type' => 'boolean',
                'validation_rules' => null,
                'display_order' => 55,
            ],
            [
                'category' => 'benefits',
                'key' => 'allowance_calculation_method',
                'label' => 'Método de Cálculo de Subsídios',
                'description' => 'Como calcular subsídios (alimentação/transporte) na folha: proportional = proporcional aos dias trabalhados, full_if_worked = integral se trabalhou pelo menos 1 dia, daily_rate = valor fixo por dia trabalhado',
                'value' => 'proportional',
                'default_value' => 'proportional',
                'value_type' => 'string',
                'validation_rules' => 'required|string|in:proportional,full_if_worked,daily_rate',
                'display_order' => 40,
            ],
            [
                'category' => 'benefits',
                'key' => 'monthly_food_allowance',
                'label' => 'Subsídio Alimentação Mensal (Kz)',
                'description' => 'Valor mensal base do subsídio de alimentação para todos os funcionários',
                'value' => '0',
                'default_value' => '0',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 41,
            ],
            [
                'category' => 'benefits',
                'key' => 'monthly_transport_allowance',
                'label' => 'Subsídio Transporte Mensal (Kz)',
                'description' => 'Valor mensal base do subsídio de transporte para todos os funcionários',
                'value' => '0',
                'default_value' => '0',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 42,
            ],
            [
                'category' => 'benefits',
                'key' => 'daily_meal_allowance',
                'label' => 'Subsídio Alimentação por Dia (Kz)',
                'description' => 'Valor fixo pago por dia trabalhado (usado se método = daily_rate)',
                'value' => '1000',
                'default_value' => '1000',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 43,
            ],
            [
                'category' => 'benefits',
                'key' => 'daily_transport_allowance',
                'label' => 'Subsídio Transporte por Dia (Kz)',
                'description' => 'Valor fixo pago por dia trabalhado (usado se método = daily_rate)',
                'value' => '1000',
                'default_value' => '1000',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 44,
            ],
            [
                'category' => 'benefits',
                'key' => 'christmas_bonus_month',
                'label' => 'Mês de Pagamento do Subsídio de Natal',
                'description' => 'Mês para pagamento do subsídio de Natal',
                'value' => '12',
                'default_value' => '12',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:11|max:12',
                'display_order' => 44,
            ],
            [
                'category' => 'benefits',
                'key' => 'meal_allowance_enabled',
                'label' => 'Subsídio de Alimentação Ativo',
                'description' => 'Habilitar subsídio de alimentação',
                'value' => '1',
                'default_value' => '1',
                'value_type' => 'boolean',
                'validation_rules' => 'boolean',
                'display_order' => 45,
            ],
            [
                'category' => 'benefits',
                'key' => 'transport_allowance_enabled',
                'label' => 'Subsídio de Transporte Ativo',
                'description' => 'Habilitar subsídio de transporte',
                'value' => '1',
                'default_value' => '1',
                'value_type' => 'boolean',
                'validation_rules' => 'boolean',
                'display_order' => 46,
            ],
            [
                'category' => 'payroll',
                'key' => 'payroll_cutoff_day',
                'label' => 'Dia de Corte da Folha',
                'description' => 'Dia do mês para fechamento da folha de pagamento',
                'value' => '25',
                'default_value' => '25',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:1|max:28',
                'display_order' => 50,
            ],
            [
                'category' => 'payroll',
                'key' => 'payroll_payment_day',
                'label' => 'Dia de Pagamento',
                'description' => 'Dia do mês para pagamento dos salários',
                'value' => '30',
                'default_value' => '30',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:1|max:31',
                'display_order' => 51,
            ],
            [
                'category' => 'payroll',
                'key' => 'inss_employee_rate',
                'label' => 'Taxa INSS Funcionário',
                'description' => 'Percentual de desconto INSS sobre salário do funcionário',
                'value' => '3',
                'default_value' => '3',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:0|max:10',
                'display_order' => 52,
            ],
            [
                'category' => 'payroll',
                'key' => 'inss_employer_rate',
                'label' => 'Taxa INSS Empresa',
                'description' => 'Percentual de contribuição INSS pela empresa',
                'value' => '8',
                'default_value' => '8',
                'value_type' => 'percentage',
                'validation_rules' => 'required|numeric|min:0|max:15',
                'display_order' => 53,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_first_hour_weekday',
                'label' => 'Multiplicador 1ª Hora Dia Útil',
                'description' => 'Multiplicador para a 1ª hora extra em dia útil (×1.25 = +25%)',
                'value' => '1.25',
                'default_value' => '1.25',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 16,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_additional_hours_weekday',
                'label' => 'Multiplicador Horas Adicionais Dia Útil',
                'description' => 'Multiplicador para horas extras adicionais em dia útil (×1.375 = +37.5%)',
                'value' => '1.375',
                'default_value' => '1.375',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 17,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_multiplier_weekend',
                'label' => 'Multiplicador Fim de Semana',
                'description' => 'Multiplicador horas extras fim de semana (×2.0 = +100%)',
                'value' => '2.0',
                'default_value' => '2.0',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:4',
                'display_order' => 18,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_multiplier_holiday',
                'label' => 'Multiplicador Feriado',
                'description' => 'Multiplicador horas extras feriado (×2.5 = +150%)',
                'value' => '2.5',
                'default_value' => '2.5',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:5',
                'display_order' => 19,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_daily_limit',
                'label' => 'Limite Diário Horas Extras',
                'description' => 'Máximo horas extras por dia (dias úteis)',
                'value' => '2',
                'default_value' => '2',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:1|max:6',
                'display_order' => 20,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_monthly_limit',
                'label' => 'Limite Mensal Horas Extras',
                'description' => 'Máximo horas extras por mês',
                'value' => '48',
                'default_value' => '48',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:20|max:100',
                'display_order' => 21,
            ],
            [
                'category' => 'overtime',
                'key' => 'overtime_yearly_limit',
                'label' => 'Limite Anual Horas Extras',
                'description' => 'Máximo horas extras por ano',
                'value' => '200',
                'default_value' => '200',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:100|max:500',
                'display_order' => 22,
            ],
            [
                'category' => 'overtime',
                'key' => 'night_shift_percentage',
                'label' => 'Adicional Noturno (%)',
                'description' => 'Percentual adicional para trabalho noturno (Art. 102º)',
                'value' => '25',
                'default_value' => '25',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:20|max:50',
                'display_order' => 23,
            ],
            [
                'category' => 'overtime',
                'key' => 'night_shift_start_hour',
                'label' => 'Hora Início Turno Noturno',
                'description' => 'Hora de início do turno noturno (22 = 22:00)',
                'value' => '22',
                'default_value' => '22',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:18|max:23',
                'display_order' => 24,
            ],
            [
                'category' => 'overtime',
                'key' => 'night_shift_end_hour',
                'label' => 'Hora Fim Turno Noturno',
                'description' => 'Hora de término do turno noturno (6 = 06:00)',
                'value' => '6',
                'default_value' => '6',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:4|max:8',
                'display_order' => 25,
            ],
            [
                'category' => 'overtime',
                'key' => 'night_shift_multiplier',
                'label' => 'Multiplicador Turno Noturno',
                'description' => 'Multiplicador para turno noturno',
                'value' => '1.25',
                'default_value' => '1.25',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:1|max:2',
                'display_order' => 26,
            ],
            [
                'category' => 'overtime',
                'key' => 'min_overtime_minutes',
                'label' => 'Mínimo Minutos para Hora Extra',
                'description' => 'Mínimo de minutos para contar como hora extra',
                'value' => '15',
                'default_value' => '15',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:5|max:30',
                'display_order' => 27,
            ],
            [
                'category' => 'overtime',
                'key' => 'round_to_nearest_minutes',
                'label' => 'Arredondamento (minutos)',
                'description' => 'Arredondar horas extras para o intervalo mais próximo',
                'value' => '15',
                'default_value' => '15',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:5|max:30',
                'display_order' => 28,
            ],
            [
                'category' => 'overtime',
                'key' => 'allow_partial_hours',
                'label' => 'Permitir Horas Parciais',
                'description' => 'Permitir registar horas fracionadas',
                'value' => '1',
                'default_value' => '1',
                'value_type' => 'boolean',
                'validation_rules' => 'boolean',
                'display_order' => 29,
            ],
            [
                'category' => 'overtime',
                'key' => 'default_hourly_rate',
                'label' => 'Taxa Horária Padrão',
                'description' => 'Taxa horária padrão (fallback)',
                'value' => '10.00',
                'default_value' => '10.00',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 30,
            ],
            [
                'category' => 'payroll',
                'key' => 'min_salary_tax_exempt',
                'label' => 'Salário Mínimo Isento IRT',
                'description' => 'Salário mínimo para isenção de IRT (AOA)',
                'value' => '70000',
                'default_value' => '70000',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 54,
            ],
            [
                'category' => 'payroll',
                'key' => 'transport_tax_exempt',
                'label' => 'Isenção Transporte IRT',
                'description' => 'Valor máximo de transporte isento de IRT (AOA)',
                'value' => '30000',
                'default_value' => '30000',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 55,
            ],
            [
                'category' => 'payroll',
                'key' => 'food_tax_exempt',
                'label' => 'Isenção Alimentação IRT',
                'description' => 'Valor máximo de alimentação isento de IRT (AOA)',
                'value' => '30000',
                'default_value' => '30000',
                'value_type' => 'decimal',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 56,
            ],
            [
                'category' => 'payroll',
                'key' => 'vacation_subsidy_percentage',
                'label' => 'Percentual Subsídio Férias',
                'description' => 'Percentual do salário base para subsídio de férias',
                'value' => '50',
                'default_value' => '50',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:0|max:100',
                'display_order' => 57,
            ],
            [
                'category' => 'payroll',
                'key' => 'christmas_subsidy_percentage',
                'label' => 'Percentual Subsídio Natal',
                'description' => 'Percentual do salário base para subsídio de Natal',
                'value' => '50',
                'default_value' => '50',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:0|max:100',
                'display_order' => 58,
            ],
            [
                'category' => 'payroll',
                'key' => 'monthly_working_days',
                'label' => 'Dias Úteis Mensais (Fallback)',
                'description' => 'Dias úteis mensais — fallback se contagem dinâmica retornar 0',
                'value' => '22',
                'default_value' => '22',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:20|max:26',
                'display_order' => 59,
            ],
            [
                'category' => 'payroll',
                'key' => 'daily_work_hours',
                'label' => 'Horas Trabalho por Dia (Overtime)',
                'description' => 'Horas de trabalho diárias para cálculo de horas extras',
                'value' => '8',
                'default_value' => '8',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:6|max:12',
                'display_order' => 60,
            ],
            [
                'category' => 'general',
                'key' => 'probation_period_days',
                'label' => 'Período de Experiência (dias)',
                'description' => 'Duração do período de experiência para novos funcionários',
                'value' => '90',
                'default_value' => '90',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:30|max:180',
                'display_order' => 60,
            ],
            [
                'category' => 'general',
                'key' => 'termination_notice_days',
                'label' => 'Aviso Prévio (dias)',
                'description' => 'Dias de aviso prévio para rescisão de contrato',
                'value' => '30',
                'default_value' => '30',
                'value_type' => 'integer',
                'validation_rules' => 'required|integer|min:15|max:60',
                'display_order' => 61,
            ],
        ];
    }
}
