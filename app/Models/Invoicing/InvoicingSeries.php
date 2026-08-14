<?php

namespace App\Models\Invoicing;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoicingSeries extends Model
{
    use HasFactory;

    public const AGT_DOCUMENT_TYPES = [
        'FA', 'FT', 'FR', 'FG', 'GF', 'AC', 'AR', 'TV',
        'RC', 'RG', 'RE', 'ND', 'NC', 'AF', 'RP', 'RA', 'CS', 'LD',
    ];

    protected $table = 'invoicing_series';

    protected $fillable = [
        'tenant_id',
        'document_type',
        'series_code',
        'name',
        'prefix',
        'include_year',
        'next_number',
        'number_padding',
        'is_default',
        'is_active',
        'current_year',
        'reset_yearly',
        'description',
        'agt_series_id',
        'atcud_validation_code',
        'agt_status',
        'agt_series_status',
        'series_year',
        'establishment_number',
        'invoicing_method',
        'agt_series_start_ts',
        'agt_series_end_ts',
        'agt_environment',
        'agt_registered_at',
        'agt_response',
        'series_contingency_indicator',
        'authorized_quantity',
        'first_document_no',
        'last_document_no',
        'submission_uuid',
    ];

    protected $casts = [
        'include_year' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'reset_yearly' => 'boolean',
        'next_number' => 'integer',
        'number_padding' => 'integer',
        'current_year' => 'integer',
        'agt_registered_at' => 'datetime',
        'agt_series_start_ts' => 'datetime',
        'agt_series_end_ts' => 'datetime',
        'agt_response' => 'array',
    ];

    // DS.120 §4.6 — Estados oficiais AGT
    public const AGT_STATUS_OPEN  = 'A'; // Aberta
    public const AGT_STATUS_USING = 'U'; // Em utilização
    public const AGT_STATUS_CLOSED = 'F'; // Fechada

    // Métodos de facturação (DS.120 §4.6)
    public const INVOICING_FEPC = 'FEPC'; // Facturação Electrónica Pré-Comunicada
    public const INVOICING_FESF = 'FESF'; // Facturação Electrónica Sem Facturação
    public const INVOICING_SF   = 'SF';   // Sem Facturação

    /** Mapeia o estado interno (`active`/`pending`/etc) para o estado AGT (A/U/F). */
    public function syncAgtSeriesStatus(): void
    {
        $next = match (true) {
            !empty($this->agt_series_end_ts)         => self::AGT_STATUS_CLOSED,
            ($this->next_number ?? 1) > 1            => self::AGT_STATUS_USING,
            $this->agt_status === 'active'           => self::AGT_STATUS_OPEN,
            default                                  => null,
        };

        if ($next && $this->agt_series_status !== $next) {
            $this->agt_series_status = $next;
            $this->save();
        }
    }

    /** Descrição humana do estado AGT. */
    public function agtStatusLabel(): string
    {
        return match ($this->agt_series_status) {
            self::AGT_STATUS_OPEN   => 'Aberta',
            self::AGT_STATUS_USING  => 'Em utilização',
            self::AGT_STATUS_CLOSED => 'Fechada',
            default                 => '—',
        };
    }

    // Verifica se a série está registada na AGT
    public function isAGTRegistered(): bool
    {
        return !empty($this->agt_series_id);
    }

    public function isAGTEligible(): bool
    {
        return in_array(strtoupper((string) $this->prefix), self::AGT_DOCUMENT_TYPES, true);
    }

    // Gera ATCUD para documento
    public function generateATCUD(int $sequentialNumber): string
    {
        // O ATCUD é <código de validação da AGT>-<sequencial>. Sem código de
        // validação NÃO há ATCUD: devolver '0' como se fosse um fazia os
        // documentos saírem impressos com "ATCUD: 0-45", um identificador falso
        // num documento fiscal entregue ao cliente. Uma série por registar tem
        // de ficar visivelmente sem ATCUD, para o problema se notar.
        if (blank($this->atcud_validation_code)) {
            return '';
        }

        return $this->atcud_validation_code . '-' . $sequentialNumber;
    }

    /**
     * A série pode produzir ATCUD? Só depois de a AGT devolver o código de
     * validação. O isAGTRegistered() olha para o agt_series_id, que pode existir
     * sem o código.
     */
    public function podeGerarAtcud(): bool
    {
        return filled($this->atcud_validation_code);
    }

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Get next document number
    /**
     * O próximo número da série, reservado de forma atómica.
     *
     * Isto lia o next_number do objecto que já tinha em mãos, procurava na
     * base de dados se aquele número estava usado e só depois incrementava.
     * Sem bloqueio, dois pedidos em voo lêem ambos 100, nenhum vê a factura do
     * outro (ainda não foi gravada) e ambos devolvem 100. O índice único
     * apanhava o segundo — mas com um erro 500, e no PWA isso gasta uma das
     * cinco tentativas antes de a venda ficar marcada como falhada.
     *
     * É o cenário de uma loja a fechar: várias caixas sem rede, cada uma com
     * dezenas de vendas na fila, todas a despejar assim que a ligação volta.
     *
     * O lockForUpdate serializa a atribuição. Quando isto corre dentro de uma
     * transacção maior — como na sincronização de uma venda POS — o bloqueio
     * só larga no commit dessa transacção, e é isso que se quer: a numeração
     * fiscal não pode ter dois documentos com o mesmo número, e prefere-se
     * esperar a arriscar.
     *
     * A alternativa — reservar o número numa transacção própria, já commitada —
     * era mais rápida mas deixava buracos na numeração sempre que a venda
     * falhasse a seguir, e buracos numa série fiscal são pior do que lentidão.
     */
    public function getNextNumber()
    {
        return DB::transaction(function () {
            $numero = $this->reservarNumero();

            // Manter este objecto coerente com o que ficou na base de dados:
            // quem o tiver em mãos não pode continuar a ver o valor antigo.
            $this->refresh();

            return $numero;
        });
    }

    private function reservarNumero()
    {
        // Reler a linha SOB BLOQUEIO. O que este objecto tem em memória pode
        // já estar velho — foi lido antes de o outro pedido incrementar.
        $serie = static::whereKey($this->getKey())->lockForUpdate()->first() ?? $this;

        $currentYear = now()->year;

        // Verificar se precisa resetar numeração
        if ($serie->reset_yearly && $serie->current_year != $currentYear) {
            $serie->update([
                'next_number' => 1,
                'current_year' => $currentYear,
            ]);
            $serie->refresh();
        }

        $number = $serie->next_number;
        $formatted = $serie->formatNumber($number);

        // Verificar se o número já existe no BD para ESTE tenant (proteção contra dessync).
        // A unicidade é por tenant (cada empresa tem numeração independente conforme SAFT-AO).
        $table = $serie->getDocumentTable();
        $column = $serie->getDocumentColumn();
        // Validar o schema: um mapeamento errado NUNCA pode impedir a emissão de
        // documentos (um nome de tabela inválido rebentava a criação inteira).
        if ($table && $column
            && \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
            $maxAttempts = 5000;
            while (
                \DB::table($table)
                    ->where('tenant_id', $serie->tenant_id)
                    ->where($column, $formatted)
                    ->exists()
                && $maxAttempts-- > 0
            ) {
                $number++;
                $formatted = $serie->formatNumber($number);
            }
        }

        // Atualizar next_number para o próximo livre
        $serie->update(['next_number' => $number + 1]);

        return $formatted;
    }

    protected function getDocumentTable(): ?string
    {
        return match($this->document_type) {
            'invoice', 'pos' => 'invoicing_sales_invoices',
            'proforma' => 'invoicing_sales_proformas',
            'receipt' => 'invoicing_receipts',
            'credit_note' => 'invoicing_credit_notes',
            'debit_note' => 'invoicing_debit_notes',
            'advance' => 'invoicing_advances',
            'purchase' => 'invoicing_purchase_invoices',
            'transport' => 'invoicing_transport_guides',
            default => null,
        };
    }

    protected function getDocumentColumn(): ?string
    {
        return match($this->document_type) {
            'invoice', 'pos' => 'invoice_number',
            'proforma' => 'proforma_number',
            'receipt' => 'receipt_number',
            'credit_note' => 'credit_note_number',
            'debit_note' => 'debit_note_number',
            'advance' => 'advance_number',
            'purchase' => 'invoice_number',
            'transport' => 'guide_number',
            default => null,
        };
    }

    // Format document number (Padrão AGT Angola)
    public function formatNumber($number)
    {
        // Formatar número com padding
        $formattedNumber = str_pad($number, $this->number_padding, '0', STR_PAD_LEFT);
        
        // Formato AGT Angola: [TIPO] [SÉRIE] [ANO]/[NÚMERO]
        // Exemplo: FT A 2025/000001
        
        $parts = [];

        // ── Primeiro token: o TIPO do documento ───────────────────────────
        //
        // Foi "SOS" durante algum tempo, por decisão de marca. A AGT recusa:
        //
        //   E32 — Código de série mal construído (SOS FR7626S6286N/000045).
        //
        // Medido contra a AGT de homologação, consultando o desfecho real de
        // submissões já entregues:
        //
        //   25 documentos começados por "SOS"    → 25 recusados, todos com E32
        //    5 documentos começados pelo tipo    →  5 validados
        //
        // Sem excepções de nenhum dos lados, e com a MESMA série nos dois
        // grupos (FR FR7626S6286N/000001 passou, SOS FR7626S6286N/000045 não),
        // o que isola o primeiro token como a única diferença.
        //
        // A convenção SAFT-AO é "{tipo} {série}/{número}": a AGT lê o primeiro
        // token como o tipo do documento. Com "SOS" lá, não o reconhece — e os
        // erros que vêm a seguir (E27 nas facturas, E14 nas notas de crédito)
        // são consequência de ela não conseguir sequer classificar o documento.
        //
        // A marca continua visível no apelido interno mostrado nos ecrãs
        // (SOS-FR-000045), que é onde não tem custo fiscal nenhum.
        $parts[] = $this->prefix ?: self::PREFIXO_NUMERO;

        // ── Segundo token: a série ────────────────────────────────────────
        // Código atribuído pela AGT quando registada (obrigatório, é o elo com
        // o portal); senão o nosso sem o "SOS" inicial, que já vai no primeiro
        // token: SOSFT → FT, SOSFR → FR, SOSPR02 → PR02.
        $serie = $this->agt_series_id
            ?: preg_replace('/^SOS/', '', (string) $this->series_code);

        if ($serie) {
            $parts[] = $serie;
        }
        
        // Ano e número
        return implode(' ', $parts) . '/' . $formattedNumber;
    }

    // Get preview of next number
    public function previewNextNumber()
    {
        return $this->formatNumber($this->next_number);
    }

    /**
     * Passa ESTA série a padrão do seu tipo, tirando o estatuto às outras.
     *
     * Ponto ÚNICO de escrita de is_default=true. Sete sítios gravavam o literal
     * `true` sem olhar para o que já lá estava, e o resultado medido em produção
     * foram 8 casos com mais do que uma série marcada como padrão para o mesmo
     * tipo de documento na mesma empresa. Qual delas a emissão apanha depende da
     * ordem que a base de dados devolver — e é ela que decide o número do próximo
     * documento fiscal.
     *
     * Dentro de uma transacção porque desmarcar as outras e marcar esta são um só
     * facto: entre os dois UPDATEs há um instante em que aquele tipo não tem
     * padrão nenhuma, e quem emitisse um documento nesse instante não a
     * encontrava.
     *
     * A ORDEM também importa, e não é arbitrária: desmarcar primeiro, marcar
     * depois. Ao contrário, as duas coexistiriam durante um statement e o índice
     * único de `padrao_unico` recusava a operação.
     */
    public function tornarPadrao(): void
    {
        DB::transaction(function () {
            static::where('tenant_id', $this->tenant_id)
                ->where('document_type', $this->document_type)
                ->whereKeyNot($this->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }

    /**
     * Uma série NOVA deste tipo deve nascer como padrão?
     *
     * Só quando aquela empresa ainda não tem nenhuma padrão para o tipo. É o que
     * os sítios de CRIAÇÃO passam a perguntar em vez de escreverem `true`: criar
     * uma série nunca é escolher a padrão do negócio — isso é um acto deliberado
     * do utilizador no ecrã de definições, e esse usa tornarPadrao().
     */
    public static function deveNascerPadrao(int $tenantId, string $documentType): bool
    {
        return !static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->exists();
    }

    // Get default series for document type
    public static function getDefaultSeries($tenantId, $documentType)
    {
        $series = static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($series) {
            return $series;
        }

        // Sem padrão ACTIVA — o que não quer dizer que não haja série nenhuma.
        // A padrão pode estar desactivada, e o estatuto continua a ser dela:
        // quem a desactivou tomou uma decisão e não é aqui que se lhe mexe. O
        // que falta é uma série ACTIVA por onde numerar, e havendo uma
        // reaproveita-se em vez de criar outra.
        //
        // Sem este passo o método deixava de convergir: a série que o
        // createDefaultSeries cria já não nasce padrão (deveNascerPadrao), pelo
        // que a chamada SEGUINTE não a encontrava e voltava a tentar criá-la —
        // com o mesmo código, contra o unique (tenant_id, document_type,
        // series_code). A primeira venda passava e todas as outras estoiravam
        // com 1062, no POS, que chama isto no próprio render.
        $activa = static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($activa) {
            return $activa;
        }

        // Nem uma activa. Ainda assim pode haver série — todas desactivadas — e
        // então criar outra é a pior das saídas: abre uma segunda numeração do
        // mesmo tipo no mesmo exercício, que é o que reprova num SAFT, e ainda
        // por cima nem chega a criar-se, porque o código é o mesmo e bate no
        // unique (tenant_id, document_type, series_code). Reaproveita-se a que
        // existe, começando pela padrão. Emitir com ela é outra conversa e tem
        // porteiro próprio: o getIssuanceSeries exige is_active e recusa.
        $qualquer = static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        // Se não existe nenhuma, criar série padrão AGT
        return $qualquer ?? static::createDefaultSeries($tenantId, $documentType);
    }

    /**
     * Serie autorizada para emissao fiscal.
     *
     * Assim que o tenant configura as chaves FE, documentos fiscais deixam de
     * poder usar series apenas locais. Cada empresa usa exclusivamente uma
     * serie activa e registada na sua propria conta AGT.
     */
    public static function getIssuanceSeries(int $tenantId, string $documentType, ?int $seriesId = null): self
    {
        $query = static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_active', true);

        // Par de chaves DO AMBIENTE activo
        $agtEnabled = \App\Services\AGT\AGTKeyStore::hasKeyPair($tenantId);
        $ambiente = \App\Services\AGT\AGTKeyStore::ambiente($tenantId);

        // SEMPRE, haja ou não chaves: nunca emitir contra uma série registada
        // NOUTRO ambiente. Sem isto, uma empresa em produção sem chaves emitia
        // com o código de série da homologação (SOS FT7626S9153N/…), que a AGT
        // de produção desconhece.
        $query->where(function ($q) use ($ambiente) {
            $q->whereNull('agt_series_id')
              ->orWhere('agt_environment', $ambiente);
        });

        if ($agtEnabled) {
            // Com chaves configuradas a série tem mesmo de estar registada
            // neste ambiente — não basta não pertencer a outro.
            $query->whereNotNull('agt_series_id');
        }

        $series = $seriesId
            ? (clone $query)->whereKey($seriesId)->first()
            : (clone $query)->orderByDesc('is_default')->orderBy('id')->first();

        if (!$series) {
            $rotulo = $ambiente === 'production' ? 'produção' : 'homologação';
            $message = $agtEnabled
                ? "Nenhuma serie fiscal AGT registada e activa em {$rotulo} para este tipo de documento."
                : "Nenhuma serie activa de {$rotulo} para este tipo de documento. "
                  . 'As series existentes pertencem ao outro ambiente e nao podem ser usadas aqui.';
            throw new \DomainException($message);
        }

        return $series;
    }

    public function nextSequentialFromDocumentNumber(string $number): int
    {
        if (preg_match('/\/(\d+)$/', $number, $matches)) {
            return (int) $matches[1];
        }
        return max(1, (int) $this->next_number - 1);
    }
    
    /**
     * Primeiro token do número de documento (SOS FT7626S9153N/000001).
     *
     * Ponto ÚNICO de reversão: pôr $this->prefix de volta em formatNumber()
     * repõe a convenção SAFT-AO "{tipo} {série}/{número}".
     */
    public const PREFIXO_NUMERO = 'SOS';

    /**
     * Prefixo AGT obrigatório por tipo de documento. Fixo pela AGT — não é
     * escolha nossa (FT para factura, NC para nota de crédito, etc.).
     *
     * FONTE ÚNICA do prefixo. O mesmo mapa estava copiado noutros sítios — no
     * createDefaultSeries aqui em baixo, no comando series:create-defaults e no
     * ecrã de definições — e as cópias divergiam sem ninguém dar por isso: o
     * ecrã mostrava o prefixo de uma cópia, o número do documento saía com o da
     * coluna `prefix`, e quem olhasse para o ecrã via tudo certo.
     *
     * Não é cosmético. A AGT lê o PRIMEIRO TOKEN do número para classificar o
     * documento (ver formatNumber): dos que foram entregues com o token errado,
     * 25 em 25 foram recusados com E32; os 5 com o token certo passaram todos.
     *
     * Só tem tipos FISCAIS. Um tipo que não esteja aqui é uma de duas coisas
     * muito diferentes, e quem lê o mapa tem de as separar:
     *
     *   · está em TIPOS_INTERNOS → documento interno, legítimo, que nunca é
     *     comunicado à AGT e por isso não tem — nem pode ter — prefixo do
     *     catálogo fiscal;
     *   · não está em lado nenhum → tipo desconhecido, quase de certeza um erro.
     *
     * Usar prefixoDe() / tipoInterno() / tipoDesconhecido() em vez de ler o
     * array à mão, que é como as cópias começam.
     */
    public const AGT_PREFIXES = [
        'invoice'     => 'FT',
        'proforma'    => 'PR',
        'receipt'     => 'RC',
        'pos'         => 'FR',
        'credit_note' => 'NC',
        'debit_note'  => 'ND',
        'advance'     => 'AD',
        'purchase'    => 'FC',
        'transport'   => 'GT',
    ];

    /**
     * Tipos que existem no produto mas não são documentos fiscais: a proforma de
     * compra é interna, nunca vai à AGT e portanto não tem prefixo do catálogo.
     * Sem esta lista era indistinguível de um document_type escrito com erro, e
     * qualquer validação contra o catálogo recusava-a.
     *
     * Ficam numa constante à parte, e não dentro de AGT_PREFIXES com valor null,
     * porque há código que percorre os VALORES daquele mapa (o agt:normalize
     * passa cada um a preg_quote); um null no meio dos valores rebentava lá, sem
     * relação visível com o tipo que se acrescentou aqui.
     */
    public const TIPOS_INTERNOS = [
        'purchase_proforma',
    ];

    /** O prefixo AGT deste tipo, ou null se não for documento fiscal. */
    public static function prefixoDe(string $documentType): ?string
    {
        return self::AGT_PREFIXES[$documentType] ?? null;
    }

    /** Documento interno: existe, é legítimo, e não se comunica à AGT. */
    public static function tipoInterno(string $documentType): bool
    {
        return in_array($documentType, self::TIPOS_INTERNOS, true);
    }

    /** Tipo que não conhecemos de todo — nem fiscal, nem interno. */
    public static function tipoDesconhecido(string $documentType): bool
    {
        return !isset(self::AGT_PREFIXES[$documentType])
            && !self::tipoInterno($documentType);
    }

    /**
     * Código de série padrão da casa: SOS + prefixo AGT (SOSFT, SOSRC, SOSNC…).
     *
     * Antes era sempre 'A', igual para todos os tipos, o que tornava impossível
     * reconhecer a série pelo código. Aparece no número enquanto a série não
     * está registada na AGT; depois disso o código atribuído pela AGT toma o
     * lugar (ver formatNumber).
     */
    public static function defaultSeriesCode(string $documentType): string
    {
        // O catálogo canónico manda. Compor a partir de AGT_PREFIXES dava
        // códigos diferentes dos acordados — 'proforma' saía SOSPR em vez de
        // SOSPROV, e 'purchase_proforma', por não estar no mapa, caía no
        // fallback e saía SOSDOC. O resultado eram DUAS normalizações a
        // disputar o mesmo campo: o series:canonical punha SOSPROC e o
        // agt:normalize renomeava-o logo a seguir.
        $canonica = \App\Services\Invoicing\SeriesCatalog::paraTipo($documentType);

        if ($canonica) {
            return $canonica['code'];
        }

        return 'SOS' . (self::prefixoDe($documentType) ?? 'DOC');
    }

    // Criar série padrão AGT Angola
    public static function createDefaultSeries($tenantId, $documentType)
    {
        // Vinha daqui uma segunda cópia do mapa de prefixos — e desactualizada:
        // não tinha 'transport', pelo que uma guia criada por esta via saía com
        // 'DOC' no primeiro token em vez de 'GT'.
        $prefix = static::prefixoDe($documentType);

        if ($prefix === null) {
            // Um tipo interno não tem prefixo AGT: o dele é escolha da casa e
            // vem do catálogo canónico, o mesmo sítio de onde já vem o código.
            // Para um tipo desconhecido fica 'DOC', para não inventar um código
            // fiscal a partir de um nome que ninguém reconhece.
            $prefix = static::tipoInterno($documentType)
                ? (\App\Services\Invoicing\SeriesCatalog::paraTipo($documentType)['prefix'] ?? 'DOC')
                : 'DOC';
        }

        $seriesCode = static::defaultSeriesCode($documentType);

        // Buscar configurações para série inicial
        $settings = InvoicingSettings::forTenant($tenantId);

        // firstOrCreate e não create: o código é sempre o mesmo para o par
        // (tenant, tipo), pelo que duas chamadas ao mesmo tempo — dois postos a
        // abrir o POS, um pedido e o job que o segue — batiam no unique e a
        // segunda estoirava com 1062. Existindo já a linha, é essa que serve;
        // criar uma variante do código só abria uma numeração paralela.
        return static::firstOrCreate([
            'tenant_id' => $tenantId,
            'document_type' => $documentType,
            'series_code' => $seriesCode,   // Padrão da casa: SOSFT, SOSRC, ...
        ], [
            'name' => "Série {$seriesCode}",
            'prefix' => $prefix,   // FT, FR, NC, etc. (fixo AGT)
            'include_year' => true,
            'next_number' => 1,
            'number_padding' => 6,
            // Só assume o estatuto se aquele tipo ainda não tiver padrão. Este
            // método é chamado pelo getDefaultSeries() quando não encontra série
            // nenhuma ACTIVA — e uma padrão desactivada continua a ser a padrão,
            // pelo que gravar `true` aqui criava a segunda.
            'is_default' => static::deveNascerPadrao((int) $tenantId, $documentType),
            'is_active' => true,
            'current_year' => now()->year,
            'reset_yearly' => true,
            'description' => "Série padrão AGT para {$prefix}",
        ]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
