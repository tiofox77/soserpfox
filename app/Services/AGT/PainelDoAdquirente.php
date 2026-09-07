<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * O PAINEL DO ADQUIRENTE — as facturas que os FORNECEDORES emitiram contra
 * esta empresa. DS.120 §§4.3 (listar), 4.4 (consultar) e 4.7 (validar).
 *
 * A lógica vivia toda dentro do componente Livewire: o payload, o cliente
 * HTTP, o tratamento dos erros da AGT e a empresa lida do utilizador em vez
 * da sessão. Aqui é uma implementação só, que o ecrã em React usa pela API,
 * pela mesma forma que a `GestaoAgt` serve as configurações.
 *
 * CONFIRMAR E REJEITAR ESCREVEM NA AGT — e escrevem no ambiente que a
 * empresa usa para emitir. Por isso todas as operações passam pelo
 * `conferir()`: pedir a lista de homologação enquanto a empresa emite em
 * produção é olhar para o universo errado, e confirmar lá dentro seria pior
 * ainda. Fora do ambiente activo sai `AmbienteErradoException`, como no
 * registo de séries e no reenvio de documentos.
 */
class PainelDoAdquirente
{
    /** As duas decisões que o adquirente pode tomar (DS.120 §4.7). */
    public const ACCOES = ['C' => 'Confirmar', 'R' => 'Rejeitar'];

    public function __construct(private readonly int $tenantId)
    {
    }

    /* ─── Regras ──────────────────────────────────────────────────────── */

    public static function regrasDoPeriodo(): array
    {
        return [
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
            'ambiente' => ['nullable', 'in:sandbox,production'],
        ];
    }

    public static function regrasDoDocumento(): array
    {
        return [
            'documento' => ['required', 'string', 'max:100'],
            'ambiente' => ['nullable', 'in:sandbox,production'],
        ];
    }

    public static function regrasDaValidacao(): array
    {
        return [
            'documento' => ['required', 'string', 'max:100'],
            'accao' => ['required', 'in:C,R'],
            'percentagem_iva_dedutivel' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_nao_dedutivel' => ['nullable', 'numeric', 'min:0'],
            'ambiente' => ['nullable', 'in:sandbox,production'],
        ];
    }

    /* ─── Ler ─────────────────────────────────────────────────────────── */

    public function ambienteActivo(): string
    {
        return GestaoAgt::normalizar(
            InvoicingSettings::where('tenant_id', $this->tenantId)->value('agt_environment')
        );
    }

    /**
     * O que o ecrã precisa de saber antes de perguntar seja o que for: qual
     * é a empresa, em que ambiente é que ela emite, o que falta configurar
     * para a AGT atender, e o período do mês corrente.
     */
    public function estado(): array
    {
        $empresa = Tenant::find($this->tenantId);
        $ambiente = $this->ambienteActivo();

        return [
            'empresa' => [
                'id' => $this->tenantId,
                'nome' => (string) ($empresa?->name ?? ''),
                'nif' => $empresa?->nif,
            ],
            'ambiente' => $ambiente,
            'rotulo' => GestaoAgt::rotulo($ambiente),
            'em_falta' => $this->gestao()->emFalta($ambiente),
            'periodo' => [
                'de' => now()->startOfMonth()->format('Y-m-d'),
                'ate' => now()->format('Y-m-d'),
            ],
            'accoes' => collect(self::ACCOES)->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values()->all(),
        ];
    }

    /**
     * As facturas recebidas no período (DS.120 §4.3).
     *
     * A AGT devolve aqui SÓ os documentos em que esta empresa é o
     * adquirente — os que ela emitiu nunca aparecem. Zero é o resultado
     * normal de quem só emite, e não um erro.
     */
    public function listar(string $de, string $ate, ?string $ambiente = null): array
    {
        $this->conferir($ambiente, __('A listagem de facturas recebidas'));
        $this->exigirPreRequisitos();

        $definicoes = $this->definicoes();

        $resposta = $this->comAAgt(__('listar as facturas recebidas'), function () use ($definicoes, $de, $ate) {
            $construtor = new AGTPayloadBuilder($definicoes);
            $http = new AGTHttpClient($definicoes);

            return $http->post(
                AGTHttpClient::ENDPOINT_LIST_INVOICES,
                $construtor->buildListarFacturas($this->nif($definicoes), $de, $ate),
                'ListarFacturas'
            );
        });

        $corpo = $resposta['response'] ?? [];
        $linhas = $corpo['resultEntryList'] ?? [];

        return [
            'periodo' => ['de' => $de, 'ate' => $ate],
            'total' => (int) ($corpo['documentResultCount']
                ?? data_get($corpo, 'statusResult.documentResultCount')
                ?? count(is_array($linhas) ? $linhas : [])),
            'facturas' => collect(is_array($linhas) ? $linhas : [])
                ->filter(fn ($l) => is_array($l))
                ->map(fn (array $l) => $this->linha($l))
                ->values()
                ->all(),
        ];
    }

    /** O detalhe de uma factura recebida (DS.120 §4.4). */
    public function detalhe(string $documento, ?string $ambiente = null): array
    {
        $this->conferir($ambiente, __('A consulta do documento'));
        $this->exigirPreRequisitos();

        $definicoes = $this->definicoes();

        $resultado = $this->comAAgt(__('consultar o documento'), function () use ($definicoes, $documento) {
            $r = (new QueryService($definicoes))->consultByNumber($documento);

            // O QueryService devolve o corpo já desmontado; o `comAAgt`
            // trabalha com a forma do AGTHttpClient.
            return ['ok' => $r['ok'], 'response' => $r['response'] ?? [], 'error' => $r['error'] ?? null];
        });

        $corpo = $resultado['response'] ?? [];

        return [
            'documento' => $documento,
            'estado' => $corpo['documentStatus'] ?? null,
            // O detalhe vai inteiro: é o que a AGT tem sobre o documento, e
            // o ecrã mostra-o como veio. A assinatura do PEDIDO não viaja.
            'detalhe' => $corpo,
        ];
    }

    /**
     * Confirma (C) ou rejeita (R) uma factura recebida (DS.120 §4.7).
     *
     * A percentagem de IVA dedutível e o valor não dedutível são
     * mutuamente exclusivos — a AGT recusa os dois juntos (E51/E52), e o
     * construtor de payloads rebenta antes disso. Aqui a recusa é uma
     * mensagem, não uma excepção sem dono.
     */
    public function validar(
        string $documento,
        string $accao,
        ?float $percentagemIvaDedutivel = null,
        ?float $valorNaoDedutivel = null,
        ?string $ambiente = null
    ): array {
        $accao = strtoupper($accao);

        if (!isset(self::ACCOES[$accao])) {
            throw new DomainException(__('Acção inválida: só se pode confirmar (C) ou rejeitar (R).'));
        }

        $this->conferir(
            $ambiente,
            $accao === 'C' ? __('A confirmação do documento') : __('A rejeição do documento')
        );
        $this->exigirPreRequisitos();

        // Rejeitar não leva percentagens nem valores: a AGT só os aceita na
        // confirmação, e enviá-los numa rejeição é um E03 à espera.
        if ($accao === 'R') {
            $percentagemIvaDedutivel = null;
            $valorNaoDedutivel = null;
        }

        if ($percentagemIvaDedutivel !== null && $valorNaoDedutivel !== null) {
            throw new DomainException(__('A percentagem de IVA dedutível e o valor não dedutível são exclusivos: indique só um.'));
        }

        $definicoes = $this->definicoes();

        $resultado = $this->comAAgt(
            $accao === 'C' ? __('confirmar o documento') : __('rejeitar o documento'),
            function () use ($definicoes, $documento, $accao, $percentagemIvaDedutivel, $valorNaoDedutivel) {
                $servico = new ValidateService($definicoes);

                $r = $accao === 'C'
                    ? $servico->confirm($documento, $percentagemIvaDedutivel, $valorNaoDedutivel)
                    : $servico->reject($documento);

                return [
                    'ok' => $r['ok'],
                    // O ValidateService devolve o errorList à cabeça; o corpo
                    // da resposta traz o mesmo, e é dele que sai a mensagem.
                    'response' => ($r['response'] ?? []) + ['errorList' => $r['errorList'] ?? []],
                    'error' => $r['error'] ?? null,
                    'actionResultCode' => $r['actionResultCode'] ?? null,
                    'documentStatusCode' => $r['documentStatusCode'] ?? null,
                ];
            }
        );

        return [
            'documento' => $documento,
            'accao' => $accao,
            'actionResultCode' => $resultado['actionResultCode'] ?? null,
            'documentStatusCode' => $resultado['documentStatusCode'] ?? null,
            'mensagem' => __('Documento :documento: :resultado (estado :estado)', [
                'documento' => $documento,
                'resultado' => $resultado['actionResultCode'] ?? 'OK',
                'estado' => $resultado['documentStatusCode'] ?? '—',
            ]),
        ];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * Uma linha da listagem, com nomes estáveis. Os campos da AGT variam
     * entre respostas (e entre versões do schema); o ecrã não pode andar a
     * adivinhar qual deles veio.
     */
    private function linha(array $l): array
    {
        return [
            'numero' => $l['documentNo'] ?? null,
            'tipo' => $l['documentType'] ?? null,
            'data' => $l['documentDate'] ?? ($l['issueDate'] ?? null),
            'estado' => $l['documentStatus'] ?? null,
            // Descrição da AGT: já vem pronta, não se traduz no ecrã.
            'estado_descricao' => $l['documentStatusDescription'] ?? null,
            'emissor' => $l['issuerTaxRegistrationNumber'] ?? ($l['supplierTaxRegistrationNumber'] ?? null),
            'liquido' => isset($l['netTotal']) ? (float) $l['netTotal'] : null,
            'total' => isset($l['grossTotal']) ? (float) $l['grossTotal'] : null,
        ];
    }

    /**
     * Uma ida à AGT, com o erro sempre em forma de mensagem.
     *
     * O que rebenta lá dentro — chave em falta, rede caída, payload
     * recusado — não pode chegar ao ecrã como um 500 sem texto: sai daqui
     * como `DomainException`, que a API traduz em 422 com o que aconteceu.
     *
     * @param  callable(): array  $chamada
     */
    private function comAAgt(string $oQue, callable $chamada): array
    {
        try {
            $resultado = $chamada();
        } catch (\Throwable $e) {
            Log::error('PainelDoAdquirente: falha ao ' . $oQue, [
                'tenant_id' => $this->tenantId,
                'erro' => $e->getMessage(),
            ]);

            throw new DomainException($e->getMessage(), 0, $e);
        }

        if (!($resultado['ok'] ?? false)) {
            $mensagem = AGTErrorCode::formatList(
                data_get($resultado, 'response.errorList') ?: ($resultado['error'] ?? null)
            );

            throw new DomainException($mensagem ?: __('A AGT não aceitou o pedido para :accao.', ['accao' => $oQue]));
        }

        return $resultado;
    }

    /**
     * Só no ambiente que a empresa usa para emitir.
     *
     * Não é uma formalidade: as facturas recebidas de homologação são
     * documentos de teste, e confirmar um deles julgando que era real (ou o
     * contrário) é um erro fiscal, não um engano de ecrã.
     */
    private function conferir(?string $ambiente, string $accao): void
    {
        if ($ambiente === null) {
            return;
        }

        $this->gestao()->conferir($ambiente, $accao);
    }

    private function exigirPreRequisitos(): void
    {
        $ambiente = $this->ambienteActivo();

        if ($falta = $this->gestao()->emFalta($ambiente)) {
            throw new DomainException(__('Não foi enviado nenhum pedido à AGT. Falta configurar: :lista.', [
                'lista' => implode(', ', $falta),
            ]));
        }
    }

    private function gestao(): GestaoAgt
    {
        return new GestaoAgt($this->tenantId);
    }

    private function definicoes(): InvoicingSettings
    {
        $definicoes = InvoicingSettings::forTenant($this->tenantId);

        if (!$definicoes) {
            throw new DomainException(__('Configurações de facturação da empresa não encontradas.'));
        }

        return $definicoes;
    }

    /** O NIF que identifica o adquirente perante a AGT. */
    private function nif(InvoicingSettings $definicoes): string
    {
        return (string) (
            $definicoes->tenant?->nif
            ?? $definicoes->tenant?->tax_id
            ?? $definicoes->company_nif
            ?? ''
        );
    }
}
