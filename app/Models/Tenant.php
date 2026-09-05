<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Regimes de IVA (AGT — Código do IVA angolano)
    |--------------------------------------------------------------------------
    | A AGT prevê TRÊS regimes. Cada empresa escolhe um e ele determina como os
    | produtos e os documentos são tributados:
    |
    |  • GERAL       — liquida IVA (14% normal; 7%/5% reduzidas; 0% exportação)
    |  • SIMPLIFICADO— liquida 7% sobre as transmissões (dedução limitada)
    |  • NÃO SUJEIÇÃO/EXCLUSÃO — não liquida IVA; documentos saem isentos e
    |                  OBRIGAM a motivo de isenção com código AGT (M04)
    |
    | Valores legados na BD (`regime_isencao`, `regime_misto`) são mapeados para
    | o regime canónico equivalente por canonicalRegime() — nunca se perde dados.
    */
    public const REGIME_GERAL         = 'regime_geral';
    public const REGIME_SIMPLIFICADO  = 'regime_simplificado';
    public const REGIME_NAO_SUJEICAO  = 'regime_nao_sujeicao';

    /** Metadados dos 3 regimes AGT (fonte única de verdade para UI e sincronização). */
    public const REGIMES = [
        self::REGIME_GERAL => [
            'label'          => 'Regime Geral',
            'short'          => 'Geral',
            'description'    => 'Liquida IVA nas vendas (14% taxa normal; 7% e 5% reduzidas; 0% exportação).',
            'turnover'       => 'Volume de negócios superior a 350.000.000 Kz (ou por opção).',
            'default_rate'   => 14.00,
            'tax_code'       => 'IVA14',
            'saft_type'      => 'NOR',
            'exempt'         => false,
            'exemption_code' => null,
        ],
        self::REGIME_SIMPLIFICADO => [
            'label'          => 'Regime Simplificado',
            'short'          => 'Simplificado',
            'description'    => 'Liquida 7% sobre as transmissões de bens e serviços, com dedução limitada.',
            'turnover'       => 'Volume de negócios entre 10.000.000 Kz e 350.000.000 Kz.',
            'default_rate'   => 7.00,
            'tax_code'       => 'IVA7',
            'saft_type'      => 'RED',
            'exempt'         => false,
            'exemption_code' => null,
        ],
        self::REGIME_NAO_SUJEICAO => [
            'label'          => 'Regime de Não Sujeição (Exclusão)',
            'short'          => 'Não Sujeição',
            'description'    => 'Não liquida IVA. Os documentos saem isentos com o motivo de isenção obrigatório (M04 — Regime Especial de Isenção, Artigo 53.º).',
            'turnover'       => 'Volume de negócios até 10.000.000 Kz.',
            'default_rate'   => 0.00,
            'tax_code'       => 'ISENTO-EXCL',
            'saft_type'      => 'ISE',
            'exempt'         => true,
            'exemption_code' => 'M04',
        ],
    ];

    /** Valores legados → regime canónico. */
    public const REGIME_ALIASES = [
        'regime_isencao' => self::REGIME_NAO_SUJEICAO,  // isenção = exclusão de IVA
        'regime_misto'   => self::REGIME_GERAL,         // misto liquida IVA
    ];

    /** Normaliza qualquer valor histórico para um dos 3 regimes AGT. */
    public static function canonicalRegime(?string $regime): string
    {
        $regime = trim((string) $regime);
        if (isset(self::REGIMES[$regime])) {
            return $regime;
        }
        return self::REGIME_ALIASES[$regime] ?? self::REGIME_GERAL;
    }

    /** Metadados do regime (já canonicalizado). */
    public function regimeMeta(): array
    {
        return self::REGIMES[self::canonicalRegime($this->regime)];
    }

    public function regimeLabel(): string
    {
        return $this->regimeMeta()['label'];
    }

    /** True se o regime não liquida IVA (documentos isentos com motivo obrigatório). */
    public function isExemptRegime(): bool
    {
        return $this->regimeMeta()['exempt'];
    }

    /** Taxa de IVA a aplicar por omissão neste regime. */
    public function regimeDefaultRate(): float
    {
        return (float) $this->regimeMeta()['default_rate'];
    }

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'database',
        'logo',
        'company_name',
        'nif',
        'regime',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'province',
        'municipality',
        'neighbourhood',
        // Código ISO 3166-1 alfa-2 — ver App\Support\Geografia.
        'country',
        'locale',
        'max_users',
        'max_storage_mb',
        'max_documents',
        'restaurant_venue_limit',
        'settings',
        'is_active',
        'accounting_integration_enabled',
        'trial_ends_at',
        'subscription_ends_at',
        'deactivation_reason',
        'deactivated_at',
        'deactivated_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'accounting_integration_enabled' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'restaurant_venue_limit' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $baseSlug = Str::slug($tenant->name);
                $slug = $baseSlug;
                $counter = 1;
                
                // Garantir slug único
                while (self::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                
                $tenant->slug = $slug;
            }
        });

        static::created(function ($tenant) {
            // Popular bancos angolanos automaticamente
            self::populateBanks();
            
            // Popular categorias de equipamentos automaticamente
            self::populateEquipmentCategories($tenant);

            // Catálogo inicial partilhado entre Restaurante, POS e Faturação.
            try {
                if (\Schema::hasTable('invoicing_categories')) {
                    \App\Models\Category::seedRestaurantDefaults($tenant->id);
                }
            } catch (\Throwable $e) {
                \Log::warning('Tenant: falha ao criar categorias iniciais', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            }
            
            // Popular métodos de pagamento padrão
            self::populatePaymentMethods($tenant);

            // Garantir armazém principal/default
            self::populateDefaultWarehouse($tenant);

            // A Fatura-Recibo (FR) é o documento principal do POS. Todo tenant
            // precisa da série local desde a criação; após configurar as chaves,
            // ela será registada/sincronizada com a conta AGT dessa empresa.
            try {
                \App\Models\Invoicing\InvoicingSeries::getDefaultSeries($tenant->id, 'pos');
            } catch (\Throwable $e) {
                \Log::error('Tenant: falha ao criar série FR padrão', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
        
        /**
         * A cascata só corre numa eliminação DEFINITIVA.
         *
         * Estava em `deleting`, que também dispara no soft delete — e o
         * resultado era incoerente ao ponto de ser perigoso: a empresa ficava
         * soft-deleted, portanto recuperável, mas os utilizadores, os papéis e
         * as subscrições dela levavam `forceDelete` e desapareciam para
         * sempre. Quem restaurasse a empresa encontrava-a sem ninguém lá
         * dentro e sem papéis — inutilizável.
         *
         * Com `forceDeleting`, um soft delete apenas esconde a empresa e nada
         * se destrói. A destruição fica reservada a quem a peça de facto, e só
         * passa pela guarda de `canBeDeleted()`.
         */
        static::forceDeleting(function ($tenant) {
            \Log::info("🗑️ INICIANDO EXCLUSÃO EM CASCATA DO TENANT", [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'deleted_by' => auth()->id() ?? 'System',
            ]);
            
            try {
                \DB::beginTransaction();
                
                // 1. DELETAR USUÁRIOS DO TENANT
                $users = $tenant->users()->get();
                foreach ($users as $user) {
                    // Remover roles específicas do tenant
                    setPermissionsTeamId($tenant->id);
                    $user->roles()->detach();
                    
                    // Remover da pivot table tenant_user
                    $user->tenants()->detach($tenant->id);
                    
                    // Se o usuário não pertence a nenhum outro tenant, deletar completamente
                    if ($user->tenants()->count() == 0) {
                        $user->forceDelete();
                        \Log::info("   👤 Usuário deletado: {$user->email}");
                    }
                }
                
                // 2. DELETAR ROLES DO TENANT
                $roles = $tenant->roles()->get();
                foreach ($roles as $role) {
                    $role->permissions()->detach();
                    $role->forceDelete();
                }
                \Log::info("   🔐 {$roles->count()} roles deletadas");
                
                // 3. DELETAR SUBSCRIPTIONS
                $subscriptions = $tenant->subscriptions()->get();
                foreach ($subscriptions as $subscription) {
                    $subscription->forceDelete();
                }
                \Log::info("   📋 {$subscriptions->count()} subscriptions deletadas");
                
                // 4. DELETAR ORDERS
                $orders = \App\Models\Order::where('tenant_id', $tenant->id)->get();
                foreach ($orders as $order) {
                    $order->forceDelete();
                }
                \Log::info("   📦 {$orders->count()} orders deletadas");
                
                // 5. DELETAR INVOICES
                $invoices = $tenant->invoices()->get();
                foreach ($invoices as $invoice) {
                    $invoice->forceDelete();
                }
                \Log::info("   🧾 {$invoices->count()} invoices deletadas");
                
                // 6. REMOVER MÓDULOS
                $tenant->modules()->detach();
                \Log::info("   🧩 Módulos desvinculados");
                
                // 7. DELETAR CATEGORIAS DE EQUIPAMENTOS
                if (class_exists('\App\Models\EquipmentCategory')) {
                    $categories = \App\Models\EquipmentCategory::where('tenant_id', $tenant->id)->get();
                    foreach ($categories as $category) {
                        $category->forceDelete();
                    }
                    \Log::info("   📁 {$categories->count()} categorias de equipamentos deletadas");
                }
                
                // 8. DELETAR MÉTODOS DE PAGAMENTO
                if (class_exists('\App\Models\Treasury\PaymentMethod')) {
                    $methods = \App\Models\Treasury\PaymentMethod::where('tenant_id', $tenant->id)->get();
                    foreach ($methods as $method) {
                        $method->forceDelete();
                    }
                    \Log::info("   💳 {$methods->count()} métodos de pagamento deletados");
                }
                
                // 9. DELETAR EVENTOS (se existir)
                if (class_exists('\App\Models\Event')) {
                    $events = \App\Models\Event::where('tenant_id', $tenant->id)->get();
                    foreach ($events as $event) {
                        $event->forceDelete();
                    }
                    \Log::info("   📅 {$events->count()} eventos deletados");
                }
                
                // 10. DELETAR EQUIPAMENTOS (se existir)
                if (class_exists('\App\Models\Equipment')) {
                    $equipments = \App\Models\Equipment::where('tenant_id', $tenant->id)->get();
                    foreach ($equipments as $equipment) {
                        $equipment->forceDelete();
                    }
                    \Log::info("   📦 {$equipments->count()} equipamentos deletados");
                }
                
                // 11. DELETAR CONVITES PENDENTES
                if (class_exists('\App\Models\UserInvitation')) {
                    $invitations = \App\Models\UserInvitation::where('tenant_id', $tenant->id)->get();
                    foreach ($invitations as $invitation) {
                        $invitation->forceDelete();
                    }
                    \Log::info("   📨 {$invitations->count()} convites deletados");
                }
                
                // 12. O RESTO — todas as outras tabelas com `tenant_id`.
                //
                // Os onze passos acima tratavam OITO tabelas. Medido nesta
                // base, 142 têm `tenant_id`: as outras 134 ficavam com linhas a
                // apontar para uma empresa que já não existe, invisíveis
                // porque os filtros por empresa nunca mais as devolvem.
                //
                // A lista é lida do esquema e não escrita à mão, senão ficava
                // desactualizada na primeira migração que acrescentasse uma
                // tabela — e o sintoma disso é silencioso.
                $limpeza = \App\Services\Tenants\EliminacaoDeEmpresa::limpar($tenant->id);

                \Log::info("   🧹 {$limpeza['passagens']} passagem(ns), "
                    . count($limpeza['apagadas']) . ' tabela(s) limpa(s), '
                    . array_sum($limpeza['apagadas']) . ' linha(s)');

                \DB::commit();

                \Log::info("✅ EXCLUSÃO EM CASCATA CONCLUÍDA COM SUCESSO", [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tabelas_limpas' => count($limpeza['apagadas']),
                    'por_limpar' => array_keys($limpeza['por_apagar']),
                ]);
                
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error("❌ ERRO NA EXCLUSÃO EM CASCATA DO TENANT", [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Popular bancos angolanos na base de dados
     */
    protected static function populateBanks()
    {
        $banks = [
            [
                'name' => 'Banco de Fomento Angola',
                'code' => 'BFA',
                'swift_code' => 'BFAOAOAO',
                'country' => 'AO',
                'website' => 'https://www.bfa.ao',
                'phone' => '+244 222 638 900',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Angolano de Investimentos',
                'code' => 'BAI',
                'swift_code' => 'BAAOAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancobai.ao',
                'phone' => '+244 222 691 919',
                'is_active' => true,
            ],
            [
                'name' => 'Banco BIC',
                'code' => 'BIC',
                'swift_code' => 'BICAAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancobic.ao',
                'phone' => '+244 222 638 900',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Económico',
                'code' => 'BE',
                'swift_code' => 'BECOAOAO',
                'country' => 'AO',
                'website' => 'https://www.be.co.ao',
                'phone' => '+244 222 445 000',
                'is_active' => true,
            ],
            [
                'name' => 'Banco de Poupança e Crédito',
                'code' => 'BPC',
                'swift_code' => 'BPCOAOAO',
                'country' => 'AO',
                'website' => 'https://www.bpc.ao',
                'phone' => '+244 222 693 939',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Millennium Atlântico',
                'code' => 'BMA',
                'swift_code' => 'BMATAOAO',
                'country' => 'AO',
                'website' => 'https://www.millenniumbcp.co.ao',
                'phone' => '+244 222 693 000',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Sol',
                'code' => 'SOL',
                'swift_code' => 'BSOLAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancosol.ao',
                'phone' => '+244 222 638 400',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Keve',
                'code' => 'KEVE',
                'swift_code' => 'KEVDAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancokeve.ao',
                'phone' => '+244 222 010 300',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Caixa Geral Angola',
                'code' => 'BCGA',
                'swift_code' => 'CGDLAOAO',
                'country' => 'AO',
                'website' => 'https://www.cgd.ao',
                'phone' => '+244 222 638 100',
                'is_active' => true,
            ],
            [
                'name' => 'Banco BAI Micro Finanças',
                'code' => 'BMF',
                'swift_code' => 'BMFAAOAO',
                'country' => 'AO',
                'website' => 'https://www.baimicro.ao',
                'phone' => '+244 222 010 400',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Comercial Angolano',
                'code' => 'BCA',
                'swift_code' => 'BCAMAOAO',
                'country' => 'AO',
                'website' => 'https://www.bca.ao',
                'phone' => '+244 222 638 700',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Standard Bank Angola',
                'code' => 'SBA',
                'swift_code' => 'SBICAOAO',
                'country' => 'AO',
                'website' => 'https://www.standardbank.co.ao',
                'phone' => '+244 222 630 200',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Prestígio',
                'code' => 'BP',
                'swift_code' => 'BPSTAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancoprestigio.ao',
                'phone' => '+244 222 010 500',
                'is_active' => true,
            ],
            [
                'name' => 'Banco VTB África',
                'code' => 'VTB',
                'swift_code' => 'VTBAAOAO',
                'country' => 'AO',
                'website' => 'https://www.vtb.co.ao',
                'phone' => '+244 222 010 600',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Yetu',
                'code' => 'YETU',
                'swift_code' => 'YETUAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancoyetu.ao',
                'phone' => '+244 222 010 700',
                'is_active' => true,
            ],
            [
                'name' => 'Finibanco Angola',
                'code' => 'FINI',
                'swift_code' => 'FINBAOAO',
                'country' => 'AO',
                'website' => 'https://www.finibanco.ao',
                'phone' => '+244 222 010 800',
                'is_active' => true,
            ],
        ];

        foreach ($banks as $bank) {
            \App\Models\Treasury\Bank::updateOrCreate(
                ['code' => $bank['code']],
                $bank
            );
        }
    }

    /**
     * Popular categorias de equipamentos padrão para o tenant
     */
    protected static function populateEquipmentCategories($tenant)
    {
        $categories = [
            ['name' => 'Som e Áudio', 'icon' => '🔊', 'color' => '#8b5cf6', 'sort_order' => 1],
            ['name' => 'Iluminação', 'icon' => '💡', 'color' => '#f59e0b', 'sort_order' => 2],
            ['name' => 'Vídeo', 'icon' => '📹', 'color' => '#ef4444', 'sort_order' => 3],
            ['name' => 'Estruturas', 'icon' => '🏗️', 'color' => '#6b7280', 'sort_order' => 4],
            ['name' => 'Efeitos Especiais', 'icon' => '✨', 'color' => '#ec4899', 'sort_order' => 5],
            ['name' => 'Decoração', 'icon' => '🎨', 'color' => '#10b981', 'sort_order' => 6],
            ['name' => 'Mobiliário', 'icon' => '🪑', 'color' => '#3b82f6', 'sort_order' => 7],
            ['name' => 'Energia', 'icon' => '⚡', 'color' => '#eab308', 'sort_order' => 8],
            ['name' => 'Outros', 'icon' => '📁', 'color' => '#64748b', 'sort_order' => 99],
        ];

        foreach ($categories as $category) {
            \App\Models\EquipmentCategory::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $category['name'],
                ],
                [
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Popular métodos de pagamento padrão para Angola
     */
    protected static function populatePaymentMethods($tenant)
    {
        $methods = [
            [
                'name' => 'Dinheiro',
                'code' => 'CASH',
                'type' => 'cash',
                'description' => 'Pagamento em dinheiro (Kwanzas)',
                'icon' => 'fa-money-bill-wave',
                'color' => '#10b981',
                'fee_percentage' => 0,
                'fee_fixed' => 0,
                'requires_account' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Multicaixa Express',
                'code' => 'MCX',
                'type' => 'digital_wallet',
                'description' => 'Multicaixa Express (carteira digital)',
                'icon' => 'fa-mobile-alt',
                'color' => '#ef4444',
                'fee_percentage' => 0,
                'fee_fixed' => 0,
                'requires_account' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'TPA (Multicaixa)',
                'code' => 'TPA',
                'type' => 'card',
                'description' => 'Terminal de Pagamento Automático',
                'icon' => 'fa-credit-card',
                'color' => '#3b82f6',
                'fee_percentage' => 2.5,
                'fee_fixed' => 0,
                'requires_account' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Transferência Bancária',
                'code' => 'TRANSFER',
                'type' => 'bank_transfer',
                'description' => 'Transferência bancária',
                'icon' => 'fa-exchange-alt',
                'color' => '#8b5cf6',
                'fee_percentage' => 0,
                'fee_fixed' => 0,
                'requires_account' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Cheque',
                'code' => 'CHECK',
                'type' => 'check',
                'description' => 'Pagamento em cheque',
                'icon' => 'fa-money-check',
                'color' => '#f59e0b',
                'fee_percentage' => 0,
                'fee_fixed' => 0,
                'requires_account' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Débito Direto',
                'code' => 'DEBIT',
                'type' => 'bank_transfer',
                'description' => 'Débito direto em conta',
                'icon' => 'fa-university',
                'color' => '#6b7280',
                'fee_percentage' => 0,
                'fee_fixed' => 0,
                'requires_account' => true,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'MB Way Angola',
                'code' => 'MBWAY',
                'type' => 'digital_wallet',
                'description' => 'MB Way Angola (se disponível)',
                'icon' => 'fa-wallet',
                'color' => '#ec4899',
                'fee_percentage' => 0,
                'fee_fixed' => 0,
                'requires_account' => false,
                'is_active' => false,
                'sort_order' => 7,
            ],
        ];

        foreach ($methods as $method) {
            \App\Models\Treasury\PaymentMethod::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => $method['code'],
                ],
                $method
            );
        }
    }

    /**
     * Garante que o tenant tem um armazém principal/default.
     */
    protected static function populateDefaultWarehouse($tenant)
    {
        try {
            \App\Models\Invoicing\Warehouse::ensureDefaultForTenant($tenant->id);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao criar armazém principal do tenant', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // Relacionamentos
    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role_id', 'is_active', 'invited_at', 'joined_at')
            ->withTimestamps();
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'tenant_module')
            ->withPivot('is_active', 'activated_at', 'deactivated_at', 'trial_ends_at', 'price')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->hasMany(\Spatie\Permission\Models\Role::class, 'tenant_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->where(function($query) {
                // Subscription sem data de fim (perpétua) OU período ainda válido
                $query->whereNull('current_period_end')
                      ->orWhere('current_period_end', '>=', now());
            })
            // ORDEM DETERMINÍSTICA. Só `latest()` (created_at) empatava em
            // empresas com duas subscrições vivas — herança de antes de o
            // TrocarDePlano cancelar tudo — e cada ecrã apanhava uma: a lista
            // dizia «Professional» e o modal, para a MESMA empresa, «Starter».
            // Ganha a que acaba mais tarde; em empate, a mais recente.
            ->orderByDesc('current_period_end')
            ->orderByDesc('id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Métodos auxiliares
    public function isOnTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Cabe mais um utilizador? ALIAS de cabeMaisUmUtilizador().
     *
     * Estes dois viviam com regras próprias e davam respostas DIFERENTES à
     * mesma pergunta: este olhava só para o plano, o outro para o maior entre
     * plano e ficha. O ecrã de utilizadores e o de super-admin discordavam um
     * do outro sobre a mesma empresa. Agora há uma regra só.
     */
    public function canAddUser()
    {
        return $this->cabeMaisUmUtilizador();
    }

    /** Tecto de utilizadores. ALIAS de limiteDeUtilizadores(). 0 = ilimitado. */
    public function getMaxUsers()
    {
        return $this->limiteDeUtilizadores();
    }

    public function hasModule($moduleSlug)
    {
        // Se tenant está inativo, não tem acesso a nenhum módulo
        if (!$this->is_active) {
            return false;
        }

        // Build OFFLINE: a licença manda nos módulos. Isto é o funil único por
        // onde passam rotas, menu e código de negócio — chega aqui uma linha.
        // Na cloud é sempre `true` (o helper devolve true com LICENSE_ENFORCE
        // desligado), portanto não muda nada do que já corre.
        if (function_exists('licenca_tem_modulo') && !licenca_tem_modulo($moduleSlug)) {
            return false;
        }

        // Regra de negócio: a TESOURARIA acompanha sempre a FATURAÇÃO.
        // Os métodos de pagamento/caixas dependem da tesouraria, logo qualquer
        // tenant com faturação ativa tem também acesso à tesouraria, mesmo que
        // o plano/pivot não a tenha explicitamente.
        if ($moduleSlug === 'treasury') {
            return $this->modules()
                    ->whereIn('slug', ['treasury', 'invoicing'])
                    ->wherePivot('is_active', true)
                    ->where(fn ($q) => $this->aindaDentroDoTeste($q))
                    ->exists();
        }

        return $this->modules()
            ->where('slug', $moduleSlug)
            ->wherePivot('is_active', true)
            ->where(fn ($q) => $this->aindaDentroDoTeste($q))
            ->exists();
    }

    /**
     * Um módulo dado a experimentar deixa de valer quando o prazo passa.
     *
     * Sem prazo (trial_ends_at a NULL) é um módulo normal do plano e vale
     * sempre — é o caso da esmagadora maioria. Só quando alguém marcou uma
     * data é que ela conta, e passada essa data o módulo deixa de aparecer
     * como disponível: este é o único portão por onde o acesso passa
     * (CheckTenantModule chama hasModule).
     */
    protected function aindaDentroDoTeste($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('tenant_module.trial_ends_at')
              ->orWhere('tenant_module.trial_ends_at', '>=', now());
        });
    }
    
    public function isActive()
    {
        return $this->is_active === true;
    }
    
    public function canAccess()
    {
        // Tenant deve estar ativo E ter subscription ativa
        return $this->is_active && $this->hasActiveSubscription();
    }
    
    /**
     * Verificar se o tenant pode ser deletado
     * (não pode ter faturas emitidas)
     */
    /**
     * Actividade que impede apagar uma empresa: tabela => o que representa.
     *
     * A guarda anterior olhava para as facturas e mais nada. Uma empresa com
     * três anos de movimentos de stock, lançamentos contabilísticos, recibos e
     * trilha de auditoria — mas sem uma factura de venda emitida — era
     * apagável, e ia-se abaixo com tudo isso atrás.
     *
     * Medido nesta base: 142 tabelas têm `tenant_id` e a cascata trata 8. Uma
     * eliminação deixa 134 tabelas com linhas órfãs, a apontar para uma empresa
     * que já não existe. Entre elas a `audit_trail`, que é append-only e cujas
     * linhas nunca poderão ser limpas.
     *
     * A conclusão é que uma empresa COM actividade não se apaga — desactiva-se.
     * Apagar fica reservado ao caso que o justifica: uma empresa criada por
     * engano, que nunca fez nada.
     */
    private const ACTIVIDADE_QUE_IMPEDE_APAGAR = [
        'invoicing_sales_invoices'   => 'facturas de venda',
        'invoicing_receipts'         => 'recibos',
        'invoicing_credit_notes'     => 'notas de crédito',
        'invoicing_debit_notes'      => 'notas de débito',
        'invoicing_purchase_invoices' => 'facturas de compra',
        'invoicing_stock_movements'  => 'movimentos de stock',
        'invoicing_pos_shifts'       => 'turnos de caixa',
        'accounting_moves'           => 'lançamentos contabilísticos',
        'treasury_transactions'      => 'movimentos de tesouraria',
        'hr_payrolls'                => 'folhas de salários',
        'agt_submissions'            => 'comunicações à AGT',
    ];

    /*
     * A TRILHA DE AUDITORIA NÃO ENTRA NESTA LISTA, e foi preciso medir para
     * perceber porquê.
     *
     * Parecia o melhor sinal de todos — é append-only e regista tudo. Mas uma
     * empresa acabada de criar já nasce com três linhas lá: o próprio
     * provisionamento cria o armazém, os métodos de pagamento e a série FR, e
     * esses modelos são auditados.
     *
     * Ou seja, a trilha bloquearia TODAS as empresas, incluindo a criada por
     * engano há cinco minutos que é o único caso em que apagar faz sentido. É
     * um sinal de que o sistema mexeu, não de que a empresa trabalhou.
     *
     * Consequência assumida: apagar uma empresa deixa essas poucas linhas de
     * auditoria órfãs. Como só se apagam empresas sem actividade nenhuma, são
     * meia dúzia de registos a dizer que um armazém foi criado — e a trilha
     * recusa eliminações por desenho, que é a garantia que interessa manter.
     */

    /**
     * O limite de utilizadores que vale de facto para esta empresa.
     *
     * O MAIOR entre a ficha e o plano. O campo "Máx. Utilizadores" da ficha
     * existia desde sempre e nunca era verificado, portanto ninguém reparou que
     * se afasta do plano: medido nesta base, três das seis empresas tinham a
     * ficha ABAIXO do que pagam — uma delas dizia 3 na ficha, com plano
     * Business de 50, e já lá trabalhavam 5 pessoas.
     *
     * O plano é o que o cliente paga; a ficha serve para conceder MAIS do que
     * o plano dá, nunca menos.
     *
     * Vive no modelo e não no ecrã de super-admin para que todos os sítios que
     * perguntem "cabe mais um?" respondam o mesmo.
     */
    public function limiteDeUtilizadores(): int
    {
        $limite = max(
            (int) ($this->max_users ?? 0),
            (int) ($this->activeSubscription?->plan?->max_users ?? 0)
        );

        // Build OFFLINE: a licença é um TETO, não um chão. O que o cliente
        // comprou não pode ser alargado mexendo na ficha da empresa — por isso
        // aqui é mínimo, ao contrário do max() acima. Na cloud devolve 0
        // (sem tecto) e nada muda.
        if (function_exists('licenca_max_utilizadores')) {
            $daLicenca = licenca_max_utilizadores();
            if ($daLicenca > 0) {
                return $limite > 0 ? min($limite, $daLicenca) : $daLicenca;
            }
        }

        return $limite;
    }

    /**
     * Quantos documentos fiscais esta empresa ainda pode emitir? NULL = sem tecto.
     *
     * O tecto viaja na SUBSCRIÇÃO, não no plano. Uma promoção que hoje passa a
     * dar 500 documentos não pode encolher o que já se prometeu a quem
     * assinou ontem: as subscrições antigas têm NULL e continuam sem limite.
     * (A mesma ideia do `com_oferta` — a subscrição guarda o acordo com que
     * nasceu; ver App\Support\AcordoDeSubscricao.)
     *
     * A ficha da empresa CONCEDE mais, nunca corta — é o `max()`, igual ao
     * limite de utilizadores.
     */
    public function limiteDeDocumentos(): ?int
    {
        $daSubscricao = $this->activeSubscription?->max_documentos;

        // Sem tecto na subscrição não há tecto nenhum — é o caso de todos os
        // planos e de todas as subscrições feitas antes desta política.
        if ($daSubscricao === null) {
            return null;
        }

        // A ficha VAZIA é ausência, não concessão: quem não escreveu nada não
        // está a dar mais nada. Só um número maior na ficha alarga o tecto.
        $daFicha = $this->max_documents;

        return $daFicha === null
            ? (int) $daSubscricao
            : max((int) $daSubscricao, (int) $daFicha);
    }

    /**
     * Documentos fiscais já emitidos por esta empresa.
     *
     * Conta-se, não se guarda: um contador numa coluna é uma verdade que
     * envelhece sozinha (basta um documento apagado ou uma migração). Só
     * corre para quem TEM tecto, que são poucos.
     *
     * Contam os documentos que vão à AGT — faturas, notas de crédito e de
     * débito, recibos. Proformas e orçamentos não contam: não são fiscais, e
     * ninguém deve gastar a sua quota a fazer uma estimativa.
     */
    public function documentosEmitidos(): int
    {
        $tabelas = [
            'invoicing_sales_invoices',
            'invoicing_credit_notes',
            'invoicing_debit_notes',
            'invoicing_receipts',
        ];

        $total = 0;

        foreach ($tabelas as $tabela) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($tabela)) {
                continue;
            }

            $total += (int) \Illuminate\Support\Facades\DB::table($tabela)
                ->where('tenant_id', $this->id)
                ->count();
        }

        return $total;
    }

    /** Ainda cabe mais um documento? */
    public function podeEmitirDocumento(): bool
    {
        $limite = $this->limiteDeDocumentos();

        return $limite === null || $this->documentosEmitidos() < $limite;
    }

    /**
     * A empresa chegou a ser ACTIVADA, ou é só um registo à espera?
     *
     * Isto existe por causa dos avisos de facturação. Aprovar um pedido de
     * licença cria a empresa com o email do formulário e sem ninguém lá dentro
     * — e o contacto de facturação recorre a esse email. Sem este travão, uma
     * empresa que nunca instalou nada começava a receber "a sua factura está a
     * vencer" no dia seguinte.
     *
     * Conta como activada de duas formas, porque há dois mundos:
     *  - NUVEM: alguém se registou e pode entrar (utilizador activo);
     *  - LOCAL: a instalação já falou connosco, ou já levou a licença — sinal
     *    de que existe mesmo uma máquina a usar isto. (Os utilizadores de uma
     *    instalação local vivem na base de dados DELA, nunca aparecem aqui.)
     */
    public function activacaoConcluida(): bool
    {
        if ($this->users()->where('users.is_active', true)->exists()) {
            return true;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('licencas_emitidas')
            && \App\Models\LicencaEmitida::where('tenant_id', $this->id)
                ->whereNotNull('ultimo_checkin')->exists()) {
            return true;
        }

        return \Illuminate\Support\Facades\Schema::hasTable('license_requests')
            && \App\Models\LicenseRequest::where('tenant_id', $this->id)
                ->whereNotNull('entregue_em')->exists();
    }

    /** A ficha está a prometer menos do que o plano dá? */
    public function fichaAbaixoDoPlano(): bool
    {
        $doPlano = (int) ($this->activeSubscription?->plan?->max_users ?? 0);

        return $doPlano > 0 && (int) ($this->max_users ?? 0) < $doPlano;
    }

    /** Ainda cabe mais um utilizador nesta empresa? */
    public function cabeMaisUmUtilizador(): bool
    {
        $limite = $this->limiteDeUtilizadores();

        // 0 = ILIMITADO. Um campo por preencher não pode trancar a porta a
        // quem paga — entre deixar entrar um a mais e bloquear a empresa
        // inteira, o erro barato é o primeiro.
        return $limite <= 0 || $this->utilizadoresQueContam() < $limite;
    }

    /**
     * Quantos utilizadores contam para o limite: só os ACTIVOS.
     *
     * Contava-se toda a gente ligada à empresa, incluindo quem já lá não
     * trabalha — desactivar alguém não libertava vaga e a única saída era
     * apagar a conta, perdendo o rasto de quem fez o quê.
     */
    public function utilizadoresQueContam(): int
    {
        return $this->users()
            ->wherePivot('is_active', true)
            ->where('users.is_active', true)
            ->count();
    }

    /**
     * Esta empresa pode ser apagada?
     *
     * @return array{can_delete:bool, reason:?string, encontrado:array<string,int>}
     */
    /**
     * O nome da empresa como deve sair IMPRESSO nos documentos.
     *
     * Uma empresa tem dois nomes: o comercial (`name`), que é o da tabuleta e
     * o que o cliente conhece, e a designação social (`company_name`), que é
     * o do registo. A empresa escolhe nas Definições de Faturação qual sai.
     *
     * Se o escolhido estiver vazio usa-se o outro — mais vale o nome errado
     * do que um documento com um cabeçalho em branco.
     *
     * ISTO É SÓ O QUE SE IMPRIME. O SAFT-AO e a comunicação à AGT não passam
     * por aqui de propósito: o que vai para o fisco não é uma preferência de
     * quem usa o sistema.
     */
    public function nomeParaDocumentos(): string
    {
        $comercial = trim((string) $this->name);
        $social = trim((string) $this->company_name);

        $escolha = optional(
            \App\Models\Invoicing\InvoicingSettings::forTenant($this->id)
        )->nome_nos_documentos;

        if ($escolha === \App\Models\Invoicing\InvoicingSettings::NOME_COMERCIAL) {
            return $comercial !== '' ? $comercial : $social;
        }

        return $social !== '' ? $social : $comercial;
    }

    public function canBeDeleted()
    {
        $encontrado = [];
        foreach (self::ACTIVIDADE_QUE_IMPEDE_APAGAR as $tabela => $descricao) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($tabela)) continue;
            $n = \DB::table($tabela)->where('tenant_id', $this->id)->count();
            if ($n > 0) $encontrado[$descricao] = $n;
        }
        if ($this->invoices()->exists()) {
            $encontrado['facturas'] = $this->invoices()->count();
        }
        if (empty($encontrado)) {
            return ['can_delete' => true, 'reason' => null, 'encontrado' => []];
        }
        $lista = [];
        foreach ($encontrado as $descricao => $n) $lista[] = "{$n} {$descricao}";
        return [
            'can_delete' => false,
            'reason' => 'Esta empresa tem actividade registada e não pode ser apagada: '
                . implode(', ', $lista) . '. Desactive-a — os dados ficam guardados e ninguém entra.',
            'encontrado' => $encontrado,
        ];
    }

    /** Guarda específica do dono: qualquer documento fiscal, até rascunho, bloqueia. */
    public function canBeArchivedByOwner(): array
    {
        $fiscais = [
            'invoicing_sales_invoices' => 'facturas/FR',
            'invoicing_credit_notes' => 'notas de crédito',
            'invoicing_debit_notes' => 'notas de débito',
            'invoicing_receipts' => 'recibos',
        ];
        $encontrado = [];
        foreach ($fiscais as $tabela => $descricao) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($tabela)) continue;
            $q = \DB::table($tabela)->where('tenant_id', $this->id);
            $n = $q->count();
            if ($n > 0) $encontrado[$descricao] = $n;
        }

        if (empty($encontrado)) {
            return ['can_delete' => true, 'reason' => null, 'encontrado' => [], 'invoices_count' => 0];
        }

        $lista = [];
        foreach ($encontrado as $descricao => $n) {
            $lista[] = "{$n} {$descricao}";
        }

        return [
            'can_delete' => false,
            'reason' => 'Esta empresa tem documentos fiscais definitivos e não pode ser apagada: '
                . implode(', ', $lista) . '.',
            'encontrado' => $encontrado,
            'invoices_count' => array_sum($encontrado),
        ];
    }
}
