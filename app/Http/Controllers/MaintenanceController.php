<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Endpoints HTTP para executar migrations/seeders/comandos remotamente.
 * Protegido por token em .env: MAINTENANCE_TOKEN
 *
 * Whitelist de comandos para evitar abuso destrutivo.
 *
 * Uso:
 *   GET /maintenance/{token}/migrate
 *   GET /maintenance/{token}/seed/PlanSeeder
 *   GET /maintenance/{token}/command/plans:update-pricing
 *   GET /maintenance/{token}/command/plans:update-pricing?args=--dry
 */
class MaintenanceController extends Controller
{
    /**
     * Comandos artisan permitidos por whitelist.
     */
    protected array $allowedCommands = [
        'plans:update-pricing',
        'create:fox-friendly-plan',
        // Acerta o fim do período de uma subscrição (a seco por omissão;
        // escreve só com --aplicar). Mostra também as subscrições todas da
        // empresa — é como se vê uma empresa com duas vivas.
        'subscricao:ajustar',
        // Quem já gastou o plano gratuito ou o período de teste. Só contagens.
        'cortesias:estado',
        'admin:set-email',
        'tenants:delete',

        // A cadeia de assinaturas SAFT-AO, conferida documento a documento.
        // SÓ LÊ — é um relatório, não corrige nada.
        //
        // O irmão dele, o `agt:reparar-elos`, fica DE FORA desta lista de
        // propósito: escreve em documentos fiscais já emitidos, e uma coisa
        // dessas não se dispara por um endereço no browser. Corre-se por
        // linha de comando, a seco primeiro, e com alguém a ler o resultado.
        'agt:verificar-cadeia',

        // O inventário dos aparelhos com PWA e a versão de cada um.
        // SÓ LÊ — é a forma de confirmar, depois de um deploy, se os
        // aparelhos apanharam a versão nova.
        'pwa:aparelhos',

        // Os países e províncias gravados nas moradas, e o que a AGT não
        // aceitaria. SÓ LÊ — serve para confirmar, antes e depois da migração,
        // que nenhum país ficou escrito por extenso.
        'geografia:diagnostico',

        // As empresas cujo país veio do DEFAULT da coluna («Portugal») e não
        // de uma escolha. SÓ MOSTRA; escreve com `--aplicar`, e isso é uma
        // ordem do dono — é o país de empresas reais, e sai em documentos.
        'geografia:pais-da-empresa',

        // A empresa de ensaio em produção. Cria uma empresa e uma equipa com
        // credenciais GERADAS, que aparecem uma vez na resposta — não há nada
        // fixo em código, ao contrário do `bancada:pwa`.
        //
        // Está aqui porque não há outra forma de a correr (só FTP e estas
        // rotas), e porque se apaga pelo mesmo caminho: `?args=--limpar`.
        // Uma bancada esquecida em produção é uma porta aberta.
        'bancada:producao',
        'bancada:senha',
        // Repõe o OPcache do lado web. Corre-se no fecho de um deploy que muda
        // classes PHP: sem isto, um ficheiro com o conteúdo certo (MD5 a bater)
        // pode continuar a correr a compilação antiga — um método novo aparece
        // como «not found» e o view:cache não resolve (é só Blade). Tem de ser
        // por HTTP: o OPcache do CLI e o do php-fpm são separados.
        'deploy:opcache-reset',
        // O deploy por pacote e o caminho de volta (App\Support\Deploy\Pacote):
        // o instantâneo guarda o que o pacote vai tocar e a base de dados; o
        // aplicar recusa sem ele; o restaurar só diz o que faria sem
        // --confirmar, e só toca na base de dados com --bd.
        'deploy:instantaneo',
        'deploy:aplicar',
        'deploy:restaurar',
        'deploy:instantaneos',
        'optimize:clear',
        'config:clear',
        'cache:clear',
        'view:clear',
        'route:clear',
        'config:cache',
        'route:cache',
        'view:cache',
        'storage:link',
        'queue:restart',

        // Modo de manutenção. Só valem alguma coisa porque as rotas
        // `maintenance/*` estão excepcionadas em bootstrap/app.php: sem isso,
        // pôr o sistema em baixo bloqueava esta própria rota e a única forma
        // de o voltar a ligar era apagar storage/framework/down por FTP.
        'down',
        'up',
        'farmaciadois:import',
        'permissions:sync-product-batches',
        'permissions:sync-pos-reports',
        'permissions:sync-quotes',
        // As permissões que o código pede e que faltavam em produção (RH, guias,
        // painéis, apagar do hotel e da oficina). A seco por omissão; escreve com
        // --aplicar. Ver App\Console\Commands\AlinharPermissoes.
        'permissoes:alinhar',
        'permissoes:conceder',
        // Liga um módulo a um tenant (ou a todos). Serve para dar acesso à
        // empresa de ensaio (#96) e reproduzir um ecrã de módulo em produção
        // sem sessão de admin. syncWithoutDetaching — nunca desliga nada.
        'module:attach',
        // Desliga um módulo de um tenant (is_active=false no pivot; reversível,
        // não apaga dados). A seco por omissão; só desliga com --aplicar. Serve
        // para trocar módulos por empresa sem passar pelo painel de superadmin.
        'module:detach',
        // Importa uma lista de produtos (categoria, nome, código, preço, custo,
        // stock) de um JSON para um tenant. A SECO por omissão (corre numa
        // transacção e faz rollback); só grava com ?args=--aplicar. Idempotente
        // pelo código. O JSON vive em storage/app/imports/ (fora da whitelist
        // de verificação, mas não é segredo — é o catálogo do cliente).
        'produtos:importar-lista',
        // Repara os acentos estragados pela importação («├üCIDO» → «ÁCIDO»)
        // nas fichas (artigos, categorias, marcas, armazéns, fornecedores,
        // clientes). SÓ MOSTRA por omissão; grava apenas com --aplicar, e
        // nunca toca em documentos emitidos.
        'acentos:corrigir',
        // Só lê: os movimentos de transferência/ajuste sem lote que a lista
        // das transferências juntava numa «transferência» gigante.
        'stock:movimentos-sem-lote',
        'clientes:importar',
        // Exporta os produtos de um tenant para JSON (só lê) — para gerar uma
        // lista de preços em PDF fora do sistema.
        'produtos:exportar',
        // Repor o PIN de turno de um funcionário (login offline do POS), o mesmo
        // que o modal da Gestão de Utilizadores faz. Seguro por omissão: gera o
        // PIN no servidor (não viaja no URL), recusa os óbvios sem --forcar, e
        // aborta se o utilizador não for membro do tenant. Grava só com --aplicar.
        'pwa:definir-pin',
        'tenant:set-tax-exclusion',
        'planos:trial-automatico',
        'planos:dias-de-teste',
        'planos:coerencia',
        // Preço de um plano. Corre-se SEMPRE com ?args=--so-ver primeiro: do
        // outro lado está o que os clientes novos vão pagar. Não repreça
        // subscrições em vigor — essas guardam o valor com que foram feitas.
        'plano:preco',
        // Preço de CATÁLOGO de um módulo. Só toca em `modules.default_price`:
        // as empresas que já têm o módulo guardam o preço acordado no pivot e
        // não são reescritas. O mesmo se faz em /superadmin/modules — isto
        // serve para o fazer sem sessão aberta e deixar rasto no log.
        'modulos:preco',
        // Facturas de renovação. Corre-se SEMPRE com ?args=--so-ver primeiro:
        // sem isso emite facturas a sério, e uma factura é um documento que o
        // cliente vê e sobre o qual lhe é pedido dinheiro.
        'subscriptions:renovar',
        // Avisos de facturação ao cliente. Também aqui o `?args=--so-ver`
        // primeiro: do outro lado estão emails e SMS a pessoas reais.
        'subscricoes:avisos',
        // Escopos de uma credencial do agente. Pode estar aqui porque NÃO
        // mexe no segredo: nada de secreto atravessa a rede. O `agente:token`,
        // que emite credenciais, continua de fora de propósito — a resposta
        // desta rota é texto simples e ficaria com o segredo em claro.
        'agente:escopos',
        'agente:responsavel-telefone',
        // Os erros do sistema, agrupados. Só lê (fechar e empurrar exigem
        // ?args=--resolver=N ou --empurrar, explícitos).
        'erros:ver',
        'sms:ver',
        // Facturas de subscricao duplicadas do mesmo periodo. Simulacao por
        // omissao: so trata com ?args=--aplicar. Nunca toca numa factura paga.
        'facturas:duplicadas',
        // Liga o "Gerenciar Stock" nos artigos que o deviam ter. Simulação
        // por omissão: sem ?args=--aplicar não grava nada. Nunca toca em
        // serviços nem na quantidade.
        'produtos:gerir-stock',
        // Lanca as saidas das vendas que nao descontaram stock. Simulacao
        // por omissao; so grava com ?args=--aplicar. Respeita as contagens
        // fisicas: vendas anteriores a ultima contagem ficam de fora.
        'vendas:descontar-stock-em-falta',
        // Só lê: procura empresa por nome/NIF/email.
        'tenants:procurar',
        'tenant:iniciar-trial',
        'roles:backfill',
        'taxes:backfill',
        'treasury:bundle',
        'payment-methods:backfill',
        'warehouses:backfill',
        'modules:sync-permissions',
        'db:dump',
        'stock:reconcile',
        'invoices:fix-cross-tenant-client',
        'invoices:fix-type-by-series',
        'modules:repair',
        'tenant:reconcile-modules',
        'superadmin:audit',
        // Diagnóstico da trilha de auditoria: só lê. Necessário em produção
        // porque o gravador falha em silêncio de propósito — sem isto,
        // "auditoria parada" e "não houve actividade" são indistinguíveis.
        'audit:health',
        // Séries canónicas. Só cria em falta e renomeia o que nunca numerou —
        // nunca toca numa série com documentos emitidos ou registada na AGT.
        'series:canonical',
        // Diagnóstico das séries: só lê. É preciso em produção porque o prefixo
        // errado não dá erro nenhum cá dentro — o documento sai, e só a AGT o
        // recusa depois, com a factura já entregue ao cliente.
        'series:diagnostico',
        // Procurar uma empresa e ver os contactos dela. So le.
        'tenants:procurar',
        // Importacao de artigos de um CSV. Simulacao por omissao: so grava
        // com --aplicar, e e idempotente pelo codigo de barras.
        'artigos:importar',
        'artigos:corrigir-codigo',
        // Só leitura: mostra o que ficou mesmo gravado.
        'artigos:ver',
        // Ultimos documentos emitidos e o imposto de cada um. So le.
        'documentos:ver',
        'notas-credito:ver',
        // Corta as quantidades de uma nota de credito ao que a factura ainda
        // tem por anular, refaz totais e assinatura, e repoe a submissao na
        // fila. A SECO por omissao; so escreve com --aplicar. Recusa-se a
        // mexer numa nota que a AGT ja tenha aceite.
        'nota-credito:acertar',
        // Poe o 'ja pago' das facturas a par dos recibos que existem. So conta;
        // escreve com --aplicar. Nunca toca numa factura sem recibo nenhum
        // (as do balcao pagam-se no acto e o documento e o proprio recibo).
        'facturas:acertar-pagos',
        // Marcacoes do salao que ficaram sem origem — a coluna nao conhecia
        // 'system' e um MySQL permissivo guardava cadeia vazia. So conta;
        // escreve apenas com --aplicar.
        'salao:origens',
        // Ligacao do hotel ao KiandaStay: estado, teste, registar o webhook
        // outra vez e trazer uma reserva perdida pelo codigo. So --ligar e
        // --reserva escrevem, e ambos por ordem explicita.
        'hotel:kiandastay',
        // Apaga artigos da base a sério, para refazer uma importação. Exige
        // --aplicar E --confirmo-que-apaga, e nunca apaga um artigo que
        // esteja numa factura, nota ou receita.
        'artigos:apagar',
        // Cria utilizadores de uma empresa. Simulacao por omissao; as
        // palavras-passe sao geradas e mostradas uma unica vez.
        // Papel "Stock e Vendas": vende, gere stock, cria artigos sem editar.
        'papel:stock-vendas',
        'utilizadores:criar',
        // Corrige nome/email/palavra-passe. A palavra-passe vem de ficheiro,
        // nunca de argumento: um argumento de URL fica nos registos.
        'utilizadores:actualizar',
        'utilizadores:restaurar-acesso',
        // Periodo de teste de uma empresa. Simulacao por omissao: mexe em receita.
        'subscricao:trial',
        // Troca de plano pela mesma regra do ecra. Simulacao por omissao.
        'empresas:trocar-plano',
        'empresas:criar',
        'empresas:regime',
        'empresas:contas-bancarias',
        // Que nome da empresa sai impresso. Sem --usar apenas lista.
        'empresas:nome-documentos',
        // De quem são as tarefas paradas na fila do AGT. Só lê.
        'agt:diagnostico-fila',
        // Porque é que a AGT recusou um documento (código, mensagem e resposta
        // crua). Só lê.
        'agt:ver-rejeicao',
        // Permissões cujo nome é prefixo de outra — só lê.
        'permissoes:sombras',
        // Reparte invoicing.documents.all pelos papéis (a seco por omissão).
        'permissions:sync-documentos',
        // Confere permissões em falta/mortas/sem descrição e os papéis de uma empresa — só lê.
        'permissoes:catalogo',
        // Preenche descrições em falta a partir do catálogo (a seco por omissão).
        'permissoes:descrever',
        // Acrescenta aos papéis por omissão o que lhes falta (nunca tira).
        'papeis:completar',
        // Apaga permissões que nenhum portão verifica (a seco por omissão).
        'permissoes:limpar-mortas',
        // Repõe tentativas a submissões esgotadas por culpa nossa (a seco por
        // omissão). ATENÇÃO: com --aplicar arma um reenvio — só por ordem.
        'agt:repor-tentativas',
        // Correcção dos prefixos. Sem --aplicar é simulação, e as séries que já
        // emitiram documentos só mudam com --forcar (mudar o prefixo a meio
        // deixa a série com números de duas formas diferentes).
        'series:corrigir-prefixos',
        // Séries padrão duplicadas. Só desmarca as que nunca numeraram nada, e
        // apenas quando há UMA com documentos emitidos — o resto fica listado
        // para decisão humana. Sem --aplicar é simulação.
        'series:corrigir-padrao',
        // Só apaga redundâncias por estrear e sem qualquer registo AGT. Uma
        // série usada/registada é sempre protegida. Simulação por omissão.
        'series:limpar-invalidas',
        'agt:normalize',
        'agt:migrate-keys',
        'agt:producer-key',
        // Uma série por vez; sem --aplicar só mostra os metadados assinados.
        // Evita repetir em lote um erro global de certificação.
        'agt:sync-series',
        'agt:seed-taxes',
        // Contabilidade: limpa integration_key mal atribuída pela importação do
        // plano (adiantamentos como Clientes, IVA dedutível como liquidado).
        // Sem --fix é só simulação.
        'accounting:fix-integration-keys',
        // Número de validação do software AGT (definição global). Sem --numero
        // apenas mostra o valor actual.
        'agt:set-software-cert',
        // RH — cria as definições em falta nas empresas com o módulo activo.
        // Só ACRESCENTA: nunca altera um valor que a empresa tenha mudado.
        'hr:settings-sync',
        // Porque e que este funcionario sai assim na folha. So le.
        'rh:diagnostico',
        // Notificações dos modelos activos. Já corre sozinho à boleia do
        // tráfego; isto serve para forçar uma passagem e ver o resultado.
        // Não duplica nada — o que saiu hoje não volta a sair.
        'notifications:send-scheduled',
        // Modelos padrão nas empresas com o módulo activo. Só acrescenta o que
        // falta; nunca altera um modelo que a empresa tenha reescrito.
        'notifications:templates-sync',
        // Põe a ficha de cada empresa a dizer, no mínimo, o que o plano dá.
        // Só SOBE limites, nunca desce.
        'tenants:alinhar-limites',
    ];

    /**
     * Seeders permitidos.
     */
    protected array $allowedSeeders = [
        'PlanSeeder',
        'ModulePlansSeeder',
        'ModuleSeeder',
        'PermissionSeeder',
        'RoleSeeder',
        'TaxSeeder',
        'InvoicingTaxSeeder',
        'DatabaseSeeder',
        // Sprint 2 — AGT DS.120 v1.1
        'AGTTaxExemptionCodeSeeder',
        'AGTIsVerbaSeeder',
        'AGTCaeCodeSeeder',
        // Sprint 4 — AGT IEC pautal (Anexo 9.7)
        'AGTIecPautalCodeSeeder',
        // RH — escalões IRT (tabela contínua)
        'IRTTaxBracketSeeder',
        // Os textos dos avisos de facturação ao cliente. firstOrCreate por
        // slug: correr outra vez não apaga o que foi editado no painel.
        'AvisosDeSubscricaoTemplatesSeeder',
        // Contabilidade — corrige types do plano legado (classe 6/7 trocadas, classe 3 toda asset)
        'FixLegacyAccountTypesSeeder',
        // Contabilidade — mapeamentos Faturação→Contabilidade (invoice, NC, ND,
        // recibos, pagamentos). Idempotente: updateOrCreate por evento.
        'IntegrationMappingSeeder',
        // O template do aviso ao administrador quando nasce uma empresa.
        'AvisoNovaEmpresaTemplateSeeder',
        // Demo hotel + salão no tenant softecangola (sites públicos de reserva/agendamento)
        'DemoHotelSalonSeeder',
        // Credencial do agente externo openclaw (so o hash; ver seeder).
        'SeedTokenOpenclawSeeder',
        // Administradora Claudina Caquienga no tenant 57 (idempotente).
        'CriarAdminClaudinaSeeder',
    ];

    public function migrate(Request $request, string $token)
    {
        $this->ensureToken($token);

        $output = new BufferedOutput();
        $exitCode = Artisan::call('migrate', ['--force' => true], $output);

        return $this->respond('migrate', $exitCode, $output, $request);
    }

    public function migrateStatus(Request $request, string $token)
    {
        $this->ensureToken($token);

        $output = new BufferedOutput();
        $exitCode = Artisan::call('migrate:status', [], $output);

        return $this->respond('migrate:status', $exitCode, $output, $request);
    }

    public function seed(Request $request, string $token, string $seeder)
    {
        $this->ensureToken($token);

        if (!in_array($seeder, $this->allowedSeeders, true)) {
            abort(403, "Seeder '{$seeder}' não está na whitelist.");
        }

        $output = new BufferedOutput();
        // A rota não aceita barras no parâmetro, por isso a whitelist usa o nome
        // simples; os seeders em sub-pasta precisam do namespace completo.
        // Concatenação e não interpolação: "\\{$var}" faz o PHP ler \{ como
        // chaveta literal e a variável não é substituída.
        $raiz = 'Database\\Seeders\\';
        $classe = $raiz . $seeder;
        if (!class_exists($classe)) {
            foreach (['Accounting'] as $subNamespace) {
                $candidata = $raiz . $subNamespace . '\\' . $seeder;
                if (class_exists($candidata)) {
                    $classe = $candidata;
                    break;
                }
            }
        }

        $exitCode = Artisan::call('db:seed', [
            '--class' => $classe,
            '--force' => true,
        ], $output);

        return $this->respond("db:seed {$seeder}", $exitCode, $output, $request);
    }

    public function command(Request $request, string $token, string $cmd)
    {
        $this->ensureToken($token);

        if (!in_array($cmd, $this->allowedCommands, true)) {
            abort(403, "Comando '{$cmd}' não está na whitelist. Permitidos: " . implode(', ', $this->allowedCommands));
        }

        // Args opcionais: ?args=--dry --foo=bar
        $args = [];
        if ($rawArgs = $request->query('args')) {
            foreach (preg_split('/\s+/', trim($rawArgs)) as $part) {
                if (strpos($part, '=') !== false) {
                    [$k, $v] = explode('=', $part, 2);
                    $args[$k] = $v;
                } else {
                    $args[$part] = true;
                }
            }
        }

        $output = new BufferedOutput();
        $exitCode = 1;

        // O `finally` não é decoração: um comando que REBENTA é precisamente o
        // que mais interessa ter na trilha, e sem isto a excepção subia e
        // levava o registo à frente — ficava rasto dos que correram bem e
        // nenhum dos que falharam a meio.
        try {
            $exitCode = Artisan::call($cmd, $args, $output);
        } finally {
            $this->registarNaTrilha($cmd, $args, $exitCode);
        }

        return $this->respond($cmd, $exitCode, $output, $request);
    }

    /**
     * Um comando corrido por HTTP contra a produção deixa rasto.
     *
     * Estas rotas fazem coisas com consequência — migrar, semear, ligar e
     * desligar módulos, repor PINs — e até aqui não deixavam linha nenhuma:
     * o observer só vê alterações de modelo, e quem corre isto não tem sessão.
     *
     * A trilha é POR EMPRESA (`audit_trail.tenant_id` é NOT NULL), por isso só
     * se regista quando o comando diz em que empresa mexe. Os comandos globais
     * (migrate, cache:clear) continuam sem rasto — é a mesma fronteira dos
     * modelos de plataforma, anotada em config/audit.php.
     */
    private function registarNaTrilha(string $cmd, array $args, int $exitCode): void
    {
        $tenantId = null;
        foreach (['tenant_id', '--tenant', 'tenant', '--tenant-id'] as $chave) {
            if (!empty($args[$chave]) && is_numeric($args[$chave])) {
                $tenantId = (int) $args[$chave];
                break;
            }
        }

        if (!$tenantId) {
            return;
        }

        // Os metadados de um acto NÃO passam pela lista `redacted` do config —
        // essa só cobre valores de modelo. Um `--pin=1234` escrevia o PIN em
        // claro numa tabela append-only e selada, de onde não sai sem partir a
        // cadeia. Aqui oculta-se antes de entrar.
        $segredos = ['pin', 'password', 'token', 'secret', 'key', 'senha'];
        $argsLimpos = [];
        foreach ($args as $chave => $valor) {
            $nome = strtolower(ltrim((string) $chave, '-'));
            $sensivel = false;
            foreach ($segredos as $s) {
                if (str_contains($nome, $s)) {
                    $sensivel = true;
                    break;
                }
            }
            $argsLimpos[$chave] = $sensivel ? '[oculto]' : $valor;
        }

        app(\App\Services\Audit\AuditRecorder::class)->acto(
            'manutencao.comando',
            $tenantId,
            [
                'comando' => $cmd,
                'argumentos' => $argsLimpos,
                'resultado' => $exitCode === 0 ? 'ok' : 'falhou',
                'codigo_saida' => $exitCode,
            ]
        );
    }

    /**
     * Importação Farmácia Neves Bendinha (SMA → soserp).
     *
     * Pré-requisito: ZIP em storage/app/farmacia_migration/farmaciadois_subset.zip
     * com tabelas import_sma_categories, import_sma_warehouses, import_sma_products,
     * import_sma_warehouses_products (4 tabelas SMA com prefixo renomeado).
     *
     * Pipeline:
     *   1. Descomprime o ZIP
     *   2. Importa SQL para o DB corrente (soserp) com prefixo import_sma_
     *   3. Executa farmaciadois:import --source-prefix=import_sma_ --with-cashiers
     *   4. (opcional) Dropa as tabelas temporárias
     */
    public function farmaciaMigration(Request $request, string $token)
    {
        $this->ensureToken($token);
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $log = [];
        $log[] = '=== Farmácia Migration Pipeline ===';
        $log[] = 'Started at: ' . now()->toIso8601String();

        try {
            $base = storage_path('app/farmacia_migration');
            $zipPath = $base . DIRECTORY_SEPARATOR . 'farmaciadois_subset.zip';
            $sqlPath = $base . DIRECTORY_SEPARATOR . 'farmaciadois_subset.sql';

            if (!is_file($zipPath)) {
                throw new \RuntimeException("ZIP não encontrado: {$zipPath}. Faça upload via FTP primeiro.");
            }
            $log[] = 'ZIP: ' . $zipPath . ' (' . filesize($zipPath) . ' bytes)';

            // 1. Descomprimir
            $zip = new \ZipArchive();
            $rc = $zip->open($zipPath);
            if ($rc !== true) {
                throw new \RuntimeException("ZipArchive::open falhou (rc={$rc})");
            }
            $zip->extractTo($base);
            $zip->close();
            if (!is_file($sqlPath)) {
                throw new \RuntimeException("SQL não extraído: {$sqlPath}");
            }
            $log[] = 'SQL extraído: ' . $sqlPath . ' (' . filesize($sqlPath) . ' bytes)';

            // 2. Limpar tabelas temporárias antes (idempotente)
            $tempTables = ['import_sma_categories', 'import_sma_warehouses', 'import_sma_products', 'import_sma_warehouses_products'];
            foreach ($tempTables as $t) {
                \DB::statement("DROP TABLE IF EXISTS `{$t}`");
            }
            $log[] = 'Tabelas import_sma_* dropáveis limpas.';

            // 3. Executar SQL — split por declarações. Como o dump tem comentários e
            // INSERT extended (pode ter ; dentro de strings) usamos PDO->exec via
            // DB::unprepared, mas em chunks para evitar memória.
            $pdo = \DB::connection()->getPdo();
            $sql = file_get_contents($sqlPath);
            // Remover BOM UTF-8 se presente
            if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
                $sql = substr($sql, 3);
            }
            // Remover linhas SET/comments do mysqldump que começam por -- ou /*!
            $cleanedLines = [];
            foreach (preg_split("/\r?\n/", $sql) as $line) {
                $trim = ltrim($line);
                if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*!')) {
                    continue;
                }
                $cleanedLines[] = $line;
            }
            $sqlClean = implode("\n", $cleanedLines);
            unset($sql, $cleanedLines);

            // mysqli multi_query alternative: usar pdo->exec por blocos separados em ;\n
            // Simpler: dividir em statements terminados por ;<newline> evitando partir strings
            $stmts = $this->splitSqlStatements($sqlClean);
            $log[] = 'SQL split em ' . count($stmts) . ' statements.';

            $exec = 0;
            foreach ($stmts as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') {
                    continue;
                }
                $pdo->exec($stmt);
                $exec++;
            }
            $log[] = "Executados {$exec} statements SQL.";

            // 4. Verificar contagens
            $cats = \DB::table('import_sma_categories')->count();
            $whs = \DB::table('import_sma_warehouses')->count();
            $prods = \DB::table('import_sma_products')->count();
            $stk = \DB::table('import_sma_warehouses_products')->count();
            $log[] = "Tabelas temporárias: categories={$cats}, warehouses={$whs}, products={$prods}, stock={$stk}";

            // 5. Executar comando artisan
            $output = new BufferedOutput();
            $exitCode = Artisan::call('farmaciadois:import', [
                '--source-db' => \DB::connection()->getDatabaseName(),
                '--source-prefix' => 'import_sma_',
                '--with-cashiers' => true,
            ], $output);
            $log[] = '--- artisan farmaciadois:import (exit=' . $exitCode . ') ---';
            $log[] = $output->fetch();

            // 6. Dropar tabelas temporárias se sucesso
            if ($exitCode === 0 && $request->query('keep') !== '1') {
                foreach ($tempTables as $t) {
                    \DB::statement("DROP TABLE IF EXISTS `{$t}`");
                }
                $log[] = 'Tabelas import_sma_* droppadas (cleanup).';
            }

            // 7. Limpar ficheiro SQL extraído
            @unlink($sqlPath);
            $log[] = 'SQL extraído limpo. Pipeline concluída.';

            $finalText = implode("\n", $log);
            $success = $exitCode === 0;
        } catch (\Throwable $e) {
            $log[] = '❌ FALHA: ' . $e->getMessage();
            $log[] = $e->getTraceAsString();
            $finalText = implode("\n", $log);
            return response($finalText, 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response($finalText, $success ? 200 : 500, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Split SQL preservando strings entre aspas (necessário para INSERT ... extended).
     */
    protected function splitSqlStatements(string $sql): array
    {
        $stmts = [];
        $buf = '';
        $len = strlen($sql);
        $inStr = false;
        $strCh = '';
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $buf .= $ch;

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($inStr) {
                if ($ch === $strCh) {
                    $inStr = false;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $inStr = true;
                $strCh = $ch;
                continue;
            }
            if ($ch === ';') {
                $stmts[] = $buf;
                $buf = '';
            }
        }
        if (trim($buf) !== '') {
            $stmts[] = $buf;
        }
        return $stmts;
    }

    /**
     * Diagnostico/correcao do .htaccess raiz (public_html/.htaccess) para bypass ModSecurity Livewire.
     * GET /maintenance/{token}/htaccess-fix      -> mostra estado actual
     * GET /maintenance/{token}/htaccess-fix?apply=1 -> aplica o bypass se ainda nao existir
     */
    /**
     * GET /maintenance/{token}/pwa-cleanup
     * Remove os ficheiros estáticos PWA legados (manifest.json, sw.js, ícones) para que
     * as rotas dinâmicas Laravel sejam usadas.
     */
    public function pwaCleanup(Request $request, string $token)
    {
        $this->ensureToken($token);

        $report = ['=== PWA CLEANUP (manual) ==='];
        $actions = \App\Http\Controllers\PwaController::performCleanup();
        if (empty($actions)) {
            $report[] = 'Nada para limpar — sistema já está em modo dinâmico.';
        } else {
            $report = array_merge($report, $actions);
        }
        $report[] = '';
        $report[] = 'DONE.';

        return response('<pre>' . implode("\n", $report) . '</pre>')
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function htaccessFix(Request $request, string $token)
    {
        $this->ensureToken($token);

        // Hardening: bloquear o acesso directo aos ficheiros da aplicação e
        // limpar os logs já expostos. Corre com ?security=1.
        if ($request->query('security') === '1') {
            return $this->aplicarSegurancaHtaccess();
        }

        $rootHtaccess = base_path('.htaccess');
        $publicHtaccess = public_path('.htaccess');

        $report = [];
        $report[] = '=== HTACCESS FIX ===';
        $report[] = 'Root .htaccess path: ' . $rootHtaccess;
        $report[] = '  exists: ' . (is_file($rootHtaccess) ? 'YES' : 'NO');
        $report[] = '  readable: ' . (is_readable($rootHtaccess) ? 'YES' : 'NO');
        $report[] = '  writable: ' . (is_writable($rootHtaccess) ? 'YES' : 'NO');
        $report[] = '';
        $report[] = 'Public .htaccess path: ' . $publicHtaccess;
        $report[] = '  exists: ' . (is_file($publicHtaccess) ? 'YES' : 'NO');
        $report[] = '';

        $bypassBlock = "\n# Bypass ModSecurity/LiteSpeed WAF para endpoints Livewire (v2 - mais agressivo)\n"
            . "<IfModule mod_security.c>\n"
            . "    SecRuleEngine Off\n"
            . "</IfModule>\n"
            . "<IfModule mod_security2.c>\n"
            . "    SecRuleEngine Off\n"
            . "</IfModule>\n"
            . "<IfModule LiteSpeed>\n"
            . "    SecRuleEngine Off\n"
            . "</IfModule>\n"
            . "# Desativar regras comuns que bloqueiam payloads Livewire\n"
            . "<IfModule mod_security2.c>\n"
            . "    SecRuleRemoveById 200002 200003 200004 949110 941100 941110 941160 941340 932100 932105 980130 980140 949070\n"
            . "    SecRequestBodyAccess Off\n"
            . "</IfModule>\n";

        foreach ([$rootHtaccess, $publicHtaccess] as $path) {
            $report[] = '--- ' . $path . ' ---';
            if (!is_file($path)) {
                $report[] = '  (ficheiro nao existe, skip)';
                continue;
            }
            $content = file_get_contents($path);
            $report[] = '  Tamanho: ' . strlen($content) . ' bytes';
            $hasBypass = str_contains($content, 'Bypass ModSecurity/WAF para endpoints Livewire');
            $report[] = '  Tem bypass Livewire: ' . ($hasBypass ? 'YES' : 'NO');

            if ($request->query('apply') === '1' && !$hasBypass) {
                if (!is_writable($path)) {
                    $report[] = '  ❌ Sem permissao de escrita.';
                    continue;
                }
                // Backup
                $backup = $path . '.bak.' . date('YmdHis');
                @copy($path, $backup);
                $report[] = '  Backup: ' . $backup;
                $newContent = rtrim($content) . "\n" . $bypassBlock;
                if (file_put_contents($path, $newContent) !== false) {
                    $report[] = '  ✅ Bypass adicionado.';
                } else {
                    $report[] = '  ❌ Falha ao escrever.';
                }
            } elseif ($hasBypass) {
                $report[] = '  (ja tem bypass, nada a fazer)';
            } elseif ($request->query('apply') !== '1') {
                $report[] = '  (use ?apply=1 para aplicar)';
            }
        }

        $report[] = '';
        $report[] = '--- Conteudo actual public_html/.htaccess (max 5000 chars) ---';
        if (is_file($rootHtaccess)) {
            $report[] = substr(file_get_contents($rootHtaccess), 0, 5000);
        } else {
            $report[] = '(ficheiro nao existe)';
        }

        return response(implode("\n", $report), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Fecha a exposição pública de ficheiros da aplicação.
     *
     * A raiz do documento é a raiz do projecto (public_html), e o .htaccess só
     * reencaminha para o Laravel os ficheiros que NÃO existem — pelo que
     * qualquer ficheiro real do projecto (storage/logs, storage/app/private,
     * database/*.sqlite, vendor/*, …) era servido directamente pelo Apache.
     *
     * A correcção acrescenta, DEPOIS da regra que serve /public (logo os
     * uploads públicos via symlink public/storage continuam a funcionar),
     * regras que devolvem 403 ao acesso directo às pastas da aplicação; e
     * trunca os logs que já continham dados pessoais. Faz backup e é
     * idempotente.
     */
    private function aplicarSegurancaHtaccess()
    {
        $root = base_path('.htaccess');
        $log = [];
        $log[] = '=== HARDENING DE SEGURANCA ===';

        if (!is_file($root)) {
            $log[] = 'Root .htaccess nao existe — abortado.';
        } elseif (!is_writable($root)) {
            $log[] = 'Root .htaccess sem permissao de escrita — abortado.';
        } else {
            $content = file_get_contents($root);
            $original = $content;

            // ── Bloco 1: bloquear acesso directo aos ficheiros da aplicacao ──
            if (!str_contains($content, 'SEGURANCA SOSERP FILES')) {
                $bloco = "\n"
                    . "    # === SEGURANCA SOSERP FILES (docroot = raiz do projecto) ===\n"
                    . "    # Servir /public ja foi tratado acima; aqui bloqueia-se o acesso\n"
                    . "    # directo aos ficheiros da aplicacao (logs, sessoes, chaves,\n"
                    . "    # sqlite, dependencias, codigo-fonte).\n"
                    . '    RewriteRule ^(app|bootstrap|config|database|resources|routes|tests|vendor|lang|node_modules|storage)(/|$) - [F,L]' . "\n"
                    . '    RewriteRule ^(artisan|server\.php|phpunit\.xml|vite\.config\.js|webpack\.mix\.js|package(-lock)?\.json)$ - [F,L]' . "\n";

                $anchor = 'RewriteRule ^(.*)$ public/$1 [L]';
                if (str_contains($content, $anchor)) {
                    $content = str_replace($anchor, $anchor . "\n" . $bloco, $content);
                    $log[] = 'Bloco FILES inserido apos a regra de /public.';
                } else {
                    $content = rtrim($content) . "\n\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n" . $bloco . "</IfModule>\n";
                    $log[] = 'Ancora /public nao encontrada — bloco FILES acrescentado ao fim.';
                }
            } else {
                $log[] = 'Bloco FILES ja presente.';
            }

            // ── Bloco 2: bloquear por extensao (defesa em profundidade) ──
            if (!str_contains($content, 'SEGURANCA SOSERP EXT')) {
                $content .= "\n# === SEGURANCA SOSERP EXT ===\n"
                    . '<FilesMatch "\.(log|sqlite|sql|bak|ya?ml|ini|sh)$">' . "\n"
                    . "    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
                $log[] = 'Bloco EXT (FilesMatch) acrescentado.';
            } else {
                $log[] = 'Bloco EXT ja presente.';
            }

            // ── Bloco 3: forcar HTTPS + remover X-Powered-By + headers estaticos ──
            if (!str_contains($content, 'SEGURANCA SOSERP NET')) {
                // Redireccionar HTTP -> HTTPS logo no inicio do rewrite.
                $redirect = "\n"
                    . "    # === SEGURANCA SOSERP NET (forcar HTTPS) ===\n"
                    . "    RewriteCond %{HTTPS} !=on\n"
                    . "    RewriteCond %{HTTP:X-Forwarded-Proto} !=https\n"
                    . '    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]' . "\n";
                if (str_contains($content, 'RewriteBase /')) {
                    $content = str_replace('RewriteBase /', 'RewriteBase /' . $redirect, $content);
                    $log[] = 'Redireccionamento HTTPS inserido apos RewriteBase.';
                } else {
                    $content = str_replace('RewriteEngine On', 'RewriteEngine On' . $redirect, $content);
                    $log[] = 'Redireccionamento HTTPS inserido apos RewriteEngine On.';
                }

                // Headers ao nivel do servidor (cobre tambem as respostas estaticas).
                $content .= "\n# === SEGURANCA SOSERP NET HEADERS ===\n"
                    . "<IfModule mod_headers.c>\n"
                    . "    Header always unset X-Powered-By\n"
                    . "    Header unset X-Powered-By\n"
                    . '    Header always set X-Content-Type-Options "nosniff"' . "\n"
                    . '    Header always set Strict-Transport-Security "max-age=31536000"' . "\n"
                    . "</IfModule>\n"
                    . "<IfModule mod_security.c>\n</IfModule>\n";
                $log[] = 'Headers de servidor (NET) acrescentados.';
            } else {
                $log[] = 'Bloco NET ja presente.';
            }

            if ($content !== $original) {
                $backup = $root . '.bak.' . date('YmdHis');
                @copy($root, $backup);
                $log[] = 'Backup: ' . basename($backup);
                $log[] = file_put_contents($root, $content) !== false
                    ? 'Root .htaccess actualizado.'
                    : 'FALHA ao escrever o root .htaccess.';
            } else {
                $log[] = 'Nada a alterar no .htaccess.';
            }
        }

        // Truncar os logs ja expostos (remove o PII que ja esta no ficheiro).
        $limpos = 0;
        $bytes = 0;
        foreach (glob(storage_path('logs') . '/*.log') ?: [] as $f) {
            $bytes += @filesize($f) ?: 0;
            if (@file_put_contents($f, '') !== false) {
                $limpos++;
            }
        }
        $log[] = "Logs truncados: {$limpos} ficheiro(s), " . round($bytes / 1048576, 1) . ' MB libertados.';

        $log[] = '';
        $log[] = 'DONE.';

        return response(implode("\n", $log), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Mostra ultimas linhas do storage/logs/laravel-YYYY-MM-DD.log
     * GET /maintenance/{token}/logs?lines=200&date=2026-05-26
     */
    public function logs(Request $request, string $token)
    {
        $this->ensureToken($token);

        $date = $request->query('date', date('Y-m-d'));
        $lines = (int) ($request->query('lines', 300));
        $lines = max(50, min($lines, 2000));

        $logFile = storage_path("logs/laravel-{$date}.log");
        if (!is_file($logFile)) {
            // fallback laravel.log
            $logFile = storage_path('logs/laravel.log');
        }

        $output = "Log file: {$logFile}\n";
        $output .= "Last {$lines} lines:\n";
        $output .= str_repeat('=', 60) . "\n";

        if (!is_file($logFile)) {
            return response($output . "(ficheiro nao existe)\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        // tail eficiente
        $f = new \SplFileObject($logFile, 'r');
        $f->seek(PHP_INT_MAX);
        $totalLines = $f->key();
        $start = max(0, $totalLines - $lines);
        $f->seek($start);
        $content = '';
        while (!$f->eof()) {
            $content .= $f->current();
            $f->next();
        }

        return response($output . $content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Diagnostico: verifica relacao user/tenant/produtos para investigar 403 em modais.
     * GET /maintenance/{token}/diag-tenant?email=farmacia@luksimoes.com
     */
    public function diagTenant(Request $request, string $token)
    {
        $this->ensureToken($token);

        $email = $request->query('email');
        $r = [];
        $r[] = '=== DIAG TENANT ===';

        if ($email) {
            $user = \App\Models\User::where('email', $email)->first();
            if (!$user) { $r[] = "User '{$email}' nao encontrado"; }
            else {
                $r[] = "User: #{$user->id} {$user->email}";
                $r[] = "  users.tenant_id = " . ($user->tenant_id ?? 'NULL');
                $r[] = "  is_super_admin  = " . ($user->is_super_admin ? 'YES' : 'no');
                $tenants = $user->tenants()->get(['tenants.id','name','nif']);
                $r[] = "  user_tenant rows (" . $tenants->count() . "):";
                foreach ($tenants as $t) { $r[] = "    - #{$t->id} {$t->name} ({$t->nif})"; }
            }
        }

        // Stats globais Products por tenant.
        // A tabela é `invoicing_products` e não `products` — esta rota dava 500
        // em produção desde sempre, e o diagnóstico que devia explicar os 403
        // era ele próprio o erro.
        $r[] = '';
        $r[] = '--- Products por tenant ---';
        $rows = \DB::table('invoicing_products')->select('tenant_id', \DB::raw('count(*) as total'))->groupBy('tenant_id')->orderByDesc('total')->limit(10)->get();
        foreach ($rows as $row) {
            $tName = \App\Models\Tenant::where('id', $row->tenant_id)->value('name') ?? '?';
            $r[] = "  tenant_id={$row->tenant_id} ({$tName}): {$row->total} produtos";
        }

        // Sample products da Farmácia Neves Bendinha
        $r[] = '';
        $r[] = '--- Amostras Farmacia Neves Bendinha (NIF 5417289442) ---';
        $tenant = \App\Models\Tenant::where('nif', '5417289442')->first();
        if ($tenant) {
            $r[] = "  Tenant: #{$tenant->id} {$tenant->name}";
            $sample = \DB::table('invoicing_products')->where('tenant_id', $tenant->id)->limit(3)->get(['id','name','tenant_id','tax_type']);
            foreach ($sample as $p) { $r[] = "    - product #{$p->id} tenant_id={$p->tenant_id} tax_type={$p->tax_type}: {$p->name}"; }
        }

        return response(implode("\n", $r), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Diagnóstico read-only das roles por tenant.
     * Compara com o conjunto canónico (getDefaultRolePermissionMap) e assinala
     * tenants com roles em falta ou roles a mais. Filtro opcional ?name= ou ?nif=.
     */
    /**
     * Rastreio de UM artigo: vendido, saído, e o que resta.
     *
     * Responde à pergunta que um utilizador levanta e a aplicação não explica:
     * "venderam 9 e restam 3 — onde estão as outras?".
     *
     * A diferença entre VENDIDO e SAÍDAS diz se houve vendas que não deixaram
     * baixa de stock. A diferença entre SAÍDAS e o que RESTA diz se alguém
     * mexeu no stock sem passar pelo livro de movimentos.
     *
     * Não devolve clientes nem valores, mas DEVOLVE números de documento, datas
     * e estados (ver 'facturas' mais abaixo) — daí depender do token como todas
     * as outras rotas daqui. A afirmação anterior de que "não expõe nada de
     * ninguém" deixou de ser verdade quando essa lista foi acrescentada, e foi
     * ela que fez passar despercebida a falta do ensureToken.
     */
    public function diagProduto(Request $request, string $token)
    {
        // Faltava, e era a única rota de manutenção sem ele: sem esta linha
        // qualquer valor servia de token e a rota devolvia números de documento,
        // datas de emissão e estados de facturas de qualquer empresa (?tenant=).
        $this->ensureToken($token);

        $tenantId = (int) $request->query('tenant');
        $termo    = trim((string) $request->query('q'));

        if (!$tenantId || $termo === '') {
            return response()->json(['erro' => 'Indique ?tenant=ID&q=codigo-ou-nome'], 422);
        }

        $produtos = DB::table('invoicing_products')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($termo) {
                $q->where('barcode', $termo)
                  ->orWhere('sku', $termo)
                  ->orWhere('name', 'like', '%' . $termo . '%');
            })
            ->limit(5)
            ->get(['id', 'name', 'barcode', 'sku', 'stock_quantity']);

        $saida = [];

        foreach ($produtos as $p) {
            $vendido = DB::table('invoicing_sales_invoice_items as it')
                ->join('invoicing_sales_invoices as i', 'i.id', '=', 'it.sales_invoice_id')
                ->where('i.tenant_id', $tenantId)
                ->where('it.product_id', $p->id)
                ->whereNull('i.deleted_at')
                ->whereIn('i.status', ['sent', 'paid', 'partially_paid', 'overdue'])
                ->selectRaw('COALESCE(SUM(it.quantity),0) q, COUNT(*) n, MIN(i.invoice_date) de, MAX(i.invoice_date) ate')
                ->first();

            $movimentos = DB::table('invoicing_stock_movements')
                ->where('tenant_id', $tenantId)->where('product_id', $p->id)
                ->selectRaw('type, COALESCE(SUM(quantity),0) q, COUNT(*) n')
                ->groupBy('type')->get()->keyBy('type');

            $porArmazem = DB::table('invoicing_stocks as s')
                ->join('invoicing_warehouses as w', 'w.id', '=', 's.warehouse_id')
                ->where('s.tenant_id', $tenantId)->where('s.product_id', $p->id)
                ->get(['w.name', 's.quantity'])
                ->mapWithKeys(fn ($r) => [$r->name => (float) $r->quantity]);

            $saidas = (float) ($movimentos['out']->q ?? 0);

            // Vendas por DIA: é assim que se confronta o que o operador diz
            // ("vendi 9") com o que o sistema registou. O total do artigo não
            // serve para isso.
            $porDia = DB::table('invoicing_sales_invoice_items as it')
                ->join('invoicing_sales_invoices as i', 'i.id', '=', 'it.sales_invoice_id')
                ->where('i.tenant_id', $tenantId)
                ->where('it.product_id', $p->id)
                ->whereNull('i.deleted_at')
                ->whereIn('i.status', ['sent', 'paid', 'partially_paid', 'overdue'])
                ->selectRaw('DATE(i.invoice_date) dia, SUM(it.quantity) q, COUNT(*) n')
                ->groupBy('dia')->orderBy('dia')
                ->get()
                ->mapWithKeys(fn ($r) => [$r->dia => ['unidades' => (float) $r->q, 'documentos' => (int) $r->n]]);

            // Cronologia: entradas, saídas e ajustes por ordem, com os saldos.
            // Um ajuste grava o valor FINAL, por isso sem o saldo anterior não
            // se sabe se subiu ou desceu — nem quanto.
            $cronologia = DB::table('invoicing_stock_movements')
                ->where('tenant_id', $tenantId)->where('product_id', $p->id)
                ->orderBy('id')
                ->get(['created_at', 'type', 'quantity', 'balance_before', 'balance_after', 'warehouse_id'])
                ->map(fn ($m) => [
                    'quando'  => (string) $m->created_at,
                    'tipo'    => $m->type,
                    'qtd'     => (float) $m->quantity,
                    'antes'   => $m->balance_before === null ? null : (float) $m->balance_before,
                    'depois'  => $m->balance_after === null ? null : (float) $m->balance_after,
                ]);

            $saida[] = [
                'produto'          => $p->name,
                'codigo'           => $p->barcode ?: $p->sku,
                'agregado'         => (float) $p->stock_quantity,
                'soma_armazens'    => round($porArmazem->sum(), 3),
                'por_armazem'      => $porArmazem,
                'vendido'          => (float) $vendido->q,
                'linhas_de_venda'  => (int) $vendido->n,
                'primeira_venda'   => $vendido->de,
                'ultima_venda'     => $vendido->ate,
                'movimentos'       => $movimentos->map(fn ($m) => ['quantidade' => (float) $m->q, 'n' => (int) $m->n]),
                'vendido_sem_baixa' => round((float) $vendido->q - $saidas, 3),
                'vendas_por_dia'    => $porDia,
                'cronologia'        => $cronologia,

                // Números de documento das vendas deste artigo.
                //
                // Deliberadamente fora do diagnóstico até aqui: queria-o
                // utilizável em produção sem expor nada. O número do documento
                // é o mínimo para uma auditoria de stock — quem pergunta "onde
                // foram as unidades" precisa de saber em que factura saíram.
                // Continua sem cliente e sem valores.
                'facturas' => DB::table('invoicing_sales_invoice_items as it')
                    ->join('invoicing_sales_invoices as i', 'i.id', '=', 'it.sales_invoice_id')
                    ->where('i.tenant_id', $tenantId)
                    ->where('it.product_id', $p->id)
                    ->whereNull('i.deleted_at')
                    ->whereIn('i.status', ['sent', 'paid', 'partially_paid', 'overdue', 'credited'])
                    ->orderBy('i.invoice_date')->orderBy('i.id')
                    ->limit(200)
                    ->get(['i.invoice_number', 'i.invoice_date', 'i.created_at', 'i.status', 'it.quantity'])
                    ->map(fn ($f) => [
                        'documento' => $f->invoice_number,
                        'data'      => $f->invoice_date,
                        'emitida'   => $f->created_at,
                        'estado'    => $f->status,
                        'qtd'       => (float) $f->quantity,
                    ]),
            ];
        }

        return response()->json([
            'empresa'   => $tenantId,
            'procurado' => $termo,
            'artigos'   => $saida,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }


    public function diagRoles(Request $request, string $token)
    {
        $this->ensureToken($token);

        $allPermissions = \Spatie\Permission\Models\Permission::all();
        $expectedMap = getDefaultRolePermissionMap($allPermissions);
        $expectedRoles = array_keys($expectedMap);

        $r = [];
        $r[] = '=== DIAG ROLES ===';
        $r[] = 'Permissões globais: ' . $allPermissions->count();
        $r[] = 'Roles canónicas esperadas (' . count($expectedRoles) . '): ' . implode(', ', $expectedRoles);
        $r[] = '';

        $tenantsQuery = \App\Models\Tenant::query()->orderBy('id');
        if ($name = $request->query('name')) {
            $tenantsQuery->where('name', 'like', '%' . $name . '%');
        }
        if ($nif = $request->query('nif')) {
            $tenantsQuery->where('nif', $nif);
        }
        $tenants = $tenantsQuery->get(['id', 'name', 'nif']);

        $r[] = 'Tenants analisados: ' . $tenants->count();
        $r[] = str_repeat('-', 60);

        foreach ($tenants as $t) {
            $roles = \Spatie\Permission\Models\Role::where('tenant_id', $t->id)
                ->withCount('permissions')->orderBy('name')->get();
            $roleNames = $roles->pluck('name')->toArray();

            $missing = array_values(array_diff($expectedRoles, $roleNames));
            $extra = array_values(array_diff($roleNames, $expectedRoles));

            $status = empty($missing) ? 'OK' : 'FALTAM ' . count($missing);
            $r[] = "#{$t->id} {$t->name} (NIF {$t->nif}) — roles: {$roles->count()} [{$status}]";
            foreach ($roles as $role) {
                $expCount = isset($expectedMap[$role->name]) ? count($expectedMap[$role->name]) : null;
                $flag = ($expCount !== null && $role->permissions_count !== $expCount) ? "  <= esperado {$expCount}" : '';
                $r[] = sprintf("    - %-26s %d perms%s", $role->name, $role->permissions_count, $flag);
            }
            if ($missing) { $r[] = '    !! EM FALTA: ' . implode(', ', $missing); }
            if ($extra)   { $r[] = '    ?? EXTRA: ' . implode(', ', $extra); }
            $r[] = '';
        }

        return response(implode("\n", $r), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function index(Request $request, string $token)
    {
        $this->ensureToken($token);

        return response()->json([
            'message' => 'Maintenance endpoint',
            'available' => [
                'migrate' => url("/maintenance/{$token}/migrate"),
                'migrate:status' => url("/maintenance/{$token}/migrate-status"),
                'seed/{Seeder}' => url("/maintenance/{$token}/seed/PlanSeeder"),
                'command/{cmd}' => url("/maintenance/{$token}/command/plans:update-pricing"),
                'command_with_args' => url("/maintenance/{$token}/command/plans:update-pricing?args=--dry"),
            ],
            'allowed_seeders' => $this->allowedSeeders,
            'allowed_commands' => $this->allowedCommands,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Compara os ficheiros do servidor com um manifesto enviado pela máquina de
     * origem, e devolve o que está em falta ou diferente.
     *
     * Existe porque o envio por FTP reportava "OK" sem confirmar a escrita: numa
     * única auditoria apareceram 260 ficheiros desalinhados, alguns há semanas —
     * classes em falta que só se manifestavam como erro 500 aleatório para os
     * utilizadores. Este endpoint é a verificação que faltava a seguir a cada
     * envio (ver o comando deploy:verify).
     *
     * Devolve apenas caminhos e tamanhos. Nunca conteúdo de ficheiros.
     */
    public function verifyFiles(Request $request, string $token)
    {
        $this->ensureToken($token);

        $manifesto = $request->input('manifest');

        if (is_string($manifesto)) {
            $manifesto = json_decode($manifesto, true);
        }

        if (!is_array($manifesto) || empty($manifesto)) {
            return response()->json(['erro' => 'Manifesto ausente ou inválido.'], 422);
        }

        if (count($manifesto) > 5000) {
            return response()->json(['erro' => 'Manifesto demasiado grande (máx. 5000).'], 422);
        }

        // Só estas raízes podem ser inspeccionadas. Sem isto um manifesto
        // forjado poderia sondar a existência de ficheiros fora do projecto.
        // Nunca inclui .env, storage/ nem vendor/.
        $raizesPermitidas = [
            'app/', 'bootstrap/', 'resources/', 'lang/', 'database/',
            'routes/', 'config/', 'public/js/', 'public/css/',
        ];

        // Ficheiros soltos da raiz que também são comparados
        $ficheirosSoltos = [
            'artisan', 'composer.json', 'composer.lock',
            'public/index.php', 'public/.htaccess', 'public/robots.txt',
        ];

        $base = base_path();
        $ausentes = [];
        $diferem = [];
        $ignorados = 0;
        $verificados = 0;

        foreach ($manifesto as $relativo => $tamanho) {
            $relativo = str_replace('\\', '/', (string) $relativo);

            // Nada de subir na árvore nem caminhos absolutos
            if ($relativo === '' || str_contains($relativo, '..') || str_starts_with($relativo, '/') || preg_match('/^[A-Za-z]:/', $relativo)) {
                $ignorados++;
                continue;
            }

            $permitido = in_array($relativo, $ficheirosSoltos, true);

            if (!$permitido) {
                foreach ($raizesPermitidas as $raiz) {
                    if (str_starts_with($relativo, $raiz)) {
                        $permitido = true;
                        break;
                    }
                }
            }

            if (!$permitido) {
                $ignorados++;
                continue;
            }

            $verificados++;
            $caminho = $base . '/' . $relativo;

            if (!is_file($caminho)) {
                $ausentes[] = $relativo;
                continue;
            }

            // O manifesto traz "tamanho:md5". Comparar pelo conteúdo e não só
            // pelo tamanho fecha a última porta: dois ficheiros com o mesmo
            // número de bytes mas conteúdo diferente passariam despercebidos.
            [$tamanhoEsperado, $hashEsperado] = array_pad(explode(':', (string) $tamanho, 2), 2, null);

            $tamanhoServidor = filesize($caminho);

            if ($tamanhoServidor !== (int) $tamanhoEsperado) {
                $diferem[] = [
                    'ficheiro' => $relativo,
                    'servidor' => $tamanhoServidor,
                    'origem'   => (int) $tamanhoEsperado,
                    'motivo'   => 'tamanho',
                ];
                continue;
            }

            if ($hashEsperado && md5_file($caminho) !== $hashEsperado) {
                $diferem[] = [
                    'ficheiro' => $relativo,
                    'servidor' => $tamanhoServidor,
                    'origem'   => (int) $tamanhoEsperado,
                    'motivo'   => 'conteudo (md5 difere, mesmo tamanho)',
                ];
            }
        }

        return response()->json([
            'verificados' => $verificados,
            'ignorados'   => $ignorados,
            'ausentes'    => $ausentes,
            'diferem'     => $diferem,
            'ok'          => empty($ausentes) && empty($diferem),
            'commit'      => trim((string) @file_get_contents($base . '/.git/HEAD')) ?: null,
            'verificado_em' => now()->toIso8601String(),
        ]);
    }

    protected function ensureToken(string $token): void
    {
        $expected = config('maintenance.token') ?: env('MAINTENANCE_TOKEN');
        if (!$expected || strlen($expected) < 16) {
            abort(503, 'MAINTENANCE_TOKEN não definido no .env (mínimo 16 caracteres).');
        }
        if (!hash_equals($expected, $token)) {
            Log::warning('Maintenance: tentativa com token inválido', ['ip' => request()->ip()]);
            abort(403, 'Token inválido.');
        }
    }

    protected function respond(string $what, int $exitCode, BufferedOutput $output, Request $request)
    {
        $text = $output->fetch();
        $success = $exitCode === 0;

        Log::info("Maintenance executou: {$what}", [
            'exit' => $exitCode,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'command' => $what,
                'success' => $success,
                'exit_code' => $exitCode,
                'output' => $text,
                'timestamp' => now()->toIso8601String(),
            ], $success ? 200 : 500);
        }

        // Output em texto plano (terminal-like)
        $header = "============================================================\n";
        $header .= " {$what}\n";
        $header .= " Status: " . ($success ? 'OK ✓' : 'FAILED ✗') . " (exit {$exitCode})\n";
        $header .= " " . now()->toIso8601String() . "\n";
        $header .= "============================================================\n\n";

        return response($header . $text, $success ? 200 : 500, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
