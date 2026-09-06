import { api } from './cliente';

export type TipoDeNota = 'credito' | 'debito';

export type FacturaParaNota = {
    id: number;
    numero: string;
    data: string | null;
    total: number;
    por_creditar: number;
};

/** Uma linha da factura, como o servidor a devolve — a taxa vem de lá. */
export type LinhaDaFactura = {
    origem_line_id: number;
    product_id: number | null;
    nome: string;
    quantity: number;
    price: number;
    discount_percent: number;
    tax_rate: number;
    tax_country_region: string;
};

export type OpcoesDasNotas = {
    titulo: string;
    clientes: Array<{ id: number; name: string; nif: string | null }>;
    artigos: Array<{ id: number; name: string; code: string | null; price: number; unit: string }>;
    motivos: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean };
};

/** Uma nota aberta para consulta. Emitida, não se edita — rectifica-se com outra. */
export type NotaAberta = {
    id: number; numero: string | null; estado: string; cliente: string | null; factura: string | null;
    issue_date: string | null; due_date: string | null; reason: string | null; type: string | null; notes: string | null; total: number; pdf: string;
    linhas: Array<{ nome: string; quantity: number; price: number; tax_rate: number; total: number }>;
};

export const notas = {
    opcoes: (tipo: TipoDeNota) => api.ler<OpcoesDasNotas>(`/notas/${tipo}/opcoes`),
    facturas: (tipo: TipoDeNota, clienteId?: string) =>
        api.ler<{ data: FacturaParaNota[] }>(`/notas/${tipo}/facturas`, { cliente_id: clienteId }),
    linhas: (tipo: TipoDeNota, facturaId: number) =>
        api.ler<{ data: LinhaDaFactura[] }>(`/notas/${tipo}/facturas/${facturaId}/linhas`),
    guardar: (tipo: TipoDeNota, corpo: Record<string, unknown>) =>
        api.criar<{ id: number; numero: string; total: number; agt: string | null; abrir: string; message: string }>(
            `/notas/${tipo}`,
            corpo,
        ),
    mostrar: (tipo: TipoDeNota, id: number) => api.ler<{ nota: NotaAberta }>(`/notas/${tipo}/${id}`),
};
