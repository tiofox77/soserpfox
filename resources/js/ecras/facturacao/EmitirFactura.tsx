import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { factura, type LinhaDaFactura } from '@/api/factura';
import type { Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
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
 * EMITIR UMA FACTURA DE VENDA (FT ou FR) — ou abrir uma que já existe.
 *
 * O ecrã mais delicado da casa, e por isso o que menos faz: recolhe o
 * cabeçalho e as linhas, pergunta os totais ao servidor a cada alteração, e
 * grava. Tudo o que a AGT compara — taxa, código SAFT, região, isenção,
 * IEC/IS, retenção, hash — é calculado no `EmissorDeFacturas`.
 *
 * Com `id`, abre a factura: um rascunho edita-se; uma factura emitida
 * abre-se só para ler, porque se rectifica com nota de crédito (Decreto
 * 71/25). O servidor é que diz qual é qual.
 *
 * Com `duplicarDe` (`?duplicar=123` na morada), abre com o CONTEÚDO de outra
 * factura e mais nada: sem `id`, sem número e sem série, gravar cria um
 * documento novo. O que viaja e o que fica está no `DuplicaDocumento`, do
 * lado do servidor — aqui nem sequer chegam os campos da identidade.
 *
 * A FACTURA-RECIBO é paga no acto: exige a forma de pagamento e nasce
 * liquidada, com a entrada na tesouraria. O ecrã só troca os campos; a regra
 * é do servidor.
 */

const LINHA_NOVA: LinhaDaFactura = { product_id: null, description: '', quantity: 1, price: 0, discount_percent: 0 };

export default function EmitirFactura({ id, duplicarDe }: { id?: number; duplicarDe?: number }) {
    const [tipo, porTipo] = useState<'FT' | 'FR'>('FT');
    const [clienteId, porClienteId] = useState('');
    const [armazemId, porArmazemId] = useState('');
    const [serieId, porSerieId] = useState('');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vencimento, porVencimento] = useState('');
    const [entrega, porEntrega] = useState('');
    const [localDeEntrega, porLocalDeEntrega] = useState('');
    const [regiao, porRegiao] = useState('');
    const [pagamento, porPagamento] = useState('');
    const [descontoComercial, porDescontoComercial] = useState('');
    const [descontoFinanceiro, porDescontoFinanceiro] = useState('');
    /*
     * O DESCONTO DE SEMPRE — o `discount_amount`, anterior aos dois acima.
     * Continua na base e continua a somar ao comercial no cálculo; tirá-lo do
     * formulário fazia com que uma factura antiga aberta para editar perdesse
     * o desconto que tinha, sem ninguém dar por isso.
     */
    const [descontoLegado, porDescontoLegado] = useState('');
    /* Prestação de serviço: é o que liga a retenção de IRT. */
    const [servico, porServico] = useState(false);
    const [retencaoTipo, porRetencaoTipo] = useState('');
    const [retencaoPct, porRetencaoPct] = useState('');
    const [notas, porNotas] = useState('');
    const [condicoes, porCondicoes] = useState('');
    const [linhas, porLinhas] = useState<LinhaDaFactura[]>([{ ...LINHA_NOVA }]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; agt: string | null; abrir: string; pdf: string; mensagem: string } | null>(null);
    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    const opcoes = useQuery({ queryKey: ['factura', 'opcoes'], queryFn: factura.opcoes, staleTime: 5 * 60_000 });
    const aberta = useQuery({ queryKey: ['factura', 'abrir', id], queryFn: () => factura.abrir(id ?? 0), enabled: id !== undefined });
    const copia = useQuery({ queryKey: ['factura', 'duplicar', duplicarDe], queryFn: () => factura.duplicar(duplicarDe ?? 0), enabled: id === undefined && duplicarDe !== undefined });

    /*
     * O conteúdo que entra no formulário — venha de uma factura aberta ou de
     * uma duplicada. É o MESMO carregamento, e é isso que garante que o
     * duplicado herda o que a edição herdaria: o que ele não herda é o que o
     * servidor não mandou.
     */
    const carregado = aberta.data ?? copia.data ?? null;

    useEffect(() => {
        const d = carregado?.documento;
        if (!d) return;
        porTipo(d.invoice_type === 'FR' ? 'FR' : 'FT');
        porClienteId(d.client_id ? String(d.client_id) : '');
        porArmazemId(d.warehouse_id ? String(d.warehouse_id) : '');
        // A SÉRIE SÓ VEM DE UMA FACTURA ABERTA. Um duplicado nasce na série
        // por omissão do seu tipo — herdar a do original era herdar metade da
        // identidade dele.
        porSerieId(aberta.data?.documento.series_id ? String(aberta.data.documento.series_id) : '');
        porDia(d.invoice_date ?? new Date().toISOString().slice(0, 10));
        porVencimento(d.due_date ?? '');
        porEntrega(d.delivery_date ?? '');
        porLocalDeEntrega(d.delivery_location ?? '');
        porRegiao(d.tax_country_region ?? '');
        porPagamento(d.payment_method ?? '');
        porDescontoComercial(d.discount_commercial ? String(d.discount_commercial) : '');
        porDescontoFinanceiro(d.discount_financial ? String(d.discount_financial) : '');
        porDescontoLegado(d.discount_amount ? String(d.discount_amount) : '');
        porServico(!!d.is_service);
        porRetencaoTipo(d.withholding_type ?? '');
        porRetencaoPct(d.withholding_percentage ? String(d.withholding_percentage) : '');
        porNotas(d.notes ?? '');
        porCondicoes(d.terms ?? '');
        porLinhas(carregado.linhas.length > 0 ? carregado.linhas.map((l) => ({ ...l, description: l.description ?? '' })) : [{ ...LINHA_NOVA }]);
    }, [carregado]);

    /*
     * O ARMAZÉM NASCE NO QUE A EMPRESA MARCOU COMO PADRÃO.
     *
     * Era um clique por documento a repetir uma decisão já tomada nas
     * definições — e o campo que mais vezes ficava esquecido, com a factura a
     * ser recusada no fim por falta dele. Continua a poder trocar-se, e uma
     * factura aberta traz o armazém que tem.
     */
    useEffect(() => {
        if (!opcoes.data || id !== undefined || armazemId !== '') return;
        if (opcoes.data.armazem_padrao) porArmazemId(String(opcoes.data.armazem_padrao));
    }, [opcoes.data, id, armazemId]);

    /* A série por omissão do tipo escolhido. A FR usa a sequência do POS. Uma factura aberta traz a sua. */
    useEffect(() => {
        if (!opcoes.data || id !== undefined) return;
        const doTipo = opcoes.data.series.filter((s) => (tipo === 'FR' ? s.document_type === 'pos' : s.document_type === 'invoice'));
        const padrao = doTipo.find((s) => s.is_default) ?? doTipo[0];
        porSerieId(padrao ? String(padrao.id) : '');
    }, [opcoes.data, tipo, id]);

    /*
     * O VENCIMENTO SAI DA CONDIÇÃO DE PAGAMENTO DO CLIENTE.
     *
     * Escolher o cliente propõe a data: dia da factura + os dias da condição
     * dele (pronto pagamento vence no próprio dia). Continua a poder mudar-se
     * à mão, e uma factura já aberta traz o vencimento que tem.
     */
    useEffect(() => {
        if (!opcoes.data || id !== undefined || !clienteId) return;
        const cliente = opcoes.data.clientes.find((c) => String(c.id) === clienteId);
        if (!cliente) return;
        const d = new Date(dia + 'T00:00:00');
        d.setDate(d.getDate() + (cliente.payment_term_days ?? 0));
        porVencimento(d.toISOString().slice(0, 10));
    }, [opcoes.data, clienteId, dia, id]);

    /* Uma linha sem artigo, sem preço e sem descrição ainda não é uma linha. */
    const comConteudo = (l: { product_id: number | null; quantity: number | string; price: number | string; description: string }) =>
        Number(l.quantity) > 0 && (l.product_id !== null || Number(l.price) > 0 || l.description.trim() !== '');

    /* A CONTA PEDE-SE AO SERVIDOR, com pausa e cancelamento — ver EmitirProposta. */
    useEffect(() => {
        let cancelado = false;
        const comLinhas = linhas.filter(comConteudo);

        if (comLinhas.length === 0) {
            porTotais(null);
            return;
        }

        porAContar(true);

        const pausa = setTimeout(() => {
            factura
                .calcular({
                    linhas: comLinhas,
                    discount_commercial: Number(descontoComercial) || 0,
                    discount_financial: Number(descontoFinanceiro) || 0,
                    // Sem estes, o total do ecrã não batia com o que o servidor
                    // ia assinar — e a diferença só aparecia depois de emitir.
                    discount_amount: Number(descontoLegado) || 0,
                    is_service: servico,
                })
                .then((r) => { if (!cancelado) porTotais(r.totais); })
                .catch(() => { if (!cancelado) porTotais(null); })
                .finally(() => { if (!cancelado) porAContar(false); });
        }, 400);

        return () => { cancelado = true; clearTimeout(pausa); };
    }, [linhas, descontoComercial, descontoFinanceiro, descontoLegado, servico]);

    const retencaoValor = totais && retencaoPct ? Math.round(totais.base * Number(retencaoPct)) / 100 : 0;

    const guardar = useMutation({
        mutationFn: (status: 'draft' | 'pending') => {
            const corpo = {
                client_id: Number(clienteId) || null,
                warehouse_id: Number(armazemId) || null,
                invoice_type: tipo,
                series_id: Number(serieId) || null,
                invoice_date: dia,
                due_date: vencimento || null,
                delivery_date: entrega || null,
                delivery_location: localDeEntrega || null,
                tax_country_region: regiao || null,
                payment_method: tipo === 'FR' ? pagamento || null : null,
                discount_commercial: Number(descontoComercial) || 0,
                discount_financial: Number(descontoFinanceiro) || 0,
                discount_amount: Number(descontoLegado) || 0,
                is_service: servico,
                withholding_type: retencaoTipo || null,
                withholding_percentage: Number(retencaoPct) || 0,
                withholding_amount: retencaoValor,
                notes: notas || null,
                terms: condicoes || null,
                status,
                linhas: linhas.filter(comConteudo),
            };
            return id !== undefined ? factura.actualizar(id, corpo) : factura.guardar(corpo);
        },
        onSuccess: (r) => { porFeito({ numero: r.numero, agt: r.agt, abrir: r.abrir, pdf: r.pdf, mensagem: r.message }); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id !== undefined && aberta.isPending) || (copia.isPending && copia.fetchStatus !== 'idle')) return <Carregando linhas={8} />;

    if (opcoes.isError || aberta.isError || copia.isError) {
        const erro = opcoes.error ?? aberta.error ?? copia.error;
        return (
            <NaoAbriu
                titulo={t('Não foi possível abrir a factura')}
                mensagem={erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}
            />
        );
    }

    if (feito) {
        return (
            <PainelDeSucesso numero={feito.numero} mensagem={feito.mensagem} agt={feito.agt} icone="fa-file-invoice">
                <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank')}>{t('PDF')}</Botao>
                <Botao icone="fa-list" onClick={() => (window.location.href = '/invoicing/sales/invoices')}>{t('Ver as facturas')}</Botao>
                {id === undefined && <Botao icone="fa-plus" onClick={() => { porFeito(null); porLinhas([{ ...LINHA_NOVA }]); porClienteId(''); porNotas(''); }}>{t('Emitir outra')}</Botao>}
            </PainelDeSucesso>
        );
    }

    const o = opcoes.data;
    const doc = aberta.data?.documento ?? null;
    const soLeitura = doc !== null && !doc.pode_editar;
    const seriesDoTipo = o.series.filter((s) => (tipo === 'FR' ? s.document_type === 'pos' : s.document_type === 'invoice'));
    const temFisicos = linhas.some((l) => o.artigos.find((a) => a.id === l.product_id)?.type !== 'servico' && l.product_id !== null);

    /*
     * A REGIÃO QUE VAI SER APLICADA.
     *
     * Escolhida à mão, é a escolhida. Em «automática», é a do CLIENTE — e essa
     * vem decidida do servidor (`TaxResolver`), no próprio cliente: a regra de
     * que Cabinda tem regime próprio não se reescreve aqui em JavaScript,
     * porque decide quanto imposto se cobra.
     */
    const regiaoAplicada = regiao || o.clientes.find((c) => String(c.id) === clienteId)?.regiao || 'AO';

    const mudarLinha = (i: number, campo: keyof LinhaDaFactura, valor: string) =>
        porLinhas((ls) =>
            ls.map((l, j) => {
                if (j !== i) return l;
                if (campo === 'product_id') {
                    const artigo = o.artigos.find((a) => String(a.id) === valor);
                    return { ...l, product_id: valor ? Number(valor) : null, price: artigo ? artigo.price : l.price, description: artigo ? artigo.name : l.description };
                }
                return { ...l, [campo]: valor };
            }),
        );

    return (
        <div className="space-y-4" data-emissor="factura">
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
                    {soLeitura ? t('Documento emitido: só leitura. Rectifica-se com nota de crédito.') : t('Rascunho: pode alterar e emitir.')}
                </FaixaDoDocumento>
            )}

            {/* Duplicado: diz de onde veio, e diz que não é o mesmo documento. */}
            {copia.data && (
                <FaixaDeDuplicado numeroDaOrigem={copia.data.origem.numero ?? ''}>
                    {t('Duplicado de')} <strong className="font-bold">{copia.data.origem.numero ?? t('documento sem número')}</strong>{' '}
                    {t('— nasce como factura nova, sem número nem série. Confira as datas e emita.')}
                </FaixaDeDuplicado>
            )}

            {/*
              * DUAS COLUNAS: o formulário à esquerda, o resumo à direita.
              *
              * O ecrã de sempre era assim e é melhor. O resumo é o que se
              * consulta o TEMPO TODO enquanto se lançam linhas — «quanto vai
              * dar isto?» — e em coluna única ficava lá em baixo, fora de
              * vista: preenchia-se o documento às cegas e só no fim se via o
              * total. Aqui fica colado ao topo e acompanha a página.
              */}
            <div className="grid gap-4 lg:grid-cols-3">

            {/* Um fieldset desligado fecha tudo o que está dentro. */}
            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0 lg:col-span-2">
            <Cartao titulo={t('Informações Gerais')} icone="fa-circle-info">
                <div className="grid gap-4 sm:grid-cols-2">
                    {/* O cliente escolhe-se com procura, e cria-se aqui mesmo
                        quando ainda não existe — ver `EscolhaDaParte`. */}
                    <EscolhaDaParte
                        criar={o.criar_parte}
                        partes={o.clientes}
                        valor={clienteId}
                        aoEscolher={porClienteId}
                        erro={erros.client_id}
                        className="sm:col-span-2"
                    />

                    {/*
                      * A REGIÃO FISCAL ESTÁ ESCONDIDA, DE PROPÓSITO.
                      *
                      * Fica em automática: o servidor deriva-a da província do
                      * cliente (`TaxResolver`), que é o que está certo em quase
                      * todos os documentos. Cabinda tem regime próprio, mas
                      * quem factura em Luanda não precisa de decidir isso em
                      * cada factura — e um campo que se deixa sempre como está
                      * é ruído entre os que é preciso preencher.
                      *
                      * O ESTADO E O ENVIO CONTINUAM: `regiao` vai no pedido, e
                      * uma factura aberta que tenha uma região escolhida
                      * conserva-a. Só o CONTROLO é que não se desenha.
                      *
                      * PARA A VOLTAR A MOSTRAR: tirar o `false &&` da linha
                      * abaixo. O crachá diz qual é a que vai ser aplicada.
                      */}
                    {false && (
                    <Campo etiqueta={t('Região fiscal')} erro={erros.tax_country_region} className="sm:col-span-2">
                        <span className="flex items-center gap-2">
                            <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                                {o.regioes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                            </select>

                            <span className={cls(
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-bold',
                                regiaoAplicada === 'AO-CAB' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600',
                            )}>
                                {t('a aplicar')}: {regiaoAplicada}
                            </span>
                        </span>
                    </Campo>
                    )}

                    <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id} obrigatorio={temFisicos}>
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">{temFisicos ? t('Escolher…') : t('Só serviços — não é preciso')}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Campo>

                    {tipo === 'FR' && (
                        <Campo etiqueta={t('Forma de pagamento')} erro={erros.payment_method} obrigatorio>
                            <select value={pagamento} onChange={(e) => porPagamento(e.target.value)} className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {o.formas_de_pagamento.map((f) => <option key={f.id} value={f.code}>{f.name}</option>)}
                            </select>
                        </Campo>
                    )}

                    {/* O TIPO SÃO DUAS ESCOLHAS COM CONSEQUÊNCIAS DIFERENTES,
                        e não duas linhas de uma lista.

                        A FT paga-se depois e o recibo vem no pagamento; a FR é
                        paga no acto e usa a MESMA sequência do POS. Quem
                        factura precisa de saber isso ANTES de escolher — o
                        ecrã de sempre punha as duas frases à vista, e num
                        `<select>` elas não cabem. Só se fixa na criação. */}
                    {/*
                      * UM GRUPO DE RÁDIOS NÃO VAI DENTRO DE UM `<label>`.
                      *
                      * O `Campo` é um `<label>`, e um `<label>` etiqueta o
                      * PRIMEIRO controlo lá dentro: o rádio da Factura passava
                      * a chamar-se «Tipo de Documento (obrigatório)» e o da
                      * Factura-Recibo ficava com o nome certo. Quem ouve o
                      * ecrã ouvia duas coisas diferentes para a mesma escolha.
                      *
                      * `<fieldset>` + `<legend>` é o que nomeia um grupo.
                      */}
                    <fieldset className="min-w-0 border-0 p-0 sm:col-span-2">
                        <legend className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('Tipo de Documento')}
                            <span className="ml-0.5 text-red-500" aria-hidden="true">*</span>
                            <span className="sr-only"> {t('(obrigatório)')}</span>
                        </legend>
                        <div className="grid grid-cols-2 gap-3">
                            {([
                                ['FT', t('Factura'), t('A pagar depois. O recibo é emitido no pagamento.'), 'indigo'],
                                ['FR', t('Factura-Recibo'), t('Paga no acto. Usa a mesma sequência do POS.'), 'emerald'],
                            ] as const).map(([valor, nome, explicacao, cor]) => (
                                <label
                                    key={valor}
                                    className={cls(
                                        'cursor-pointer border-2 p-3 transition-all duration-200',
                                        RAIO,
                                        id !== undefined && 'cursor-not-allowed opacity-60',
                                        tipo === valor
                                            ? (cor === 'emerald'
                                                ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-100'
                                                : 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100')
                                            : 'border-slate-200 hover:border-slate-300',
                                    )}
                                >
                                    <span className="flex items-start gap-2">
                                        <input
                                            type="radio"
                                            name="invoice_type"
                                            value={valor}
                                            checked={tipo === valor}
                                            disabled={id !== undefined}
                                            onChange={() => porTipo(valor)}
                                            className={cls('mt-1', cor === 'emerald' ? 'text-emerald-600' : 'text-indigo-600')}
                                        />
                                        <span>
                                            <span className="block text-sm font-bold text-slate-900">
                                                {nome} <span className="font-mono text-xs text-slate-500">({valor})</span>
                                            </span>
                                            <span className="mt-0.5 block text-[11px] text-slate-600">{explicacao}</span>
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {erros.invoice_type?.[0] && (
                            <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.invoice_type[0]}</p>
                        )}
                    </fieldset>

                    <Campo
                        etiqueta={t('Série fiscal')}
                        erro={erros.series_id}
                        className="sm:col-span-2"
                        /* A NOTA DA SÉRIE: o ecrã de sempre explicava porque é
                           que a lista é curta. Sem ela, quem não vê a sua série
                           conclui que o sistema a perdeu. */
                        ajuda={t('Apenas séries activas desta empresa e sincronizadas com a AGT são apresentadas.')}
                    >
                        <select value={serieId} onChange={(e) => porSerieId(e.target.value)} disabled={id !== undefined} className={entrada}>
                            {seriesDoTipo.length === 0 && <option value="">{t('Sem série activa para este tipo')}</option>}
                            {seriesDoTipo.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.series_code} · {s.name}{s.is_default ? ` (${t('por omissão')})` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>


                    <Campo etiqueta={t('Data')} erro={erros.invoice_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Vencimento')} erro={erros.due_date}>
                        <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Data de entrega')} erro={erros.delivery_date}>
                        <input type="date" value={entrega} onChange={(e) => porEntrega(e.target.value)} className={entrada} />
                    </Campo>

                    {/* ONDE os bens são entregues. O emissor já o gravava e o
                        ecrã não o pedia — a factura saía sem morada de
                        entrega, que é o que a guia depois precisa. */}
                    <Campo etiqueta={t('Local de Entrega')} erro={erros.delivery_location}>
                        <input
                            type="text"
                            value={localDeEntrega}
                            onChange={(e) => porLocalDeEntrega(e.target.value)}
                            placeholder={t('Local de entrega dos bens')}
                            className={entrada}
                        />
                    </Campo>

                    {/* O armazém só é obrigatório com artigos físicos. */}


                </div>
            </Cartao>

            <Cartao
                titulo={t('Linhas')}
                icone="fa-box"
                accoes={
                    !soLeitura && (
                        <>
                            {/* O selector com procura: um `<select>` com o
                                catálogo inteiro não se usa ao balcão. O
                                `<select>` por linha fica — é o caminho de
                                quem trabalha por teclado. */}
                            <EscolhaDeArtigo
                                catalogo={o.artigos}
                                aoEscolher={(a) => porLinhas((ls) => juntarArtigo(ls, { ...LINHA_NOVA }, a))}
                            />
                            {/* A linha em branco fica discreta: quem factura
                                escolhe do catálogo, e só descreve à mão o que
                                lá não está. */}
                            <Botao altura="pequeno" icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>{t('Nova linha')}</Botao>
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
                                {/* A COLUNA DO IMPOSTO, que tinha desaparecido.
                                    O ecrã de sempre mostrava aqui a taxa de IVA
                                    da linha e os dois selectores do imposto
                                    ESPECIAL: o IEC (bebidas, tabaco, combustível,
                                    viaturas) e o Imposto de Selo. O servidor
                                    continuava a aceitá-los — só não havia por
                                    onde os escolher, e uma factura de bebidas
                                    deixou de poder levar o que a lei manda. */}
                                <th className={cls('w-52', CELULA_DO_CABECALHO)}>{t('Taxa')}</th>
                                <th className={cls('w-32 text-right', CELULA_DO_CABECALHO)}>{t('Total')}</th>
                                <th className={cls('w-12', CELULA_DO_CABECALHO)}></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => {
                                const artigo = o.artigos.find((a) => a.id === l.product_id);

                                return (
                                <tr key={i} className={LINHA_DA_TABELA} style={cascata(i)}>
                                    <td className="px-4 py-2">
                                        <select value={l.product_id ?? ''} onChange={(e) => mudarLinha(i, 'product_id', e.target.value)} aria-label={t('Artigo da linha :n', { n: i + 1 })} className={entrada}>
                                            <option value="">{t('Escolher…')}</option>
                                            {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                        </select>
                                        {/* O QUE É E EM QUE SE VENDE. O ecrã de
                                            sempre punha aqui o crachá de
                                            produto/serviço e a unidade — é o que
                                            faz reparar numa linha «serviço» com
                                            armazém escolhido. */}
                                        {artigo && (
                                            <p className="mt-1 flex items-center gap-2 text-xs">
                                                <span className={cls(
                                                    'rounded-full px-2 py-0.5 font-semibold',
                                                    artigo.type === 'servico'
                                                        ? 'bg-sky-100 text-sky-700'
                                                        : 'bg-purple-100 text-purple-700',
                                                )}>
                                                    <i className={cls('mr-1 fas', artigo.type === 'servico' ? 'fa-concierge-bell' : 'fa-box')} aria-hidden="true" />
                                                    {artigo.type === 'servico' ? t('Serviço') : t('Produto')}
                                                </span>
                                                {artigo.unit && <span className="text-slate-400">{artigo.unit}</span>}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-2"><input value={l.description} onChange={(e) => mudarLinha(i, 'description', e.target.value)} aria-label={t('Descrição da linha :n', { n: i + 1 })} className={entrada} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => mudarLinha(i, 'quantity', e.target.value)} aria-label={t('Quantidade da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" step="0.01" value={l.price} onChange={(e) => mudarLinha(i, 'price', e.target.value)} aria-label={t('Preço da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" max="100" step="0.01" value={l.discount_percent} onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)} aria-label={t('Desconto da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>

                                    {/* O IMPOSTO DA LINHA.
                                        O IVA vem do artigo e não se escolhe aqui
                                        — é o `TaxResolver` que o decide, e por
                                        isso mostra-se e não se edita. O IEC e o
                                        Selo é que são escolha de quem factura. */}
                                    <td className="px-4 py-2 align-top">
                                        <div className="space-y-1">
                                            <select
                                                value={l.iec ?? ''}
                                                onChange={(e) => mudarLinha(i, 'iec', e.target.value)}
                                                aria-label={t('IEC da linha :n', { n: i + 1 })}
                                                className={cls(entrada, 'h-8 text-xs')}
                                            >
                                                <option value="">{t('+ IEC')}</option>
                                                {o.iec.map((c) => (
                                                    <option key={c.codigo} value={c.codigo}>
                                                        {c.codigo} · {c.descricao} ({c.taxa}%)
                                                    </option>
                                                ))}
                                            </select>

                                            <select
                                                value={l.is ?? ''}
                                                onChange={(e) => mudarLinha(i, 'is', e.target.value)}
                                                aria-label={t('Imposto de selo da linha :n', { n: i + 1 })}
                                                className={cls(entrada, 'h-8 text-xs')}
                                            >
                                                <option value="">{t('+ Selo')}</option>
                                                {o.selo.map((v) => (
                                                    <option key={v.codigo} value={v.codigo}>
                                                        V{v.codigo} · {v.descricao}{' '}
                                                        ({v.tipo === 'PERCENTAGE' ? `${v.taxa}%` : `AKZ ${v.taxa}`})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </td>

                                    {/* O TOTAL DA LINHA. Sem ele, conferir uma
                                        factura de vinte linhas obriga a fazer a
                                        conta de cabeça vinte vezes. */}
                                    <td className="px-4 py-2 text-right align-top">
                                        <span className="font-bold tabular-nums text-slate-900">
                                            {kz(Number(l.quantity || 0) * Number(l.price || 0) * (1 - Number(l.discount_percent || 0) / 100))}
                                        </span>
                                        <span className="block text-xs text-slate-400">Kz</span>
                                    </td>

                                    <td className="px-4 py-2 text-right align-top">
                                        {/* A última linha não se apaga: um documento sem linhas não é um documento. */}
                                        {linhas.length > 1 && !soLeitura && (
                                            <ApagarLinha aoCarregar={() => porLinhas((ls) => ls.filter((_, j) => j !== i))} rotulo={t('Apagar linha :n', { n: i + 1 })} />
                                        )}
                                    </td>
                                </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                {erros.linhas?.[0] && <p role="alert" className="border-t border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{erros.linhas[0]}</p>}
            </Cartao>

            {/* OS DESCONTOS, os três, numa fileira. A retenção saiu daqui:
                pertence ao resumo, que é onde se vê o efeito dela. */}
            <Cartao titulo={t('Descontos')} icone="fa-tags">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta={t('Desconto comercial (antes IVA)')} erro={erros.discount_commercial}>
                        <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>

                    {/* O DESCONTO DE SEMPRE.
                        Existe na base desde antes dos dois ao lado e soma ao
                        comercial no cálculo do servidor. Tirá-lo do formulário
                        fazia uma factura antiga aberta para editar perder o
                        desconto que tinha, calada. */}
                    <Campo
                        etiqueta={t('Desconto (legado)')}
                        erro={erros.discount_amount}
                        ajuda={t('Campo antigo, mantido para as facturas que o têm. Soma ao comercial.')}
                    >
                        <input type="number" min="0" step="0.01" value={descontoLegado} onChange={(e) => porDescontoLegado(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Desconto financeiro (após IVA)')} erro={erros.discount_financial}>
                        <input type="number" min="0" step="0.01" value={descontoFinanceiro} onChange={(e) => porDescontoFinanceiro(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                </div>
            </Cartao>

            <Cartao titulo={t('Observações')} icone="fa-sticky-note">
                <div className="space-y-4">
                    <Campo etiqueta={t('Notas')} erro={erros.notes}>
                        <textarea rows={3} value={notas} onChange={(e) => porNotas(e.target.value)} placeholder={t('Informações adicionais…')} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>

                    {/* AS CONDIÇÕES SAEM NO PAPEL — prazos, garantias.
                        Não são notas internas. */}
                    <Campo etiqueta={t('Termos e Condições')} erro={erros.terms}>
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
            </fieldset>

            {/*
              * O RESUMO — um cartão só, colado ao topo.
              *
              * Tudo o que responde a «quanto vai dar isto» está aqui dentro e
              * em mais lado nenhum: a natureza do documento, as parcelas, a
              * retenção e o total. Espalhá-lo por dois cartões obrigava a
              * saltar entre eles para perceber de onde vinha um número.
              */}
            <div className="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <CartaoDeTotais titulo={t('Resumo')} aContar={aContar}>
                    {/* A NATUREZA E A RETENÇÃO vivem no resumo porque é aqui
                        que se vê o que elas fazem ao total. O fieldset é
                        próprio: os botões abaixo têm de continuar a funcionar
                        numa factura só de leitura. */}
                    <fieldset disabled={soLeitura} className="space-y-3 border-0 px-5 pt-4 pb-1">
                        <label className={cls(
                            'flex cursor-pointer items-center gap-3 border p-3 text-sm transition-colors',
                            RAIO,
                            servico ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200',
                        )}>
                            <input
                                type="checkbox"
                                checked={servico}
                                onChange={(e) => porServico(e.target.checked)}
                                className="h-5 w-5 rounded border-slate-300 text-indigo-600"
                            />
                            <span className="font-bold text-slate-700">
                                <i className="fas fa-concierge-bell mr-1.5 text-indigo-600" aria-hidden="true" />
                                {t('É prestação de serviço (IRT)')}
                            </span>
                        </label>
                    </fieldset>

                            {/* A RETENÇÃO ESCOLHE-SE AQUI, debaixo da parcela
                                que ela produz: mudar o tipo e ver o total
                                mexer é a mesma coisa num sítio só. */}
                            <fieldset disabled={soLeitura} className="border-0 px-5 py-3">
                                <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <i className="fas fa-hand-holding-dollar mr-1 text-rose-600" aria-hidden="true" />
                                    {t('Retenção na fonte')}
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    <select
                                        value={retencaoTipo}
                                        onChange={(e) => porRetencaoTipo(e.target.value)}
                                        aria-label={t('Retenção na fonte')}
                                        className={cls(entrada, 'h-9 text-xs')}
                                    >
                                        <option value="">{t('Sem retenção')}</option>
                                        {o.retencoes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                                    </select>
                                    <input
                                        type="number" min="0" max="100" step="0.01"
                                        value={retencaoPct}
                                        onChange={(e) => porRetencaoPct(e.target.value)}
                                        disabled={!retencaoTipo}
                                        placeholder="%"
                                        aria-label={t('Percentagem')}
                                        className={cls(entrada, 'h-9 text-right text-xs tabular-nums disabled:bg-slate-100')}
                                    />
                                </div>
                                {erros.withholding_type?.[0] && <p role="alert" className="mt-1 text-xs text-red-600">{erros.withholding_type[0]}</p>}
                                {erros.withholding_percentage?.[0] && <p role="alert" className="mt-1 text-xs text-red-600">{erros.withholding_percentage[0]}</p>}
                            </fieldset>

                    {totais ? (
                        <>
                            <dl className={cls('px-5 pt-3', aContar && 'opacity-60')}>
                                <ParcelaDoTotal rotulo={t('Valor bruto')} valor={kz(totais.bruto)} />
                                {totais.desconto_comercial > 0 && <ParcelaDoTotal rotulo={t('Desconto comercial')} valor={kz(-totais.desconto_comercial)} icone="fa-scissors" />}
                                <ParcelaDoTotal rotulo={t('Incidência de IVA')} valor={kz(totais.base)} />
                                <ParcelaDoTotal rotulo={t('Imposto')} valor={kz(totais.imposto)} icone="fa-percent" realce="imposto" />
                                {Number(descontoFinanceiro) > 0 && <ParcelaDoTotal rotulo={t('Desconto financeiro')} valor={kz(-Number(descontoFinanceiro))} icone="fa-scissors" />}
                                {retencaoValor > 0 && <ParcelaDoTotal rotulo={t('Retenção :tipo', { tipo: retencaoTipo })} valor={kz(-retencaoValor)} icone="fa-hand-holding-dollar" realce="retencao" />}
                            </dl>

                            <TotalGrande
                                rotulo={t('Total')}
                                valor={<>{kz(totais.total - retencaoValor)} <span className="text-base font-normal text-emerald-800/60">Kz</span></>}
                                nota={t('Contado no servidor — é o mesmo cálculo que assina o documento.')}
                            />

                            {/* A BASE E A LEI QUE MANDA NA CONTA.
                                O ecrã de sempre fechava o resumo com a
                                incidência do IVA e a nota do Decreto — é o que
                                um contabilista procura para conferir, e é o que
                                se responde a quem pergunta de onde saiu o
                                número. */}
                            <div className={cls('mt-4 bg-blue-50 px-3 py-2.5 text-xs text-slate-600', RAIO)}>
                                <p className="flex justify-between">
                                    <span>{t('Incidência IVA (base)')}:</span>
                                    <span className="font-semibold tabular-nums">{kz(totais.base)} Kz</span>
                                </p>
                                <p className="mt-2 text-[10px] text-slate-500">
                                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                                    {t('Cálculo conforme Decreto Presidencial 312/18 — AGT Angola')}
                                </p>
                            </div>
                        </>
                    ) : (
                        <SemNada icone="fa-calculator">{t('Escolha um artigo e uma quantidade para ver os totais.')}</SemNada>
                    )}
                </CartaoDeTotais>

                {/*
                  * OS BOTÕES, debaixo do resumo e um por linha.
                  *
                  * É a última coisa que se faz e fica onde a vista já está —
                  * no resumo, depois de conferir o total. Um por linha porque
                  * são decisões diferentes: guardar para acabar depois, ou
                  * EMITIR, que assina e não se desfaz.
                  *
                  * Fora do fieldset: numa factura só de leitura os campos
                  * fecham-se, mas voltar à lista tem de continuar a funcionar.
                  */}
                <div className={cls(CARTAO, 'space-y-3 p-5')}>
                    {!soLeitura && (
                        <Botao
                            className="w-full"
                            icone="fa-file"
                            aTrabalhar={guardar.isPending && guardar.variables === 'draft'}
                            onClick={() => guardar.mutate('draft')}
                        >
                            {t('Guardar rascunho')}
                        </Botao>
                    )}

                    {!soLeitura && (
                        <Botao
                            className="w-full"
                            cor="primaria"
                            tom="solida"
                            altura="grande"
                            icone="fa-file-signature"
                            aTrabalhar={guardar.isPending && guardar.variables === 'pending'}
                            disabled={!o.permissoes.pode_criar}
                            onClick={() => guardar.mutate('pending')}
                        >
                            {tipo === 'FR' ? t('Emitir factura-recibo') : t('Emitir factura')}
                        </Botao>
                    )}

                    <Botao className="w-full" icone="fa-arrow-left" onClick={() => (window.location.href = '/invoicing/sales/invoices')}>
                        {soLeitura ? t('Voltar às facturas') : t('Cancelar')}
                    </Botao>
                </div>
            </div>

            </div>
        </div>
    );
}
