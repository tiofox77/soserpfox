import { api } from './cliente';

export type Serie = {
    id: number; document_type: string; tipo_rotulo: string; series_code: string; name: string; prefix: string;
    include_year: boolean; next_number: number; number_padding: number; is_default: boolean; is_active: boolean; reset_yearly: boolean;
    description: string | null; series_year: number | null; establishment_number: string | null; invoicing_method: string | null;
    agt_series_id: string | null; registada: boolean; emitidos: number; exemplo: string;
};

export type OpcoesDasSeries = {
    tipos: Array<{ valor: string; rotulo: string; prefixo: string | null }>;
    metodos: Array<{ valor: string; rotulo: string }>;
    metodo_padrao: string;
    ano: number;
    permissoes: { pode_escrever: boolean };
};

type Pagina = { data: Serie[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };

export const series = {
    opcoes: () => api.ler<OpcoesDasSeries>('/series/opcoes'),
    lista: (f: { procura?: string; tipo?: string; page?: number }) => api.ler<Pagina>('/series', f),
    guardar: (corpo: Record<string, unknown>) => api.criar<{ data: Serie; message: string }>('/series', corpo),
    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<{ data: Serie; message: string }>(`/series/${id}`, corpo),
    eliminar: (id: number) => api.apagar<{ message: string }>(`/series/${id}`),
};
