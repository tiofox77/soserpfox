import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda } from '@/api/revenda';
import { t } from '@/i18n';

import { BotaoGrande, MolduraDeEntrada } from './Entrada';
import { Campo, entrada } from './SejaRevendedor';

/** A SENHA ESQUECIDA do revendedor: o link segue por email (RV-07). */
export default function EsqueciASenha({ logo = null, nome = '' }: { logo?: string | null; nome?: string }) {
    const [email, porEmail] = useState('');
    const pedir = useMutation({ mutationFn: () => revenda.esqueci(email), meta: { aviso: false } });
    const erros = pedir.error instanceof ErroDaApi ? pedir.error.erros : {};
    const submeter = (e: FormEvent) => { e.preventDefault(); pedir.mutate(); };

    return (
        <MolduraDeEntrada logo={logo} nome={nome} titulo={t('Esqueceu a senha?')} subtitulo={t('Enviamos um link para escolher outra.')}>
            {pedir.isSuccess ? (
                <div className="animate-fade-in text-center">
                    <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-2xl text-emerald-600"><i className="fas fa-envelope-circle-check" aria-hidden="true" /></span>
                    <p className="mt-4 text-gray-700">{pedir.data.message}</p>
                </div>
            ) : (
                <form onSubmit={submeter} className="space-y-5">
                    <Campo id="rvs-email" rotulo={t('Email')} erro={erros.email?.[0]} icone="fa-envelope">
                        <input id="rvs-email" type="email" required autoFocus value={email} onChange={(e) => porEmail(e.target.value)} className={entrada(erros.email?.[0])} />
                    </Campo>
                    {pedir.error && !erros.email && <p role="alert" className="text-sm text-red-700">{(pedir.error as Error).message}</p>}
                    <BotaoGrande aTrabalhar={pedir.isPending} icone="fa-paper-plane">{t('Enviar o link')}</BotaoGrande>
                </form>
            )}
            <p className="mt-6 text-center text-sm"><a href="/revendedor/entrar" className="font-semibold text-violet-700 hover:text-violet-900"><i className="fas fa-arrow-left mr-1.5" aria-hidden="true" />{t('Voltar à entrada')}</a></p>
        </MolduraDeEntrada>
    );
}
