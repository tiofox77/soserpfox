/**
 * A PONTE DOS ECRÃS DA CONTABILIDADE.
 *
 * Fala com `/api/v1/invoicing/react/contabilidade/*`. As regras de um
 * lançamento vivem no servidor (`Services\Accounting\Lancamentos`) — o ecrã
 * mostra-as e obedece-lhes, não as repete.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

export type Serie = { etiquetas: string[]; valores: number[] };

type Meta = {
    current_page: number; last_page: number; per_page: number;
    total: number; from: number | null; to: number | null;
};

/* ─── O painel ────────────────────────────────────────────────────────── */

export type PainelDaContabilidade = {
    periodo: { de: string; ate: string };
    /**
     * Quem não pode ver relatórios não vê valores — e eles não viajam: o
     * servidor manda `null` em vez de os mandar e pedir ao ecrã que os tape.
     */
    ve_valores: boolean;
    /** A natureza decide o sinal: activo e gasto a débito, o resto a crédito. */
    saldos: {
        activo: number | null; passivo: number | null; capital: number | null;
        proveitos: number | null; gastos: number | null; resultado: number | null;
    };
    plano: {
        contas: number; activo: number; passivo: number; capital: number;
        proveitos: number; gastos: number; bloqueadas: number; agregacao: number;
    };
    lancamentos: { total: number; rascunhos: number; confirmados: number; do_mes: number };
    recentes: Array<{
        id: number; ref: string; dia: string | null; diario: string | null;
        autor: string | null; total: number | null; nota: string | null;
    }>;
    mensal: Serie;
    contas_movimentadas: Serie;
    por_diario: Serie;
};

/* ─── O plano de contas ───────────────────────────────────────────────── */

export type Conta = {
    id: number;
    codigo: string;
    nome: string;
    tipo: string;
    tipo_rotulo: string;
    natureza: string;
    nivel: number;
    mae: string | null;
    mae_id: number | null;
    /** Soma as filhas e não recebe movimento próprio. */
    agregacao: boolean;
    bloqueada: boolean;
    descricao: string | null;
    chave: string | null;
    subtipo: string | null;
    custo_fixo: boolean;
    /** Quantas linhas de lançamento já tem — é o que decide se se pode apagar. */
    linhas: number;
};

export type FichaDaConta = Conta & {
    imposto_id: number | null;
    imposto: string | null;
    centro_de_custo_id: number | null;
    centro_de_custo: string | null;
    reflexao_debito_id: number | null;
    reflexao_debito: string | null;
    reflexao_credito_id: number | null;
    reflexao_credito: string | null;
    filhas: number;
    /** Liga a conta ao balanço e à demonstração de resultados. Só se lê. */
    chave_de_integracao: string | null;
    criada_em: string | null;
    actualizada_em: string | null;
};

export type OpcoesDasContas = {
    tipos: Escolha[];
    naturezas: Escolha[];
    /** As contas-mãe são as de agregação: só essas somam filhas. */
    maes: Array<Escolha & { nivel: number }>;
    impostos: Escolha[];
    centros_de_custo: Escolha[];
    contas: Escolha[];
    permissoes: { criar: boolean; editar: boolean; eliminar: boolean };
};

export type LinhaDaRazao = {
    id: number;
    lancamento: number;
    ref: string;
    dia: string;
    diario: string | null;
    nota: string | null;
    debito: number;
    credito: number;
    acumulado: number;
};

export type RazaoDaConta = {
    conta: {
        id: number; codigo: string; nome: string;
        tipo_rotulo: string; natureza: string; cresce_a_debito: boolean;
    };
    periodo: { de: string; ate: string };
    /** Sem o saldo de abertura, o saldo final não bate com nada. */
    abertura: number;
    data: LinhaDaRazao[];
    totais: { debito: number; credito: number; saldo: number };
};

/* ─── Os lançamentos ──────────────────────────────────────────────────── */

export type Lancamento = {
    id: number;
    ref: string;
    dia: string | null;
    diario: string | null;
    diario_id: number | null;
    periodo: string | null;
    periodo_id: number | null;
    periodo_aberto: boolean;
    nota: string | null;
    estado: string;
    estado_rotulo: string;
    debito: number;
    credito: number;
    linhas: number;
    autor: string | null;
    confirmado_em: string | null;
    /** O que se pode fazer, decidido no servidor — o ecrã não adivinha. */
    pode_confirmar: boolean;
    pode_apagar: boolean;
    pode_estornar: boolean;
};

export type LinhaDoLancamento = {
    id: number;
    conta_id: number | null;
    conta: string | null;
    debito: number;
    credito: number;
    nota: string | null;
};

export type FichaDoLancamento = Lancamento & {
    tipo_de_documento_id: number | null;
    tipo_de_documento: string | null;
    confirmado_por: string | null;
    linhas_do_lancamento: LinhaDoLancamento[];
};

export type PeriodoAberto = Escolha & { de: string | null; ate: string | null };

export type OpcoesDosLancamentos = {
    diarios: Array<Escolha & { prefixo: string | null }>;
    periodos: PeriodoAberto[];
    /** Nem bloqueadas nem de agregação: só as que recebem movimento. */
    contas: Array<Escolha & { natureza: string }>;
    tipos_de_documento: Escolha[];
    estados: Escolha[];
    periodo_de_hoje: number | null;
    permissoes: { gerir: boolean };
};

/* ─── Os períodos ─────────────────────────────────────────────────────── */

export type PeriodoContabilistico = {
    id: number;
    codigo: string;
    nome: string;
    de: string | null;
    ate: string | null;
    estado: string;
    estado_rotulo: string;
    fechado_em: string | null;
    fechado_por: string | null;
    /** O período em que hoje cai: é o que se usa ao lançar. */
    e_o_de_hoje: boolean;
    lancamentos: number;
    rascunhos: number;
    debito: number;
    credito: number;
    diferenca: number;
    equilibrado: boolean;
    /** O que segura o fecho, decidido no servidor e dito antes do clique. */
    pode_fechar: boolean;
    porque_nao_fecha: string | null;
    pode_reabrir: boolean;
};

export type PeriodosDoAno = {
    ano: number;
    anos: number[];
    data: PeriodoContabilistico[];
    resumo: { total: number; abertos: number; fechados: number; rascunhos: number };
    permissoes: { gerir: boolean };
};

/* ─── As moedas e os câmbios ──────────────────────────────────────────── */

export type Moeda = {
    id: number;
    codigo: string;
    nome: string;
    simbolo: string;
    casas: number;
    activa: boolean;
    /** Quantos câmbios a usam — é o que decide se se pode apagar. */
    cambios: number;
};

export type Cambio = {
    id: number;
    de_id: number;
    de: string | null;
    para_id: number;
    para: string | null;
    dia: string | null;
    taxa: number;
    origem: string | null;
};

export type MoedasECambios = {
    moedas: Moeda[];
    taxas: Cambio[];
    resumo: { moedas: number; activas: number; cambios: number; ultimo_cambio: string | null };
    /** A lista é da PLATAFORMA: escrever nela mexe com todas as empresas. */
    permissoes: { gerir: boolean };
};

/* ─── A analítica ─────────────────────────────────────────────────────── */

export type Dimensao = {
    id: number;
    codigo: string;
    nome: string;
    /** Uma pergunta que não se pode deixar em branco ao lançar. */
    obrigatoria: boolean;
    etiquetas: number;
    etiquetas_activas: number;
};

export type Etiqueta = {
    id: number;
    codigo: string;
    nome: string;
    descricao: string | null;
    activa: boolean;
};

export type Analitica = {
    dimensoes: Dimensao[];
    escolhida: number | null;
    etiquetas: Etiqueta[];
    resumo: { dimensoes: number; obrigatorias: number; etiquetas: number };
    permissoes: { gerir: boolean };
};

/* ─── Os orçamentos ───────────────────────────────────────────────────── */

export type MesDoOrcamento = {
    mes: number;
    chave: string;
    previsto: number;
    realizado: number;
    desvio: number;
};

export type Orcamento = {
    id: number;
    nome: string;
    ano: number;
    conta_id: number | null;
    conta: string | null;
    centro_de_custo_id: number | null;
    centro_de_custo: string | null;
    estado: string;
    estado_rotulo: string;
    previsto: number;
    /** A soma dos lançamentos confirmados da conta, no ano. */
    realizado: number;
    desvio: number;
    /** Sem previsto não há percentagem — e zero não é «100%». */
    execucao: number | null;
    meses: MesDoOrcamento[];
    valores: Record<string, number>;
};

export type OrcamentosDoAno = {
    ano: number;
    anos: number[];
    data: Orcamento[];
    resumo: { orcamentos: number; rascunhos: number; aprovados: number; previsto: number };
    estados: Escolha[];
    contas: Escolha[];
    centros_de_custo: Escolha[];
    permissoes: { gerir: boolean };
};

/* ─── O imobilizado ───────────────────────────────────────────────────── */

export type Bem = {
    id: number;
    codigo: string;
    nome: string;
    categoria: string | null;
    categoria_id: number | null;
    conta: string | null;
    aquisicao: string | null;
    valor: number;
    residual: number;
    vida_util: number;
    metodo: string;
    metodo_rotulo: string;
    taxa: number | null;
    amortizado: number;
    liquido: number;
    por_amortizar: number;
    estado: string;
    estado_rotulo: string;
    localizacao: string | null;
    serie: string | null;
    amortizacoes: number;
    amortizacoes_lancadas: number;
};

export type LinhaDeAmortizacao = {
    id: number;
    dia: string | null;
    periodo: string | null;
    valor: number;
    acumulado: number;
    liquido: number;
    estado: string;
    lancamento: string | null;
    pode_lancar: boolean;
};

export type FichaDoBem = Bem & {
    descricao: string | null;
    conta_id: number | null;
    conta_de_gasto_id: number | null;
    conta_de_gasto: string | null;
    conta_acumulada_id: number | null;
    conta_acumulada: string | null;
    abate: string | null;
    valor_do_abate: number | null;
    linhas: LinhaDeAmortizacao[];
};

export type OpcoesDoImobilizado = {
    contas: Array<Escolha & { tipo: string }>;
    /** As omissões da família, para o formulário as herdar. */
    categorias: Array<Escolha & { vida_util: number; metodo: string; taxa: number | null }>;
    metodos: Escolha[];
    estados: Escolha[];
    permissoes: { gerir: boolean; lancar: boolean };
};

/* ─── A reconciliação bancária ────────────────────────────────────────── */

export type Reconciliacao = {
    id: number;
    conta_id: number | null;
    conta: string | null;
    dia: string;
    saldo_do_extracto: number;
    saldo_contabilistico: number;
    diferenca: number;
    estado: string;
    estado_rotulo: string;
    linhas: number;
    casadas: number;
    por_casar: number;
    formato: string | null;
    conciliado_em: string | null;
};

export type SugestaoDeCasamento = {
    linha_id: number;
    confianca: number;
    lancamento: string | null;
    dia: string | null;
    nota: string | null;
    debito: number;
    credito: number;
};

export type LinhaDoExtracto = {
    id: number;
    dia: string;
    referencia: string | null;
    descricao: string | null;
    valor: number;
    tipo: string;
    tipo_rotulo: string;
    estado: string;
    confianca: number | null;
    lancamento: string | null;
    lancamento_dia: string | null;
    /** Só nas que faltam: são uma consulta por linha. */
    sugestoes: SugestaoDeCasamento[];
};

export type FichaDaReconciliacao = Reconciliacao & {
    linhas_do_extracto: LinhaDoExtracto[];
};

/* ─── Os relatórios ───────────────────────────────────────────────────── */

export type Mapa = {
    valor: string;
    rotulo: string;
    icone: string;
    descricao: string;
};

export type OpcoesDosRelatorios = {
    mapas: Mapa[];
    contas: Escolha[];
    diarios: Escolha[];
    /** Que mapas descarregam, e em que formato. */
    exportacoes: { pdf: string[]; excel: string[] };
};

/**
 * O CONTEÚDO DE UM MAPA.
 *
 * Cada um tem a sua forma — o balancete é uma tabela de contas, o balanço é uma
 * árvore de rubricas, o mapa de IVA são duas listas. O ecrã desenha-os um a um,
 * e por isso o tipo é o do JSON tal como vem.
 */
export type RelatorioDaContabilidade = {
    mapa: string;
    periodo: { de: string; ate: string };
    data: Record<string, unknown>;
};

/* ─── As definições ───────────────────────────────────────────────────── */

export type EventoDaIntegracao = {
    evento: string;
    rotulo: string;
    configurado: boolean;
    activo: boolean;
    /** O lançamento nasce confirmado em vez de ficar em rascunho. */
    confirma_sozinho: boolean;
    diario_id: number | null;
    debito_id: number | null;
    credito_id: number | null;
    imposto_id: number | null;
};

export type DefinicoesDaContabilidade = {
    montagem: {
        contas: number; diarios: number; periodos: number; periodos_do_ano: number;
        tipos_de_documento: number; impostos: number; centros_de_custo: number; lancamentos: number;
    };
    ano: number;
    integracao: { ligada: boolean; mapeamentos_activos: number };
    eventos: EventoDaIntegracao[];
    contas: Escolha[];
    diarios: Escolha[];
    /** Ver e mexer são direitos diferentes — e era isso que faltava. */
    permissoes: { editar: boolean };
};

const C = '/contabilidade';

export const contabilidade = {
    painel: (f: { de?: string; ate?: string }) => api.ler<PainelDaContabilidade>(`${C}/painel`, f),

    contas: {
        opcoes: () => api.ler<OpcoesDasContas>(`${C}/contas/opcoes`),
        listar: (f: {
            procura?: string; tipo?: string; nivel?: number | '';
            natureza?: string; estado?: string; por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Conta[]; meta: Meta;
            resumo: { total: number; activas: number; bloqueadas: number; agregacao: number };
        }>(`${C}/contas`, f),
        ficha: (id: number) => api.ler<{ data: FichaDaConta }>(`${C}/contas/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Conta }>(`${C}/contas/${id}`, dados)
               : api.criar<Recado & { data: Conta }>(`${C}/contas`, dados),
        apagar: (id: number) => api.apagar<Recado>(`${C}/contas/${id}`),
        estado: (id: number) => api.criar<Recado & { bloqueada: boolean }>(`${C}/contas/${id}/estado`, {}),
        razao: (id: number, f: { de?: string; ate?: string }) =>
            api.ler<RazaoDaConta>(`${C}/contas/${id}/razao`, f),
    },

    relatorios: {
        opcoes: () => api.ler<OpcoesDosRelatorios>(`${C}/relatorios/opcoes`),
        mostrar: (f: { mapa: string; de?: string; ate?: string; conta?: number | ''; diario?: number | '' }) =>
            api.ler<RelatorioDaContabilidade>(`${C}/relatorios`, f),
        /**
         * A DESCARGA É UMA MORADA, não um pedido: devolve um ficheiro, e o ecrã
         * abre-a numa janela nova para o browser o guardar.
         */
        morada: (f: { mapa: string; formato: 'pdf' | 'excel'; de: string; ate: string }) =>
            `/accounting/reports/descarregar?${new URLSearchParams(f).toString()}`,
    },

    definicoes: {
        ler: () => api.ler<DefinicoesDaContabilidade>(`${C}/definicoes`),
        sincronizar: (peca: string, ano?: number) =>
            api.criar<Recado>(`${C}/definicoes/sincronizar`, { peca, ano }),
        integracao: (ligada: boolean) =>
            api.criar<Recado & { aviso?: boolean }>(`${C}/definicoes/integracao`, { ligada }),
        mapeamento: (dados: Record<string, unknown>) => api.criar<Recado>(`${C}/definicoes/mapeamento`, dados),
        apagarTudo: () => api.apagar<Recado>(`${C}/definicoes/tudo`),
    },

    reconciliacao: {
        listar: (f: { conta?: number | ''; estado?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: Reconciliacao[]; meta: Meta;
                resumo: { total: number; por_conciliar: number; conciliadas: number; com_diferenca: number };
                contas: Escolha[];
                formatos: Escolha[];
                estados: Escolha[];
                permissoes: { gerir: boolean };
            }>(`${C}/reconciliacao`, f),
        ficha: (id: number) => api.ler<{ data: FichaDaReconciliacao }>(`${C}/reconciliacao/${id}`),
        /** O extracto vai como ficheiro: multipart, não JSON. */
        importar: (corpo: FormData) => api.enviar<Recado & { id: number }>(`${C}/reconciliacao/importar`, corpo),
        apagar: (id: number) => api.apagar<Recado>(`${C}/reconciliacao/${id}`),
        automatico: (id: number) => api.criar<Recado>(`${C}/reconciliacao/${id}/automatico`, {}),
        aprovar: (id: number) => api.criar<Recado>(`${C}/reconciliacao/${id}/aprovar`, {}),
        casar: (linha: number, linhaDoLancamento: number) =>
            api.criar<Recado>(`${C}/reconciliacao/linhas/${linha}/casar`, { linha_id: linhaDoLancamento }),
        desfazer: (linha: number) => api.criar<Recado>(`${C}/reconciliacao/linhas/${linha}/desfazer`, {}),
    },

    orcamentos: {
        listar: (f: { ano?: number; procura?: string; estado?: string }) =>
            api.ler<OrcamentosDoAno>(`${C}/orcamentos`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${C}/orcamentos/${id}`, dados) : api.criar<Recado>(`${C}/orcamentos`, dados),
        estado: (id: number, estado: string) => api.criar<Recado>(`${C}/orcamentos/${id}/estado`, { estado }),
        apagar: (id: number) => api.apagar<Recado>(`${C}/orcamentos/${id}`),
    },

    imobilizado: {
        opcoes: () => api.ler<OpcoesDoImobilizado>(`${C}/imobilizado/opcoes`),
        listar: (f: { procura?: string; estado?: string; categoria?: number | ''; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: Bem[]; meta: Meta;
                resumo: { bens: number; aquisicao: number; amortizado: number; liquido: number; por_lancar: number };
                estados: Escolha[];
                permissoes: { gerir: boolean; lancar: boolean };
            }>(`${C}/imobilizado`, f),
        ficha: (id: number) => api.ler<{ data: FichaDoBem }>(`${C}/imobilizado/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { id: number }>(`${C}/imobilizado/${id}`, dados)
               : api.criar<Recado & { id: number }>(`${C}/imobilizado`, dados),
        apagar: (id: number) => api.apagar<Recado>(`${C}/imobilizado/${id}`),
        /** Calcular não é lançar: as linhas nascem em rascunho. */
        calcular: (dados: { ate?: string; bem?: number }) =>
            api.criar<Recado & { criadas: number }>(`${C}/imobilizado/calcular`, dados),
        lancar: (id: number) =>
            api.criar<Recado & { lancamento: string | null }>(`${C}/imobilizado/amortizacoes/${id}/lancar`, {}),
    },

    moedas: {
        listar: (f: { procura?: string; so_activas?: boolean; moeda?: number | ''; de?: string; ate?: string }) =>
            api.ler<MoedasECambios>(`${C}/moedas`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${C}/moedas/${id}`, dados) : api.criar<Recado>(`${C}/moedas`, dados),
        apagar: (id: number) => api.apagar<Recado>(`${C}/moedas/${id}`),
        /** O par mais a data são a chave: repetir CORRIGE em vez de somar. */
        guardarCambio: (dados: Record<string, unknown>) => api.criar<Recado>(`${C}/moedas/cambios`, dados),
        apagarCambio: (id: number) => api.apagar<Recado>(`${C}/moedas/cambios/${id}`),
    },

    analitica: {
        listar: (f: { dimensao?: number | ''; procura?: string; estado?: string }) =>
            api.ler<Analitica>(`${C}/analitica`, f),
        guardarDimensao: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { id: number }>(`${C}/analitica/dimensoes/${id}`, dados)
               : api.criar<Recado & { id: number }>(`${C}/analitica/dimensoes`, dados),
        apagarDimensao: (id: number) => api.apagar<Recado>(`${C}/analitica/dimensoes/${id}`),
        guardarEtiqueta: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado>(`${C}/analitica/etiquetas/${id}`, dados)
               : api.criar<Recado>(`${C}/analitica/etiquetas`, dados),
        apagarEtiqueta: (id: number) => api.apagar<Recado>(`${C}/analitica/etiquetas/${id}`),
    },

    periodos: {
        listar: (ano?: number) => api.ler<PeriodosDoAno>(`${C}/periodos`, ano ? { ano } : {}),
        criar: (dados: Record<string, unknown>) => api.criar<Recado>(`${C}/periodos`, dados),
        /** Os doze meses de um exercício. Incremental: nunca toca num existente. */
        gerar: (ano: number) => api.criar<Recado & { criados: number }>(`${C}/periodos/gerar`, { ano }),
        fechar: (id: number) => api.criar<Recado>(`${C}/periodos/${id}/fechar`, {}),
        reabrir: (id: number) => api.criar<Recado>(`${C}/periodos/${id}/reabrir`, {}),
    },

    lancamentos: {
        opcoes: () => api.ler<OpcoesDosLancamentos>(`${C}/lancamentos/opcoes`),
        referencia: (diario: number) => api.ler<{ ref: string }>(`${C}/lancamentos/referencia/${diario}`),
        listar: (f: {
            procura?: string; estado?: string; diario?: number | ''; periodo?: number | '';
            de?: string; ate?: string; por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Lancamento[]; meta: Meta;
            resumo: {
                total: number; rascunhos: number; confirmados: number;
                do_mes: number;
                /** Rascunhos em período fechado: já não se podem confirmar. */
                presos: number;
            };
        }>(`${C}/lancamentos`, f),
        ficha: (id: number) => api.ler<{ data: FichaDoLancamento }>(`${C}/lancamentos/${id}`),
        criar: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Lancamento }>(`${C}/lancamentos`, dados),
        confirmar: (id: number) =>
            api.criar<Recado & { data: Lancamento }>(`${C}/lancamentos/${id}/confirmar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${C}/lancamentos/${id}`),
        estornar: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Lancamento }>(`${C}/lancamentos/${id}/estornar`, dados),
    },
};
