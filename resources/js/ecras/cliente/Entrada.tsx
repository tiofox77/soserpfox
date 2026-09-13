import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { portal } from '@/api/portalDoCliente';
import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

/**
 * A ENTRADA NO PORTAL DO CLIENTE.
 *
 * Um formulário de verdade (Enter envia, o browser oferece guardar a senha).
 * A página seguinte é pedida por navegação completa: a sessão muda de dono ao
 * entrar, e com ela o token.
 */
export default function Entrada({ logo = null, nome = '' }: { logo?: string | null; nome?: string }) {
    const [email, porEmail] = useState('');
    const [senha, porSenha] = useState('');
    const [lembrar, porLembrar] = useState(false);

    const entrar = useMutation({
        mutationFn: () => portal.entrar({ email, password: senha, remember: lembrar }),
        onSuccess: (r) => window.location.assign(r.ir_para),
    });

    const erros = entrar.error instanceof ErroDaApi ? entrar.error.erros : {};
    const submeter = (e: FormEvent) => { e.preventDefault(); entrar.mutate(); };

    return (
        <div className="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
            <div className="animate-scale-in w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mb-4 flex justify-center">
                        {logo ? (
                            <img src={logo} alt={nome} className="h-20 w-auto object-contain" />
                        ) : (
                            <span className="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 shadow-lg">
                                <i className="fas fa-users icon-float text-3xl text-white" aria-hidden="true" />
                            </span>
                        )}
                    </div>
                    <h1 className="mb-2 text-3xl font-bold text-gray-900">{t('Portal do Cliente')}</h1>
                    <p className="text-gray-600">{t('Acesse suas faturas e eventos')}</p>
                </div>

                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-xl">
                    <form onSubmit={submeter} className="space-y-6" noValidate>
                        <label className="block">
                            <span className="mb-2 block text-sm font-semibold text-gray-700"><i className="fas fa-envelope mr-2 text-blue-500" aria-hidden="true" />{t('Email')}</span>
                            <input type="email" name="email" autoComplete="email" required autoFocus value={email} onChange={(e) => porEmail(e.target.value)}
                                placeholder="seu@email.com"
                                className={cls('w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-blue-500', TRANSICAO)} />
                            {erros.email?.[0] && <span role="alert" className="mt-2 block text-sm text-red-600"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{erros.email[0]}</span>}
                        </label>

                        <label className="block">
                            <span className="mb-2 block text-sm font-semibold text-gray-700"><i className="fas fa-lock mr-2 text-purple-500" aria-hidden="true" />{t('Senha')}</span>
                            <input type="password" name="password" autoComplete="current-password" required value={senha} onChange={(e) => porSenha(e.target.value)}
                                placeholder="••••••••"
                                className={cls('w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-transparent focus:ring-2 focus:ring-purple-500', TRANSICAO)} />
                            {erros.password?.[0] && <span role="alert" className="mt-2 block text-sm text-red-600"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{erros.password[0]}</span>}
                        </label>

                        {entrar.error && !Object.keys(erros).length && (
                            <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{entrar.error.message}</p>
                        )}

                        <div className="flex items-center justify-between">
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={lembrar} onChange={(e) => porLembrar(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                {t('Lembrar-me')}
                            </label>
                            <a href="/client/forgot-password" className="text-sm font-medium text-blue-600 hover:text-blue-800">{t('Esqueceu a senha?')}</a>
                        </div>

                        <button type="submit" disabled={entrar.isPending}
                            className={cls('w-full rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-3 font-semibold text-white hover:scale-[1.02] hover:from-blue-700 hover:to-purple-700 disabled:cursor-not-allowed disabled:opacity-50', TRANSICAO, FOCO)}>
                            <i className={cls('fas mr-2', entrar.isPending ? 'fa-spinner fa-spin' : 'fa-right-to-bracket')} aria-hidden="true" />
                            {entrar.isPending ? t('Entrando...') : t('Entrar no Portal')}
                        </button>
                    </form>

                    <div className="mt-6 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
                        {t('Ainda não tem acesso?')}
                        <span className="block text-xs text-gray-500">{t('Entre em contato com nossa equipe')}</span>
                    </div>
                </div>

                <div className="mt-6 text-center">
                    <a href="/login" className="text-sm font-medium text-gray-600 hover:text-gray-900"><i className="fas fa-arrow-left mr-2" aria-hidden="true" />{t('Voltar para login de usuários')}</a>
                </div>
            </div>
        </div>
    );
}
