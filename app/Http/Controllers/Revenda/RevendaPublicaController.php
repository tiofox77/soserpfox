<?php

namespace App\Http\Controllers\Revenda;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Revenda\AvisosDaRevenda;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Support\Seguranca\RegraDaSenha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * O LADO PÚBLICO DO PROGRAMA DE REVENDEDORES (RV-02, RV-05, RV-06).
 */
class RevendaPublicaController extends Controller
{
    /**
     * O LINK DE AFILIADO — /r/CÓDIGO.
     *
     * Guarda o código durante 60 dias e leva ao site. Um código que não é de um
     * revendedor aprovado leva ao site na mesma, sem guardar nada: o visitante
     * não tem culpa de um link velho.
     *
     * Desde 22/09/2026 o cookie NÃO preenche o registo: é o cliente que escreve
     * o código. Serve só para a ligação ficar marcada «por link» quando o
     * código escrito é o mesmo do link.
     */
    public function link(Request $request, string $codigo): RedirectResponse
    {
        $revendedor = LigacaoAoRevendedor::porCodigo($codigo);
        $destino = redirect()->to(url('/'));

        if (! $revendedor) {
            return $destino;
        }

        return $destino->withCookie(cookie(
            LigacaoAoRevendedor::COOKIE,
            $revendedor->code,
            LigacaoAoRevendedor::DIAS_DO_COOKIE * 24 * 60,
            null, null, $request->isSecure(), true, false, 'lax',
        ));
    }

    /** O campo do registo confirma o nome do revendedor antes de criar a conta. */
    public function verificarCodigo(Request $request): JsonResponse
    {
        $dados = $request->validate(['codigo' => ['required', 'string', 'max:20']]);
        $revendedor = LigacaoAoRevendedor::porCodigo($dados['codigo']);

        if (! $revendedor) {
            return response()->json(['message' => __('Não encontramos nenhum revendedor com este código.')], 404);
        }

        return response()->json(['codigo' => $revendedor->code, 'nome' => $revendedor->nomeVisivel()]);
    }

    /** O PEDIDO PARA SER REVENDEDOR — fica por aprovar. */
    public function pedir(Request $request): JsonResponse
    {
        // O campo escondido: uma pessoa não o vê nem o preenche; um robot sim.
        if (filled($request->input('site_da_empresa'))) {
            return response()->json(['message' => __('Recebemos o seu pedido. Vamos analisá-lo e respondemos por email.')], 201);
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'nif' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150', Rule::unique('resellers', 'email')],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'province' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'motivation' => ['required', 'string', 'min:10', 'max:2000'],
            'password' => ['required', 'string', 'confirmed', RegraDaSenha::regra()],
            'aceito_termos' => ['accepted'],
        ], [
            'email.unique' => __('Já existe um pedido de revendedor com este email.'),
            'motivation.required' => __('Conte-nos como pensa revender: os seus clientes, a zona, a experiência.'),
            'aceito_termos.accepted' => __('Aceite os Termos de Serviço e a Política de Privacidade para continuar.'),
        ], [
            'name' => __('Nome'),
            'phone' => __('Telefone'),
            'motivation' => __('Como pensa revender'),
            'password' => __('Senha'),
        ]);

        unset($dados['aceito_termos']);
        $revendedor = Reseller::create($dados);

        app(AvisosDaRevenda::class)->pedidoRecebido($revendedor);

        return response()->json(['message' => __('Recebemos o seu pedido. Vamos analisá-lo e respondemos por email.')], 201);
    }
}
