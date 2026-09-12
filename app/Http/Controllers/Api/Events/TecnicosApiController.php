<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Events\Technician;
use App\Models\HR\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS TÉCNICOS — quem monta, quem opera, e em quê.
 *
 * AS ESPECIALIDADES SÃO O QUE INTERESSA: um evento com transmissão precisa de
 * alguém que faça streaming, e escalar um técnico de áudio para isso é dar por
 * ela no dia. São uma lista, e pelo menos uma é obrigatória.
 *
 * A IMPORTAÇÃO DO RH evita a segunda lista de pessoas: quem já é funcionário
 * não se escreve outra vez, e a importação recusa quem já cá está — por e-mail
 * ou por telefone.
 */
class TecnicosApiController extends Controller
{
    public const NIVEIS = [
        'junior' => 'Júnior',
        'pleno' => 'Pleno',
        'senior' => 'Sénior',
        'master' => 'Mestre',
    ];

    public const ESPECIALIDADES = [
        'audio' => ['rotulo' => 'Áudio', 'icone' => 'fa-microphone'],
        'video' => ['rotulo' => 'Vídeo', 'icone' => 'fa-video'],
        'iluminacao' => ['rotulo' => 'Iluminação', 'icone' => 'fa-lightbulb'],
        'streaming' => ['rotulo' => 'Transmissão', 'icone' => 'fa-satellite-dish'],
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
        $this->exigir($request, 'events.technicians.view');

        return response()->json([
            'niveis' => collect(self::NIVEIS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'especialidades' => collect(self::ESPECIALIDADES)->map(fn ($e, $v) => [
                'valor' => $v, 'rotulo' => __($e['rotulo']), 'icone' => $e['icone'],
            ])->values(),
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('events.technicians.manage')],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.technicians.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'especialidade' => ['nullable', Rule::in(array_keys(self::ESPECIALIDADES))],
            'nivel' => ['nullable', Rule::in(array_keys(self::NIVEIS))],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Technician::forTenant()
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhere('email', 'like', "%{$t}%")
                ->orWhere('phone', 'like', "%{$t}%")))
            ->when($filtros['especialidade'] ?? null,
                fn ($q, $e) => $q->whereJsonContains('specialties', $e))
            ->when($filtros['nivel'] ?? null, fn ($q, $n) => $q->where('level', $n))
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Technician $t) => $this->linha($t))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => Technician::forTenant()->count(),
                'activos' => Technician::forTenant()->where('is_active', true)->count(),
                'do_rh' => Technician::forTenant()->whereNotNull('user_id')->count(),
            ],
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('events.technicians.manage')],
        ]);
    }

    private function linha(Technician $t): array
    {
        $especialidades = collect($t->specialties ?? []);

        return [
            'id' => $t->id,
            'nome' => $t->name,
            'email' => $t->email,
            'telefone' => $t->phone,
            'documento' => $t->document,
            'morada' => $t->address,
            'especialidades' => $especialidades->values()->all(),
            'especialidades_rotulos' => $especialidades
                ->map(fn ($e) => __(self::ESPECIALIDADES[$e]['rotulo'] ?? $e))->values()->all(),
            'nivel' => $t->level,
            'nivel_rotulo' => __(self::NIVEIS[$t->level] ?? $t->level),
            'preco_hora' => (float) $t->hourly_rate,
            'preco_dia' => (float) $t->daily_rate,
            'nascimento' => $t->birth_date?->format('Y-m-d'),
            'admissao' => $t->hire_date?->format('Y-m-d'),
            'notas' => $t->notes,
            'activo' => (bool) $t->is_active,
            'disponivel' => (bool) $t->is_available,
            'do_rh' => $t->user_id !== null,
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.technicians.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'document' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'specialties' => ['required', 'array', 'min:1'],
            'specialties.*' => [Rule::in(array_keys(self::ESPECIALIDADES))],
            'level' => ['required', Rule::in(array_keys(self::NIVEIS))],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'birth_date' => ['nullable', 'date'],
            'hire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'is_available' => ['boolean'],
        ], [
            'specialties.required' => __('Escolha pelo menos uma especialidade.'),
        ], [
            'name' => __('nome'), 'phone' => __('telefone'),
            'specialties' => __('especialidades'), 'level' => __('nível'),
        ]);

        $valores = [
            'name' => $dados['name'],
            'email' => ($dados['email'] ?? '') ?: null,
            'phone' => $dados['phone'],
            'document' => ($dados['document'] ?? '') ?: null,
            'address' => ($dados['address'] ?? '') ?: null,
            'specialties' => array_values(array_unique($dados['specialties'])),
            'level' => $dados['level'],
            'hourly_rate' => (float) ($dados['hourly_rate'] ?? 0),
            'daily_rate' => (float) ($dados['daily_rate'] ?? 0),
            'birth_date' => $dados['birth_date'] ?? null,
            'hire_date' => $dados['hire_date'] ?? null,
            'notes' => ($dados['notes'] ?? '') ?: null,
            'is_active' => (bool) ($dados['is_active'] ?? true),
            'is_available' => (bool) ($dados['is_available'] ?? true),
        ];

        $tecnico = $id
            ? tap(Technician::forTenant()->findOrFail($id))->update($valores)
            : Technician::create($valores + ['tenant_id' => activeTenantId()]);

        return response()->json([
            'message' => $id ? __('Técnico actualizado.') : __('Técnico criado.'),
            'data' => $this->linha($tecnico->fresh()),
        ], $id ? 200 : 201);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.technicians.manage');

        $tecnico = Technician::forTenant()->findOrFail($id);
        $tecnico->update(['is_active' => ! $tecnico->is_active]);

        return response()->json([
            'message' => $tecnico->is_active ? __('Técnico activo.') : __('Técnico desligado.'),
            'activo' => (bool) $tecnico->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.technicians.manage');

        $tecnico = Technician::forTenant()->findOrFail($id);

        /*
         * QUEM TEM MATERIAL EM MÃO NÃO SE APAGA.
         *
         * A ficha ia-se e o equipamento ficava emprestado a um id que já não
         * aponta para ninguém: desaparecia dos atrasos e do historial, e um
         * projector ficava perdido sem ninguém dar por isso.
         */
        if (Equipment::forTenant()->where('borrowed_to_technician_id', $tecnico->id)
            ->where('status', 'emprestado')->exists()) {
            $this->recusa(__('Este técnico tem equipamento por devolver. Desligue-o em vez de o apagar.'));
        }

        $tecnico->delete();

        return response()->json(['message' => __('Técnico removido.')]);
    }

    /* ─── A importação do RH ───────────────────────────────────────────── */

    /** Quem está no RH e ainda não é técnico. */
    public function doRh(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.technicians.manage');

        return response()->json(['data' => $this->candidatos()->map(fn (Employee $f) => [
            'id' => $f->id,
            'nome' => $f->full_name,
            'email' => $f->email,
            'telefone' => $f->phone,
            'cargo' => $f->position?->title,
        ])->values()]);
    }

    private function candidatos()
    {
        $emails = Technician::forTenant()->whereNotNull('email')->pluck('email')->filter()->all();
        $telefones = Technician::forTenant()->whereNotNull('phone')->pluck('phone')->filter()->all();

        /*
         * O `whereNotIn` COM LISTA VAZIA E COLUNA NULA.
         *
         * A consulta antiga era `whereNotIn('email', [])->whereNotIn('phone', [])`
         * encadeados: em MySQL, `NULL NOT IN (…)` é NULO — nem verdadeiro nem
         * falso —, pelo que TODO o funcionário sem e-mail desaparecia da lista
         * de importação. Eram justamente os que faltava importar.
         */
        return Employee::forTenant()
            ->with('position:id,title')
            ->where(fn ($q) => $q
                ->whereNull('email')
                ->when($emails, fn ($w) => $w->orWhereNotIn('email', $emails), fn ($w) => $w->orWhereNotNull('email')))
            ->where(fn ($q) => $q
                ->whereNull('phone')
                ->when($telefones, fn ($w) => $w->orWhereNotIn('phone', $telefones), fn ($w) => $w->orWhereNotNull('phone')))
            ->orderBy('first_name')
            ->get();
    }

    public function importarDoRh(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.technicians.manage');

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], ['ids.required' => __('Escolha pelo menos um funcionário.')]);

        $tenantId = activeTenantId();
        $entraram = 0;
        $repetidos = 0;

        foreach (Employee::forTenant()->whereIn('id', $dados['ids'])->with('position:id,title')->get() as $f) {
            $jaExiste = Technician::forTenant()
                ->where(function ($q) use ($f) {
                    $q->whereRaw('1 = 0');

                    if ($f->email) {
                        $q->orWhere('email', $f->email);
                    }

                    if ($f->phone) {
                        $q->orWhere('phone', $f->phone);
                    }
                })
                ->exists();

            if ($jaExiste) {
                $repetidos++;

                continue;
            }

            Technician::create([
                'tenant_id' => $tenantId,
                'user_id' => $f->user_id,
                'name' => $f->full_name,
                'email' => $f->email,
                'phone' => $f->phone ?: '—',
                'document' => $f->bi_number ?: $f->nif,
                // Sem especialidade, o técnico não aparece em escala nenhuma.
                // O áudio é o palpite honesto, e muda-se na ficha.
                'specialties' => ['audio'],
                'level' => 'pleno',
                'hourly_rate' => 0,
                'daily_rate' => 0,
                'is_active' => true,
                'is_available' => true,
                'notes' => $f->position?->title ? __('Cargo no RH: :c', ['c' => $f->position->title]) : null,
            ]);

            $entraram++;
        }

        return response()->json([
            'message' => $repetidos > 0
                ? __(':n importados, :r já cá estavam.', ['n' => $entraram, 'r' => $repetidos])
                : __(':n importados.', ['n' => $entraram]),
            'importados' => $entraram,
        ]);
    }
}
