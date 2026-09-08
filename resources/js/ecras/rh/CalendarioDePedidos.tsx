import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { pedidos, type OpcoesDoPedido } from '@/api/pedidos';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O CALENDÁRIO DO MÊS — quem está fora, e quando.
 *
 * A lista responde a «que pedidos há». Isto responde à pergunta que se faz
 * ANTES de aprovar mais um: «quem já está fora nessa semana?». É o que impede
 * aprovar meia equipa para os mesmos dias — e era o que o ecrã de férias em
 * Blade tinha.
 *
 * NÃO É UM CALENDÁRIO COMPLETO nem precisa de o ser: é uma grelha de semanas
 * com os dias ocupados pintados, e a legenda ao lado. Uma biblioteca de
 * calendário para desenhar sete colunas seria 90 kB para o browser
 * descarregar e uma dependência a manter.
 */
export function CalendarioDePedidos({ tipo, o, aoAbrir }: {
    tipo: string;
    o: OpcoesDoPedido;
    aoAbrir: (id: number) => void;
}) {
    const hoje = new Date();
    const [ano, porAno] = useState(hoje.getFullYear());
    const [mes, porMes] = useState(hoje.getMonth() + 1);

    const q = useQuery({
        queryKey: ['rh', 'pedidos', tipo, 'calendario', ano, mes],
        queryFn: () => pedidos.calendario(tipo, ano, mes),
        placeholderData: keepPreviousData,
    });

    const andar = (passo: number) => {
        const d = new Date(ano, mes - 1 + passo, 1);
        porAno(d.getFullYear());
        porMes(d.getMonth() + 1);
    };

    /* Os dias do mês, e em que coluna cada um cai (segunda = 0). */
    const primeiro = new Date(ano, mes - 1, 1);
    const quantosDias = new Date(ano, mes, 0).getDate();
    const recuo = (primeiro.getDay() + 6) % 7;

    const eventos = q.data?.eventos ?? [];

    /*
     * QUANTOS ESTÃO FORA EM CADA DIA — contado uma vez, e não por célula.
     *
     * Com trinta pessoas e trinta e um dias, perguntar por célula é mil
     * comparações a cada desenho.
     */
    const porDia = new Map<string, typeof eventos>();

    for (const e of eventos) {
        if (!e.de || !e.ate) continue;

        for (let d = new Date(e.de); d <= new Date(e.ate); d.setDate(d.getDate() + 1)) {
            const chave = d.toISOString().slice(0, 10);
            porDia.set(chave, [...(porDia.get(chave) ?? []), e]);
        }
    }

    const nomeDoMes = new Date(ano, mes - 1, 1).toLocaleDateString(etiquetaIntl(), { month: 'long', year: 'numeric' });

    return (
        <Cartao
            titulo={t('Calendário')}
            icone="fa-calendar-days"
            accoes={
                <div className="flex items-center gap-2">
                    <Botao altura="pequeno" icone="fa-chevron-left" onClick={() => andar(-1)}>{t('Anterior')}</Botao>
                    <span className="min-w-[9rem] text-center text-sm font-bold capitalize text-slate-700">{nomeDoMes}</span>
                    <Botao altura="pequeno" icone="fa-chevron-right" onClick={() => andar(1)}>{t('Seguinte')}</Botao>
                </div>
            }
        >
            {q.isPending ? (
                <Carregando linhas={4} />
            ) : (
                <div className="space-y-3">
                    <div className="grid grid-cols-7 gap-1 text-center">
                        {['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].map((d) => (
                            <span key={d} className="py-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">{t(d)}</span>
                        ))}

                        {Array.from({ length: recuo }).map((_, i) => <span key={`vazio-${i}`} />)}

                        {Array.from({ length: quantosDias }, (_, i) => i + 1).map((dia) => {
                            const chave = `${ano}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                            const nesse = porDia.get(chave) ?? [];
                            const eHoje = chave === new Date().toISOString().slice(0, 10);
                            const fimDeSemana = (new Date(ano, mes - 1, dia).getDay() + 6) % 7 >= 5;

                            return (
                                <div
                                    key={dia}
                                    className={cls(
                                        'min-h-[4.5rem] border p-1 text-left transition-colors duration-200',
                                        RAIO,
                                        eHoje ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-slate-200',
                                        fimDeSemana && nesse.length === 0 && 'bg-slate-50',
                                    )}
                                >
                                    <span className={cls('block text-xs font-bold tabular-nums', eHoje ? 'text-indigo-700' : 'text-slate-400')}>
                                        {dia}
                                    </span>

                                    <span className="mt-0.5 flex flex-col gap-0.5">
                                        {nesse.slice(0, 2).map((e) => (
                                            <button
                                                key={e.id}
                                                type="button"
                                                onClick={() => aoAbrir(e.id)}
                                                title={`${e.funcionario} · ${e.numero}`}
                                                className={cls(
                                                    'truncate rounded px-1 py-0.5 text-left text-[10px] font-semibold transition-all duration-150 hover:scale-[1.03]',
                                                    FOCO,
                                                    e.estado === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800',
                                                )}
                                            >
                                                {e.funcionario.split(' ')[0]}
                                            </button>
                                        ))}

                                        {nesse.length > 2 && (
                                            <span className="px-1 text-[10px] font-semibold text-slate-400">
                                                {t('+:quantos', { quantos: nesse.length - 2 })}
                                            </span>
                                        )}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                        <span className="inline-flex items-center gap-1.5">
                            <span className="inline-block h-3 w-3 rounded bg-amber-100" aria-hidden="true" />
                            {t('Por aprovar')}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span className="inline-block h-3 w-3 rounded bg-emerald-100" aria-hidden="true" />
                            {t('Aprovado')}
                        </span>
                        <span className="ml-auto">
                            {t(':quantos no mês', { quantos: eventos.length })}
                        </span>
                    </div>
                </div>
            )}
        </Cartao>
    );
}
