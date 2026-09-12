/**
 * OS ECRÃS QUE O REACT SABE MONTAR.
 *
 * O nome à esquerda é o que vai no `data-ecra` do Blade. O `import()` é
 * preguiçoso de propósito: cada ecrã é um pedaço à parte, e quem abre a lista
 * de facturas não descarrega o ecrã dos produtos.
 *
 * Um nome que não esteja aqui não monta nada e diz-se na consola — em vez de
 * a página ficar com um buraco branco sem explicação.
 */

import type { ComponentType } from 'react';

/*
 * As props vêm do `data-props` do Blade e são diferentes por ecrã, por isso
 * o registo aceita qualquer forma. A verificação a sério é dentro do ecrã, que
 * declara o que espera receber.
 */
type Ecra = () => Promise<{ default: ComponentType<any> }>;

export const ecras: Record<string, Ecra> = {
    'facturacao/lista-de-facturas': () =>
        import('./facturacao/vendas/ListaDeFacturas'),

    'facturacao/clientes': () =>
        import('./facturacao/Clientes'),

    'facturacao/produtos': () =>
        import('./facturacao/Produtos'),

    'facturacao/painel': () =>
        import('./facturacao/Painel'),

    // Serve CINCO documentos: o `tipo` vem nas props, do Blade.
    'facturacao/documentos': () =>
        import('./facturacao/ListaDeDocumentos'),

    // E TRÊS propostas: proforma de venda, orçamento, proforma de compra.
    'facturacao/emitir-proposta': () =>
        import('./facturacao/EmitirProposta'),

    'facturacao/registar-recibo': () =>
        import('./facturacao/RegistarRecibo'),

    // Notas de crédito e de débito: o `tipo` vem nas props.
    'facturacao/emitir-nota': () =>
        import('./facturacao/EmitirNota'),

    // A factura de venda, FT ou FR — o ecrã mais delicado da casa.
    'facturacao/emitir-factura': () =>
        import('./facturacao/EmitirFactura'),

    // A factura de compra: a que dá entrada de stock e de lotes.
    'facturacao/emitir-factura-de-compra': () =>
        import('./facturacao/EmitirFacturaDeCompra'),

    // As definições da facturação e as séries de numeração.
    'facturacao/definicoes': () =>
        import('./facturacao/Definicoes'),
    'facturacao/gateways-de-notificacao': () =>
        import('./facturacao/GatewaysDeNotificacao'),

    /*
     * ─── RECURSOS HUMANOS ──────────────────────────────────────────────
     *
     * Os catálogos do RH (departamentos, cargos, turnos) usam o ecrã genérico
     * `facturacao/catalogo`, como os da tesouraria. Aqui ficam os que têm
     * ecrã próprio.
     */
    'rh/funcionarios': () =>
        import('./rh/Funcionarios'),

    // SEIS pedidos com aprovação: férias, licenças, horas extras, turno
    // nocturno, adiantamentos e descontos. O `tipo` vem nas props.
    'rh/pedidos': () =>
        import('./rh/Pedidos'),

    // O ponto de cada dia, e a folha de pagamento do mês.
    'rh/presencas': () =>
        import('./rh/Presencas'),
    'rh/folha': () =>
        import('./rh/Folha'),

    // O painel (avisos primeiro), os cinco mapas, o mapa de IRT que se
    // entrega à AGT, as definições que decidem os salários, e os contratos —
    // o ecrã que nunca existiu sobre uma tabela que já decidia o pagamento.
    'rh/painel': () =>
        import('./rh/Painel'),
    'rh/relatorios': () =>
        import('./rh/Relatorios'),
    'rh/mapa-de-irt': () =>
        import('./rh/MapaDeIrt'),
    'rh/definicoes': () =>
        import('./rh/Definicoes'),
    'rh/contratos': () =>
        import('./rh/Contratos'),

    // A casca: a barra lateral com o menu, o suporte e o utilizador.
    'casca': () =>
        import('./casca/Casca'),

    // SEIS catálogos (fornecedores, categorias, marcas, armazéns, condições
    // de pagamento, impostos): o `tipo` vem nas props, o esquema do servidor.
    'facturacao/catalogo': () =>
        import('./facturacao/Catalogo'),

    // Registar (ou editar, com `id` nas props) um adiantamento.
    'facturacao/emitir-adiantamento': () =>
        import('./facturacao/EmitirAdiantamento'),

    // As guias de transporte e de remessa: lista e registo.
    'facturacao/guias-de-transporte': () =>
        import('./facturacao/GuiasDeTransporte'),

    // As importações: o processo, do pedido ao armazém.
    'facturacao/importacoes': () =>
        import('./facturacao/Importacoes'),

    // O stock por armazém: ajustar, transferir, movimentação em lote.
    'facturacao/stock': () =>
        import('./facturacao/Stock'),

    // As quebras: registo e relatório no mesmo sítio.
    'facturacao/quebras': () =>
        import('./facturacao/Quebras'),

    // Os lotes e as validades.
    'facturacao/lotes': () =>
        import('./facturacao/Lotes'),

    // Transferências entre armazéns e ajustes em lote; e entre empresas.
    'facturacao/transferencias-entre-armazens': () =>
        import('./facturacao/TransferenciasEntreArmazens'),
    'facturacao/transferencias-entre-empresas': () =>
        import('./facturacao/TransferenciasEntreEmpresas'),
    // As séries de documentos e a trilha de auditoria.
    'facturacao/series': () =>
        import('./facturacao/Series'),
    'facturacao/auditoria': () =>
        import('./facturacao/Auditoria'),
    // O gerador SAFT-AO.
    'facturacao/saft': () =>
        import('./facturacao/Saft'),
    // A AGT: os dois ambientes, e a ficha do contribuinte.
    'facturacao/agt': () =>
        import('./facturacao/Agt'),
    'facturacao/credenciais-agt': () =>
        import('./facturacao/CredenciaisAgt'),
    // O painel do adquirente: as facturas que os fornecedores emitiram contra esta empresa.
    'facturacao/adquirente-agt': () =>
        import('./facturacao/AdquirenteAgt'),
    // Os relatórios: a porta, o ecrã genérico de qualquer mapa, e os gráficos.
    'facturacao/relatorios-hub': () =>
        import('./facturacao/RelatoriosHub'),
    'facturacao/relatorio': () =>
        import('./facturacao/Relatorio'),
    'facturacao/graficos': () =>
        import('./facturacao/Graficos'),
    // O BALCÃO. A venda entra pelo `PosSaleService`, a mesma porta do PWA.
    'facturacao/pos': () => import('./facturacao/pos/PontoDeVenda'),
    'facturacao/pos-relatorio': () => import('./facturacao/pos/RelatorioDoPos'),

    // Os turnos do POS, o modo offline e os modelos de proposta.
    'facturacao/turnos': () =>
        import('./facturacao/TurnosDoPos'),
    'facturacao/historico-de-turnos': () =>
        import('./facturacao/HistoricoDeTurnos'),
    'facturacao/importar-copia-offline': () =>
        import('./facturacao/ImportarCopiaOffline'),
    'facturacao/definir-pin': () =>
        import('./facturacao/DefinirPin'),
    'facturacao/modelos-de-proposta': () =>
        import('./facturacao/ModelosDeProposta'),

    /*
     * O RESTAURANTE.
     *
     * A CARTA serve dois endereços (pratos e categorias) e os CONTACTOS
     * também (clientes e fornecedores): o separador de arranque vem nas props,
     * da rota. São o mesmo ecrã porque são a mesma decisão — a ordem das
     * categorias decide-se a olhar para os pratos.
     */
    'restaurant/painel': () =>
        import('./restaurant/Painel'),
    'restaurant/sala': () =>
        import('./restaurant/Sala'),
    'restaurant/comandas': () =>
        import('./restaurant/Comandas'),
    'restaurant/balcao': () =>
        import('./restaurant/Balcao'),
    'restaurant/cozinha': () =>
        import('./restaurant/Cozinha'),
    'restaurant/carta': () =>
        import('./restaurant/Carta'),
    'restaurant/contactos': () =>
        import('./restaurant/Contactos'),
    'restaurant/reservas': () =>
        import('./restaurant/Reservas'),
    'restaurant/fichas': () =>
        import('./restaurant/Fichas'),
    'restaurant/stock': () =>
        import('./restaurant/Stock'),
    'restaurant/relatorios': () =>
        import('./restaurant/Relatorios'),
    'restaurant/definicoes': () =>
        import('./restaurant/Definicoes'),
    'restaurant/aparencia-da-carta': () =>
        import('./restaurant/Aparencia'),

    /*
     * O SALÃO. Os PRODUTOS e o BALCÃO são os da facturação — o ecrã completo
     * em vez da cópia reduzida que o salão tinha. Estes são só dele.
     */
    'salao/painel': () =>
        import('./salao/Painel'),
    'salao/marcacoes': () =>
        import('./salao/Marcacoes'),
    'salao/servicos': () =>
        import('./salao/Servicos'),
    'salao/profissionais': () =>
        import('./salao/Profissionais'),
    'salao/clientes': () =>
        import('./salao/Clientes'),
    'salao/tempos': () =>
        import('./salao/Tempos'),
    'salao/definicoes': () =>
        import('./salao/Definicoes'),

    /*
     * OS EVENTOS. O parque, os conjuntos e as categorias eram TRÊS MORADAS com
     * a mesma barra de navegação copiada no topo de cada uma — e a barra era a
     * prova de que pertenciam ao mesmo ecrã. Hoje são três separadores do
     * mesmo, e cada morada continua a existir, a abrir no seu.
     */
    'eventos/painel': () =>
        import('./eventos/Painel'),
    'eventos/agenda': () =>
        import('./eventos/Agenda'),
    'eventos/equipamentos': () =>
        import('./eventos/Equipamentos'),
    'eventos/equipamentos-painel': () =>
        import('./eventos/EquipamentosPainel'),
    'eventos/locais': () =>
        import('./eventos/Locais'),
    'eventos/tipos': () =>
        import('./eventos/Tipos'),
    'eventos/tecnicos': () =>
        import('./eventos/Tecnicos'),
    'eventos/relatorios': () =>
        import('./eventos/Relatorios'),

    // A TESOURARIA. Os catálogos (bancos, formas, caixas, tipos, categorias,
    // contas) passam pelo ecrã genérico; os movimentos têm o seu.
    'tesouraria/movimentos': () =>
        import('./tesouraria/Movimentos'),
    'tesouraria/transferencias': () =>
        import('./tesouraria/Transferencias'),
    'tesouraria/painel': () =>
        import('./tesouraria/Painel'),
    'tesouraria/relatorios': () =>
        import('./tesouraria/Relatorios'),
    'facturacao/editor-de-modelo': () =>
        import('./facturacao/EditorDeModelo'),

    // A OFICINA. Os catálogos (mecânicos, viaturas, serviços) passam pelo ecrã
    // genérico e as peças pelo dos artigos; estes dois são só dela.
    'oficina/painel': () =>
        import('./oficina/Painel'),
    'oficina/relatorios': () =>
        import('./oficina/Relatorios'),
    'oficina/ordens': () =>
        import('./oficina/OrdensDeServico'),

    // O HOTEL. Os catálogos (tipos de quarto, quartos, hóspedes, pessoal,
    // pacotes, códigos) passam pelo ecrã genérico; estes são só dele.
    'hotel/balcao': () =>
        import('./hotel/Balcao'),
    'hotel/calendario': () =>
        import('./hotel/Calendario'),
    'hotel/definicoes': () =>
        import('./hotel/Definicoes'),
    'hotel/kiandastay': () =>
        import('./hotel/KiandaStay'),
    /* A PÁGINA PÚBLICA: sem menu, sem sessão, e com as cores da casa. */
    'hotel/reservar': () =>
        import('./hotel/Reservar'),
    'hotel/check-out': () =>
        import('./hotel/CheckOut'),
    'hotel/folio': () =>
        import('./hotel/Folio'),
    'hotel/limpeza': () =>
        import('./hotel/Limpeza'),
    'hotel/manutencao': () =>
        import('./hotel/Manutencao'),
    'hotel/painel': () =>
        import('./hotel/Painel'),
    'hotel/relatorios': () =>
        import('./hotel/Relatorios'),
    'hotel/tarifas': () =>
        import('./hotel/Tarifas'),
    'hotel/reservas': () =>
        import('./hotel/Reservas'),
};
