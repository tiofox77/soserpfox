import { useQuery } from '@tanstack/react-query';

import { relatorios } from '@/api/relatorios';
import { ErroDaApi } from '@/api/cliente';
import { Carregando } from '@/ui/Carregando';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A PORTA DOS RELATÓRIOS: as secções e os mapas de cada uma, como o
 * servidor as lista (`Relatorios\Catalogo`). Cada cartão abre o mapa no
 * ecrã genérico.
 */
const CORES: Record<string, string> = {
    emerald: 'bg-emerald-100 text-emerald-700', green: 'bg-green-100 text-green-700', orange: 'bg-orange-100 text-orange-700',
    blue: 'bg-blue-100 text-blue-700', red: 'bg-red-100 text-red-700', pink: 'bg-pink-100 text-pink-700', amber: 'bg-amber-100 text-amber-700',
};

export default function RelatoriosHub() {
    const q = useQuery({ queryKey: ['relatorios', 'seccoes'], queryFn: relatorios.seccoes, staleTime: 5 * 60_000 });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    return (
        <div className="space-y-6" data-hub>
            <div className={cls('bg-gradient-to-r from-indigo-600 to-indigo-500 p-6 text-white', RAIO)}>
                <h1 className="text-2xl font-bold">{t('Relatórios')}</h1>
                <p className="text-sm text-indigo-100">{t('Análises e mapas operacionais da gestão de faturação')}</p>
            </div>
            {q.data.seccoes.map((s) => (
                <section key={s.titulo} aria-label={s.titulo}>
                    <h2 className="mb-3 flex items-center gap-2 text-lg font-bold text-slate-800">
                        <span className={cls('inline-flex h-8 w-8 items-center justify-center', RAIO, CORES[s.cor] ?? CORES.blue)}><i className={cls('fas', s.icone)} aria-hidden="true" /></span>
                        {s.titulo}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {s.relatorios.map((r) => (
                            <a key={r.slug} href={r.caminho} className={cls('flex items-start gap-3 border border-slate-200 bg-white p-4 hover:border-indigo-300 hover:shadow-md', RAIO, FOCO)} data-mapa={r.slug}>
                                <span className={cls('inline-flex h-10 w-10 shrink-0 items-center justify-center', RAIO, CORES[s.cor] ?? CORES.blue)}><i className={cls('fas', r.icone)} aria-hidden="true" /></span>
                                <span>
                                    <span className="block font-semibold text-slate-900">{r.nome}</span>
                                    <span className="block text-xs leading-relaxed text-slate-500">{r.desc}</span>
                                </span>
                            </a>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
