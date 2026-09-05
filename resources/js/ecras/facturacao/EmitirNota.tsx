import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { notas, type LinhaDaFactura, type TipoDeNota } from '@/api/notas';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, FOCO, RAIO, cls, data as fmtData, kz } from '@/ui/tokens';

/**
 * EMITIR UMA NOTA DE CRÉDITO OU DE DÉBITO.
 *
 * O QUE ESTE ECRÃ FAZ: escolhe a factura, mostra as linhas dela, deixa
 * acertar QUANTIDADES, e grava. O que ele NÃO faz é tudo o que a AGT compara:
 * a taxa, o código SAFT, a região, o motivo de isenção e os totais vêm da
 * linha original e são calculados no servidor pelo `EmissorDeNotas` — o mesmo
 * que o ecrã Livewire chama. Daqui só sai a quantidade.
 *
 * O TRAVÃO DO E43 vive lá e responde 422 com a razão. Este ecrã mostra-a nas
 * linhas — a nota nunca chega a nascer, que é o objectivo: a AGT recusaria
 * dias depois, quando já não há como desfazer.
 */
type Linha = LinhaDaFactura & { quantidade: number | string };

export default function EmitirNota({ tipo }: { tipo: TipoDeNota }) {
    const eCredito = tipo === 'credito';

    const [clienteId, porClienteId] = useState('');
    const [facturaId, porFacturaId] = useState('');
    const [linhas, porLinhas] = useState<Linha[]>([]);
    const [motivo, porMotivo] = useState('');
    const [tipoDeCredito, porTipoDeCredito] = useState<'total' | 'partial'>('total');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vencimento, porVencimento] = useState('');
    const [notasTexto, porNotasTexto] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; agt: string | null; abrir: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['notas', tipo, 'opcoes'],
        queryFn: () => notas.opcoes(tipo),
        staleTime: 5 * 60_000,
    });

    const facturas = useQuery({
        queryKey: ['notas', tipo, 'facturas', clienteId],
        queryFn: () => notas.facturas(tipo, clienteId || undefined),
        enabled: clienteId !== '',
        staleTime: 15_000,
    });

    const linhasDaFactura = useQuery({
        queryKey: ['notas', tipo, 'linhas', facturaId],
        queryFn: () => notas.linhas(tipo, Number(facturaId)),
        enabled: facturaId !== '',
    });

    /* As linhas da factura chegam do servidor e entram todas, com a
       quantidade que a factura teve. Numa NC parcial acerta-se daqui. */
    useEffect(() => {
        if (linhasDaFactura.data) {
            porLinhas(linhasDaFactura.data.data.map((l) => ({ ...l, quantidade: l.quantity })));
        }
    }, [linhasDaFactura.data]);

    useEffect(() => {
        if (opcoes.data && !motivo) {
            porMotivo(opcoes.data.motivos[0]?.valor ?? '');
        }
    }, [opcoes.data, motivo]);

    const guardar = useMutation({
        mutationFn: () =>
            notas.guardar(tipo, {
                client_id: Number(clienteId),
                invoice_id: facturaId ? Number(facturaId) : null,
                issue_date: dia,
                due_date: eCredito ? null : vencimento || null,
                reason: motivo,
                type: eCredito ? tipoDeCredito : null,
                notes: notasTexto || null,
                linhas: linhas
                    .filter((l) => Number(l.quantidade) > 0)
                    .map((l) => ({ origem_line_id: l.origem_line_id, quantity: Number(l.quantidade) })),
            }),
        onSuccess: (r) => {
            porFeito({ numero: r.numero, agt: r.agt, abrir: r.abrir });
            porErros({});
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending) {
        return <Carregando linhas={5} />;
    }

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir</h2>
                <p className="text-sm text-red-800">
                    {opcoes.error instanceof ErroDaApi ? opcoes.error.message : 'Verifique a ligação.'}
                </p>
            </div>
        );
    }

    if (feito) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{feito.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">{eCredito ? 'Nota de crédito' : 'Nota de débito'} emitida.</p>
                {feito.agt && <p className="mt-1 text-xs text-slate-400">{feito.agt}</p>}
                <div className="mt-6 flex justify-center">
                    <Botao cor="primaria" tom="solida" icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>
                        Ver as notas
                    </Botao>
                </div>
            </div>
        );
    }

    const o = opcoes.data;
    const listaDeFacturas = facturas.data?.data ?? [];
    const escolhida = listaDeFacturas.find((f) => String(f.id) === facturaId);

    const mudarQuantidade = (i: number, valor: string) =>
        porLinhas((ls) => ls.map((l, j) => (j === i ? { ...l, quantidade: valor } : l)));

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            <Cartao titulo="Documento a corrigir">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta="Cliente" erro={erros.client_id} obrigatorio>
                        <select
                            value={clienteId}
                            onChange={(e) => {
                                porClienteId(e.target.value);
                                porFacturaId('');
                                porLinhas([]);
                            }}
                            className={entrada}
                        >
                            <option value="">Escolher…</option>
                            {o.clientes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                    {c.nif ? ` · ${c.nif}` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Factura" erro={erros.invoice_id} obrigatorio>
                        <select
                            value={facturaId}
                            onChange={(e) => porFacturaId(e.target.value)}
                            disabled={!clienteId}
                            className={entrada}
                        >
                            <option value="">{clienteId ? 'Escolher…' : 'Escolha o cliente primeiro'}</option>
                            {listaDeFacturas.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.numero} · {fmtData(f.data)} · {kz(f.total)} Kz
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Data" erro={erros.issue_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta="Motivo" erro={erros.reason} obrigatorio>
                        <select value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada}>
                            {o.motivos.map((m) => (
                                <option key={m.valor} value={m.valor}>
                                    {m.rotulo}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    {eCredito ? (
                        <Campo etiqueta="Alcance" erro={erros.type} obrigatorio>
                            <select
                                value={tipoDeCredito}
                                onChange={(e) => porTipoDeCredito(e.target.value as 'total' | 'partial')}
                                className={entrada}
                            >
                                <option value="total">Anulação total</option>
                                <option value="partial">Rectificação parcial</option>
                            </select>
                        </Campo>
                    ) : (
                        <Campo etiqueta="Vencimento" erro={erros.due_date}>
                            <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                        </Campo>
                    )}
                </div>

                {/* QUANTO AINDA SE PODE CREDITAR, à vista. É o número que o E43 compara. */}
                {eCredito && escolhida && (
                    <p className="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        Esta factura tem <strong className="tabular-nums text-amber-700">{kz(escolhida.por_creditar)} Kz</strong> por anular
                        {escolhida.por_creditar < escolhida.total && (
                            <span className="text-slate-400"> (de {kz(escolhida.total)} — o resto já foi creditado)</span>
                        )}
                        .
                    </p>
                )}
            </Cartao>

            <Cartao titulo="Linhas" semPadding>
                {linhasDaFactura.isFetching ? (
                    <p className="px-5 py-6 text-sm text-slate-400">A ler as linhas da factura…</p>
                ) : linhas.length === 0 ? (
                    <p className="px-5 py-6 text-sm text-slate-500">Escolha a factura para ver as linhas.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">Artigo</th>
                                    <th className="px-4 py-3 text-right font-semibold">Na factura</th>
                                    <th className="w-32 px-4 py-3 text-right font-semibold">{eCredito ? 'A anular' : 'A debitar'}</th>
                                    <th className="px-4 py-3 text-right font-semibold">Preço</th>
                                    <th className="px-4 py-3 text-right font-semibold">Imposto</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((l, i) => (
                                    <tr key={l.origem_line_id}>
                                        <td className="px-4 py-2 text-slate-800">{l.nome}</td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.quantity}</td>
                                        <td className="px-4 py-2">
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.001"
                                                value={l.quantidade}
                                                onChange={(e) => mudarQuantidade(i, e.target.value)}
                                                aria-label={`Quantidade da linha ${i + 1}`}
                                                className={cls(entrada, 'text-right tabular-nums', FOCO)}
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-600">{kz(l.price)}</td>
                                        {/* A taxa e a região são as da linha ORIGINAL — vêm do
                                            servidor, e é assim que a AGT as compara. */}
                                        <td className="px-4 py-2 text-right text-xs tabular-nums text-slate-500">
                                            {l.tax_rate}% · {l.tax_country_region}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {erros.linhas?.[0] && (
                    <p role="alert" className="border-t border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {erros.linhas[0]}
                    </p>
                )}
            </Cartao>

            <Cartao titulo="Observações">
                <textarea
                    rows={3}
                    value={notasTexto}
                    onChange={(e) => porNotasTexto(e.target.value)}
                    aria-label="Observações"
                    className={cls(entrada, 'h-auto py-2')}
                />
            </Cartao>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = eCredito ? '/invoicing/credit-notes' : '/invoicing/debit-notes')}>
                    Cancelar
                </Botao>
                <Botao
                    cor={eCredito ? 'perigo' : 'primaria'}
                    tom="solida"
                    altura="grande"
                    icone={eCredito ? 'fa-file-circle-minus' : 'fa-file-circle-plus'}
                    aTrabalhar={guardar.isPending}
                    disabled={!o.permissoes.pode_criar || linhas.length === 0}
                    onClick={() => guardar.mutate()}
                >
                    {eCredito ? 'Emitir nota de crédito' : 'Emitir nota de débito'}
                </Botao>
            </div>
        </div>
    );
}
