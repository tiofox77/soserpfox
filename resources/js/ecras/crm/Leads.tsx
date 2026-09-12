import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { crm, type Lead } from '@/api/crm';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS LEADS — quem ainda não é cliente mas pode vir a ser.
 *
 * O ecrã é uma FILA DE TRABALHO e não um arquivo. Criar é uma linha — nome,
 * telefone, origem, Enter — porque um formulário de vinte campos para registar
 * uma chamada perdida é a razão por que ninguém regista chamadas perdidas.
 *
 * Cada cartão tem os três destinos à vista: avançar no caminho, converter em
 * cliente, ou perder COM MOTIVO. «Perdido sem razão» não ensina nada; os
 * motivos somados são a única coisa que diz onde a casa perde negócios.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    novo: 'aviso',
    contactado: 'primaria',
    qualificado: 'primaria',
    convertido: 'bom',
    perdido: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    novo: 'fa-star',
    contactado: 'fa-phone',
    qualificado: 'fa-thumbs-up',
    convertido: 'fa-handshake',
    perdido: 'fa-xmark',
};

const VAZIO = { name: '', phone: '', email: '', company: '', source: 'telefone', notes: '' };

export default function Leads() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; estado?: string; origem?: string;
        por_pagina?: number; page?: number;
    }>({ estado: 'abertos', por_pagina: 24, page: 1 });

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);

    // A linha de criar vive no topo do ecrã, sempre aberta.
    const [linha, porLinha] = useState({ name: '', phone: '', source: 'telefone' });

    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aConverter, porAConverter] = useState<Lead | null>(null);
    const [conversao, porConversao] = useState({ title: '', amount: '' });
    const [aPerder, porAPerder] = useState<Lead | null>(null);
    const [motivo, porMotivo] = useState('');
    const [aActividade, porAActividade] = useState<Lead | null>(null);
    const [actividade, porActividade] = useState({ type: 'chamada', subject: '', notes: '' });
    const [aConversa, porAConversa] = useState<number | null>(null);

    const opcoes = useQuery({ queryKey: ['crm', 'leads', 'opcoes'], queryFn: () => crm.leads.opcoes() });

    const lista = useQuery({
        queryKey: ['crm', 'leads', filtros],
        queryFn: () => crm.leads.lista(filtros),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['crm'] });
    };

    const criarRapido = useMutation({
        mutationFn: () => crm.leads.guardar(null, linha),
        onSuccess: (r) => { feito(r.message); porLinha({ name: '', phone: '', source: linha.source }); },
        onError: porErro,
    });

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => crm.leads.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const avancar = useMutation({
        mutationFn: (id: number) => crm.leads.avancar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const converter = useMutation({
        mutationFn: () => crm.leads.converter(
            aConverter!.id, conversao.title, Number(conversao.amount || 0),
        ),
        onSuccess: (r) => { feito(r.message); porAConverter(null); },
        onError: porErro,
    });

    const perder = useMutation({
        mutationFn: () => crm.leads.perder(aPerder!.id, motivo),
        onSuccess: (r) => { feito(r.message); porAPerder(null); porMotivo(''); },
        onError: porErro,
    });

    const reabrir = useMutation({
        mutationFn: (id: number) => crm.leads.reabrir(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const registar = useMutation({
        mutationFn: () => crm.leads.actividade(aActividade!.id, actividade),
        onSuccess: (r) => {
            feito(r.message);
            porAActividade(null);
            porActividade({ type: 'chamada', subject: '', notes: '' });
        },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Leads')}
                subtitulo={t('Quem ainda não é cliente mas pode vir a ser')}
                icone="fa-user-plus"
                cor="ciano"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => { porAEditar(null); porFormulario({ ...VAZIO }); }}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Lead com ficha completa')}
                            </button>
                        )}
                        <a href="/crm/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-bullseye" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-user-clock">
                            {t(':n em jogo', { n: numero(resumo.abertos) })}
                        </EstadoNaFaixa>
                        <EstadoNaFaixa icone="fa-handshake">
                            {t(':n convertidos', { n: numero(resumo.convertidos) })}
                        </EstadoNaFaixa>
                    </div>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {/*
              * A LINHA DE CRIAR fica SEMPRE ABERTA no topo.
              *
              * Não é um botão que abre uma janela: quem está ao telefone escreve
              * o nome, o número e carrega em Enter. Tudo o resto pode esperar.
              */}
            {pode && (
                <form
                    className={cls('flex flex-wrap items-end gap-3 p-4', CARTAO)}
                    onSubmit={(e) => { e.preventDefault(); if (linha.name.trim()) criarRapido.mutate(); }}
                >
                    <Campo etiqueta={t('Nome')} className="min-w-[12rem] flex-1">
                        <input type="text" value={linha.name}
                            onChange={(e) => porLinha({ ...linha, name: e.target.value })}
                            placeholder={t('Quem ligou…')} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Telefone')} className="w-44">
                        <input type="text" value={linha.phone}
                            onChange={(e) => porLinha({ ...linha, phone: e.target.value })}
                            className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Origem')} className="w-44">
                        <select value={linha.source}
                            onChange={(e) => porLinha({ ...linha, source: e.target.value })}
                            className={entrada}>
                            {o.origens.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Botao type="submit" cor="primaria" tom="solida" icone="fa-plus"
                        aTrabalhar={criarRapido.isPending} disabled={!linha.name.trim()}>
                        {t('Pôr na fila')}
                    </Botao>
                </form>
            )}

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero
                        rotulo={t('Em jogo')} valor={numero(resumo.abertos)} icone="fa-user-clock" tom="teal"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'abertos', page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Novos')} valor={numero(resumo.novos)} icone="fa-star" tom="ambar"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'novo', page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Convertidos')} valor={numero(resumo.convertidos)} icone="fa-handshake" tom="verde"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'convertido', page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Perdidos')} valor={numero(resumo.perdidos)} icone="fa-xmark" tom="vermelho"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'perdido', page: 1 })}
                    />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Nome, empresa, telefone ou e-mail…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-48">
                    <select value={filtros.estado ?? 'abertos'}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value, page: 1 })}
                        className={entrada}>
                        <option value="abertos">{t('Em jogo')}</option>
                        <option value="todos">{t('Todos')}</option>
                        {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Origem')} className="w-44">
                    <select value={filtros.origem ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, origem: e.target.value || undefined, page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todas')}</option>
                        {o.origens.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-user-plus"
                    titulo={t('Nenhum lead')}
                    frase={t('Quem liga, quem passa, quem manda mensagem — tudo isso é um lead enquanto não for cliente.')}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {lista.data.data.map((l, i) => (
                        <article key={l.id} style={cascata(i)}
                            className={cls('entra flex flex-col gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO)}>
                            <div className="flex items-start gap-3">
                                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-cyan-50 text-cyan-600">
                                    <i className="fas fa-user" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-slate-800">{l.nome}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {l.empresa ?? t('Sem empresa')}
                                    </p>
                                </div>
                                <Etiqueta cor={COR_DO_ESTADO[l.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[l.estado]}>
                                    {l.estado_rotulo}
                                </Etiqueta>
                            </div>

                            <dl className="space-y-1 text-xs text-slate-600">
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-phone w-4 text-cyan-600" aria-hidden="true" />
                                    <span className="truncate">{l.telefone ?? t('Sem telefone')}</span>
                                </div>
                                {l.email && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-envelope w-4 text-slate-400" aria-hidden="true" />
                                        <span className="truncate">{l.email}</span>
                                    </div>
                                )}
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-signs-post w-4 text-slate-400" aria-hidden="true" />
                                    <span className="truncate">{l.origem_rotulo}</span>
                                </div>
                                {l.cliente && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-handshake w-4 text-emerald-500" aria-hidden="true" />
                                        <span className="truncate">{l.cliente}</span>
                                    </div>
                                )}
                                {l.motivo_da_perda && (
                                    <div className="flex items-start gap-2 text-red-600">
                                        <i className="fas fa-circle-info w-4 flex-none pt-0.5" aria-hidden="true" />
                                        <span className="min-w-0">{l.motivo_da_perda}</span>
                                    </div>
                                )}
                            </dl>

                            <div className="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                                <Botao altura="pequeno" icone="fa-comments" onClick={() => porAConversa(l.id)}>
                                    {t(':n', { n: String(l.actividades) })}
                                </Botao>

                                {pode && (
                                    <>
                                        {l.seguinte && (
                                            <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-arrow-right"
                                                aTrabalhar={avancar.isPending} onClick={() => avancar.mutate(l.id)}>
                                                {o.estados.find((e) => e.valor === l.seguinte)?.rotulo ?? t('Avançar')}
                                            </Botao>
                                        )}

                                        {l.estado !== 'convertido' && l.estado !== 'perdido' && (
                                            <>
                                                <Botao altura="pequeno" cor="bom" icone="fa-handshake"
                                                    onClick={() => {
                                                        porAConverter(l);
                                                        porConversao({
                                                            title: t('Proposta para :nome', { nome: l.empresa || l.nome }),
                                                            amount: '',
                                                        });
                                                    }}>
                                                    {t('Converter')}
                                                </Botao>
                                                <Botao altura="pequeno" cor="perigo" icone="fa-xmark"
                                                    onClick={() => { porAPerder(l); porMotivo(''); }}
                                                    aria-label={t('Dar por perdido')} />
                                            </>
                                        )}

                                        {l.estado === 'perdido' && (
                                            <Botao altura="pequeno" icone="fa-rotate-left"
                                                aTrabalhar={reabrir.isPending} onClick={() => reabrir.mutate(l.id)}>
                                                {t('Reabrir')}
                                            </Botao>
                                        )}

                                        <Botao altura="pequeno" icone="fa-clipboard-list"
                                            onClick={() => porAActividade(l)} aria-label={t('Registar actividade')} />
                                        <Botao altura="pequeno" icone="fa-pen"
                                            onClick={() => {
                                                porAEditar(l.id);
                                                porFormulario({
                                                    name: l.nome, phone: l.telefone ?? '', email: l.email ?? '',
                                                    company: l.empresa ?? '', source: l.origem, notes: l.notas ?? '',
                                                });
                                            }}
                                            aria-label={t('Editar lead')} />
                                    </>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            )}

            {meta && meta.last_page > 1 && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-slate-500">
                        {t('A mostrar :de a :ate de :total', {
                            de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                        })}
                    </p>
                    <div className="flex items-center gap-2">
                        <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
                        <Botao altura="pequeno" icone="fa-chevron-left" disabled={meta.current_page <= 1}
                            onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                            aria-label={t('Página anterior')} />
                        <span className="text-xs font-semibold tabular-nums text-slate-600">
                            {meta.current_page}/{meta.last_page}
                        </span>
                        <Botao altura="pequeno" icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page}
                            onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                            aria-label={t('Página seguinte')} />
                    </div>
                </div>
            )}

            {/* ─── A ficha completa ───────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar lead') : t('Lead com ficha completa')}
                icone="fa-user-plus"
                cor="ciano"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate(formulario)}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                                <input type="text" value={formulario.phone}
                                    onChange={(e) => porFormulario({ ...formulario, phone: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('E-mail')} erro={erros.email}>
                                <input type="email" value={formulario.email}
                                    onChange={(e) => porFormulario({ ...formulario, email: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Empresa')} erro={erros.company}>
                                <input type="text" value={formulario.company}
                                    onChange={(e) => porFormulario({ ...formulario, company: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Origem')} obrigatorio erro={erros.source}
                                ajuda={t('É daqui que sai o gráfico de onde vale a pena gastar.')}>
                                <select value={formulario.source}
                                    onChange={(e) => porFormulario({ ...formulario, source: e.target.value })}
                                    className={entrada}>
                                    {o.origens.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Notas')} erro={erros.notes}>
                            <textarea rows={3} value={formulario.notes}
                                onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            {/* ─── Converter ──────────────────────────────────────────── */}

            <Modal
                aberto={aConverter !== null}
                aoFechar={() => porAConverter(null)}
                titulo={t('Converter em cliente')}
                subtitulo={t('Nasce o cliente e, com ele, a primeira oportunidade')}
                icone="fa-handshake"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAConverter(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={converter.isPending}
                            onClick={() => converter.mutate()}>
                            {t('Converter')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <AvisoDeErro erro={converter.error} />

                    <Campo etiqueta={t('Título da oportunidade')} obrigatorio>
                        <input type="text" value={conversao.title}
                            onChange={(e) => porConversao({ ...conversao, title: e.target.value })}
                            className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Valor estimado')}
                        ajuda={t('Pode ficar a zero — o valor afina-se no funil.')}>
                        <input type="number" step="0.01" min="0" value={conversao.amount}
                            onChange={(e) => porConversao({ ...conversao, amount: e.target.value })}
                            className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                </div>
            </Modal>

            {/* ─── Perder ─────────────────────────────────────────────── */}

            <Modal
                aberto={aPerder !== null}
                aoFechar={() => porAPerder(null)}
                titulo={t('Dar por perdido')}
                subtitulo={aPerder?.nome}
                icone="fa-xmark"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAPerder(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-check" aTrabalhar={perder.isPending}
                            disabled={motivo.trim().length < 3} onClick={() => perder.mutate()}>
                            {t('Registar')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <AvisoDeErro erro={perder.error} />

                    <Campo etiqueta={t('Motivo')} obrigatorio
                        ajuda={t('«Perdido sem razão» não ensina nada. Os motivos somados dizem onde se perde.')}>
                        <textarea rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)}
                            placeholder={t('Preço, prazo, foi a outro fornecedor…')} className={entrada} />
                    </Campo>
                </div>
            </Modal>

            {/* ─── A actividade ───────────────────────────────────────── */}

            <Modal
                aberto={aActividade !== null}
                aoFechar={() => porAActividade(null)}
                titulo={t('Registar no histórico')}
                subtitulo={aActividade?.nome}
                icone="fa-clipboard-list"
                cor="ciano"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAActividade(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={registar.isPending}
                            onClick={() => registar.mutate()}>
                            {t('Registar')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <AvisoDeErro erro={registar.error} />

                    <Campo etiqueta={t('Tipo')} obrigatorio>
                        <select value={actividade.type}
                            onChange={(e) => porActividade({ ...actividade, type: e.target.value })}
                            className={entrada}>
                            {o.tipos_de_actividade.map((x) => (
                                <option key={x.valor} value={x.valor}>{x.rotulo}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Assunto')} obrigatorio
                        ajuda={t('Falar com um lead novo passa-o sozinho a contactado.')}>
                        <input type="text" value={actividade.subject}
                            onChange={(e) => porActividade({ ...actividade, subject: e.target.value })}
                            className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Notas')}>
                        <textarea rows={3} value={actividade.notes}
                            onChange={(e) => porActividade({ ...actividade, notes: e.target.value })}
                            className={entrada} />
                    </Campo>
                </div>
            </Modal>

            <Conversa id={aConversa} aoFechar={() => porAConversa(null)} aoFeito={feito} />
        </div>
    );
}

/* ─── A conversa ──────────────────────────────────────────────────────── */

/**
 * O HISTÓRICO DO LEAD, e responder por WhatsApp sem sair da fila.
 *
 * O botão de responder só aparece quando a empresa tem a ligação ao Meta ligada
 * E o lead tem número — senão era um campo que escrevia para o vazio.
 */
function Conversa({
    id, aoFechar, aoFeito,
}: {
    id: number | null;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const cache = useQueryClient();
    const [texto, porTexto] = useState('');

    const conversa = useQuery({
        queryKey: ['crm', 'leads', 'conversa', id],
        queryFn: () => crm.leads.conversa(id as number),
        enabled: id !== null,
    });

    const responder = useMutation({
        mutationFn: () => crm.leads.responder(id as number, texto),
        onSuccess: (r) => {
            porTexto('');
            aoFeito(r.message);
            void cache.invalidateQueries({ queryKey: ['crm', 'leads', 'conversa', id] });
        },
    });

    const c = conversa.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={c?.data.nome ?? t('Conversa')}
            subtitulo={c?.numero ?? undefined}
            icone="fa-comments"
            cor="ciano"
            largura="md"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {conversa.isPending ? (
                <Carregando linhas={5} />
            ) : conversa.isError ? (
                <AvisoDeErro erro={conversa.error} />
            ) : c ? (
                <div className="space-y-4">
                    {c.actividades.length === 0 ? (
                        <SemNada
                            icone="fa-comments"
                            titulo={t('Sem histórico')}
                            frase={t('Cada chamada, visita ou mensagem registada aparece aqui, por ordem.')}
                        />
                    ) : (
                        <ol className="space-y-2">
                            {c.actividades.map((a, i) => (
                                <li key={a.id} style={cascata(i)}
                                    className={cls(
                                        'entra border px-3 py-2', RAIO,
                                        a.sentido === 'out'
                                            ? 'ml-8 border-cyan-200 bg-cyan-50'
                                            : 'mr-8 border-slate-200 bg-white',
                                    )}>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-xs font-semibold text-slate-700">
                                            {a.tipo_rotulo} · {a.assunto}
                                        </span>
                                        <span className="flex-none text-[11px] tabular-nums text-slate-500">
                                            {a.quando}
                                        </span>
                                    </div>
                                    {a.notas && <p className="mt-1 text-sm text-slate-700">{a.notas}</p>}
                                </li>
                            ))}
                        </ol>
                    )}

                    {c.pode_whatsapp ? (
                        <form
                            className="flex items-end gap-2 border-t border-slate-100 pt-4"
                            onSubmit={(e) => { e.preventDefault(); if (texto.trim()) responder.mutate(); }}
                        >
                            <Campo etiqueta={t('Responder por WhatsApp')} className="flex-1">
                                <input type="text" value={texto} onChange={(e) => porTexto(e.target.value)}
                                    className={entrada} />
                            </Campo>
                            <Botao type="submit" cor="bom" tom="solida" icone="fa-paper-plane"
                                aTrabalhar={responder.isPending} disabled={!texto.trim()}>
                                {t('Enviar')}
                            </Botao>
                        </form>
                    ) : (
                        <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500', RAIO)}>
                            <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                            {t('Sem WhatsApp para onde responder: falta a ligação ao Meta ou o número do lead.')}
                        </p>
                    )}

                    <AvisoDeErro erro={responder.error} />
                </div>
            ) : null}
        </Modal>
    );
}
