<?php

namespace App\Services\AGT;

/**
 * Tradução de códigos de erro AGT (DS.120 §5.2) para mensagens UX em PT.
 *
 * Uso típico:
 *   AGTErrorCode::translate('E07') // → "Software não certificado pela AGT."
 *   AGTErrorCode::formatList($errorList) // → "[E01] Falta de parâmetro; [E07] Software..."
 */
class AGTErrorCode
{
    /** Mapa código => mensagem amigável em PT-PT. */
    public const MESSAGES = [
        // Validações genéricas
        'E01' => 'Falta de parâmetro obrigatório.',
        'E02' => 'Formato inválido do parâmetro.',
        'E03' => 'Valor não esperado para o parâmetro.',
        'E04' => 'O valor de "numberOfEntries" não coincide com o tamanho do array "documents".',

        // Contribuinte / Software
        'E05' => 'NIF emissor sem actividade económica registada na AGT.',
        'E06' => 'Software de facturação não autorizado.',
        'E07' => 'Software não certificado pela AGT.',
        'E08' => 'Assinatura "jwsSoftwareSignature" inválida.',

        // Documento
        'E09' => 'Factura já existe no repositório (documento duplicado).',
        'E10' => 'Documento com formato inválido.',
        'E11' => 'NIF angolano desconhecido na AGT.',
        'E12' => 'NIF inválido (dígito de controlo errado).',
        'E13' => 'Data de emissão fora do intervalo permitido.',
        'E14' => 'Tipo de documento não suportado.',
        'E15' => 'Linha de documento inválida.',
        'E16' => 'Total do documento inconsistente com soma das linhas.',
        'E17' => 'Imposto inconsistente (taxContribution não bate com taxPercentage).',

        // Adesão FE
        'E28' => 'Emissor não aderiu à Facturação Electrónica.',
        'E29' => 'Data de emissão anterior à adesão à FE.',
        'E30' => 'Período de declaração já encerrado.',

        // Séries
        'E31' => 'Código de série já em utilização.',
        'E32' => 'Quantidade autorizada inválida (FE-RNG-082/083).',
        'E33' => 'Estabelecimento desconhecido para o contribuinte.',
        'E34' => 'Série inexistente para o contribuinte.',
        'E35' => 'Ano de série fora da janela permitida (Jan–15Dez = corrente; >15Dez = corrente ou seguinte).',
        'E36' => 'Indicador de contingência inválido (esperado N ou C).',

        // Assinatura
        'E40' => 'Assinatura "jwsSignature" do request inválida.',
        'E41' => 'Assinatura "jwsDocumentSignature" do documento inválida.',

        // Validação documento (4.7)
        'E50' => 'Acção de validação inválida (esperado C ou R).',
        'E51' => 'Percentagem de IVA dedutível inválida.',
        'E52' => 'Valor não dedutível inválido.',
        'E53' => 'Documento não está disponível para confirmação/rejeição.',
        'E54' => 'Documento já foi confirmado.',
        'E55' => 'Documento já foi rejeitado.',

        // Consulta
        'E93' => 'Documento desconhecido.',
        'E94' => 'NIF da chamada diferente do NIF do documento.',
        'E95' => 'Período de consulta excede o limite permitido.',

        // Rate limiting
        'E98' => 'Demasiadas solicitações repetidas (rate limit). Aguarde antes de tentar novamente.',

        // Genéricos
        'E99' => 'Erro interno na AGT (tentar novamente).',
    ];

    /** Severidade sugerida para UI. */
    public const SEVERITY_INFO    = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR   = 'error';
    public const SEVERITY_RATE    = 'rate-limit';

    public static function translate(?string $code, ?string $fallback = null): string
    {
        if (empty($code)) return $fallback ?? 'Erro desconhecido.';
        $code = strtoupper(trim($code));
        return self::MESSAGES[$code] ?? ($fallback ?? "Código AGT {$code} (sem tradução).");
    }

    /** Severidade UX do código. */
    public static function severity(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === 'E98') return self::SEVERITY_RATE;
        if (in_array($code, ['E54', 'E55', 'E09'], true)) return self::SEVERITY_WARNING;
        return self::SEVERITY_ERROR;
    }

    /**
     * Formata uma errorList AGT para string única amigável.
     * Aceita tanto `[{idError, descriptionError, documentNo}]` como string simples.
     */
    public static function formatList(mixed $errorList): string
    {
        if (empty($errorList)) return '';
        if (is_string($errorList)) return $errorList;

        if (!is_array($errorList)) return '';

        $parts = [];
        foreach ($errorList as $err) {
            if (!is_array($err)) {
                if (!empty($err)) $parts[] = (string) $err;
                continue;
            }
            $code = $err['idError'] ?? $err['errorCode'] ?? null;
            $desc = $err['descriptionError'] ?? $err['errorDescription'] ?? null;
            $docNo = $err['documentNo'] ?? null;

            $msg = $code ? self::translate($code, $desc) : ($desc ?? '');
            if ($docNo) {
                $msg .= " (doc {$docNo})";
            }
            if ($code) {
                $msg = "[{$code}] {$msg}";
            }
            if ($msg !== '') $parts[] = $msg;
        }
        return implode('; ', $parts);
    }

    /** Devolve array estruturado para UI (badge + mensagem). */
    public static function toArray(mixed $errorList): array
    {
        if (empty($errorList) || !is_array($errorList)) return [];

        $out = [];
        foreach ($errorList as $err) {
            if (!is_array($err)) continue;
            $code = strtoupper(trim((string) ($err['idError'] ?? $err['errorCode'] ?? '')));
            $out[] = [
                'code'        => $code,
                'message'     => self::translate($code, $err['descriptionError'] ?? null),
                'severity'    => self::severity($code),
                'documentNo'  => $err['documentNo'] ?? null,
                'raw'         => $err,
            ];
        }
        return $out;
    }
}
