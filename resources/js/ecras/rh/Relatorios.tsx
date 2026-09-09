import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { relatorios, type Mapa, type OpcoesDosMapas } from '@/api/rh';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { entrada } from '@/ui/Campo';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS CINCO MAPAS DO RH.
 *
 * Um mapa de salários é o salário de toda a gente numa página só — e este ecrã
 * não tinha permissão nenhuma: bastava ter o módulo activo. Ganhou
 * `hr.reports.view`.
 *
 * NENHUM MAPA RECALCULA NADA. O de salários e o de custos lêem as linhas da
 * folha; o saldo de férias pergunta ao `VacationService`, que é a fonte única
 * do direito. Se recalculassem, o mapa podia dizer um valor e o recibo que o
 * trabalhador tem em casa dizer outro.
 *
 * OS FILTROS MUDAM COM O MAPA: o quadro de pessoal é do ano inteiro e não pede
 * mês; o custo por departamento já é POR departamento e não pede o filtro. É
 * o servidor que o diz — `pede_mes` e `pede_departamento` vêm no esquema.
 */

type Filtros = { mapa: string; ano: number; mes: number; departamento: string };

export default function Relatorios() {
    const agora = new Date();

    const [f, porF] = useState<Filtros>({
        mapa: 'mapa_de_salarios',
        ano: agora.getFullYear(),
        mes: agora.getMonth() + 1,
        departamento: '',
    });

    const opcoes = useQuery({ queryKey: ['rh', 'mapas', 'opcoes'], queryFn: relatorios.opcoes, staleTime: 5 * 60_000 });

    const escolhido = opcoes.data?.mapas.find((m) => m.valor === f.mapa);

    const mapa = useQuery({
        queryKey: ['rh', 'mapas', f],
        queryFn: () => relatorios.mostrar({
            mapa: f.mapa,
            ano: f.ano,
            mes: escolhido?.pede_mes ? f.mes : undefined,
            departamento: escolhido?.pede_departamento && f.departamento ? f.departamento : undefined,
        }),
        enabled: opcoes.isSuccess,
        placeholderData: keepPreviousData,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Relatórios de RH')}
                subtitulo={t('Os mapas que se conferem, se imprimem e se entregam')}
                icone="fa-chart-pie"
                cor="roxo"
            />

            {/* A ESCOLHA DO MAPA É UMA FILA DE BOTÕES e não um `select`: são
                cinco, cada um com o seu ícone, e vê-se tudo o que há sem
                abrir nada. */}
            <div className="flex flex-wrap gap-2">
                {o.mapas.map((m, i) => (
                    <button
                        key={m.valor}
                        type="button"
                        onClick={() => porF((v) => ({ ...v, mapa: m.valor }))}
                        aria-pressed={f.mapa === m.valor}
                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                        className={cls(
                            'entra inline-flex items-center gap-2 border px-3.5 py-2 text-sm font-semibold',
                            'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
                            RAIO, FOCO,
                            f.mapa === m.valor
                                ? 'border-purple-500 bg-purple-50 text-purple-800 shadow-sm'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-purple-300',
                        )}
                    >
                        <i className={cls('fas', m.icone, f.mapa === m.valor && 'text-purple-600')} aria-hidden="true" />
                        {m.rotulo}
                    </button>
                ))}
            </div>

            <Cartao titulo={t('O período')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Ano')}</span>
                        <select value={String(f.ano)} onChange={(e) => porF((v) => ({ ...v, ano: Number(e.target.value) }))} className={entrada}>
                            {o.anos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                        </select>
                    </label>

                    {escolhido?.pede_mes && (
                        <label className="block">
                            <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Mês')}</span>
                            <select value={String(f.mes)} onChange={(e) => porF((v) => ({ ...v, mes: Number(e.target.value) }))} className={cls(entrada, 'capitalize')}>
                                {o.meses.map((m) => <option key={m.valor} value={m.valor} className="capitalize">{m.rotulo}</option>)}
                            </select>
                        </label>
                    )}

                    {escolhido?.pede_departamento && (
                        <label className="block">
                            <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Departamento')}</span>
                            <select value={f.departamento} onChange={(e) => porF((v) => ({ ...v, departamento: e.target.value }))} className={entrada}>
                                <option value="">{t('Todos')}</option>
                                {o.departamentos.map((d) => <option key={d.valor} value={d.valor}>{d.rotulo}</option>)}
                            </select>
                        </label>
                    )}

                    <div className="flex items-end">
                        <Botao altura="pequeno" icone="fa-eraser"
                            onClick={() => porF((v) => ({ ...v, ano: agora.getFullYear(), mes: agora.getMonth() + 1, departamento: '' }))}>
                            {t('Limpar')}
                        </Botao>
                    </div>
                </div>
            </Cartao>

            {mapa.isPending ? (
                <Carregando />
            ) : mapa.isError ? (
                <Falhou erro={mapa.error} />
            ) : (
                <div className={mapa.isFetching ? 'opacity-70 transition-opacity' : 'transition-opacity'}>
                    <Resultado m={mapa.data} o={o} />
                </div>
            )}
        </div>
    );
}

/* ─── O que cada mapa mostra ────────────────────────────────────────── */

function Resultado({ m, o }: { m: Mapa; o: OpcoesDosMapas }) {
    const nome = o.mapas.find((x) => x.valor === m.mapa);

    // O PERÍODO DO TÍTULO É O QUE O MAPA USA. O saldo de férias e o quadro de
    // pessoal são do ANO — escrever «setembro 2026» num deles fazia parecer
    // que os números eram só desse mês.
    const periodo = nome?.pede_mes ? `${m.periodo.nome_do_mes} ${m.periodo.ano}` : String(m.periodo.ano);
    const titulo = nome ? `${nome.rotulo} — ${periodo}` : periodo;

    if (m.nada) {
        return (
            <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                    <i className={cls('fas text-4xl text-slate-300', nome?.icone ?? 'fa-chart-pie')} aria-hidden="true" />
                </div>
                <p className="text-lg font-bold text-slate-800">{m.nada}</p>
                <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('Escolha outro período lá em cima.')}</p>
            </div>
        );
    }

    return (
        <Cartao
            titulo={titulo}
            icone={nome?.icone ?? 'fa-table'}
            accoes={m.folha && (
                <span className="flex items-center gap-2 text-xs text-slate-500">
                    <span className="font-mono">{m.folha.numero}</span>
                    <Etiqueta cor={m.folha.estado === 'paid' || m.folha.estado === 'approved' ? 'bom' : 'neutra'}>
                        {t(m.folha.estado === 'draft' ? 'Rascunho' : m.folha.estado === 'approved' ? 'Aprovada' : m.folha.estado === 'paid' ? 'Paga' : m.folha.estado)}
                    </Etiqueta>
                </span>
            )}
            semPadding
        >
            {m.mapa === 'mapa_de_salarios' && <MapaDeSalarios m={m} />}
            {m.mapa === 'custo_por_departamento' && <CustoPorDepartamento m={m} />}
            {m.mapa === 'resumo_de_presencas' && <ResumoDePresencas m={m} />}
            {m.mapa === 'saldo_de_ferias' && <SaldoDeFerias m={m} />}
            {m.mapa === 'quadro_de_pessoal' && <QuadroDePessoal m={m} />}
        </Cartao>
    );
}

/** A tabela partilhada: cabeçalho, linhas e a linha de totais em rodapé. */
function Tabela({ colunas, children, rodape, minimo = '52rem' }: {
    colunas: Array<{ chave: string; rotulo: string; direita?: boolean }>;
    children: React.ReactNode;
    rodape?: React.ReactNode;
    minimo?: string;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm" style={{ minWidth: minimo }}>
                <thead className="bg-slate-50">
                    <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                        {colunas.map((c) => (
                            <th key={c.chave} className={cls('px-4 py-3 font-bold', c.direita && 'text-right')}>{c.rotulo}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">{children}</tbody>
                {rodape && <tfoot className="border-t-2 border-slate-200 bg-slate-50 font-bold">{rodape}</tfoot>}
            </table>
        </div>
    );
}

const dinheiro = (v: unknown) => kz(Number(v ?? 0));
const inteiro = (v: unknown) => Number(v ?? 0).toLocaleString(etiquetaIntl());

function Pessoa({ l }: { l: Record<string, unknown> }) {
    return (
        <td className="px-4 py-2.5">
            <span className="block font-semibold text-slate-900">{String(l.nome ?? '')}</span>
            {l.numero ? <span className="block font-mono text-xs text-slate-400">{String(l.numero)}</span> : null}
        </td>
    );
}

function MapaDeSalarios({ m }: { m: Mapa }) {
    return (
        <Tabela
            minimo="66rem"
            colunas={[
                { chave: 'nome', rotulo: t('Funcionário') },
                { chave: 'dep', rotulo: t('Departamento') },
                { chave: 'base', rotulo: t('Salário base'), direita: true },
                { chave: 'ali', rotulo: t('Sub. alimentação'), direita: true },
                { chave: 'tra', rotulo: t('Sub. transporte'), direita: true },
                { chave: 'bruto', rotulo: t('Bruto'), direita: true },
                { chave: 'inss', rotulo: t('INSS'), direita: true },
                { chave: 'irt', rotulo: t('IRT'), direita: true },
                { chave: 'liq', rotulo: t('Líquido'), direita: true },
            ]}
            rodape={m.totais && (
                <tr>
                    <td className="px-4 py-3" colSpan={2}>{t('Total')}</td>
                    {['base', 'alimentacao', 'transporte', 'bruto', 'inss', 'irt', 'liquido'].map((k) => (
                        <td key={k} className="px-4 py-3 text-right tabular-nums">{dinheiro(m.totais?.[k])}</td>
                    ))}
                </tr>
            )}
        >
            {m.linhas.map((l, i) => (
                <tr key={String(l.id)} className="entra transition-colors hover:bg-purple-50/40" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                    <Pessoa l={l} />
                    <td className="px-4 py-2.5 text-slate-600">{String(l.departamento ?? '—')}</td>
                    {['base', 'alimentacao', 'transporte', 'bruto'].map((k) => (
                        <td key={k} className="px-4 py-2.5 text-right tabular-nums text-slate-700">{dinheiro(l[k])}</td>
                    ))}
                    <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">{dinheiro(l.inss)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">{dinheiro(l.irt)}</td>
                    <td className="px-4 py-2.5 text-right font-bold tabular-nums text-emerald-700">{dinheiro(l.liquido)}</td>
                </tr>
            ))}
        </Tabela>
    );
}

function CustoPorDepartamento({ m }: { m: Mapa }) {
    return (
        <Tabela
            colunas={[
                { chave: 'dep', rotulo: t('Departamento') },
                { chave: 'pessoas', rotulo: t('Pessoas'), direita: true },
                { chave: 'bruto', rotulo: t('Bruto'), direita: true },
                { chave: 'inss', rotulo: t('INSS (trabalhador + empresa)'), direita: true },
                { chave: 'irt', rotulo: t('IRT'), direita: true },
                { chave: 'liq', rotulo: t('Líquido'), direita: true },
                { chave: 'media', rotulo: t('Líquido médio'), direita: true },
            ]}
            rodape={m.totais && (
                <tr>
                    <td className="px-4 py-3">{t('Total')}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{inteiro(m.totais.pessoas)}</td>
                    {['bruto', 'inss', 'irt', 'liquido'].map((k) => (
                        <td key={k} className="px-4 py-3 text-right tabular-nums">{dinheiro(m.totais?.[k])}</td>
                    ))}
                    <td />
                </tr>
            )}
        >
            {m.linhas.map((l, i) => (
                <tr key={i} className="entra transition-colors hover:bg-purple-50/40" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                    <td className="px-4 py-2.5 font-semibold text-slate-900">{String(l.departamento ?? '')}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{inteiro(l.pessoas)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-slate-700">{dinheiro(l.bruto)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">{dinheiro(l.inss)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">{dinheiro(l.irt)}</td>
                    <td className="px-4 py-2.5 text-right font-bold tabular-nums text-emerald-700">{dinheiro(l.liquido)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-slate-500">{dinheiro(l.media)}</td>
                </tr>
            ))}
        </Tabela>
    );
}

function ResumoDePresencas({ m }: { m: Mapa }) {
    return (
        <Tabela
            minimo="58rem"
            colunas={[
                { chave: 'nome', rotulo: t('Funcionário') },
                { chave: 'dep', rotulo: t('Departamento') },
                { chave: 'eq', rotulo: t('Equivalente trabalhado'), direita: true },
                { chave: 'atr', rotulo: t('Atrasos'), direita: true },
                { chave: 'meio', rotulo: t('Meio dia'), direita: true },
                { chave: 'falt', rotulo: t('Faltas'), direita: true },
                { chave: 'just', rotulo: t('Justificadas'), direita: true },
                { chave: 'taxa', rotulo: t('Assiduidade'), direita: true },
            ]}
        >
            {m.linhas.map((l, i) => {
                const taxa = Number(l.taxa ?? 0);

                return (
                    <tr key={String(l.id)} className="entra transition-colors hover:bg-purple-50/40" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                        <Pessoa l={l} />
                        <td className="px-4 py-2.5 text-slate-600">{String(l.departamento ?? '—')}</td>
                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-emerald-700">{Number(l.equivalente ?? 0).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 })}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums text-amber-700">{inteiro(l.atrasos)}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums text-sky-700">{inteiro(l.meio_dia)}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums text-red-700">{inteiro(l.faltas)}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums text-indigo-700">{inteiro(l.justificadas)}</td>
                        <td className="px-4 py-2.5">
                            <div className="flex items-center justify-end gap-2">
                                <span className="h-2 w-16 overflow-hidden rounded-full bg-slate-200">
                                    <span
                                        className={cls('block h-full rounded-full transition-all duration-500',
                                            taxa >= 90 ? 'bg-emerald-500' : taxa >= 70 ? 'bg-amber-500' : 'bg-red-500')}
                                        style={{ width: `${taxa}%` }}
                                    />
                                </span>
                                <span className={cls('w-14 text-right text-xs font-bold tabular-nums',
                                    taxa >= 90 ? 'text-emerald-700' : taxa >= 70 ? 'text-amber-700' : 'text-red-700')}>
                                    {taxa.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 })}%
                                </span>
                            </div>
                        </td>
                    </tr>
                );
            })}
        </Tabela>
    );
}

function SaldoDeFerias({ m }: { m: Mapa }) {
    return (
        <Tabela
            minimo="46rem"
            colunas={[
                { chave: 'nome', rotulo: t('Funcionário') },
                { chave: 'dep', rotulo: t('Departamento') },
                { chave: 'dir', rotulo: t('Direito (dias)'), direita: true },
                { chave: 'goz', rotulo: t('Gozados (dias)'), direita: true },
                { chave: 'saldo', rotulo: t('Saldo'), direita: true },
                { chave: 'barra', rotulo: t('Gozado'), direita: true },
            ]}
            rodape={m.totais && (
                <tr>
                    <td className="px-4 py-3" colSpan={2}>{t('Total')}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{inteiro(m.totais.direito)}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{inteiro(m.totais.gozados)}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{inteiro(m.totais.saldo)}</td>
                    <td />
                </tr>
            )}
        >
            {m.linhas.map((l, i) => {
                const direito = Number(l.direito ?? 0);
                const gozados = Number(l.gozados ?? 0);
                const parte = direito > 0 ? Math.min(100, (gozados / direito) * 100) : 0;

                return (
                    <tr key={String(l.id)} className="entra transition-colors hover:bg-purple-50/40" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                        <Pessoa l={l} />
                        <td className="px-4 py-2.5 text-slate-600">{String(l.departamento ?? '—')}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums">{inteiro(l.direito)}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums text-amber-700">{inteiro(l.gozados)}</td>
                        <td className={cls('px-4 py-2.5 text-right font-bold tabular-nums', Number(l.saldo) > 0 ? 'text-emerald-700' : 'text-slate-400')}>
                            {inteiro(l.saldo)}
                        </td>
                        <td className="px-4 py-2.5">
                            <span className="ml-auto block h-2 w-20 overflow-hidden rounded-full bg-slate-200">
                                <span className="block h-full rounded-full bg-indigo-500 transition-all duration-500" style={{ width: `${parte}%` }} />
                            </span>
                        </td>
                    </tr>
                );
            })}
        </Tabela>
    );
}

function QuadroDePessoal({ m }: { m: Mapa }) {
    const inicio = Number(m.totais?.inicio ?? 0);
    const fim = Number(m.totais?.fim ?? 0);
    const variacao = fim - inicio;

    return (
        <div className="space-y-4 p-5">
            <div className="grid gap-3 sm:grid-cols-3">
                <CartaoNumero aspecto="claro" rotulo={t('No início do ano')} tom="cinza" icone="fa-user-clock" valor={inteiro(inicio)} />
                <CartaoNumero aspecto="claro" rotulo={t('Agora')} tom="indigo" icone="fa-users" valor={inteiro(fim)} />
                <CartaoNumero aspecto="claro" rotulo={t('Variação no ano')}
                    tom={variacao > 0 ? 'verde' : variacao < 0 ? 'vermelho' : 'cinza'}
                    icone={variacao >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}
                    valor={`${variacao > 0 ? '+' : ''}${inteiro(variacao)}`} />
            </div>

            {m.grafico && (
                <GraficoDeBarras
                    dados={m.grafico.etiquetas.map((rotulo, i) => ({ rotulo, valor: m.grafico?.valores[i] ?? 0 }))}
                    titulo={t('Pessoas activas ao fim de cada mês')}
                />
            )}

            <Tabela
                minimo="28rem"
                colunas={[
                    { chave: 'mes', rotulo: t('Mês') },
                    { chave: 'pessoas', rotulo: t('Pessoas activas'), direita: true },
                    { chave: 'var', rotulo: t('Variação'), direita: true },
                ]}
            >
                {m.linhas.map((l, i) => {
                    const v = l.variacao === null ? null : Number(l.variacao);

                    return (
                        <tr key={i} className="entra transition-colors hover:bg-purple-50/40" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                            <td className="px-4 py-2.5 font-semibold capitalize text-slate-900">{String(l.nome_do_mes ?? '')}</td>
                            <td className="px-4 py-2.5 text-right tabular-nums">{inteiro(l.pessoas)}</td>
                            <td className={cls('px-4 py-2.5 text-right font-semibold tabular-nums',
                                v === null ? 'text-slate-300' : v > 0 ? 'text-emerald-700' : v < 0 ? 'text-red-700' : 'text-slate-400')}>
                                {v === null ? '—' : `${v > 0 ? '+' : ''}${inteiro(v)}`}
                            </td>
                        </tr>
                    );
                })}
            </Tabela>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
