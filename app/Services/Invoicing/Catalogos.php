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

            /*
             * OS DOS RECURSOS HUMANOS. Mesma forma, mesmo ecrã — e permissões
             * NOVAS, porque o módulo inteiro não tinha nenhuma aplicada.
             */
            'departamentos' => self::departamentos(),
            'cargos' => self::cargos(),
            'turnos' => self::turnos(),

            /*
             * OS DA OFICINA. Mecânicos, viaturas e serviços eram três
             * componentes Livewire com a forma de sempre — lista, modal,
             * gravar, apagar — e nenhum deles tinha guarda de empresa nos
             * `find()`. Aqui têm a do escopo global mais a das permissões.
             */
            'mecanicos' => self::mecanicos(),
            'viaturas' => self::viaturas(),
            'estados-de-viatura' => self::estadosDeViatura(),
            'modelos-de-inspeccao' => self::modelosDeInspeccao(),
            'lugares-da-oficina' => self::lugaresDaOficina(),
            'servicos' => self::servicos(),

            /*
             * OS DO HOTEL. Os tipos de quarto, os quartos, os hóspedes e o
             * pessoal — quatro listas com a forma de sempre. Os hóspedes são
             * CLIENTES DA FACTURAÇÃO com duas bandeiras próprias: é a tabela
             * que as reservas usam, e é a que a factura precisa.
             */
            'tipos-de-quarto' => self::tiposDeQuarto(),
            'quartos' => self::quartos(),
            'hospedes' => self::hospedes(),
            'pessoal-do-hotel' => self::pessoalDoHotel(),

            /*
             * OS PACOTES E OS CÓDIGOS PROMOCIONAIS vivem na mesma entrada do
             * menu, em duas abas — eram duas listas dentro do mesmo componente
             * Livewire. Aqui são dois catálogos, e o ecrã põe-lhes as abas.
             */
            'pacotes' => self::pacotes(),
            'codigos-promocionais' => self::codigosPromocionais(),
            /* E as ÉPOCAS, que são a primeira aba das tarifas. */
            'epocas-do-hotel' => self::epocasDoHotel(),

            /*
             * OS DA CONTABILIDADE. Três listas com a forma de sempre — e cada
             * uma com uma guarda que o Livewire não tinha: os `find()` dos
             * diários e das dimensões não olhavam à empresa, apagava-se um
             * diário com lançamentos em cima, e o código repetia-se à vontade.
             */
            'diarios' => self::diarios(),
            'tipos-de-documento' => self::tiposDeDocumento(),
            'centros-de-custo' => self::centrosDeCusto(),
            'familias-do-imobilizado' => self::familiasDoImobilizado(),
        ];
    }

    /** As especialidades que a oficina reconhece — as do ecrã de sempre. */
    public const ESPECIALIDADES = [
        'Mecânica Geral', 'Motor', 'Suspensão', 'Elétrica', 'Pintura', 'Chapa',
    ];

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

    /* ─── Os catálogos dos RECURSOS HUMANOS ────────────────────────────── */

    /*
     * Departamentos, cargos e turnos são o mesmo que os de cima: lista com
     * procura, formulário num modal, gravar, apagar. Em Livewire eram dois
     * componentes (o dos departamentos fazia também os cargos, com dois
     * conjuntos de métodos iguais) e 480 linhas.
     *
     * AS PERMISSÕES SÃO NOVAS, e é de propósito: nenhuma das 26 rotas do RH
     * tinha guarda nenhuma — qualquer utilizador de uma empresa com o módulo
     * activo abria a folha de pagamento. Cada catálogo que passa para aqui
     * ganha a sua, e a rota passa a exigi-la.
     */

    private static function departamentos(): array
    {
        return [
            'modelo' => \App\Models\HR\Department::class,
            'titulo' => 'Departamentos',
            'singular' => 'Departamento',
            'icone' => 'fa-building',
            'cor' => 'roxo',
            'descricao' => 'Gerir os departamentos da empresa',
            'novo' => 'Novo Departamento',
            'rota' => '/hr/departments',
            'permissoes' => self::porVerbo('hr.departments'),
            'pesquisa' => ['name', 'code', 'description'],
            'pesquisa_ajuda' => 'Nome, código ou descrição',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'manager_id', 'rotulo' => 'Responsável', 'formato' => 'escolha'],
                ['chave' => 'description', 'rotulo' => 'Descrição', 'formato' => 'texto'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true, ajuda: 'Curto e único nesta empresa — RH, FIN, OPS.'),
                self::campo('manager_id', 'Responsável', 'referencia', referencia: 'utilizadores'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'code' => 'required|string|max:50',
                'manager_id' => 'nullable|integer',
                'description' => 'nullable|string|max:2000',
                'is_active' => 'boolean',
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\HR\Department::class, 'Já existe um departamento com esse código.'),
                /* O `daCasa` não serve aqui: a tabela `users` não tem
                   `tenant_id` — a pertença é pela tabela do meio. */
                function (array $d, ?Model $m, int $tenantId): array {
                    if (empty($d['manager_id'])) {
                        return [];
                    }

                    $daCasa = User::whereKey($d['manager_id'])
                        ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))->exists();

                    return $daCasa ? [] : ['manager_id' => __('Esse utilizador não pertence a esta empresa.')];
                },
            ]),
            'preparar' => fn (array $d) => array_merge($d, [
                'code' => trim((string) ($d['code'] ?? '')),
                'manager_id' => ($d['manager_id'] ?? null) ?: null,
            ]),
            'referencias' => fn (int $t) => [
                'utilizadores' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $t))
                    ->orderBy('name')->get()->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->all(),
            ],
            /*
             * Um departamento com gente dentro não se apaga — nem com cargos
             * pendurados nele. Apagá-lo deixava funcionários a apontar para um
             * departamento que já não existe, e a folha a agrupar por nada.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\HR\Employee::where('department_id', $m->id)->exists()
                && ! \App\Models\HR\Position::where('department_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há funcionários ou cargos neste departamento.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function cargos(): array
    {
        return [
            'modelo' => \App\Models\HR\Position::class,
            'titulo' => 'Cargos',
            'singular' => 'Cargo',
            'icone' => 'fa-user-tie',
            'cor' => 'ciano',
            'descricao' => 'Gerir os cargos e as bandas salariais',
            'novo' => 'Novo Cargo',
            'rota' => '/hr/positions',
            'permissoes' => self::porVerbo('hr.positions'),
            'pesquisa' => ['title', 'code', 'description'],
            'pesquisa_ajuda' => 'Título, código ou descrição',
            'ordem' => [['title', 'asc']],
            'colunas' => [
                ['chave' => 'title', 'rotulo' => 'Cargo', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'department_id', 'rotulo' => 'Departamento', 'formato' => 'escolha'],
                ['chave' => 'min_salary', 'rotulo' => 'Salário mínimo', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'max_salary', 'rotulo' => 'Salário máximo', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('title', 'Cargo', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('department_id', 'Departamento', 'referencia', referencia: 'departamentos'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('min_salary', 'Salário mínimo (Kz)', 'numero', passo: 0.01, min: 0),
                self::campo('max_salary', 'Salário máximo (Kz)', 'numero', passo: 0.01, min: 0),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'title' => 'required|string|min:2|max:255',
                'code' => 'required|string|max:50',
                'department_id' => 'nullable|integer',
                'min_salary' => 'nullable|numeric|min:0',
                'max_salary' => 'nullable|numeric|min:0',
                'description' => 'nullable|string|max:2000',
                'is_active' => 'boolean',
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\HR\Position::class, 'Já existe um cargo com esse código.'),
                self::daCasa('department_id', \App\Models\HR\Department::class, 'Esse departamento não é desta empresa.'),
                /*
                 * A BANDA SALARIAL TEM DE FAZER SENTIDO. Um mínimo acima do
                 * máximo passava calado e depois nada o usava para nada — o
                 * cargo ficava com uma banda impossível na ficha.
                 */
                function (array $d): array {
                    $min = $d['min_salary'] ?? null;
                    $max = $d['max_salary'] ?? null;

                    return ($min !== null && $max !== null && $min !== '' && $max !== '' && (float) $min > (float) $max)
                        ? ['max_salary' => __('O máximo não pode ser menor do que o mínimo.')]
                        : [];
                },
            ]),
            'preparar' => fn (array $d) => array_merge($d, [
                'code' => trim((string) ($d['code'] ?? '')),
                'department_id' => ($d['department_id'] ?? null) ?: null,
                'min_salary' => ($d['min_salary'] ?? '') === '' ? null : (float) $d['min_salary'],
                'max_salary' => ($d['max_salary'] ?? '') === '' ? null : (float) $d['max_salary'],
            ]),
            'referencias' => fn (int $t) => [
                'departamentos' => \App\Models\HR\Department::withoutGlobalScopes()
                    ->where('tenant_id', $t)->orderBy('name')
                    ->get()->map(fn ($d) => ['valor' => (string) $d->id, 'rotulo' => $d->name])->all(),
            ],
            'pode_apagar' => fn (Model $m) => ! \App\Models\HR\Employee::where('position_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há funcionários com este cargo.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function turnos(): array
    {
        return [
            'modelo' => \App\Models\HR\Shift::class,
            'titulo' => 'Turnos',
            'singular' => 'Turno',
            'icone' => 'fa-clock',
            'cor' => 'primaria',
            'descricao' => 'Horários de trabalho e dias de cada turno',
            'novo' => 'Novo Turno',
            'rota' => '/hr/shifts',
            'permissoes' => self::porVerbo('hr.shifts'),
            'pesquisa' => ['name', 'code', 'description'],
            'pesquisa_ajuda' => 'Nome, código ou descrição',
            'ordem' => [['display_order', 'asc'], ['start_time', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Turno', 'formato' => 'texto'],
                ['chave' => 'start_time', 'rotulo' => 'Entrada', 'formato' => 'hora'],
                ['chave' => 'end_time', 'rotulo' => 'Saída', 'formato' => 'hora'],
                ['chave' => 'hours_per_day', 'rotulo' => 'Horas/dia', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'work_days', 'rotulo' => 'Dias', 'formato' => 'dias'],
                ['chave' => 'color', 'rotulo' => 'Cor', 'formato' => 'cor'],
                ['chave' => 'is_night_shift', 'rotulo' => 'Nocturno', 'formato' => 'booleano'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto'),
                self::campo('start_time', 'Entrada', 'hora', obrigatorio: true, omissao: '08:00'),
                self::campo('end_time', 'Saída', 'hora', obrigatorio: true, omissao: '17:00'),
                self::campo('hours_per_day', 'Horas por dia', 'numero', obrigatorio: true, omissao: 8, passo: 0.25, min: 0, max: 24),
                self::campo('work_days', 'Dias de trabalho', 'dias', omissao: [1, 2, 3, 4, 5], largura: 'inteira',
                    ajuda: 'Os dias em que este turno é cumprido.'),
                self::campo('color', 'Cor', 'cor', omissao: '#3b82f6',
                    ajuda: 'É por ela que o turno se distingue no calendário de presenças.'),
                self::campo('display_order', 'Ordem', 'numero', omissao: 0),
                /*
                 * NOCTURNO NÃO É UMA ETIQUETA: é o que faz a lei angolana
                 * acrescentar 25% às horas cumpridas entre as 22:00 e as
                 * 06:00. Fica escrito no campo, porque quem cria um turno de
                 * madrugada tem de saber que isto muda o que se paga.
                 */
                self::campo('is_night_shift', 'Turno nocturno (acréscimo de 25%)', 'booleano', omissao: false),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'code' => 'nullable|string|max:50',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'hours_per_day' => 'required|numeric|min:0|max:24',
                'work_days' => 'nullable|array',
                'work_days.*' => 'integer|min:1|max:7',
                'color' => 'nullable|string|max:7',
                'display_order' => 'nullable|integer|min:0',
                'is_night_shift' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:2000',
            ],
            'validar' => self::codigoUnico(\App\Models\HR\Shift::class, 'Já existe um turno com esse código.'),
            'preparar' => fn (array $d) => array_merge($d, [
                'code' => ($d['code'] ?? '') === '' ? null : trim((string) $d['code']),
                // Um turno sem dia nenhum não é um turno: sem escolha, a
                // semana de trabalho de segunda a sexta.
                'work_days' => array_values(array_unique(array_map('intval', $d['work_days'] ?? []))) ?: [1, 2, 3, 4, 5],
                'display_order' => (int) ($d['display_order'] ?? 0),
                'hours_per_day' => (float) ($d['hours_per_day'] ?? 8),
            ]),
            /*
             * Um turno com gente ou com presenças marcadas não se apaga: as
             * presenças passadas ficariam a apontar para um horário que já não
             * existe, e o cálculo do atraso deixava de ter contra o que medir.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\HR\Employee::where('shift_id', $m->id)->exists()
                && ! \App\Models\HR\Attendance::where('shift_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há funcionários ou presenças neste turno.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true, 'atribuir' => true],

            /*
             * ATRIBUIR EM LOTE — o que o ecrã em Livewire tinha e não se
             * podia perder.
             *
             * Pôr trinta pessoas no turno da manhã um a um, pela ficha de cada
             * uma, é meia hora de trabalho. O modal escolhe-as todas de uma vez.
             *
             * A DESCRIÇÃO fica aqui e não no ecrã: é o esquema que sabe quem se
             * atribui a quê. Qualquer catálogo que declare isto ganha o mesmo
             * modal — é a mesma ideia de todo este registo.
             */
            'atribuir' => [
                'modelo' => \App\Models\HR\Employee::class,
                'coluna' => 'shift_id',
                'titulo' => 'Atribuir funcionários ao turno',
                'nada' => 'Ainda não há funcionários activos nesta empresa.',
                'pesquisa' => ['first_name', 'last_name', 'employee_number'],
                'pesquisa_ajuda' => 'Nome ou número',
                // Só quem está ao serviço: um funcionário cessado não entra
                // em turno nenhum.
                'onde' => fn ($q) => $q->where('status', 'active'),
                'nome' => fn (Model $e) => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: ($e->full_name ?? '—'),
                'nota' => fn (Model $e) => $e->employee_number,
            ],
        ];
    }

    /* ─── A oficina ────────────────────────────────────────────────────── */

    private static function mecanicos(): array
    {
        return [
            'modelo' => \App\Models\Workshop\Mechanic::class,
            'titulo' => 'Mecânicos',
            'singular' => 'Mecânico',
            'icone' => 'fa-user-gear',
            'cor' => 'laranja',
            'descricao' => 'A equipa da oficina, as especialidades e o preço da mão-de-obra',
            'novo' => 'Novo Mecânico',
            'rota' => '/workshop/mechanics',
            'permissoes' => self::porVerbo('workshop.mechanics'),
            'pesquisa' => ['name', 'email', 'phone', 'document'],
            'pesquisa_ajuda' => 'Nome, email, telefone ou documento',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'specialties', 'rotulo' => 'Especialidades', 'formato' => 'multi'],
                ['chave' => 'level', 'rotulo' => 'Nível', 'formato' => 'escolha'],
                ['chave' => 'phone', 'rotulo' => 'Telefone', 'formato' => 'texto'],
                ['chave' => 'hourly_rate', 'rotulo' => 'Preço/hora', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'daily_rate', 'rotulo' => 'Preço/dia', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'is_available', 'rotulo' => 'Disponível', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'level', 'rotulo' => 'Nível', 'opcoes' => self::NIVEIS_DE_MECANICO],
                ['chave' => 'is_available', 'rotulo' => 'Disponibilidade', 'opcoes' => [
                    ['valor' => '1', 'rotulo' => 'Disponível'],
                    ['valor' => '0', 'rotulo' => 'Ocupado'],
                ]],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('phone', 'Telefone', 'texto', obrigatorio: true),
                self::campo('email', 'Email', 'email'),
                self::campo('mobile', 'Telemóvel', 'texto'),
                self::campo('document', 'Documento (BI/NIF)', 'texto'),
                self::campo('level', 'Nível', 'escolha', obrigatorio: true, omissao: 'pleno', opcoes: self::NIVEIS_DE_MECANICO),
                /*
                 * AS ESPECIALIDADES SÃO O QUE FAZ A LISTA SERVIR PARA ALGUMA
                 * COISA: quem tem um carro com o motor aberto procura quem
                 * mexe em motores, e não a lista toda por ordem alfabética.
                 * Por isso é obrigatório escolher pelo menos uma — era a regra
                 * do ecrã de sempre.
                 */
                self::campo('specialties', 'Especialidades', 'multi', obrigatorio: true, omissao: [],
                    opcoes: array_map(fn ($e) => ['valor' => $e, 'rotulo' => __($e)], self::ESPECIALIDADES),
                    largura: 'inteira', ajuda: 'Pelo menos uma.'),
                self::campo('hourly_rate', 'Preço por hora (Kz)', 'numero', omissao: 0, passo: 0.01, min: 0),
                self::campo('daily_rate', 'Preço por dia (Kz)', 'numero', omissao: 0, passo: 0.01, min: 0),
                self::campo('birth_date', 'Data de nascimento', 'data'),
                self::campo('hire_date', 'Data de admissão', 'data'),
                /*
                 * DISPONÍVEL não é o mesmo que ACTIVO, e o ecrã de sempre
                 * mostrava os dois crachás lado a lado: activo é «trabalha
                 * cá», disponível é «pode pegar numa ordem agora».
                 */
                self::campo('is_available', 'Disponível para trabalho', 'booleano', omissao: true),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('address', 'Morada', 'textarea', largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'mobile' => 'nullable|string|max:20',
                'document' => 'nullable|string|max:50',
                'level' => 'required|in:junior,pleno,senior,master',
                'specialties' => 'required|array|min:1',
                'specialties.*' => 'string|max:100',
                'hourly_rate' => 'nullable|numeric|min:0',
                'daily_rate' => 'nullable|numeric|min:0',
                'birth_date' => 'nullable|date',
                'hire_date' => 'nullable|date',
                'is_available' => 'boolean',
                'is_active' => 'boolean',
                'address' => 'nullable|string|max:2000',
                'notes' => 'nullable|string|max:2000',
            ],
            'preparar' => fn (array $d) => array_merge($d, [
                // Só as que a oficina reconhece: uma especialidade inventada no
                // pedido entrava na lista e ficava lá para sempre.
                'specialties' => array_values(array_intersect(
                    array_map('strval', $d['specialties'] ?? []), self::ESPECIALIDADES,
                )),
                'hourly_rate' => ($d['hourly_rate'] ?? '') === '' ? 0 : (float) $d['hourly_rate'],
                'daily_rate' => ($d['daily_rate'] ?? '') === '' ? 0 : (float) $d['daily_rate'],
                'birth_date' => ($d['birth_date'] ?? '') ?: null,
                'hire_date' => ($d['hire_date'] ?? '') ?: null,
            ]),
            'validar' => function (array $d): array {
                // O `preparar` corre DEPOIS desta verificação: uma lista só com
                // nomes inventados esvaziava-se em silêncio e gravava um
                // mecânico sem especialidade nenhuma.
                $boas = array_intersect(array_map('strval', $d['specialties'] ?? []), self::ESPECIALIDADES);

                return $boas ? [] : ['specialties' => __('Escolha pelo menos uma especialidade.')];
            },
            /*
             * UM MECÂNICO COM ORDENS NÃO SE APAGA — guarda que o ecrã em
             * Livewire não tinha. Apagá-lo deixava as ordens de serviço a
             * apontar para quem já não existe, e o relatório de produtividade
             * a somar horas a ninguém.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Workshop\WorkOrder::where('mechanic_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há ordens de serviço deste mecânico.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true, 'importar' => true],
            /*
             * IMPORTAR DE RH — o botão que o ecrã de sempre tinha.
             *
             * A oficina não contrata duas vezes a mesma pessoa: ela já está na
             * ficha de pessoal, com o nome, o telefone e o BI. Escrevê-la
             * outra vez à mão é trabalho e é um erro à espera de acontecer.
             */
            'importar' => [
                'modelo' => \App\Models\HR\Employee::class,
                'botao' => 'Importar de RH',
                'titulo' => 'Importar mecânicos do pessoal',
                'nada' => 'Não há funcionários ao serviço nesta empresa.',
                'pesquisa' => ['first_name', 'last_name', 'employee_number'],
                'pesquisa_ajuda' => 'Nome ou número',
                'onde' => fn ($q) => $q->where('status', 'active'),
                'nome' => fn (Model $e) => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: ($e->full_name ?? '—'),
                'nota' => fn (Model $e) => $e->employee_number,
                /*
                 * JÁ CÁ ESTÁ quem tem o mesmo email OU o mesmo telefone — a
                 * mesma regra do ecrã de sempre, mas numa consulta só em vez
                 * de uma por funcionário.
                 */
                'ja_ca' => function ($candidatos, int $tenantId): array {
                    $emails = $candidatos->pluck('email')->filter()->all();
                    $telefones = $candidatos->pluck('phone')->filter()->all();

                    if (! $emails && ! $telefones) {
                        return [];
                    }

                    $existem = \App\Models\Workshop\Mechanic::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where(function ($q) use ($emails, $telefones) {
                            $emails && $q->orWhereIn('email', $emails);
                            $telefones && $q->orWhereIn('phone', $telefones);
                        })
                        ->get(['email', 'phone']);

                    $temEmail = $existem->pluck('email')->filter()->all();
                    $temTelefone = $existem->pluck('phone')->filter()->all();

                    return $candidatos
                        ->filter(fn ($e) => ($e->email && in_array($e->email, $temEmail, true))
                            || ($e->phone && in_array($e->phone, $temTelefone, true)))
                        ->pluck('id')->all();
                },
                'mapear' => fn (Model $e) => [
                    'user_id' => $e->user_id,
                    'name' => $e->full_name,
                    'email' => $e->email,
                    'phone' => $e->phone,
                    'mobile' => $e->mobile,
                    'document' => $e->bi_number ?? $e->nif,
                    // O cargo que a pessoa tem no RH é o palpite mais honesto
                    // para a especialidade; quem sabe melhor corrige na ficha.
                    'specialties' => [in_array($e->position?->title, self::ESPECIALIDADES, true)
                        ? $e->position->title : 'Mecânica Geral'],
                    'level' => 'pleno',
                    'hourly_rate' => 0,
                    'daily_rate' => 0,
                    'is_active' => true,
                    'is_available' => true,
                ],
            ],
        ];
    }

    /** Os quatro níveis de um mecânico — os do ecrã de sempre. */
    private const NIVEIS_DE_MECANICO = [
        ['valor' => 'junior', 'rotulo' => 'Júnior'],
        ['valor' => 'pleno', 'rotulo' => 'Pleno'],
        ['valor' => 'senior', 'rotulo' => 'Sénior'],
        ['valor' => 'master', 'rotulo' => 'Mestre'],
    ];

    private static function viaturas(): array
    {
        return [
            'modelo' => \App\Models\Workshop\Vehicle::class,
            'titulo' => 'Viaturas',
            'singular' => 'Viatura',
            'icone' => 'fa-car',
            'cor' => 'primaria',
            'descricao' => 'As viaturas da oficina, os donos e a validade dos documentos',
            'novo' => 'Nova Viatura',
            'rota' => '/workshop/vehicles',
            'permissoes' => self::porVerbo('workshop.vehicles'),
            /*
             * UMA VIATURA CHAMA-SE PELA MATRÍCULA, e não tem `name` nenhum.
             * Sem isto, a pergunta de apagar saía «Vai apagar . Não há volta.»
             */
            'nome' => 'plate',
            /*
             * A FICHA DA VIATURA — o olho da lista abre os dados e as folhas de
             * obra que lhe foram abertas, com a factura de cada uma (pedido de
             * 15/09/2026). É um ecrã próprio: `oficina/FichaDaViatura`.
             */
            'ficha' => 'viatura',
            /*
             * O FORMULÁRIO EM SEPARADORES — pedido de 15/09/2026 («melhorar o
             * modal de adicionar ou editar»). Vinte e três campos numa coluna
             * só obrigavam a descer até ao fim para achar o seguro; agora cada
             * coisa tem o seu sítio, e a aba com erros acende-se ao gravar. As
             * fotografias são o quarto separador (desenhado pelo ecrã).
             */
            'grupos' => [
                ['chave' => 'viatura', 'rotulo' => 'Viatura', 'icone' => 'fa-car', 'campos' => ['plate', 'work_order_ref', 'tag_number', 'status', 'brand', 'model', 'year', 'color', 'fuel_type', 'mileage', 'vin', 'engine_number', 'notes']],
                ['chave' => 'dono', 'rotulo' => 'Dono', 'icone' => 'fa-user', 'campos' => ['client_id', 'owner_name', 'owner_phone', 'owner_email', 'owner_nif', 'owner_address']],
                ['chave' => 'documentos', 'rotulo' => 'Documentos', 'icone' => 'fa-id-card', 'campos' => ['registration_document', 'registration_expiry', 'insurance_company', 'insurance_policy', 'insurance_expiry', 'inspection_expiry']],
            ],
            'pesquisa' => ['plate', 'vehicle_number', 'work_order_ref', 'tag_number', 'owner_name', 'brand', 'model', 'vin'],
            'pesquisa_ajuda' => 'Matrícula, WO#, TAG#, nº, dono, marca, modelo ou chassis',
            'ordem' => [['created_at', 'desc']],
            'colunas' => [
                // A chapa pequena, como a matrícula nova de Angola.
                ['chave' => 'plate', 'rotulo' => 'Matrícula', 'formato' => 'matricula'],
                ['chave' => 'vehicle_number', 'rotulo' => 'Nº', 'formato' => 'texto'],
                // O WO# e o TAG# do papel da oficina — pedido de 15/09/2026.
                ['chave' => 'work_order_ref', 'rotulo' => 'WO#', 'formato' => 'codigo', 'icone' => 'fa-clipboard-list', 'tom' => 'roxo'],
                ['chave' => 'tag_number', 'rotulo' => 'TAG#', 'formato' => 'codigo', 'icone' => 'fa-key', 'tom' => 'ambar'],
                ['chave' => 'owner_name', 'rotulo' => 'Proprietário', 'formato' => 'texto'],
                ['chave' => 'brand', 'rotulo' => 'Marca', 'formato' => 'texto'],
                ['chave' => 'model', 'rotulo' => 'Modelo', 'formato' => 'texto'],
                ['chave' => 'year', 'rotulo' => 'Ano', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'mileage', 'rotulo' => 'KM', 'formato' => 'numero', 'alinhar' => 'direita'],
                /*
                 * AS TRÊS VALIDADES SAEM A VERMELHO QUANDO PASSAM.
                 *
                 * É a coluna «Documentos» do ecrã de sempre. Uma viatura com o
                 * seguro caducado não sai da oficina, e uma data escrita a
                 * preto no meio de vinte não se vê.
                 */
                ['chave' => 'registration_expiry', 'rotulo' => 'Livrete', 'formato' => 'validade'],
                ['chave' => 'insurance_expiry', 'rotulo' => 'Seguro', 'formato' => 'validade'],
                ['chave' => 'inspection_expiry', 'rotulo' => 'Inspecção', 'formato' => 'validade'],
                /*
                 * O ESTADO MUDA-SE NA PRÓPRIA TABELA (`rapido`) — pedido de 15/09/2026:
                 * abrir a ficha inteira para passar de «Em serviço» a «Pronta
                 * para entrega» era trabalho a mais para a mudança mais frequente.
                 */
                ['chave' => 'status', 'rotulo' => 'Estado', 'formato' => 'escolha', 'rapido' => true],
            ],
            'filtros' => [
                ['chave' => 'status', 'rotulo' => 'Estado', 'referencia' => 'estados'],
                ['chave' => 'fuel_type', 'rotulo' => 'Combustível', 'opcoes' => self::COMBUSTIVEIS],
            ],
            'campos' => [
                self::campo('plate', 'Matrícula', 'texto', obrigatorio: true, ajuda: 'Única nesta empresa.'),
                /*
                 * O WO# E O TAG# — os dois números do papel que acompanha o carro:
                 * o da folha de obra (WO#) e o da etiqueta pendurada na chave (TAG#).
                 */
                self::campo('work_order_ref', 'Folha de obra (WO#)', 'texto', ajuda: 'O número da folha de obra escrito no papel. Ex.: 1050186'),
                self::campo('tag_number', 'TAG#', 'texto', ajuda: 'O número da etiqueta da chave. Ex.: 42'),
                // Os estados são um catálogo de cada oficina (Estados de Viatura).
                self::campo('status', 'Estado', 'escolha', obrigatorio: true, omissao: 'active', referencia: 'estados'),
                /*
                 * O DONO PODE SER UM CLIENTE DA FACTURAÇÃO — e é o que liga a
                 * ordem de serviço à factura. Escolhê-lo aqui não substitui os
                 * campos escritos: há viaturas de quem nunca foi facturado.
                 */
                // Escolher o cliente escreve os dados do dono (sem apagar o que se escreveu à mão).
                array_merge(self::campo('client_id', 'Cliente', 'referencia', referencia: 'clientes',
                    ajuda: 'Se o dono já é cliente da casa: os dados do dono preenchem-se sozinhos.'), [
                    'preencher' => ['owner_name' => 'nome', 'owner_phone' => 'telefone', 'owner_email' => 'email', 'owner_nif' => 'nif', 'owner_address' => 'morada'],
                ]),
                self::campo('owner_name', 'Proprietário', 'texto', obrigatorio: true),
                self::campo('owner_phone', 'Telefone do dono', 'texto'),
                self::campo('owner_email', 'Email do dono', 'email'),
                self::campo('owner_nif', 'NIF do dono', 'texto'),
                self::campo('owner_address', 'Morada do dono', 'textarea', largura: 'inteira'),
                self::campo('brand', 'Marca', 'texto', obrigatorio: true),
                self::campo('model', 'Modelo', 'texto', obrigatorio: true),
                self::campo('year', 'Ano', 'numero', passo: 1, min: 1900, max: 2100),
                self::campo('color', 'Cor', 'texto'),
                self::campo('vin', 'Nº de chassis (VIN)', 'texto'),
                self::campo('engine_number', 'Nº do motor', 'texto'),
                self::campo('fuel_type', 'Combustível', 'escolha', omissao: 'Gasolina', opcoes: self::COMBUSTIVEIS),
                self::campo('mileage', 'Quilómetros', 'numero', omissao: 0, passo: 1, min: 0),
                self::campo('registration_document', 'Nº do livrete', 'texto'),
                self::campo('registration_expiry', 'Validade do livrete', 'validade'),
                self::campo('insurance_company', 'Seguradora', 'texto'),
                self::campo('insurance_policy', 'Apólice', 'texto'),
                self::campo('insurance_expiry', 'Validade do seguro', 'validade'),
                self::campo('inspection_expiry', 'Validade da inspecção', 'validade'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'plate' => 'required|string|max:20',
                'work_order_ref' => 'nullable|string|max:40',
                'tag_number' => 'nullable|string|max:20',
                'client_id' => 'nullable|integer',
                'owner_name' => 'required|string|max:255',
                'owner_phone' => 'nullable|string|max:20',
                'owner_email' => 'nullable|email|max:255',
                'owner_nif' => 'nullable|string|max:20',
                'owner_address' => 'nullable|string|max:2000',
                'brand' => 'required|string|max:100',
                'model' => 'required|string|max:100',
                'year' => 'nullable|integer|min:1900|max:2100',
                'color' => 'nullable|string|max:50',
                'vin' => 'nullable|string|max:50',
                'engine_number' => 'nullable|string|max:50',
                'fuel_type' => 'nullable|in:Gasolina,Diesel,Elétrico,Híbrido,GPL',
                'mileage' => 'nullable|integer|min:0',
                'registration_document' => 'nullable|string|max:100',
                'registration_expiry' => 'nullable|date',
                'insurance_company' => 'nullable|string|max:150',
                'insurance_policy' => 'nullable|string|max:100',
                'insurance_expiry' => 'nullable|date',
                'inspection_expiry' => 'nullable|date',
                'status' => 'required|string|max:40',
                'notes' => 'nullable|string|max:2000',
            ],
            'validar' => self::tudoIsto([
                // A MATRÍCULA É ÚNICA POR EMPRESA — a regra do ecrã de sempre.
                self::codigoUnico(\App\Models\Workshop\Vehicle::class, 'Esta matrícula já está registada.', 'plate'),
                self::daCasa('client_id', \App\Models\Client::class, 'Esse cliente não é desta empresa.'),
                // O estado tem de ser um dos desta oficina.
                function (array $d, ?Model $m, int $tenantId) {
                    // Gravar antes de alguém ter aberto a lista também tem os estados padrão.
                    \App\Models\Workshop\VehicleStatus::garantirCatalogo($tenantId);

                    return collect(\App\Models\Workshop\VehicleStatus::todosDe($tenantId))->contains('valor', (string) ($d['status'] ?? ''))
                        ? [] : ['status' => __('Esse estado não existe nesta oficina.')];
                },
            ]),
            // Uma oficina sem estados recebe os padrões à primeira vista.
            'antes' => fn (int $tenantId) => \App\Models\Workshop\VehicleStatus::garantirCatalogo($tenantId),
            'preparar' => fn (array $d) => array_merge($d, [
                // A matrícula lê-se sempre igual: sem espaços à volta e em
                // maiúsculas, senão «LD-42-11-AA» e «ld-42-11-aa» são duas.
                'plate' => mb_strtoupper(trim((string) ($d['plate'] ?? ''))),
                'work_order_ref' => trim((string) ($d['work_order_ref'] ?? '')) ?: null,
                'tag_number' => mb_strtoupper(trim((string) ($d['tag_number'] ?? ''))) ?: null,
                'client_id' => ($d['client_id'] ?? null) ?: null,
                'year' => ($d['year'] ?? '') === '' ? null : (int) $d['year'],
                'mileage' => ($d['mileage'] ?? '') === '' ? 0 : (int) $d['mileage'],
                'registration_expiry' => ($d['registration_expiry'] ?? '') ?: null,
                'insurance_expiry' => ($d['insurance_expiry'] ?? '') ?: null,
                'inspection_expiry' => ($d['inspection_expiry'] ?? '') ?: null,
            ]),
            'referencias' => fn (int $t) => [
                'clientes' => \App\Models\Client::withoutGlobalScopes()
                    ->where('tenant_id', $t)->orderBy('name')
                    ->get(['id', 'name', 'phone', 'mobile', 'email', 'nif', 'address'])->map(fn ($c) => [
                        'valor' => (string) $c->id,
                        'rotulo' => $c->name,
                        // O que o formulário escreve no dono ao escolher este cliente.
                        'dados' => ['nome' => $c->name, 'telefone' => $c->phone ?: $c->mobile, 'email' => $c->email, 'nif' => $c->nif, 'morada' => $c->address],
                    ])->all(),
                'estados' => \App\Models\Workshop\VehicleStatus::todosDe($t),
                // As listas das fotografias (fase, serviço, zona) — o separador Fotografias.
                ...collect(\App\Models\Workshop\VehiclePhoto::listas())->mapWithKeys(fn ($l, $k) => ['fotos_' . $k => $l])->all(),
            ],
            /*
             * O NÚMERO INTERNO É GERADO, e de forma atómica: a coluna tem
             * índice único por empresa e um `create()` cru deixava-a a nulo.
             */
            'criar' => fn (array $dados) => \App\Models\Workshop\Vehicle::createWithTenantNumber($dados, 'vehicle_number', 'VEH-'),
            /*
             * UMA VIATURA COM ORDENS NÃO SE APAGA — guarda nova. Apagá-la
             * deixava a ordem de serviço sem viatura: sem matrícula na folha
             * de obra e sem nada a que ligar a factura.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Workshop\WorkOrder::where('vehicle_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há ordens de serviço desta viatura.',
            'accoes' => ['activar' => false, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
            'datas' => true,
        ];
    }

    /**
     * OS ESTADOS DE VIATURA — cada oficina cria os seus (15/09/2026).
     *
     * Eram quatro fixos. Nascem oito padrões à primeira vista; o código de cada
     * um nasce do nome e não muda, para as viaturas não perderem o estado quando
     * ele é renomeado. «Pode receber ordens» decide se a viatura aparece ao
     * abrir uma ordem de serviço nova.
     */
    /**
     * OS MODELOS DA INSPECÇÃO DIGITAL (15/09/2026, OF-02).
     *
     * Os pontos escrevem-se um por linha, «Secção: ponto». Uma empresa que nunca
     * abriu a lista recebe a «Revisão geral» com 33 pontos.
     */
    /**
     * OS ELEVADORES E BAIAS (15/09/2026, OF-05) — os lugares da agenda.
     */
    private static function lugaresDaOficina(): array
    {
        $modelo = \App\Models\Workshop\Bay::class;
        $cores = [
            ['valor' => 'azul', 'rotulo' => 'Azul'], ['valor' => 'verde', 'rotulo' => 'Verde'], ['valor' => 'ambar', 'rotulo' => 'Âmbar'],
            ['valor' => 'laranja', 'rotulo' => 'Laranja'], ['valor' => 'roxo', 'rotulo' => 'Roxo'], ['valor' => 'teal', 'rotulo' => 'Verde-azulado'],
            ['valor' => 'vermelho', 'rotulo' => 'Vermelho'], ['valor' => 'cinza', 'rotulo' => 'Cinzento'],
        ];

        return [
            'modelo' => $modelo,
            'titulo' => 'Elevadores e Baias',
            'singular' => 'Lugar',
            'icone' => 'fa-warehouse',
            'cor' => 'ciano',
            'descricao' => 'Os lugares onde se trabalha nos carros — é por eles que a agenda mede a capacidade',
            'novo' => 'Novo Lugar',
            'rota' => '/workshop/bays',
            'permissoes' => ['ver' => 'workshop.work-orders.view', 'criar' => 'workshop.work-orders.edit', 'editar' => 'workshop.work-orders.edit', 'apagar' => 'workshop.work-orders.edit'],
            'pesquisa' => ['name'],
            'pesquisa_ajuda' => 'Nome',
            'ordem' => [['sort_order', 'asc'], ['name', 'asc']],
            'antes' => fn (int $tenantId) => $modelo::garantirCatalogo($tenantId),
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'kind', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'color', 'rotulo' => 'Cor', 'formato' => 'escolha'],
                ['chave' => 'sort_order', 'rotulo' => 'Ordem', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true, ajuda: 'Ex.: Elevador 1, Cabine de pintura.'),
                self::campo('kind', 'Tipo', 'escolha', obrigatorio: true, omissao: 'elevador', opcoes: collect($modelo::TIPOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => $r])->values()->all()),
                self::campo('color', 'Cor na agenda', 'escolha', obrigatorio: true, omissao: 'azul', opcoes: $cores),
                self::campo('sort_order', 'Ordem na agenda', 'numero', omissao: 0, passo: 1, min: 0),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|string|max:80',
                'kind' => 'required|in:' . implode(',', array_keys($modelo::TIPOS)),
                'color' => 'required|in:' . implode(',', $modelo::CORES),
                'sort_order' => 'nullable|integer|min:0|max:999',
                'is_active' => 'boolean',
            ],
            'validar' => function (array $d, ?Model $m, int $tenantId) use ($modelo) {
                $repetido = $modelo::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', $d['name'] ?? '')
                    ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();

                return $repetido ? ['name' => __('Já existe um lugar com esse nome.')] : [];
            },
            'preparar' => function (array $d) {
                $d['sort_order'] = (int) ($d['sort_order'] ?? 0);
                $d['is_active'] = (bool) ($d['is_active'] ?? true);

                return $d;
            },
            // As marcações guardam o lugar como nulo se ele for apagado; desactivar é o caminho.
            'pode_apagar' => fn (Model $m) => ! \App\Models\Workshop\Appointment::where('bay_id', $m->id)->whereIn('status', \App\Models\Workshop\Appointment::OCUPAM)->where('ends_at', '>', now())->exists(),
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function modelosDeInspeccao(): array
    {
        $modelo = \App\Models\Workshop\InspectionTemplate::class;

        return [
            'modelo' => $modelo,
            'titulo' => 'Modelos de Inspecção',
            'singular' => 'Modelo de inspecção',
            'icone' => 'fa-list-check',
            'cor' => 'bom',
            'descricao' => 'Os pontos que a oficina confere em cada inspecção, com semáforo',
            'novo' => 'Novo Modelo',
            'rota' => '/workshop/inspection-templates',
            'permissoes' => ['ver' => 'workshop.work-orders.view', 'criar' => 'workshop.work-orders.edit', 'editar' => 'workshop.work-orders.edit', 'apagar' => 'workshop.work-orders.edit'],
            'pesquisa' => ['name', 'description', 'points'],
            'pesquisa_ajuda' => 'Nome ou ponto',
            'ordem' => [['is_default', 'desc'], ['name', 'asc']],
            'antes' => fn (int $tenantId) => $modelo::garantirCatalogo($tenantId),
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'kind', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'is_required', 'rotulo' => 'Obrigatório', 'formato' => 'booleano'],
                ['chave' => 'points_count', 'rotulo' => 'Pontos', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'is_default', 'rotulo' => 'Padrão', 'formato' => 'padrao'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true, ajuda: 'Ex.: Revisão geral, Pré-compra, Antes de viagem.'),
                self::campo('kind', 'Tipo', 'escolha', obrigatorio: true, omissao: 'inspecao', opcoes: [
                    ['valor' => 'inspecao', 'rotulo' => 'Inspecção'], ['valor' => 'qualidade', 'rotulo' => 'Controlo de qualidade'],
                ]),
                self::campo('description', 'Descrição', 'texto'),
                self::campo('points', 'Pontos a conferir', 'textarea', obrigatorio: true, largura: 'inteira',
                    ajuda: 'Um ponto por linha, com a secção antes dos dois pontos. Ex.: «Travões: Pastilhas da frente».'),
                self::campo('is_default', 'Modelo que aparece primeiro', 'booleano', omissao: false),
                self::campo('is_required', 'Obrigatório para concluir a ordem', 'booleano', omissao: false,
                    ajuda: 'Só nos modelos de controlo de qualidade: a ordem não passa a Concluída sem ele feito e sem pontos urgentes.'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|string|max:120',
                'description' => 'nullable|string|max:500',
                'points' => 'required|string|max:20000',
                'kind' => 'nullable|in:inspecao,qualidade',
                'is_default' => 'boolean',
                'is_required' => 'boolean',
                'is_active' => 'boolean',
            ],
            'validar' => function (array $d, ?Model $m, int $tenantId) use ($modelo) {
                $erros = [];
                $repetido = $modelo::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', $d['name'] ?? '')
                    ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();
                $pontos = count($modelo::lerPontos((string) ($d['points'] ?? '')));

                if ($repetido) {
                    $erros['name'] = __('Já existe um modelo com esse nome.');
                }
                if ($pontos === 0) {
                    $erros['points'] = __('Escreva pelo menos um ponto.');
                } elseif ($pontos > 200) {
                    $erros['points'] = __('No máximo 200 pontos por modelo.');
                }

                return $erros;
            },
            'preparar' => function (array $d) {
                $d['is_default'] = (bool) ($d['is_default'] ?? false);
                $d['is_active'] = (bool) ($d['is_active'] ?? true);
                $d['kind'] = ($d['kind'] ?? '') === 'qualidade' ? 'qualidade' : 'inspecao';
                // Só um controlo de qualidade pode ser obrigatório para concluir.
                $d['is_required'] = ($d['kind'] ?? 'inspecao') === 'qualidade' && (bool) ($d['is_required'] ?? false);

                return $d;
            },
            // Só um aparece primeiro.
            'depois' => function (Model $m) use ($modelo) {
                if ($m->is_default) {
                    $modelo::withoutGlobalScopes()->where('tenant_id', $m->tenant_id)->whereKeyNot($m->id)->update(['is_default' => false]);
                }
            },
            'padrao' => function (Model $m) use ($modelo) {
                $modelo::withoutGlobalScopes()->where('tenant_id', $m->tenant_id)->update(['is_default' => false]);
                $m->update(['is_default' => true]);
            },
            // As inspecções já feitas guardam o nome e os pontos: apagar o modelo não as toca.
            'pode_apagar' => fn (Model $m) => true,
            'accoes' => ['activar' => true, 'padrao' => true, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private static function estadosDeViatura(): array
    {
        $modelo = \App\Models\Workshop\VehicleStatus::class;

        return [
            'modelo' => $modelo,
            'titulo' => 'Estados de Viatura',
            'singular' => 'Estado de viatura',
            'icone' => 'fa-traffic-light',
            'cor' => 'primaria',
            'descricao' => 'Os estados que as viaturas da oficina podem ter',
            'novo' => 'Novo Estado',
            'rota' => '/workshop/vehicle-statuses',
            'permissoes' => ['ver' => 'workshop.vehicles.view', 'criar' => 'workshop.vehicles.edit', 'editar' => 'workshop.vehicles.edit', 'apagar' => 'workshop.vehicles.edit'],
            'pesquisa' => ['name', 'code'],
            'pesquisa_ajuda' => 'Nome ou código',
            'ordem' => [['sort_order', 'asc'], ['name', 'asc']],
            'antes' => fn (int $tenantId) => $modelo::garantirCatalogo($tenantId),
            // O código nasce no `preparar` e não é campo do formulário.
            'extras' => ['code'],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'color', 'rotulo' => 'Cor', 'formato' => 'escolha'],
                ['chave' => 'accepts_orders', 'rotulo' => 'Recebe ordens', 'formato' => 'booleano'],
                ['chave' => 'is_default', 'rotulo' => 'Padrão', 'formato' => 'padrao'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true, ajuda: 'Como aparece na lista das viaturas.'),
                self::campo('color', 'Cor', 'escolha', obrigatorio: true, omissao: 'azul', opcoes: [
                    ['valor' => 'verde', 'rotulo' => 'Verde'], ['valor' => 'azul', 'rotulo' => 'Azul'],
                    ['valor' => 'ambar', 'rotulo' => 'Âmbar'], ['valor' => 'laranja', 'rotulo' => 'Laranja'],
                    ['valor' => 'teal', 'rotulo' => 'Verde-azulado'], ['valor' => 'roxo', 'rotulo' => 'Roxo'],
                    ['valor' => 'vermelho', 'rotulo' => 'Vermelho'], ['valor' => 'cinza', 'rotulo' => 'Cinzento'],
                ]),
                self::campo('accepts_orders', 'Pode receber ordens de serviço', 'booleano', omissao: true,
                    ajuda: 'Desligado, a viatura não aparece ao abrir uma ordem nova (ex.: abatida ou vendida).'),
                self::campo('is_default', 'Estado das viaturas novas', 'booleano', omissao: false),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'name' => 'required|string|max:80',
                'color' => 'required|in:' . implode(',', $modelo::CORES),
                'accepts_orders' => 'boolean',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
            ],
            'validar' => function (array $d, ?Model $m, int $tenantId) use ($modelo) {
                $repetido = $modelo::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', $d['name'])
                    ->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists();

                return $repetido ? ['name' => __('Já existe um estado com esse nome.')] : [];
            },
            'preparar' => function (array $d, ?Model $m, int $tenantId) use ($modelo) {
                $d['accepts_orders'] = (bool) ($d['accepts_orders'] ?? true);
                $d['is_default'] = (bool) ($d['is_default'] ?? false);
                $d['is_active'] = (bool) ($d['is_active'] ?? true);

                if (! $m) {
                    $d['code'] = $modelo::codigoPara($tenantId, (string) $d['name']);
                    $d['sort_order'] = (int) $modelo::withoutGlobalScopes()->where('tenant_id', $tenantId)->max('sort_order') + 1;
                }

                return $d;
            },
            // Só um é o das viaturas novas.
            'depois' => function (Model $m) use ($modelo) {
                if ($m->is_default) {
                    $modelo::withoutGlobalScopes()->where('tenant_id', $m->tenant_id)->whereKeyNot($m->id)->update(['is_default' => false]);
                    $modelo::esquecer();
                }
            },
            'padrao' => function (Model $m) use ($modelo) {
                $modelo::withoutGlobalScopes()->where('tenant_id', $m->tenant_id)->update(['is_default' => false]);
                $m->update(['is_default' => true]);
            },
            // UM ESTADO EM USO NÃO SE APAGA: as viaturas ficavam com um código que
            // já não se lê. Desactiva-se, e deixa de aparecer para as novas.
            'pode_apagar' => fn (Model $m) => ! $m->is_default
                && ! \App\Models\Workshop\Vehicle::withoutGlobalScopes()->where('tenant_id', $m->tenant_id)->where('status', $m->code)->exists(),
            'porque_nao_apaga' => 'Há viaturas neste estado (ou é o estado padrão). Desactive-o em vez de o apagar.',
            'accoes' => ['activar' => true, 'padrao' => true, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private const COMBUSTIVEIS = [
        ['valor' => 'Gasolina', 'rotulo' => 'Gasolina'],
        ['valor' => 'Diesel', 'rotulo' => 'Gasóleo'],
        ['valor' => 'Elétrico', 'rotulo' => 'Eléctrico'],
        ['valor' => 'Híbrido', 'rotulo' => 'Híbrido'],
        ['valor' => 'GPL', 'rotulo' => 'GPL'],
    ];

    private static function servicos(): array
    {
        return [
            'modelo' => \App\Models\Workshop\Service::class,
            'titulo' => 'Serviços',
            'singular' => 'Serviço',
            'icone' => 'fa-screwdriver-wrench',
            'cor' => 'roxo',
            'descricao' => 'O que a oficina faz, o preço da mão-de-obra e as horas previstas',
            'novo' => 'Novo Serviço',
            'rota' => '/workshop/services',
            'permissoes' => self::porVerbo('workshop.services'),
            'pesquisa' => ['name', 'service_code', 'description'],
            'pesquisa_ajuda' => 'Nome, código ou descrição',
            'ordem' => [['sort_order', 'asc'], ['name', 'asc']],
            'colunas' => [
                ['chave' => 'service_code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'name', 'rotulo' => 'Serviço', 'formato' => 'texto'],
                ['chave' => 'category', 'rotulo' => 'Categoria', 'formato' => 'escolha'],
                ['chave' => 'labor_cost', 'rotulo' => 'Mão-de-obra', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'estimated_hours', 'rotulo' => 'Horas', 'formato' => 'numero', 'alinhar' => 'direita'],
            ],
            'filtros' => [
                ['chave' => 'category', 'rotulo' => 'Categoria', 'opcoes' => self::CATEGORIAS_DE_SERVICO],
            ],
            'campos' => [
                self::campo('name', 'Serviço', 'texto', obrigatorio: true),
                self::campo('category', 'Categoria', 'escolha', obrigatorio: true, omissao: 'Manutenção', opcoes: self::CATEGORIAS_DE_SERVICO),
                self::campo('labor_cost', 'Mão-de-obra (Kz)', 'numero', obrigatorio: true, omissao: 0, passo: 0.01, min: 0),
                self::campo('estimated_hours', 'Horas previstas', 'numero', obrigatorio: true, omissao: 1, passo: 0.25, min: 0),
                self::campo('sort_order', 'Ordem', 'numero', omissao: 0, passo: 1, min: 0),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'category' => 'required|in:Manutenção,Reparação,Inspeção,Pintura,Mecânica,Elétrica,Chapa,Pneus,Outro',
                'labor_cost' => 'required|numeric|min:0',
                'estimated_hours' => 'required|numeric|min:0',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:2000',
            ],
            'preparar' => fn (array $d) => array_merge($d, [
                'labor_cost' => (float) ($d['labor_cost'] ?? 0),
                'estimated_hours' => (float) ($d['estimated_hours'] ?? 0),
                'sort_order' => (int) ($d['sort_order'] ?? 0),
            ]),
            // O código do serviço é gerado por empresa, de forma atómica.
            'criar' => fn (array $dados) => \App\Models\Workshop\Service::createWithTenantNumber($dados, 'service_code', 'SRV-'),
            /*
             * UM SERVIÇO JÁ LANÇADO NUMA ORDEM NÃO SE APAGA — guarda nova. A
             * linha da ordem aponta para ele, e sem ele a folha de obra deixa
             * de dizer o que se fez ao carro.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Workshop\WorkOrderItem::where('service_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Este serviço já foi lançado em ordens de serviço.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private const CATEGORIAS_DE_SERVICO = [
        ['valor' => 'Manutenção', 'rotulo' => 'Manutenção'],
        ['valor' => 'Reparação', 'rotulo' => 'Reparação'],
        ['valor' => 'Inspeção', 'rotulo' => 'Inspecção'],
        ['valor' => 'Pintura', 'rotulo' => 'Pintura'],
        ['valor' => 'Mecânica', 'rotulo' => 'Mecânica'],
        ['valor' => 'Elétrica', 'rotulo' => 'Eléctrica'],
        ['valor' => 'Chapa', 'rotulo' => 'Chapa'],
        ['valor' => 'Pneus', 'rotulo' => 'Pneus'],
        ['valor' => 'Outro', 'rotulo' => 'Outro'],
    ];

    /* ─── O hotel ──────────────────────────────────────────────────────── */

    /** O que um quarto pode ter — a lista do ecrã de sempre. */
    public const COMODIDADES = [
        'wifi' => 'WiFi gratuito', 'ac' => 'Ar condicionado', 'tv' => 'TV por cabo',
        'minibar' => 'Minibar', 'safe' => 'Cofre', 'balcony' => 'Varanda',
        'sea_view' => 'Vista para o mar', 'bathtub' => 'Banheira', 'shower' => 'Chuveiro',
        'hairdryer' => 'Secador', 'iron' => 'Ferro de engomar', 'desk' => 'Secretária',
        'phone' => 'Telefone', 'room_service' => 'Serviço de quarto',
        'breakfast' => 'Pequeno-almoço incluído',
    ];

    /** @return list<array{valor: string, rotulo: string}> */
    private static function escolhasDe(array $mapa): array
    {
        return collect($mapa)->map(fn ($rotulo, $valor) => ['valor' => (string) $valor, 'rotulo' => $rotulo])->values()->all();
    }

    private static function tiposDeQuarto(): array
    {
        return [
            'modelo' => \App\Models\Hotel\RoomType::class,
            'titulo' => 'Tipos de Quarto',
            'singular' => 'Tipo de Quarto',
            'icone' => 'fa-bed',
            'cor' => 'roxo',
            'descricao' => 'O que a casa oferece, quanto custa a noite e o que o quarto tem',
            'novo' => 'Novo Tipo de Quarto',
            'rota' => '/hotel/room-types',
            'permissoes' => self::porVerbo('hotel.room-types'),
            'pesquisa' => ['name', 'code', 'description'],
            'pesquisa_ajuda' => 'Nome, código ou descrição',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Tipo', 'formato' => 'texto'],
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'capacity', 'rotulo' => 'Pessoas', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'base_price', 'rotulo' => 'Preço/noite', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'weekend_price', 'rotulo' => 'Fim-de-semana', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'amenities', 'rotulo' => 'Comodidades', 'formato' => 'multi'],
            ],
            'filtros' => [],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('code', 'Código', 'texto', ajuda: 'Curto e único nesta casa — SGL, DBL, STE.'),
                self::campo('base_price', 'Preço por noite (Kz)', 'numero', obrigatorio: true, omissao: 0, passo: 0.01, min: 0),
                /*
                 * O PREÇO DE FIM-DE-SEMANA fica em branco quando não há: um
                 * zero seria um quarto de graça ao sábado, e é assim que a
                 * tarifa o lê.
                 */
                self::campo('weekend_price', 'Preço ao fim-de-semana (Kz)', 'numero', passo: 0.01, min: 0,
                    ajuda: 'Em branco usa o preço normal.'),
                self::campo('capacity', 'Pessoas', 'numero', obrigatorio: true, omissao: 2, passo: 1, min: 1, max: 20),
                self::campo('extra_bed_capacity', 'Camas extra', 'numero', obrigatorio: true, omissao: 0, passo: 1, min: 0, max: 5),
                self::campo('extra_bed_price', 'Preço da cama extra (Kz)', 'numero', obrigatorio: true, omissao: 0, passo: 0.01, min: 0),
                self::campo('amenities', 'Comodidades', 'multi', omissao: [],
                    opcoes: self::escolhasDe(self::COMODIDADES), largura: 'inteira'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira',
                    ajuda: 'É esta que o site de reservas mostra.'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'code' => 'nullable|string|max:20',
                'description' => 'nullable|string|max:5000',
                'base_price' => 'required|numeric|min:0',
                'weekend_price' => 'nullable|numeric|min:0',
                'capacity' => 'required|integer|min:1|max:20',
                'extra_bed_capacity' => 'required|integer|min:0|max:5',
                'extra_bed_price' => 'required|numeric|min:0',
                'amenities' => 'array',
                'amenities.*' => 'string|max:50',
                'is_active' => 'boolean',
            ],
            'validar' => self::codigoUnico(\App\Models\Hotel\RoomType::class, 'Já existe um tipo de quarto com esse código.'),
            'preparar' => fn (array $d) => array_merge($d, [
                'code' => ($d['code'] ?? '') === '' ? null : mb_strtoupper(trim((string) $d['code'])),
                'weekend_price' => ($d['weekend_price'] ?? '') === '' ? null : (float) $d['weekend_price'],
                // Só as comodidades que a casa reconhece: uma inventada no
                // pedido entrava na lista e ficava lá para sempre.
                'amenities' => array_values(array_intersect(
                    array_map('strval', $d['amenities'] ?? []), array_keys(self::COMODIDADES),
                )),
            ]),
            /*
             * UM TIPO COM QUARTOS NÃO SE APAGA — a guarda do ecrã de sempre.
             * Sem ele, os quartos ficavam a apontar para um tipo que já não
             * existe, e sem tipo não há preço da noite.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Hotel\Room::where('room_type_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há quartos deste tipo.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => true, 'apagar' => true, 'galeria' => true],
            'imagem' => ['coluna' => 'featured_image', 'pasta' => 'hotel/room-types', 'prefixo' => 'destaque', 'rotulo' => 'Imagem de destaque'],
            'galeria' => ['coluna' => 'gallery', 'pasta' => 'hotel/room-types', 'rotulo' => 'Galeria do quarto'],
        ];
    }

    private const ESTADOS_DO_QUARTO = [
        'available' => 'Livre',
        'occupied' => 'Ocupado',
        'reserved' => 'Reservado',
        'cleaning' => 'Em limpeza',
        'maintenance' => 'Em manutenção',
    ];

    private const LIMPEZA_DO_QUARTO = [
        'clean' => 'Limpo',
        'dirty' => 'Sujo',
        'in_progress' => 'Em limpeza',
        'inspecting' => 'Em inspecção',
        'out_of_order' => 'Fora de serviço',
    ];

    private static function quartos(): array
    {
        return [
            'modelo' => \App\Models\Hotel\Room::class,
            'titulo' => 'Quartos',
            'singular' => 'Quarto',
            'icone' => 'fa-door-open',
            'cor' => 'ciano',
            'descricao' => 'Os quartos da casa, o piso e como estão agora',
            'novo' => 'Novo Quarto',
            'rota' => '/hotel/rooms',
            'permissoes' => self::porVerbo('hotel.rooms'),
            /* Um quarto chama-se pelo NÚMERO, e não tem `name`. */
            'nome' => 'number',
            'pesquisa' => ['number', 'floor', 'notes'],
            'pesquisa_ajuda' => 'Número, piso ou notas',
            'ordem' => [['floor', 'asc'], ['number', 'asc']],
            'colunas' => [
                ['chave' => 'number', 'rotulo' => 'Quarto', 'formato' => 'texto'],
                ['chave' => 'floor', 'rotulo' => 'Piso', 'formato' => 'texto'],
                ['chave' => 'room_type_id', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'status', 'rotulo' => 'Estado', 'formato' => 'escolha'],
                ['chave' => 'housekeeping_status', 'rotulo' => 'Limpeza', 'formato' => 'escolha'],
                ['chave' => 'features', 'rotulo' => 'Extras', 'formato' => 'multi'],
            ],
            'filtros' => [
                ['chave' => 'status', 'rotulo' => 'Estado', 'opcoes' => self::escolhasDe(self::ESTADOS_DO_QUARTO)],
                ['chave' => 'housekeeping_status', 'rotulo' => 'Limpeza', 'opcoes' => self::escolhasDe(self::LIMPEZA_DO_QUARTO)],
                ['chave' => 'room_type_id', 'rotulo' => 'Tipo', 'referencia' => 'tipos'],
                ['chave' => 'floor', 'rotulo' => 'Piso', 'tipo' => 'texto', 'ajuda' => 'Escreva o piso.'],
            ],
            'campos' => [
                self::campo('number', 'Número', 'texto', obrigatorio: true),
                self::campo('room_type_id', 'Tipo de quarto', 'referencia', obrigatorio: true, referencia: 'tipos'),
                self::campo('floor', 'Piso', 'texto'),
                self::campo('status', 'Estado', 'escolha', obrigatorio: true, omissao: 'available',
                    opcoes: self::escolhasDe(self::ESTADOS_DO_QUARTO)),
                /*
                 * O ESTADO DE LIMPEZA É OUTRA COISA que o estado do quarto: um
                 * quarto pode estar livre e sujo, e é essa a diferença entre
                 * «pode entrar alguém» e «pode vender-se». O ecrã em Blade
                 * tinha a coluna e não a deixava mudar.
                 */
                self::campo('housekeeping_status', 'Limpeza', 'escolha', obrigatorio: true, omissao: 'clean',
                    opcoes: self::escolhasDe(self::LIMPEZA_DO_QUARTO)),
                /*
                 * OS EXTRAS DO QUARTO — o que ELE tem além do que o tipo dá.
                 * A coluna existia e nunca teve controlo nenhum no formulário:
                 * guardava-se sempre a lista vazia.
                 */
                self::campo('features', 'Extras deste quarto', 'multi', omissao: [],
                    opcoes: self::escolhasDe(self::COMODIDADES), largura: 'inteira',
                    ajuda: 'Além do que o tipo de quarto já dá.'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'number' => 'required|string|max:20',
                'room_type_id' => 'required|integer',
                'floor' => 'nullable|string|max:10',
                'status' => 'required|in:available,occupied,maintenance,cleaning,reserved',
                'housekeeping_status' => 'required|in:clean,dirty,in_progress,inspecting,out_of_order',
                'features' => 'array',
                'features.*' => 'string|max:50',
                'notes' => 'nullable|string|max:2000',
                'is_active' => 'boolean',
            ],
            'validar' => self::tudoIsto([
                // O NÚMERO DO QUARTO É ÚNICO NA CASA. Dois «101» é o começo de
                // uma reserva no quarto errado.
                self::codigoUnico(\App\Models\Hotel\Room::class, 'Já existe um quarto com esse número.', 'number'),
                self::daCasa('room_type_id', \App\Models\Hotel\RoomType::class, 'Esse tipo de quarto não é desta casa.'),
            ]),
            'preparar' => fn (array $d) => array_merge($d, [
                'number' => trim((string) ($d['number'] ?? '')),
                'floor' => ($d['floor'] ?? '') === '' ? null : trim((string) $d['floor']),
                'features' => array_values(array_intersect(
                    array_map('strval', $d['features'] ?? []), array_keys(self::COMODIDADES),
                )),
            ]),
            'referencias' => fn (int $t) => [
                'tipos' => \App\Models\Hotel\RoomType::withoutGlobalScopes()
                    ->where('tenant_id', $t)->orderBy('name')
                    ->get(['id', 'name'])->map(fn ($r) => ['valor' => (string) $r->id, 'rotulo' => $r->name])->all(),
            ],
            /*
             * UM QUARTO COM RESERVA VIVA NÃO SE APAGA — a guarda do ecrã de
             * sempre. Apagá-lo deixava alguém com reserva e sem quarto.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Hotel\Reservation::where('room_id', $m->id)
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])->exists(),
            'porque_nao_apaga' => 'Há reservas activas neste quarto.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private const DOCUMENTOS_DO_HOSPEDE = [
        'bi' => 'Bilhete de Identidade',
        'passaporte' => 'Passaporte',
        'cartao_residente' => 'Cartão de Residente',
        'carta_conducao' => 'Carta de Condução',
        'outro' => 'Outro',
    ];

    private static function hospedes(): array
    {
        return [
            /*
             * O HÓSPEDE É UM CLIENTE DA FACTURAÇÃO — não uma segunda ficha.
             *
             * É a tabela que as reservas usam (`hotel_reservations.client_id`)
             * e a que a factura precisa. As duas bandeiras do hotel (VIP e
             * lista negra) são colunas próprias em `invoicing_clients`.
             */
            'modelo' => \App\Models\Client::class,
            'titulo' => 'Hóspedes',
            'singular' => 'Hóspede',
            'icone' => 'fa-user-tie',
            'cor' => 'primaria',
            'descricao' => 'Quem já cá ficou — e quem se recebe de braços abertos',
            'novo' => 'Novo Hóspede',
            'rota' => '/hotel/guests',
            'permissoes' => self::porVerbo('hotel.guests'),
            'pesquisa' => ['name', 'email', 'phone', 'document_number', 'nif'],
            'pesquisa_ajuda' => 'Nome, email, telefone, documento ou NIF',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'phone', 'rotulo' => 'Telefone', 'formato' => 'texto'],
                ['chave' => 'email', 'rotulo' => 'Email', 'formato' => 'texto'],
                ['chave' => 'document_number', 'rotulo' => 'Documento', 'formato' => 'texto'],
                ['chave' => 'nationality', 'rotulo' => 'Nacionalidade', 'formato' => 'texto'],
                ['chave' => 'hotel_vip', 'rotulo' => 'VIP', 'formato' => 'booleano'],
                ['chave' => 'hotel_blacklisted', 'rotulo' => 'Lista negra', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'hotel_vip', 'rotulo' => 'VIP', 'opcoes' => [
                    ['valor' => '1', 'rotulo' => 'Só VIP'],
                    ['valor' => '0', 'rotulo' => 'Sem VIP'],
                ]],
                ['chave' => 'hotel_blacklisted', 'rotulo' => 'Lista negra', 'opcoes' => [
                    ['valor' => '1', 'rotulo' => 'Só a lista negra'],
                    ['valor' => '0', 'rotulo' => 'Fora da lista negra'],
                ]],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('phone', 'Telefone', 'texto'),
                self::campo('email', 'Email', 'email'),
                self::campo('document_type', 'Tipo de documento', 'escolha', omissao: 'bi',
                    opcoes: self::escolhasDe(self::DOCUMENTOS_DO_HOSPEDE)),
                self::campo('document_number', 'Nº do documento', 'texto'),
                self::campo('nif', 'NIF', 'texto', ajuda: 'Único nesta empresa — é a identidade fiscal.'),
                self::campo('nationality', 'Nacionalidade', 'texto', omissao: 'Angola'),
                self::campo('birth_date', 'Data de nascimento', 'data'),
                self::campo('gender', 'Sexo', 'escolha', opcoes: [
                    ['valor' => 'male', 'rotulo' => 'Masculino'],
                    ['valor' => 'female', 'rotulo' => 'Feminino'],
                    ['valor' => 'other', 'rotulo' => 'Outro'],
                ]),
                /*
                 * A LISTA NEGRA É UMA DECISÃO, não uma etiqueta: quem lá está
                 * não volta a ficar hospedado. Fica junto do VIP porque são as
                 * duas metades da mesma pergunta.
                 */
                self::campo('hotel_vip', 'Hóspede VIP', 'booleano', omissao: false),
                self::campo('hotel_blacklisted', 'Na lista negra', 'booleano', omissao: false),
                /*
                 * A PROVÍNCIA FALTAVA À FICHA DO HÓSPEDE, e estava no
                 * formulário rápido das reservas: quem criava o hóspede pelo
                 * balcão escrevia-a, quem o criava por aqui não tinha onde. É a
                 * mesma coluna do cliente, e alimenta a morada da factura.
                 */
                self::campo('province', 'Província', 'provincia'),
                self::campo('city', 'Cidade', 'texto'),
                self::campo('country', 'País', 'texto', omissao: 'Angola'),
                self::campo('address', 'Morada', 'textarea', largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'document_type' => 'nullable|string|max:50',
                'document_number' => 'nullable|string|max:50',
                'nationality' => 'nullable|string|max:100',
                'birth_date' => 'nullable|date',
                'gender' => 'nullable|in:male,female,other',
                'address' => 'nullable|string|max:2000',
                'city' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'nif' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:2000',
                'hotel_vip' => 'boolean',
                'hotel_blacklisted' => 'boolean',
            ],
            /*
             * O NIF É ÚNICO POR EMPRESA — há índice na base e o ecrã de sempre
             * não o dizia: repetir um NIF dava um 1062 cru na cara de quem
             * estava a escrever a ficha do hóspede.
             */
            'validar' => self::codigoUnico(\App\Models\Client::class, 'Já existe um cliente com esse NIF.', 'nif'),
            'preparar' => fn (array $d) => array_merge($d, [
                'nif' => ($d['nif'] ?? '') === '' ? null : trim((string) $d['nif']),
                'birth_date' => ($d['birth_date'] ?? '') ?: null,
                'gender' => ($d['gender'] ?? '') ?: null,
                // Um hóspede é uma PESSOA: o tipo decide se há retenção de IRT
                // na factura, e sem ele a coluna ficava a nulo.
                'type' => 'pessoa_fisica',
                'is_active' => true,
            ]),
            /*
             * UM HÓSPEDE COM RESERVAS OU COM FACTURAS NÃO SE APAGA — guarda
             * nova. O ecrã de sempre apagava e deixava a reserva sem ninguém.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Hotel\Reservation::where('client_id', $m->id)->exists()
                && ! \App\Models\Invoicing\SalesInvoice::where('client_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Este hóspede tem reservas ou facturas.',
            'accoes' => ['activar' => false, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
            'datas' => true,
        ];
    }

    private static function pessoalDoHotel(): array
    {
        return [
            'modelo' => \App\Models\Hotel\Staff::class,
            'titulo' => 'Pessoal do Hotel',
            'singular' => 'Colaborador',
            'icone' => 'fa-users-gear',
            'cor' => 'laranja',
            'descricao' => 'Quem trabalha na casa, em que departamento e a que horas',
            'novo' => 'Novo Colaborador',
            'rota' => '/hotel/staff',
            'permissoes' => self::porVerbo('hotel.staff'),
            'pesquisa' => ['name', 'email', 'phone', 'document'],
            'pesquisa_ajuda' => 'Nome, email, telefone ou documento',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'position', 'rotulo' => 'Função', 'formato' => 'escolha'],
                ['chave' => 'department', 'rotulo' => 'Departamento', 'formato' => 'escolha'],
                ['chave' => 'phone', 'rotulo' => 'Telefone', 'formato' => 'texto'],
                ['chave' => 'work_start', 'rotulo' => 'Entrada', 'formato' => 'hora'],
                ['chave' => 'work_end', 'rotulo' => 'Saída', 'formato' => 'hora'],
                ['chave' => 'working_days', 'rotulo' => 'Dias', 'formato' => 'dias'],
            ],
            'filtros' => [
                ['chave' => 'department', 'rotulo' => 'Departamento', 'opcoes' => self::escolhasDe(\App\Models\Hotel\Staff::DEPARTMENTS)],
                ['chave' => 'position', 'rotulo' => 'Função', 'opcoes' => self::escolhasDe(\App\Models\Hotel\Staff::POSITIONS)],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('position', 'Função', 'escolha', obrigatorio: true, omissao: 'receptionist',
                    opcoes: self::escolhasDe(\App\Models\Hotel\Staff::POSITIONS)),
                self::campo('department', 'Departamento', 'escolha', obrigatorio: true, omissao: 'front_desk',
                    opcoes: self::escolhasDe(\App\Models\Hotel\Staff::DEPARTMENTS)),
                self::campo('email', 'Email', 'email'),
                self::campo('phone', 'Telefone', 'texto'),
                self::campo('document', 'Documento (BI/NIF)', 'texto'),
                self::campo('work_start', 'Entrada', 'hora', omissao: '08:00'),
                self::campo('work_end', 'Saída', 'hora', omissao: '17:00'),
                self::campo('working_days', 'Dias de trabalho', 'dias', omissao: [1, 2, 3, 4, 5, 6], largura: 'inteira'),
                self::campo('birth_date', 'Data de nascimento', 'data'),
                self::campo('hire_date', 'Data de admissão', 'data'),
                self::campo('hourly_rate', 'Preço por hora (Kz)', 'numero', omissao: 0, passo: 0.01, min: 0),
                self::campo('monthly_salary', 'Salário mensal (Kz)', 'numero', omissao: 0, passo: 0.01, min: 0),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('skills', 'Competências', 'texto', largura: 'inteira'),
                self::campo('address', 'Morada', 'textarea', largura: 'inteira'),
                self::campo('notes', 'Notas', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'position' => 'required|string|max:50',
                'department' => 'required|string|max:50',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'document' => 'nullable|string|max:50',
                'work_start' => 'nullable|date_format:H:i',
                'work_end' => 'nullable|date_format:H:i',
                'working_days' => 'nullable|array',
                'working_days.*' => 'integer|min:1|max:7',
                'birth_date' => 'nullable|date',
                'hire_date' => 'nullable|date',
                'hourly_rate' => 'nullable|numeric|min:0',
                'monthly_salary' => 'nullable|numeric|min:0',
                'skills' => 'nullable|string|max:500',
                'address' => 'nullable|string|max:2000',
                'notes' => 'nullable|string|max:2000',
                'is_active' => 'boolean',
            ],
            'validar' => function (array $d): array {
                $funcoes = array_keys(\App\Models\Hotel\Staff::POSITIONS);
                $areas = array_keys(\App\Models\Hotel\Staff::DEPARTMENTS);

                if (! in_array($d['position'] ?? '', $funcoes, true)) {
                    return ['position' => __('Essa função não existe.')];
                }

                return in_array($d['department'] ?? '', $areas, true)
                    ? []
                    : ['department' => __('Esse departamento não existe.')];
            },
            'preparar' => fn (array $d) => array_merge($d, [
                'working_days' => array_values(array_unique(array_map('intval', $d['working_days'] ?? []))) ?: [1, 2, 3, 4, 5, 6],
                'birth_date' => ($d['birth_date'] ?? '') ?: null,
                'hire_date' => ($d['hire_date'] ?? '') ?: null,
                'hourly_rate' => ($d['hourly_rate'] ?? '') === '' ? 0 : (float) $d['hourly_rate'],
                'monthly_salary' => ($d['monthly_salary'] ?? '') === '' ? 0 : (float) $d['monthly_salary'],
            ]),
            /*
             * QUEM TEM ORDENS DE MANUTENÇÃO ATRIBUÍDAS NÃO SE APAGA — guarda
             * nova. Apagá-lo deixava a ordem sem responsável.
             */
            'pode_apagar' => fn (Model $m) => ! \App\Models\Hotel\MaintenanceOrder::where('assigned_to', $m->id)->exists(),
            'porque_nao_apaga' => 'Há ordens de manutenção atribuídas a esta pessoa.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => true, 'apagar' => true, 'importar' => true],
            'imagem' => ['coluna' => 'photo', 'pasta' => 'hotel/staff', 'prefixo' => 'foto', 'rotulo' => 'Fotografia'],
            /*
             * IMPORTAR DE RH — o botão que o ecrã de sempre tinha. O hotel não
             * contrata duas vezes a mesma pessoa: ela já está na ficha de
             * pessoal do RH.
             */
            'importar' => [
                'modelo' => \App\Models\HR\Employee::class,
                'botao' => 'Importar de RH',
                'titulo' => 'Importar pessoal do RH',
                'nada' => 'Não há funcionários ao serviço nesta empresa.',
                'pesquisa' => ['first_name', 'last_name', 'employee_number'],
                'pesquisa_ajuda' => 'Nome ou número',
                'onde' => fn ($q) => $q->where('status', 'active'),
                'nome' => fn (Model $e) => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: ($e->full_name ?? '—'),
                'nota' => fn (Model $e) => $e->employee_number,
                /*
                 * JÁ CÁ ESTÁ quem foi importado antes — e a ligação é o
                 * `hr_employee_id`, que a coluna tem e o ecrã de sempre nunca
                 * preenchia: importar duas vezes criava duas fichas da mesma
                 * pessoa, sem maneira de as juntar.
                 */
                'ja_ca' => function ($candidatos, int $tenantId): array {
                    return \App\Models\Hotel\Staff::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereIn('hr_employee_id', $candidatos->pluck('id'))
                        ->pluck('hr_employee_id')->all();
                },
                'mapear' => fn (Model $e) => [
                    'hr_employee_id' => $e->id,
                    'user_id' => $e->user_id,
                    'name' => $e->full_name ?: trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')),
                    'email' => $e->email,
                    'phone' => $e->phone,
                    'document' => $e->bi_number ?? $e->nif,
                    'address' => $e->address,
                    'birth_date' => $e->birth_date,
                    'hire_date' => $e->hire_date,
                    'monthly_salary' => $e->salary ?? 0,
                    // A função e o departamento do hotel não são os do RH:
                    // entram na omissão e corrigem-se na ficha. Era o que o
                    // ecrã de sempre fazia, e dizia-o num comentário.
                    'position' => 'other',
                    'department' => 'front_desk',
                    'working_days' => [1, 2, 3, 4, 5, 6],
                    'is_active' => true,
                ],
            ],
        ];
    }

    private const TIPOS_DE_PACOTE = [
        'romantic' => 'Romântico',
        'family' => 'Família',
        'business' => 'Negócios',
        'wellness' => 'Bem-estar',
        'adventure' => 'Aventura',
        'other' => 'Outro',
    ];

    private static function pacotes(): array
    {
        return [
            'modelo' => \App\Models\Hotel\Package::class,
            'titulo' => 'Pacotes',
            'singular' => 'Pacote',
            'icone' => 'fa-gift',
            'cor' => 'rosa',
            'descricao' => 'O que a casa vende além da noite — e o que está incluído',
            'novo' => 'Novo Pacote',
            'rota' => '/hotel/packages',
            'permissoes' => self::porVerbo('hotel.packages'),
            'pesquisa' => ['name', 'description'],
            'pesquisa_ajuda' => 'Nome ou descrição',
            'ordem' => [['priority', 'desc'], ['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Pacote', 'formato' => 'texto'],
                ['chave' => 'type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'price', 'rotulo' => 'Preço', 'formato' => 'dinheiro', 'alinhar' => 'direita'],
                ['chave' => 'min_nights', 'rotulo' => 'Noites mín.', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'valid_until', 'rotulo' => 'Válido até', 'formato' => 'validade'],
                ['chave' => 'included_services', 'rotulo' => 'Inclui', 'formato' => 'etiquetas'],
                ['chave' => 'show_online', 'rotulo' => 'No site', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'type', 'rotulo' => 'Tipo', 'opcoes' => self::escolhasDe(self::TIPOS_DE_PACOTE)],
                ['chave' => 'show_online', 'rotulo' => 'No site de reservas', 'opcoes' => [
                    ['valor' => '1', 'rotulo' => 'Só os que aparecem'],
                    ['valor' => '0', 'rotulo' => 'Só os escondidos'],
                ]],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'other',
                    opcoes: self::escolhasDe(self::TIPOS_DE_PACOTE)),
                self::campo('price', 'Preço do pacote (Kz)', 'numero', passo: 0.01, min: 0,
                    ajuda: 'Em branco quando o pacote é só um desconto.'),
                self::campo('discount_percentage', 'Desconto (%)', 'numero', passo: 0.01, min: 0, max: 100),
                self::campo('discount_amount', 'Desconto fixo (Kz)', 'numero', passo: 0.01, min: 0),
                self::campo('min_nights', 'Noites mínimas', 'numero', obrigatorio: true, omissao: 1, passo: 1, min: 1),
                self::campo('max_nights', 'Noites máximas', 'numero', passo: 1, min: 1),
                self::campo('valid_from', 'Válido de', 'data'),
                self::campo('valid_until', 'Válido até', 'validade'),
                self::campo('room_type_ids', 'Tipos de quarto', 'multi', omissao: [], referencia: 'tipos',
                    largura: 'inteira', ajuda: 'Sem escolha nenhuma, vale para todos.'),
                self::campo('included_services', 'Serviços incluídos', 'etiquetas', omissao: [], largura: 'inteira',
                    ajuda: 'Escreva e carregue em Enter — «Pequeno-almoço», «Transfer do aeroporto».'),
                self::campo('priority', 'Ordem', 'numero', omissao: 0, passo: 1, min: 0),
                self::campo('show_online', 'Mostrar no site de reservas', 'booleano', omissao: true),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'type' => 'required|in:romantic,family,business,wellness,adventure,other',
                'price' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'discount_amount' => 'nullable|numeric|min:0',
                'min_nights' => 'required|integer|min:1',
                'max_nights' => 'nullable|integer|min:1',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date',
                'room_type_ids' => 'array',
                'included_services' => 'array',
                'included_services.*' => 'string|max:120',
                'priority' => 'nullable|integer|min:0',
                'show_online' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:5000',
            ],
            'validar' => function (array $d): array {
                $de = $d['valid_from'] ?? null;
                $ate = $d['valid_until'] ?? null;

                // Um pacote que acaba antes de começar nunca aparece no site, e
                // ninguém percebe porquê.
                if ($de && $ate && $ate < $de) {
                    return ['valid_until' => __('A validade não pode acabar antes de começar.')];
                }

                $min = (int) ($d['min_nights'] ?? 1);
                $max = $d['max_nights'] ?? null;

                return ($max !== null && $max !== '' && (int) $max < $min)
                    ? ['max_nights' => __('O máximo de noites não pode ser menor do que o mínimo.')]
                    : [];
            },
            'preparar' => fn (array $d) => array_merge($d, [
                'price' => ($d['price'] ?? '') === '' ? null : (float) $d['price'],
                'discount_percentage' => ($d['discount_percentage'] ?? '') === '' ? null : (float) $d['discount_percentage'],
                'discount_amount' => ($d['discount_amount'] ?? '') === '' ? null : (float) $d['discount_amount'],
                'max_nights' => ($d['max_nights'] ?? '') === '' ? null : (int) $d['max_nights'],
                'valid_from' => ($d['valid_from'] ?? '') ?: null,
                'valid_until' => ($d['valid_until'] ?? '') ?: null,
                'priority' => (int) ($d['priority'] ?? 0),
                'included_services' => array_values(array_filter(array_map(
                    fn ($s) => trim((string) $s), $d['included_services'] ?? []
                ))),
                'room_type_ids' => array_values(array_map('intval', $d['room_type_ids'] ?? [])),
                // O `slug` é o que o site de reservas põe no endereço, e a
                // coluna existia sem ninguém a preencher.
                'slug' => \Illuminate\Support\Str::slug((string) ($d['name'] ?? '')) ?: null,
            ]),
            'referencias' => fn (int $t) => [
                'tipos' => \App\Models\Hotel\RoomType::withoutGlobalScopes()
                    ->where('tenant_id', $t)->orderBy('name')
                    ->get(['id', 'name'])->map(fn ($r) => ['valor' => (string) $r->id, 'rotulo' => $r->name])->all(),
            ],
            'pode_apagar' => fn (Model $m) => true,
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => true, 'apagar' => true],
            'imagem' => ['coluna' => 'image', 'pasta' => 'hotel/packages', 'prefixo' => 'pacote', 'rotulo' => 'Imagem do pacote'],
        ];
    }

    private static function codigosPromocionais(): array
    {
        return [
            'modelo' => \App\Models\Hotel\PromoCode::class,
            'titulo' => 'Códigos Promocionais',
            'singular' => 'Código Promocional',
            'icone' => 'fa-ticket',
            'cor' => 'aviso',
            'descricao' => 'Os códigos que dão desconto, e quantas vezes valem',
            'novo' => 'Novo Código',
            'rota' => '/hotel/packages',
            'permissoes' => self::porVerbo('hotel.packages'),
            'pesquisa' => ['code', 'name', 'description'],
            'pesquisa_ajuda' => 'Código, nome ou descrição',
            'ordem' => [['code', 'asc']],
            'colunas' => [
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'discount_type', 'rotulo' => 'Desconto', 'formato' => 'escolha'],
                ['chave' => 'discount_value', 'rotulo' => 'Valor', 'formato' => 'numero', 'alinhar' => 'direita'],
                /*
                 * QUANTAS VEZES JÁ FOI USADO — a coluna existia e o ecrã não a
                 * mostrava. É o número que decide se o código já deu o que
                 * tinha a dar.
                 */
                ['chave' => 'times_used', 'rotulo' => 'Usado', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'valid_until', 'rotulo' => 'Válido até', 'formato' => 'validade'],
            ],
            'filtros' => [
                ['chave' => 'discount_type', 'rotulo' => 'Tipo de desconto', 'opcoes' => [
                    ['valor' => 'percentage', 'rotulo' => 'Percentagem'],
                    ['valor' => 'fixed', 'rotulo' => 'Valor fixo'],
                ]],
            ],
            'campos' => [
                self::campo('code', 'Código', 'texto', obrigatorio: true, ajuda: 'É este que o hóspede escreve. Único nesta casa.'),
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('discount_type', 'Tipo de desconto', 'escolha', obrigatorio: true, omissao: 'percentage', opcoes: [
                    ['valor' => 'percentage', 'rotulo' => 'Percentagem'],
                    ['valor' => 'fixed', 'rotulo' => 'Valor fixo'],
                ]),
                self::campo('discount_value', 'Valor do desconto', 'numero', obrigatorio: true, omissao: 0, passo: 0.01, min: 0),
                self::campo('min_amount', 'Compra mínima (Kz)', 'numero', passo: 0.01, min: 0),
                self::campo('max_discount', 'Desconto máximo (Kz)', 'numero', passo: 0.01, min: 0,
                    ajuda: 'Trava uma percentagem que daria demasiado.'),
                self::campo('usage_limit', 'Limite de utilizações', 'numero', passo: 1, min: 1,
                    ajuda: 'Em branco é sem limite.'),
                self::campo('usage_per_customer', 'Por hóspede', 'numero', omissao: 1, passo: 1, min: 1),
                self::campo('valid_from', 'Válido de', 'data'),
                self::campo('valid_until', 'Válido até', 'validade'),
                self::campo('room_type_ids', 'Tipos de quarto', 'multi', omissao: [], referencia: 'tipos',
                    largura: 'inteira', ajuda: 'Sem escolha nenhuma, vale para todos.'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'code' => 'required|string|max:50',
                'name' => 'required|string|min:2|max:255',
                'discount_type' => 'required|in:percentage,fixed',
                'discount_value' => 'required|numeric|min:0',
                'min_amount' => 'nullable|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'usage_per_customer' => 'nullable|integer|min:1',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date',
                'room_type_ids' => 'array',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:2000',
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Hotel\PromoCode::class, 'Já existe um código promocional com esse código.'),
                /*
                 * UMA PERCENTAGEM ACIMA DE 100 é uma estadia de graça com
                 * troco. A regra `numeric|min:0` deixava passar 500%.
                 */
                function (array $d): array {
                    return ($d['discount_type'] ?? '') === 'percentage' && (float) ($d['discount_value'] ?? 0) > 100
                        ? ['discount_value' => __('Uma percentagem não passa dos 100%.')]
                        : [];
                },
            ]),
            'preparar' => fn (array $d) => array_merge($d, [
                'code' => mb_strtoupper(trim((string) ($d['code'] ?? ''))),
                'min_amount' => ($d['min_amount'] ?? '') === '' ? null : (float) $d['min_amount'],
                'max_discount' => ($d['max_discount'] ?? '') === '' ? null : (float) $d['max_discount'],
                'usage_limit' => ($d['usage_limit'] ?? '') === '' ? null : (int) $d['usage_limit'],
                'usage_per_customer' => ($d['usage_per_customer'] ?? '') === '' ? 1 : (int) $d['usage_per_customer'],
                'valid_from' => ($d['valid_from'] ?? '') ?: null,
                'valid_until' => ($d['valid_until'] ?? '') ?: null,
                'room_type_ids' => array_values(array_map('intval', $d['room_type_ids'] ?? [])),
            ]),
            'referencias' => fn (int $t) => [
                'tipos' => \App\Models\Hotel\RoomType::withoutGlobalScopes()
                    ->where('tenant_id', $t)->orderBy('name')
                    ->get(['id', 'name'])->map(fn ($r) => ['valor' => (string) $r->id, 'rotulo' => $r->name])->all(),
            ],
            /*
             * UM CÓDIGO JÁ USADO NÃO SE APAGA — guarda nova. As reservas que o
             * usaram ficariam com um desconto sem explicação.
             */
            'pode_apagar' => fn (Model $m) => (int) $m->times_used === 0,
            'porque_nao_apaga' => 'Este código já foi usado em reservas.',
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    private const MODIFICADORES_DA_EPOCA = [
        'multiplier' => 'Multiplicador (× sobre o preço)',
        'percentage' => 'Percentagem (+/- %)',
        'fixed' => 'Valor fixo (Kz)',
    ];

    private static function epocasDoHotel(): array
    {
        return [
            'modelo' => \App\Models\Hotel\RateSeason::class,
            'titulo' => 'Épocas',
            'singular' => 'Época',
            'icone' => 'fa-calendar-week',
            'cor' => 'teal',
            'descricao' => 'Época alta, época baixa — o que muda o preço da noite',
            'novo' => 'Nova Época',
            'rota' => '/hotel/seasons',
            'permissoes' => self::porVerbo('hotel.rates'),
            'pesquisa' => ['name', 'description'],
            'pesquisa_ajuda' => 'Nome ou descrição',
            'ordem' => [['start_date', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Época', 'formato' => 'texto'],
                ['chave' => 'start_date', 'rotulo' => 'De', 'formato' => 'data'],
                ['chave' => 'end_date', 'rotulo' => 'Até', 'formato' => 'data'],
                ['chave' => 'modifier_type', 'rotulo' => 'Como muda', 'formato' => 'escolha'],
                ['chave' => 'price_modifier', 'rotulo' => 'Valor', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'priority', 'rotulo' => 'Prioridade', 'formato' => 'numero', 'alinhar' => 'direita'],
                ['chave' => 'color', 'rotulo' => 'Cor', 'formato' => 'cor'],
            ],
            'filtros' => [
                ['chave' => 'modifier_type', 'rotulo' => 'Como muda', 'opcoes' => self::escolhasDe(self::MODIFICADORES_DA_EPOCA)],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('start_date', 'De', 'data', obrigatorio: true),
                self::campo('end_date', 'Até', 'data', obrigatorio: true),
                self::campo('modifier_type', 'Como muda o preço', 'escolha', obrigatorio: true, omissao: 'multiplier',
                    opcoes: self::escolhasDe(self::MODIFICADORES_DA_EPOCA)),
                self::campo('price_modifier', 'Valor', 'numero', obrigatorio: true, omissao: 1, passo: 0.01, min: 0,
                    ajuda: 'No multiplicador, 1,5 é «mais 50%».'),
                /*
                 * A PRIORIDADE DECIDE QUEM GANHA quando duas épocas se
                 * sobrepõem — e sobrepõem-se sempre (o Natal cai dentro da
                 * época alta). O ecrã de sempre tinha o campo sem dizer para
                 * que servia.
                 */
                self::campo('priority', 'Prioridade', 'numero', omissao: 0, passo: 1, min: 0,
                    ajuda: 'Quando duas épocas se sobrepõem, ganha a de prioridade mais alta.'),
                self::campo('color', 'Cor no calendário', 'cor', omissao: '#3b82f6'),
                self::campo('is_active', 'Activa', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'name' => 'required|string|min:2|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'price_modifier' => 'required|numeric|min:0',
                'modifier_type' => 'required|in:multiplier,percentage,fixed',
                'priority' => 'nullable|integer|min:0',
                'color' => 'nullable|string|max:7',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:2000',
            ],
            'preparar' => fn (array $d) => array_merge($d, [
                'priority' => (int) ($d['priority'] ?? 0),
                'price_modifier' => (float) ($d['price_modifier'] ?? 1),
            ]),
            'pode_apagar' => fn (Model $m) => true,
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /* ─── Os da contabilidade ──────────────────────────────────────────── */

    /** As contas que recebem movimento — nem bloqueadas nem de agregação. */
    private static function contasDeMovimento(int $tenantId): array
    {
        return \App\Models\Accounting\Account::where('tenant_id', $tenantId)
            ->where('blocked', false)->where('is_view', false)
            ->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->all();
    }

    /**
     * OS DIÁRIOS — por onde entram os lançamentos.
     *
     * O QUE ESTAVA PARTIDO: `Journal::find($id)` no editar e no apagar, sem
     * olhar à empresa — o diário de outra companhia editava-se e apagava-se
     * pelo id. E apagar um diário com LANÇAMENTOS em cima deixava-os a apontar
     * para um id que já não existe.
     *
     * O CÓDIGO tinha de ser único e não era: a base tem o índice
     * `(tenant_id, code)` e repetir dava um 1062 cru.
     */
    private static function diarios(): array
    {
        $tipos = [
            ['valor' => 'sale', 'rotulo' => 'Vendas'],
            ['valor' => 'purchase', 'rotulo' => 'Compras'],
            ['valor' => 'cash', 'rotulo' => 'Caixa'],
            ['valor' => 'bank', 'rotulo' => 'Banco'],
            ['valor' => 'payroll', 'rotulo' => 'Salários'],
            ['valor' => 'adjustment', 'rotulo' => 'Ajustes'],
            /*
             * `general` está no `enum` da base e em diários a sério — o
             * apuramento de resultados procura-o por `type = 'general'` — e
             * faltava na lista: abrir um diário geral e carregar em Guardar
             * dava erro de validação sobre o valor que o registo já tinha.
             */
            ['valor' => 'general', 'rotulo' => 'Operações diversas'],
        ];

        return [
            'modelo' => \App\Models\Accounting\Journal::class,
            'titulo' => 'Diários',
            'singular' => 'Diário',
            'icone' => 'fa-book',
            'cor' => 'bom',
            'descricao' => 'Por onde entram os lançamentos',
            'novo' => 'Novo Diário',
            'rota' => '/accounting/journals',
            'permissoes' => [
                'ver' => 'accounting.journals.view', 'criar' => 'accounting.journals.manage',
                'editar' => 'accounting.journals.manage', 'apagar' => 'accounting.journals.manage',
            ],
            'pesquisa' => ['code', 'name'],
            'pesquisa_ajuda' => 'Código ou nome do diário',
            'ordem' => [['code', 'asc']],
            'colunas' => [
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'sequence_prefix', 'rotulo' => 'Prefixo', 'formato' => 'texto'],
                ['chave' => 'last_number', 'rotulo' => 'Último nº', 'formato' => 'numero'],
                ['chave' => 'active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'type', 'rotulo' => 'Tipo', 'opcoes' => $tipos],
            ],
            'campos' => [
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'general', opcoes: $tipos),
                self::campo('sequence_prefix', 'Prefixo da referência', 'texto', obrigatorio: true, omissao: 'DG-',
                    ajuda: 'O que vem antes do número: «DG-» dá «DG-00001».'),
                /*
                 * O ÚLTIMO NÚMERO mexe-se, mas com aviso: é o contador da
                 * referência, e baixá-lo faria o diário tentar repetir
                 * referências já usadas. Quem grava salta as tomadas, mas o
                 * campo diz para que serve em vez de ser um número solto.
                 */
                self::campo('last_number', 'Último número usado', 'numero', omissao: 0, min: 0,
                    ajuda: 'A referência seguinte sai daqui. Só se mexe ao trazer numeração de outro sistema.'),
                self::campo('default_debit_account_id', 'Conta de débito por omissão', 'referencia', referencia: 'contas'),
                self::campo('default_credit_account_id', 'Conta de crédito por omissão', 'referencia', referencia: 'contas'),
                self::campo('active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'code' => 'required|max:20',
                'name' => 'required|max:255',
                'type' => 'required|in:sale,purchase,cash,bank,payroll,adjustment,general',
                'sequence_prefix' => 'required|max:10',
                'last_number' => 'nullable|integer|min:0',
                'default_debit_account_id' => 'nullable|integer',
                'default_credit_account_id' => 'nullable|integer',
                'active' => 'boolean',
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Accounting\Journal::class, 'Já existe um diário com esse código.'),
                // AS CONTAS SÃO DESTA EMPRESA: `nullable|integer` aceitava o id
                // da conta de outra companhia, e o diário ficava a apontar para
                // fora de casa sem erro nenhum.
                self::daCasa('default_debit_account_id', \App\Models\Accounting\Account::class, 'Conta não encontrada nesta empresa.'),
                self::daCasa('default_credit_account_id', \App\Models\Accounting\Account::class, 'Conta não encontrada nesta empresa.'),
            ]),
            'preparar' => fn (array $d) => array_merge($d, ['last_number' => (int) ($d['last_number'] ?? 0)]),
            // UM DIÁRIO COM LANÇAMENTOS NÃO DESAPARECE: os lançamentos ficariam
            // a apontar para um id que não existe. Desactiva-se.
            'pode_apagar' => fn (Model $m) => ! \App\Models\Accounting\Move::where('journal_id', $m->id)->exists()
                && ! \App\Models\Accounting\DocumentType::where('journal_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há lançamentos ou tipos de documento neste diário. Desactive-o em vez de o apagar.',
            'referencias' => fn (int $t) => ['contas' => self::contasDeMovimento($t)],
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /**
     * OS TIPOS DE DOCUMENTO — o que cada documento faz aos mapas legais.
     *
     * As bandeiras não são enfeite: dizem se o documento entra nos mapas
     * recapitulativos, se leva retenção na fonte, e se conta para o balanço
     * financeiro ou para o analítico. É por elas que os mapas da AGT saem
     * certos, e por isso vêem-se na lista e filtram-se.
     */
    private static function tiposDeDocumento(): array
    {
        return [
            'modelo' => \App\Models\Accounting\DocumentType::class,
            'titulo' => 'Tipos de Documento',
            'singular' => 'Tipo de documento',
            'icone' => 'fa-file-lines',
            'cor' => 'ciano',
            'descricao' => 'O que cada documento faz aos mapas legais',
            'novo' => 'Novo Tipo de Documento',
            'rota' => '/accounting/document-types',
            'nome' => 'description',
            'permissoes' => [
                'ver' => 'accounting.document-types.view', 'criar' => 'accounting.document-types.manage',
                'editar' => 'accounting.document-types.manage', 'apagar' => 'accounting.document-types.manage',
            ],
            'pesquisa' => ['code', 'description'],
            'pesquisa_ajuda' => 'Código ou descrição',
            'ordem' => [['display_order', 'asc'], ['code', 'asc']],
            'colunas' => [
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'description', 'rotulo' => 'Descrição', 'formato' => 'texto'],
                ['chave' => 'journal_id', 'rotulo' => 'Diário', 'formato' => 'referencia'],
                ['chave' => 'recapitulativos', 'rotulo' => 'Recapitulativos', 'formato' => 'booleano'],
                ['chave' => 'retencao_fonte', 'rotulo' => 'Retenção', 'formato' => 'booleano'],
                ['chave' => 'bal_financeira', 'rotulo' => 'Bal. financeira', 'formato' => 'booleano'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'journal_id', 'rotulo' => 'Diário', 'referencia' => 'diarios'],
                ['chave' => 'recapitulativos', 'rotulo' => 'Recapitulativos', 'opcoes' => self::SIM_OU_NAO],
                ['chave' => 'retencao_fonte', 'rotulo' => 'Retenção na fonte', 'opcoes' => self::SIM_OU_NAO],
                ['chave' => 'bal_financeira', 'rotulo' => 'Balanço financeiro', 'opcoes' => self::SIM_OU_NAO],
            ],
            'campos' => [
                self::campo('code', 'Código', 'texto', obrigatorio: true, ajuda: 'Até 10 caracteres.'),
                self::campo('description', 'Descrição', 'texto', obrigatorio: true),
                self::campo('journal_id', 'Diário', 'referencia', referencia: 'diarios'),
                self::campo('journal_code', 'Código do diário', 'texto',
                    ajuda: 'O código que o sistema antigo usava, quando é diferente do diário escolhido.'),
                self::campo('display_order', 'Ordem', 'numero', omissao: 0, min: 0),
                self::campo('recapitulativos', 'Entra nos mapas recapitulativos', 'booleano'),
                self::campo('retencao_fonte', 'Leva retenção na fonte', 'booleano'),
                self::campo('bal_financeira', 'Conta para o balanço financeiro', 'booleano', omissao: true),
                self::campo('bal_analitica', 'Conta para o balanço analítico', 'booleano'),
                self::campo('rec_informacao', 'Recolha de informação', 'numero', omissao: 0, min: 0),
                self::campo('tipo_doc_imo', 'Tipo de documento do imobilizado', 'numero', omissao: 0, min: 0),
                self::campo('calculo_fluxo_caixa', 'Cálculo do fluxo de caixa', 'numero', omissao: 0, min: 0),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
            ],
            'regras' => [
                'code' => 'required|string|max:10',
                'description' => 'required|string|max:255',
                'journal_id' => 'nullable|integer',
                'journal_code' => 'nullable|string|max:20',
                'display_order' => 'nullable|integer|min:0',
                'recapitulativos' => 'boolean',
                'retencao_fonte' => 'boolean',
                'bal_financeira' => 'boolean',
                'bal_analitica' => 'boolean',
                'rec_informacao' => 'nullable|integer|min:0',
                'tipo_doc_imo' => 'nullable|integer|min:0',
                'calculo_fluxo_caixa' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Accounting\DocumentType::class, 'Já existe um tipo de documento com esse código.'),
                // O DIÁRIO É DESTA EMPRESA. A regra era `exists:accounting_journals,id`
                // sem empresa: apontava para o diário de outra companhia.
                self::daCasa('journal_id', \App\Models\Accounting\Journal::class, 'Diário não encontrado nesta empresa.'),
            ]),
            'preparar' => fn (array $d) => array_merge($d, [
                'display_order' => (int) ($d['display_order'] ?? 0),
                'rec_informacao' => (int) ($d['rec_informacao'] ?? 0),
                'tipo_doc_imo' => (int) ($d['tipo_doc_imo'] ?? 0),
                'calculo_fluxo_caixa' => (int) ($d['calculo_fluxo_caixa'] ?? 0),
            ]),
            // Um tipo já usado num lançamento não desaparece: o lançamento
            // deixaria de saber o que era.
            'pode_apagar' => fn (Model $m) => ! \App\Models\Accounting\Move::where('document_type_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há lançamentos deste tipo de documento. Desactive-o em vez de o apagar.',
            'referencias' => fn (int $t) => [
                'diarios' => \App\Models\Accounting\Journal::where('tenant_id', $t)->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->map(fn ($j) => ['valor' => (string) $j->id, 'rotulo' => $j->code.' · '.$j->name])->all(),
            ],
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /**
     * OS CENTROS DE CUSTO — onde o gasto foi feito.
     *
     * O QUE ESTAVA PARTIDO: não havia como APAGAR nem como DESACTIVAR. Pior: o
     * gravar forçava `is_active => true` em toda a edição, pelo que um centro
     * desactivado por outra via voltava a ficar activo sem ninguém pedir. E a
     * lista só mostrava os de raiz — um centro pendurado noutro não aparecia em
     * lado nenhum, e não havia como o editar.
     */
    private static function centrosDeCusto(): array
    {
        $tipos = [
            ['valor' => 'revenue', 'rotulo' => 'Proveito'],
            ['valor' => 'cost', 'rotulo' => 'Custo'],
            ['valor' => 'support', 'rotulo' => 'Apoio'],
        ];

        return [
            'modelo' => \App\Models\Accounting\CostCenter::class,
            'titulo' => 'Centros de Custo',
            'singular' => 'Centro de custo',
            'icone' => 'fa-building',
            'cor' => 'roxo',
            'descricao' => 'Onde o gasto foi feito',
            'novo' => 'Novo Centro de Custo',
            'rota' => '/accounting/cost-centers',
            'permissoes' => [
                'ver' => 'accounting.cost-centers.view', 'criar' => 'accounting.cost-centers.manage',
                'editar' => 'accounting.cost-centers.manage', 'apagar' => 'accounting.cost-centers.manage',
            ],
            'pesquisa' => ['code', 'name'],
            'pesquisa_ajuda' => 'Código ou nome',
            'ordem' => [['code', 'asc']],
            'colunas' => [
                ['chave' => 'code', 'rotulo' => 'Código', 'formato' => 'texto'],
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'type', 'rotulo' => 'Tipo', 'formato' => 'escolha'],
                ['chave' => 'parent_id', 'rotulo' => 'Centro-mãe', 'formato' => 'referencia'],
                ['chave' => 'is_active', 'rotulo' => 'Activo', 'formato' => 'booleano'],
            ],
            'filtros' => [
                ['chave' => 'type', 'rotulo' => 'Tipo', 'opcoes' => $tipos],
                ['chave' => 'nivel', 'rotulo' => 'Nível', 'opcoes' => [
                    ['valor' => 'principal', 'rotulo' => 'De raiz'],
                    ['valor' => 'filha', 'rotulo' => 'Pendurado noutro'],
                ]],
            ],
            'campos' => [
                self::campo('code', 'Código', 'texto', obrigatorio: true),
                self::campo('name', 'Nome', 'texto', obrigatorio: true),
                self::campo('type', 'Tipo', 'escolha', obrigatorio: true, omissao: 'cost', opcoes: $tipos),
                self::campo('parent_id', 'Centro-mãe', 'referencia', referencia: 'centros'),
                self::campo('is_active', 'Activo', 'booleano', omissao: true),
                self::campo('description', 'Descrição', 'textarea', largura: 'inteira'),
            ],
            'regras' => [
                'code' => 'required|max:50',
                'name' => 'required|max:255',
                'type' => 'required|in:revenue,cost,support',
                'parent_id' => 'nullable|integer',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:2000',
            ],
            'validar' => self::tudoIsto([
                self::codigoUnico(\App\Models\Accounting\CostCenter::class, 'Já existe um centro de custo com esse código.'),
                self::daCasa('parent_id', \App\Models\Accounting\CostCenter::class, 'Centro-mãe não encontrado nesta empresa.'),
                // NÃO SE PENDURA EM SI PRÓPRIO: fazia um ciclo que deitava
                // abaixo qualquer percurso da árvore.
                function (array $d, ?Model $m, int $t) {
                    return $m && ! empty($d['parent_id']) && (int) $d['parent_id'] === (int) $m->id
                        ? ['parent_id' => __('Um centro de custo não se pendura em si próprio.')]
                        : [];
                },
            ]),
            // Um centro com FILHOS ou já usado numa conta não desaparece.
            'pode_apagar' => fn (Model $m) => ! \App\Models\Accounting\CostCenter::where('parent_id', $m->id)->exists()
                && ! \App\Models\Accounting\Account::where('default_cost_center_id', $m->id)->exists()
                && ! \App\Models\Accounting\Budget::where('cost_center_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há centros pendurados neste, contas ou orçamentos que o usam. Desactive-o em vez de o apagar.',
            'referencias' => fn (int $t) => [
                'centros' => \App\Models\Accounting\CostCenter::where('tenant_id', $t)->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->all(),
            ],
            'accoes' => ['activar' => true, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /**
     * AS FAMÍLIAS DO IMOBILIZADO — «Viaturas», «Equipamento informático».
     *
     * O que elas guardam são as OMISSÕES da família: quantos anos dura, por que
     * método se amortiza e a que taxa. Um bem novo herda-as, em vez de obrigar
     * quem o registou a saber a vida útil de cor.
     *
     * A tabela existia desde 2025 e não tinha ecrã nenhum: o campo «Categoria» do
     * imobilizado apontava para uma lista que ninguém podia preencher.
     */
    private static function familiasDoImobilizado(): array
    {
        $metodos = [
            ['valor' => 'linear', 'rotulo' => 'Quotas constantes'],
            ['valor' => 'declining_balance', 'rotulo' => 'Quotas degressivas'],
        ];

        return [
            'modelo' => \App\Models\Accounting\FixedAssetCategory::class,
            'titulo' => 'Famílias do Imobilizado',
            'singular' => 'Família',
            'icone' => 'fa-layer-group',
            'cor' => 'roxo',
            'descricao' => 'As omissões de cada família de bens',
            'novo' => 'Nova Família',
            'rota' => '/accounting/fixed-asset-categories',
            'permissoes' => [
                'ver' => 'accounting.fixed-assets.view', 'criar' => 'accounting.fixed-assets.manage',
                'editar' => 'accounting.fixed-assets.manage', 'apagar' => 'accounting.fixed-assets.manage',
            ],
            'pesquisa' => ['name'],
            'pesquisa_ajuda' => 'Nome da família',
            'ordem' => [['name', 'asc']],
            'colunas' => [
                ['chave' => 'name', 'rotulo' => 'Nome', 'formato' => 'texto'],
                ['chave' => 'default_useful_life', 'rotulo' => 'Vida útil (anos)', 'formato' => 'numero'],
                ['chave' => 'default_depreciation_method', 'rotulo' => 'Método', 'formato' => 'escolha'],
                ['chave' => 'default_depreciation_rate', 'rotulo' => 'Taxa', 'formato' => 'percentagem'],
            ],
            'filtros' => [
                ['chave' => 'default_depreciation_method', 'rotulo' => 'Método', 'opcoes' => $metodos],
            ],
            'campos' => [
                self::campo('name', 'Nome', 'texto', obrigatorio: true, ajuda: 'Ex.: Viaturas, Equipamento informático'),
                self::campo('default_useful_life', 'Vida útil por omissão (anos)', 'numero', obrigatorio: true, omissao: 5, min: 1, max: 100),
                self::campo('default_depreciation_method', 'Método por omissão', 'escolha', obrigatorio: true, omissao: 'linear', opcoes: $metodos),
                self::campo('default_depreciation_rate', 'Taxa anual (%)', 'percentagem', min: 0, max: 100,
                    ajuda: 'Só as quotas degressivas a usam. Vazio usa o dobro da quota linear.'),
            ],
            'regras' => [
                'name' => 'required|string|max:255',
                'default_useful_life' => 'required|integer|min:1|max:100',
                'default_depreciation_method' => 'required|in:linear,declining_balance',
                'default_depreciation_rate' => 'nullable|numeric|min:0|max:100',
            ],
            'validar' => self::codigoUnico(
                \App\Models\Accounting\FixedAssetCategory::class,
                'Já existe uma família com esse nome.',
                'name',
            ),
            // Uma família com bens não desaparece: os bens ficariam sem as
            // omissões que herdaram e sem nome de família nenhum.
            'pode_apagar' => fn (Model $m) => ! \App\Models\Accounting\FixedAsset::where('category_id', $m->id)->exists(),
            'porque_nao_apaga' => 'Há bens nesta família.',
            'accoes' => ['activar' => false, 'padrao' => false, 'logotipo' => false, 'apagar' => true],
        ];
    }

    /** As duas respostas de um filtro sobre uma bandeira. */
    private const SIM_OU_NAO = [
        ['valor' => '1', 'rotulo' => 'Sim'],
        ['valor' => '0', 'rotulo' => 'Não'],
    ];

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

            /*
             * UMA HORA SAI `08:00`, e não a data inteira.
             *
             * O modelo do turno converte `start_time` para Carbon; em JSON
             * isso vai como `2026-09-08T08:00:00Z`, que o `<input type="time">`
             * não sabe ler — o campo abria vazio e gravar apagava a hora que
             * lá estava.
             */
            if (($c['tipo'] ?? '') === 'hora') {
                $linha[$c['chave']] = $valor instanceof \DateTimeInterface
                    ? $valor->format('H:i')
                    : ($valor ? substr((string) $valor, 0, 5) : null);
            }

            /*
             * UMA DATA SAI `2026-09-09`, pela mesma razão que a hora.
             *
             * O modelo converte-a para Carbon e em JSON ela vai com hora e
             * fuso; o `<input type="date">` não a lê, e o campo abria vazio.
             */
            if (in_array($c['tipo'] ?? '', ['data', 'validade'], true)) {
                $linha[$c['chave']] = $valor instanceof \DateTimeInterface
                    ? $valor->format('Y-m-d')
                    : ($valor ? substr((string) $valor, 0, 10) : null);
            }

            if ($c['tipo'] === 'escolha') {
                // As opções podem vir de uma referência (os estados de viatura de cada oficina).
                $opcoes = isset($c['referencia']) ? ($referencias[$c['referencia']] ?? []) : ($c['opcoes'] ?? []);
                $linha['rotulos'][$c['chave']] = collect($opcoes)->firstWhere('valor', (string) $valor)['rotulo'] ?? (string) $valor;
            } elseif ($c['tipo'] === 'referencia') {
                $linha['rotulos'][$c['chave']] = collect($referencias[$c['referencia']] ?? [])->firstWhere('valor', (string) $valor)['rotulo'] ?? '';
            } elseif ($c['tipo'] === 'pais') {
                $linha['rotulos'][$c['chave']] = Geografia::nomeDoPais($valor) ?? (string) $valor;
            }
        }

        /*
         * AS COLUNAS QUE NÃO SÃO CAMPOS — o número gerado da viatura, o código
         * do serviço.
         *
         * A linha nascia só dos CAMPOS DO FORMULÁRIO, e o que se mostra sem se
         * poder escrever ficava de fora: a coluna «Nº» da lista de viaturas
         * saía vazia em todas as linhas, sem erro nenhum a dizer porquê.
         */
        foreach ($def['colunas'] as $c) {
            if (! array_key_exists($c['chave'], $linha) && array_key_exists($c['chave'], $m->getAttributes())) {
                $linha[$c['chave']] = $m->{$c['chave']};
            }
        }

        foreach (['is_active', 'is_default'] as $extra) {
            if (array_key_exists($extra, $m->getAttributes())) {
                $linha[$extra] = $m->{$extra};
            }
        }

        /*
         * A IMAGEM SAI SEMPRE COMO `logo`, seja qual for a coluna.
         *
         * O ecrã desenha uma miniatura e um botão de trocar; a coluna é do
         * esquema — `logo` no fornecedor, `featured_image` no tipo de quarto,
         * `image` no pacote. Deixar o nome da coluna chegar ao browser obrigava
         * o ecrã a saber de que catálogo se trata, que é o contrário do que ele
         * é.
         */
        $coluna = $def['imagem']['coluna'] ?? 'logo';

        if (array_key_exists($coluna, $m->getAttributes())) {
            $linha['logo'] = $m->{$coluna}
                ? Storage::disk('public')->url($m->{$coluna})
                : null;
        }

        /*
         * A GALERIA — várias imagens numa coluna JSON.
         *
         * Cada uma vai com o CAMINHO e o URL: o URL para se ver, o caminho
         * para se poder apagar aquela e não «a terceira», que muda de sítio
         * assim que se apaga outra.
         */
        if (! empty($def['galeria'])) {
            $linha['galeria'] = collect($m->{$def['galeria']['coluna']} ?? [])
                ->filter()
                ->map(fn ($caminho) => [
                    'caminho' => $caminho,
                    'url' => Storage::disk('public')->url($caminho),
                ])->values()->all();
        }

        $linha['pode_apagar'] = $def['accoes']['apagar'] && ($def['pode_apagar'])($m);

        return $linha;
    }
}
