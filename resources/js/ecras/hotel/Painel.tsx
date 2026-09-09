import { useQuery } from '@tanstack/react-query';

import { painelDoHotel, type QuartoNoMapa, type ReservaDoPainel, type Serie } from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DO HOTEL — quem chega hoje, quem sai, e como está a casa.
 *
 * A pergunta que traz alguém aqui de manhã é «o que é que acontece hoje»: as
 * chegadas e as saídas do dia vêm primeiro, e o MAPA DA CASA — piso a piso,
 * com a cor do estado — vem logo a seguir. É esse mapa que a recepção olha
 * antes de dizer «temos quarto».
 *
 * O NOME DO HÓSPEDE APARECE. O painel em Blade lia a ficha antiga
 * (`hotel_guests`), que está a nulo em tudo o que se cria hoje: as chegadas do
 * dia saíam sem o nome de ninguém.
 */

const paraGrafico = (s: Serie) => s.etiquetas.map((rotulo, i) => ({ rotulo, valor: s.valores[i] ?? 0 }));

/** A cor de cada estado de quarto — a mesma no mapa e na barra de estados. */
const COR_DO_QUARTO: Record<string, { fundo: string; texto: string; ponto: string }> = {
    available: { fundo: 'bg-emerald-50 border-emerald-300', texto: 'text-emerald-800', ponto: 'bg-emerald-500' },
    occupied: { fundo: 'bg-sky-50 border-sky-300', texto: 'text-sky-800', ponto: 'bg-sky-500' },
    reserved: { fundo: 'bg-indigo-50 border-indigo-300', texto: 'text-indigo-800', ponto: 'bg-indigo-500' },
    cleaning: { fundo: 'bg-amber-50 border-amber-300', texto: 'text-amber-800', ponto: 'bg-amber-500' },
    maintenance: { fundo: 'bg-red-50 border-red-300', texto: 'text-red-800', ponto: 'bg-red-500' },
};

export default function Painel() {
    const q = useQuery({ queryKey: ['hotel', 'painel'], queryFn: painelDoHotel.ler, staleTime: 60_000 });

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <Falhou erro={q.error} />;

    const d = q.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Painel do Hotel')}
                subtitulo={t('Quem chega hoje, quem sai, e como está a casa')}
                icone="fa-hotel"
                cor="ciano"
            />

            {/* O QUE ACONTECE HOJE, antes dos números: é isto que faz alguém
                abrir o painel de manhã. */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Chegadas hoje')}
                    tom={d.hoje.chegadas.length > 0 ? 'indigo' : 'cinza'} icone="fa-right-to-bracket"
                    valor={numero(d.hoje.chegadas.length)} />
                <CartaoNumero aspecto="claro" rotulo={t('Saídas hoje')}
                    tom={d.hoje.saidas.length > 0 ? 'ambar' : 'cinza'} icone="fa-right-from-bracket"
                    valor={numero(d.hoje.saidas.length)} />
                <CartaoNumero aspecto="claro" rotulo={t('Hospedados')} tom="azul" icone="fa-bed"
                    valor={numero(d.hoje.hospedados.length)} />
                <CartaoNumero aspecto="claro" rotulo={t('Taxa de ocupação')} tom="verde" icone="fa-chart-pie"
                    nota={t(':ocupados de :total quartos', { ocupados: d.quartos.ocupados, total: d.quartos.total })}
                    valor={`${d.quartos.ocupacao.toLocaleString(etiquetaIntl())}%`} />
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Quartos livres')} tom="verde" icone="fa-door-open"
                    valor={numero(d.quartos.livres)} />
                <CartaoNumero aspecto="claro" rotulo={t('Em limpeza')}
                    tom={d.quartos.limpeza > 0 ? 'ambar' : 'cinza'} icone="fa-broom"
                    valor={numero(d.quartos.limpeza)} />
                {/* A MANUTENÇÃO ABERTA vem ao painel do hotel: um quarto com
                    uma fuga de água não se vende, e quem está na recepção não
                    abre o ecrã da manutenção para saber disso. */}
                <CartaoNumero aspecto="claro" rotulo={t('Manutenção aberta')}
                    tom={d.manutencao.urgentes > 0 ? 'vermelho' : d.manutencao.abertas > 0 ? 'ambar' : 'cinza'}
                    icone="fa-screwdriver-wrench"
                    nota={d.manutencao.urgentes > 0 ? t(':n urgente(s)', { n: d.manutencao.urgentes }) : undefined}
                    valor={numero(d.manutencao.abertas)} />
                {d.dinheiro ? (
                    <CartaoNumero aspecto="claro" rotulo={t('Facturado este mês')} tom="verde" icone="fa-money-bill-wave"
                        nota={d.dinheiro.por_receber > 0 ? t(':v por receber', { v: `${kz(d.dinheiro.por_receber)} Kz` }) : undefined}
                        valor={`${kz(d.dinheiro.do_mes)} Kz`} />
                ) : (
                    <CartaoNumero aspecto="claro" rotulo={t('Reservas por decidir')}
                        tom={d.por_decidir.length > 0 ? 'ambar' : 'cinza'} icone="fa-circle-question"
                        valor={numero(d.por_decidir.length)} />
                )}
            </div>

            {/* O MAPA DA CASA. */}
            <Cartao titulo={t('O mapa da casa')} icone="fa-map"
                subtitulo={t('Piso a piso, com o estado de cada quarto')}>
                {d.mapa.length === 0 ? (
                    <p className="py-6 text-center text-sm text-slate-400">{t('Ainda não há quartos registados.')}</p>
                ) : (
                    <div className="space-y-4">
                        <ul className="flex flex-wrap gap-3 text-xs">
                            {Object.entries(COR_DO_QUARTO).map(([chave, cor]) => (
                                <li key={chave} className="flex items-center gap-1.5 text-slate-500">
                                    <span className={cls('h-2.5 w-2.5 rounded-full', cor.ponto)} aria-hidden="true" />
                                    {rotuloDoEstado(chave, d)}
                                </li>
                            ))}
                        </ul>

                        {d.mapa.map((piso) => (
                            <section key={piso.piso}>
                                <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                    {piso.piso === '—' ? t('Sem piso') : t('Piso :n', { n: piso.piso })}
                                </h3>
                                <ul className="flex flex-wrap gap-2">
                                    {piso.quartos.map((quarto, i) => <QuadradoDoQuarto key={quarto.id} q={quarto} i={i} />)}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Lista titulo={t('Chegadas de hoje')} icone="fa-right-to-bracket"
                    vazio={t('Não há chegadas marcadas para hoje.')} linhas={d.hoje.chegadas} />
                <Lista titulo={t('Saídas de hoje')} icone="fa-right-from-bracket"
                    vazio={t('Não há saídas marcadas para hoje.')} linhas={d.hoje.saidas} />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Lista titulo={t('Reservas por decidir')} icone="fa-circle-question"
                    vazio={t('Não há reservas à espera de decisão.')} linhas={d.por_decidir} />
                <Lista titulo={t('Próximas chegadas (7 dias)')} icone="fa-calendar-day"
                    vazio={t('Não há chegadas nos próximos sete dias.')} linhas={d.proximas} />
            </div>

            <Cartao titulo={d.ve_dinheiro ? t('Receita, mês a mês') : t('Estadas, mês a mês')}
                icone="fa-chart-column"
                subtitulo={t('Num hotel a sazonalidade é o negócio: um mau mês e uma época baixa são decisões opostas.')}>
                <GraficoDeBarras dados={paraGrafico(d.series.mensal)} altura={220}
                    titulo={d.ve_dinheiro ? t('Receita das estadas fechadas (Kz)') : t('Estadas fechadas')} />
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Ocupação dos últimos 30 dias')} icone="fa-chart-line"
                    subtitulo={t('Contada por NOITE — uma estada de cinco noites ocupa cinco dias.')}>
                    <GraficoDeBarras dados={paraGrafico(d.series.ocupacao)} altura={180}
                        titulo={t('Quartos ocupados por noite')} />
                </Cartao>

                <Cartao titulo={d.ve_dinheiro ? t('Quanto rende cada tipo de quarto') : t('Estadas por tipo de quarto')}
                    icone="fa-bed">
                    <GraficoHorizontal dados={paraGrafico(d.series.por_tipo)}
                        titulo={d.ve_dinheiro ? t('Receita por tipo') : t('Estadas por tipo')}
                        unidade={d.ve_dinheiro ? 'Kz' : ''}
                        vazio={t('Ainda não há estadas fechadas.')} />
                </Cartao>
            </div>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

function rotuloDoEstado(chave: string, d: { series: { estados: Serie } }): string {
    const i = d.series.estados.chaves?.indexOf(chave) ?? -1;

    if (i >= 0) return d.series.estados.etiquetas[i] ?? chave;

    // Um estado sem quartos não vem na série: a legenda mostra-o na mesma,
    // senão a cor aparece no mapa e não se sabe o que quer dizer.
    return ({
        available: t('Livre'), occupied: t('Ocupado'), reserved: t('Reservado'),
        cleaning: t('Em limpeza'), maintenance: t('Em manutenção'),
    } as Record<string, string>)[chave] ?? chave;
}

function QuadradoDoQuarto({ q, i }: { q: QuartoNoMapa; i: number }) {
    const cor = COR_DO_QUARTO[q.estado] ?? { fundo: 'bg-slate-50 border-slate-300', texto: 'text-slate-700', ponto: 'bg-slate-400' };

    /* O TÍTULO leva tudo o que não cabe no quadrado — quem lá está e até quando. */
    const dica = [
        q.tipo,
        q.estado_rotulo,
        q.hospede ? t('Hóspede: :nome', { nome: q.hospede }) : null,
        q.ate ? t('Até :quando', { quando: data(q.ate) }) : null,
        q.limpeza === 'dirty' ? t('Por limpar') : null,
    ].filter(Boolean).join(' · ');

    return (
        <li
            className="entra"
            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
        >
            <span
                title={dica}
                className={cls(
                    'relative flex h-16 w-20 flex-col items-center justify-center border-2 transition-transform duration-200 hover:-translate-y-0.5',
                    RAIO, cor.fundo, cor.texto,
                )}
            >
                <span className="text-sm font-bold tabular-nums">{q.numero}</span>
                <span className="max-w-full truncate px-1 text-[10px] opacity-80">
                    {q.hospede ?? q.estado_rotulo}
                </span>

                {/* SUJO É OUTRA COISA que ocupado: um quarto livre e por limpar
                    não se vende, e a cor do estado não o diz. */}
                {q.limpeza === 'dirty' && (
                    <span className="absolute right-1 top-1 text-[9px] text-amber-600" aria-label={t('Por limpar')}>
                        <i className="fas fa-broom" aria-hidden="true" />
                    </span>
                )}
            </span>
        </li>
    );
}

function Lista({ titulo, icone, vazio, linhas }: {
    titulo: string;
    icone: string;
    vazio: string;
    linhas: ReservaDoPainel[];
}) {
    return (
        <Cartao titulo={titulo} icone={icone}>
            {linhas.length === 0 ? (
                <p className="py-6 text-center text-sm text-slate-400">{vazio}</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {linhas.map((r, i) => (
                        <li key={r.id} className="entra flex items-center justify-between gap-3 py-2"
                            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                            <span className="min-w-0">
                                <span className="block truncate font-semibold text-slate-800">{r.hospede}</span>
                                <span className="block truncate text-xs text-slate-400">
                                    {r.numero}
                                    {r.quarto ? ` · ${t('Quarto :n', { n: r.quarto })}` : r.tipo ? ` · ${r.tipo}` : ''}
                                </span>
                            </span>
                            <span className="flex-none text-right">
                                <span className="block text-sm tabular-nums text-slate-600">
                                    {r.entrada ? data(r.entrada) : '—'}
                                    {r.saida ? ` → ${data(r.saida)}` : ''}
                                </span>
                                <span className="block text-xs text-slate-400">
                                    {t(':n noite(s)', { n: r.noites })}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </Cartao>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6', FOCO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o painel do hotel')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
