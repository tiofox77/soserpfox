import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    fecho,
    type EstadaPorSair,
    type ExtraDoFecho,
    type FolioDaEstada,
    type OpcoesDoFecho,
} from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O CHECK-OUT — fechar a estada e emitir o documento.
 *
 * É o sítio do módulo onde os erros custam mais: um documento a mais na cadeia
 * SAFT só se anula por nota de crédito. Por isso o ecrã diz, ANTES de se
 * carregar em fechar, o que já está facturado — e desliga a emissão sozinho
 * quando o sinal já cobre a estada inteira.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • A PROCURA REBENTAVA: procurava por `rooms.room_number`, uma coluna que
 *    não existe (é `number`), e por `guest.name`, a ficha antiga que está
 *    vazia. Escrever no campo dava um erro de SQL.
 *  • A FIDELIDADE era dada ao `guest` (ficha vazia): uma estada de um cliente
 *    nunca contava para nada.
 *  • O QUARTO ficava em «limpeza» mas o seu estado de limpeza não mudava — o
 *    quadro da governanta não via o quarto que acabou de vagar.
 */

export default function CheckOut({ id }: { id?: number }) {
    const cache = useQueryClient();

    const [procura, porProcura] = useState('');
    const [aFechar, porAFechar] = useState<number | null>(id ?? null);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['hotel', 'fecho', 'opcoes'], queryFn: fecho.opcoes, staleTime: 5 * 60_000 });

    const lista = useQuery({
        queryKey: ['hotel', 'fecho', 'por-sair', procura],
        queryFn: () => fecho.porSair(procura),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const l = lista.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Check-out')}
                subtitulo={t('Fechar a estada e emitir o documento')}
                icone="fa-right-from-bracket"
                cor="laranja"
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <CartaoNumero aspecto="claro" rotulo={t('Já deviam ter saído')}
                    tom={l && l.atrasados.length > 0 ? 'vermelho' : 'cinza'} icone="fa-triangle-exclamation"
                    nota={t('libertar o quarto')} valor={l ? numero(l.atrasados.length) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Saem hoje')} tom="laranja" icone="fa-right-from-bracket"
                    valor={l ? numero(l.hoje.length) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Hospedados')} tom="azul" icone="fa-bed"
                    nota={t('no total')} valor={l ? numero(l.total) : '—'} />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <Campo etiqueta={t('Procurar')}>
                    <input type="search" value={procura} className={entrada}
                        placeholder={t('Nº da reserva, quarto ou nome do hóspede')}
                        onChange={(e) => porProcura(e.target.value)} />
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={8} />
            ) : lista.isError ? (
                <Falhou erro={lista.error} />
            ) : l && l.total === 0 ? (
                <div className={cls(CARTAO)}>
                    <SemNada
                        icone="fa-bed"
                        titulo={t('Ninguém hospedado')}
                        frase={t('Não há estadas em curso para fechar.')}
                    />
                </div>
            ) : l && (
                <div className={cls('space-y-4', lista.isFetching && 'opacity-70 transition-opacity')}>
                    {/* QUEM JÁ DEVIA TER SAÍDO VEM PRIMEIRO: é o quarto que a
                        recepção precisa de libertar. */}
                    <Grupo
                        titulo={t('Já deviam ter saído')}
                        icone="fa-triangle-exclamation"
                        cor="vermelho"
                        estadas={l.atrasados}
                        aoFechar={porAFechar}
                    />
                    <Grupo
                        titulo={t('Saem hoje')}
                        icone="fa-right-from-bracket"
                        cor="laranja"
                        estadas={l.hoje}
                        aoFechar={porAFechar}
                    />
                    <Grupo
                        titulo={t('Saem depois')}
                        icone="fa-calendar-days"
                        cor="cinza"
                        estadas={l.depois}
                        aoFechar={porAFechar}
                    />
                </div>
            )}

            {aFechar !== null && (
                <Fechar
                    id={aFechar}
                    o={o}
                    aoFechar={() => porAFechar(null)}
                    aoFeito={(mensagem) => {
                        void cache.invalidateQueries({ queryKey: ['hotel', 'fecho'] });
                        porAFechar(null); porRecado(mensagem);
                    }}
                />
            )}
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

const CABECA: Record<string, string> = {
    vermelho: 'text-red-700',
    laranja: 'text-orange-700',
    cinza: 'text-slate-600',
};

function Grupo({ titulo, icone, cor, estadas, aoFechar }: {
    titulo: string;
    icone: string;
    cor: string;
    estadas: EstadaPorSair[];
    aoFechar: (id: number) => void;
}) {
    if (estadas.length === 0) return null;

    return (
        <section className={cls(CARTAO, 'p-4')}>
            <h2 className={cls('mb-3 flex items-center gap-2 text-sm font-bold', CABECA[cor])}>
                <i className={cls('fas', icone)} aria-hidden="true" />
                {titulo}
                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-500">
                    {estadas.length}
                </span>
            </h2>

            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {estadas.map((e, i) => (
                    <li key={e.id} className="entra" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                        <div className={cls(
                            'flex h-full flex-col border border-slate-200 bg-white p-4 transition-all duration-200',
                            'hover:-translate-y-0.5 hover:shadow-md', RAIO,
                        )}>
                            <div className="flex items-start justify-between gap-2">
                                <span className="min-w-0">
                                    <span className="block truncate font-bold text-slate-800">{e.hospede}</span>
                                    <span className="block font-mono text-xs text-slate-400">{e.numero}</span>
                                </span>
                                <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-gradient-to-br from-orange-500 to-red-500 text-sm font-bold text-white shadow">
                                    {e.quarto ?? '—'}
                                </span>
                            </div>

                            <p className="mt-2 text-xs text-slate-500">
                                <i className="fas fa-calendar-days mr-1.5" aria-hidden="true" />
                                {e.entrada && e.saida
                                    ? t(':de a :ate', { de: data(e.entrada), ate: data(e.saida) })
                                    : '—'}
                                <span className="ml-2">{t(':n noite(s)', { n: e.noites })}</span>
                            </p>

                            <dl className="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
                                <div className="flex justify-between gap-2">
                                    <dt className="text-slate-500">{t('Total')}</dt>
                                    <dd className="font-bold tabular-nums text-slate-800">{kz(e.total)} Kz</dd>
                                </div>
                                {e.por_receber > 0 && (
                                    <div className="flex justify-between gap-2">
                                        <dt className="text-red-600">{t('Por receber')}</dt>
                                        <dd className="font-bold tabular-nums text-red-600">{kz(e.por_receber)} Kz</dd>
                                    </div>
                                )}
                            </dl>

                            <div className="mt-3 flex gap-2">
                                <Botao cor="aviso" tom="solida" icone="fa-right-from-bracket"
                                    onClick={() => aoFechar(e.id)}>
                                    {t('Check-out')}
                                </Botao>
                                <a href={`/hotel/reservations/${e.id}/folio`} title={t('Folio')}
                                    className={cls('inline-flex items-center gap-1.5 border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:border-slate-300', RAIO, FOCO)}>
                                    <i className="fas fa-list-ul" aria-hidden="true" />
                                </a>
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Fechar({ id, o, aoFechar, aoFeito }: {
    id: number;
    o: OpcoesDoFecho;
    aoFechar: () => void;
    aoFeito: (mensagem: string) => void;
}) {
    const [extras, porExtras] = useState<ExtraDoFecho[]>([]);
    const [novo, porNovo] = useState({ description: '', quantity: '1', unit_price: '' });
    const [pagamento, porPagamento] = useState('');
    const [meio, porMeio] = useState('');
    const [facturar, porFacturar] = useState(true);
    const [mexeuNaFactura, porMexeuNaFactura] = useState(false);
    const [notas, porNotas] = useState('');

    const folio = useQuery({ queryKey: ['hotel', 'folio', id], queryFn: () => fecho.conta(id) });

    const fechar = useMutation({
        mutationFn: () => fecho.fechar(id, { extras, pagamento, meio, facturar, notas }),
        onSuccess: (r) => aoFeito(r.message),
    });

    const f = folio.data;

    /*
     * A CONTA COM OS EXTRAS DESTE ECRÃ INCLUÍDOS.
     *
     * O servidor faz a mesma soma ao gravar; aqui é só para o operador ver o
     * que vai cobrar antes de carregar no botão.
     */
    const porLancar = extras.reduce((s, x) => s + x.quantity * x.unit_price, 0);
    const base = f ? Math.max(0, f.conta.base + porLancar) : 0;
    const taxa = f && f.conta.base > 0 ? f.conta.imposto / f.conta.base : 0;
    const imposto = Math.round(base * taxa * 100) / 100;
    const total = base + imposto;
    const porReceber = f ? Math.max(0, total - f.conta.pago) : 0;

    // O saldo por omissão, e a emissão desligada sozinha quando o sinal já
    // cobre tudo — mas só enquanto o operador não mexer na caixa.
    useEffect(() => {
        porPagamento(String(porReceber));
    }, [porReceber]);

    useEffect(() => {
        if (f && ! mexeuNaFactura) {
            porFacturar(! (f.conta.ja_facturada && porLancar === 0));
        }
    }, [f, porLancar, mexeuNaFactura]);

    useEffect(() => {
        porMeio((m) => m || o.meios_de_pagamento[0]?.valor || '');
    }, [o.meios_de_pagamento]);

    const daApi = fechar.error instanceof ErroDaApi ? fechar.error : null;

    const juntarExtra = () => {
        const q = Number(novo.quantity) || 0;
        const p = Number(novo.unit_price) || 0;

        if (! novo.description.trim() || q <= 0 || p <= 0) return;

        porExtras((xs) => [...xs, { description: novo.description.trim(), quantity: q, unit_price: p }]);
        porNovo({ description: '', quantity: '1', unit_price: '' });
    };

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Check-out')}
            subtitulo={f ? t(':numero · :hospede · quarto :quarto', {
                numero: f.reserva.numero, hospede: f.reserva.hospede, quarto: f.reserva.quarto ?? '—',
            }) : undefined}
            icone="fa-right-from-bracket"
            cor="laranja"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="aviso" tom="solida" icone="fa-right-from-bracket" aTrabalhar={fechar.isPending}
                        disabled={! f || ! f.aberto}
                        onClick={() => fechar.mutate()}>
                        {t('Confirmar check-out')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={fechar.error} />

            {folio.isPending ? (
                <Carregando linhas={8} />
            ) : folio.isError ? (
                <Falhou erro={folio.error} />
            ) : f && (
                <div className="space-y-4">
                    {! f.aberto && f.porque_fechou && (
                        <p className={cls('border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700', RAIO)} role="alert">
                            <i className="fas fa-lock mr-2 text-slate-400" aria-hidden="true" />
                            {f.porque_fechou}
                        </p>
                    )}

                    {/* O QUE JÁ ESTÁ FACTURADO, dito ANTES de se carregar em
                        fechar: se o sinal já cobre a estada, emitir outra é um
                        documento a mais na cadeia SAFT. */}
                    {f.conta.facturas.length > 0 && (
                        <section className={cls('border border-indigo-200 bg-indigo-50 p-3', RAIO)}>
                            <h3 className="mb-2 text-sm font-bold text-indigo-900">
                                <i className="fas fa-file-invoice mr-2" aria-hidden="true" />
                                {t('Já facturado nesta estada')}
                            </h3>
                            <ul className="space-y-1 text-sm text-indigo-800">
                                {f.conta.facturas.map((x) => (
                                    <li key={x.id} className="flex justify-between gap-2">
                                        <a href={`/invoicing/sales/invoices/${x.id}/preview`} target="_blank" rel="noreferrer"
                                            className={cls('font-semibold hover:underline', FOCO, RAIO)}>
                                            {x.numero}
                                        </a>
                                        <span className="tabular-nums">{kz(x.total)} Kz</span>
                                    </li>
                                ))}
                            </ul>
                            {f.conta.ja_facturada && porLancar === 0 && (
                                <p className="mt-2 border-t border-indigo-200 pt-2 text-xs font-semibold text-indigo-900">
                                    <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                                    {t('A estada já está toda facturada. Não emita outra factura — reimprima a que existe.')}
                                </p>
                            )}
                        </section>
                    )}

                    {/* Os consumos do folio — os que já lá estão. */}
                    {f.consumos.length > 0 && (
                        <section className={cls('border border-slate-200 p-3', RAIO)}>
                            <h3 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-list-ul mr-2 text-slate-400" aria-hidden="true" />
                                {t('Consumos do folio')}
                            </h3>
                            <ul className="max-h-40 space-y-1 overflow-y-auto text-sm">
                                {f.consumos.map((c) => (
                                    <li key={c.id} className="flex justify-between gap-2 text-slate-600">
                                        <span className="truncate">
                                            <i className={cls('fas mr-1.5 text-slate-400', c.icone)} aria-hidden="true" />
                                            {c.descricao}
                                        </span>
                                        <span className="tabular-nums">{kz(c.total)} Kz</span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {/* Um consumo de última hora, antes de fechar. */}
                    {f.aberto && (
                        <section className={cls('border border-slate-200 p-3', RAIO)}>
                            <h3 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-plus mr-2 text-slate-400" aria-hidden="true" />
                                {t('Juntar antes de fechar')}
                            </h3>

                            <div className="grid gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
                                <input value={novo.description} className={entrada}
                                    placeholder={t('Ex.: minibar')}
                                    aria-label={t('Descrição')}
                                    onChange={(e) => porNovo((n) => ({ ...n, description: e.target.value }))} />
                                <input type="number" min={0.01} step="0.01" value={novo.quantity}
                                    className={cls(entrada, 'w-20 text-right tabular-nums')}
                                    aria-label={t('Quantidade')}
                                    onChange={(e) => porNovo((n) => ({ ...n, quantity: e.target.value }))} />
                                <input type="number" min={0} step="0.01" value={novo.unit_price}
                                    className={cls(entrada, 'w-28 text-right tabular-nums')}
                                    aria-label={t('Preço unitário (Kz)')}
                                    placeholder={t('Preço')}
                                    onChange={(e) => porNovo((n) => ({ ...n, unit_price: e.target.value }))} />
                                <Botao icone="fa-plus" onClick={juntarExtra}>{t('Juntar')}</Botao>
                            </div>

                            {extras.length > 0 && (
                                <ul className="mt-2 space-y-1 text-sm">
                                    {extras.map((x, i) => (
                                        <li key={i} className="flex items-center justify-between gap-2 text-slate-700">
                                            <span className="truncate">{x.description} × {x.quantity}</span>
                                            <span className="flex items-center gap-2">
                                                <span className="tabular-nums">{kz(x.quantity * x.unit_price)} Kz</span>
                                                <button type="button" aria-label={t('Tirar')}
                                                    onClick={() => porExtras((xs) => xs.filter((_, j) => j !== i))}
                                                    className={cls('p-1 text-slate-400 transition-colors hover:text-red-600', RAIO, FOCO)}>
                                                    <i className="fas fa-xmark" aria-hidden="true" />
                                                </button>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}

                    {/* A CONTA. */}
                    <dl className={cls('space-y-1.5 border border-amber-200 bg-amber-50 p-4', RAIO)}>
                        <Linha rotulo={t('Alojamento')} valor={f.conta.alojamento} />
                        <Linha rotulo={t('Consumos')} valor={f.conta.consumos + porLancar} />
                        {f.conta.desconto > 0 && <Linha rotulo={t('Desconto')} valor={-f.conta.desconto} />}
                        <Linha rotulo={t('Imposto')} valor={imposto} />
                        <div className="flex items-baseline justify-between gap-3 border-t border-amber-200 pt-1.5">
                            <dt className="font-bold text-amber-900">{t('Total')}</dt>
                            <dd className="text-xl font-bold tabular-nums text-amber-900">{kz(total)} Kz</dd>
                        </div>
                        <Linha rotulo={t('Já pago')} valor={f.conta.pago} />
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <dt className={porReceber > 0 ? 'font-bold text-red-700' : 'text-emerald-700'}>
                                {t('Por receber')}
                            </dt>
                            <dd className={cls('font-bold tabular-nums', porReceber > 0 ? 'text-red-700' : 'text-emerald-700')}>
                                {kz(porReceber)} Kz
                            </dd>
                        </div>
                    </dl>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Campo etiqueta={t('Recebido agora (Kz)')} obrigatorio erro={daApi?.erros.pagamento}>
                            <input type="number" min={0} step="0.01" value={pagamento}
                                className={cls(entrada, 'text-right text-lg tabular-nums')}
                                onChange={(e) => porPagamento(e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Meio de pagamento')} erro={daApi?.erros.meio}>
                            <select value={meio} className={entrada} onChange={(e) => porMeio(e.target.value)}>
                                <option value="">—</option>
                                {o.meios_de_pagamento.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>

                    <label className={cls(
                        'flex cursor-pointer items-start gap-3 border p-3 transition-colors',
                        RAIO,
                        facturar ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50',
                    )}>
                        <input type="checkbox" checked={facturar}
                            className={cls('mt-0.5 h-5 w-5 rounded border-slate-300 text-indigo-600', FOCO)}
                            onChange={(e) => { porFacturar(e.target.checked); porMexeuNaFactura(true); }} />
                        <span>
                            <span className="block text-sm font-semibold text-slate-800">{t('Emitir factura')}</span>
                            <span className="block text-xs text-slate-500">
                                {t('O que já foi facturado nos adiantamentos é abatido — a mesma base não se tributa duas vezes.')}
                            </span>
                        </span>
                    </label>

                    {facturar && ! f.reserva.client_id && (
                        <p className={cls('border border-red-200 bg-red-50 p-3 text-sm text-red-800', RAIO)} role="alert">
                            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                            {t('Esta reserva não tem hóspede associado: não há a quem facturar. Associe um, ou feche sem documento.')}
                        </p>
                    )}

                    <Campo etiqueta={t('Notas da factura')}>
                        <textarea rows={2} value={notas} className={cls(entrada, 'h-auto py-2')}
                            onChange={(e) => porNotas(e.target.value)} />
                    </Campo>

                    <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <Etiqueta cor={f.reserva.estado === 'checked_in' ? 'bom' : 'neutra'}>
                            {f.reserva.estado_rotulo}
                        </Etiqueta>
                        <span>
                            {t(':de a :ate · :n noite(s)', {
                                de: f.reserva.entrada ? data(f.reserva.entrada) : '—',
                                ate: f.reserva.saida ? data(f.reserva.saida) : '—',
                                n: f.reserva.noites,
                            })}
                        </span>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-amber-800">{rotulo}</dt>
            <dd className="font-semibold tabular-nums text-amber-900">{kz(valor)} Kz</dd>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o check-out')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
