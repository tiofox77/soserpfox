import { api } from './cliente';

/** Uma linha da lista de movimentos. */
export type Movimento = {
    id: number;
    numero: string;
    data: string | null;
    data_curta: string | null;
    descricao: string | null;
    tipo: 'income' | 'expense' | 'transfer';
    categoria: string | null;
    categoria_nome: string | null;
    forma_de_pagamento: string | null;
    valor: number;
    moeda: string;
    estado: 'pending' | 'completed' | 'cancelled';
    referencia: string | null;
    /**
     * A FACTURA POR TRÁS, quando a há.
     *
     * Não é decoração: é o que diz ao ecrã que este movimento não se estorna
     * aqui. Anular uma venda é emitir uma nota de crédito — com linhas
     * escolhidas, imposto recalculado, stock reposto e comunicação à AGT.
     */
    factura_id: number | null;
    compra_id: number | null;
    /* O que o modal de editar volta a pôr no formulário. */
    transaction_type_id: number | null;
    transaction_category_id: number | null;
    payment_method_id: number | null;
    account_id: number | null;
    cash_register_id: number | null;
    notas: string | null;
};

/** Um documento ligado ao movimento, na ficha. */
export type DocumentoLigado = {
    id: number;
    numero: string;
    numero_agt?: string | null;
    data: string | null;
    cliente?: string | null;
    fornecedor?: string | null;
    motivo?: string;
    total: number;
    estado: string;
    estado_rotulo?: string;
    morada: string;
};

export type FichaDoMovimento = Movimento & {
    conta: string | null;
    caixa: string | null;
    tipo_nome: string | null;
    criado_por: string | null;
    criado_em: string | null;
    factura: DocumentoLigado | null;
    compra: DocumentoLigado | null;
    nota_de_credito: DocumentoLigado | null;
};

export type OpcoesDosMovimentos = {
    formas_de_pagamento: Array<{
        id: number;
        nome: string;
        tipo: string;
        /** O destino que o método já sabe: dinheiro vai ao caixa, o resto à conta. */
        conta_padrao: number | null;
        caixa_padrao: number | null;
    }>;
    contas: Array<{ id: number; nome: string }>;
    caixas: Array<{ id: number; nome: string }>;
    tipos: Array<{ id: number; nome: string; natureza: 'income' | 'expense' | 'transfer' }>;
    categorias: Array<{ id: number; nome: string; codigo: string | null; tipo_id: number | null }>;
    categorias_para_filtrar: Array<{ valor: string; rotulo: string }>;
    moedas: string[];
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

export type FiltrosDosMovimentos = {
    procura?: string;
    tipo?: string;
    estado?: string;
    categoria?: string;
    conta?: number | '';
    caixa?: number | '';
    de?: string;
    ate?: string;
    por_pagina?: number;
    page?: number;
};

export type PaginaDeMovimentos = {
    data: Movimento[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    resumo: { movimentos: number; entradas: number; saidas: number; saldo: number };
};

/** O corpo do formulário — os mesmos campos do modal de sempre. */
export type FormularioDoMovimento = {
    transaction_type_id: number | '';
    transaction_category_id: number | '';
    amount: number | string;
    currency: string;
    transaction_date: string;
    payment_method_id: number | '';
    account_id: number | '';
    cash_register_id: number | '';
    reference: string;
    description: string;
    notes: string;
    status: 'pending' | 'completed' | 'cancelled';
};

const RAIZ = '/tesouraria/movimentos';

export const movimentos = {
    opcoes: () => api.ler<OpcoesDosMovimentos>(`${RAIZ}/opcoes`),

    lista: (filtros: FiltrosDosMovimentos) => api.ler<PaginaDeMovimentos>(RAIZ, filtros),

    ficha: (id: number) => api.ler<FichaDoMovimento>(`${RAIZ}/${id}`),

    criar: (corpo: FormularioDoMovimento) =>
        api.criar<{ id: number; numero: string; message: string }>(RAIZ, corpo),

    actualizar: (id: number, corpo: FormularioDoMovimento) =>
        api.guardar<{ message: string }>(`${RAIZ}/${id}`, corpo),

    eliminar: (id: number) => api.apagar<{ message: string }>(`${RAIZ}/${id}`),

    /**
     * ESTORNAR. Devolve 409 com a morada do relatório do POS quando há
     * factura — nesse caso a anulação é uma nota de crédito, não um
     * movimento de sinal contrário.
     */
    creditar: (id: number) =>
        api.criar<{ id: number; numero: string; message: string }>(`${RAIZ}/${id}/creditar`, {}),
};
