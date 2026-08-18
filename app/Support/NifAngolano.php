<?php

namespace App\Support;

/**
 * Classifica um NIF angolano de EMPRESA, sem o expor.
 *
 * As regras são as mesmas que a plataforma já aplica no registo
 * (App\Rules\NifDeEmpresa): o NIF de pessoa colectiva tem nove ou dez
 * dígitos e começa por 5; letras denunciam o número do BI; um número
 * começado por 2 é de pessoa singular e por 3 de estrangeiro.
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
     * @return array{estado: string, motivo: ?string, mascarado: ?string}
     */
    public static function classificar(?string $nif): array
    {
        $limpo = preg_replace('/[\s.\-\/]/', '', (string) $nif) ?? '';

        if ($limpo === '') {
            return ['estado' => self::AUSENTE, 'motivo' => 'NIF por preencher', 'mascarado' => null];
        }

        $mascarado = self::mascarar($limpo);

        // Consumidor final num campo de empresa é um registo por terminar.
        if ($limpo === '999999999') {
            return ['estado' => self::INVALIDO, 'motivo' => 'consumidor final (999999999) — o registo ficou a meio', 'mascarado' => $mascarado];
        }

        // Letras: alguém pôs o número do bilhete de identidade.
        if (preg_match('/[A-Za-z]/', $limpo)) {
            return ['estado' => self::INVALIDO, 'motivo' => 'parece o número do BI (tem letras), não o NIF da empresa', 'mascarado' => $mascarado];
        }

        if (!preg_match('/^\d{9,10}$/', $limpo)) {
            $n = strlen($limpo);
            return ['estado' => self::INVALIDO, 'motivo' => "tem {$n} dígitos; o NIF de empresa tem nove ou dez", 'mascarado' => $mascarado];
        }

        // O que distingue empresa de pessoa é o primeiro dígito.
        $inicio = $limpo[0];
        if ($inicio !== '5') {
            $quem = match ($inicio) {
                '2'     => 'pessoa singular',
                '3'     => 'estrangeiro',
                default => 'não empresarial',
            };
            return ['estado' => self::INVALIDO, 'motivo' => "começa por {$inicio} ({$quem}); o NIF de empresa começa por 5", 'mascarado' => $mascarado];
        }

        return ['estado' => self::VALIDO, 'motivo' => null, 'mascarado' => $mascarado];
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
