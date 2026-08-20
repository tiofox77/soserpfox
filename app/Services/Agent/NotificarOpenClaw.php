<?php

namespace App\Services\Agent;

use App\Models\ErroDoSistema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Empurra os erros novos para o agente externo, para ele avisar quem é preciso.
 *
 * O agente já pode PERGUNTAR (GET status/digest, GET logs/errors). Isto é o
 * outro sentido: quando o sistema parte às três da manhã, não se espera que
 * alguém se lembre de perguntar.
 *
 * O QUE NÃO PODE ACONTECER AQUI
 * -----------------------------
 * · RECURSÃO. Um pedido HTTP que falha escreve no log; essa escrita cria um
 *   erro; esse erro quer ser notificado; a notificação falha outra vez. É um
 *   ciclo que só pára quando o servidor cair. Por isso a chamada HTTP corre
 *   com o registo de erros SUSPENSO, e a falha nunca é escrita como erro.
 * · BLOQUEAR. Corre no fim do pedido, depois de a resposta seguir, com um
 *   tempo de espera curto. Ninguém fica à espera do agente externo.
 * · RAJADA. Uma avaria que produza cinquenta problemas distintos em dois
 *   minutos não pode virar cinquenta mensagens. Há tecto por passagem e um
 *   intervalo mínimo entre passagens.
 *
 * A ASSINATURA
 * ------------
 * O corpo vai assinado com HMAC-SHA256 sobre `timestamp.corpo`, no cabeçalho
 * `X-Soserp-Signature`. É o que permite ao agente saber que a mensagem veio
 * mesmo daqui e não de quem descobriu o URL dele.
 */
class NotificarOpenClaw
{
    /** Enquanto isto for verdade, o RegistoDeErros não grava nada. */
    private static bool $aNotificar = false;

    public static function estaANotificar(): bool
    {
        return self::$aNotificar;
    }

    /**
     * Manda os erros por notificar.
     *
     * @return array{enviados:int, erros:int, motivo:?string}
     */
    public function empurrar(): array
    {
        $url = (string) config('agent.erros.webhook_url', '');

        if (!config('agent.erros.notificar', false) || $url === '') {
            return ['enviados' => 0, 'erros' => 0, 'motivo' => 'desligado'];
        }

        $porNotificar = ErroDoSistema::abertos()
            ->whereNull('notificado_em')
            ->orderByDesc('ultima_vez')
            ->limit((int) config('agent.erros.max_por_passagem', 10))
            ->get();

        if ($porNotificar->isEmpty()) {
            return ['enviados' => 0, 'erros' => 0, 'motivo' => 'nada novo'];
        }

        $corpo = [
            'evento'    => 'erros.novos',
            'plataforma' => config('app.name', 'SOS ERP'),
            'url'       => config('app.url'),
            'quando'    => now()->toIso8601String(),
            'total'     => $porNotificar->count(),
            'erros'     => $porNotificar->map(fn ($e) => $e->paraOAgente())->all(),
        ];

        $enviou = $this->postar($url, $corpo);

        if (!$enviou) {
            return ['enviados' => 0, 'erros' => $porNotificar->count(), 'motivo' => 'o agente não respondeu'];
        }

        // Marcar só DEPOIS de o agente confirmar. Ao contrário, uma falha de
        // rede deixava o erro por avisar para sempre.
        DB::table('erros_do_sistema')
            ->whereIn('id', $porNotificar->pluck('id'))
            ->update([
                'notificado_em' => now(),
                'notificacoes'  => DB::raw('notificacoes + 1'),
                'updated_at'    => now(),
            ]);

        return ['enviados' => $porNotificar->count(), 'erros' => 0, 'motivo' => null];
    }

    /**
     * O pedido, com o registo de erros suspenso.
     *
     * É esta suspensão que corta a recursão: se o agente estiver em baixo, o
     * cliente HTTP escreve no log, e sem isto essa escrita criaria um erro
     * novo que voltava a querer ser notificado.
     */
    private function postar(string $url, array $corpo): bool
    {
        self::$aNotificar = true;

        try {
            $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $momento = (string) now()->timestamp;
            $segredo = (string) config('agent.erros.webhook_secret', '');

            $resposta = Http::timeout((int) config('agent.erros.timeout', 8))
                ->withHeaders([
                    'Content-Type'        => 'application/json',
                    'X-Soserp-Event'      => 'erros.novos',
                    'X-Soserp-Timestamp'  => $momento,
                    'X-Soserp-Signature'  => $segredo === ''
                        ? ''
                        : hash_hmac('sha256', $momento . '.' . $json, $segredo),
                ])
                ->withBody($json, 'application/json')
                ->post($url);

            return $resposta->successful();
        } catch (\Throwable) {
            // Sem log de propósito. Ver o comentário do topo.
            return false;
        } finally {
            self::$aNotificar = false;
        }
    }

    /**
     * A tranca de tempo, para a boleia do tráfego.
     *
     * Devolve falso se outra passagem já correu há pouco.
     */
    public function podeCorrer(): bool
    {
        return Cache::add(
            'plataforma:notificar-openclaw',
            1,
            (int) config('agent.erros.intervalo_segundos', 300)
        );
    }
}
