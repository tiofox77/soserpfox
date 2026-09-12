<?php

namespace App\Http\Controllers\Api\Utilizadores;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoDePermissoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * OS PAPÉIS E AS PERMISSÕES.
 *
 * O sistema tem umas trezentas e quarenta permissões. Mostrá-las todas, com o
 * nome técnico e em bloco, era mostrar um muro: ninguém sabia o que é que
 * `hotel.rooms.edit` fazia, nem porque é que uma empresa sem hotel a via.
 *
 * Por isso o catálogo é O QUE ESTA EMPRESA PODE VER — o núcleo mais os módulos
 * ACTIVOS —, em português, agrupado por entidade («Faturas de Venda»: Ver ·
 * Criar · Editar · Eliminar), com atalhos para marcar um módulo inteiro ou só a
 * consulta, e para começar a partir de um papel que já existe.
 *
 * O QUE AQUI NÃO HÁ: criar permissões novas. O ecrã antigo deixava — e a
 * permissão nascia GLOBAL, sem empresa nenhuma, escrita a partir de dentro de
 * uma casa. Pior: com os curingas desligados, uma permissão inventada não é
 * verificada em lado nenhum do código, por isso não dava poder a ninguém. Era
 * uma escrita que atravessava empresas para não fazer rigorosamente nada.
 */
class PapeisApiController extends Controller
{
    private function exigir(Request $request): void
    {
        $eu = $request->user();

        abort_unless(
            $eu?->can('users.roles.manage') || $eu?->can('users.permissions') || $eu?->can('users.manage'),
            403,
            __('Sem permissão para gerir papéis.'),
        );
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /** O papel tem de ser desta empresa — nunca se edita o de outra casa. */
    private function daCasa(int $id): Role
    {
        return Role::where('id', $id)->where('tenant_id', activeTenantId())->firstOrFail();
    }

    /** @var Collection<int, Permission>|null */
    private ?Collection $todas = null;

    private function permissoes(): Collection
    {
        return $this->todas ??= Permission::orderBy('name')->get();
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request);

        $tenantId = activeTenantId();
        setPermissionsTeamId($tenantId);

        $papeis = Role::withCount(['permissions', 'users'])
            ->where('tenant_id', $tenantId)
            ->orderBy('name')->get();

        return response()->json([
            'data' => $papeis->map(fn (Role $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'descricao' => $p->description,
                'permissoes' => $p->permissions_count,
                'utilizadores' => $p->users_count,
                'criado_em' => $p->created_at?->format('Y-m-d'),
            ])->values(),
            'resumo' => [
                'papeis' => $papeis->count(),
                /*
                 * PAPÉIS SEM NINGUÉM são a sujidade mais comum deste ecrã: um
                 * papel desenhado uma vez, nunca atribuído, e que fica a dar a
                 * ideia de que alguém o usa.
                 */
                'sem_utilizadores' => $papeis->where('users_count', 0)->count(),
                'sem_permissoes' => $papeis->where('permissions_count', 0)->count(),
                'permissoes_visiveis' => collect($this->catalogoDaEmpresa())->sum('total'),
            ],
        ]);
    }

    /** Os grupos visíveis desta empresa: o núcleo mais os módulos activos. */
    private function catalogoDaEmpresa(): array
    {
        $activos = Tenant::find(activeTenantId())
            ?->modules()->wherePivot('is_active', true)->pluck('slug')->all() ?? [];

        $grupos = [];

        foreach (CatalogoDePermissoes::gruposPara($activos) as $slug => $grupo) {
            $doGrupo = CatalogoDePermissoes::doGrupo($this->permissoes(), $grupo);

            if ($doGrupo->isEmpty()) {
                continue;
            }

            $entidades = [];

            foreach ($doGrupo as $p) {
                $entidades[CatalogoDePermissoes::entidade($p->name)][] = [
                    'id' => (int) $p->id,
                    'nome' => $p->name,
                    'rotulo' => CatalogoDePermissoes::rotulo($p),
                    'accao' => CatalogoDePermissoes::accao($p->name),
                    // A CONSULTA é o que o atalho «só leitura» marca: ver,
                    // aceder, relatórios e painel — nada que escreva.
                    'leitura' => in_array(
                        CatalogoDePermissoes::accao($p->name),
                        ['view', 'access', 'reports', 'dashboard'],
                        true,
                    ),
                ];
            }

            $grupos[$slug] = [
                'slug' => $slug,
                'nome' => $grupo['nome'],
                'icone' => $grupo['icone'],
                'ids' => $doGrupo->pluck('id')->map(fn ($i) => (int) $i)->all(),
                'total' => $doGrupo->count(),
                'entidades' => collect($entidades)->map(fn ($linhas, $nome) => [
                    'nome' => $nome, 'linhas' => $linhas,
                ])->values()->all(),
            ];
        }

        return $grupos;
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request);

        $tenantId = activeTenantId();

        return response()->json([
            'grupos' => array_values($this->catalogoDaEmpresa()),
            // «Começar a partir de» — copiar as permissões de um papel que já
            // existe é como toda a gente faz o segundo papel.
            'papeis' => Role::where('tenant_id', $tenantId)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => $p->name])->values(),
        ]);
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $papel = $this->daCasa($id);

        return response()->json([
            'data' => [
                'id' => $papel->id,
                'nome' => $papel->name,
                'descricao' => $papel->description,
                'permissoes' => $papel->permissions->pluck('id')->map(fn ($i) => (int) $i)->values(),
                'utilizadores' => $papel->users()->count(),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request);

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => [
                'required', 'string', 'min:3', 'max:100',
                // O NOME É ÚNICO DENTRO DA EMPRESA, não no sistema: duas casas
                // podem ter ambas um «Gerente», e têm.
                Rule::unique('roles', 'name')->where('tenant_id', $tenantId)->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissoes' => ['nullable', 'array'],
            'permissoes.*' => ['integer'],
        ], [], ['name' => __('nome')]);

        setPermissionsTeamId($tenantId);

        $papel = $id ? $this->daCasa($id) : new Role(['guard_name' => 'web']);

        $papel->fill([
            'name' => $dados['name'],
            'description' => $dados['description'] ?? null,
        ]);
        $papel->tenant_id = $tenantId;
        $papel->guard_name = 'web';
        $papel->save();

        /*
         * SÓ SE ATRIBUI O QUE A EMPRESA VÊ.
         *
         * O ecrã mostra o núcleo mais os módulos activos; gravar um id que não
         * está nessa lista era dar, por um pedido escrito à mão, permissões de
         * um módulo que a empresa não comprou.
         */
        $visiveis = collect($this->catalogoDaEmpresa())->pluck('ids')->flatten()->all();
        $escolhidas = array_values(array_intersect(
            array_map('intval', $dados['permissoes'] ?? []),
            $visiveis,
        ));

        $papel->syncPermissions(Permission::whereIn('id', $escolhidas)->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => $id ? __('Papel actualizado.') : __('Papel criado.'),
            'data' => ['id' => $papel->id, 'nome' => $papel->name],
        ], $id ? 200 : 201);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $papel = $this->daCasa($id);

        /*
         * UM PAPEL COM GENTE NÃO SE APAGA.
         *
         * Quem o tinha ficava sem permissão nenhuma e sem aviso — descobria-o
         * na manhã seguinte, à frente de um cliente, com o ecrã a dizer 403.
         */
        $quantos = $papel->users()->count();

        if ($quantos > 0) {
            $this->recusa(__('Este papel está atribuído a :n utilizador(es). Mude-os de papel primeiro.', [
                'n' => $quantos,
            ]));
        }

        $papel->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => __('Papel eliminado.')]);
    }

    /* ─── Atribuir papéis a quem já cá está ────────────────────────────── */

    public function utilizadores(Request $request): JsonResponse
    {
        $this->exigir($request);

        $tenantId = activeTenantId();
        setPermissionsTeamId($tenantId);

        $procura = trim((string) $request->query('procura', ''));

        $lista = User::with('roles')
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->when($procura !== '', function ($q) use ($procura) {
                $t = '%'.$procura.'%';

                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->orderBy('name')->get();

        return response()->json([
            'data' => $lista->map(fn (User $u) => [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'activo' => (bool) $u->is_active,
                'super_admin' => (bool) $u->is_super_admin,
                // OS PAPÉIS DESTA EMPRESA, e só os desta: o mesmo utilizador
                // tem outros noutra casa, e mostrá-los aqui confundia tudo.
                'papeis' => $u->roles
                    ->filter(fn ($p) => (int) ($p->pivot->tenant_id ?? 0) === $tenantId)
                    ->map(fn ($p) => ['id' => $p->id, 'nome' => $p->name])->values(),
            ])->values(),
        ]);
    }

    public function atribuir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'papeis' => ['present', 'array'],
            'papeis.*' => ['integer'],
        ]);

        $u = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->findOrFail($id);

        setPermissionsTeamId($tenantId);

        $papeis = Role::whereIn('id', $dados['papeis'])->where('tenant_id', $tenantId)->get();

        /*
         * `syncRoles` só mexe nos papéis da equipa activa — o `setPermissionsTeamId`
         * acima é o que garante isso. Sem ele, atribuir um papel aqui apagava
         * os papéis que a pessoa tem nas outras empresas.
         */
        $u->syncRoles($papeis);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => __('Papéis de :nome actualizados.', ['nome' => $u->name]),
            'papeis' => $papeis->map(fn ($p) => ['id' => $p->id, 'nome' => $p->name])->values(),
        ]);
    }
}
