import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { suporte, type Sugestao } from '@/api/suporte';
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
 * O QUADRO DE MELHORIAS.
 *
 * É DE TODA A EMPRESA, e é esse o sentido de haver votos: uma sugestão que
 * cinco colegas votam vale mais do que cinco pedidos soltos a dizer o mesmo.
 *
 * O VOTO ESTAVA ABERTO À RUA: a sugestão era procurada por id e mais nada, e
 * bastava escrever o número de outra empresa para lhe mexer na contagem. E o
 * total era somado e subtraído à mão, ao lado da linha do voto — dois cliques
 * seguidos e a coluna deixava de corresponder às linhas.
 */

const COR_DO_ESTADO: Record<string, 'primaria' | 'bom' | 'aviso' | 'neutra' | 'perigo'> = {
    pending: 'neutra',
    under_review: 'aviso',
    planned: 'primaria',
    in_development: 'primaria',
    completed: 'bom',
    rejected: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    pending: 'fa-hourglass-start',
    under_review: 'fa-magnifying-glass',
    planned: 'fa-calendar-check',
    in_development: 'fa-hammer',
    completed: 'fa-circle-check',
    rejected: 'fa-xmark',
};

const VAZIO = { title: '', description: '' };

export default function QuadroDeMelhorias() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ ordem?: string; procura?: string }>({ ordem: 'populares' });
    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [imagens, porImagens] = useState<File[]>([]);
    const [aApagar, porAApagar] = useState<Sugestao | null>(null);

    const opcoes = useQuery({ queryKey: ['suporte', 'opcoes'], queryFn: () => suporte.opcoes() });
    const lista = useQuery({
        queryKey: ['suporte', 'melhorias', filtros],
        queryFn: () => suporte.melhorias.listar(filtros),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['suporte'] });
    };

    const sugerir = useMutation({
        mutationFn: () => suporte.melhorias.sugerir(formulario as unknown as Record<string, string>, imagens),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porImagens([]); },
        onError: porErro,
    });

    const votar = useMutation({
        mutationFn: (id: number) => suporte.melhorias.votar(id),
        onSuccess: () => { porErro(null); void cache.invalidateQueries({ queryKey: ['suporte'] }); },
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => suporte.melhorias.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: (e) => { porErro(e); porAApagar(null); },
    });

    if (opcoes.isPending) return <Carregando linhas={6} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const erros = (sugerir.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const ORDENS = [
        { valor: 'populares', rotulo: t('Mais votadas'), icone: 'fa-fire' },
        { valor: 'recentes', rotulo: t('Recentes'), icone: 'fa-clock' },
        { valor: 'minhas', rotulo: t('Minhas sugestões'), icone: 'fa-user' },
    ];

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Quadro de Melhorias')}
                subtitulo={t('O que os colegas mais votam é o que se faz primeiro')}
                icone="fa-lightbulb"
                cor="primaria"
                accoes={
                    <>
                        <button type="button" className={ACCAO_DA_FAIXA}
                            onClick={() => { porFormulario({ ...VAZIO }); porImagens([]); }}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Sugerir melhoria')}
                        </button>
                        <a href="/support/tickets" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-life-ring" aria-hidden="true" />
                            {t('Pedidos de Suporte')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-lightbulb">
                        {t(':n sugestões', { n: numero(resumo?.total ?? 0) })}
                    </EstadoNaFaixa>
                    {(resumo?.planeadas ?? 0) > 0 && (
                        <EstadoNaFaixa icone="fa-calendar-check">
                            {t(':n já planeadas', { n: numero(resumo?.planeadas ?? 0) })}
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
                    <CartaoNumero rotulo={t('Sugestões')} valor={numero(resumo.total)} icone="fa-lightbulb" tom="indigo" />
                    <CartaoNumero rotulo={t('Minhas')} valor={numero(resumo.minhas)} icone="fa-user" tom="azul"
                        aoCarregar={() => porFiltros({ ...filtros, ordem: 'minhas' })} />
                    <CartaoNumero rotulo={t('Planeadas')} valor={numero(resumo.planeadas)} icone="fa-calendar-check" tom="roxo"
                        nota={t('Aceites e a caminho')} />
                    <CartaoNumero rotulo={t('Feitas')} valor={numero(resumo.feitas)} icone="fa-circle-check" tom="verde" />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <div className="flex flex-wrap gap-1.5">
                    {ORDENS.map((x) => (
                        <button key={x.valor} type="button"
                            onClick={() => porFiltros({ ...filtros, ordem: x.valor })}
                            className={cls(
                                'inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold transition',
                                RAIO, FOCO,
                                (filtros.ordem ?? 'populares') === x.valor
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                            )}>
                            <i className={`fas ${x.icone}`} aria-hidden="true" />
                            {x.rotulo}
                        </button>
                    ))}
                </div>

                <Campo etiqueta={t('Procurar')} className="ml-auto min-w-[12rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value })}
                            placeholder={t('Título ou descrição…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={5} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-lightbulb"
                    titulo={t('Nenhuma sugestão')}
                    frase={t('Uma sugestão votada pelos colegas vale mais do que cinco pedidos soltos a dizer o mesmo.')}
                    accao={
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => { porFormulario({ ...VAZIO }); porImagens([]); }}>
                            {t('Sugerir melhoria')}
                        </Botao>
                    }
                />
            ) : (
                <ul className="space-y-3">
                    {lista.data.data.map((x, i) => (
                        <li key={x.id} style={cascata(i)}
                            className={cls('entra flex gap-4 p-5 transition hover:shadow-md', CARTAO)}>
                            {/* O VOTO: o botão e o total, lado a lado, como num quadro. */}
                            <div className="flex flex-none flex-col items-center">
                                <button
                                    type="button"
                                    onClick={() => votar.mutate(x.id)}
                                    disabled={votar.isPending}
                                    aria-pressed={x.votei}
                                    aria-label={x.votei ? t('Retirar o voto') : t('Votar')}
                                    className={cls(
                                        'grid h-12 w-12 place-items-center rounded-full text-lg transition-all duration-200',
                                        FOCO,
                                        x.votei
                                            ? 'bg-indigo-600 text-white shadow-md hover:scale-105'
                                            : 'bg-slate-100 text-slate-500 hover:bg-indigo-100 hover:text-indigo-600 hover:scale-105',
                                    )}
                                >
                                    <i className="fas fa-arrow-up" aria-hidden="true" />
                                </button>
                                <span className="mt-1.5 text-lg font-bold tabular-nums text-slate-800">
                                    {numero(x.votos)}
                                </span>
                                <span className="text-[11px] text-slate-400">{t('votos')}</span>
                            </div>

                            <div className="min-w-0 flex-1">
                                <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                    <Etiqueta cor={COR_DO_ESTADO[x.estado] ?? 'neutra'}
                                        icone={ICONE_DO_ESTADO[x.estado] ?? 'fa-lightbulb'}>
                                        {x.estado_rotulo}
                                    </Etiqueta>
                                    {x.minha && <Etiqueta cor="primaria" icone="fa-user">{t('Minha')}</Etiqueta>}
                                    <span className="text-xs text-slate-400">
                                        {[x.autor, x.quando].filter(Boolean).join(' · ')}
                                    </span>

                                    {/* Só se retira uma sugestão que ninguém votou —
                                        o servidor recusa o resto. */}
                                    {x.minha && x.votos === 0 && (
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                            className="ml-auto"
                                            onClick={() => porAApagar(x)}
                                            aria-label={t('Retirar sugestão')} />
                                    )}
                                </div>

                                <h3 className="text-base font-bold text-slate-800">{x.titulo}</h3>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{x.descricao}</p>

                                {x.imagens.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {x.imagens.map((url) => (
                                            <a key={url} href={url} target="_blank" rel="noreferrer">
                                                <img src={url} alt=""
                                                    className="h-16 w-16 rounded-lg border border-indigo-100 object-cover transition hover:scale-105" />
                                            </a>
                                        ))}
                                    </div>
                                )}

                                {x.comentarios > 0 && (
                                    <p className="mt-2 text-xs text-slate-400">
                                        <i className="far fa-comment mr-1" aria-hidden="true" />
                                        {t(':n comentários', { n: numero(x.comentarios) })}
                                    </p>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {/* ─── Sugerir ───────────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porImagens([]); }}
                titulo={t('Sugerir melhoria')}
                subtitulo={t('Os colegas votam — e o mais votado é o que se faz primeiro')}
                icone="fa-lightbulb"
                cor="primaria"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porImagens([]); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane"
                            aTrabalhar={sugerir.isPending} onClick={() => sugerir.mutate()}>
                            {t('Enviar sugestão')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={sugerir.error} />

                        <Campo etiqueta={t('Título')} obrigatorio erro={erros.title}
                            ajuda={t('Uma frase que um colega reconheça de relance.')}>
                            <input type="text" value={formulario.title}
                                onChange={(e) => porFormulario({ ...formulario, title: e.target.value })}
                                placeholder={t('Ex.: exportar o mapa de vendas para Excel')} className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.description}
                            ajuda={t('O que faz falta, e sobretudo PARA QUÊ — o problema convence mais do que a solução.')}>
                            <textarea rows={6} value={formulario.description}
                                onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo
                            etiqueta={t('Imagens')}
                            erro={erros.images ?? erros['images.0']}
                            ajuda={t('Até :n imagens, 2 MB cada.', { n: String(o.maximo_de_imagens) })}
                        >
                            <input type="file" multiple accept="image/*"
                                onChange={(e) => porImagens(Array.from(e.target.files ?? []).slice(0, o.maximo_de_imagens))}
                                className={cls(entrada, 'file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700')} />
                        </Campo>

                        {imagens.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {imagens.map((f) => (
                                    <span key={f.name}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                        <i className="fas fa-image" aria-hidden="true" />
                                        {f.name}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* ─── Retirar ───────────────────────────────────────────── */}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Retirar sugestão')}
                subtitulo={aApagar?.titulo}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Retirar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Só se retira uma sugestão que nenhum colega tenha votado. A partir do primeiro voto, ela já não é só sua.')}
                </p>
            </Modal>
        </div>
    );
}
