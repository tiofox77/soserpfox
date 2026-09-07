import { api } from './cliente';

/** Uma linha de qualquer das listas que partilham forma. */
export type LinhaDeDocumento = {
    id: number;
    /** A série INTERNA, a que a empresa reconhece — é por ela que se procura. */
    numero: string;
    /** A da AGT, logo abaixo; null enquanto a série não estiver registada. */
    numero_agt: string | null;
    /** O selo do Portal AGT, já decidido pelo servidor. */
    agt: {
        natureza: 'propria' | 'fornecedor' | 'nao-fiscal';
        estado: string | null;
        rotulo: string;
        cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    };
    parte: string;

    /**
     * O LADO — só os recibos o têm: venda ou compra.
     *
     * Não é decoração: um recibo de venda é dinheiro que entrou, um de
     * compra é dinheiro que saiu, e dois do mesmo valor e do mesmo dia
     * são indistinguíveis sem isto.
     */
    lado?: { valor: string; rotulo: string; cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo'; icone: string } | null;

    /**
     * OS MONTANTES A MAIS — o usado e o disponível de um adiantamento,
     * pela chave da coluna. Vazio nos documentos que não os declaram.
     */
    montantes?: Record<string, number>;
    data: string | null;
    estado: string;
    estado_rotulo: string;
    estado_cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    valor: number;

    /**
     * O PRAZO: vencimento nas facturas de compra, validade nas propostas.
     * `expirado` vem decidido do servidor — a comparação com «hoje» é a dele,
     * e não a do relógio de quem está a ver.
     */
    prazo?: string | null;
    expirado?: boolean;

    /** A factura de origem, nas notas de crédito e de débito. */
    origem?: { id: number; numero: string } | null;

    /** O motivo da nota, com o rótulo já traduzido pelo servidor. */
    motivo?: string | null;
    motivo_rotulo?: string | null;

    /** Só nas facturas de compra, que são as únicas com pagamentos. */
    pago?: number;
    saldo?: number;
    /**
     * As acções que ESTA factura de compra ainda aceita, decididas no
     * servidor pelo mesmo serviço que depois as executa — um botão que
     * aparece e depois recusa é pior do que um botão que não aparece.
     */
    pode_anular?: boolean;
    pode_marcar_paga?: boolean;
    /** Um documento já convertido ou anulado não se elimina. */
    pode_apagar?: boolean;
};

/** Os números dos cartões, contados sobre a lista FILTRADA inteira. */
export type ResumoDosDocumentos = {
    total: number;
    valor: number;
    /** Quantos há em cada estado — a lista vem ordenada do maior para o menor. */
    por_estado: Array<{
        estado: string;
        rotulo: string;
        cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
        quantos: number;
        valor: number;
    }>;
};

export type PaginaDeDocumentos = {
    data: LinhaDeDocumento[];
    resumo: ResumoDosDocumentos;
    meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export type OpcoesDosDocumentos = {
    titulo: string;
    /** A frase por baixo do título, na faixa. */
    descricao: string;
    /** O rótulo do botão de criar: «Nova Nota de Crédito», «Novo Orçamento». */
    novo: string;
    /** Criar é outra permissão: quem só vê a lista não a cria. */
    pode_criar: boolean;
    /** 'cliente' ou 'fornecedor' — é o cabeçalho da coluna. */
    parte: string;
    /** O cabeçalho da coluna da parte, quando o documento tem dois lados. */
    parte_rotulo: string | null;
    rota: string;
    tem_saldo: boolean;
    /** O que a coluna «Portal AGT» diz neste documento. */
    agt: 'propria' | 'fornecedor' | 'nao-fiscal';
    /** Só nas facturas de compra, e só para quem pode emitir recibos. */
    pode_pagar: boolean;
    /**
     * Se esta lista oferece duplicar. Quais o fazem está no servidor
     * (`TiposDeDocumento::duplicaveis`), com a permissão de criar já pesada.
     */
    pode_duplicar: boolean;
    /** Se este tipo se elimina por aqui, e se este utilizador o pode fazer. */
    pode_apagar: boolean;
    /** Converter em factura — só nas propostas, e com a permissão de facturar. */
    pode_converter: boolean;
    /** Se há histórico de conversões para abrir. */
    tem_historico: boolean;
    /** O cabeçalho da coluna do prazo, ou null se este documento não o tem. */
    prazo: string | null;
    /** O cabeçalho da coluna da factura de origem, nas notas. */
    origem: string | null;
    /** Os motivos que ESTE tipo de nota tem. Vazio nos outros documentos. */
    motivos: Array<{ valor: string; rotulo: string }>;

    /**
     * OS LADOS deste documento — venda e compra, nos recibos. Nulo onde o
     * documento tem um lado só, que é em todos os outros.
     */
    /** As colunas de valor a mais — «Usado», «Disponível» nos adiantamentos. */
    montantes: Array<{ chave: string; rotulo: string; icone: string }>;

    lados: {
        rotulo: string;
        opcoes: Array<{ valor: string; rotulo: string; cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo'; icone: string }>;
    } | null;
    estados: Array<{ valor: string; rotulo: string }>;
};

export type FiltrosDeDocumentos = {
    procura?: string;
    estado?: string;
    de?: string;
    ate?: string;
    /** Só nas notas: devolução, desconto, juros, multa… */
    motivo?: string;
    /** O lado, nos recibos: `sale` ou `purchase`. */
    lado?: string;
    por_pagina?: number;
    page?: number;
};

/** A ficha de um documento: o que se olha de relance, sem sair da lista. */
export type FichaDeDocumento = {
    numero: string;
    numero_agt: string | null;
    parte: { nome: string; nif: string | null; email: string | null; telefone: string | null };
    data: string | null;
    prazo: string | null;
    prazo_rotulo: string | null;
    estado_rotulo: string;
    estado_cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    agt: { rotulo: string; cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo' };
    regiao_fiscal: string | null;
    notas: string | null;
    /**
     * O bloco fiscal — hash, estado SAFT e submissão. Null no que a empresa
     * NÃO comunica: proformas, orçamentos e facturas de compra (essa é do
     * fornecedor que a emitiu).
     */
    fiscal: {
        hash: string | null;
        estado_saft: string | null;
        agt_estado: string | null;
        agt_referencia: string | null;
        agt_submetido_em: string | null;
    } | null;

    /** O documento que esta nota rectifica. Null fora das notas. */
    rectifica: {
        rotulo: string;
        numero: string | null;
        id: number | null;
        motivo: string | null;
        expressao: string | null;
    } | null;

    /** Recibos e adiantamentos não têm linhas: são dinheiro, não mercadoria. */
    tem_linhas: boolean;
    linhas: Array<{
        descricao: string;
        unidade: string | null;
        quantidade: number;
        preco: number;
        desconto: number;
        taxa: number;
        total: number;
    }>;
    totais: {
        subtotal: number;
        desconto_comercial: number;
        desconto_financeiro: number;
        imposto: number;
        /** A retenção na fonte (IRT). Zero na esmagadora maioria. */
        retencao: number;
        total: number;
    };
    /** IEC e Imposto de Selo, se os houver — sem eles o total não reconcilia. */
    impostos_extra: Array<{ tipo: string; valor: number }>;
};

/** O histórico de conversões: que facturas já saíram desta proposta. */
export type HistoricoDeConversoes = {
    documento: {
        numero: string;
        parte: string;
        data: string | null;
        valor: number;
        estado_rotulo: string;
        estado_cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    };
    facturas: Array<{
        id: number;
        numero: string;
        data: string | null;
        vencimento: string | null;
        total: number;
        estado_rotulo: string;
        estado_cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
        rota: string;
    }>;
};

export const documentos = {
    lista: (tipo: string, filtros: FiltrosDeDocumentos) =>
        api.ler<PaginaDeDocumentos>(`/documentos/${tipo}`, filtros),
    opcoes: (tipo: string) => api.ler<OpcoesDosDocumentos>(`/documentos/${tipo}/opcoes`),

    apagar: (tipo: string, id: number) =>
        api.apagar<{ message: string }>(`/documentos/${tipo}/${id}`),

    /**
     * Converter uma proposta em factura. A conta é do modelo, e a factura
     * nasce em RASCUNHO: converter não é emitir.
     */
    converter: (tipo: string, id: number) =>
        api.criar<{ message: string; factura: { id: number; numero: string; rota: string } }>(
            `/documentos/${tipo}/${id}/converter`,
            {},
        ),

    historico: (tipo: string, id: number) =>
        api.ler<HistoricoDeConversoes>(`/documentos/${tipo}/${id}/historico`),

    /** A ficha para o modal de VER: cabeçalho, linhas e totais. */
    ficha: (tipo: string, id: number) => api.ler<FichaDeDocumento>(`/documentos/${tipo}/${id}`),
};
