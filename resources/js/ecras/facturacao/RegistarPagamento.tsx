import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { pagamentos, type TipoDeFactura } from '@/api/pagamentos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * REGISTAR O PAGAMENTO DE UMA FACTURA — o modal que as listas abrem.
 *
 * Mostra quanto falta, deixa escolher a forma, a conta ou a caixa, e usar
 * um adiantamento do cliente. O que acontece ao gravar — o recibo, o
 * movimento de tesouraria, o adiantamento abatido, o excedente que vira
 * adiantamento, a AGT — é do servidor (`RegistoDePagamento`), o mesmo que o
 * modal Livewire chama. Este ecrã só soma o que falta depois, para o
 * utilizador ver antes de confirmar.
 */
export function RegistarPagamento({ tipo, id, aoFechar, aoRegistar }: {
    tipo: TipoDeFactura;
    id: number;
    aoFechar: () => void;
    aoRegistar: (mensagem: string) => void;
}) {
    const ctx = useQuery({ queryKey: ['pagamentos', tipo, id], queryFn: () => pagamentos.contexto(tipo, id) });

    const [valor, porValor] = useState('');
    const [forma, porForma] = useState('cash');
    const [contaId, porContaId] = useState('');
    const [caixaId, porCaixaId] = useState('');
    const [referencia, porReferencia] = useState('');
    const [notas, porNotas] = useState('');
    const [usarAdiantamento, porUsarAdiantamento] = useState(false);
    const [adiantamentoId, porAdiantamentoId] = useState('');
    const [doAdiantamento, porDoAdiantamento] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    /* O valor proposto é o que falta; a conta e a caixa, as padrão. */
    useEffect(() => {
        const c = ctx.data;
        if (!c) return;
        porValor(String(c.por_pagar));
        porContaId(c.conta_padrao ? String(c.conta_padrao) : '');
        porCaixaId(c.caixa_padrao ? String(c.caixa_padrao) : '');
    }, [ctx.data]);

    const escolherAdiantamento = (idEscolhido: string) => {
        porAdiantamentoId(idEscolhido);
        const a = ctx.data?.adiantamentos.find((x) => String(x.id) === idEscolhido);
        if (a && ctx.data) {
            const usar = Math.min(a.disponivel, ctx.data.por_pagar);
            porDoAdiantamento(String(usar));
            porValor(String(Math.max(0, Math.round((ctx.data.por_pagar - usar) * 100) / 100)));
        }
    };

    const registar = useMutation({
        mutationFn: () =>
            pagamentos.registar(tipo, id, {
                amount: Number(valor) || 0,
                payment_method: forma,
                account_id: Number(contaId) || null,
                cash_register_id: Number(caixaId) || null,
                reference: referencia || null,
                notes: notas || null,
                advance_id: usarAdiantamento ? Number(adiantamentoId) || null : null,
                advance_amount: usarAdiantamento ? Number(doAdiantamento) || 0 : 0,
            }),
        onSuccess: (r) => aoRegistar(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const c = ctx.data;
    const pagoAgora = (Number(valor) || 0) + (usarAdiantamento ? Number(doAdiantamento) || 0 : 0);
    const faltaDepois = c ? Math.max(0, Math.round((c.por_pagar - pagoAgora) * 100) / 100) : 0;
    const excedente = c ? Math.max(0, Math.round((pagoAgora - c.por_pagar) * 100) / 100) : 0;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={c ? t('Pagar :numero', { numero: c.factura.numero }) : t('Pagar')}
            subtitulo={c ? c.factura.parte : undefined}
            icone="fa-money-bill-wave"
            cor="bom"
            largura="md"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="bom" tom="solida" icone="fa-money-bill-wave" aTrabalhar={registar.isPending} disabled={!c} onClick={() => registar.mutate()}>{t('Registar pagamento')}</Botao></>}
        >
            <AvisoDeErro erro={registar.error ?? ctx.error} />
            {!c ? <Carregando linhas={4} /> : (
                <div className="space-y-4">
                    {/* OS TRÊS NÚMEROS DA FACTURA, em destaque e cada um com o
                        seu ícone: quanto é, quanto já se pagou, e o que falta —
                        que é o que a pessoa está a olhar. */}
                    <div className={cls('grid grid-cols-1 gap-3 border border-indigo-200 bg-indigo-50 px-4 py-4 text-sm sm:grid-cols-3', RAIO)}>
                        <div><p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-900/70"><i className="fas fa-file-invoice" aria-hidden="true" />{c.factura.parte}</p><p className="mt-0.5 text-lg font-bold tabular-nums text-indigo-900">{kz(c.factura.total)}</p></div>
                        <div><p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800/70"><i className="fas fa-circle-check" aria-hidden="true" />{t('Já pago')}</p><p className="mt-0.5 text-lg font-bold tabular-nums text-emerald-700">{kz(c.factura.pago)}</p></div>
                        <div><p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-amber-800/70"><i className="fas fa-hourglass-half" aria-hidden="true" />{t('Falta')}</p><p className="mt-0.5 text-xl font-bold tabular-nums text-amber-700" data-por-pagar>{kz(c.por_pagar)}</p></div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Valor a pagar (Kz)')} erro={erros.amount} obrigatorio>
                            <input type="number" min="0" step="0.01" value={valor} onChange={(e) => porValor(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta={t('Forma de pagamento')} erro={erros.payment_method} obrigatorio>
                            <select value={forma} onChange={(e) => porForma(e.target.value)} className={entrada}>
                                {c.formas.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                            </select>
                        </Campo>
                        {c.contas.length > 0 && (
                            <Campo etiqueta={t('Conta bancária')} erro={erros.account_id}>
                                <select value={contaId} onChange={(e) => porContaId(e.target.value)} className={entrada}>
                                    <option value="">{t('A que a tesouraria decidir')}</option>
                                    {c.contas.map((x) => <option key={x.id} value={x.id}>{x.nome}</option>)}
                                </select>
                            </Campo>
                        )}
                        {c.caixas.length > 0 && (
                            <Campo etiqueta={t('Caixa')} erro={erros.cash_register_id}>
                                <select value={caixaId} onChange={(e) => porCaixaId(e.target.value)} className={entrada}>
                                    <option value="">{t('A que a tesouraria decidir')}</option>
                                    {c.caixas.map((x) => <option key={x.id} value={x.id}>{x.nome}</option>)}
                                </select>
                            </Campo>
                        )}
                        <Campo etiqueta={t('Referência')} erro={erros.reference}><input value={referencia} onChange={(e) => porReferencia(e.target.value)} className={entrada} /></Campo>
                        <Campo etiqueta={t('Observações')} erro={erros.notes}><input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} /></Campo>
                    </div>

                    {c.adiantamentos.length > 0 && (
                        <div className={cls('border border-amber-200 bg-amber-50 p-3', RAIO)}>
                            <label className="flex cursor-pointer items-center gap-2 text-sm font-semibold text-amber-900">
                                <input type="checkbox" checked={usarAdiantamento} onChange={(e) => { porUsarAdiantamento(e.target.checked); if (e.target.checked && c.adiantamentos[0]) escolherAdiantamento(String(c.adiantamentos[0].id)); else { porDoAdiantamento(''); porValor(String(c.por_pagar)); } }} className="h-4 w-4 rounded border-amber-300 text-amber-600" />
                                <i className="fas fa-coins text-amber-600" aria-hidden="true" />
                                {t('Usar um adiantamento deste cliente')}
                            </label>
                            {usarAdiantamento && (
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <Campo etiqueta={t('Adiantamento')} erro={erros.advance_id}>
                                        <select value={adiantamentoId} onChange={(e) => escolherAdiantamento(e.target.value)} className={entrada}>
                                            {c.adiantamentos.map((a) => <option key={a.id} value={a.id}>{a.numero} · {kz(a.disponivel)} Kz</option>)}
                                        </select>
                                    </Campo>
                                    <Campo etiqueta={t('A abater (Kz)')} erro={erros.advance_amount}>
                                        <input type="number" min="0" step="0.01" value={doAdiantamento} onChange={(e) => porDoAdiantamento(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                                    </Campo>
                                </div>
                            )}
                        </div>
                    )}

                    {/* O QUE FICA DEPOIS DE CARREGAR — o «Resumo do Pagamento»
                        do ecrã de sempre. Verde quando a factura fica
                        liquidada, âmbar quando ainda sobra por pagar: a cor
                        acompanha o ícone, nunca vai sozinha. */}
                    <div
                        className={cls(
                            'flex items-start gap-3 border px-4 py-3 text-sm',
                            RAIO,
                            faltaDepois > 0 ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900',
                        )}
                        data-falta-depois
                    >
                        <span
                            className={cls(
                                'grid h-8 w-8 flex-none place-items-center rounded-lg',
                                faltaDepois > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700',
                            )}
                        >
                            <i className={`fas ${faltaDepois > 0 ? 'fa-hourglass-half' : 'fa-circle-check'}`} aria-hidden="true" />
                        </span>
                        <p className="min-w-0 leading-relaxed">
                            {faltaDepois > 0
                                ? <>{t('Depois deste pagamento ficam a faltar')}{' '}<strong className="text-base font-bold tabular-nums">{kz(faltaDepois)} Kz</strong>.</>
                                : excedente > 0 && tipo === 'sale'
                                    ? <>{t('Fica liquidada. O excedente de')}{' '}<strong className="text-base font-bold tabular-nums">{kz(excedente)} Kz</strong>{' '}{t('vira adiantamento do cliente.')}</>
                                    : <>{t('Fica liquidada.')}</>}
                        </p>
                    </div>
                </div>
            )}
        </Modal>
    );
}
