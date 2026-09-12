<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Services\Plataforma\AuditRecorderDoDono;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * AS CHAVES RSA DO SAF-T — a assinatura dos documentos exportados.
 *
 * O QUE MUDOU DE SUBSTÂNCIA:
 *
 *  · A CHAVE PÚBLICA IA INTEIRA PARA A PÁGINA ao abrir (numa propriedade
 *    pública, dentro do HTML) só para mostrar os primeiros cem caracteres.
 *    Agora vai a impressão digital — identifica o par sem o espalhar.
 *  · REGENERAR SEM FRASE DE CONFIRMAÇÃO. Um `wire:confirm` e pronto: um clique
 *    por engano invalidava a assinatura de tudo o que já tinha sido exportado.
 *    Agora escreve-se REGENERAR, e a cópia de segurança das antigas é
 *    obrigatória — se não se consegue guardá-la, não se regenera.
 *  · GERAR QUANDO JÁ EXISTEM é recusado: era a mesma acção que regenerar, mas
 *    sem cópia de segurança nenhuma.
 *
 * AS DESCARGAS continuam — são o que se entrega à AGT na certificação — mas por
 * uma rota de página, com a guarda do dono, e ficam registadas.
 */
class ChavesSaftApiController extends Controller
{
    private const PUBLICA = 'saft/public_key.pem';

    private const PRIVADA = 'saft/private_key.pem';

    private const METADADOS = 'saft/metadata.json';

    public function index(): JsonResponse
    {
        $disco = Storage::disk('local');
        $publica = $disco->exists(self::PUBLICA);
        $privada = $disco->exists(self::PRIVADA);

        $metadados = $disco->exists(self::METADADOS)
            ? (json_decode($disco->get(self::METADADOS), true) ?: [])
            : [];

        return response()->json([
            'publica' => $publica ? [
                'data' => date('d/m/Y H:i', $disco->lastModified(self::PUBLICA)),
                'impressao' => strtoupper(substr(hash('sha256', $disco->get(self::PUBLICA)), 0, 32)),
            ] : null,
            'privada' => $privada ? [
                'data' => date('d/m/Y H:i', $disco->lastModified(self::PRIVADA)),
            ] : null,
            'metadados' => [
                'gerada_em' => $metadados['generated_at'] ?? null,
                'algoritmo' => $metadados['algorithm'] ?? 'RSA-2048',
                'digest' => $metadados['digest'] ?? 'SHA-256',
                'conformidade' => $metadados['compliance'] ?? 'SAFT-AO Angola',
            ],
            'copias' => collect($disco->directories('saft/backups'))
                ->map(fn ($d) => basename($d))->sortDesc()->values(),
            'openssl' => extension_loaded('openssl'),
        ]);
    }

    public function gerar(): JsonResponse
    {
        if (Storage::disk('local')->exists(self::PRIVADA)) {
            throw ValidationException::withMessages([
                'chaves' => __('As chaves já existem. Para as substituir, use «Regenerar», que guarda uma cópia das actuais.'),
            ]);
        }

        $this->criarPar();

        return response()->json(['message' => __('Chaves do SAF-T geradas.')], 201);
    }

    public function regenerar(Request $request): JsonResponse
    {
        $request->validate([
            'confirmacao' => ['required', 'in:REGENERAR'],
        ], [
            'confirmacao.required' => __('Escreva REGENERAR para confirmar.'),
            'confirmacao.in' => __('Escreva REGENERAR, em maiúsculas, para confirmar.'),
        ]);

        $disco = Storage::disk('local');

        // A CÓPIA DE SEGURANÇA É CONDIÇÃO: sem ela, regenerar apagava a única
        // chave que verifica o que já foi exportado.
        if ($disco->exists(self::PRIVADA)) {
            $pasta = 'saft/backups/'.now()->format('Y-m-d_His');
            $disco->makeDirectory($pasta);

            $ok = $disco->copy(self::PRIVADA, "{$pasta}/private_key.pem")
                && (! $disco->exists(self::PUBLICA) || $disco->copy(self::PUBLICA, "{$pasta}/public_key.pem"));

            if (! $ok) {
                throw ValidationException::withMessages([
                    'chaves' => __('Não foi possível guardar a cópia das chaves actuais. Nada foi alterado.'),
                ]);
            }
        }

        $this->criarPar();

        Log::warning('Chaves do SAF-T regeneradas pelo dono da plataforma', ['user_id' => auth()->id()]);

        return response()->json(['message' => __('Chaves do SAF-T regeneradas. As antigas ficaram guardadas.')]);
    }

    /**
     * A descarga — PEM, texto, ou as duas num ficheiro só.
     *
     * Rota de página e não de API: um ficheiro não se lê como JSON.
     */
    public function descarregar(string $qual, string $formato): Response
    {
        $disco = Storage::disk('local');

        abort_unless(in_array($qual, ['publica', 'privada', 'ambas'], true), 404);
        abort_unless(in_array($formato, ['pem', 'txt'], true), 404);

        Log::info('Descarga das chaves do SAF-T', ['user_id' => auth()->id(), 'qual' => $qual, 'formato' => $formato]);

        if ($qual === 'ambas') {
            abort_unless($disco->exists(self::PUBLICA) && $disco->exists(self::PRIVADA), 404);

            $m = $disco->exists(self::METADADOS) ? (json_decode($disco->get(self::METADADOS), true) ?: []) : [];
            $linha = str_repeat('=', 46);

            $conteudo = "{$linha}\n       CHAVES SAFT-AO ANGOLA\n{$linha}\n\n"
                ."INFORMAÇÕES:\n"
                .'- Gerado em: '.($m['generated_at'] ?? 'N/A')."\n"
                .'- Algoritmo: '.($m['algorithm'] ?? 'RSA-2048')."\n"
                .'- Digest: '.($m['digest'] ?? 'SHA-256')."\n"
                .'- Conformidade: '.($m['compliance'] ?? 'SAFT-AO Angola')."\n\n"
                ."{$linha}\n       CHAVE PÚBLICA (PUBLIC KEY)\n{$linha}\n\n".$disco->get(self::PUBLICA)."\n\n"
                ."{$linha}\n       CHAVE PRIVADA (PRIVATE KEY)\n       MANTENHA EM SEGURANÇA\n{$linha}\n\n".$disco->get(self::PRIVADA)."\n\n"
                ."{$linha}\nIMPORTANTE:\n- Guarde este ficheiro em local seguro\n- NÃO partilhe a chave privada\n- Regenerar as chaves invalida a verificação dos documentos anteriores\n{$linha}\n";

            return response()->streamDownload(fn () => print($conteudo), 'saft_chaves_completas_'.date('Y-m-d_His').'.txt', [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $caminho = $qual === 'publica' ? self::PUBLICA : self::PRIVADA;
        abort_unless($disco->exists($caminho), 404);

        $nome = $qual === 'publica' ? 'saft_public_key' : 'saft_private_key';

        if ($formato === 'pem') {
            return $disco->download($caminho, "{$nome}.pem");
        }

        $conteudo = $disco->get($caminho);

        return response()->streamDownload(fn () => print($conteudo), "{$nome}.txt", ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** O par RSA-2048 com SHA-256, como a AGT pede. */
    private function criarPar(): void
    {
        if (! extension_loaded('openssl')) {
            throw ValidationException::withMessages(['chaves' => __('A extensão OpenSSL não está ligada no PHP deste servidor.')]);
        }

        while (openssl_error_string() !== false);

        $configuracao = sys_get_temp_dir().DIRECTORY_SEPARATOR.'openssl.cnf';

        if (! file_exists($configuracao)) {
            file_put_contents($configuracao, "[ req ]\ndefault_bits = 2048\ndistinguished_name = req_distinguished_name\n\n[ req_distinguished_name ]\n");
        }

        $opcoes = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $configuracao,
        ];

        $par = openssl_pkey_new($opcoes);

        if (! $par || ! openssl_pkey_export($par, $privada, null, $opcoes) || ! ($detalhes = openssl_pkey_get_details($par))) {
            $erros = [];

            while ($e = openssl_error_string()) {
                $erros[] = $e;
            }

            throw ValidationException::withMessages([
                'chaves' => __('O OpenSSL não gerou as chaves: :erro', ['erro' => implode(', ', $erros) ?: __('sem detalhe')]),
            ]);
        }

        $disco = Storage::disk('local');
        $disco->makeDirectory('saft');
        $disco->put(self::PRIVADA, $privada);
        $disco->put(self::PUBLICA, $detalhes['key']);
        $disco->put(self::METADADOS, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'algorithm' => 'RSA-2048',
            'digest' => 'SHA-256',
            'compliance' => 'SAFT-AO Angola',
            'php_version' => PHP_VERSION,
            'openssl_version' => OPENSSL_VERSION_TEXT,
        ], JSON_PRETTY_PRINT));
    }
}
