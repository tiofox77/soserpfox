import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { ALTURAS, CORES, FOCO, RAIO, cls, type Altura, type Cor } from './tokens';

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
    cor?: Cor;
    altura?: Altura;
    /** Preenchido (a acção principal) ou suave (as outras). */
    tom?: 'solida' | 'suave';
    icone?: string;
    /** Enquanto trabalha, o botão diz que está a trabalhar e não deixa clicar. */
    aTrabalhar?: boolean;
    children?: ReactNode;
};

/**
 * Um botão, e há UM aspecto de botão.
 *
 * Não recebe `className` de propósito. Era por aí que um partial Blade se
 * copiava e se ajustava «só desta vez» — e é assim que se chega a 244
 * gradientes diferentes.
 */
export function Botao({
    cor = 'neutra',
    altura = 'normal',
    tom = 'suave',
    icone,
    aTrabalhar = false,
    disabled,
    children,
    ...resto
}: Props) {
    return (
        <button
            {...resto}
            disabled={disabled || aTrabalhar}
            className={cls(
                'inline-flex items-center justify-center gap-2 font-semibold transition',
                'disabled:cursor-not-allowed disabled:opacity-50',
                RAIO,
                ALTURAS[altura],
                CORES[cor][tom],
                FOCO,
            )}
        >
            {aTrabalhar ? (
                <i className="fas fa-spinner fa-spin" aria-hidden="true" />
            ) : (
                icone && <i className={`fas ${icone}`} aria-hidden="true" />
            )}
            {children}
        </button>
    );
}
