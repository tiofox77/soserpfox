import { api } from './cliente';

export type Guia = {
    id: number;
    numero: string;
    tipo: 'GT' | 'GR';
    tipo_rotulo: string;
    cliente: string;
    /** A matrícula da viatura, quando a guia a leva. */
    viatura: string | null;
    data: string;
    estado: string;
    assinada: boolean;
    agt: string | null;
    atcud: string | null;
    pdf: string;
};

export type LinhaDaGuia = {
    product_id: number | null;
    product_name: string;
    description: string;
    quantity: number | string;
    unit: string;
};

export type OpcoesDasGuias = {
    tipos: Array<{ valor: 'GT' | 'GR'; rotulo: string }>;
    clientes: Array<{ id: number; name: string; nif: string | null }>;
    facturas: Array<{ id: number; invoice_number: string; client_id: number | null }>;
    artigos: Array<{ id: number; name: string; unit: string | null }>;
    permissoes: { pode_criar: boolean };
};

type Pagina = { data: Guia[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };

export const guias = {
    opcoes: () => api.ler<OpcoesDasGuias>('/guias/opcoes'),
    lista: (filtros: { procura?: string; page?: number }) => api.ler<Pagina>('/guias', filtros),
    linhasDaFactura: (id: number) => api.ler<{ client_id: number | null; linhas: LinhaDaGuia[] }>(`/guias/facturas/${id}/linhas`),
    guardar: (corpo: Record<string, unknown>) => api.criar<{ data: Guia; message: string }>('/guias', corpo),
    comunicar: (id: number) => api.criar<{ data: Guia; message: string }>(`/guias/${id}/agt`, {}),
    anular: (id: number) => api.apagar<{ message: string }>(`/guias/${id}`),
};
