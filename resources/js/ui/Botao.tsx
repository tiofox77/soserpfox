import type { ButtonHTMLAttributes, ReactNode } from 'react';

import { ALTURAS, CORES, FOCO, GRADIENTES, RAIO, TOQUE, TRANSICAO, cls, type Altura, type Cor } from './tokens';

/**
 * O BOTÃO DA CASA.
 *
 * O tom `solida` leva gradiente, sombra e movimento — sobe um pouco ao passar
 * o rato, afunda ao carregar. Não é enfeite: é o que diz «isto responde», e é
 * o que o resto do sistema (o que ainda está em Blade) faz desde sempre. Um
 * ecrã novo sem isso lê-se como uma maquete ao lado de um produto acabado.
 *
 * O `suave` fica discreto de propósito: numa barra com quatro acções, só uma
 * é a principal.
 */
export function Botao({
    cor = 'neutra',
    altura = 'normal',
    tom = 'suave',
    icone,
    aTrabalhar = false,
    disabled,
    children,
    className,
    ...resto
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    cor?: Cor;
    altura?: Altura;
    tom?: 'solida' | 'suave';
    icone?: string;
    aTrabalhar?: boolean;
    children?: ReactNode;
}) {
    const solida = tom === 'solida';

    return (
        <button
            {...resto}
            disabled={disabled || aTrabalhar}
            className={cls(
                'group inline-flex items-center justify-center gap-2 font-semibold',
                TRANSICAO,
                RAIO,
                ALTURAS[altura],
                // O gradiente e a sombra são do botão principal; o suave
                // mantém-se plano para não competir com ele.
                solida ? cls(GRADIENTES[cor], 'text-white shadow-md', TOQUE) : CORES[cor].suave,
                FOCO,
                // Um botão desactivado não se mexe nem se levanta: prometer
                // resposta a quem não a vai ter é pior do que não prometer.
                'disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:transform-none',
                className,
            )}
        >
            {aTrabalhar ? (
                <i className="fas fa-spinner fa-spin" aria-hidden="true" />
            ) : (
                icone && (
                    <i
                        className={cls(
                            `fas ${icone}`,
                            'transition-transform duration-200',
                            // O «+» roda ao passar, como no ecrã de sempre.
                            icone.includes('plus') && 'group-hover:rotate-90',
                        )}
                        aria-hidden="true"
                    />
                )
            )}
            {children}
        </button>
    );
}
