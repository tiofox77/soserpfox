import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { emissor, type LinhaDoEditor, type Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Campo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { EscolhaDaParte } from './EscolhaDaParte';
import { EscolhaDeArtigo, juntarArtigo } from './EscolhaDeArtigo';
import {
    ApagarLinha,
    CABECALHO_DA_TABELA,
    CELULA_DO_CABECALHO,
    CartaoDeTotais,
    FaixaDeDuplicado,
    FaixaDoDocumento,
    LINHA_DA_TABELA,
    NaoAbriu,
    PainelDeSucesso,
    ParcelaDoTotal,
    SemNada,
    TotalGrande,
    cascata,
} from './PecasDoEditor';

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
    const [armazemId, porArmazemId] = useState('');
    const [data, porData] = useState(() => new Date().toISOString().slice(0, 10));
    const [validoAte, porValidoAte] = useState('');
    const [regiao, porRegiao] = useState('');
    const [eServico, porEServico] = useState(false);
    const [notas, porNotas] = useState('');
    const [condicoes, porCondicoes] = useState('');
    const [modeloId, porModeloId] = useState('');
    const [campos, porCampos] = useState<Record<string, string>>({});
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
        porArmazemId(d.warehouse_id ? String(d.warehouse_id) : '');
        porData(d.data ?? new Date().toISOString().slice(0, 10));
        porValidoAte(d.valido_ate ?? '');
        porRegiao(d.tax_country_region ?? '');
        porEServico(Boolean(d.is_service));
        porNotas(d.notas ?? '');
        porCondicoes(d.condicoes ?? '');
        porModeloId(d.quote_template_id ? String(d.quote_template_id) : '');
        porCampos({ ...(d.campos_proposta ?? {}) });
        porLinhas(carregado.linhas.length > 0 ? carregado.linhas : [{ ...LINHA_NOVA }]);
    }, [carregado]);

    /*
     * UMA PROPOSTA NOVA NASCE COM O ARMAZÉM E O MODELO POR OMISSÃO DA EMPRESA.
     *
     * Era o que o `mount` do Livewire fazia: ninguém se lembra de escolher o
     * modelo, e sem ele o orçamento saía pelo desenho antigo mesmo com um
     * modelo desenhado à espera. Uma proposta aberta ou duplicada traz os
     * seus, e por isso não passa por aqui.
     */
    useEffect(() => {
        if (!opcoes.data || id !== undefined || duplicarDe !== undefined) return;
        porArmazemId(opcoes.data.armazem_padrao ? String(opcoes.data.armazem_padrao) : '');
        porModeloId(opcoes.data.modelo_padrao ? String(opcoes.data.modelo_padrao) : '');
    }, [opcoes.data, id, duplicarDe]);

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
                // `is_service` vai junto porque muda os totais: uma prestação
                // de serviço retém 6,5% de IRT.
                .calcular(tipo, { linhas: comLinhas, is_service: eServico })
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
    }, [linhas, tipo, eServico]);

    const guardar = useMutation({
        mutationFn: () => {
            const corpo = {
                parte_id: Number(parteId),
                warehouse_id: Number(armazemId) || null,
                data,
                valido_ate: validoAte || null,
                tax_country_region: regiao || null,
                is_service: eServico,
                notas: notas || null,
                condicoes: condicoes || null,
                quote_template_id: Number(modeloId) || null,
                campos_proposta: campos,
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

    /* Mercadoria no documento: é isso que torna o armazém obrigatório. Uma
       proposta marcada como prestação de serviço dispensa-o na mesma. */
    const temFisicos = linhas.some((l) => l.product_id !== null && o.artigos.find((a) => a.id === l.product_id)?.type !== 'servico');
    const precisaDeArmazem = temFisicos && !eServico;

    /* O modelo escolhido, e os campos livres que ele pede a quem escreve. */
    const modelo = o.modelos.find((m) => String(m.id) === modeloId) ?? null;

    /* Gravado: o ecrã dá o número e sai da frente. */
    if (gravado) {
        return (
            <PainelDeSucesso numero={gravado.numero} mensagem={gravado.mensagem} icone="fa-file-signature">
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
                            porCondicoes('');
                            porCampos({});
                        }}
                    >
                        {t('Emitir outro')}
                    </Botao>
                )}
            </PainelDeSucesso>
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
                <FaixaDoDocumento
                    soLeitura={soLeitura}
                    numero={doc.numero ?? t('Rascunho')}
                    estado={doc.estado}
                    accao={
                        <a href={doc.pdf} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md', RAIO, FOCO)}><i className="fas fa-file-pdf text-red-500" aria-hidden="true" />{t('PDF')}</a>
                    }
                >
                    {soLeitura ? t('Este documento já seguiu: só leitura.') : t('Rascunho: pode alterar.')}
                </FaixaDoDocumento>
            )}

            {/* Duplicado: diz de onde veio, e diz que não é o mesmo documento. */}
            {copia.data && (
                <FaixaDeDuplicado numeroDaOrigem={copia.data.origem.numero ?? ''}>
                    {t('Duplicado de')} <strong className="font-bold">{copia.data.origem.numero ?? t('documento sem número')}</strong>{' '}
                    {t('— nasce como documento novo, sem número. Confira a data e a validade.')}
                </FaixaDeDuplicado>
            )}

            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo={t('Dados do documento')} icone="fa-circle-info">
                <div className="grid gap-4 sm:grid-cols-3">
                    {/* A outra parte escolhe-se com procura, e cria-se aqui
                        mesmo quando ainda não existe — cliente numa proposta
                        de venda, fornecedor numa de compra. */}
                    <EscolhaDaParte
                        criar={o.criar_parte}
                        partes={o.partes}
                        valor={parteId}
                        aoEscolher={porParteId}
                        erro={erros.parte_id}
                        className="sm:col-span-2"
                    />

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

                    {/* O armazém só é obrigatório havendo mercadoria — um
                        documento só de serviços dispensa-o. */}
                    <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id} obrigatorio={precisaDeArmazem}>
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">{precisaDeArmazem ? t('Escolher…') : t('Só serviços — não é preciso')}</option>
                            {o.armazens.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
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

                {/* PRESTAÇÃO DE SERVIÇO: retém-se IRT a 6,5% e o armazém
                    deixa de fazer falta. A conta é do servidor — marcar isto
                    volta a perguntar-lhe os totais. */}
                <label className="mt-4 flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={eServico}
                        onChange={(e) => porEServico(e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300"
                    />
                    {t('É Prestação de Serviço (IRT 6.5%)')}
                </label>
            </Cartao>

            {/* O MODELO POR QUE A PROPOSTA É DESENHADA.
                Só o orçamento tem modelos — nos outros documentos o servidor
                manda a lista vazia e isto nem aparece. */}
            {o.modelos.length > 0 && (
                <Cartao titulo={t('Proposta')} icone="fa-swatchbook">
                    <Campo etiqueta={t('Modelo')} erro={erros.quote_template_id}>
                        <select value={modeloId} onChange={(e) => porModeloId(e.target.value)} className={entrada}>
                            <option value="">{t('Sem modelo — documento simples')}</option>
                            {o.modelos.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.nome}
                                    {m.is_default ? ` · ${t('padrão')}` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    {/* Os campos que o modelo escolhido deixou por preencher.
                        É aqui que a proposta deixa de ser genérica. */}
                    {modelo && modelo.campos.length > 0 && (
                        <div className="mt-4 space-y-4">
                            {modelo.campos.map((c) => (
                                <Campo key={c.chave} etiqueta={c.rotulo}>
                                    <textarea
                                        rows={c.linhas || 4}
                                        value={campos[c.chave] ?? ''}
                                        onChange={(e) => porCampos((v) => ({ ...v, [c.chave]: e.target.value }))}
                                        placeholder={c.ajuda || c.rotulo}
                                        className={cls(entrada, 'h-auto py-2')}
                                    />
                                </Campo>
                            ))}
                        </div>
                    )}

                    {modelo && modelo.campos.length === 0 && (
                        <p className="mt-3 text-xs text-slate-400">
                            {t('Este modelo não tem campos a preencher — sai sempre igual.')}
                        </p>
                    )}
                </Cartao>
            )}

            <Cartao
                titulo={t('Linhas')}
                icone="fa-box"
                accoes={
                    !soLeitura && (
                        <>
                            {/* O selector com procura, ao lado da linha em
                                branco — como o editor de sempre tinha. */}
                            <EscolhaDeArtigo
                                catalogo={o.artigos}
                                aoEscolher={(a) => porLinhas((ls) => juntarArtigo(ls, { ...LINHA_NOVA }, a))}
                            />
                            <Botao altura="pequeno" icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>
                                {t('Nova linha')}
                            </Botao>
                        </>
                    )
                }
                semPadding
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className={CABECALHO_DA_TABELA}>
                            <tr className="border-b border-slate-200">
                                <th className={CELULA_DO_CABECALHO}>{t('Artigo')}</th>
                                <th className={CELULA_DO_CABECALHO}>{t('Descrição')}</th>
                                <th className={cls('w-24 text-right', CELULA_DO_CABECALHO)}>{t('Qtd.')}</th>
                                <th className={cls('w-32 text-right', CELULA_DO_CABECALHO)}>{t('Preço')}</th>
                                <th className={cls('w-24 text-right', CELULA_DO_CABECALHO)}>{t('Desc. %')}</th>
                                <th className={cls('w-12', CELULA_DO_CABECALHO)}></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => (
                                <tr key={i} className={LINHA_DA_TABELA} style={cascata(i)}>
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
                                            <ApagarLinha
                                                aoCarregar={() => porLinhas((ls) => ls.filter((_, j) => j !== i))}
                                                rotulo={t('Apagar linha :n', { n: i + 1 })}
                                            />
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
                <Cartao titulo={t('Observações')} icone="fa-pen">
                    <div className="space-y-4">
                        <Campo etiqueta={t('Notas')} erro={erros.notas}>
                            <textarea
                                rows={4}
                                value={notas}
                                onChange={(e) => porNotas(e.target.value)}
                                aria-label={t('Observações')}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>

                        {/* AS CONDIÇÕES SAEM NO DOCUMENTO — prazos de
                            pagamento, garantias. Não são notas internas. */}
                        <Campo etiqueta={t('Termos e Condições')} erro={erros.condicoes}>
                            <textarea
                                rows={3}
                                value={condicoes}
                                onChange={(e) => porCondicoes(e.target.value)}
                                placeholder={t('Condições de pagamento, garantias, etc.')}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>
                    </div>
                </Cartao>

                {/* OS TOTAIS SÃO OS DO SERVIDOR. Este bloco não calcula nada. */}
                <CartaoDeTotais titulo={t('Totais')} aContar={aContar}>
                    {totais ? (
                        <>
                            <dl className={cls('px-5 pt-3', aContar && 'opacity-60')}>
                                <ParcelaDoTotal rotulo={t('Valor bruto')} valor={kz(totais.bruto)} />
                                {totais.desconto_por_linha > 0 && (
                                    <ParcelaDoTotal rotulo={t('Desconto nas linhas')} valor={kz(-totais.desconto_por_linha)} icone="fa-scissors" />
                                )}
                                <ParcelaDoTotal rotulo={t('Valor líquido')} valor={kz(totais.liquido)} />
                                <ParcelaDoTotal rotulo={t('Incidência de IVA')} valor={kz(totais.base)} />
                                <ParcelaDoTotal rotulo={t('Imposto')} valor={kz(totais.imposto)} icone="fa-percent" realce="imposto" />
                                {totais.retencao > 0 && (
                                    <ParcelaDoTotal rotulo={t('Retenção')} valor={kz(-totais.retencao)} icone="fa-hand-holding-dollar" realce="retencao" />
                                )}
                            </dl>
                            <TotalGrande
                                rotulo={t('Total')}
                                valor={<>{kz(totais.total)} <span className="text-base font-normal text-emerald-800/60">Kz</span></>}
                                nota={t('Contado no servidor — é o mesmo cálculo que vai para o documento.')}
                            />
                        </>
                    ) : (
                        <SemNada icone="fa-calculator">
                            {t('Escolha um artigo e uma quantidade para ver os totais.')}
                        </SemNada>
                    )}
                </CartaoDeTotais>
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

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <NaoAbriu titulo={t('Não foi possível abrir o emissor')} mensagem={daApi?.message ?? t('Verifique a ligação.')} />
    );
}
