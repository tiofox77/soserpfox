import { useEffect, type RefObject } from 'react';

/**
 * FECHAR AO CLICAR FORA, OU COM ESCAPE — o `@click.away` do Alpine que os
 * menus do topo usavam.
 */
export function useFecharFora(ref: RefObject<HTMLElement | null>, aberto: boolean, fechar: () => void): void {
    useEffect(() => {
        if (!aberto) return;

        const clique = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) fechar();
        };
        const tecla = (e: KeyboardEvent) => {
            if (e.key === 'Escape') fechar();
        };

        document.addEventListener('mousedown', clique);
        document.addEventListener('keydown', tecla);

        return () => {
            document.removeEventListener('mousedown', clique);
            document.removeEventListener('keydown', tecla);
        };
    }, [ref, aberto, fechar]);
}

/**
 * As cores que chegam do servidor por nome (`red`, `amber`…). O Tailwind só
 * gera as classes que encontra escritas por inteiro no código — `bg-${cor}-50`
 * não existiria no CSS. Por isso vão todas escritas aqui.
 */
export const TONS: Record<string, { fundo: string; suave: string; texto: string; icone: string; borda: string; gradiente: string; solido: string }> = {
    red: { fundo: 'bg-red-50', suave: 'bg-red-100', texto: 'text-red-900', icone: 'text-red-600', borda: 'border-red-500', gradiente: 'from-red-500 to-red-600', solido: 'bg-red-600 hover:bg-red-700' },
    orange: { fundo: 'bg-orange-50', suave: 'bg-orange-100', texto: 'text-orange-900', icone: 'text-orange-600', borda: 'border-orange-500', gradiente: 'from-orange-500 to-orange-600', solido: 'bg-orange-600 hover:bg-orange-700' },
    amber: { fundo: 'bg-amber-50', suave: 'bg-amber-100', texto: 'text-amber-900', icone: 'text-amber-600', borda: 'border-amber-500', gradiente: 'from-amber-500 to-amber-600', solido: 'bg-amber-600 hover:bg-amber-700' },
    yellow: { fundo: 'bg-yellow-50', suave: 'bg-yellow-100', texto: 'text-yellow-900', icone: 'text-yellow-600', borda: 'border-yellow-500', gradiente: 'from-yellow-500 to-yellow-600', solido: 'bg-yellow-600 hover:bg-yellow-700' },
    green: { fundo: 'bg-green-50', suave: 'bg-green-100', texto: 'text-green-900', icone: 'text-green-600', borda: 'border-green-500', gradiente: 'from-green-500 to-green-600', solido: 'bg-green-600 hover:bg-green-700' },
    blue: { fundo: 'bg-blue-50', suave: 'bg-blue-100', texto: 'text-blue-900', icone: 'text-blue-600', borda: 'border-blue-500', gradiente: 'from-blue-500 to-blue-600', solido: 'bg-blue-600 hover:bg-blue-700' },
    cyan: { fundo: 'bg-cyan-50', suave: 'bg-cyan-100', texto: 'text-cyan-900', icone: 'text-cyan-600', borda: 'border-cyan-500', gradiente: 'from-cyan-500 to-cyan-600', solido: 'bg-cyan-600 hover:bg-cyan-700' },
    purple: { fundo: 'bg-purple-50', suave: 'bg-purple-100', texto: 'text-purple-900', icone: 'text-purple-600', borda: 'border-purple-500', gradiente: 'from-purple-500 to-purple-600', solido: 'bg-purple-600 hover:bg-purple-700' },
};

export const tom = (cor: string) => TONS[cor] ?? TONS.blue!;
