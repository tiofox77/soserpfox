<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plataforma\AvisosDeConta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

use function Illuminate\Support\defer;

/**
 * QUEM TRABALHA NUMA EMPRESA — visto e mexido pelo dono da plataforma.
 *
 * O QUE MUDOU:
 *
 *  · O ESCOLHER UM UTILIZADOR EXISTENTE era um `<select>` com TODAS as pessoas
 *    da plataforma, carregado a cada render do ecrã das empresas — mesmo com o
 *    modal fechado não, mas com ele aberto sim, e inteiro. Com milhares de
 *    contas é uma lista que não se usa. Agora procura-se, a partir de dois
 *    caracteres, e vêm vinte.
 *  · «SEM PAPEL» deixou de ser oferecido na lista de papéis: escolhê-lo dava
 *    erro, porque nenhum papel é «nenhum». Quem não deve ter papel sai da
 *    empresa.
 */
class EmpresaUtilizadoresApiController extends Controller
{
    public function index(int $empresa): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);

        setPermissionsTeamId($t->id);

        $pessoas = $t->users()->withPivot('is_active', 'joined_at')->orderBy('name')->get();

        // OS PAPÉIS DE TODOS numa consulta, e não um `roles()->first()` por
        // pessoa como o ecrã fazia.
        $papeis = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('tenant_id', $t->id)
            ->whereIn('model_id', $pessoas->pluck('id'))
            ->pluck('role_id', 'model_id');

        return response()->json([
            'empresa' => ['id' => $t->id, 'nome' => $t->name],
            'limite' => $t->limiteDeUtilizadores(),
            'cabe_mais_um' => $t->cabeMaisUmUtilizador(),
            'utilizadores' => $pessoas->map(fn (User $u) => [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'papel' => isset($papeis[$u->id]) ? (int) $papeis[$u->id] : null,
                'entrou_em' => $u->pivot->joined_at ? \Carbon\Carbon::parse($u->pivot->joined_at)->format('d/m/Y') : null,
            ])->values(),
            'papeis' => Role::where('tenant_id', $t->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($r) => ['valor' => (string) $r->id, 'rotulo' => $r->name])->values(),
        ]);
    }

    /** Procurar alguém que ainda não está nesta empresa. */
    public function procurar(Request $request, int $empresa): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['pessoas' => []]);
        }

        $jaEstao = $t->users()->pluck('users.id');

        return response()->json([
            'pessoas' => User::whereNotIn('id', $jaEstao)
                ->where('is_super_admin', false)
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                ->orderBy('name')->limit(20)->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => $u->id, 'nome' => $u->name, 'email' => $u->email])->values(),
        ]);
    }

    public function juntar(Request $request, int $empresa): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);

        $dados = $request->validate([
            'novo' => ['required', 'boolean'],
            'papel' => ['required', 'integer'],
            'utilizador' => ['exclude_if:novo,true', 'required', 'integer', 'exists:users,id'],
            'nome' => ['exclude_if:novo,false', 'required', 'string', 'min:3', 'max:190'],
            'email' => ['exclude_if:novo,false', 'required', 'email', 'max:190', 'unique:users,email'],
            'telefone' => ['exclude_if:novo,false', 'nullable', 'string', 'max:40'],
            'senha' => ['exclude_if:novo,false', 'required', 'string', 'min:6'],
        ], [], [
            'papel' => __('papel'),
            'utilizador' => __('utilizador'),
            'senha' => __('senha'),
        ]);

        if (! $t->cabeMaisUmUtilizador()) {
            throw ValidationException::withMessages([
                'utilizador' => __('Esta empresa já tem :n utilizador(es) e o limite é :l. Aumente o limite na ficha da empresa ou mude o plano.', [
                    'n' => $t->users()->count(), 'l' => $t->limiteDeUtilizadores(),
                ]),
            ]);
        }

        // O PAPEL VALIDA-SE ANTES DE CRIAR SEJA O QUE FOR: recusar depois de o
        // utilizador existir deixava uma conta órfã sem permissões.
        $papel = $this->papelDaEmpresa($dados['papel'], $t->id);

        if (! $papel) {
            throw ValidationException::withMessages(['papel' => __('Escolha um papel desta empresa.')]);
        }

        if (! $dados['novo']) {
            $pessoa = User::findOrFail($dados['utilizador']);

            if ($pessoa->tenants()->where('tenants.id', $t->id)->exists()) {
                throw ValidationException::withMessages(['utilizador' => __('Esta pessoa já pertence a esta empresa.')]);
            }

            if (! $pessoa->is_super_admin && ! $pessoa->canAddMoreCompanies()) {
                throw ValidationException::withMessages([
                    'utilizador' => __('Esta pessoa já gere :n empresa(s) e o plano dela permite :m.', [
                        'n' => $pessoa->tenants()->count(), 'm' => $pessoa->getMaxCompaniesLimit(),
                    ]),
                ]);
            }
        }

        $pessoa = DB::transaction(function () use ($dados, $t, $papel) {
            $pessoa = $dados['novo']
                ? User::create([
                    'name' => $dados['nome'],
                    'email' => $dados['email'],
                    'phone' => $dados['telefone'] ?? null,
                    'password' => Hash::make($dados['senha']),
                    'is_active' => true,
                ])
                : User::findOrFail($dados['utilizador']);

            $pessoa->tenants()->attach($t->id, ['is_active' => true, 'joined_at' => now()]);

            setPermissionsTeamId($t->id);
            $pessoa->assignRole($papel);

            return $pessoa;
        });

        if ($dados['novo']) {
            // EMAIL E SMS DEPOIS DA RESPOSTA: um SMTP lento fazia parecer que a
            // criação tinha falhado, com o utilizador já criado. A senha passa
            // por valor ao fecho e não fica em sítio nenhum depois disto.
            $senha = $dados['senha'];
            $telefone = $dados['telefone'] ?? null;

            defer(function () use ($pessoa, $senha, $telefone, $t) {
                app(AvisosDeConta::class)->credenciais($pessoa, $senha, $t);

                if ($telefone) {
                    try {
                        (new \App\Services\SmsService())->sendNewAccountSms($pessoa, $senha, $t);
                    } catch (\Throwable $e) {
                        Log::error('SMS de boas-vindas não enviado', ['user_id' => $pessoa->id, 'erro' => $e->getMessage()]);
                    }
                }
            });

            return response()->json([
                'message' => __('Utilizador criado. As credenciais seguem por email:sms.', [
                    'sms' => $telefone ? __(' e SMS') : '',
                ]),
            ], 201);
        }

        return response()->json(['message' => __(':nome juntou-se à empresa.', ['nome' => $pessoa->name])], 201);
    }

    public function papel(Request $request, int $empresa, int $utilizador): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);
        $pessoa = $t->users()->where('users.id', $utilizador)->firstOrFail();

        // O PAPEL TEM DE SER DESTA EMPRESA. O `Role::find()` que aqui estava
        // aceitava o id de um papel de OUTRA — e dava a alguém as permissões
        // definidas por uma empresa diferente.
        $papel = $this->papelDaEmpresa($request->input('papel'), $t->id);

        if (! $papel) {
            throw ValidationException::withMessages(['papel' => __('Esse papel não pertence a esta empresa.')]);
        }

        setPermissionsTeamId($t->id);
        $pessoa->roles()->wherePivot('tenant_id', $t->id)->detach();
        $pessoa->assignRole($papel);

        return response()->json(['message' => __('Papel de :nome passou a :papel.', ['nome' => $pessoa->name, 'papel' => $papel->name])]);
    }

    public function retirar(int $empresa, int $utilizador): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);
        $pessoa = $t->users()->where('users.id', $utilizador)->firstOrFail();

        // NÃO DEIXAR A EMPRESA SEM NINGUÉM. Tirar o último utilizador deixava a
        // empresa sem forma de lá entrar.
        if ($t->users()->count() <= 1) {
            throw ValidationException::withMessages([
                'utilizador' => __('Esta é a última pessoa com acesso a esta empresa. Junte outra antes de a retirar, ou desactive a empresa.'),
            ]);
        }

        setPermissionsTeamId($t->id);
        $pessoa->roles()->wherePivot('tenant_id', $t->id)->detach();
        $pessoa->tenants()->detach($t->id);

        return response()->json(['message' => __(':nome saiu da empresa.', ['nome' => $pessoa->name])]);
    }

    /**
     * O papel indicado pertence mesmo a esta empresa? O id vem do browser, e o
     * que vem do browser filtra-se sempre pela empresa.
     */
    private function papelDaEmpresa($papelId, int $empresaId): ?Role
    {
        if (! $papelId) {
            return null;
        }

        return Role::where('id', $papelId)->where('tenant_id', $empresaId)->first();
    }
}
