/** O que a casca do servidor (`App\Support\PaginaDoPwa`) manda a cada ecrã. */

export type NomeDoEcra =
    | 'entrada' | 'pin-esquecido' | 'inicio' | 'catalogo' | 'clientes' | 'novo-cliente'
    | 'documentos' | 'novo-documento' | 'pos' | 'restaurante' | 'sem-acesso';

export interface EntradaDoMenu {
    chave: string;
    url: string;
    icone: string;
    etiqueta: string;
    destaque: boolean;
}

export interface RotasDoPwa {
    entrada: string;
    pinEsquecido: string;
    inicio: string;
    catalogo: string;
    clientes: string;
    novoCliente: string;
    documentos: string;
    novoDocumento: string;
    pos: string;
    restaurante: string;
    sair: string;
    aplicacao: string;
    definirPin: string;
    login: string;
    subscricaoExpirada: string;
}

export interface PropsDoPwa {
    ecra: NomeDoEcra;
    csrf: string;
    lingua: string;
    versao: { numero: string; assinatura: string; etiqueta: string };
    rotas: RotasDoPwa;

    // Só nas públicas (entrada e PIN esquecido).
    erro?: string | null;
    email?: string;

    // Só com sessão.
    utilizador?: { id: number; nome: string; email: string } | null;
    empresa?: number | null;
    menu?: EntradaDoMenu[];
    provincias?: string[];
    semAcesso?: { chave: string; etiqueta: string; permissao: string | null; modulo: string | null };
}
