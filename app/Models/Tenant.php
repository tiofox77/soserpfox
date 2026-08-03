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
        'country',
        'max_users',
        'max_storage_mb',
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
        
        static::deleting(function ($tenant) {
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
                
                \DB::commit();
                
                \Log::info("✅ EXCLUSÃO EM CASCATA CONCLUÍDA COM SUCESSO", [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
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
            ->withPivot('is_active', 'activated_at', 'deactivated_at')
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
            ->latest();
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

    public function canAddUser()
    {
        $maxUsers = $this->getMaxUsers();
        return $this->users()->count() < $maxUsers;
    }
    
    public function getMaxUsers()
    {
        // Buscar do plano ativo através da subscription
        $subscription = $this->activeSubscription;
        
        if ($subscription && $subscription->plan) {
            return $subscription->plan->max_users;
        }
        
        // Fallback para o valor do tenant
        return $this->max_users ?? 3;
    }

    public function hasModule($moduleSlug)
    {
        // Se tenant está inativo, não tem acesso a nenhum módulo
        if (!$this->is_active) {
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
                    ->exists();
        }
        
        return $this->modules()
            ->where('slug', $moduleSlug)
            ->wherePivot('is_active', true)
            ->exists();
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
    public function canBeDeleted()
    {
        // Verificar se tem faturas
        if ($this->invoices()->exists()) {
            return [
                'can_delete' => false,
                'reason' => 'Não é possível excluir uma empresa que já tem faturas emitidas.',
                'invoices_count' => $this->invoices()->count(),
            ];
        }
        
        // Verificar se tem sales_invoices (módulo de faturação)
        if (class_exists('\App\Models\Invoicing\SalesInvoice')) {
            $salesInvoicesCount = \App\Models\Invoicing\SalesInvoice::where('tenant_id', $this->id)->count();
            if ($salesInvoicesCount > 0) {
                return [
                    'can_delete' => false,
                    'reason' => 'Não é possível excluir uma empresa que já tem faturas emitidas.',
                    'invoices_count' => $salesInvoicesCount,
                ];
            }
        }
        
        return [
            'can_delete' => true,
            'reason' => null,
        ];
    }
}
