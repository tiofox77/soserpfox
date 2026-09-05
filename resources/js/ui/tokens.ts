/**
 * AS DECISÕES DE ASPECTO, NUM SÍTIO SÓ.
 *
 * As vistas Blade têm hoje **244 combinações de gradiente diferentes** e
 * **7 raios de canto**. Não é feio ecrã a ecrã — é inconsistente, e é a
 * inconsistência que faz um produto parecer amador quando cada peça, sozinha,
 * está bem feita.
 *
 * A regra daqui para a frente: nenhum componente escolhe cores nem raios. Ou
 * está aqui, ou não existe. É a diferença entre um partial Blade — que se copia
 * e se ajusta sem ninguém dar por isso — e um componente que só sabe ser de uma
 * maneira.
 *
 * E os gradientes acabaram. Um cabeçalho a esbater roxo em índigo em cada ecrã
 * não é identidade: é ruído. A cor passa a querer dizer alguma coisa —
 * `perigo` é destruir, `aviso` é ter cuidado — e o resto é tinta neutra.
 */

/** Um raio, e é este. */
export const RAIO = 'rounded-xl';

/** O que separa um objecto do fundo. Sombra só onde alguma coisa flutua. */
export const CARTAO = 'bg-white border border-slate-200 ' + RAIO;
export const FLUTUA = 'shadow-lg';

/**
 * As cores por PAPEL e não por gosto.
 *
 * `primaria` é a acção principal do ecrã, e há uma só por ecrã.
 * `perigo` destrói ou anula. `aviso` avisa. `bom` confirma.
 * O resto é `neutra`.
 */
export const CORES = {
    primaria: {
        solida: 'bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800',
        suave: 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
        texto: 'text-indigo-600',
        risca: 'border-indigo-500',
    },
    neutra: {
        solida: 'bg-slate-800 text-white hover:bg-slate-900',
        suave: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
        texto: 'text-slate-600',
        risca: 'border-slate-300',
    },
    bom: {
        solida: 'bg-emerald-600 text-white hover:bg-emerald-700',
        suave: 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        texto: 'text-emerald-700',
        risca: 'border-emerald-500',
    },
    aviso: {
        solida: 'bg-amber-500 text-white hover:bg-amber-600',
        suave: 'bg-amber-50 text-amber-800 hover:bg-amber-100',
        texto: 'text-amber-700',
        risca: 'border-amber-500',
    },
    perigo: {
        solida: 'bg-red-600 text-white hover:bg-red-700',
        suave: 'bg-red-50 text-red-700 hover:bg-red-100',
        texto: 'text-red-700',
        risca: 'border-red-500',
    },
} as const;

export type Cor = keyof typeof CORES;

/** Alturas de controlo. Três, e não uma por ecrã. */
export const ALTURAS = {
    pequeno: 'h-8 px-3 text-xs',
    normal: 'h-10 px-4 text-sm',
    grande: 'h-12 px-6 text-base',
} as const;

export type Altura = keyof typeof ALTURAS;

/** O foco vê-se sempre. Quem navega por teclado tem de saber onde está. */
export const FOCO =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2';

/** Junta classes ignorando o que for falso. */
export function cls(...partes: Array<string | false | null | undefined>): string {
    return partes.filter(Boolean).join(' ');
}

/**
 * Dinheiro em kwanzas, à maneira daqui: 1.234,56.
 *
 * Uma só função. A máscara existe hoje em `mascara-dinheiro.js` e outra vez no
 * `MoneyHelper` do servidor — é para deixar de haver uma terceira.
 */
export function kz(valor: number | string | null | undefined, casas = 2): string {
    const n = typeof valor === 'string' ? Number(valor) : (valor ?? 0);

    if (!Number.isFinite(n)) {
        return '0,00';
    }

    return n.toLocaleString('pt-PT', {
        minimumFractionDigits: casas,
        maximumFractionDigits: casas,
    });
}

/** Uma data como se lê aqui: 04/09/2026. */
export function data(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
