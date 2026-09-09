import { api } from './cliente';

/**
 * O HOTEL, para os ecrãs em React.
 *
 * Os catálogos (tipos de quarto, quartos, hóspedes, pessoal, pacotes, códigos
 * promocionais) passam pelas rotas genéricas — `api/catalogos.ts`. Aqui ficam
 * os ecrãs que são só do hotel.
 */

export type Escolha = { valor: string; rotulo: string };

/* ─── Manutenção ────────────────────────────────────────────────────── */

export type OpcoesDaManutencao = {
    tipos: Escolha[];
    prioridades: Escolha[];
    categorias: Escolha[];
    estados: Escolha[];
    /** As quatro colunas do quadro — «cancelada» não é coluna, é um fim. */
    colunas_do_quadro: Array<{ valor: string; rotulo: string }>;
    quartos: Escolha[];
    pessoal: Escolha[];
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
    /**
     * SE QUEM ESTÁ A VER TEM FICHA DE PESSOAL DO HOTEL.
     *
     * O botão «atribuir-me» só faz alguma coisa a quem tem, e o ecrã de sempre
     * mostrava-o a toda a gente: quem não tinha carregava e não acontecia nada.
     */
    tenho_ficha: boolean;
};

export type OrdemDeManutencao = {
    id: number;
    numero: string;
    titulo: string;
    descricao: string | null;
    local: string | null;
    quarto: string | null;
    room_id: number | null;
    responsavel: string | null;
    assigned_to: number | null;
    quem_reportou: string | null;
    tipo: string;
    tipo_rotulo: string;
    prioridade: string;
    prioridade_rotulo: string;
    categoria: string;
    categoria_rotulo: string;
    estado: string;
    estado_rotulo: string;
    agendada: string | null;
    iniciada: string | null;
    concluida: string | null;
    custo_previsto: number | null;
    tempo_previsto: number | null;
    custo: number | null;
    /** O tempo real sai das duas datas, e não de uma coluna que podia discordar. */
    minutos_gastos: number | null;
    resolucao: string | null;
    aberta_em: string | null;
};

export type FiltrosDaManutencao = {
    procura?: string;
    estado?: string;
    prioridade?: string;
    categoria?: string;
    quarto?: string;
    responsavel?: string;
    page?: number;
    por_pagina?: number;
};

export type ListaDeManutencao = {
    data: OrdemDeManutencao[];
    meta: { total: number; current_page: number; last_page: number; per_page: number };
    resumo: {
        total: number;
        pendentes: number;
        em_curso: number;
        a_espera: number;
        concluidas: number;
        urgentes: number;
    };
};

export type QuadroDaManutencao = {
    colunas: Array<{ estado: string; rotulo: string; quantas: number; ordens: OrdemDeManutencao[] }>;
};

export type OrdemParaGravar = {
    title: string;
    type: string;
    priority: string;
    category: string;
    room_id: string;
    assigned_to: string;
    description: string;
    location: string;
    estimated_cost: string;
    estimated_time: string;
    scheduled_date: string;
};

export const manutencao = {
    opcoes: () => api.ler<OpcoesDaManutencao>('/hotel/manutencao/opcoes'),
    lista: (filtros: FiltrosDaManutencao) => api.ler<ListaDeManutencao>('/hotel/manutencao', filtros),
    quadro: () => api.ler<QuadroDaManutencao>('/hotel/manutencao/quadro'),

    criar: (dados: OrdemParaGravar) =>
        api.criar<{ data: OrdemDeManutencao; message: string }>('/hotel/manutencao', dados),

    guardar: (id: number, dados: OrdemParaGravar) =>
        api.guardar<{ data: OrdemDeManutencao; message: string }>(`/hotel/manutencao/${id}`, dados),

    apagar: (id: number) => api.apagar<{ message: string }>(`/hotel/manutencao/${id}`),

    /** Ao concluir escreve-se o que se fez e quanto custou. */
    estado: (id: number, estado: string, fecho?: { resolucao?: string; custo?: string }) =>
        api.criar<{ data: OrdemDeManutencao; message: string }>(`/hotel/manutencao/${id}/estado`, { estado, ...fecho }),

    atribuirMe: (id: number) =>
        api.criar<{ data: OrdemDeManutencao; message: string }>(`/hotel/manutencao/${id}/atribuir-me`, {}),
};

/* ─── O painel ──────────────────────────────────────────────────────── */

export type Serie = { etiquetas: string[]; valores: number[]; chaves?: string[] };

/** Uma reserva, na forma curta que o painel mostra. */
export type ReservaDoPainel = {
    id: number;
    numero: string;
    /** O nome de quem fica — o painel em Blade mostrava-o sempre vazio. */
    hospede: string;
    quarto: string | null;
    tipo: string | null;
    entrada: string | null;
    saida: string | null;
    noites: number;
    estado: string;
};

export type QuartoNoMapa = {
    id: number;
    numero: string;
    tipo: string | null;
    estado: string;
    estado_rotulo: string;
    limpeza: string | null;
    hospede: string | null;
    ate: string | null;
};

export type PainelDoHotel = {
    /** Se quem está a ver pode ver dinheiro — decidido no servidor. */
    ve_dinheiro: boolean;
    quartos: {
        total: number;
        livres: number;
        ocupados: number;
        manutencao: number;
        limpeza: number;
        ocupacao: number;
    };
    hoje: {
        chegadas: ReservaDoPainel[];
        saidas: ReservaDoPainel[];
        hospedados: ReservaDoPainel[];
    };
    por_decidir: ReservaDoPainel[];
    proximas: ReservaDoPainel[];
    dinheiro: { do_mes: number; por_receber: number } | null;
    manutencao: { abertas: number; urgentes: number };
    series: { mensal: Serie; ocupacao: Serie; por_tipo: Serie; estados: Serie };
    /** O desenho da casa, piso a piso. */
    mapa: Array<{ piso: string; quartos: QuartoNoMapa[] }>;
};

export const painelDoHotel = {
    ler: () => api.ler<PainelDoHotel>('/hotel/painel'),
};

/* ─── Os mapas ──────────────────────────────────────────────────────── */

export type ColunaDoMapa = {
    chave: string;
    rotulo: string;
    formato: 'texto' | 'numero' | 'dinheiro' | 'data' | 'percentagem';
};

export type LinhaDoMapa = Record<string, string | number | null>;

export type OpcoesDosMapasDoHotel = {
    mapas: Array<Escolha & { icone: string }>;
    tipos_de_quarto: Escolha[];
};

/** Os números que a hotelaria pergunta — ocupação, ADR e RevPAR. */
export type KpisDoHotel = {
    quartos: number;
    dias: number;
    noites_vendidas: number;
    noites_disponiveis: number;
    ocupacao: number;
    receita: number;
    adr: number;
    revpar: number;
    reservas: number;
    chegadas: number;
    saidas: number;
    canceladas: number;
};

export type MapaDoHotel = {
    mapa: string;
    descargas: { pdf: string; excel: string };
    kpis: KpisDoHotel;
    colunas: ColunaDoMapa[];
    linhas: LinhaDoMapa[];
    totais: LinhaDoMapa | null;
    nada: string | null;
    periodo: { de: string; ate: string };
};

export type FiltrosDoMapaDoHotel = {
    mapa: string;
    de?: string;
    ate?: string;
    tipo_de_quarto?: string;
};

export const mapasDoHotel = {
    opcoes: () => api.ler<OpcoesDosMapasDoHotel>('/hotel/relatorios/opcoes'),
    mostrar: (filtros: FiltrosDoMapaDoHotel) => api.ler<MapaDoHotel>('/hotel/relatorios', filtros),
};

/* ─── A limpeza dos quartos ─────────────────────────────────────────── */

export type OpcoesDaLimpeza = {
    tipos: Escolha[];
    prioridades: Escolha[];
    estados: Escolha[];
    /** Os estados de limpeza do QUARTO — limpo, sujo, em limpeza, … */
    limpezas: Escolha[];
    quartos: Escolha[];
    andares: Escolha[];
    /** Quem limpa é um utilizador da empresa, e não uma ficha de pessoal. */
    pessoas: Escolha[];
    permissoes: { pode_gerir: boolean };
};

export type PontoDaLista = { indice: number; item: string; feito: boolean };

export type TarefaDeLimpeza = {
    id: number;
    room_id: number;
    quarto: string | null;
    piso: string | null;
    tipo_de_quarto: string | null;
    tipo: string;
    tipo_rotulo: string;
    prioridade: string;
    prioridade_rotulo: string;
    estado: string;
    estado_rotulo: string;
    assigned_to: number | null;
    responsavel: string | null;
    verificada_por: string | null;
    /** Sai da reserva pela ficha certa: a antiga (`hotel_guests`) está vazia. */
    hospede: string | null;
    saida: string | null;
    dia: string | null;
    hora: string | null;
    minutos_previstos: number | null;
    minutos_gastos: number | null;
    comecou: string | null;
    acabou: string | null;
    notas: string | null;
    problema: string | null;
    atrasada: boolean;
    lista: PontoDaLista[];
    feitos: number;
    pontos: number;
    progresso: number;
};

export type QuartoNaPlanta = {
    id: number;
    numero: string;
    piso: string | null;
    tipo: string | null;
    estado: string;
    limpeza: string | null;
    limpeza_rotulo: string;
    /** A tarefa do dia, sem filtro: o pino não pode desaparecer com um filtro. */
    tarefa: { id: number; prioridade: string; estado: string; responsavel: string | null } | null;
};

export type FiltrosDaLimpeza = {
    dia?: string;
    prioridade?: string;
    responsavel?: string;
    andar?: string;
};

export type DiaDeLimpeza = {
    dia: string;
    tarefas: TarefaDeLimpeza[];
    resumo: {
        total: number;
        pendentes: number;
        em_curso: number;
        concluidas: number;
        com_problema: number;
        /** De todos os dias, e não do escolhido: é o que está por acabar. */
        atrasadas: number;
        quartos: number;
        quartos_limpos: number;
        quartos_sujos: number;
        quartos_em_limpeza: number;
        limpeza: number;
    };
    quartos: QuartoNaPlanta[];
};

export type TarefaParaGravar = {
    room_id: string;
    task_type: string;
    priority: string;
    assigned_to: string;
    scheduled_date: string;
    scheduled_time: string;
    estimated_duration: string;
    notes: string;
};

type RespostaDaTarefa = { data: TarefaDeLimpeza; message?: string };

export const limpeza = {
    opcoes: () => api.ler<OpcoesDaLimpeza>('/hotel/limpeza/opcoes'),
    dia: (filtros: FiltrosDaLimpeza) => api.ler<DiaDeLimpeza>('/hotel/limpeza', filtros),

    criar: (dados: TarefaParaGravar) => api.criar<RespostaDaTarefa>('/hotel/limpeza', dados),
    guardar: (id: number, dados: TarefaParaGravar) => api.guardar<RespostaDaTarefa>(`/hotel/limpeza/${id}`, dados),
    apagar: (id: number) => api.apagar<{ message: string }>(`/hotel/limpeza/${id}`),

    /** As tarefas do dia a partir das saídas e das estadas. */
    gerar: () => api.criar<{ quantas: number; message: string }>('/hotel/limpeza/gerar', {}),

    /** Começar põe o quarto em limpeza; acabar e verificar deixam-no disponível. */
    estado: (id: number, accao: 'comecar' | 'acabar' | 'verificar' | 'problema', problema?: string) =>
        api.criar<RespostaDaTarefa>(`/hotel/limpeza/${id}/estado`, { accao, problema }),

    ponto: (id: number, indice: number) => api.criar<RespostaDaTarefa>(`/hotel/limpeza/${id}/ponto`, { indice }),

    atribuir: (id: number, pessoa: string) => api.criar<RespostaDaTarefa>(`/hotel/limpeza/${id}/atribuir`, { pessoa }),
};

/* ─── As reservas ───────────────────────────────────────────────────── */

export type OpcoesDasReservas = {
    estados: Escolha[];
    fontes: Escolha[];
    estados_de_pagamento: Escolha[];
    filtros_de_data: Escolha[];
    /** O preço base viaja com o tipo: escolher o tipo preenche a taxa. */
    tipos_de_quarto: Array<Escolha & { preco: number }>;
    /** `tipo` liga o quarto ao tipo — a lista filtra-se sem ir ao servidor. */
    quartos: Array<Escolha & { tipo: string }>;
    meios_de_pagamento: Escolha[];
    provincias: string[];
    permissoes: {
        pode_criar: boolean;
        pode_editar: boolean;
        pode_apagar: boolean;
        /** Criar o hóspede aqui mesmo é a permissão da FICHA, não a da reserva. */
        pode_criar_hospede: boolean;
    };
};

/** O que se pode fazer a esta reserva — decidido pela tabela de transições. */
export type PodeNaReserva = {
    confirmar: boolean;
    entrada: boolean;
    saida: boolean;
    cancelar: boolean;
    nao_compareceu: boolean;
    editar: boolean;
    receber: boolean;
};

export type Reserva = {
    id: number;
    numero: string;
    codigo: string | null;
    client_id: number | null;
    hospede: string;
    telefone: string | null;
    email: string | null;
    room_type_id: number | null;
    tipo_de_quarto: string | null;
    room_id: number | null;
    quarto: string | null;
    entrada: string | null;
    saida: string | null;
    noites: number;
    adultos: number;
    criancas: number;
    camas_extra: number;
    fonte: string;
    fonte_rotulo: string;
    estado: string;
    estado_rotulo: string;
    taxa: number;
    total: number;
    pago: number;
    por_receber: number;
    estado_de_pagamento: string;
    estado_de_pagamento_rotulo: string;
    invoice_id: number | null;
    factura: string | null;
    pode: PodeNaReserva;

    /* Só na ficha (o modal de ver). */
    subtotal?: number;
    desconto?: number;
    imposto?: number;
    extras?: number;
    pedidos?: string | null;
    notas?: string | null;
    meio_de_pagamento?: string | null;
    criada_por?: string | null;
    criada_em?: string | null;
    cancelada_em?: string | null;
    motivo_do_cancelamento?: string | null;
};

export type FiltrosDasReservas = {
    procura?: string;
    estado?: string;
    fonte?: string;
    quando?: string;
    page?: number;
    por_pagina?: number;
};

export type ListaDeReservas = {
    data: Reserva[];
    meta: { total: number; current_page: number; last_page: number; per_page: number };
    /** Da casa, e não da página nem do filtro. */
    resumo: { entram_hoje: number; saem_hoje: number; hospedados: number; pendentes: number };
};

export type HospedeDaProcura = {
    id: number;
    nome: string;
    telefone: string | null;
    email: string | null;
    nif: string | null;
    vip: boolean;
    /** Quem está na lista negra não volta a ficar hospedado. */
    lista_negra: boolean;
};

export type QuartoLivre = {
    id: number;
    numero: string;
    piso: string | null;
    limpeza: string | null;
    limpeza_rotulo: string;
};

export type ReservaParaGravar = {
    client_id: string;
    room_type_id: string;
    room_id: string;
    check_in_date: string;
    check_out_date: string;
    adults: string;
    children: string;
    extra_beds: string;
    source: string;
    room_rate: string;
    discount: string;
    special_requests: string;
    internal_notes: string;
    payment_method: string;
    paid_amount: string;
};

/** `aviso` é o das facturas por regularizar — cancelar não anula documentos. */
type RespostaDaReserva = { data: Reserva; message?: string; aviso?: string | null };

export const reservas = {
    opcoes: () => api.ler<OpcoesDasReservas>('/hotel/reservas/opcoes'),
    lista: (filtros: FiltrosDasReservas) => api.ler<ListaDeReservas>('/hotel/reservas', filtros),
    ficha: (id: number) => api.ler<{ data: Reserva }>(`/hotel/reservas/${id}`),
    hospedes: (procura: string) => api.ler<{ data: HospedeDaProcura[] }>('/hotel/reservas/hospedes', { procura }),
    quartosLivres: (id: number) => api.ler<{ data: QuartoLivre[] }>(`/hotel/reservas/${id}/quartos-livres`),

    criar: (dados: ReservaParaGravar) => api.criar<RespostaDaReserva>('/hotel/reservas', dados),
    guardar: (id: number, dados: ReservaParaGravar) => api.guardar<RespostaDaReserva>(`/hotel/reservas/${id}`, dados),

    estado: (id: number, accao: 'confirmar' | 'entrada' | 'nao-compareceu' | 'cancelar', extra?: { quarto?: number; motivo?: string }) =>
        api.criar<RespostaDaReserva>(`/hotel/reservas/${id}/estado`, { accao, ...extra }),

    receber: (id: number, valor: string, meio: string, facturar: boolean) =>
        api.criar<RespostaDaReserva>(`/hotel/reservas/${id}/receber`, { valor, meio, facturar }),
};
