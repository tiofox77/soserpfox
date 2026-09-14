<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTCommunicationLog;
use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\NifDeEmpresa;
use App\Services\Audit\AuditRecorder;
use Closure;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A CONFIGURAÇÃO AGT DE UMA EMPRESA — a única implementação.
 *
 * Tudo o que o ecrã de sempre (Livewire) e o ecrã em React fazem à AGT
 * passa por aqui: os dois ambientes em separado, as chaves de cada um, a
 * activação deliberada, a sincronização das séries, o reenvio das
 * submissões, as consultas e a ficha do contribuinte. Os ecrãs só traduzem
 * o resultado em avisos.
 *
 * Há um ambiente ACTIVO, o que assina e transmite os documentos reais, e há
 * o ambiente que se está a VER. Eram a mesma coisa, e isso obrigava a pôr a
 * empresa em produção só para lá instalar as chaves — ou, pior, bastava
 * espreitar a homologação e guardar por outro motivo qualquer para uma
 * empresa em produção cair para homologação sem ninguém dar por isso. Por
 * isso mudar de ambiente é `activarAmbiente()`, e mais nada o muda.
 */
class GestaoAgt
{
    public const AMBIENTES = ['sandbox' => 'Homologação', 'production' => 'Produção'];

    /**
     * O ListarFacturas NÃO lista o que a empresa emitiu: devolve os documentos
     * em que ela é o ADQUIRENTE. Com o rótulo «Listar facturas» quem só emite
     * via sempre zero e concluía que nada tinha chegado à AGT.
     */
    public const OPERACOES = [
        'listarFacturas' => 'Listar documentos RECEBIDOS (a empresa como adquirente)',
        'consultarFactura' => 'Consultar factura',
        'obterEstado' => 'Obter estado do pedido',
    ];

    /** Tipo AGT da série (o prefixo) por extenso, para o ecrã. */
    public const TIPOS_DE_SERIE = [
        'FT' => 'Factura',
        'FR' => 'Factura-recibo',
        'FA' => 'Factura de adiantamento',
        'FG' => 'Factura global',
        'GF' => 'Factura genérica',
        'AC' => 'Aviso de cobrança',
        'AR' => 'Aviso de cobrança/recibo',
        'TV' => 'Talão de venda',
        'RC' => 'Recibo',
        'RG' => 'Outros recibos',
        'RE' => 'Estorno ou anulação de recibo',
        'ND' => 'Nota de débito',
        'NC' => 'Nota de crédito',
        'AF' => 'Factura/recibo de autofacturação',
        'RP' => 'Prémio ou recibo de prémio',
        'RA' => 'Resseguro aceite',
        'CS' => 'Imputação a co-seguradoras',
        'LD' => 'Imputação a co-seguradora líder',
        'PR' => 'Proforma',
        'GT' => 'Guia de transporte',
        'AD' => 'Adiantamento',
        'FC' => 'Factura de compra',
    ];

    public function __construct(private readonly int $tenantId)
    {
    }

    public static function normalizar(?string $ambiente): string
    {
        return $ambiente === 'production' ? 'production' : 'sandbox';
    }

    public static function rotulo(?string $ambiente): string
    {
        return self::AMBIENTES[self::normalizar($ambiente)];
    }

    /**
     * A empresa que este utilizador pode configurar. O super admin da
     * plataforma pode qualquer uma; os outros, só as suas.
     */
    public static function empresaPermitida(?User $user, ?int $tenantId): ?int
    {
        if (!$tenantId || !Tenant::whereKey($tenantId)->exists()) {
            return null;
        }

        if ($user?->isPlatformSuperAdmin()) {
            return $tenantId;
        }

        return $user?->tenants()->whereKey($tenantId)->exists() ? $tenantId : null;
    }

    /* ─── Ler ─────────────────────────────────────────────────────────── */

    /** O que está gravado, com os valores de quem ainda não gravou nada. */
    public function ler(): array
    {
        $s = InvoicingSettings::where('tenant_id', $this->tenantId)->first();

        return [
            'agt_environment' => self::normalizar($s?->agt_environment),
            'agt_auto_submit' => (bool) ($s?->agt_auto_submit ?? false),
            'agt_eac_code' => (string) ($s?->agt_eac_code ?? ''),
            'agt_require_validation' => (bool) ($s?->agt_require_validation ?? true),
        ];
    }

    /** O ambiente que está mesmo a emitir. */
    public function ambienteActivo(): string
    {
        return $this->ler()['agt_environment'];
    }

    /**
     * Chaves DO AMBIENTE pedido: o Portal do Contribuinte entrega pares
     * diferentes para homologação e para produção. E as credenciais do
     * produtor DESSE ambiente: as de homologação não autenticam contra a
     * AGT real.
     */
    public function chaves(string $ambiente): array
    {
        $ambiente = self::normalizar($ambiente);

        return [
            'publica' => Storage::disk('local')->exists(AGTKeyStore::publicKeyPath($this->tenantId, $ambiente)),
            'privada' => Storage::disk('local')->exists(AGTKeyStore::privateKeyPath($this->tenantId, $ambiente)),
            'produtor' => AGTProducerStore::temCredenciais($ambiente),
        ];
    }

    /**
     * Estado dos DOIS ambientes de uma vez. Ver um de cada vez escondia o
     * essencial: que produção ainda não tem chaves e portanto não pode ser
     * activada.
     */
    public function estadoAmbientes(string $aVer): array
    {
        $aVer = self::normalizar($aVer);
        $activo = $this->ambienteActivo();
        $estado = [];

        foreach (self::AMBIENTES as $ambiente => $rotulo) {
            $estado[$ambiente] = [
                'rotulo' => $rotulo,
                'chaves' => AGTKeyStore::hasKeyPair($this->tenantId, $ambiente),
                'activo' => $activo === $ambiente,
                'a_ver' => $aVer === $ambiente,
                'produtor' => AGTProducerStore::temChaves($ambiente) && AGTProducerStore::temCredenciais($ambiente),
            ];
        }

        return $estado;
    }

    public function relatorio(string $ambiente): array
    {
        return (new AGTService($this->tenantId))->getComplianceReport(self::normalizar($ambiente));
    }

    /**
     * Só o ambiente pedido: homologação e produção são universos separados
     * e misturá-los faz um documento de teste passar por real.
     */
    public function logs(string $ambiente, int $limite = 20): Collection
    {
        return AGTCommunicationLog::where('tenant_id', $this->tenantId)
            ->where('agt_environment', self::normalizar($ambiente))
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Histórico completo: documentos processados não podem "desaparecer"
     * quando passam de submitted para validated/rejected.
     */
    public function submissoes(string $ambiente, int $limite = 50): Collection
    {
        return AGTSubmission::where('tenant_id', $this->tenantId)
            ->where('agt_environment', self::normalizar($ambiente))
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Todas as séries activas — e o estado de cada uma é o DESTE ambiente.
     *
     * Escondiam-se as registadas no outro ambiente, para uma série de
     * homologação não passar por registada em produção. Mas escondê-las tirava
     * do ecrã exactamente as que é preciso registar depois de mudar de
     * ambiente. Aparecem, «por registar» aqui (ver estadoDaSerie()).
     */
    public function series(string $ambiente): Collection
    {
        return InvoicingSeries::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get();
    }

    /**
     * Uma série vista de um ambiente: registada lá, por registar, recusada
     * pela AGT, ou fora da AGT (tipos não fiscais).
     *
     * @return array{estado: string, tipo_rotulo: string, atcud: ?string, erros: array<int, array{codigo: ?string, descricao: string}>}
     */
    public static function estadoDaSerie(InvoicingSeries $s, string $ambiente): array
    {
        $ambiente = self::normalizar($ambiente);
        $prefixo = strtoupper((string) $s->prefix);
        $rotulo = self::TIPOS_DE_SERIE[$prefixo]
            ?? (InvoicingSeries::prefixoDe((string) $s->document_type) ? (self::TIPOS_DE_SERIE[InvoicingSeries::prefixoDe((string) $s->document_type)] ?? null) : null)
            ?? ($prefixo !== '' ? $prefixo : (string) $s->document_type);

        if (!$s->isAGTEligible()) {
            return ['estado' => 'nao_aplicavel', 'tipo_rotulo' => $rotulo, 'atcud' => null, 'erros' => []];
        }

        $desteAmbiente = $s->agt_environment === $ambiente;

        // Só os erros que a AGT deu NESTE ambiente: a recusa de homologação
        // não diz nada sobre o registo em produção.
        $erros = $desteAmbiente
            ? collect(AGTErrorCode::lista(is_array($s->agt_response) ? $s->agt_response : []))
                ->map(fn (array $e) => ['codigo' => $e['codigo'], 'descricao' => $e['descricao']])
                ->values()->all()
            : [];

        $estado = match (true) {
            filled($s->agt_series_id) && $desteAmbiente => 'registada',
            $erros !== [] => 'rejeitada',
            default => 'por_registar',
        };

        return [
            'estado' => $estado,
            'tipo_rotulo' => $rotulo,
            'atcud' => $estado === 'registada' ? ($s->atcud_validation_code ?: $s->agt_series_id) : null,
            'erros' => $estado === 'registada' ? [] : $erros,
        ];
    }

    /**
     * PRONTO PARA PRODUÇÃO? A lista, item a item.
     *
     * Activar produção só com o par RSA do contribuinte não chegava: as
     * credenciais do produtor e o número de certificação caem, sem aviso,
     * nos valores partilhados de homologação — e a AGT real recusa-os todos
     * (401 nas credenciais, E39 no número). A empresa ficava «em produção»
     * a falhar cada documento. Aqui exige-se o de PRODUÇÃO de cada um.
     *
     * A mesma lista vai no `estado` e na recusa do activarAmbiente(), para o
     * ecrã mostrar o que falta antes de alguém carregar no botão.
     *
     * @return array<int, array{chave: string, rotulo: string, ok: bool}>
     */
    public function prontidaoProducao(): array
    {
        $tenant = Tenant::find($this->tenantId);
        $credenciais = AGTProducerStore::credenciais('production');

        return [
            [
                'chave' => 'rsa_contribuinte',
                'rotulo' => __('Par RSA de Produção do contribuinte (Portal do Contribuinte)'),
                'ok' => AGTKeyStore::hasKeyPair($this->tenantId, 'production'),
            ],
            [
                'chave' => 'produtor',
                'rotulo' => __('Credenciais do produtor próprias de Produção'),
                'ok' => $credenciais['proprias'] && $credenciais['username'] !== '' && $credenciais['password'] !== '',
            ],
            [
                'chave' => 'rsa_produtor',
                'rotulo' => __('Chave RSA do produtor'),
                'ok' => AGTProducerStore::temChaves('production'),
            ],
            [
                'chave' => 'certificado',
                'rotulo' => __('Número de certificação de Produção próprio'),
                'ok' => AGTProducerStore::temCertificacaoPropria('production'),
            ],
            [
                'chave' => 'nif',
                'rotulo' => __('NIF da empresa'),
                'ok' => filled($tenant?->nif ?? $tenant?->tax_id ?? null),
            ],
        ];
    }

    /**
     * Quantas submissões ainda estão a meio (por enviar ou à espera do
     * veredicto) em cada ambiente. É o que o ecrã tem de dizer antes de uma
     * troca: essas ficam paradas até a empresa voltar ao ambiente delas.
     *
     * @return array{sandbox: int, production: int}
     */
    public function pendentesPorAmbiente(): array
    {
        $contagem = AGTSubmission::where('tenant_id', $this->tenantId)
            ->whereIn('status', [AGTSubmission::STATUS_PENDING, AGTSubmission::STATUS_SUBMITTED])
            ->selectRaw('agt_environment, COUNT(*) as n')
            ->groupBy('agt_environment')
            ->pluck('n', 'agt_environment');

        return [
            'sandbox' => (int) ($contagem['sandbox'] ?? 0),
            'production' => (int) ($contagem['production'] ?? 0),
        ];
    }

    /**
     * A EMPRESA ESTÁ MESMO A COMUNICAR À AGT?
     *
     * É o aviso do antigo componente `agt/aviso-comunicacao`: ligar o envio
     * automático não chega. Sem chaves o documento não se assina, sem CAE a AGT
     * recusa, em homologação o que segue não tem valor fiscal, e sem o
     * produtor configurado nem sequer há com quem falar. Em todos os casos a
     * venda faz-se e imprime-se na mesma — e ninguém dá por nada. Uma farmácia
     * chegou às 1108 facturas emitidas e nenhuma comunicada.
     *
     * A contagem segue a regra das Inconsistências da plataforma (facturas de
     * venda sem estado de comunicação), acrescida das que ficaram «pendentes»
     * ou recusadas: nenhuma dessas chegou ao fisco.
     *
     * @return array{comunica: bool, motivos: array<int, string>, emitidos_30d: int, por_comunicar: int}
     */
    public function comunicacao(): array
    {
        $d = $this->ler();
        $ambiente = $d['agt_environment'];
        $motivos = [];

        if (!AGTKeyStore::hasKeyPair($this->tenantId, $ambiente)) {
            $motivos[] = __('Não há chaves RSA instaladas para o ambiente activo. Sem elas o documento não pode ser assinado, e a AGT só aceita documentos assinados.');
        }
        if (blank($d['agt_eac_code'])) {
            $motivos[] = __('O código CAE não está definido. A AGT exige-o em todas as submissões e recusa as que chegam sem ele.');
        }
        if ($ambiente !== 'production') {
            $motivos[] = __('O ambiente activo é o de homologação (testes). O que for enviado fica no ambiente de testes da AGT e não conta como documento comunicado.');
        }
        if (!$d['agt_auto_submit']) {
            $motivos[] = __('O envio automático está desligado: os documentos só vão à AGT quando alguém os enviar à mão.');
        }
        if (!(AGTProducerStore::temCredenciais($ambiente) && AGTProducerStore::temChaves($ambiente))) {
            $motivos[] = __('O produtor do software não está configurado para o ambiente activo (credenciais ou chave). Contacte o suporte.');
        }

        // Sem o escopo da empresa ACTIVA: o super admin vê aqui outra empresa,
        // e o escopo contava as facturas da dele. O filtro por esta vai à mão.
        $base = SalesInvoice::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'draft'));

        $emitidos = (clone $base)->count();
        $porComunicar = (clone $base)
            ->where(fn ($q) => $q->whereNull('agt_status')->orWhereNotIn('agt_status', ['submitted', 'validated']))
            ->count();

        return [
            'comunica' => $motivos === [],
            'motivos' => $motivos,
            'emitidos_30d' => $emitidos,
            'por_comunicar' => $porComunicar,
        ];
    }

    /** Classes CAE (5 dígitos) — é o nível que a AGT espera em eacCode. */
    public static function classesCae(): Collection
    {
        return Cache::remember('agt_cae_classes', 3600, fn () => DB::table('agt_cae_codes')
            ->where('is_active', true)
            ->where('level', 'class')
            ->orderBy('code')
            ->get(['code', 'description']));
    }

    /** O que falta para falar com a AGT neste ambiente. */
    public function emFalta(string $ambiente): array
    {
        $c = $this->chaves($ambiente);
        $falta = [];

        if (!$c['produtor']) {
            $falta[] = 'credenciais globais do produtor';
        }
        if (!$c['publica']) {
            $falta[] = 'chave pública do Portal AGT';
        }
        if (!$c['privada']) {
            $falta[] = 'chave privada do Portal AGT';
        }

        return $falta;
    }

    /* ─── Definições ──────────────────────────────────────────────────── */

    /**
     * Os dois interruptores são OBRIGATÓRIOS.
     *
     * Com `boolean` apenas, um POST que só trouxesse o CAE gravava
     * `agt_auto_submit` como falso (o `?? false` do guardar) — e a empresa
     * deixava de comunicar à AGT por ter corrigido um código. Quem grava as
     * definições diz o que quer em cada um.
     */
    public static function regras(): array
    {
        return [
            'agt_auto_submit' => ['required', 'boolean'],
            'agt_eac_code' => ['nullable', 'string', 'max:8', self::regraDoCae()],
            'agt_require_validation' => ['required', 'boolean'],
        ];
    }

    /**
     * O CAE tem de existir no catálogo — o mesmo que o ecrã oferece (classes,
     * 5 dígitos, activas).
     *
     * Um código inventado grava-se sem queixa e só aparece na primeira
     * submissão recusada. Numa instalação onde o catálogo ainda não foi
     * carregado não há contra o quê comparar: aí exige-se só a forma de uma
     * classe, para não impedir ninguém de configurar a empresa.
     */
    public static function regraDoCae(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $codigo = trim((string) $valor);

            if ($codigo === '') {
                return;
            }

            $catalogo = self::classesCae();

            if ($catalogo->isEmpty()) {
                if (!preg_match('/^\d{5}$/', $codigo)) {
                    $falhar(__('O código CAE tem cinco dígitos (a classe da actividade).'));
                }

                return;
            }

            if (!$catalogo->contains(fn ($c) => (string) $c->code === $codigo)) {
                $falhar(__('O código CAE :codigo não existe no catálogo da AGT.', ['codigo' => $codigo]));
            }
        };
    }

    /**
     * Sem agt_environment de propósito: mudar de ambiente é a acção
     * activarAmbiente(), não um efeito lateral de guardar o CAE.
     */
    public function guardar(array $d): void
    {
        $this->definicoes()->update([
            'agt_auto_submit' => (bool) ($d['agt_auto_submit'] ?? false),
            // CAE: vazio grava NULL, para o mapper cair no marcador em vez de ''
            'agt_eac_code' => ($d['agt_eac_code'] ?? null) ?: null,
            'agt_require_validation' => (bool) ($d['agt_require_validation'] ?? true),
        ]);
    }

    /**
     * Passa a emitir pelo ambiente pedido. Acção deliberada e separada do
     * Guardar: é ela que decide se os documentos desta empresa vão para a
     * AGT real ou para a de testes.
     *
     * DUAS GUARDAS, por esta ordem:
     *
     *  · produção só com a prontidão toda (prontidaoProducao()) — senão a
     *    empresa ficava «em produção» a falhar cada documento;
     *  · em qualquer sentido, só com `confirmar`. Voltar a homologação com
     *    documentos de produção por enviar era mandá-los para a AGT de testes
     *    (fechado na raiz: o despacho, a consulta e o reenvio já só tocam nas
     *    submissões do ambiente activo). Não se bloqueia a troca, mas quem
     *    carrega no botão tem de ver quantas ficam à espera — e dizer que sim.
     *
     * @return array{tipo: string, mensagem: string, pendentes_por_ambiente: array{sandbox: int, production: int}}
     *
     * @throws RecusaComDetalhes  `prontidao` quando falta algo; `confirmar_necessario` sem confirmação
     */
    public function activarAmbiente(string $novo, bool $confirmar = false): array
    {
        $novo = self::normalizar($novo);
        $anterior = $this->ambienteActivo();
        $pendentes = $this->pendentesPorAmbiente();

        if ($novo === $anterior) {
            return ['tipo' => 'info', 'mensagem' => __('Já é este o ambiente activo.'), 'pendentes_por_ambiente' => $pendentes];
        }

        if ($novo === 'production') {
            $prontidao = $this->prontidaoProducao();
            $falta = array_values(array_filter($prontidao, fn ($item) => !$item['ok']));

            if ($falta !== []) {
                // O par RSA à cabeça: sem ele não se assina documento nenhum.
                $mensagem = collect($falta)->contains('chave', 'rsa_contribuinte')
                    ? __('Instale primeiro o par RSA de Produção do Portal do Contribuinte. Sem ele nenhum documento é assinado.')
                    : __('Ainda não é possível activar Produção. Falta: :lista.', [
                        'lista' => collect($falta)->pluck('rotulo')->implode('; '),
                    ]);

                throw new RecusaComDetalhes($mensagem, ['prontidao' => $prontidao]);
            }
        }

        if (!$confirmar) {
            throw new RecusaComDetalhes(
                __('Confirme a troca para :ambiente. :n submissão(ões) de :anterior ficam à espera até a empresa voltar a esse ambiente.', [
                    'ambiente' => self::rotulo($novo),
                    'n' => $pendentes[$anterior],
                    'anterior' => self::rotulo($anterior),
                ]),
                ['confirmar_necessario' => true, 'pendentes_por_ambiente' => $pendentes]
            );
        }

        $this->definicoes()->update(['agt_environment' => $novo]);

        $this->auditar('agt.ambiente.activado', [
            'de' => $anterior,
            'para' => $novo,
            'pendentes_por_ambiente' => $pendentes,
        ]);

        $rotulo = $novo === 'production' ? 'PRODUÇÃO' : 'Homologação';
        $mensagem = "Ambiente activo: {$rotulo}. Os próximos documentos seguem por aqui.";

        if ($pendentes[$anterior] > 0) {
            $mensagem .= ' ' . __(':n submissão(ões) de :anterior ficam à espera até a empresa voltar a esse ambiente.', [
                'n' => $pendentes[$anterior],
                'anterior' => self::rotulo($anterior),
            ]);
        }

        return ['tipo' => 'success', 'mensagem' => $mensagem, 'pendentes_por_ambiente' => $pendentes];
    }

    /* ─── Chaves do contribuinte ──────────────────────────────────────── */

    /**
     * Valida o par e guarda-o no ambiente pedido, sem sobrepor o outro.
     * As chaves erradas saem como erros de validação com o campo certo.
     */
    public function guardarChaves(string $ambiente, string $publica, string $privada, bool $confirmar = false): string
    {
        $publica = trim($publica) . PHP_EOL;
        $privada = trim($privada) . PHP_EOL;
        $recursoPublico = @openssl_pkey_get_public($publica);
        $recursoPrivado = @openssl_pkey_get_private($privada);

        if (!$recursoPublico) {
            throw ValidationException::withMessages(['contributorPublicKey' => 'A chave pública não é um PEM RSA válido.']);
        }
        if (!$recursoPrivado) {
            throw ValidationException::withMessages(['contributorPrivateKey' => 'A chave privada não é um PEM RSA válido ou está protegida por password.']);
        }

        $detalhesPublico = openssl_pkey_get_details($recursoPublico);
        $detalhesPrivado = openssl_pkey_get_details($recursoPrivado);
        if (
            empty($detalhesPublico['rsa']['n'])
            || empty($detalhesPrivado['rsa']['n'])
            || !hash_equals($detalhesPublico['rsa']['n'], $detalhesPrivado['rsa']['n'])
        ) {
            throw ValidationException::withMessages(['contributorPrivateKey' => 'As chaves pública e privada não pertencem ao mesmo par RSA.']);
        }

        $ambiente = self::normalizar($ambiente);
        $substitui = AGTKeyStore::hasKeyPair($this->tenantId, $ambiente);

        // Trocar o par que está a ASSINAR os documentos da empresa é um acto
        // com consequência imediata: um par errado e a próxima factura sai
        // assinada com uma chave que a AGT não conhece.
        $this->exigirConfirmacaoNoActivo($ambiente, $substitui, $confirmar, __('Estas são as chaves que assinam os documentos de :ambiente, o ambiente activo. Confirme para as substituir.', [
            'ambiente' => self::rotulo($ambiente),
        ]));

        $anterior = $substitui ? $this->impressaoDaPublica($ambiente) : null;

        AGTKeyStore::store($this->tenantId, $publica, $privada, $ambiente);

        $this->auditar('agt.chaves.guardadas', [
            'ambiente' => $ambiente,
            'substituiu' => $substitui,
            'publica_sha256' => hash('sha256', $publica),
            'publica_anterior_sha256' => $anterior,
        ]);

        return 'Par de chaves guardado para ' . self::rotulo($ambiente) . '. O outro ambiente não foi alterado.';
    }

    /** Só as do ambiente pedido — o outro par tem de sobreviver. */
    public function removerChaves(string $ambiente, bool $confirmar = false): string
    {
        $ambiente = self::normalizar($ambiente);
        $existem = AGTKeyStore::hasKeyPair($this->tenantId, $ambiente)
            || Storage::disk('local')->exists(AGTKeyStore::publicKeyPath($this->tenantId, $ambiente))
            || Storage::disk('local')->exists(AGTKeyStore::privateKeyPath($this->tenantId, $ambiente));

        // Apagar as do ambiente ACTIVO pára a facturação no instante seguinte:
        // nenhum documento se assina. Pede-se um sim explícito.
        $this->exigirConfirmacaoNoActivo($ambiente, $existem, $confirmar, __('Estas são as chaves que assinam os documentos de :ambiente, o ambiente activo. Sem elas nenhum documento é assinado. Confirme para as remover.', [
            'ambiente' => self::rotulo($ambiente),
        ]));

        // A impressão digital da PÚBLICA, lida antes de apagar: é o que permite
        // saber mais tarde que par foi retirado. A chave em si nunca vai à trilha.
        $impressao = $this->impressaoDaPublica($ambiente);

        $dir = AGTKeyStore::directory($this->tenantId, $ambiente);
        Storage::disk('local')->delete(["{$dir}/public_key.pem", "{$dir}/private_key.pem"]);

        // Legado (sem ambiente): só se apaga quando se está no ambiente a que
        // essas chaves pertenciam, que a migração assumiu ser homologação.
        if ($ambiente === 'sandbox') {
            $legado = AGTKeyStore::legacyDirectory($this->tenantId);
            Storage::disk('local')->delete(["{$legado}/public_key.pem", "{$legado}/private_key.pem"]);
        }

        // Só fica na trilha quando havia o que remover: um clique sobre um
        // ambiente já vazio não é um acto sobre chave nenhuma.
        if ($existem) {
            $this->auditar('agt.chaves.removidas', [
                'ambiente' => $ambiente,
                'era_o_activo' => $ambiente === $this->ambienteActivo(),
                'publica_sha256' => $impressao,
            ]);
        }

        return 'Chaves de ' . self::rotulo($ambiente) . ' removidas. O outro ambiente não foi alterado.';
    }

    /* ─── Falar com a AGT ─────────────────────────────────────────────── */

    /**
     * Testa o ambiente pedido — é o que permite validar as chaves de
     * produção antes de as pôr a emitir. Sem os pré-requisitos não sai
     * pedido nenhum.
     */
    /**
     * @return array{ok: bool, mensagem: string, ambiente: string, http: ?int}
     */
    public function testarLigacao(string $ambiente): array
    {
        $ambiente = self::normalizar($ambiente);
        $this->exigirPreRequisitos($ambiente);

        try {
            $r = (new AGTClient($this->tenantId, $ambiente))->testConnection();
        } catch (\Exception $e) {
            $r = ['success' => false, 'error' => $e->getMessage()];
        }

        $ok = (bool) ($r['success'] ?? false);

        return [
            'ok' => $ok,
            'mensagem' => (string) ($ok
                ? ($r['message'] ?? __('Conexão estabelecida com sucesso!'))
                : ($r['error'] ?? __('Falha na conexão'))),
            'ambiente' => (string) ($r['environment'] ?? $ambiente),
            'http' => isset($r['http_status']) ? (int) $r['http_status'] : null,
        ];
    }

    /**
     * Acções que escrevem na AGT (registar séries, reenviar documentos)
     * correm sempre no ambiente ACTIVO. Executá-las enquanto se olha para o
     * outro registava séries em produção com o ecrã a dizer homologação.
     */
    public function conferir(string $aVer, string $accao): void
    {
        $aVer = self::normalizar($aVer);
        $activo = $this->ambienteActivo();

        if ($aVer === $activo) {
            return;
        }

        throw new AmbienteErradoException(
            'Está a ver ' . self::rotulo($aVer) . ' mas a empresa emite em ' . self::rotulo($activo)
            . ". {$accao} iria para " . self::rotulo($activo) . '. Volte ao ambiente activo ou active este.'
        );
    }

    public function sincronizarSeries(string $aVer): array
    {
        $this->conferir($aVer, 'A sincronização de séries');
        $this->exigirPreRequisitos($aVer);

        $r = (new AGTService($this->tenantId))->syncAllSeries();

        $this->auditar('agt.series.sincronizadas', [
            'ambiente' => $this->ambienteActivo(),
            'total' => $r['total'] ?? 0,
            'registadas' => $r['success'] ?? 0,
            'falharam' => $r['failed'] ?? 0,
            'series' => collect($r['details'] ?? [])->map(fn ($d) => [
                'serie_id' => $d['serie_id'] ?? null,
                'ok' => $d['ok'] ?? false,
                'codigo_erro' => $d['codigo_erro'] ?? null,
                'ambiente_anterior' => $d['ambiente_anterior'] ?? null,
            ])->values()->all(),
        ]);

        return $r;
    }

    /**
     * Pergunta à AGT pelo estado das submissões que ficaram a meio — só as do
     * ambiente activo, que é o único a quem a pergunta chega.
     */
    public function actualizarEstados(): int
    {
        $service = new QueryService(InvoicingSettings::forTenant($this->tenantId));
        $actualizadas = 0;

        $submissoes = AGTSubmission::where('tenant_id', $this->tenantId)
            ->doAmbiente($this->ambienteActivo())
            ->whereIn('status', ['pending', 'submitted'])
            ->whereNotNull('agt_reference')
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($submissoes as $submissao) {
            try {
                $resultado = $service->consultByRequestId($submissao->agt_reference);
                $codigo = (string) ($resultado['resultCode'] ?? '');
                $corpo = $resultado['response'] ?? [];

                if ($codigo === '0') {
                    $submissao->markAsValidated($submissao->agt_reference, $submissao->atcud, $corpo);
                    $actualizadas++;
                } elseif ($codigo === '2') {
                    $submissao->markAsRejected(
                        AGTErrorCode::primeiroCodigo($corpo) ?? 'RESULT_CODE_2',
                        AGTErrorCode::formatarResposta($corpo) ?: 'Documento rejeitado pela AGT.',
                        $corpo
                    );
                    $actualizadas++;
                }
            } catch (\Throwable $e) {
                Log::warning('GestaoAgt: falha ao actualizar submissao', [
                    'tenant_id' => $this->tenantId,
                    'submission_id' => $submissao->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $actualizadas;
    }

    /** Uma submissão DESTA empresa, ou nada — nem por id. */
    public function submissao(int $id): AGTSubmission
    {
        $s = AGTSubmission::find($id);

        if (!$s || (int) $s->tenant_id !== $this->tenantId) {
            throw new DomainException(__('Submissão não encontrada'));
        }

        return $s;
    }

    /**
     * Repor as tentativas de uma submissão esgotada e reenviá-la já.
     *
     * Quando a recusa foi culpa NOSSA (versão do schema fora de prazo,
     * imposto mal arredondado), o documento fica com as tentativas gastas e
     * ninguém conseguia reenviar sem ir à base. Corrigido o código, quem
     * gere a empresa carrega aqui: o contador volta a zero e o documento
     * segue no acto. O acto fica com rasto próprio (auditoria por modelo).
     */
    public function reporEReenviar(int $id, string $aVer): array
    {
        $this->conferir($aVer, 'O reenvio do documento');
        $s = $this->submissao($id);
        $this->exigirSubmissaoDoAmbienteActivo($s);
        $this->exigirEstadoReenviavel($s);

        // Repor só quando o «Reenviar» já não chega. Com tentativas por gastar
        // a reposição só servia para apagar o rasto das que falharam.
        if (!$s->podeRepor($this->ambienteActivo())) {
            throw new DomainException(__('Ainda restam tentativas (:feitas de :max). Use «Reenviar»; repor só é preciso depois de as esgotar.', [
                'feitas' => (int) $s->retry_count,
                'max' => AGTSubmission::MAX_TENTATIVAS_DO_BOTAO,
            ]));
        }

        $antes = ['status' => $s->status, 'retry_count' => (int) $s->retry_count, 'error_code' => $s->error_code];

        $s->update([
            'status' => AGTSubmission::STATUS_PENDING,
            'retry_count' => 0,
            'error_code' => null,
            'error_message' => null,
        ]);

        $this->auditar('agt.submissao.reposta', [
            'submissao_id' => $s->id,
            'documento' => $s->document_number,
            'ambiente' => $s->agt_environment,
            'antes' => $antes,
        ], $s);

        return $this->reenviar($id, $aVer);
    }

    public function reenviar(int $id, string $aVer): array
    {
        $this->conferir($aVer, 'O reenvio do documento');
        $s = $this->submissao($id);
        $this->exigirSubmissaoDoAmbienteActivo($s);
        $this->exigirEstadoReenviavel($s);

        if (!$s->canRetry()) {
            throw new DomainException(__('Máximo de tentativas atingido'));
        }

        $documento = $s->document;
        if (!$documento) {
            throw new DomainException(__('Documento não encontrado'));
        }

        return (new AGTService($this->tenantId))->submitToAGT($documento);
    }

    public static function regrasDaConsulta(): array
    {
        return [
            'apiOperation' => 'required|in:' . implode(',', array_keys(self::OPERACOES)),
            'apiRequestId' => 'required_if:apiOperation,obterEstado|nullable|string|max:100',
            'apiDocumentNo' => 'required_if:apiOperation,consultarFactura|nullable|string|max:100',
            'apiDateFrom' => 'required_if:apiOperation,listarFacturas|nullable|date',
            'apiDateTo' => 'required_if:apiOperation,listarFacturas|nullable|date|after_or_equal:apiDateFrom',
        ];
    }

    /**
     * Consulta: corre no ambiente que se está a ver. É só leitura.
     *
     * @return array{ok: bool, mensagem: string, http: ?int, ms: int, testado_em: string, data: array}
     */
    public function consultar(string $operacao, array $p, string $ambiente): array
    {
        $inicio = microtime(true);

        try {
            $client = new AGTClient($this->tenantId, self::normalizar($ambiente));
            $resultado = match ($operacao) {
                'obterEstado' => $client->getStatus(trim((string) ($p['apiRequestId'] ?? ''))),
                'consultarFactura' => $client->getInvoice(trim((string) ($p['apiDocumentNo'] ?? ''))),
                default => $client->listInvoices([
                    'date_from' => $p['apiDateFrom'] ?? null,
                    'date_to' => $p['apiDateTo'] ?? null,
                ]),
            };
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['success' => false, 'error' => $e->getMessage()];
        }

        $ok = (bool) ($resultado['success'] ?? false);

        return [
            'ok' => $ok,
            'mensagem' => (string) ($ok
                ? ($resultado['message'] ?? __('Consulta executada.'))
                : ($resultado['error'] ?? __('A AGT não respondeu à consulta.'))),
            'http' => isset($resultado['status']) ? (int) $resultado['status'] : null,
            'ms' => (int) ((microtime(true) - $inicio) * 1000),
            'testado_em' => now()->format('d/m/Y H:i:s'),
            'data' => $resultado + ['operation' => $operacao, 'ambiente' => self::normalizar($ambiente)],
        ];
    }

    /* ─── A ficha do contribuinte ─────────────────────────────────────── */

    public static function regrasDoContribuinte(): array
    {
        return [
            'tax_registration_number' => ['nullable', 'string', 'max:15'],
            'agt_establishment_number' => ['required', 'string', 'max:200'],
            'agt_notification_emails' => ['nullable', 'string', 'max:1000'],
            'agt_eac_code' => ['nullable', 'string', 'max:8', self::regraDoCae()],
            'agt_auto_submit' => ['boolean'],
            'agt_require_validation' => ['boolean'],
        ];
    }

    /** O pedido traz um NIF DIFERENTE do que a empresa tem? */
    public function nifMuda(array $d): bool
    {
        $novo = strtoupper(trim((string) ($d['tax_registration_number'] ?? '')));

        if ($novo === '') {
            return false;
        }

        $tenant = Tenant::find($this->tenantId);

        return $novo !== strtoupper(trim((string) ($tenant?->nif ?? $tenant?->tax_id ?? '')));
    }

    /**
     * MUDAR O NIF PELA FICHA DA AGT.
     *
     * O NIF vai em cada documento assinado e em cada série registada: é por
     * ele que a AGT conhece a empresa. Trocá-lo depois de haver séries
     * registadas ou documentos comunicados deixa uns e outros a apontar para
     * um contribuinte que já não é este — as séries deixam de valer e os
     * documentos seguintes são recusados. Isso é conversa com o suporte, não
     * um campo que se edita. Antes disso, o número tem de ser de EMPRESA.
     */
    public function exigirNifMutavel(string $nif): void
    {
        $v = Validator::make(['tax_registration_number' => $nif], ['tax_registration_number' => [new NifDeEmpresa()]]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $temSeries = InvoicingSeries::where('tenant_id', $this->tenantId)->whereNotNull('agt_series_id')->exists();
        $temSubmissoes = AGTSubmission::where('tenant_id', $this->tenantId)->exists();

        if ($temSeries || $temSubmissoes) {
            throw new DomainException(__('O NIF desta empresa já está em séries registadas ou documentos comunicados à AGT e não se muda aqui. Fale com o suporte.'));
        }
    }

    public function lerContribuinte(): array
    {
        $s = InvoicingSettings::where('tenant_id', $this->tenantId)->first();
        $tenant = Tenant::find($this->tenantId);
        $ambiente = self::normalizar($s?->agt_environment);

        return [
            'agt_environment' => $ambiente,
            'tax_registration_number' => (string) ($tenant?->nif ?? $tenant?->tax_id ?? ''),
            'agt_establishment_number' => (string) ($s?->agt_establishment_number ?? 'SEDE'),
            'agt_auto_submit' => (bool) ($s?->agt_auto_submit ?? false),
            'agt_require_validation' => (bool) ($s?->agt_require_validation ?? true),
            'agt_notification_emails' => (string) ($s?->agt_notification_emails ?? ''),
            'agt_eac_code' => $s?->agt_eac_code,
            'produtor' => AGTProducerStore::temCredenciais($ambiente),
            'chave_legado' => Storage::disk('local')->exists($this->caminhoDaChaveLegado()),
        ];
    }

    /** Emails de aviso: um CSV que tem de ser todo válido. Devolve o primeiro inválido, ou nada. */
    public static function emailInvalido(?string $csv): ?string
    {
        foreach (array_filter(array_map('trim', explode(',', (string) $csv))) as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    public function guardarContribuinte(array $d): void
    {
        if ($invalido = self::emailInvalido($d['agt_notification_emails'] ?? null)) {
            throw ValidationException::withMessages(['agt_notification_emails' => "Email inválido: {$invalido}"]);
        }

        // O NIF primeiro: uma recusa aqui não pode deixar o resto gravado a meio.
        $mudaNif = $this->nifMuda($d);
        if ($mudaNif) {
            $this->exigirNifMutavel(strtoupper(trim((string) $d['tax_registration_number'])));
        }

        $campos = [
            'agt_establishment_number' => $d['agt_establishment_number'],
            'agt_notification_emails' => ($d['agt_notification_emails'] ?? null) ?: null,
            'agt_eac_code' => ($d['agt_eac_code'] ?? null) ?: null,
        ];

        // Os interruptores só quando vêm no pedido. O `?? false` desligava o
        // envio automático a quem gravasse só o estabelecimento.
        foreach (['agt_auto_submit', 'agt_require_validation'] as $interruptor) {
            if (array_key_exists($interruptor, $d) && $d[$interruptor] !== null) {
                $campos[$interruptor] = (bool) $d[$interruptor];
            }
        }

        $this->definicoes()->update($campos);

        // O NIF é da empresa, não das definições da facturação.
        if ($mudaNif) {
            Tenant::find($this->tenantId)?->update(['nif' => strtoupper(trim((string) $d['tax_registration_number']))]);
        }
    }

    /**
     * A CHAVE PRIVADA DO MODO ANTIGO, sem ambiente.
     *
     * O ecrã de configuração AGT guarda pares POR AMBIENTE — é o caminho de
     * hoje. Esta fica pelo ecrã do contribuinte, para as instalações que ainda
     * assinam com ela e que nunca correram o `agt:migrate-keys`.
     *
     * VALIDA-SE ANTES DE GRAVAR. Um texto que não seja um PEM RSA legível
     * escreve-se na mesma sem queixa nenhuma, e o defeito só aparece na
     * primeira factura que a AGT recusa — longe daqui, e sem ninguém ligar
     * uma coisa à outra. O erro sai no campo, como sai no par por ambiente.
     */
    public function guardarChaveLegado(string $pem): void
    {
        $pem = trim($pem) . PHP_EOL;

        if (!@openssl_pkey_get_private($pem)) {
            throw ValidationException::withMessages([
                'contributor_private_key' => 'A chave privada não é um PEM RSA válido ou está protegida por password.',
            ]);
        }

        $caminho = $this->caminhoDaChaveLegado();
        $anterior = $this->impressaoDaChavePrivada($caminho);

        Storage::disk('local')->put($caminho, $pem);

        $this->auditar('agt.chave_legado.guardada', [
            'publica_sha256' => $this->impressaoDaChavePrivada($caminho),
            'publica_anterior_sha256' => $anterior,
        ]);
    }

    /**
     * Remover a chave do modo antigo pede o mesmo sim que as do ambiente
     * activo: as empresas que ainda a usam deixam de assinar no instante
     * seguinte.
     */
    public function removerChaveLegado(bool $confirmar = false): void
    {
        $caminho = $this->caminhoDaChaveLegado();
        $existe = Storage::disk('local')->exists($caminho);

        if ($existe && !$confirmar) {
            throw new RecusaComDetalhes(
                __('Esta chave pode estar a assinar os documentos da empresa. Confirme para a remover.'),
                ['confirmar_necessario' => true]
            );
        }

        $impressao = $this->impressaoDaChavePrivada($caminho);

        Storage::disk('local')->delete($caminho);

        if ($existe) {
            $this->auditar('agt.chave_legado.removida', ['publica_sha256' => $impressao]);
        }
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::firstOrCreate(
            ['tenant_id' => $this->tenantId],
            ['default_currency' => 'AOA']
        );
    }

    /**
     * A submissão é do ambiente activo? Uma do outro fica quieta: reenviá-la
     * mandava-a para a AGT errada, que é o defeito que isto fecha.
     */
    private function exigirSubmissaoDoAmbienteActivo(AGTSubmission $s): void
    {
        $activo = $this->ambienteActivo();

        if (!$s->eDoAmbiente($activo)) {
            throw new AmbienteErradoException(__('Esta submissão pertence a :dela e a empresa emite em :activo. Não se reenvia daqui: fica à espera até a empresa voltar a :dela.', [
                'dela' => self::rotulo($s->agt_environment),
                'activo' => self::rotulo($activo),
            ]));
        }
    }

    /** Os estados que nunca se reenviam, cada um com a sua razão. */
    private function exigirEstadoReenviavel(AGTSubmission $s): void
    {
        match ($s->status) {
            AGTSubmission::STATUS_VALIDATED => throw new DomainException(__('Este documento já foi validado pela AGT.')),
            // Está na AGT à espera de veredicto: reenviar era pedir-lhe que
            // o registasse outra vez enquanto ainda decide o primeiro.
            AGTSubmission::STATUS_SUBMITTED => throw new DomainException(__('A AGT ainda está a processar — actualize o estado.')),
            AGTSubmission::STATUS_CANCELLED => throw new DomainException(__('Esta submissão foi cancelada e não se reenvia.')),
            default => null,
        };
    }

    /** Pede `confirmar` quando se mexe no que está a assinar no ambiente activo. */
    private function exigirConfirmacaoNoActivo(string $ambiente, bool $haQueMexer, bool $confirmar, string $mensagem): void
    {
        if ($haQueMexer && !$confirmar && $ambiente === $this->ambienteActivo()) {
            throw new RecusaComDetalhes($mensagem, ['confirmar_necessario' => true]);
        }
    }

    /** SHA-256 da chave pública do ambiente, ou nada. Nunca a chave. */
    private function impressaoDaPublica(string $ambiente): ?string
    {
        $caminho = AGTKeyStore::publicKeyPath($this->tenantId, $ambiente);

        return Storage::disk('local')->exists($caminho)
            ? hash('sha256', (string) Storage::disk('local')->get($caminho))
            : null;
    }

    /**
     * SHA-256 da chave pública DERIVADA de uma privada. É a mesma impressão
     * que a das chaves por ambiente — e a privada não sai do disco.
     */
    private function impressaoDaChavePrivada(string $caminho): ?string
    {
        if (!Storage::disk('local')->exists($caminho)) {
            return null;
        }

        $recurso = @openssl_pkey_get_private((string) Storage::disk('local')->get($caminho));
        $publica = $recurso ? (openssl_pkey_get_details($recurso)['key'] ?? null) : null;

        return $publica ? hash('sha256', $publica) : null;
    }

    /** Um acto na trilha desta empresa. A trilha nunca rebenta com a acção. */
    private function auditar(string $evento, array $metadata, ?\Illuminate\Database\Eloquent\Model $alvo = null): void
    {
        app(AuditRecorder::class)->acto($evento, $this->tenantId, $metadata, $alvo);
    }

    private function exigirPreRequisitos(string $ambiente): void
    {
        if ($falta = $this->emFalta($ambiente)) {
            throw new DomainException('Não foi enviado nenhum pedido à AGT. Falta configurar: ' . implode(', ', $falta) . '.');
        }
    }

    /**
     * A chave do modo antigo mora onde o resto do sistema arruma as chaves: na
     * pasta legada do `AGTKeyStore`, que é a mesma que o assinador lê quando
     * não encontra o par do ambiente. Escrito à mão, o caminho seguia o seu
     * caminho no dia em que aquele mudasse.
     */
    private function caminhoDaChaveLegado(): string
    {
        return AGTKeyStore::legacyDirectory($this->tenantId) . '/private_key.pem';
    }
}
