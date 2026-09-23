import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { movimentos } from '@/api/tesouraria';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

/**
 * ARRUMAR DE UMA VEZ os movimentos que não caíram em conta nem caixa
 * (23/09/2026).
 *
 * O painel dizia «38 movimentos por arrumar» e levava à lista inteira de
 * movimentos: arrumá-los era abri-los um a um. Aqui vêm juntos por forma de
 * pagamento, cada grupo já com o destino sugerido (o numerário à caixa de quem
 * o registou, o TPA à conta da forma), e um só botão arruma tudo. A sugestão
 * troca-se no grupo; um grupo sem sugestão tem de ser escolhido.
 */
export function ArrumarMovimentos({ aberto, aoFechar }: { aberto: boolean; aoFechar: () => void }) {
    const cliente = useQueryClient();
    const [, porRecado] = useRecadoNoCanto('');
    const [escolhas, porEscolhas] = useState<Record<string, string>>({});
    const [erro, porErro] = useState<string | null>(null);

    const dados = useQuery({ queryKey: ['tesouraria', 'por-arrumar'], queryFn: movimentos.porArrumar, enabled: aberto });

    // A sugestão de cada grupo é a escolha de partida.
    useEffect(() => {
        if (dados.data) {
            porEscolhas(Object.fromEntries(dados.data.grupos.map((g) => [g.chave, g.sugestao ?? ''])));
        }
    }, [dados.data]);

    const semDestino = useMemo(() => (dados.data?.grupos ?? []).filter((g) => !escolhas[g.chave]).length, [dados.data, escolhas]);

    const arrumar = useMutation({
        mutationFn: () => movimentos.arrumar((dados.data?.grupos ?? []).map((g) => ({ ids: g.ids, destino: escolhas[g.chave] ?? '' }))),
        meta: { aviso: false },
        onSuccess: (r) => {
            porRecado(r.message);
            cliente.invalidateQueries({ queryKey: ['tesouraria'] });
            aoFechar();
        },
        onError: (e) => porErro(e instanceof ErroDaApi ? e.message : t('Não foi possível arrumar os movimentos.')),
    });

    const total = dados.data?.total;

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Arrumar movimentos')}
            subtitulo={t('Dinheiro registado que não caiu em conta nem caixa nenhum')}
            icone="fa-screwdriver-wrench"
            cor="aviso"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times" disabled={arrumar.isPending}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-check"
                        aTrabalhar={arrumar.isPending}
                        disabled={!total?.movimentos || semDestino > 0}
                        onClick={() => { porErro(null); arrumar.mutate(); }}
                    >
                        {t('Arrumar :n movimentos', { n: total?.movimentos ?? 0 })}
                    </Botao>
                </>
            }
        >
            {dados.isPending && <Carregando linhas={4} />}

            {dados.data && dados.data.grupos.length === 0 && (
                <p className="text-sm text-slate-600">{t('Não há nada por arrumar.')}</p>
            )}

            {dados.data && dados.data.grupos.length > 0 && (
                <div className="space-y-4">
                    <p className="text-sm text-slate-600">
                        {t('Cada grupo já vem com um destino sugerido: o numerário vai para a caixa de quem o registou, o resto para a conta da forma de pagamento. Confirme ou troque e carregue em «Arrumar». O saldo da caixa ou conta escolhida passa a contar com este dinheiro.')}
                    </p>

                    {erro && (
                        <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>{erro}</div>
                    )}

                    <ul className="divide-y divide-slate-200 border border-slate-200" data-grupos>
                        {dados.data.grupos.map((g) => (
                            <li key={g.chave} className="flex flex-wrap items-center gap-3 px-4 py-3">
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-slate-900">
                                        <i className={cls('fas mr-2', g.numerario ? 'fa-money-bill-wave text-emerald-600' : 'fa-credit-card text-indigo-600')} aria-hidden="true" />
                                        {g.forma}
                                    </p>
                                    <p className="text-xs text-slate-500 tabular-nums">
                                        {t(':n movimento(s)', { n: g.movimentos })}
                                        {g.entradas > 0 && ` · ${t('entradas')} ${kz(g.entradas)}`}
                                        {g.saidas > 0 && ` · ${t('saídas')} ${kz(g.saidas)}`}
                                        {g.de && ` · ${g.de === g.ate ? g.de : `${g.de} → ${g.ate}`}`}
                                    </p>
                                </div>
                                <label className="w-full sm:w-72">
                                    <span className="sr-only">{t('Destino de :forma', { forma: g.forma })}</span>
                                    <select
                                        className={cls(entrada, !escolhas[g.chave] && 'border-amber-400')}
                                        value={escolhas[g.chave] ?? ''}
                                        onChange={(e) => porEscolhas((antes) => ({ ...antes, [g.chave]: e.target.value }))}
                                    >
                                        <option value="">{t('— Escolha a caixa ou conta —')}</option>
                                        <optgroup label={t('Caixas')}>
                                            {dados.data.destinos.filter((d) => d.tipo === 'caixa').map((d) => <option key={d.valor} value={d.valor}>{d.rotulo}</option>)}
                                        </optgroup>
                                        <optgroup label={t('Contas bancárias')}>
                                            {dados.data.destinos.filter((d) => d.tipo === 'conta').map((d) => <option key={d.valor} value={d.valor}>{d.rotulo}</option>)}
                                        </optgroup>
                                    </select>
                                </label>
                            </li>
                        ))}
                    </ul>

                    {semDestino > 0 && (
                        <p className="text-sm font-semibold text-amber-800">{t('Falta escolher o destino de :n grupo(s).', { n: semDestino })}</p>
                    )}
                </div>
            )}
        </Modal>
    );
}
