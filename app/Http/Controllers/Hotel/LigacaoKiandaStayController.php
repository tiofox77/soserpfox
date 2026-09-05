<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\LigacaoKiandaStay;
use App\Services\Hotel\KiandaStay;
use Illuminate\Http\Request;

/**
 * A volta do «Entrar com o KiandaStay».
 *
 * O hoteleiro autorizou no site e voltou aqui com um bilhete de dez minutos.
 * Troca-se por um token que vale só para a casa dele, e a ligação fica feita —
 * sem ninguém ter copiado chave nenhuma.
 */
class LigacaoKiandaStayController extends Controller
{
    public function retorno(Request $request)
    {
        $ligacao = LigacaoKiandaStay::paraTenant(activeTenantId());

        $codigo = (string) $request->query('code', '');
        $estado = (string) $request->query('state', '');

        /*
         * O `state` é o que prova que esta volta corresponde à ida DESTA sessão.
         * Sem ele, bastava mandar ao utilizador um endereço com um código de
         * outra autorização para lhe ligar a casa de outra pessoa.
         */
        $esperado = session()->pull('kiandastay_state');

        if (! $codigo || ! $esperado || ! hash_equals($esperado, $estado)) {
            return redirect()->route('hotel.kiandastay')
                ->with('error', __('A autorização não pôde ser confirmada. Tente ligar outra vez.'));
        }

        $r = KiandaStay::para($ligacao)->trocarCodigo($codigo);

        if (! $r['ok']) {
            return redirect()->route('hotel.kiandastay')
                ->with('error', __('Não foi possível concluir a ligação: :erro', ['erro' => $r['erro']]));
        }

        return redirect()->route('hotel.kiandastay')
            ->with('success', __('Ligado ao KiandaStay. As reservas de :hotel passam a entrar sozinhas.', [
                'hotel' => $ligacao->fresh()->property_name ?: __('a sua casa'),
            ]));
    }
}
