import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { entregaDaOrdem, type EntregaParaGravar, type RespostaDaEntrega } from '@/api/oficina';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { QuadroDeAssinatura, type QuadroDeAssinaturaRef } from '@/ui/QuadroDeAssinatura';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora, kz } from '@/ui/tokens';

/**
 * A ENTREGA DA VIATURA (15/09/2026, OF-13).
 *
 * O termo de levantamento: o dinheiro à vista (factura, pago, em falta), os km
 * e o combustível à saída ao lado dos da entrada, o que se conferiu com o
 * cliente e a assinatura de quem levanta — que pode também dar a ordem por
 * Entregue.
 */

const MARCAS_DO_DEPOSITO = ['E', '', '¼', '', '½', '', '¾', '', 'F'];

const ICONE_DO_CONFERIDO: Record<string, string> = {
    trabalho_explicado: 'fa-comments',
    pecas_substituidas: 'fa-gears',
    teste_estrada: 'fa-road',
    sem_luzes: 'fa-lightbulb',
    viatura_limpa: 'fa-soap',
    objectos_devolvidos: 'fa-bag-shopping',
    chaves_documentos: 'fa-key',
};

const doServidor = (r: RespostaDaEntrega): EntregaParaGravar => ({
    km_saida: r.data.km_saida ?? (r.entrada.km || null),
    combustivel: r.data.combustivel ?? r.entrada.combustivel,
    conferido: r.data.conferido,
    nome: r.data.nome ?? r.ordem.dono ?? '',
    documento: r.data.documento ?? '',
    notas: r.data.notas ?? '',
});

export function EntregaDaViatura({ id, aoMudar }: { id: number; aoMudar: (m: string) => void }) {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'ordens', 'entrega', id], queryFn: () => entregaDaOrdem.ler(id) });
    const [form, porForm] = useState<EntregaParaGravar | null>(null);
    const [mexido, porMexido] = useState(false);
    const [aAssinar, porAAssinar] = useState(false);

    useEffect(() => {
        if (q.data && !mexido) porForm(doServidor(q.data));
    }, [q.data, mexido]);

    const responder = (r: RespostaDaEntrega) => {
        cache.setQueryData(['oficina', 'ordens', 'entrega', id], r);
        porForm(doServidor(r));
        porMexido(false);
        void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'], predicate: (x) => x.queryKey[2] !== 'entrega' });
        if (r.message) aoMudar(r.message);
    };

    const gravar = useMutation({ mutationFn: () => entregaDaOrdem.gravar(id, form as EntregaParaGravar), onSuccess: responder });
    const tirar = useMutation({ mutationFn: () => entregaDaOrdem.tirarAssinatura(id), onSuccess: responder });

    if (q.isPending || !form) return <Carregando linhas={6} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const r = q.data;
    const e = r.data;
    const podeEditar = r.pode_editar;
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};
    const mudar = (p: Partial<EntregaParaGravar>) => { porForm({ ...form, ...p }); porMexido(true); };
    const andados = form.km_saida !== null && r.entrada.km ? form.km_saida - r.entrada.km : null;
    const valida = e.assinatura_valida && !mexido;
    const caixa = cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-50', RAIO, FOCO);

    return (
        <div className="space-y-4">
            {/* O DINHEIRO À VISTA — quem entrega sabe o que falta antes de dar as chaves. */}
            <div className="grid gap-3 sm:grid-cols-3">
                {[
                    { rotulo: r.contas.factura ? t('Facturado') : t('Total da ordem'), valor: r.contas.total, icone: 'fa-file-invoice', tom: 'from-indigo-500 to-violet-600' },
                    { rotulo: t('Pago'), valor: r.contas.pago, icone: 'fa-circle-check', tom: 'from-emerald-500 to-teal-600' },
                    { rotulo: t('Em falta'), valor: r.contas.falta, icone: r.contas.falta > 0 ? 'fa-triangle-exclamation' : 'fa-thumbs-up', tom: r.contas.falta > 0 ? 'from-red-500 to-rose-600' : 'from-emerald-500 to-green-600' },
                ].map((c, i) => (
                    <div key={c.rotulo} style={cascata(i)} className={cls('entra card-hover flex items-center gap-3 border border-slate-200 bg-white p-3 shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-md')}>
                        <span className={cls('grid h-10 w-10 flex-none place-items-center rounded-xl bg-gradient-to-br text-white shadow', c.tom)}>
                            <i className={cls('fas icon-float', c.icone)} aria-hidden="true" />
                        </span>
                        <span>
                            <span className="block text-xs font-semibold text-slate-500">{c.rotulo}</span>
                            <span className="block text-lg font-extrabold tabular-nums text-slate-900">{kz(c.valor)} <span className="text-xs font-semibold text-slate-400">Kz</span></span>
                        </span>
                    </div>
                ))}
            </div>
            <p className={cls('flex flex-wrap items-center gap-2 border px-3 py-2 text-xs', RAIO,
                !r.contas.factura ? 'border-amber-200 bg-amber-50 text-amber-900' : r.contas.falta > 0 ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800')}>
                <i className={cls('fas', !r.contas.factura ? 'fa-file-circle-exclamation' : r.contas.falta > 0 ? 'fa-hand-holding-dollar' : 'fa-circle-check')} aria-hidden="true" />
                {!r.contas.factura
                    ? t('Esta ordem ainda não foi facturada.')
                    : r.contas.falta > 0
                        ? t('A factura :numero tem :valor Kz por pagar.', { numero: r.contas.factura.numero, valor: kz(r.contas.falta) })
                        : t('A factura :numero está paga.', { numero: r.contas.factura.numero })}
                {r.contas.factura && (
                    <a href={r.contas.factura.morada} target="_blank" rel="noopener noreferrer" className={cls('ml-auto font-semibold underline-offset-2 hover:underline', FOCO)}>
                        <i className="fas fa-arrow-up-right-from-square mr-1" aria-hidden="true" />{t('Ver a factura')}
                    </a>
                )}
            </p>

            {/* OF-17: o cliente ainda tem a viatura de cortesia — recebe-se antes de lhe dar a dele. */}
            {r.cortesia && (
                <a href="/workshop/courtesy-cars" className={cls('group flex flex-wrap items-center gap-3 border border-amber-300 bg-gradient-to-r from-amber-50 to-orange-50 p-3 text-sm text-amber-900 shadow-sm', RAIO_GRANDE, TRANSICAO, FOCO, 'hover:-translate-y-0.5 hover:shadow-md')}>
                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow"><i className="fas fa-car-side icon-float" aria-hidden="true" /></span>
                    <span className="min-w-0 flex-1">
                        <span className="block font-semibold">{t('O cliente tem a viatura de cortesia :m', { m: r.cortesia.matricula ?? '' })}</span>
                        <span className="block text-xs">{t('Saiu a :data com :km km — receba-a antes de entregar.', { data: dataHora(r.cortesia.saida), km: r.cortesia.km_saida.toLocaleString('pt-PT') })}</span>
                    </span>
                    <i className="fas fa-arrow-right transition-transform group-hover:translate-x-1" aria-hidden="true" />
                </a>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)}>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800"><i className="fas fa-gauge-high text-indigo-500" aria-hidden="true" />{t('Como sai')}</h3>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium text-slate-700">{t('Km à saída')}</span>
                            <input type="number" min={0} inputMode="numeric" value={form.km_saida ?? ''} disabled={!podeEditar}
                                onChange={(ev) => mudar({ km_saida: ev.target.value === '' ? null : Number(ev.target.value) })} className={cls(caixa, 'tabular-nums')} />
                            {erros.km_saida && <span className="mt-1 block text-xs text-red-600">{erros.km_saida[0]}</span>}
                        </label>
                        <div className="text-sm">
                            <span className="mb-1 block font-medium text-slate-700">{t('À entrada')}</span>
                            <p className={cls('flex h-[38px] items-center gap-2 bg-slate-50 px-3 tabular-nums text-slate-600', RAIO)}>
                                {r.entrada.km.toLocaleString('pt-PT')} km
                                {andados !== null && andados > 0 && <span className="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">+{andados.toLocaleString('pt-PT')}</span>}
                            </p>
                        </div>
                    </div>

                    <p className="mb-1.5 mt-4 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                        <i className="fas fa-gas-pump text-slate-400" aria-hidden="true" />{t('Combustível à saída')}
                        <span className="ml-auto text-xs text-slate-500">
                            {r.entrada.combustivel !== null && t('Entrou com :n de 8', { n: r.entrada.combustivel })}
                        </span>
                    </p>
                    <div role="radiogroup" aria-label={t('Combustível à saída')} className="grid grid-cols-9 gap-1">
                        {MARCAS_DO_DEPOSITO.map((marca, n) => {
                            const cheio = form.combustivel !== null && n <= form.combustivel && n > 0;
                            const cor = n <= 2 ? 'bg-red-500' : n <= 4 ? 'bg-amber-400' : 'bg-emerald-500';
                            return (
                                <button key={n} type="button" role="radio" aria-checked={form.combustivel === n} disabled={!podeEditar}
                                    aria-label={n === 0 ? t('Vazio') : n === 8 ? t('Cheio') : t(':n de 8', { n })}
                                    onClick={() => mudar({ combustivel: form.combustivel === n ? null : n })}
                                    className={cls('group flex flex-col items-center gap-1', FOCO, RAIO)}>
                                    <span className={cls('relative h-7 w-full rounded-md ring-1 ring-inset transition-all duration-300', cheio ? cls(cor, 'ring-transparent shadow-sm') : 'bg-slate-100 ring-slate-200 group-hover:bg-slate-200',
                                        form.combustivel === n && 'ring-2 ring-indigo-500')}>
                                        {r.entrada.combustivel === n && <span className="absolute -top-1.5 left-1/2 h-2 w-2 -translate-x-1/2 rounded-full border border-white bg-slate-700" title={t('À entrada')} />}
                                    </span>
                                    <span className="h-3 text-[10px] font-bold text-slate-400">{marca}</span>
                                </button>
                            );
                        })}
                    </div>
                    {(r.entrada.chaves !== null || r.entrada.objectos) && (
                        <p className={cls('mt-4 bg-indigo-50 px-3 py-2 text-xs text-indigo-900', RAIO)}>
                            <i className="fas fa-clipboard-check mr-1.5" aria-hidden="true" />
                            {t('No check-in:')} {r.entrada.chaves !== null && t(':n chave(s)', { n: r.entrada.chaves })}{r.entrada.objectos && ` · ${r.entrada.objectos}`}
                        </p>
                    )}
                </section>

                <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)}>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                        <i className="fas fa-list-check text-emerald-500" aria-hidden="true" />{t('Conferido com o cliente')}
                        <span className="ml-auto text-xs font-semibold text-slate-400">{form.conferido.length}/{r.listas.checklist.length}</span>
                    </h3>
                    <ul className="space-y-1.5">
                        {r.listas.checklist.map((c, i) => {
                            const feito = form.conferido.includes(c.valor);
                            return (
                                <li key={c.valor} style={cascata(i)} className="entra">
                                    <button type="button" aria-pressed={feito} disabled={!podeEditar}
                                        onClick={() => mudar({ conferido: feito ? form.conferido.filter((x) => x !== c.valor) : [...form.conferido, c.valor] })}
                                        className={cls('flex w-full items-center gap-3 border px-3 py-2 text-left text-sm', RAIO, TRANSICAO, FOCO,
                                            feito ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50')}>
                                        <span className={cls('grid h-7 w-7 flex-none place-items-center rounded-lg transition-all duration-300', feito ? 'scale-110 bg-emerald-500 text-white shadow' : 'bg-slate-100 text-slate-400')}>
                                            <i className={cls('fas', feito ? 'fa-check' : ICONE_DO_CONFERIDO[c.valor] ?? 'fa-circle')} aria-hidden="true" />
                                        </span>
                                        {c.rotulo}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </section>
            </div>

            <section className={cls('grid gap-3 border border-slate-200 bg-white p-4 sm:grid-cols-2', RAIO_GRANDE)}>
                <label className="block text-sm">
                    <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-user mr-1.5 text-slate-400" aria-hidden="true" />{t('Quem levanta a viatura')}</span>
                    <input value={form.nome} maxLength={150} disabled={!podeEditar} onChange={(ev) => mudar({ nome: ev.target.value })} className={caixa} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-id-card mr-1.5 text-slate-400" aria-hidden="true" />{t('Documento (BI / carta)')}</span>
                    <input value={form.documento} maxLength={40} disabled={!podeEditar} onChange={(ev) => mudar({ documento: ev.target.value })} className={caixa} />
                </label>
                <label className="block text-sm sm:col-span-2">
                    <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-note-sticky mr-1.5 text-slate-400" aria-hidden="true" />{t('Notas internas')}</span>
                    <textarea rows={2} maxLength={2000} value={form.notas} disabled={!podeEditar} onChange={(ev) => mudar({ notas: ev.target.value })} className={caixa} />
                </label>
            </section>

            {/* A ASSINATURA */}
            {e.assinatura ? (
                <div className={cls('animate-fade-in flex flex-wrap items-center gap-3 border p-3', RAIO_GRANDE, valida ? 'border-emerald-200 bg-emerald-50' : 'border-amber-300 bg-amber-50')}>
                    <img src={e.assinatura} alt={t('Assinatura de :nome', { nome: e.nome ?? '' })} className="h-14 w-36 rounded-lg border border-white bg-white object-contain shadow-sm" />
                    <span className="min-w-0 flex-1 text-sm">
                        <span className={cls('flex items-center gap-1.5 font-semibold', valida ? 'text-emerald-800' : 'text-amber-900')}>
                            <i className={cls('fas', valida ? 'fa-circle-check' : 'fa-triangle-exclamation')} aria-hidden="true" />
                            {valida ? t('Levantada por :nome', { nome: e.nome ?? '' }) : t('Alterado depois de assinado')}
                        </span>
                        <span className="block text-xs text-slate-600">
                            {dataHora(e.assinado_em)}
                            {e.falta_ao_assinar !== null && e.falta_ao_assinar > 0 && ` · ${t('saiu com :valor Kz por pagar', { valor: kz(e.falta_ao_assinar) })}`}
                        </span>
                    </span>
                    {podeEditar && (
                        <span className="flex flex-wrap gap-2">
                            {!valida && <Botao cor="aviso" tom="solida" altura="pequeno" icone="fa-signature" onClick={() => porAAssinar(true)}>{t('Pedir nova assinatura')}</Botao>}
                            <Botao altura="pequeno" icone="fa-eraser" aTrabalhar={tirar.isPending} onClick={() => tirar.mutate()}>{t('Remover assinatura')}</Botao>
                        </span>
                    )}
                </div>
            ) : null}

            {podeEditar && (
                <div className="flex flex-wrap items-center gap-2">
                    {mexido && <span className="text-xs font-semibold text-amber-700"><i className="fas fa-circle mr-1 animate-pulse text-[8px]" aria-hidden="true" />{t('Alterações por gravar')}</span>}
                    <Botao icone="fa-floppy-disk" className="sm:ml-auto" aTrabalhar={gravar.isPending} disabled={!mexido} onClick={() => gravar.mutate()}>{t('Gravar')}</Botao>
                    {(!e.assinatura || !valida) && r.ordem.estado !== 'cancelled' && (
                        <Botao cor="bom" tom="solida" icone="fa-signature" onClick={() => porAAssinar(true)}>
                            {r.ordem.estado === 'delivered' ? t('Assinatura de quem levanta') : t('Assinar e entregar')}
                        </Botao>
                    )}
                </div>
            )}

            {aAssinar && (
                <AssinarEntrega id={id} form={form} jaEntregue={r.ordem.estado === 'delivered'} falta={r.contas.falta}
                    aoFechar={() => porAAssinar(false)} aoAssinar={(x) => { porAAssinar(false); responder(x); }} />
            )}
        </div>
    );
}

function AssinarEntrega({ id, form, jaEntregue, falta, aoFechar, aoAssinar }: {
    id: number; form: EntregaParaGravar; jaEntregue: boolean; falta: number;
    aoFechar: () => void; aoAssinar: (r: RespostaDaEntrega) => void;
}) {
    const quadro = useRef<QuadroDeAssinaturaRef>(null);
    const [riscado, porRiscado] = useState(false);
    const [nome, porNome] = useState(form.nome);
    const [entregar, porEntregar] = useState(!jaEntregue);

    const assinar = useMutation({
        mutationFn: () => entregaDaOrdem.assinar(id, { ...form, nome, assinatura: quadro.current?.imagem() ?? '', entregar }),
        onSuccess: aoAssinar,
    });
    const primeiroErro = assinar.error instanceof ErroDaApi ? Object.values(assinar.error.erros ?? {})[0]?.[0] : undefined;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Termo de entrega')} subtitulo={t('Quem levanta confirma como a viatura sai')} icone="fa-handshake" cor="bom" largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao icone="fa-eraser" onClick={() => quadro.current?.limpar()} disabled={!riscado || assinar.isPending}>{t('Limpar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-pen-nib" aTrabalhar={assinar.isPending} disabled={!riscado || nome.trim() === ''} onClick={() => assinar.mutate()}>
                        {entregar ? t('Assinar e entregar') : t('Confirmar assinatura')}
                    </Botao>
                </>
            }>
            <div className="space-y-3">
                {falta > 0 && (
                    <p className={cls('flex items-start gap-2 border border-red-200 bg-red-50 p-2.5 text-xs text-red-800', RAIO)}>
                        <i className="fas fa-hand-holding-dollar mt-0.5" aria-hidden="true" />
                        {t('Atenção: há :valor Kz por pagar. Fica registado no termo.', { valor: kz(falta) })}
                    </p>
                )}
                {primeiroErro ? <p className="text-sm text-red-700">{primeiroErro}</p> : <AvisoDeErro erro={assinar.error} />}
                <label className="block text-sm">
                    <span className="mb-1 block font-medium text-slate-700">{t('Nome de quem levanta')}</span>
                    <input value={nome} maxLength={150} onChange={(ev) => porNome(ev.target.value)} className={cls('w-full border border-slate-300 px-3 py-2 text-sm', RAIO, FOCO)} />
                </label>
                <QuadroDeAssinatura ref={quadro} aoRiscar={porRiscado} />
                {!jaEntregue && (
                    <label className={cls('flex items-center gap-2 border border-slate-200 bg-slate-50 px-3 py-2 text-sm', RAIO)}>
                        <input type="checkbox" checked={entregar} onChange={(ev) => porEntregar(ev.target.checked)} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                        {t('Dar a ordem por Entregue')}
                    </label>
                )}
                <p className="text-xs text-slate-500">{t('Ao assinar, o cliente confirma os km e o combustível à saída e o que foi conferido.')}</p>
            </div>
        </Modal>
    );
}
