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
