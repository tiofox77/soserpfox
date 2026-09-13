/**
 * A PONTE DOS ECRÃS DO RESTAURANTE.
 *
 * Fala com `/api/v1/invoicing/react/restaurant/*` — a morada dos ecrãs com
 * sessão, a mesma de todos os outros módulos. Não confundir com
 * `/api/v1/restaurant`, que é a API da app móvel e do PWA offline: tem outra
 * forma, outra autenticação e outros compromissos.
 */

import { api, criarApi } from './cliente';
import type { GrupoDeIcones } from './catalogos';

/**
 * O PULSO DA COZINHA vive noutra morada — a do módulo, `/restaurant/...`.
 *
 * Não é um descuido: é a agregação barata que o ecrã da cozinha pergunta de
 * três em três segundos, e mudá-la para debaixo da API dos ecrãs era partir o
 * único caminho barato que ela tem (e o único que o Livewire e o React
 * partilham enquanto a carta pública não passar também).
 */
const apiDoModulo = criarApi('/restaurant');

/** Um par para um `<select>` — a forma que o servidor devolve em todo o lado. */
export type Escolha = { valor: string; rotulo: string };

/* ─── O painel ────────────────────────────────────────────────────────── */

export type Serie = { etiquetas: string[]; valores: number[] };

export type PainelDoRestaurante = {
    dias: number;
    resumo: {
        mesas: number;
        mesas_ocupadas: number;
        comandas_abertas: number;
        venda_hoje: number;
        venda_ontem: number;
        comandas_hoje: number;
        ticket_medio: number;
    };
    mesas_por_estado: Array<{ estado: string; rotulo: string; total: number }>;
    ultimas: Array<{
        id: number; numero: string; mesa: string | null; empregado: string | null;
        canal: string; canal_rotulo: string; estado: string; total: number; criada_em: string | null;
    }>;
    por_dia: Serie;
    por_hora: Serie;
    pratos: Serie;
    por_mesa: Serie;
};

/* ─── A sala ──────────────────────────────────────────────────────────── */

export type Mesa = {
    id: number;
    codigo: string;
    nome: string;
    lugares: number;
    zona: string | null;
    estado: string;
    estado_rotulo: string;
    comanda: {
        id: number; numero: string; estado: string;
        total: number; pessoas: number; aberta_em: string | null;
    } | null;
};

export type PedidoDaCarta = {
    id: number;
    mesa: string | null;
    cliente: string | null;
    telefone: string | null;
    observacoes: string | null;
    total_previsto: number;
    artigos: Array<{ nome: string; quantidade: number }>;
    criado_em: string | null;
};

export type NaEspera = {
    id: number; nome: string; telefone: string | null;
    pessoas: number; minutos: number; chegou_em: string | null;
};

export type MapaDaSala = {
    estabelecimento: number;
    zonas: Escolha[];
    mesas: Mesa[];
    contagem: Record<string, number>;
    pedidos_da_carta: PedidoDaCarta[];
    espera: NaEspera[];
};

export type OpcoesDaSala = {
    estabelecimentos: Escolha[];
    estados_da_mesa: Escolha[];
    canais: Escolha[];
    permissoes: { pode_gerir: boolean; pode_abrir: boolean };
};

/* ─── As comandas ─────────────────────────────────────────────────────── */

export type LinhaDaComanda = {
    id: number; numero: string; mesa: string | null; empregado: string | null;
    canal: string; canal_rotulo: string; estado: string; estado_rotulo: string;
    pessoas: number; cliente: string | null; total: number; criada_em: string | null;
};

export type ArtigoDaComanda = {
    id: number;
    product_id: number;
    nome: string;
    quantidade: number;
    facturada: number;
    por_facturar: number;
    unidade: string | null;
    preco: number;
    total: number;
    estado_na_cozinha: string;
    observacoes: string | null;
    so_anulavel: boolean;
};

export type FichaDaComanda = {
    comanda: LinhaDaComanda & {
        estabelecimento: string | null;
        venue_id: number | null;
        table_id: number | null;
        client_id: number | null;
        telefone: string | null;
        morada: string | null;
        taxa_de_entrega: number;
        gorjeta: number;
        observacoes: string | null;
        subtotal: number;
        imposto: number;
        despachada_em: string | null;
        aberta: boolean;
        para_fora: boolean;
        entrega_ja_facturada: boolean;
    };
    artigos: ArtigoDaComanda[];
    mesas_livres: Escolha[];
    comandas_para_juntar: Escolha[];
};

export type ArtigoDaCarta = {
    id: number; nome: string; codigo: string | null;
    preco: number; unidade: string | null; categoria: string | null; imagem: string | null;
};

export type OpcoesDasComandas = {
    clientes: Escolha[];
    formas_de_pagamento: Escolha[];
    categorias: Array<Escolha & { icone: string; cor: string }>;
    impostos: Escolha[];
    canais: Escolha[];
    estados: Escolha[];
    definicoes: { gorjetas: boolean; taxa_de_servico: number; cozinha: boolean; exige_ficha: boolean };
    tem_turno: boolean;
    permissoes: {
        pode_criar: boolean; pode_editar: boolean; pode_anular: boolean;
        pode_transferir: boolean; pode_dividir: boolean; pode_facturar: boolean;
    };
};

export type FiltrosDasComandas = {
    procura?: string; estado?: string; canal?: string;
    estabelecimento?: number | ''; abertas?: boolean; por_pagina?: number; page?: number;
};

export type PaginaDeComandas = {
    data: LinhaDaComanda[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
};

export type Fecho = {
    document_type: 'FR' | 'FT';
    client_id: number | null;
    payment_method_id: number | null;
    idempotency_key: string;
    item_ids: number[];
    payments?: Array<{ payment_method_id: number; amount: number }>;
    tip_amount?: number;
};

/* ─── A cozinha ───────────────────────────────────────────────────────── */

export type Bilhete = {
    id: number;
    numero: string;
    posto: string | null;
    estado: string;
    estado_rotulo: string;
    seguinte: string | null;
    seguinte_rotulo: string | null;
    prioridade: number;
    comanda: string | null;
    mesa: string | null;
    canal: string | null;
    pessoas: number;
    na_fila_desde: string | null;
    minutos: number;
    artigos: Array<{ id: number; nome: string; quantidade: number; unidade: string | null; observacoes: string | null; estado: string }>;
};

/* ─── A carta ─────────────────────────────────────────────────────────── */

export type CategoriaDaCarta = {
    id: number; nome: string; descricao: string | null; icone: string; cor: string;
    activa: boolean; ordem: number; pratos: number; da_casa: boolean;
};

export type Prato = {
    id: number; nome: string; codigo: string | null; preco: number; unidade: string | null;
    categoria: string | null; category_id: number | null; disponivel: boolean;
    imagem: string | null; falta_ficha: boolean;
};

/* ─── As reservas ─────────────────────────────────────────────────────── */

export type ReservaDeMesa = {
    id: number; numero: string; nome: string; telefone: string | null; email: string | null;
    pessoas: number; mesa: string | null; table_id: number | null;
    estabelecimento: string | null; venue_id: number | null;
    quando: string | null; duracao: number; estado: string; estado_rotulo: string;
    observacoes: string | null; pode: Escolha[];
};

export type ReservaParaGravar = {
    venue_id: string; table_id: string; guest_name: string; phone: string; email: string;
    guest_count: string; reserved_at: string; duration_minutes: string; notes: string;
};

/* ─── As fichas técnicas ──────────────────────────────────────────────── */

export type Ficha = {
    id: number;
    product_id: number;
    prato: string;
    preco_de_venda: number;
    rende: number;
    unidade: string;
    activa: boolean;
    custo: number;
    custo_por_dose: number;
    margem: number | null;
    ingredientes: Array<{
        id: number; ingredient_product_id: number; nome: string; quantidade: number;
        unidade: string; quebra: number; quantidade_com_quebra: number; custo: number;
    }>;
};

/* ─── O stock ─────────────────────────────────────────────────────────── */

export type ExistenciaDoRestaurante = {
    id: number; artigo: string; unidade: string | null; armazem: string | null;
    disponivel: number; minimo: number; em_falta: boolean;
};

/* ─── As definições ───────────────────────────────────────────────────── */

export type RegrasDoRestaurante = {
    default_warehouse_id: number | null;
    default_client_id: number | null;
    use_kitchen_workflow: boolean;
    require_recipe_for_products: boolean;
    reserve_stock_on_confirm: boolean;
    consume_stock_on_kitchen: boolean;
    allow_negative_stock: boolean;
    service_charge_percent: number;
    tips_enabled: boolean;
    kitchen_auto_print: boolean;
};

export type CartaPublica = {
    menu_slug: string;
    online_menu_enabled: boolean;
    menu_whatsapp_enabled: boolean;
    menu_orders_enabled: boolean;
    menu_show_prices: boolean;
    menu_whatsapp_number: string;
    menu_title: string;
    menu_description: string;
    menu_primary_color: string;
};

export type EstruturaDaCasa = {
    id: number; codigo: string; nome: string; warehouse_id: number | null; activo: boolean;
    zonas: Array<{
        id: number; nome: string; activa: boolean;
        mesas: Array<{ id: number; codigo: string; nome: string; lugares: number; activa: boolean; estado: string }>;
    }>;
};

export type DefinicoesDoRestaurante = {
    regras: RegrasDoRestaurante;
    carta: CartaPublica;
    url_da_carta: string | null;
    estabelecimentos: EstruturaDaCasa[];
    postos: Array<{ id: number; codigo: string; nome: string; estabelecimento: string | null; venue_id: number; activo: boolean }>;
    armazens: Escolha[];
    clientes: Escolha[];
    limite_de_estabelecimentos: number;
    pedido_de_limite: { id: number; requested_limit: number; created_at: string } | null;
    permissoes: { pode_editar: boolean };
};

/* ─── A aparência da carta ────────────────────────────────────────────── */

export type AparenciaDaCarta = {
    aparencia: {
        menu_title: string; menu_description: string; menu_primary_color: string;
        menu_accent_color: string; menu_theme: string; menu_destaques_titulo: string;
        menu_show_prices: boolean;
    };
    capa: string | null;
    logo: string | null;
    temas: Escolha[];
    url_da_carta: string | null;
    carta_publicada: boolean;
    destaques: Array<{ id: number; product_id: number; nome: string; preco: number; imagem: string | null }>;
    maximo_de_destaques: number;
    permissoes: { pode_editar: boolean };
};

type Recado = { message: string };

const R = '/restaurant';

export const restaurante = {
    painel: (dias?: number) => api.ler<PainelDoRestaurante>(`${R}/painel`, { dias }),

    sala: {
        opcoes: () => api.ler<OpcoesDaSala>(`${R}/sala/opcoes`),
        mapa: (estabelecimento?: number | '', zona?: number | '') =>
            api.ler<MapaDaSala>(`${R}/sala`, { estabelecimento, zona }),
        criarMesa: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/sala/mesas`, dados),
        abrir: (dados: { table_id: number; guest_count: number; notes?: string }) =>
            api.criar<Recado & { order_id: number }>(`${R}/sala/abrir`, dados),
        abrirSemMesa: (dados: Record<string, unknown>) =>
            api.criar<Recado & { order_id: number }>(`${R}/sala/abrir-sem-mesa`, dados),
        estadoDaMesa: (id: number, estado: string) => api.criar<Recado>(`${R}/sala/mesas/${id}/estado`, { estado }),
        limpar: (id: number) => api.criar<Recado>(`${R}/sala/mesas/${id}/limpar`, {}),
        aceitarPedido: (id: number, venue_id?: number) =>
            api.criar<Recado & { order_id: number }>(`${R}/sala/carta/${id}/aceitar`, { venue_id }),
        descartarPedido: (id: number) => api.criar<Recado>(`${R}/sala/carta/${id}/descartar`, {}),
        chegouAFila: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/sala/espera`, dados),
        sentar: (id: number, table_id: number) =>
            api.criar<Recado & { order_id: number }>(`${R}/sala/espera/${id}/sentar`, { table_id }),
        desistiu: (id: number) => api.criar<Recado>(`${R}/sala/espera/${id}/desistiu`, {}),
    },

    comandas: {
        opcoes: () => api.ler<OpcoesDasComandas>(`${R}/comandas/opcoes`),
        lista: (f: FiltrosDasComandas) => api.ler<PaginaDeComandas>(`${R}/comandas`, f as Record<string, string | number | boolean>),
        ficha: (id: number) => api.ler<FichaDaComanda>(`${R}/comandas/${id}`),
        artigos: (f: { procura?: string; categoria?: number | ''; limite?: number }) =>
            api.ler<{ data: ArtigoDaCarta[] }>(`${R}/comandas/artigos`, f),
        acrescentar: (id: number, dados: { product_id: number; quantity: number; notes?: string | null }) =>
            api.criar<Recado>(`${R}/comandas/${id}/artigos`, dados),
        quantidade: (id: number, item: number, dados: { quantity?: number; delta?: number }) =>
            api.guardar<Recado>(`${R}/comandas/${id}/artigos/${item}`, dados),
        remover: (id: number, item: number) => api.apagar<Recado>(`${R}/comandas/${id}/artigos/${item}`),
        anular: (id: number, item: number, reason: string) =>
            api.criar<Recado>(`${R}/comandas/${id}/artigos/${item}/anular`, { reason }),
        confirmar: (id: number) => api.criar<Recado>(`${R}/comandas/${id}/confirmar`, {}),
        despachar: (id: number) => api.criar<Recado>(`${R}/comandas/${id}/despachar`, {}),
        libertarMesa: (id: number) => api.criar<Recado>(`${R}/comandas/${id}/libertar-mesa`, {}),
        /** A comanda inteira — só aceite quando não tem artigos vivos nem nada facturado. */
        anularComanda: (id: number) => api.criar<Recado>(`${R}/comandas/${id}/anular`, {}),
        transferir: (id: number, table_id: number) => api.criar<Recado>(`${R}/comandas/${id}/transferir`, { table_id }),
        juntar: (id: number, target_order_id: number) =>
            api.criar<Recado & { order_id: number }>(`${R}/comandas/${id}/juntar`, { target_order_id }),
        fechar: (id: number, dados: Fecho) =>
            api.criar<Recado & { factura: { id: number; numero: string; total: number } }>(`${R}/comandas/${id}/fechar`, dados),
        artigoRapido: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/comandas/artigo-rapido`, dados),
    },

    cozinha: {
        opcoes: () => api.ler<{ postos: Escolha[]; estados: Escolha[]; impressao_automatica: boolean; permissoes: { pode_gerir: boolean } }>(`${R}/cozinha/opcoes`),
        bilhetes: (posto?: number | '') => api.ler<{ data: Bilhete[] }>(`${R}/cozinha`, { posto }),
        avancar: (id: number) => api.criar<Recado>(`${R}/cozinha/${id}/avancar`, {}),

        /** «Mudou alguma coisa?» — uma agregação, sem relação nenhuma. */
        pulso: (posto?: number | '') =>
            apiDoModulo.ler<{ pulso: string; bilhetes: number }>('/kitchen/pulso', { station: posto }),
    },

    carta: {
        opcoes: () => api.ler<{
            categorias: CategoriaDaCarta[]; impostos: Escolha[];
            definicoes: { exige_ficha: boolean; carta_publica: boolean };
            galeria_de_icones: GrupoDeIcones[];
            permissoes: { pode_gerir: boolean };
        }>(`${R}/carta/opcoes`),
        lista: (f: { categoria?: number | ''; procura?: string; so_fora?: boolean }) =>
            api.ler<{ categorias: CategoriaDaCarta[]; data: Prato[]; exige_ficha: boolean }>(`${R}/carta`, f),
        criarPrato: (dados: { name: string; price: number; category_id?: number | null }) =>
            api.criar<Recado & { aviso: boolean }>(`${R}/carta/pratos`, dados),
        preco: (id: number, price: string) => api.guardar<Recado>(`${R}/carta/pratos/${id}/preco`, { price }),
        nome: (id: number, name: string) => api.guardar<Recado>(`${R}/carta/pratos/${id}/nome`, { name }),
        categoriaDoPrato: (id: number, category_id: number | null) =>
            api.guardar<Recado>(`${R}/carta/pratos/${id}/categoria`, { category_id }),
        disponibilidade: (id: number) => api.criar<Recado>(`${R}/carta/pratos/${id}/disponibilidade`, {}),
        guardarCategoria: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { categorias: CategoriaDaCarta[] }>(`${R}/carta/categorias/${id}`, dados)
               : api.criar<Recado & { categorias: CategoriaDaCarta[] }>(`${R}/carta/categorias`, dados),
        alternarCategoria: (id: number) => api.criar<Recado & { categorias: CategoriaDaCarta[] }>(`${R}/carta/categorias/${id}/alternar`, {}),
        moverCategoria: (id: number, direccao: 'cima' | 'baixo') =>
            api.criar<{ categorias: CategoriaDaCarta[] }>(`${R}/carta/categorias/${id}/mover`, { direccao }),
        apagarCategoria: (id: number) => api.apagar<Recado & { categorias: CategoriaDaCarta[] }>(`${R}/carta/categorias/${id}`),
    },

    reservas: {
        opcoes: () => api.ler<{
            estabelecimentos: Array<Escolha & { mesas: Array<Escolha & { lugares: number }> }>;
            estados: Escolha[];
            permissoes: { pode_criar: boolean; pode_editar: boolean; pode_cancelar: boolean };
        }>(`${R}/reservas/opcoes`),
        lista: (f: { dia?: string; estabelecimento?: number | ''; estado?: string }) =>
            api.ler<{ dia: string; data: ReservaDeMesa[]; resumo: Record<string, number>; pessoas: number }>(`${R}/reservas`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${R}/reservas/${id}`, dados) : api.criar<Recado>(`${R}/reservas`, dados),
        estado: (id: number, estado: string) => api.criar<Recado>(`${R}/reservas/${id}/estado`, { estado }),
    },

    fichas: {
        opcoes: () => api.ler<{
            artigos: Array<Escolha & { unidade: string; preco: number; custo: number }>;
            permissoes: { pode_gerir: boolean };
        }>(`${R}/fichas/opcoes`),
        lista: (procura?: string) =>
            api.ler<{ data: Ficha[]; resumo: { total: number; activas: number; ingredientes: number } }>(`${R}/fichas`, { procura }),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Ficha }>(`${R}/fichas/${id}`, dados)
               : api.criar<Recado & { data: Ficha }>(`${R}/fichas`, dados),
        acrescentar: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Ficha }>(`${R}/fichas/${id}/ingredientes`, dados),
        removerIngrediente: (id: number, linha: number) =>
            api.apagar<Recado & { data: Ficha }>(`${R}/fichas/${id}/ingredientes/${linha}`),
        alternar: (id: number) => api.criar<Recado>(`${R}/fichas/${id}/alternar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/fichas/${id}`),
    },

    stock: {
        opcoes: () => api.ler<{
            armazens: Array<Escolha & { padrao: boolean }>;
            artigos: Array<Escolha & { unidade: string }>;
            permissoes: { pode_lancar: boolean };
        }>(`${R}/stock/opcoes`),
        lista: (f: { procura?: string; armazem?: number | '' }) => api.ler<{
            data: ExistenciaDoRestaurante[];
            desperdicios: Array<{ id: number; artigo: string; quantidade: number; unidade: string | null; custo: number; motivo: string | null; quando: string | null }>;
            producao: Array<{ id: number; comanda: string | null; artigo: string; unidade: string | null; quantidade: number; motivo: string; quem: string | null; quando: string | null }>;
        }>(`${R}/stock`, f),
        desperdicio: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/stock/desperdicio`, dados),
    },

    relatorios: (de?: string, ate?: string) => api.ler<{
        de: string; ate: string;
        resumo: { vendas: number; comandas: number; ticket: number; gorjetas: number; anuladas: number; reservas: number; desperdicio: number };
        pratos: Array<{ nome: string; quantidade: number; total: number }>;
        canais: Array<{ canal: string; rotulo: string; comandas: number; total: number; entregas: number }>;
        grafico_de_pratos: Serie;
    }>(`${R}/relatorios`, { de, ate }),

    definicoes: {
        ler: () => api.ler<DefinicoesDoRestaurante>(`${R}/definicoes`),
        guardar: (dados: RegrasDoRestaurante) => api.guardar<Recado>(`${R}/definicoes`, dados),
        guardarCarta: (dados: CartaPublica) => api.guardar<Recado & { url_da_carta: string | null }>(`${R}/definicoes/carta`, dados),
        criarEstabelecimento: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/definicoes/estabelecimentos`, dados),
        pedirMais: (dados: { requested_limit: number; reason?: string }) =>
            api.criar<Recado>(`${R}/definicoes/estabelecimentos/pedir`, dados),
        criarZona: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/definicoes/zonas`, dados),
        criarPosto: (dados: Record<string, unknown>) => api.criar<Recado>(`${R}/definicoes/postos`, dados),
        alternar: (tipo: string, id: number) => api.criar<Recado>(`${R}/definicoes/${tipo}/${id}/alternar`, {}),
        renomear: (tipo: string, id: number, dados: Record<string, unknown>) =>
            api.guardar<Recado>(`${R}/definicoes/${tipo}/${id}`, dados),
        apagar: (tipo: string, id: number) => api.apagar<Recado>(`${R}/definicoes/${tipo}/${id}`),
    },

    aparencia: {
        ler: () => api.ler<AparenciaDaCarta>(`${R}/aparencia`),
        guardar: (dados: Record<string, unknown>) => api.guardar<Recado>(`${R}/aparencia`, dados),
        imagem: (qual: 'capa' | 'logo', ficheiro: File) => {
            const corpo = new FormData();
            corpo.append('qual', qual);
            corpo.append('ficheiro', ficheiro);

            return api.enviar<Recado & { url: string }>(`${R}/aparencia/imagem`, corpo);
        },
        removerImagem: (qual: 'capa' | 'logo') => api.apagar<Recado>(`${R}/aparencia/imagem`, { qual }),
        candidatos: (procura: string) =>
            api.ler<{ data: Array<{ id: number; nome: string; preco: number }> }>(`${R}/aparencia/candidatos`, { procura }),
        destacar: (product_id: number) =>
            api.criar<Recado & { destaques: AparenciaDaCarta['destaques'] }>(`${R}/aparencia/destaques`, { product_id }),
        mover: (id: number, sentido: 'cima' | 'baixo') =>
            api.criar<{ destaques: AparenciaDaCarta['destaques'] }>(`${R}/aparencia/destaques/${id}/mover`, { sentido }),
        retirar: (id: number) =>
            api.apagar<Recado & { destaques: AparenciaDaCarta['destaques'] }>(`${R}/aparencia/destaques/${id}`),
    },
};
