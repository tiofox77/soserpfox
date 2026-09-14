<?php

namespace App\Services\Plataforma;

use App\Http\Middleware\SessaoSoDeLeitura;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A PERSONIFICAÇÃO — o dono da plataforma entra numa empresa COMO uma pessoa
 * dessa empresa, com as permissões dela.
 *
 * PORQUÊ COMO ALGUÉM, E NÃO «COMO ELE PRÓPRIO». O super admin não tem papéis
 * nas empresas dos clientes, e não há `Gate::before` que lhe abra as portas:
 * entrar como ele próprio dava uma empresa onde nada se podia ver. Entrar como
 * o dono (ou como a pessoa que ligou para o suporte) mostra EXACTAMENTE o que
 * essa pessoa vê — que é o que se quer quando se vai ajudar.
 *
 * O que antes havia era uma chave de sessão (`impersonate_tenant_id`) que
 * ninguém lia: o `activeTenant()` só aceita empresas a que o utilizador
 * pertence, e o super admin voltava sempre à sua. A trilha dizia «entrou» de
 * uma entrada que nunca aconteceu.
 *
 * AS REGRAS, TODAS AQUI (é a fonte única — o controlador e o middleware só
 * perguntam):
 *
 *  · só o super admin DA PLATAFORMA entra (`isPlatformSuperAdmin`, nunca o
 *    `is_super_admin` sozinho nem o papel «Super Admin» de uma empresa);
 *  · nunca como outro super admin da plataforma, nem como si próprio, nem
 *    dentro de outra personificação;
 *  · a pessoa tem de estar activa na conta E naquela empresa;
 *  · dura no máximo DUAS HORAS (ver PersonificacaoComPrazo);
 *  · tudo fica na trilha: o momento da entrada, a saída com a duração, e cada
 *    acto pelo meio leva o `impersonator_id` (ver AuditRecorder).
 */
class Personificacao
{
    /** Quanto tempo pode durar, no máximo. Passado isto, sai-se sozinho. */
    public const DURACAO_EM_MINUTOS = 120;

    /**
     * A chave que o AuditRecorder já lia — por isso não muda de nome: é ela
     * que põe o par «quem fez / em nome de quem» em cada linha da trilha.
     */
    public const CHAVE_DO_ADMIN = 'impersonator_id';

    /** O resto do estado: empresa, pessoa, desde quando e para onde voltar. */
    public const CHAVE = 'personificacao';

    /** Para onde se volta depois da última saída feita por esta instância. */
    private ?string $destino = null;

    public function __construct(private AuditRecorder $auditoria) {}

    public function activa(): bool
    {
        return session()->has(self::CHAVE_DO_ADMIN);
    }

    /* ─── Entrar ──────────────────────────────────────────────────────── */

    /**
     * @throws AuthorizationException  se quem pede não é super admin da plataforma
     * @throws ValidationException     se a pessoa não serve ou já há personificação
     */
    public function entrar(Request $request, User $admin, Tenant $empresa, ?int $utilizadorId = null): User
    {
        if (! $admin->isPlatformSuperAdmin()) {
            throw new AuthorizationException(__('Só o super admin da plataforma pode entrar em nome de alguém.'));
        }

        // Personificação dentro de personificação apagava o rasto de quem
        // abriu a primeira porta: o `impersonator_id` passava a ser o alvo.
        if ($this->activa()) {
            throw ValidationException::withMessages([
                'personificacao' => [__('Já está a personificar alguém. Volte à plataforma antes de entrar noutra empresa.')],
            ]);
        }

        $alvo = $this->escolherAlvo($empresa, $admin, $utilizadorId);

        $sessao = $request->session();
        $voltarA = $this->origemSegura($request);
        $empresaDoAdmin = $sessao->get('active_tenant_id');

        // Sessão nova a cada mudança de identidade, e a velha APAGADA (o `true`):
        // um id de sessão que alguém tivesse apanhado antes não herda o acesso
        // à empresa do cliente.
        $sessao->regenerate(true);
        $sessao->forget('impersonate_tenant_id');

        $sessao->put(self::CHAVE_DO_ADMIN, $admin->id);
        $sessao->put(self::CHAVE, [
            'empresa_id' => $empresa->id,
            'utilizador_id' => $alvo->id,
            'desde' => now()->getTimestamp(),
            'voltar_a' => $voltarA,
            'empresa_do_admin' => $empresaDoAdmin,
        ]);

        $this->autenticarSemEvento($request, $alvo);

        $sessao->put('active_tenant_id', $empresa->id);
        setPermissionsTeamId($empresa->id);
        $alvo->unsetRelation('roles')->unsetRelation('permissions');

        /*
         * A ENTRADA VAI PARA A TRILHA DEPOIS DE A SESSÃO JÁ SER A DO ALVO.
         *
         * O gravador tira o actor do momento em que o acto é registado: assim a
         * linha sai com `user_id` = a pessoa e `impersonator_id` = o admin — a
         * mesma forma de todos os actos que se seguem, e o ecrã da auditoria
         * lê a entrada e o resto da mesma maneira.
         */
        $this->auditoria->acto('personificacao.entrou', $empresa->id, [
            'admin' => ['id' => $admin->id, 'nome' => $admin->name, 'email' => $admin->email],
            'utilizador' => ['id' => $alvo->id, 'nome' => $alvo->name, 'email' => $alvo->email],
            'empresa' => $empresa->name,
            'ip' => $request->ip(),
            'termina_em' => now()->addMinutes(self::DURACAO_EM_MINUTOS)->toIso8601String(),
        ], $empresa);

        $this->gravarSessaoMesmoNumaLeitura($request);

        return $alvo;
    }

    /**
     * Em nome de quem se entra.
     *
     * Pedido: tem de pertencer à empresa. Por omissão: o DONO — quem tem o
     * papel «Super Admin» DESSA empresa (o papel da empresa e a atribuição na
     * empresa, as duas) — porque é quem vê tudo o que a empresa tem. Sem dono
     * activo, a primeira pessoa activa que sirva.
     */
    private function escolherAlvo(Tenant $empresa, User $admin, ?int $utilizadorId): User
    {
        if ($utilizadorId !== null) {
            $alvo = $empresa->users()->where('users.id', $utilizadorId)->first();

            if (! $alvo) {
                throw ValidationException::withMessages([
                    'utilizador_id' => [__('Esta pessoa não pertence a esta empresa.')],
                ]);
            }

            if ($motivo = $this->porQueNaoServe($alvo, $admin)) {
                throw ValidationException::withMessages(['utilizador_id' => [$motivo]]);
            }

            return $alvo;
        }

        $pessoas = $empresa->users()->orderBy('users.id')->get();
        $donos = $this->donos($empresa, $pessoas->pluck('id')->all());

        $servem = $pessoas->filter(fn (User $u) => $this->porQueNaoServe($u, $admin) === null);

        $alvo = $servem->first(fn (User $u) => in_array($u->id, $donos, true)) ?? $servem->first();

        if (! $alvo) {
            throw ValidationException::withMessages([
                'utilizador_id' => [__('Esta empresa não tem nenhum utilizador activo em nome de quem entrar.')],
            ]);
        }

        return $alvo;
    }

    /**
     * Null se a pessoa serve; senão, a frase que o diz. O modal mostra a mesma
     * frase ao lado de quem não se pode escolher.
     *
     * @param  User  $pessoa  carregada pela relação da empresa (traz o pivot)
     */
    public function porQueNaoServe(User $pessoa, User $admin): ?string
    {
        if ($pessoa->id === $admin->id) {
            return __('É a sua própria conta.');
        }

        // Entrar como outro dono da plataforma era herdar poderes que não se
        // têm por esta porta — o painel inteiro, com outro nome na trilha.
        if ($pessoa->isPlatformSuperAdmin()) {
            return __('É super admin da plataforma.');
        }

        if ($pessoa->is_active === false) {
            return __('A conta está desactivada.');
        }

        if (isset($pessoa->pivot) && ! (bool) $pessoa->pivot->is_active) {
            return __('Está desactivado nesta empresa.');
        }

        return null;
    }

    /**
     * Quem é dono desta empresa: o papel «Super Admin» da empresa, atribuído
     * na empresa. Os dois `tenant_id` — um papel global atribuído dentro de
     * uma empresa é outra conversa (ver User::isPlatformSuperAdmin).
     *
     * @param  array<int>  $ids
     * @return array<int>
     */
    private function donos(Tenant $empresa, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', User::class)
            ->where('mhr.tenant_id', $empresa->id)
            ->where('r.tenant_id', $empresa->id)
            ->where('r.name', 'Super Admin')
            ->whereIn('mhr.model_id', $ids)
            ->pluck('mhr.model_id')
            ->map(fn ($id) => (int) $id)
            ->unique()->values()->all();
    }

    /**
     * As pessoas de uma empresa, para o modal de escolher em nome de quem se
     * entra — com o dono marcado e, em quem não serve, o porquê.
     */
    public function pessoasDaEmpresa(Tenant $empresa, User $admin): array
    {
        /** @var Collection<int, User> $pessoas */
        $pessoas = $empresa->users()->orderBy('name')->get();
        $ids = $pessoas->pluck('id')->all();
        $donos = $this->donos($empresa, $ids);

        // Os papéis de todos numa consulta — não um `getRoleNames()` por pessoa.
        $papeis = $ids === [] ? collect() : DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', User::class)
            ->where('mhr.tenant_id', $empresa->id)
            ->whereIn('mhr.model_id', $ids)
            ->orderBy('r.name')
            ->get(['mhr.model_id', 'r.name'])
            ->groupBy('model_id');

        return $pessoas->map(function (User $u) use ($admin, $donos, $papeis) {
            $motivo = $this->porQueNaoServe($u, $admin);

            return [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'papeis' => collect($papeis->get($u->id, []))->pluck('name')->unique()->values()->all(),
                'activo' => $u->is_active !== false && (bool) $u->pivot->is_active,
                'dono' => in_array($u->id, $donos, true),
                'super_admin_da_plataforma' => $u->isPlatformSuperAdmin(),
                'pode_entrar' => $motivo === null,
                'motivo' => $motivo,
            ];
        })
            // O dono primeiro: é quem se escolhe quase sempre.
            ->sortBy(fn (array $p) => [$p['dono'] ? 0 : 1, $p['pode_entrar'] ? 0 : 1, mb_strtolower((string) $p['nome'])])
            ->values()->all();
    }

    /* ─── Sair ────────────────────────────────────────────────────────── */

    /**
     * Voltar à conta do admin.
     *
     * Devolve o admin — ou null quando ele já não pode voltar (apagado,
     * desactivado, deixou de ser super admin da plataforma entretanto): nesse
     * caso a sessão acaba de vez, porque devolver-lhe a conta era devolver um
     * acesso que já lhe tiraram.
     *
     * @throws ValidationException se não há personificação nenhuma
     */
    public function sair(Request $request, string $motivo = 'pedido'): ?User
    {
        if (! $this->activa()) {
            throw ValidationException::withMessages([
                'personificacao' => [__('Não está a personificar ninguém.')],
            ]);
        }

        return $this->terminar($request, 'personificacao.saiu', ['motivo' => $motivo]);
    }

    /**
     * Termina a personificação, por pedido ou porque tinha de acabar.
     *
     * O acto vai para a trilha ANTES de trocar de conta, pela mesma razão da
     * entrada: sai com o par pessoa/admin, que é o par de tudo o que se fez.
     */
    public function terminar(Request $request, string $evento, array $extra = []): ?User
    {
        $sessao = $request->session();
        $dados = (array) $sessao->get(self::CHAVE, []);
        $adminId = $sessao->get(self::CHAVE_DO_ADMIN);
        $desde = isset($dados['desde']) ? (int) $dados['desde'] : null;

        if ($adminId !== null) {
            $this->auditoria->acto($evento, isset($dados['empresa_id']) ? (int) $dados['empresa_id'] : null, array_merge([
                'admin' => ['id' => (int) $adminId],
                'utilizador' => ['id' => isset($dados['utilizador_id']) ? (int) $dados['utilizador_id'] : null],
                'duracao_em_segundos' => $desde !== null ? max(0, now()->getTimestamp() - $desde) : null,
                'ip' => $request->ip(),
            ], $extra));
        }

        $admin = $adminId !== null ? User::find($adminId) : null;
        $this->destino = $this->destinoSeguro($dados['voltar_a'] ?? null);

        $sessao->forget([self::CHAVE_DO_ADMIN, self::CHAVE, 'impersonate_tenant_id']);
        $this->gravarSessaoMesmoNumaLeitura($request);

        if (! $admin || $admin->is_active === false || ! $admin->isPlatformSuperAdmin()) {
            // Sem conta a que voltar, não se fica com a do cliente: fora.
            $sessao->invalidate();
            $sessao->regenerateToken();
            Auth::guard('web')->forgetUser();
            setPermissionsTeamId(null);
            $this->destino = route('login');

            return null;
        }

        $this->autenticarSemEvento($request, $admin);

        $empresaDoAdmin = $dados['empresa_do_admin'] ?? null;
        $empresaDoAdmin ? $sessao->put('active_tenant_id', $empresaDoAdmin) : $sessao->forget('active_tenant_id');
        setPermissionsTeamId($empresaDoAdmin);

        // A sessão da personificação não fica guardada para trás: quem tivesse
        // o id dela continuava a ser a pessoa, com o admin marcado.
        $sessao->regenerate(true);

        return $admin;
    }

    /** Para onde mandar o browser depois da última saída. */
    public function destino(): string
    {
        return $this->destino ?? route('superadmin.dashboard');
    }

    /* ─── O que se sabe durante ───────────────────────────────────────── */

    /** O que a faixa do topo mostra. Null fora de uma personificação. */
    public function estado(): ?array
    {
        if (! $this->activa()) {
            return null;
        }

        $dados = (array) session(self::CHAVE, []);
        $admin = User::find(session(self::CHAVE_DO_ADMIN));
        $empresa = isset($dados['empresa_id']) ? Tenant::find($dados['empresa_id']) : null;
        $pessoa = isset($dados['utilizador_id']) ? User::find($dados['utilizador_id']) : null;
        $desde = Carbon::createFromTimestamp((int) ($dados['desde'] ?? 0), config('app.timezone'));

        return [
            'activa' => true,
            'admin' => $admin ? ['id' => $admin->id, 'nome' => $admin->name] : null,
            'empresa' => $empresa ? ['id' => $empresa->id, 'nome' => $empresa->name, 'activa' => (bool) $empresa->is_active] : null,
            'utilizador' => $pessoa ? ['id' => $pessoa->id, 'nome' => $pessoa->name, 'email' => $pessoa->email] : null,
            'desde' => $desde->toIso8601String(),
            'expira_em' => $desde->copy()->addMinutes(self::DURACAO_EM_MINUTOS)->toIso8601String(),
        ];
    }

    /** Passaram as duas horas? Sem hora de início também conta como passado. */
    public function expirou(): bool
    {
        $desde = session(self::CHAVE . '.desde');

        return $desde === null
            || now()->getTimestamp() - (int) $desde >= self::DURACAO_EM_MINUTOS * 60;
    }

    /**
     * A pessoa em nome de quem se está continua a poder estar lá?
     *
     * Desactivada a meio, retirada da empresa, ou a sessão aponta para outra
     * pessoa que não a que se escolheu: a personificação acaba.
     */
    public function pessoaAindaServe(): bool
    {
        $dados = (array) session(self::CHAVE, []);
        $quem = Auth::guard('web')->user();

        if (! $quem instanceof User || ! isset($dados['utilizador_id'], $dados['empresa_id'])) {
            return false;
        }

        if ($quem->id !== (int) $dados['utilizador_id'] || $quem->is_active === false) {
            return false;
        }

        return DB::table('tenant_user')
            ->where('user_id', $quem->id)
            ->where('tenant_id', (int) $dados['empresa_id'])
            ->where('is_active', true)
            ->exists();
    }

    public function utilizadorPersonificadoId(): ?int
    {
        $id = session(self::CHAVE . '.utilizador_id');

        return $id !== null ? (int) $id : null;
    }

    public function empresaId(): ?int
    {
        $id = session(self::CHAVE . '.empresa_id');

        return $id !== null ? (int) $id : null;
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * Trocar a conta da sessão SEM o evento de login.
     *
     * O `Auth::login()` dispara o `Login`, e a trilha escrevia «login» da
     * pessoa — que não entrou, nem sabe a senha de ninguém. A entrada verdadeira
     * já fica como `personificacao.entrou`. Também não se toca no «lembrar-me»:
     * o cookie que existir é o do admin, e é para ele que deve voltar.
     */
    private function autenticarSemEvento(Request $request, User $quem): void
    {
        /** @var \Illuminate\Auth\SessionGuard $guarda */
        $guarda = Auth::guard('web');

        $request->session()->put($guarda->getName(), $quem->getAuthIdentifier());
        $guarda->setUser($quem);
    }

    /**
     * Um GET à API não grava a sessão (SessaoSoDeLeitura). Mas quando a
     * personificação acaba num GET — o prazo que passa enquanto o topo
     * pergunta — a mudança TEM de ficar gravada, senão o pedido seguinte
     * voltava a ser a pessoa, e a sessão regenerada ficava sem dono.
     */
    private function gravarSessaoMesmoNumaLeitura(Request $request): void
    {
        $request->attributes->set(SessaoSoDeLeitura::GRAVAR, true);
    }

    /**
     * De onde veio o pedido de entrada, para lá voltar à saída — só se for uma
     * página da plataforma. Um Referer é do browser; não se segue para fora.
     */
    private function origemSegura(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return null;
        }

        $partes = parse_url($referer);

        if (! $partes || (isset($partes['host']) && $partes['host'] !== $request->getHost())) {
            return null;
        }

        $caminho = $partes['path'] ?? '';

        return $this->destinoSeguro($caminho . (isset($partes['query']) ? '?' . $partes['query'] : ''));
    }

    private function destinoSeguro(?string $caminho): ?string
    {
        if (! is_string($caminho) || ! str_starts_with($caminho, '/superadmin/') || str_contains($caminho, '//')) {
            return null;
        }

        return $caminho;
    }
}
