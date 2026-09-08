import { api } from './cliente';
import type { Cor } from '@/ui/tokens';

/**
 * O PONTO E A FOLHA.
 *
 * O ponto é a origem do que a folha desconta: uma falta a menos aqui é
 * dinheiro a mais no salário. As regras vivem no servidor — o
 * `PresencasApiController` conta as horas, e a folha inteira é do
 * `PayrollService`.
 */

export type Escolha = { valor: string; rotulo: string };
export type Estado = Escolha & { cor: Cor };

/* ─── Presenças ─────────────────────────────────────────────────────── */

export type OpcoesDoPonto = {
    funcionarios: Array<Escolha & { nota: string | null; turno: number | null }>;
    turnos: Array<Escolha & { entrada: string | null }>;
    estados: Estado[];
    sistemas: Escolha[];
    permissoes: { pode_gerir: boolean };
};

export type LinhaDePonto = {
    id: number;
    employee_id: number;
    funcionario: string;
    numero: string | null;
    date: string | null;
    check_in: string | null;
    check_out: string | null;
    hours_worked: number;
    overtime_hours: number;
    status: string;
    is_late: boolean;
    late_minutes: number;
    shift_id: number | null;
    notes: string | null;
};

export type ResumoDoPonto = {
    total: number;
    por_estado: Array<Estado & { quantos: number }>;
    horas: number;
    atrasos: number;
};

/** Quem ainda não picou hoje — a lista do botão de entrada rápida. */
export type PorMarcar = { id: number; nome: string; numero: string | null };

export type FiltrosDoPonto = {
    procura?: string;
    de?: string;
    ate?: string;
    funcionario?: string;
    estado?: string;
    page?: number;
    por_pagina?: number;
};

/** Uma linha do calendário do mês: uma pessoa, e o que fez em cada dia. */
export type LinhaDoMes = {
    id: number;
    nome: string;
    dias: Record<string, { id: number; estado: string; horas: number; atrasado: boolean }>;
};

export const presencas = {
    opcoes: () => api.ler<OpcoesDoPonto>('/rh/presencas/opcoes'),

    lista: (filtros: FiltrosDoPonto) =>
        api.ler<{
            data: LinhaDePonto[];
            meta: { total: number; current_page: number; last_page: number };
            periodo: { de: string; ate: string };
            resumo: ResumoDoPonto;
            por_marcar: PorMarcar[];
        }>('/rh/presencas', filtros),

    calendario: (ano: number, mes: number) =>
        api.ler<{ de: string; ate: string; dias: number; linhas: LinhaDoMes[] }>('/rh/presencas/calendario', { ano, mes }),

    guardar: (dados: Record<string, unknown>) =>
        api.criar<{ documento: LinhaDePonto; message: string }>('/rh/presencas', dados),

    actualizar: (id: number, dados: Record<string, unknown>) =>
        api.guardar<{ documento: LinhaDePonto; message: string }>(`/rh/presencas/${id}`, dados),

    eliminar: (id: number) => api.apagar<{ message: string }>(`/rh/presencas/${id}`),

    /** Marcar a entrada agora — um clique por pessoa, sem formulário. */
    entrada: (funcionario: number) =>
        api.criar<{ documento: LinhaDePonto; message: string }>(`/rh/presencas/entrada/${funcionario}`, {}),

    saida: (id: number) =>
        api.criar<{ documento: LinhaDePonto; message: string }>(`/rh/presencas/${id}/saida`, {}),

    importar: (ficheiro: File, sistema: string) => {
        const corpo = new FormData();
        corpo.append('ficheiro', ficheiro);
        corpo.append('sistema', sistema);

        return api.enviar<{ importados: number; ignorados: number; erros: string[]; message: string }>(
            '/rh/presencas/importar',
            corpo,
        );
    },
};

/* ─── Folha de pagamento ────────────────────────────────────────────── */

export type OpcoesDaFolha = {
    estados: Estado[];
    meses: Escolha[];
    permissoes: { pode_processar: boolean };
};

export type LinhaDaFolha = {
    id: number;
    numero: string;
    year: number;
    month: number;
    mes: string;
    periodo: { de: string | null; ate: string | null };
    pagamento: string | null;
    estado: string;
    totais: {
        bruto: number; subsidios: number; bonus: number; descontos: number;
        irt: number; inss_trabalhador: number; inss_empresa: number; liquido: number;
    };
    funcionarios: number;
    processados: number;
    decisao: { aprovado_por: string | null; aprovado_em: string | null };
};

/** A linha de um trabalhador, agrupada como o recibo a mostra. */
export type LinhaDoTrabalhador = {
    id: number;
    employee_id: number;
    funcionario: string;
    numero: string | null;
    ganhos: Record<string, number>;
    bruto: number;
    impostos: Record<string, number>;
    descontos: Record<string, number>;
    total_descontos: number;
    liquido: number;
    tempo: Record<string, number>;
};

export type FichaDaFolha = LinhaDaFolha & { notes: string | null; linhas: LinhaDoTrabalhador[] };

export const folha = {
    opcoes: () => api.ler<OpcoesDaFolha>('/rh/folha/opcoes'),

    lista: (filtros: { ano?: number; estado?: string; page?: number; por_pagina?: number }) =>
        api.ler<{
            data: LinhaDaFolha[];
            meta: { total: number; current_page: number; last_page: number };
            resumo: { total: number; rascunhos: number; por_pagar: number; liquido_do_ano: number };
        }>('/rh/folha', filtros),

    ficha: (id: number) => api.ler<{ documento: FichaDaFolha }>(`/rh/folha/${id}`),

    criar: (year: number, month: number) =>
        api.criar<{ documento: LinhaDaFolha; message: string }>('/rh/folha', { year, month }),

    eliminar: (id: number) => api.apagar<{ message: string }>(`/rh/folha/${id}`),

    /*
     * OS CINCO PASSOS DO CICLO. É o `pagar` que dispara as deduções dos
     * adiantamentos e dos descontos — e não o aprovar.
     */
    processar: (id: number) => api.criar<{ documento: LinhaDaFolha; message: string }>(`/rh/folha/${id}/processar`, {}),
    aprovar: (id: number) => api.criar<{ documento: LinhaDaFolha; message: string }>(`/rh/folha/${id}/aprovar`, {}),
    pagar: (id: number) => api.criar<{ documento: LinhaDaFolha; message: string }>(`/rh/folha/${id}/pagar`, {}),
    recalcular: (id: number) => api.criar<{ documento: LinhaDaFolha; message: string }>(`/rh/folha/${id}/recalcular`, {}),

    acertarLinha: (id: number, linha: number) =>
        api.guardar<{ documento: LinhaDoTrabalhador; message: string }>(`/rh/folha/${id}/linhas/${linha}`, {}),
};
