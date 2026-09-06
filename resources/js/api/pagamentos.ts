import { api } from './cliente';

export type TipoDeFactura = 'sale' | 'purchase';

export type ContextoDoPagamento = {
    factura: { id: number; numero: string; parte: string; total: number; pago: number };
    por_pagar: number;
    formas: Array<{ valor: string; rotulo: string }>;
    adiantamentos: Array<{ id: number; numero: string; disponivel: number }>;
    contas: Array<{ id: number; nome: string }>;
    caixas: Array<{ id: number; nome: string }>;
    conta_padrao: number | null;
    caixa_padrao: number | null;
};

export const pagamentos = {
    contexto: (tipo: TipoDeFactura, id: number) => api.ler<ContextoDoPagamento>(`/pagamentos/${tipo}/${id}`),
    registar: (tipo: TipoDeFactura, id: number, corpo: Record<string, unknown>) =>
        api.criar<{ recibo: string | null; estado: string; excedente: number; aviso_agt: string | null; message: string }>(`/pagamentos/${tipo}/${id}`, corpo),
};
