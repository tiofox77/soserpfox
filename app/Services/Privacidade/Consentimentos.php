<?php

namespace App\Services\Privacidade;

use App\Models\User;
use App\Support\Privacidade\Ip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * O CONSENTIMENTO: o que se escolheu, e a prova de que se escolheu.
 *
 * Duas memórias com papéis diferentes:
 *
 *  · o COOKIE `sos_consentimento` é o que o browser e as páginas lêem a cada
 *    visita («posso carregar o Google Analytics?») — tem de funcionar para quem
 *    ainda não tem conta;
 *  · a TABELA `consentimentos` é a prova (RGPD art. 7.º, n.º 1): o quê, quando,
 *    em que versão, de onde. Só se acrescentam linhas: retirar é uma linha nova
 *    com `aceite = false`, e a mais recente de cada tipo é a que vale.
 *
 * O cookie é texto simples e curto — «v2026-09-15.e1.m0» — e fica fora da cifra
 * dos cookies do Laravel, porque é o JavaScript da página que o escreve e lê.
 */
class Consentimentos
{
    public const TIPOS = ['termos', 'privacidade', 'estatisticas', 'marketing'];

    public static function versao(): string
    {
        return (string) config('privacidade.versao');
    }

    /**
     * A escolha que o browser trouxe, se for da versão em vigor.
     *
     * @return array{estatisticas: bool, marketing: bool}|null
     */
    public static function doPedido(?Request $request = null): ?array
    {
        $valor = ($request ?? request())->cookie((string) config('privacidade.cookie'));

        if (! is_string($valor) || ! preg_match('/^v([0-9-]{10})\.e([01])\.m([01])$/', $valor, $m)) {
            return null;
        }

        if ($m[1] !== self::versao()) {
            return null;
        }

        return ['estatisticas' => $m[2] === '1', 'marketing' => $m[3] === '1'];
    }

    public static function permite(string $categoria, ?Request $request = null): bool
    {
        return (bool) (self::doPedido($request)[$categoria] ?? false);
    }

    public static function valorDoCookie(bool $estatisticas, bool $marketing): string
    {
        return sprintf('v%s.e%d.m%d', self::versao(), $estatisticas ? 1 : 0, $marketing ? 1 : 0);
    }

    public static function cookie(bool $estatisticas, bool $marketing): Cookie
    {
        return cookie(
            (string) config('privacidade.cookie'),
            self::valorDoCookie($estatisticas, $marketing),
            (int) config('privacidade.cookie_dias', 180) * 24 * 60,
            '/', null, request()->isSecure(), false, false, 'lax'
        );
    }

    /** Grava uma escolha. Nunca rebenta quem a chama: o registo não pode falhar por isto. */
    public static function registar(string $tipo, bool $aceite, string $origem, ?User $user = null, ?string $visitante = null, ?Request $request = null): void
    {
        if (! in_array($tipo, self::TIPOS, true) || ! Schema::hasTable('consentimentos')) {
            return;
        }

        $request ??= request();

        try {
            DB::table('consentimentos')->insert([
                'user_id' => $user?->id,
                'visitor_id' => $visitante && preg_match('/^[0-9a-f-]{36}$/i', $visitante) ? $visitante : null,
                'tipo' => $tipo,
                'aceite' => $aceite,
                'versao' => self::versao(),
                'origem' => mb_substr($origem, 0, 40),
                'ip' => Ip::anonimizar($request->ip()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * O estado actual de cada tipo para uma pessoa (a linha mais recente).
     *
     * @return array<string, array{aceite: bool, versao: string, quando: string, origem: string}|null>
     */
    public static function actuais(User $user): array
    {
        $estado = array_fill_keys(self::TIPOS, null);

        if (! Schema::hasTable('consentimentos')) {
            return $estado;
        }

        $linhas = DB::table('consentimentos')->where('user_id', $user->id)->orderBy('id')->get();

        foreach ($linhas as $l) {
            $estado[$l->tipo] = [
                'aceite' => (bool) $l->aceite,
                'versao' => $l->versao,
                'quando' => (string) $l->created_at,
                'origem' => $l->origem,
            ];
        }

        return $estado;
    }

    /** O histórico inteiro de uma pessoa — para a exportação dos dados. */
    public static function historico(User $user): array
    {
        if (! Schema::hasTable('consentimentos')) {
            return [];
        }

        return DB::table('consentimentos')->where('user_id', $user->id)->orderByDesc('id')
            ->get(['tipo', 'aceite', 'versao', 'origem', 'ip', 'user_agent', 'created_at'])
            ->map(fn ($l) => (array) $l)->all();
    }
}
