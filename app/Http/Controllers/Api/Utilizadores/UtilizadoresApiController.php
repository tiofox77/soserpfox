<?php

namespace App\Http\Controllers\Api\Utilizadores;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\PinDeTurno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * OS UTILIZADORES DA EMPRESA.
 *
 * UM UTILIZADOR PERTENCE A VÁRIAS EMPRESAS e tem um PAPEL DIFERENTE em cada
 * uma: é gerente numa e caixa noutra. Por isso o formulário não tem «o papel» —
 * tem um papel por empresa, e é isso que se grava.
 *
 * O QUE NÃO SE APAGA: quem tem documentos emitidos não se elimina, desactiva-se.
 * Apagá-lo deixava facturas assinadas por um id que já não aponta para ninguém,
 * e a auditoria fiscal pergunta por quem emitiu. E ninguém se apaga nem se
 * desactiva a si próprio, nem a um super administrador.
 *
 * O PIN DE TURNO vive aqui porque é o administrador que o repõe a quem se
 * esqueceu — e é a única forma de abrir turno no POS quando a rede cai.
 */
class UtilizadoresApiController extends Controller
{
    /**
     * `users.manage` é o guarda-chuva.
     *
     * As permissões finas existem há muito (`users.view`, `users.create`,
     * `users.edit`, `users.delete`, `users.invite`) e nunca ninguém as pedia:
     * as três páginas estavam todas atrás de `users.manage`. Agora pedem-se —
     * mas quem já tem o `users.manage` continua a poder tudo, senão a migração
     * fechava a porta a todos os administradores que existem.
     */
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless(
            $this->pode($request, $permissao),
            403,
            __('Sem permissão para esta operação.'),
        );
    }

    private function pode(Request $request, string $permissao): bool
    {
        $eu = $request->user();

        return (bool) ($eu?->can($permissao) || $eu?->can('users.manage'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /**
     * O utilizador tem de ser desta empresa.
     *
     * NUNCA SE CONFIA NO ID QUE VEM DO BROWSER: sem esta verificação, um número
     * escrito à mão editava a conta de alguém de outra casa.
     */
    private function daCasa(Request $request, int $id): User
    {
        $u = User::with(['roles', 'tenants'])->findOrFail($id);

        if (! $request->user()?->is_super_admin) {
            abort_unless(
                $u->tenants()->where('tenants.id', activeTenantId())->exists(),
                404,
            );
        }

        return $u;
    }

    /** As empresas que QUEM ESTÁ A EDITAR pode atribuir. */
    private function minhasEmpresas(Request $request)
    {
        $eu = $request->user();

        return $eu->is_super_admin
            ? Tenant::orderBy('name')->get(['id', 'name'])
            : $eu->tenants()->orderBy('name')->get(['tenants.id', 'tenants.name']);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'users.view');

        $eu = $request->user();
        $tenantId = activeTenantId();
        $empresas = $this->minhasEmpresas($request);

        $papeis = Role::whereIn('tenant_id', $empresas->pluck('id'))
            ->orderBy('tenant_id')->orderBy('name')->get(['id', 'name', 'tenant_id']);

        $empresa = Tenant::find($tenantId);

        return response()->json([
            'empresas' => $empresas->map(fn ($e) => ['valor' => (string) $e->id, 'rotulo' => $e->name])->values(),
            'papeis' => $papeis->map(fn ($p) => [
                'valor' => (string) $p->id, 'rotulo' => $p->name, 'empresa' => (int) $p->tenant_id,
            ])->values(),
            'empresa_activa' => $tenantId,
            /*
             * O TECTO DE UTILIZADORES vem de uma regra só (0 = ilimitado, e só
             * as contas activas contam). O ecrã mostra-o ANTES de alguém
             * escrever o formulário todo para levar com o limite no fim.
             */
            'limite' => [
                'usados' => $empresa?->users()->count() ?? 0,
                'maximo' => $empresa?->limiteDeUtilizadores() ?? 0,
                'cabe_mais' => (bool) $empresa?->cabeMaisUmUtilizador(),
            ],
            'permissoes' => [
                'criar' => $this->pode($request, 'users.create'),
                'editar' => $this->pode($request, 'users.edit'),
                'eliminar' => $this->pode($request, 'users.delete'),
                'convidar' => $this->pode($request, 'users.invite'),
                'papeis' => $this->pode($request, 'users.roles.manage'),
                'super_admin' => (bool) $eu?->is_super_admin,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'users.view');

        $eu = $request->user();
        $tenantId = activeTenantId();

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['todos', 'activos', 'inactivos'])],
            'papel' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = fn () => User::query()
            ->when(! $eu->is_super_admin, fn ($q) => $q->whereHas(
                'tenants', fn ($w) => $w->where('tenants.id', $tenantId),
            ))
            ->when(($filtros['estado'] ?? 'todos') === 'activos', fn ($q) => $q->where('is_active', true))
            ->when(($filtros['estado'] ?? 'todos') === 'inactivos', fn ($q) => $q->where('is_active', false))
            // O FILTRO POR PAPEL é por empresa: o papel só quer dizer alguma
            // coisa dentro da casa onde foi atribuído.
            ->when(! empty($filtros['papel']), fn ($q) => $q->whereHas(
                'roles',
                fn ($w) => $w->where('roles.id', $filtros['papel'])
                    ->where('model_has_roles.tenant_id', $tenantId),
            ))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t));
            });

        $lista = $base()
            ->with(['roles', 'tenants:id,name'])
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($lista->items())->map(fn (User $u) => $this->linha($u, $tenantId))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => $base()->count(),
                'activos' => $base()->where('is_active', true)->count(),
                'inactivos' => $base()->where('is_active', false)->count(),
                'com_pin' => $base()->whereNotNull('pos_pin_hash')->count(),
            ],
        ]);
    }

    private function linha(User $u, int $tenantId): array
    {
        $papelEm = fn ($empresaId) => $u->roles
            ->first(fn ($p) => (int) ($p->pivot->tenant_id ?? 0) === (int) $empresaId);

        return [
            'id' => $u->id,
            'nome' => $u->name,
            'email' => $u->email,
            'activo' => (bool) $u->is_active,
            'super_admin' => (bool) $u->is_super_admin,
            'tem_pin' => $u->temPinPos(),
            'pin_em' => $u->pos_pin_set_at?->format('Y-m-d'),
            'criado_em' => $u->created_at?->format('Y-m-d'),
            /*
             * O PAPEL É POR EMPRESA: o mesmo utilizador é gerente numa e caixa
             * noutra. Uma coluna com «o papel» só podia mentir numa das duas.
             */
            'empresas' => $u->tenants->map(fn ($e) => [
                'id' => $e->id,
                'nome' => $e->name,
                'papel' => $papelEm($e->id)?->name,
                'papel_id' => $papelEm($e->id)?->id,
            ])->values(),
            'papel_aqui' => $papelEm($tenantId)?->name,
        ];
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'users.view');

        return response()->json([
            'data' => $this->linha($this->daCasa($request, $id), activeTenantId()),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'users.edit' : 'users.create');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'is_active' => ['boolean'],
            'empresas' => ['required', 'array', 'min:1'],
            'empresas.*' => ['integer'],
            'papeis' => ['nullable', 'array'],
        ], [], ['name' => __('nome'), 'password' => __('palavra-passe')]);

        /*
         * SÓ SE ATRIBUI O QUE SE TEM.
         *
         * Um administrador não pode pôr alguém numa empresa a que ele próprio
         * não pertence — seria dar acesso a uma casa que não é a sua.
         */
        $minhas = $this->minhasEmpresas($request)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $empresas = array_values(array_intersect(array_map('intval', $dados['empresas']), $minhas));

        if (! $empresas) {
            $this->recusa(__('Escolha pelo menos uma empresa a que tenha acesso.'));
        }

        // O TECTO conta-se ANTES de criar: um utilizador a mais do que o plano
        // permite entra, e depois ninguém sabe qual é que sobra.
        if (! $id) {
            $empresa = Tenant::find(activeTenantId());

            if ($empresa && ! $empresa->cabeMaisUmUtilizador()) {
                $this->recusa(__('Limite de utilizadores atingido (:n). Liberte uma conta ou aumente o plano.', [
                    'n' => $empresa->limiteDeUtilizadores(),
                ]));
            }
        }

        $u = DB::transaction(function () use ($dados, $id, $empresas, $request) {
            $valores = [
                'name' => $dados['name'],
                'email' => $dados['email'],
                'is_active' => (bool) ($dados['is_active'] ?? true),
            ];

            if (! empty($dados['password'])) {
                $valores['password'] = Hash::make($dados['password']);
            }

            $u = $id ? tap($this->daCasa($request, $id))->update($valores) : User::create($valores);

            $this->sincronizarEmpresas($u, $empresas, $dados['papeis'] ?? [], $request);

            return $u;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => $id ? __('Utilizador actualizado.') : __('Utilizador criado.'),
            'data' => $this->linha($u->fresh(['roles', 'tenants']), activeTenantId()),
        ], $id ? 200 : 201);
    }

    /** As empresas e o papel em cada uma. */
    private function sincronizarEmpresas(User $u, array $empresas, array $papeis, Request $request): void
    {
        /*
         * A LIGAÇÃO LÊ-SE DA TABELA, NÃO DA RELAÇÃO.
         *
         * `User::tenants()` filtra por `is_active` no pivot — uma ligação
         * desactivada não aparece lá. Perguntar à relação se a ligação existe
         * respondia «não» e criava uma segunda linha para a mesma empresa.
         */
        $existentes = DB::table('tenant_user')->where('user_id', $u->id)
            ->pluck('tenant_id')->map(fn ($i) => (int) $i)->all();

        foreach ($empresas as $empresaId) {
            $novo = ! in_array($empresaId, $existentes, true);

            if ($novo) {
                $u->tenants()->attach($empresaId, ['is_active' => true, 'joined_at' => now()]);
            } else {
                DB::table('tenant_user')->where('user_id', $u->id)
                    ->where('tenant_id', $empresaId)->update(['is_active' => true]);
            }

            $u->roles()->wherePivot('tenant_id', $empresaId)->detach();

            $papelId = $papeis[$empresaId] ?? $papeis[(string) $empresaId] ?? null;
            $papel = null;

            if ($papelId) {
                // O PAPEL TEM DE SER DA EMPRESA: um id de outra casa dava ao
                // utilizador as permissões dela.
                $papel = Role::where('id', $papelId)->where('tenant_id', $empresaId)->first();

                if ($papel) {
                    setPermissionsTeamId($empresaId);
                    $u->assignRole($papel);
                }
            }

            if ($novo) {
                $this->avisar($u, (int) $empresaId, $papel?->name, $request);
            }
        }

        $aRetirar = array_values(array_diff($existentes, $empresas));

        if ($aRetirar) {
            $u->tenants()->detach($aRetirar);

            foreach ($aRetirar as $empresaId) {
                $u->roles()->wherePivot('tenant_id', $empresaId)->detach();
            }
        }

        // O `tenant_id` da ficha é só a casa de entrada; não se reescreve a
        // quem já a tem, senão mexer-lhe nas empresas mudava-lhe a de omissão.
        if (empty($u->tenant_id)) {
            $u->update(['tenant_id' => $empresas[0]]);
        }
    }

    /** O aviso de que passou a ter acesso — e que nunca rebenta a gravação. */
    private function avisar(User $u, int $empresaId, ?string $papel, Request $request): void
    {
        try {
            $u->notify(new \App\Notifications\UserAddedToTenantNotification(
                Tenant::find($empresaId),
                $request->user(),
                $papel,
            ));
        } catch (\Throwable $e) {
            // Um e-mail que não sai não pode impedir alguém de entrar no
            // sistema. Fica no registo, e a conta funciona na mesma.
            \Log::error('Aviso de acesso à empresa não saiu', [
                'user_id' => $u->id, 'tenant_id' => $empresaId, 'erro' => $e->getMessage(),
            ]);
        }
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'users.edit');

        $u = $this->daCasa($request, $id);

        if ($u->is_super_admin) {
            $this->recusa(__('Não se altera o estado de um super administrador.'));
        }

        if ($u->id === $request->user()?->id) {
            $this->recusa(__('Não pode desactivar a sua própria conta.'));
        }

        $u->update(['is_active' => ! $u->is_active]);

        return response()->json([
            'message' => $u->is_active ? __('Utilizador activado.') : __('Utilizador desactivado.'),
            'activo' => (bool) $u->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'users.delete');

        $u = $this->daCasa($request, $id);

        if ($u->is_super_admin) {
            $this->recusa(__('Não se elimina um super administrador.'));
        }

        if ($u->id === $request->user()?->id) {
            $this->recusa(__('Não pode eliminar a sua própria conta.'));
        }

        /*
         * QUEM TEM DOCUMENTOS NÃO SE APAGA.
         *
         * Uma factura emitida guarda quem a emitiu. Apagar a conta deixava o
         * documento assinado por um id que já não aponta para ninguém — e a
         * auditoria pergunta pelo nome, não pelo número.
         */
        foreach ([
            'invoicing_sales_invoices' => 'created_by',
            'invoicing_sales_proformas' => 'created_by',
            'invoicing_purchase_invoices' => 'created_by',
            'invoicing_credit_notes' => 'created_by',
            'invoicing_debit_notes' => 'created_by',
            'invoicing_receipts' => 'created_by',
            'orders' => 'user_id',
        ] as $tabela => $coluna) {
            if (DB::getSchemaBuilder()->hasTable($tabela)
                && DB::table($tabela)->where($coluna, $u->id)->exists()) {
                $this->recusa(__('Este utilizador tem documentos emitidos. Desactive-o em vez de o eliminar.'));
            }
        }

        DB::transaction(function () use ($u) {
            $u->roles()->detach();
            $u->tenants()->detach();
            // SOFT DELETE: a conta sai das listas e dos acessos, mas a linha
            // fica — é o que mantém legível tudo o que ela tocou.
            $u->delete();
        });

        return response()->json(['message' => __('Utilizador eliminado.')]);
    }

    /**
     * O PIN DE TURNO — a chave do POS sem rede.
     *
     * É o administrador que o define a quem se esqueceu: sem ele, o tablet não
     * deixa abrir turno quando a rede cai, e é justamente aí que faz falta.
     */
    public function pin(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'users.edit');

        $dados = $request->validate([
            'pin' => ['required', 'digits_between:4,6', 'same:pin_confirmation'],
            'pin_confirmation' => ['required'],
        ], [
            'pin.required' => __('Escreva o PIN.'),
            'pin.digits_between' => __('O PIN tem de ter 4 a 6 dígitos.'),
            'pin.same' => __('Os dois PIN não coincidem.'),
        ]);

        $u = $this->daCasa($request, $id);

        // A lista dos óbvios vive num sítio só (`PinDeTurno`): estava aqui, no
        // auto-serviço e no comando, e já não eram iguais.
        if (PinDeTurno::ehObvio($dados['pin'])) {
            throw ValidationException::withMessages(['pin' => [__('Escolha um PIN menos óbvio.')]]);
        }

        try {
            $u->definirPinPos($dados['pin']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['pin' => [$e->getMessage()]]);
        }

        return response()->json([
            'message' => __('PIN de :nome definido. Vai para os tablets na próxima sincronização.', [
                'nome' => $u->name,
            ]),
        ]);
    }

    /* ─── Os convites ──────────────────────────────────────────────────── */

    public function convites(Request $request): JsonResponse
    {
        $this->exigir($request, 'users.invite');

        $lista = UserInvitation::with(['invitedBy:id,name', 'user:id,name'])
            ->forTenant(activeTenantId())
            ->latest()->limit(200)->get();

        return response()->json([
            'data' => $lista->map(fn (UserInvitation $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'email' => $c->email,
                'papel' => $c->role,
                // O ESTADO GRAVADO NÃO CHEGA: um convite pendente cuja data já
                // passou continua «pending» na base até alguém lhe tocar.
                'estado' => $c->isExpired() ? 'expired' : $c->status,
                'expira_em' => $c->expires_at?->format('Y-m-d H:i'),
                'convidado_por' => $c->invitedBy?->name,
                'aceite_por' => $c->user?->name,
                'quando' => $c->created_at?->format('Y-m-d H:i'),
            ])->values(),
            'resumo' => [
                'total' => $lista->count(),
                'pendentes' => $lista->filter(fn ($c) => $c->isPending())->count(),
                'aceites' => $lista->where('status', 'accepted')->count(),
                'expirados' => $lista->filter(fn ($c) => $c->isExpired())->count(),
            ],
        ]);
    }

    public function convidar(Request $request): JsonResponse
    {
        $this->exigir($request, 'users.invite');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
        ], [], ['name' => __('nome'), 'role_id' => __('papel')]);

        $jaEsta = User::where('email', $dados['email'])
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->exists();

        if ($jaEsta) {
            $this->recusa(__('Este e-mail já pertence a um utilizador desta empresa.'));
        }

        $pendente = UserInvitation::where('email', $dados['email'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pendente) {
            $this->recusa(__('Já existe um convite pendente para este e-mail.'));
        }

        /*
         * NÃO SE CONVIDA QUEM NÃO VAI CABER.
         *
         * O limite é verificado outra vez ao aceitar — é lá que a conta nasce —
         * mas avisar agora poupa ao convidado descobrir a porta fechada depois
         * de já ter escolhido a palavra-passe.
         */
        $empresa = Tenant::find($tenantId);

        if ($empresa && ! $empresa->cabeMaisUmUtilizador()) {
            $this->recusa(__('Limite de utilizadores atingido (:n). Liberte uma conta ou aumente o plano.', [
                'n' => $empresa->limiteDeUtilizadores(),
            ]));
        }

        $papel = Role::find($dados['role_id']);

        $convite = UserInvitation::create([
            'tenant_id' => $tenantId,
            'invited_by' => $request->user()?->id,
            'email' => $dados['email'],
            'name' => $dados['name'],
            'role' => $papel?->name ?? 'user',
            'role_id' => $dados['role_id'],
        ]);

        return response()->json([
            'message' => $this->enviar($convite)
                ? __('Convite enviado para :email.', ['email' => $convite->email])
                : __('Convite criado, mas o e-mail não saiu. Verifique a configuração de envio e reenvie.'),
        ], 201);
    }

    /**
     * O e-mail sai FORA da transacção, e o convite sobrevive-lhe.
     *
     * O envio depende de SMTP e de um modelo de e-mail gravados na base: se
     * faltar um deles, rebenta. Antes isso desfazia o convite inteiro e não
     * ficava rasto nenhum do que se tinha tentado fazer — agora o convite fica
     * gravado e reenvia-se com um botão.
     */
    private function enviar(UserInvitation $convite): bool
    {
        try {
            $convite->sendInvitationEmail();

            return true;
        } catch (\Throwable $e) {
            \Log::error('Convite criado mas não enviado', [
                'convite' => $convite->id, 'email' => $convite->email, 'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function reenviar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'users.invite');

        $c = UserInvitation::where('tenant_id', activeTenantId())->findOrFail($id);

        if ($c->status === 'accepted') {
            $this->recusa(__('Este convite já foi aceite.'));
        }

        // Reenviar é dar mais sete dias: o antigo já pode ter expirado, e um
        // link morto dentro de um e-mail novo era o pior dos dois mundos.
        $c->update(['expires_at' => now()->addDays(7), 'status' => 'pending']);

        return response()->json([
            'message' => $this->enviar($c)
                ? __('Convite reenviado para :email.', ['email' => $c->email])
                : __('O e-mail não saiu. Verifique a configuração de envio.'),
        ]);
    }

    public function cancelarConvite(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'users.invite');

        $c = UserInvitation::where('tenant_id', activeTenantId())->findOrFail($id);

        if ($c->status === 'accepted') {
            $this->recusa(__('Este convite já foi aceite.'));
        }

        $c->markAsCancelled();

        return response()->json(['message' => __('Convite cancelado.')]);
    }
}
