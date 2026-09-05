import { api } from './cliente';

export type LinhaDoEditor = {
    product_id: number | null;
    description: string;
    quantity: number | string;
    price: number | string;
    discount_percent: number | string;
};

/** O que o SERVIDOR devolve de cada linha. O ecrã não faz contas. */
export type LinhaCalculada = {
    product_id: number | null;
    nome: string;
    description: string | null;
    unit: string;
    quantity: number;
    price: number;
    discount_percent: number;
    bruto: number;
    desconto: number;
    base: number;
    tax_rate: number;
    tax_code: string | null;
    exemption_reason: string | null;
    imposto: number;
    total: number;
};

export type Totais = {
    bruto: number;
    desconto_comercial: number;
    desconto_por_linha: number;
    liquido: number;
    base: number;
    imposto: number;
    retencao: number;
    total: number;
};

export type OpcoesDoEmissor = {
    titulo: string;
    parte: string;
    rota: string;
    partes: Array<{ id: number; name: string; nif: string | null }>;
    artigos: Array<{ id: number; name: string; code: string | null; price: number; unit: string }>;
    permissoes: { pode_criar: boolean };
};

export const emissor = {
    opcoes: (tipo: string) => api.ler<OpcoesDoEmissor>(`/emissor/${tipo}/opcoes`),

    calcular: (tipo: string, corpo: { linhas: LinhaDoEditor[]; desconto_comercial?: number; desconto_financeiro?: number }) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>(`/emissor/${tipo}/calcular`, corpo),

    guardar: (tipo: string, corpo: Record<string, unknown>) =>
        api.criar<{ id: number; numero: string; total: number; abrir: string; message: string }>(
            `/emissor/${tipo}`,
            corpo,
        ),
};
