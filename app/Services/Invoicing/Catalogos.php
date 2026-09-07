<?php

namespace App\Services\Invoicing;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Rules\PaisIso;
use App\Support\Geografia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * OS CATÁLOGOS DA FACTURAÇÃO — fornecedores, categorias, marcas, armazéns,
 * condições de pagamento e impostos — descritos num sítio só.
 *
 * Eram seis componentes Livewire com a mesma forma: uma lista com procura, um
 * formulário num modal, gravar, apagar. O que os distinguia (as colunas, os
 * campos, as regras, quem pode apagar o quê) vivia espalhado por 1.300 linhas.
 * Aqui é um ESQUEMA por catálogo, e o ecrã em React desenha-o; a API lê daqui
 * as regras e as guardas.
 *
 * O QUE CADA ESQUEMA DIZ:
 *
 *  · `campos`      — o formulário: chave, rótulo, tipo, obrigatório, omissão.
 *  · `colunas`     — a tabela.
 *  · `regras`      — a validação, a MESMA que o Livewire aplicava.
 *  · `preparar`    — o que se normaliza antes de gravar (a morada, o código
 *                    SAFT que acompanha o tipo, a ordem da condição nova).
 *  · `depois`      — o que muda nos outros ao gravar este (só um padrão).
 *  · `pode_apagar` — a guarda: um fornecedor com compras, uma categoria com
 *                    artigos, um armazém com stock não se apagam.
 *  · `permissoes`  — por verbo. Os catálogos que têm permissão de criar,
 *                    editar e apagar no catálogo de permissões usam-nas; os
 *                    impostos só têm `edit`, e as condições de pagamento
 *                    andam com as definições.
 */
final class Catalogos
{
    public const TIPOS_DE_ENTIDADE = [
        ['valor' => 'pessoa_juridica', 'rotulo' => 'Pessoa Colectiva'],
        ['valor' => 'pessoa_fisica', 'rotulo' => 'Pessoa Singular'],
    ];

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::todos());
    }

    public static function um(string $slug): array
    {
        return self::todos()[$slug];
    }

    /** @return array<string, array> */
    public static function todos(): array
    {
        return [
            'fornecedores' => self::fornecedores(),
            'categorias' => self::categorias(),
            'marcas' => self::marcas(),
            'armazens' => self::armazens(),
            'condicoes-de-pagamento' => self::condicoesDePagamento(),
            'impostos' => self::impostos(),
        ];
    }

    /* ─── Os esquemas ──────────────────────────────────────────────────── */

    private static function fornecedores(): array
    {
        return [
            'modelo' => Supplier::class,
            'titulo' => 'Fornecedores',
            'singular' => 'Fornecedor',
            'icone' => 'fa-truck',
            'cor' => 'laranja',
            'descricao' => 'Gerir fornecedores',
            'novo' => 'Novo Fornecedor',
            'rota' => '/invoicing/suppliers',
            'permissoes' => self::porVerbo('invoicing.suppliers'),
            'pesquisa' => ['name', 'nif', 'email', 'phone'],
            'pesquisa_ajuda' => 'Nome, NIF, email ou telefone',
            'ordem' => [['created_at', 'desc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'nif', 'rotulo' => 'NIF', 'formato' => 'texto'],
                ['chave' => 'type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'email', 'rotulo' => 'Email', 'formato' => 'texto'],
                ['chave' => 'phone', 'rotulo' => 'Telefone', 'formato' => 'texto'],
                ['chave' => 'city', 'rotulo' => 'Cidade', 'formato' => 'texto'],
            ],
            'filtros' => [
                ['chave' => 'type', 'rotulo' => 'Tipo', 'opcoes' => self::TIPOS_DE_ENTIDADE],
                // A CIDADE era um filtro próprio no ecrã de sempre, escrito à
                // mão: com fornecedores em três províncias, é por ela que se
                // encontra quem serve uma delas.
                ['chave' => 'city', 'rotulo' => 'Cidade', 'tipo' => 'texto', 'ajuda' => 'Ex.: Luanda'],
            ],
            // «Quem entrou este mês» é uma pergunta que se faz a esta lista.
            'datas' => true,
            'campos' => [
                self::campo('type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'pessoa_juridica', opcoes: self::TIPOS_DE_ENTIDADE),
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('nif', 'NIF', 'texto'),
                self::campo('email', 'Email', 'email'),
                self::campo('phone', 'Telefone', 'texto'),
                self::campo('mobile', 'Telemóvel', 'texto'),
                self::campo('country', 'País', 'pais', obrigatorio: true, omissao: Geografia::PAIS_PADRAO),
                self::campo('province', 'Província', 'provincia'),
                self::campo('municipality', 'Município', 'municipio'),
                self::campo('neighbourhood', 'Bairro', 'texto'),
                self::campo('city', 'Cidade', 'cidade', ajuda: 'Em Angola a cidade é o município.'),
                self::campo('postal_code', 'Código postal', 'texto'),
                self::campo('address', 'Morada', 'texto', largura: 'inteira'),
            ],
            'regras' => [
                'type' => 'required|in:pessoa_juridica,pessoa_fisica',
                'name' => 'required|min:3',
                'nif' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'mobile' => 'nullable|string',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'municipality' => 'nullable|string|max:100',
                'neighbourhood' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country' => ['required', 'string', 'size:2', new PaisIso()],
            ],
            'preparar' => fn (array $d, ?Model $m, int $tenantId) => array_merge($d, self::morada($d)),
            'pode_apagar' => fn (Model $m) => ! PurchaseInvoice::where('supplier_id', $m->id)->exists(),
            'ao_apagar' => function (Model $m) {
                // A pasta do logótipo vai com ele.
                $pasta = 'suppliers/' . $m->id;
                if (Storage::disk('public')->exists($pasta)) {
                    Storage::disk('public')->deleteDirectory($pasta);
                }
            },
            'accoes' => ['activar' => false, 'padrao' => false, 'logotipo' => true, 'apagar' => true],
            'geografia' => true,
            /*
             * O FORNECEDOR TEM EXTRATO — o que já lhe comprámos, quanto se lhe
             * deve, o que mais lhe compramos e de quanto em quanto tempo. Uma
             * marca ou uma unidade de medida não têm nada disto, e uma janela
             * que abre vazia faz acreditar que não se comprou nada.
             */
            'extrato' => true,
        ];
    }

    private static function categorias(): array
    {
        return [
            'modelo' => Category::class,
            'titulo' => 'Categorias',
            'singular' => 'Categoria',
            'icone' => 'fa-folder',
            'cor' => 'ciano',
            'descricao' => 'Gerir categorias e subcategorias',
            'novo' => 'Nova Categoria',
            'rota' => '/invoicing/categories',
            'permissoes' => self::porVerbo('invoicing.categories'),
            'pesquisa' => ['name', 'description'],
            'pesquisa_ajuda' => 'Nome ou descrição',
            'ordem' => [['order', 'asc'], ['created_at', 'desc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'parent_id', 'rotulo' => 'Categoria-mãe', 'formato' => 'escolha'],
                ['chave' => 'icon', 'rotulo' => 'Ícone', 'formato' => 'icone'],
                ['chave' => 'color', 'rotulo' => 'Cor', 'formato' => 'cor'],
                ['chave' => 'order', 'rotulo' => 'Ordem', 'formato' => 'numero', 'alinhar' => 'direita'],
            ],
            'filtros' => [
                ['chave' => 'nivel', 'rotulo' => 'Nível', 'opcoes' => [
                    ['valor' => 'principal', 'rotulo' => 'Principais'],
                    ['valor' => 'sub', 'rotulo' => 'Subcategorias'],
                ]],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('parent_id', 'Categoria-mãe', 'referencia', referencia: 'categorias_principais', ajuda: 'Vazio para uma categoria principal.'),
                self::campo('icon', 'Ícone (Font Awesome)', 'texto', obrigatorio: true, omissao: 'fa-folder'),
                self::campo('color', 'Cor', 'cor', obrigatorio: true, omissao: '#3B82F6'),
                self::campo('order', 'Ordem', 'numero', omissao: 0),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|min:2',
                'parent_id' => 'nullable|integer',
                'description' => 'nullable|string',
                'icon' => 'required|string',
                'color' => 'required|string',
                'order' => 'nullable|integer',
            ],
            'validar' => function (array $d, ?Model $m, int $tenantId) {
                if (empty($d['parent_id'])) {
                    return [];
                }
                if ($m && (int) $d['parent_id'] === (int) $m->id) {
                    return ['parent_id' => __('Uma categoria não pode ser mãe de si própria.')];
                }
                $mae = Category::where('tenant_id', $tenantId)->find($d['parent_id']);

                return $mae ? [] : ['parent_id' => __('Categoria-mãe desconhecida nesta empresa.')];
            },
            'preparar' => fn (array $d) => array_merge($d, ['parent_id' => ($d['parent_id'] ?? null) ?: null, 'order' => (int) ($d['order'] ?? 0)]),
            'pode_apagar' => fn (Model $m) => ! Category::where('parent_id', $m->id)->exists()
                && ! Product::where('category_id', $m->id)->exists(),
            'referencias' => fn (int $tenantId) => [
                'categorias_principais' => Category::where('tenant_id', $tenantId)->whereNull('parent_id')->orderBy('name')
                    ->get(['id', 'name'])->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->all(),
            ],
            'accoes' => ['activar' => false, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function marcas(): array
    {
        return [
            'modelo' => Brand::class,
            'titulo' => 'Marcas',
            'singular' => 'Marca',
            'icone' => 'fa-tag',
            'cor' => 'rosa',
            'descricao' => 'Gerir marcas de produtos',
            'novo' => 'Nova Marca',
            'rota' => '/invoicing/brands',
            'permissoes' => self::porVerbo('invoicing.brands'),
            'pesquisa' => ['name', 'description'],
            'pesquisa_ajuda' => 'Nome ou descrição',
            'ordem' => [['order', 'asc'], ['created_at', 'desc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'description', 'rotulo' => 'Descrição', 'formato' => 'texto'],
                ['chave' => 'website', 'rotulo' => 'Site', 'formato' => 'texto'],
                ['chave' => 'order', 'rotulo' => 'Ordem', 'formato' => 'numero', 'alinhar' => 'direita'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('icon', 'Ícone (Font Awesome)', 'texto', obrigatorio: true, omissao: 'fa-tag'),
                self::campo('logo', 'Logótipo (URL)', 'url'),
                self::campo('website', 'Site', 'url'),
                self::campo('order', 'Ordem', 'numero', omissao: 0),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|min:2',
                'description' => 'nullable|string',
                'icon' => 'required|string',
                'logo' => 'nullable|url',
                'website' => 'nullable|url',
                'order' => 'nullable|integer',
            ],
            'preparar' => fn (array $d) => array_merge($d, ['order' => (int) ($d['order'] ?? 0)]),
            'pode_apagar' => fn (Model $m) => ! Product::where('brand_id', $m->id)->exists(),
            'accoes' => ['activar' => false, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function armazens(): array
    {
        return [
            'modelo' => Warehouse::class,
            'titulo' => 'Armazéns',
            'singular' => 'Armazém',
            'icone' => 'fa-warehouse',
            'cor' => 'primaria',
            'descricao' => 'Gerir armazéns',
            'novo' => 'Novo Armazém',
            'rota' => '/invoicing/warehouses',
            'permissoes' => self::porVerbo('invoicing.warehouses'),
            'pesquisa' => ['name', 'code', 'city'],
            'pesquisa_ajuda' => 'Nome, código ou cidade',
            'ordem' => [['created_at', 'desc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'city', 'rotulo' => 'Cidade', 'formato' => 'texto'],
                ['chave' => 'manager_id', 'rotulo' => 'Responsável', 'formato' => 'escolha'],
                ['chave' => 'is_default', 'rotulo' => 'Padrão', 'formato' => 'padrao'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'is_active', 'rotulo' => 'Estado', 'opcoes' => [
                    ['valor' => '1', 'rotulo' => 'Activos'],
                    ['valor' => '0', 'rotulo' => 'Inactivos'],
                ]],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('location', 'Localização', 'texto'),
                self::campo('city', 'Cidade', 'texto'),
                self::campo('address', 'Morada', 'texto', largura: 'inteira'),
                self::campo('postal_code', 'Código postal', 'texto'),
                self::campo('phone', 'Telefone', 'texto'),
                self::campo('email', 'Email', 'email'),
                self::campo('manager_id', 'Responsável', 'referencia', referencia: 'utilizadores'),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('is_default', 'Armazém padrão', 'booleano', omissao: false),
            ],
            'regras' => [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50',
                'location' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'manager_id' => 'nullable|integer',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
            ],
            'validar' => function (array $d, ?Model $m, int $tenantId) {
                if (empty($d['manager_id'])) {
                    return [];
                }
                $e = User::whereKey($d['manager_id'])->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))->exists();

                return $e ? [] : ['manager_id' => __('Esse utilizador não pertence a esta empresa.')];
            },
            'preparar' => fn (array $d) => array_merge($d, [
                'manager_id' => ($d['manager_id'] ?? null) ?: null,
                'is_active' => (bool) ($d['is_active'] ?? true),
                'is_default' => (bool) ($d['is_default'] ?? false),
            ]),
            // Se marcado como padrão, desmarcar os outros.
            'depois' => function (Model $m) {
                if ($m->is_default) {
                    $m->setAsDefault();
                }
            },
            'padrao' => fn (Model $m) => $m->setAsDefault(),
            'pode_apagar' => fn (Model $m) => ! Stock::where('warehouse_id', $m->id)->where('quantity', '>', 0)->exists(),
            'referencias' => fn (int $tenantId) => [
                'utilizadores' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))->orderBy('name')
                    ->get(['id', 'name'])->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->all(),
            ],
            'accoes' => ['activar' => true, 'padrao' => true, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function condicoesDePagamento(): array
    {
        return [
            'modelo' => PaymentTerm::class,
            'titulo' => 'Condições de Pagamento',
            'singular' => 'Condição de pagamento',
            'icone' => 'fa-calendar-check',
            'cor' => 'bom',
            'descricao' => 'Gerir condições de pagamento',
            'novo' => 'Nova Condição',
            'rota' => '/invoicing/payment-terms',
            // Andam com as definições da facturação.
            'permissoes' => ['ver' => 'invoicing.settings.view', 'criar' => 'invoicing.settings.edit', 'editar' => 'invoicing.settings.edit', 'apagar' => 'invoicing.settings.edit'],
            'pesquisa' => ['name'],
            'pesquisa_ajuda' => 'Nome',
            'ordem' => [['sort_order', 'asc'], ['name', 'asc']],
            // O catálogo é provisionado à primeira vista.
            'antes' => fn (int $tenantId) => PaymentTerm::provisionarPadroes($tenantId),
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'days', 'rotulo' => 'Dias', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'is_default', 'rotulo' => 'Padrão', 'formato' => 'padrao'],
                ['chave' => 'is_active', 'rotulo' => 'Activa', 'formato' => 'booleano'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('days', 'Dias até ao vencimento', 'numero', obrigatorio: true, omissao: 0, min: 0, max: 3650),
                self::campo('is_default', 'Condição padrão dos clientes novos', 'booleano', omissao: false),
                self::campo('is_active', 'Activa', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|string|max:100',
                'days' => 'required|integer|min:0|max:3650',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
            ],
            // Nome único por empresa (excepto a própria em edição).
            'validar' => function (array $d, ?Model $m, int $tenantId) {
                $duplicado = PaymentTerm::where('tenant_id', $tenantId)
                    ->where('name', $d['name'])
                    ->when($m, fn ($q) => $q->where('id', '!=', $m->id))
                    ->exists();

                return $duplicado ? ['name' => __('Já existe uma condição com esse nome.')] : [];
            },
            'preparar' => function (array $d, ?Model $m, int $tenantId) {
                $d['days'] = (int) $d['days'];
                $d['is_default'] = (bool) ($d['is_default'] ?? false);
                $d['is_active'] = (bool) ($d['is_active'] ?? true);
                if (! $m) {
                    $d['sort_order'] = (int) (PaymentTerm::where('tenant_id', $tenantId)->max('sort_order')) + 1;
                }

                return $d;
            },
            // Só uma pode ser a padrão.
            'depois' => function (Model $m) {
                if ($m->is_default) {
                    PaymentTerm::where('tenant_id', $m->tenant_id)->where('id', '!=', $m->id)
                        ->where('is_default', true)->update(['is_default' => false]);
                }
            },
            'padrao' => fn (Model $m) => PaymentTerm::definirPadrao($m->tenant_id, $m->id),
            // A FK nos clientes é ON DELETE SET NULL: não se perde nada crítico.
            'pode_apagar' => fn (Model $m) => true,
            'accoes' => ['activar' => true, 'padrao' => true, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function impostos(): array
    {
        $tiposSaft = [
            ['valor' => 'NOR', 'rotulo' => 'NOR — Taxa normal'],
            ['valor' => 'RED', 'rotulo' => 'RED — Taxa reduzida'],
            ['valor' => 'ISE', 'rotulo' => 'ISE — Isento'],
            ['valor' => 'NS', 'rotulo' => 'NS — Não sujeito'],
            ['valor' => 'OUT', 'rotulo' => 'OUT — Outro'],
        ];
        $tipos = [
            ['valor' => 'iva', 'rotulo' => 'IVA'],
            ['valor' => 'irt', 'rotulo' => 'IRT'],
            ['valor' => 'other', 'rotulo' => 'Outro'],
        ];

        return [
            'modelo' => Tax::class,
            'titulo' => 'Impostos',
            'singular' => 'Imposto',
            'icone' => 'fa-percent',
            'cor' => 'aviso',
            'descricao' => 'Gerir taxas de imposto',
            'novo' => 'Novo Imposto',
            'rota' => '/invoicing/taxes',
            // No catálogo de permissões os impostos só têm `view` e `edit`.
            'permissoes' => ['ver' => 'invoicing.taxes.view', 'criar' => 'invoicing.taxes.edit', 'editar' => 'invoicing.taxes.edit', 'apagar' => 'invoicing.taxes.edit'],
            'pesquisa' => ['name', 'code'],
            'pesquisa_ajuda' => 'Nome ou código',
            'ordem' => [['is_default', 'desc'], ['type', 'asc'], ['rate', 'desc']],
            'colunas' => [
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'rate', 'rotulo' => 'Taxa', 'formato' => 'percentagem', 'alinhar' => 'direita'],
                ['chave' => 'type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'saft_type', 'rotulo' => 'SAFT', 'formato' => 'escolha'],
                ['chave' => 'is_default', 'rotulo' => 'Padrão', 'formato' => 'padrao'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'type', 'rotulo' => 'Tipo', 'opcoes' => $tipos],
            ],
            'campos' => [
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('rate', 'Taxa (%)', 'numero', obrigatorio: true, omissao: 14, passo: 0.01, min: 0, max: 100),
                self::campo('type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'iva', opcoes: $tipos),
                self::campo('saft_type', 'Código SAFT', 'escolha', obrigatorio: true, omissao: 'NOR', opcoes: $tiposSaft, ajuda: 'É o que se declara à AGT. Acompanha o tipo.'),
                self::campo('exemption_reason', 'Motivo de isenção (código AGT)', 'texto'),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
                self::campo('is_default', 'Imposto padrão', 'booleano', omissao: false),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('include_in_price', 'Incluído no preço', 'booleano', omissao: false),
            ],
            'regras' => [
                'code' => 'required|max:20',
                'name' => 'required|max:100',
                'rate' => 'required|numeric|min:0|max:100',
                'type' => 'required|in:iva,irt,other',
                'saft_type' => 'required|in:NOR,RED,ISE,NS,OUT',
                'exemption_reason' => 'nullable|string|max:20',
                'description' => 'nullable|string',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
                'include_in_price' => 'boolean',
            ],
            // O código SAFT acompanha o tipo: é o saft_code que o TaxResolver
            // lê para declarar o imposto à AGT.
            'preparar' => function (array $d, ?Model $m) {
                $d['saft_code'] = $d['saft_type'];
                $d['is_default'] = (bool) ($d['is_default'] ?? false);
                $d['is_active'] = (bool) ($d['is_active'] ?? true);
                $d['include_in_price'] = (bool) ($d['include_in_price'] ?? false);
                if (! $m) {
                    $d['compound_tax'] = false;
                }

                return $d;
            },
            'depois' => function (Model $m) {
                if ($m->is_default) {
                    Tax::where('tenant_id', $m->tenant_id)->where('id', '!=', $m->id)->update(['is_default' => false]);
                }
            },
            'padrao' => function (Model $m) {
                Tax::where('tenant_id', $m->tenant_id)->where('id', '!=', $m->id)->update(['is_default' => false]);
                $m->update(['is_default' => true]);
            },
            // O ecrã de sempre não apaga impostos: uma taxa referida por artigos
            // e por linhas de documentos não desaparece.
            'pode_apagar' => fn (Model $m) => false,
            'accoes' => ['activar' => true, 'padrao' => true, 'logotipo' => false, 'apagar' => false],
        ];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private static function porVerbo(string $prefixo): array
    {
        return ['ver' => "$prefixo.view", 'criar' => "$prefixo.create", 'editar' => "$prefixo.edit", 'apagar' => "$prefixo.delete"];
    }

    private static function campo(
        string $chave, string $rotulo, string $tipo, bool $obrigatorio = false, mixed $omissao = null,
        ?array $opcoes = null, ?string $referencia = null, ?string $ajuda = null, string $largura = 'meia',
        ?float $passo = null, ?float $min = null, ?float $max = null,
    ): array {
        return array_filter([
            'chave' => $chave, 'rotulo' => __($rotulo), 'tipo' => $tipo, 'obrigatorio' => $obrigatorio,
            'omissao' => $omissao, 'largura' => $largura, 'opcoes' => $opcoes, 'referencia' => $referencia,
            'ajuda' => $ajuda ? __($ajuda) : null, 'passo' => $passo, 'min' => $min, 'max' => $max,
        ], fn ($v) => $v !== null);
    }

    /**
     * A morada normalizada — a mesma regra do trait ConcordaComAMorada: o
     * país é um código ISO, a província angolana escreve-se como no catálogo,
     * e em Angola a cidade é o município.
     */
    private static function morada(array $d): array
    {
        $pais = Geografia::normalizarPais($d['country'] ?? null) ?? Geografia::PAIS_PADRAO;
        $ehAo = $pais === Geografia::PAIS_PADRAO;
        $provincia = trim((string) ($d['province'] ?? ''));
        $municipio = trim((string) ($d['municipality'] ?? ''));

        return [
            'address' => trim((string) ($d['address'] ?? '')) ?: null,
            'country' => $pais,
            'province' => $provincia === '' ? null : ($ehAo ? Geografia::normalizarProvincia($provincia) : $provincia),
            'municipality' => $municipio === '' ? null : $municipio,
            'neighbourhood' => trim((string) ($d['neighbourhood'] ?? '')) ?: null,
            'city' => $ehAo ? ($municipio ?: null) : (trim((string) ($d['city'] ?? '')) ?: null),
            'postal_code' => trim((string) ($d['postal_code'] ?? '')) ?: null,
        ];
    }

    /* ─── O que a API usa ──────────────────────────────────────────────── */

    /** A consulta base do catálogo, já com a empresa, a procura, os filtros e a ordem. */
    public static function consulta(array $def, int $tenantId, array $filtros): Builder
    {
        $q = $def['modelo']::query()->where('tenant_id', $tenantId);

        if (! empty($filtros['procura'])) {
            $q->where(function (Builder $w) use ($def, $filtros) {
                foreach ($def['pesquisa'] as $c) {
                    $w->orWhere($c, 'like', '%' . $filtros['procura'] . '%');
                }
            });
        }

        foreach ($def['filtros'] as $f) {
            $valor = $filtros[$f['chave']] ?? '';
            if ($valor === '' || $valor === null) {
                continue;
            }
            if ($f['chave'] === 'nivel') {
                $valor === 'principal' ? $q->whereNull('parent_id') : $q->whereNotNull('parent_id');
            } elseif (($f['tipo'] ?? 'escolha') === 'texto') {
                // UM FILTRO ESCRITO À MÃO procura por dentro: «Luanda» tem de
                // apanhar «Luanda Sul». Era assim o `cityFilter` do ecrã de
                // sempre, e um `=` obrigava a acertar a cidade toda.
                $q->where($f['chave'], 'like', '%' . $valor . '%');
            } else {
                $q->where($f['chave'], $valor);
            }
        }

        /*
         * O INTERVALO DE DATAS, por data de criação da ficha.
         *
         * Só nos catálogos que o declaram (`'datas' => true`): é uma pergunta
         * que faz sentido numa lista de fornecedores («quem entrou este mês»)
         * e nenhum numa de unidades de medida.
         */
        if (! empty($def['datas'])) {
            if (! empty($filtros['de'])) {
                $q->whereDate('created_at', '>=', $filtros['de']);
            }
            if (! empty($filtros['ate'])) {
                $q->whereDate('created_at', '<=', $filtros['ate']);
            }
        }

        foreach ($def['ordem'] as [$coluna, $sentido]) {
            $q->orderBy($coluna, $sentido);
        }

        return $q;
    }

    /** Uma linha como a tabela a mostra: os campos, as bandeiras e os rótulos. */
    public static function linha(array $def, Model $m, array $referencias): array
    {
        $linha = ['id' => $m->id, 'rotulos' => []];

        foreach ($def['campos'] as $c) {
            $valor = $m->{$c['chave']};
            $linha[$c['chave']] = $valor;

            if ($c['tipo'] === 'escolha') {
                $linha['rotulos'][$c['chave']] = collect($c['opcoes'])->firstWhere('valor', (string) $valor)['rotulo'] ?? (string) $valor;
            } elseif ($c['tipo'] === 'referencia') {
                $linha['rotulos'][$c['chave']] = collect($referencias[$c['referencia']] ?? [])->firstWhere('valor', (string) $valor)['rotulo'] ?? '';
            } elseif ($c['tipo'] === 'pais') {
                $linha['rotulos'][$c['chave']] = Geografia::nomeDoPais($valor) ?? (string) $valor;
            }
        }

        foreach (['is_active', 'is_default', 'logo'] as $extra) {
            if (array_key_exists($extra, $m->getAttributes())) {
                $linha[$extra] = $m->{$extra};
            }
        }

        if (isset($linha['logo']) && $linha['logo']) {
            $linha['logo'] = Storage::disk('public')->url($linha['logo']);
        }

        $linha['pode_apagar'] = $def['accoes']['apagar'] && ($def['pode_apagar'])($m);

        return $linha;
    }
}
