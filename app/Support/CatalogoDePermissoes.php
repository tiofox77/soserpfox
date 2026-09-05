<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * O catálogo de permissões, em português e por módulo.
 *
 * O ecrã de papéis mostrava 340 nomes técnicos em maiúsculas — `hotel.rooms.edit`
 * sem uma palavra a dizer o que é — e mostrava-os a TODAS as empresas, tivessem
 * ou não o módulo. Metade não tinha descrição na base. Isto é a fonte única
 * do que cada permissão significa e a que módulo pertence.
 *
 * Rótulo, por ordem: a descrição gravada na base; o catálogo do comando
 * modules:sync-permissions; e, por fim, uma frase montada a partir do nome
 * («Editar Quartos»). Nunca fica um nome cru.
 */
final class CatalogoDePermissoes
{
    /** Módulos e os prefixos das suas permissões — a mesma tabela do menu. */
    public const MODULOS = [
        'invoicing'     => ['nome' => 'Faturação',            'icone' => 'fa-file-invoice',     'prefixos' => ['invoicing.', 'customers.', 'products.']],
        'treasury'      => ['nome' => 'Tesouraria',           'icone' => 'fa-coins',            'prefixos' => ['treasury.']],
        'contabilidade' => ['nome' => 'Contabilidade',        'icone' => 'fa-book',             'prefixos' => ['accounting.']],
        'rh'            => ['nome' => 'Recursos Humanos',     'icone' => 'fa-id-badge',         'prefixos' => ['hr.', 'rh.', 'attendance.', 'payroll.', 'employees.']],
        'oficina'       => ['nome' => 'Oficina',              'icone' => 'fa-wrench',           'prefixos' => ['workshop.']],
        'hotel'         => ['nome' => 'Hotel',                'icone' => 'fa-hotel',            'prefixos' => ['hotel.']],
        'restaurant'    => ['nome' => 'Restaurante',          'icone' => 'fa-utensils',         'prefixos' => ['restaurant.']],
        'salon'         => ['nome' => 'Salão de Beleza',      'icone' => 'fa-cut',              'prefixos' => ['salon.']],
        'eventos'       => ['nome' => 'Eventos',              'icone' => 'fa-calendar-star',    'prefixos' => ['events.', 'eventos.']],
        'crm'           => ['nome' => 'CRM',                  'icone' => 'fa-handshake',        'prefixos' => ['crm.']],
        'inventario'    => ['nome' => 'Inventário',           'icone' => 'fa-clipboard-check',  'prefixos' => ['inventario.', 'inventory.']],
        'compras'       => ['nome' => 'Compras',              'icone' => 'fa-shopping-cart',    'prefixos' => ['compras.', 'purchases.']],
        'projetos'      => ['nome' => 'Projetos',             'icone' => 'fa-diagram-project',  'prefixos' => ['projetos.', 'projects.']],
        'notifications' => ['nome' => 'Notificações',         'icone' => 'fa-bell',             'prefixos' => ['notifications.']],
    ];

    /**
     * Grupos que não são módulos: vêm sempre, em qualquer empresa.
     * `billing` e `settings` são de quem gere a empresa.
     */
    public const NUCLEO = [
        'users'    => ['nome' => 'Utilizadores e Papéis',   'icone' => 'fa-users-cog', 'prefixos' => ['users.']],
        'empresa'  => ['nome' => 'Empresa e Pacote',        'icone' => 'fa-building',  'prefixos' => ['billing.', 'settings.']],
    ];

    /** Entidades: o meio do nome → como se lê. */
    private const ENTIDADES = [
        'dashboard' => 'Painel', 'reports' => 'Relatórios', 'settings' => 'Definições',
        'clients' => 'Clientes', 'customers' => 'Clientes', 'products' => 'Produtos', 'suppliers' => 'Fornecedores',
        'sales' => 'Vendas', 'invoices' => 'Faturas', 'proformas' => 'Proformas', 'quotes' => 'Orçamentos',
        'purchases' => 'Compras', 'receipts' => 'Recibos', 'credit-notes' => 'Notas de Crédito', 'debit-notes' => 'Notas de Débito',
        'advances' => 'Adiantamentos', 'documents' => 'Documentos', 'series' => 'Séries', 'taxes' => 'Impostos', 'saft' => 'SAF-T',
        'agt' => 'AGT', 'pos' => 'Ponto de Venda', 'stock' => 'Stock', 'warehouses' => 'Armazéns', 'warehouse-transfer' => 'Transferências entre Armazéns',
        'inter-company-transfer' => 'Transferências entre Empresas', 'imports' => 'Importações', 'brands' => 'Marcas', 'categories' => 'Categorias',
        'product-batches' => 'Lotes', 'payment-terms' => 'Condições de Pagamento',
        'accounts' => 'Contas', 'banks' => 'Bancos', 'cash-registers' => 'Caixas', 'payment-methods' => 'Métodos de Pagamento',
        'transactions' => 'Movimentos', 'transfers' => 'Transferências',
        'journals' => 'Diários', 'document-types' => 'Tipos de Documento', 'moves' => 'Lançamentos', 'periods' => 'Períodos',
        'reconciliation' => 'Reconciliação', 'fixed-assets' => 'Ativos Fixos', 'budgets' => 'Orçamentos', 'cost-centers' => 'Centros de Custo',
        'currencies' => 'Moedas', 'analytics' => 'Contabilidade Analítica',
        'rooms' => 'Quartos', 'room-types' => 'Tipos de Quarto', 'reservations' => 'Reservas', 'guests' => 'Hóspedes', 'checkout' => 'Check-out',
        'housekeeping' => 'Limpeza', 'maintenance' => 'Manutenção', 'packages' => 'Pacotes', 'rates' => 'Tarifas', 'staff' => 'Pessoal',
        'walk-in' => 'Entrada sem Reserva', 'calendar' => 'Calendário',
        'appointments' => 'Marcações', 'professionals' => 'Profissionais', 'services' => 'Serviços',
        'mechanics' => 'Mecânicos', 'parts' => 'Peças', 'vehicles' => 'Viaturas', 'work-orders' => 'Ordens de Trabalho', 'repairs' => 'Reparações',
        'floor' => 'Sala e Mesas', 'kitchen' => 'Cozinha', 'menu' => 'Carta', 'orders' => 'Pedidos', 'recipes' => 'Receitas',
        'leads' => 'Contactos', 'opportunities' => 'Oportunidades', 'integrations' => 'Integrações',
        'equipment' => 'Equipamentos', 'technicians' => 'Técnicos', 'types' => 'Tipos', 'venues' => 'Locais',
        'contagem' => 'Contagens', 'requisicoes' => 'Requisições', 'encomendas' => 'Encomendas',
        'tarefas' => 'Tarefas', 'horas' => 'Horas', 'roles' => 'Papéis e Permissões',
        'employees' => 'Funcionários', 'payroll' => 'Salários', 'attendance' => 'Assiduidade', 'payments' => 'Pagamentos',
        // Nomes de duas partes: o primeiro segmento é a própria entidade.
        'users' => 'Utilizadores', 'billing' => 'Pacote e Faturação da Empresa', 'notifications' => 'Notificações',
        'compras' => 'Compras', 'projetos' => 'Projetos', 'inventario' => 'Inventário', 'crm' => 'CRM',
        'hotel' => 'Hotel', 'salon' => 'Salão', 'workshop' => 'Oficina', 'restaurant' => 'Restaurante',
        'invoicing' => 'Faturação', 'treasury' => 'Tesouraria', 'accounting' => 'Contabilidade', 'hr' => 'Recursos Humanos', 'rh' => 'Recursos Humanos',
    ];

    /** Acções: a última parte do nome → o verbo. */
    private const ACCOES = [
        'view' => 'Ver', 'create' => 'Criar', 'edit' => 'Editar', 'delete' => 'Eliminar', 'manage' => 'Gerir',
        'export' => 'Exportar', 'import' => 'Importar', 'issue' => 'Emitir', 'cancel' => 'Anular', 'convert' => 'Converter',
        'send' => 'Enviar', 'generate' => 'Gerar', 'pdf' => 'Imprimir', 'process' => 'Processar', 'complete' => 'Concluir',
        'charge' => 'Cobrar', 'discount' => 'Dar Desconto', 'split' => 'Dividir', 'transfer' => 'Transferir', 'waste' => 'Registar Quebras',
        'invite' => 'Convidar', 'permissions' => 'Gerir Permissões', 'access' => 'Aceder', 'sell' => 'Vender', 'refund' => 'Devolver',
        'all' => 'Ver de Todos', 'reports' => 'Ver Relatórios', 'dashboard' => 'Ver Painel', 'settings' => 'Configurar', 'chart' => 'Ver Gráficos',
        'decidir' => 'Aprovar ou Rejeitar', 'receber' => 'Dar Entrada', 'registar' => 'Registar', 'gerir' => 'Gerir', 'facturar' => 'Faturar',
    ];

    /** Ordem em que as acções aparecem dentro de uma entidade. */
    private const ORDEM_DAS_ACCOES = ['view', 'create', 'edit', 'delete', 'manage', 'all'];

    /** @var array<string,string>|null cache do catálogo do sync */
    private static ?array $catalogoDoSync = null;

    /** O rótulo em português. Nunca devolve o nome cru. */
    public static function rotulo(Permission|string $permissao): string
    {
        $nome = $permissao instanceof Permission ? $permissao->name : $permissao;

        if ($permissao instanceof Permission && trim((string) $permissao->description) !== '') {
            return trim($permissao->description);
        }

        $doSync = self::catalogoDoSync()[$nome] ?? null;
        if ($doSync) {
            return $doSync;
        }

        return self::humanizar($nome);
    }

    /** «invoicing.sales.invoices.view» → «Ver Faturas de Venda». */
    public static function humanizar(string $nome): string
    {
        $partes = explode('.', $nome);
        $accao = array_pop($partes);

        // «customers.view» tem só duas partes: a primeira É a entidade, não
        // um módulo a saltar. Com três ou mais, a primeira é o módulo.
        if (count($partes) > 1) {
            array_shift($partes);
        }

        // «sales.invoices» lê-se «Faturas de Venda»; «purchases.invoices», «Faturas de Compra».
        $contexto = '';
        if (($partes[0] ?? null) === 'sales') {
            $contexto = ' de Venda';
            array_shift($partes);
        } elseif (($partes[0] ?? null) === 'purchases' && count($partes) > 1) {
            $contexto = ' de Compra';
            array_shift($partes);
        }

        $entidade = implode(' ', array_map(fn ($p) => self::ENTIDADES[$p] ?? ucfirst(str_replace('-', ' ', $p)), $partes));

        // «hotel.dashboard.view» → «Ver Painel»; «invoicing.pos.reports» → «Ver Relatórios do Ponto de Venda».
        $verbo = self::ACCOES[$accao] ?? ucfirst($accao);

        if ($entidade === '') {
            return $verbo;
        }

        return trim("{$verbo} {$entidade}{$contexto}");
    }

    /** A entidade de uma permissão, para agrupar no ecrã. */
    public static function entidade(string $nome): string
    {
        $partes = explode('.', $nome);
        array_pop($partes);

        if (count($partes) > 1) {
            array_shift($partes);
        }

        if (! $partes) {
            return 'Geral';
        }

        $contexto = '';
        if ($partes[0] === 'sales') {
            $contexto = ' de Venda';
            array_shift($partes);
        } elseif ($partes[0] === 'purchases' && count($partes) > 1) {
            $contexto = ' de Compra';
            array_shift($partes);
        }

        $entidade = implode(' ', array_map(fn ($p) => self::ENTIDADES[$p] ?? ucfirst(str_replace('-', ' ', $p)), $partes));

        return $entidade === '' ? 'Geral' : $entidade.$contexto;
    }

    public static function accao(string $nome): string
    {
        $partes = explode('.', $nome);

        return end($partes);
    }

    /** Posição da acção na linha: Ver, Criar, Editar, Eliminar, Gerir, o resto. */
    public static function ordemDaAccao(string $nome): int
    {
        $i = array_search(self::accao($nome), self::ORDEM_DAS_ACCOES, true);

        return $i === false ? 99 : $i;
    }

    /** A que grupo (módulo ou núcleo) uma permissão pertence, ou null se for lixo antigo. */
    public static function grupoDe(string $nome): ?string
    {
        foreach (self::NUCLEO + self::MODULOS as $slug => $grupo) {
            foreach ($grupo['prefixos'] as $prefixo) {
                if (str_starts_with($nome, $prefixo)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * Os grupos que uma empresa deve ver: o núcleo mais os módulos ACTIVOS.
     *
     * @param  iterable<string>  $modulosActivos  slugs
     * @return array<string, array{nome:string, icone:string, prefixos:array}>
     */
    public static function gruposPara(iterable $modulosActivos): array
    {
        $grupos = self::NUCLEO;
        $activos = is_array($modulosActivos) ? $modulosActivos : iterator_to_array($modulosActivos);

        foreach (self::MODULOS as $slug => $modulo) {
            if (in_array($slug, $activos, true)) {
                $grupos[$slug] = $modulo;
            }
        }

        return $grupos;
    }

    /**
     * As permissões de um grupo, já ordenadas por entidade e acção.
     *
     * @param  Collection<int, Permission>  $todas
     */
    public static function doGrupo(Collection $todas, array $grupo): Collection
    {
        return $todas
            ->filter(function (Permission $p) use ($grupo) {
                foreach ($grupo['prefixos'] as $prefixo) {
                    if (str_starts_with($p->name, $prefixo)) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy(fn (Permission $p) => self::entidade($p->name).'|'.str_pad((string) self::ordemDaAccao($p->name), 2, '0', STR_PAD_LEFT).'|'.$p->name)
            ->values();
    }

    /** @return array<string,string> nome → rótulo, do comando modules:sync-permissions */
    private static function catalogoDoSync(): array
    {
        if (self::$catalogoDoSync !== null) {
            return self::$catalogoDoSync;
        }

        try {
            $classe = \App\Console\Commands\SyncModulePermissions::class;
            $propriedade = new \ReflectionProperty($classe, 'modulePerms');
            $propriedade->setAccessible(true);
            self::$catalogoDoSync = (array) $propriedade->getValue(app($classe));
        } catch (\Throwable) {
            self::$catalogoDoSync = [];
        }

        return self::$catalogoDoSync;
    }
}
