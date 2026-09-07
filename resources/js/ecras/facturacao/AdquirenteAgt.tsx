import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { adquirente, type Accao, type EstadoDoAdquirente, type FacturaRecebida } from '@/api/adquirente';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { EstadoNaFaixa, Faixa, SemNada, cascata } from './faixa';

/**
 * AS FACTURAS RECEBIDAS — O PAINEL DO ADQUIRENTE (DS.120 §§4.3, 4.4, 4.7).
 *
 * Aqui não se emite nada: vê-se o que os FORNECEDORES emitiram contra esta
 * empresa, abre-se o detalhe de uma, e confirma-se (C) ou rejeita-se (R).
 *
 * CONFIRMAR E REJEITAR ESCREVEM NA AGT, e não há volta. Por isso a decisão
 * passa por uma janela que diz qual é o documento, e o servidor
 * (`PainelDoAdquirente`) recusa-a fora do ambiente em que a empresa emite.
 *
 * A listagem começa vazia de propósito: cada consulta é uma ida à AGT, e
 * abrir a página não é razão para lá bater.
 */
export default function AdquirenteAgt() {
    const estado = useQuery({ queryKey: ['adquirente', 'estado'], queryFn: adquirente.estado, staleTime: 5 * 60_000 });

    if (estado.isPending) return <Carregando linhas={8} />;
    if (estado.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as facturas recebidas')}</h2>
                <p className="text-sm text-red-800">{estado.error instanceof ErroDaApi ? estado.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    return <Painel e={estado.data.data} podeValidar={estado.data.permissoes.pode_validar} />;
}

function Painel({ e, podeValidar }: { e: EstadoDoAdquirente; podeValidar: boolean }) {
    const cache = useQueryClient();
    const [periodo, porPeriodo] = useState(e.periodo);
    // Só se pergunta à AGT depois de alguém pedir: `pedido` é o período que
    // já foi consultado, e é ele que serve de chave à consulta.
    const [pedido, porPedido] = useState<{ de: string; ate: string } | null>(null);
    const [documento, porDocumento] = useState<string | null>(null);
    const [decisao, porDecisao] = useState<Accao | null>(null);
    const [recado, porRecado] = useState<{ tipo: 'bom' | 'mau'; texto: string } | null>(null);

    const falhou = (erro: unknown) => porRecado({ tipo: 'mau', texto: erro instanceof ErroDaApi ? erro.message : t('Não foi possível concluir.') });

    const lista = useQuery({
        queryKey: ['adquirente', 'facturas', pedido?.de ?? '', pedido?.ate ?? ''],
        queryFn: () => adquirente.listar(pedido!.de, pedido!.ate),
        enabled: pedido !== null,
        placeholderData: keepPreviousData,
        retry: false,
    });

    const detalhe = useQuery({
        queryKey: ['adquirente', 'factura', documento ?? ''],
        queryFn: () => adquirente.detalhe(documento!),
        enabled: documento !== null,
        retry: false,
    });

    const validar = useMutation({
        mutationFn: (corpo: { percentagem?: number | null; valor?: number | null }) => adquirente.validar({
            documento: documento!,
            accao: decisao!,
            percentagem_iva_dedutivel: corpo.percentagem ?? null,
            valor_nao_dedutivel: corpo.valor ?? null,
        }),
        onSuccess: (r) => {
            porRecado({ tipo: 'bom', texto: r.message });
            porDecisao(null);
            void cache.invalidateQueries({ queryKey: ['adquirente', 'facturas'] });
            void cache.invalidateQueries({ queryKey: ['adquirente', 'factura'] });
        },
        onError: falhou,
    });

    const facturas = lista.data?.data.facturas ?? [];

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, recado.tipo === 'bom' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                    <span><i className={cls('fas mr-2', recado.tipo === 'bom' ? 'fa-circle-check' : 'fa-circle-exclamation')} aria-hidden="true" />{recado.texto}</span>
                    <button type="button" onClick={() => porRecado(null)} aria-label={t('Fechar')} className="p-1"><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Faixa
                icone="fa-file-import"
                cor="laranja"
                titulo={t('Facturas Recebidas')}
                subtitulo={t('Listar, consultar e validar facturas emitidas por fornecedores — DS.120 §§4.3, 4.4, 4.7.')}
                accoes={
                    <EstadoNaFaixa icone={e.ambiente === 'production' ? 'fa-shield-halved' : 'fa-flask'}>
                        <span data-ambiente>{t('A consultar em')} <strong>{e.rotulo}</strong></span>
                    </EstadoNaFaixa>
                }
            />

            <Cartao
                titulo={t('Facturas recebidas de fornecedores')}
                icone="fa-file-import"
                accoes={<Botao cor="primaria" tom="solida" icone="fa-magnifying-glass" aTrabalhar={lista.isFetching} onClick={() => { porRecado(null); porPedido({ ...periodo }); }}>{t('Listar facturas')}</Botao>}
            >
                <p className="mb-4 text-sm text-slate-600">
                    <strong className="text-slate-900">{e.empresa.nome}</strong>
                    {e.empresa.nif && <span className="ml-2 font-mono text-xs text-slate-500">{t('NIF')} {e.empresa.nif}</span>}
                </p>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta={t('De')} obrigatorio>
                        <input type="date" value={periodo.de} onChange={(ev) => porPeriodo({ ...periodo, de: ev.target.value })} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Até')} obrigatorio>
                        <input type="date" value={periodo.ate} onChange={(ev) => porPeriodo({ ...periodo, ate: ev.target.value })} className={entrada} />
                    </Campo>
                </div>

                {e.em_falta.length > 0 && (
                    <p className="mt-4 text-sm text-amber-800">{t('Falta configurar: :lista.', { lista: e.em_falta.join(', ') })}</p>
                )}

                <p className="mt-4 text-xs text-slate-500">{t('A AGT devolve aqui só os documentos em que esta empresa é o adquirente. Os que ela emitiu não aparecem nesta lista.')}</p>
            </Cartao>

            {lista.isError && (
                <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)}>
                    <i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('A AGT não respondeu.')}
                </div>
            )}

            <Cartao titulo={t('Facturas do período')} icone="fa-list" semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm" data-facturas>
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                                <th className="px-4 py-3 font-semibold">{t('Documento')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Tipo')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Data')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Estado')}</th>
                                <th className="px-4 py-3 text-right font-semibold">{t('Total líquido')}</th>
                                <th className="w-32 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {/* A tabela começa vazia de propósito, e o vazio
                                explica-se: cada consulta é uma ida à AGT. */}
                            {pedido === null && (
                                <tr><td colSpan={6}><SemNada icone="fa-file-import" titulo={t('Ainda não se consultou a AGT')} frase={t('Escolha o período e carregue em «Listar facturas».')} /></td></tr>
                            )}
                            {pedido !== null && lista.isFetching && facturas.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A consultar a AGT…')}</td></tr>
                            )}
                            {pedido !== null && !lista.isFetching && facturas.length === 0 && !lista.isError && (
                                <tr><td colSpan={6}><SemNada icone="fa-inbox" titulo={t('Nenhuma factura recebida neste período.')} frase={t('Nenhum fornecedor emitiu contra esta empresa entre as datas escolhidas.')} /></td></tr>
                            )}
                            {facturas.map((f, i) => (
                                <Linha key={f.numero ?? i} i={i} f={f} aoAbrir={() => { porRecado(null); porDocumento(f.numero); }} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </Cartao>

            {documento !== null && (
                <Cartao
                    titulo={t('Documento :numero', { numero: documento })}
                    icone="fa-file-invoice"
                    accoes={podeValidar && (
                        <span className="flex gap-2">
                            <Botao cor="bom" tom="solida" icone="fa-check" onClick={() => porDecisao('C')}>{t('Confirmar')}</Botao>
                            <Botao cor="perigo" tom="solida" icone="fa-xmark" onClick={() => porDecisao('R')}>{t('Rejeitar')}</Botao>
                        </span>
                    )}
                >
                    {detalhe.isPending && <Carregando linhas={4} />}
                    {detalhe.isError && (
                        <p role="alert" className="text-sm text-red-800">{detalhe.error instanceof ErroDaApi ? detalhe.error.message : t('A AGT não respondeu.')}</p>
                    )}
                    {detalhe.data && (
                        <>
                            <p className="mb-3 text-xs text-slate-500">{t('Detalhe tal como a AGT o tem (DS.120 §4.4).')}</p>
                            <pre data-detalhe className={cls('max-h-96 overflow-auto border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800', RAIO)}>{JSON.stringify(detalhe.data.data.detalhe, null, 2)}</pre>
                        </>
                    )}
                </Cartao>
            )}

            {/* A `key` remonta a janela a cada decisão: o que se escreveu numa
                confirmação não pode aparecer na seguinte, nem noutro documento. */}
            <JanelaDaDecisao
                key={`${documento ?? ''}-${decisao ?? ''}`}
                accao={decisao}
                documento={documento}
                aTrabalhar={validar.isPending}
                aoFechar={() => porDecisao(null)}
                aoDecidir={(p, v) => validar.mutate({ percentagem: p, valor: v })}
            />
        </div>
    );
}

function Linha({ f, i, aoAbrir }: { f: FacturaRecebida; i: number; aoAbrir: () => void }) {
    const cor = f.estado === 'V' ? 'bom' : f.estado === 'I' ? 'perigo' : 'neutra';
    const icone = f.estado === 'V' ? 'fa-circle-check' : f.estado === 'I' ? 'fa-circle-xmark' : 'fa-clock';

    return (
        <tr className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
            <td className="px-4 py-2 font-mono text-xs font-semibold text-slate-900">{f.numero ?? '—'}</td>
            <td className="px-4 py-2 text-slate-600">{f.tipo ?? '—'}</td>
            <td className="whitespace-nowrap px-4 py-2 text-slate-600">{f.data ?? '—'}</td>
            <td className="px-4 py-2">
                {/* O estado vem escrito pela AGT: mostra-se como veio — e com
                    o ícone, para não depender só da cor. */}
                <Etiqueta cor={cor} icone={icone}>{f.estado_descricao ?? f.estado ?? '—'}</Etiqueta>
            </td>
            <td className="px-4 py-2 text-right tabular-nums">{f.liquido === null ? '—' : `${kz(f.liquido)} Kz`}</td>
            <td className="px-4 py-2 text-right">
                {f.numero && <Botao icone="fa-eye" onClick={aoAbrir}>{t('Detalhe')}</Botao>}
            </td>
        </tr>
    );
}

/**
 * A JANELA DA DECISÃO.
 *
 * A percentagem de IVA dedutível e o valor não dedutível são EXCLUSIVOS: a
 * AGT recusa os dois juntos. Preencher um limpa o outro, para não haver
 * maneira de os enviar ao mesmo tempo.
 */
function JanelaDaDecisao({ accao, documento, aTrabalhar, aoFechar, aoDecidir }: {
    accao: Accao | null;
    documento: string | null;
    aTrabalhar: boolean;
    aoFechar: () => void;
    aoDecidir: (percentagem: number | null, valor: number | null) => void;
}) {
    const [percentagem, porPercentagem] = useState('');
    const [valor, porValor] = useState('');

    const numero = (v: string) => (v.trim() === '' ? null : Number(v));

    const fechar = () => { porPercentagem(''); porValor(''); aoFechar(); };

    return (
        <Modal
            aberto={accao !== null}
            aoFechar={fechar}
            titulo={accao === 'R' ? t('Rejeitar o documento') : t('Confirmar o documento')}
            rodape={
                <>
                    <Botao onClick={fechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor={accao === 'R' ? 'perigo' : 'bom'}
                        tom="solida"
                        icone={accao === 'R' ? 'fa-xmark' : 'fa-check'}
                        aTrabalhar={aTrabalhar}
                        onClick={() => aoDecidir(numero(percentagem), numero(valor))}
                    >
                        {accao === 'R' ? t('Rejeitar') : t('Confirmar')}
                    </Botao>
                </>
            }
        >
            <p className="text-sm text-slate-700">
                {t('Documento: :numero', { numero: documento ?? '' })}
            </p>
            <p className="mt-1 text-sm text-slate-500">
                {t('A decisão segue para a AGT e não tem volta.')}
            </p>

            {accao === 'C' && (
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('% de IVA dedutível')}>
                        <input type="number" step="0.01" min="0" max="100" value={percentagem}
                            onChange={(ev) => { porPercentagem(ev.target.value); if (ev.target.value !== '') porValor(''); }}
                            className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Valor de IVA não dedutível (Kz)')}>
                        <input type="number" step="0.01" min="0" value={valor}
                            onChange={(ev) => { porValor(ev.target.value); if (ev.target.value !== '') porPercentagem(''); }}
                            className={entrada} />
                    </Campo>
                    <p className="text-xs text-slate-500 sm:col-span-2">{t('Indique um ou outro, nunca os dois — a AGT recusa-os juntos.')}</p>
                </div>
            )}
        </Modal>
    );
}
