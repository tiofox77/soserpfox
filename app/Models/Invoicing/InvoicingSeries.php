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

    // Get default series for document type
    public static function getDefaultSeries($tenantId, $documentType)
    {
        $series = static::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        
        // Se não existe, criar série padrão AGT
        if (!$series) {
            $series = static::createDefaultSeries($tenantId, $documentType);
        }
        
        return $series;
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

        return 'SOS' . (self::AGT_PREFIXES[$documentType] ?? 'DOC');
    }

    // Criar série padrão AGT Angola
    public static function createDefaultSeries($tenantId, $documentType)
    {
        // Mapeamento de tipos para prefixos AGT
        $agtPrefixes = [
            'invoice' => 'FT',      // Fatura
            'proforma' => 'PR',     // Proforma
            'receipt' => 'RC',      // Recibo
            'pos' => 'FR',          // Fatura-Recibo
            'credit_note' => 'NC',  // Nota de Crédito
            'debit_note' => 'ND',   // Nota de Débito
            'advance' => 'AD',      // Adiantamento
            'purchase' => 'FC',     // Fatura de Compra
        ];
        
        $prefix = $agtPrefixes[$documentType] ?? 'DOC';
        $seriesCode = static::defaultSeriesCode($documentType);

        // Buscar configurações para série inicial
        $settings = InvoicingSettings::forTenant($tenantId);

        return static::create([
            'tenant_id' => $tenantId,
            'document_type' => $documentType,
            'series_code' => $seriesCode,   // Padrão da casa: SOSFT, SOSRC, ...
            'name' => "Série {$seriesCode}",
            'prefix' => $prefix,   // FT, FR, NC, etc. (fixo AGT)
            'include_year' => true,
            'next_number' => 1,
            'number_padding' => 6,
            'is_default' => true,
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
