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

export type TipoDeCampo =
    | 'texto' | 'numero' | 'email' | 'url' | 'textarea' | 'booleano' | 'cor'
    | 'escolha' | 'referencia' | 'pais' | 'provincia' | 'municipio' | 'cidade';

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
    formato: 'texto' | 'escolha' | 'booleano' | 'numero' | 'percentagem' | 'cor' | 'icone' | 'padrao' | 'dinheiro';
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
    accoes: { activar: boolean; padrao: boolean; logotipo: boolean; apagar: boolean };
    referencias: Record<string, Escolha[]>;
    geografia: { paises: Escolha[]; provincias: string[]; municipios: Record<string, string[]>; pais_padrao: string } | null;
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

    logotipo: (tipo: string, id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('logotipo', ficheiro);

        return api.enviar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/logotipo`, corpo);
    },
};
