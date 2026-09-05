import { CORES, cls, type Cor } from './tokens';

/**
 * O estado de um documento, dito por palavras E por cor.
 *
 * Nunca só por cor: quem não distingue verde de âmbar tem de conseguir ler o
 * estado à mesma.
 */
export function Etiqueta({ cor = 'neutra', icone, children }: {
    cor?: Cor;
    icone?: string;
    children: React.ReactNode;
}) {
    return (
        <span
            className={cls(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                CORES[cor].suave,
            )}
        >
            {icone && <i className={`fas ${icone}`} aria-hidden="true" />}
            {children}
        </span>
    );
}
