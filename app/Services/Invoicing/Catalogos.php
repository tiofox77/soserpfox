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

            /*
             * OS DA TESOURARIA. Têm a mesma forma dos de cima — lista, modal,
             * gravar, apagar — e por isso vivem no mesmo registo em vez de
             * cinco componentes com o mesmo desenho. As permissões são as do
             * módulo `treasury`, que existiam e nenhuma rota aplicava.
             */
            'contas-bancarias' => self::contasBancarias(),
            'bancos' => self::bancos(),
            'formas-de-pagamento' => self::formasDePagamento(),
            'caixas' => self::caixas(),
            'tipos-de-movimento' => self::tiposDeMovimento(),
            'categorias-de-movimento' => self::categoriasDeMovimento(),
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
                self::campo('icon', 'Ícone', 'icone', obrigatorio: true, omissao: 'fa-folder'),
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
                self::campo('icon', 'Ícone', 'icone', obrigatorio: true, omissao: 'fa-tag'),
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

    /* ─── Os catálogos da TESOURARIA ───────────────────────────────────── */

    /**
     * AS CONTAS BANCÁRIAS.
     *
     * Cabem no registo como os outros, com duas diferenças que valem a pena:
     * o SALDO não se escreve — é o `initial_balance` que se põe e o
     * `current_balance` que a tesouraria mantém —, e a conta que aparece nas
     * facturas escolhe-se aqui (`show_on_invoice` e a ordem).
     *
     * O modelo tem `BelongsToTenant`, e esse trait põe escopo GLOBAL: o
     * `findOrFail` do controlador genérico já só vê as contas desta empresa.
     */
    private static function contasBancarias(): array
    {
        $moedas = [
            ['valor' => 'AOA', 'rotulo' => 'AOA — Kwanza'],
            ['valor' => 'USD', 'rotulo' => 'USD — Dólar'],
            ['valor' => 'EUR', 'rotulo' => 'EUR — Euro'],
        ];
        /*
         * DUAS LÍNGUAS NA MESMA COLUNA, e não fui eu que as pus.
         *
         * O ecrã em Livewire gravava `checking`/`savings`/`investment`; o
         * comando `contas:da-empresa` grava `corrente`. Ninguém lê esta
         * coluna para decidir coisa nenhuma — é uma etiqueta — mas uma conta
         * gravada em inglês aparecia com o campo em branco, e ao guardar
         * mudava de valor sem se dizer.
         *
         * As opções ficam em português (é o que se escreve de novo) e o
         * `preparar` traduz as antigas, para convergirem à medida que se
         * editam em vez de ficarem duas para sempre.
         */
        $tipos = [
            ['valor' => 'corrente', 'rotulo' => 'Conta à ordem'],
            ['valor' => 'poupanca', 'rotulo' => 'Poupança'],
            ['valor' => 'prazo', 'rotulo' => 'A prazo'],
            ['valor' => 'outra', 'rotulo' => 'Outra'],
        ];

        $tiposAntigos = ['checking' => 'corrente', 'savings' => 'poupanca', 'investment' => 'prazo'];

        return [
            'modelo' => \App\Models\Treasury\Account::class,
            'titulo' => 'Contas Bancárias',
            'singular' => 'Conta bancária',
            'icone' => 'fa-piggy-bank',
            'cor' => 'ciano',
            'descricao' => 'Onde o dinheiro da empresa está',
            'novo' => 'Nova Conta',
            'rota' => '/treasury/accounts',
            'permissoes' => self::porVerbo('treasury.accounts'),
            'pesquisa' => ['account_name', 'account_number', 'iban'],
            'pesquisa_ajuda' => 'Nome, número ou IBAN',
            'ordem' => [['is_default', 'desc'], ['account_name', 'asc']],
            'colunas' => [
                ['chave' => 'account_name', 'rotulo' => 'Conta', 'formato' => 'texto'],
                ['chave' => 'bank_id', 'rotulo' => 'Banco', 'formato' => 'referencia'],
                ['chave' => 'account_number', 'rotulo' => 'Número', 'formato' => 'texto'],
                ['chave' => 'currency', 'rotulo' => 'Moeda', 'formato' => 'escolha'],
                ['chave' => 'current_balance', 'rotulo' => 'Saldo', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'is_default', 'rotulo' => 'Padrão', 'formato' => 'padrao'],
                ['chave' => 'is_active', 'rotulo' => 'Activa', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'currency', 'rotulo' => 'Moeda', 'opcoes' => $moedas],
            ],
            'campos' => [
                self::campo('account_name', 'Nome da conta', 'texto', obrigatorio: true),
                self::campo('bank_id', 'Banco', 'referencia', obrigatorio: true, referencia: 'bancos'),
                self::campo('account_number', 'Número da conta', 'texto', obrigatorio: true),
                self::campo('iban', 'IBAN', 'texto'),
                self::campo('currency', 'Moeda', 'escolha', obrigatorio: true, omissao: 'AOA', opcoes: $moedas),
                self::campo('account_type', 'Tipo', 'escolha', omissao: 'corrente', opcoes: $tipos),
                // O SALDO INICIAL escreve-se uma vez; o corrente é da tesouraria.
                self::campo('initial_balance', 'Saldo inicial', 'numero', omissao: 0, passo: 0.01, ajuda: 'O saldo corrente é mantido pelos movimentos.'),
                self::campo('manager_name', 'Gestor de conta', 'texto'),
                self::campo('manager_phone', 'Telefone do gestor', 'texto'),
                self::campo('manager_email', 'Email do gestor', 'email'),
                self::campo('show_on_invoice', 'Mostrar nas facturas', 'booleano', omissao: false, ajuda: 'Aparece no rodapé, para o cliente pagar.'),
                self::campo('invoice_display_order', 'Ordem nas facturas', 'numero', omissao: 0, min: 0),
                self::campo('notes', 'Observações', 'textarea', largura: 'inteira'),
                self::campo('is_default', 'Conta padrão', 'booleano', omissao: false),
                self::campo('is_active', 'Activa', 'booleano', omissao: true),
            ],
            'regras' => [
                'account_name' => 'required|max:150',
                'bank_id' => 'required|integer',
                'account_number' => 'required|max:50',
                'iban' => 'nullable|max:50',
                'currency' => 'required|in:AOA,USD,EUR',
                'account_type' => 'nullable|max:30',
                'initial_balance' => 'nullable|numeric',
                'manager_name' => 'nullable|max:120',
                'manager_phone' => 'nullable|max:40',
                'manager_email' => 'nullable|email|max:150',
                'show_on_invoice' => 'boolean',
                'invoice_display_order' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
            ],
            'referencias' => fn (int $t) => [
                'bancos' => \App\Models\Treasury\Bank::where('is_active', true)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($x) => ['valor' => (string) $x->id, 'rotulo' => $x->name])->all(),
            ],
            /*
             * O SALDO CORRENTE nasce do inicial quando a conta é nova. Depois
             * disso é dos movimentos, e mexer-lhe aqui era reescrever a
             * tesouraria por um formulário.
             */
            /*
             * O BANCO TEM DE EXISTIR. A regra era `required|integer`: um id
             * inventado passava a validação e rebentava na chave estrangeira,
             * com o erro de SQL inteiro na cara do utilizador.
             *
             * Aqui não se usa o `daCasa`: a tabela dos bancos é PARTILHADA e
             * não tem `tenant_id` nenhum por onde filtrar.
             */
            'validar' => fn (array $d) => \App\Models\Treasury\Bank::whereKey($d['bank_id'] ?? 0)->exists()
                ? [] : ['bank_id' => __('Banco desconhecido.')],
            'preparar' => function (array $d, ?Model $m) use ($tiposAntigos) {
                if (! $m) {
                    $d['current_balance'] = $d['initial_balance'] ?? 0;
                }

                // As etiquetas em inglês convergem para as de cá.
                $d['account_type'] = $tiposAntigos[$d['account_type'] ?? ''] ?? ($d['account_type'] ?? null);

                return $d;
            },
            'depois' => function (Model $m) {
                if ($m->is_default) {
                    \App\Models\Treasury\Account::where('tenant_id', $m->tenant_id)
                        ->where('id', '!=', $m->id)->update(['is_default' => false]);
                }
            },
            'padrao' => function (Model $m) {
                \App\Models\Treasury\Account::where('tenant_id', $m->tenant_id)
                    ->where('id', '!=', $m->id)->update(['is_default' => false]);
                $m->update(['is_default' => true]);
            },
            /*
             * UMA CONTA COM MOVIMENTOS NÃO SE APAGA — nem uma com saldo.
             *
             * O ecrã de sempre apagava sem perguntar nada: `Account::
             * findOrFail($id)->delete()`. Uma conta que desaparece leva
             * consigo a explicação de para onde foi o dinheiro.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Treasury\Transaction::where('account_id', $m->id)->exists()
                && abs((float) $m->current_balance) < 0.01,
            'porque_nao_apaga' => 'A conta tem movimentos ou saldo.',
            'accoes' => ['activar' => true, 'padrao' => true, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /**
     * OS BANCOS — e são de toda a gente.
     *
     * A tabela `treasury_banks` NÃO TEM `tenant_id`: é a lista nacional dos
     * bancos angolanos, dezasseis, igual para todas as empresas. Daí o
     * `partilhado`.
     *
     * E daí também a permissão de MEXER ser a de apagar, e não a de criar: o
     * ecrã de sempre deixava qualquer empresa apagar um banco que todas usam,
     * com um `Bank::findOrFail($id)` sem escopo nenhum. Uma lista partilhada
     * lê-se com `view` e altera-se com `delete`, que é a permissão mais
     * restrita que o módulo tem.
     */
    private static function bancos(): array
    {
        return [
            'modelo' => \App\Models\Treasury\Bank::class,
            'partilhado' => true,
            'titulo' => 'Bancos',
            'singular' => 'Banco',
            'icone' => 'fa-building-columns',
            'cor' => 'primaria',
            'descricao' => 'A lista de bancos, partilhada por todas as empresas',
            'novo' => 'Novo Banco',
            'rota' => '/treasury/banks',
            /*
             * ESCREVER AQUI MEXE COM TODAS AS EMPRESAS, e por isso pede a
             * permissão mais forte das três. A tabela é partilhada: renomear
             * ou apagar um banco muda-o para toda a gente, não só para quem
             * está a carregar no botão.
             */
            'permissoes' => [
                'ver' => 'treasury.banks.view',
                'criar' => 'treasury.banks.delete',
                'editar' => 'treasury.banks.delete',
                'apagar' => 'treasury.banks.delete',
            ],
            'pesquisa' => ['name', 'code', 'swift_code'],
            'pesquisa_ajuda' => 'Nome, código ou SWIFT',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'swift_code', 'rotulo' => 'SWIFT', 'formato' => 'texto'],
                ['chave' => 'phone', 'rotulo' => 'Telefone', 'formato' => 'texto'],
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
                self::campo('code', 'Código', 'texto', ajuda: 'O código do banco no BNA (ex.: 0006).'),
                self::campo('swift_code', 'SWIFT / BIC', 'texto'),
                self::campo('country', 'País', 'pais', omissao: Geografia::PAIS_PADRAO),
                self::campo('phone', 'Telefone', 'texto'),
                self::campo('website', 'Sítio na internet', 'texto'),
                self::campo('logo_url', 'Logótipo (endereço)', 'texto', largura: 'inteira'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|max:150',
                'code' => 'nullable|max:20',
                'swift_code' => 'nullable|max:20',
                'country' => 'nullable|max:2',
                'phone' => 'nullable|max:40',
                'website' => 'nullable|max:190',
                'logo_url' => 'nullable|max:255',
                'is_active' => 'boolean',
            ],
            /*
             * O CÓDIGO DO BANCO É ÚNICO — e GLOBALMENTE, ao contrário dos
             * outros catálogos da tesouraria.
             *
             * A tabela é partilhada por todas as empresas (não tem
             * `tenant_id`) e o índice único está no `code` sozinho. Repetir
             * um código dava um 1062 cru na cara de quem estivesse a criar —
             * e como a tabela é partilhada, o duplicado podia vir de uma
             * empresa que a pessoa nunca viu.
             */
            'validar' => function (array $d, ?Model $m, int $tenantId) {
                if (empty($d['code'])) {
                    return [];
                }

                $repetido = \App\Models\Treasury\Bank::where('code', $d['code'])
                    ->when($m, fn ($q) => $q->where('id', '!=', $m->id))->exists();

                return $repetido ? ['code' => __('Já existe um banco com esse código.')] : [];
            },
            /*
             * NÃO SE APAGA UM BANCO QUE ALGUÉM USA — e «alguém» aqui é
             * qualquer empresa do sistema, não só a de quem está a carregar.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Treasury\Account::where('bank_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há contas bancárias neste banco.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /** As formas de pagamento da tesouraria — para onde o dinheiro vai. */
    private static function formasDePagamento(): array
    {
        /*
         * OS TIPOS REAIS, os que estão na base.
         *
         * Estavam aqui quatro valores inventados — `bank` e `manual` não
         * existem em registo nenhum, e faltavam `bank_transfer`,
         * `digital_wallet` e `check`, que existem em dezenas. O efeito era
         * pior do que uma lista errada: abrir a «Transferência Bancária» que
         * já lá está e carregar em Guardar dava erro de validação, porque o
         * valor do próprio registo não constava dos aceites.
         *
         * `cash` é o único com significado no código — é ele que manda o
         * dinheiro para o caixa em vez da conta (ver `TreasuryMovementService`).
         */
        $tipos = [
            ['valor' => 'cash', 'rotulo' => 'Numerário'],
            ['valor' => 'card', 'rotulo' => 'Cartão / TPA'],
            ['valor' => 'bank_transfer', 'rotulo' => 'Transferência bancária'],
            ['valor' => 'digital_wallet', 'rotulo' => 'Carteira digital'],
            ['valor' => 'check', 'rotulo' => 'Cheque'],
            ['valor' => 'other', 'rotulo' => 'Outra'],
        ];

        return [
            'modelo' => \App\Models\Treasury\PaymentMethod::class,
            'titulo' => 'Formas de Pagamento',
            'singular' => 'Forma de pagamento',
            'icone' => 'fa-credit-card',
            'cor' => 'roxo',
            'descricao' => 'Como o dinheiro entra e sai, e para onde vai',
            'novo' => 'Nova Forma',
            'rota' => '/treasury/payment-methods',
            'permissoes' => self::porVerbo('treasury.payment-methods'),
            'pesquisa' => ['name', 'code'],
            'pesquisa_ajuda' => 'Nome ou código',
            'ordem' => [['sort_order', 'asc'], ['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'fee_percentage', 'rotulo' => 'Comissão', 'formato' => 'percentagem', 'alinhar' => 'direita'],
                ['chave' => 'is_active', 'rotulo' => 'Activa', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'type', 'rotulo' => 'Tipo', 'opcoes' => $tipos],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true, ajuda: 'É por ele que o POS e os recibos a identificam.'),
                self::campo('type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'cash', opcoes: $tipos),
                self::campo('description', 'Descrição', 'texto'),
                self::campo('icon', 'Ícone', 'icone', omissao: 'fa-money-bill'),
                self::campo('color', 'Cor', 'texto', omissao: 'green'),
                self::campo('fee_percentage', 'Comissão (%)', 'numero', omissao: 0, passo: 0.01, min: 0, max: 100),
                self::campo('fee_fixed', 'Comissão fixa', 'numero', omissao: 0, passo: 0.01, min: 0),
                self::campo('requires_account', 'Exige conta bancária', 'booleano', omissao: false),
                self::campo('default_account_id', 'Conta por omissão', 'referencia', referencia: 'contas'),
                self::campo('default_cash_register_id', 'Caixa por omissão', 'referencia', referencia: 'caixas'),
                self::campo('is_active', 'Activa', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|max:100',
                'code' => 'required|max:30',
                'type' => 'required|in:cash,card,bank_transfer,digital_wallet,check,other',
                'description' => 'nullable|max:255',
                'icon' => 'nullable|max:60',
                'color' => 'nullable|max:30',
                'fee_percentage' => 'nullable|numeric|min:0|max:100',
                'fee_fixed' => 'nullable|numeric|min:0',
                'requires_account' => 'boolean',
                'default_account_id' => 'nullable|integer',
                'default_cash_register_id' => 'nullable|integer',
                'is_active' => 'boolean',
            ],
            /*
             * O DESTINO TEM DE SER DESTA CASA.
             *
             * `nullable|integer` aceitava o id da conta bancária de outra
             * empresa: a forma de pagamento ficava a apontar para fora, sem
             * erro nenhum. O dinheiro não ia lá parar — o serviço volta a
             * filtrar pela empresa — mas a configuração ficava a mentir, e
             * quem a lesse não perceberia porque é que o saldo não mexia.
             */
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Treasury\PaymentMethod::class, 'Já existe uma forma de pagamento com esse código.'),
                self::daCasa('default_account_id', \App\Models\Treasury\Account::class, 'Essa conta bancária não é desta empresa.'),
                self::daCasa('default_cash_register_id', \App\Models\Treasury\CashRegister::class, 'Esse caixa não é desta empresa.'),
            ]),
            'referencias' => fn (int $t) => [
                // A conta bancária chama-se `account_name` e não `name`: a
                // coluna «name» aqui não existe, e a consulta rebentava.
                'contas' => \App\Models\Treasury\Account::where('tenant_id', $t)->where('is_active', true)
                    ->orderBy('account_name')->get(['id', 'account_name'])
                    ->map(fn ($x) => ['valor' => $x->id, 'rotulo' => $x->account_name])->all(),
                'caixas' => \App\Models\Treasury\CashRegister::where('tenant_id', $t)->where('is_active', true)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($x) => ['valor' => $x->id, 'rotulo' => $x->name])->all(),
            ],
            /*
             * UMA FORMA JÁ USADA NÃO SE APAGA. Um recibo que aponte para ela
             * ficava a dizer que foi pago por uma forma que não existe — e a
             * tesouraria não saberia de onde veio o dinheiro.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Treasury\Transaction::where('payment_method_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há movimentos de tesouraria com esta forma.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /**
     * AS CAIXAS. Uma caixa ABERTA não se apaga: tem dinheiro contado lá dentro
     * e um turno por fechar.
     */
    private static function caixas(): array
    {
        return [
            'modelo' => \App\Models\Treasury\CashRegister::class,
            'titulo' => 'Caixas',
            'singular' => 'Caixa',
            'icone' => 'fa-cash-register',
            'cor' => 'bom',
            'descricao' => 'As caixas do balcão e quem as opera',
            'novo' => 'Nova Caixa',
            'rota' => '/treasury/cash-registers',
            'permissoes' => self::porVerbo('treasury.cash-registers'),
            'pesquisa' => ['name', 'code'],
            'pesquisa_ajuda' => 'Nome ou código',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'user_id', 'rotulo' => 'Operador', 'formato' => 'escolha'],
                ['chave' => 'current_balance', 'rotulo' => 'Saldo', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'status', 'rotulo' => 'Estado', 'formato' => 'escolha'],
                ['chave' => 'is_active', 'rotulo' => 'Activa', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'status', 'rotulo' => 'Estado', 'opcoes' => [
                    ['valor' => 'open', 'rotulo' => 'Aberta'],
                    ['valor' => 'closed', 'rotulo' => 'Fechada'],
                ]],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('user_id', 'Operador', 'referencia', referencia: 'utilizadores', ajuda: 'Quem responde por esta caixa.'),
                self::campo('opening_balance', 'Fundo de maneio', 'numero', omissao: 0, passo: 0.01, min: 0),
                self::campo('opening_notes', 'Observações de abertura', 'textarea', largura: 'inteira'),
                self::campo('is_active', 'Activa', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|max:100',
                'code' => 'required|max:30',
                'user_id' => 'nullable|integer',
                'opening_balance' => 'nullable|numeric|min:0',
                'opening_notes' => 'nullable|string',
                'is_active' => 'boolean',
            ],
            'referencias' => fn (int $t) => [
                /*
                 * OS UTILIZADORES VÊM DO PIVÔ, e não de `users.tenant_id`.
                 *
                 * Medido em produção: uma empresa com nove pessoas no pivô
                 * mostrava oito — e a que faltava não podia ser responsável
                 * de caixa nenhum. Quem é acrescentado pela gestão de
                 * utilizadores entra SÓ pelo pivô.
                 */
                'utilizadores' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $t))
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($x) => ['valor' => $x->id, 'rotulo' => $x->name])->all(),
            ],
            /*
             * O OPERADOR TEM DE SER DESTA EMPRESA.
             *
             * A regra `nullable|integer` aceitava qualquer utilizador do
             * sistema: bastava trocar o valor no pedido para pôr uma pessoa
             * de outra empresa como responsável de um caixa que não é dela.
             * O ecrã em Livewire prendia-a ao pivô e a regra perdeu-se ao
             * passar para aqui.
             */
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Treasury\CashRegister::class, 'Já existe uma caixa com esse código.'),
                function (array $d, ?Model $m, int $tenantId) {
                    if (empty($d['user_id'])) {
                        return [];
                    }

                    $daCasa = User::whereKey($d['user_id'])
                        ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))->exists();

                    return $daCasa ? [] : ['user_id' => __('Esse utilizador não pertence a esta empresa.')];
                },
            ]),
            'preparar' => fn (array $d) => array_merge($d, ['user_id' => ($d['user_id'] ?? null) ?: null]),
            // Uma caixa ABERTA tem dinheiro contado e um turno por fechar.
            'pode_apagar' => fn (Model $m) => $m->status !== 'open',
            'porque_nao_apaga' => 'A caixa está aberta. Feche o turno primeiro.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /** Os TIPOS de movimento: o que é entrada e o que é saída. */
    private static function tiposDeMovimento(): array
    {
        /*
         * AS TRÊS NATUREZAS, as da coluna.
         *
         * `transfer` faltava aqui e está no `enum` da base e em registos a
         * sério — o que fazia com que abrir um tipo de transferência e
         * carregar em Guardar desse erro de validação sobre o valor que o
         * próprio registo já tinha.
         */
        $naturezas = [
            ['valor' => 'income', 'rotulo' => 'Entrada'],
            ['valor' => 'expense', 'rotulo' => 'Saída'],
            ['valor' => 'transfer', 'rotulo' => 'Transferência'],
        ];

        return [
            'modelo' => \App\Models\Treasury\TransactionType::class,
            'titulo' => 'Tipos de Movimento',
            'singular' => 'Tipo de movimento',
            'icone' => 'fa-right-left',
            'cor' => 'ciano',
            'descricao' => 'O que conta como entrada e o que conta como saída',
            'novo' => 'Novo Tipo',
            'rota' => '/treasury/transaction-types',
            /*
             * A EMPRESA NOVA NÃO FICA COM A LISTA VAZIA.
             *
             * O ecrã em Livewire semeava os tipos e as categorias de omissão
             * ao abrir, e a semeadura perdeu-se ao passar para o ecrã
             * genérico: quem entrasse aqui pela primeira vez via uma lista
             * sem nada e sem por onde começar — e os movimentos ficavam por
             * classificar. Semeia os DOIS, porque as categorias dependem dos
             * tipos e este catálogo pode ser o primeiro a abrir.
             */
            'antes' => fn (int $t) => \App\Models\Treasury\TransactionCategory::seedDefaultsForTenant($t),
            'permissoes' => self::porVerbo('treasury.transactions'),
            'pesquisa' => ['name', 'code'],
            'pesquisa_ajuda' => 'Nome ou código',
            'ordem' => [['nature', 'asc'], ['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'nature', 'rotulo' => 'Natureza', 'formato' => 'escolha'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'nature', 'rotulo' => 'Natureza', 'opcoes' => $naturezas],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('nature', 'Natureza', 'escolha', obrigatorio: true, omissao: 'income', opcoes: $naturezas),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
                self::campo('color', 'Cor', 'texto', omissao: 'blue'),
                self::campo('icon', 'Ícone', 'icone', omissao: 'fa-right-left'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|max:100',
                'code' => 'required|max:30',
                'nature' => 'required|in:income,expense,transfer',
                'description' => 'nullable|string',
                'color' => 'nullable|max:30',
                'icon' => 'nullable|max:60',
                'is_active' => 'boolean',
            ],
            'validar' => self::codigoUnico(\App\Models\Treasury\TransactionType::class, 'Já existe um tipo de movimento com esse código.'),
            /*
             * Um tipo com CATEGORIAS por baixo, ou já usado num MOVIMENTO, não
             * desaparece: o histórico deixaria de saber o que aquilo era.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Treasury\TransactionCategory::where('transaction_type_id', $m->id)->exists()
                && ! \App\Models\Treasury\Transaction::where('transaction_type_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há categorias ou movimentos deste tipo.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /** As CATEGORIAS, que penduram de um tipo. */
    private static function categoriasDeMovimento(): array
    {
        return [
            'modelo' => \App\Models\Treasury\TransactionCategory::class,
            'titulo' => 'Categorias de Movimento',
            'singular' => 'Categoria de movimento',
            'icone' => 'fa-tags',
            'cor' => 'rosa',
            'descricao' => 'O detalhe dentro de cada tipo de movimento',
            'novo' => 'Nova Categoria',
            'rota' => '/treasury/transaction-categories',
            /*
             * A EMPRESA NOVA NÃO FICA COM A LISTA VAZIA.
             *
             * O ecrã em Livewire semeava os tipos e as categorias de omissão
             * ao abrir, e a semeadura perdeu-se ao passar para o ecrã
             * genérico: quem entrasse aqui pela primeira vez via uma lista
             * sem nada e sem por onde começar — e os movimentos ficavam por
             * classificar. Semeia os DOIS, porque as categorias dependem dos
             * tipos e este catálogo pode ser o primeiro a abrir.
             */
            'antes' => fn (int $t) => \App\Models\Treasury\TransactionCategory::seedDefaultsForTenant($t),
            'permissoes' => self::porVerbo('treasury.transactions'),
            'pesquisa' => ['name', 'code'],
            'pesquisa_ajuda' => 'Nome ou código',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'transaction_type_id', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'is_active', 'rotulo' => 'Activa', 'formato' => 'booleano'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('transaction_type_id', 'Tipo de movimento', 'referencia', referencia: 'tipos', ajuda: 'Em branco, serve a qualquer tipo.'),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
                self::campo('is_active', 'Activa', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|max:100',
                'code' => 'required|max:30',
                /*
                 * O TIPO É OPCIONAL — e tinha ficado obrigatório.
                 *
                 * Onze das categorias de omissão nascem SEM tipo, de
                 * propósito: são as que servem a qualquer um, e o ecrã dos
                 * movimentos lê o nulo como «serve sempre». Exigi-lo aqui
                 * tornava essas onze impossíveis de editar — o formulário
                 * pedia um valor que o próprio registo não tem.
                 */
                'transaction_type_id' => 'nullable|integer',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ],
            'referencias' => fn (int $t) => [
                'tipos' => \App\Models\Treasury\TransactionType::where('tenant_id', $t)->where('is_active', true)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($x) => ['valor' => $x->id, 'rotulo' => $x->name])->all(),
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Treasury\TransactionCategory::class, 'Já existe uma categoria com esse código.'),
                self::daCasa('transaction_type_id', \App\Models\Treasury\TransactionType::class, 'Esse tipo de movimento não é desta empresa.'),
            ]),
            'preparar' => fn (array $d) => array_merge($d, [
                'transaction_type_id' => ($d['transaction_type_id'] ?? null) ?: null,
            ]),
            'pode_apagar' => fn (Model $m) => ! \App\Models\Treasury\Transaction::where('transaction_category_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há movimentos nesta categoria.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function porVerbo(string $prefixo): array
    {
        return ['ver' => "$prefixo.view", 'criar' => "$prefixo.create", 'editar' => "$prefixo.edit", 'apagar' => "$prefixo.delete"];
    }

    /**
     * O CÓDIGO É ÚNICO POR EMPRESA — e diz-se no campo, não em SQL.
     *
     * Os catálogos da tesouraria têm todos um índice único `(tenant_id, code)`
     * na base e nenhum o declarava: repetir um código dava um 1062 cru na cara
     * do utilizador, com o SQL inteiro lá dentro, em vez de «Já existe uma
     * forma de pagamento com esse código».
     *
     * Vive aqui e não numa regra `Rule::unique(...)` porque a regra precisa de
     * IGNORAR o próprio registo ao editar, e o esquema não sabe qual é — o
     * `validar` recebe-o.
     *
     * @param  class-string<Model>  $modelo
     * @return callable(array, ?Model, int): array
     */
    private static function codigoUnico(string $modelo, string $frase, string $coluna = 'code'): callable
    {
        return function (array $d, ?Model $m, int $tenantId) use ($modelo, $frase, $coluna) {
            if (($d[$coluna] ?? '') === '') {
                return [];
            }

            $repetido = $modelo::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where($coluna, $d[$coluna])
                ->when($m, fn ($q) => $q->where('id', '!=', $m->id))
                ->exists();

            return $repetido ? [$coluna => __($frase)] : [];
        };
    }

    /**
     * Junta várias verificações numa só, para o `validar` que só aceita uma.
     *
     * @param  array<int, callable(array, ?Model, int): array>  $verificacoes
     * @return callable(array, ?Model, int): array
     */
    private static function tudoIsto(array $verificacoes): callable
    {
        return function (array $d, ?Model $m, int $tenantId) use ($verificacoes) {
            foreach ($verificacoes as $verificar) {
                // A PRIMEIRA QUE FALHA MANDA. Devolver as duas de uma vez
                // obrigaria a juntar mensagens de campos diferentes, e o
                // formulário só sabe mostrar uma por campo.
                if ($erros = $verificar($d, $m, $tenantId)) {
                    return $erros;
                }
            }

            return [];
        };
    }

    /**
     * Um `id` que tem de apontar para algo DESTA empresa.
     *
     * Sem isto, `nullable|integer` aceitava o id de uma conta bancária ou de
     * um utilizador de outra empresa — o registo ficava a apontar para fora
     * de casa, e nada dava erro.
     *
     * @param  class-string<Model>  $modelo
     * @return callable(array, ?Model, int): array
     */
    private static function daCasa(string $campo, string $modelo, string $frase): callable
    {
        return function (array $d, ?Model $m, int $tenantId) use ($campo, $modelo, $frase) {
            if (empty($d[$campo])) {
                return [];
            }

            $existe = $modelo::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->whereKey($d[$campo])->exists();

            return $existe ? [] : [$campo => __($frase)];
        };
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
        /*
         * O CATÁLOGO PARTILHADO NÃO SE ESCOPA — porque não tem por onde.
         *
         * Os bancos angolanos são uma lista nacional: dezasseis, iguais para
         * toda a gente, e a tabela nem sequer tem `tenant_id`. Filtrar por uma
         * coluna que não existe deitava a consulta abaixo.
         *
         * Quem os pode MUDAR é outra conversa, e resolve-se nas permissões:
         * ver a nota em `bancos()`.
         */
        $q = empty($def['partilhado'])
            ? $def['modelo']::query()->where('tenant_id', $tenantId)
            : $def['modelo']::query();

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
