import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { emissor, type LinhaDoEditor, type Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * EMITIR UMA PROPOSTA — proforma de venda, orçamento ou proforma de compra.
 *
 * ESTE ECRÃ NÃO FAZ CONTAS. Nenhuma. A cada alteração de linha pergunta ao
 * servidor (`/calcular`) e mostra o que ele responder; ao gravar, o servidor
 * volta a fazer tudo e ignora o que daqui for de totais.
 *
 * Parece um desperdício de um pedido — e é de propósito. Uma cópia da
 * matemática do imposto em TypeScript divergiria da do servidor ao primeiro
 * ajuste, e a divergência aparece como um cêntimo numa factura que a AGT
 * recusa com E70. Um pedido por alteração é barato; um documento recusado
 * dias depois não é.
 *
 * SÓ PROPOSTAS. Facturas, recibos e notas de crédito têm cada uma o seu
 * travão e entram por si — ver `TiposDeDocumento::editaveis()`.
 */

const LINHA_NOVA: LinhaDoEditor = {
    product_id: null,
    description: '',
    quantity: 1,
    price: 0,
    discount_percent: 0,
};

export default function EmitirProposta({ tipo }: { tipo: string }) {
    const [parteId, porParteId] = useState('');
    const [data, porData] = useState(() => new Date().toISOString().slice(0, 10));
    const [validoAte, porValidoAte] = useState('');
    const [notas, porNotas] = useState('');
    const [linhas, porLinhas] = useState<LinhaDoEditor[]>([{ ...LINHA_NOVA }]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [gravado, porGravado] = useState<{ numero: string; abrir: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['emissor', tipo, 'opcoes'],
        queryFn: () => emissor.opcoes(tipo),
        staleTime: 5 * 60_000,
    });

    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    /*
     * A CONTA PEDE-SE AO SERVIDOR, COM UMA PAUSA.
     *
     * Sem a pausa, escrever «1500» no preço são quatro pedidos — um por tecla.
     * Com 400 ms, é um. E o `cancelado` impede que a resposta de um pedido
     * antigo chegue depois da de um novo e escreva por cima dela: com rede
     * lenta isso acontece, e os totais ficavam a mostrar uma versão anterior
     * das linhas.
     */
    useEffect(() => {
        let cancelado = false;

        const comLinhas = linhas.filter((l) => Number(l.quantity) > 0);

        if (comLinhas.length === 0) {
            porTotais(null);
            return;
        }

        porAContar(true);

        const t = setTimeout(() => {
            emissor
                .calcular(tipo, { linhas: comLinhas })
                .then((r) => {
                    if (!cancelado) porTotais(r.totais);
                })
                .catch(() => {
                    if (!cancelado) porTotais(null);
                })
                .finally(() => {
                    if (!cancelado) porAContar(false);
                });
        }, 400);

        return () => {
            cancelado = true;
            clearTimeout(t);
        };
    }, [linhas, tipo]);

    const guardar = useMutation({
        mutationFn: () =>
            emissor.guardar(tipo, {
                parte_id: Number(parteId),
                data,
                valido_ate: validoAte || null,
                notas: notas || null,
                linhas: linhas.filter((l) => Number(l.quantity) > 0),
            }),
        onSuccess: (r) => {
            porGravado({ numero: r.numero, abrir: r.abrir });
            porErros({});
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending) {
        return <Carregando linhas={6} />;
    }

    if (opcoes.isError) {
        return <Falhou erro={opcoes.error} />;
    }

    const o = opcoes.data;

    /* Gravado: o ecrã dá o número e sai da frente. */
    if (gravado) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{gravado.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">Gravado como rascunho.</p>
                <div className="mt-6 flex justify-center gap-2">
                    <Botao cor="primaria" tom="solida" icone="fa-arrow-right" onClick={() => (window.location.href = gravado.abrir)}>
                        Abrir o documento
                    </Botao>
                    <Botao
                        icone="fa-plus"
                        onClick={() => {
                            porGravado(null);
                            porLinhas([{ ...LINHA_NOVA }]);
                            porParteId('');
                            porNotas('');
                        }}
                    >
                        Emitir outro
                    </Botao>
                </div>
            </div>
        );
    }

    const mudarLinha = (i: number, campo: keyof LinhaDoEditor, valor: string) =>
        porLinhas((ls) =>
            ls.map((l, j) => {
                if (j !== i) return l;

                if (campo === 'product_id') {
                    // Escolher o artigo traz o preço dele — que se pode mudar.
                    const artigo = o.artigos.find((a) => String(a.id) === valor);

                    return {
                        ...l,
                        product_id: valor ? Number(valor) : null,
                        price: artigo ? artigo.price : l.price,
                        description: artigo ? artigo.name : l.description,
                    };
                }

                return { ...l, [campo]: valor };
            }),
        );

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            <Cartao titulo="Dados do documento">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo
                        etiqueta={o.parte === 'fornecedor' ? 'Fornecedor' : 'Cliente'}
                        erro={erros.parte_id}
                        obrigatorio
                    >
                        <select value={parteId} onChange={(e) => porParteId(e.target.value)} className={entrada}>
                            <option value="">Escolher…</option>
                            {o.partes.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                    {p.nif ? ` · ${p.nif}` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Data" erro={erros.data} obrigatorio>
                        <input type="date" value={data} onChange={(e) => porData(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta="Válido até" erro={erros.valido_ate}>
                        <input
                            type="date"
                            value={validoAte}
                            onChange={(e) => porValidoAte(e.target.value)}
                            className={entrada}
                        />
                    </Campo>
                </div>
            </Cartao>

            <Cartao
                titulo="Linhas"
                accoes={
                    <Botao icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>
                        Nova linha
                    </Botao>
                }
                semPadding
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">Artigo</th>
                                <th className="px-4 py-3 font-semibold">Descrição</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">Qtd.</th>
                                <th className="w-32 px-4 py-3 text-right font-semibold">Preço</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">Desc. %</th>
                                <th className="w-12 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2">
                                        <select
                                            value={l.product_id ?? ''}
                                            onChange={(e) => mudarLinha(i, 'product_id', e.target.value)}
                                            aria-label={`Artigo da linha ${i + 1}`}
                                            className={entrada}
                                        >
                                            <option value="">Escolher…</option>
                                            {o.artigos.map((a) => (
                                                <option key={a.id} value={a.id}>
                                                    {a.name}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-4 py-2">
                                        <input
                                            value={l.description}
                                            onChange={(e) => mudarLinha(i, 'description', e.target.value)}
                                            aria-label={`Descrição da linha ${i + 1}`}
                                            className={entrada}
                                        />
                                    </td>
                                    <td className="px-4 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            value={l.quantity}
                                            onChange={(e) => mudarLinha(i, 'quantity', e.target.value)}
                                            aria-label={`Quantidade da linha ${i + 1}`}
                                            className={cls(entrada, 'text-right tabular-nums')}
                                        />
                                    </td>
                                    <td className="px-4 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={l.price}
                                            onChange={(e) => mudarLinha(i, 'price', e.target.value)}
                                            aria-label={`Preço da linha ${i + 1}`}
                                            className={cls(entrada, 'text-right tabular-nums')}
                                        />
                                    </td>
                                    <td className="px-4 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value={l.discount_percent}
                                            onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)}
                                            aria-label={`Desconto da linha ${i + 1}`}
                                            className={cls(entrada, 'text-right tabular-nums')}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {/* A última linha não se apaga: um documento
                                            sem linhas não é um documento, e um ecrã
                                            vazio sem forma de recomeçar é pior. */}
                                        {linhas.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => porLinhas((ls) => ls.filter((_, j) => j !== i))}
                                                aria-label={`Apagar linha ${i + 1}`}
                                                className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}
                                            >
                                                <i className="fas fa-trash" aria-hidden="true" />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {erros.linhas?.[0] && (
                    <p role="alert" className="border-t border-slate-100 px-4 py-3 text-sm font-medium text-red-600">
                        {erros.linhas[0]}
                    </p>
                )}
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo="Observações">
                    <textarea
                        rows={4}
                        value={notas}
                        onChange={(e) => porNotas(e.target.value)}
                        aria-label="Observações"
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Cartao>

                {/* OS TOTAIS SÃO OS DO SERVIDOR. Este bloco não calcula nada. */}
                <Cartao titulo="Totais">
                    {totais ? (
                        <dl className={cls('space-y-1.5 text-sm', aContar && 'opacity-50')}>
                            <Total rotulo="Valor bruto" valor={totais.bruto} />
                            {totais.desconto_por_linha > 0 && (
                                <Total rotulo="Desconto nas linhas" valor={-totais.desconto_por_linha} />
                            )}
                            <Total rotulo="Valor líquido" valor={totais.liquido} />
                            <Total rotulo="Incidência de IVA" valor={totais.base} />
                            <Total rotulo="Imposto" valor={totais.imposto} />
                            {totais.retencao > 0 && <Total rotulo="Retenção" valor={-totais.retencao} />}
                            <div className="mt-2 flex items-baseline justify-between border-t border-slate-200 pt-2">
                                <dt className="font-bold text-slate-900">Total</dt>
                                <dd className="text-xl font-bold tabular-nums text-slate-900">
                                    {kz(totais.total)} <span className="text-sm font-normal text-slate-400">Kz</span>
                                </dd>
                            </div>
                            <p className="pt-1 text-xs text-slate-400">
                                Contado no servidor — é o mesmo cálculo que vai para o documento.
                            </p>
                        </dl>
                    ) : (
                        <p className="py-6 text-center text-sm text-slate-400">
                            Escolha um artigo e uma quantidade para ver os totais.
                        </p>
                    )}
                </Cartao>
            </div>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = o.rota)}>Cancelar</Botao>
                <Botao
                    cor="primaria"
                    tom="solida"
                    altura="grande"
                    icone="fa-check"
                    aTrabalhar={guardar.isPending}
                    disabled={!o.permissoes.pode_criar}
                    onClick={() => guardar.mutate()}
                >
                    Gravar rascunho
                </Botao>
            </div>
        </div>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */


function Total({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="flex items-baseline justify-between">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="tabular-nums text-slate-800">{kz(valor)}</dd>
        </div>
    );
}


function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir o emissor</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? 'Verifique a ligação.'}</p>
        </div>
    );
}
