import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type ConfiguracaoWhatsApp, definicoes } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada } from '@/ui/SemNada';
import { RAIO, cls } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Interruptor, Recado, SegredoGuardado } from './comum';

/**
 * O WHATSAPP DA PLATAFORMA (Twilio).
 *
 * O TOKEN DA TWILIO já não vem para a página: o componente antigo punha-o numa
 * propriedade pública, dentro do HTML. Diz-se só se está guardado. E ligar sem
 * conta, token e número é recusado — antes ligava-se e nada saía.
 */
export default function WhatsApp() {
    const fila = useQueryClient();
    const [recado, porRecado] = useState<{ texto: string; aviso?: boolean } | null>(null);
    const [f, porF] = useState<ConfiguracaoWhatsApp | null>(null);
    const [disponiveis, porDisponiveis] = useState<Array<{ sid: string; name: string; language?: string }>>([]);
    const [teste, porTeste] = useState({ numero: '', mensagem: '' });

    const dados = useQuery({ queryKey: ['plataforma', 'whatsapp'], queryFn: definicoes.whatsapp.ler });

    useEffect(() => { if (dados.data) porF(dados.data.configuracao); }, [dados.data]);

    const feito = (texto: string, aviso = false) => {
        porRecado({ texto, aviso });
        void fila.invalidateQueries({ queryKey: ['plataforma', 'whatsapp'] });
    };

    const guardar = useMutation({ mutationFn: () => definicoes.whatsapp.guardar({ ...f }), onSuccess: (r) => feito(r.message) });
    const testar = useMutation({
        mutationFn: definicoes.whatsapp.testar,
        onSuccess: (r) => porRecado({ texto: r.message }),
        onError: (e) => porRecado({ texto: e instanceof ErroDaApi ? e.message : t('A ligação falhou.'), aviso: true }),
    });
    const modelos = useMutation({ mutationFn: definicoes.whatsapp.modelos, onSuccess: (r) => { porDisponiveis(r.modelos); porRecado({ texto: r.message }); } });
    const enviar = useMutation({ mutationFn: () => definicoes.whatsapp.enviarTeste(teste.numero, teste.mensagem), onSuccess: (r) => { porTeste({ numero: '', mensagem: '' }); porRecado({ texto: r.message }); } });

    if (dados.isPending || !f) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir o WhatsApp')} erro={dados.error} />;

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const errosDoTeste = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};

    return (
        <div className="space-y-4">
            <Faixa titulo={t('WhatsApp')} subtitulo={t('As mensagens automáticas pela Twilio')} icone="fa-comment-dots" cor="teal">
                <EstadoNaFaixa icone={dados.data.activo ? 'fa-circle-check' : 'fa-circle-pause'}>
                    {dados.data.activo ? t('Activo e configurado') : t('Desligado ou por configurar')}
                </EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado?.texto ?? null} aviso={recado?.aviso} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={guardar.error ?? modelos.error} />

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Credenciais da Twilio')} icone="fa-key">
                    <div className="space-y-4">
                        <Campo etiqueta={t('Account SID')} erro={erros.twilio_account_sid}>
                            <input className={cls(entrada, 'font-mono text-xs')} autoComplete="off" value={f.twilio_account_sid} onChange={(e) => porF({ ...f, twilio_account_sid: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Auth Token')} erro={erros.twilio_auth_token} ajuda={<SegredoGuardado guardado={dados.data.configuracao.token_guardado} />}>
                            <input type="password" autoComplete="new-password" className={entrada} value={f.twilio_auth_token} onChange={(e) => porF({ ...f, twilio_auth_token: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Número de envio')} erro={erros.whatsapp_from_number} ajuda={t('No formato whatsapp:+14155238886, ou só o número.')}>
                            <input className={entrada} value={f.whatsapp_from_number} onChange={(e) => porF({ ...f, whatsapp_from_number: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Business Account ID')} erro={erros.whatsapp_business_account_id}>
                            <input className={cls(entrada, 'font-mono text-xs')} value={f.whatsapp_business_account_id} onChange={(e) => porF({ ...f, whatsapp_business_account_id: e.target.value })} />
                        </Campo>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Interruptor rotulo={t('WhatsApp ligado')} nota={erros.is_enabled?.[0]} valor={f.is_enabled} aoMudar={(v) => porF({ ...f, is_enabled: v })} />
                            <Interruptor rotulo={t('Sandbox')} nota={t('A área de testes da Twilio.')} valor={f.is_sandbox} aoMudar={(v) => porF({ ...f, is_sandbox: v })} cor="amber" />
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Botao cor="primaria" tom="suave" icone="fa-plug" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>{t('Testar ligação')}</Botao>
                            <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
                        </div>
                    </div>
                </Cartao>

                <Cartao titulo={t('Avisos automáticos')} icone="fa-bell" subtitulo={t('Que acontecimentos mandam mensagem')}>
                    <div className="space-y-2">
                        {dados.data.avisos.map((a) => (
                            <Interruptor key={a.valor} rotulo={a.rotulo} valor={Boolean(f.notification_settings[a.valor])}
                                aoMudar={(v) => porF({ ...f, notification_settings: { ...f.notification_settings, [a.valor]: v } })} />
                        ))}
                        <p className="pt-2 text-xs text-slate-500">{t('Os avisos gravam-se com o botão «Guardar» das credenciais.')}</p>
                    </div>
                </Cartao>
            </div>

            <Cartao
                titulo={t('Modelos de mensagem')}
                icone="fa-file-lines"
                accoes={<Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-cloud-arrow-down" aTrabalhar={modelos.isPending} onClick={() => modelos.mutate()}>{t('Ir buscar à Twilio')}</Botao>}
            >
                <div className="space-y-4">
                    {f.templates.length === 0 ? <SemNada icone="fa-file-lines" frase={t('Nenhum modelo escolhido.')} /> : (
                        <ul className="divide-y divide-slate-100">
                            {f.templates.map((m, i) => (
                                <li key={m.sid} className="flex items-center justify-between gap-3 py-2">
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-semibold text-slate-800">{m.name ?? m.sid}</span>
                                        <span className="block font-mono text-xs text-slate-500">{m.sid}{m.language ? ` · ${m.language}` : ''}</span>
                                    </span>
                                    <Botao cor="perigo" tom="suave" altura="pequeno" icone="fa-xmark" onClick={() => porF({ ...f, templates: f.templates.filter((_, j) => j !== i) })}>{t('Retirar')}</Botao>
                                </li>
                            ))}
                        </ul>
                    )}

                    {disponiveis.length > 0 && (
                        <div className="entra">
                            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{t('Na Twilio')}</p>
                            <ul className="grid gap-2 md:grid-cols-2">
                                {disponiveis.map((m) => {
                                    const ja = f.templates.some((x) => x.sid === m.sid);

                                    return (
                                        <li key={m.sid} className={cls('flex items-center justify-between gap-2 border px-3 py-2', RAIO, ja ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200')}>
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold text-slate-800">{m.name}</span>
                                                <span className="block font-mono text-[10px] text-slate-500">{m.sid}</span>
                                            </span>
                                            {ja ? <Etiqueta cor="bom">{t('já escolhido')}</Etiqueta> : (
                                                <Botao cor="bom" tom="suave" altura="pequeno" icone="fa-plus" onClick={() => porF({ ...f, templates: [...f.templates, m] })}>{t('Juntar')}</Botao>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>
            </Cartao>

            <Cartao titulo={t('Mensagem de teste')} icone="fa-paper-plane">
                <div className="space-y-3">
                    <AvisoDeErro erro={enviar.error} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <Campo etiqueta={t('Número')} obrigatorio erro={errosDoTeste.numero}>
                            <input className={entrada} value={teste.numero} placeholder="+244 9XX XXX XXX" onChange={(e) => porTeste({ ...teste, numero: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Mensagem')} obrigatorio erro={errosDoTeste.mensagem} className="md:col-span-2">
                            <input className={entrada} value={teste.mensagem} onChange={(e) => porTeste({ ...teste, mensagem: e.target.value })} />
                        </Campo>
                    </div>
                    <div className="flex justify-end">
                        <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar teste')}</Botao>
                    </div>
                </div>
            </Cartao>
        </div>
    );
}
