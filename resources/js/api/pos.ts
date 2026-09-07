import { api } from './cliente';

/** Um artigo na grelha do balcão. */
export type ArtigoDoPos = {
    id: number;
    nome: string;
    codigo: string | null;
    codigo_de_barras: string | null;
    unidade: string | null;
    servico: boolean;
    preco: number;
    /**
     * «PREÇO NO POS» É UMA PERGUNTA, não um valor.
     *
     * Quando é verdade, o artigo não entra direito no carrinho: abre o modal
     * do preço, com o de catálogo já lá escrito como proposta. É o artigo que
     * se vende a peso, ou por acordo.
     */
    pergunta_preco: boolean;
    imagem: string | null;
    /** Nulo em serviços e em artigos sem gestão de stock: esses vendem-se sempre. */
    stock: number | null;
    categoria_id: number | null;
};

export type CategoriaDoPos = { id: number; nome: string; artigos: number };

export type ClienteDoPos = {
    id: number;
    nome: string;
    nif: string | null;
    telefone: string | null;
    email: string | null;
};

export type OpcoesDoPos = {
    /** Sem turno aberto não se vende. O ecrã manda a pessoa abrir um. */
    turno: { id: number; numero: string; aberto_em: string | null; abertura: number } | null;
    rota_dos_turnos: string;
    armazem: { id: number | null; nome: string | null };
    categorias: CategoriaDoPos[];
    formas_de_pagamento: Array<{ valor: string; rotulo: string }>;
    definicoes: {
        esconde_sem_stock: boolean;
        montantes_rapidos: number[];
        taxa_irt: number;
        mascara_de_preco: boolean;
    };
    permissoes: { pode_vender: boolean; pode_criar_cliente: boolean; pode_mudar_preco: boolean };
};

/** Uma forma de pagamento de uma venda repartida. */
export type Pagamento = { method: string; amount: number; reference?: string | null };

/** O que o servidor devolve quando a venda fecha. */
export type VendaFechada = {
    id: number;
    numero: string;
    numero_interno: string;
    total: number;
    data: string | null;
    cliente: string;
    qr: string | null;
    preview: string;
    message: string;
};

export const pos = {
    opcoes: () => api.ler<OpcoesDoPos>('/pos/opcoes'),

    artigos: (filtros: { procura?: string; categoria?: number | null; armazem?: number | null }) =>
        api.ler<{ data: ArtigoDoPos[] }>('/pos/artigos', filtros),

    clientes: (procura: string) => api.ler<{ data: ClienteDoPos[] }>('/pos/clientes', { procura }),

    /**
     * FECHAR A VENDA.
     *
     * O `local_uuid` é gerado no ecrã, um por tentativa. Não é burocracia do
     * offline: é o que torna a venda idempotente. Se a rede tossir e o
     * operador carregar outra vez, o servidor devolve a factura que já gravou
     * em vez de gravar uma segunda com o mesmo dinheiro e o mesmo stock.
     */
    vender: (corpo: Record<string, unknown>) => api.criar<VendaFechada>('/pos/vender', corpo),
};
