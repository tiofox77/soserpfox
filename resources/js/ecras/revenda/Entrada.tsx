import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent, type ReactNode } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda } from '@/api/revenda';
import { t } from '@/i18n';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

import { Campo, entrada } from './SejaRevendedor';

/**
 * A ENTRADA NO PORTAL DO REVENDEDOR (RV-07).
 *
 * Um formulário de verdade (Enter envia, o browser oferece guardar a senha). A
 * página seguinte abre por navegação completa: a sessão muda de dono ao entrar.
 */
export function MolduraDeEntrada({ logo, nome, titulo, subtitulo, children }: { logo?: string | null; nome?: string; titulo: string; subtitulo: string; children: ReactNode }) {
    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-violet-50 via-white to-emerald-50 px-4 py-12">
            <span aria-hidden="true" className="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-violet-200/40 blur-3xl" />
            <span aria-hidden="true" className="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-emerald-200/40 blur-3xl" />
            <div className="animate-scale-in relative w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mb-4 flex justify-center">
                        {logo ? (
                            <img src={logo} alt={nome} className="h-16 w-auto object-contain" />
                        ) : (
                            <span className="grid h-20 w-20 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-emerald-600 shadow-lg">
                                <i className="fas fa-handshake icon-float text-3xl text-white" aria-hidden="true" />
                            </span>
                        )}
                    </div>
                    <h1 className="mb-1 text-3xl font-black text-gray-900">{titulo}</h1>
                    <p className="text-gray-600">{subtitulo}</p>
                </div>
                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-xl">{children}</div>
            </div>
        </div>
    );
}

export function BotaoGrande({ aTrabalhar, icone, children }: { aTrabalhar: boolean; icone: string; children: ReactNode }) {
    return (
        <button type="submit" disabled={aTrabalhar}
            className={cls('flex w-full items-center justify-center gap-2 bg-gradient-to-r from-violet-600 to-emerald-600 px-4 py-3 font-bold text-white shadow-lg hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60', RAIO, TRANSICAO, FOCO)}>
            <i className={cls('fas', aTrabalhar ? 'fa-spinner fa-spin' : icone)} aria-hidden="true" />{children}
        </button>
    );
}

export default function Entrada({ logo = null, nome = '' }: { logo?: string | null; nome?: string }) {
    const [email, porEmail] = useState('');
    const [senha, porSenha] = useState('');
    const [lembrar, porLembrar] = useState(false);

    const entrar = useMutation({
        mutationFn: () => revenda.entrar({ email, password: senha, remember: lembrar }),
        onSuccess: (r) => window.location.assign(r.ir_para),
        meta: { aviso: false },
    });

    const erros = entrar.error instanceof ErroDaApi ? entrar.error.erros : {};
    const submeter = (e: FormEvent) => { e.preventDefault(); entrar.mutate(); };

    return (
        <MolduraDeEntrada logo={logo} nome={nome} titulo={t('Portal do Revendedor')} subtitulo={t('As suas empresas, as subscrições e as comissões')}>
            <form onSubmit={submeter} className="space-y-5">
                {entrar.error && !erros.email && (
                    <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{(entrar.error as Error).message}</p>
                )}
                <Campo id="rve-email" rotulo={t('Email')} erro={erros.email?.[0]} icone="fa-envelope">
                    <input id="rve-email" type="email" required autoFocus autoComplete="username" value={email} onChange={(e) => porEmail(e.target.value)} className={entrada(erros.email?.[0])} />
                </Campo>
                <Campo id="rve-senha" rotulo={t('Senha')} erro={erros.password?.[0]} icone="fa-lock">
                    <input id="rve-senha" type="password" required autoComplete="current-password" value={senha} onChange={(e) => porSenha(e.target.value)} className={entrada(erros.password?.[0])} />
                </Campo>
                <div className="flex items-center justify-between gap-3 text-sm">
                    <label className="flex items-center gap-2 text-gray-700">
                        <input type="checkbox" checked={lembrar} onChange={(e) => porLembrar(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-violet-600" />
                        {t('Lembrar-me')}
                    </label>
                    <a href="/revendedor/esqueci-a-senha" className="font-semibold text-violet-700 hover:text-violet-900">{t('Esqueceu a senha?')}</a>
                </div>
                <BotaoGrande aTrabalhar={entrar.isPending} icone="fa-right-to-bracket">{t('Entrar')}</BotaoGrande>
            </form>
            <p className="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
                {t('Ainda não é revendedor?')} <a href="/revendedores#pedido" className="font-semibold text-violet-700 hover:text-violet-900">{t('Faça o pedido')}</a>
            </p>
        </MolduraDeEntrada>
    );
}
