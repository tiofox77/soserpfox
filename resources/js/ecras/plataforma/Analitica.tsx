import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type Escolha, type FiltrosDaAnalitica, plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeLinha } from '@/ui/GraficoDeLinha';
import { Modal } from '@/ui/Modal';
import { Rotulo, entrada } from '@/ui/Campo';
import { SemNada, cascata } from '@/ui/SemNada';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * QUEM NOS DESCOBRIU, POR ONDE, E O QUE FOI VER.
 *
 * O ecrã em Blade tinha oitocentas e cinquenta linhas, e a maior parte delas
 * eram contas: a percentagem de cada barra, o SVG da série de dias construído
 * com `@php` a acumular uma string a meio do HTML, a bandeira de cada país, a
 * classificação do canal de entrada de um visitante. Isso é do servidor — e
 * está lá. Aqui ficou o desenho.
 *
 * O QUE MELHOROU:
 *
 *  · UM CARTÃO QUE MENTIA. O quarto número da segunda fila dizia «Registos» e
 *    mostrava o TOTAL DE EVENTOS do período — dezenas de milhares onde deviam
 *    estar os cliques em «criar conta». O total de eventos tem o seu sítio, no
 *    pé do ecrã, e «Registos» mostra registos.
 *  · A TENDÊNCIA comparava maçãs com laranjas: o período de agora conta só quem
 *    não tem sessão iniciada, o anterior contava todos — a seta apontava para
 *    baixo por construção. E «0%» era o que aparecia quando não HAVIA período
 *    anterior, o que não é o mesmo que «ficou igual»: agora diz-se.
 *  · O PERCURSO DE UM VISITANTE é um pedido próprio. Abrir a ficha de alguém
 *    recalculava os vinte agregados do ecrã todo.
 *  · OS FILTROS aplicam-se sem recarregar a página inteira, e o botão de limpar
 *    aparece só quando há algo para limpar.
 */
export default function Analitica() {
    const [filtros, porFiltros] = useState<FiltrosDaAnalitica>({ periodo: '7d' });
    const [aba, porAba] = useState('geral');
    const [visitante, porVisitante] = useState<string | null>(null);

    const dados = useQuery({
        queryKey: ['plataforma', 'analitica', filtros],
        queryFn: () => plataforma.analitica.ler(filtros),
        // «Quem está online agora» envelhece em segundos, não em minutos.
        refetchInterval: 30_000,
        staleTime: 15_000,
    });

    const mexer = (campo: keyof FiltrosDaAnalitica, valor: string) =>
        porFiltros((f) => ({ ...f, [campo]: valor || undefined }));

    const periodo = (nome: string) =>
        porFiltros((f) => (nome === 'custom'
            ? { ...f, periodo: nome }
            : { ...f, periodo: nome, de: undefined, ate: undefined }));

    const limpar = () =>
        porFiltros((f) => ({ periodo: f.periodo, de: f.de, ate: f.ate }));

    const temFiltros = Boolean(
        filtros.aparelho || filtros.canal || filtros.pais || filtros.browser || filtros.pagina,
    );

    if (dados.isPending) return <Carregando linhas={14} />;

    if (dados.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a analítica')}</h2>
                <p className="text-sm text-red-800">
                    {dados.error instanceof ErroDaApi ? dados.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const d = dados.data;
    const n = d.numeros;

    const abas = [
        { chave: 'geral', rotulo: t('Visão geral'), icone: 'fa-chart-pie' },
        { chave: 'visitantes', rotulo: t('Visitantes (:n)', { n: d.visitantes.length }), icone: 'fa-fire' },
        { chave: 'origens', rotulo: t('De onde vêm'), icone: 'fa-route' },
        { chave: 'regiao', rotulo: t('Região'), icone: 'fa-earth-africa' },
        { chave: 'procuras', rotulo: t('Pesquisas'), icone: 'fa-magnifying-glass' },
        { chave: 'funil', rotulo: t('Funil'), icone: 'fa-filter' },
        { chave: 'aovivo', rotulo: t('Ao vivo'), icone: 'fa-bolt' },
    ];

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Analítica e visitantes')}
                subtitulo={t('Quem nos descobriu, por onde, e o que foi ver')}
                icone="fa-chart-line"
                cor="ciano"
                accoes={
                    <div className="flex flex-wrap gap-1.5">
                        {d.opcoes.periodos.map((p) => (
                            <button
                                key={p.valor}
                                type="button"
                                onClick={() => periodo(p.valor)}
                                className={cls(
                                    'px-3 py-1.5 text-xs font-bold',
                                    RAIO, FOCO, TRANSICAO,
                                    (filtros.periodo ?? '7d') === p.valor
                                        ? 'bg-white text-slate-900 shadow'
                                        : 'bg-white/20 text-white hover:bg-white/30',
                                )}
                                aria-pressed={(filtros.periodo ?? '7d') === p.valor}
                            >
                                {p.rotulo}
                            </button>
                        ))}
                    </div>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-users">
                        {t(':n visitante(s) no período', { n: kz(n.visitantes, 0) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-calendar-day">
                        {t('de :de a :ate', { de: d.periodo.de, ate: d.periodo.ate })}
                    </EstadoNaFaixa>
                    {/* SEM COOKIES DE TERCEIROS: é próprio, e vale dizê-lo. */}
                    <EstadoNaFaixa icone="fa-shield-halved">{t('100% próprio')}</EstadoNaFaixa>
                </div>
            </Faixa>

            {/* O PERÍODO À MEDIDA */}
            {filtros.periodo === 'custom' && (
                <div className={cls(CARTAO, 'flex flex-wrap items-end gap-3 p-4')}>
                    <label className="block">
                        <Rotulo>{t('De')}</Rotulo>
                        <input
                            type="date"
                            className={cls(entrada, 'w-44')}
                            value={filtros.de ?? ''}
                            onChange={(e) => mexer('de', e.target.value)}
                        />
                    </label>
                    <label className="block">
                        <Rotulo>{t('Até')}</Rotulo>
                        <input
                            type="date"
                            className={cls(entrada, 'w-44')}
                            value={filtros.ate ?? ''}
                            onChange={(e) => mexer('ate', e.target.value)}
                        />
                    </label>
                </div>
            )}

            <QuemEstaAgora agora={d.agora} />

            <NoSistema utilizadores={d.utilizadores} />

            {/* OS FILTROS.
                Os que aqui estavam eram dois — aparelho e utm_source — e o
                segundo nunca filtrou nada, porque `utm_source` está vazio em
                todos os registos: nunca chegou cá uma visita por campanha
                marcada. O canal deriva-se do referrer e existe sempre. */}
            <div className={cls(CARTAO, 'p-4')}>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Escolher
                        etiqueta={t('Aparelho')}
                        valor={filtros.aparelho ?? ''}
                        opcoes={d.opcoes.aparelhos}
                        todos={t('Todos')}
                        aoMudar={(v) => mexer('aparelho', v)}
                    />
                    <Escolher
                        etiqueta={t('Veio de')}
                        valor={filtros.canal ?? ''}
                        opcoes={d.opcoes.canais}
                        todos={t('Todos os canais')}
                        aoMudar={(v) => mexer('canal', v)}
                    />
                    <Escolher
                        etiqueta={t('País')}
                        valor={filtros.pais ?? ''}
                        opcoes={d.opcoes.paises}
                        todos={t('Todos')}
                        aoMudar={(v) => mexer('pais', v)}
                    />
                    <Escolher
                        etiqueta={t('Browser')}
                        valor={filtros.browser ?? ''}
                        opcoes={d.opcoes.browsers}
                        todos={t('Todos')}
                        aoMudar={(v) => mexer('browser', v)}
                    />
                    <Escolher
                        etiqueta={t('Página')}
                        valor={filtros.pagina ?? ''}
                        opcoes={d.opcoes.paginas}
                        todos={t('Todas')}
                        aoMudar={(v) => mexer('pagina', v)}
                    />
                </div>

                {temFiltros && (
                    <div className="entra mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <p className="text-xs text-slate-500">
                            <i className="fas fa-filter mr-1.5" aria-hidden="true" />
                            {t('A mostrar :v visitante(s) de :e registo(s).', {
                                v: kz(n.visitantes, 0), e: kz(n.eventos, 0),
                            })}
                        </p>
                        <Botao cor="neutra" altura="pequeno" tom="suave" icone="fa-rotate-left" onClick={limpar}>
                            {t('Limpar filtros')}
                        </Botao>
                    </div>
                )}
            </div>

            {/* OS NÚMEROS */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    rotulo={t('Visitantes únicos')}
                    valor={kz(n.visitantes, 0)}
                    icone="fa-users"
                    tom="azul"
                    nota={n.tendencia === null
                        ? t('Sem período anterior para comparar')
                        : t(':n% face ao período anterior', {
                            n: n.tendencia > 0 ? `+${n.tendencia}` : n.tendencia,
                        })}
                />
                <CartaoNumero
                    rotulo={t('Páginas vistas')}
                    valor={kz(n.pageviews, 0)}
                    icone="fa-eye"
                    tom="verde"
                    nota={t(':n sessão(ões)', { n: kz(n.sessoes, 0) })}
                />
                <CartaoNumero
                    rotulo={t('Cliques em acções')}
                    valor={kz(n.cliques, 0)}
                    icone="fa-bullseye"
                    tom="ambar"
                    nota={t('registo :r · whatsapp :w · módulos :m', {
                        r: n.registos, w: n.whatsapp, m: n.modulos,
                    })}
                />
                <CartaoNumero
                    rotulo={t('Taxa de conversão')}
                    valor={`${n.conversao}%`}
                    icone="fa-percent"
                    tom="roxo"
                    nota={t('visitante → começou o registo')}
                />
            </div>

            {/* A QUALIDADE DA VISITA.
                Um total de visitantes não distingue quem leu o site de quem
                fechou o separador — estas quatro medidas distinguem. */}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Medida rotulo={t('Páginas por sessão')} valor={String(n.paginas_por_sessao)} />
                <Medida
                    rotulo={t('Saem à primeira')}
                    valor={`${n.rejeicao}%`}
                    nota={t('sessões de uma só página')}
                    alerta={n.rejeicao > 70}
                />
                <Medida rotulo={t('Pesquisas')} valor={kz(n.pesquisas, 0)} />
                <Medida rotulo={t('Registos começados')} valor={kz(n.registos, 0)} />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <Separadores abas={abas} activa={aba} aoMudar={porAba} />

                <div className="pt-4">
                    <PainelDoSeparador chave="geral" activa={aba}>
                        <div className="space-y-6">
                            <Cartao titulo={t('Visitantes por dia')} icone="fa-chart-line" semPadding>
                                <div className="p-4">
                                    <GraficoDeLinha
                                        titulo={t('Visitantes por dia')}
                                        unidade={t('visitante(s)')}
                                        dados={d.dias.etiquetas.map((rotulo, i) => ({
                                            rotulo, valor: d.dias.valores[i] ?? 0,
                                        }))}
                                    />
                                </div>
                            </Cartao>

                            <div className="grid gap-4 lg:grid-cols-2">
                                <Cartao titulo={t('Páginas mais visitadas')} icone="fa-trophy">
                                    <Barras
                                        linhas={d.paginas.map((p) => ({
                                            rotulo: p.pagina,
                                            valor: p.vistas,
                                            nota: t(':n visitante(s) único(s)', { n: p.visitantes }),
                                        }))}
                                        cor="from-blue-500 to-blue-600"
                                        monospace
                                    />
                                </Cartao>

                                <Cartao titulo={t('De onde vieram')} icone="fa-globe">
                                    {/* OS CANAIS PRIMEIRO: é a leitura de uma
                                        olhadela, e cada um filtra o ecrã. */}
                                    {d.origens.canais.length > 0 && (
                                        <div className="mb-3 flex flex-wrap gap-2">
                                            {d.origens.canais.map((c) => (
                                                <button
                                                    key={c.canal}
                                                    type="button"
                                                    onClick={() => mexer('canal', c.canal === filtros.canal ? '' : c.canal)}
                                                    aria-pressed={c.canal === filtros.canal}
                                                    className={cls(
                                                        'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold',
                                                        'rounded-full ring-1 ring-inset', TRANSICAO, FOCO,
                                                        'hover:-translate-y-0.5 hover:shadow-sm',
                                                        c.canal === filtros.canal
                                                            ? 'bg-indigo-600 text-white ring-indigo-600'
                                                            : CANAL[c.canal] ?? 'bg-slate-100 text-slate-700 ring-slate-200',
                                                    )}
                                                >
                                                    <i className={cls('fas', c.icone)} aria-hidden="true" />
                                                    {maiuscula(c.canal)}
                                                    <span className="opacity-70">{c.visitantes}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}

                                    <Barras
                                        linhas={d.origens.fontes.map((f) => ({
                                            rotulo: f.fonte,
                                            icone: f.icone,
                                            valor: f.visitantes,
                                        }))}
                                        cor="from-emerald-500 to-teal-600"
                                    />
                                </Cartao>
                            </div>

                            <div className="grid gap-4 lg:grid-cols-3">
                                <Lista
                                    titulo={t('Aparelhos')}
                                    icone="fa-mobile-screen"
                                    itens={d.aparelhos.tipos.map((a) => ({
                                        rotulo: nomeDoAparelho(a.nome),
                                        icone: ICONE_DO_APARELHO[a.nome] ?? 'fa-circle-question',
                                        valor: a.visitantes,
                                    }))}
                                    total={d.aparelhos.tipos.reduce((s, a) => s + a.visitantes, 0)}
                                />
                                <Lista
                                    titulo={t('Browsers')}
                                    icone="fa-window-maximize"
                                    itens={d.aparelhos.browsers.map((b) => ({ rotulo: b.nome, valor: b.visitantes }))}
                                />
                                <Lista
                                    titulo={t('Países')}
                                    icone="fa-earth-africa"
                                    itens={d.regiao.paises.map((p) => ({
                                        rotulo: `${p.bandeira} ${p.nome}`,
                                        valor: p.visitantes,
                                    }))}
                                    rodape={d.regiao.por_resolver > 0
                                        ? t(':n endereço(s) por resolver', { n: d.regiao.por_resolver })
                                        : undefined}
                                />
                            </div>

                            {d.ao_vivo.por_acao.length > 0 && (
                                <Cartao titulo={t('Cliques por acção')} icone="fa-bullseye">
                                    <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                                        {d.ao_vivo.por_acao.map((e, i) => (
                                            <div
                                                key={e.nome ?? i}
                                                className={cls(CARTAO, 'entra p-3 text-center')}
                                                style={cascata(i)}
                                            >
                                                <p className="truncate text-xs text-slate-500">{e.nome ?? '—'}</p>
                                                <p className="text-lg font-bold text-amber-600">{e.quantos}</p>
                                            </div>
                                        ))}
                                    </div>
                                </Cartao>
                            )}
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="visitantes" activa={aba}>
                        <Visitantes lista={d.visitantes} aoAbrir={porVisitante} />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="origens" activa={aba}>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <Cartao titulo={t('Páginas')} icone="fa-file-lines" semPadding>
                                <Tabela
                                    cabecalhos={[t('Página'), t('Vistas'), t('Únicos'), t('Tempo médio')]}
                                    vazio={t('Sem páginas no período.')}
                                    linhas={d.paginas.map((p) => [
                                        <code key="p" className="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{p.pagina}</code>,
                                        <b key="v">{p.vistas}</b>,
                                        <span key="u" className="text-blue-600">{p.visitantes}</span>,
                                        <span key="t" className="text-slate-500">
                                            {p.tempo_medio === null ? '—' : t(':n s', { n: p.tempo_medio })}
                                        </span>,
                                    ])}
                                />
                            </Cartao>

                            <Cartao titulo={t('Origens detalhadas')} icone="fa-route" semPadding>
                                <Tabela
                                    cabecalhos={[t('Fonte'), t('Canal'), t('Visitantes')]}
                                    vazio={t('Sem origens no período.')}
                                    linhas={d.origens.fontes.map((f) => [
                                        <span key="f" className="font-medium text-slate-800">
                                            <i className={cls('fas mr-1.5 text-slate-400', f.icone)} aria-hidden="true" />
                                            {f.fonte}
                                        </span>,
                                        <span
                                            key="c"
                                            className={cls(
                                                'inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset',
                                                CANAL[f.canal] ?? 'bg-slate-100 text-slate-700 ring-slate-200',
                                            )}
                                        >
                                            {maiuscula(f.canal)}
                                        </span>,
                                        <b key="v">{f.visitantes}</b>,
                                    ])}
                                />
                            </Cartao>
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="regiao" activa={aba}>
                        <div className="space-y-4">
                            {d.regiao.paises.length === 0 && d.regiao.por_resolver > 0 && (
                                <div className={cls('border-2 border-blue-200 bg-blue-50 p-4', RAIO)}>
                                    <p className="font-bold text-blue-900">
                                        <i className="fas fa-hourglass-half mr-2" aria-hidden="true" />
                                        {t('A descobrir de onde vêm')}
                                    </p>
                                    <p className="mt-1 text-sm text-blue-800">
                                        {t(':n endereço(s) ainda por resolver. Vão sendo descobertos com o tráfego do sistema, em lotes — não há tarefa agendada neste alojamento.', { n: d.regiao.por_resolver })}
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-4 lg:grid-cols-2">
                                <Cartao titulo={t('Países')} icone="fa-earth-africa">
                                    <Barras
                                        linhas={d.regiao.paises.map((p) => ({
                                            rotulo: `${p.bandeira} ${p.nome}`,
                                            valor: p.visitantes,
                                            aoClicar: () => mexer('pais', p.codigo === filtros.pais ? '' : p.codigo),
                                        }))}
                                        cor="from-amber-500 to-orange-600"
                                        vazio={t('Ainda sem países resolvidos.')}
                                    />
                                </Cartao>

                                <div className="space-y-4">
                                    <Cartao titulo={t('Cidades')} icone="fa-city">
                                        <Barras
                                            linhas={d.regiao.cidades.map((c) => ({
                                                rotulo: `${c.bandeira} ${c.nome}`,
                                                valor: c.visitantes,
                                            }))}
                                            cor="from-slate-500 to-slate-700"
                                            vazio={t('Ainda sem cidades resolvidas.')}
                                        />
                                    </Cartao>

                                    <Lista
                                        titulo={t('Sistemas')}
                                        icone="fa-desktop"
                                        itens={d.aparelhos.sistemas.map((s) => ({ rotulo: s.nome, valor: s.visitantes }))}
                                    />
                                </div>
                            </div>
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="procuras" activa={aba}>
                        <div className="space-y-3">
                            <p className={cls('border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600', RAIO)}>
                                <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                                {t('O que as pessoas procuram nos ecrãs de venda. Registado no momento em que a pesquisa leva a escolher um artigo — e não a cada tecla premida, que encheria a lista de pedaços de palavras.')}
                            </p>

                            {d.procuras.length === 0 ? (
                                <SemNada
                                    icone="fa-magnifying-glass"
                                    titulo={t('Ainda sem pesquisas registadas')}
                                    frase={t('As pesquisas aparecem aqui assim que alguém procurar um artigo no ponto de venda e escolher um resultado. O site público não tem caixa de pesquisa, por isso nada vem de lá.')}
                                />
                            ) : (
                                <Barras
                                    linhas={d.procuras.map((p) => ({
                                        rotulo: p.termo,
                                        valor: p.vezes,
                                        nota: t(':n pessoa(s) · última vez :q', { n: p.pessoas, q: p.ultima ?? '—' }),
                                    }))}
                                    cor="from-indigo-500 to-purple-600"
                                />
                            )}
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="funil" activa={aba}>
                        <Funil degraus={d.funil} />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="aovivo" activa={aba}>
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <i className="fas fa-bolt text-amber-500" aria-hidden="true" />
                                    {t('Os últimos :n eventos', { n: d.ao_vivo.eventos.length })}
                                </h3>
                                <Botao
                                    cor="primaria"
                                    altura="pequeno"
                                    tom="suave"
                                    icone="fa-rotate"
                                    aTrabalhar={dados.isFetching}
                                    onClick={() => void dados.refetch()}
                                >
                                    {t('Actualizar')}
                                </Botao>
                            </div>

                            {d.ao_vivo.eventos.length === 0 ? (
                                <SemNada icone="fa-bolt" frase={t('Sem eventos no período escolhido.')} />
                            ) : (
                                <ul className="max-h-[600px] space-y-1 overflow-y-auto">
                                    {d.ao_vivo.eventos.map((e, i) => {
                                        const tipo = TIPO_DE_EVENTO[e.tipo] ?? { icone: 'fa-circle', cor: 'slate' };

                                        return (
                                            <li
                                                key={e.id}
                                                className={cls(
                                                    'entra flex items-center gap-3 border-l-4 p-2', RAIO, TRANSICAO,
                                                    'hover:bg-slate-50',
                                                    BORDA[tipo.cor],
                                                )}
                                                style={cascata(i)}
                                            >
                                                <i className={cls('fas', tipo.icone, TEXTO[tipo.cor])} aria-hidden="true" />
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-1.5 text-sm">
                                                        <span className={cls('rounded px-1.5 py-0.5 text-[11px] font-bold uppercase', FUNDO[tipo.cor], TEXTO[tipo.cor])}>
                                                            {e.tipo}
                                                        </span>
                                                        {e.nome && <code className="text-xs text-slate-600">{e.nome}</code>}
                                                    </div>
                                                    <p className="truncate text-xs text-slate-500">
                                                        {e.pagina ?? '/'}
                                                        {e.modulo && <> · <b>{e.modulo}</b></>}
                                                        {e.utm && <> · UTM: {e.utm}</>}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => porVisitante(e.visitante)}
                                                    className={cls('shrink-0 text-right', FOCO, RAIO)}
                                                >
                                                    <span className="block font-mono text-[10px] text-slate-400 hover:text-indigo-600">
                                                        {e.visitante.slice(0, 6)}
                                                    </span>
                                                    <span className="block text-[10px] text-slate-500">{e.ha_quanto}</span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                    </PainelDoSeparador>
                </div>
            </div>

            <p className="text-center text-xs text-slate-400">
                {t('Total de eventos no período: :n · 100% próprio · sem cookies de terceiros', {
                    n: kz(n.eventos, 0),
                })}
            </p>

            <Percurso visitante={visitante} aoFechar={() => porVisitante(null)} />
        </div>
    );
}

/* ─── Quem está aqui agora ────────────────────────────────────────────── */

/**
 * Fora do período e fora dos filtros de propósito: «agora» é agora, e quem
 * olha para isto quer saber quem está no site neste momento, não quem esteve
 * na janela que escolheu no filtro.
 */
function QuemEstaAgora({ agora }: { agora: { visitantes: number; paginas: Array<{ pagina: string; visitantes: number }> } }) {
    return (
        <div className={cls('bg-gradient-to-r from-slate-900 to-slate-800 p-4 text-white shadow-lg', RAIO)}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div className="flex shrink-0 items-center gap-3">
                    <span className="relative flex h-3 w-3">
                        {agora.visitantes > 0 && (
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                        )}
                        <span className={cls(
                            'relative inline-flex h-3 w-3 rounded-full',
                            agora.visitantes > 0 ? 'bg-emerald-500' : 'bg-slate-500',
                        )} />
                    </span>
                    <div>
                        <p className="text-3xl font-extrabold leading-none">{agora.visitantes}</p>
                        <p className="mt-0.5 text-xs font-bold uppercase tracking-wider text-slate-300">
                            {agora.visitantes === 1 ? t('visitante agora') : t('visitantes agora')}
                        </p>
                        <p className="mt-0.5 text-[10px] text-slate-500">{t('sem sessão iniciada')}</p>
                    </div>
                </div>

                <div className="min-w-0 flex-1">
                    {agora.paginas.length > 0 ? (
                        <>
                            <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                {t('A ver neste momento')}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {agora.paginas.map((p, i) => (
                                    <span
                                        key={p.pagina}
                                        className={cls('entra bg-white/10 px-2.5 py-1 text-xs font-medium', RAIO)}
                                        style={cascata(i)}
                                    >
                                        {p.pagina}
                                        <span className="ml-1 text-slate-400">{p.visitantes}</span>
                                    </span>
                                ))}
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-slate-400">{t('Ninguém no site nos últimos 5 minutos.')}</p>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * QUEM ESTÁ DENTRO DO SISTEMA.
 *
 * Estes não são visitantes: são clientes a trabalhar. Contavam para os
 * visitantes, para as sessões e para as páginas vistas, e apareciam
 * classificados como tráfego «directo» — o que enchia os números de captação
 * com gente que já paga. Agora têm o seu sítio, com nome.
 */
function NoSistema({ utilizadores }: {
    utilizadores: {
        online: number;
        lista: Array<{
            nome: string; email: string | null; empresa: string | null; pagina: string;
            ha_quanto: string | null; agora: boolean; acessos: number;
        }>;
    };
}) {
    return (
        <section className={cls(CARTAO, 'overflow-hidden border-l-4 border-l-blue-500')}>
            <header className="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5">
                <h3 className="flex items-center gap-2 text-base font-bold text-slate-900">
                    <i className="fas fa-user-check text-blue-600" aria-hidden="true" />
                    {t('Utilizadores no sistema')}
                    {utilizadores.online > 0 && (
                        <Etiqueta cor="bom" ponto>{t(':n agora', { n: utilizadores.online })}</Etiqueta>
                    )}
                </h3>
                <span className="text-xs text-slate-500">{t('últimas 24 horas')}</span>
            </header>

            {utilizadores.lista.length === 0 ? (
                <p className="px-5 pb-5 text-sm text-slate-500">{t('Ninguém autenticado nas últimas 24 horas.')}</p>
            ) : (
                <Tabela
                    cabecalhos={[t('Utilizador'), t('Empresa'), t('Onde está'), t('Páginas'), t('Visto')]}
                    vazio=""
                    linhas={utilizadores.lista.map((u) => [
                        <span key="n" className="flex items-center gap-2">
                            <span className="relative flex h-2 w-2 shrink-0">
                                {u.agora && (
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                )}
                                <span className={cls(
                                    'relative inline-flex h-2 w-2 rounded-full',
                                    u.agora ? 'bg-emerald-500' : 'bg-slate-300',
                                )} />
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate font-semibold text-slate-900">{u.nome}</span>
                                {u.email && <span className="block truncate text-xs text-slate-500">{u.email}</span>}
                            </span>
                        </span>,
                        <span key="e" className="text-slate-700">{u.empresa ?? '—'}</span>,
                        <code key="p" className="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{u.pagina}</code>,
                        <span key="a" className="text-slate-700">{u.acessos}</span>,
                        <span key="v" className="whitespace-nowrap text-xs text-slate-500">
                            {u.agora ? t('agora') : u.ha_quanto}
                        </span>,
                    ])}
                />
            )}
        </section>
    );
}

/* ─── Os visitantes, por interesse ────────────────────────────────────── */

function Visitantes({ lista, aoAbrir }: {
    lista: Array<{
        id: string; pontos: number; pageviews: number; cliques: number; paginas: number;
        bandeira: string; pais: string | null; aparelho: string | null; browser: string | null;
        utm: string | null; referrer: string | null; visto: string | null;
    }>;
    aoAbrir: (id: string) => void;
}) {
    if (lista.length === 0) {
        return <SemNada icone="fa-fire" frase={t('Nenhum visitante no período escolhido.')} />;
    }

    return (
        <div className="space-y-3">
            <p className="flex items-center gap-2 text-sm text-slate-700">
                <i className="fas fa-fire text-orange-500" aria-hidden="true" />
                {t('Visitantes por pontos de interesse — quanto mais alto, mais vale um telefonema.')}
            </p>

            <Tabela
                cabecalhos={[t('Pontos'), t('Visitante'), t('Páginas'), t('Cliques'), t('Origem'), t('Aparelho'), t('Última visita')]}
                vazio={t('Nenhum visitante no período escolhido.')}
                linhas={lista.map((v) => [
                    <Etiqueta key="p" cor={corDosPontos(v.pontos)}>
                        {quenturaDosPontos(v.pontos)} {v.pontos}
                    </Etiqueta>,
                    <button
                        key="v"
                        type="button"
                        onClick={() => aoAbrir(v.id)}
                        className={cls('font-mono text-xs font-semibold text-indigo-600 hover:underline', FOCO, RAIO)}
                    >
                        {v.id.slice(0, 8)}…
                        {v.bandeira && <span className="ml-1" title={v.pais ?? undefined}>{v.bandeira}</span>}
                    </button>,
                    <span key="g" className="text-slate-700">
                        {v.pageviews} <span className="text-xs text-slate-400">{t('(:n únicas)', { n: v.paginas })}</span>
                    </span>,
                    <b key="c" className="text-amber-600">{v.cliques}</b>,
                    <span key="o" className="text-xs text-slate-600">
                        {v.utm && <span className="rounded bg-blue-100 px-1.5 py-0.5 text-blue-700">{v.utm}</span>}
                        {v.referrer && <span className="inline-block max-w-[160px] truncate align-bottom text-slate-500">{v.referrer}</span>}
                        {!v.utm && !v.referrer && <span className="text-slate-400">{t('directo')}</span>}
                    </span>,
                    <span key="a" className="text-xs text-slate-600">
                        <i
                            className={cls('fas mr-1 text-slate-400', ICONE_DO_APARELHO[v.aparelho ?? ''] ?? 'fa-desktop')}
                            aria-hidden="true"
                        />
                        {v.browser ?? '—'}
                    </span>,
                    <span key="u" className="whitespace-nowrap text-xs text-slate-500">{v.visto}</span>,
                ])}
            />
        </div>
    );
}

/* ─── O funil ─────────────────────────────────────────────────────────── */

function Funil({ degraus }: { degraus: Array<{ degrau: string; quantos: number; icone: string }> }) {
    const maximo = Math.max(...degraus.map((d) => d.quantos), 0);
    const primeiro = degraus[0]?.quantos ?? 0;
    const ultimo = degraus[degraus.length - 1]?.quantos ?? 0;

    return (
        <div className="space-y-4">
            <div className="max-w-3xl space-y-3">
                {degraus.map((d, i) => {
                    const anterior = degraus[i - 1]?.quantos ?? 0;
                    const perda = i > 0 && anterior > 0
                        ? Math.round(((anterior - d.quantos) / anterior) * 1000) / 10
                        : 0;
                    const largura = maximo > 0 ? (d.quantos / maximo) * 100 : 0;

                    return (
                        <div key={d.degrau} className="entra" style={cascata(i)}>
                            <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                                <span className="flex items-center gap-2 font-bold text-slate-700">
                                    <i className={cls('fas', d.icone, CORES_DO_FUNIL[i])} aria-hidden="true" />
                                    {d.degrau}
                                </span>
                                <span className="text-sm">
                                    <b>{kz(d.quantos, 0)}</b>
                                    {perda > 0 && (
                                        <span className="ml-2 text-xs text-red-500">
                                            {t('↓ :n% desiste aqui', { n: perda })}
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div className="h-10 overflow-hidden rounded-xl bg-slate-100">
                                <div
                                    className={cls(
                                        'flex h-full items-center px-3 text-sm font-bold text-white',
                                        'bg-gradient-to-r', FUNDOS_DO_FUNIL[i],
                                        'transition-all duration-700',
                                    )}
                                    style={{ width: `${Math.max(largura, 6)}%` }}
                                >
                                    {d.quantos}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {primeiro > 0 && (
                <div className={cls('border border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 p-4', RAIO)}>
                    <p className="text-sm font-bold text-emerald-900">
                        <i className="fas fa-trophy mr-1.5" aria-hidden="true" />
                        {t('Da página inicial ao registo:')}
                        <span className="ml-2 text-2xl">
                            {Math.round((ultimo / primeiro) * 10000) / 100}%
                        </span>
                    </p>
                </div>
            )}
        </div>
    );
}

/* ─── O percurso de um visitante ──────────────────────────────────────── */

/** Por onde entrou, por onde andou, onde parou. Um pedido próprio. */
function Percurso({ visitante, aoFechar }: { visitante: string | null; aoFechar: () => void }) {
    const percurso = useQuery({
        queryKey: ['plataforma', 'analitica', 'percurso', visitante],
        queryFn: () => plataforma.analitica.percurso(visitante!),
        enabled: Boolean(visitante),
    });

    return (
        <Modal
            aberto={Boolean(visitante)}
            aoFechar={aoFechar}
            titulo={t('Percurso do visitante')}
            subtitulo={visitante ?? undefined}
            icone="fa-shoe-prints"
            cor="ciano"
            largura="lg"
        >
            {percurso.isPending ? (
                <Carregando linhas={6} />
            ) : percurso.isError ? (
                <p className="text-sm text-red-700">{t('Não foi possível ler o percurso.')}</p>
            ) : (
                <div className="space-y-5">
                    {percurso.data.cabeca && (
                        <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <Ficha rotulo={t('Chegou')} valor={percurso.data.cabeca.chegou ?? '—'} />
                            <Ficha
                                rotulo={t('Veio de')}
                                valor={
                                    <span className={cls(
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset',
                                        CANAL[percurso.data.cabeca.canal] ?? 'bg-slate-100 text-slate-700 ring-slate-200',
                                    )}>
                                        {percurso.data.cabeca.fonte}
                                    </span>
                                }
                            />
                            <Ficha
                                rotulo={t('Onde')}
                                valor={`${percurso.data.cabeca.bandeira} ${percurso.data.cabeca.onde ?? '—'}`}
                            />
                            <Ficha
                                rotulo={t('Aparelho')}
                                valor={`${nomeDoAparelho(percurso.data.cabeca.aparelho)} · ${percurso.data.cabeca.browser ?? '—'}`}
                            />
                        </div>
                    )}

                    {percurso.data.passos.length === 0 ? (
                        <SemNada icone="fa-shoe-prints" frase={t('Sem registos para este visitante.')} />
                    ) : (
                        <ol className="ml-3 space-y-4 border-l-2 border-slate-200">
                            {percurso.data.passos.map((p, i) => (
                                <li key={p.id} className="entra relative pl-6" style={cascata(i)}>
                                    <span className={cls(
                                        'absolute -left-[7px] top-1.5 h-3 w-3 rounded-full ring-2 ring-white',
                                        PONTO_DO_PASSO[p.tipo] ?? 'bg-slate-400',
                                    )} />
                                    <div className="flex items-baseline justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-slate-900">
                                                {p.tipo === 'search'
                                                    ? <>{t('Pesquisou')} <b>{p.termo}</b></>
                                                    : p.tipo === 'cta_click'
                                                        ? <>{t('Clicou')} <b>{p.nome}</b></>
                                                        : p.pagina}
                                            </p>
                                            {p.tipo === 'pageview' && p.segundos ? (
                                                <p className="text-[11px] text-slate-400">
                                                    {t('esteve :n s', { n: p.segundos })}
                                                </p>
                                            ) : null}
                                        </div>
                                        <span className="shrink-0 text-xs text-slate-400">{p.quando}</span>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </div>
            )}
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

function Escolher({ etiqueta, valor, opcoes, todos, aoMudar }: {
    etiqueta: string;
    valor: string;
    opcoes: Escolha[];
    todos: string;
    aoMudar: (valor: string) => void;
}) {
    return (
        <label className="block">
            <Rotulo>{etiqueta}</Rotulo>
            <select className={entrada} value={valor} onChange={(e) => aoMudar(e.target.value)}>
                <option value="">{todos}</option>
                {opcoes.map((o) => (
                    <option key={o.valor} value={o.valor}>{o.rotulo}</option>
                ))}
            </select>
        </label>
    );
}

function Medida({ rotulo, valor, nota, alerta = false }: {
    rotulo: string;
    valor: string;
    nota?: string;
    alerta?: boolean;
}) {
    return (
        <div className={cls(CARTAO, 'p-4', TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-md')}>
            <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{rotulo}</p>
            <p className={cls('mt-1 text-2xl font-extrabold', alerta ? 'text-red-600' : 'text-slate-900')}>{valor}</p>
            {nota && <p className="text-[11px] text-slate-400">{nota}</p>}
        </div>
    );
}

/** Uma lista de barras proporcionais — o padrão que o ecrã repete seis vezes. */
function Barras({ linhas, cor, monospace = false, vazio }: {
    linhas: Array<{ rotulo: string; valor: number; nota?: string; icone?: string; aoClicar?: () => void }>;
    cor: string;
    monospace?: boolean;
    vazio?: string;
}) {
    if (linhas.length === 0) {
        return <p className="py-6 text-center text-sm text-slate-400">{vazio ?? t('Sem dados no período.')}</p>;
    }

    const maximo = Math.max(...linhas.map((l) => l.valor), 1);

    return (
        <div className="space-y-2">
            {linhas.map((l, i) => (
                <div
                    key={`${l.rotulo}-${i}`}
                    className={cls(CARTAO, 'entra p-2', TRANSICAO, 'hover:shadow-sm')}
                    style={cascata(i)}
                >
                    <div className="mb-1 flex items-center justify-between gap-2">
                        {l.aoClicar ? (
                            <button
                                type="button"
                                onClick={l.aoClicar}
                                className={cls('min-w-0 flex-1 truncate text-left text-sm font-semibold text-slate-700 hover:text-indigo-600', TRANSICAO, FOCO, RAIO)}
                            >
                                {l.rotulo}
                            </button>
                        ) : (
                            <span className={cls('min-w-0 flex-1 truncate text-slate-700', monospace ? 'font-mono text-xs' : 'text-sm font-semibold')}>
                                {l.icone && <i className={cls('fas mr-1.5 text-slate-400', l.icone)} aria-hidden="true" />}
                                {l.rotulo}
                            </span>
                        )}
                        <span className="shrink-0 text-xs font-bold text-slate-900">{kz(l.valor, 0)}</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className={cls('h-full bg-gradient-to-r transition-all duration-700', cor)}
                            style={{ width: `${(l.valor / maximo) * 100}%` }}
                        />
                    </div>
                    {l.nota && <p className="mt-1 text-[10px] text-slate-400">{l.nota}</p>}
                </div>
            ))}
        </div>
    );
}

function Lista({ titulo, icone, itens, total, rodape }: {
    titulo: string;
    icone: string;
    itens: Array<{ rotulo: string; valor: number; icone?: string }>;
    /** Quando vem, cada linha ganha a sua percentagem do total. */
    total?: number;
    rodape?: string;
}) {
    return (
        <Cartao titulo={titulo} icone={icone}>
            {itens.length === 0 ? (
                <p className="py-4 text-center text-xs text-slate-400">{t('Sem dados no período.')}</p>
            ) : (
                <ul className="space-y-2">
                    {itens.map((i) => (
                        <li key={i.rotulo} className="flex items-center justify-between gap-2 text-sm">
                            <span className="min-w-0 truncate text-slate-700">
                                {i.icone && <i className={cls('fas mr-1.5 text-slate-400', i.icone)} aria-hidden="true" />}
                                {i.rotulo}
                            </span>
                            <span className="shrink-0 font-bold text-slate-900">
                                {i.valor}
                                {total && total > 0 && (
                                    <span className="ml-1 text-xs font-normal text-slate-400">
                                        ({Math.round((i.valor / total) * 1000) / 10}%)
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {rodape && <p className="mt-2 text-[10px] text-slate-400">{rodape}</p>}
        </Cartao>
    );
}

function Tabela({ cabecalhos, linhas, vazio }: {
    cabecalhos: string[];
    linhas: React.ReactNode[][];
    vazio: string;
}) {
    if (linhas.length === 0) {
        return <p className="py-8 text-center text-sm text-slate-400">{vazio}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-slate-200 bg-slate-50/70 text-left">
                        {cabecalhos.map((c) => (
                            <th key={c} className="px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                                {c}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {linhas.map((linha, i) => (
                        <tr key={i} className={cls('entra', TRANSICAO, 'hover:bg-indigo-50/40')} style={cascata(i)}>
                            {linha.map((celula, j) => (
                                <td key={j} className="px-3 py-2 align-middle">{celula}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Ficha({ rotulo, valor }: { rotulo: string; valor: React.ReactNode }) {
    return (
        <div>
            <span className="block text-xs text-slate-500">{rotulo}</span>
            <span className="text-slate-800">{valor}</span>
        </div>
    );
}

/* ─── As paletas ──────────────────────────────────────────────────────── */

/** A cor de cada canal. O `match` em PHP servia classes de Tailwind do Blade. */
const CANAL: Record<string, string> = {
    directo: 'bg-slate-100 text-slate-700 ring-slate-200',
    'orgânico': 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    social: 'bg-blue-100 text-blue-700 ring-blue-200',
    campanha: 'bg-purple-100 text-purple-700 ring-purple-200',
    'referência': 'bg-amber-100 text-amber-700 ring-amber-200',
    interno: 'bg-slate-100 text-slate-500 ring-slate-200',
};

const ICONE_DO_APARELHO: Record<string, string> = {
    mobile: 'fa-mobile-screen',
    desktop: 'fa-desktop',
    tablet: 'fa-tablet-screen-button',
    bot: 'fa-robot',
};

const TIPO_DE_EVENTO: Record<string, { icone: string; cor: string }> = {
    pageview: { icone: 'fa-eye', cor: 'blue' },
    cta_click: { icone: 'fa-bullseye', cor: 'amber' },
    click: { icone: 'fa-arrow-pointer', cor: 'purple' },
    search: { icone: 'fa-magnifying-glass', cor: 'indigo' },
    form_submit: { icone: 'fa-paper-plane', cor: 'emerald' },
};

// Estas três listas existem porque o Tailwind não vê classes montadas em tempo
// de execução: `border-${cor}-500` nunca entra na folha de estilos. No Blade a
// classe era construída assim e as cores dos eventos NUNCA apareceram.
const BORDA: Record<string, string> = {
    blue: 'border-blue-500 bg-blue-50/30',
    amber: 'border-amber-500 bg-amber-50/30',
    purple: 'border-purple-500 bg-purple-50/30',
    indigo: 'border-indigo-500 bg-indigo-50/30',
    emerald: 'border-emerald-500 bg-emerald-50/30',
    slate: 'border-slate-400 bg-slate-50/30',
};

const TEXTO: Record<string, string> = {
    blue: 'text-blue-600',
    amber: 'text-amber-600',
    purple: 'text-purple-600',
    indigo: 'text-indigo-600',
    emerald: 'text-emerald-600',
    slate: 'text-slate-500',
};

const FUNDO: Record<string, string> = {
    blue: 'bg-blue-100',
    amber: 'bg-amber-100',
    purple: 'bg-purple-100',
    indigo: 'bg-indigo-100',
    emerald: 'bg-emerald-100',
    slate: 'bg-slate-100',
};

const PONTO_DO_PASSO: Record<string, string> = {
    pageview: 'bg-blue-500',
    cta_click: 'bg-amber-500',
    search: 'bg-indigo-500',
};

const CORES_DO_FUNIL = ['text-blue-500', 'text-purple-500', 'text-pink-500', 'text-emerald-500'];

const FUNDOS_DO_FUNIL = [
    'from-blue-500 to-blue-600',
    'from-purple-500 to-purple-600',
    'from-pink-500 to-pink-600',
    'from-emerald-500 to-emerald-600',
];

function maiuscula(palavra: string): string {
    return palavra.charAt(0).toUpperCase() + palavra.slice(1);
}

/** A coluna guarda a palavra em inglês; o ecrã mostra-a em português. */
function nomeDoAparelho(tipo: string | null): string {
    const nomes: Record<string, string> = {
        desktop: t('Computador'),
        mobile: t('Telemóvel'),
        tablet: t('Tablet'),
        bot: t('Robô'),
    };

    return tipo ? nomes[tipo] ?? maiuscula(tipo) : '—';
}

function corDosPontos(pontos: number): 'perigo' | 'aviso' | 'primaria' | 'neutra' {
    if (pontos >= 50) return 'perigo';
    if (pontos >= 20) return 'aviso';
    if (pontos >= 10) return 'primaria';

    return 'neutra';
}

function quenturaDosPontos(pontos: number): string {
    if (pontos >= 50) return '🔥🔥';
    if (pontos >= 20) return '🔥';
    if (pontos >= 10) return '⭐';

    return '·';
}
