import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { suporte, type Pedido } from '@/api/suporte';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS PEDIDOS DE SUPORTE.
 *
 * UM PEDIDO É DE QUEM O ABRIU. A descrição de um problema leva lá dentro
 * números, nomes e capturas de ecrã da casa de quem escreve — por isso a lista
 * é a de cada um, e não a da empresa.
 *
 * O ESTADO «À ESPERA DE SI» existia desde sempre e não havia sítio nenhum onde
 * responder: o botão «Ver Detalhes» do ecrã antigo não fazia nada. Um pedido
 * parado à espera de uma resposta que não se podia escrever é um pedido morto.
 */

const COR_DO_ESTADO: Record<string, 'primaria' | 'bom' | 'aviso' | 'neutra' | 'perigo'> = {
    open: 'bom',
    in_progress: 'primaria',
    waiting_response: 'aviso',
    resolved: 'primaria',
    closed: 'neutra',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    open: 'fa-folder-open',
    in_progress: 'fa-spinner',
    waiting_response: 'fa-hand',
    resolved: 'fa-circle-check',
    closed: 'fa-lock',
};

const COR_DA_PRIORIDADE: Record<string, 'neutra' | 'primaria' | 'aviso' | 'perigo'> = {
    low: 'neutra',
    medium: 'primaria',
    high: 'aviso',
    urgent: 'perigo',
};

const VAZIO = { subject: '', description: '', priority: 'medium', category: 'other' };

export default function PedidosDeSuporte() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ estado?: string; procura?: string }>({ estado: 'todos' });
    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [imagens, porImagens] = useState<File[]>([]);
    const [aberto, porAberto] = useState<number | null>(null);
    const [resposta, porResposta] = useState('');

    const opcoes = useQuery({ queryKey: ['suporte', 'opcoes'], queryFn: () => suporte.opcoes() });
    const lista = useQuery({
        queryKey: ['suporte', 'pedidos', filtros],
        queryFn: () => suporte.pedidos.listar(filtros),
    });
    const ficha = useQuery({
        queryKey: ['suporte', 'pedido', aberto],
        queryFn: () => suporte.pedidos.ficha(aberto as number),
        enabled: aberto !== null,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['suporte'] });
    };

    const abrir = useMutation({
        mutationFn: () => suporte.pedidos.abrir(formulario as unknown as Record<string, string>, imagens),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porImagens([]); },
        onError: porErro,
    });

    const responder = useMutation({
        mutationFn: (texto: string) => suporte.pedidos.responder(aberto as number, texto),
        onSuccess: (r) => { feito(r.message); porResposta(''); },
        onError: porErro,
    });

    const fechar = useMutation({
        mutationFn: (id: number) => suporte.pedidos.fechar(id),
        onSuccess: (r) => { feito(r.message); porAberto(null); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={6} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const erros = (abrir.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const escolher = (ficheiros: FileList | null) => {
        // O tecto é dito, não imposto em silêncio: o ecrã antigo cortava a
        // lista sem uma palavra, e quem anexava seis nunca sabia porque é que
        // a sexta não estava lá.
        porImagens(Array.from(ficheiros ?? []).slice(0, o.maximo_de_imagens));
    };

    const emAberto = ['open', 'in_progress', 'waiting_response'];

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Pedidos de Suporte')}
                subtitulo={t('Os seus pedidos de ajuda, e o que o suporte respondeu')}
                icone="fa-life-ring"
                cor="roxo"
                accoes={
                    <>
                        <button type="button" className={ACCAO_DA_FAIXA}
                            onClick={() => { porFormulario({ ...VAZIO }); porImagens([]); }}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Novo pedido')}
                        </button>
                        <a href="/support/features" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-lightbulb" aria-hidden="true" />
                            {t('Quadro de Melhorias')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-folder-open">
                        {t(':n por resolver', {
                            n: numero((resumo?.abertos ?? 0) + (resumo?.em_curso ?? 0) + (resumo?.a_responder ?? 0)),
                        })}
                    </EstadoNaFaixa>
                    {(resumo?.a_responder ?? 0) > 0 && (
                        <EstadoNaFaixa icone="fa-hand">
                            {t(':n à espera de si', { n: numero(resumo?.a_responder ?? 0) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Abertos')} valor={numero(resumo.abertos)} icone="fa-folder-open" tom="verde"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'abertos' })} />
                    <CartaoNumero rotulo={t('Em curso')} valor={numero(resumo.em_curso)} icone="fa-spinner" tom="azul" />
                    <CartaoNumero rotulo={t('À espera de si')} valor={numero(resumo.a_responder)} icone="fa-hand"
                        tom={resumo.a_responder > 0 ? 'ambar' : 'cinza'}
                        nota={t('O suporte perguntou algo e ainda não teve resposta')} />
                    <CartaoNumero rotulo={t('Resolvidos')} valor={numero(resumo.resolvidos)} icone="fa-circle-check" tom="roxo"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'fechados' })} />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value })}
                            placeholder={t('Número, assunto ou descrição…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-44">
                    <select value={filtros.estado ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value })}
                        className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        <option value="abertos">{t('Por resolver')}</option>
                        <option value="fechados">{t('Resolvidos')}</option>
                    </select>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={5} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-life-ring"
                    titulo={t('Nenhum pedido')}
                    frase={t('Um pedido de suporte é a forma de alguém do outro lado ver o que se está a passar aqui — com o número, o ecrã e a captura.')}
                    accao={
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porFormulario({ ...VAZIO }); porImagens([]); }}>
                            {t('Novo pedido')}
                        </Botao>
                    }
                />
            ) : (
                <ul className="space-y-2">
                    {lista.data.data.map((x, i) => (
                        <li key={x.id} style={cascata(i)}
                            className={cls('entra p-4 transition hover:shadow-md', CARTAO)}>
                            <div className="flex flex-wrap items-center gap-3">
                                <span className={cls(
                                    'grid h-10 w-10 flex-none place-items-center rounded-xl',
                                    x.estado === 'waiting_response' ? 'bg-amber-50 text-amber-600'
                                        : x.estado === 'closed' ? 'bg-slate-100 text-slate-400'
                                            : 'bg-purple-50 text-purple-600',
                                )}>
                                    <i className={`fas ${ICONE_DO_ESTADO[x.estado] ?? 'fa-life-ring'}`} aria-hidden="true" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 truncate text-sm font-semibold text-slate-800">
                                        <span className="font-mono text-xs text-slate-400">{x.numero}</span>
                                        {x.assunto}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">{x.descricao}</p>
                                </div>

                                <Etiqueta cor={COR_DA_PRIORIDADE[x.prioridade] ?? 'neutra'} ponto>
                                    {x.prioridade_rotulo}
                                </Etiqueta>

                                <Etiqueta cor="neutra" icone="fa-folder">{x.categoria_rotulo}</Etiqueta>

                                {x.mensagens > 0 && (
                                    <Etiqueta cor="primaria" icone="fa-comments">{numero(x.mensagens)}</Etiqueta>
                                )}

                                {x.imagens.length > 0 && (
                                    <Etiqueta cor="neutra" icone="fa-images">{numero(x.imagens.length)}</Etiqueta>
                                )}

                                <Etiqueta cor={COR_DO_ESTADO[x.estado] ?? 'neutra'}>{x.estado_rotulo}</Etiqueta>

                                <Botao altura="pequeno" icone="fa-eye" onClick={() => { porAberto(x.id); porResposta(''); }}>
                                    {t('Ver')}
                                </Botao>
                            </div>

                            {x.imagens.length > 0 && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {x.imagens.slice(0, 4).map((url) => (
                                        <img key={url} src={url} alt=""
                                            className="h-14 w-14 rounded-lg border border-slate-200 object-cover" />
                                    ))}
                                    {x.imagens.length > 4 && (
                                        <span className="self-center text-xs text-slate-400">
                                            {t('+:n', { n: numero(x.imagens.length - 4) })}
                                        </span>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {/* ─── Abrir um pedido ───────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porImagens([]); }}
                titulo={t('Novo pedido de suporte')}
                subtitulo={t('Quanto mais concreto, mais depressa se resolve')}
                icone="fa-life-ring"
                cor="roxo"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porImagens([]); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane"
                            aTrabalhar={abrir.isPending} onClick={() => abrir.mutate()}>
                            {t('Enviar pedido')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={abrir.error} />

                        <Campo etiqueta={t('Assunto')} obrigatorio erro={erros.subject}>
                            <input type="text" value={formulario.subject}
                                onChange={(e) => porFormulario({ ...formulario, subject: e.target.value })}
                                placeholder={t('Descreva o problema numa linha…')} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Prioridade')} obrigatorio erro={erros.priority}>
                                <select value={formulario.priority}
                                    onChange={(e) => porFormulario({ ...formulario, priority: e.target.value })}
                                    className={entrada}>
                                    {o.prioridades.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Categoria')} obrigatorio erro={erros.category}>
                                <select value={formulario.category}
                                    onChange={(e) => porFormulario({ ...formulario, category: e.target.value })}
                                    className={entrada}>
                                    {o.categorias.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.description}
                            ajuda={t('O que fez, o que esperava, e o que apareceu. Se houver um número de documento, escreva-o.')}>
                            <textarea rows={6} value={formulario.description}
                                onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo
                            etiqueta={t('Imagens')}
                            erro={erros.images ?? erros['images.0']}
                            ajuda={t('Até :n imagens, 2 MB cada. Uma captura de ecrã vale por meia página de descrição.', {
                                n: String(o.maximo_de_imagens),
                            })}
                        >
                            <input type="file" multiple accept="image/*"
                                onChange={(e) => escolher(e.target.files)}
                                className={cls(entrada, 'file:mr-3 file:rounded-lg file:border-0 file:bg-purple-50 file:px-3 file:py-1.5 file:text-purple-700')} />
                        </Campo>

                        {imagens.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {imagens.map((f) => (
                                    <span key={f.name}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-2.5 py-1 text-xs text-purple-700">
                                        <i className="fas fa-image" aria-hidden="true" />
                                        {f.name}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* ─── O fio do pedido ───────────────────────────────────── */}

            <Modal
                aberto={aberto !== null}
                aoFechar={() => porAberto(null)}
                titulo={ficha.data?.data.numero ?? t('Pedido')}
                subtitulo={ficha.data?.data.assunto}
                icone="fa-comments"
                cor="roxo"
                largura="lg"
                rodape={
                    <>
                        {ficha.data && emAberto.includes(ficha.data.data.estado) && (
                            <Botao cor="perigo" icone="fa-lock" aTrabalhar={fechar.isPending}
                                onClick={() => aberto && fechar.mutate(aberto)}>
                                {t('Já não preciso')}
                            </Botao>
                        )}
                        <Botao onClick={() => porAberto(null)}>{t('Fechar')}</Botao>
                    </>
                }
            >
                {ficha.isPending ? (
                    <Carregando linhas={4} />
                ) : ficha.isError ? (
                    <AvisoDeErro erro={ficha.error} />
                ) : ficha.data ? (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Etiqueta cor={COR_DO_ESTADO[ficha.data.data.estado] ?? 'neutra'} ponto>
                                {ficha.data.data.estado_rotulo}
                            </Etiqueta>
                            <Etiqueta cor={COR_DA_PRIORIDADE[ficha.data.data.prioridade] ?? 'neutra'}>
                                {ficha.data.data.prioridade_rotulo}
                            </Etiqueta>
                            <Etiqueta cor="neutra" icone="fa-folder">{ficha.data.data.categoria_rotulo}</Etiqueta>
                            <span className="ml-auto text-xs text-slate-400">{ficha.data.data.quando}</span>
                        </div>

                        <p className={cls('whitespace-pre-wrap border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700', RAIO)}>
                            {ficha.data.data.descricao}
                        </p>

                        {ficha.data.data.imagens.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {ficha.data.data.imagens.map((url) => (
                                    <a key={url} href={url} target="_blank" rel="noreferrer">
                                        <img src={url} alt=""
                                            className="h-20 w-20 rounded-lg border border-slate-200 object-cover transition hover:scale-105" />
                                    </a>
                                ))}
                            </div>
                        )}

                        {/* O FIO: a pergunta do suporte e a resposta de quem pediu. */}
                        <ul className="space-y-2">
                            {ficha.data.fio.map((m) => (
                                <li key={m.id}
                                    className={cls(
                                        'border p-3 text-sm',
                                        RAIO,
                                        m.do_suporte
                                            ? 'border-purple-200 bg-purple-50 text-purple-900'
                                            : 'ml-8 border-slate-200 bg-white text-slate-700',
                                    )}>
                                    <p className="mb-1 flex items-center gap-2 text-xs font-semibold">
                                        <i className={`fas ${m.do_suporte ? 'fa-headset' : 'fa-user'}`} aria-hidden="true" />
                                        <span>{m.do_suporte ? t('Suporte') : (m.autor ?? t('Eu'))}</span>
                                        <span className="ml-auto font-normal text-slate-400">{m.quando}</span>
                                    </p>
                                    <p className="whitespace-pre-wrap">{m.texto}</p>
                                </li>
                            ))}
                        </ul>

                        {emAberto.includes(ficha.data.data.estado) ? (
                            <div className="space-y-2">
                                <AvisoDeErro erro={responder.error} />

                                <Campo etiqueta={t('Responder')}
                                    ajuda={t('Responder devolve o pedido à fila do suporte.')}>
                                    <textarea rows={3} value={resposta}
                                        onChange={(e) => porResposta(e.target.value)}
                                        className={entrada} />
                                </Campo>

                                <div className="flex justify-end">
                                    <Botao cor="primaria" tom="solida" icone="fa-paper-plane"
                                        disabled={resposta.trim().length < 2}
                                        aTrabalhar={responder.isPending}
                                        onClick={() => responder.mutate(resposta)}>
                                        {t('Enviar resposta')}
                                    </Botao>
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm text-slate-500">
                                {t('Este pedido está fechado. Se o problema voltar, abra um novo.')}
                            </p>
                        )}
                    </div>
                ) : null}
            </Modal>
        </div>
    );
}
