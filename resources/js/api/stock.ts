import { api } from './cliente';

export type LinhaDeStock = {
    /** Nulo no artigo SEM linha de stock — não há o que ajustar nem transferir. */
    id: number | null;
    product_id: number;
    artigo: string | null;
    codigo: string | null;
    unidade: string | null;
    conteudo: string | null;
    conservacao: string | null;
    conservacao_rotulo: string | null;
    warehouse_id: number | null;
    armazem: string | null;
    quantidade: number;
    disponivel: number;
    /** O que já está comprometido e ainda não saiu. */
    reservado: number;
    minimo: number;
    baixo: boolean;
    custo: number;
    valor: number;
};

export type Resumo = { artigos: number; quantidade: number; valor: number; baixo: number };

export type OpcoesDoStock = {
    armazens: Array<{ id: number; name: string; is_default: boolean }>;
    armazem_padrao: number | null;
    mostra_conservacao: boolean;
    conservacao: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_editar: boolean; pode_transferir: boolean };
};

export type ArtigoParaLote = { id: number; name: string; code: string | null; unit: string | null; net_content: string | null; cost: number; actual: number };

export type ItemDoLote = { product_id: number; product_name: string; code: string | null; unit: string | null; op: 'add' | 'sub'; quantity: number | string; unit_cost: number | string; actual: number };

export type Movimento = {
    id: number; quando: string | null; tipo: string; tipo_rotulo: string; quantidade: number; saldo_depois: number | null;
    armazem: string | null; lote: string | null; notas: string | null; quem: string | null;
    /** O papel do lote — só quando há lote (e uma referência que a rota apanha). */
    preview: string | null; pdf: string | null;
};

export type FiltrosDeStock = {
    procura?: string;
    armazem?: string;
    baixo?: boolean;
    conservacao?: string;
    /** '' = com e sem · 'com' = o que está na prateleira · 'sem' = o que falta. */
    existencia?: string;
    page?: number;
};

type Pagina = { data: LinhaDeStock[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; resumo: Resumo };

export const stock = {
    opcoes: () => api.ler<OpcoesDoStock>('/stock/opcoes'),
    lista: (f: FiltrosDeStock) => api.ler<Pagina>('/stock', { ...f, baixo: f.baixo ? 1 : undefined }),
    artigos: (procura: string, armazem?: string) => api.ler<{ data: ArtigoParaLote[] }>('/stock/artigos', { procura, armazem }),
    movimentos: (produto: number) => api.ler<{ data: Movimento[] }>(`/stock/movimentos/${produto}`),
    ajustar: (corpo: { stock_id: number; nova_quantidade: number; notas?: string | null }) =>
        api.criar<{ data: LinhaDeStock; referencia: string | null; pdf: string | null; message: string }>('/stock/ajustar', corpo),
    transferir: (corpo: { stock_id: number; para_armazem_id: number; quantidade: number; notas?: string | null }) =>
        api.criar<{ referencia: string; pdf: string; message: string }>('/stock/transferir', corpo),
    entrada: (corpo: { armazem_id: number; itens: Array<Omit<ItemDoLote, 'code' | 'unit' | 'actual'>>; notas?: string | null }) =>
        api.criar<{ referencia: string; ok: number; erros: string[]; pdf: string; message: string }>('/stock/entrada', corpo),
};
