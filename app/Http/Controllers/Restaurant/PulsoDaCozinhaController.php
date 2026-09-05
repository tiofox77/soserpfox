<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\KitchenTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O pulso da cozinha: mudou alguma coisa desde a última vez?
 *
 * PORQUE EXISTE. O ecrã da cozinha refazia-se de 15 em 15 segundos, sempre,
 * estivesse ou não a acontecer alguma coisa. Duas contas más ao mesmo tempo:
 *
 *   · ATRASO — um prato podia ficar até quinze segundos à espera de ser visto.
 *     Num serviço cheio, quinze segundos por prato é uma fila que se acumula.
 *   · PESO — cada ecrã aberto refazia a consulta grande (bilhetes, artigos,
 *     comanda, mesa, posto) e a página inteira, de quinze em quinze segundos,
 *     numa cozinha parada às três da tarde.
 *
 * Isto responde à pergunta barata: uma agregação sobre uma tabela, sem
 * carregar relação nenhuma. O ecrã pergunta de três em três segundos e só
 * manda refazer a página quando a resposta muda. O atraso cai de 15s para 3s
 * E o trabalho cai a quase nada quando não há nada a acontecer.
 *
 * NÃO SE USA WEBSOCKET de propósito: esta aplicação também corre em casa do
 * cliente, sem internet e sem servidor de eventos. Uma solução que exigisse
 * Pusher funcionava na nuvem e deixava a cozinha do on-premise como estava.
 */
class PulsoDaCozinhaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = activeTenantId();

        $pulso = KitchenTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($request->integer('station'), fn ($q, $id) => $q->where('station_id', $id))
            ->whereIn('status', ['queued', 'accepted', 'preparing', 'ready'])
            // A contagem apanha bilhetes novos e bilhetes que saíram; o
            // `MAX(updated_at)` apanha os que mudaram de estado sem sair. Um
            // sem o outro deixava passar metade das mudanças.
            ->selectRaw('COUNT(*) quantos, COALESCE(MAX(updated_at), 0) ultimo')
            ->first();

        return response()->json([
            'pulso' => substr(md5($pulso->quantos.'|'.$pulso->ultimo), 0, 12),
            'bilhetes' => (int) $pulso->quantos,
        ])->header('Cache-Control', 'no-store');
    }
}
