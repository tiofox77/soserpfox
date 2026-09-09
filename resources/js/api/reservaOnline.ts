import { criarApi } from './cliente';

/**
 * A PÁGINA PÚBLICA DE RESERVAS — a casa vista de fora.
 *
 * Outra porta que a dos ecrãs de dentro: aqui não há sessão nem empresa
 * activa, e quem manda é o SLUG da morada. O `{slug}` vai no caminho, e é ele
 * que o servidor usa para saber de que casa se trata.
 */
const api = criarApi('/api/publico/hotel');

export type ComodidadePublica = { valor: string; rotulo: string; icone: string };

export type ACasa = {
    nome: string;
    descricao: string | null;
    morada: string | null;
    cidade: string | null;
    pais: string | null;
    telefone: string | null;
    whatsapp: string | null;
    email: string | null;
    website: string | null;
    estrelas: number;
    logo: string | null;
    capa: string | null;
    /** As cores da casa: a página pinta-se com elas. */
    cor: string;
    cor2: string;
    boas_vindas: string | null;
    instagram: string | null;
    facebook: string | null;
    mapa: string | null;
    tripadvisor: string | null;
    booking: string | null;
    check_in: string;
    check_out: string;
    politica_de_reserva: string | null;
    politica_de_cancelamento: string | null;
    regras: string | null;
    comodidades: ComodidadePublica[];
    sinal: boolean;
    sinal_percentagem: number;
    antecedencia_minima_horas: number;
    antecedencia_maxima_dias: number;
    cancelamento_horas: number;
};

export type TipoPublico = {
    id: number;
    nome: string;
    descricao: string | null;
    preco_base: number;
    capacidade: number;
    camas_extra: number;
    comodidades: ComodidadePublica[];
    fotos: string[];
};

/** O mesmo tipo, já com o preço destas datas — o que sai das tarifas da casa. */
export type TipoComPreco = TipoPublico & {
    livres: number;
    noites: number;
    preco_por_noite: number;
    preco_total: number;
};

export type HospedePublico = {
    id: number;
    nome: string;
    telefone: string | null;
    email: string | null;
};

export type ReservaFeita = {
    numero: string;
    codigo: string | null;
    tipo: string;
    entrada: string | null;
    saida: string | null;
    noites: number;
    adultos: number;
    criancas: number;
    preco_por_noite: number;
    total: number;
    /** O que a casa pede para confirmar — zero quando não pede sinal. */
    sinal: number;
    sinal_percentagem: number;
};

export const reservaOnline = {
    casa: (slug: string) =>
        api.ler<{ casa: ACasa; tipos: TipoPublico[]; destaques: number[] }>(`/${slug}`),

    disponibilidade: (slug: string, de: string, ate: string) =>
        api.ler<{ de: string; ate: string; tipos: TipoComPreco[] }>(`/${slug}/disponibilidade`, { de, ate }),

    entrar: (slug: string, telefone: string, senha: string) =>
        api.criar<{ hospede: HospedePublico }>(`/${slug}/entrar`, { telefone, senha }),

    registar: (slug: string, dados: { nome: string; telefone: string; email: string; senha: string }) =>
        api.criar<{ hospede: HospedePublico }>(`/${slug}/registar`, dados),

    reservar: (slug: string, dados: {
        tipo: number;
        de: string;
        ate: string;
        adultos: number;
        criancas: number;
        hospede_id?: number | null;
        nome?: string;
        telefone?: string;
        email?: string;
        notas?: string;
    }) => api.criar<{ reserva: ReservaFeita }>(`/${slug}/reservar`, dados),
};
