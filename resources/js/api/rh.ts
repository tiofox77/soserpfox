import { api } from './cliente';
import type { Cor } from '@/ui/tokens';

/**
 * O RECURSOS HUMANOS, para os ecrãs em React.
 *
 * Começa pela FICHA DO FUNCIONÁRIO — o ecrã mais denso do módulo: 74 colunas,
 * nove documentos com validade e uma fotografia. As regras vivem no servidor
 * (`FuncionariosApiController`); aqui é só a forma do que viaja.
 */

export type Escolha = { valor: string; rotulo: string };

/** Um beneficiário: quem recebe o que a lei manda pagar a quem fica. */
export type Beneficiario = { nome: string; parentesco?: string; contacto?: string };

/** Um dos nove documentos da ficha, com o ficheiro que já lá está. */
export type DocumentoDaFicha = { chave: string; rotulo: string; url: string | null };

export type OpcoesDoFuncionario = {
    departamentos: Escolha[];
    /** Os cargos trazem o departamento a que pertencem, para filtrar a lista. */
    cargos: Array<Escolha & { departamento: number | null }>;
    turnos: Array<Escolha & { cor: string }>;
    chefias: Escolha[];
    bancos: Escolha[];
    generos: Escolha[];
    vinculos: Escolha[];
    estados: Array<Escolha & { cor: Cor }>;
    categorias_de_carta: Escolha[];
    geografia: { provincias: string[]; municipios: Record<string, string[]> };
    documentos: Array<{ chave: string; rotulo: string }>;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

/** A linha da lista: o que se lê de relance. */
export type LinhaDeFuncionario = {
    id: number;
    numero: string;
    nome: string;
    email: string | null;
    telefone: string | null;
    departamento: string | null;
    cargo: string | null;
    admissao: string | null;
    estado: string;
    salario: number;
    fotografia: string | null;
    /** Quantos dos sete documentos caducam nos próximos 60 dias (ou já caducaram). */
    documentos_a_vencer: number;
};

export type ResumoDosFuncionarios = {
    total: number;
    activos: number;
    de_licenca: number;
    cessados: number;
    documentos_a_vencer: number;
};

/** A ficha inteira. As chaves são as da base, para o formulário não traduzir. */
export type FichaDoFuncionario = {
    id: number;
    numero: string;
    fotografia: string | null;
    documentos: DocumentoDaFicha[];
    beneficiaries: Beneficiario[];
} & Record<string, string | number | null | undefined | Beneficiario[] | DocumentoDaFicha[]>;

export type FiltrosDeFuncionarios = {
    procura?: string;
    departamento?: string;
    cargo?: string;
    turno?: string;
    estado?: string;
    page?: number;
    por_pagina?: number;
};

/** Quem já está na casa noutro módulo e ainda não tem ficha de RH. */
export type Importavel = { id: number; nome: string; nota: string | null };

const RAIZ = '/rh/funcionarios';

export const funcionarios = {
    opcoes: () => api.ler<OpcoesDoFuncionario>(`${RAIZ}/opcoes`),

    lista: (filtros: FiltrosDeFuncionarios) =>
        api.ler<{
            data: LinhaDeFuncionario[];
            meta: { total: number; current_page: number; last_page: number; from: number | null; to: number | null };
            resumo: ResumoDosFuncionarios;
        }>(RAIZ, filtros),

    abrir: (id: number) => api.ler<{ documento: FichaDoFuncionario }>(`${RAIZ}/${id}`),

    guardar: (dados: Record<string, unknown>) =>
        api.criar<{ documento: FichaDoFuncionario; message: string }>(RAIZ, dados),

    actualizar: (id: number, dados: Record<string, unknown>) =>
        api.guardar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}`, dados),

    eliminar: (id: number) => api.apagar<{ message: string }>(`${RAIZ}/${id}`),

    /*
     * OS FICHEIROS SOBEM À PARTE — um ficheiro não viaja em JSON, e o
     * formulário não fica refém do upload: grava-se a ficha, e os documentos
     * sobem depois, um a um.
     */
    fotografia: (id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('ficheiro', ficheiro);

        return api.enviar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}/fotografia`, corpo);
    },

    documento: (id: number, tipo: string, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('ficheiro', ficheiro);

        return api.enviar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}/documentos/${tipo}`, corpo);
    },

    apagarDocumento: (id: number, tipo: string) =>
        api.apagar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}/documentos/${tipo}`),

    importaveis: () => api.ler<{ tecnicos: Importavel[]; hotel: Importavel[] }>(`${RAIZ}/importaveis`),

    importar: (origem: 'tecnicos' | 'hotel', ids: number[]) =>
        api.criar<{ importados: number; saltados: number; message: string }>(`${RAIZ}/importar`, { origem, ids }),
};

/* ─── O painel ──────────────────────────────────────────────────────── */

export type Serie = { etiquetas: string[]; valores: number[] };

export type Aviso = {
    tom: Cor;
    icone: string;
    titulo: string;
    texto: string;
    morada: string;
    accao: string;
};

export type Painel = {
    cartoes: { funcionarios: number; activos: number; de_ferias: number; presentes_hoje: number };
    hoje: { presentes: number; atrasados: number; ausentes: number; marcados: number };
    folha: {
        existe: boolean;
        mes: string | null;
        estado: string | null;
        bruto: number;
        liquido: number;
        descontos: number;
        trabalhadores: number;
    };
    avisos: Aviso[];
    graficos: {
        custo_mensal: Serie;
        por_departamento: Serie;
        presenca_da_semana: Serie;
        por_vinculo: Serie;
    };
    listas: {
        aniversarios: Array<{ id: number; nome: string; numero: string | null; dia: string | null }>;
        admissoes: Array<{ id: number; nome: string; numero: string | null; quando: string | null }>;
        proximas_ferias: Array<{ id: number; nome: string; de: string | null; ate: string | null; dias: number }>;
    };
};

export const painel = {
    ler: () => api.ler<Painel>('/rh/painel'),
};

/* ─── Os mapas ──────────────────────────────────────────────────────── */

export type OpcoesDosMapas = {
    mapas: Array<Escolha & { icone: string; pede_mes: boolean; pede_departamento: boolean }>;
    departamentos: Escolha[];
    meses: Escolha[];
    anos: Escolha[];
};

export type FiltrosDoMapa = {
    mapa: string;
    ano: number;
    mes?: number;
    departamento?: string;
};

/**
 * Cada mapa tem as suas colunas. O ecrã desenha-as a partir do nome do mapa —
 * uma tabela genérica escondia que o mapa de salários tem totais por coluna e
 * o quadro de pessoal tem uma variação com sinal.
 */
export type Mapa = {
    mapa: string;
    periodo: { ano: number; mes: number; nome_do_mes: string };
    linhas: Array<Record<string, unknown>>;
    totais: Record<string, number> | null;
    nada?: string | null;
    folha?: { numero: string; estado: string };
    grafico?: Serie;
};

export const relatorios = {
    opcoes: () => api.ler<OpcoesDosMapas>('/rh/relatorios/opcoes'),
    mostrar: (filtros: FiltrosDoMapa) => api.ler<Mapa>('/rh/relatorios', filtros),
};

/* ─── O mapa de IRT ─────────────────────────────────────────────────── */

export type LinhaDeIrt = {
    employee_id: number;
    numero: string;
    nome: string;
    nif: string;
    seguranca: string;
    departamento: string;
    bruto: number;
    inss: number;
    base: number;
    irt: number;
    taxa: number;
    isento: boolean;
    folhas: number;
};

export type MapaDeIrt = {
    periodo: { ano: number; mes: number; nome_do_mes: string; etiqueta: string };
    linhas: LinhaDeIrt[];
    totais: {
        trabalhadores: number;
        tributados: number;
        isentos: number;
        bruto: number;
        inss: number;
        base: number;
        irt: number;
    };
    folhas: Array<{ numero: string; estado: string }>;
    ignoradas: Array<{ numero: string; estado: string }>;
    departamentos: Escolha[];
};

export const irt = {
    ler: (ano: number, mes: number, departamento?: string) =>
        api.ler<MapaDeIrt>('/rh/relatorios/irt', { ano, mes, departamento }),
};

/* ─── As definições ─────────────────────────────────────────────────── */

export type CampoDeDefinicao = {
    chave: string;
    etiqueta: string;
    ajuda: string | null;
    tipo: 'integer' | 'decimal' | 'percentage' | 'boolean' | 'text' | 'json';
    tipo_rotulo: string;
    valor: string | number | boolean | null;
    padrao: string | null;
    /** Fica no ecrã, mas nenhum cálculo a lê ainda. */
    informativa: boolean;
};

export type SeccaoDeDefinicoes = {
    chave: string;
    rotulo: string;
    icone: string;
    /** Um tom por secção, para as sete se distinguirem de relance. */
    cor: string;
    campos: CampoDeDefinicao[];
};

export type Definicoes = {
    seccoes: SeccaoDeDefinicoes[];
    criadas_agora: number;
    permissoes: { pode_editar: boolean };
};

export const definicoes = {
    ler: () => api.ler<Definicoes>('/rh/definicoes'),
    guardar: (valores: Record<string, string | number | boolean>) =>
        api.guardar<{ message: string; valores: Record<string, unknown> }>('/rh/definicoes', { valores }),
    repor: (seccao: string) => api.criar<{ message: string }>('/rh/definicoes/repor', { seccao }),
};

/* ─── Os contratos ──────────────────────────────────────────────────── */

export type OpcoesDosContratos = {
    estados: Array<Escolha & { cor: Cor }>;
    tipos: Escolha[];
    periodicidades: Escolha[];
    funcionarios: Array<Escolha & { nota: string | null; salario: number }>;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_eliminar: boolean };
};

export type LinhaDeContrato = {
    id: number;
    numero: string;
    funcionario: string;
    numero_do_funcionario: string | null;
    employee_id: number;
    tipo: string;
    estado: string;
    inicio: string | null;
    fim: string | null;
    /** Negativo se já passou. Nulo num contrato sem termo. */
    dias_para_o_fim: number | null;
    base: number;
    total: number;
};

export type FichaDeContrato = LinhaDeContrato & {
    trial_period_end: string | null;
    food_allowance: number;
    transport_allowance: number;
    housing_allowance: number;
    other_allowances: number;
    payment_frequency: string;
    weekly_hours: number;
    work_start_time: string | null;
    work_end_time: string | null;
    has_health_insurance: boolean;
    has_life_insurance: boolean;
    vacation_days_per_year: number;
    subject_to_irt: boolean;
    subject_to_inss: boolean;
    irt_percentage: number | null;
    termination_date: string | null;
    termination_reason: string | null;
    notes: string | null;
};

export type FiltrosDosContratos = {
    procura?: string;
    estado?: string;
    tipo?: string;
    funcionario?: string;
    page?: number;
    por_pagina?: number;
};

export const contratos = {
    opcoes: () => api.ler<OpcoesDosContratos>('/rh/contratos/opcoes'),

    lista: (filtros: FiltrosDosContratos) =>
        api.ler<{
            data: LinhaDeContrato[];
            meta: { total: number; current_page: number; last_page: number };
            resumo: { total: number; em_vigor: number; a_terminar: number; massa_salarial: number };
        }>('/rh/contratos', filtros),

    ficha: (id: number) => api.ler<{ documento: FichaDeContrato }>(`/rh/contratos/${id}`),

    guardar: (dados: Record<string, unknown>) =>
        api.criar<{ documento: FichaDeContrato; message: string }>('/rh/contratos', dados),

    actualizar: (id: number, dados: Record<string, unknown>) =>
        api.guardar<{ documento: FichaDeContrato; message: string }>(`/rh/contratos/${id}`, dados),

    cessar: (id: number, dados: { termination_date: string; termination_reason: string }) =>
        api.criar<{ documento: LinhaDeContrato; message: string }>(`/rh/contratos/${id}/cessar`, dados),

    eliminar: (id: number) => api.apagar<{ message: string }>(`/rh/contratos/${id}`),
};
