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

import { etiquetaIntl } from '@/i18n';

/** Um raio, e é este. */
export const RAIO = 'rounded-xl';

/** Os cartões e os modais são mais redondos do que os botões. */
export const RAIO_GRANDE = 'rounded-2xl';

/** O que separa um objecto do fundo. Sombra só onde alguma coisa flutua. */
export const CARTAO = 'bg-white border border-slate-200 shadow-sm ' + RAIO_GRANDE;
export const FLUTUA = 'shadow-lg';

/**
 * O MOVIMENTO DA CASA.
 *
 * A aplicação já tinha o seu vocabulário — `animate-fade-in`,
 * `animate-scale-in`, `btn-press` — definido no `layouts/app.blade.php` desde
 * antes desta migração. Os ecrãs em React tinham ficado sem ele, e por isso
 * pareciam outro produto: tudo aparecia de repente e nada respondia ao toque.
 *
 * Reutiliza-se o que existe em vez de inventar um segundo conjunto: assim um
 * modal do React abre exactamente como um modal do resto do sistema.
 */
export const TRANSICAO = 'transition-all duration-200';

/** O carregar de um botão: levanta ao passar, afunda ao carregar. */
export const TOQUE = 'transform hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0 active:scale-[.98]';

/**
 * Os gradientes, um por cor.
 *
 * O ecrã em Blade usava-os nos botões principais e nos cabeçalhos dos modais,
 * e é o que dá o ar de acabado. Um botão principal chapado ao lado de um
 * cabeçalho com gradiente lê-se como um esboço por acabar.
 */
export const GRADIENTES = {
    primaria: 'bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700',
    neutra: 'bg-gradient-to-r from-slate-700 to-slate-800 hover:from-slate-800 hover:to-slate-900',
    bom: 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700',
    aviso: 'bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600',
    perigo: 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700',
} as const;

/**
 * A COR DE CADA ECRÃ — a faixa do cabeçalho e o modal que ele abre.
 *
 * Os cinco papéis acima (`GRADIENTES`) são por SIGNIFICADO: primária é a acção
 * principal, perigo destrói, aviso avisa. Isto é outra coisa: é a identidade
 * da página. No Blade, cada lista tinha a sua — clientes verde, artigos
 * roxo→rosa, fornecedores laranja, categorias ciano, marcas rosa — e o modal
 * que ela abre repetia-a no cabeçalho. Não é enfeite: quem trabalha nestas
 * listas todo o dia reconhece a página pela faixa antes de ler o título.
 *
 * SEM `hover:`, ao contrário dos papéis: um cabeçalho não é um botão, e
 * escurecer ao passar o rato promete um clique que não existe.
 */
export const CORES_DE_ECRA = {
    primaria: 'bg-gradient-to-r from-indigo-600 to-violet-600',
    neutra: 'bg-gradient-to-r from-slate-700 to-slate-800',
    bom: 'bg-gradient-to-r from-emerald-600 to-teal-600',
    aviso: 'bg-gradient-to-r from-amber-500 to-orange-500',
    perigo: 'bg-gradient-to-r from-red-600 to-rose-600',
    laranja: 'bg-gradient-to-r from-orange-600 to-red-600',
    roxo: 'bg-gradient-to-r from-purple-600 to-pink-600',
    ciano: 'bg-gradient-to-r from-cyan-600 to-blue-600',
    rosa: 'bg-gradient-to-r from-pink-600 to-rose-600',
    // A TESOURARIA. Era esta a faixa dos seus ecrãs em Blade — reconhece-se
    // o módulo pela cor antes de se ler o título, e não se perde ao migrar.
    teal: 'bg-gradient-to-r from-teal-600 to-cyan-600',
} as const;

export type CorDeEcra = keyof typeof CORES_DE_ECRA;

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

/*
 * O FORMATO SEGUE A LÍNGUA, e não o sítio onde o código foi escrito.
 *
 * Estava 'pt-PT' escrito à mão nos dois formatadores que se seguem. O ecrã
 * traduzia-se e os números por baixo não: quem trabalha em inglês lia
 * «1.234,56» onde esperava «1,234.56». É a meia-tradução que passa
 * despercebida a quem revê o texto e salta à vista a quem usa.
 *
 * Quem sabe a língua é o `i18n` — daqui só se pergunta. Em português nada
 * muda: sem escolha, `lingua()` devolve 'pt' e 'pt' dá o mesmo 'pt-PT' que
 * estava fixo, com vírgula decimal e ponto nos milhares.
 */

/**
 * Dinheiro em kwanzas, à maneira de quem está a olhar: 1.234,56 em português,
 * 1,234.56 em inglês.
 *
 * Uma só função. A máscara existe hoje em `mascara-dinheiro.js` e outra vez no
 * `MoneyHelper` do servidor — é para deixar de haver uma terceira.
 */
export function kz(valor: number | string | null | undefined, casas = 2): string {
    const n = typeof valor === 'string' ? Number(valor) : (valor ?? 0);

    if (!Number.isFinite(n)) {
        return (0).toLocaleString(etiquetaIntl(), {
            minimumFractionDigits: casas,
            maximumFractionDigits: casas,
        });
    }

    return n.toLocaleString(etiquetaIntl(), {
        minimumFractionDigits: casas,
        maximumFractionDigits: casas,
    });
}

/** Uma data como se lê na língua de quem está a olhar: 04/09/2026. */
export function data(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleDateString(etiquetaIntl(), { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/**
 * A DATA COM A HORA — para o que aconteceu num instante, e não num dia.
 *
 * Um consumo lançado no folio, uma limpeza começada, um documento emitido: a
 * hora é a parte que responde «foi antes ou depois?».
 */
export function dataHora(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleString(etiquetaIntl(), {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
}

/**
 * HÁ QUANTO TEMPO — o `diffForHumans` do Blade, na língua de quem olha.
 *
 * Para o que interessa pela distância e não pela data: «visto há 3 dias» diz
 * logo que um aparelho adormeceu; «09/09/2026 14:02» obriga a fazer a conta.
 */
export function haQuanto(iso: string | null | undefined, agora: Date = new Date()): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    const segundos = Math.round((d.getTime() - agora.getTime()) / 1000);
    const passos: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 31_536_000], ['month', 2_592_000], ['week', 604_800], ['day', 86_400], ['hour', 3_600], ['minute', 60],
    ];
    const formato = new Intl.RelativeTimeFormat(etiquetaIntl(), { numeric: 'auto' });

    for (const [unidade, tamanho] of passos) {
        if (Math.abs(segundos) >= tamanho) {
            return formato.format(Math.round(segundos / tamanho), unidade);
        }
    }

    return formato.format(segundos, 'second');
}
