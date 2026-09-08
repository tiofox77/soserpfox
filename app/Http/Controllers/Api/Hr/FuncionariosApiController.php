<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Position;
use App\Models\HR\Shift;
use App\Models\Treasury\Bank;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A FICHA DO FUNCIONÁRIO, para o ecrã em React.
 *
 * O ecrã mais denso do RH: 74 colunas, sete documentos com validade e uma
 * fotografia. O que ele guarda é o que a empresa tem de saber de cada pessoa
 * — e é a origem do que a folha de pagamento depois calcula.
 *
 * TRÊS DECISÕES QUE VIVEM AQUI:
 *
 * 1. AS COLUNAS A DOBRAR ESCREVEM-SE AS DUAS. A tabela tem quatro pares para a
 *    mesma coisa (`salary`/`base_salary`, `status`/`employment_status`,
 *    `transport_allowance`/`transport_benefit`,
 *    `meal_allowance`/`food_benefit`): o formulário em Blade gravava numas e
 *    partes do cálculo liam as outras, e um funcionário editado ficava com o
 *    salário novo numa coluna e o antigo na outra — a folha do mês saía pelo
 *    antigo, calada. Aqui grava-se o mesmo valor nas duas, e a partir de
 *    agora não divergem. O passado corrige-se com
 *    `rh:alinhar-colunas-do-funcionario --aplicar`.
 *
 * 2. O NÚMERO É DO MODELO. `HasTenantNumber` numera por empresa, à prova de
 *    eliminações e de concorrência. Não se escreve à mão.
 *
 * 3. OS DOCUMENTOS SÃO UMA PORTA À PARTE. Um ficheiro não viaja em JSON, e o
 *    formulário não pode ficar refém do upload: grava-se a ficha, e cada
 *    documento sobe depois, um a um, pela sua porta.
 */
class FuncionariosApiController extends Controller
{
    /** Os nove documentos da ficha: a chave, e a coluna onde o caminho fica. */
    public const DOCUMENTOS = [
        'bi' => 'bi_document_path',
        'passport' => 'passport_document_path',
        'work_permit' => 'work_permit_document_path',
        'residence_permit' => 'residence_permit_document_path',
        'driver_license' => 'driver_license_document_path',
        'health_insurance' => 'health_insurance_document_path',
        'contract' => 'contract_document_path',
        'probation' => 'probation_document_path',
        'criminal_record' => 'criminal_record_document_path',
    ];

    /**
     * OS PARES DE COLUNAS QUE DIZEM A MESMA COISA.
     *
     * Esquerda: a que o formulário conhece. Direita: a irmã, que partes do
     * cálculo lêem. Escrevem-se sempre as duas.
     */
    private const IRMAS = [
        'salary' => 'base_salary',
        'status' => 'employment_status',
        'transport_allowance' => 'transport_benefit',
        'meal_allowance' => 'food_benefit',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** O que o ecrã precisa de saber ao abrir: listas, escolhas e permissões. */
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'employees.view');

        $tenantId = activeTenantId();

        return response()->json([
            'departamentos' => Department::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['valor' => (string) $d->id, 'rotulo' => $d->name])->values(),

            'cargos' => Position::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('title')->get(['id', 'title', 'department_id'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->title, 'departamento' => $c->department_id])->values(),

            'turnos' => Shift::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('display_order')->get(['id', 'name', 'color'])
                ->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => $t->name, 'cor' => $t->color])->values(),

            /*
             * A CHEFIA é outro funcionário desta empresa — e não um
             * utilizador do sistema. Quem chefia pode nem ter acesso.
             */
            'chefias' => Employee::where('tenant_id', $tenantId)->where('status', 'active')
                ->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name'])
                ->map(fn ($e) => ['valor' => (string) $e->id, 'rotulo' => trim($e->first_name . ' ' . $e->last_name)])->values(),

            // Os bancos são partilhados por todas as empresas.
            'bancos' => Bank::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($b) => ['valor' => $b->name, 'rotulo' => $b->name])->values(),

            'generos' => [
                ['valor' => 'M', 'rotulo' => __('Masculino')],
                ['valor' => 'F', 'rotulo' => __('Feminino')],
                ['valor' => 'Outro', 'rotulo' => __('Outro')],
            ],
            'vinculos' => collect(['Contrato', 'Freelancer', 'Estágio', 'Temporário'])
                ->map(fn ($v) => ['valor' => $v, 'rotulo' => __($v)])->values(),
            'estados' => [
                ['valor' => 'active', 'rotulo' => __('Activo'), 'cor' => 'bom'],
                ['valor' => 'on_leave', 'rotulo' => __('De licença'), 'cor' => 'aviso'],
                ['valor' => 'suspended', 'rotulo' => __('Suspenso'), 'cor' => 'aviso'],
                ['valor' => 'terminated', 'rotulo' => __('Cessado'), 'cor' => 'perigo'],
            ],
            'categorias_de_carta' => collect(['A', 'B', 'C', 'D', 'E'])
                ->map(fn ($c) => ['valor' => $c, 'rotulo' => $c])->values(),

            'geografia' => [
                'provincias' => Geografia::provincias(),
                'municipios' => collect(Geografia::provincias())->mapWithKeys(fn ($p) => [$p => Geografia::municipios($p)]),
            ],

            'documentos' => collect(self::DOCUMENTOS)->keys()->map(fn ($k) => [
                'chave' => $k,
                'rotulo' => self::rotuloDoDocumento($k),
            ])->values(),

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('employees.create'),
                'pode_editar' => (bool) $request->user()?->can('employees.edit'),
                'pode_apagar' => (bool) $request->user()?->can('employees.delete'),
            ],
        ]);
    }

    /** A lista, com procura, filtros e as contagens do topo. */
    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'employees.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'integer'],
            'cargo' => ['nullable', 'integer'],
            'turno' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string', 'max:20'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $base = fn () => Employee::where('tenant_id', $tenantId);

        $q = $base()->with(['department:id,name', 'position:id,title'])
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(function ($sub) use ($p) {
                foreach (['first_name', 'last_name', 'employee_number', 'email', 'phone', 'nif'] as $coluna) {
                    $sub->orWhere($coluna, 'like', "%{$p}%");
                }
            }))
            ->when($filtros['departamento'] ?? null, fn ($q, $d) => $q->where('department_id', $d))
            ->when($filtros['cargo'] ?? null, fn ($q, $c) => $q->where('position_id', $c))
            ->when($filtros['turno'] ?? null, fn ($q, $t) => $q->where('shift_id', $t))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->latest('id');

        $pagina = $q->paginate($filtros['por_pagina'] ?? 15)->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Employee $e) => $this->linha($e))->values(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'from' => $pagina->firstItem(),
                'to' => $pagina->lastItem(),
            ],
            /*
             * AS CONTAGENS SÃO DE TODA A EMPRESA, não da página.
             *
             * «12 activos» tem de querer dizer doze activos, e não doze na
             * página que está à frente.
             */
            'resumo' => [
                'total' => $base()->count(),
                'activos' => $base()->where('status', 'active')->count(),
                'de_licenca' => $base()->where('status', 'on_leave')->count(),
                'cessados' => $base()->where('status', 'terminated')->count(),
                // Documentos a vencer nos próximos 60 dias: é o aviso do painel.
                'documentos_a_vencer' => $base()->where('status', 'active')
                    ->where(fn ($q) => $this->comDocumentoAVencer($q))->count(),
            ],
        ]);
    }

    /** A ficha inteira, para o formulário abrir. */
    public function abrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'employees.view');

        $e = Employee::where('tenant_id', activeTenantId())->findOrFail($id);

        return response()->json(['documento' => $this->ficha($e)]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'employees.create');

        $dados = $this->validar($request);

        // O NÚMERO É DO MODELO: por empresa, à prova de eliminações.
        $dados['employee_number'] = Employee::generateTenantNumber('employee_number', 'EMP-');
        $dados['tenant_id'] = activeTenantId();

        $e = Employee::create($dados);

        return response()->json([
            'documento' => $this->ficha($e),
            'message' => __('Funcionário :n criado.', ['n' => $e->employee_number]),
        ], 201);
    }

    public function actualizar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'employees.edit');

        $e = Employee::where('tenant_id', activeTenantId())->findOrFail($id);
        $e->update($this->validar($request, $e));

        return response()->json([
            'documento' => $this->ficha($e->fresh()),
            'message' => __('Funcionário :n actualizado.', ['n' => $e->employee_number]),
        ]);
    }

    /**
     * ELIMINAR — e é um `soft delete`, de propósito.
     *
     * A ficha sai da lista mas a linha fica: as folhas de pagamento antigas
     * apontam para ela, e apagá-la a sério deixava recibos já emitidos sem
     * saber de quem eram.
     */
    public function eliminar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'employees.delete');

        $e = Employee::where('tenant_id', activeTenantId())->findOrFail($id);
        $numero = $e->employee_number;

        $e->delete();

        return response()->json(['message' => __('Funcionário :n removido.', ['n' => $numero])]);
    }

    /* ─── Os documentos ────────────────────────────────────────────────── */

    /**
     * UM DOCUMENTO SOBE PELA SUA PORTA.
     *
     * Cada ficheiro é substituído no sítio: o antigo é apagado antes, senão
     * a pasta da pessoa enchia-se de versões que ninguém volta a ver.
     */
    public function guardarDocumento(Request $request, int $id, string $tipo): JsonResponse
    {
        $this->exigir($request, 'employees.edit');

        abort_unless(isset(self::DOCUMENTOS[$tipo]), 404, __('Esse documento não existe na ficha.'));

        $request->validate([
            'ficheiro' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $tenantId = activeTenantId();
        $e = Employee::where('tenant_id', $tenantId)->findOrFail($id);
        $coluna = self::DOCUMENTOS[$tipo];

        if ($e->{$coluna}) {
            Storage::disk('public')->delete($e->{$coluna});
        }

        $caminho = $request->file('ficheiro')->storeAs(
            "tenants/{$tenantId}/employees/{$e->id}/documentos",
            $tipo . '.' . $request->file('ficheiro')->extension(),
            'public',
        );

        $e->update([$coluna => $caminho]);

        return response()->json([
            'documento' => $this->ficha($e->fresh()),
            'message' => __(':documento carregado.', ['documento' => self::rotuloDoDocumento($tipo)]),
        ]);
    }

    public function apagarDocumento(Request $request, int $id, string $tipo): JsonResponse
    {
        $this->exigir($request, 'employees.edit');

        abort_unless(isset(self::DOCUMENTOS[$tipo]), 404, __('Esse documento não existe na ficha.'));

        $e = Employee::where('tenant_id', activeTenantId())->findOrFail($id);
        $coluna = self::DOCUMENTOS[$tipo];

        if ($e->{$coluna}) {
            Storage::disk('public')->delete($e->{$coluna});
            $e->update([$coluna => null]);
        }

        return response()->json([
            'documento' => $this->ficha($e->fresh()),
            'message' => __(':documento removido.', ['documento' => self::rotuloDoDocumento($tipo)]),
        ]);
    }

    /** A FOTOGRAFIA é o retrato da ficha, e não um documento com validade. */
    public function guardarFotografia(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'employees.edit');

        $request->validate(['ficheiro' => ['required', 'image', 'max:2048']]);

        $tenantId = activeTenantId();
        $e = Employee::where('tenant_id', $tenantId)->findOrFail($id);

        if ($e->photo) {
            Storage::disk('public')->delete($e->photo);
        }

        $caminho = $request->file('ficheiro')->storeAs(
            "tenants/{$tenantId}/employees/{$e->id}",
            'foto.' . $request->file('ficheiro')->extension(),
            'public',
        );

        $e->update(['photo' => $caminho]);

        return response()->json([
            'documento' => $this->ficha($e->fresh()),
            'message' => __('Fotografia carregada.'),
        ]);
    }

    /* ─── Importar de outros módulos ──────────────────────────────────── */

    /**
     * QUEM JÁ ESTÁ NA CASA E AINDA NÃO TEM FICHA DE RH.
     *
     * Os técnicos da oficina e o pessoal do hotel já foram registados uma vez.
     * Reescrever nome, contacto e documento para os pôr no RH é trabalho feito
     * duas vezes — e duas fichas da mesma pessoa que depois divergem.
     *
     * SÓ APARECE QUEM AINDA NÃO FOI IMPORTADO: quem já tem ficha com o mesmo
     * email ou telefone fica de fora, senão a lista oferecia duplicados.
     */
    public function importaveis(Request $request): JsonResponse
    {
        $this->exigir($request, 'employees.create');

        $tenantId = activeTenantId();

        $emails = Employee::where('tenant_id', $tenantId)->whereNotNull('email')->pluck('email')->all();
        $telefones = Employee::where('tenant_id', $tenantId)->whereNotNull('phone')->pluck('phone')->all();

        $tecnicos = collect();
        $hotel = collect();

        if (class_exists(\App\Models\Events\Technician::class)) {
            $tecnicos = \App\Models\Events\Technician::where('tenant_id', $tenantId)
                ->when($emails, fn ($q) => $q->whereNotIn('email', $emails))
                ->when($telefones, fn ($q) => $q->whereNotIn('phone', $telefones))
                ->orderBy('name')->get(['id', 'name', 'email', 'phone'])
                ->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name, 'nota' => $t->email ?: $t->phone]);
        }

        if (class_exists(\App\Models\Hotel\Staff::class)) {
            $jaImportados = Employee::where('tenant_id', $tenantId)
                ->whereNotNull('hotel_staff_id')->pluck('hotel_staff_id');

            $hotel = \App\Models\Hotel\Staff::where('tenant_id', $tenantId)->where('is_active', true)
                ->whereNotIn('id', $jaImportados)
                ->orderBy('name')->get(['id', 'name', 'email', 'phone'])
                ->map(fn ($s) => ['id' => $s->id, 'nome' => $s->name, 'nota' => $s->email ?: $s->phone]);
        }

        return response()->json([
            'tecnicos' => $tecnicos->values(),
            'hotel' => $hotel->values(),
        ]);
    }

    /**
     * IMPORTAR — e SALTAR quem já cá está.
     *
     * A verificação é por email OU telefone, como no ecrã de sempre: são os
     * dois campos por que uma pessoa se reconhece quando o nome está escrito
     * de duas maneiras. O que já existe conta-se e diz-se; não se cria a
     * segunda ficha.
     */
    public function importar(Request $request): JsonResponse
    {
        $this->exigir($request, 'employees.create');

        $dados = $request->validate([
            'origem' => ['required', 'in:tecnicos,hotel'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $tenantId = activeTenantId();
        $importados = 0;
        $saltados = 0;

        $modelo = $dados['origem'] === 'tecnicos' ? \App\Models\Events\Technician::class : \App\Models\Hotel\Staff::class;

        abort_unless(class_exists($modelo), 404, __('Essa origem não existe nesta instalação.'));

        foreach ($modelo::where('tenant_id', $tenantId)->whereIn('id', $dados['ids'])->get() as $origem) {
            $repetido = Employee::where('tenant_id', $tenantId)
                ->where(function ($q) use ($origem) {
                    if ($origem->email) {
                        $q->where('email', $origem->email);
                    }
                    if ($origem->phone) {
                        $q->orWhere('phone', $origem->phone);
                    }
                })->exists();

            // Sem email nem telefone não há como reconhecer: importa-se, e
            // quem revê a lista junta as fichas se forem a mesma pessoa.
            if ($repetido && ($origem->email || $origem->phone)) {
                $saltados++;
                continue;
            }

            [$primeiro, $ultimo] = array_pad(explode(' ', (string) $origem->name, 2), 2, '');

            Employee::create(array_filter([
                'tenant_id' => $tenantId,
                'user_id' => $origem->user_id ?? null,
                'hotel_staff_id' => $dados['origem'] === 'hotel' ? $origem->id : null,
                'employee_number' => Employee::generateTenantNumber('employee_number', 'EMP-'),
                'first_name' => $primeiro ?: __('Sem nome'),
                'last_name' => $ultimo,
                'email' => $origem->email,
                'phone' => $origem->phone,
                'bi_number' => $origem->document ?? null,
                'address' => $origem->address ?? null,
                'birth_date' => $origem->birth_date ?? null,
                'hire_date' => $origem->hire_date ?? now()->toDateString(),
                'salary' => $origem->monthly_salary ?? null,
                'base_salary' => $origem->monthly_salary ?? null,
                'photo' => $origem->photo ?? null,
                'employment_type' => 'Contrato',
                'status' => 'active',
                'employment_status' => 'active',
            ], fn ($v) => $v !== null));

            $importados++;
        }

        return response()->json([
            'importados' => $importados,
            'saltados' => $saltados,
            'message' => $saltados > 0
                ? __(':importados importado(s), :saltados já existia(m).', ['importados' => $importados, 'saltados' => $saltados])
                : __(':importados importado(s).', ['importados' => $importados]),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * A VALIDAÇÃO, e depois a normalização.
     *
     * As regras são as do ecrã de sempre, mais as dos campos que ele nunca
     * ofereceu e a base já guardava. As chaves estrangeiras confirmam-se
     * contra ESTA empresa: um `exists:` simples aceitava o departamento de
     * outra.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Employee $existente = null): array
    {
        $tenantId = activeTenantId();

        $desta = fn (string $tabela) => function ($atributo, $valor, $falhar) use ($tabela, $tenantId) {
            if ($valor && ! DB::table($tabela)->where('id', $valor)->where('tenant_id', $tenantId)->exists()) {
                $falhar(__('Essa escolha não é desta empresa.'));
            }
        };

        $dados = $request->validate([
            // Pessoais
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:M,F,Outro'],
            'nif' => ['nullable', 'string', 'max:50'],
            'social_security_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],

            // Documentos
            'bi_number' => ['nullable', 'string', 'max:50'],
            'bi_expiry_date' => ['nullable', 'date'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expiry_date' => ['nullable', 'date'],
            'work_permit_number' => ['nullable', 'string', 'max:50'],
            'work_permit_expiry_date' => ['nullable', 'date'],
            'residence_permit_number' => ['nullable', 'string', 'max:50'],
            'residence_permit_expiry_date' => ['nullable', 'date'],
            'driver_license_number' => ['nullable', 'string', 'max:50'],
            'driver_license_expiry_date' => ['nullable', 'date'],
            'driver_license_category' => ['nullable', 'string', 'max:10'],
            'health_insurance_number' => ['nullable', 'string', 'max:50'],
            'health_insurance_expiry_date' => ['nullable', 'date'],
            'health_insurance_provider' => ['nullable', 'string', 'max:255'],
            'criminal_record_number' => ['nullable', 'string', 'max:50'],
            'criminal_record_issue_date' => ['nullable', 'date'],
            'contract_expiry_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date'],

            // Morada
            'address' => ['nullable', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],

            // Vínculo
            'department_id' => ['nullable', 'integer', $desta('hr_departments')],
            'position_id' => ['nullable', 'integer', $desta('hr_positions')],
            'shift_id' => ['nullable', 'integer', $desta('hr_shifts')],
            'manager_id' => ['nullable', 'integer', $desta('hr_employees')],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_type' => ['required', 'in:Contrato,Freelancer,Estágio,Temporário'],
            'status' => ['required', 'in:active,suspended,terminated,on_leave'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Remuneração
            'salary' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'meal_allowance' => ['nullable', 'numeric', 'min:0'],
            'family_allowance' => ['nullable', 'numeric', 'min:0'],
            'position_subsidy' => ['nullable', 'numeric', 'min:0'],
            'performance_subsidy' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:100'],

            // Beneficiários: nome e parentesco, para o que a lei manda pagar
            // a quem fica.
            'beneficiaries' => ['nullable', 'array', 'max:20'],
            'beneficiaries.*.nome' => ['required', 'string', 'max:255'],
            'beneficiaries.*.parentesco' => ['nullable', 'string', 'max:100'],
            'beneficiaries.*.contacto' => ['nullable', 'string', 'max:50'],
        ], [
            'first_name.required' => __('O primeiro nome é obrigatório.'),
            'last_name.required' => __('O último nome é obrigatório.'),
            'employment_type.required' => __('Escolha o tipo de vínculo.'),
            'status.required' => __('Escolha o estado do funcionário.'),
            'termination_date.after_or_equal' => __('A cessação não pode ser anterior à admissão.'),
        ]);

        /*
         * NINGUÉM CHEFIA A SI PRÓPRIO. Passava, e a cadeia de chefia ficava
         * com um laço que os relatórios percorriam para sempre.
         */
        if ($existente && ($dados['manager_id'] ?? null) && (int) $dados['manager_id'] === $existente->id) {
            abort(422, __('Um funcionário não pode ser chefe de si próprio.'));
        }

        // Um campo vazio é NULO na base, e não uma cadeia vazia: é o que
        // faz «sem NIF» distinguir-se de «NIF por preencher».
        foreach ($dados as $chave => $valor) {
            if ($valor === '') {
                $dados[$chave] = null;
            }
        }

        // AS COLUNAS A DOBRAR: escrevem-se as duas. Ver o cabeçalho.
        foreach (self::IRMAS as $principal => $irma) {
            if (array_key_exists($principal, $dados)) {
                $dados[$irma] = $dados[$principal];
            }
        }

        return $dados;
    }

    /** A linha da lista: o que se lê de relance. */
    private function linha(Employee $e): array
    {
        return [
            'id' => $e->id,
            'numero' => $e->employee_number,
            'nome' => trim($e->first_name . ' ' . $e->last_name),
            'email' => $e->email,
            'telefone' => $e->mobile ?: $e->phone,
            'departamento' => $e->department?->name,
            'cargo' => $e->position?->title,
            'admissao' => $e->hire_date?->format('Y-m-d'),
            'estado' => $e->status,
            'salario' => (float) ($e->salary ?? 0),
            'fotografia' => $e->photo ? Storage::disk('public')->url($e->photo) : null,
            /*
             * O AVISO DOS DOCUMENTOS, contado na linha.
             *
             * Um BI caducado é uma pessoa que não pode ser paga em condições,
             * e ninguém vai à ficha de cada um verificar sete datas.
             */
            'documentos_a_vencer' => $this->quantosAVencer($e),
        ];
    }

    /** A ficha inteira, para o formulário. */
    private function ficha(Employee $e): array
    {
        $data = fn ($v) => $v?->format('Y-m-d');

        $campos = [
            'id' => $e->id,
            'numero' => $e->employee_number,
            'first_name' => $e->first_name,
            'last_name' => $e->last_name,
            'birth_date' => $data($e->birth_date),
            'gender' => $e->gender,
            'nif' => $e->nif,
            'social_security_number' => $e->social_security_number,
            'email' => $e->email,
            'phone' => $e->phone,
            'mobile' => $e->mobile,
            'address' => $e->address,
            'province' => $e->province,
            'city' => $e->city,
            'department_id' => $e->department_id,
            'position_id' => $e->position_id,
            'shift_id' => $e->shift_id,
            'manager_id' => $e->manager_id,
            'hire_date' => $data($e->hire_date),
            'termination_date' => $data($e->termination_date),
            'employment_type' => $e->employment_type,
            'status' => $e->status,
            'notes' => $e->notes,
            'bank_name' => $e->bank_name,
            'bank_account' => $e->bank_account,
            'iban' => $e->iban,
            'beneficiaries' => $e->beneficiaries ?? [],
            'fotografia' => $e->photo ? Storage::disk('public')->url($e->photo) : null,
        ];

        foreach (['bi', 'passport', 'work_permit', 'residence_permit', 'driver_license', 'health_insurance'] as $d) {
            $campos[$d . '_number'] = $e->{$d . '_number'};
            $campos[$d . '_expiry_date'] = $data($e->{$d . '_expiry_date'});
        }

        $campos['driver_license_category'] = $e->driver_license_category;
        $campos['health_insurance_provider'] = $e->health_insurance_provider;
        $campos['criminal_record_number'] = $e->criminal_record_number;
        $campos['criminal_record_issue_date'] = $data($e->criminal_record_issue_date);
        $campos['contract_expiry_date'] = $data($e->contract_expiry_date);
        $campos['probation_end_date'] = $data($e->probation_end_date);

        foreach (['salary', 'bonus', 'transport_allowance', 'meal_allowance', 'family_allowance', 'position_subsidy', 'performance_subsidy'] as $dinheiro) {
            $campos[$dinheiro] = $e->{$dinheiro} === null ? null : (float) $e->{$dinheiro};
        }

        // Os ficheiros que já lá estão, com a morada por onde se abrem.
        $campos['documentos'] = collect(self::DOCUMENTOS)->map(fn ($coluna, $tipo) => [
            'chave' => $tipo,
            'rotulo' => self::rotuloDoDocumento($tipo),
            'url' => $e->{$coluna} ? Storage::disk('public')->url($e->{$coluna}) : null,
        ])->values();

        return $campos;
    }

    /**
     * QUANTOS DOCUMENTOS ESTÃO A VENCER — nos próximos 60 dias, ou já
     * caducados. É o número que acende o crachá na lista.
     */
    private function quantosAVencer(Employee $e): int
    {
        $limite = now()->addDays(60);
        $quantos = 0;

        foreach (self::COLUNAS_DE_VALIDADE as $coluna) {
            if ($e->{$coluna} && $e->{$coluna}->lte($limite)) {
                $quantos++;
            }
        }

        return $quantos;
    }

    /** As sete datas que caducam. */
    private const COLUNAS_DE_VALIDADE = [
        'bi_expiry_date', 'passport_expiry_date', 'work_permit_expiry_date',
        'residence_permit_expiry_date', 'driver_license_expiry_date',
        'health_insurance_expiry_date', 'contract_expiry_date',
    ];

    /** @param  \Illuminate\Database\Eloquent\Builder  $q */
    private function comDocumentoAVencer($q): void
    {
        $limite = now()->addDays(60)->toDateString();

        foreach (self::COLUNAS_DE_VALIDADE as $coluna) {
            $q->orWhere(fn ($sub) => $sub->whereNotNull($coluna)->whereDate($coluna, '<=', $limite));
        }
    }

    private static function rotuloDoDocumento(string $tipo): string
    {
        return match ($tipo) {
            'bi' => __('Bilhete de Identidade'),
            'passport' => __('Passaporte'),
            'work_permit' => __('Autorização de Trabalho'),
            'residence_permit' => __('Autorização de Residência'),
            'driver_license' => __('Carta de Condução'),
            'health_insurance' => __('Seguro de Saúde'),
            'contract' => __('Contrato'),
            'probation' => __('Período Experimental'),
            'criminal_record' => __('Registo Criminal'),
            default => $tipo,
        };
    }
}
