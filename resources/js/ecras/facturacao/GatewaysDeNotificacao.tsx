import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { ErroDaApi } from '@/api/cliente';
import { gatewaysDeNotificacao as api, type Canal, type DefinicoesDeGateways, type EcraDeGateways } from '@/api/gatewaysDeNotificacao';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { Etiqueta } from '@/ui/Etiqueta';
import { RAIO, cls } from '@/ui/tokens';
import { Faixa, SemNada, cascata } from './faixa';

type Separador = 'painel' | Canal;
const CANAIS: Array<{ chave: Separador; nome: string; icone: string; cor: string }> = [
    { chave: 'painel', nome: 'Visão geral', icone: 'fa-chart-line', cor: 'text-indigo-600' },
    { chave: 'email', nome: 'Email', icone: 'fa-envelope', cor: 'text-blue-600' },
    { chave: 'sms', nome: 'SMS', icone: 'fa-comment-sms', cor: 'text-violet-600' },
    { chave: 'whatsapp', nome: 'WhatsApp', icone: 'fa-brands fa-whatsapp', cor: 'text-emerald-600' },
];

export default function GatewaysDeNotificacao() {
    const fila = useQueryClient();
    const ecra = useQuery({ queryKey: ['gateways-de-notificacao'], queryFn: api.ler });
    const [forma, porForma] = useState<DefinicoesDeGateways | null>(null);
    const [separador, porSeparador] = useState<Separador>(() => {
        const pedido = new URLSearchParams(location.search).get('tab');
        return pedido && ['painel', 'email', 'sms', 'whatsapp'].includes(pedido) ? pedido as Separador : 'sms';
    });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [mensagem, porMensagem] = useState<{ bom: boolean; texto: string } | null>(null);
    const [templatesWhatsApp, porTemplatesWhatsApp] = useState<Array<{ sid: string; name: string; status?: string }>>([]);

    useEffect(() => {
        if (!ecra.data || forma) return;
        porForma({
            ...ecra.data.definicoes,
            smtp_password: '', sms_auth_token: '', sms_api_token: '', whatsapp_auth_token: '',
            email_notifications: ecra.data.definicoes.email_notifications ?? {},
            sms_notifications: ecra.data.definicoes.sms_notifications ?? {},
            whatsapp_notifications: ecra.data.definicoes.whatsapp_notifications ?? {},
            email_notification_templates: ecra.data.definicoes.email_notification_templates ?? {},
            sms_notification_templates: ecra.data.definicoes.sms_notification_templates ?? {},
            whatsapp_notification_templates: ecra.data.definicoes.whatsapp_notification_templates ?? {},
            whatsapp_templates: ecra.data.definicoes.whatsapp_templates ?? [],
        });
    }, [ecra.data, forma]);

    useEffect(() => {
        if (!mensagem) return;
        const id = setTimeout(() => porMensagem(null), 6000);
        return () => clearTimeout(id);
    }, [mensagem]);

    const guardar = useMutation({
        mutationFn: api.guardar,
        onSuccess: (r) => {
            porErros({});
            porMensagem({ bom: true, texto: r.message });
            porForma((f) => f ? ({ ...f, smtp_password: '', sms_auth_token: '', sms_api_token: '', whatsapp_auth_token: '' }) : f);
            fila.invalidateQueries({ queryKey: ['gateways-de-notificacao'] });
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const testarSms = useMutation({
        mutationFn: () => api.testarSms({ sms_provider: forma?.sms_provider ?? '', sms_api_token: forma?.sms_api_token, sms_sender_id: forma?.sms_sender_id ?? null }),
        onSuccess: (r) => porMensagem({ bom: true, texto: `${r.message}${r.balance != null ? ` Saldo: ${r.balance}${r.currency ? ` ${r.currency}` : ''}` : ''}` }),
        onError: (e) => porMensagem({ bom: false, texto: e instanceof Error ? e.message : 'Não foi possível consultar o fornecedor.' }),
    });
    const testarEmail = useMutation({
        mutationFn: () => api.testarEmail(forma ?? {}),
        onSuccess: (r) => porMensagem({ bom: true, texto: r.message }),
        onError: (e) => porMensagem({ bom: false, texto: e instanceof Error ? e.message : 'Não foi possível enviar o teste.' }),
    });
    const procurarTemplates = useMutation({
        mutationFn: () => api.templatesWhatsApp(forma ?? {}),
        onSuccess: (r) => { porTemplatesWhatsApp(r.templates); porMensagem({ bom: true, texto: r.message }); },
        onError: (e) => porMensagem({ bom: false, texto: e instanceof Error ? e.message : 'Não foi possível consultar os templates.' }),
    });
    const testarWhatsApp = useMutation({
        mutationFn: (teste: { telefone: string; sid: string; nome: string }) => api.testarWhatsApp({
            ...(forma ?? {}), test_phone: teste.telefone, template_sid: teste.sid, template_name: teste.nome, variables: {},
        }),
        onSuccess: (r) => porMensagem({ bom: true, texto: r.message }),
        onError: (e) => porMensagem({ bom: false, texto: e instanceof Error ? e.message : 'Não foi possível enviar o teste.' }),
    });

    if (ecra.isPending || !forma) return <Carregando linhas={10} />;
    if (ecra.isError) return <div role="alert" className={cls('border border-red-200 bg-red-50 p-6 text-red-900', RAIO)}>Não foi possível abrir os gateways. Verifique a ligação.</div>;

    const podeEditar = ecra.data.permissoes.pode_editar;
    const mudar = <K extends keyof DefinicoesDeGateways>(campo: K, valor: DefinicoesDeGateways[K]) => porForma((f) => f ? ({ ...f, [campo]: valor }) : f);
    const texto = (campo: keyof DefinicoesDeGateways) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => mudar(campo, e.target.value as never);

    return (
        <div className="space-y-4">
            <Faixa icone="fa-bell" titulo="Gateways de Notificação" subtitulo="Email, SMS e WhatsApp da empresa, num único lugar" />

            {mensagem && <div role="status" className={cls('flex items-start gap-2 border px-4 py-3 text-sm', RAIO, mensagem.bom ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}><i className={cls('fas mt-0.5', mensagem.bom ? 'fa-circle-check' : 'fa-circle-exclamation')} aria-hidden="true" /><span>{mensagem.texto}</span></div>}
            <AvisoDeErro erro={guardar.error} />

            <nav aria-label="Áreas dos gateways" className="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                {CANAIS.map((c) => <button key={c.chave} type="button" onClick={() => porSeparador(c.chave)} className={cls('inline-flex min-h-10 flex-none items-center gap-2 rounded-xl px-4 text-sm font-semibold transition', separador === c.chave ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50')}><i className={cls(c.icone.startsWith('fa-brands') ? '' : 'fas', c.icone, separador === c.chave ? 'text-white' : c.cor)} aria-hidden="true" />{c.nome}</button>)}
                <a href="/notifications/templates" className="ml-auto inline-flex min-h-10 flex-none items-center gap-2 rounded-xl px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50"><i className="fas fa-file-lines text-amber-500" aria-hidden="true" />Gerir templates</a>
            </nav>

            {separador === 'painel' ? <Painel ecra={ecra.data} forma={forma} /> : (
                <>
                    {separador === 'email' && <Email forma={forma} mudar={mudar} texto={texto} erros={erros} segredos={ecra.data.segredos_guardados} podeEditar={podeEditar} testar={() => testarEmail.mutate()} testando={testarEmail.isPending} />}
                    {separador === 'sms' && <Sms forma={forma} mudar={mudar} texto={texto} erros={erros} segredos={ecra.data.segredos_guardados} podeEditar={podeEditar} testar={() => testarSms.mutate()} testando={testarSms.isPending} />}
                    {separador === 'whatsapp' && <WhatsApp forma={forma} mudar={mudar} texto={texto} erros={erros} segredos={ecra.data.segredos_guardados} podeEditar={podeEditar} templates={templatesWhatsApp} procurar={() => procurarTemplates.mutate()} procurando={procurarTemplates.isPending} testar={(x) => testarWhatsApp.mutate(x)} testando={testarWhatsApp.isPending} />}
                    <Preferencias canal={separador} forma={forma} mudar={mudar} ecra={ecra.data} />
                </>
            )}

            {separador !== 'painel' && <div className="sticky bottom-0 z-10 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/95 px-3 py-3 shadow-[0_-4px_12px_-8px_rgba(15,23,42,.35)] backdrop-blur">{!podeEditar && <span className="mr-auto text-sm text-slate-500">Só pode consultar estas configurações.</span>}<Botao cor="primaria" tom="solida" altura="grande" icone="fa-floppy-disk" disabled={!podeEditar} aTrabalhar={guardar.isPending} onClick={() => guardar.mutate(forma)}>Guardar configurações</Botao></div>}
        </div>
    );
}

function Painel({ ecra, forma }: { ecra: EcraDeGateways; forma: DefinicoesDeGateways }) {
    const activos = [forma.email_enabled, forma.sms_enabled, forma.whatsapp_enabled].filter(Boolean).length;
    return <div className="space-y-4"><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{[
        ['Canais activos', String(activos), 'fa-tower-broadcast', 'from-indigo-500 to-violet-600'],
        ['Templates disponíveis', String(ecra.templates.length), 'fa-file-lines', 'from-amber-500 to-orange-500'],
        ['Eventos configuráveis', String(ecra.eventos.length), 'fa-bolt', 'from-cyan-500 to-blue-600'],
        ['Credenciais protegidas', String(Object.values(ecra.segredos_guardados).filter(Boolean).length), 'fa-shield-halved', 'from-emerald-500 to-green-600'],
    ].map(([n, v, i, c], k) => <div key={n} className="entra rounded-2xl border border-slate-100 bg-white p-5 shadow-sm" style={cascata(k)}><div className={cls('mb-4 grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br text-white shadow', c)}><i className={cls('fas', i)} aria-hidden="true" /></div><p className="text-3xl font-black text-slate-900">{v}</p><p className="mt-1 text-sm font-medium text-slate-500">{n}</p></div>)}</div><Cartao titulo="Estado por canal" icone="fa-signal"><div className="grid gap-3 sm:grid-cols-3">{(['email', 'sms', 'whatsapp'] as Canal[]).map((c) => { const ligado = forma[`${c}_enabled`]; return <button key={c} type="button" onClick={() => location.href = `?tab=${c}`} className={cls('flex items-center justify-between rounded-xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-sm', ligado ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200')}><span className="font-semibold capitalize text-slate-800">{c}</span><Etiqueta cor={ligado ? 'bom' : 'neutra'} ponto>{ligado ? 'Activo' : 'Inactivo'}</Etiqueta></button>; })}</div></Cartao></div>;
}

type Mudar = <K extends keyof DefinicoesDeGateways>(campo: K, valor: DefinicoesDeGateways[K]) => void;
type Texto = (campo: keyof DefinicoesDeGateways) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => void;
const segredo = (guardado: boolean) => guardado ? '•••••••• (guardado — deixe vazio para manter)' : 'Introduza a credencial';

function Email({ forma, mudar, texto, erros, segredos, podeEditar, testar, testando }: { forma: DefinicoesDeGateways; mudar: Mudar; texto: Texto; erros: Record<string, string[]>; segredos: Record<string, boolean>; podeEditar: boolean; testar: () => void; testando: boolean }) {
    return <Cartao titulo="Servidor de email" subtitulo="SMTP usado apenas por esta empresa" icone="fa-envelope" accoes={<Botao icone="fa-paper-plane" disabled={!podeEditar || !forma.from_email} aTrabalhar={testando} onClick={testar}>Enviar email de teste</Botao>}><Interruptor etiqueta="Activar notificações por email" valor={forma.email_enabled} aoMudar={(v) => mudar('email_enabled', v)} /><div className="mt-5 grid gap-4 md:grid-cols-2"><Campo etiqueta="Servidor SMTP" obrigatorio={forma.email_enabled} erro={erros.smtp_host}><input className={entrada} value={forma.smtp_host ?? ''} onChange={texto('smtp_host')} placeholder="smtp.exemplo.com" /></Campo><Campo etiqueta="Porta" obrigatorio={forma.email_enabled} erro={erros.smtp_port}><input className={entrada} type="number" min="1" max="65535" value={forma.smtp_port ?? 587} onChange={texto('smtp_port')} /></Campo><Campo etiqueta="Utilizador"><input className={entrada} value={forma.smtp_username ?? ''} onChange={texto('smtp_username')} autoComplete="username" /></Campo><Campo etiqueta="Senha" erro={erros.smtp_password} ajuda="Nunca é devolvida ao navegador."><input className={entrada} type="password" value={forma.smtp_password ?? ''} onChange={texto('smtp_password')} placeholder={segredo(!!segredos.smtp_password)} autoComplete="new-password" /></Campo><Campo etiqueta="Segurança"><select className={entrada} value={forma.smtp_encryption ?? 'tls'} onChange={texto('smtp_encryption')}><option value="tls">TLS</option><option value="ssl">SSL</option></select></Campo><Campo etiqueta="Email remetente" obrigatorio={forma.email_enabled} erro={erros.from_email}><input className={entrada} type="email" value={forma.from_email ?? ''} onChange={texto('from_email')} /></Campo><Campo etiqueta="Nome remetente" className="md:col-span-2"><input className={entrada} value={forma.from_name ?? ''} onChange={texto('from_name')} placeholder="SOS ERP" /></Campo></div><p className="mt-4 text-xs text-amber-700"><i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />O botão de teste envia uma mensagem real para o email remetente.</p></Cartao>;
}

function Sms({ forma, mudar, texto, erros, segredos, podeEditar, testar, testando }: { forma: DefinicoesDeGateways; mudar: Mudar; texto: Texto; erros: Record<string, string[]>; segredos: Record<string, boolean>; podeEditar: boolean; testar: () => void; testando: boolean }) {
    const telco = forma.sms_provider === 'telcosms';
    return <Cartao titulo="Gateway de SMS" subtitulo="O remetente TelcoSMS é sempre SOSERP" icone="fa-comment-sms" accoes={<Botao icone="fa-signal" disabled={!podeEditar || !forma.sms_provider} aTrabalhar={testando} onClick={testar}>Consultar ligação/saldo</Botao>}><Interruptor etiqueta="Activar notificações por SMS" valor={forma.sms_enabled} aoMudar={(v) => mudar('sms_enabled', v)} /><div className="mt-5 grid gap-4 md:grid-cols-2"><Campo etiqueta="Fornecedor" obrigatorio={forma.sms_enabled} erro={erros.sms_provider}><select className={entrada} value={forma.sms_provider ?? ''} onChange={texto('sms_provider')}><option value="">Seleccione</option><option value="telcosms">TelcoSMS Angola</option><option value="d7networks">D7 Networks</option><option value="twilio">Twilio</option><option value="nexmo">Nexmo / Vonage</option><option value="other">Outro</option></select></Campo>{telco || forma.sms_provider === 'd7networks' ? <Campo etiqueta={telco ? 'Chave da aplicação (api_key_app)' : 'API Token'} erro={erros.sms_api_token} ajuda="Cifrada; deixe vazio para conservar a chave actual."><input className={entrada} type="password" value={forma.sms_api_token ?? ''} onChange={texto('sms_api_token')} placeholder={segredo(!!segredos.sms_api_token)} autoComplete="new-password" /></Campo> : <><Campo etiqueta="Conta / API Key"><input className={entrada} value={forma.sms_account_sid ?? ''} onChange={texto('sms_account_sid')} /></Campo><Campo etiqueta="Token / segredo"><input className={entrada} type="password" value={forma.sms_auth_token ?? ''} onChange={texto('sms_auth_token')} placeholder={segredo(!!segredos.sms_auth_token)} autoComplete="new-password" /></Campo></>}<Campo etiqueta="Remetente"><input className={cls(entrada, telco && 'bg-slate-50')} readOnly={telco} value={telco ? 'SOSERP' : (forma.sms_sender_id ?? '')} onChange={texto('sms_sender_id')} maxLength={30} /></Campo><Campo etiqueta="Número de origem"><input className={entrada} value={forma.sms_from_number ?? ''} onChange={texto('sms_from_number')} placeholder="+244..." /></Campo></div>{telco && <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><i className="fas fa-circle-info mr-2" aria-hidden="true" />Use a chave PRD ou QAS da aplicação SOSERP aprovada na TelcoSMS. A consulta acima contacta realmente o fornecedor.</div>}</Cartao>;
}

function WhatsApp({ forma, mudar, texto, erros, segredos, podeEditar, templates, procurar, procurando, testar, testando }: { forma: DefinicoesDeGateways; mudar: Mudar; texto: Texto; erros: Record<string, string[]>; segredos: Record<string, boolean>; podeEditar: boolean; templates: Array<{ sid: string; name: string; status?: string }>; procurar: () => void; procurando: boolean; testar: (x: { telefone: string; sid: string; nome: string }) => void; testando: boolean }) {
    const [telefone, porTelefone] = useState(''); const [sid, porSid] = useState('');
    const escolhido = templates.find((x) => x.sid === sid);
    return <Cartao titulo="WhatsApp Business" subtitulo="Credenciais, templates e número oficial" icone="fa-brands fa-whatsapp" accoes={<Botao icone="fa-arrows-rotate" disabled={!podeEditar || !forma.whatsapp_account_sid} aTrabalhar={procurando} onClick={procurar}>Procurar templates</Botao>}><Interruptor etiqueta="Activar notificações por WhatsApp" valor={forma.whatsapp_enabled} aoMudar={(v) => mudar('whatsapp_enabled', v)} /><div className="mt-5 grid gap-4 md:grid-cols-2"><Campo etiqueta="Fornecedor" obrigatorio={forma.whatsapp_enabled} erro={erros.whatsapp_provider}><select className={entrada} value={forma.whatsapp_provider ?? 'twilio'} onChange={texto('whatsapp_provider')}><option value="twilio">Twilio</option><option value="meta">Meta Cloud API</option></select></Campo><Campo etiqueta="Account SID"><input className={entrada} value={forma.whatsapp_account_sid ?? ''} onChange={texto('whatsapp_account_sid')} /></Campo><Campo etiqueta="Token" ajuda="Nunca é devolvido ao navegador."><input className={entrada} type="password" value={forma.whatsapp_auth_token ?? ''} onChange={texto('whatsapp_auth_token')} placeholder={segredo(!!segredos.whatsapp_auth_token)} autoComplete="new-password" /></Campo><Campo etiqueta="Número de origem" obrigatorio={forma.whatsapp_enabled} erro={erros.whatsapp_from_number}><input className={entrada} value={forma.whatsapp_from_number ?? ''} onChange={texto('whatsapp_from_number')} placeholder="whatsapp:+244..." /></Campo><Campo etiqueta="Business Account ID"><input className={entrada} value={forma.whatsapp_business_account_id ?? ''} onChange={texto('whatsapp_business_account_id')} /></Campo><Interruptor etiqueta="Usar ambiente Sandbox" valor={forma.whatsapp_sandbox} aoMudar={(v) => mudar('whatsapp_sandbox', v)} /></div>{templates.length > 0 && <div className="mt-5 grid gap-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 md:grid-cols-[1fr_1fr_auto]"><Campo etiqueta="Telefone de teste"><input className={entrada} value={telefone} onChange={(e) => porTelefone(e.target.value)} placeholder="+244..." /></Campo><Campo etiqueta="Template"><select className={entrada} value={sid} onChange={(e) => porSid(e.target.value)}><option value="">Seleccione</option>{templates.map((x) => <option key={x.sid} value={x.sid}>{x.name}{x.status ? ` · ${x.status}` : ''}</option>)}</select></Campo><div className="flex items-end"><Botao cor="bom" tom="solida" icone="fa-paper-plane" disabled={!telefone || !escolhido} aTrabalhar={testando} onClick={() => escolhido && testar({ telefone, sid: escolhido.sid, nome: escolhido.name })}>Enviar teste</Botao></div></div>}<p className="mt-4 text-xs text-amber-700"><i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />Procurar templates e enviar teste contactam realmente o fornecedor.</p></Cartao>;
}

function Preferencias({ canal, forma, mudar, ecra }: { canal: Canal; forma: DefinicoesDeGateways; mudar: Mudar; ecra: EcraDeGateways }) {
    const campoNotif = `${canal}_notifications` as const;
    const campoTpl = `${canal}_notification_templates` as const;
    const notificacoes = forma[campoNotif];
    const mapeamento = forma[campoTpl];
    const templates = ecra.templates.filter((x) => x[`${canal}_enabled`]);
    const alterar = (chave: string, valor: boolean) => mudar(campoNotif, { ...notificacoes, [chave]: valor });
    const escolher = (chave: string, valor: string) => mudar(campoTpl, { ...mapeamento, [chave]: valor ? Number(valor) : null });
    if (ecra.eventos.length === 0) return <SemNada icone="fa-bell-slash" titulo="Sem eventos configuráveis" frase="Crie templates para começar." />;
    return <Cartao titulo="Quando notificar" subtitulo="Active o evento e, se desejar, associe um template" icone="fa-bolt" semPadding><div className="overflow-x-auto"><table className="w-full min-w-[680px] text-sm"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th className="px-5 py-3">Evento</th><th className="px-5 py-3 text-center">Enviar</th><th className="px-5 py-3">Template</th></tr></thead><tbody className="divide-y divide-slate-100">{ecra.eventos.map((e, i) => <tr key={e.chave} className="entra hover:bg-slate-50" style={cascata(i)}><td className="px-5 py-3"><span className="inline-flex items-center gap-3 font-medium text-slate-800"><span className="grid h-9 w-9 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><i className={cls('fas', e.icone)} aria-hidden="true" /></span>{e.nome}</span></td><td className="px-5 py-3 text-center"><input type="checkbox" className="h-5 w-5 rounded border-slate-300 text-indigo-600" checked={!!notificacoes[e.chave]} onChange={(x) => alterar(e.chave, x.target.checked)} aria-label={`${notificacoes[e.chave] ? 'Desactivar' : 'Activar'} ${e.nome} por ${canal}`} /></td><td className="px-5 py-3"><select className={cls(entrada, 'max-w-sm')} value={String(mapeamento[e.chave] ?? '')} onChange={(x) => escolher(e.chave, x.target.value)}><option value="">Template padrão</option>{templates.map((tpl) => <option key={tpl.id} value={tpl.id}>{tpl.name} · {tpl.module}</option>)}</select></td></tr>)}</tbody></table></div></Cartao>;
}

function Interruptor({ etiqueta, valor, aoMudar }: { etiqueta: string; valor: boolean; aoMudar: (v: boolean) => void }) {
    return <label className={cls('flex cursor-pointer items-center justify-between gap-4 rounded-xl border px-4 py-3 text-sm font-semibold transition', valor ? 'border-indigo-200 bg-indigo-50 text-indigo-900' : 'border-slate-200 text-slate-700 hover:bg-slate-50')}><span>{etiqueta}</span><span className={cls('relative h-6 w-11 rounded-full transition', valor ? 'bg-indigo-600' : 'bg-slate-300')}><input type="checkbox" className="sr-only" checked={valor} onChange={(e) => aoMudar(e.target.checked)} /><span className={cls('absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition', valor ? 'left-[22px]' : 'left-0.5')} /></span></label>;
}
