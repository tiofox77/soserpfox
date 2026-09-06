/**
 * A forma do menu que o `App\Support\MenuDaCasca` monta no servidor e entrega
 * ao ecrã pelas props. Não há pedido à API: o menu já vem decidido — quem o
 * vê, o que está activo — porque isso é do servidor e de mais ninguém.
 */

export type Ligacao = {
    rotulo: string;
    prefixo: string | null;
    forte: boolean;
    icone: string;
    marca: boolean;
    cor: string;
    barra: string;
    hover: string;
    url: string;
    activo: boolean;
    topo: boolean;
    relatorio: boolean;
};

export type Entrada = Ligacao | { separador: true } | { titulo: string } | { sub: Grupo };

export type Grupo = {
    chave: string;
    rotulo: string;
    prefixo: string | null;
    leve: boolean;
    icone: string;
    cor: string;
    simples: boolean;
    url: string | null;
    activo: boolean;
    aberto: boolean;
    entradas: Entrada[];
};

export type MenuDaCasca = {
    principal: Ligacao[];
    grupos: Grupo[];
    superadmin: Array<{ titulo: string; entradas: Ligacao[] }>;
    fox: boolean;
    suporte: { url: string; activo: boolean; rotulo: string; extra: string };
    utilizador: {
        nome: string;
        papel: string;
        ligacoes: Array<{ url: string; rotulo: string; icone: string; cor: string }>;
        atualizacoes: { url: string; rotulo: string; versao: string };
        sair: string;
    };
};

export type PropsDaCasca = {
    menu: MenuDaCasca;
    logo: string | null;
    nome: string;
    csrf: string;
    voltar: string;
};

export const eLigacao = (e: Entrada): e is Ligacao => 'url' in e && 'rotulo' in e;
export const eSeparador = (e: Entrada): e is { separador: true } => 'separador' in e;
export const eTitulo = (e: Entrada): e is { titulo: string } => 'titulo' in e;
export const eSub = (e: Entrada): e is { sub: Grupo } => 'sub' in e;
