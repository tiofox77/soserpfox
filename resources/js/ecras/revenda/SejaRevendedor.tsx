import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent, type ReactNode } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda } from '@/api/revenda';
import { t } from '@/i18n';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls } from '@/ui/tokens';

import { GRADIENTE_DO_PORTAL } from './comum';

/**
 * «SEJA REVENDEDOR» — a página pública do programa (RV-02).
 *
 * Diz o que o revendedor ganha e como funciona, e recebe o pedido. O pedido
 * fica por aprovar: é o super admin que decide e define a comissão.
 */
export const PROVINCIAS = ['Bengo', 'Benguela', 'Bié', 'Cabinda', 'Cuando', 'Cuanza Norte', 'Cuanza Sul', 'Cubango', 'Cunene', 'Huambo', 'Huíla', 'Icolo e Bengo', 'Luanda', 'Lunda Norte', 'Lunda Sul', 'Malanje', 'Moxico', 'Moxico Leste', 'Namibe', 'Uíge', 'Zaire'];

const VAZIO = {
    name: '', company_name: '', nif: '', email: '', phone: '', province: '', city: '', website: '',
    motivation: '', password: '', password_confirmation: '', aceito_termos: false, site_da_empresa: '',
};

const VANTAGENS = [
    { icone: 'fa-sack-dollar', cor: 'from-emerald-500 to-teal-600', titulo: 'Comissão em cada pagamento', texto: 'Ganha sobre as subscrições das empresas que traz, pela regra combinada consigo.' },
    { icone: 'fa-gauge-high', cor: 'from-violet-500 to-purple-600', titulo: 'O seu próprio portal', texto: 'Vê cada empresa: plano, estado, validade, o que falta pagar e quando entraram pela última vez.' },
    { icone: 'fa-building-circle-check', cor: 'from-sky-500 to-indigo-600', titulo: 'Cria empresas pelos clientes', texto: 'Regista a empresa, escolhe o plano e o dono recebe os dados de entrada por email.' },
    { icone: 'fa-arrows-rotate', cor: 'from-amber-500 to-orange-600', titulo: 'Trata das subscrições', texto: 'Muda de plano, renova e envia o comprovativo em nome do cliente.' },
];

const PASSOS = [
    { icone: 'fa-paper-plane', titulo: 'Faça o pedido', texto: 'Preencha o formulário desta página. Leva dois minutos.' },
    { icone: 'fa-user-check', titulo: 'Aprovamos', texto: 'Analisamos o pedido e enviamos por email o seu código, o link e a comissão.' },
    { icone: 'fa-share-nodes', titulo: 'Partilhe e acompanhe', texto: 'Quem se regista pelo seu link ou com o seu código fica ligado a si.' },
];

export default function SejaRevendedor({ logo = null, nome = 'SOSERP' }: { logo?: string | null; nome?: string }) {
    const [d, porD] = useState(VAZIO);
    const campo = <K extends keyof typeof VAZIO>(k: K, v: (typeof VAZIO)[K]) => porD((a) => ({ ...a, [k]: v }));

    const pedir = useMutation({
        mutationFn: () => revenda.pedir(d),
        meta: { aviso: false },
    });

    const erros = pedir.error instanceof ErroDaApi ? pedir.error.erros : {};
    const erro = (k: string) => erros[k]?.[0];
    const submeter = (e: FormEvent) => { e.preventDefault(); pedir.mutate(); };

    return (
        <div className="min-h-screen bg-white text-gray-900">
            <header className="sticky top-0 z-20 border-b border-gray-100 bg-white/85 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                    <a href="/" className={cls('flex items-center gap-3', RAIO, FOCO)}>
                        {logo ? <img src={logo} alt={nome} className="h-10 w-auto" /> : <span className="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-violet-600 to-emerald-600 text-white"><i className="fas fa-chart-line" aria-hidden="true" /></span>}
                        <span className="text-lg font-bold">{nome}</span>
                    </a>
                    <a href="/revendedor/entrar" className={cls('inline-flex items-center gap-2 border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50', RAIO, TRANSICAO, FOCO)}>
                        <i className="fas fa-right-to-bracket" aria-hidden="true" />{t('Já sou revendedor')}
                    </a>
                </div>
            </header>

            {/* O TOPO — a promessa, em duas linhas, e o caminho para o pedido. */}
            <section className={cls('relative overflow-hidden bg-gradient-to-br text-white', GRADIENTE_DO_PORTAL)}>
                <span aria-hidden="true" className="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
                <span aria-hidden="true" className="pointer-events-none absolute -right-16 -bottom-24 h-80 w-80 rounded-full bg-emerald-300/25 blur-3xl" />
                <div className="relative mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_1fr] lg:py-20">
                    <div className="animate-fade-in">
                        <p className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-widest">
                            <i className="fas fa-handshake" aria-hidden="true" />{t('Programa de Revendedores')}
                        </p>
                        <h1 className="mt-5 text-4xl font-black leading-tight sm:text-5xl" style={{ textWrap: 'balance' } as React.CSSProperties}>
                            {t('Revenda o :nome e ganhe com cada cliente que traz', { nome })}
                        </h1>
                        <p className="mt-5 max-w-xl text-lg text-white/90">
                            {t('Leve às empresas da sua zona um sistema de gestão feito para Angola — facturação certificada pela AGT, POS, stock e muito mais — e acompanhe cada uma num portal só seu.')}
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <a href="#pedido" className={cls('group inline-flex items-center gap-2 bg-white px-6 py-3 font-bold text-violet-700 shadow-lg hover:-translate-y-0.5 hover:shadow-xl', RAIO, TRANSICAO, FOCO)}>
                                {t('Quero ser revendedor')}<i className="fas fa-arrow-down transition-transform group-hover:translate-y-0.5" aria-hidden="true" />
                            </a>
                            <a href="#como-funciona" className={cls('inline-flex items-center gap-2 border border-white/40 px-6 py-3 font-semibold text-white hover:bg-white/10', RAIO, TRANSICAO, FOCO)}>
                                {t('Como funciona')}
                            </a>
                        </div>
                    </div>
                    {/* O cartão do link: é isto que o revendedor passa a ter. */}
                    <div className="animate-scale-in self-center">
                        <div className={cls('rotate-1 bg-white/95 p-6 text-gray-800 shadow-2xl transition-transform duration-500 hover:rotate-0', RAIO_GRANDE)}>
                            <p className="text-xs font-bold uppercase tracking-widest text-violet-600">{t('O seu link de afiliado')}</p>
                            <p className="mt-2 break-all rounded-lg bg-violet-50 px-3 py-2 font-mono text-sm text-violet-900">soserp.vip/r/<b>JOAO4821</b></p>
                            <div className="mt-5 grid grid-cols-2 gap-3 text-center">
                                {[['fa-building', t('Empresas'), 'bg-violet-50 text-violet-700'], ['fa-sack-dollar', t('Comissões'), 'bg-emerald-50 text-emerald-700']].map(([icone, rotulo, tom]) => (
                                    <div key={rotulo} className={cls('p-3', RAIO, tom)}>
                                        <i className={cls('fas icon-float text-2xl', icone)} aria-hidden="true" />
                                        <p className="mt-1 text-sm font-semibold">{rotulo}</p>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-4 text-center text-xs text-gray-500">{t('Exemplo do cartão que recebe ao ser aprovado.')}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <h2 className="text-center text-3xl font-black">{t('O que ganha como revendedor')}</h2>
                <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {VANTAGENS.map((v, i) => (
                        <article key={v.titulo} style={cascata(i)} className={cls('entra card-hover border border-gray-100 bg-white p-6 shadow-md', RAIO_GRANDE)}>
                            <span className={cls('grid h-12 w-12 place-items-center bg-gradient-to-br text-white shadow', RAIO, v.cor)}>
                                <i className={cls('fas icon-float text-xl', v.icone)} aria-hidden="true" />
                            </span>
                            <h3 className="mt-4 font-bold">{t(v.titulo)}</h3>
                            <p className="mt-1.5 text-sm text-gray-600">{t(v.texto)}</p>
                        </article>
                    ))}
                </div>
            </section>

            <section id="como-funciona" className="scroll-mt-20 bg-slate-50">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <h2 className="text-center text-3xl font-black">{t('Como funciona')}</h2>
                    <ol className="mt-10 grid gap-6 md:grid-cols-3">
                        {PASSOS.map((p, i) => (
                            <li key={p.titulo} style={cascata(i)} className="entra relative flex gap-4">
                                <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-violet-600 text-lg font-black text-white shadow-lg ring-4 ring-violet-100">{i + 1}</span>
                                <div>
                                    <h3 className="font-bold"><i className={cls('fas mr-2 text-violet-500', p.icone)} aria-hidden="true" />{t(p.titulo)}</h3>
                                    <p className="mt-1 text-sm text-gray-600">{t(p.texto)}</p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            <section id="pedido" className="scroll-mt-20 mx-auto max-w-3xl px-4 py-16 sm:px-6">
                {pedir.isSuccess ? (
                    <div className={cls('animate-scale-in border border-emerald-200 bg-emerald-50 p-10 text-center shadow-lg', RAIO_GRANDE)}>
                        <span className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-500 text-4xl text-white shadow-lg ring-8 ring-emerald-100">
                            <i className="fas fa-check" aria-hidden="true" />
                        </span>
                        <h2 className="mt-6 text-2xl font-black text-emerald-900">{t('Pedido enviado!')}</h2>
                        <p className="mx-auto mt-2 max-w-md text-emerald-800">{pedir.data.message}</p>
                        <p className="mt-4 text-sm text-emerald-700">{t('Enviámos uma confirmação para :email.', { email: d.email })}</p>
                    </div>
                ) : (
                    <form onSubmit={submeter} noValidate className={cls('border border-gray-100 bg-white p-6 shadow-xl sm:p-8', RAIO_GRANDE)}>
                        <h2 className="text-2xl font-black">{t('Pedido para ser revendedor')}</h2>
                        <p className="mt-1 text-sm text-gray-600">{t('Respondemos por email. Os campos com * são obrigatórios.')}</p>

                        {pedir.error && !Object.keys(erros).length && (
                            <p role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{(pedir.error as Error).message}</p>
                        )}

                        {/* O campo que só um robot preenche. */}
                        <input type="text" name="site_da_empresa" tabIndex={-1} autoComplete="off" aria-hidden="true" value={d.site_da_empresa}
                            onChange={(e) => campo('site_da_empresa', e.target.value)} className="absolute -left-[9999px] h-0 w-0 opacity-0" />

                        <div className="mt-6 grid gap-4 sm:grid-cols-2">
                            <Campo id="rv-nome" rotulo={t('Nome completo')} obrigatorio erro={erro('name')} icone="fa-user">
                                <input id="rv-nome" value={d.name} onChange={(e) => campo('name', e.target.value)} autoComplete="name" className={entrada(erro('name'))} />
                            </Campo>
                            <Campo id="rv-empresa" rotulo={t('Empresa (se revende por uma)')} erro={erro('company_name')} icone="fa-building">
                                <input id="rv-empresa" value={d.company_name} onChange={(e) => campo('company_name', e.target.value)} autoComplete="organization" className={entrada(erro('company_name'))} />
                            </Campo>
                            <Campo id="rv-email" rotulo={t('Email')} obrigatorio erro={erro('email')} icone="fa-envelope">
                                <input id="rv-email" type="email" value={d.email} onChange={(e) => campo('email', e.target.value)} autoComplete="email" className={entrada(erro('email'))} />
                            </Campo>
                            <Campo id="rv-telefone" rotulo={t('Telefone / WhatsApp')} obrigatorio erro={erro('phone')} icone="fa-phone">
                                <input id="rv-telefone" type="tel" value={d.phone} onChange={(e) => campo('phone', e.target.value)} autoComplete="tel" placeholder="923 000 000" className={entrada(erro('phone'))} />
                            </Campo>
                            <Campo id="rv-nif" rotulo={t('NIF')} erro={erro('nif')} icone="fa-id-card">
                                <input id="rv-nif" value={d.nif} onChange={(e) => campo('nif', e.target.value)} className={entrada(erro('nif'))} />
                            </Campo>
                            <Campo id="rv-site" rotulo={t('Site ou página')} erro={erro('website')} icone="fa-globe">
                                <input id="rv-site" type="url" value={d.website} onChange={(e) => campo('website', e.target.value)} placeholder="https://" className={entrada(erro('website'))} />
                            </Campo>
                            <Campo id="rv-provincia" rotulo={t('Província')} erro={erro('province')} icone="fa-map">
                                <select id="rv-provincia" value={d.province} onChange={(e) => campo('province', e.target.value)} className={entrada(erro('province'))}>
                                    <option value="">{t('Escolher...')}</option>
                                    {PROVINCIAS.map((p) => <option key={p} value={p}>{p}</option>)}
                                </select>
                            </Campo>
                            <Campo id="rv-cidade" rotulo={t('Cidade / município')} erro={erro('city')} icone="fa-location-dot">
                                <input id="rv-cidade" value={d.city} onChange={(e) => campo('city', e.target.value)} className={entrada(erro('city'))} />
                            </Campo>
                            <Campo id="rv-como" rotulo={t('Como pensa revender')} obrigatorio erro={erro('motivation')} icone="fa-lightbulb" largo>
                                <textarea id="rv-como" rows={3} value={d.motivation} onChange={(e) => campo('motivation', e.target.value)}
                                    placeholder={t('Os seus clientes, a zona onde trabalha, a experiência com sistemas de gestão...')} className={entrada(erro('motivation'))} />
                            </Campo>
                            <Campo id="rv-senha" rotulo={t('Senha para o portal')} obrigatorio erro={erro('password')} icone="fa-lock">
                                <input id="rv-senha" type="password" value={d.password} onChange={(e) => campo('password', e.target.value)} autoComplete="new-password" className={entrada(erro('password'))} />
                            </Campo>
                            <Campo id="rv-senha2" rotulo={t('Repetir a senha')} obrigatorio icone="fa-lock">
                                <input id="rv-senha2" type="password" value={d.password_confirmation} onChange={(e) => campo('password_confirmation', e.target.value)} autoComplete="new-password" className={entrada()} />
                            </Campo>
                        </div>
                        <p className="mt-2 text-xs text-gray-500">{t('A senha precisa de pelo menos 8 caracteres, com letras e números. Só serve depois de o pedido ser aprovado.')}</p>

                        <label className={cls('mt-5 flex items-start gap-3 border bg-blue-50/60 p-4 text-sm', RAIO, erro('aceito_termos') ? 'border-red-300' : 'border-blue-100')}>
                            <input type="checkbox" checked={d.aceito_termos} onChange={(e) => campo('aceito_termos', e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-gray-300 text-violet-600" />
                            <span>
                                {t('Concordo com os')} <a href="/termos" target="_blank" rel="noreferrer" className="font-semibold text-blue-700 underline">{t('Termos de Serviço')}</a> {t('e li a')} <a href="/privacidade" target="_blank" rel="noreferrer" className="font-semibold text-blue-700 underline">{t('Política de Privacidade')}</a>.
                                {erro('aceito_termos') && <span className="mt-1 block text-red-700">{erro('aceito_termos')}</span>}
                            </span>
                        </label>

                        <button type="submit" disabled={pedir.isPending}
                            className={cls('mt-6 flex w-full items-center justify-center gap-2 bg-gradient-to-r from-violet-600 to-emerald-600 px-6 py-3.5 text-base font-bold text-white shadow-lg hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60', RAIO, TRANSICAO, FOCO)}>
                            <i className={cls('fas', pedir.isPending ? 'fa-spinner fa-spin' : 'fa-paper-plane')} aria-hidden="true" />
                            {pedir.isPending ? t('A enviar...') : t('Enviar pedido')}
                        </button>
                    </form>
                )}
            </section>

            <footer className="border-t border-gray-100 py-8 text-center text-sm text-gray-500">
                © {new Date().getFullYear()} {nome} · <a href="/" className="hover:text-violet-700">{t('Voltar ao site')}</a>
            </footer>
        </div>
    );
}

export const entrada = (erro?: string) => cls(
    'w-full border bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100',
    RAIO, erro ? 'border-red-400' : 'border-gray-300',
);

export function Campo({ id, rotulo, obrigatorio, erro, icone, largo, children }: {
    id: string; rotulo: string; obrigatorio?: boolean; erro?: string; icone?: string; largo?: boolean; children: ReactNode;
}) {
    return (
        <div className={cls(largo && 'sm:col-span-2')}>
            <label htmlFor={id} className="mb-1.5 block text-sm font-semibold text-gray-700">
                {icone && <i className={cls('fas mr-1.5 text-violet-500', icone)} aria-hidden="true" />}{rotulo}{obrigatorio && <span className="text-red-500"> *</span>}
            </label>
            {children}
            {erro && <p className="animate-fade-in mt-1 text-xs text-red-600"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{erro}</p>}
        </div>
    );
}
