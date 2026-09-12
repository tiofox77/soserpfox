import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { notificacoes as api, type Modelo, type Previsao } from '@/api/notificacoes';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS MODELOS DE NOTIFICAÇÃO: o que se diz, em que canal, e quando.
 *
 * UM MODELO SEM CANAL NENHUM LIGADO nunca manda nada — e nada no ecrã antigo o
 * dizia sem se abrir a ficha. Aqui conta-se, e a lista marca-o.
 *
 * O TESTE PASSA PELO MESMO CAMINHO DO ENVIO A SÉRIO. O ecrã antigo
 * reimplementava os três canais por dentro e o do SMS era um `TODO`: escrevia
 * no log e dizia «Teste enviado com sucesso via SMS» sem nada ter saído.
 */

const COR_DO_CANAL: Record<string, 'primaria' | 'bom' | 'aviso' | 'neutra'> = {
    email: 'primaria',
    sms: 'aviso',
    whatsapp: 'bom',
};

const ICONE_DO_CANAL: Record<string, string> = {
    email: 'fa-envelope',
    sms: 'fa-comment-sms',
    whatsapp: 'fa-comment-dots',
};

const ROTULO_DO_CANAL = (c: string) => ({
    email: t('E-mail'), sms: t('SMS'), whatsapp: t('WhatsApp'),
}[c] ?? c);

const VAZIO = {
    name: '', slug: '', module: 'events', description: '',
    trigger_event: 'created', notify_before_minutes: '', notify_at_time: '',
    email_enabled: false, sms_enabled: false, whatsapp_enabled: false,
    email_subject: '', email_body: '', sms_body: '',
    email_template_id: '', sms_template_sid: '', whatsapp_template_sid: '',
    is_active: true,
};

type Formulario = typeof VAZIO;

export default function ModelosDeNotificacao() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ canal?: string; modulo?: string; procura?: string; estado?: string }>({
        canal: 'todos', modulo: 'todos', estado: 'todos',
    });

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<Formulario | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Modelo | null>(null);

    /* O teste. */
    const [aTestar, porATestar] = useState<number | null>(null);
    const [variaveis, porVariaveis] = useState<Record<string, string>>({});
    const [destino, porDestino] = useState({ email: '', telefone: '' });
    const [canaisDoTeste, porCanaisDoTeste] = useState<string[]>([]);
    const [previsao, porPrevisao] = useState<Previsao | null>(null);

    const opcoes = useQuery({ queryKey: ['notificacoes', 'modelos', 'opcoes'], queryFn: () => api.modelos.opcoes() });
    const lista = useQuery({
        queryKey: ['notificacoes', 'modelos', filtros],
        queryFn: () => api.modelos.listar(filtros),
    });
    const doModulo = useQuery({
        queryKey: ['notificacoes', 'variaveis', formulario?.module],
        queryFn: () => api.modelos.variaveis(formulario?.module as string),
        enabled: formulario !== null,
    });
    const teste = useQuery({
        queryKey: ['notificacoes', 'teste', aTestar],
        queryFn: () => api.modelos.preparar(aTestar as number),
        enabled: aTestar !== null,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['notificacoes'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => api.modelos.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); fechar(); },
        onError: porErro,
    });

    const estado = useMutation({
        mutationFn: (id: number) => api.modelos.estado(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => api.modelos.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: (e) => { porErro(e); porAApagar(null); },
    });

    const abrirFicha = useMutation({
        mutationFn: (id: number) => api.modelos.ficha(id),
        onSuccess: (r) => {
            const d = r.data;

            porAEditar(d.id);
            porFormulario({
                name: d.nome, slug: d.slug ?? '', module: d.modulo, description: d.descricao ?? '',
                trigger_event: d.evento,
                notify_before_minutes: d.antes_minutos !== null ? String(d.antes_minutos) : '',
                notify_at_time: d.a_hora ?? '',
                email_enabled: d.email_enabled, sms_enabled: d.sms_enabled, whatsapp_enabled: d.whatsapp_enabled,
                email_subject: d.email_subject ?? '', email_body: d.email_body ?? '', sms_body: d.sms_body ?? '',
                email_template_id: d.email_template_id !== null ? String(d.email_template_id) : '',
                sms_template_sid: d.sms_template_sid ?? '',
                whatsapp_template_sid: d.whatsapp_template_sid ?? '',
                is_active: d.activo,
            });
        },
        onError: porErro,
    });

    const rever = useMutation({
        mutationFn: (v: Record<string, string>) => api.modelos.previsao(aTestar as number, v),
        onSuccess: (r) => porPrevisao(r.previsao),
        onError: porErro,
    });

    const enviarTeste = useMutation({
        mutationFn: () => api.modelos.testar(aTestar as number, {
            canais: canaisDoTeste,
            email: destino.email || null,
            telefone: destino.telefone || null,
            variaveis,
        }),
        onSuccess: (r) => { feito(r.message); fecharTeste(); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.gerir;
    const podeTestar = o.permissoes.testar;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const fechar = () => { porFormulario(null); porAEditar(null); };

    const fecharTeste = () => {
        porATestar(null);
        porVariaveis({});
        porDestino({ email: '', telefone: '' });
        porCanaisDoTeste([]);
        porPrevisao(null);
    };

    const abrirTeste = (m: Modelo) => {
        porATestar(m.id);
        porCanaisDoTeste(m.canais);
        porPrevisao(null);
    };

    // A preparação chega do servidor com as variáveis já preenchidas com dados
    // de exemplo: um modelo revê-se com o texto POSTO, não com as chavetas.
    if (teste.data && Object.keys(variaveis).length === 0 && Object.keys(teste.data.variaveis).length > 0) {
        porVariaveis(teste.data.variaveis);
        porDestino((d) => ({ ...d, email: d.email || (teste.data.email_sugerido ?? '') }));
        porPrevisao(teste.data.previsao);
    }

    const submeter = () => {
        if (!formulario) return;

        guardar.mutate({
            ...formulario,
            notify_before_minutes: formulario.notify_before_minutes === ''
                ? null : Number(formulario.notify_before_minutes),
            notify_at_time: formulario.notify_at_time || null,
            email_template_id: formulario.email_template_id === '' ? null : Number(formulario.email_template_id),
        });
    };

    const variaveisDoModulo = doModulo.data?.variaveis ?? [];

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Modelos de Notificação')}
                subtitulo={t('O que se diz, em que canal, e quando')}
                icone="fa-file-lines"
                cor="ciano"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo modelo')}
                            </button>
                        )}
                        <a href="/notifications/settings" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-sliders" aria-hidden="true" />
                            {t('Definições de canais')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-file-lines">
                        {t(':n modelos activos', { n: numero(resumo?.activos ?? 0) })}
                    </EstadoNaFaixa>
                    {(resumo?.sem_canal ?? 0) > 0 && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">
                            {t(':n sem canal nenhum', { n: numero(resumo?.sem_canal ?? 0) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            {(lista.data?.criados_agora ?? 0) > 0 && (
                <p className={cls('border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm text-indigo-900', RAIO)}>
                    <i className="fas fa-wand-magic-sparkles mr-2" aria-hidden="true" />
                    {t('Criámos :n modelos em falta nesta empresa. Só se acrescenta o que faltava — nada do que já cá estava foi tocado.', {
                        n: numero(lista.data?.criados_agora ?? 0),
                    })}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Modelos')} valor={numero(resumo.total)} icone="fa-file-lines" tom="teal"
                        aoCarregar={() => porFiltros({ ...filtros, canal: 'todos', estado: 'todos' })} />
                    <CartaoNumero rotulo={t('Activos')} valor={numero(resumo.activos)} icone="fa-circle-check" tom="verde"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'activos' })} />
                    <CartaoNumero rotulo={t('Por e-mail')} valor={numero(resumo.email)} icone="fa-envelope" tom="indigo"
                        aoCarregar={() => porFiltros({ ...filtros, canal: 'email' })} />
                    <CartaoNumero
                        rotulo={t('Sem canal')} valor={numero(resumo.sem_canal)} icone="fa-plug-circle-xmark"
                        tom={resumo.sem_canal > 0 ? 'ambar' : 'cinza'}
                        nota={t('Um modelo sem canal ligado nunca manda nada')}
                    />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value })}
                            placeholder={t('Nome ou descrição…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Módulo')} className="w-52">
                    <select value={filtros.modulo ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, modulo: e.target.value })} className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        {o.modulos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Canal')} className="w-40">
                    <select value={filtros.canal ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, canal: e.target.value })} className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        <option value="email">{t('E-mail')}</option>
                        <option value="sms">{t('SMS')}</option>
                        <option value="whatsapp">{t('WhatsApp')}</option>
                    </select>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-40">
                    <select value={filtros.estado ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value })} className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        <option value="activos">{t('Activos')}</option>
                        <option value="inactivos">{t('Desactivados')}</option>
                    </select>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-file-lines"
                    titulo={t('Nenhum modelo')}
                    frase={t('Um modelo é o texto de um aviso — o assunto, o corpo, e as variáveis que o sistema preenche sozinho.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                            {t('Novo modelo')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <ul className="space-y-2">
                    {lista.data.data.map((x, i) => (
                        <li key={x.id} style={cascata(i)}
                            className={cls('entra flex flex-wrap items-center gap-3 p-4 transition hover:shadow-md', CARTAO)}>
                            <span className={cls(
                                'grid h-10 w-10 flex-none place-items-center rounded-xl',
                                x.canais.length === 0 ? 'bg-amber-50 text-amber-600'
                                    : x.activo ? 'bg-teal-50 text-teal-600' : 'bg-slate-100 text-slate-400',
                            )}>
                                <i className="fas fa-file-lines" aria-hidden="true" />
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-slate-800">{x.nome}</p>
                                <p className="truncate text-xs text-slate-500">
                                    {[x.modulo_rotulo, x.evento_rotulo].filter(Boolean).join(' · ')}
                                </p>
                            </div>

                            {x.canais.length === 0 ? (
                                <Etiqueta cor="aviso" icone="fa-plug-circle-xmark">{t('Sem canal')}</Etiqueta>
                            ) : x.canais.map((c) => (
                                <Etiqueta key={c} cor={COR_DO_CANAL[c] ?? 'neutra'} icone={ICONE_DO_CANAL[c]}>
                                    {ROTULO_DO_CANAL(c)}
                                </Etiqueta>
                            ))}

                            {/* UM MODELO ACTIVO QUE NUNCA DISPARA é pior do que
                                um desligado: parece que está a funcionar. */}
                            {!x.dispara && (
                                <span title={x.porque_nao_dispara ?? undefined}>
                                    <Etiqueta cor="aviso" icone="fa-circle-info">{t('Ainda não dispara')}</Etiqueta>
                                </span>
                            )}

                            <Etiqueta cor={x.activo ? 'bom' : 'neutra'} ponto>
                                {x.activo ? t('Activo') : t('Desactivado')}
                            </Etiqueta>

                            <div className="flex flex-none items-center gap-1.5">
                                {podeTestar && x.canais.length > 0 && (
                                    <Botao altura="pequeno" icone="fa-paper-plane" onClick={() => abrirTeste(x)}>
                                        {t('Testar')}
                                    </Botao>
                                )}
                                {pode && (
                                    <>
                                        <Botao altura="pequeno" icone="fa-pen"
                                            aTrabalhar={abrirFicha.isPending && abrirFicha.variables === x.id}
                                            onClick={() => abrirFicha.mutate(x.id)}
                                            aria-label={t('Editar modelo')} />
                                        <Botao altura="pequeno" cor={x.activo ? 'aviso' : 'bom'}
                                            icone={x.activo ? 'fa-pause' : 'fa-play'}
                                            aTrabalhar={estado.isPending}
                                            onClick={() => estado.mutate(x.id)}
                                            aria-label={x.activo ? t('Desactivar') : t('Activar')} />
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                            onClick={() => porAApagar(x)}
                                            aria-label={t('Eliminar modelo')} />
                                    </>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {/* ─── O editor ──────────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={fechar}
                titulo={aEditar ? t('Editar modelo') : t('Novo modelo')}
                subtitulo={t('As variáveis entre chavetas são preenchidas pelo sistema')}
                icone="fa-file-lines"
                cor="ciano"
                largura="xl"
                rodape={
                    <>
                        <Botao onClick={fechar}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check"
                            aTrabalhar={guardar.isPending} onClick={submeter}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                <input type="text" value={formulario.name}
                                    onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Módulo')} obrigatorio erro={erros.module}
                                ajuda={t('Decide as variáveis que o sistema sabe preencher.')}>
                                <select value={formulario.module}
                                    onChange={(e) => porFormulario({ ...formulario, module: e.target.value })}
                                    className={entrada}>
                                    {o.modulos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Quando')} obrigatorio erro={erros.trigger_event}>
                                <select value={formulario.trigger_event}
                                    onChange={(e) => porFormulario({ ...formulario, trigger_event: e.target.value })}
                                    className={entrada}>
                                    {o.eventos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>

                            {formulario.trigger_event === 'date_approaching' && (
                                <Campo etiqueta={t('Quantos minutos antes')} erro={erros.notify_before_minutes}
                                    ajuda={t('1440 minutos é um dia antes.')}>
                                    <input type="number" min="0" value={formulario.notify_before_minutes}
                                        onChange={(e) => porFormulario({ ...formulario, notify_before_minutes: e.target.value })}
                                        className={cls(entrada, 'tabular-nums')} />
                                </Campo>
                            )}

                            <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-2">
                                <input type="text" value={formulario.description}
                                    onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        {/* AS VARIÁVEIS DO MÓDULO, à vista de quem escreve. */}
                        {variaveisDoModulo.length > 0 && (
                            <div className={cls('border border-cyan-200 bg-cyan-50 p-3', RAIO)}>
                                <p className="mb-2 text-xs font-bold text-cyan-900">
                                    <i className="fas fa-code mr-1.5" aria-hidden="true" />
                                    {t('Variáveis deste módulo — carregue para copiar')}
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {variaveisDoModulo.map((v) => (
                                        <button key={v.chave} type="button"
                                            title={v.rotulo}
                                            onClick={() => void navigator.clipboard?.writeText(`{{${v.chave}}}`)}
                                            className={cls(
                                                'rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-cyan-800 ring-1 ring-cyan-200 transition hover:bg-cyan-100',
                                                FOCO,
                                            )}>
                                            {`{{${v.chave}}}`}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* ── E-mail ── */}
                        <CanalDoModelo
                            icone="fa-envelope" tom="indigo" rotulo={t('E-mail')}
                            ligado={formulario.email_enabled}
                            aoLigar={(v) => porFormulario({ ...formulario, email_enabled: v })}
                        >
                            <Campo etiqueta={t('Assunto')} obrigatorio erro={erros.email_subject}>
                                <input type="text" value={formulario.email_subject}
                                    onChange={(e) => porFormulario({ ...formulario, email_subject: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Corpo')} obrigatorio erro={erros.email_body}>
                                <textarea rows={6} value={formulario.email_body}
                                    onChange={(e) => porFormulario({ ...formulario, email_body: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </CanalDoModelo>

                        {/* ── SMS ── */}
                        <CanalDoModelo
                            icone="fa-comment-sms" tom="ambar" rotulo={t('SMS')}
                            ligado={formulario.sms_enabled}
                            aoLigar={(v) => porFormulario({ ...formulario, sms_enabled: v })}
                        >
                            <Campo
                                etiqueta={t('Texto')}
                                obrigatorio
                                erro={erros.sms_body}
                                // O SMS conta-se em caracteres e paga-se por
                                // mensagem: acima de 160 são duas.
                                ajuda={t(':n caracteres — acima de 160 são duas mensagens.', {
                                    n: String(formulario.sms_body.length),
                                })}
                            >
                                <textarea rows={3} value={formulario.sms_body}
                                    onChange={(e) => porFormulario({ ...formulario, sms_body: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </CanalDoModelo>

                        {/* ── WhatsApp ── */}
                        <CanalDoModelo
                            icone="fa-comment-dots" tom="teal" rotulo={t('WhatsApp')}
                            ligado={formulario.whatsapp_enabled}
                            aoLigar={(v) => porFormulario({ ...formulario, whatsapp_enabled: v })}
                        >
                            <Campo
                                etiqueta={t('Modelo aprovado (SID)')}
                                obrigatorio
                                erro={erros.whatsapp_template_sid}
                                ajuda={t('O WhatsApp só manda modelos aprovados pelo fornecedor. Traga-os nas definições de canais.')}
                            >
                                <input type="text" value={formulario.whatsapp_template_sid}
                                    onChange={(e) => porFormulario({ ...formulario, whatsapp_template_sid: e.target.value })}
                                    placeholder="HX…" className={cls(entrada, 'font-mono text-xs')} />
                            </Campo>
                        </CanalDoModelo>

                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" checked={formulario.is_active}
                                onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                className="h-5 w-5 rounded text-teal-600" />
                            {t('Modelo activo')}
                            <span className="text-xs text-slate-400">
                                {t('— um modelo desactivado fica guardado e não manda nada.')}
                            </span>
                        </label>
                    </div>
                )}
            </Modal>

            {/* ─── O teste ───────────────────────────────────────────── */}

            <Modal
                aberto={aTestar !== null}
                aoFechar={fecharTeste}
                titulo={t('Enviar um teste')}
                subtitulo={teste.data?.data.nome}
                icone="fa-paper-plane"
                cor="ciano"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={fecharTeste}>{t('Fechar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane"
                            disabled={canaisDoTeste.length === 0}
                            aTrabalhar={enviarTeste.isPending}
                            onClick={() => enviarTeste.mutate()}>
                            {t('Enviar')}
                        </Botao>
                    </>
                }
            >
                {teste.isPending ? (
                    <Carregando linhas={5} />
                ) : teste.isError ? (
                    <AvisoDeErro erro={teste.error} />
                ) : teste.data ? (
                    <div className="space-y-4">
                        <AvisoDeErro erro={enviarTeste.error} />

                        <p className={cls('border border-cyan-200 bg-cyan-50 p-3 text-sm text-cyan-900', RAIO)}>
                            <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                            {t('O teste sai pelo mesmo caminho do envio a sério — mesmas credenciais, mesmo texto, mesma operadora.')}
                        </p>

                        <div>
                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                {t('Por que canais')}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {teste.data.data.canais.map((c) => (
                                    <label key={c} className={cls(
                                        'inline-flex cursor-pointer items-center gap-2 border px-3 py-2 text-sm font-medium transition',
                                        RAIO,
                                        canaisDoTeste.includes(c)
                                            ? 'border-cyan-300 bg-cyan-50 text-cyan-800'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                                    )}>
                                        <input type="checkbox" checked={canaisDoTeste.includes(c)}
                                            onChange={() => porCanaisDoTeste(
                                                canaisDoTeste.includes(c)
                                                    ? canaisDoTeste.filter((x) => x !== c)
                                                    : [...canaisDoTeste, c],
                                            )}
                                            className="h-4 w-4 rounded text-cyan-600" />
                                        <i className={`fas ${ICONE_DO_CANAL[c]}`} aria-hidden="true" />
                                        {ROTULO_DO_CANAL(c)}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {canaisDoTeste.includes('email') && (
                                <Campo etiqueta={t('Para que e-mail')} obrigatorio>
                                    <input type="email" value={destino.email}
                                        onChange={(e) => porDestino({ ...destino, email: e.target.value })}
                                        className={entrada} />
                                </Campo>
                            )}
                            {(canaisDoTeste.includes('sms') || canaisDoTeste.includes('whatsapp')) && (
                                <Campo etiqueta={t('Para que número')} obrigatorio
                                    ajuda={t('Número angolano, com ou sem indicativo.')}>
                                    <input type="tel" value={destino.telefone}
                                        onChange={(e) => porDestino({ ...destino, telefone: e.target.value })}
                                        placeholder="923 000 000" className={entrada} />
                                </Campo>
                            )}
                        </div>

                        {/* AS VARIÁVEIS, já com dados de exemplo: um modelo
                            revê-se com o texto POSTO, não com as chavetas. */}
                        {Object.keys(variaveis).length > 0 && (
                            <div>
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                        {t('Variáveis')}
                                    </p>
                                    <Botao altura="pequeno" icone="fa-wand-magic-sparkles"
                                        onClick={() => {
                                            const cheias = { ...variaveis };

                                            for (const chave of Object.keys(cheias)) {
                                                cheias[chave] = teste.data.exemplo[chave] ?? '';
                                            }

                                            porVariaveis(cheias);
                                            rever.mutate(cheias);
                                        }}>
                                        {t('Pôr dados de exemplo')}
                                    </Botao>
                                    <Botao altura="pequeno" icone="fa-eraser"
                                        onClick={() => {
                                            const vazias = { ...variaveis };

                                            for (const chave of Object.keys(vazias)) vazias[chave] = '';

                                            porVariaveis(vazias);
                                            rever.mutate(vazias);
                                        }}>
                                        {t('Limpar')}
                                    </Botao>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    {Object.entries(variaveis).map(([chave, valor]) => (
                                        <Campo key={chave} etiqueta={chave}>
                                            <input type="text" value={valor}
                                                onChange={(e) => porVariaveis({ ...variaveis, [chave]: e.target.value })}
                                                onBlur={() => rever.mutate(variaveis)}
                                                className={entrada} />
                                        </Campo>
                                    ))}
                                </div>
                            </div>
                        )}

                        {previsao && (
                            <div className="space-y-2">
                                <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                    {t('Como vai ficar')}
                                </p>

                                {previsao.assunto && (
                                    <div className={cls('border border-slate-200 p-3', RAIO)}>
                                        <p className="text-[11px] font-bold uppercase text-slate-400">{t('Assunto')}</p>
                                        <p className="text-sm font-semibold text-slate-800">{previsao.assunto}</p>
                                    </div>
                                )}

                                {previsao.corpo && (
                                    <div className={cls('border border-slate-200 p-3', RAIO)}>
                                        <p className="text-[11px] font-bold uppercase text-slate-400">{t('Corpo')}</p>
                                        <p className="whitespace-pre-wrap text-sm text-slate-700">{previsao.corpo}</p>
                                    </div>
                                )}

                                {previsao.sms && (
                                    <div className={cls('border border-amber-200 bg-amber-50 p-3', RAIO)}>
                                        <p className="text-[11px] font-bold uppercase text-amber-600">{t('SMS')}</p>
                                        <p className="whitespace-pre-wrap text-sm text-amber-900">{previsao.sms}</p>
                                        <p className="mt-1 text-[11px] text-amber-600">
                                            {t(':n caracteres', { n: String(previsao.sms.length) })}
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                ) : null}
            </Modal>

            {/* ─── Eliminar ──────────────────────────────────────────── */}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar modelo')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('O modelo sai da lista e deixa de mandar avisos. O registo do que já saiu por ele fica — é o que permite saber o que foi enviado e quando.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

const TONS = {
    indigo: { texto: 'text-indigo-500', risca: 'border-indigo-200 bg-indigo-50/50' },
    ambar: { texto: 'text-amber-600', risca: 'border-amber-200 bg-amber-50/50' },
    teal: { texto: 'text-teal-500', risca: 'border-teal-200 bg-teal-50/50' },
} as const;

/** Um canal do modelo: o interruptor manda, e o conteúdo só aparece ligado. */
function CanalDoModelo({ icone, tom, rotulo, ligado, aoLigar, children }: {
    icone: string;
    tom: keyof typeof TONS;
    rotulo: string;
    ligado: boolean;
    aoLigar: (v: boolean) => void;
    children: React.ReactNode;
}) {
    return (
        <section className={cls(
            'border-2 p-4 transition-all duration-200',
            RAIO,
            ligado ? TONS[tom].risca : 'border-slate-200 bg-white',
        )}>
            <label className="flex cursor-pointer items-center gap-3">
                <input type="checkbox" checked={ligado} onChange={(e) => aoLigar(e.target.checked)}
                    className="h-5 w-5 rounded text-teal-600" />
                <i className={`fas ${icone} ${TONS[tom].texto}`} aria-hidden="true" />
                <span className="text-sm font-bold text-slate-800">{rotulo}</span>
            </label>

            {ligado && <div className="mt-4 space-y-4">{children}</div>}
        </section>
    );
}
