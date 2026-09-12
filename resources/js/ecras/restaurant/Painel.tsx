import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import { restaurante } from '@/api/restaurant';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls, dataHora, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DO RESTAURANTE.
 *
 * QUATRO PERGUNTAS e não uma parede de números: quanto se vendeu hoje (e se
 * isso é melhor ou pior do que ontem), quantas mesas estão ocupadas, a que
 * horas se vende, e o que é que sai mais.
 *
 * A COMPARAÇÃO COM ONTEM está no cartão e não num gráfico à parte: o número
 * de hoje sozinho não diz nada a quem abre o ecrã às três da tarde.
 */

const PERIODOS = [7, 14, 30, 90];

const COR_DO_ESTADO: Record<string, 'bom' | 'primaria' | 'aviso' | 'neutra' | 'perigo'> = {
    available: 'bom',
    reserved: 'primaria',
    occupied: 'aviso',
    waiting_kitchen: 'aviso',
    served: 'primaria',
    billing: 'aviso',
    cleaning: 'neutra',
    blocked: 'perigo',
};

export default function PainelDoRestaurante() {
    const [dias, porDias] = useState(14);

    const painel = useQuery({
        queryKey: ['restaurante', 'painel', dias],
        queryFn: () => restaurante.painel(dias),
        refetchInterval: 60_000,
    });

    if (painel.isPending) return <Carregando linhas={8} />;
    if (painel.isError) return <AvisoDeErro erro={painel.error} />;

    const p = painel.data;
    const r = p.resumo;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    // A diferença para ontem à mesma hora não existe; a diferença para o dia
    // inteiro de ontem existe e é a que toda a gente usa na cabeça.
    const variacao = r.venda_ontem > 0
        ? Math.round(((r.venda_hoje - r.venda_ontem) / r.venda_ontem) * 100)
        : null;

    const ocupacao = r.mesas > 0 ? Math.round((r.mesas_ocupadas / r.mesas) * 100) : 0;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Painel do Restaurante')}
                subtitulo={t('A venda de hoje, a sala e a forma da semana')}
                icone="fa-utensils"
                cor="laranja"
                accoes={
                    <>
                        <a href="/restaurant/pos" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-cash-register" aria-hidden="true" />
                            {t('Balcão')}
                        </a>
                        <a href="/restaurant/floor" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chair" aria-hidden="true" />
                            {t('Sala')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-receipt">
                        {t(':n comandas abertas', { n: numero(r.comandas_abertas) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-chair">
                        {t('Sala a :n%', { n: String(ocupacao) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro"
                    rotulo={t('Vendido hoje')}
                    valor={kz(r.venda_hoje)}
                    sufixo="Kz"
                    icone="fa-sack-dollar"
                    tom="verde"
                    nota={variacao === null
                        ? t('Ontem não houve venda')
                        : t(':v% que ontem (:k Kz)', { v: (variacao > 0 ? '+' : '') + variacao, k: kz(r.venda_ontem, 0) })}
                />
                <CartaoNumero
                    aspecto="claro"
                    rotulo={t('Comandas hoje')}
                    valor={numero(r.comandas_hoje)}
                    icone="fa-receipt"
                    tom="indigo"
                    nota={t(':n abertas agora', { n: numero(r.comandas_abertas) })}
                />
                <CartaoNumero
                    aspecto="claro"
                    rotulo={t('Ticket médio')}
                    valor={kz(r.ticket_medio)}
                    sufixo="Kz"
                    icone="fa-chart-line"
                    tom="laranja"
                    nota={t('Diz se se vende mais ou só se atende mais')}
                />
                <CartaoNumero
                    aspecto="claro"
                    rotulo={t('Mesas ocupadas')}
                    valor={`${numero(r.mesas_ocupadas)}/${numero(r.mesas)}`}
                    icone="fa-chair"
                    tom={ocupacao >= 80 ? 'vermelho' : ocupacao >= 50 ? 'ambar' : 'teal'}
                    nota={t(':n% da sala', { n: String(ocupacao) })}
                />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {t('Período')}
                </span>
                {PERIODOS.map((d) => (
                    <button
                        key={d}
                        type="button"
                        onClick={() => porDias(d)}
                        className={cls(
                            'px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                            RAIO, FOCO,
                            d === dias
                                ? 'bg-orange-600 text-white shadow-md'
                                : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                        )}
                    >
                        {t(':n dias', { n: String(d) })}
                    </button>
                ))}
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao titulo={t('Venda por dia')} icone="fa-calendar-day" subtitulo={t('Os dias fechados aparecem a zero')}>
                    <GraficoDeBarras
                        titulo={t('Venda por dia')}
                        dados={p.por_dia.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_dia.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('A que horas se vende')} icone="fa-clock" subtitulo={t('Das 6h à 1h — a escala de pessoal sai daqui')}>
                    <GraficoDeBarras
                        titulo={t('Venda por hora')}
                        dados={p.por_hora.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_hora.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Os que mais saem')} icone="fa-fire" subtitulo={t('Por quantidade servida')}>
                    <GraficoHorizontal
                        titulo={t('Pratos mais vendidos')}
                        unidade=""
                        vazio={t('Ainda não saiu nada neste período.')}
                        dados={p.pratos.etiquetas.map((e, i) => ({ rotulo: e, valor: p.pratos.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Quanto rende cada mesa')} icone="fa-chair" subtitulo={t('Uma mesa que nunca aparece aqui está mal colocada')}>
                    <GraficoHorizontal
                        titulo={t('Receita por mesa')}
                        vazio={t('Ainda não houve serviço à mesa neste período.')}
                        dados={p.por_mesa.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_mesa.valores[i] ?? 0 }))}
                    />
                </Cartao>
            </div>

            <div className="grid gap-5 lg:grid-cols-3">
                <Cartao titulo={t('A sala agora')} icone="fa-map" className="lg:col-span-1">
                    {p.mesas_por_estado.length === 0 ? (
                        <SemNada icone="fa-chair" frase={t('Ainda não há mesas nesta casa.')} />
                    ) : (
                        <ul className="space-y-2">
                            {p.mesas_por_estado.map((e) => (
                                <li key={e.estado} className="flex items-center justify-between gap-3">
                                    <Etiqueta cor={COR_DO_ESTADO[e.estado] ?? 'neutra'} ponto>
                                        {e.rotulo}
                                    </Etiqueta>
                                    <span className="text-sm font-bold tabular-nums text-slate-800">{numero(e.total)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao titulo={t('Últimas comandas')} icone="fa-clock-rotate-left" semPadding className="lg:col-span-2">
                    {p.ultimas.length === 0 ? (
                        <SemNada icone="fa-receipt" frase={t('Ainda não há comandas.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.ultimas.map((o, i) => (
                                <li key={o.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {o.numero}
                                            {o.mesa && <span className="ml-2 font-normal text-slate-500">· {o.mesa}</span>}
                                        </p>
                                        <p className="truncate text-xs text-slate-500">
                                            {o.canal_rotulo}
                                            {o.empregado && ` · ${o.empregado}`}
                                            {' · '}
                                            {dataHora(o.criada_em)}
                                        </p>
                                    </div>
                                    <span className="flex-none text-sm font-bold tabular-nums text-slate-900">
                                        {kz(o.total)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>
        </div>
    );
}
