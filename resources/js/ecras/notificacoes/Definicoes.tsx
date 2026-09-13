import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    notificacoes as api,
    type CanalDeEmail, type CanalDeSms, type CanalDeWhatsApp,
    type DefinicoesDeNotificacao, type TipoDeAviso,
} from '@/api/notificacoes';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * AS DEFINIÇÕES DE NOTIFICAÇÃO: e-mail, SMS e WhatsApp.
 *
 * OS SEGREDOS NÃO SAEM DO SERVIDOR. A senha do SMTP e os tokens das operadoras
 * abrem VAZIOS de propósito: um `type="password"` esconde os caracteres no ecrã,
 * não no código-fonte da página. O que o ecrã sabe é que existe um guardado — e
 * deixar o campo em branco quer dizer «mantém o que lá está», nunca «apaga».
 *
 * VER NÃO É CONFIGURAR: quem só pode ver não leva botões que o servidor recusa.
 */

type Estado = {
    email: CanalDeEmail & { smtp_password: string };
    sms: CanalDeSms & { auth_token: string; api_token: string };
    whatsapp: CanalDeWhatsApp & { auth_token: string };
};

export default function DefinicoesDeNotificacao() {
    const cache = useQueryClient();

    const [aba, porAba] = useState('email');
    const [estado, porEstado] = useState<Estado | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const ficha = useQuery({ queryKey: ['notificacoes', 'definicoes'], queryFn: () => api.definicoes.ler() });

    // A ficha só se copia para o formulário quando chega — e não a cada
    // desenho, senão o que se está a escrever era apagado por baixo da mão.
    useEffect(() => {
        if (ficha.data && estado === null) {
            porEstado({
                email: { ...ficha.data.data.email, smtp_password: '' },
                sms: { ...ficha.data.data.sms, auth_token: '', api_token: '' },
                whatsapp: { ...ficha.data.data.whatsapp, auth_token: '' },
            });
        }
    }, [ficha.data, estado]);

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        porEstado(null);
        void cache.invalidateQueries({ queryKey: ['notificacoes'] });
    };

    const guardar = useMutation({
        mutationFn: () => api.definicoes.guardar(estado as unknown as Record<string, unknown>),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const testarEmail = useMutation({
        mutationFn: () => api.definicoes.testarEmail({
            smtp_host: estado?.email.smtp_host,
            smtp_port: estado?.email.smtp_port,
            smtp_username: estado?.email.smtp_username,
            smtp_password: estado?.email.smtp_password,
            smtp_encryption: estado?.email.smtp_encryption,
            from_email: estado?.email.from_email,
            from_name: estado?.email.from_name,
        }),
        onSuccess: (r) => { porErro(null); porRecado(r.message); },
        onError: porErro,
    });

    const testarSms = useMutation({
        mutationFn: () => api.definicoes.testarSms({
            provider: estado?.sms.provider,
            api_token: estado?.sms.api_token,
            auth_token: estado?.sms.auth_token,
            account_sid: estado?.sms.account_sid,
            sender_id: estado?.sms.sender_id,
        }),
        onSuccess: (r) => { porErro(null); porRecado(r.message); },
        onError: porErro,
    });

    const buscarModelos = useMutation({
        mutationFn: () => api.definicoes.modelosDeWhatsApp({
            account_sid: estado?.whatsapp.account_sid,
            auth_token: estado?.whatsapp.auth_token,
            from_number: estado?.whatsapp.from_number,
        }),
        onSuccess: (r) => {
            porErro(null);
            porRecado(r.message);

            if (estado) {
                porEstado({ ...estado, whatsapp: { ...estado.whatsapp, templates: r.data } });
            }
        },
        onError: porErro,
    });

    if (ficha.isPending || estado === null) return <Carregando linhas={10} />;
    if (ficha.isError) return <AvisoDeErro erro={ficha.error} />;

    const f: DefinicoesDeNotificacao = ficha.data;
    const pode = f.permissoes.configurar;
    const podeTestar = f.permissoes.testar;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const ligados = [estado.email.enabled, estado.sms.enabled, estado.whatsapp.enabled].filter(Boolean).length;

    const abas = [
        { chave: 'email', rotulo: t('E-mail'), icone: 'fa-envelope' },
        { chave: 'sms', rotulo: t('SMS'), icone: 'fa-comment-sms' },
        { chave: 'whatsapp', rotulo: t('WhatsApp'), icone: 'fa-brands fa-whatsapp' },
    ];

    const mudarEmail = (m: Partial<Estado['email']>) => porEstado({ ...estado, email: { ...estado.email, ...m } });
    const mudarSms = (m: Partial<Estado['sms']>) => porEstado({ ...estado, sms: { ...estado.sms, ...m } });
    const mudarWa = (m: Partial<Estado['whatsapp']>) => porEstado({ ...estado, whatsapp: { ...estado.whatsapp, ...m } });

    const operadoraDoSms = f.operadoras.sms.find((o) => o.valor === estado.sms.provider);

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Notificações')}
                subtitulo={t('Por onde a empresa avisa: e-mail, SMS e WhatsApp')}
                icone="fa-bell"
                cor="ciano"
                accoes={
                    <>
                        <a href="/notifications/templates" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-file-lines" aria-hidden="true" />
                            {t('Modelos de Notificação')}
                        </a>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => guardar.mutate()} disabled={guardar.isPending}>
                                <i className="fas fa-floppy-disk" aria-hidden="true" />
                                {t('Guardar')}
                            </button>
                        )}
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-tower-broadcast">
                        {t(':n de 3 canais ligados', { n: numero(ligados) })}
                    </EstadoNaFaixa>
                    {!pode && <EstadoNaFaixa icone="fa-eye">{t('Só de leitura')}</EstadoNaFaixa>}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero
                    rotulo={t('E-mail')} valor={estado.email.enabled ? t('Ligado') : t('Desligado')}
                    icone="fa-envelope" tom={estado.email.enabled ? 'verde' : 'cinza'}
                    nota={estado.email.smtp_host ?? t('Sem servidor de saída')}
                    aoCarregar={() => porAba('email')}
                />
                <CartaoNumero
                    rotulo={t('SMS')} valor={estado.sms.enabled ? t('Ligado') : t('Desligado')}
                    icone="fa-comment-sms" tom={estado.sms.enabled ? 'azul' : 'cinza'}
                    nota={operadoraDoSms?.rotulo ?? t('Sem operadora')}
                    aoCarregar={() => porAba('sms')}
                />
                <CartaoNumero
                    rotulo={t('WhatsApp')} valor={estado.whatsapp.enabled ? t('Ligado') : t('Desligado')}
                    icone="fa-comment-dots" tom={estado.whatsapp.enabled ? 'teal' : 'cinza'}
                    nota={estado.whatsapp.sandbox ? t('Em ambiente de ensaio') : t('Em produção')}
                    aoCarregar={() => porAba('whatsapp')}
                />
            </div>

            <Separadores abas={abas} activa={aba} aoMudar={porAba} />

            {/* ─── E-mail ────────────────────────────────────────────── */}

            <PainelDoSeparador chave="email" activa={aba}>
                <div className="space-y-4">
                    <Interruptor
                        ligado={estado.email.enabled}
                        podeEditar={pode}
                        rotulo={t('Avisar por e-mail')}
                        nota={t('Sem isto, nenhum aviso de e-mail sai desta empresa.')}
                        aoMudar={(v) => mudarEmail({ enabled: v })}
                    />

                    <div className={cls('p-5', CARTAO)}>
                        <h3 className="mb-4 text-sm font-bold text-slate-800">
                            <i className="fas fa-server mr-2 text-cyan-500" aria-hidden="true" />
                            {t('Servidor de saída (SMTP)')}
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Servidor')} obrigatorio={estado.email.enabled}
                                erro={erros['email.smtp_host']}>
                                <input type="text" value={estado.email.smtp_host ?? ''} disabled={!pode}
                                    onChange={(e) => mudarEmail({ smtp_host: e.target.value })}
                                    placeholder="smtp.exemplo.ao" className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Porta')} obrigatorio={estado.email.enabled}
                                erro={erros['email.smtp_port']} ajuda={t('Normalmente 587 (TLS) ou 465 (SSL).')}>
                                <input type="number" value={estado.email.smtp_port ?? ''} disabled={!pode}
                                    onChange={(e) => mudarEmail({ smtp_port: e.target.value ? Number(e.target.value) : null })}
                                    className={cls(entrada, 'tabular-nums')} />
                            </Campo>

                            <Campo etiqueta={t('Utilizador')} erro={erros['email.smtp_username']}>
                                <input type="text" value={estado.email.smtp_username ?? ''} disabled={!pode}
                                    autoComplete="off" onChange={(e) => mudarEmail({ smtp_username: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Segredo
                                etiqueta={t('Senha')}
                                guardado={Boolean(f.segredos.smtp_password)}
                                valor={estado.email.smtp_password}
                                podeEditar={pode}
                                erro={erros['email.smtp_password']}
                                aoMudar={(v) => mudarEmail({ smtp_password: v })}
                            />

                            <Campo etiqueta={t('Encriptação')} erro={erros['email.smtp_encryption']}>
                                <select value={estado.email.smtp_encryption ?? ''} disabled={!pode}
                                    onChange={(e) => mudarEmail({ smtp_encryption: e.target.value })}
                                    className={entrada}>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">{t('Nenhuma')}</option>
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Endereço remetente')} obrigatorio={estado.email.enabled}
                                erro={erros['email.from_email']}>
                                <input type="email" value={estado.email.from_email ?? ''} disabled={!pode}
                                    onChange={(e) => mudarEmail({ from_email: e.target.value })} className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Nome do remetente')} erro={erros['email.from_name']}
                                className="sm:col-span-2">
                                <input type="text" value={estado.email.from_name ?? ''} disabled={!pode}
                                    onChange={(e) => mudarEmail({ from_name: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        {podeTestar && (
                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                {/* UM TESTE MANDA UM E-MAIL A SÉRIO. Uma ligação
                                    que abre não prova nada: o que faz falta
                                    saber é se a mensagem chega à caixa. */}
                                <Botao icone="fa-paper-plane" aTrabalhar={testarEmail.isPending}
                                    onClick={() => testarEmail.mutate()}>
                                    {t('Enviar e-mail de teste')}
                                </Botao>
                                <p className="text-xs text-slate-500">
                                    {t('Vai para o próprio endereço remetente.')}
                                </p>
                            </div>
                        )}
                    </div>

                    <Avisos
                        titulo={t('Que avisos saem por e-mail')}
                        tipos={f.tipos}
                        ligados={estado.email.notifications}
                        modelos={f.modelos.filter((m) => m.email)}
                        escolhidos={estado.email.notification_templates}
                        podeEditar={pode}
                        aoMudar={(notifications, notification_templates) =>
                            mudarEmail({ notifications, notification_templates })}
                    />
                </div>
            </PainelDoSeparador>

            {/* ─── SMS ───────────────────────────────────────────────── */}

            <PainelDoSeparador chave="sms" activa={aba}>
                <div className="space-y-4">
                    <Interruptor
                        ligado={estado.sms.enabled}
                        podeEditar={pode}
                        rotulo={t('Avisar por SMS')}
                        nota={t('O SMS chega a quem não tem internet — e é o que custa dinheiro por mensagem.')}
                        aoMudar={(v) => mudarSms({ enabled: v })}
                    />

                    <div className={cls('p-5', CARTAO)}>
                        <h3 className="mb-4 text-sm font-bold text-slate-800">
                            <i className="fas fa-tower-cell mr-2 text-blue-500" aria-hidden="true" />
                            {t('Operadora')}
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Operadora')} obrigatorio={estado.sms.enabled}
                                erro={erros['sms.provider']}>
                                <select value={estado.sms.provider} disabled={!pode}
                                    onChange={(e) => mudarSms({ provider: e.target.value })} className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {f.operadoras.sms.map((o) => (
                                        <option key={o.valor} value={o.valor}>{o.rotulo}</option>
                                    ))}
                                </select>
                            </Campo>

                            {estado.sms.provider === 'twilio' ? (
                                <>
                                    <Campo etiqueta={t('SID da conta')} erro={erros['sms.account_sid']}>
                                        <input type="text" value={estado.sms.account_sid ?? ''} disabled={!pode}
                                            autoComplete="off"
                                            onChange={(e) => mudarSms({ account_sid: e.target.value })}
                                            className={entrada} />
                                    </Campo>
                                    <Segredo
                                        etiqueta={t('Token de autenticação')}
                                        guardado={Boolean(f.segredos.sms_auth_token)}
                                        valor={estado.sms.auth_token}
                                        podeEditar={pode}
                                        erro={erros['sms.auth_token']}
                                        aoMudar={(v) => mudarSms({ auth_token: v })}
                                    />
                                </>
                            ) : estado.sms.provider ? (
                                <Segredo
                                    etiqueta={t('Chave da aplicação / API')}
                                    guardado={Boolean(f.segredos.sms_api_token)}
                                    valor={estado.sms.api_token}
                                    podeEditar={pode}
                                    erro={erros['sms.api_token']}
                                    ajuda={t('Sem chave, o canal fica activo e não sai SMS nenhum.')}
                                    aoMudar={(v) => mudarSms({ api_token: v })}
                                />
                            ) : null}

                            <Campo etiqueta={t('Número de origem')} erro={erros['sms.from_number']}>
                                <input type="text" value={estado.sms.from_number ?? ''} disabled={!pode}
                                    onChange={(e) => mudarSms({ from_number: e.target.value })} className={entrada} />
                            </Campo>

                            <Campo
                                etiqueta={t('Remetente')}
                                erro={erros['sms.sender_id']}
                                // A TelcoSMS não deixa escolher: é sempre o
                                // remetente registado, e o servidor repõe-no.
                                ajuda={estado.sms.provider === 'telcosms'
                                    ? t('A TelcoSMS usa sempre o remetente registado.')
                                    : t('O nome que aparece no telemóvel de quem recebe.')}
                            >
                                <input type="text" value={estado.sms.sender_id ?? ''}
                                    disabled={!pode || estado.sms.provider === 'telcosms'}
                                    onChange={(e) => mudarSms({ sender_id: e.target.value })}
                                    className={cls(entrada, 'disabled:bg-slate-50 disabled:text-slate-400')} />
                            </Campo>
                        </div>

                        {podeTestar && estado.sms.provider && (
                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <Botao icone="fa-plug-circle-check" aTrabalhar={testarSms.isPending}
                                    onClick={() => testarSms.mutate()}>
                                    {t('Testar a operadora')}
                                </Botao>
                                <p className="text-xs text-slate-500">
                                    {t('Pergunta o saldo — não gasta uma mensagem.')}
                                </p>
                            </div>
                        )}
                    </div>

                    <Avisos
                        titulo={t('Que avisos saem por SMS')}
                        tipos={f.tipos}
                        ligados={estado.sms.notifications}
                        modelos={f.modelos.filter((m) => m.sms)}
                        escolhidos={estado.sms.notification_templates}
                        podeEditar={pode}
                        aoMudar={(notifications, notification_templates) =>
                            mudarSms({ notifications, notification_templates })}
                    />
                </div>
            </PainelDoSeparador>

            {/* ─── WhatsApp ──────────────────────────────────────────── */}

            <PainelDoSeparador chave="whatsapp" activa={aba}>
                <div className="space-y-4">
                    <Interruptor
                        ligado={estado.whatsapp.enabled}
                        podeEditar={pode}
                        rotulo={t('Avisar por WhatsApp')}
                        nota={t('O WhatsApp só manda MODELOS APROVADOS pelo fornecedor — texto livre é recusado.')}
                        aoMudar={(v) => mudarWa({ enabled: v })}
                    />

                    <div className={cls('p-5', CARTAO)}>
                        <h3 className="mb-4 text-sm font-bold text-slate-800">
                            <i className="fas fa-comment-dots mr-2 text-teal-500" aria-hidden="true" />
                            {t('Fornecedor')}
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Fornecedor')} obrigatorio={estado.whatsapp.enabled}
                                erro={erros['whatsapp.provider']}>
                                <select value={estado.whatsapp.provider} disabled={!pode}
                                    onChange={(e) => mudarWa({ provider: e.target.value })} className={entrada}>
                                    {f.operadoras.whatsapp.map((o) => (
                                        <option key={o.valor} value={o.valor}>{o.rotulo}</option>
                                    ))}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('SID da conta')} erro={erros['whatsapp.account_sid']}>
                                <input type="text" value={estado.whatsapp.account_sid ?? ''} disabled={!pode}
                                    autoComplete="off"
                                    onChange={(e) => mudarWa({ account_sid: e.target.value })} className={entrada} />
                            </Campo>

                            <Segredo
                                etiqueta={t('Token de autenticação')}
                                guardado={Boolean(f.segredos.whatsapp_auth_token)}
                                valor={estado.whatsapp.auth_token}
                                podeEditar={pode}
                                erro={erros['whatsapp.auth_token']}
                                aoMudar={(v) => mudarWa({ auth_token: v })}
                            />

                            <Campo etiqueta={t('Número de origem')} obrigatorio={estado.whatsapp.enabled}
                                erro={erros['whatsapp.from_number']}>
                                <input type="text" value={estado.whatsapp.from_number ?? ''} disabled={!pode}
                                    onChange={(e) => mudarWa({ from_number: e.target.value })}
                                    placeholder="+244…" className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Conta de negócio')} erro={erros['whatsapp.business_account_id']}>
                                <input type="text" value={estado.whatsapp.business_account_id ?? ''} disabled={!pode}
                                    onChange={(e) => mudarWa({ business_account_id: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <label className="mt-4 flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" checked={estado.whatsapp.sandbox} disabled={!pode}
                                onChange={(e) => mudarWa({ sandbox: e.target.checked })}
                                className="h-5 w-5 rounded text-teal-600" />
                            {t('Ambiente de ensaio (sandbox)')}
                            <span className="text-xs text-slate-400">
                                {t('— só chega a números aprovados no fornecedor.')}
                            </span>
                        </label>

                        {podeTestar && (
                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <Botao icone="fa-cloud-arrow-down" aTrabalhar={buscarModelos.isPending}
                                    onClick={() => buscarModelos.mutate()}>
                                    {t('Buscar os modelos aprovados')}
                                </Botao>
                                {estado.whatsapp.templates.length > 0 && (
                                    <Etiqueta cor="bom" icone="fa-file-lines">
                                        {t(':n modelos', { n: numero(estado.whatsapp.templates.length) })}
                                    </Etiqueta>
                                )}
                            </div>
                        )}

                        {estado.whatsapp.templates.length > 0 && (
                            <ul className="mt-3 space-y-1.5">
                                {estado.whatsapp.templates.map((m) => (
                                    <li key={m.sid}
                                        className={cls('flex items-center gap-2 border border-slate-200 px-3 py-2 text-xs', RAIO)}>
                                        <i className="fas fa-file-lines text-teal-500" aria-hidden="true" />
                                        <span className="font-semibold text-slate-700">{m.name ?? m.sid}</span>
                                        <code className="ml-auto text-[11px] text-slate-400">{m.sid}</code>
                                        {pode && (
                                            <Botao altura="pequeno" cor="perigo" icone="fa-xmark"
                                                onClick={() => mudarWa({
                                                    templates: estado.whatsapp.templates.filter((x) => x.sid !== m.sid),
                                                })}
                                                aria-label={t('Tirar o modelo')} />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <Avisos
                        titulo={t('Que avisos saem por WhatsApp')}
                        tipos={f.tipos}
                        ligados={estado.whatsapp.notifications}
                        modelos={f.modelos.filter((m) => m.whatsapp)}
                        escolhidos={estado.whatsapp.notification_templates}
                        podeEditar={pode}
                        aoMudar={(notifications, notification_templates) =>
                            mudarWa({ notifications, notification_templates })}
                    />
                </div>
            </PainelDoSeparador>

            {pode && (
                <div className="flex items-center gap-3 pb-2">
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk"
                        aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                        {t('Guardar definições')}
                    </Botao>
                    <p className="text-xs text-slate-500">
                        {t('Um campo de segredo em branco mantém o que já está guardado.')}
                    </p>
                </div>
            )}
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

function Interruptor({ ligado, podeEditar, rotulo, nota, aoMudar }: {
    ligado: boolean;
    podeEditar: boolean;
    rotulo: string;
    nota: string;
    aoMudar: (v: boolean) => void;
}) {
    return (
        <label className={cls(
            'flex cursor-pointer items-center gap-3 border-2 p-4 transition',
            RAIO,
            ligado ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-white',
            !podeEditar && 'cursor-default',
        )}>
            <input type="checkbox" checked={ligado} disabled={!podeEditar}
                onChange={(e) => aoMudar(e.target.checked)}
                className="h-5 w-5 rounded text-emerald-600" />
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold text-slate-800">{rotulo}</span>
                <span className="block text-xs text-slate-500">{nota}</span>
            </span>
            <Etiqueta cor={ligado ? 'bom' : 'neutra'} ponto>
                {ligado ? t('Ligado') : t('Desligado')}
            </Etiqueta>
        </label>
    );
}

/**
 * UM CAMPO DE SEGREDO.
 *
 * Abre vazio, diga o que disser a base. O que o ecrã mostra é se há um guardado
 * — e deixá-lo em branco ao gravar quer dizer «mantém», nunca «apaga».
 */
function Segredo({ etiqueta, guardado, valor, podeEditar, erro, ajuda, aoMudar }: {
    etiqueta: string;
    guardado: boolean;
    valor: string;
    podeEditar: boolean;
    erro?: string[];
    ajuda?: string;
    aoMudar: (v: string) => void;
}) {
    return (
        <Campo
            etiqueta={etiqueta}
            erro={erro}
            ajuda={ajuda ?? (guardado
                ? t('Já há um guardado. Deixe em branco para o manter.')
                : t('Ainda não há nenhum guardado.'))}
        >
            <div className="relative">
                <input type="password" value={valor} disabled={!podeEditar} autoComplete="new-password"
                    onChange={(e) => aoMudar(e.target.value)}
                    placeholder={guardado ? '••••••••' : ''}
                    className={cls(entrada, 'pr-24')} />
                <span className="absolute right-2 top-1/2 -translate-y-1/2">
                    <Etiqueta cor={guardado ? 'bom' : 'neutra'} icone={guardado ? 'fa-lock' : 'fa-lock-open'}>
                        {guardado ? t('Configurado') : t('Por configurar')}
                    </Etiqueta>
                </span>
            </div>
        </Campo>
    );
}

/**
 * QUE AVISOS SAEM POR ESTE CANAL — e com que modelo.
 *
 * Os avisos vêm agrupados por área: uma lista corrida de doze interruptores não
 * se lê, e ninguém sabia quais eram de RH e quais eram de eventos.
 */
function Avisos({ titulo, tipos, ligados, modelos, escolhidos, podeEditar, aoMudar }: {
    titulo: string;
    tipos: TipoDeAviso[];
    ligados: Record<string, boolean>;
    modelos: Array<{ id: number; nome: string }>;
    escolhidos: Record<string, number | string>;
    podeEditar: boolean;
    aoMudar: (ligados: Record<string, boolean>, modelos: Record<string, number | string>) => void;
}) {
    const grupos = tipos.reduce<Record<string, TipoDeAviso[]>>((acc, x) => {
        (acc[x.grupo] ??= []).push(x);

        return acc;
    }, {});

    return (
        <div className={cls('p-5', CARTAO)}>
            <h3 className="mb-4 text-sm font-bold text-slate-800">
                <i className="fas fa-list-check mr-2 text-indigo-500" aria-hidden="true" />
                {titulo}
            </h3>

            <div className="space-y-4">
                {Object.entries(grupos).map(([grupo, doGrupo]) => (
                    <div key={grupo}>
                        <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{grupo}</p>
                        <ul className="space-y-1.5">
                            {doGrupo.map((x) => (
                                <li key={x.valor}
                                    className={cls(
                                        'flex flex-wrap items-center gap-3 border p-2.5 transition',
                                        RAIO,
                                        ligados[x.valor] ? 'border-indigo-200 bg-indigo-50/50' : 'border-slate-200',
                                    )}>
                                    <label className="flex min-w-[12rem] flex-1 items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" checked={Boolean(ligados[x.valor])} disabled={!podeEditar}
                                            onChange={(e) => aoMudar(
                                                { ...ligados, [x.valor]: e.target.checked },
                                                escolhidos,
                                            )}
                                            className="h-5 w-5 rounded text-indigo-600" />
                                        {x.rotulo}
                                    </label>

                                    {/* O MODELO diz o que se escreve; o
                                        interruptor diz se sai. São duas
                                        decisões e não uma. */}
                                    <select
                                        value={escolhidos[x.valor] ?? ''}
                                        disabled={!podeEditar || !ligados[x.valor]}
                                        onChange={(e) => aoMudar(ligados, {
                                            ...escolhidos, [x.valor]: e.target.value,
                                        })}
                                        aria-label={t('Modelo de :aviso', { aviso: x.rotulo })}
                                        className={cls(entrada, 'w-56 disabled:bg-slate-50 disabled:text-slate-400')}
                                    >
                                        <option value="">{t('Texto por omissão')}</option>
                                        {modelos.map((m) => (
                                            <option key={m.id} value={m.id}>{m.nome}</option>
                                        ))}
                                    </select>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </div>
    );
}
