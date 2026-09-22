<?php

namespace App\Support;

/**
 * Classifica um NIF angolano de EMPRESA, sem o expor.
 *
 * As regras são as mesmas que a plataforma já aplica no registo
 * (App\Rules\NifDeEmpresa): o NIF de pessoa colectiva tem nove ou dez
 * dígitos e começa por 5 — ou dez começados por 0 (ver `formatoDeEmpresa`);
 * letras denunciam o número do BI; um número começado por 2 é de pessoa
 * singular e por 3 de estrangeiro.
 *
 * Aqui NÃO se faz falhar validação nenhuma — classifica-se, para o agente
 * poder assinalar a um humano os NIF que merecem revisão, e devolve-se
 * sempre o número MASCARADO. O NIF completo nunca sai desta classe.
 */
class NifAngolano
{
    public const VALIDO   = 'valido';
    public const INVALIDO = 'invalido';
    public const AUSENTE  = 'ausente';

    /**
     * Classifica um NIF e devolve-o.
     *
     * `nif` é o número por inteiro, já limpo de espaços e pontuação.
     * `mascarado` continua a existir para quem o queira mostrar num ecrã
     * público — mas a API do agente usa o `nif`, por decisão de quem gere a
     * plataforma: um NIF cortado ao meio não se consegue verificar contra a
     * AGT nem comparar com um documento, que é para o que ele serve.
     *
     * @return array{estado: string, motivo: ?string, nif: ?string, mascarado: ?string}
     */
    public static function classificar(?string $nif): array
    {
        $limpo = preg_replace('/[\s.\-\/]/', '', (string) $nif) ?? '';

        if ($limpo === '') {
            return ['estado' => self::AUSENTE, 'motivo' => 'NIF por preencher', 'nif' => null, 'mascarado' => null];
        }

        $mascarado = self::mascarar($limpo);

        // Consumidor final num campo de empresa é um registo por terminar.
        if ($limpo === '999999999') {
            return ['estado' => self::INVALIDO, 'motivo' => 'consumidor final (999999999) — o registo ficou a meio', 'nif' => $limpo, 'mascarado' => $mascarado];
        }

        // Letras: alguém pôs o número do bilhete de identidade.
        if (preg_match('/[A-Za-z]/', $limpo)) {
            return ['estado' => self::INVALIDO, 'motivo' => 'parece o número do BI (tem letras), não o NIF da empresa', 'nif' => $limpo, 'mascarado' => $mascarado];
        }

        if (!preg_match('/^\d{9,10}$/', $limpo)) {
            $n = strlen($limpo);
            return ['estado' => self::INVALIDO, 'motivo' => "tem {$n} dígitos; o NIF de empresa tem nove ou dez", 'nif' => $limpo, 'mascarado' => $mascarado];
        }

        // O que distingue empresa de pessoa é o primeiro dígito.
        $inicio = $limpo[0];
        if (!self::formatoDeEmpresa($limpo)) {
            if ($inicio === '0') {
                return ['estado' => self::INVALIDO, 'motivo' => 'começa por 0 com nove dígitos; os NIF começados por 0 têm dez', 'nif' => $limpo, 'mascarado' => $mascarado];
            }

            $quem = match ($inicio) {
                '2'     => 'pessoa singular',
                '3'     => 'estrangeiro',
                default => 'não empresarial',
            };
            return ['estado' => self::INVALIDO, 'motivo' => "começa por {$inicio} ({$quem}); o NIF de empresa começa por 5", 'nif' => $limpo, 'mascarado' => $mascarado];
        }

        return ['estado' => self::VALIDO, 'motivo' => null, 'nif' => $limpo, 'mascarado' => $mascarado];
    }

    /**
     * O NÚMERO TEM O FEITIO DE UM NIF DE EMPRESA? A regra única — a do registo
     * (App\Rules\NifDeEmpresa), a desta classe e a da lista da plataforma.
     *
     *  · nove ou dez dígitos começados por 5 — o NIF de pessoa colectiva;
     *  · dez dígitos começados por 0 (22/09/2026) — há alvarás comerciais
     *    emitidos com NIF assim, com zeros à esquerda (0000083092, de um
     *    empresário em nome individual). Recusá-lo impedia um contribuinte
     *    real de se registar. Só com DEZ dígitos: nove começados por 0 são,
     *    quase sempre, os algarismos do BI sem as letras (004512345…).
     *
     * `$limpo` já sem espaços, pontos nem traços.
     */
    public static function formatoDeEmpresa(string $limpo): bool
    {
        return (bool) preg_match('/^(5\d{8,9}|0\d{9})$/', $limpo);
    }

    /** 54******23 — dois dígitos de cada ponta, o meio tapado. */
    public static function mascarar(?string $nif): ?string
    {
        $limpo = preg_replace('/[\s.\-\/]/', '', (string) $nif) ?? '';

        if ($limpo === '') {
            return null;
        }

        if (strlen($limpo) <= 4) {
            return substr($limpo, 0, 1) . str_repeat('*', max(1, strlen($limpo) - 1));
        }

        return substr($limpo, 0, 2)
            . str_repeat('*', strlen($limpo) - 4)
            . substr($limpo, -2);
    }
}
