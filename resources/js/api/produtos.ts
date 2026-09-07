import { api, type Pagina } from './cliente';

/**
 * OS CAMPOS DE SECTOR, num sítio só.
 *
 * Farmácia, vestuário, cosmética e mercearia. Nenhum é obrigatório — a maioria
 * do catálogo não preenche nenhum — e por isso viajam sempre, mesmo a null: uma
 * chave omitida deixava o valor anterior no ecrã, e um artigo que deixasse de
 * exigir receita continuava a aparecer marcado.
 */
export type CamposDeSector = {
    requires_prescription: boolean;
    is_controlled: boolean;
    active_ingredient: string | null;
    dosage: string | null;
    pharmaceutical_form: string | null;
    armed_registration: string | null;
    size: string | null;
    color: string | null;
    gender: string | null;
    material: string | null;
    net_content: string | null;
    pao_months: number | null;
    inci_ingredients: string | null;
    storage_conditions: string | null;
    allergens: string | null;
    origin_country: string | null;
};

/** Uma imagem da galeria: o caminho é a chave, a URL é para mostrar. */
export type ImagemDoArtigo = { caminho: string; url: string };

export type Artigo = CamposDeSector & {
    id: number;
    name: string;
    code: string | null;
    sku: string | null;
    barcode: string | null;
    type: 'produto' | 'servico';
    tipo_rotulo: string;
    description: string | null;
    unit: string;
    category: { id: number; name: string } | null;
    category_id: number | null;
    /** A marca do artigo. Só o número — o nome está nas opções. */
    brand_id: number | null;
    /** O fornecedor habitual deste artigo. */
    supplier_id: number | null;
    price: number;
    cost: number | null;
    tax_type: 'iva' | 'isento';
    tax_rate_id: number | null;
    taxa: number | null;
    exemption_reason: string | null;
    manage_stock: boolean;
    /** O preço deste artigo pergunta-se na hora da venda, no POS. */
    preco_no_pos: boolean;
    /** Null num serviço: um serviço não tem stock nenhum. */
    stock: number | null;
    stock_min: number | null;
    stock_max: number | null;
    em_falta: boolean;
    esgotado: boolean;

    /*
     * LOTES E VALIDADES. Viajam sempre, como os de sector: uma chave omitida
     * deixava o valor anterior no formulário, e um artigo a que se tirou o
     * controlo de lotes continuava a aparecer marcado.
     */
    track_batches: boolean;
    track_expiry: boolean;
    require_batch_on_purchase: boolean;
    require_batch_on_sale: boolean;

    is_active: boolean;
    /** Se está na lixeira: apagado, mas recuperável. */
    eliminado: boolean;
    /** A morada da imagem de destaque, já pronta a mostrar. */
    imagem: string | null;
    /** O que está gravado na coluna — é com isto que se pede para apagar. */
    imagem_caminho: string | null;
    galeria: ImagemDoArtigo[];
};

/**
 * O que se envia ao gravar.
 *
 * `stock_quantity` só existe na CRIAÇÃO — numa edição o servidor ignora-o, e
 * é de propósito: o stock é derivado das linhas e ajusta-se na Gestão de
 * Stock, com movimento registado.
 *
 * As imagens NÃO vão aqui: um ficheiro não viaja em JSON. Sobem à parte, em
 * multipart, depois de o artigo existir e ter um número.
 */
export type ArtigoParaGravar = {
    name: string;
    type: 'produto' | 'servico';
    description: string | null;
    /**
     * O código do artigo. Em branco, o servidor gera-o (`PROD000001`) — é por
     * isso que aqui pode ir vazio e não é `required` do lado de lá.
     */
    code: string | null;
    sku: string | null;
    barcode: string | null;
    price: number | string;
    cost: number | string | null;
    unit: string;
    category_id: number | string;
    brand_id: number | string | null;
    supplier_id: number | string | null;
    tax_type: 'iva' | 'isento';
    tax_rate_id: number | string | null;
    exemption_reason: string | null;
    manage_stock: boolean;
    preco_no_pos: boolean;
    stock_min: number | string | null;
    stock_max: number | string | null;
    is_active: boolean;
    stock_quantity?: number | string;

    // Lotes e validades. Um SERVIÇO não os tem: o servidor apaga-os.
    track_batches: boolean;
    track_expiry: boolean;
    require_batch_on_purchase: boolean;
    require_batch_on_sale: boolean;

    // Os de sector. Vazios em quase todo o catálogo, e é assim que fica bem.
    requires_prescription: boolean;
    is_controlled: boolean;
    active_ingredient: string | null;
    dosage: string | null;
    pharmaceutical_form: string | null;
    armed_registration: string | null;
    size: string | null;
    color: string | null;
    gender: string | null;
    material: string | null;
    net_content: string | null;
    pao_months: number | string | null;
    inci_ingredients: string | null;
    storage_conditions: string | null;
    allergens: string | null;
    origin_country: string | null;
};

export type FiltrosDeArtigos = {
    procura?: string;
    tipo?: string;
    categoria?: string;
    activo?: string;
    so_em_falta?: string;
    /** 'sim' ou 'nao' — 'nao' é um filtro a sério, não a ausência de filtro. */
    prescricao?: string;
    tamanho?: string;
    cor?: string;
    conservacao?: string;
    /** O que falta na ficha: 'sem_preco' | 'sem_codigo_barras' | 'sem_categoria'. */
    qualidade?: string;
    /** O intervalo em que a ficha foi criada. */
    de?: string;
    ate?: string;
    /** '1' mostra SÓ os apagados — a lixeira, para os restaurar. */
    eliminados?: string;
    por_pagina?: number;
    page?: number;
};

/** Os números dos cartões, contados sobre o catálogo FILTRADO inteiro. */
export type ResumoDosArtigos = {
    total: number;
    preco_medio: number;
    servicos: number;
    em_falta: number;
};

/** O rastreio de um artigo: as vendas e os movimentos, e o que não bate certo. */
export type RastreioDeArtigo = {
    artigo: { id: number; nome: string; codigo: string | null; unidade: string };
    vendas: Array<{
        id: number;
        data: string | null;
        documento: string | null;
        documento_id: number | null;
        cliente: string | null;
        quantidade: number;
        preco: number;
        total: number;
    }>;
    movimentos: Array<{
        id: number;
        data: string | null;
        tipo: string;
        armazem: string | null;
        quantidade: number;
        origem: string;
    }>;
    por_armazem: Array<{ armazem: string; quantidade: number }>;
    resumo: {
        qtd_vendida: number;
        valor_vendido: number;
        documentos: number;
        entradas: number;
        saidas: number;
        stock_total: number;
        /** Vendido menos saídas. Diferente de zero é o sintoma a investigar. */
        divergencia: number;
    };
    dias: number;
};

/** Um valor de lista fechada, com o rótulo que se mostra. */
export type Escolha = { valor: string; rotulo: string };

export type OpcoesDosArtigos = {
    categorias: Array<{ id: number; name: string }>;
    marcas: Array<{ id: number; name: string }>;
    fornecedores: Array<{ id: number; name: string }>;
    taxas: Array<{ id: number; name: string; rate: number }>;
    unidades: string[];
    generos: Escolha[];
    conservacao: Escolha[];
    /** Os perfis LIGADOS nas Definições: dizem o que aparece por omissão. */
    perfis: string[];
    /** O que o catálogo TEM — para mostrar campos e filtros a quem desligou o perfil. */
    variantes: {
        tamanhos: string[];
        cores: string[];
        ha_receituario: boolean;
        ha_conservacao: boolean;
    };
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

export const produtos = {
    lista: (filtros: FiltrosDeArtigos) =>
        api.ler<Pagina<Artigo> & { resumo: ResumoDosArtigos }>('/products', filtros),

    /** Tirar da lixeira: a ficha, o histórico e as imagens voltam intactos. */
    restaurar: (id: number) => api.criar<{ data: Artigo; message: string }>(`/products/${id}/restaurar`, {}),
    opcoes: () => api.ler<OpcoesDosArtigos>('/products/opcoes'),
    criar: (dados: ArtigoParaGravar) => api.criar<{ data: Artigo }>('/products', dados),
    guardar: (id: number, dados: ArtigoParaGravar) =>
        api.guardar<{ data: Artigo }>(`/products/${id}`, dados),
    apagar: (id: number) => api.apagar<{ message: string; desactivado: boolean }>(`/products/${id}`),

    /** A imagem de destaque. Uma só — a nova substitui a que lá estava. */
    /**
     * PARA ONDE FOI ESTE ARTIGO: vendas e movimentos de stock, lado a lado.
     *
     * As duas listas juntas de propósito — é a discrepância entre elas que
     * denuncia a baixa de stock que falhou.
     */
    rastreio: (id: number, dias: number) =>
        api.ler<RastreioDeArtigo>(`/products/${id}/rastreio`, { dias }),

    imagem: (id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('imagem', ficheiro);

        return api.enviar<{ data: Artigo }>(`/products/${id}/imagem`, corpo);
    },

    apagarImagem: (id: number) => api.apagar<{ data: Artigo }>(`/products/${id}/imagem`),

    /** A galeria ACRESCENTA: o que já lá está não se perde. */
    galeria: (id: number, ficheiros: File[]) => {
        const corpo = new FormData();
        ficheiros.forEach((f) => corpo.append('imagens[]', f));

        return api.enviar<{ data: Artigo }>(`/products/${id}/galeria`, corpo);
    },

    apagarDaGaleria: (id: number, caminho: string) =>
        api.apagar<{ data: Artigo }>(`/products/${id}/galeria?caminho=${encodeURIComponent(caminho)}`),
};
