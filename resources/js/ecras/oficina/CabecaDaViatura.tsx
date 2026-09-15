import type { OpcoesDoCatalogo } from '@/api/catalogos';
import { t, tn } from '@/i18n';
import { RAIO_GRANDE, TRANSICAO, cls } from '@/ui/tokens';

/**
 * O CARTÃO VIVO DA VIATURA — por cima do formulário de adicionar e editar.
 *
 * Pedido de 15/09/2026: «melhorar o modal de adicionar ou editar». Enquanto se
 * escreve, o cartão desenha a matrícula como a chapa, a marca e o modelo, a cor,
 * o estado com a cor do catálogo e as três validades (livrete, seguro,
 * inspecção) a dizer se passaram — o que antes só se via depois de gravar.
 */

/** As cores que se escrevem à mão, em português, para uma amostra. */
const AMOSTRA: Array<[RegExp, string]> = [
    [/pret/i, '#111827'], [/branc/i, '#f8fafc'], [/prat/i, '#cbd5e1'], [/cinz/i, '#6b7280'],
    [/vermelh|encarnad/i, '#dc2626'], [/azul/i, '#2563eb'], [/verde/i, '#16a34a'], [/amarel/i, '#facc15'],
    [/laranj/i, '#f97316'], [/castanh|marrom/i, '#78350f'], [/beg/i, '#e7d7b4'], [/dourad/i, '#ca8a04'],
    [/rox|lil/i, '#7c3aed'], [/rosa/i, '#ec4899'], [/bord/i, '#7f1d1d'],
];

const PONTO: Record<string, string> = {
    verde: 'bg-emerald-400', azul: 'bg-blue-400', ambar: 'bg-amber-400', laranja: 'bg-orange-400',
    teal: 'bg-teal-400', roxo: 'bg-purple-400', vermelho: 'bg-red-400', cinza: 'bg-slate-300',
};

function validade(iso: unknown): { tom: 'ok' | 'perto' | 'passou' | 'nada'; dias: number } {
    if (!iso) return { tom: 'nada', dias: 0 };
    const dia = new Date(`${String(iso).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(dia.getTime())) return { tom: 'nada', dias: 0 };
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    const dias = Math.round((dia.getTime() - hoje.getTime()) / 86400000);

    return { tom: dias < 0 ? 'passou' : dias <= 30 ? 'perto' : 'ok', dias };
}

export function CabecaDaViatura({ valores, o, fotos = 0 }: { valores: Record<string, unknown> | null; o: OpcoesDoCatalogo; fotos?: number }) {
    if (!valores) return null;

    const v = (k: string) => String(valores[k] ?? '').trim();
    const matricula = v('plate').toUpperCase();
    const estado = (o.referencias.estados ?? []).find((e) => e.valor === v('status'));
    const cor = AMOSTRA.find(([re]) => re.test(v('color')))?.[1];
    const km = Number(v('mileage') || 0);

    const documentos = [
        { chave: 'registration_expiry', nome: t('Livrete'), icone: 'fa-id-card' },
        { chave: 'insurance_expiry', nome: t('Seguro'), icone: 'fa-shield-halved' },
        { chave: 'inspection_expiry', nome: t('Inspecção'), icone: 'fa-clipboard-check' },
    ];

    return (
        <div className={cls('relative mb-4 overflow-hidden bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-950 p-4 text-white shadow-lg', RAIO_GRANDE)}>
            {/* O brilho que passa — o mesmo gesto dos cartões do painel. */}
            <span aria-hidden="true" className="pointer-events-none absolute -right-10 -top-16 h-44 w-44 rounded-full bg-indigo-500/20 blur-2xl" />
            <span aria-hidden="true" className="pointer-events-none absolute -bottom-20 left-1/3 h-40 w-40 rounded-full bg-purple-500/10 blur-2xl" />

            <div className="relative flex flex-wrap items-center gap-4">
                {/* A CHAPA DA MATRÍCULA */}
                <div className={cls('flex items-stretch overflow-hidden rounded-md border-2 border-slate-900 bg-white text-slate-900 shadow-md', TRANSICAO, matricula ? 'scale-100' : 'opacity-70')}>
                    <span className="flex w-6 flex-col items-center justify-center bg-blue-700 text-[8px] font-bold leading-none text-white">
                        <span>ANG</span>
                    </span>
                    <span className="min-w-[8.5rem] px-3 py-1.5 text-center font-mono text-lg font-black tracking-[0.18em] sm:text-xl">
                        {matricula || 'LD-00-00-XX'}
                    </span>
                </div>

                <div className="min-w-0 flex-1 basis-[15rem]">
                    <p className="flex flex-wrap items-center gap-x-2 break-words text-lg font-bold leading-tight">
                        <i className="fas fa-car-side text-indigo-300 icon-float" aria-hidden="true" />
                        {[v('brand'), v('model')].filter(Boolean).join(' ') || <span className="font-normal text-white/50">{t('Marca e modelo')}</span>}
                        {v('year') && <span className="text-sm font-medium text-white/60">{v('year')}</span>}
                    </p>
                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-white/70">
                        {v('color') && (
                            <span className="inline-flex items-center gap-1.5">
                                <span className="h-3 w-3 rounded-full ring-2 ring-white/40" style={{ background: cor ?? 'transparent' }} aria-hidden="true" />
                                {v('color')}
                            </span>
                        )}
                        {v('fuel_type') && <span><i className="fas fa-gas-pump mr-1" aria-hidden="true" />{v('fuel_type')}</span>}
                        {km > 0 && <span className="tabular-nums"><i className="fas fa-gauge-high mr-1" aria-hidden="true" />{t(':km km', { km: km.toLocaleString() })}</span>}
                        {v('owner_name') && <span className="truncate"><i className="fas fa-user mr-1" aria-hidden="true" />{v('owner_name')}</span>}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {estado && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ring-white/20">
                            <span className={cls('h-2 w-2 rounded-full', PONTO[estado.cor ?? 'cinza'] ?? PONTO.cinza)} aria-hidden="true" />
                            {estado.rotulo}
                        </span>
                    )}
                    {fotos > 0 && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-400/20 px-2.5 py-1 text-xs font-semibold text-amber-200 ring-1 ring-inset ring-amber-300/30">
                            <i className="fas fa-camera" aria-hidden="true" />
                            {tn(':n fotografia por enviar|:n fotografias por enviar', fotos, { n: fotos })}
                        </span>
                    )}
                </div>
            </div>

            {/* AS TRÊS VALIDADES */}
            <ul className="relative mt-3 grid grid-cols-1 gap-1.5 sm:grid-cols-3 sm:gap-2">
                {documentos.map((d) => {
                    const x = validade(valores[d.chave]);

                    return (
                        <li key={d.chave} className={cls('flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs ring-1 ring-inset', TRANSICAO,
                            x.tom === 'passou' ? 'bg-red-500/20 text-red-100 ring-red-400/40'
                                : x.tom === 'perto' ? 'bg-amber-500/20 text-amber-100 ring-amber-300/40'
                                    : x.tom === 'ok' ? 'bg-emerald-500/15 text-emerald-100 ring-emerald-300/30'
                                        : 'bg-white/5 text-white/50 ring-white/10')}>
                            <i className={cls('fas', d.icone, x.tom === 'passou' && 'animate-pulse')} aria-hidden="true" />
                            <span className="font-semibold">{d.nome}</span>
                            <span className="ml-auto truncate">
                                {x.tom === 'nada' ? t('Sem data')
                                    : x.tom === 'passou' ? tn('Caducou há :n dia|Caducou há :n dias', -x.dias, { n: -x.dias })
                                        : x.dias === 0 ? t('Vence hoje')
                                            : tn('Vence em :n dia|Vence em :n dias', x.dias, { n: x.dias })}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
