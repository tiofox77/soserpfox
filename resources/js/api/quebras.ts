import { api } from './cliente';

export type Quebra = {
    id: number; quando: string | null; artigo: string | null; unidade: string | null; armazem: string | null;
    quantidade: number; custo_unitario: number; custo: number; motivo: string; motivo_rotulo: string;
    notas: string | null; quem: string | null; anulada: boolean;
};

export type OpcoesDasQuebras = {
    motivos: Array<{ valor: string; rotulo: string }>;
    armazens: Array<{ id: number; name: string }>;
    armazem_padrao: number | null;
    permissoes: { pode_registar: boolean };
};

export type ResumoDasQuebras = {
    registos: number;
    custo: number;
    por_motivo: Array<{ motivo: string; rotulo: string; registos: number; custo: number }>;
    por_produto: Array<{ artigo: string | null; unidade: string | null; quantidade: number; custo: number }>;
};

type Pagina = { data: Quebra[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; resumo: ResumoDasQuebras };

export const quebras = {
    opcoes: () => api.ler<OpcoesDasQuebras>('/quebras/opcoes'),
    artigos: (procura: string) => api.ler<{ data: Array<{ id: number; name: string; code: string | null; unit: string | null }> }>('/quebras/artigos', { procura }),
    lista: (f: { de?: string; ate?: string; motivo?: string; page?: number }) => api.ler<Pagina>('/quebras', f),
    registar: (corpo: Record<string, unknown>) => api.criar<{ data: Quebra; message: string }>('/quebras', corpo),
    anular: (id: number) => api.criar<{ message: string }>(`/quebras/${id}/anular`, {}),
};
