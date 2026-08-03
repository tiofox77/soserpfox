<?php

namespace App\Models\Invoicing;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    public function getNextNumber()
    {
        $currentYear = now()->year;
        
        // Verificar se precisa resetar numeração
        if ($this->reset_yearly && $this->current_year != $currentYear) {
            $this->update([
                'next_number' => 1,
                'current_year' => $currentYear,
            ]);
            $this->refresh();
        }
        
        $number = $this->next_number;
        $formatted = $this->formatNumber($number);

        // Verificar se o número já existe no BD para ESTE tenant (proteção contra dessync).
        // A unicidade é por tenant (cada empresa tem numeração independente conforme SAFT-AO).
        $table = $this->getDocumentTable();
        $column = $this->getDocumentColumn();
        // Validar o schema: um mapeamento errado NUNCA pode impedir a emissão de
        // documentos (um nome de tabela inválido rebentava a criação inteira).
        if ($table && $column
            && \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
            $maxAttempts = 5000;
            while (
                \DB::table($table)
                    ->where('tenant_id', $this->tenant_id)
                    ->where($column, $formatted)
                    ->exists()
                && $maxAttempts-- > 0
            ) {
                $number++;
                $formatted = $this->formatNumber($number);
            }
        }

        // Atualizar next_number para o próximo livre
        $this->update(['next_number' => $number + 1]);
        
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

        // ── Primeiro token: marca da casa ─────────────────────────────────
        // Decisão do cliente: o número começa por SOS (SOS FT…, SOS FR…).
        //
        // Nota fiscal: a convenção SAFT-AO é "{tipo} {série}/{número}", em que o
        // primeiro token é o tipo de documento (FT, FR, NC…) — é assim que o
        // portal da AGT os apresenta. O tipo continua a ser enviado no campo
        // 'documentType' do payload, mas deixa de constar do número.
        // Reversão: trocar self::PREFIXO_NUMERO por $this->prefix aqui.
        $parts[] = self::PREFIXO_NUMERO;

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
