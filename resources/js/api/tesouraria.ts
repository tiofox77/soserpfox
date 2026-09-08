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

/* ─── Transferências entre contas e caixas ────────────────────────────── */

/** Uma origem ou destino possível: conta bancária ou caixa, com o saldo. */
export type Bolso = { id: number; nome: string; saldo: number };

export type Transferencia = {
    id: number;
    numero: string;
    data: string | null;
    de: string | null;
    de_e_caixa: boolean;
    para: string | null;
    para_e_caixa: boolean;
    valor: number;
    taxa: number;
    moeda: string;
    descricao: string | null;
    referencia: string | null;
    autor: string | null;
};

export type OpcoesDasTransferencias = {
    contas: Bolso[];
    caixas: Bolso[];
    moedas: string[];
    permissoes: { pode_criar: boolean; pode_anular: boolean };
};

export type PaginaDeTransferencias = {
    data: Transferencia[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    resumo: { transferencias: number; movido: number; taxas: number };
};

/**
 * O corpo do registo.
 *
 * `de` e `para` viajam como `"account:5"` / `"cash:3"` — uma escolha só para
 * duas listas diferentes, que é como o ecrã de sempre o fazia. O servidor
 * confirma que existem e que são desta empresa antes de mexer em dinheiro.
 */
export type FormularioDaTransferencia = {
    de: string;
    para: string;
    amount: number | string;
    fee: number | string;
    currency: string;
    transfer_date: string;
    description: string;
    reference: string;
};

const RAIZ_TRF = '/tesouraria/transferencias';

export const transferencias = {
    opcoes: () => api.ler<OpcoesDasTransferencias>(`${RAIZ_TRF}/opcoes`),

    lista: (filtros: { procura?: string; por_pagina?: number; page?: number }) =>
        api.ler<PaginaDeTransferencias>(RAIZ_TRF, filtros),

    criar: (corpo: FormularioDaTransferencia) =>
        api.criar<{ id: number; numero: string; message: string }>(RAIZ_TRF, corpo),

    anular: (id: number) => api.apagar<{ message: string }>(`${RAIZ_TRF}/${id}`),
};

/* ─── O painel ────────────────────────────────────────────────────────── */

export type PeriodoDoPainel = 'today' | 'week' | 'month' | 'year';

export type PainelDaTesouraria = {
    periodo: PeriodoDoPainel;
    de: string;
    ate: string;
    /** O dinheiro que existe AGORA. Não segue o período. */
    saldos: { caixas: number; contas: number; total: number };
    movimento: { entradas: number; saidas: number; saldo: number };
    /** Facturar não é receber: a ponte entre os documentos e a conta. */
    facturacao: {
        facturado: number; cobrado: number; a_receber: number;
        comprado: number; pago: number; a_pagar: number;
    };
    /** O que precisa de conserto — e o segundo número é a causa do primeiro. */
    por_consertar: { movimentos_sem_destino: number; formas_sem_destino: number };
    grafico: { dias: string[]; entradas: number[]; saidas: number[] };
    categorias: {
        entradas: Array<{ rotulo: string; valor: number }>;
        saidas: Array<{ rotulo: string; valor: number }>;
    };
    recentes: Array<{
        id: number; numero: string; data: string | null; descricao: string | null;
        tipo: 'income' | 'expense' | 'transfer'; valor: number; moeda: string;
        estado: 'pending' | 'completed' | 'cancelled';
        forma_de_pagamento: string | null; destino: string | null;
    }>;
    caixas: Array<{ id: number; nome: string; estado: string; saldo: number }>;
    contas: Array<{ id: number; nome: string; banco: string | null; numero: string | null; saldo: number }>;
};

export const painelDaTesouraria = {
    ler: (periodo: PeriodoDoPainel) => api.ler<PainelDaTesouraria>('/tesouraria/painel', { periodo }),
};

/* ─── Os relatórios ───────────────────────────────────────────────────── */

export type TipoDeRelatorio = 'cash_flow' | 'dre' | 'receivables' | 'payables';

/** Uma linha por categoria, já com o nome legível em vez do código. */
export type LinhaDeCategoria = { codigo: string | null; rotulo: string; valor: number };

/** Uma factura por liquidar, de um lado ou do outro. */
export type LinhaEmAberto = {
    invoice_number: string;
    client?: string;
    supplier?: string;
    invoice_date: string | null;
    due_date: string | null;
    total: number;
    paid: number;
    balance: number;
    status: string;
    overdue: boolean;
};

export type RelatorioDeTesouraria = {
    tipo: TipoDeRelatorio;
    titulo: string;
    periodo: string;
    de: string;
    ate: string;
    tipos: Array<{ valor: TipoDeRelatorio; rotulo: string }>;
    /** As moradas do PDF e do Excel, montadas pelo servidor. */
    descargas: { pdf: string; excel: string };
    dados: Partial<{
        /* Fluxo de caixa */
        initialBalance: number;
        incomeByCategory: LinhaDeCategoria[];
        totalIncome: number;
        expenseByCategory: LinhaDeCategoria[];
        totalExpense: number;
        finalBalance: number;
        /* Demonstração de resultados */
        grossRevenue: number;
        deductions: number;
        netRevenue: number;
        operationalCosts: number;
        grossProfit: number;
        expensesByCategory: LinhaDeCategoria[];
        totalExpenses: number;
        operationalProfit: number;
        netProfit: number;
        /* Contas a receber e a pagar */
        receivables: LinhaEmAberto[];
        totalReceivables: number;
        payables: LinhaEmAberto[];
        totalPayables: number;
        totalOverdue: number;
    }>;
};

export const relatoriosDaTesouraria = {
    ler: (f: { tipo: TipoDeRelatorio; periodo: string; de?: string; ate?: string }) =>
        api.ler<RelatorioDeTesouraria>('/tesouraria/relatorios', f),
};
