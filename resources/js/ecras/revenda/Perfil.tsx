import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState, type FormEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda, type PerfilDoRevendedor } from '@/api/revenda';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, cls } from '@/ui/tokens';

import { Cabecalho, GRADIENTE_DO_PORTAL, LinkDeAfiliado } from './comum';
import { Campo, PROVINCIAS, entrada } from './SejaRevendedor';

/** O PERFIL DO REVENDEDOR: os dados, o IBAN para receber e a senha (RV-07). */
export default function Perfil() {
    const q = useQuery({ queryKey: ['revenda', 'perfil'], queryFn: revenda.perfil });
    const [d, porD] = useState<PerfilDoRevendedor | null>(null);
    const [senha, porSenha] = useState({ actual: '', nova: '', nova_confirmation: '' });

    useEffect(() => { if (q.data) porD(q.data.perfil); }, [q.data]);

    const guardar = useMutation({ mutationFn: () => revenda.guardarPerfil(d ?? {}) });
    const mudarSenha = useMutation({
        mutationFn: () => revenda.senha(senha),
        onSuccess: () => porSenha({ actual: '', nova: '', nova_confirmation: '' }),
    });

    if (q.isPending || !d) return <Carregando linhas={8} />;

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const errosSenha = mudarSenha.error instanceof ErroDaApi ? mudarSenha.error.erros : {};
    const campo = <K extends keyof PerfilDoRevendedor>(k: K, v: PerfilDoRevendedor[K]) => porD((a) => (a ? { ...a, [k]: v } : a));
    const texto = (k: keyof PerfilDoRevendedor) => (d[k] as string | null) ?? '';

    return (
        <div className="space-y-6">
            <Cabecalho titulo={t('O meu perfil')} subtitulo={d.email} icone="fa-user-gear" gradiente={GRADIENTE_DO_PORTAL} />

            <LinkDeAfiliado link={d.link} codigo={d.codigo} regra={d.regra} compacto />

            <form onSubmit={(e: FormEvent) => { e.preventDefault(); guardar.mutate(); }} className={cls(CARTAO, 'entra p-6')}>
                <h2 className="mb-4 text-lg font-bold text-gray-900"><i className="fas fa-id-card mr-2 text-violet-500" aria-hidden="true" />{t('Os seus dados')}</h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Campo id="pf-nome" rotulo={t('Nome completo')} obrigatorio erro={erros.name?.[0]} icone="fa-user">
                        <input id="pf-nome" value={texto('name')} onChange={(e) => campo('name', e.target.value)} className={entrada(erros.name?.[0])} />
                    </Campo>
                    <Campo id="pf-empresa" rotulo={t('Empresa')} erro={erros.company_name?.[0]} icone="fa-building">
                        <input id="pf-empresa" value={texto('company_name')} onChange={(e) => campo('company_name', e.target.value)} className={entrada(erros.company_name?.[0])} />
                    </Campo>
                    <Campo id="pf-nif" rotulo={t('NIF')} erro={erros.nif?.[0]} icone="fa-id-card">
                        <input id="pf-nif" value={texto('nif')} onChange={(e) => campo('nif', e.target.value)} className={entrada(erros.nif?.[0])} />
                    </Campo>
                    <Campo id="pf-email" rotulo={t('Email (a sua entrada)')} icone="fa-envelope">
                        <input id="pf-email" value={d.email} readOnly className={cls(entrada(), 'bg-slate-50 text-slate-500')} />
                    </Campo>
                    <Campo id="pf-tel" rotulo={t('Telefone / WhatsApp')} obrigatorio erro={erros.phone?.[0]} icone="fa-phone">
                        <input id="pf-tel" type="tel" value={texto('phone')} onChange={(e) => campo('phone', e.target.value)} className={entrada(erros.phone?.[0])} />
                    </Campo>
                    <Campo id="pf-site" rotulo={t('Site ou página')} erro={erros.website?.[0]} icone="fa-globe">
                        <input id="pf-site" type="url" value={texto('website')} onChange={(e) => campo('website', e.target.value)} className={entrada(erros.website?.[0])} />
                    </Campo>
                    <Campo id="pf-prov" rotulo={t('Província')} erro={erros.province?.[0]} icone="fa-map">
                        <select id="pf-prov" value={texto('province')} onChange={(e) => campo('province', e.target.value)} className={entrada(erros.province?.[0])}>
                            <option value="">{t('Escolher...')}</option>
                            {PROVINCIAS.map((p) => <option key={p} value={p}>{p}</option>)}
                        </select>
                    </Campo>
                    <Campo id="pf-cidade" rotulo={t('Cidade / município')} erro={erros.city?.[0]} icone="fa-location-dot">
                        <input id="pf-cidade" value={texto('city')} onChange={(e) => campo('city', e.target.value)} className={entrada(erros.city?.[0])} />
                    </Campo>
                </div>

                <h3 className="mb-3 mt-6 font-bold text-gray-900"><i className="fas fa-building-columns mr-2 text-emerald-500" aria-hidden="true" />{t('Onde recebe as comissões')}</h3>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo id="pf-banco" rotulo={t('Banco')} erro={erros.bank_name?.[0]} icone="fa-landmark">
                        <input id="pf-banco" value={texto('bank_name')} onChange={(e) => campo('bank_name', e.target.value)} className={entrada(erros.bank_name?.[0])} />
                    </Campo>
                    <Campo id="pf-iban" rotulo="IBAN" erro={erros.iban?.[0]} icone="fa-hashtag">
                        <input id="pf-iban" value={texto('iban')} onChange={(e) => campo('iban', e.target.value.toUpperCase())} placeholder="AO06 ..." className={cls(entrada(erros.iban?.[0]), 'font-mono')} />
                    </Campo>
                </div>

                <div className="mt-6 flex justify-end">
                    <Botao type="submit" cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}>{t('Guardar')}</Botao>
                </div>
            </form>

            <form onSubmit={(e: FormEvent) => { e.preventDefault(); mudarSenha.mutate(); }} className={cls(CARTAO, 'entra p-6')}>
                <h2 className="mb-4 text-lg font-bold text-gray-900"><i className="fas fa-key mr-2 text-amber-500" aria-hidden="true" />{t('Mudar a senha')}</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo id="pf-actual" rotulo={t('Senha actual')} erro={errosSenha.actual?.[0]} icone="fa-lock">
                        <input id="pf-actual" type="password" autoComplete="current-password" value={senha.actual} onChange={(e) => porSenha((s) => ({ ...s, actual: e.target.value }))} className={entrada(errosSenha.actual?.[0])} />
                    </Campo>
                    <Campo id="pf-nova" rotulo={t('Senha nova')} erro={errosSenha.nova?.[0]} icone="fa-lock">
                        <input id="pf-nova" type="password" autoComplete="new-password" value={senha.nova} onChange={(e) => porSenha((s) => ({ ...s, nova: e.target.value }))} className={entrada(errosSenha.nova?.[0])} />
                    </Campo>
                    <Campo id="pf-nova2" rotulo={t('Repetir a senha nova')} icone="fa-lock">
                        <input id="pf-nova2" type="password" autoComplete="new-password" value={senha.nova_confirmation} onChange={(e) => porSenha((s) => ({ ...s, nova_confirmation: e.target.value }))} className={entrada()} />
                    </Campo>
                </div>
                <div className="mt-6 flex justify-end">
                    <Botao type="submit" cor="aviso" tom="solida" icone="fa-key" aTrabalhar={mudarSenha.isPending}>{t('Mudar a senha')}</Botao>
                </div>
            </form>
        </div>
    );
}
