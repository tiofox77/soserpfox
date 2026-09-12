<?php

namespace App\Http\Controllers\Api\Conta;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscriptions\DireitoACortesia;
use App\Support\ContaDaPlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A MINHA CONTA: as empresas, o plano, as facturas, o perfil e a senha.
 *
 * O QUE ESTAVA PARTIDO E NINGUÉM VIA: o direito de EDITAR e ELIMINAR uma
 * empresa era decidido por `pivot.role_id == 2` — um número escrito à mão. Cada
 * empresa cria os seus próprios papéis, com os seus próprios ids; o papel de
 * dono é o 2 na primeira empresa da base e é outro em todas as seguintes. Quem
 * tivesse três empresas via os botões numa e não via em nenhuma das outras.
 *
 * A regra certa já existia ao lado, em `isTenantOwner()`: o papel diz-se pelo
 * NOME («Super Admin», «Owner», «Dono»), não pelo número.
 */
class MinhaContaApiController extends Controller
{
    private const PAPEIS_DE_DONO = ['Super Admin', 'Owner', 'Dono'];

    private function eu(Request $request): User
    {
        return $request->user();
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /** Quem gere a conta: o dono ou um administrador. */
    private function exigirGestao(Request $request): void
    {
        abort_unless(
            $this->eu($request)->canManageAccount(),
            403,
            __('Sem permissão para gerir o plano e a facturação desta conta.'),
        );
    }

    /**
     * ESTE UTILIZADOR É DONO DESTA EMPRESA?
     *
     * Pelo NOME do papel, e não pelo número. Era esta a linha que faltava nos
     * três sítios que decidiam a edição e a remoção de uma empresa.
     */
    private function ehDono(User $u, int $tenantId): bool
    {
        if ($u->is_super_admin) {
            return true;
        }

        $papelId = $u->tenants()->where('tenants.id', $tenantId)->value('tenant_user.role_id');

        return $papelId !== null && DB::table('roles')
            ->where('id', $papelId)
            ->whereIn('name', self::PAPEIS_DE_DONO)
            ->exists();
    }

    private function minhaEmpresa(Request $request, int $id): Tenant
    {
        $t = Tenant::find($id);

        abort_if(! $t, 404, __('Empresa não encontrada.'));
        abort_unless($this->eu($request)->belongsToTenant($id) || $this->eu($request)->is_super_admin, 404);

        return $t;
    }

    /* ─── O retrato da conta ──────────────────────────────────────────── */

    public function mostrar(Request $request): JsonResponse
    {
        $u = $this->eu($request);
        $gere = $u->canManageAccount();
        $activa = $u->activeTenant();

        $maximo = $u->getMaxCompaniesLimit();
        $quantas = $u->tenants()->count();

        $empresas = $u->tenants()->withPivot('role_id', 'is_active', 'joined_at')->get();

        return response()->json([
            'perfil' => [
                'nome' => $u->name,
                'email' => $u->email,
                'telefone' => $u->phone,
                'bio' => $u->bio,
                'avatar' => $u->avatar ? Storage::url($u->avatar) : null,
                'super_admin' => (bool) $u->is_super_admin,
                'ultimo_acesso' => $u->last_login_at?->format('Y-m-d H:i'),
                'senha_mudada_em' => $u->last_password_changed?->format('Y-m-d H:i'),
            ],
            'limite' => [
                'usadas' => $quantas,
                'maximo' => $maximo >= 999 ? null : $maximo,
                'excedido' => ! $u->is_super_admin && $quantas > $maximo,
                'cabe_mais' => $u->is_super_admin || $quantas < $maximo,
            ],
            'empresas' => $empresas->map(fn (Tenant $t) => $this->linhaDaEmpresa($request, $t))->values(),
            'plano' => $this->planoActual($activa),
            'planos' => $gere ? $this->planosDisponiveis($u, $activa) : [],
            'facturas' => $gere ? $this->facturas($activa) : [],
            'pedidos' => $gere ? $this->pedidos($u) : [],
            'conta_da_plataforma' => $gere ? ContaDaPlataforma::dados() : null,
            'permissoes' => ['gerir_conta' => $gere],
        ]);
    }

    private function linhaDaEmpresa(Request $request, Tenant $t): array
    {
        $subscricao = $t->activeSubscription;
        $papel = $t->pivot?->role_id
            ? DB::table('roles')->where('id', $t->pivot->role_id)->value('name')
            : null;

        return [
            'id' => $t->id,
            'nome' => $t->name,
            'designacao' => $t->company_name,
            'nif' => $t->nif,
            'email' => $t->email,
            'telefone' => $t->phone,
            'morada' => $t->address,
            'regime' => Tenant::canonicalRegime($t->regime),
            'logo' => $t->logo ? Storage::url($t->logo) : null,
            'activa' => (int) $t->id === (int) activeTenantId(),
            'utilizadores' => $t->users()->count(),
            'modulos' => $t->modules()->wherePivot('is_active', true)->count(),
            'plano' => $subscricao?->plan?->name,
            'papel' => $papel ?? __('Utilizador'),
            // O DIREITO DE MEXER vem do NOME do papel, não de um número.
            'sou_dono' => $this->ehDono($this->eu($request), (int) $t->id),
            'desde' => $t->pivot?->joined_at ? \Carbon\Carbon::parse($t->pivot->joined_at)->format('Y-m-d') : null,
        ];
    }

    private function planoActual(?Tenant $empresa): ?array
    {
        $s = $empresa?->activeSubscription;

        if (! $s || ! $s->plan) {
            return null;
        }

        $fim = $s->current_period_end;
        $dias = $fim ? (int) ceil(now()->floatDiffInDays($fim, false)) : null;

        return [
            'nome' => $s->plan->name,
            'preco' => (float) $s->plan->price_monthly,
            'max_utilizadores' => $s->plan->max_users,
            'max_empresas' => $s->plan->max_companies >= 999 ? null : $s->plan->max_companies,
            'funcionalidades' => $s->plan->features ?? [],
            'estado' => $s->status,
            'em_teste' => $s->status === 'trial',
            'ciclo' => $s->billing_cycle,
            'ciclo_rotulo' => \App\Support\CicloDeFacturacao::nome($s->billing_cycle),
            'termina_em' => $fim?->format('Y-m-d'),
            'dias_que_faltam' => $dias,
            // Dez dias é a antecedência com que o aviso passa a valer a pena.
            'a_terminar' => $dias !== null && $dias <= 10,
            'dias_de_teste' => $s->plan->trial_days,
        ];
    }

    private function planosDisponiveis(User $u, ?Tenant $empresa): array
    {
        $direito = $empresa
            ? DireitoACortesia::daEmpresa($empresa, $u)
            : DireitoACortesia::de($u);

        return Plan::publico()->with('modules')->orderBy('price_monthly')->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'descricao' => $p->description,
                'destaque' => (bool) $p->is_featured,
                'precos' => [
                    'monthly' => (float) $p->price_monthly,
                    'quarterly' => (float) $p->price_quarterly,
                    'semiannual' => (float) $p->price_semiannual,
                    'yearly' => (float) $p->price_yearly,
                ],
                'max_utilizadores' => $p->max_users,
                'max_empresas' => $p->max_companies >= 999 ? null : $p->max_companies,
                'funcionalidades' => $p->features ?? [],
                'modulos' => $p->modules->pluck('name')->values(),
                'dias_de_teste' => (int) $p->trial_days,
                'auto_activa' => (bool) $p->auto_activate,
                'actual' => $empresa?->activeSubscription?->plan_id === $p->id,
                /*
                 * A CORTESIA É UMA SÓ, PARA SEMPRE.
                 *
                 * Plano gratuito ou período de teste: o primeiro que se usar
                 * gasta o direito ao outro. Antes olhava-se só para o MESMO
                 * plano na MESMA empresa, e bastava criar outra empresa — ou
                 * saltar do FOX Friendly para os 30 dias do Business e daí para
                 * os do Enterprise — para a casa continuar a não pagar.
                 */
                'recusa' => $direito->motivoParaRecusar($p),
            ])->values()->all();
    }

    /**
     * AS FACTURAS DA SUBSCRIÇÃO — o que a plataforma cobra a esta empresa.
     *
     * O separador mostrava só PEDIDOS, e um pedido só existe quando se
     * contrata. As renovações emitem factura sem pedido nenhum, pelo que o
     * cliente recebia a conta do período seguinte e não tinha onde a ver.
     */
    private function facturas(?Tenant $empresa): array
    {
        if (! $empresa) {
            return [];
        }

        return Invoice::where('tenant_id', $empresa->id)
            ->whereNotNull('subscription_id')
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->limit(12)->get()
            ->map(fn (Invoice $f) => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'descricao' => $f->description ?: __('Subscrição'),
                'total' => (float) $f->total,
                'dia' => $f->invoice_date?->format('Y-m-d'),
                'vence_em' => $f->due_date?->format('Y-m-d'),
                'estado' => $f->status,
                'vencida' => $f->status !== 'paid' && $f->due_date && $f->due_date->isPast(),
            ])->values()->all();
    }

    private function pedidos(User $u): array
    {
        return Order::with(['tenant:id,name', 'plan:id,name'])
            ->where('user_id', $u->id)
            ->latest()->limit(50)->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'numero' => $o->order_number ?: 'ORD-'.str_pad((string) $o->id, 6, '0', STR_PAD_LEFT),
                'empresa' => $o->tenant?->name,
                'plano' => $o->plan?->name,
                'ciclo' => $o->billing_cycle,
                'ciclo_rotulo' => \App\Support\CicloDeFacturacao::nome($o->billing_cycle ?? 'monthly'),
                'valor' => (float) $o->amount,
                'estado' => $o->status,
                'estado_rotulo' => match ($o->status) {
                    'pending' => __('Pendente'), 'approved' => __('Aprovado'),
                    'processing' => __('Em processamento'), 'completed' => __('Concluído'),
                    'paid' => __('Pago'), 'cancelled' => __('Cancelado'),
                    'failed' => __('Falhou'), 'refunded' => __('Reembolsado'),
                    default => $o->status,
                },
                'tem_comprovativo' => filled($o->payment_proof),
                'comprovativo' => $o->payment_proof ? Storage::url($o->payment_proof) : null,
                'referencia' => ContaDaPlataforma::referencia((int) $o->user_id, (int) $o->id),
                'quando' => $o->created_at?->format('Y-m-d H:i'),
            ])->values()->all();
    }

    /* ─── As empresas ─────────────────────────────────────────────────── */

    public function criarEmpresa(Request $request): JsonResponse
    {
        $this->exigirGestao($request);

        $u = $this->eu($request);
        $maximo = $u->getMaxCompaniesLimit();

        if (! $u->is_super_admin && $u->tenants()->count() >= $maximo) {
            $this->recusa(__('Limite de empresas atingido: o seu plano permite :n. Mude de plano ou remova uma.', [
                'n' => $maximo,
            ]));
        }

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'nif' => ['required', new \App\Rules\NifDeEmpresa()],
            'regime' => ['required', Rule::in(array_merge(
                array_keys(Tenant::REGIMES), array_keys(Tenant::REGIME_ALIASES),
            ))],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['name' => __('nome'), 'nif' => __('NIF'), 'regime' => __('regime fiscal')]);

        $empresa = DB::transaction(function () use ($dados, $u) {
            $actual = $u->activeTenant();
            $subscricao = $actual?->activeSubscription;
            $modulos = $actual ? $actual->modules()->wherePivot('is_active', true)->get() : collect();

            $t = Tenant::create([
                'name' => $dados['name'],
                'company_name' => $dados['name'],
                'nif' => $dados['nif'],
                'regime' => $dados['regime'],
                'address' => $dados['address'] ?? null,
                'phone' => $dados['phone'] ?? null,
                'email' => ($dados['email'] ?? null) ?: $u->email,
                'is_active' => true,
            ]);

            createDefaultRolesForTenant($t->id);
            initializeAccountingDataForTenant($t->id);

            $dono = \Spatie\Permission\Models\Role::where('name', 'Super Admin')
                ->where('tenant_id', $t->id)->first();

            $u->tenants()->attach($t->id, [
                'role_id' => $dono?->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            if ($dono) {
                setPermissionsTeamId($t->id);
                $u->assignRole($dono);
            }

            /*
             * A SUBSCRIÇÃO REPLICA-SE COM AS MESMAS DATAS.
             *
             * Uma empresa nova com o período a contar do zero desalinhava as
             * renovações: a casa passava a receber duas facturas em dias
             * diferentes pelo mesmo plano.
             */
            if ($subscricao && $subscricao->plan) {
                $t->subscriptions()->create([
                    'plan_id' => $subscricao->plan_id,
                    'status' => $subscricao->status,
                    'billing_cycle' => $subscricao->billing_cycle,
                    'amount' => $subscricao->amount,
                    'current_period_start' => $subscricao->current_period_start,
                    'current_period_end' => $subscricao->current_period_end,
                    'ends_at' => $subscricao->ends_at,
                    'trial_ends_at' => $subscricao->trial_ends_at,
                ]);
            }

            // Os módulos passam pelo serviço, para a empresa nova nascer com as
            // dependências (Facturação ⇒ Tesouraria), os pré-requisitos
            // (métodos de pagamento, impostos, armazém) e as permissões.
            if ($modulos->isNotEmpty()) {
                $sync = new \App\Services\Tenant\TenantModuleSyncService();

                foreach ($modulos as $m) {
                    $sync->activateModule($t, $m->slug);
                }
            }

            return $t;
        });

        // Depois do commit, e não dentro dele: um e-mail não se desfaz com um
        // rollback, e mandá-lo antes de a empresa estar mesmo gravada era
        // arriscar avisar de uma criação que afinal não aconteceu.
        try {
            app(\App\Services\Plataforma\AvisoDeNovaEmpresa::class)
                ->confirmarAoResponsavel($empresa, $this->eu($request));
        } catch (\Throwable $e) {
            \Log::error('Aviso de nova empresa não saiu', ['tenant' => $empresa->id, 'erro' => $e->getMessage()]);
        }

        return response()->json([
            'message' => __('Empresa criada com o mesmo plano e os mesmos módulos.'),
            'data' => ['id' => $empresa->id, 'nome' => $empresa->name],
        ], 201);
    }

    public function editarEmpresa(Request $request, int $id): JsonResponse
    {
        $t = $this->minhaEmpresa($request, $id);

        // O DONO, PELO NOME DO PAPEL. Era aqui o `role_id == 2`.
        abort_unless($this->ehDono($this->eu($request), $id), 403,
            __('Só o dono da empresa pode alterar os dados dela.'));

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            // A MESMA REGRA DA CRIAÇÃO. Aqui era `min:9|max:14`, que deixava
            // passar o número do bilhete de identidade.
            'nif' => ['required', new \App\Rules\NifDeEmpresa()],
            'regime' => ['required', Rule::in(array_merge(
                array_keys(Tenant::REGIMES), array_keys(Tenant::REGIME_ALIASES),
            ))],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['name' => __('nome'), 'nif' => __('NIF'), 'regime' => __('regime fiscal')]);

        $anterior = $t->regime;
        $novo = $dados['regime'];

        $t->update([
            'name' => $dados['name'],
            'company_name' => $dados['name'],
            'nif' => $dados['nif'],
            'regime' => $novo,
            'address' => $dados['address'] ?? null,
            'phone' => $dados['phone'] ?? null,
            'email' => ($dados['email'] ?? null) ?: $this->eu($request)->email,
        ]);

        $recado = __('Empresa actualizada.');

        if (Tenant::canonicalRegime($anterior) !== Tenant::canonicalRegime($novo)) {
            $r = (new \App\Services\Tenant\TaxRegimeSyncer())->sync($t->fresh(), $anterior);

            if (! empty($r['changed'])) {
                $recado .= ' '.__('Regime aplicado: :regime.', [
                    'regime' => Tenant::REGIMES[Tenant::canonicalRegime($novo)]['label'],
                ]);
            }
        }

        return response()->json(['message' => $recado]);
    }

    public function logotipo(Request $request, int $id): JsonResponse
    {
        $t = $this->minhaEmpresa($request, $id);

        abort_unless($this->ehDono($this->eu($request), $id), 403,
            __('Só o dono da empresa pode alterar os dados dela.'));

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,svg', 'max:2048'],
        ], [
            'logo.image' => __('O logótipo tem de ser uma imagem.'),
            'logo.max' => __('O logótipo não pode passar de 2 MB.'),
        ]);

        if ($t->logo && Storage::disk('public')->exists($t->logo)) {
            Storage::disk('public')->delete($t->logo);
        }

        $caminho = $request->file('logo')->store('logos', 'public');

        $t->update(['logo' => $caminho]);

        return response()->json([
            'message' => __('Logótipo actualizado.'),
            'logo' => Storage::url($caminho),
        ]);
    }

    public function apagarLogotipo(Request $request, int $id): JsonResponse
    {
        $t = $this->minhaEmpresa($request, $id);

        abort_unless($this->ehDono($this->eu($request), $id), 403,
            __('Só o dono da empresa pode alterar os dados dela.'));

        if ($t->logo && Storage::disk('public')->exists($t->logo)) {
            Storage::disk('public')->delete($t->logo);
        }

        $t->update(['logo' => null]);

        return response()->json(['message' => __('Logótipo removido.')]);
    }

    /** O que impede — ou não — arquivar esta empresa. */
    public function podeArquivar(Request $request, int $id): JsonResponse
    {
        $t = $this->minhaEmpresa($request, $id);
        $u = $this->eu($request);

        $razoes = [];

        if (! $this->ehDono($u, $id)) {
            $razoes[] = __('Só o dono da empresa a pode remover.');
        }

        if ($u->tenants()->count() <= 1) {
            $razoes[] = __('Não pode remover a sua única empresa.');
        }

        $verificacao = $t->canBeArchivedByOwner();

        if (! ($verificacao['can_delete'] ?? false)) {
            $razoes[] = $verificacao['reason'].' ('.__(':n factura(s) emitida(s)', [
                'n' => $verificacao['invoices_count'] ?? 0,
            ]).')';
        }

        /*
         * O NÚMERO DE CLIENTES é um AVISO, não um impedimento.
         *
         * O ecrã antigo procurava `App\Models\Invoicing\Client` — uma classe
         * que não existe — dentro de um `class_exists()`, pelo que o aviso
         * nunca chegava a aparecer. O cliente é `App\Models\Client`, e a
         * pergunta é sempre a esta empresa.
         */
        $clientes = \App\Models\Client::withoutGlobalScopes()->where('tenant_id', $id)->count();

        return response()->json([
            'pode' => $razoes === [],
            'razoes' => $razoes,
            'nome' => $t->name,
            // Não impede — avisa. Os dados ficam arquivados e recuperáveis.
            'clientes' => $clientes,
        ]);
    }

    public function arquivarEmpresa(Request $request, int $id): JsonResponse
    {
        $t = $this->minhaEmpresa($request, $id);
        $u = $this->eu($request);

        $dados = $request->validate([
            'confirmacao' => ['required', 'string'],
        ]);

        abort_unless($this->ehDono($u, $id), 403, __('Só o dono da empresa a pode remover.'));

        if ($u->tenants()->count() <= 1) {
            $this->recusa(__('Não pode remover a sua única empresa.'));
        }

        // ESCREVER O NOME é a única confirmação que não se carrega por engano.
        if (trim($dados['confirmacao']) !== trim($t->name)) {
            throw ValidationException::withMessages([
                'confirmacao' => [__('Escreva exactamente o nome da empresa para confirmar.')],
            ]);
        }

        $verificacao = $t->canBeArchivedByOwner();

        if (! ($verificacao['can_delete'] ?? false)) {
            $this->recusa($verificacao['reason'].' ('.__(':n factura(s) emitida(s)', [
                'n' => $verificacao['invoices_count'] ?? 0,
            ]).')');
        }

        $eraActiva = (int) activeTenantId() === (int) $t->id;

        // SOFT DELETE: os dados e as ligações ficam recuperáveis. A cascata
        // destrutiva do modelo só corre num `forceDelete`.
        $t->delete();

        $trocou = null;

        if ($eraActiva) {
            $primeira = $u->tenants()->first();

            if ($primeira) {
                $u->switchTenant($primeira->id);
                $trocou = $primeira->id;
            }
        }

        return response()->json([
            'message' => __('Empresa removida da conta e arquivada em segurança.'),
            // O contexto inteiro mudou (empresa, permissões, menu): o ecrã tem
            // de aterrar numa página NOVA, e não recarregar a que tinha.
            'trocou_para' => $trocou,
        ]);
    }

    public function trocarDeEmpresa(Request $request, int $id): JsonResponse
    {
        $u = $this->eu($request);

        if (! $u->is_super_admin && $u->tenants()->count() > $u->getMaxCompaniesLimit()) {
            $this->recusa(__('Limite excedido: o plano permite :max empresas e tem :n. Mude de plano ou remova uma.', [
                'max' => $u->getMaxCompaniesLimit(), 'n' => $u->tenants()->count(),
            ]));
        }

        if (! $u->switchTenant($id)) {
            $this->recusa(__('Não foi possível passar para esta empresa.'));
        }

        return response()->json(['message' => __('Empresa activa alterada.')]);
    }

    /* ─── O perfil ────────────────────────────────────────────────────── */

    public function perfil(Request $request): JsonResponse
    {
        $u = $this->eu($request);

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($u->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => __('nome'), 'email' => __('e-mail')]);

        $u->update($dados);

        return response()->json(['message' => __('Perfil actualizado.')]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $u = $this->eu($request);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
        ], [
            'avatar.image' => __('A fotografia tem de ser uma imagem.'),
            'avatar.max' => __('A fotografia não pode passar de 2 MB.'),
        ]);

        if ($u->avatar && Storage::disk('public')->exists($u->avatar)) {
            Storage::disk('public')->delete($u->avatar);
        }

        $caminho = $request->file('avatar')->store('avatars', 'public');

        $u->update(['avatar' => $caminho]);

        return response()->json([
            'message' => __('Fotografia actualizada.'),
            'avatar' => Storage::url($caminho),
        ]);
    }

    public function apagarAvatar(Request $request): JsonResponse
    {
        $u = $this->eu($request);

        if ($u->avatar && Storage::disk('public')->exists($u->avatar)) {
            Storage::disk('public')->delete($u->avatar);
        }

        $u->update(['avatar' => null]);

        return response()->json(['message' => __('Fotografia removida.')]);
    }

    public function senha(Request $request): JsonResponse
    {
        $u = $this->eu($request);

        $dados = $request->validate([
            'actual' => ['required', 'string'],
            'nova' => ['required', 'string', 'min:8', 'different:actual', 'confirmed'],
            'nova_confirmation' => ['required'],
        ], [
            'nova.min' => __('A senha nova tem de ter pelo menos 8 caracteres.'),
            'nova.different' => __('A senha nova tem de ser diferente da actual.'),
            'nova.confirmed' => __('As duas senhas não coincidem.'),
        ]);

        if (! Hash::check($dados['actual'], $u->password)) {
            throw ValidationException::withMessages(['actual' => [__('A senha actual não está certa.')]]);
        }

        $u->update([
            'password' => Hash::make($dados['nova']),
            'last_password_changed' => now(),
        ]);

        return response()->json(['message' => __('Senha alterada.')]);
    }

    /* ─── O plano ─────────────────────────────────────────────────────── */

    public function contratar(Request $request): JsonResponse
    {
        $this->exigirGestao($request);

        $dados = $request->validate([
            'plan_id' => ['required', 'integer'],
            'ciclo' => ['required', Rule::in(['monthly', 'quarterly', 'semiannual', 'yearly'])],
            'comprovativo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'comprovativo.mimes' => __('O comprovativo tem de ser PDF, JPG ou PNG.'),
            'comprovativo.max' => __('O comprovativo não pode passar de 5 MB.'),
        ]);

        $u = $this->eu($request);
        $empresa = $u->activeTenant();

        if (! $empresa) {
            $this->recusa(__('Não há empresa activa.'));
        }

        $plano = Plan::publico()->find($dados['plan_id']);

        if (! $plano) {
            $this->recusa(__('Plano não encontrado.'));
        }

        // DEFESA DUPLA: o ecrã pode ser contornado, e o pedido vem de fora.
        $direito = DireitoACortesia::daEmpresa($empresa, $u);

        if ($recusa = $direito->motivoParaRecusar($plano)) {
            $this->recusa($recusa);
        }

        $valor = match ($dados['ciclo']) {
            'yearly' => $plano->price_yearly,
            'semiannual' => $plano->price_semiannual,
            'quarterly' => $plano->price_quarterly,
            default => $plano->price_monthly,
        };

        $pedido = DB::transaction(function () use ($dados, $request, $plano, $empresa, $u, $valor, $direito) {
            $comprovativo = $request->hasFile('comprovativo')
                ? $request->file('comprovativo')->store('payment-proofs', 'public')
                : null;

            /*
             * O PERÍODO DE TESTE SÓ SE DÁ A QUEM O TEM POR GASTAR.
             *
             * A quem já o usou, o pedido segue o caminho normal: transferência,
             * comprovativo, aprovação.
             */
            $auto = (bool) $plano->auto_activate
                && (int) $plano->trial_days > 0
                && ! $comprovativo
                && $direito->temDireitoATeste($plano);

            $pedido = Order::create([
                'tenant_id' => $empresa->id,
                'user_id' => $u->id,
                'plan_id' => $plano->id,
                'amount' => $valor,
                'billing_cycle' => $dados['ciclo'],
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'payment_proof' => $comprovativo,
                'notes' => $auto
                    ? "Pedido auto-aprovado. Plano '{$plano->name}' com {$plano->trial_days} dias de teste."
                    : null,
            ]);

            if ($auto) {
                // O `OrderObserver::updated` despacha o resto: cancelar a
                // subscrição antiga, criar a nova e sincronizar os módulos.
                $pedido->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => null]);
            }

            $pedido->auto = $auto;

            return $pedido;
        });

        if ($pedido->auto ?? false) {
            return response()->json([
                'message' => __('Plano :plano activado com :n dias gratuitos.', [
                    'plano' => $plano->name, 'n' => $plano->trial_days,
                ]),
                'activado' => true,
            ], 201);
        }

        return response()->json([
            'message' => $pedido->payment_proof
                ? __('Pedido criado com o comprovativo anexado. Aguarde a validação (até 24 horas úteis).')
                : __('Pedido criado. Faça a transferência e anexe o comprovativo em «Pagar».'),
            'activado' => false,
            'pedido' => $pedido->id,
        ], 201);
    }

    /** O comprovativo de um pedido que já existe. */
    public function comprovativo(Request $request, int $id): JsonResponse
    {
        $this->exigirGestao($request);

        $request->validate([
            'comprovativo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'comprovativo.mimes' => __('O comprovativo tem de ser PDF, JPG ou PNG.'),
            'comprovativo.max' => __('O comprovativo não pode passar de 5 MB.'),
        ]);

        // O PEDIDO É DE QUEM O FEZ: um id escrito à mão não anexa nada ao
        // pedido de outra pessoa.
        $pedido = Order::where('user_id', $this->eu($request)->id)->findOrFail($id);

        if ($pedido->status !== 'pending') {
            $this->recusa(__('Este pedido já não está à espera de pagamento.'));
        }

        $pedido->update([
            'payment_proof' => $request->file('comprovativo')->store('payment-proofs', 'public'),
        ]);

        return response()->json(['message' => __('Comprovativo anexado. Aguarde a validação.')]);
    }
}
