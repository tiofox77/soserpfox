import type { ReactNode } from 'react';

import { t } from '@/i18n';
import { Etiqueta } from '@/ui/Etiqueta';
import { cascata } from '@/ui/SemNada';
import { RAIO_GRANDE, TRANSICAO, cls, kz } from '@/ui/tokens';

/**
 * AS PEÇAS QUE OS ECRÃS DO PORTAL REPETEM: o cabeçalho com gradiente (como no
 * Blade de sempre), os cartões de números e o estado de uma factura.
 */

export function Cabecalho({ titulo, subtitulo, icone, gradiente, children }: {
    titulo: string; subtitulo: string; icone: string; gradiente: string; children?: ReactNode;
}) {
    return (
        <header className={cls('animate-fade-in mb-6 bg-gradient-to-r p-6 text-white shadow-lg', gradiente, RAIO_GRANDE)}>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                    <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                        <i className={cls('fas icon-float text-2xl', icone)} aria-hidden="true" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-bold">{titulo}</h1>
                        <p className="text-sm opacity-90">{subtitulo}</p>
                    </div>
                </div>
                {children}
            </div>
        </header>
    );
}

const BORDAS: Record<string, { borda: string; rotulo: string; icone: string; fundo: string }> = {
    azul: { borda: 'border-blue-500', rotulo: 'text-blue-600', icone: 'text-blue-500', fundo: 'bg-blue-100' },
    laranja: { borda: 'border-orange-500', rotulo: 'text-orange-600', icone: 'text-orange-500', fundo: 'bg-orange-100' },
    verde: { borda: 'border-green-500', rotulo: 'text-green-600', icone: 'text-green-500', fundo: 'bg-green-100' },
    roxo: { borda: 'border-purple-500', rotulo: 'text-purple-600', icone: 'text-purple-500', fundo: 'bg-purple-100' },
    vermelho: { borda: 'border-red-500', rotulo: 'text-red-600', icone: 'text-red-500', fundo: 'bg-red-100' },
    indigo: { borda: 'border-indigo-500', rotulo: 'text-indigo-600', icone: 'text-indigo-500', fundo: 'bg-indigo-100' },
    ambar: { borda: 'border-amber-500', rotulo: 'text-amber-600', icone: 'text-amber-500', fundo: 'bg-amber-100' },
    cinza: { borda: 'border-gray-400', rotulo: 'text-gray-600', icone: 'text-gray-500', fundo: 'bg-gray-100' },
};

export function Numero({ i, cor, rotulo, valor, nota, icone }: { i: number; cor: keyof typeof BORDAS; rotulo: string; valor: ReactNode; nota?: ReactNode; icone: string }) {
    const c = BORDAS[cor] ?? BORDAS.azul!;

    return (
        <div className={cls('entra card-hover border-l-4 bg-white p-6 shadow-lg', c.borda, RAIO_GRANDE, TRANSICAO)} style={cascata(i)}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className={cls('mb-2 text-sm font-semibold', c.rotulo)}>{rotulo}</p>
                    <p className="mb-1 text-3xl font-bold tabular-nums text-gray-900">{valor}</p>
                    {nota && <p className="text-xs text-gray-500">{nota}</p>}
                </div>
                <span className={cls('flex h-12 w-12 shrink-0 items-center justify-center rounded-xl', c.fundo)}>
                    <i className={cls('fas icon-float text-xl', icone, c.icone)} aria-hidden="true" />
                </span>
            </div>
        </div>
    );
}

export function EstadoDaFactura({ estado, rotulo, atrasada }: { estado: string; rotulo: string; atrasada: boolean }) {
    if (atrasada && estado !== 'paid') return <Etiqueta cor="perigo" icone="fa-triangle-exclamation">{t('Atrasada')}</Etiqueta>;

    const cor = ({ paid: 'bom', partially_paid: 'primaria', pending: 'aviso', sent: 'aviso', overdue: 'perigo', credited: 'neutra', cancelled: 'neutra' } as const)[estado as 'paid'] ?? 'neutra';

    return <Etiqueta cor={cor} ponto>{rotulo}</Etiqueta>;
}

export const kwanzas = (v: number) => `${kz(v)} Kz`;
