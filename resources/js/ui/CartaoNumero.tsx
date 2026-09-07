import type { ReactNode } from 'react';

import { cls } from './tokens';

/**
 * O CARTÃO DE NÚMERO — o de gradiente que esta casa usa desde sempre.
 *
 * É o cartão do topo do painel e das listas: um rótulo pequeno, o número
 * grande, e o ícone num círculo translúcido à direita. Vinha assim dos ecrãs
 * em Blade (`bg-gradient-to-br from-…-500 to-…-600 rounded-xl shadow-lg p-4
 * text-white`), e foi das coisas que mais se notou a faltar quando os ecrãs
 * passaram a React: quatro caixas brancas com números pretos dizem a mesma
 * informação e parecem outro produto.
 *
 * A COR NÃO É DECORAÇÃO. Segue o significado — o que está por receber é
 * âmbar, o vencido é vermelho, o facturado é o azul da casa. Quem olha para o
 * topo da lista sabe onde está o problema antes de ler os rótulos. Por isso o
 * ícone também vai: cor sozinha não chega a quem não a distingue.
 */

const TONS = {
    azul: 'from-blue-500 to-blue-600',
    indigo: 'from-indigo-500 to-violet-600',
    verde: 'from-emerald-500 to-green-600',
    ambar: 'from-amber-500 to-orange-500',
    vermelho: 'from-red-500 to-rose-600',
    cinza: 'from-slate-500 to-slate-600',
    roxo: 'from-purple-600 to-pink-600',
    laranja: 'from-orange-500 to-red-600',
} as const;

export type TomDoCartao = keyof typeof TONS;

/**
 * O TEXTO COLORIDO da variante clara.
 *
 * Na versão de fundo branco o rótulo leva a cor do tom (era assim nos ecrãs de
 * clientes e artigos: «Total Clientes» a verde por cima do número preto). Fica
 * num mapa e não interpolado: sem build, o Tailwind do browser não gera uma
 * classe que só existe montada em tempo de execução.
 */
const TEXTOS = {
    azul: 'text-blue-600',
    indigo: 'text-indigo-600',
    verde: 'text-emerald-600',
    ambar: 'text-amber-600',
    vermelho: 'text-red-600',
    cinza: 'text-slate-600',
    roxo: 'text-purple-600',
    laranja: 'text-orange-600',
} as const;

/** O anel suave da variante clara — a mesma família da cor do ícone. */
const ANEIS = {
    azul: 'border-blue-100',
    indigo: 'border-indigo-100',
    verde: 'border-emerald-100',
    ambar: 'border-amber-100',
    vermelho: 'border-red-100',
    cinza: 'border-slate-200',
    roxo: 'border-purple-100',
    laranja: 'border-orange-100',
} as const;

export function CartaoNumero({
    rotulo,
    valor,
    sufixo,
    icone,
    tom = 'indigo',
    nota,
    aoCarregar,
    aspecto = 'cheio',
    className,
}: {
    rotulo: string;
    valor: ReactNode;
    /** «Kz», «%», o que vier depois do número em pequeno. */
    sufixo?: string;
    icone: string;
    tom?: TomDoCartao;
    /** Uma linha pequena por baixo: «12 documentos», «+4% que o mês passado». */
    nota?: ReactNode;
    /** Quando o cartão é um filtro, carrega-se nele. */
    aoCarregar?: () => void;
    /**
     * `cheio` — o cartão todo com gradiente e texto branco (o do painel).
     * `claro` — cartão branco com o ÍCONE em quadrado de gradiente, número
     * grande a preto e rótulo colorido. Era este o das listas de clientes e
     * artigos em Blade, e é este que dá o número grande e legível.
     *
     * Não são dois desenhos a competir: o cheio é para uma faixa de cartões
     * que se lê como um bloco; o claro é para quando o NÚMERO é o assunto.
     */
    aspecto?: 'cheio' | 'claro';
    className?: string;
}) {
    const Elemento = aoCarregar ? 'button' : 'div';

    if (aspecto === 'claro') {
        return (
            <Elemento
                type={aoCarregar ? 'button' : undefined}
                onClick={aoCarregar}
                className={cls(
                    // `card-hover` vive no layout desde sempre: levanta o
                    // cartão, aprofunda a sombra e passa-lhe um brilho por
                    // cima. Era o que os ecrãs em Blade usavam e o React não
                    // chamava — foi disto que se sentiu a falta.
                    'card-hover rounded-2xl border bg-white p-5 text-left shadow-lg',
                    ANEIS[tom],
                    aoCarregar && 'cursor-pointer',
                    className,
                )}
            >
                {/* O `icon-float` levanta o ícone quando o cartão é apontado —
                    é a regra `.card-hover:hover .icon-float` do layout. */}
                <span
                    className={cls(
                        'icon-float mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br text-2xl text-white shadow-lg',
                        TONS[tom],
                    )}
                >
                    <i className={`fas ${icone}`} aria-hidden="true" />
                </span>

                <p className={cls('text-sm font-semibold', TEXTOS[tom])}>{rotulo}</p>
                <p className="mt-1 text-3xl font-bold leading-tight tabular-nums text-slate-900 sm:text-4xl">
                    {valor}
                    {sufixo && <span className="ml-1 text-base font-normal text-slate-400">{sufixo}</span>}
                </p>
                {nota && <p className="mt-1 text-xs text-slate-500">{nota}</p>}
            </Elemento>
        );
    }

    return (
        <Elemento
            type={aoCarregar ? 'button' : undefined}
            onClick={aoCarregar}
            className={cls(
                // `gradient-shift` faz o gradiente correr ao passar o rato: o
                // cartão responde sem se mexer do sítio, que é o que se quer
                // numa faixa de quatro alinhados.
                'card-hover gradient-shift bg-gradient-to-br p-4 text-left text-white shadow-lg rounded-xl',
                TONS[tom],
                aoCarregar && 'cursor-pointer',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    {/* O rótulo quebra em vez de ser cortado: «Valores Pendentes» a dizer
                        «Valores Pend…» é o pormenor que faz o ecrã parecer por
                        acabar. */}
                    <p className="text-xs font-medium leading-tight text-white/75">{rotulo}</p>
                    <p className="mt-1 text-xl font-bold leading-tight tabular-nums sm:text-2xl">
                        {valor}
                        {sufixo && <span className="ml-1 text-sm font-normal text-white/70">{sufixo}</span>}
                    </p>
                    {nota && <p className="mt-0.5 text-[11px] leading-tight text-white/70">{nota}</p>}
                </div>

                <span className="icon-float grid h-10 w-10 flex-none place-items-center rounded-full bg-white/20 sm:h-12 sm:w-12 sm:text-lg">
                    <i className={`fas ${icone}`} aria-hidden="true" />
                </span>
            </div>
        </Elemento>
    );
}
