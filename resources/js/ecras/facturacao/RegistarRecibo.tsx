import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { recibos, type FacturaPorReceber } from '@/api/recibos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, RAIO, cls, data as fmtData, kz } from '@/ui/tokens';

/**
 * REGISTAR UM RECIBO.
 *
 * O ecrã diz sempre QUANTO FALTA e propõe esse valor. É a diferença entre
 * receber e adivinhar: quem está ao balcão com o cliente à frente não tem de
 * abrir a factura noutro separador para saber quanto pedir.
 *
 * O que o servidor garante e este ecrã só reflecte: não se recebe mais do que
 * falta, o `paid_amount` sobe pelos ganchos do modelo (nunca por uma conta
 * feita aqui), e uma factura-recibo do balcão nem aparece na lista — é paga no
 * acto e oferecer-lhe recibo é convidar a receber duas vezes.
 */
export default function RegistarRecibo() {
    const [tipo, porTipo] = useState<'sale' | 'purchase'>('sale');
    const [parteId, porParteId] = useState('');
    const [facturaId, porFacturaId] = useState('');
    const [valor, porValor] = useState('');
    const [forma, porForma] = useState('cash');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [referencia, porReferencia] = useState('');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; agt: string | null } | null>(null);

    const opcoes = useQuery({
        queryKey: ['recibos', 'opcoes'],
        queryFn: recibos.opcoes,
        staleTime: 5 * 60_000,
    });

    const facturas = useQuery({
        queryKey: ['recibos', 'facturas', tipo, parteId],
        queryFn: () => recibos.facturas(tipo, parteId || undefined),
        staleTime: 15_000,
    });

    const guardar = useMutation({
        mutationFn: () =>
            recibos.guardar({
                type: tipo,
                client_id: tipo === 'sale' ? Number(parteId) || null : null,
                supplier_id: tipo === 'purchase' ? Number(parteId) || null : null,
                invoice_id: facturaId ? Number(facturaId) : null,
                payment_date: dia,
                payment_method: forma,
                amount_paid: Number(valor),
                reference: referencia || null,
                notes: notas || null,
            }),
        onSuccess: (r) => {
            porFeito({ numero: r.numero, agt: r.agt });
            porErros({});
            void facturas.refetch();
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
                <p className="mt-1 text-sm text-slate-500">Recibo criado.</p>
                {feito.agt && <p className="mt-1 text-xs text-slate-400">{feito.agt}</p>}
                <div className="mt-6 flex justify-center gap-2">
                    <Botao
                        cor="primaria"
                        tom="solida"
                        icone="fa-list"
                        onClick={() => (window.location.href = '/invoicing/receipts')}
                    >
                        Ver os recibos
                    </Botao>
                    <Botao
                        icone="fa-plus"
                        onClick={() => {
                            porFeito(null);
                            porFacturaId('');
                            porValor('');
                            porReferencia('');
                        }}
                    >
                        Registar outro
                    </Botao>
                </div>
            </div>
        );
    }

    const o = opcoes.data;
    const partes = tipo === 'sale' ? o.clientes : o.fornecedores;
    const lista = facturas.data?.data ?? [];
    const escolhida = lista.find((f) => String(f.id) === facturaId);

    /* Escolher a factura propõe o que falta. Continua a poder mudar-se. */
    const escolherFactura = (id: string) => {
        porFacturaId(id);

        const f = lista.find((x) => String(x.id) === id);

        if (f) {
            porValor(String(f.falta));
        }
    };

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            <Cartao titulo="De quem se recebe">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta="Tipo" obrigatorio>
                        <select
                            value={tipo}
                            onChange={(e) => {
                                porTipo(e.target.value as 'sale' | 'purchase');
                                porParteId('');
                                porFacturaId('');
                                porValor('');
                            }}
                            className={entrada}
                        >
                            <option value="sale">Recebimento de cliente</option>
                            <option value="purchase">Pagamento a fornecedor</option>
                        </select>
                    </Campo>

                    <Campo
                        etiqueta={tipo === 'sale' ? 'Cliente' : 'Fornecedor'}
                        erro={erros.client_id ?? erros.supplier_id}
                        obrigatorio
                    >
                        <select
                            value={parteId}
                            onChange={(e) => {
                                porParteId(e.target.value);
                                porFacturaId('');
                            }}
                            className={entrada}
                        >
                            <option value="">Escolher…</option>
                            {partes.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                    {p.nif ? ` · ${p.nif}` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Data" erro={erros.payment_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>
                </div>
            </Cartao>

            <Cartao titulo="Factura">
                {facturas.isFetching ? (
                    <p className="py-4 text-sm text-slate-400">A procurar facturas por receber…</p>
                ) : lista.length === 0 ? (
                    <p className="py-4 text-sm text-slate-500">
                        Não há facturas por receber
                        {parteId ? ' deste ' + (tipo === 'sale' ? 'cliente' : 'fornecedor') : ''}. Pode registar
                        um recibo sem factura — fica como adiantamento.
                    </p>
                ) : (
                    <Campo etiqueta="Factura a receber" erro={erros.invoice_id}>
                        <select
                            value={facturaId}
                            onChange={(e) => escolherFactura(e.target.value)}
                            className={entrada}
                        >
                            <option value="">Sem factura (adiantamento)</option>
                            {lista.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.numero} · {fmtData(f.data)} · faltam {kz(f.falta)} Kz
                                </option>
                            ))}
                        </select>
                    </Campo>
                )}

                {/* QUANTO FALTA, À VISTA. É a pergunta que quem recebe faz. */}
                {escolhida && <Saldo f={escolhida} />}
            </Cartao>

            <Cartao titulo="Pagamento">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta="Valor recebido" erro={erros.amount_paid} obrigatorio>
                        <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={valor}
                            onChange={(e) => porValor(e.target.value)}
                            className={cls(entrada, 'text-right text-lg font-bold tabular-nums')}
                        />
                    </Campo>

                    <Campo etiqueta="Forma" erro={erros.payment_method} obrigatorio>
                        <select value={forma} onChange={(e) => porForma(e.target.value)} className={entrada}>
                            {o.formas.map((f) => (
                                <option key={f.valor} value={f.valor}>
                                    {f.rotulo}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Referência" erro={erros.reference}>
                        <input
                            value={referencia}
                            onChange={(e) => porReferencia(e.target.value)}
                            placeholder="Nº da transferência, cheque…"
                            className={entrada}
                        />
                    </Campo>

                    <div className="sm:col-span-3">
                        <Campo etiqueta="Observações" erro={erros.notes}>
                            <textarea
                                rows={2}
                                value={notas}
                                onChange={(e) => porNotas(e.target.value)}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>
                    </div>
                </div>
            </Cartao>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = '/invoicing/receipts')}>Cancelar</Botao>
                <Botao
                    cor="bom"
                    tom="solida"
                    altura="grande"
                    icone="fa-receipt"
                    aTrabalhar={guardar.isPending}
                    disabled={!o.permissoes.pode_criar}
                    onClick={() => guardar.mutate()}
                >
                    Registar recibo
                </Botao>
            </div>
        </div>
    );
}

function Saldo({ f }: { f: FacturaPorReceber }) {
    return (
        <dl className="mt-4 grid grid-cols-3 gap-3 border-t border-slate-100 pt-4 text-sm">
            <div>
                <dt className="text-xs uppercase tracking-wider text-slate-400">Total</dt>
                <dd className="tabular-nums text-slate-800">{kz(f.total)}</dd>
            </div>
            <div>
                <dt className="text-xs uppercase tracking-wider text-slate-400">Já recebido</dt>
                <dd className="tabular-nums text-slate-800">{kz(f.pago)}</dd>
            </div>
            <div>
                <dt className="text-xs uppercase tracking-wider text-slate-400">Falta</dt>
                <dd className="text-lg font-bold tabular-nums text-amber-600">{kz(f.falta)}</dd>
            </div>
        </dl>
    );
}


