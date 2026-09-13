import { useMemo, useState } from 'react';

import { etiquetaIntl, t } from '@/i18n';
import { cascata } from '@/ui/SemNada';
import { cls } from '@/ui/tokens';

type Item = string | { texto?: string; produto?: 'web' | 'pwa' | 'ambos' };

type Versao = {
    version: string;
    date: string;
    type?: 'major' | 'minor' | 'patch';
    title?: string;
    features?: Item[];
    improvements?: Item[];
    fixes?: Item[];
    security?: Item[];
};

const TIPO = {
    major: ['MAJOR', 'bg-red-100 text-red-700 border-red-300'],
    minor: ['MINOR', 'bg-blue-100 text-blue-700 border-blue-300'],
    patch: ['PATCH', 'bg-emerald-100 text-emerald-700 border-emerald-300'],
} as const;

const SECCOES = [
    { chave: 'features', titulo: 'Novas Funcionalidades', icone: 'fa-rocket', texto: 'text-emerald-700', fundo: 'bg-emerald-50 border-emerald-200' },
    { chave: 'improvements', titulo: 'Melhorias', icone: 'fa-arrow-trend-up', texto: 'text-blue-700', fundo: 'bg-blue-50 border-blue-200' },
    { chave: 'fixes', titulo: 'Correções', icone: 'fa-bug-slash', texto: 'text-amber-700', fundo: 'bg-amber-50 border-amber-200' },
    { chave: 'security', titulo: 'Segurança', icone: 'fa-shield-halved', texto: 'text-red-700', fundo: 'bg-red-50 border-red-200' },
] as const;

const PRODUTOS = [
    { valor: '', rotulo: 'Tudo' },
    { valor: 'web', rotulo: 'Web' },
    { valor: 'pwa', rotulo: 'Offline' },
] as const;

const quando = (iso: string, opcoes: Intl.DateTimeFormatOptions) => new Date(`${iso}T12:00:00`).toLocaleDateString(etiquetaIntl(), opcoes);

/**
 * AS ACTUALIZAÇÕES DO SISTEMA (`/changelog`) — o histórico de versões.
 *
 * As versões vêm do `config/changelog.php`, nas props da página. Um item tanto
 * pode ser texto simples (as centenas de linhas de histórico já escritas) como
 * `{texto, produto}`, e as duas formas convivem de propósito.
 *
 * Ganhou o que o Blade não tinha: procurar nas notas, e ver só o que é da web
 * ou só o que é do modo offline — que é a pergunta de quem abre esta página
 * com um telemóvel na mão.
 */
export default function Actualizacoes({ atual, versoes }: { atual: string; versoes: Versao[] }) {
    const [procura, porProcura] = useState('');
    const [produto, porProduto] = useState<'' | 'web' | 'pwa'>('');

    const visiveis = useMemo(() => {
        const termo = procura.trim().toLocaleLowerCase();
        const serve = (item: Item) => {
            const texto = typeof item === 'string' ? item : (item.texto ?? '');
            const de = typeof item === 'string' ? null : (item.produto ?? null);
            if (produto && de && de !== 'ambos' && de !== produto) return false;
            if (produto && !de && produto === 'pwa') return false;
            return termo === '' || texto.toLocaleLowerCase().includes(termo);
        };

        return versoes
            .map((v) => ({
                ...v,
                features: (v.features ?? []).filter(serve),
                improvements: (v.improvements ?? []).filter(serve),
                fixes: (v.fixes ?? []).filter(serve),
                security: (v.security ?? []).filter(serve),
            }))
            .filter((v) => (termo === '' && !produto) || SECCOES.some((s) => v[s.chave].length > 0) || (termo !== '' && (v.title ?? '').toLocaleLowerCase().includes(termo)));
    }, [versoes, procura, produto]);

    const ultima = versoes[0];

    return (
        <div className="mx-auto max-w-5xl">
            <div className="animate-fade-in mb-8 rounded-2xl bg-gradient-to-r from-indigo-600 via-blue-600 to-purple-600 p-6 text-white shadow-xl lg:p-8">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-3">
                        <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20"><i className="fas fa-rocket icon-float text-2xl" aria-hidden="true" /></span>
                        <div>
                            <h1 className="text-2xl font-bold leading-tight lg:text-3xl">{t('Atualizações do Sistema')}</h1>
                            <p className="text-sm text-blue-100">{t('Histórico de versões e novidades do SOS ERP')}</p>
                        </div>
                    </div>
                    <div className="lg:text-right">
                        <p className="text-xs uppercase tracking-wider text-blue-100">{t('Versão Actual')}</p>
                        <p className="mt-1 text-3xl font-extrabold">v{atual}</p>
                        {ultima?.date && <p className="mt-1 text-xs text-blue-100"><i className="far fa-calendar mr-1" aria-hidden="true" />{quando(ultima.date, { day: 'numeric', month: 'long', year: 'numeric' })}</p>}
                    </div>
                </div>
            </div>

            <div className="mb-6 flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                <label className="flex h-11 flex-1 items-center gap-2 rounded-xl border border-gray-200 px-3 focus-within:ring-2 focus-within:ring-indigo-500">
                    <i className="fas fa-magnifying-glass text-gray-400" aria-hidden="true" />
                    <input type="search" value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Procurar nas notas…')} aria-label={t('Procurar nas notas')}
                        className="min-w-0 flex-1 border-0 bg-transparent text-sm focus:outline-none focus:ring-0" />
                </label>
                <div className="flex gap-1 rounded-xl bg-gray-100 p-1" role="group" aria-label={t('Produto')}>
                    {PRODUTOS.map((p) => (
                        <button key={p.valor} type="button" onClick={() => porProduto(p.valor)} aria-pressed={produto === p.valor}
                            className={cls('rounded-lg px-3 py-1.5 text-sm font-semibold transition', produto === p.valor ? 'bg-white text-indigo-700 shadow' : 'text-gray-600 hover:text-gray-900')}>
                            {t(p.rotulo)}
                        </button>
                    ))}
                </div>
            </div>

            <div className="relative">
                <div className="absolute bottom-0 left-6 top-0 hidden w-0.5 bg-gradient-to-b from-indigo-400 via-blue-300 to-gray-200 md:block" />

                {visiveis.length === 0 && (
                    <p className="rounded-2xl border border-dashed border-gray-300 bg-white py-12 text-center text-gray-500">{t('Nenhuma nota encontrada.')}</p>
                )}

                {visiveis.map((v, i) => {
                    const actual = v.version === atual;
                    const [rotulo, classe] = TIPO[v.type ?? 'patch'] ?? TIPO.patch;

                    return (
                        <div key={v.version} className="entra relative mb-8 md:pl-16" style={cascata(Math.min(i, 8))}>
                            <div className={cls('absolute left-0 top-2 hidden h-12 w-12 items-center justify-center rounded-full shadow-md md:flex',
                                actual ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white ring-4 ring-emerald-100' : 'border-2 border-indigo-300 bg-white text-indigo-600')}>
                                <i className={cls('fas', actual ? 'fa-star' : 'fa-tag')} aria-hidden="true" />
                            </div>

                            <article className="card-hover overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-md">
                                <header className="flex flex-col gap-3 border-b border-gray-100 px-6 py-4 md:flex-row md:items-center md:justify-between">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <span className="text-xl font-bold text-gray-900">v{v.version}</span>
                                        <span className={cls('rounded-full border px-2.5 py-1 text-xs font-bold', classe)}>{rotulo}</span>
                                        {actual && <span className="rounded-full bg-emerald-500 px-2.5 py-1 text-xs font-bold text-white"><i className="fas fa-check-circle mr-1" aria-hidden="true" />{t('Em produção')}</span>}
                                    </div>
                                    <span className="text-sm text-gray-500"><i className="far fa-calendar mr-1" aria-hidden="true" />{quando(v.date, { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                                </header>

                                <div className="space-y-4 px-6 py-5">
                                    {v.title && <h3 className="text-lg font-semibold text-gray-800">{v.title}</h3>}
                                    {SECCOES.map((s) => v[s.chave].length > 0 && (
                                        <div key={s.chave} className={cls('rounded-xl border p-4', s.fundo)}>
                                            <p className={cls('mb-2 flex items-center text-sm font-bold', s.texto)}><i className={cls('fas mr-2', s.icone)} aria-hidden="true" />{t(s.titulo)}</p>
                                            <ul className="space-y-1.5">
                                                {v[s.chave].map((item, j) => {
                                                    const texto = typeof item === 'string' ? item : (item.texto ?? '');
                                                    const de = typeof item === 'string' ? null : (item.produto ?? null);
                                                    return (
                                                        <li key={j} className="flex items-start gap-2 text-sm text-gray-700">
                                                            <i className={cls('fas fa-check-circle mt-0.5 text-xs', s.texto)} aria-hidden="true" />
                                                            <span>
                                                                {de && (
                                                                    <span className={cls('mr-1.5 inline-block rounded px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide',
                                                                        de === 'pwa' ? 'bg-emerald-100 text-emerald-700' : de === 'web' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-700')}>
                                                                        {de === 'ambos' ? t('Web + Offline') : de === 'pwa' ? t('Offline') : t('Web')}
                                                                    </span>
                                                                )}
                                                                {texto}
                                                            </span>
                                                        </li>
                                                    );
                                                })}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            </article>
                        </div>
                    );
                })}
            </div>

            <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p className="mb-2 flex items-center font-bold"><i className="fas fa-mobile-alt mr-2" aria-hidden="true" />{t('O PWA já estava instalado e os ícones não mudaram?')}</p>
                <ul className="ml-4 list-disc space-y-1.5">
                    <li>{t('O conteúdo da app actualiza-se automaticamente — quando há nova versão aparece um aviso "Nova versão disponível" no canto inferior direito (basta clicar em Atualizar agora).')}</li>
                    <li>{t('Os ícones do atalho são guardados pelo sistema operativo (Android/iOS/Windows) no momento da instalação e não actualizam sozinhos.')}</li>
                    <li>{t('Para ver os novos ícones: desinstalar o atalho (manter premido → Desinstalar) e reinstalar a partir do botão "Instalar" do navegador.')}</li>
                    <li>{t('A versão actual está sempre visível no header do PWA e neste cabeçalho — confirme que coincide com :versao.', { versao: `v${atual}` })}</li>
                </ul>
            </div>

            <p className="mt-8 pb-8 text-center text-xs text-gray-500"><i className="fas fa-info-circle mr-1" aria-hidden="true" />{t('Sugestões ou problemas? Use o botão de suporte no canto inferior direito.')}</p>
        </div>
    );
}
