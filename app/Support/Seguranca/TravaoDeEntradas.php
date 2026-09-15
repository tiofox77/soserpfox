<?php

namespace App\Support\Seguranca;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * O TRAVÃO DAS ENTRADAS — uma regra só para as três portas com senha.
 *
 * Pedido de 2026-09-15: «o campo de login devia ter 5 tentativas e depois
 * bloquear por 10 minutos». O que havia eram três regras diferentes: o /login
 * do site deixava 5 por MINUTO (ao fim de 60 segundos voltava a deixar mais 5
 * — 7200 senhas por dia a um mesmo email), a API e o portal do cliente copiavam
 * isso, e nenhuma travava quem experimenta a mesma senha em muitos emails.
 *
 * AS DUAS CONTAS:
 *
 *  · POR EMAIL + IP — cinco falhas em dez minutos fecham a porta durante DEZ
 *    MINUTOS contados a partir da quinta, não do resto da janela. Não é só por
 *    email de propósito: bloquear o email de alguém bastaria para um estranho
 *    o impedir de trabalhar escrevendo cinco senhas erradas.
 *  · POR IP — vinte falhas em dez minutos, em quaisquer emails, fecham a porta
 *    a esse IP durante dez minutos. É o que trava quem experimenta «123456» em
 *    duzentas contas diferentes (uma falha por email nunca chegava às cinco).
 *
 * Cada porta tem o seu prefixo: bloquear o portal do cliente não fecha o site.
 * As falhas contam-se ANTES de se saber se o email existe, e a mensagem é a
 * mesma — o travão não diz a ninguém que contas há.
 */
class TravaoDeEntradas
{
    public const TENTATIVAS = 5;

    public const TENTATIVAS_POR_IP = 20;

    public const JANELA_SEGUNDOS = 600;

    public const BLOQUEIO_SEGUNDOS = 600;

    public function __construct(private string $porta = 'web')
    {
    }

    public static function para(string $porta): self
    {
        return new self($porta);
    }

    /** Segundos que faltam para voltar a poder tentar (0 = pode). */
    public function bloqueadoPor(?string $email, ?string $ip): int
    {
        $segundos = 0;

        foreach ([$this->chaveDeBloqueio($email, $ip), $this->chaveDeBloqueioDoIp($ip)] as $chave) {
            if (RateLimiter::tooManyAttempts($chave, 1)) {
                $segundos = max($segundos, RateLimiter::availableIn($chave));
            }
        }

        return $segundos;
    }

    /**
     * Uma falha. Devolve quantas tentativas restam antes do bloqueio (0 quando
     * esta falha acabou de o fechar).
     */
    public function falhou(?string $email, ?string $ip): int
    {
        $chave = $this->chave($email, $ip);
        $doIp = $this->chaveDoIp($ip);

        RateLimiter::hit($chave, self::JANELA_SEGUNDOS);
        RateLimiter::hit($doIp, self::JANELA_SEGUNDOS);

        if (RateLimiter::attempts($doIp) >= self::TENTATIVAS_POR_IP) {
            $this->fechar($this->chaveDeBloqueioDoIp($ip), $doIp, ['porta' => $this->porta, 'motivo' => 'ip', 'ip' => $ip]);
        }

        $feitas = RateLimiter::attempts($chave);

        if ($feitas >= self::TENTATIVAS) {
            $this->fechar($this->chaveDeBloqueio($email, $ip), $chave, ['porta' => $this->porta, 'motivo' => 'email', 'ip' => $ip]);

            return 0;
        }

        return self::TENTATIVAS - $feitas;
    }

    /** Entrou: as falhas deste email neste IP esquecem-se. As do IP não. */
    public function entrou(?string $email, ?string $ip): void
    {
        RateLimiter::clear($this->chave($email, $ip));
    }

    /** A frase do bloqueio, em minutos (ninguém conta 587 segundos). */
    public static function mensagemDeBloqueio(int $segundos): string
    {
        $minutos = max(1, (int) ceil($segundos / 60));

        return trans_choice(
            'Demasiadas tentativas falhadas. Por segurança, a entrada fica bloqueada durante :minutos minuto.|Demasiadas tentativas falhadas. Por segurança, a entrada fica bloqueada durante :minutos minutos.',
            $minutos,
            ['minutos' => $minutos]
        );
    }

    /** A frase de uma falha: genérica, e com o aviso quando o bloqueio está perto. */
    public static function mensagemDeFalha(int $restam, string $generica): string
    {
        if ($restam <= 0) {
            return self::mensagemDeBloqueio(self::BLOQUEIO_SEGUNDOS);
        }

        if ($restam <= 2) {
            return $generica . ' ' . trans_choice(
                'Resta :n tentativa antes de a entrada ficar bloqueada 10 minutos.|Restam :n tentativas antes de a entrada ficar bloqueada 10 minutos.',
                $restam,
                ['n' => $restam]
            );
        }

        return $generica;
    }

    private function fechar(string $bloqueio, string $contador, array $contexto): void
    {
        if (RateLimiter::tooManyAttempts($bloqueio, 1)) {
            return;
        }

        RateLimiter::hit($bloqueio, self::BLOQUEIO_SEGUNDOS);
        RateLimiter::clear($contador);

        // Sem o email: um bloqueio não pode ser a maneira de escrever no log
        // os emails que alguém anda a experimentar.
        Log::warning('Entrada bloqueada por tentativas falhadas', $contexto);
    }

    private function chave(?string $email, ?string $ip): string
    {
        return 'entrada:' . $this->porta . ':' . sha1(Str::transliterate(Str::lower(trim((string) $email))) . '|' . $ip);
    }

    private function chaveDoIp(?string $ip): string
    {
        return 'entrada-ip:' . $this->porta . ':' . sha1((string) $ip);
    }

    private function chaveDeBloqueio(?string $email, ?string $ip): string
    {
        return 'bloqueio:' . $this->chave($email, $ip);
    }

    private function chaveDeBloqueioDoIp(?string $ip): string
    {
        return 'bloqueio:' . $this->chaveDoIp($ip);
    }
}
