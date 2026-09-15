import { api, criarApi } from './cliente';

/**
 * A OFICINA, para os ecrãs em React.
 *
 * Os catálogos (mecânicos, viaturas, serviços) e as peças passam pelas rotas
 * genéricas — `api/catalogos.ts` e `api/produtos.ts`. Aqui ficam os dois ecrãs
 * que são só da oficina: o painel e os mapas.
 */

export type Escolha = { valor: string; rotulo: string };

export type Serie = { etiquetas: string[]; valores: number[]; chaves?: string[] };

/** Uma ordem urgente, como o painel a mostra. */
export type OrdemUrgente = {
    id: number;
    numero: string;
    matricula: string | null;
    dono: string | null;
    estado: string;
    estado_rotulo: string;
};

/** Uma viatura com documentos a caducar, e quais. */
export type ViaturaComDocumentos = {
    id: number;
    matricula: string;
    viatura: string;
    documentos: Array<{ nome: string; quando: string; dias: number }>;
};

export type PainelDaOficina = {
    periodo: { de: string; ate: string };
    /**
     * SE QUEM ESTÁ A VER PODE VER DINHEIRO.
     *
     * Quando é falso, os valores nem saem do servidor: `dinheiro` vem nulo e
     * os gráficos contam ORDENS em vez de kwanzas. O Blade escondia-os no HTML
     * com «•••» e mandava o número na mesma para o browser.
     */
    ve_dinheiro: boolean;
    cartoes: { ordens: number; pendentes: number; em_curso: number; concluidas: number };
    dinheiro: { facturado: number; a_receber: number } | null;
    viaturas: { total: number; activas: number };
    series: { mensal: Serie; estados: Serie; servicos: Serie; mecanicos: Serie };
    top_servicos: Array<{ nome: string; vezes: number; receita: number | null }>;
    urgentes: OrdemUrgente[];
    documentos_a_caducar: ViaturaComDocumentos[];
};

export const painelDaOficina = {
    ler: (de?: string, ate?: string) =>
        api.ler<PainelDaOficina>('/oficina/painel', { de, ate }),
};

/* ─── Os mapas ──────────────────────────────────────────────────────── */

export type ColunaDoMapa = {
    chave: string;
    rotulo: string;
    formato: 'texto' | 'numero' | 'dinheiro' | 'data' | 'estado';
};

export type LinhaDoMapa = Record<string, string | number | null>;

export type OpcoesDosMapasDaOficina = {
    mapas: Array<Escolha & { icone: string; pede_estado: boolean }>;
    estados: Escolha[];
};

export type MapaDaOficina = {
    mapa: string;
    /** As moradas do papel e do Excel — compostas pelo servidor, com os filtros. */
    descargas: { pdf: string; excel: string };
    colunas: ColunaDoMapa[];
    linhas: LinhaDoMapa[];
    totais: LinhaDoMapa | null;
    /** A frase de «não há nada», escrita pelo mapa que a diz. */
    nada: string | null;
    periodo: { de: string; ate: string };
};

export type FiltrosDoMapa = {
    mapa: string;
    de?: string;
    ate?: string;
    estado?: string;
};

export const mapasDaOficina = {
    opcoes: () => api.ler<OpcoesDosMapasDaOficina>('/oficina/relatorios/opcoes'),
    mostrar: (filtros: FiltrosDoMapa) => api.ler<MapaDaOficina>('/oficina/relatorios', filtros),
};

/* ─── As ordens de serviço ──────────────────────────────────────────── */

export type ViaturaParaEscolher = Escolha & { dono: string | null; km: number };
export type ServicoParaEscolher = Escolha & {
    codigo: string | null;
    descricao: string | null;
    preco: number;
    horas: number;
};
export type ArtigoParaEscolher = Escolha & {
    codigo: string | null;
    descricao: string | null;
    preco: number;
    unidade: string | null;
    /** A existência no armazém DE ONDE A PEÇA VAI SAIR, e não a soma de todos. */
    stock: number;
};

export type OpcoesDasOrdens = {
    estados: Escolha[];
    prioridades: Escolha[];
    categorias_de_anexo: Escolha[];
    viaturas: ViaturaParaEscolher[];
    mecanicos: Escolha[];
    servicos: ServicoParaEscolher[];
    /** OF-10: o cliente recebe SMS/email quando a ordem muda (módulo Notificações configurado). */
    avisos_ao_cliente?: boolean;
    permissoes: {
        pode_criar: boolean;
        pode_editar: boolean;
        pode_apagar: boolean;
        /** Facturar é emitir um documento fiscal: é a permissão da facturação. */
        pode_facturar: boolean;
    };
};

export type Ordem = {
    id: number;
    numero: string;
    matricula: string | null;
    viatura: string | null;
    dono: string | null;
    mecanico: string | null;
    mechanic_id: number | null;
    vehicle_id: number | null;
    entrada: string | null;
    estado: string;
    estado_rotulo: string;
    prioridade: string;
    prioridade_rotulo: string;
    km: number;
    mao_de_obra: number;
    pecas: number;
    desconto: number;
    imposto: number;
    total: number;
    pago: number;
    saldo: number;
    estado_pagamento: string | null;
    facturada: boolean;
    /** Agendada para uma data já passada, e ainda por fechar. */
    atrasada: boolean;
    dias_na_oficina: number;
};

export type LinhaDaOrdem = {
    id: number;
    tipo: 'service' | 'part';
    codigo: string | null;
    nome: string;
    descricao: string | null;
    quantidade: number;
    preco: number;
    desconto: number;
    subtotal: number;
    horas: number;
    mecanico: string | null;
    referencia: string | null;
    marca: string | null;
    original: boolean;
    /** OF-03: só as aprovadas contam para os totais, saem do stock e vão à factura. */
    aprovacao: 'approved' | 'pending' | 'declined';
    aprovacao_em: string | null;
    aprovacao_por: string | null;
};

/** O estado da aprovação do orçamento pelo cliente (OF-03). */
export type AprovacaoDaOrdem = {
    a_espera: number;
    valor_a_espera: number;
    recusadas: number;
    link: string | null;
    pedido_em: string | null;
    expira_em: string | null;
    assinado_por: string | null;
    assinado_em: string | null;
    assinatura: string | null;
};

export type EventoDaOrdem = {
    id: number;
    accao: string;
    descricao: string | null;
    quem: string | null;
    quando: string | null;
};

export type AnexoDaOrdem = {
    id: number;
    nome: string;
    url: string;
    categoria: string;
    categoria_rotulo: string;
    tamanho: string;
    imagem: boolean;
    descricao: string | null;
    quem: string | null;
    quando: string | null;
};

/** A ficha traz a viatura por extenso, para o separador de informação. */
export type ViaturaDaFicha = {
    id: number;
    matricula: string;
    marca: string | null;
    modelo: string | null;
    ano: number | null;
    cor: string | null;
    combustivel: string | null;
    dono: string | null;
    telefone: string | null;
};

export type FichaDaOrdem = Ordem & {
    /** A viatura por extenso — chave própria, para não tapar a da lista. */
    viatura_ficha: ViaturaDaFicha | null;
    agendada_para: string | null;
    iniciada_em: string | null;
    concluida_em: string | null;
    entregue_em: string | null;
    garantia_ate: string | null;
    garantia_dias: number;
    problema: string | null;
    diagnostico: string | null;
    trabalho: string | null;
    recomendacoes: string | null;
    notas: string | null;
    linhas: LinhaDaOrdem[];
    aprovacao: AprovacaoDaOrdem;
    historico: EventoDaOrdem[];
    anexos: AnexoDaOrdem[];
    factura: { id: number; numero: string; cliente: string | null; quando: string | null; morada: string } | null;
};

export type FiltrosDasOrdens = {
    procura?: string;
    estado?: string;
    prioridade?: string;
    page?: number;
    por_pagina?: number;
};

export type ListaDeOrdens = {
    data: Ordem[];
    meta: { total: number; current_page: number; last_page: number; per_page: number };
    resumo: { total: number; em_aberto: number; em_curso: number; concluidas: number };
};

/** O que o formulário da ordem grava. */
export type OrdemParaGravar = {
    vehicle_id: string;
    mechanic_id: string;
    received_at: string;
    scheduled_for: string;
    mileage_in: string;
    problem_description: string;
    diagnosis: string;
    work_performed: string;
    recommendations: string;
    status: string;
    priority: string;
    warranty_days: string;
    notes: string;
};

export type LinhaParaGravar = {
    type: 'service' | 'part';
    service_id: string;
    product_id: string;
    code: string;
    name: string;
    description: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    hours: string;
    mechanic_id: string;
    part_number: string;
    brand: string;
    is_original: boolean;
    /** OF-03: a linha fica à espera da aprovação do cliente. */
    precisa_aprovacao: boolean;
};

export const ordens = {
    opcoes: () => api.ler<OpcoesDasOrdens>('/oficina/ordens/opcoes'),

    /** As peças procuram-se: o catálogo desta casa tem doze mil artigos. */
    artigos: (procura: string) =>
        api.ler<{ armazem: string | null; data: ArtigoParaEscolher[] }>('/oficina/ordens/artigos', { procura }),

    lista: (filtros: FiltrosDasOrdens) => api.ler<ListaDeOrdens>('/oficina/ordens', filtros),

    ficha: (id: number) => api.ler<{ data: FichaDaOrdem }>(`/oficina/ordens/${id}`),

    criar: (dados: OrdemParaGravar) =>
        api.criar<{ data: Ordem; message: string }>('/oficina/ordens', dados),

    guardar: (id: number, dados: OrdemParaGravar) =>
        api.guardar<{ data: Ordem; message: string }>(`/oficina/ordens/${id}`, dados),

    apagar: (id: number) => api.apagar<{ message: string }>(`/oficina/ordens/${id}`),

    /**
     * MUDAR O ESTADO — a porta única.
     *
     * Não é um `update` de uma coluna: passar a «Concluída» desconta as peças
     * do stock e anular devolve-as. As que não conseguiram sair vêm em
     * `falhas`, para o ecrã as poder mostrar como aviso.
     */
    estado: (id: number, estado: string) =>
        api.criar<{ data: Ordem; message: string; falhas: string[] }>(`/oficina/ordens/${id}/estado`, { estado }),

    juntarLinha: (id: number, dados: LinhaParaGravar) =>
        api.criar<{ message: string }>(`/oficina/ordens/${id}/linhas`, dados),

    tirarLinha: (id: number, linha: number) =>
        api.apagar<{ message: string }>(`/oficina/ordens/${id}/linhas/${linha}`),

    desconto: (id: number, desconto: number) =>
        api.guardar<{ message: string }>(`/oficina/ordens/${id}/desconto`, { desconto }),

    facturar: (id: number) =>
        api.criar<{ message: string; morada: string }>(`/oficina/ordens/${id}/facturar`, {}),

    anexar: (id: number, ficheiros: File[], categoria: string, descricao: string) => {
        const corpo = new FormData();
        ficheiros.forEach((f) => corpo.append('ficheiros[]', f));
        corpo.append('categoria', categoria);
        corpo.append('descricao', descricao);

        return api.enviar<{ message: string }>(`/oficina/ordens/${id}/anexos`, corpo);
    },

    apagarAnexo: (id: number, anexo: number) =>
        api.apagar<{ message: string }>(`/oficina/ordens/${id}/anexos/${anexo}`),

    /* OF-03: o orçamento aprovado pelo cliente. */
    pedirAprovacao: (id: number) => api.criar<{ data: AprovacaoDaOrdem; message: string }>(`/oficina/ordens/${id}/aprovacao`, {}),
    cancelarAprovacao: (id: number) => api.apagar<{ data: AprovacaoDaOrdem; message: string }>(`/oficina/ordens/${id}/aprovacao`),
    decidirLinha: (id: number, linha: number, decisao: LinhaDaOrdem['aprovacao']) =>
        api.criar<{ data: AprovacaoDaOrdem; message: string }>(`/oficina/ordens/${id}/linhas/${linha}/aprovacao`, { decisao }),
};

/** Uma folha de obra na ficha da viatura, com a factura que saiu dela. */
export type FolhaDaViatura = {
    id: number;
    numero: string;
    entrada: string | null;
    concluida: string | null;
    estado: string;
    estado_rotulo: string;
    mecanico: string | null;
    km: number;
    problema: string | null;
    total: number;
    factura: {
        id: number;
        numero: string;
        tipo: string;
        data: string | null;
        vencimento: string | null;
        estado: string;
        estado_rotulo: string;
        total: number;
        pago: number;
        falta: number;
        vencida: boolean;
        preview: string | null;
        pdf: string | null;
    } | null;
};

export type FolhasDaViatura = {
    viatura: { id: number; matricula: string; viatura: string; dono: string | null; cliente: string | null; km: number };
    resumo: { ordens: number; abertas: number; facturas: number; facturado: number; por_receber: number; ultima_visita: string | null; fotos: number };
    ordens: FolhaDaViatura[];
    pode_ver_facturas: boolean;
};

/** As folhas de obra de uma viatura e as facturas que saíram delas — a ficha da viatura. */
export const folhasDaViatura = (id: number) => api.ler<FolhasDaViatura>(`/oficina/ordens/viatura/${id}`);

/* ─── As fotografias da viatura — antes, durante, depois e danos ─────── */

export type FaseDaFoto = 'antes' | 'durante' | 'depois' | 'dano';

export type FotoDaViatura = {
    /** Número nas da viatura; `anexo-N` nas que vêm de uma folha de obra (só se lêem aqui). */
    id: number | string;
    origem: 'viatura' | 'ordem';
    url: string;
    fase: FaseDaFoto;
    servico: string;
    zona: string | null;
    descricao: string | null;
    ordem_id: number | null;
    ordem: string | null;
    nome: string | null;
    largura: number | null;
    altura: number | null;
    por: string | null;
    em: string | null;
};

export type FotosDaViatura = {
    fotos: FotoDaViatura[];
    ordens: Escolha[];
    listas: { fases: Escolha[]; servicos: Escolha[]; zonas: Escolha[] };
    pode_editar: boolean;
};

/** O que se diz de um lote de fotografias: a fase, o serviço, a zona, a folha. */
export type DadosDaFoto = { fase: FaseDaFoto; servico: string; zona: string; ordem_id: string; descricao: string };

export const fotografiasDaViatura = {
    ler: (id: number) => api.ler<FotosDaViatura>(`/oficina/viaturas/${id}/fotografias`),

    juntar: (id: number, ficheiros: File[], dados: DadosDaFoto) => {
        const corpo = new FormData();
        ficheiros.forEach((f) => corpo.append('fotografias[]', f));
        (Object.keys(dados) as Array<keyof DadosDaFoto>).forEach((k) => { if (dados[k] !== '') corpo.append(k, dados[k]); });

        return api.enviar<{ data: FotoDaViatura[]; message: string }>(`/oficina/viaturas/${id}/fotografias`, corpo);
    },

    mudar: (id: number, foto: number, dados: DadosDaFoto) =>
        api.guardar<{ data: FotoDaViatura; message: string }>(`/oficina/viaturas/${id}/fotografias/${foto}`, {
            ...dados, zona: dados.zona || null, ordem_id: dados.ordem_id || null, descricao: dados.descricao || null,
        }),

    tirar: (id: number, foto: number) => api.apagar<{ message: string }>(`/oficina/viaturas/${id}/fotografias/${foto}`),
};

/* ─── O check-in da viatura (OF-01) ─────────────────────────────────── */

export type DanoDoCheckin = { x: number; y: number; tipo: string; nota: string | null };

export type CheckinDaOrdem = {
    existe: boolean;
    km: number;
    /** Em oitavos do depósito: 0 = vazio, 8 = cheio. */
    combustivel: number | null;
    danos: DanoDoCheckin[];
    acessorios: string[];
    luzes: string[];
    chaves: number | null;
    objectos: string | null;
    notas: string | null;
    assinatura: string | null;
    assinado_por: string | null;
    assinado_em: string | null;
    /** Falso quando se mudou alguma coisa depois de o cliente assinar. */
    assinatura_valida: boolean;
    registado_por: string | null;
    actualizado_em: string | null;
};

export type RespostaDoCheckin = {
    data: CheckinDaOrdem;
    listas: {
        tipos_de_dano: Array<Escolha & { letra: string; cor: string }>;
        acessorios: Escolha[];
        luzes: Escolha[];
    };
    pode_editar: boolean;
    message?: string;
};

export type CheckinParaGravar = {
    km: number | null;
    combustivel: number | null;
    danos: DanoDoCheckin[];
    acessorios: string[];
    luzes: string[];
    chaves: number | null;
    objectos: string;
    notas: string;
};

export const checkin = {
    ler: (id: number) => api.ler<RespostaDoCheckin>(`/oficina/ordens/${id}/checkin`),
    gravar: (id: number, dados: CheckinParaGravar) => api.guardar<RespostaDoCheckin>(`/oficina/ordens/${id}/checkin`, dados),
    assinar: (id: number, assinatura: string, nome: string) => api.criar<RespostaDoCheckin>(`/oficina/ordens/${id}/checkin/assinatura`, { assinatura, nome }),
    tirarAssinatura: (id: number) => api.apagar<RespostaDoCheckin>(`/oficina/ordens/${id}/checkin/assinatura`),
};

/* ─── A inspecção digital com semáforo (OF-02) ──────────────────────── */

export type EstadoDoPonto = 'ok' | 'atencao' | 'urgente' | 'na';

export type PontoDaInspeccao = { seccao: string; ponto: string; estado: EstadoDoPonto | null; nota: string | null; foto: string | null };

export type InspeccaoDaOrdem = {
    id: number;
    nome: string;
    /** OF-09: `qualidade` é o controlo de qualidade antes de concluir. */
    tipo?: 'inspecao' | 'qualidade';
    concluida_em: string | null;
    por: string | null;
    em: string | null;
    contas: { ok: number; atencao: number; urgente: number; na: number; por_ver: number };
    pontos: PontoDaInspeccao[];
};

export type RespostaDasInspeccoes = {
    inspeccoes: InspeccaoDaOrdem[];
    modelos: Array<Escolha & { pontos: number; padrao: boolean; tipo: 'inspecao' | 'qualidade'; obrigatorio: boolean }>;
    estados: Escolha[];
    /** OF-09: se a ordem só conclui com o controlo de qualidade, e o que falta. */
    qualidade: { obrigatorio: boolean; pendente: string | null };
    recomendacoes: string | null;
    pode_editar: boolean;
    criada?: number;
    message?: string;
};

export const inspeccoes = {
    ler: (id: number) => api.ler<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes`),
    comecar: (id: number, modeloId: string) => api.criar<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes`, { modelo_id: Number(modeloId) }),
    gravar: (id: number, inspeccao: number, resultados: Array<{ estado: EstadoDoPonto | null; nota: string | null }>, extra: { concluir?: boolean; reabrir?: boolean } = {}) =>
        api.guardar<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes/${inspeccao}`, { resultados, ...extra }),
    apagar: (id: number, inspeccao: number) => api.apagar<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes/${inspeccao}`),
    recomendar: (id: number, inspeccao: number) => api.criar<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes/${inspeccao}/recomendar`, {}),
    foto: (id: number, inspeccao: number, ponto: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('foto', ficheiro);
        return api.enviar<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes/${inspeccao}/pontos/${ponto}/foto`, corpo);
    },
    tirarFoto: (id: number, inspeccao: number, ponto: number) => api.apagar<RespostaDasInspeccoes>(`/oficina/ordens/${id}/inspeccoes/${inspeccao}/pontos/${ponto}/foto`),
};

/* ─── O orçamento visto pelo cliente, pelo link (OF-03) — sem sessão ── */

export type OrcamentoParaAprovar = {
    empresa: { nome: string | null; telefone: string | null; email: string | null };
    ordem: { numero: string; matricula: string | null; viatura: string; dono: string | null; problema: string | null; diagnostico: string | null };
    linhas: Array<{ id: number; tipo: 'service' | 'part'; nome: string; descricao: string | null; quantidade: number; preco: number; desconto: number; subtotal: number; aprovacao: 'approved' | 'pending' | 'declined'; decidida_em: string | null }>;
    contas: { aprovado: number; a_espera: number; desconto: number };
    aberto: boolean;
    motivo: string | null;
    expira_em: string | null;
    assinado_por: string | null;
    assinado_em: string | null;
};

const pelaChave = criarApi('/oficina/aprovar');

export const orcamentoPeloLink = {
    ver: (token: string) => pelaChave.ler<OrcamentoParaAprovar>(`/${token}/dados`),
    responder: (token: string, decisoes: Record<number, 'approved' | 'declined'>, nome: string, assinatura: string) =>
        pelaChave.criar<{ message: string }>(`/${token}`, { decisoes, nome, assinatura }),
};
/* ─── O quadro de trabalho (OF-04) ──────────────────────────────────── */

export type CartaoDoQuadro = {
    id: number;
    numero: string;
    matricula: string | null;
    tag: string | null;
    wo: string | null;
    viatura: string | null;
    dono: string | null;
    mecanico_id: number | null;
    mecanico: string | null;
    prioridade: string;
    prioridade_rotulo: string;
    entrada: string | null;
    agendada_para: string | null;
    atrasada: boolean;
    total: number;
    facturada: boolean;
    checkin: 'feito' | 'assinado' | null;
    a_espera: number;
    /** OF-06: os mecânicos com o relógio a correr neste carro. */
    a_trabalhar: string[];
};

export type QuadroDaOficina = {
    colunas: Array<{ estado: string; rotulo: string; cartoes: CartaoDoQuadro[] }>;
    mecanicos: Escolha[];
    pode_editar: boolean;
};

export const quadroDaOficina = {
    ler: (filtros: { mecanico?: string; procura?: string }) => api.ler<QuadroDaOficina>('/oficina/ordens/quadro', filtros),
    mecanico: (id: number, mecanicoId: string) =>
        api.criar<{ message: string }>(`/oficina/ordens/${id}/mecanico`, { mechanic_id: mecanicoId === '' ? null : Number(mecanicoId) }),
};

/* ─── A agenda da oficina (OF-05) ───────────────────────────────────── */

export type MarcacaoDaAgenda = {
    id: number;
    /** `2026-09-16T09:00`, na hora local da oficina. */
    inicio: string;
    fim: string;
    duracao: number;
    estado: 'marcada' | 'confirmada' | 'chegou' | 'faltou' | 'cancelada';
    estado_rotulo: string;
    servico: string;
    notas: string | null;
    vehicle_id: number | null;
    matricula: string | null;
    viatura: string | null;
    cliente: string | null;
    telefone: string | null;
    plate: string | null;
    customer_name: string | null;
    customer_phone: string | null;
    bay_id: number | null;
    lugar: string | null;
    cor: string | null;
    mechanic_id: number | null;
    mecanico: string | null;
    ordem_id: number | null;
    ordem: string | null;
};

export type AgendaDaOficina = {
    marcacoes: MarcacaoDaAgenda[];
    lugares: Array<Escolha & { cor: string; tipo: string }>;
    mecanicos: Escolha[];
    estados: Escolha[];
    horario: { abre: string; fecha: string };
    pode_criar: boolean;
    pode_editar: boolean;
};

export type MarcacaoParaGravar = {
    vehicle_id: string;
    plate: string;
    customer_name: string;
    customer_phone: string;
    bay_id: string;
    mechanic_id: string;
    inicio: string;
    duracao: string;
    service: string;
    notes: string;
};

export const agendaDaOficina = {
    ler: (de: string, ate: string) => api.ler<AgendaDaOficina>('/oficina/agenda', { de, ate }),
    criar: (d: MarcacaoParaGravar) => api.criar<{ data: MarcacaoDaAgenda; message: string }>('/oficina/agenda', d),
    guardar: (id: number, d: MarcacaoParaGravar) => api.guardar<{ data: MarcacaoDaAgenda; message: string }>(`/oficina/agenda/${id}`, d),
    estado: (id: number, estado: string) => api.criar<{ data: MarcacaoDaAgenda; message: string }>(`/oficina/agenda/${id}/estado`, { estado }),
    chegou: (id: number) => api.criar<{ data: MarcacaoDaAgenda; ordem_id: number; message: string }>(`/oficina/agenda/${id}/chegou`, {}),
    apagar: (id: number) => api.apagar<{ message: string }>(`/oficina/agenda/${id}`),
};

/* ─── O registo de tempos por tarefa (OF-06) ────────────────────────── */

export type RegistoDeTempo = {
    id: number;
    mecanico_id: number;
    mecanico: string | null;
    linha_id: number | null;
    linha: string | null;
    inicio: string;
    fim: string | null;
    inicio_iso: string;
    minutos: number;
    a_correr: boolean;
    notas: string | null;
};

export type TemposDaOrdem = {
    registos: RegistoDeTempo[];
    linhas: Array<{ id: number; nome: string; vendidas: number; trabalhadas: number }>;
    contas: { vendidas: number; trabalhadas: number; eficiencia: number | null };
    mecanicos: Escolha[];
    mecanico_da_ordem: string | null;
    aberta: boolean;
    pode_editar: boolean;
    message?: string;
};

export const temposDaOrdem = {
    ler: (id: number) => api.ler<TemposDaOrdem>(`/oficina/ordens/${id}/tempos`),
    comecar: (id: number, mecanicoId: string, linhaId: string) =>
        api.criar<TemposDaOrdem>(`/oficina/ordens/${id}/tempos`, { mecanico_id: Number(mecanicoId) || null, linha_id: linhaId ? Number(linhaId) : null }),
    parar: (id: number, registo: number) => api.criar<TemposDaOrdem>(`/oficina/ordens/${id}/tempos/${registo}/parar`, {}),
    corrigir: (id: number, registo: number, inicio: string, fim: string) => api.guardar<TemposDaOrdem>(`/oficina/ordens/${id}/tempos/${registo}`, { inicio, fim }),
    apagar: (id: number, registo: number) => api.apagar<TemposDaOrdem>(`/oficina/ordens/${id}/tempos/${registo}`),
};

/* ─── Os pacotes de serviço (OF-07) ─────────────────────────────────── */

export type LinhaDoPacote = {
    tipo: 'service' | 'part';
    service_id: number | null;
    product_id: number | null;
    codigo: string | null;
    nome: string;
    quantidade: number;
    preco: number;
    desconto: number;
    horas: number;
};

export type PacoteDeServico = {
    id: number;
    nome: string;
    descricao: string | null;
    linhas: LinhaDoPacote[];
    total: number;
    horas: number;
    usado: number;
    activo: boolean;
};

export const pacotesDeServico = {
    lista: () => api.ler<{ data: PacoteDeServico[]; pode_gerir: boolean }>('/oficina/pacotes'),
    criar: (d: { nome: string; descricao: string; activo: boolean; linhas: LinhaDoPacote[] }) =>
        api.criar<{ data: PacoteDeServico; message: string }>('/oficina/pacotes', d),
    guardar: (id: number, d: { nome: string; descricao: string; activo: boolean; linhas: LinhaDoPacote[] }) =>
        api.guardar<{ data: PacoteDeServico; message: string }>(`/oficina/pacotes/${id}`, d),
    apagar: (id: number) => api.apagar<{ message: string }>(`/oficina/pacotes/${id}`),
    juntarAOrdem: (ordem: number, pacote: number, precisaAprovacao: boolean) =>
        api.criar<{ message: string }>(`/oficina/ordens/${ordem}/pacotes`, { pacote_id: pacote, precisa_aprovacao: precisaAprovacao }),
    guardarDaOrdem: (ordem: number, nome: string) =>
        api.criar<{ data: PacoteDeServico; message: string }>(`/oficina/ordens/${ordem}/guardar-pacote`, { nome }),
};

/* ─── As peças da ordem e o que falta (OF-08) ───────────────────────── */

export type PecasDaOrdem = {
    armazem: string | null;
    pecas: Array<{ id: number; nome: string; codigo: string | null; quantidade: number; do_catalogo: boolean; em_stock: number | null; falta: number; aprovacao: string }>;
    requisicoes: Array<{ id: number; numero: string; estado: string; estado_rotulo: string; linhas: number; em: string | null }>;
    tem_compras: boolean;
    pode_pedir: boolean;
    estado_da_ordem: string;
    message?: string;
};

export const pecasDaOrdem = {
    ler: (id: number) => api.ler<PecasDaOrdem>(`/oficina/ordens/${id}/pecas`),
    requisitar: (id: number, linhas: Array<{ linha_id: number; quantidade: number }>, submeter: boolean, esperar: boolean) =>
        api.criar<PecasDaOrdem>(`/oficina/ordens/${id}/pecas/requisicao`, { linhas, submeter, esperar }),
};

/* ─── Os lembretes de manutenção (OF-11) ────────────────────────────── */

export type TipoDeLembrete = 'revisao' | 'seguro' | 'inspeccao' | 'livrete';
export type CanalDeContacto = 'sms' | 'email' | 'whatsapp' | 'telefone' | 'nota';

export type Lembrete = {
    chave: string;
    viatura_id: number;
    tipo: TipoDeLembrete;
    tipo_rotulo: string;
    vencimento: string;
    vencido: boolean;
    /** Dias até à data (negativo = já passou); nulo na revisão só por km. */
    dias: number | null;
    data: string | null;
    km_previstos: number | null;
    km_estimados: number | null;
    km_por_dia: number | null;
    faltam_km: number | null;
    ultima_revisao: string | null;
    matricula: string;
    marca_modelo: string;
    dono: string | null;
    telefone: string | null;
    email: string | null;
    na_oficina: boolean;
    pausado_ate: string | null;
    /** O texto curto já preenchido — o que segue no WhatsApp. */
    mensagem: string;
    contactos: number;
    ultimo_contacto: { canal: CanalDeContacto; canal_rotulo: string; quando: string | null; por: string | null; nota: string | null } | null;
};

export type DefinicoesDosLembretes = {
    service_interval_km: number;
    service_interval_months: number;
    remind_days_before: number;
    remind_km_before: number;
    documents_days_before: number;
    auto_reminders: boolean;
};

export type LembretesDaOficina = {
    data: Lembrete[];
    definicoes: DefinicoesDosLembretes;
    canais: { sms: boolean; email: boolean };
    tipos: Escolha[];
    pode_gerir: boolean;
};

export type RevisaoParaGravar = {
    proxima_data: string | null;
    proximo_km: number | null;
    intervalo_km: number | null;
    intervalo_meses: number | null;
    feita?: boolean;
};

export const lembretesDaOficina = {
    ler: () => api.ler<LembretesDaOficina>('/oficina/lembretes'),
    definicoes: (d: DefinicoesDosLembretes) => api.guardar<{ definicoes: DefinicoesDosLembretes; message: string }>('/oficina/lembretes/definicoes', d),
    contacto: (viatura: number, d: { tipo: TipoDeLembrete; canal: CanalDeContacto; nota?: string }) =>
        api.criar<{ message: string }>(`/oficina/lembretes/${viatura}/contacto`, d),
    adiar: (viatura: number, dias: number) => api.criar<{ message: string }>(`/oficina/lembretes/${viatura}/adiar`, { dias }),
    revisao: (viatura: number, d: RevisaoParaGravar) => api.guardar<{ message: string }>(`/oficina/lembretes/${viatura}/revisao`, d),
};
