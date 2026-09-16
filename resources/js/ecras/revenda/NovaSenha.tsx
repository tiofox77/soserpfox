import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda } from '@/api/revenda';
import { t } from '@/i18n';

import { BotaoGrande, MolduraDeEntrada } from './Entrada';
import { Campo, entrada } from './SejaRevendedor';

/** A NOVA SENHA do revendedor, pelo link do email (RV-07). */
export default function NovaSenha({ token = '', logo = null, nome = '' }: { token?: string; logo?: string | null; nome?: string }) {
    const [email, porEmail] = useState(() => new URLSearchParams(window.location.search).get('email') ?? '');
    const [senha, porSenha] = useState('');
    const [repetir, porRepetir] = useState('');

    const guardar = useMutation({
        mutationFn: () => revenda.novaSenha({ token, email, password: senha, password_confirmation: repetir }),
        onSuccess: (r) => window.setTimeout(() => window.location.assign(r.ir_para), 1200),
    });
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const submeter = (e: FormEvent) => { e.preventDefault(); guardar.mutate(); };

    return (
        <MolduraDeEntrada logo={logo} nome={nome} titulo={t('Nova senha')} subtitulo={t('Escolha a senha do portal do revendedor.')}>
            <form onSubmit={submeter} className="space-y-5">
                <Campo id="rvn-email" rotulo={t('Email')} erro={erros.email?.[0]} icone="fa-envelope">
                    <input id="rvn-email" type="email" required value={email} onChange={(e) => porEmail(e.target.value)} autoComplete="username" className={entrada(erros.email?.[0])} />
                </Campo>
                <Campo id="rvn-senha" rotulo={t('Nova senha')} erro={erros.password?.[0]} icone="fa-lock">
                    <input id="rvn-senha" type="password" required autoFocus value={senha} onChange={(e) => porSenha(e.target.value)} autoComplete="new-password" className={entrada(erros.password?.[0])} />
                </Campo>
                <Campo id="rvn-senha2" rotulo={t('Repetir a senha')} icone="fa-lock">
                    <input id="rvn-senha2" type="password" required value={repetir} onChange={(e) => porRepetir(e.target.value)} autoComplete="new-password" className={entrada()} />
                </Campo>
                <p className="text-xs text-gray-500">{t('Pelo menos 8 caracteres, com letras e números.')}</p>
                <BotaoGrande aTrabalhar={guardar.isPending || guardar.isSuccess} icone="fa-key">{t('Guardar a senha')}</BotaoGrande>
            </form>
        </MolduraDeEntrada>
    );
}
