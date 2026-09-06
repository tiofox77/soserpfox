import { api } from './cliente';

export type Importacao = {
    id: number;
    numero: string;
    reference: string | null;
    supplier_id: number;
    fornecedor: string | null;
    warehouse_id: number | null;
    armazem: string | null;
    order_date: string | null;
    expected_arrival_date: string | null;
    origin_country: string;
    origin_port: string | null;
    destination_port: string | null;
    shipping_company: string | null;
    transport_type: 'maritime' | 'air' | 'land';
    fob_value: number;
    freight_cost: number;
    insurance_cost: number;
    cif_value: number;
    notes: string | null;
    estado: string;
    estado_rotulo: string;
    estado_cor: string;
};

export type Escolha = { valor: string; rotulo: string };

export type OpcoesDasImportacoes = {
    fornecedores: Array<{ id: number; name: string }>;
    armazens: Array<{ id: number; name: string }>;
    estados: Escolha[];
    transportes: Escolha[];
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

export type Resumo = { total: number; em_transito: number; na_alfandega: number; valor_em_curso: number };

type Pagina = { data: Importacao[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; resumo: Resumo };

export const importacoes = {
    opcoes: () => api.ler<OpcoesDasImportacoes>('/importacoes/opcoes'),
    lista: (filtros: { procura?: string; estado?: string; fornecedor?: string; page?: number }) => api.ler<Pagina>('/importacoes', filtros),
    guardar: (corpo: Record<string, unknown>) => api.criar<{ data: Importacao; message: string }>('/importacoes', corpo),
    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<{ data: Importacao; message: string }>(`/importacoes/${id}`, corpo),
    estado: (id: number, estado: string) => api.criar<{ data: Importacao; message: string }>(`/importacoes/${id}/estado`, { estado }),
    apagar: (id: number) => api.apagar<{ message: string }>(`/importacoes/${id}`),
};
