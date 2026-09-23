import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { compra, type LinhaDaCompra } from '@/api/compra';
import type { Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { EscolhaDaParte } from './EscolhaDaParte';
import { CampoDoArtigo, EscolhaDeArtigo, juntarArtigo, trocarArtigo, useArtigosConhecidos, type ArtigoDaLinha } from './EscolhaDeArtigo';
import { useImprimirAoGravar } from './imprimirAoGravar';
import {
    ApagarLinha,
    CABECALHO_DA_TABELA,
    CAMPO_DE_PRECO,
    CELULA_DO_CABECALHO,
    CartaoDeTotais,
    FaixaDeDuplicado,
    FaixaDoDocumento,
    LINHA_DA_TABELA,
    NaoAbriu,
    PainelDeSucesso,
    PapelBloqueado,
    ParcelaDoTotal,
    SemNada,
    TotalGrande,
    cascata,
} from './PecasDoEditor';

/**
 * REGISTAR UMA FACTURA DE COMPRA — ou abrir uma que já existe.
 *
 * É a factura do fornecedor, e é ela que dá ENTRADA de stock, cria os lotes
 * e actualiza o custo do artigo. Nada disso se faz aqui: o ecrã recolhe o
 * cabeçalho e as linhas, pergunta os totais ao servidor a cada alteração, e
 * grava pelo `EmissorDeCompras`. Com `id`, abre a compra: só um rascunho se
 * altera — a registada já deu entrada do stock e abre-se só para ler.
 *
 * Com `duplicarDe` (`?duplicar=123` na morada), abre com o CONTEÚDO de outra
 * compra — fornecedor, linhas, preços, lotes — e mais nada: sem `id` e sem
 * número, gravar regista uma compra nova. Duplicar não mexe em stock nenhum;
 * o stock só entra quando o duplicado for mesmo gravado.
 *
 * O PREÇO DE CADA LINHA É O QUE O FORNECEDOR COBROU: nasce do custo
 * conhecido do artigo e corrige-se à mão. O lote e a validade são por linha,
 * porque é a compra que os cria.
 */

const LINHA_NOVA: LinhaDaCompra = { product_id: null, description: '', quantity: 1, price: 0, discount_percent: 0, batch_number: '', expiry_date: '', manufacturing_date: '', alert_days: 30 };

type Estado = 'draft' | 'pending' | 'paid';

export default function EmitirFacturaDeCompra({ id, duplicarDe }: { id?: number; duplicarDe?: number }) {
    const [fornecedorId, porFornecedorId] = useState('');
    const [armazemId, porArmazemId] = useState('');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vencimento, porVencimento] = useState('');
    const [regiao, porRegiao] = useState('AO');
    const [eServico, porEServico] = useState(false);
    const [descontoComercial, porDescontoComercial] = useState('');
    /**
     * O DESCONTO DE SEMPRE (`discount_amount`), que soma ao comercial.
     *
     * Existe na base desde antes dos outros dois, a validação sempre o aceitou
     * e o ecrã não o oferecia: uma compra antiga aberta para editar perdia o
     * desconto que tinha, calada, na primeira gravação.
     */
    const [descontoLegado, porDescontoLegado] = useState('');
    const [descontoFinanceiro, porDescontoFinanceiro] = useState('');
    const [notas, porNotas] = useState('');
    /**
     * OS TERMOS E CONDIÇÕES — o ecrã de sempre pedia-os e a API sempre os
     * aceitou; só o formulário em React é que não os oferecia. São as
     * condições que ficam escritas no documento (prazo, garantia, entrega).
     */
    const [termos, porTermos] = useState('');
    const [linhas, porLinhas] = useState<LinhaDaCompra[]>([{ ...LINHA_NOVA }]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; abrir: string; pdf: string; mensagem: string } | null>(null);
    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    const opcoes = useQuery({ queryKey: ['compra', 'opcoes'], queryFn: compra.opcoes, staleTime: 5 * 60_000 });
    /* O PDF abre sozinho ao registar, se a empresa o pediu — ver `imprimirAoGravar`. */
    const impressao = useImprimirAoGravar(opcoes.data?.imprimir_ao_gravar);
    /** O catálogo carregado (até 500), a custo, e os artigos escolhidos pela procura: o que as linhas mostram. */
    const conhecidos = useArtigosConhecidos(opcoes.data?.artigos.map((a) => ({ ...a, price: a.cost })));
    const aberta = useQuery({ queryKey: ['compra', 'abrir', id], queryFn: () => compra.abrir(id ?? 0), enabled: id !== undefined });
    const copia = useQuery({ queryKey: ['compra', 'duplicar', duplicarDe], queryFn: () => compra.duplicar(duplicarDe ?? 0), enabled: id === undefined && duplicarDe !== undefined });

    /*
     * O conteúdo que entra no formulário — venha de uma compra aberta ou de
     * uma duplicada. É o MESMO carregamento: o duplicado herda o que a edição
     * herdaria, e o que ele não herda é o que o servidor não mandou.
     */
    const carregado = aberta.data ?? copia.data ?? null;

    useEffect(() => {
        const d = carregado?.documento;
        if (!d) return;
        porFornecedorId(d.supplier_id ? String(d.supplier_id) : '');
        porArmazemId(d.warehouse_id ? String(d.warehouse_id) : '');
        porDia(d.invoice_date ?? new Date().toISOString().slice(0, 10));
        porVencimento(d.due_date ?? '');
        porRegiao(d.tax_country_region || 'AO');
        porEServico(d.is_service);
        porDescontoComercial(d.discount_commercial ? String(d.discount_commercial) : '');
        porDescontoLegado(d.discount_amount ? String(d.discount_amount) : '');
        porDescontoFinanceiro(d.discount_financial ? String(d.discount_financial) : '');
        porNotas(d.notes ?? '');
        porTermos(d.terms ?? '');
        porLinhas(carregado.linhas.length > 0 ? carregado.linhas : [{ ...LINHA_NOVA }]);
    }, [carregado]);

    /*
     * UMA COMPRA NOVA NASCE COM O ARMAZÉM POR OMISSÃO DA EMPRESA.
     *
     * A compra dá entrada de stock e o armazém é obrigatório: escolhê-lo à mão
     * de cada vez era uma paragem em todas as compras. Vale o marcado como
     * padrão; e onde não há nenhum marcado mas só existe um armazém, é esse.
     * Uma compra aberta ou duplicada traz o seu, e não passa por aqui.
     */
    useEffect(() => {
        if (!opcoes.data || id !== undefined || duplicarDe !== undefined) return;

        /* Sem padrão marcado, só se escolhe sozinho havendo UM armazém: com
           vários, adivinhar dava entrada do stock no armazém errado. */
        const unico = opcoes.data.armazens.length === 1 ? opcoes.data.armazens[0]?.id : null;
        const escolha = opcoes.data.armazem_padrao ?? unico;

        if (escolha) porArmazemId(String(escolha));
    }, [opcoes.data, id, duplicarDe]);

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
            compra
                .calcular({
                    linhas: comLinhas,
                    discount_commercial: Number(descontoComercial) || 0,
                    discount_amount: Number(descontoLegado) || 0,
                    discount_financial: Number(descontoFinanceiro) || 0,
                    is_service: eServico,
                })
                .then((r) => { if (!cancelado) porTotais(r.totais); })
                .catch(() => { if (!cancelado) porTotais(null); })
                .finally(() => { if (!cancelado) porAContar(false); });
        }, 400);

        return () => { cancelado = true; clearTimeout(pausa); };
    }, [linhas, descontoComercial, descontoLegado, descontoFinanceiro, eServico]);

    const guardar = useMutation({
        mutationFn: (status: Estado) => {
            const corpo = {
                supplier_id: Number(fornecedorId) || null,
                warehouse_id: Number(armazemId) || null,
                invoice_date: dia,
                due_date: vencimento || null,
                tax_country_region: regiao,
                is_service: eServico,
                discount_commercial: Number(descontoComercial) || 0,
                discount_amount: Number(descontoLegado) || 0,
                discount_financial: Number(descontoFinanceiro) || 0,
                notes: notas || null,
                terms: termos || null,
                status,
                linhas: linhas
                    .filter(comConteudo)
                    .map((l) => ({ ...l, batch_number: l.batch_number || null, expiry_date: l.expiry_date || null, manufacturing_date: l.manufacturing_date || null })),
            };
            return id !== undefined ? compra.actualizar(id, corpo) : compra.guardar(corpo);
        },
        onSuccess: (r) => {
            porFeito({ numero: r.numero, abrir: r.abrir, pdf: r.pdf, mensagem: r.message });
            porErros({});
            // O rascunho não se imprime; a compra registada (por pagar ou paga) sim.
            impressao.depoisDeGravar(r.pdf, r.estado);
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id !== undefined && aberta.isPending) || (copia.isPending && copia.fetchStatus !== 'idle')) return <Carregando linhas={8} />;

    if (opcoes.isError || aberta.isError || copia.isError) {
        const erro = opcoes.error ?? aberta.error ?? copia.error;
        return (
            <NaoAbriu
                titulo={t('Não foi possível abrir o registo de compras')}
                mensagem={erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}
            />
        );
    }

    if (feito) {
        return (
            <PainelDeSucesso numero={feito.numero} mensagem={feito.mensagem} icone="fa-truck-ramp-box" aviso={impressao.bloqueado && <PapelBloqueado />}>
                {/* O PAPEL DA COMPRA, como nos outros ecrãs de sucesso — e o
                    remédio quando a impressão ao gravar é bloqueada. */}
                <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank', 'noopener')}>{t('PDF')}</Botao>
                <Botao icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>{t('Ver compras')}</Botao>
                {id === undefined && <Botao icone="fa-plus" onClick={() => { porFeito(null); impressao.esquecer(); porLinhas([{ ...LINHA_NOVA }]); porFornecedorId(''); porNotas(''); }}>{t('Registar outra')}</Botao>}
            </PainelDeSucesso>
        );
    }

    const o = opcoes.data;
    const doc = aberta.data?.documento ?? null;
    const soLeitura = doc !== null && !doc.pode_editar;

    const mudarLinha = (i: number, campo: keyof LinhaDaCompra, valor: string) =>
        porLinhas((ls) =>
            ls.map((l, j) => {
                if (j !== i) return l;
                if (campo === 'product_id') {
                    const artigo = o.artigos.find((a) => String(a.id) === valor);
                    return { ...l, product_id: valor ? Number(valor) : null, price: artigo ? artigo.cost : l.price, description: artigo ? artigo.name : l.description };
                }
                return { ...l, [campo]: valor };
            }),
        );

    const aGuardar = (estado: Estado) => guardar.isPending && guardar.variables === estado;

    return (
        <div className="space-y-4" data-emissor="compra">
            <AvisoDeErro erro={guardar.error} />

            {doc && (
                <FaixaDoDocumento soLeitura={soLeitura} numero={doc.numero ?? t('Rascunho')} estado={doc.estado}>
                    {soLeitura ? t('Compra registada: só leitura. O stock já deu entrada.') : t('Rascunho: pode alterar e registar.')}
                </FaixaDoDocumento>
            )}

            {/* Duplicado: diz de onde veio, e diz que não é o mesmo documento. */}
            {copia.data && (
                <FaixaDeDuplicado numeroDaOrigem={copia.data.origem.numero ?? ''}>
                    {t('Duplicado de')} <strong className="font-bold">{copia.data.origem.numero ?? t('documento sem número')}</strong>{' '}
                    {t('— nasce como compra nova, sem número. O stock só entra quando esta for registada.')}
                </FaixaDeDuplicado>
            )}

            {/*
              * DUAS COLUNAS: o formulário à esquerda, o resumo à direita.
              *
              * O ecrã de sempre era assim e é melhor. O resumo é o que se
              * consulta o TEMPO TODO enquanto se lançam linhas — «quanto é que
              * esta factura dá?» — e em coluna única ficava lá em baixo, fora
              * de vista, o que numa compra é conferir às cegas o que o
              * fornecedor cobrou.
              */}
            {/* O resumo numa coluna estreita e fixa: os totais cabem em 20rem, e as linhas precisam da largura. */}
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">

            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo={t('Documento do fornecedor')} icone="fa-circle-info">
                <div className="grid gap-4 sm:grid-cols-2">
                    {/* O fornecedor escolhe-se com procura, e cria-se aqui
                        mesmo quando ainda não existe — ver `EscolhaDaParte`. */}
                    <EscolhaDaParte
                        criar={o.criar_parte}
                        partes={o.fornecedores}
                        valor={fornecedorId}
                        aoEscolher={porFornecedorId}
                        erro={erros.supplier_id}
                        className="sm:col-span-2"
                    />

                    {/*
                      * A REGIÃO FISCAL ESTÁ ESCONDIDA, DE PROPÓSITO — o mesmo
                      * que na factura de venda e nas propostas.
                      *
                      * Fica em automática. Cabinda tem regime próprio e o que
                      * manda é o LOCAL DA OPERAÇÃO, mas quem compra em Luanda
                      * não decide isso em cada factura — e um campo que se
                      * deixa sempre como está é ruído entre os que é preciso
                      * preencher. O estado e o envio continuam: tirar o
                      * `false &&` abaixo volta a mostrar o controlo.
                      */}
                    {false && (
                    <Campo etiqueta={t('Região fiscal')} erro={erros.tax_country_region} className="sm:col-span-2">
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                        </select>
                    </Campo>
                    )}

                    {/* A compra dá entrada de stock: o armazém é sempre obrigatório. */}
                    <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id} obrigatorio className="sm:col-span-2">
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Data')} erro={erros.invoice_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Vencimento')} erro={erros.due_date}>
                        <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                    </Campo>
                </div>
            </Cartao>

            <Cartao
                titulo={t('Linhas')}
                icone="fa-box"
                accoes={
                    !soLeitura && (
                        <>
                            {/* NUMA COMPRA O QUE SE PROPÕE É O CUSTO, não o
                                preço de venda — é o mesmo que o `<select>` já
                                fazia, e o cartão mostra o mesmo número. */}
                            <EscolhaDeArtigo
                                preco="custo"
                                catalogo={o.artigos.map((a) => ({ ...a, price: a.cost }))}
                                aoEscolher={(a) => {
                                    conhecidos.lembrar(a);
                                    porLinhas((ls) => juntarArtigo(ls, { ...LINHA_NOVA }, a));
                                }}
                            />
                            <Botao altura="pequeno" icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>{t('Nova linha')}</Botao>
                        </>
                    )
                }
                semPadding
            >
                {/*
                  * A LINHA DA COMPRA TEM MAIS DO QUE CABE, e por isso a tabela
                  * tem largura mínima e a caixa é que rola.
                  *
                  * São sete colunas — e uma delas é o lote inteiro. Sem o
                  * mínimo, o browser espremia a quantidade para 35px e o
                  * desconto para 40: caixas onde não se lê o que lá está
                  * escrito. Numa secretária larga não rola nada.
                  */}
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[55rem] text-sm">
                        <thead className={CABECALHO_DA_TABELA}>
                            <tr className="border-b border-slate-200">
                                <th className={CELULA_DO_CABECALHO}>{t('Artigo')}</th>
                                <th className={cls('w-20 text-right', CELULA_DO_CABECALHO)}>{t('Qtd.')}</th>
                                <th className={cls('w-40 text-right', CELULA_DO_CABECALHO)}>{t('Preço de compra')}</th>
                                <th className={cls('w-20 text-right', CELULA_DO_CABECALHO)}>{t('Desc. %')}</th>
                                {/* LOTE, FABRICO, VALIDADE e DIAS DE ALERTA numa
                                    coluna só, como no ecrã de sempre: são quatro
                                    campos de UMA coisa — o lote que entra — e
                                    espalhados por quatro colunas empurravam o
                                    total para fora do ecrã.

                                    Sem a fabricação não se separam duas remessas
                                    com a mesma validade; sem os dias de alerta o
                                    lote avisa com os 30 por omissão, que num
                                    fresco chega tarde. */}
                                <th className={cls('w-64', CELULA_DO_CABECALHO)}>{t('Lote / Validade')}</th>
                                <th className={cls('w-28 text-right', CELULA_DO_CABECALHO)}>{t('Total')}</th>
                                <th className={cls('w-12', CELULA_DO_CABECALHO)}></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => {
                                const artigo = conhecidos.um(l.product_id);

                                return (
                                <tr key={i} className={LINHA_DA_TABELA} style={cascata(i)}>
                                    <td className="px-4 py-2 align-top">
                                        <CampoDoArtigo
                                            n={i + 1}
                                            artigo={conhecidos.um(l.product_id, l)}
                                            aoEscolher={(a) => {
                                                conhecidos.lembrar(a);
                                                porLinhas((ls) => trocarArtigo(ls, i, a));
                                            }}
                                            preco="custo"
                                            catalogo={o.artigos.map((a) => ({ ...a, price: a.cost }))}
                                        />
                                        {l.product_id === null && (
                                            <input value={l.description} onChange={(e) => mudarLinha(i, 'description', e.target.value)} placeholder={t('Ou descreva a linha…')} aria-label={t('Descrição da linha :n', { n: i + 1 })} className={cls(entrada, 'mt-1')} />
                                        )}
                                        {/* O QUE É E EM QUE SE COMPRA: o crachá de
                                            produto/serviço e a unidade. Numa compra
                                            é o que faz reparar que a linha «serviço»
                                            não dá entrada de stock nenhuma. */}
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
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => mudarLinha(i, 'quantity', e.target.value)} aria-label={t('Quantidade da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" step="0.01" value={l.price} onChange={(e) => mudarLinha(i, 'price', e.target.value)} aria-label={t('Preço da linha :n', { n: i + 1 })} className={cls(entrada, CAMPO_DE_PRECO)} /></td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" max="100" step="0.01" value={l.discount_percent} onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)} aria-label={t('Desconto da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    {/* O LOTE INTEIRO NUMA CÉLULA: o número em
                                        cima, as duas datas lado a lado e os dias
                                        de alerta por baixo. Cada campo mantém o
                                        seu nome — quem trabalha por teclado
                                        continua a chegar a todos. */}
                                    <td className="px-4 py-2 align-top">
                                        {/* O mínimo é o que uma caixa de data
                                            precisa para mostrar `dd/mm/aaaa`:
                                            mais estreita, o browser corta a
                                            máscara e ninguém sabe o que
                                            escrever. A tabela rola se for
                                            preciso. */}
                                        <div className="min-w-[14rem] space-y-1">
                                            <input value={l.batch_number} onChange={(e) => mudarLinha(i, 'batch_number', e.target.value)} placeholder={t('Lote')} aria-label={t('Lote da linha :n', { n: i + 1 })} className={cls(entrada, 'h-8 text-xs')} />
                                            <div className="grid grid-cols-2 gap-1">
                                                <input type="date" value={l.manufacturing_date} onChange={(e) => mudarLinha(i, 'manufacturing_date', e.target.value)} title={t('Fabrico')} aria-label={t('Fabrico da linha :n', { n: i + 1 })} className={cls(entrada, 'h-8 px-1 text-xs')} />
                                                <input type="date" value={l.expiry_date} onChange={(e) => mudarLinha(i, 'expiry_date', e.target.value)} title={t('Validade')} aria-label={t('Validade da linha :n', { n: i + 1 })} className={cls(entrada, 'h-8 px-1 text-xs')} />
                                            </div>
                                            <input type="number" min="0" step="1" value={l.alert_days} onChange={(e) => mudarLinha(i, 'alert_days', e.target.value)} title={t('Alerta (dias)')} aria-label={t('Dias de alerta da linha :n', { n: i + 1 })} className={cls(entrada, 'h-8 text-right text-xs tabular-nums')} />
                                        </div>
                                    </td>

                                    {/* O TOTAL DA LINHA, sem imposto: é o que se
                                        confere contra a factura do fornecedor. */}
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

            {/* OS DESCONTOS DO DOCUMENTO, os três, numa fileira — como no
                ecrã de sempre. Os das LINHAS são outra coisa e ficam lá. */}
            <Cartao titulo={t('Descontos')} icone="fa-tags">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta={t('Desconto comercial (antes IVA)')} erro={erros.discount_commercial}>
                        <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>

                    {/* O DESCONTO DE SEMPRE: soma ao comercial no cálculo do
                        servidor. Tirá-lo do formulário fazia uma compra antiga
                        aberta para editar perder o desconto que tinha, calada. */}
                    <Campo
                        etiqueta={t('Desconto (legado)')}
                        erro={erros.discount_amount}
                        ajuda={t('Campo antigo, mantido para os documentos que o têm. Soma ao comercial.')}
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
                    <Campo etiqueta={t('Observações')} erro={erros.notes}>
                        <textarea rows={3} value={notas} onChange={(e) => porNotas(e.target.value)} placeholder={t('Informações adicionais…')} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                    <Campo etiqueta={t('Termos e Condições')} erro={erros.terms}>
                        <textarea rows={3} value={termos} onChange={(e) => porTermos(e.target.value)} placeholder={t('Condições de pagamento, garantias, etc.')} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                </div>
            </Cartao>
            </fieldset>

            {/*
              * O RESUMO — um cartão só, colado ao topo.
              *
              * Tudo o que responde a «quanto é que isto dá» está aqui dentro:
              * a natureza do documento, as parcelas e o total a pagar. OS
              * TOTAIS SÃO OS DO SERVIDOR — este bloco não calcula nada.
              */}
            <div className="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <CartaoDeTotais titulo={t('Resumo')} aContar={aContar}>
                    {/* PRESTAÇÃO DE SERVIÇO: retém-se IRT a 6,5%. Vive no
                        resumo porque é aqui que se vê o que ela faz ao total.
                        Fieldset próprio: os botões abaixo têm de continuar a
                        funcionar numa compra só de leitura. */}
                    <fieldset disabled={soLeitura} className="space-y-3 border-0 px-5 pt-4 pb-1">
                        <label className={cls(
                            'flex cursor-pointer items-center gap-3 border p-3 text-sm transition-colors',
                            RAIO,
                            eServico ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200',
                        )}>
                            <input
                                type="checkbox"
                                checked={eServico}
                                onChange={(e) => porEServico(e.target.checked)}
                                className="h-5 w-5 rounded border-slate-300 text-indigo-600"
                            />
                            <span className="font-bold text-slate-700">
                                <i className="fas fa-concierge-bell mr-1.5 text-indigo-600" aria-hidden="true" />
                                {t('Prestação de serviço (retém IRT 6,5%)')}
                            </span>
                        </label>
                    </fieldset>

                    {totais ? (
                        <>
                            <dl className={cls('px-5 pt-3', aContar && 'opacity-60')}>
                                <ParcelaDoTotal rotulo={t('Valor bruto')} valor={kz(totais.bruto)} />
                                {totais.desconto_comercial > 0 && <ParcelaDoTotal rotulo={t('Desconto comercial')} valor={kz(-totais.desconto_comercial)} icone="fa-scissors" />}
                                <ParcelaDoTotal rotulo={t('Incidência de IVA')} valor={kz(totais.base)} />
                                <ParcelaDoTotal rotulo={t('Imposto')} valor={kz(totais.imposto)} icone="fa-percent" realce="imposto" />
                                {Number(descontoFinanceiro) > 0 && <ParcelaDoTotal rotulo={t('Desconto financeiro')} valor={kz(-Number(descontoFinanceiro))} icone="fa-scissors" />}
                                {totais.retencao > 0 && <ParcelaDoTotal rotulo={t('Retenção IRT (6,5%)')} valor={kz(-totais.retencao)} icone="fa-hand-holding-dollar" realce="retencao" />}
                            </dl>

                            <TotalGrande
                                rotulo={t('Total a pagar')}
                                valor={<>{kz(totais.total)} <span className="text-base font-normal text-emerald-800/60">Kz</span></>}
                                nota={t('Contado no servidor.')}
                            />

                            {/* A BASE E A LEI QUE MANDA NA CONTA — é o que um
                                contabilista procura para conferir a factura do
                                fornecedor contra o que aqui ficou registado. */}
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
                  * São decisões diferentes: guardar para acabar depois,
                  * REGISTAR (que dá entrada do stock e não se desfaz sem
                  * anular), ou registar já paga. Um por linha para não se
                  * carregar no de baixo a pensar no de cima.
                  *
                  * Fora do fieldset: numa compra só de leitura os campos
                  * fecham-se, mas voltar à lista tem de continuar a funcionar.
                  */}
                <div className={cls(CARTAO, 'space-y-3 p-5')}>
                    {!soLeitura && <Botao className="w-full" icone="fa-file" aTrabalhar={aGuardar('draft')} onClick={() => guardar.mutate('draft')}>{t('Guardar rascunho')}</Botao>}

                    {!soLeitura && (
                        <Botao className="w-full" cor="primaria" tom="solida" altura="grande" icone="fa-truck-ramp-box" aTrabalhar={aGuardar('pending')} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('pending')}>
                            {t('Registar compra')}
                        </Botao>
                    )}

                    {!soLeitura && <Botao className="w-full" icone="fa-money-bill" aTrabalhar={aGuardar('paid')} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('paid')}>{t('Registar como paga')}</Botao>}

                    <Botao className="w-full" icone="fa-arrow-left" onClick={() => (window.location.href = '/invoicing/purchases/invoices')}>
                        {soLeitura ? t('Voltar às compras') : t('Cancelar')}
                    </Botao>
                </div>
            </div>

            </div>
        </div>
    );
}
