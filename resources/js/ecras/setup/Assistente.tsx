import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent, type ReactNode } from 'react';

import { ErroDaApi, criarApi } from '@/api/cliente';
import { t } from '@/i18n';
import { Campo, entrada } from '@/ui/Campo';
import { cascata } from '@/ui/SemNada';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

const raiz = criarApi('');

type Regime = { valor: string; rotulo: string };

type Props = {
    empresa?: string;
    nif?: string;
    plano?: string | null;
    regimes?: Regime[];
    logo?: string | null;
    nome?: string;
};

/**
 * O ASSISTENTE DE 1.ª UTILIZAÇÃO DO ON-PREMISE.
 *
 * Com a licença activa e nenhuma empresa na base de dados, cria a empresa e o
 * utilizador administrador. A empresa, o NIF e o plano vêm da licença — o
 * cliente revê e completa. Os campos são os do assistente de sempre, todos.
 */
export default function Assistente({ empresa = '', nif = '', plano = null, regimes = [], logo = null }: Props) {
    const [f, porF] = useState({
        empresa,
        nif,
        regime: regimes[0]?.valor ?? 'regime_geral',
        telefone: '',
        email_empresa: '',
        endereco: '',
        admin_nome: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
    });

    const criar = useMutation({
        mutationFn: () => raiz.criar<{ message: string; ir_para: string }>('/setup', f),
        onSuccess: (r) => window.location.assign(r.ir_para),
    });

    const erros = criar.error instanceof ErroDaApi ? criar.error.erros : {};
    const primeiroErro = Object.values(erros)[0]?.[0] ?? criar.error?.message;
    const muda = (chave: keyof typeof f) => (e: { target: { value: string } }) => porF({ ...f, [chave]: e.target.value });
    const submeter = (e: FormEvent) => { e.preventDefault(); criar.mutate(); };

    const campoAzul = cls(entrada, 'h-11 focus-visible:ring-blue-500');
    const campoVerde = cls(entrada, 'h-11 focus-visible:ring-emerald-500');

    return (
        <div
            className="min-h-screen bg-slate-100 px-4 py-10"
            style={{ backgroundImage: 'radial-gradient(at 20% 0%, #e0e7ff 0px, transparent 55%), radial-gradient(at 90% 10%, #dbeafe 0px, transparent 45%)' }}
        >
            <div className="mx-auto w-full max-w-3xl">
                <header className="animate-fade-in mb-8 text-center">
                    {logo ? (
                        <img src={logo} alt="" className="mx-auto mb-4 h-12" onError={(e) => { e.currentTarget.style.display = 'none'; }} />
                    ) : (
                        <span className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-600/25">
                            <i className="fas fa-rocket icon-float text-2xl text-white" aria-hidden="true" />
                        </span>
                    )}
                    <h1 className="text-3xl font-bold text-slate-900">{t('Bem-vindo ao SOSERP')}</h1>
                    <p className="mt-2 text-slate-500">{t('Falta um passo: criar a sua empresa e o utilizador administrador.')}</p>

                    <ol className="mt-6 flex items-center justify-center gap-2 text-xs font-semibold" aria-label={t('Passos')}>
                        <li className="flex items-center gap-2 text-emerald-600">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100"><i className="fas fa-check text-[10px]" aria-hidden="true" /></span>
                            {t('Licença')}
                        </li>
                        <li aria-hidden="true" className="h-px w-10 bg-slate-300" />
                        <li className="flex items-center gap-2 text-blue-600" aria-current="step">
                            <span className="flex h-6 w-6 animate-pulse items-center justify-center rounded-full bg-blue-600 text-white">2</span>
                            {t('Empresa')}
                        </li>
                        <li aria-hidden="true" className="h-px w-10 bg-slate-300" />
                        <li className="flex items-center gap-2 text-slate-400">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200">3</span>
                            {t('Entrar')}
                        </li>
                    </ol>
                </header>

                <form onSubmit={submeter} noValidate className="animate-scale-in overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                    {primeiroErro && (
                        <div role="alert" className="animate-fade-in border-b border-red-200 bg-red-50 px-8 py-4">
                            <p className="flex items-center text-sm font-semibold text-red-800"><i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />{primeiroErro}</p>
                        </div>
                    )}

                    <Seccao i={0} icone="fa-building" cor="bg-blue-50 text-blue-600" titulo={t('A sua empresa')} nota={t('Alguns dados vieram da licença — reveja e complete.')}>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Campo etiqueta={t('Nome da empresa')} obrigatorio erro={erros.empresa} className="md:col-span-2">
                                <input className={campoAzul} autoComplete="organization" value={f.empresa} onChange={muda('empresa')} />
                            </Campo>
                            <Campo etiqueta={t('NIF')} erro={erros.nif}>
                                <input className={campoAzul} value={f.nif} onChange={muda('nif')} />
                            </Campo>
                            <Campo etiqueta={t('Regime fiscal')} obrigatorio erro={erros.regime} ajuda={t('Decide os impostos com que a empresa nasce.')}>
                                <select className={campoAzul} value={f.regime} onChange={muda('regime')}>
                                    {regimes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Telefone')} erro={erros.telefone}>
                                <input type="tel" className={campoAzul} autoComplete="tel" value={f.telefone} onChange={muda('telefone')} />
                            </Campo>
                            <Campo etiqueta={t('Email da empresa')} erro={erros.email_empresa}>
                                <input type="email" className={campoAzul} value={f.email_empresa} onChange={muda('email_empresa')} />
                            </Campo>
                            <Campo etiqueta={t('Endereço')} erro={erros.endereco} className="md:col-span-2">
                                <input className={campoAzul} autoComplete="street-address" value={f.endereco} onChange={muda('endereco')} />
                            </Campo>
                        </div>

                        {plano && (
                            <p className="mt-4 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">
                                <i className="fas fa-certificate" aria-hidden="true" />{t('Plano da licença: :plano', { plano })}
                            </p>
                        )}
                    </Seccao>

                    <div className="border-t border-slate-100" />

                    <Seccao i={1} icone="fa-user-shield" cor="bg-emerald-50 text-emerald-600" titulo={t('Utilizador administrador')} nota={t('É com esta conta que vai entrar no sistema.')}>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.admin_nome} className="md:col-span-2">
                                <input className={campoVerde} autoComplete="name" value={f.admin_nome} onChange={muda('admin_nome')} />
                            </Campo>
                            <Campo etiqueta={t('Email (login)')} obrigatorio erro={erros.admin_email} className="md:col-span-2">
                                <input type="email" className={campoVerde} autoComplete="username" value={f.admin_email} onChange={muda('admin_email')} />
                            </Campo>
                            <Campo etiqueta={t('Password')} obrigatorio erro={erros.admin_password} ajuda={t('Mínimo 8 caracteres.')}>
                                <input type="password" className={campoVerde} autoComplete="new-password" value={f.admin_password} onChange={muda('admin_password')} />
                            </Campo>
                            <Campo etiqueta={t('Confirmar password')} obrigatorio erro={erros.admin_password_confirmation}>
                                <input type="password" className={campoVerde} autoComplete="new-password" value={f.admin_password_confirmation} onChange={muda('admin_password_confirmation')} />
                            </Campo>
                        </div>
                    </Seccao>

                    <footer className="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-8 py-5">
                        <p className="hidden text-xs text-slate-500 sm:block"><i className="fas fa-lock mr-1" aria-hidden="true" />{t('Os dados ficam nesta máquina.')}</p>
                        <button type="submit" disabled={criar.isPending}
                            className={cls('ml-auto rounded-xl bg-blue-600 px-7 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 hover:-translate-y-0.5 hover:bg-blue-700 disabled:opacity-50', TRANSICAO, FOCO)}>
                            {criar.isPending
                                ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A criar...')}</>
                                : <>{t('Criar empresa e entrar')} <i className="fas fa-arrow-right ml-1" aria-hidden="true" /></>}
                        </button>
                    </footer>
                </form>

                <p className="mt-6 text-center text-xs text-slate-400">SOSERP · Softec Angola</p>
            </div>
        </div>
    );
}

function Seccao({ i, icone, cor, titulo, nota, children }: { i: number; icone: string; cor: string; titulo: string; nota: string; children: ReactNode }) {
    return (
        <section className="entra px-8 pb-6 pt-8" style={cascata(i * 3)}>
            <div className="mb-5 flex items-center gap-3">
                <span className={cls('flex h-9 w-9 items-center justify-center rounded-xl', cor)}><i className={cls('fas icon-float', icone)} aria-hidden="true" /></span>
                <div>
                    <h2 className="font-bold text-slate-900">{titulo}</h2>
                    <p className="text-xs text-slate-500">{nota}</p>
                </div>
            </div>
            {children}
        </section>
    );
}
