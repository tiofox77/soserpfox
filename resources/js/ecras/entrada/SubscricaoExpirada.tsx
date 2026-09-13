import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls, kz } from '@/ui/tokens';

import { Marca, Recados, type PropsDeEntrada } from './comum';

/**
 * A SUBSCRIÇÃO EXPIROU.
 *
 * O `CheckSubscription` manda para aqui com a última subscrição. Mostra-se o
 * que se perdeu, os planos públicos (os mesmos da montra) e os caminhos: a
 * conta, o suporte, sair. O telefone do Blade era um número de exemplo
 * («923 456 789»); o contacto vem agora das definições da plataforma.
 */
type Plano = {
    id: number;
    nome: string;
    mensal: number;
    tem_anual: boolean;
    utilizadores: number;
    armazenamento_gb: number;
    destaque: boolean;
    funcionalidades: string[];
    escolher: string;
};

type Props = PropsDeEntrada & {
    empresa: { nome: string; email: string } | null;
    expirou: { em: string | null; plano: string | null } | null;
    planos: Plano[];
    conta: string;
    sair: string;
};

const MODULOS: Array<[string, string]> = [
    ['Faturação', 'fa-file-invoice'], ['RH', 'fa-users'], ['Contabilidade', 'fa-calculator'], ['Oficina', 'fa-wrench'],
    ['CRM', 'fa-handshake'], ['Inventário', 'fa-boxes'], ['Compras', 'fa-shopping-cart'], ['Projetos', 'fa-project-diagram'],
    ['Eventos', 'fa-calendar-alt'], ['Restaurante', 'fa-utensils'],
];

export default function SubscricaoExpirada(p: Props) {
    const contacto = p.contacto ?? { email: '', telefone: '' };
    const assunto = encodeURIComponent(`${t('Renovação de Subscription')} - ${p.empresa?.nome ?? t('Cliente')}`);

    return (
        <div className="relative flex min-h-screen items-center justify-center p-4">
            <div className="w-full max-w-6xl py-8">
                <div className="entra"><Marca logo={p.logo} nome={p.nome} site={p.site} /></div>

                <div className="animate-scale-in overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div className="bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600 p-8 text-center text-white">
                        <span className="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
                            <i className="fas fa-exclamation-triangle icon-float text-5xl" aria-hidden="true" />
                        </span>
                        <h1 className="mb-2 text-3xl font-bold">🔒 {t('Subscription Expirada')}</h1>
                        <p className="text-lg text-blue-100">{t('Seu plano de acesso ao :app expirou', { app: p.nome ?? 'SOS ERP' })}</p>
                    </div>

                    <div className="p-8">
                        <Recados recados={p.recados} />

                        {p.empresa && (
                            <div className="mb-6 flex items-center rounded-2xl border-2 border-blue-200 bg-gradient-to-r from-blue-50 to-purple-50 p-6">
                                <i className="fas fa-building mr-3 text-2xl text-blue-600" aria-hidden="true" />
                                <div>
                                    <h2 className="text-lg font-bold text-gray-800">{p.empresa.nome}</h2>
                                    <p className="text-sm text-gray-600">{p.empresa.email}</p>
                                </div>
                            </div>
                        )}

                        {p.expirou && (
                            <div className="mb-6 flex items-start rounded-2xl border-2 border-red-200 bg-red-50 p-6">
                                <i className="fas fa-calendar-times mr-4 mt-1 text-2xl text-red-600" aria-hidden="true" />
                                <div>
                                    <h2 className="mb-2 font-bold text-red-800">{t('Seu plano expirou em:')}</h2>
                                    <p className="mb-3 text-2xl font-bold text-red-600">{p.expirou.em ?? t('Data não disponível')}</p>
                                    {p.expirou.plano && <p className="text-sm text-gray-600"><strong>{t('Plano anterior:')}</strong> {p.expirou.plano}</p>}
                                </div>
                            </div>
                        )}

                        <div className="mb-8">
                            <h2 className="mb-4 flex items-center text-xl font-bold text-gray-800"><i className="fas fa-info-circle mr-3 text-blue-600" aria-hidden="true" />{t('O que aconteceu?')}</h2>
                            <p className="mb-4 leading-relaxed text-gray-600">{t('Seu período de subscription chegou ao fim. Para continuar acessando todos os módulos e funcionalidades do sistema, é necessário renovar seu plano.')}</p>
                            <p className="leading-relaxed text-gray-600">{t('Não se preocupe! Seus dados estão seguros e serão preservados. Ao renovar, você terá acesso imediato a tudo novamente.')}</p>
                        </div>

                        <div className="mb-8 rounded-2xl border-2 border-orange-200 bg-gradient-to-r from-orange-50 to-red-50 p-6">
                            <h2 className="mb-4 flex items-center font-bold text-gray-800"><i className="fas fa-lock mr-3 text-orange-600" aria-hidden="true" />{t('Módulos Bloqueados')}</h2>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                {MODULOS.map(([nome, icone], i) => (
                                    <div key={nome} className="entra flex items-center text-sm text-gray-600" style={{ ['--i' as string]: i }}>
                                        <i className={cls('fas mr-2 text-orange-500', icone)} aria-hidden="true" />{t(nome)}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="mb-8">
                            <h2 className="mb-4 flex items-center font-bold text-gray-800"><i className="fas fa-check-circle mr-3 text-green-600" aria-hidden="true" />{t('Ao renovar você terá:')}</h2>
                            <ul className="space-y-3">
                                {[
                                    [t('Acesso imediato'), t('a todos os módulos do sistema')],
                                    [t('Dados preservados'), t('- nada foi perdido!')],
                                    [t('Suporte técnico'), t('prioritário')],
                                    [t('Atualizações'), t('constantes e novas funcionalidades')],
                                    [t('Conformidade fiscal'), t('AGT Angola garantida')],
                                ].map(([forte, resto], i) => (
                                    <li key={i} className="entra flex items-start" style={{ ['--i' as string]: i }}>
                                        <i className="fas fa-check mr-3 mt-1 text-green-500" aria-hidden="true" />
                                        <span className="text-gray-600"><strong>{forte}</strong> {resto}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="space-y-3">
                            <button type="button" onClick={() => document.getElementById('planos')?.scrollIntoView({ behavior: 'smooth' })}
                                className={cls('block w-full rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4 text-center text-lg font-bold text-white shadow-lg hover:scale-[1.02] hover:from-blue-700 hover:to-purple-700', TRANSICAO, FOCO)}>
                                <i className="fas fa-shopping-cart mr-2" aria-hidden="true" />{t('Ver Planos e Renovar')}
                            </button>
                            <a href={`${p.conta}?tab=plan`} className={cls('block w-full rounded-xl border-2 border-blue-300 bg-white px-6 py-3 text-center font-semibold text-blue-700 hover:bg-gray-50', TRANSICAO, FOCO)}>
                                <i className="fas fa-crown mr-2" aria-hidden="true" />{t('Gerenciar Plano Atual')}
                            </a>
                            <a href={`mailto:${contacto.email}?subject=${assunto}`} className={cls('block w-full rounded-xl border-2 border-gray-300 bg-white px-6 py-3 text-center font-semibold text-gray-700 hover:bg-gray-50', TRANSICAO, FOCO)}>
                                <i className="fas fa-headset mr-2" aria-hidden="true" />{t('Falar com Suporte')}
                            </a>
                            <form action={p.sair} method="POST">
                                <input type="hidden" name="_token" value={p.csrf} />
                                <button type="submit" className={cls('w-full rounded-xl border-2 border-gray-200 bg-white px-6 py-3 text-center font-semibold text-gray-500 hover:bg-gray-50', TRANSICAO, FOCO)}>
                                    <i className="fas fa-sign-out-alt mr-2" aria-hidden="true" />{t('Sair do Sistema')}
                                </button>
                            </form>
                        </div>

                        <div className="mt-8 border-t-2 border-gray-100 pt-6 text-center text-sm text-gray-500">
                            {contacto.telefone && <p className="mb-2"><i className="fas fa-phone mr-2" aria-hidden="true" /><strong>{t('Telefone')}:</strong> {contacto.telefone}</p>}
                            <p><i className="fas fa-envelope mr-2" aria-hidden="true" /><strong>{t('Email')}:</strong> {contacto.email}</p>
                        </div>
                    </div>
                </div>

                <section id="planos" className="mt-8 rounded-3xl bg-white p-8 shadow-2xl">
                    <div className="mb-10 text-center">
                        <h2 className="mb-2 text-3xl font-bold text-gray-900"><i className="fas fa-rocket mr-2 text-blue-600" aria-hidden="true" />{t('Escolha seu Plano')}</h2>
                        <p className="text-gray-600">{t('Selecione o melhor plano para o seu negócio')}</p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        {p.planos.map((pl, i) => (
                            <div key={pl.id} style={{ ['--i' as string]: i }}
                                className={cls('card-hover entra relative rounded-2xl border-2 bg-white p-6 shadow-lg hover:shadow-2xl', pl.destaque ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200')}>
                                {pl.destaque && (
                                    <span className="absolute -top-4 left-1/2 inline-flex -translate-x-1/2 items-center rounded-full bg-gradient-to-r from-yellow-400 to-orange-500 px-4 py-1.5 text-xs font-bold text-white shadow-lg">
                                        <i className="fas fa-fire mr-1" aria-hidden="true" />{t('Popular')}
                                    </span>
                                )}
                                <div className={cls('mb-4 text-center', pl.destaque && 'mt-2')}>
                                    <span className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-purple-100">
                                        <i className={cls('fas icon-float text-2xl text-blue-600', pl.destaque ? 'fa-crown' : 'fa-box')} aria-hidden="true" />
                                    </span>
                                    <h3 className="mb-1 text-xl font-bold text-gray-900">{pl.nome}</h3>
                                    <div className="mb-2 flex items-baseline justify-center gap-1">
                                        <span className="text-3xl font-bold tabular-nums text-gray-900">{kz(pl.mensal, 0)}</span>
                                        <span className="text-sm text-gray-600">{t('Kz/mês')}</span>
                                    </div>
                                    {pl.tem_anual && <p className="text-xs text-green-600"><i className="fas fa-tag mr-1" aria-hidden="true" />{t('Economize no anual')}</p>}
                                </div>

                                <div className="mb-4 space-y-2">
                                    <div className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                                        <span className="flex items-center text-xs text-gray-600"><i className="fas fa-users mr-2 text-blue-500" aria-hidden="true" />{t('Utilizadores')}</span>
                                        <span className="font-bold text-gray-900">{pl.utilizadores}</span>
                                    </div>
                                    <div className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                                        <span className="flex items-center text-xs text-gray-600"><i className="fas fa-database mr-2 text-purple-500" aria-hidden="true" />{t('Storage')}</span>
                                        <span className="font-bold text-gray-900">{pl.armazenamento_gb.toFixed(1)}GB</span>
                                    </div>
                                </div>

                                {pl.funcionalidades.length > 0 && (
                                    <div className="mb-4 space-y-1.5 border-t border-gray-100 pt-4">
                                        {pl.funcionalidades.slice(0, 3).map((f, j) => (
                                            <div key={j} className="flex items-start gap-2">
                                                <i className="fas fa-check mt-0.5 flex-shrink-0 text-xs text-green-500" aria-hidden="true" />
                                                <span className="text-xs text-gray-700">{f}</span>
                                            </div>
                                        ))}
                                        {pl.funcionalidades.length > 3 && (
                                            <p className="pt-1 text-xs font-medium text-blue-600">{t('+ :n recursos adicionais', { n: pl.funcionalidades.length - 3 })}</p>
                                        )}
                                    </div>
                                )}

                                <a href={pl.escolher} className={cls('btn-press block w-full rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-5 py-3 text-center text-sm font-bold text-white shadow-lg hover:from-blue-700 hover:to-purple-700', TRANSICAO, FOCO)}>
                                    <i className="fas fa-shopping-cart mr-2" aria-hidden="true" />{t('Selecionar')}
                                </a>
                            </div>
                        ))}
                    </div>

                    <div className="mt-8 flex flex-wrap items-center justify-center gap-8 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-purple-50 p-5 text-center">
                        {([['fa-bolt', 'text-blue-600', 'Acesso Imediato'], ['fa-shield-alt', 'text-green-600', 'Dados Preservados'], ['fa-headset', 'text-purple-600', 'Suporte Prioritário'], ['fa-check-circle', 'text-blue-600', 'AGT Angola']] as const).map(([icone, cor, rotulo]) => (
                            <div key={rotulo} className="flex items-center gap-2">
                                <i className={cls('fas text-lg', icone, cor)} aria-hidden="true" />
                                <span className="text-sm font-semibold text-gray-700">{t(rotulo)}</span>
                            </div>
                        ))}
                    </div>
                </section>

                <p className="mt-6 text-center text-sm text-gray-500">&copy; {new Date().getFullYear()} {p.nome} - {t('Sistema de Gestão Empresarial')}</p>
            </div>
        </div>
    );
}
