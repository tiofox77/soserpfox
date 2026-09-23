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
    /** Só nos serviços do salão: quanto tempo leva, em minutos. */
    duracao?: number;

    /**
     * O QUE O BALCÃO TEM DE SABER ANTES DE FECHAR A VENDA.
     *
     * Depois de emitida a factura, o artigo já saiu da farmácia. A RECEITA
     * avisa e não trava — o operador pode tê-la na mão. O CONTROLADO
     * (psicotrópico) PERGUNTA, e só entra depois de alguém responder.
     */
    receita: boolean;
    controlado: boolean;

    /**
     * A TAXA DE IMPOSTO DESTE ARTIGO, em percentagem. Zero quer dizer isento.
     *
     * Vem resolvida do servidor (`TaxResolver`, a mesma fonte que ele usa a
     * emitir): o ecrã mostra-a, nunca a calcula. É com ela que o carrinho
     * volta a ter a linha do IVA e o «A pagar» COM imposto — como o balcão em
     * Livewire sempre teve, e a migração para React perdeu.
     */
    taxa: number;
};

/**
 * Uma categoria como o balcão a mostra: com os acentos reparados e, se a mesma
 * estava gravada duas vezes, as duas num botão só (`ids` — é por eles que se filtra).
 */
export type CategoriaDoPos = { id: number; ids: number[]; nome: string; artigos: number };

export type ClienteDoPos = {
    id: number;
    nome: string;
    nif: string | null;
    telefone: string | null;
    email: string | null;
};

export type OpcoesDoPos = {
    /** `salon` no balcão do salão: há o separador dos serviços. */
    modulo: 'salon' | null;
    categorias_de_servicos: CategoriaDoPos[];
    /** Sem turno aberto não se vende. O ecrã manda a pessoa abrir um. */
    turno: { id: number; numero: string; aberto_em: string | null; abertura: number } | null;
    rota_dos_turnos: string;
    armazem: { id: number | null; nome: string | null };
    categorias: CategoriaDoPos[];
    /** O logótipo que o cartão de um artigo sem imagem mostra, esbatido. */
    logotipo: string | null;
    formas_de_pagamento: Array<{ valor: string; rotulo: string }>;
    definicoes: {
        esconde_sem_stock: boolean;
        montantes_rapidos: number[];
        taxa_irt: number;
        mascara_de_preco: boolean;
    };
    /**
     * De quem é o carrinho: a empresa e o operador.
     *
     * Vem do servidor porque a chave do espelho em `localStorage` tem de dizer
     * as duas coisas — sem a empresa, trocar de empresa levava o carrinho
     * atrás; sem o operador, um balcão partilhado passava-o ao turno seguinte.
     */
    dono_do_carrinho: { empresa: number; operador: number };
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
    /** O QR da AGT, já como data-URI: o helper devolve um array e isto é a imagem. */
    qr: string | null;
    atcud: string | null;
    /** O papel que a empresa configurou: o modal abre neste. */
    formato: 'talao' | 'a4';
    /** As duas moradas do documento verdadeiro, geradas pelo servidor. */
    papeis: { talao: string; a4: string };
    /** Alias mantido para consumidores antigos; aponta para o papel A4. */
    preview: string;
    message: string;
};

/**
 * O QUE UM CÓDIGO DE BARRAS É.
 *
 * A grelha esconde o que está sem stock: ler um artigo esgotado dava um ecrã
 * vazio, indistinguível de «este código não existe». A pergunta vai ao catálogo
 * inteiro e a resposta diz qual dos casos é.
 */
export type LeituraDeCodigo =
    | { estado: 'curto' }
    | { estado: 'desconhecido' }
    | { estado: 'inactivo' | 'sem_stock' | 'de_modulo'; nome: string; message: string }
    | { estado: 'encontrado'; artigo: ArtigoDoPos };

export const pos = {
    opcoes: (modulo?: string | null) => api.ler<OpcoesDoPos>('/pos/opcoes', modulo ? { modulo } : undefined),

    porCodigo: (codigo: string) => api.ler<LeituraDeCodigo>('/pos/por-codigo', { codigo }),

    /** `categoria`: os `ids` da categoria, separados por vírgula. */
    artigos: (filtros: {
        procura?: string;
        categoria?: string | null;
        armazem?: number | null;
        modulo?: string | null;
        tipo?: 'servicos' | 'produtos';
        /** A página da grelha (60 de cada vez); `meta.mais` diz se há outra. */
        pagina?: number;
    }) =>
        api.ler<{ data: ArtigoDoPos[]; meta?: { pagina: number; por_pagina: number; mais: boolean } }>('/pos/artigos', filtros),

    clientes: (procura: string) => api.ler<{ data: ClienteDoPos[] }>('/pos/clientes', { procura }),

    /** O cliente rápido: NIF opcional, e um NIF que já existe devolve esse cliente. */
    criarCliente: (dados: { name: string; nif: string | null; phone: string | null; email: string | null }) =>
        api.criar<{ data: ClienteDoPos; existente: boolean; message: string }>('/pos/clientes', dados),

    /**
     * FECHAR A VENDA.
     *
     * O `local_uuid` é gerado no ecrã, um por VENDA (e não por clique). Não é
     * burocracia do offline: é o que torna a venda idempotente. Se a rede
     * tossir e o operador carregar outra vez, vai o mesmo identificador e o
     * servidor devolve a factura que já gravou, em vez de gravar uma segunda
     * com o mesmo dinheiro e o mesmo stock.
     */
    vender: (corpo: Record<string, unknown>) => api.criar<VendaFechada>('/pos/vender', corpo),
};
