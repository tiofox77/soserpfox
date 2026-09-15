<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Tenant;
use App\Support\Seguranca\TravaoDeEntradas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * ENTRAR NO PORTAL DO CLIENTE.
 *
 * As mesmas regras do componente — só entra quem tem o portal ligado e a ficha
 * activa, e fica registada a hora da entrada — e uma que faltava: TENTATIVAS
 * LIMITADAS. O formulário aceitava tentativas sem fim; com a senha gerada pela
 * empresa e o email do cliente à vista numa factura, era uma porta aberta a
 * quem quisesse adivinhar.
 *
 * O MESMO EMAIL EM VÁRIAS EMPRESAS (15/09/2026). A entrada procurava «o»
 * cliente com este email — e o MySQL devolvia um qualquer. Quem era cliente de
 * duas empresas nunca entrava na segunda: a senha era conferida contra a ficha
 * da primeira, e cada tentativa contava para o bloqueio. Agora confere-se a
 * senha em TODAS as fichas com o email; se bater em mais de uma, o cliente
 * escolhe a empresa (e só entre essas — nunca se mostram empresas onde a senha
 * não bateu). Uma empresa desactivada não entra na lista.
 *
 * EMAIL, TELEFONE OU NOME DE UTILIZADOR (15/09/2026). Um campo só: com «@» é
 * o email; só com algarismos (e espaços, +, -) é o telefone, comparado já
 * normalizado (`portal_phone`); o resto é o nome de utilizador que a empresa
 * criou. As mesmas regras para os três — senha conferida em todas as fichas,
 * escolha da empresa, tentativas limitadas por identificador e IP.
 */
class EntradaNoPortalController extends Controller
{
    /** Quanto tempo vale a escolha da empresa depois de acertar a senha. */
    private const MINUTOS_PARA_ESCOLHER = 5;

    public function entrar(Request $request): JsonResponse
    {
        // `login` é o campo novo; `email` continua a servir a quem ainda o manda.
        $request->merge(['login' => trim((string) ($request->input('login') ?? $request->input('email') ?? ''))]);
        $dados = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ], ['login.required' => __('Escreva o email, o telefone ou o nome de utilizador.')]);
        $identificador = mb_strtolower($dados['login']);

        // A regra das três portas: 5 falhas fecham 10 minutos, e 20 falhas do
        // mesmo IP em quaisquer emails também (TravaoDeEntradas).
        $travao = TravaoDeEntradas::para('portal-cliente');

        if ($segundos = $travao->bloqueadoPor($identificador, $request->ip())) {
            throw ValidationException::withMessages([
                'login' => TravaoDeEntradas::mensagemDeBloqueio($segundos),
            ])->status(429);
        }

        $fichas = $this->fichasQueAbrem($dados['login'], $dados['password']);

        if ($fichas->isEmpty()) {
            $restam = $travao->falhou($identificador, $request->ip());

            throw ValidationException::withMessages([
                'login' => TravaoDeEntradas::mensagemDeFalha($restam, __('As credenciais fornecidas não correspondem aos nossos registros ou você não tem acesso ao portal.')),
            ])->status($restam === 0 ? 429 : 422);
        }

        $travao->entrou($identificador, $request->ip());

        if ($fichas->count() === 1) {
            return $this->abrir($request, $fichas->first(), (bool) ($dados['remember'] ?? false));
        }

        // Várias empresas: guarda-se QUAIS, e o cliente escolhe uma delas.
        $request->session()->put('portal.escolha', [
            'ids' => $fichas->pluck('id')->all(),
            'lembrar' => (bool) ($dados['remember'] ?? false),
            'ate' => now()->addMinutes(self::MINUTOS_PARA_ESCOLHER)->timestamp,
        ]);

        $nomes = Tenant::whereIn('id', $fichas->pluck('tenant_id'))->get()->keyBy('id');

        return response()->json([
            'message' => __('Escolha a empresa.'),
            'escolher' => $fichas->map(fn (Client $c) => [
                'id' => $c->id,
                'empresa' => $nomes[$c->tenant_id]?->nomeParaDocumentos() ?: $nomes[$c->tenant_id]?->name,
                // O mesmo telefone em duas fichas da mesma empresa: o nome desfaz a dúvida.
                'cliente' => $c->name,
            ])->values(),
        ]);
    }

    public function escolherEmpresa(Request $request): JsonResponse
    {
        $dados = $request->validate(['id' => ['required', 'integer']]);
        $escolha = $request->session()->get('portal.escolha');

        if (! $escolha || ($escolha['ate'] ?? 0) < now()->timestamp || ! in_array((int) $dados['id'], $escolha['ids'] ?? [], true)) {
            $request->session()->forget('portal.escolha');

            throw ValidationException::withMessages([
                'login' => __('A escolha expirou. Volte a escrever os dados de entrada e a senha.'),
            ]);
        }

        $cliente = Client::withoutGlobalScopes()->whereKey($dados['id'])
            ->where('portal_access', true)->where('is_active', true)->first();

        if (! $cliente || ! Tenant::whereKey($cliente->tenant_id)->where('is_active', true)->exists()) {
            $request->session()->forget('portal.escolha');

            throw ValidationException::withMessages(['login' => __('O acesso a este portal não está disponível. Contacte a empresa.')]);
        }

        $request->session()->forget('portal.escolha');

        return $this->abrir($request, $cliente, (bool) ($escolha['lembrar'] ?? false));
    }

    /**
     * As fichas com este email, telefone ou nome de utilizador, portal ligado,
     * activas, de empresa activa — e em que a senha bate.
     *
     * @return Collection<int, Client>
     */
    private function fichasQueAbrem(string $identificador, string $senha): Collection
    {
        $consulta = Client::withoutGlobalScopes();

        if (str_contains($identificador, '@')) {
            $consulta->where('email', $identificador);
        } elseif (\App\Support\TelefoneDoPortal::pareceTelefone($identificador)) {
            $consulta->where('portal_phone', \App\Support\TelefoneDoPortal::normalizar($identificador));
        } else {
            $consulta->where('portal_username', \App\Support\TelefoneDoPortal::utilizador($identificador));
        }

        return $consulta
            ->where('portal_access', true)
            ->where('is_active', true)
            ->whereNotNull('password')
            ->whereIn('tenant_id', Tenant::where('is_active', true)->select('id'))
            ->orderBy('id')
            ->get()
            ->filter(fn (Client $c) => Hash::check($senha, (string) $c->password))
            ->values();
    }

    private function abrir(Request $request, Client $cliente, bool $lembrar): JsonResponse
    {
        Auth::guard('client')->login($cliente, $lembrar);
        $cliente->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return response()->json([
            'message' => __('Bem-vindo!'),
            'ir_para' => redirect()->intended(route('client.dashboard'))->getTargetUrl(),
        ]);
    }
}
