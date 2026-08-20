<?php

namespace App\Services\Agent;

use App\Models\ErroDoSistema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Transforma o ruído do log em problemas contáveis.
 *
 * O ficheiro de log tem 2000 linhas das quais 1972 são INFO de rotina. Um erro
 * a sério afoga-se lá dentro, e ninguém está a ler logs às três da manhã. Isto
 * agrupa por PROBLEMA — uma linha por impressão digital, com um contador — para
 * que alguém (o agente externo) possa avisar uma vez e não mil.
 *
 * TRÊS COISAS QUE ISTO NUNCA PODE FAZER
 * -------------------------------------
 * 1. REBENTAR. Corre dentro do canal de log. Uma excepção aqui rebentaria no
 *    meio de outra coisa qualquer, e a mensagem original perdia-se.
 * 2. RECORRER. Se falhar e escrever no log, essa escrita volta a chamar isto.
 *    A tranca estática corta o ciclo; sem ela, um erro de base de dados dava
 *    uma recursão infinita dentro do tratamento de erros.
 * 3. GUARDAR SEGREDOS. O que aqui entra sai por uma API para um agente
 *    externo. Uma password num contexto de log passaria a estar num JSON que
 *    sai de casa.
 */
class RegistoDeErros
{
    /** Está-se a registar um erro neste momento? Corta a recursão. */
    private static bool $aRegistar = false;

    /** Níveis que são problemas. Warning é ruído demais para acordar alguém. */
    public const NIVEIS = ['error', 'critical', 'alert', 'emergency'];

    /**
     * Mensagens que não são problemas do sistema.
     *
     * Um 404 ou uma password errada são o sistema a funcionar. Enchê-lo-iam de
     * linhas e treinariam quem vigia a ignorar a lista — que é como se perde o
     * erro que interessava.
     */
    private const RUIDO = [
        'NotFoundHttpException',
        'ModelNotFoundException',
        'ValidationException',
        'AuthenticationException',
        'TokenMismatchException',
        'MethodNotAllowedHttpException',
        'ThrottleRequestsException',
        'CorruptComponentPayloadException',
    ];

    /** Regista uma ocorrência. Devolve a linha, ou null se foi ignorada. */
    public function registar(
        string $nivel,
        string $mensagem,
        array $contexto = [],
        ?string $ficheiro = null,
        ?int $linha = null,
        ?string $excepcao = null,
    ): ?ErroDoSistema {
        if (self::$aRegistar) {
            return null;   // já estamos aqui dentro: não recorrer
        }

        // O empurrão para o agente externo faz um pedido HTTP. Se o agente
        // estiver em baixo, o cliente HTTP escreve no log — e essa escrita
        // criaria um erro novo que voltava a querer ser notificado, num
        // ciclo que só parava com o servidor em baixo.
        if (NotificarOpenClaw::estaANotificar()) {
            return null;
        }

        $nivel = strtolower($nivel);

        if (!in_array($nivel, self::NIVEIS, true)) {
            return null;
        }

        if ($this->eRuido($mensagem, $excepcao)) {
            return null;
        }

        self::$aRegistar = true;

        try {
            return $this->gravar($nivel, $mensagem, $contexto, $ficheiro, $linha, $excepcao);
        } catch (\Throwable) {
            // Em silêncio DE PROPÓSITO: escrever no log aqui voltaria a entrar
            // neste método. Perder o registo de um erro é mau; entrar em
            // recursão dentro do tratamento de erros é pior.
            return null;
        } finally {
            self::$aRegistar = false;
        }
    }

    private function gravar(
        string $nivel, string $mensagem, array $contexto,
        ?string $ficheiro, ?int $linha, ?string $excepcao,
    ): ?ErroDoSistema {
        $impressao = $this->impressaoDigital($nivel, $mensagem, $ficheiro, $linha, $excepcao);
        $agora = now();

        $existente = ErroDoSistema::where('fingerprint', $impressao)->first();

        if ($existente) {
            // Um UPDATE cru e não um save(): dois pedidos em simultâneo com
            // `save()` perderiam uma das contagens.
            DB::table('erros_do_sistema')
                ->where('id', $existente->id)
                ->update([
                    'ocorrencias' => DB::raw('ocorrencias + 1'),
                    'ultima_vez'  => $agora,
                    'updated_at'  => $agora,
                ]);

            // Voltou a acontecer depois de alguém o fechar: reabre e volta a
            // merecer aviso. Sem isto, um erro dado por resolvido que regressa
            // ficava calado para sempre.
            if ($existente->resolvido_em) {
                $existente->reabrir();
            }

            return $existente->refresh();
        }

        try {
            return ErroDoSistema::create([
                'fingerprint'  => $impressao,
                'nivel'        => $nivel,
                'mensagem'     => mb_substr($mensagem, 0, 2000),
                'ficheiro'     => $ficheiro ? mb_substr($ficheiro, 0, 255) : null,
                'linha'        => $linha,
                'excepcao'     => $excepcao ? mb_substr($excepcao, 0, 190) : null,
                'contexto'     => $this->redigir($contexto),
                'tenant_id'    => $this->empresaDoPedido(),
                'url'          => $this->urlDoPedido(),
                'ocorrencias'  => 1,
                'primeira_vez' => $agora,
                'ultima_vez'   => $agora,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Outro pedido criou-a entretanto: somar a ocorrência dele.
            DB::table('erros_do_sistema')
                ->where('fingerprint', $impressao)
                ->update(['ocorrencias' => DB::raw('ocorrencias + 1'), 'ultima_vez' => $agora]);

            return ErroDoSistema::where('fingerprint', $impressao)->first();
        }
    }

    /**
     * A impressão digital: o que faz de duas ocorrências o mesmo problema.
     *
     * Limpam-se números, ids, uuids, datas e caminhos, porque são o que varia
     * entre ocorrências do MESMO problema. Sem isso, "Utilizador 123 não
     * encontrado" e "Utilizador 456 não encontrado" seriam problemas
     * diferentes e o agente mandava uma mensagem por cliente afectado.
     */
    public function impressaoDigital(
        string $nivel, string $mensagem, ?string $ficheiro, ?int $linha, ?string $excepcao,
    ): string {
        return hash('sha256', implode('|', [
            $nivel,
            $excepcao ?? '',
            $ficheiro ?? '',
            (string) ($linha ?? ''),
            $this->normalizar($mensagem),
        ]));
    }

    /** Tira da mensagem tudo o que varia de ocorrência para ocorrência. */
    public function normalizar(string $mensagem): string
    {
        $m = mb_strtolower(mb_substr($mensagem, 0, 500));

        $m = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<uuid>', $m);
        $m = preg_replace('/\d{4}-\d{2}-\d{2}([ t]\d{2}:\d{2}(:\d{2})?)?/', '<data>', $m);
        $m = preg_replace('~[a-z]:[\\\\/][^\s"]+~i', '<caminho>', $m);
        $m = preg_replace('~/[^\s"]{6,}~', '<caminho>', $m);
        $m = preg_replace('/\b\d+\b/', '<n>', $m);
        $m = preg_replace('/\s+/', ' ', $m);

        return trim((string) $m);
    }

    /**
     * Segredos fora.
     *
     * Isto sai por uma API para um agente externo: o que aqui entrar sai de
     * casa. Reutiliza a lista da auditoria, que já foi pensada para o mesmo
     * problema, e acrescenta o que costuma aparecer em contextos de log.
     */
    public function redigir(array $contexto): array
    {
        $proibidos = array_map('strtolower', array_merge(
            config('audit.redacted', []),
            ['authorization', 'cookie', 'senha', 'palavra_passe', 'api_key',
                'apitoken', 'access_token', 'refresh_token', 'credentials', 'pin'],
        ));

        $limpo = [];

        foreach ($contexto as $chave => $valor) {
            if (in_array(strtolower((string) $chave), $proibidos, true)) {
                $limpo[$chave] = '[oculto]';
                continue;
            }

            if (is_array($valor)) {
                $limpo[$chave] = $this->redigir($valor);
                continue;
            }

            if (is_scalar($valor) || $valor === null) {
                // O rasto de pilha vem inteiro no contexto do Laravel e traz
                // argumentos de chamadas — onde as passwords passam.
                $limpo[$chave] = mb_substr((string) $valor, 0, 1000);
                continue;
            }

            $limpo[$chave] = '[' . get_debug_type($valor) . ']';
        }

        return $limpo;
    }

    private function eRuido(string $mensagem, ?string $excepcao): bool
    {
        $agulha = $excepcao . ' ' . $mensagem;

        foreach (self::RUIDO as $ruido) {
            if (str_contains($agulha, $ruido)) {
                return true;
            }
        }

        return false;
    }

    /** Nunca rebenta: isto corre dentro do tratamento de erros. */
    private function empresaDoPedido(): ?int
    {
        try {
            return activeTenantId() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function urlDoPedido(): ?string
    {
        try {
            if (app()->runningInConsole()) {
                return 'consola';
            }

            // Só o caminho: a query string leva tokens e emails.
            $caminho = request()?->path();

            return $caminho ? mb_substr('/' . $caminho, 0, 255) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
