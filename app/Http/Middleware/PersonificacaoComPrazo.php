<?php

namespace App\Http\Middleware;

use App\Services\Plataforma\Personificacao;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A PERSONIFICAÇÃO TEM PRAZO E TEM LIMITES — verificados a cada pedido.
 *
 * Sem pedido nenhum de personificação na sessão, isto é uma leitura de sessão
 * e mais nada.
 *
 * O PRAZO: duas horas. Um separador esquecido aberto na casa de um cliente é
 * uma porta aberta com o nome dele; passado o prazo, o pedido seguinte volta à
 * conta do admin e a trilha regista `personificacao.expirou`.
 *
 * A PESSOA: desactivada a meio, ou retirada da empresa, a personificação acaba
 * — e acaba AQUI, antes do SaiQuemFoiDesactivado, que de outro modo punha fora
 * a sessão inteira: o admin pagava pela conta do cliente.
 *
 * OS LIMITES: há coisas que ninguém faz em nome de outra pessoa, nem o dono da
 * plataforma — ver PROIBIDAS.
 */
class PersonificacaoComPrazo
{
    /**
     * O QUE NUNCA SE FAZ DURANTE A PERSONIFICAÇÃO — por nome de rota.
     *
     * A lista é uma só e vive aqui. A regra: tudo o que é a CREDENCIAL da
     * pessoa (senha, email com que entra, PIN de turno, tokens de API) é dela.
     * Mudá-lo em nome dela é ficar com a conta — e é exactamente o que um
     * suporte nunca pode poder, mesmo com a trilha a registar.
     *
     *  · conta.senha / conta.perfil — a senha, e o perfil porque é lá que se
     *    muda o EMAIL, que é o nome com que se entra e para onde vai a
     *    recuperação da senha;
     *  · react.pin.definir — o PIN de turno do próprio, que é a senha do POS
     *    sem rede;
     *  · password.* — pedir e repor a senha pelo email;
     *  · api.auth.login — é a porta que CRIA tokens de API (a app móvel): um
     *    token não caduca com as duas horas;
     *  · casca.empresa / conta.empresas.activar — mudar de empresa. A entrada
     *    foi autorizada e auditada NUMA empresa; saltar para outra da mesma
     *    pessoa era entrar nela sem ter pedido. Volta-se à plataforma e entra-se
     *    na outra.
     *
     * @var array<string, string> rota => frase da recusa
     */
    private const PROIBIDAS = [
        'api.invoicing.react.conta.senha' => 'Durante a personificação não se muda a senha.',
        'api.invoicing.react.conta.perfil' => 'Durante a personificação não se mudam o email nem os dados de acesso.',
        'api.invoicing.react.pin.definir' => 'Durante a personificação não se muda o PIN de turno.',
        'password.email' => 'Durante a personificação não se repõe a senha.',
        'password.update' => 'Durante a personificação não se repõe a senha.',
        'password.confirm' => 'Durante a personificação não se repõe a senha.',
        'api.auth.login' => 'Durante a personificação não se criam tokens de API.',
        'api.casca.empresa' => 'Durante a personificação não se muda de empresa. Volte à plataforma e entre na outra.',
        'api.invoicing.react.conta.empresas.activar' => 'Durante a personificação não se muda de empresa. Volte à plataforma e entre na outra.',
    ];

    /**
     * Portas sem nome que dão no mesmo (o POST de `password/confirm` do
     * Auth::routes não tem nome). Por caminho, com a frase da mesma família.
     *
     * @var array<string, string>
     */
    private const PROIBIDAS_POR_CAMINHO = [
        'password/*' => 'Durante a personificação não se repõe a senha.',
    ];

    /**
     * O QUE NÃO SE FAZ À PRÓPRIA PESSOA PERSONIFICADA — no ecrã dos
     * utilizadores, onde o `{id}` é quem se edita. Sobre os COLEGAS continua a
     * valer o que o papel da pessoa deixar (é para isso que se entra), sobre
     * ela própria não: é mudar-lhe a senha e o email pela porta do lado, apagar
     * ou desactivar a conta, ou mudar-lhe o PIN.
     *
     * @var array<string, string>
     */
    private const PROIBIDAS_SOBRE_A_PESSOA = [
        'api.invoicing.react.utilizadores.guardar' => 'Durante a personificação não se mudam o email nem os dados de acesso.',
        'api.invoicing.react.utilizadores.apagar' => 'Durante a personificação não se apaga a conta de quem se personifica.',
        'api.invoicing.react.utilizadores.estado' => 'Durante a personificação não se desactiva a conta de quem se personifica.',
        'api.invoicing.react.utilizadores.pin' => 'Durante a personificação não se muda o PIN de turno.',
    ];

    /**
     * O QUE O REVENDEDOR NÃO FAZ, além de tudo o que o admin também não faz
     * (21/09/2026).
     *
     * O revendedor é de fora da plataforma, e a autorização da empresa é o que
     * o deixa entrar. Estas portas abririam-lhe um acesso que sobrevive a essa
     * autorização: criar ou convidar uma conta com o email dele, mudar o email
     * de um colega para o dele, reactivar uma conta antiga, subir-se de papel.
     * Depois bastava entrar pela porta da frente — fora das duas horas, e
     * mesmo depois de a empresa desligar a autorização.
     *
     * E não abre a porta a si próprio: o interruptor da autorização fica com
     * quem gere a empresa.
     *
     * @var array<string, string>
     */
    private const PROIBIDAS_AO_REVENDEDOR = [
        'api.invoicing.react.utilizadores.criar' => 'O revendedor não cria contas na empresa. Peça a quem a gere.',
        'api.invoicing.react.utilizadores.convidar' => 'O revendedor não convida pessoas para a empresa. Peça a quem a gere.',
        'api.invoicing.react.utilizadores.reenviar' => 'O revendedor não convida pessoas para a empresa. Peça a quem a gere.',
        'api.invoicing.react.utilizadores.guardar' => 'O revendedor não muda os dados de acesso das pessoas da empresa.',
        'api.invoicing.react.utilizadores.estado' => 'O revendedor não activa nem desactiva contas da empresa.',
        'api.invoicing.react.utilizadores.pin' => 'O revendedor não muda PINs de turno.',
        'api.invoicing.react.papeis.criar' => 'O revendedor não mexe nos papéis e permissões da empresa.',
        'api.invoicing.react.papeis.guardar' => 'O revendedor não mexe nos papéis e permissões da empresa.',
        'api.invoicing.react.papeis.atribuir' => 'O revendedor não mexe nos papéis e permissões da empresa.',
        'api.invoicing.react.empresa.suporte-do-revendedor.definir' => 'A autorização do revendedor é decidida por quem gere a empresa.',
    ];

    /**
     * Sair do sistema, durante a personificação, é voltar à plataforma: o
     * «Sair» do menu não pode fechar a sessão do ADMIN sem registar a saída.
     */
    private const SAIDAS = ['logout', 'invoicing.offline.sair'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        // O super admin OU um revendedor. Olhar só para a chave do admin deixava
        // o revendedor sem prazo e sem a lista do que é proibido.
        $personificacao = app(Personificacao::class);

        if (! $personificacao->activa()) {
            return $next($request);
        }

        // Tem de se saber ANTES de terminar: o terminar apaga as chaves.
        $doRevendedor = $personificacao->doRevendedor();
        $voltouA = $doRevendedor ? __('Voltou ao seu portal de revendedor.') : __('Voltou à sua conta.');

        if ($personificacao->expirou()) {
            $personificacao->terminar($request, 'personificacao.expirou', [
                'minutos' => Personificacao::DURACAO_EM_MINUTOS,
            ]);

            return $this->acabou($request, $personificacao, __('A personificação terminou: passaram as 2 horas.') . ' ' . $voltouA);
        }

        if (! $personificacao->pessoaAindaServe()) {
            $personificacao->terminar($request, 'personificacao.saiu', ['motivo' => 'pessoa_indisponivel']);

            return $this->acabou($request, $personificacao, __('A personificação terminou: a pessoa foi desactivada ou saiu da empresa.') . ' ' . $voltouA);
        }

        // A empresa desligou a autorização a meio, ou deixou de ser dele: sai
        // já, e não às duas horas por já lá estar dentro.
        if (! $personificacao->empresaAindaPermite()) {
            $personificacao->terminar($request, 'personificacao.saiu', ['motivo' => 'autorizacao_retirada']);

            return $this->acabou($request, $personificacao, __('A empresa retirou a autorização do revendedor.') . ' ' . $voltouA);
        }

        $rota = $request->route()?->getName();

        if ($rota !== null && in_array($rota, self::SAIDAS, true)) {
            $personificacao->sair($request, 'sair_do_menu');

            return $this->acabou($request, $personificacao, $doRevendedor ? $voltouA : __('Voltou à plataforma.'), 200);
        }

        if ($doRevendedor && $rota !== null && isset(self::PROIBIDAS_AO_REVENDEDOR[$rota])) {
            return response()->json(['message' => __(self::PROIBIDAS_AO_REVENDEDOR[$rota])], 403);
        }

        if ($recusa = $this->recusa($request, $rota, $personificacao)) {
            return response()->json(['message' => __($recusa)], 403);
        }

        // A empresa fica presa à da entrada. As trocas estão fechadas acima;
        // isto apanha o resto (uma sessão que outro separador mexeu).
        $empresa = $personificacao->empresaId();

        if ($empresa !== null && (int) $request->session()->get('active_tenant_id') !== $empresa) {
            $request->session()->put('active_tenant_id', $empresa);
            setPermissionsTeamId($empresa);
        }

        return $next($request);
    }

    private function recusa(Request $request, ?string $rota, Personificacao $personificacao): ?string
    {
        if ($rota !== null && isset(self::PROIBIDAS[$rota])) {
            return self::PROIBIDAS[$rota];
        }

        foreach (self::PROIBIDAS_POR_CAMINHO as $caminho => $frase) {
            if ($request->is($caminho) && ! $request->isMethodSafe()) {
                return $frase;
            }
        }

        $pessoa = $personificacao->utilizadorPersonificadoId();

        if ($rota !== null && isset(self::PROIBIDAS_SOBRE_A_PESSOA[$rota])
            && (int) $request->route()?->parameter('id') === $pessoa) {
            return self::PROIBIDAS_SOBRE_A_PESSOA[$rota];
        }

        // O PIN reposto sem rede chega pela fila com o `user_id` no corpo.
        if ($rota === 'api.invoicing.pin.repor' && (int) $request->input('user_id') === $pessoa) {
            return 'Durante a personificação não se muda o PIN de turno.';
        }

        return null;
    }

    /**
     * A resposta de quem já não está personificado: um pedido da API recebe a
     * frase e para onde ir (e a faixa do topo leva o browser para lá); uma
     * página vai direita à plataforma, com o recado.
     */
    private function acabou(Request $request, Personificacao $personificacao, string $mensagem, int $estadoJson = 401): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $mensagem,
                'personificacao_terminou' => true,
                'seguir_para' => $personificacao->destino(),
            ], $estadoJson);
        }

        return redirect()->to($personificacao->destino())->with('warning', $mensagem);
    }
}
