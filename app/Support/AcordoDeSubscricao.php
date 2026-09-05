<?php

namespace App\Support;

use App\Models\Plan;
use Carbon\Carbon;

/**
 * O ACORDO de uma subscrição, calculado num sítio só.
 *
 * Plano e ciclo dizem o preço de tabela e o período de tabela. O acordo é o
 * que quem gere a plataforma fecha por cima disso: dar ou não a oferta do
 * anual, um período em dias à medida, cobrar por utilizador, ou um valor
 * escrito à mão. Três ecrãs mostram e gravam isto (Alterar Plano, Gestão de
 * Facturação, TrocarDePlano) — e se cada um fizesse as contas por si, um
 * prometia uma coisa e outro gravava outra.
 *
 * Opções (todas facultativas):
 *   com_oferta            bool   dar os 2 meses do anual (omissão: sim)
 *   dias                  int    período em dias — ganha ao ciclo
 *   preco_por_utilizador  float  cobrar N utilizadores × este preço
 *   utilizadores          int    o N — omissão: os utilizadores do plano
 *   valor                 float  valor fechado à mão — ganha a tudo
 */
final class AcordoDeSubscricao
{
    /**
     * @return array{
     *   fim: Carbon, dias: int, com_oferta: bool, dias_personalizados: ?int,
     *   valor: float, preco_por_utilizador: ?float, utilizadores_cobrados: ?int,
     *   base: string, oferta_aplicavel: bool
     * }
     */
    public static function calcular(Plan $plano, string $ciclo, array $opcoes = [], ?Carbon $inicio = null): array
    {
        // Um só «agora» para o fim e para a contagem — senão quem escreve
        // 364 lê 363, pelos milissegundos entre dois relógios.
        $inicio = $inicio ? $inicio->copy() : now();
        $ciclo = CicloDeFacturacao::normalizar($ciclo);

        $comOferta = (bool) ($opcoes['com_oferta'] ?? true);
        $dias = self::inteiro($opcoes['dias'] ?? null);

        // «N dias» é ATÉ AO FIM do N-ésimo dia: quem fecha 364 dias quer ler
        // «faltam 364», não «363d 23h» no crachá do plano.
        $fim = $dias > 0
            ? $inicio->copy()->addDays($dias)->endOfDay()
            : CicloDeFacturacao::fim($inicio, $ciclo, $comOferta);

        [$valor, $porUtilizador, $utilizadores, $base] = self::valor($plano, $ciclo, $opcoes);

        // O tecto de documentos do plano viaja para a subscrição, no momento
        // em que ela nasce. Mudar a política do plano amanhã não mexe em quem
        // já assinou; e quem assina hoje leva o que o plano diz hoje.
        $tecto = array_key_exists('max_documentos', $opcoes)
            ? ($opcoes['max_documentos'] === null || $opcoes['max_documentos'] === '' ? null : (int) $opcoes['max_documentos'])
            : ($plano->max_documents !== null ? (int) $plano->max_documents : null);

        return [
            'fim' => $fim,
            'dias' => (int) $inicio->diffInDays($fim),
            'com_oferta' => $comOferta,
            'dias_personalizados' => $dias > 0 ? $dias : null,
            'max_documentos' => $tecto,
            'valor' => $valor,
            'preco_por_utilizador' => $porUtilizador,
            'utilizadores_cobrados' => $utilizadores,
            'base' => $base,
            // A oferta só tem sentido num anual sem dias à medida.
            'oferta_aplicavel' => $ciclo === 'yearly' && $dias <= 0,
        ];
    }

    /** As colunas da subscrição que guardam o acordo, prontas a gravar. */
    public static function colunas(array $acordo): array
    {
        return [
            'com_oferta' => $acordo['com_oferta'],
            'dias_personalizados' => $acordo['dias_personalizados'],
            'amount' => $acordo['valor'],
            'preco_por_utilizador' => $acordo['preco_por_utilizador'],
            'utilizadores_cobrados' => $acordo['utilizadores_cobrados'],
            'max_documentos' => $acordo['max_documentos'],
        ];
    }

    /** @throws \InvalidArgumentException */
    public static function validar(array $opcoes): void
    {
        $dias = $opcoes['dias'] ?? null;

        if ($dias !== null && $dias !== '' && (int) $dias <= 0) {
            throw new \InvalidArgumentException('Os dias do período têm de ser um número positivo.');
        }

        if ((int) $dias > 3660) {
            throw new \InvalidArgumentException('Um período de mais de dez anos não é uma subscrição — é um engano de tecla.');
        }

        foreach (['preco_por_utilizador', 'valor'] as $campo) {
            if (isset($opcoes[$campo]) && $opcoes[$campo] !== '' && (float) $opcoes[$campo] < 0) {
                throw new \InvalidArgumentException('Um preço não pode ser negativo.');
            }
        }

        $utilizadores = $opcoes['utilizadores'] ?? null;

        if ($utilizadores !== null && $utilizadores !== '' && (int) $utilizadores <= 0) {
            throw new \InvalidArgumentException('Cobrar por utilizador exige pelo menos um utilizador.');
        }
    }

    /** @return array{0: float, 1: ?float, 2: ?int, 3: string} */
    private static function valor(Plan $plano, string $ciclo, array $opcoes): array
    {
        // Um valor fechado à mão ganha a tudo — é o que se escreveu no acordo.
        if (isset($opcoes['valor']) && $opcoes['valor'] !== '') {
            return [round((float) $opcoes['valor'], 2), null, null, 'valor acordado à mão'];
        }

        $porUtilizador = $opcoes['preco_por_utilizador'] ?? null;

        if ($porUtilizador !== null && $porUtilizador !== '') {
            $utilizadores = max(1, self::inteiro($opcoes['utilizadores'] ?? null) ?: (int) ($plano->max_users ?? 1));
            $porUtilizador = round((float) $porUtilizador, 2);

            return [
                round($porUtilizador * $utilizadores, 2),
                $porUtilizador,
                $utilizadores,
                "{$utilizadores} utilizador(es) × ".number_format($porUtilizador, 2).' Kz',
            ];
        }

        return [(float) $plano->getPrice($ciclo), null, null, 'preço de tabela do plano'];
    }

    private static function inteiro(mixed $valor): int
    {
        return ($valor === null || $valor === '') ? 0 : (int) $valor;
    }
}
