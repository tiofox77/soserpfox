import { api } from './cliente';

export type Lote = {
    id: number; product_id: number; artigo: string | null; unidade: string | null; warehouse_id: number | null; armazem: string | null;
    batch_number: string | null; manufacturing_date: string | null; expiry_date: string | null; dias: number | null;
    quantity: number; quantity_available: number; cost_price: number; alert_days: number; notes: string | null; status: string; pode_apagar: boolean;
};

export type OpcoesDosLotes = {
    artigos: Array<{ id: number; name: string; unit: string | null }>;
    armazens: Array<{ id: number; name: string }>;
    estados: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

type Pagina = { data: Lote[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; resumo: { activos: number; a_expirar: number; expirados: number } };

export const lotes = {
    opcoes: () => api.ler<OpcoesDosLotes>('/lotes/opcoes'),
    lista: (f: { procura?: string; produto?: string; armazem?: string; estado?: string; page?: number }) => api.ler<Pagina>('/lotes', f),
    guardar: (corpo: Record<string, unknown>) => api.criar<{ data: Lote; message: string }>('/lotes', corpo),
    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<{ data: Lote; message: string }>(`/lotes/${id}`, corpo),
    apagar: (id: number) => api.apagar<{ message: string }>(`/lotes/${id}`),
};
