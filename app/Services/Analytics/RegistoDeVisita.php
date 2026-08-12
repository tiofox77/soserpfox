<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registo de actos a partir do SERVIDOR.
 *
 * O recolector em JavaScript só sabe o que acontece no site público, e nem
 * sequer está carregado dentro da aplicação. As pesquisas — "os nomes mais
 * pesquisados" — acontecem em ecrãs Livewire, onde o que o utilizador escreve
 * nunca chega a passar pelo browser de forma que um script possa apanhar de
 * modo fiável. Por isso são registadas aqui, no sítio onde a pesquisa é
 * mesmo feita.
 *
 * Regra que não se negoceia: isto NUNCA rebenta. Uma pesquisa num ecrã de
 * vendas não pode falhar porque o registo de estatísticas teve um problema.
 */
class RegistoDeVisita
{
    /**
     * Termos com menos de três letras não se guardam.
     *
     * Quem procura escreve letra a letra, e cada tecla premida chega aqui.
     * Guardar "a", "am", "ami" e "amid" enche a tabela de lixo e faz o painel
     * dos mais pesquisados mostrar o alfabeto. Três letras é onde a intenção
     * começa a ser legível.
     */
    private const MINIMO_LETRAS = 3;

    /**
     * Regista uma pesquisa.
     *
     * @param  string      $termo    o que a pessoa escreveu
     * @param  string      $onde     ecrã onde pesquisou ('pos', 'produtos', ...)
     * @param  int|null    $achados  quantos resultados apareceram, se se souber
     */
    public static function pesquisa(string $termo, string $onde, ?int $achados = null): void
    {
        $termo = trim(preg_replace('/\s+/', ' ', $termo));

        if (mb_strlen($termo) < self::MINIMO_LETRAS) {
            return;
        }

        self::gravar([
            'type'         => 'search',
            'event_name'   => 'search_' . $onde,
            'search_term'  => mb_strtolower(mb_substr($termo, 0, 150)),
            'path'         => $onde,
            'meta'         => $achados !== null ? ['resultados' => $achados] : null,
        ]);
    }

    /** Regista um acto qualquer feito do lado do servidor. */
    public static function acto(string $tipo, string $nome, array $extra = []): void
    {
        self::gravar(array_merge([
            'type'       => $tipo,
            'event_name' => $nome,
        ], $extra));
    }

    /**
     * A escrita, com o contexto de quem está a usar.
     *
     * `visitor_id` e `session_id` são obrigatórios na tabela. Para actos de
     * dentro da aplicação derivam-se do utilizador e da sessão, de forma
     * estável: o mesmo utilizador dá sempre o mesmo visitor_id, e assim as
     * pesquisas dele agrupam-se como as de um visitante do site.
     */
    private static function gravar(array $dados): void
    {
        try {
            $utilizador = auth()->user();

            AnalyticsEvent::create(array_merge([
                'visitor_id' => self::identificadorEstavel('v', $utilizador?->getKey()),
                'session_id' => self::identificadorEstavel('s', session()->getId()),
                'ip'         => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
                'user_id'    => $utilizador?->getKey(),
                'tenant_id'  => self::empresaActiva(),
                'language'   => substr((string) request()->getPreferredLanguage(), 0, 10),
                'created_at' => now(),
            ], $dados));
        } catch (\Throwable $e) {
            // Uma pesquisa num ecrã de vendas não pode falhar por causa disto.
            Log::debug('Registo de analytics não gravado', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Um UUID sempre igual para a mesma origem.
     *
     * As colunas são char(36) e esperam a forma de um UUID; um md5 com hífenes
     * nos sítios certos serve, é estável, e não expõe o identificador real do
     * utilizador na tabela.
     */
    private static function identificadorEstavel(string $prefixo, string|int|null $semente): string
    {
        $h = md5($prefixo . '|' . ($semente ?? 'anonimo') . '|' . config('app.key'));

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3)
             . '-a' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }

    private static function empresaActiva(): ?int
    {
        try {
            return activeTenantId() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
