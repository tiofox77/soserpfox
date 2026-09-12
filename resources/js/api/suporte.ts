/**
 * A PONTE DOS ECRÃS DO SUPORTE.
 *
 * Fala com `/api/v1/invoicing/react/suporte/*`. As imagens vão em multipart —
 * é o único sítio destes dois ecrãs onde o corpo não é JSON.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

export type OpcoesDoSuporte = {
    categorias: Escolha[];
    prioridades: Escolha[];
    maximo_de_imagens: number;
};

/* ─── Os pedidos de ajuda ─────────────────────────────────────────────── */

export type Pedido = {
    id: number;
    numero: string;
    assunto: string;
    descricao: string;
    prioridade: string;
    prioridade_rotulo: string;
    categoria: string;
    categoria_rotulo: string;
    estado: string;
    estado_rotulo: string;
    imagens: string[];
    mensagens: number;
    resolvido_em: string | null;
    quando: string | null;
};

export type MensagemDoPedido = {
    id: number;
    texto: string;
    /** Quem escreveu: o suporte, ou eu. */
    do_suporte: boolean;
    autor: string | null;
    quando: string | null;
};

export type ListaDePedidos = {
    data: Pedido[];
    resumo: {
        total: number; abertos: number; em_curso: number;
        a_responder: number; resolvidos: number;
    };
};

/* ─── O quadro de melhorias ───────────────────────────────────────────── */

export type Sugestao = {
    id: number;
    titulo: string;
    descricao: string;
    estado: string;
    estado_rotulo: string;
    votos: number;
    votei: boolean;
    comentarios: number;
    imagens: string[];
    autor: string | null;
    minha: boolean;
    quando: string | null;
};

export type ListaDeSugestoes = {
    data: Sugestao[];
    resumo: { total: number; minhas: number; planeadas: number; feitas: number };
};

const R = '/suporte';

/** O formulário com imagens: o browser é que põe a fronteira do multipart. */
function comImagens(campos: Record<string, string>, imagens: File[]): FormData {
    const corpo = new FormData();

    for (const [chave, valor] of Object.entries(campos)) corpo.append(chave, valor);
    for (const ficheiro of imagens) corpo.append('images[]', ficheiro);

    return corpo;
}

export const suporte = {
    opcoes: () => api.ler<OpcoesDoSuporte>(`${R}/opcoes`),

    pedidos: {
        listar: (f: { estado?: string; procura?: string }) =>
            api.ler<ListaDePedidos>(`${R}/pedidos`, f),
        ficha: (id: number) =>
            api.ler<{ data: Pedido; fio: MensagemDoPedido[] }>(`${R}/pedidos/${id}`),
        abrir: (campos: Record<string, string>, imagens: File[]) =>
            api.enviar<Recado & { data: Pedido }>(`${R}/pedidos`, comImagens(campos, imagens)),
        responder: (id: number, message: string) =>
            api.criar<Recado>(`${R}/pedidos/${id}/responder`, { message }),
        fechar: (id: number) => api.criar<Recado>(`${R}/pedidos/${id}/fechar`, {}),
    },

    melhorias: {
        listar: (f: { ordem?: string; procura?: string }) =>
            api.ler<ListaDeSugestoes>(`${R}/sugestoes`, f),
        sugerir: (campos: Record<string, string>, imagens: File[]) =>
            api.enviar<Recado & { data: { id: number } }>(`${R}/sugestoes`, comImagens(campos, imagens)),
        votar: (id: number) =>
            api.criar<{ votos: number; votei: boolean }>(`${R}/sugestoes/${id}/votar`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/sugestoes/${id}`),
    },
};
