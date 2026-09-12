<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS PROFISSIONAIS DO SALÃO — quem atende, quando, e em quê.
 *
 * O HORÁRIO NÃO É DECORAÇÃO: é ele que decide o que a página pública de
 * marcação oferece. Um profissional sem dias de trabalho não aparece lá, e um
 * com almoço marcado não recebe marcações a essa hora.
 *
 * IMPORTAR DO RH existe porque o cabeleireiro já está em `hr_employees` com
 * nome, contacto e data de admissão. Escrevê-lo outra vez à mão era o caminho
 * mais curto para duas fichas da mesma pessoa com dados diferentes — e a
 * importação recusa quem já cá está, por e-mail ou por telefone.
 */
class ProfissionaisApiController extends Controller
{
    /** Os dias da semana como a base os guarda (0 = domingo). */
    public const DIAS = [
        1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
        5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo',
    ];

    public const NIVEIS = [
        'junior' => 'Júnior', 'pleno' => 'Pleno', 'senior' => 'Sénior', 'master' => 'Master',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.professionals.view');

        return response()->json([
            'dias' => collect(self::DIAS)->map(fn ($r, $v) => ['valor' => (int) $v, 'rotulo' => __($r)])->values(),
            'niveis' => collect(self::NIVEIS)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'servicos' => Service::forTenant()->active()->orderBy('name')->get()
                ->map(fn (Service $s) => ['valor' => (string) $s->id, 'rotulo' => $s->name])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('salon.professionals.create'),
                'pode_editar' => (bool) $request->user()?->can('salon.professionals.edit'),
                'pode_apagar' => (bool) $request->user()?->can('salon.professionals.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.professionals.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Professional::forTenant()
            ->with('services:id,name')
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")->orWhere('specialization', 'like', "%{$t}%")))
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Professional $p) => $this->linha($p))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => Professional::forTenant()->count(),
                'activos' => Professional::forTenant()->where('is_active', true)->count(),
                'na_marcacao_online' => Professional::forTenant()
                    ->where('is_active', true)->where('accepts_online_booking', true)->count(),
            ],
        ]);
    }

    private function linha(Professional $p): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->name,
            'alcunha' => $p->nickname,
            'email' => $p->email,
            'telefone' => $p->phone,
            'documento' => $p->document,
            'morada' => $p->address,
            'especialidade' => $p->specialization,
            'nivel' => $p->level,
            'nivel_rotulo' => $p->level ? __(self::NIVEIS[$p->level] ?? $p->level) : null,
            'biografia' => $p->bio,
            'nascimento' => $p->birth_date?->format('Y-m-d'),
            'admissao' => $p->hire_date?->format('Y-m-d'),
            'dias' => array_map('intval', $p->working_days ?? []),
            'entrada' => $p->work_start?->format('H:i'),
            'saida' => $p->work_end?->format('H:i'),
            'almoco_de' => $p->lunch_start?->format('H:i'),
            'almoco_ate' => $p->lunch_end?->format('H:i'),
            'comissao' => (float) $p->commission_percent,
            'preco_hora' => (float) $p->hourly_rate,
            'preco_dia' => (float) $p->daily_rate,
            'marcacao_online' => (bool) $p->accepts_online_booking,
            'activo' => (bool) $p->is_active,
            'disponivel' => (bool) $p->is_available,
            'servicos' => $p->services->map(fn ($s) => ['id' => $s->id, 'nome' => $s->name])->values(),
            'service_ids' => $p->services->pluck('id')->values(),
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'salon.professionals.edit' : 'salon.professionals.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'document' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'level' => ['nullable', Rule::in(array_keys(self::NIVEIS))],
            'bio' => ['nullable', 'string', 'max:2000'],
            'birth_date' => ['nullable', 'date'],
            'hire_date' => ['nullable', 'date'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:0,6'],
            'work_start' => ['required', 'string', 'max:8'],
            'work_end' => ['required', 'string', 'max:8', 'after:work_start'],
            'lunch_start' => ['nullable', 'string', 'max:8'],
            'lunch_end' => ['nullable', 'string', 'max:8'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'accepts_online_booking' => ['boolean'],
            'is_active' => ['boolean'],
            'is_available' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
        ], [
            'work_end.after' => __('A hora de saída tem de ser depois da de entrada.'),
        ], [
            'name' => __('nome'), 'working_days' => __('dias de trabalho'),
            'work_start' => __('entrada'), 'work_end' => __('saída'),
        ]);

        $valores = [
            'name' => $dados['name'],
            'nickname' => $dados['nickname'] ?? null,
            'email' => $dados['email'] ?? null,
            'phone' => $dados['phone'] ?? null,
            'document' => $dados['document'] ?? null,
            'address' => $dados['address'] ?? null,
            'specialization' => $dados['specialization'] ?? null,
            'level' => $dados['level'] ?? null,
            'bio' => $dados['bio'] ?? null,
            'birth_date' => $dados['birth_date'] ?? null,
            'hire_date' => $dados['hire_date'] ?? null,
            'working_days' => array_values(array_map('intval', $dados['working_days'])),
            'work_start' => $dados['work_start'],
            'work_end' => $dados['work_end'],
            'lunch_start' => $dados['lunch_start'] ?? null,
            'lunch_end' => $dados['lunch_end'] ?? null,
            'commission_percent' => (float) ($dados['commission_percent'] ?? 0),
            'hourly_rate' => (float) ($dados['hourly_rate'] ?? 0),
            'daily_rate' => (float) ($dados['daily_rate'] ?? 0),
            'accepts_online_booking' => (bool) ($dados['accepts_online_booking'] ?? true),
            'is_active' => (bool) ($dados['is_active'] ?? true),
            'is_available' => (bool) ($dados['is_available'] ?? true),
        ];

        $profissional = $id
            ? tap(Professional::forTenant()->findOrFail($id))->update($valores)
            : Professional::create($valores + ['tenant_id' => $tenantId]);

        /*
         * OS SERVIÇOS TÊM DE SER DESTA EMPRESA. O `sync` aceita qualquer id, e
         * um número escrito à mão no pedido punha o serviço de outra casa na
         * ficha deste profissional.
         */
        if (array_key_exists('service_ids', $dados)) {
            $meus = Service::forTenant()->whereIn('id', $dados['service_ids'] ?? [])->pluck('id');

            $profissional->services()->sync($meus);
        }

        return response()->json([
            'message' => $id ? __('Profissional actualizado.') : __('Profissional criado.'),
            'data' => $this->linha($profissional->fresh('services')),
        ], $id ? 200 : 201);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.professionals.edit');

        $p = Professional::forTenant()->findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);

        return response()->json([
            'message' => $p->is_active ? __('Profissional activo.') : __('Profissional desligado.'),
            'activo' => (bool) $p->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.professionals.delete');

        $p = Professional::forTenant()->findOrFail($id);

        // Uma agenda por cumprir não desaparece com a ficha de quem a ia
        // cumprir: as clientes continuam à espera de alguém.
        if ($p->appointments()->whereIn('status', ['scheduled', 'confirmed', 'arrived', 'in_progress'])->exists()) {
            $this->recusa(__('Este profissional tem marcações por atender. Desligue-o em vez de o apagar.'));
        }

        $p->delete();

        return response()->json(['message' => __('Profissional removido.')]);
    }

    /* ─── A importação do RH ───────────────────────────────────────────── */

    /** Quem está no RH e ainda não é profissional do salão. */
    public function doRh(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.professionals.create');

        $tenantId = activeTenantId();

        $emails = Professional::forTenant()->whereNotNull('email')->where('email', '!=', '')->pluck('email')->all();
        $telefones = Professional::forTenant()->whereNotNull('phone')->where('phone', '!=', '')->pluck('phone')->all();

        $candidatos = Employee::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->when($emails !== [], fn ($q) => $q->where(fn ($sub) => $sub
                ->whereNull('email')->orWhere('email', '')->orWhereNotIn('email', $emails)))
            ->when($telefones !== [], fn ($q) => $q->where(fn ($sub) => $sub
                ->whereNull('phone')->orWhere('phone', '')->orWhereNotIn('phone', $telefones)))
            ->with('position:id,name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'data' => $candidatos->map(fn (Employee $f) => [
                'id' => $f->id,
                'nome' => $f->full_name,
                'email' => $f->email,
                'telefone' => $f->phone ?? $f->mobile,
                'cargo' => $f->position?->name,
            ])->values(),
        ]);
    }

    public function importarDoRh(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.professionals.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
        ], [], ['employee_ids' => __('funcionários')]);

        $importados = 0;
        $saltados = 0;

        foreach (Employee::where('tenant_id', $tenantId)->whereIn('id', $dados['employee_ids'])->with('position')->get() as $f) {
            $jaLa = Professional::forTenant()
                ->where(function ($q) use ($f) {
                    if ($f->email) $q->where('email', $f->email);
                    if ($f->phone) $q->orWhere('phone', $f->phone);
                })
                ->exists();

            if ($jaLa) {
                $saltados++;

                continue;
            }

            Professional::create([
                'tenant_id' => $tenantId,
                'user_id' => $f->user_id,
                'name' => $f->full_name,
                'email' => $f->email,
                'phone' => $f->phone ?? $f->mobile,
                'document' => $f->bi_number ?? $f->nif,
                'address' => $f->address,
                'specialization' => $f->position?->name ?? __('Geral'),
                'level' => 'pleno',
                'birth_date' => $f->birth_date,
                'hire_date' => $f->hire_date ?? now(),
                'working_days' => [1, 2, 3, 4, 5, 6],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'commission_percent' => 0,
                'hourly_rate' => 0,
                'daily_rate' => 0,
                'is_active' => true,
                'is_available' => true,
                'accepts_online_booking' => true,
            ]);

            $importados++;
        }

        return response()->json([
            'message' => $saltados > 0
                ? __(':n importado(s) — :s já cá estavam.', ['n' => $importados, 's' => $saltados])
                : __(':n funcionário(s) importado(s).', ['n' => $importados]),
        ]);
    }
}
