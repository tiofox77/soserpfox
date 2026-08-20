<?php

namespace App\Console\Commands;

use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * O que se passa com o salário de um funcionário.
 *
 * Só LÊ. Existe porque a pergunta "porque é que este funcionário sai assim na
 * folha?" só se conseguia responder abrindo a base de dados de produção à mão,
 * e a resposta quase nunca é um bug: é um campo por preencher, um contrato sem
 * salário, ou o funcionário estar `inactive` quando a folha foi gerada.
 *
 * Mostra, por esta ordem, tudo o que decide o valor que sai na folha:
 * o estado, o salário base (do contrato ou do funcionário), os subsídios,
 * e a linha que lhe saiu em cada folha.
 */
class RhDiagnosticoFuncionario extends Command
{
    protected $signature = 'rh:diagnostico
        {--tenant= : id da empresa}
        {--nome= : parte do nome do funcionário}
        {--folhas=6 : quantas folhas mostrar}';

    protected $description = 'Mostra o que decide o salário de um funcionário (só lê)';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $nome = trim((string) $this->option('nome'));

        if (!$tenantId || $nome === '') {
            $this->error('Indique --tenant=<id> e --nome=<parte do nome>.');

            return self::FAILURE;
        }

        $empresa = Tenant::find($tenantId);

        if (!$empresa) {
            $this->error("Não existe a empresa #{$tenantId}.");

            return self::FAILURE;
        }

        $this->line("Empresa: <info>{$empresa->name}</info> (#{$empresa->id})");

        $funcionarios = Employee::where('tenant_id', $tenantId)
            // `hr_employees` só tem `full_name` — não há coluna `name`.
            ->where('full_name', 'like', "%{$nome}%")
            ->get();

        if ($funcionarios->isEmpty()) {
            $this->warn("Nenhum funcionário com '{$nome}' nesta empresa.");
            $this->line('Existem ' . Employee::where('tenant_id', $tenantId)->count() . ' funcionário(s) no total.');

            return self::SUCCESS;
        }

        foreach ($funcionarios as $f) {
            $this->mostrar($f);
        }

        return self::SUCCESS;
    }

    private function mostrar(Employee $f): void
    {
        $this->newLine();
        $this->line(str_repeat('=', 66));
        $this->line("  #{$f->id}  " . ($f->full_name ?? '(sem nome)'));
        $this->line(str_repeat('=', 66));

        $contrato = $f->activeContract;

        // O salário que a folha usa, pela MESMA ordem do PayrollService.
        $salarioContrato = $contrato->base_salary ?? null;
        $salarioBaseFunc = $f->base_salary ?? null;
        $salarioFunc     = $f->salary ?? null;
        $usado = (float) ($salarioContrato ?? $salarioBaseFunc ?? $salarioFunc ?? 0);

        $this->table(['campo', 'valor', 'nota'], [
            ['estado', $f->status ?? '—',
                $f->status === 'active' ? 'entra na folha' : '<comment>NÃO entra: a folha só apanha os activos</comment>'],
            ['contrato activo', $contrato ? "#{$contrato->id}" : '<comment>nenhum</comment>',
                $contrato ? '' : 'sem contrato, usa-se o salário do funcionário'],
            ['contrato → salário base', $this->kz($salarioContrato), 'é o primeiro a ser usado'],
            ['funcionário → base_salary', $this->kz($salarioBaseFunc), 'usado se o contrato não tiver'],
            ['funcionário → salary', $this->kz($salarioFunc), 'último recurso'],
            ['<info>SALÁRIO QUE A FOLHA USA</info>', '<info>' . $this->kz($usado) . '</info>',
                $usado > 0 ? '' : '<comment>ZERO — é isto que faz sair uma folha vazia</comment>'],
            ['subsídio alimentação', $this->kz($f->food_benefit), 'se 0, usa-se o global das definições'],
            ['subsídio transporte', $this->kz($f->transport_benefit), 'idem'],
            ['bónus', $this->kz($f->bonus), ''],
            ['abono de família', $this->kz($f->family_allowance), ''],
            ['data de admissão', optional($f->hire_date)->format('d/m/Y') ?? '—', ''],
        ]);

        if ($usado <= 0) {
            $this->newLine();
            $this->error('Este funcionário não tem salário definido em lado nenhum.');
            $this->line('  A folha vai processá-lo com base 0 — e o recibo sai a zeros.');
            $this->line('  Preencher em RH → Funcionários → editar, ou criar-lhe um contrato.');
        }

        if (($f->status ?? '') !== 'active') {
            $this->newLine();
            $this->error("Estado '{$f->status}': a folha NÃO o apanha.");
            $this->line('  O PayrollService só processa quem está `active`.');
        }

        // As folhas onde ele aparece.
        $linhas = PayrollItem::where('employee_id', $f->id)
            ->with('payroll')
            ->latest('id')
            ->limit((int) $this->option('folhas'))
            ->get();

        $this->newLine();

        if ($linhas->isEmpty()) {
            $this->warn('Não aparece em nenhuma folha.');

            $folhas = Payroll::where('tenant_id', $f->tenant_id)->count();
            $this->line("  A empresa tem {$folhas} folha(s). Se foram geradas ANTES de este");
            $this->line('  funcionário ser criado — ou enquanto ele não estava activo — ele não');
            $this->line('  entra nelas. Uma folha não se actualiza sozinha: é preciso eliminá-la');
            $this->line('  e voltar a gerar.');

            return;
        }

        // A assiduidade é o que mais estraga uma folha, e não se vê em lado
        // nenhum: o salário é proporcional aos dias pagos, e um dia útil sem
        // marcação conta como FALTA.
        $this->assiduidade($f, $linhas->first()?->payroll);

        $this->line('Linhas nas folhas:');
        $this->table(
            ['folha', 'período', 'estado', 'base', 'bruto', 'INSS', 'IRT', 'líquido'],
            $linhas->map(fn ($l) => [
                $l->payroll->payroll_number ?? '—',
                ($l->payroll->month ?? '?') . '/' . ($l->payroll->year ?? '?'),
                $l->payroll->status ?? '—',
                $this->kz($l->base_salary),
                $this->kz($l->gross_salary),
                $this->kz($l->inss_employee ?? $l->inss),
                $this->kz($l->irt),
                $this->kz($l->net_salary),
            ])->all()
        );
    }

    /**
     * Quantos dias foram marcados, e o que isso custa.
     *
     * `assume_present_without_attendance` (por omissão LIGADO) salva as
     * empresas que não usam o módulo de Assiduidade: sem NENHUM registo no
     * período, assume-se presença total.
     *
     * Mas é tudo-ou-nada. Basta UMA marcação no mês para a rede desaparecer, e
     * a partir daí todos os dias úteis sem marcação passam a faltas
     * descontadas. Duas marcações de teste no início do mês cortam o salário
     * em ~90% — sem erro nenhum no ecrã.
     */
    private function assiduidade(Employee $f, $folha): void
    {
        if (!$folha) {
            return;
        }

        $inicio = \Carbon\Carbon::parse($folha->period_start);
        $fim = \Carbon\Carbon::parse($folha->period_end);

        $registos = \App\Models\HR\Attendance::where('employee_id', $f->id)
            ->whereBetween('date', [$inicio->toDateString(), $fim->toDateString()])
            ->get();

        // Dias úteis do período (sábados fora, como o PayrollService por omissão).
        $uteis = 0;
        $c = $inicio->copy();
        while ($c->lte($fim)) {
            if ($c->isWeekday()) {
                $uteis++;
            }
            $c->addDay();
        }

        $presentes = $registos->where('status', 'present')->count();
        $rede = (bool) \App\Models\HR\HRSetting::get('assume_present_without_attendance', true);

        $this->line('Assiduidade em ' . $inicio->format('m/Y') . ':');
        $this->table(['', 'valor'], [
            ['dias úteis no período', $uteis],
            ['registos de assiduidade', $registos->count()],
            ['marcados como presente', $presentes],
            ['rede "assumir presença"', $rede ? 'LIGADA' : 'desligada'],
            ['<info>a rede aplica-se?</info>',
                $registos->isEmpty() && $rede
                    ? '<info>SIM — assume-se presença total</info>'
                    : '<comment>NÃO — há registos, os dias sem marcação contam como FALTA</comment>'],
        ]);

        if ($registos->isNotEmpty() && $presentes < $uteis) {
            $faltas = $uteis - $presentes;
            $this->newLine();
            $this->error("{$faltas} dia(s) útil(eis) sem marcação = {$faltas} falta(s) descontada(s).");
            $this->line('  A rede de segurança é tudo-ou-nada: basta UMA marcação no mês');
            $this->line('  para ela desaparecer. Se a empresa não usa Assiduidade a sério,');
            $this->line('  a saída é apagar os registos do período OU marcar os dias todos.');
        }
    }

    private function kz($v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return number_format((float) $v, 2, ',', '.');
    }
}
