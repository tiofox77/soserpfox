import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { restaurante } from '@/api/restaurant';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS RELATÓRIOS DO RESTAURANTE.
 *
 * O QUE CONTA COMO VENDA são as comandas FECHADAS. Uma comanda aberta ainda
 * pode crescer ou ser anulada: contá-la seria dar por vendido o que ainda está
 * em cima da mesa.
 *
 * O DESPERDÍCIO APARECE PELO CUSTO e ao lado da venda — é a única forma de
 * perceber o peso que tem. Um número de quilos sozinho não se compara com nada.
 */

const ATALHOS = [
    { chave: 'hoje', rotulo: 'Hoje' },
    { chave: 'semana', rotulo: 'Esta semana' },
    { chave: 'mes', rotulo: 'Este mês' },
    { chave: 'mes-passado', rotulo: 'Mês passado' },
] as const;

function intervalo(chave: string): { de: string; ate: string } {
    const hoje = new Date();
    const iso = (d: Date) => d.toISOString().slice(0, 10);

    if (chave === 'hoje') return { de: iso(hoje), ate: iso(hoje) };

    if (chave === 'semana') {
        const inicio = new Date(hoje);
        // Segunda-feira: `getDay()` dá 0 ao domingo, e a semana da casa começa
        // à segunda como em todo o lado onde isto se usa.
        inicio.setDate(hoje.getDate() - ((hoje.getDay() + 6) % 7));

        return { de: iso(inicio), ate: iso(hoje) };
    }

    if (chave === 'mes-passado') {
        const inicio = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
        const fim = new Date(hoje.getFullYear(), hoje.getMonth(), 0);

        return { de: iso(inicio), ate: iso(fim) };
    }

    return { de: iso(new Date(hoje.getFullYear(), hoje.getMonth(), 1)), ate: iso(hoje) };
}

export default function Relatorios() {
    const [periodo, porPeriodo] = useState(() => intervalo('mes'));

    const dados = useQuery({
        queryKey: ['restaurante', 'relatorios', periodo],
        queryFn: () => restaurante.relatorios(periodo.de, periodo.ate),
        placeholderData: keepPreviousData,
    });

    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Relatórios do Restaurante')}
                subtitulo={t('Um período, e o que aconteceu nele')}
                icone="fa-chart-column"
                cor="roxo"
            />

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('De')} className="w-44">
                    <input type="date" value={periodo.de} onChange={(e) => porPeriodo({ ...periodo, de: e.target.value })} className={entrada} />
                </Campo>

                <Campo etiqueta={t('Até')} className="w-44">
                    <input type="date" value={periodo.ate} onChange={(e) => porPeriodo({ ...periodo, ate: e.target.value })} className={entrada} />
                </Campo>

                <div className="flex flex-wrap gap-1.5 pb-0.5">
                    {ATALHOS.map((a) => (
                        <Botao key={a.chave} onClick={() => porPeriodo(intervalo(a.chave))}>
                            {t(a.rotulo)}
                        </Botao>
                    ))}
                </div>
            </div>

            {dados.isPending ? (
                <Carregando linhas={8} />
            ) : dados.isError ? (
                <AvisoDeErro erro={dados.error} />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Vendido')} valor={kz(dados.data.resumo.vendas)} sufixo="Kz"
                            icone="fa-sack-dollar" tom="verde" nota={t('Só comandas fechadas')}
                        />
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Comandas')} valor={numero(dados.data.resumo.comandas)}
                            icone="fa-receipt" tom="indigo"
                            nota={dados.data.resumo.anuladas > 0
                                ? t(':n anuladas no período', { n: numero(dados.data.resumo.anuladas) })
                                : undefined}
                        />
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Ticket médio')} valor={kz(dados.data.resumo.ticket)} sufixo="Kz"
                            icone="fa-chart-line" tom="laranja"
                        />
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Desperdício')} valor={kz(dados.data.resumo.desperdicio)} sufixo="Kz"
                            icone="fa-trash-can" tom="ambar" nota={t('Ao custo, para se comparar com a venda')}
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className={cls(CARTAO, 'flex items-center gap-4 p-5')}>
                            <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-emerald-50 text-xl text-emerald-600">
                                <i className="fas fa-hand-holding-dollar" aria-hidden="true" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Gorjetas')}</p>
                                <p className="text-xl font-bold tabular-nums text-slate-900">
                                    {kz(dados.data.resumo.gorjetas)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                </p>
                                <p className="text-xs text-slate-500">{t('Do pessoal — não entram na factura')}</p>
                            </div>
                        </div>

                        <div className={cls(CARTAO, 'flex items-center gap-4 p-5')}>
                            <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-cyan-50 text-xl text-cyan-600">
                                <i className="fas fa-calendar-check" aria-hidden="true" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Reservas')}</p>
                                <p className="text-xl font-bold tabular-nums text-slate-900">{numero(dados.data.resumo.reservas)}</p>
                                <p className="text-xs text-slate-500">{t('Marcadas para este período')}</p>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <Cartao titulo={t('Os que mais saem')} icone="fa-fire" subtitulo={t('Por quantidade servida')}>
                            <GraficoHorizontal
                                titulo={t('Pratos mais vendidos')}
                                unidade=""
                                vazio={t('Não saiu nada neste período.')}
                                dados={dados.data.grafico_de_pratos.etiquetas.map((e, i) => ({
                                    rotulo: e, valor: dados.data.grafico_de_pratos.valores[i] ?? 0,
                                }))}
                            />
                        </Cartao>

                        <Cartao titulo={t('Por canal')} icone="fa-diagram-project" semPadding>
                            {dados.data.canais.length === 0 ? (
                                <SemNada icone="fa-diagram-project" frase={t('Nenhuma comanda fechada neste período.')} />
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {dados.data.canais.map((c, i) => (
                                        <li key={c.canal} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-800">{c.rotulo}</p>
                                                <p className="text-xs text-slate-500">
                                                    {t(':n comandas', { n: numero(c.comandas) })}
                                                    {c.entregas > 0 && ` · ${t('entregas :v Kz', { v: kz(c.entregas, 0) })}`}
                                                </p>
                                            </div>
                                            <p className="text-sm font-bold tabular-nums text-slate-900">
                                                {kz(c.total)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Cartao>
                    </div>

                    <Cartao titulo={t('Pratos vendidos')} icone="fa-list" semPadding>
                        {dados.data.pratos.length === 0 ? (
                            <SemNada icone="fa-utensils" frase={t('Nenhum prato servido neste período.')} />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[28rem] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50/60 text-left">
                                            <th scope="col" className="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Prato')}</th>
                                            <th scope="col" className="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Quantidade')}</th>
                                            <th scope="col" className="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {dados.data.pratos.map((p, i) => (
                                            <tr key={p.nome} style={cascata(i)} className="entra">
                                                <td className="px-5 py-2.5 font-medium text-slate-800">{p.nome}</td>
                                                <td className="px-5 py-2.5 text-right tabular-nums text-slate-700">{kz(p.quantidade, 0)}</td>
                                                <td className="px-5 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(p.total)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Cartao>
                </>
            )}
        </div>
    );
}
