import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { emissor, type LinhaDoEditor, type Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Campo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * EMITIR UMA PROPOSTA — proforma de venda, orçamento ou proforma de compra —
 * ou abrir uma que já existe.
 *
 * ESTE ECRÃ NÃO FAZ CONTAS. Nenhuma. A cada alteração de linha pergunta ao
 * servidor (`/calcular`) e mostra o que ele responder; ao gravar, o servidor
 * volta a fazer tudo e ignora o que daqui for de totais. Uma cópia da
 * matemática do imposto em TypeScript divergiria da do servidor ao primeiro
 * ajuste, e a divergência aparece como um cêntimo numa factura que a AGT
 * recusa com E70.
 *
 * Com `id`, abre a proposta: um rascunho edita-se; uma que já seguiu abre-se
 * só para ler. O servidor é que diz qual é qual.
 *
 * Com `duplicarDe` (`?duplicar=123` na morada), abre com o CONTEÚDO de outra
 * proposta e mais nada: sem `id` e sem número, gravar cria um documento novo.
 * O que viaja e o que fica está no `DuplicaDocumento`, do lado do servidor.
 */

const LINHA_NOVA: LinhaDoEditor = {
    product_id: null,
    description: '',
    quantity: 1,
    price: 0,
    discount_percent: 0,
};

export default function EmitirProposta({ tipo, id, duplicarDe }: { tipo: string; id?: number; duplicarDe?: number }) {
    const [parteId, porParteId] = useState('');
    const [data, porData] = useState(() => new Date().toISOString().slice(0, 10));
    const [validoAte, porValidoAte] = useState('');
    const [regiao, porRegiao] = useState('');
    const [notas, porNotas] = useState('');
    const [linhas, porLinhas] = useState<LinhaDoEditor[]>([{ ...LINHA_NOVA }]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [gravado, porGravado] = useState<{ numero: string; abrir: string; mensagem: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['emissor', tipo, 'opcoes'],
        queryFn: () => emissor.opcoes(tipo),
        staleTime: 5 * 60_000,
    });
    const aberta = useQuery({ queryKey: ['emissor', tipo, 'abrir', id], queryFn: () => emissor.abrir(tipo, id ?? 0), enabled: id !== undefined });
    const copia = useQuery({ queryKey: ['emissor', tipo, 'duplicar', duplicarDe], queryFn: () => emissor.duplicar(tipo, duplicarDe ?? 0), enabled: id === undefined && duplicarDe !== undefined });

    /*
     * O conteúdo que entra no formulário — venha de uma proposta aberta ou de
     * uma duplicada. É o MESMO carregamento: o duplicado herda o que a edição
     * herdaria, e o que ele não herda é o que o servidor não mandou.
     */
    const carregado = aberta.data ?? copia.data ?? null;

    useEffect(() => {
        const d = carregado?.documento;
        if (!d) return;
        porParteId(d.parte_id ? String(d.parte_id) : '');
        porData(d.data ?? new Date().toISOString().slice(0, 10));
        porValidoAte(d.valido_ate ?? '');
        porRegiao(d.tax_country_region ?? '');
        porNotas(d.notas ?? '');
        porLinhas(carregado.linhas.length > 0 ? carregado.linhas : [{ ...LINHA_NOVA }]);
    }, [carregado]);

    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    /* Uma linha sem artigo, sem preço e sem descrição ainda não é uma linha. */
    const comConteudo = (l: { product_id: number | null; quantity: number | string; price: number | string; description: string }) =>
        Number(l.quantity) > 0 && (l.product_id !== null || Number(l.price) > 0 || l.description.trim() !== '');

    /*
     * A CONTA PEDE-SE AO SERVIDOR, COM UMA PAUSA.
     *
     * Sem a pausa, escrever «1500» no preço são quatro pedidos — um por tecla.
     * Com 400 ms, é um. E o `cancelado` impede que a resposta de um pedido
     * antigo chegue depois da de um novo e escreva por cima dela.
     */
    useEffect(() => {
        let cancelado = false;

        const comLinhas = linhas.filter(comConteudo);

        if (comLinhas.length === 0) {
            porTotais(null);
            return;
        }

        porAContar(true);

        const pausa = setTimeout(() => {
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
            clearTimeout(pausa);
        };
    }, [linhas, tipo]);

    const guardar = useMutation({
        mutationFn: () => {
            const corpo = {
                parte_id: Number(parteId),
                data,
                valido_ate: validoAte || null,
                tax_country_region: regiao || null,
                notas: notas || null,
                linhas: linhas.filter(comConteudo),
            };
            return id !== undefined ? emissor.actualizar(tipo, id, corpo) : emissor.guardar(tipo, corpo);
        },
        onSuccess: (r) => {
            porGravado({ numero: r.numero, abrir: r.abrir, mensagem: r.message });
            porErros({});
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id !== undefined && aberta.isPending) || (copia.isPending && copia.fetchStatus !== 'idle')) {
        return <Carregando linhas={6} />;
    }

    if (opcoes.isError || aberta.isError || copia.isError) {
        return <Falhou erro={opcoes.error ?? aberta.error ?? copia.error} />;
    }

    const o = opcoes.data;
    const doc = aberta.data?.documento ?? null;
    const soLeitura = doc !== null && !doc.pode_editar;

    /* Gravado: o ecrã dá o número e sai da frente. */
    if (gravado) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{gravado.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">{gravado.mensagem}</p>
                <div className="mt-6 flex justify-center gap-2">
                    {/* ABRIR O QUE SE ACABOU DE GRAVAR.
                        O servidor sempre devolveu a morada do documento, e o
                        ecrã nunca a usava: quem grava um rascunho para o
                        continuar tinha de ir procurá-lo à lista. */}
                    <Botao cor="primaria" tom="solida" icone="fa-up-right-from-square" onClick={() => (window.location.href = gravado.abrir)}>
                        {t('Abrir o documento')}
                    </Botao>
                    <Botao icone="fa-list" onClick={() => (window.location.href = o.rota)}>
                        {t('Ver a lista')}
                    </Botao>
                    {id === undefined && (
                        <Botao
                            icone="fa-plus"
                            onClick={() => {
                                porGravado(null);
                                porLinhas([{ ...LINHA_NOVA }]);
                                porParteId('');
                                porNotas('');
                            }}
                        >
                            {t('Emitir outro')}
                        </Botao>
                    )}
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
        <div className="space-y-4" data-emissor={tipo}>
            <AvisoDeErro erro={guardar.error} />

            {doc && (
                <div className={cls('flex flex-wrap items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, soLeitura ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-200 bg-amber-50 text-amber-900')} data-documento-aberto>
                    <span className="flex items-center gap-2">
                        <strong>{doc.numero ?? t('Rascunho')}</strong>
                        <Etiqueta cor={soLeitura ? 'neutra' : 'aviso'}>{doc.estado}</Etiqueta>
                        {soLeitura ? t('Este documento já seguiu: só leitura.') : t('Rascunho: pode alterar.')}
                    </span>
                    <a href={doc.pdf} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-file-pdf" aria-hidden="true" />{t('PDF')}</a>
                </div>
            )}

            {/* Duplicado: diz de onde veio, e diz que não é o mesmo documento. */}
            {copia.data && (
                <div className={cls('flex flex-wrap items-center gap-2 border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900', RAIO)} data-duplicado-de={copia.data.origem.numero ?? ''}>
                    <i className="fas fa-copy" aria-hidden="true" />
                    <span>
                        {t('Duplicado de')} <strong>{copia.data.origem.numero ?? t('documento sem número')}</strong>{' '}
                        {t('— nasce como documento novo, sem número. Confira a data e a validade.')}
                    </span>
                </div>
            )}

            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo={t('Dados do documento')}>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo
                        etiqueta={o.parte === 'fornecedor' ? t('Fornecedor') : t('Cliente')}
                        erro={erros.parte_id}
                        obrigatorio
                    >
                        <select value={parteId} onChange={(e) => porParteId(e.target.value)} className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {o.partes.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                    {p.nif ? ` · ${p.nif}` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Data')} erro={erros.data} obrigatorio>
                        <input type="date" value={data} onChange={(e) => porData(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Válido até')} erro={erros.valido_ate}>
                        <input
                            type="date"
                            value={validoAte}
                            onChange={(e) => porValidoAte(e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    {/* Cabinda tem regime próprio, e é o LOCAL DA OPERAÇÃO que
                        decide — não a sede de ninguém. Vazio deriva da
                        província da outra parte. */}
                    <Campo etiqueta={t('Região fiscal')} erro={erros.tax_country_region}>
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => (
                                <option key={r.valor} value={r.valor}>
                                    {r.rotulo}
                                </option>
                            ))}
                        </select>
                    </Campo>
                </div>
            </Cartao>

            <Cartao
                titulo={t('Linhas')}
                accoes={
                    !soLeitura && (
                        <Botao icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>
                            {t('Nova linha')}
                        </Botao>
                    )
                }
                semPadding
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">{t('Artigo')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Descrição')}</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">{t('Qtd.')}</th>
                                <th className="w-32 px-4 py-3 text-right font-semibold">{t('Preço')}</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">{t('Desc. %')}</th>
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
                                            aria-label={t('Artigo da linha :n', { n: i + 1 })}
                                            className={entrada}
                                        >
                                            <option value="">{t('Escolher…')}</option>
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
                                            aria-label={t('Descrição da linha :n', { n: i + 1 })}
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
                                            aria-label={t('Quantidade da linha :n', { n: i + 1 })}
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
                                            aria-label={t('Preço da linha :n', { n: i + 1 })}
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
                                            aria-label={t('Desconto da linha :n', { n: i + 1 })}
                                            className={cls(entrada, 'text-right tabular-nums')}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {/* A última linha não se apaga: um documento
                                            sem linhas não é um documento. */}
                                        {linhas.length > 1 && !soLeitura && (
                                            <button
                                                type="button"
                                                onClick={() => porLinhas((ls) => ls.filter((_, j) => j !== i))}
                                                aria-label={t('Apagar linha :n', { n: i + 1 })}
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
                <Cartao titulo={t('Observações')}>
                    <textarea
                        rows={4}
                        value={notas}
                        onChange={(e) => porNotas(e.target.value)}
                        aria-label={t('Observações')}
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Cartao>

                {/* OS TOTAIS SÃO OS DO SERVIDOR. Este bloco não calcula nada. */}
                <Cartao titulo={t('Totais')}>
                    {totais ? (
                        <dl className={cls('space-y-1.5 text-sm', aContar && 'opacity-50')}>
                            <Total rotulo={t('Valor bruto')} valor={totais.bruto} />
                            {totais.desconto_por_linha > 0 && (
                                <Total rotulo={t('Desconto nas linhas')} valor={-totais.desconto_por_linha} />
                            )}
                            <Total rotulo={t('Valor líquido')} valor={totais.liquido} />
                            <Total rotulo={t('Incidência de IVA')} valor={totais.base} />
                            <Total rotulo={t('Imposto')} valor={totais.imposto} />
                            {totais.retencao > 0 && <Total rotulo={t('Retenção')} valor={-totais.retencao} />}
                            <div className="mt-2 flex items-baseline justify-between border-t border-slate-200 pt-2">
                                <dt className="font-bold text-slate-900">{t('Total')}</dt>
                                <dd className="text-xl font-bold tabular-nums text-slate-900">
                                    {kz(totais.total)} <span className="text-sm font-normal text-slate-400">Kz</span>
                                </dd>
                            </div>
                            <p className="pt-1 text-xs text-slate-400">
                                {t('Contado no servidor — é o mesmo cálculo que vai para o documento.')}
                            </p>
                        </dl>
                    ) : (
                        <p className="py-6 text-center text-sm text-slate-400">
                            {t('Escolha um artigo e uma quantidade para ver os totais.')}
                        </p>
                    )}
                </Cartao>
            </div>
            </fieldset>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = o.rota)}>{soLeitura ? t('Voltar à lista') : t('Cancelar')}</Botao>
                {!soLeitura && (
                    <Botao
                        cor="primaria"
                        tom="solida"
                        altura="grande"
                        icone="fa-check"
                        aTrabalhar={guardar.isPending}
                        disabled={!o.permissoes.pode_criar && id === undefined}
                        onClick={() => guardar.mutate()}
                    >
                        {id !== undefined ? t('Guardar alterações') : t('Gravar rascunho')}
                    </Botao>
                )}
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
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o emissor')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
