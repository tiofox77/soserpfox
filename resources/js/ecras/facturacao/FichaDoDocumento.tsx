import { useQuery } from '@tanstack/react-query';
import { documentos } from '@/api/documentos';
import { etiquetaIntl, t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PdfDoEcra } from '@/ui/PdfDoEcra';
import { RAIO, cls, data, kz } from '@/ui/tokens';
import { Dado } from './ExtratoDaParte';

/** O ícone acompanha a cor do selo — nunca só a cor. */
export const SINAL_AGT: Record<string, string> = {
    bom: 'fa-circle-check',
    primaria: 'fa-cloud-arrow-up',
    perigo: 'fa-triangle-exclamation',
    aviso: 'fa-clock',
    neutra: 'fa-minus-circle',
};

/**
 * A FICHA DE UM DOCUMENTO — o modal de VER que a lista em Blade tinha.
 *
 * Quem só quer conferir um documento não tem de abrir o editor (onde se
 * estraga um por engano) nem a pré-visualização (uma página inteira, feita
 * para imprimir). Isto é o que se olha de relance: quem, quando, o que leva e
 * quanto dá — e dali salta-se para a pré-visualização, que é de onde se
 * imprime.
 */
export function FichaDoDocumento({
    tipo,
    documento,
    rota,
    rotuloDaParte,
    aoFechar,
}: {
    tipo: string;
    documento: { id: number; numero: string; numero_agt: string | null } | null;
    rota: string;
    /** «Cliente», «Fornecedor» ou «Cliente/Fornecedor» — o mesmo da tabela. */
    rotuloDaParte: string;
    aoFechar: () => void;
}) {
    const q = useQuery({
        queryKey: ['documentos', tipo, 'ficha', documento?.id],
        queryFn: () => documentos.ficha(tipo, documento!.id),
        enabled: documento !== null,
    });

    if (!documento) {
        return null;
    }

    const f = q.data;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={documento.numero}
            subtitulo={documento.numero_agt ?? t('Série ainda não registada na AGT')}
            icone="fa-file-invoice"
            cor="primaria"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>

                    {/* OS DOIS PDF, como a linha da lista os tem — e como o
                        modal em Blade os tinha. Sem eles, quem abria a ficha
                        para conferir um orçamento e o queria mandar ao cliente
                        tinha de fechar, voltar à linha e procurar o botão. O
                        do servidor (DomPDF) tem texto para copiar; o do ecrã é
                        a própria pré-visualização, fotografada. O do servidor
                        abre à parte: no mesmo separador, o PDF tomava o lugar
                        da lista. */}
                    <div className="flex items-center justify-center gap-1">
                        <PdfDoEcra
                            url={`${rota}/${documento.id}/preview`}
                            titulo={t('Descarregar :numero em PDF', { numero: documento.numero })}
                        />
                    </div>

                    <a
                        href={`${rota}/${documento.id}/preview`}
                        target="_blank"
                        rel="noreferrer"
                        className={cls(
                            'inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2',
                            'text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl',
                        )}
                    >
                        <i className="fas fa-print" aria-hidden="true" />
                        {t('Pré-visualizar / Imprimir')}
                    </a>
                </>
            }
        >
            {q.isPending || !f ? (
                <Carregando />
            ) : (
                <div className="space-y-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-user mr-2 text-slate-400" aria-hidden="true" />
                                {t('Informações do :parte', { parte: rotuloDaParte })}
                            </h4>
                            <p className="font-bold text-slate-900">{f.parte.nome}</p>
                            {f.parte.nif && <p className="text-sm text-slate-600">NIF: {f.parte.nif}</p>}
                            {f.parte.email && <p className="text-sm text-slate-600">{f.parte.email}</p>}
                            {f.parte.telefone && <p className="text-sm text-slate-600">{f.parte.telefone}</p>}
                        </section>

                        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-circle-info mr-2 text-slate-400" aria-hidden="true" />
                                {t('Documento')}
                            </h4>
                            <div className="space-y-1.5">
                                <Dado rotulo={t('Data')} valor={data(f.data)} />
                                {f.prazo_rotulo && <Dado rotulo={f.prazo_rotulo} valor={data(f.prazo)} />}
                                {f.regiao_fiscal && <Dado rotulo={t('Região fiscal')} valor={f.regiao_fiscal} />}
                                <div className="flex items-start gap-2 pt-1 text-sm">
                                    <span className="w-28 flex-none text-slate-500">{t('Estado')}</span>
                                    <span className="flex flex-wrap gap-1.5">
                                        <Etiqueta cor={f.estado_cor}>{f.estado_rotulo}</Etiqueta>
                                        <Etiqueta cor={f.agt.cor} icone={SINAL_AGT[f.agt.cor]}>
                                            {f.agt.rotulo}
                                        </Etiqueta>
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    {/* O DOCUMENTO RECTIFICADO, nas notas. Uma nota corrige uma
                        factura, e a factura tem de estar escrita na ficha: é o
                        que liga as duas na conferência. */}
                    {f.rectifica && (
                        <section className={cls('border border-amber-200 bg-amber-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-amber-900">
                                <i className="fas fa-file-pen mr-2" aria-hidden="true" />
                                {t('Documento Rectificado')}
                            </h4>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-amber-700">{f.rectifica.rotulo}</p>
                                    {f.rectifica.id ? (
                                        <a
                                            href={`/invoicing/sales/invoices/${f.rectifica.id}/preview`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-semibold text-indigo-700 hover:underline"
                                        >
                                            {f.rectifica.numero}
                                        </a>
                                    ) : (
                                        <p className="text-sm text-slate-500">{t('Sem factura associada')}</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs text-amber-700">{t('Motivo')}</p>
                                    <p className="text-sm font-semibold text-slate-900">{f.rectifica.motivo ?? '—'}</p>
                                </div>
                                <div>
                                    {/* A expressão que a lei manda escrever na nota. */}
                                    <p className="text-xs text-amber-700">{t('Expressão (Art. 12.º)')}</p>
                                    <p className="text-sm font-semibold text-slate-900">{f.rectifica.expressao}</p>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* AS LINHAS. Um recibo ou um adiantamento não as tem — são
                        dinheiro, não mercadoria — e a tabela não aparece em vez
                        de aparecer vazia. */}
                    {f.tem_linhas && (
                        <section>
                            <h4 className="mb-2 text-sm font-bold text-slate-700">
                                <i className="fas fa-box mr-1.5 text-slate-400" aria-hidden="true" />
                                {t('Produtos (:quantos)', { quantos: f.linhas.length })}
                            </h4>
                            <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                                <table className="w-full min-w-[560px] text-sm">
                                    <thead className="bg-slate-50 text-xs text-slate-600">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Produto')}</th>
                                            <th className="px-3 py-2 text-center font-semibold">{t('Qtd')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Preço')}</th>
                                            <th className="px-3 py-2 text-center font-semibold">{t('Desc%')}</th>
                                            <th className="px-3 py-2 text-center font-semibold">IVA</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {f.linhas.map((l, i) => (
                                            <tr key={i} className="transition-colors hover:bg-indigo-50/50">
                                                <td className="px-3 py-2">
                                                    <p className="font-semibold text-slate-900">{l.descricao}</p>
                                                    {l.unidade && (
                                                        <p className="text-xs text-slate-400">{l.unidade}</p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-center tabular-nums">
                                                    {l.quantidade.toLocaleString(etiquetaIntl(), {
                                                        maximumFractionDigits: 3,
                                                    })}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">{kz(l.preco)}</td>
                                                <td className="px-3 py-2 text-center tabular-nums">{l.desconto}%</td>
                                                <td className="px-3 py-2 text-center tabular-nums">{l.taxa}%</td>
                                                <td className="px-3 py-2 text-right font-bold tabular-nums">
                                                    {kz(l.total)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                        <Soma rotulo={t('Subtotal')} valor={f.totais.subtotal} />
                        {f.totais.desconto_comercial > 0 && (
                            <Soma rotulo={t('Desconto Comercial')} valor={f.totais.desconto_comercial} />
                        )}
                        {f.totais.desconto_financeiro > 0 && (
                            <Soma rotulo={t('Desconto Financeiro')} valor={f.totais.desconto_financeiro} />
                        )}
                        <Soma rotulo="IVA" valor={f.totais.imposto} />
                        {/* IEC E IMPOSTO DE SELO. Sem eles o total não
                            reconcilia: quem soma o subtotal com o IVA fica a
                            dever a diferença sem perceber de onde vem. */}
                        {f.impostos_extra.map((x) => (
                            <Soma key={x.tipo} rotulo={x.tipo} valor={x.valor} />
                        ))}
                        {/* A RETENÇÃO NA FONTE, só quando existe — como no modal
                            de sempre. Sem ela, um documento com retenção mostra um
                            total que não fecha com as parcelas de cima. */}
                        {f.totais.retencao > 0 && <Soma rotulo={t('Retenção')} valor={f.totais.retencao} />}
                        <div className="mt-2 flex items-baseline justify-between border-t border-slate-300 pt-2">
                            <span className="font-bold text-slate-800">{t('TOTAL')}</span>
                            <span className="text-xl font-bold tabular-nums text-slate-900">
                                {kz(f.totais.total)} Kz
                            </span>
                        </div>
                    </section>

                    {f.notas && (
                        <section className={cls('border border-slate-200 bg-white p-4', RAIO)}>
                            <h4 className="mb-1 text-sm font-bold text-slate-700">
                                <i className="fas fa-align-left mr-1.5 text-slate-400" aria-hidden="true" />
                                {t('Notas')}
                            </h4>
                            <p className="whitespace-pre-line text-sm text-slate-600">{f.notas}</p>
                        </section>
                    )}

                    {/* O BLOCO FISCAL — só no que a empresa comunica. É o que
                        responde à pergunta que se faz a seguir a emitir: «foi
                        aceite?». Numa proforma não aparece: nunca é enviada. */}
                    {f.fiscal && (
                        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-landmark mr-2 text-slate-400" aria-hidden="true" />
                                {t('Dados Fiscais AGT')}
                            </h4>
                            <div className="grid gap-3 text-sm sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-slate-500">{t('Hash SAFT')}</p>
                                    <p className="font-mono font-bold text-slate-900">{f.fiscal.hash ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">{t('Estado SAFT')}</p>
                                    <p className="font-bold text-slate-900">{f.fiscal.estado_saft ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">{t('Submissão AGT')}</p>
                                    <Etiqueta cor={f.agt.cor} icone={SINAL_AGT[f.agt.cor]}>
                                        {f.agt.rotulo}
                                    </Etiqueta>
                                </div>
                            </div>
                            {f.fiscal.agt_referencia && (
                                <p className="mt-2 text-xs text-slate-500">
                                    {t('Referência AGT:')}{' '}
                                    <span className="font-mono text-slate-700">{f.fiscal.agt_referencia}</span>
                                    {f.fiscal.agt_submetido_em && ` · ${f.fiscal.agt_submetido_em}`}
                                </p>
                            )}
                        </section>
                    )}
                </div>
            )}
        </Modal>
    );
}

/** Uma linha dos totais: rótulo à esquerda, valor à direita. */
export function Soma({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="flex items-baseline justify-between py-0.5 text-sm">
            <span className="text-slate-600">{rotulo}</span>
            <span className="font-semibold tabular-nums text-slate-800">{kz(valor)} Kz</span>
        </div>
    );
}
