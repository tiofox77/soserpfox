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

export function CartaoNumero({
    rotulo,
    valor,
    sufixo,
    icone,
    tom = 'indigo',
    nota,
    aoCarregar,
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
    className?: string;
}) {
    const Elemento = aoCarregar ? 'button' : 'div';

    return (
        <Elemento
            type={aoCarregar ? 'button' : undefined}
            onClick={aoCarregar}
            className={cls(
                'bg-gradient-to-br p-4 text-left text-white shadow-lg rounded-xl',
                TONS[tom],
                'transform transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl',
                aoCarregar && 'hover:scale-[1.02] active:scale-100 cursor-pointer',
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

                <span className="grid h-10 w-10 flex-none place-items-center rounded-full bg-white/20 sm:h-12 sm:w-12 sm:text-lg">
                    <i className={`fas ${icone}`} aria-hidden="true" />
                </span>
            </div>
        </Elemento>
    );
}
