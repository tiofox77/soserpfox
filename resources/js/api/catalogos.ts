import { api, type Pagina } from './cliente';

/**
 * OS CATÁLOGOS — fornecedores, categorias, marcas, armazéns, condições de
 * pagamento e impostos — têm todos a mesma forma: uma lista com procura e
 * filtros, e um formulário. O que muda entre eles vem do servidor
 * (`Catalogos`), como esquema: as colunas da tabela, os campos do formulário
 * e as acções que cada um tem. Um catálogo novo entra lá e este ecrã
 * desenha-o.
 */

export type Escolha = { valor: string; rotulo: string };

/** Um grupo da galeria de ícones — «Dinheiro», «Comida e bebida». */
export type GrupoDeIcones = {
    nome: string;
    /** O `nome` é por que se procura: «carrinho», não `cart-shopping`. */
    icones: Array<{ codigo: string; nome: string }>;
};

export type TipoDeCampo =
    | 'texto' | 'numero' | 'email' | 'url' | 'textarea' | 'booleano' | 'cor' | 'icone'
    | 'escolha' | 'referencia' | 'pais' | 'provincia' | 'municipio' | 'cidade'
    /** Uma hora do dia (`08:00`) e os dias da semana em que se trabalha. */
    | 'hora' | 'dias';

export type Campo = {
    chave: string;
    rotulo: string;
    tipo: TipoDeCampo;
    obrigatorio: boolean;
    omissao: string | number | boolean | null;
    largura: 'meia' | 'inteira';
    opcoes?: Escolha[];
    /** Para `referencia`: a chave em `referencias` de onde vêm as opções. */
    referencia?: string;
    ajuda?: string;
    passo?: number;
    min?: number;
    max?: number;
};

export type Coluna = {
    chave: string;
    rotulo: string;
    formato: 'texto' | 'escolha' | 'booleano' | 'numero' | 'percentagem' | 'cor' | 'icone' | 'padrao' | 'dinheiro'
        | 'hora' | 'dias';
    alinhar?: 'direita';
};

export type Filtro = {
    chave: string;
    rotulo: string;
    /** `escolha` (lista fechada) ou `texto` (escrito à mão, procura por dentro). */
    tipo?: 'escolha' | 'texto';
    /** A dica do campo de texto — nunca há opções num filtro escrito. */
    ajuda?: string | null;
    opcoes?: Escolha[];
};

export type OpcoesDoCatalogo = {
    titulo: string;
    singular: string;
    icone: string;
    /** A cor da faixa deste catálogo — cada lista tinha a sua em Blade. */
    cor: string;
    /** A linha por baixo do título: «Gerir fornecedores». */
    descricao: string;
    /** O rótulo do botão de criar: «Novo Fornecedor», «Nova Categoria». */
    novo: string;
    pesquisa: string;
    colunas: Coluna[];
    campos: Campo[];
    filtros: Filtro[];
    /** Se este catálogo aceita o intervalo de datas de criação. */
    datas: boolean;
    /** Se tem ficha de VER com extrato — hoje, só os fornecedores. */
    extrato: boolean;
    accoes: { activar: boolean; padrao: boolean; logotipo: boolean; apagar: boolean; atribuir?: boolean };
    /** Como se chama a atribuição em lote neste catálogo — nulo onde não há. */
    atribuir: { titulo: string; nada: string; pesquisa_ajuda: string } | null;
    referencias: Record<string, Escolha[]>;
    geografia: { paises: Escolha[]; provincias: string[]; municipios: Record<string, string[]>; pais_padrao: string } | null;
    /**
     * A GALERIA DE ÍCONES, quando este catálogo tem um campo que a use.
     *
     * Vem do servidor e não daqui: há formulários em React e formulários em
     * Blade, e duas galerias em dois sítios eram duas listas a divergir à
     * primeira adição.
     */
    galeria_de_icones: GrupoDeIcones[] | null;
    permissoes: { pode_escrever: boolean };
    voltar: string;
};

export type Linha = {
    id: number;
    pode_apagar: boolean;
    is_active?: boolean;
    is_default?: boolean;
    logo?: string | null;
    /** Os rótulos das escolhas e referências, prontos a mostrar. */
    rotulos: Record<string, string>;
} & Record<string, unknown>;

/** Um candidato à atribuição em lote — um funcionário, num turno. */
export type Atribuivel = {
    id: number;
    nome: string;
    /** A segunda linha: o número do funcionário, o código. */
    nota: string | null;
    atribuido: boolean;
};

export type FiltrosDoCatalogo = {
    procura?: string;
    page?: number;
    por_pagina?: number;
    /** O intervalo de criação, só nos catálogos que o declaram. */
    de?: string;
    ate?: string;
} & Record<string, string | number | undefined>;

export const catalogos = {
    opcoes: (tipo: string) => api.ler<OpcoesDoCatalogo>(`/catalogos/${tipo}/opcoes`),

    lista: (tipo: string, filtros: FiltrosDoCatalogo) => api.ler<Pagina<Linha>>(`/catalogos/${tipo}`, filtros),

    criar: (tipo: string, dados: Record<string, unknown>) =>
        api.criar<{ data: Linha; message: string }>(`/catalogos/${tipo}`, dados),

    guardar: (tipo: string, id: number, dados: Record<string, unknown>) =>
        api.guardar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}`, dados),

    apagar: (tipo: string, id: number) => api.apagar<{ message: string }>(`/catalogos/${tipo}/${id}`),

    accao: (tipo: string, id: number, accao: 'activar' | 'padrao') =>
        api.criar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/${accao}`, {}),

    /** Quem se pode atribuir a este registo, e quem já lá está. */
    atribuiveis: (tipo: string, id: number, procura: string) =>
        api.ler<{ data: Atribuivel[]; total: number }>(`/catalogos/${tipo}/${id}/atribuiveis`, { procura }),

    /**
     * A lista COMPLETA de quem fica: quem lá está e não vem, sai. Mandar só
     * os novos deixava sem maneira de tirar alguém sem ir à ficha dele.
     */
    atribuir: (tipo: string, id: number, ids: number[]) =>
        api.criar<{ quantos: number; message: string }>(`/catalogos/${tipo}/${id}/atribuir`, { ids }),

    logotipo: (tipo: string, id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('logotipo', ficheiro);

        return api.enviar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/logotipo`, corpo);
    },
};
