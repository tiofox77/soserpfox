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
    is_active: boolean;
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
    sku: string | null;
    barcode: string | null;
    price: number | string;
    cost: number | string | null;
    unit: string;
    category_id: number | string;
    tax_type: 'iva' | 'isento';
    tax_rate_id: number | string | null;
    exemption_reason: string | null;
    manage_stock: boolean;
    preco_no_pos: boolean;
    stock_min: number | string | null;
    stock_max: number | string | null;
    is_active: boolean;
    stock_quantity?: number | string;

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
    por_pagina?: number;
    page?: number;
};

/** Um valor de lista fechada, com o rótulo que se mostra. */
export type Escolha = { valor: string; rotulo: string };

export type OpcoesDosArtigos = {
    categorias: Array<{ id: number; name: string }>;
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
    lista: (filtros: FiltrosDeArtigos) => api.ler<Pagina<Artigo>>('/products', filtros),
    opcoes: () => api.ler<OpcoesDosArtigos>('/products/opcoes'),
    criar: (dados: ArtigoParaGravar) => api.criar<{ data: Artigo }>('/products', dados),
    guardar: (id: number, dados: ArtigoParaGravar) =>
        api.guardar<{ data: Artigo }>(`/products/${id}`, dados),
    apagar: (id: number) => api.apagar<{ message: string; desactivado: boolean }>(`/products/${id}`),

    /** A imagem de destaque. Uma só — a nova substitui a que lá estava. */
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
