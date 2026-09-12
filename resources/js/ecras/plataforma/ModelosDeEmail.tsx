import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type FichaDoModeloDeEmail, type ModeloDeEmail, ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, dataHora, haQuanto } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { BotaoDeIcone, Confirmar, ErroDoEcra, Interruptor, Paginas, Recado } from './comum';

/**
 * OS MODELOS DE EMAIL DA PLATAFORMA.
 *
 * O que mudou em relação ao Blade:
 *
 *  · APAGAR PERGUNTA ANTES, e diz quando o modelo foi usado pela última vez —
 *    o botão apagava à primeira, e um modelo que o sistema pede calava esse
 *    email sem erro nenhum.
 *  · O IDENTIFICADOR NÃO SE MUDA DEPOIS DE CRIADO: é por ele que o sistema pede
 *    o modelo.
 *  · AS VARIÁVEIS SÃO BOTÕES que se inserem onde está o cursor, em vez de uma
 *    lista para copiar à mão.
 *  · A PRÉ-VISUALIZAÇÃO corre numa moldura isolada: o HTML do modelo não
 *    executa nada dentro do painel.
 */
export default function ModelosDeEmail() {
    const fila = useQueryClient();
    const [filtros, porFiltros] = useState<{ procura?: string; pagina: number }>({ pagina: 1 });
    const [recado, porRecado] = useState<string | null>(null);
    const [aEditar, porAEditar] = useState<number | 'novo' | null>(null);
    const [aVer, porAVer] = useState<ModeloDeEmail | null>(null);
    const [aTestar, porATestar] = useState<ModeloDeEmail | null>(null);
    const [aApagar, porAApagar] = useState<ModeloDeEmail | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'modelos-de-email', filtros],
        queryFn: () => ferramentas.modelosDeEmail.ler(filtros),
        placeholderData: keepPreviousData,
    });

    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'modelos-de-email'] }); };

    const alternar = useMutation({ mutationFn: (id: number) => ferramentas.modelosDeEmail.alternar(id), onSuccess: (r) => feito(r.message) });
    const apagar = useMutation({ mutationFn: (id: number) => ferramentas.modelosDeEmail.apagar(id), onSuccess: (r) => { porAApagar(null); feito(r.message); } });

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <ErroDoEcra titulo={t('Não foi possível abrir os modelos de email')} erro={lista.error} />;

    const d = lista.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Email Templates')}
                subtitulo={t('Gerencie os templates de email do sistema')}
                icone="fa-envelopes-bulk"
                cor="primaria"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAEditar('novo')}>
                        <i className="fas fa-plus" aria-hidden="true" />{t('Novo Template')}
                    </button>
                }
            >
                <EstadoNaFaixa icone="fa-layer-group">{t(':n modelos', { n: d.paginacao.total })}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={alternar.error} />

            <div className={cls(CARTAO, 'p-4')}>
                <label className="relative block">
                    <span className="sr-only">{t('Pesquisar templates...')}</span>
                    <i className="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input type="search" className={cls(entrada, 'pl-9')} placeholder={t('Pesquisar templates...')} value={filtros.procura ?? ''}
                        onChange={(e) => porFiltros({ procura: e.target.value || undefined, pagina: 1 })} />
                </label>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {d.modelos.length === 0 ? <SemNada icone="fa-envelope-open" frase={t('Nenhum template encontrado')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gradient-to-r from-blue-600 to-purple-600 text-left text-xs uppercase tracking-wider text-white">
                                <tr>
                                    <th className="px-5 py-3">{t('Template')}</th>
                                    <th className="px-5 py-3">{t('Slug')}</th>
                                    <th className="px-5 py-3">{t('Assunto')}</th>
                                    <th className="px-5 py-3">{t('Último envio')}</th>
                                    <th className="px-5 py-3 text-center">{t('Status')}</th>
                                    <th className="px-5 py-3 text-center">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.modelos.map((m, i) => (
                                    <tr key={m.id} className={cls('cascata hover:bg-indigo-50/40', TRANSICAO)} style={cascata(i)}>
                                        <td className="px-5 py-3">
                                            <p className="font-bold text-slate-900">{m.nome}</p>
                                            {m.descricao && <p className="mt-1 text-xs text-slate-500">{m.descricao}</p>}
                                        </td>
                                        <td className="px-5 py-3"><code className="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{m.slug}</code></td>
                                        <td className="max-w-xs truncate px-5 py-3 text-slate-700" title={m.assunto}>{m.assunto}</td>
                                        <td className="whitespace-nowrap px-5 py-3 text-xs text-slate-500">
                                            {m.ultimo_envio ? <span title={dataHora(m.ultimo_envio)}>{haQuanto(m.ultimo_envio)} · {t(':n envios', { n: m.envios })}</span> : t('Nunca enviado')}
                                        </td>
                                        <td className="px-5 py-3 text-center">
                                            <button type="button" onClick={() => alternar.mutate(m.id)} disabled={alternar.isPending}
                                                className={cls('rounded-full px-3 py-1 text-xs font-semibold', TRANSICAO, FOCO,
                                                    m.activo ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-700 hover:bg-slate-200')}>
                                                <i className={cls('fas mr-1', m.activo ? 'fa-check' : 'fa-xmark')} aria-hidden="true" />
                                                {m.activo ? t('Ativo') : t('Inativo')}
                                            </button>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-center gap-1">
                                                <BotaoDeIcone icone="fa-paper-plane" rotulo={t('Enviar Teste')} cor="text-emerald-600 hover:bg-emerald-50" onClick={() => porATestar(m)} />
                                                <BotaoDeIcone icone="fa-eye" rotulo={t('Preview')} cor="text-purple-600 hover:bg-purple-50" onClick={() => porAVer(m)} />
                                                <BotaoDeIcone icone="fa-pen-to-square" rotulo={t('Editar')} cor="text-blue-600 hover:bg-blue-50" onClick={() => porAEditar(m.id)} />
                                                <BotaoDeIcone icone="fa-trash" rotulo={t('Excluir')} cor="text-red-500 hover:bg-red-50" onClick={() => porAApagar(m)} />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="px-5 pb-4">
                    <Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={(p) => porFiltros((f) => ({ ...f, pagina: p }))} />
                </div>
            </section>

            {aEditar !== null && (
                <Formulario
                    id={aEditar === 'novo' ? null : aEditar}
                    variaveis={d.variaveis}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(m) => { porAEditar(null); feito(m); }}
                />
            )}

            {aVer && <Previsualizar modelo={aVer} aoFechar={() => porAVer(null)} />}

            {aTestar && (
                <Testar modelo={aTestar} email={d.o_meu_email ?? ''} aoFechar={() => porATestar(null)} aoEnviar={(m) => { porATestar(null); feito(m); }} />
            )}

            <Confirmar
                aberto={aApagar !== null}
                titulo={t('Excluir o template?')}
                subtitulo={aApagar?.nome}
                rotulo={t('Excluir')}
                aTrabalhar={apagar.isPending}
                erro={apagar.error}
                aoConfirmar={() => aApagar && apagar.mutate(aApagar.id)}
                aoFechar={() => porAApagar(null)}
            >
                <p>{t('O sistema pede os modelos pelo identificador. Se «:slug» ainda for pedido, esse email deixa de sair.', { slug: aApagar?.slug ?? '' })}</p>
                {aApagar?.ultimo_envio
                    ? <p className="rounded-lg bg-amber-50 p-3 font-semibold text-amber-800"><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{t('Usado pela última vez :quando.', { quando: haQuanto(aApagar.ultimo_envio) })}</p>
                    : <p className="text-slate-500">{t('Não há registo de nenhum envio com este modelo.')}</p>}
            </Confirmar>
        </div>
    );
}

const VAZIO: FichaDoModeloDeEmail = { slug: '', name: '', subject: '', body_html: '', body_text: '', description: '', is_active: true };

function Formulario({ id, variaveis, aoFechar, aoGuardar }: {
    id: number | null; variaveis: string[]; aoFechar: () => void; aoGuardar: (m: string) => void;
}) {
    const [f, porF] = useState<FichaDoModeloDeEmail | null>(id ? null : VAZIO);
    const corpo = useRef<HTMLTextAreaElement>(null);

    const ficha = useQuery({ queryKey: ['plataforma', 'modelos-de-email', 'ficha', id], queryFn: () => ferramentas.modelosDeEmail.ficha(id as number), enabled: id !== null });

    useEffect(() => { if (ficha.data) porF(ficha.data.ficha); }, [ficha.data]);

    const guardar = useMutation({ mutationFn: () => ferramentas.modelosDeEmail.guardar(id, { ...f }), onSuccess: (r) => aoGuardar(r.message) });
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};

    const inserir = (variavel: string) => {
        if (!f) return;
        const campo = corpo.current;
        const marca = `{${variavel}}`;
        const inicio = campo?.selectionStart ?? f.body_html.length;
        const fim = campo?.selectionEnd ?? f.body_html.length;
        porF({ ...f, body_html: f.body_html.slice(0, inicio) + marca + f.body_html.slice(fim) });
        requestAnimationFrame(() => { campo?.focus(); campo?.setSelectionRange(inicio + marca.length, inicio + marca.length); });
    };

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar Template') : t('Novo Template')}
            subtitulo={f?.name || undefined}
            icone={id ? 'fa-pen-to-square' : 'fa-plus'}
            cor="primaria"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} disabled={!f} onClick={() => guardar.mutate()}>
                        {id ? t('Atualizar') : t('Criar')}
                    </Botao>
                </div>
            }
        >
            {!f ? <Carregando linhas={6} /> : (
                <div className="space-y-4">
                    <AvisoDeErro erro={ficha.error ?? (Object.keys(erros).length ? null : guardar.error)} />

                    <div className="grid gap-4 md:grid-cols-2">
                        <Campo etiqueta={t('Nome do Template')} obrigatorio erro={erros.name}>
                            <input className={entrada} value={f.name} onChange={(e) => porF({ ...f, name: e.target.value })} placeholder={t('Email de Boas-vindas')} />
                        </Campo>
                        <Campo etiqueta={t('Slug')} obrigatorio={!id} erro={erros.slug}
                            ajuda={id ? t('O identificador não muda: é por ele que o sistema pede o modelo.') : t('Só letras minúsculas, números, hífen e sublinhado.')}>
                            <input className={cls(entrada, 'font-mono', id !== null && 'cursor-not-allowed bg-slate-100 text-slate-500')} value={f.slug} readOnly={Boolean(id)}
                                onChange={(e) => porF({ ...f, slug: e.target.value })} placeholder="welcome" />
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Assunto do Email')} obrigatorio erro={erros.subject}>
                        <input className={entrada} maxLength={255} value={f.subject} onChange={(e) => porF({ ...f, subject: e.target.value })} placeholder={t('Bem-vindo ao {app_name}!')} />
                    </Campo>

                    <Campo etiqueta={t('Descrição')} erro={erros.description}>
                        <textarea rows={2} className={entrada} value={f.description} onChange={(e) => porF({ ...f, description: e.target.value })} placeholder={t('Descrição do quando este template é usado...')} />
                    </Campo>

                    <div>
                        <div className={cls('mb-2 border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900', RAIO)}>
                            <p className="mb-2 font-semibold"><i className="fas fa-wand-magic-sparkles mr-1" aria-hidden="true" />{t('Variáveis disponíveis — carregue para inserir no conteúdo:')}</p>
                            <div className="flex flex-wrap gap-1.5">
                                {variaveis.map((v) => (
                                    <button key={v} type="button" onClick={() => inserir(v)}
                                        className={cls('rounded-md bg-white px-2 py-0.5 font-mono text-[11px] text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-100', TRANSICAO, FOCO)}>
                                        {`{${v}}`}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <Campo etiqueta={t('Conteúdo HTML')} obrigatorio erro={erros.body_html}>
                            <textarea ref={corpo} rows={15} className={cls(entrada, 'font-mono text-xs')} value={f.body_html} onChange={(e) => porF({ ...f, body_html: e.target.value })} />
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Conteúdo Texto (opcional)')} erro={erros.body_text} ajuda={t('Versão em texto puro para clientes de email que não suportam HTML')}>
                        <textarea rows={4} className={entrada} value={f.body_text} onChange={(e) => porF({ ...f, body_text: e.target.value })} />
                    </Campo>

                    <Interruptor rotulo={t('Template Ativo')} nota={t('Desligado, os envios que pedem este modelo não o encontram activo.')} valor={f.is_active} aoMudar={(v) => porF({ ...f, is_active: v })} />
                </div>
            )}
        </Modal>
    );
}

function Previsualizar({ modelo, aoFechar }: { modelo: ModeloDeEmail; aoFechar: () => void }) {
    const previa = useQuery({ queryKey: ['plataforma', 'modelos-de-email', 'previa', modelo.id], queryFn: () => ferramentas.modelosDeEmail.previsualizar(modelo.id) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Preview do Template')} subtitulo={modelo.nome} icone="fa-eye" cor="roxo" largura="xl"
            rodape={<div className="flex justify-end"><Botao cor="neutra" onClick={aoFechar}>{t('Fechar')}</Botao></div>}>
            {previa.isPending && <Carregando linhas={6} />}
            <AvisoDeErro erro={previa.error} />
            {previa.data && (
                <div className="space-y-4">
                    <div className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                        <p className="text-xs text-slate-500">{t('Assunto')}:</p>
                        <p className="text-lg font-bold text-slate-900">{previa.data.assunto}</p>
                    </div>
                    <iframe title={t('Preview do Template')} sandbox="" srcDoc={previa.data.html} className={cls('h-[32rem] w-full border border-slate-200 bg-white', RAIO)} />
                    {previa.data.texto && (
                        <div>
                            <p className="mb-2 text-sm font-semibold text-slate-700">{t('Versão Texto')}:</p>
                            <pre className={cls('whitespace-pre-wrap bg-slate-50 p-4 text-sm text-slate-700', RAIO)}>{previa.data.texto}</pre>
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Testar({ modelo, email: inicial, aoFechar, aoEnviar }: { modelo: ModeloDeEmail; email: string; aoFechar: () => void; aoEnviar: (m: string) => void }) {
    const [email, porEmail] = useState(inicial);
    const enviar = useMutation({ mutationFn: () => ferramentas.modelosDeEmail.enviarTeste(modelo.id, email), onSuccess: (r) => aoEnviar(r.message) });
    const erros = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Enviar Email de Teste')} subtitulo={modelo.nome} icone="fa-paper-plane" cor="bom" largura="sm"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar Teste')}</Botao>
                </div>
            }>
            <div className="space-y-4">
                <p className="text-sm text-slate-600">{t('Digite o email para onde deseja enviar o teste:')}</p>
                <Campo etiqueta={t('Email')} obrigatorio erro={erros.email}>
                    <input type="email" className={entrada} value={email} onChange={(e) => porEmail(e.target.value)} placeholder={t('seu-email@exemplo.com')} />
                </Campo>
                <AvisoDeErro erro={erros.email ? null : enviar.error} />
                <p className={cls('border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800', RAIO)}>
                    <i className="fas fa-circle-info mr-2" aria-hidden="true" />{t('O email será enviado com dados de exemplo para você visualizar como ficará.')}
                </p>
            </div>
        </Modal>
    );
}
