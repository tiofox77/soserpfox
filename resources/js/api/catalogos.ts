import { api, type Pagina } from './cliente';

/**
 * OS CATÁLOGOS — fornecedores, categorias, marcas, armazéns, condições de
 * pagamento e impostos — têm todos a mesma forma: uma lista com procura e
 * filtros, e um formulário. O que muda entre eles vem do servidor
 * (`Catalogos`), como esquema: as colunas da tabela, os campos do formulário
 * e as acções que cada um tem. Um catálogo novo entra lá e este ecrã
 * desenha-o.
 */

/** `cor`: só nas escolhas que a têm (os estados de viatura) — pinta a etiqueta. */
export type Escolha = { valor: string; rotulo: string; cor?: string; dados?: Record<string, string | null> };

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
    | 'hora' | 'dias'
    /**
     * UMA DATA, e a `validade` que é uma data com prazo.
     *
     * Escrevem-se do mesmo modo; o que muda é a leitura na lista — o seguro
     * de uma viatura que já caducou sai a vermelho, e é isso que faz alguém
     * pegar no ecrã.
     */
    | 'data' | 'validade'
    /** Uma lista fechada de onde se marcam VÁRIAS: as especialidades de um mecânico. */
    | 'multi'
    /**
     * UMA LISTA ESCRITA À MÃO — os serviços incluídos num pacote.
     *
     * Ao contrário do `multi`, não há lista de onde escolher: cada casa
     * inclui no pacote o que quer, e escrever «Transfer do aeroporto» não
     * pode obrigar a mexer no código.
     */
    | 'etiquetas';

export type Campo = {
    chave: string;
    rotulo: string;
    tipo: TipoDeCampo;
    obrigatorio: boolean;
    /** As listas (`dias`, `multi`) trazem a omissão já em lista. */
    omissao: string | number | boolean | number[] | string[] | null;
    largura: 'meia' | 'inteira';
    opcoes?: Escolha[];
    /** Para `referencia`: a chave em `referencias` de onde vêm as opções. */
    referencia?: string;
    ajuda?: string;
    passo?: number;
    min?: number;
    max?: number;
    /**
     * ESCOLHER ESTE PREENCHE AQUELES — `{ campo_de_destino: chave_em_dados }`.
     * O cliente da viatura escreve o nome, o telefone e o NIF do dono.
     */
    preencher?: Record<string, string>;
};

/** Um separador do formulário e os campos que leva. */
export type GrupoDeCampos = { chave: string; rotulo: string; icone: string; campos: string[] };

export type Coluna = {
    chave: string;
    rotulo: string;
    formato: 'texto' | 'escolha' | 'booleano' | 'numero' | 'percentagem' | 'cor' | 'icone' | 'padrao' | 'dinheiro'
        // `referencia` mostra o NOME que vem no `rotulos`, e não o id: faltava
        // aqui e na célula, e a coluna «Banco» das contas bancárias escrevia «7».
        | 'referencia' | 'hora' | 'dias' | 'data' | 'validade' | 'multi' | 'etiquetas'
        // A chapa da matrícula de Angola (as viaturas).
        | 'matricula'
        // Um número curto numa pastilha com ícone: o WO# e o TAG# da viatura.
        | 'codigo';
    /** Nas colunas `codigo`: o ícone e a cor da pastilha. */
    icone?: string;
    tom?: 'roxo' | 'ambar';
    alinhar?: 'direita';
    /** Nas colunas `multi` os rótulos das chaves; nas `rapido`, a lista que muda o valor na tabela. */
    opcoes?: Escolha[];
    /** Muda-se na própria tabela, sem abrir a ficha (o estado da viatura). */
    rapido?: boolean;
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
    /**
     * A COLUNA POR QUE SE CHAMA UMA LINHA — quase sempre `name`.
     *
     * É o que sai no título da janela de ver, no subtítulo do formulário e na
     * pergunta de apagar. Uma viatura não tem `name`: chama-se pela matrícula,
     * e «Vai apagar . Não há volta.» era o que a pergunta dizia.
     */
    nome: string;
    pesquisa: string;
    colunas: Coluna[];
    campos: Campo[];
    filtros: Filtro[];
    /** Se este catálogo aceita o intervalo de datas de criação. */
    datas: boolean;
    /** Se tem ficha de VER com extrato — hoje, só os fornecedores. */
    extrato: boolean;
    /** Uma ficha própria de VER — hoje, só a da viatura (`oficina/FichaDaViatura`). */
    ficha?: string | null;
    /** Os separadores do formulário, onde os há (a viatura: Viatura, Dono, Documentos). */
    grupos?: GrupoDeCampos[];
    accoes: { activar: boolean; padrao: boolean; logotipo: boolean; apagar: boolean; atribuir?: boolean; importar?: boolean; galeria?: boolean };
    /** Como se chama a imagem deste catálogo — «Logótipo», «Imagem de destaque». */
    imagem: { rotulo: string } | null;
    /** A galeria de imagens, onde a há — é ela que o site de reservas mostra. */
    galeria: { rotulo: string } | null;
    /** Como se chama a atribuição em lote neste catálogo — nulo onde não há. */
    atribuir: { titulo: string; nada: string; pesquisa_ajuda: string } | null;
    /** Como se chama a importação em lote — «Importar de RH», nos mecânicos. */
    importar: { botao: string; titulo: string; nada: string; pesquisa_ajuda: string } | null;
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
    /** As imagens da galeria: o URL para se ver, o caminho para se apagar. */
    galeria?: Array<{ caminho: string; url: string }>;
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
    /** Na IMPORTAÇÃO: quem já cá está vem marcado e não se desmarca. */
    bloqueado?: boolean;
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

    /** Mudar um valor na própria tabela — só nas colunas `rapido`. */
    campo: (tipo: string, id: number, chave: string, valor: string) =>
        api.criar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/campo`, { chave, valor }),

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

    /** Quem se pode importar para este catálogo — os que já cá estão vêm bloqueados. */
    importaveis: (tipo: string, procura: string) =>
        api.ler<{ data: Atribuivel[]; total: number }>(`/catalogos/${tipo}/importaveis`, { procura }),

    /**
     * IMPORTAR CRIA — e não apaga quem não vier na lista. Desfazer uma
     * importação é apagar o registo, com a guarda do `pode_apagar` a valer.
     */
    importar: (tipo: string, ids: number[]) =>
        api.criar<{ quantos: number; message: string }>(`/catalogos/${tipo}/importar`, { ids }),

    logotipo: (tipo: string, id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('logotipo', ficheiro);

        return api.enviar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/logotipo`, corpo);
    },

    /** Juntar imagens à galeria de um registo. */
    juntarAGaleria: (tipo: string, id: number, ficheiros: File[]) => {
        const corpo = new FormData();
        ficheiros.forEach((f) => corpo.append('imagens[]', f));

        return api.enviar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/galeria`, corpo);
    },

    /** Tirar uma pelo CAMINHO: pela posição, apagar duas seguidas apagava a errada. */
    tirarDaGaleria: (tipo: string, id: number, caminho: string) =>
        api.apagar<{ data: Linha; message: string }>(`/catalogos/${tipo}/${id}/galeria`, { caminho }),
};
