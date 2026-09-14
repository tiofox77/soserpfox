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

        // Os que a AGT devolveu de facto nas recusas destes documentos, com o
        // sentido que se apurou caso a caso (ver os comandos agt:ver-rejeicao,
        // notas:acertar e o DocumentMapper). A descrição da AGT continua a ir
        // ao lado: esta é só a explicação de quem já tropeçou nela.
        'E23' => 'O netTotal do documento não corresponde à soma das linhas.',
        'E39' => 'Os dados assinados do produtor (productId, productVersion, número de certificação) não coincidem com o Processo de Certificação deste ambiente.',
        'E43' => 'A soma do que a nota anula excede o que o documento base ainda tem por anular.',
        'E70' => 'O taxContribution de uma linha não corresponde ao imposto apurado pela AGT (arredondamento ao cêntimo por excesso sobre base × taxa).',

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
     *
     * A DESCRIÇÃO DA AGT NUNCA SE PERDE. Trocava-se pelo texto local sempre que
     * o código era conhecido — e o texto local de um código é o que se julgou
     * que ele queria dizer, não o que a AGT disse daquele documento. Uma recusa
     * E43 que traz o valor em causa na descrição ficava reduzida a uma frase
     * genérica. Vão as duas: primeiro a da AGT, depois a explicação.
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
            $code = self::codigoDe($err);
            $desc = trim((string) ($err['descriptionError'] ?? $err['errorDescription'] ?? ''));
            $docNo = $err['documentNo'] ?? null;
            $local = $code !== null ? (self::MESSAGES[$code] ?? null) : null;

            $msg = match (true) {
                $desc !== '' && $local !== null && $local !== $desc => "{$desc} — {$local}",
                $desc !== '' => $desc,
                $code !== null => self::translate($code),
                default => '',
            };
            if ($docNo) {
                $msg .= " (doc {$docNo})";
            }
            if ($code) {
                $msg = "[{$code}] {$msg}";
            }
            if (trim($msg) !== '') $parts[] = $msg;
        }
        return implode('; ', $parts);
    }

    /**
     * TODOS os erros de uma resposta, estejam onde estiverem.
     *
     * A AGT não os põe sempre no mesmo sítio: o registo devolve `errorList`,
     * o obterEstado traz os do pedido em `requestErrorList` e os de cada
     * documento em `documentStatusList[].errorList` — e é neste último que
     * vêm o E43 e o E70. Ler só o primeiro deixava a recusa sem código.
     *
     * Aceita a resposta inteira ou já só uma lista de erros.
     *
     * @return array<int, array{codigo: ?string, descricao: string, explicacao: ?string}>
     */
    public static function lista(mixed $resposta): array
    {
        if (!is_array($resposta) || $resposta === []) {
            return is_string($resposta) && trim($resposta) !== ''
                ? [['codigo' => null, 'descricao' => trim($resposta), 'explicacao' => null]]
                : [];
        }

        $brutos = [];
        $eResposta = array_intersect_key($resposta, array_flip(['errorList', 'requestErrorList', 'documentStatusList', 'statusResult']));

        if ($eResposta !== []) {
            foreach (['errorList', 'requestErrorList'] as $campo) {
                foreach ((array) ($resposta[$campo] ?? []) as $e) {
                    $brutos[] = $e;
                }
            }
            foreach ((array) data_get($resposta, 'statusResult.requestErrorList', []) as $e) {
                $brutos[] = $e;
            }
            foreach ((array) ($resposta['documentStatusList'] ?? []) as $linha) {
                foreach ((array) (is_array($linha) ? ($linha['errorList'] ?? []) : []) as $e) {
                    $brutos[] = is_array($e) && !isset($e['documentNo']) && isset($linha['documentNo'])
                        ? $e + ['documentNo' => $linha['documentNo']]
                        : $e;
                }
            }
        } elseif (array_is_list($resposta)) {
            $brutos = $resposta;
        }

        $saida = [];
        foreach ($brutos as $e) {
            if (is_array($e)) {
                $codigo = self::codigoDe($e);
                $descricao = trim((string) ($e['descriptionError'] ?? $e['errorDescription'] ?? ''));
                if ($codigo === null && $descricao === '') {
                    continue;
                }
                $saida[] = [
                    'codigo' => $codigo,
                    'descricao' => $descricao !== '' ? $descricao : self::translate($codigo),
                    'explicacao' => $codigo !== null ? (self::MESSAGES[$codigo] ?? null) : null,
                    'documento' => isset($e['documentNo']) ? (string) $e['documentNo'] : null,
                ];
            } elseif (is_scalar($e) && trim((string) $e) !== '') {
                $saida[] = ['codigo' => null, 'descricao' => trim((string) $e), 'explicacao' => null, 'documento' => null];
            }
        }

        return $saida;
    }

    /** Uma resposta inteira numa frase: «[E43] descrição da AGT — explicação (doc …)». */
    public static function formatarResposta(mixed $resposta): string
    {
        return collect(self::lista($resposta))->map(function (array $e) {
            $texto = $e['descricao'];
            if ($e['explicacao'] !== null && $e['explicacao'] !== $e['descricao']) {
                $texto .= ' — ' . $e['explicacao'];
            }
            if ($e['documento'] !== null) {
                $texto .= " (doc {$e['documento']})";
            }

            return $e['codigo'] !== null ? "[{$e['codigo']}] {$texto}" : $texto;
        })->implode('; ');
    }

    /** O código da AGT do primeiro erro que o tenha (E39, E43…), ou nada. */
    public static function primeiroCodigo(mixed $resposta): ?string
    {
        foreach (self::lista($resposta) as $e) {
            if ($e['codigo'] !== null) {
                return $e['codigo'];
            }
        }

        return null;
    }

    private static function codigoDe(array $erro): ?string
    {
        $codigo = strtoupper(trim((string) ($erro['idError'] ?? $erro['errorCode'] ?? '')));

        return $codigo !== '' ? $codigo : null;
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
