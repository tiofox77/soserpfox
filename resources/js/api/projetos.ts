/**
 * A PONTE DOS ECRÃS DOS PROJETOS.
 *
 * Fala com `/api/v1/invoicing/react/projetos/*` — a morada dos ecrãs com
 * sessão, a mesma de todos os outros módulos.
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

export type LinhaDoPainel = {
    id: number;
    codigo: string;
    nome: string;
    cliente: string | null;
    estado: string;
    estado_rotulo: string;
    horas: number;
    orcamento: number;
    gasto: number;
    percentagem: number | null;
};

export type PainelDosProjetos = {
    resumo: {
        activos: number;
        horas_mes: number;
        tarefas_abertas: number;
        tarefas_atrasadas: number;
        horas_por_facturar: number;
        valor_por_facturar: number;
    };
    activos: LinhaDoPainel[];
    estouros: LinhaDoPainel[];
    atrasadas: Array<{
        id: number; titulo: string; projeto: string | null;
        responsavel: string | null; prazo: string | null; dias: number;
        prioridade: string; prioridade_rotulo: string;
    }>;
    horas_por_mes: Serie;
};

/* ─── Os projetos ─────────────────────────────────────────────────────── */

export type Projeto = {
    id: number;
    codigo: string;
    nome: string;
    client_id: number | null;
    cliente: string | null;
    responsavel_id: number | null;
    responsavel: string | null;
    estado: string;
    estado_rotulo: string;
    inicio: string | null;
    fim_previsto: string | null;
    orcamento: number;
    valor_hora: number;
    descricao: string | null;
    horas: number;
    consumido: number;
    percentagem: number | null;
    acima_do_orcamento: boolean;
    aceita_horas: boolean;
};

export type FichaDoProjeto = Projeto & {
    autor: string | null;
    facturado: number;
    horas_facturadas: number;
    por_facturar: number;
    horas_por_facturar: number;
    linhas_por_facturar: number;
};

export type OpcoesDosProjetos = {
    estados: Escolha[];
    clientes: Escolha[];
    pessoas: Escolha[];
    permissoes: { pode_gerir: boolean; pode_facturar: boolean };
};

/* ─── As tarefas ──────────────────────────────────────────────────────── */

export type Tarefa = {
    id: number;
    titulo: string;
    descricao: string | null;
    projeto_id: number | null;
    projeto: string | null;
    projeto_nome: string | null;
    responsavel_id: number | null;
    responsavel: string | null;
    estado: string;
    estado_rotulo: string;
    prioridade: string;
    prioridade_rotulo: string;
    prazo: string | null;
    atrasada: boolean;
    horas_estimadas: number | null;
    concluida_em: string | null;
};

export type OpcoesDasTarefas = {
    estados: Escolha[];
    prioridades: Escolha[];
    projetos: Escolha[];
    pessoas: Escolha[];
    permissoes: { pode_gerir: boolean };
};

/* ─── As horas ────────────────────────────────────────────────────────── */

export type Lancamento = {
    id: number;
    projeto_id: number | null;
    projeto: string | null;
    projeto_nome: string | null;
    tarefa_id: number | null;
    tarefa: string | null;
    dia: string | null;
    horas: number;
    descricao: string | null;
    facturavel: boolean;
    valor_hora: number | null;
    facturada: boolean;
};

export type SemanaDeHoras = {
    semana: string;
    de: string;
    ate: string;
    dias: Array<{
        dia: string; rotulo: string; hoje: boolean;
        total: number; linhas: Lancamento[];
    }>;
    resumo: { total: number; facturavel: number; facturadas: number };
};

const R = '/projetos';

export const projetos = {
    painel: () => api.ler<PainelDosProjetos>(`${R}/painel`),

    lista: {
        opcoes: () => api.ler<OpcoesDosProjetos>(`${R}/lista/opcoes`),
        listar: (f: { procura?: string; estado?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: Projeto[]; meta: Meta;
                resumo: { total: number; activos: number; concluidos: number };
            }>(`${R}/lista`, f),
        ficha: (id: number) => api.ler<{
            data: FichaDoProjeto;
            facturas: Array<{ id: number; numero: string; dia: string | null; estado: string; total: number }>;
            tarefas: { abertas: number; total: number };
        }>(`${R}/lista/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Projeto }>(`${R}/lista/${id}`, dados)
               : api.criar<Recado & { data: Projeto }>(`${R}/lista`, dados),
        estado: (id: number, estado: string) =>
            api.criar<Recado & { data: Projeto }>(`${R}/lista/${id}/estado`, { estado }),
        facturar: (id: number) => api.criar<Recado & {
            factura: { id: number; numero: string; total: number };
        }>(`${R}/lista/${id}/facturar`, {}),
    },

    tarefas: {
        opcoes: () => api.ler<OpcoesDasTarefas>(`${R}/tarefas/opcoes`),
        listar: (f: {
            procura?: string; projeto?: number | ''; estado?: string;
            prioridade?: string; so_minhas?: boolean;
            por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Tarefa[]; meta: Meta;
            resumo: { abertas: number; atrasadas: number; minhas: number };
        }>(`${R}/tarefas`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Tarefa }>(`${R}/tarefas/${id}`, dados)
               : api.criar<Recado & { data: Tarefa }>(`${R}/tarefas`, dados),
        estado: (id: number, estado: string) =>
            api.criar<Recado & { data: Tarefa }>(`${R}/tarefas/${id}/estado`, { estado }),
    },

    horas: {
        opcoes: () => api.ler<{
            projetos: Escolha[];
            permissoes: { pode_gerir_de_todos: boolean };
        }>(`${R}/horas/opcoes`),
        tarefasDo: (projeto: number) => api.ler<{ data: Escolha[] }>(`${R}/horas/tarefas/${projeto}`),
        semana: (semana?: string) => api.ler<SemanaDeHoras>(`${R}/horas`, { semana }),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Lancamento }>(`${R}/horas/${id}`, dados)
               : api.criar<Recado & { data: Lancamento }>(`${R}/horas`, dados),
        apagar: (id: number) => api.apagar<Recado>(`${R}/horas/${id}`),
    },
};
