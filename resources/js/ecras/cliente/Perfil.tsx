import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState, type FormEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { portal } from '@/api/portalDoCliente';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { cascata } from '@/ui/SemNada';
import { CARTAO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { Recado } from '../plataforma/comum';
import { Cabecalho } from './comum';

/** O PERFIL DO CLIENTE — os dados de contacto e a senha do portal. */
export default function Perfil() {
    const pedido = useQuery({ queryKey: ['portal', 'perfil'], queryFn: portal.perfil });
    const [f, porF] = useState({ name: '', email: '', phone: '' });
    const [s, porS] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });
    const [recado, porRecado] = useRecadoNoCanto(null);

    useEffect(() => { if (pedido.data) porF(pedido.data.perfil); }, [pedido.data]);

    const guardar = useMutation({ mutationFn: () => portal.guardarPerfil(f), onSuccess: (r) => porRecado(r.message) });
    const senha = useMutation({
        mutationFn: () => portal.mudarSenha(s),
        onSuccess: (r) => { porRecado(r.message); porS({ current_password: '', new_password: '', new_password_confirmation: '' }); },
    });

    if (pedido.isPending) return <Carregando linhas={5} />;

    const errosPerfil = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const errosSenha = senha.error instanceof ErroDaApi ? senha.error.erros : {};
    const submeter = (fn: () => void) => (e: FormEvent) => { e.preventDefault(); fn(); };

    return (
        <div>
            <Cabecalho titulo={t('Meu Perfil')} subtitulo={t('Gerencie suas informações pessoais')} icone="fa-circle-user" gradiente="from-purple-600 to-indigo-600" />

            <div className="mb-6"><Recado texto={recado} aoFechar={() => porRecado(null)} /></div>

            <div className="grid gap-6 lg:grid-cols-2">
                <form onSubmit={submeter(() => guardar.mutate())} className={cls(CARTAO, 'entra card-hover space-y-4 p-6')}>
                    <h2 className="text-xl font-bold text-gray-900"><i className="fas fa-id-card icon-float mr-2 text-purple-600" aria-hidden="true" />{t('Informações Pessoais')}</h2>
                    <AvisoDeErro erro={Object.keys(errosPerfil).length ? null : guardar.error} />
                    <Campo etiqueta={t('Nome')} obrigatorio erro={errosPerfil.name}>
                        <input className={entrada} autoComplete="name" value={f.name} onChange={(e) => porF({ ...f, name: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Email')} obrigatorio erro={errosPerfil.email}>
                        <input type="email" className={entrada} autoComplete="email" value={f.email} onChange={(e) => porF({ ...f, email: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Telefone')} erro={errosPerfil.phone}>
                        <input type="tel" className={entrada} autoComplete="tel" value={f.phone} onChange={(e) => porF({ ...f, phone: e.target.value })} />
                    </Campo>
                    <div className="flex justify-end">
                        <Botao type="submit" cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending}>{t('Salvar Alterações')}</Botao>
                    </div>
                </form>

                <form onSubmit={submeter(() => senha.mutate())} className={cls(CARTAO, 'entra card-hover space-y-4 p-6')} style={cascata(1)}>
                    <h2 className="text-xl font-bold text-gray-900"><i className="fas fa-lock icon-float mr-2 text-indigo-600" aria-hidden="true" />{t('Alterar Senha')}</h2>
                    <AvisoDeErro erro={Object.keys(errosSenha).length ? null : senha.error} />
                    <Campo etiqueta={t('Senha Atual')} obrigatorio erro={errosSenha.current_password}>
                        <input type="password" className={entrada} autoComplete="current-password" value={s.current_password} onChange={(e) => porS({ ...s, current_password: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Nova Senha')} obrigatorio erro={errosSenha.new_password} ajuda={t('Pelo menos 6 caracteres.')}>
                        <input type="password" className={entrada} autoComplete="new-password" value={s.new_password} onChange={(e) => porS({ ...s, new_password: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Confirmar Nova Senha')} obrigatorio erro={errosSenha.new_password_confirmation}>
                        <input type="password" className={entrada} autoComplete="new-password" value={s.new_password_confirmation} onChange={(e) => porS({ ...s, new_password_confirmation: e.target.value })} />
                    </Campo>
                    <div className="flex justify-end">
                        <Botao type="submit" cor="primaria" tom="solida" icone="fa-key" aTrabalhar={senha.isPending}>{t('Alterar Senha')}</Botao>
                    </div>
                </form>
            </div>
        </div>
    );
}
