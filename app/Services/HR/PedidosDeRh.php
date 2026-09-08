<?php

namespace App\Services\Hr;

use App\Models\HR\Employee;
use App\Models\HR\Leave;
use App\Models\HR\Overtime;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\SalaryDiscount;
use App\Models\HR\Vacation;
use App\Services\HR\HRSettingsService;
use App\Services\HR\LeaveService;
use App\Services\HR\OvertimeService;
use App\Services\HR\SalaryAdvanceService;
use App\Services\HR\VacationService;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * OS SEIS PEDIDOS DO RH, descritos num sítio só.
 *
 * Férias, licenças, horas extras, turno nocturno, adiantamentos e descontos
 * salariais têm todos a MESMA FORMA: alguém pede, alguém aprova ou recusa
 * com um motivo, e depois paga-se ou desconta-se. Em Livewire eram seis
 * componentes com 2.210 linhas, e as mesmas quatro operações escritas seis
 * vezes — corrigir a rejeição num deixava as outras cinco por corrigir.
 *
 * O QUE CADA ESQUEMA DIZ:
 *
 *  · `modelo`, `numero`  — a tabela e a coluna do número do pedido.
 *  · `campos` / `regras` — o formulário e a validação, as MESMAS do Livewire.
 *  · `criar`             — quem cria de facto. NÃO É AQUI: é o serviço próprio
 *                          (`VacationService`, `OvertimeService`…), que é onde
 *                          vivem as contas do direito a férias, do
 *                          multiplicador da hora extra e do tecto do
 *                          adiantamento. Reescrevê-las aqui era ter duas.
 *  · `estados`           — os que ESTA tabela tem mesmo, com cor e rótulo.
 *  · `accoes`            — aprovar, rejeitar, pagar, cancelar, eliminar.
 *  · `ao_aprovar`        — o que mais muda ao aprovar. Só o adiantamento tem:
 *                          o valor aprovado pode ser menor do que o pedido, e
 *                          é dele que sai a prestação e o saldo.
 *  · `resumo`            — os números do topo, contados no servidor.
 *
 * A GUARDA É POR VERBO e por tipo: aprovar férias e aprovar um adiantamento
 * são decisões diferentes, e quem pode uma pode não poder a outra.
 */
final class PedidosDeRh
{
    /** @return array<string, array<string, mixed>> */
    public static function todos(): array
    {
        return [
            'ferias' => self::ferias(),
            'licencas' => self::licencas(),
            'horas-extras' => self::horasExtras(),
            'turno-nocturno' => self::turnoNocturno(),
            'adiantamentos' => self::adiantamentos(),
            'descontos' => self::descontos(),
        ];
    }

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::todos());
    }

    /** @return array<string, mixed> */
    public static function um(string $slug): array
    {
        $todos = self::todos();

        if (! isset($todos[$slug])) {
            throw new InvalidArgumentException("Pedido de RH desconhecido: {$slug}");
        }

        return $todos[$slug];
    }

    /* ─── Os esquemas ──────────────────────────────────────────────────── */

    private static function ferias(): array
    {
        return [
            'modelo' => Vacation::class,
            'titulo' => 'Férias',
            'singular' => 'Pedido de férias',
            'novo' => 'Novo Pedido',
            'icone' => 'fa-umbrella-beach',
            'cor' => 'aviso',
            'descricao' => 'Pedidos de férias, o direito de cada um e o subsídio',
            'rota' => '/hr/vacations',
            'numero' => 'vacation_number',
            'data' => 'start_date',
            'permissoes' => self::porVerbo('hr.vacations'),
            'pdf' => '/hr/vacations/:id/pdf',
            'pesquisa' => ['vacation_number', 'notes'],
            'campos' => [
                self::campo('employee_id', 'Funcionário', 'funcionario', obrigatorio: true),
                self::campo('reference_year', 'Ano de referência', 'numero', obrigatorio: true, omissao: null,
                    ajuda: 'O ano a que estas férias dizem respeito — é dele que sai o direito.'),
                self::campo('vacation_type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'normal', opcoes: [
                    ['valor' => 'normal', 'rotulo' => 'Normais'],
                    ['valor' => 'accumulated', 'rotulo' => 'Acumuladas'],
                    ['valor' => 'advance', 'rotulo' => 'Antecipadas'],
                    ['valor' => 'collective', 'rotulo' => 'Colectivas'],
                ]),
                self::campo('start_date', 'Início', 'data', obrigatorio: true),
                self::campo('end_date', 'Fim', 'data', obrigatorio: true),
                self::campo('replacement_employee_id', 'Substituto', 'funcionario',
                    ajuda: 'Quem fica no lugar durante a ausência.'),
                self::campo('is_collective', 'Férias colectivas', 'booleano', omissao: false),
                self::campo('collective_group', 'Grupo colectivo', 'texto'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'employee_id' => ['required', 'integer'],
                'reference_year' => ['required', 'integer', 'min:2020', 'max:2050'],
                'vacation_type' => ['required', 'in:normal,accumulated,advance,collective'],
                // O `after:today` do ecrã de sempre: férias marcam-se antes de
                // se gozarem, não depois.
                'start_date' => ['required', 'date', 'after:today'],
                'end_date' => ['required', 'date', 'after:start_date'],
                'replacement_employee_id' => ['nullable', 'integer'],
                'is_collective' => ['nullable', 'boolean'],
                'collective_group' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            'anexo' => ['coluna' => 'attachment_path', 'rotulo' => 'Anexo'],
            // Quem está fora, e quando: é a pergunta que se faz antes de
            // aprovar mais um pedido para a mesma semana.
            'calendario' => ['de' => 'start_date', 'ate' => 'end_date'],
            'criar' => fn (array $d) => app(VacationService::class)->createVacationRequest($d),
            'estados' => [
                ['valor' => 'pending', 'rotulo' => 'Pendente', 'cor' => 'aviso'],
                ['valor' => 'approved', 'rotulo' => 'Aprovado', 'cor' => 'bom'],
                ['valor' => 'in_progress', 'rotulo' => 'A decorrer', 'cor' => 'primaria'],
                ['valor' => 'completed', 'rotulo' => 'Concluído', 'cor' => 'neutra'],
                ['valor' => 'rejected', 'rotulo' => 'Recusado', 'cor' => 'perigo'],
                ['valor' => 'cancelled', 'rotulo' => 'Anulado', 'cor' => 'neutra'],
            ],
            'accoes' => ['aprovar' => true, 'rejeitar' => true, 'pagar' => true, 'cancelar' => true, 'apagar' => true],
            // Umas férias já a decorrer também se pagam: o pagamento faz-se
            // muitas vezes com a pessoa já fora.
            'pagar_a_partir_de' => ['approved', 'in_progress'],
            'valor' => ['coluna' => 'total_amount', 'rotulo' => 'Total'],
            'colunas' => [
                ['chave' => 'working_days', 'rotulo' => 'Dias úteis', 'formato' => 'numero'],
                ['chave' => 'start_date', 'rotulo' => 'Início', 'formato' => 'data'],
                ['chave' => 'end_date', 'rotulo' => 'Fim', 'formato' => 'data'],
            ],
        ];
    }

    private static function licencas(): array
    {
        return [
            'modelo' => Leave::class,
            'titulo' => 'Licenças e Faltas',
            'singular' => 'Licença',
            'novo' => 'Nova Licença',
            'icone' => 'fa-calendar-times',
            'cor' => 'laranja',
            'descricao' => 'Doença, maternidade, luto e as outras ausências justificadas',
            'rota' => '/hr/leaves',
            'numero' => 'leave_number',
            'data' => 'start_date',
            'permissoes' => self::porVerbo('hr.leaves'),
            'pdf' => '/hr/leaves/:id/pdf',
            'pesquisa' => ['leave_number', 'reason'],
            'campos' => [
                self::campo('employee_id', 'Funcionário', 'funcionario', obrigatorio: true),
                self::campo('leave_type', 'Tipo de licença', 'escolha', obrigatorio: true, omissao: 'justified', opcoes: [
                    ['valor' => 'sick', 'rotulo' => 'Doença'],
                    ['valor' => 'maternity', 'rotulo' => 'Maternidade'],
                    ['valor' => 'paternity', 'rotulo' => 'Paternidade'],
                    ['valor' => 'bereavement', 'rotulo' => 'Luto'],
                    ['valor' => 'marriage', 'rotulo' => 'Casamento'],
                    ['valor' => 'study', 'rotulo' => 'Estudo'],
                    ['valor' => 'unpaid', 'rotulo' => 'Sem vencimento'],
                    ['valor' => 'justified', 'rotulo' => 'Justificada'],
                    ['valor' => 'other', 'rotulo' => 'Outra'],
                ]),
                self::campo('start_date', 'Início', 'data', obrigatorio: true),
                self::campo('end_date', 'Fim', 'data', obrigatorio: true),
                self::campo('has_medical_certificate', 'Tem atestado médico', 'booleano', omissao: false),
                self::campo('reason', 'Motivo', 'textarea', obrigatorio: true, largura: 'inteira',
                    ajuda: 'Pelo menos dez caracteres — é o que fica escrito no processo.'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'employee_id' => ['required', 'integer'],
                'leave_type' => ['required', 'in:sick,maternity,paternity,bereavement,marriage,study,unpaid,justified,other'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'has_medical_certificate' => ['nullable', 'boolean'],
                'reason' => ['required', 'string', 'min:10', 'max:500'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            'anexo' => ['coluna' => 'document_path', 'rotulo' => 'Atestado ou justificativo'],
            'calendario' => ['de' => 'start_date', 'ate' => 'end_date'],
            'criar' => fn (array $d) => app(LeaveService::class)->createLeaveRequest($d),
            'estados' => [
                ['valor' => 'pending', 'rotulo' => 'Pendente', 'cor' => 'aviso'],
                ['valor' => 'approved', 'rotulo' => 'Aprovada', 'cor' => 'bom'],
                ['valor' => 'rejected', 'rotulo' => 'Recusada', 'cor' => 'perigo'],
                ['valor' => 'cancelled', 'rotulo' => 'Anulada', 'cor' => 'neutra'],
            ],
            'accoes' => ['aprovar' => true, 'rejeitar' => true, 'pagar' => false, 'cancelar' => true, 'apagar' => true],
            'valor' => ['coluna' => 'deduction_amount', 'rotulo' => 'Desconto'],
            'colunas' => [
                ['chave' => 'leave_type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'working_days', 'rotulo' => 'Dias úteis', 'formato' => 'numero'],
                ['chave' => 'start_date', 'rotulo' => 'Início', 'formato' => 'data'],
                ['chave' => 'end_date', 'rotulo' => 'Fim', 'formato' => 'data'],
            ],
        ];
    }

    private static function horasExtras(): array
    {
        return [
            'modelo' => Overtime::class,
            'titulo' => 'Horas Extras',
            'singular' => 'Hora extra',
            'novo' => 'Lançar Horas',
            'icone' => 'fa-business-time',
            'cor' => 'rosa',
            'descricao' => 'As horas a mais, com o multiplicador que a lei manda',
            'rota' => '/hr/overtime',
            'numero' => 'overtime_number',
            'data' => 'date',
            'permissoes' => self::porVerbo('hr.overtime'),
            'pdf' => '/hr/overtime/:id/pdf',
            'pesquisa' => ['overtime_number', 'description'],
            /* Só as que NÃO são do turno nocturno: esse tem ecrã próprio, e a
               mesma tabela serve os dois. */
            'onde' => fn ($q) => $q->where('is_night_shift', false),
            'campos' => [
                self::campo('employee_id', 'Funcionário', 'funcionario', obrigatorio: true),
                self::campo('date', 'Data', 'data', obrigatorio: true),
                self::campo('input_type', 'Como se lança', 'escolha', obrigatorio: true, omissao: 'time_range', opcoes: [
                    ['valor' => 'time_range', 'rotulo' => 'Das … às …'],
                    ['valor' => 'daily', 'rotulo' => 'Horas neste dia'],
                    ['valor' => 'monthly', 'rotulo' => 'Horas no mês'],
                ], ajuda: 'Das … às … calcula sozinho; as outras duas recebem o número de horas.'),
                self::campo('start_time', 'Das', 'hora'),
                self::campo('end_time', 'Às', 'hora'),
                self::campo('direct_hours', 'Horas', 'numero', passo: 0.25),
                self::campo('overtime_type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'weekday', opcoes: [
                    ['valor' => 'weekday', 'rotulo' => 'Dia útil'],
                    ['valor' => 'weekend', 'rotulo' => 'Fim-de-semana'],
                    ['valor' => 'holiday', 'rotulo' => 'Feriado'],
                    ['valor' => 'night', 'rotulo' => 'Nocturna'],
                ], ajuda: 'É o que escolhe o multiplicador: 1,25× no dia útil, 2× ao fim-de-semana, 2,5× no feriado.'),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'employee_id' => ['required', 'integer'],
                'date' => ['required', 'date'],
                'input_type' => ['required', 'in:time_range,daily,monthly'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'end_time' => ['nullable', 'date_format:H:i'],
                'direct_hours' => ['nullable', 'numeric', 'min:0', 'max:400'],
                'overtime_type' => ['required', 'in:weekday,weekend,holiday,night'],
                'description' => ['nullable', 'string', 'max:500'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            'criar' => fn (array $d) => app(OvertimeService::class)->createOvertimeRecord($d),
            'estados' => [
                ['valor' => 'pending', 'rotulo' => 'Pendente', 'cor' => 'aviso'],
                ['valor' => 'approved', 'rotulo' => 'Aprovada', 'cor' => 'bom'],
                ['valor' => 'paid', 'rotulo' => 'Paga', 'cor' => 'primaria'],
                ['valor' => 'rejected', 'rotulo' => 'Recusada', 'cor' => 'perigo'],
                ['valor' => 'cancelled', 'rotulo' => 'Anulada', 'cor' => 'neutra'],
            ],
            'accoes' => ['aprovar' => true, 'rejeitar' => true, 'pagar' => true, 'cancelar' => true, 'apagar' => true],
            'valor' => ['coluna' => 'total_amount', 'rotulo' => 'Valor'],
            'colunas' => [
                ['chave' => 'date', 'rotulo' => 'Data', 'formato' => 'data'],
                ['chave' => 'total_hours', 'rotulo' => 'Horas', 'formato' => 'numero'],
                ['chave' => 'overtime_type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'multiplier', 'rotulo' => 'Mult.', 'formato' => 'numero'],
            ],
        ];
    }

    private static function turnoNocturno(): array
    {
        return [
            'modelo' => Overtime::class,
            'titulo' => 'Turno Nocturno',
            'singular' => 'Turno nocturno',
            'novo' => 'Lançar Noites',
            'icone' => 'fa-moon',
            'cor' => 'roxo',
            'descricao' => 'O acréscimo de 25% das horas cumpridas entre as 22:00 e as 06:00',
            'rota' => '/hr/overtime-night-shift',
            'numero' => 'overtime_number',
            'data' => 'date',
            'permissoes' => self::porVerbo('hr.overtime'),
            'pdf' => '/hr/overtime/:id/pdf',
            'pesquisa' => ['overtime_number', 'description'],
            'onde' => fn ($q) => $q->where('is_night_shift', true),
            'campos' => [
                self::campo('employee_id', 'Funcionário', 'funcionario', obrigatorio: true),
                self::campo('date', 'Mês de referência', 'data', obrigatorio: true,
                    ajuda: 'Qualquer dia do mês a que as noites dizem respeito.'),
                self::campo('night_days', 'Noites', 'numero', obrigatorio: true, omissao: 1, min: 1, max: 31,
                    ajuda: 'Quantas noites foram cumpridas neste mês.'),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'employee_id' => ['required', 'integer'],
                'date' => ['required', 'date'],
                'night_days' => ['required', 'integer', 'min:1', 'max:31'],
                'description' => ['nullable', 'string', 'max:500'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            /*
             * NÃO É UMA HORA EXTRA, e por isso não passa pelo
             * `createOvertimeRecord`: o que a lei manda pagar a quem cumpre a
             * noite é 25% sobre o valor do DIA, contado por NOITES cumpridas
             * no mês. A conta estava dentro do componente Livewire e mudou-se
             * para o `OvertimeService`, onde a API e os ensaios lhe chegam.
             */
            'criar' => fn (array $d) => app(OvertimeService::class)->createNightShiftRecord($d),
            'estados' => [
                ['valor' => 'pending', 'rotulo' => 'Pendente', 'cor' => 'aviso'],
                ['valor' => 'approved', 'rotulo' => 'Aprovado', 'cor' => 'bom'],
                ['valor' => 'paid', 'rotulo' => 'Pago', 'cor' => 'primaria'],
                ['valor' => 'rejected', 'rotulo' => 'Recusado', 'cor' => 'perigo'],
                ['valor' => 'cancelled', 'rotulo' => 'Anulado', 'cor' => 'neutra'],
            ],
            'accoes' => ['aprovar' => true, 'rejeitar' => true, 'pagar' => true, 'cancelar' => true, 'apagar' => true],
            'valor' => ['coluna' => 'total_amount', 'rotulo' => 'Valor'],
            'colunas' => [
                ['chave' => 'date', 'rotulo' => 'Mês', 'formato' => 'data'],
                ['chave' => 'total_hours', 'rotulo' => 'Horas', 'formato' => 'numero'],
            ],
        ];
    }

    private static function adiantamentos(): array
    {
        return [
            'modelo' => SalaryAdvance::class,
            'titulo' => 'Adiantamentos',
            'singular' => 'Adiantamento',
            'novo' => 'Novo Adiantamento',
            'icone' => 'fa-hand-holding-dollar',
            'cor' => 'ciano',
            'descricao' => 'Salário adiantado, com tecto e prestações',
            'rota' => '/hr/advances',
            'numero' => 'advance_number',
            'data' => 'request_date',
            'permissoes' => self::porVerbo('hr.advances'),
            'pdf' => '/hr/advances/:id/pdf',
            'pesquisa' => ['advance_number', 'reason'],
            'campos' => [
                self::campo('employee_id', 'Funcionário', 'funcionario', obrigatorio: true),
                self::campo('requested_amount', 'Valor pedido (Kz)', 'dinheiro', obrigatorio: true),
                self::campo('installments', 'Prestações', 'numero', obrigatorio: true, omissao: 1, min: 1, max: 12,
                    ajuda: 'Em quantos meses é descontado ao salário.'),
                self::campo('reason', 'Motivo', 'textarea', obrigatorio: true, largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'employee_id' => ['required', 'integer'],
                'requested_amount' => ['required', 'numeric', 'min:1'],
                'installments' => ['required', 'integer', 'min:1', 'max:12'],
                'reason' => ['required', 'string', 'min:10', 'max:500'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            'criar' => fn (array $d) => app(SalaryAdvanceService::class)->createAdvanceRequest($d),
            /*
             * APROVAR UM ADIANTAMENTO É DECIDIR QUANTO.
             *
             * Só este pedido é que se aprova por um valor DIFERENTE do pedido:
             * pede-se 200.000 e aprova-se 120.000. É desse valor que sai o
             * saldo e a prestação, e é por isso que a aprovação tem
             * formulário próprio.
             */
            'ao_aprovar' => [
                'campos' => [
                    self::campo('approved_amount', 'Valor aprovado (Kz)', 'dinheiro', obrigatorio: true),
                    self::campo('installment_amount', 'Prestação mensal (Kz)', 'dinheiro', obrigatorio: true),
                ],
                'regras' => [
                    'approved_amount' => ['required', 'numeric', 'min:1'],
                    'installment_amount' => ['required', 'numeric', 'min:1'],
                ],
                'aplicar' => fn (Model $m, array $d) => [
                    'approved_amount' => $d['approved_amount'],
                    'installment_amount' => $d['installment_amount'],
                    'balance' => $d['approved_amount'],
                ],
            ],
            'estados' => [
                ['valor' => 'pending', 'rotulo' => 'Pendente', 'cor' => 'aviso'],
                ['valor' => 'approved', 'rotulo' => 'Aprovado', 'cor' => 'bom'],
                ['valor' => 'paid', 'rotulo' => 'Pago', 'cor' => 'primaria'],
                ['valor' => 'in_deduction', 'rotulo' => 'Em desconto', 'cor' => 'primaria'],
                ['valor' => 'completed', 'rotulo' => 'Liquidado', 'cor' => 'neutra'],
                ['valor' => 'rejected', 'rotulo' => 'Recusado', 'cor' => 'perigo'],
                ['valor' => 'cancelled', 'rotulo' => 'Anulado', 'cor' => 'neutra'],
            ],
            'accoes' => ['aprovar' => true, 'rejeitar' => true, 'pagar' => true, 'cancelar' => true, 'apagar' => true],
            'valor' => ['coluna' => 'approved_amount', 'rotulo' => 'Aprovado'],
            'colunas' => [
                ['chave' => 'requested_amount', 'rotulo' => 'Pedido', 'formato' => 'dinheiro'],
                ['chave' => 'installments', 'rotulo' => 'Prestações', 'formato' => 'numero'],
                ['chave' => 'balance', 'rotulo' => 'Por descontar', 'formato' => 'dinheiro'],
            ],
        ];
    }

    private static function descontos(): array
    {
        return [
            'modelo' => SalaryDiscount::class,
            'titulo' => 'Descontos Salariais',
            'singular' => 'Desconto',
            'novo' => 'Novo Desconto',
            'icone' => 'fa-percent',
            'cor' => 'perigo',
            'descricao' => 'Descontos acordados, em prestações',
            'rota' => '/hr/salary-discounts',
            'numero' => 'id',
            'data' => 'request_date',
            'permissoes' => self::porVerbo('hr.discounts'),
            'pdf' => '/hr/salary-discounts/:id/pdf',
            'pesquisa' => ['discount_type', 'reason'],
            'campos' => [
                self::campo('employee_id', 'Funcionário', 'funcionario', obrigatorio: true),
                self::campo('discount_type', 'Tipo de desconto', 'escolha', obrigatorio: true, opcoes: [
                    ['valor' => 'dano', 'rotulo' => 'Dano ou perda'],
                    ['valor' => 'emprestimo', 'rotulo' => 'Empréstimo'],
                    ['valor' => 'material', 'rotulo' => 'Material adiantado'],
                    ['valor' => 'multa', 'rotulo' => 'Multa'],
                    ['valor' => 'outro', 'rotulo' => 'Outro'],
                ]),
                self::campo('request_date', 'Data', 'data', obrigatorio: true),
                self::campo('amount', 'Valor (Kz)', 'dinheiro', obrigatorio: true),
                self::campo('installments', 'Prestações', 'numero', obrigatorio: true, omissao: 1, min: 1, max: 24),
                self::campo('reason', 'Motivo', 'textarea', obrigatorio: true, largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'employee_id' => ['required', 'integer'],
                'discount_type' => ['required', 'string', 'max:255'],
                'request_date' => ['required', 'date'],
                'amount' => ['required', 'numeric', 'min:1'],
                'installments' => ['required', 'integer', 'min:1', 'max:24'],
                'reason' => ['required', 'string', 'min:10', 'max:500'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            'anexo' => ['coluna' => 'signed_document', 'rotulo' => 'Documento assinado'],
            /*
             * O DESCONTO NÃO TEM SERVIÇO PRÓPRIO: a única conta é a prestação,
             * e é uma divisão. Faz-se aqui, e não num serviço de três linhas.
             */
            'criar' => function (array $d) {
                $prestacoes = max(1, (int) $d['installments']);

                return SalaryDiscount::create(array_merge($d, [
                    'installment_amount' => round($d['amount'] / $prestacoes, 2),
                    'remaining_installments' => $prestacoes,
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                ]));
            },
            'estados' => [
                ['valor' => 'pending', 'rotulo' => 'Pendente', 'cor' => 'aviso'],
                ['valor' => 'approved', 'rotulo' => 'Aprovado', 'cor' => 'bom'],
                ['valor' => 'completed', 'rotulo' => 'Liquidado', 'cor' => 'neutra'],
                ['valor' => 'rejected', 'rotulo' => 'Recusado', 'cor' => 'perigo'],
            ],
            'accoes' => ['aprovar' => true, 'rejeitar' => true, 'pagar' => false, 'cancelar' => false, 'apagar' => true],
            'valor' => ['coluna' => 'amount', 'rotulo' => 'Valor'],
            'colunas' => [
                ['chave' => 'discount_type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'request_date', 'rotulo' => 'Data', 'formato' => 'data'],
                ['chave' => 'installments', 'rotulo' => 'Prestações', 'formato' => 'numero'],
                ['chave' => 'remaining_installments', 'rotulo' => 'Por descontar', 'formato' => 'numero'],
            ],
        ];
    }

    /* ─── Peças ────────────────────────────────────────────────────────── */

    private static function porVerbo(string $prefixo): array
    {
        return [
            'ver' => "$prefixo.view",
            'criar' => "$prefixo.create",
            'aprovar' => "$prefixo.approve",
            'apagar' => "$prefixo.delete",
        ];
    }

    /** @return array<string, mixed> */
    private static function campo(
        string $chave, string $rotulo, string $tipo, bool $obrigatorio = false, mixed $omissao = null,
        ?array $opcoes = null, ?string $ajuda = null, string $largura = 'meia',
        ?float $passo = null, ?float $min = null, ?float $max = null,
    ): array {
        return array_filter([
            'chave' => $chave, 'rotulo' => __($rotulo), 'tipo' => $tipo, 'obrigatorio' => $obrigatorio,
            'omissao' => $omissao, 'largura' => $largura, 'opcoes' => $opcoes,
            'ajuda' => $ajuda ? __($ajuda) : null, 'passo' => $passo, 'min' => $min, 'max' => $max,
        ], fn ($v) => $v !== null);
    }

    /**
     * O NOME DE QUEM PEDIU, para a lista e para a ficha.
     *
     * Vem daqui e não de um `with()` no controlador porque o funcionário pode
     * ter sido removido (soft delete) desde que o pedido foi feito, e o
     * pedido continua a ter de se ler.
     */
    public static function nomeDoFuncionario(?Employee $e): string
    {
        return $e ? trim($e->first_name . ' ' . $e->last_name) : __('Funcionário removido');
    }
}
