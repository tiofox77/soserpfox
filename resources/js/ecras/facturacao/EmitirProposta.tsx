import { Suspense, lazy, useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { emissor, type LinhaCalculada, type LinhaDoEditor, type Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Campo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { EscolhaDaParte } from './EscolhaDaParte';
import { CampoDoArtigo, EscolhaDeArtigo, juntarArtigo, trocarArtigo, useArtigosConhecidos, type ArtigoDaLinha } from './EscolhaDeArtigo';
import { useImprimirAoGravar } from './imprimirAoGravar';
import { eRica, textoDaDescricao } from './descricaoRica';
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

/** O editor formatado pesa: só vem quando se abre a descrição de uma linha. */
const EditorDeDescricao = lazy(() => import('./EditorDeDescricao'));

const LINHA_NOVA: LinhaDoEditor = {
    product_id: null,
    description: '',
    quantity: 1,
    price: 0,
    discount_percent: 0,
};

/** Guardar e ficar, ou guardar e dar por enviada — os dois botões de sempre. */
type Estado = 'draft' | 'sent';

export default function EmitirProposta({ tipo, id, duplicarDe }: { tipo: string; id?: number; duplicarDe?: number }) {
    const [parteId, porParteId] = useState('');
    const [armazemId, porArmazemId] = useState('');
    const [data, porData] = useState(() => new Date().toISOString().slice(0, 10));
    const [validoAte, porValidoAte] = useState('');
    const [regiao, porRegiao] = useState('');
    const [eServico, porEServico] = useState(false);
    /* OS TRÊS DESCONTOS DO DOCUMENTO — ver o cartão «Descontos». */
    const [descontoComercial, porDescontoComercial] = useState('');
    const [descontoLegado, porDescontoLegado] = useState('');
    const [descontoFinanceiro, porDescontoFinanceiro] = useState('');
    const [notas, porNotas] = useState('');
    const [condicoes, porCondicoes] = useState('');
    const [modeloId, porModeloId] = useState('');
    const [campos, porCampos] = useState<Record<string, string>>({});
    const [linhas, porLinhas] = useState<LinhaDoEditor[]>([{ ...LINHA_NOVA }]);
    /** A linha cuja descrição está aberta no editor (proformas de venda e orçamentos). */
    const [descricaoAberta, porDescricaoAberta] = useState<number | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [gravado, porGravado] = useState<{ numero: string; abrir: string; pdf: string; preview: string; mensagem: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['emissor', tipo, 'opcoes'],
        queryFn: () => emissor.opcoes(tipo),
        staleTime: 5 * 60_000,
    });

    /* O PDF abre sozinho ao gravar, se a empresa o pediu — ver `imprimirAoGravar`. */
    const impressao = useImprimirAoGravar(opcoes.data?.imprimir_ao_gravar);
    /** O catálogo carregado (até 500) e os artigos escolhidos pela procura: o que as linhas mostram. */
    const conhecidos = useArtigosConhecidos(opcoes.data?.artigos);

    const aberta =useQuery({ queryKey: ['emissor', tipo, 'abrir', id], queryFn: () => emissor.abrir(tipo, id ?? 0), enabled: id !== undefined });
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
        /* Um zero não se escreve na caixa: fica vazia, como nasceu. */
        porDescontoComercial(d.desconto_comercial ? String(d.desconto_comercial) : '');
        porDescontoLegado(d.desconto_legado ? String(d.desconto_legado) : '');
        porDescontoFinanceiro(d.desconto_financeiro ? String(d.desconto_financeiro) : '');
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
        // As condições de pagamento da empresa, escritas: mudam-se aqui só para este documento.
        porCondicoes(opcoes.data.condicoes_padrao ?? '');
    }, [opcoes.data, id, duplicarDe]);

    const [totais, porTotais] = useState<Totais | null>(null);
    /** As linhas como o servidor as contou: a taxa e o IVA de cada uma, pela ordem das enviadas. */
    const [calculadas, porCalculadas] = useState<LinhaCalculada[]>([]);
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
            porCalculadas([]);
            return;
        }

        porAContar(true);

        const pausa = setTimeout(() => {
            emissor
                // `is_service` vai junto porque muda os totais: uma prestação
                // de serviço retém 6,5% de IRT. Os descontos idem — sem eles o
                // ecrã mostrava um total e o documento gravava outro.
                .calcular(tipo, {
                    linhas: comLinhas,
                    desconto_comercial: Number(descontoComercial) || 0,
                    desconto_legado: Number(descontoLegado) || 0,
                    desconto_financeiro: Number(descontoFinanceiro) || 0,
                    is_service: eServico,
                })
                .then((r) => {
                    if (cancelado) return;
                    porTotais(r.totais);
                    porCalculadas(r.linhas);
                })
                .catch(() => {
                    if (cancelado) return;
                    porTotais(null);
                    porCalculadas([]);
                })
                .finally(() => {
                    if (!cancelado) porAContar(false);
                });
        }, 400);

        return () => {
            cancelado = true;
            clearTimeout(pausa);
        };
    }, [linhas, tipo, eServico, descontoComercial, descontoLegado, descontoFinanceiro]);

    const guardar = useMutation({
        mutationFn: (estado: Estado) => {
            const corpo = {
                parte_id: Number(parteId),
                warehouse_id: Number(armazemId) || null,
                data,
                valido_ate: validoAte || null,
                tax_country_region: regiao || null,
                is_service: eServico,
                desconto_comercial: Number(descontoComercial) || 0,
                desconto_legado: Number(descontoLegado) || 0,
                desconto_financeiro: Number(descontoFinanceiro) || 0,
                // «Guardar alterações» numa proposta que já existe NÃO manda estado: mandar
                // `draft` devolvia a rascunho uma proposta já enviada — o servidor só muda o
                // estado quando o pedido o diz, e só «Guardar e enviar» o deve dizer.
                estado: id !== undefined && estado === 'draft' ? undefined : estado,
                notas: notas || null,
                condicoes: condicoes || null,
                quote_template_id: Number(modeloId) || null,
                campos_proposta: campos,
                linhas: linhas.filter(comConteudo),
            };
            return id !== undefined ? emissor.actualizar(tipo, id, corpo) : emissor.guardar(tipo, corpo);
        },
        onSuccess: (r) => {
            porGravado({ numero: r.numero, abrir: r.abrir, pdf: r.pdf, preview: r.preview, mensagem: r.message });
            porErros({});
            // «Gravar rascunho» não imprime; «Guardar e enviar» sim — é a
            // proposta a sair para a outra parte.
            impressao.depoisDeGravar(r.pdf, r.estado);
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
    const temFisicos = linhas.some((l) => l.product_id !== null && conhecidos.um(l.product_id)?.type !== 'servico');
    const precisaDeArmazem = temFisicos && !eServico;

    /* O modelo escolhido, e os campos livres que ele pede a quem escreve. */
    const modelo = o.modelos.find((m) => String(m.id) === modeloId) ?? null;

    /* Gravado: o ecrã dá o número e sai da frente. */
    if (gravado) {
        return (
            <PainelDeSucesso
                numero={gravado.numero}
                mensagem={gravado.mensagem}
                icone="fa-file-signature"
                aviso={impressao.bloqueado && <PapelBloqueado />}
            >
                {/* O PAPEL — o que os outros ecrãs de sucesso sempre tiveram e
                    este não: quem acabava de gravar uma proposta para a mandar
                    ao cliente tinha de a ir procurar à lista para a imprimir. */}
                <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(gravado.pdf, '_blank', 'noopener')}>
                    {t('PDF')}
                </Botao>
                <Botao icone="fa-eye" onClick={() => window.open(gravado.preview, '_blank', 'noopener')}>
                    {t('Pré-visualizar')}
                </Botao>
                {/* ABRIR O QUE SE ACABOU DE GRAVAR.
                    O servidor sempre devolveu a morada do documento, e o
                    ecrã nunca a usava: quem grava um rascunho para o
                    continuar tinha de ir procurá-lo à lista. */}
                <Botao icone="fa-up-right-from-square" onClick={() => (window.location.href = gravado.abrir)}>
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
                            impressao.esquecer();
                            porLinhas([{ ...LINHA_NOVA }]);
                            porParteId('');
                            porNotas('');
                            porCondicoes(o.condicoes_padrao ?? '');
                            porDescontoComercial('');
                            porDescontoLegado('');
                            porDescontoFinanceiro('');
                            porCampos({});
                        }}
                    >
                        {t('Emitir outro')}
                    </Botao>
                )}
            </PainelDeSucesso>
        );
    }

    /*
     * O ERRO DE CADA LINHA, NA LINHA.
     *
     * O servidor numera as linhas que RECEBEU — as vazias ficam de fora antes
     * de seguir (`comConteudo`) — e por isso o `linhas.1` dele não é
     * forçosamente a segunda linha do ecrã. Traduz-se a posição de volta.
     * Sem isto, uma linha sem artigo só dava «Há campos por corrigir» e
     * ninguém sabia qual.
     */
    const enviadas = linhas.map((l, i) => (comConteudo(l) ? i : -1)).filter((i) => i >= 0);
    const erroDaLinha = (i: number): string[] | undefined => {
        const k = enviadas.indexOf(i);
        if (k < 0) return undefined;

        return erros[`linhas.${k}.product_id`] ?? erros[`linhas.${k}.quantity`] ?? erros[`linhas.${k}.price`];
    };

    /** Trocar o artigo de uma linha pela janela de procura. */
    const escolherArtigo = (i: number, a: ArtigoDaLinha) => {
        conhecidos.lembrar(a);
        // Escolher o artigo resolve o que o servidor apontou nas linhas.
        porErros((e) => Object.fromEntries(Object.entries(e).filter(([k]) => !k.startsWith('linhas.'))));
        porLinhas((ls) => trocarArtigo(ls, i, a));
    };

    const mudarLinha = (i: number, campo: keyof LinhaDoEditor, valor: string) => {
        // Escolher o artigo resolve o que o servidor apontou nas linhas: o
        // vermelho sai logo, sem esperar pela próxima gravação.
        if (campo === 'product_id' && valor) {
            porErros((e) => Object.fromEntries(Object.entries(e).filter(([k]) => !k.startsWith('linhas.'))));
        }

        porLinhas((ls) =>
            ls.map((l, j) => {
                if (j !== i) return l;

                if (campo === 'product_id') {
                    // Escolher o artigo traz o preço dele — que se pode mudar. O
                    // servidor já o resolveu: custo numa proforma de compra.
                    const artigo = o.artigos.find((a) => String(a.id) === valor);

                    return {
                        ...l,
                        product_id: valor ? Number(valor) : null,
                        price: artigo ? artigo.price : l.price,
                        // Uma descrição já escrita no editor não se perde por se trocar o artigo.
                        description: artigo && !eRica(l.description) ? artigo.name : l.description,
                    };
                }

                return { ...l, [campo]: valor };
            }),
        );
    };

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

            {/*
              * DUAS COLUNAS: o formulário à esquerda, o resumo à direita.
              *
              * O ecrã de sempre era assim e é melhor. O resumo é o que se
              * consulta o TEMPO TODO enquanto se lançam linhas — «quanto vai
              * dar isto?» — e em coluna única ficava lá em baixo, fora de
              * vista. Aqui fica colado ao topo e acompanha a página.
              */}
            {/* O resumo numa coluna estreita e fixa: os totais cabem em 20rem, e as linhas precisam da largura. */}
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">

            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo={t('Dados do documento')} icone="fa-circle-info">
                <div className="grid gap-4 sm:grid-cols-2">
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

                    {/*
                      * A REGIÃO FISCAL ESTÁ ESCONDIDA, DE PROPÓSITO — o mesmo
                      * que na factura de venda.
                      *
                      * Fica em automática: o servidor deriva-a da província da
                      * outra parte (`TaxResolver`), que é o que está certo em
                      * quase todos os documentos. Cabinda tem regime próprio,
                      * mas quem trabalha em Luanda não precisa de decidir isso
                      * em cada proposta — e um campo que se deixa sempre como
                      * está é ruído entre os que é preciso preencher.
                      *
                      * O ESTADO E O ENVIO CONTINUAM: `regiao` vai no pedido, e
                      * uma proposta aberta que tenha região escolhida conserva-a.
                      * Só o CONTROLO é que não se desenha — tirar o `false &&`
                      * abaixo volta a mostrá-lo.
                      */}
                    {false && (
                    <Campo etiqueta={t('Região fiscal')} erro={erros.tax_country_region} className="sm:col-span-2">
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => (
                                <option key={r.valor} value={r.valor}>
                                    {r.rotulo}
                                </option>
                            ))}
                        </select>
                    </Campo>
                    )}

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
                </div>
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
                                preco={o.preco}
                                catalogo={o.artigos}
                                aoEscolher={(a) => {
                                    conhecidos.lembrar(a);
                                    porLinhas((ls) => juntarArtigo(ls, { ...LINHA_NOVA }, a));
                                }}
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
                                <th className={cls('w-40 text-right', CELULA_DO_CABECALHO)}>{t('Preço')}</th>
                                <th className={cls('w-24 text-right', CELULA_DO_CABECALHO)}>{t('Desc. %')}</th>
                                <th className={cls('w-32 text-right', CELULA_DO_CABECALHO)}>{t('IVA')}</th>
                                {/* O TOTAL DA LINHA. Sem ele, conferir uma
                                    proposta de vinte linhas obriga a fazer a
                                    conta de cabeça vinte vezes. */}
                                <th className={cls('w-32 text-right', CELULA_DO_CABECALHO)}>{t('Total s/ IVA')}</th>
                                <th className={cls('w-12', CELULA_DO_CABECALHO)}></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => {
                                const artigo = conhecidos.um(l.product_id);
                                const erroAqui = erroDaLinha(i);

                                return (
                                <tr key={i} className={LINHA_DA_TABELA} style={cascata(i)}>
                                    <td className="px-4 py-2 align-top">
                                        <CampoDoArtigo
                                            n={i + 1}
                                            artigo={conhecidos.um(l.product_id, l)}
                                            aoEscolher={(a) => escolherArtigo(i, a)}
                                            preco={o.preco}
                                            catalogo={o.artigos}
                                            invalido={!!erroAqui}
                                        />
                                        {/* O QUE É E EM QUE SE VENDE: o crachá de
                                            produto/serviço e a unidade — é o que
                                            faz reparar numa linha «serviço» com
                                            armazém escolhido. */}
                                        {erroAqui?.[0] && (
                                            <p role="alert" className="mt-1 flex items-start gap-1.5 text-xs font-medium text-red-600 animate-fade-in">
                                                <i className="fas fa-circle-exclamation mt-0.5" aria-hidden="true" />
                                                {erroAqui[0]}
                                            </p>
                                        )}
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
                                                {artigo.code && <span className="font-mono text-slate-400">{artigo.code}</span>}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 align-top">
                                        {o.descricao_rica ? (
                                            <DescricaoDaLinha
                                                texto={l.description}
                                                n={i + 1}
                                                aoAbrir={() => porDescricaoAberta(i)}
                                            />
                                        ) : (
                                            <input
                                                value={l.description}
                                                onChange={(e) => mudarLinha(i, 'description', e.target.value)}
                                                aria-label={t('Descrição da linha :n', { n: i + 1 })}
                                                className={entrada}
                                            />
                                        )}
                                    </td>
                                    <td className="px-4 py-2 align-top">
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
                                    <td className="px-4 py-2 align-top">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={l.price}
                                            onChange={(e) => mudarLinha(i, 'price', e.target.value)}
                                            aria-label={t('Preço da linha :n', { n: i + 1 })}
                                            className={cls(entrada, CAMPO_DE_PRECO)}
                                        />
                                    </td>
                                    <td className="px-4 py-2 align-top">
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

                                    {/* O IVA DA LINHA, como o servidor o contou: o valor e a taxa, ou o motivo da isenção. */}
                                    <td className="px-4 py-2 text-right align-top">
                                        <IvaDaLinha calculada={enviadas.indexOf(i) >= 0 ? calculadas[enviadas.indexOf(i)] : undefined} aContar={aContar} />
                                    </td>

                                    {/* O TOTAL DA LINHA, sem imposto: é o que se
                                        confere contra a lista de preços. */}
                                    <td className="px-4 py-2 text-right align-top">
                                        <span className="flex h-10 items-center justify-end gap-1 whitespace-nowrap font-bold tabular-nums text-slate-900">
                                            {kz(Number(l.quantity || 0) * Number(l.price || 0) * (1 - Number(l.discount_percent || 0) / 100))}
                                            <span className="text-xs font-normal text-slate-400">Kz</span>
                                            </span>
                                    </td>

                                    <td className="px-4 py-2 text-right align-top">
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
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {erros.linhas?.[0] && (
                    <p role="alert" className="border-t border-slate-100 px-4 py-3 text-sm font-medium text-red-600">
                        {erros.linhas[0]}
                    </p>
                )}
            </Cartao>

            {/* OS DESCONTOS DO DOCUMENTO, os três, numa fileira — como no
                ecrã de sempre. Os das LINHAS são outra coisa e ficam lá. */}
            <Cartao titulo={t('Descontos')} icone="fa-tags">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta={t('Desconto comercial (antes IVA)')} erro={erros.desconto_comercial}>
                        <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>

                    {/* O DESCONTO DE SEMPRE.
                        Existe na base desde antes dos dois ao lado e soma ao
                        comercial no cálculo do servidor. Tirá-lo do formulário
                        fazia uma proposta antiga aberta para editar perder o
                        desconto que tinha, calada. */}
                    <Campo
                        etiqueta={t('Desconto (legado)')}
                        erro={erros.desconto_legado}
                        ajuda={t('Campo antigo, mantido para os documentos que o têm. Soma ao comercial.')}
                    >
                        <input type="number" min="0" step="0.01" value={descontoLegado} onChange={(e) => porDescontoLegado(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Desconto financeiro (após IVA)')} erro={erros.desconto_financeiro}>
                        <input type="number" min="0" step="0.01" value={descontoFinanceiro} onChange={(e) => porDescontoFinanceiro(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                    </Campo>
                </div>
            </Cartao>

            <Cartao titulo={t('Observações')} icone="fa-pen">
                <div className="space-y-4">
                    <Campo etiqueta={t('Notas')} erro={erros.notas}>
                        <textarea
                            rows={3}
                            value={notas}
                            onChange={(e) => porNotas(e.target.value)}
                            aria-label={t('Observações')}
                            placeholder={t('Informações adicionais…')}
                            className={cls(entrada, 'h-auto py-2')}
                        />
                    </Campo>

                    {/* AS CONDIÇÕES SAEM NO DOCUMENTO — prazos de
                        pagamento, garantias. Não são notas internas. Nas
                        vendas nascem com as da empresa e saem no rodapé. */}
                    <Campo
                        etiqueta={o.condicoes_padrao !== undefined && o.preco === 'venda' ? t('Condições e políticas de pagamento') : t('Termos e Condições')}
                        erro={erros.condicoes}
                        ajuda={o.preco === 'venda' ? t('Saem no rodapé do documento. Vêm das condições da empresa (Definições › Textos por omissão) e podem mudar-se só para este.') : undefined}
                    >
                        <textarea
                            rows={o.preco === 'venda' ? 8 : 3}
                            value={condicoes}
                            onChange={(e) => porCondicoes(e.target.value)}
                            placeholder={t('Condições de pagamento, garantias, etc.')}
                            className={cls(entrada, 'h-auto py-2 leading-relaxed')}
                        />
                    </Campo>
                    {!soLeitura && o.condicoes_padrao && condicoes !== o.condicoes_padrao && (
                        <div className="-mt-2 flex justify-end">
                            <Botao altura="pequeno" icone="fa-rotate-left" onClick={() => porCondicoes(o.condicoes_padrao ?? '')}>
                                {t('Repor as da empresa')}
                            </Botao>
                        </div>
                    )}
                </div>
            </Cartao>
            </fieldset>

            {/*
              * O RESUMO — um cartão só, colado ao topo.
              *
              * Tudo o que responde a «quanto vai dar isto» está aqui dentro e
              * em mais lado nenhum: a natureza do documento, as parcelas e o
              * total. Os TOTAIS SÃO OS DO SERVIDOR — este bloco não calcula.
              */}
            <div className="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <CartaoDeTotais titulo={t('Resumo')} aContar={aContar}>
                    {/* PRESTAÇÃO DE SERVIÇO: retém-se IRT a 6,5% e o armazém
                        deixa de fazer falta. Vive no resumo porque é aqui que
                        se vê o que ela faz ao total. O fieldset é próprio: os
                        botões abaixo têm de continuar a funcionar num
                        documento só de leitura. */}
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
                                {t('É Prestação de Serviço (IRT 6.5%)')}
                            </span>
                        </label>
                    </fieldset>

                    {totais ? (
                        <>
                            <dl className={cls('px-5 pt-3', aContar && 'opacity-60')}>
                                <ParcelaDoTotal rotulo={t('Valor bruto')} valor={kz(totais.bruto)} />
                                {totais.desconto_por_linha > 0 && (
                                    <ParcelaDoTotal rotulo={t('Desconto nas linhas')} valor={kz(-totais.desconto_por_linha)} icone="fa-scissors" />
                                )}
                                {totais.desconto_comercial > 0 && (
                                    <ParcelaDoTotal rotulo={t('Desconto comercial')} valor={kz(-totais.desconto_comercial)} icone="fa-scissors" />
                                )}
                                <ParcelaDoTotal rotulo={t('Valor líquido')} valor={kz(totais.liquido)} />
                                <ParcelaDoTotal rotulo={t('Incidência de IVA')} valor={kz(totais.base)} />
                                <ParcelaDoTotal rotulo={t('Imposto')} valor={kz(totais.imposto)} icone="fa-percent" realce="imposto" />
                                {Number(descontoFinanceiro) > 0 && (
                                    <ParcelaDoTotal rotulo={t('Desconto financeiro')} valor={kz(-Number(descontoFinanceiro))} icone="fa-scissors" />
                                )}
                                {totais.retencao > 0 && (
                                    <ParcelaDoTotal rotulo={t('Retenção')} valor={kz(-totais.retencao)} icone="fa-hand-holding-dollar" realce="retencao" />
                                )}
                            </dl>

                            <TotalGrande
                                rotulo={t('Total')}
                                valor={<>{kz(totais.total)} <span className="text-base font-normal text-emerald-800/60">Kz</span></>}
                                nota={t('Contado no servidor — é o mesmo cálculo que vai para o documento.')}
                            />

                            {/* A BASE E A LEI QUE MANDA NA CONTA — é o que um
                                contabilista procura para conferir, e é o que se
                                responde a quem pergunta de onde saiu o número. */}
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
                        <SemNada icone="fa-calculator">
                            {t('Escolha um artigo e uma quantidade para ver os totais.')}
                        </SemNada>
                    )}
                </CartaoDeTotais>

                {/*
                  * OS BOTÕES, debaixo do resumo e um por linha.
                  *
                  * São decisões diferentes, e o ecrã de sempre tinha as duas:
                  * GUARDAR deixa a proposta em rascunho para se acabar depois;
                  * GUARDAR E ENVIAR diz que ela saiu para a outra parte. Uma
                  * proposta enviada ainda se corrige — não é documento fiscal.
                  *
                  * Fora do fieldset: num documento só de leitura os campos
                  * fecham-se, mas voltar à lista tem de continuar a funcionar.
                  */}
                <div className={cls(CARTAO, 'space-y-3 p-5')}>
                    {!soLeitura && (
                        <Botao
                            className="w-full"
                            icone="fa-file"
                            aTrabalhar={guardar.isPending && guardar.variables === 'draft'}
                            disabled={!o.permissoes.pode_criar && id === undefined}
                            onClick={() => guardar.mutate('draft')}
                        >
                            {id !== undefined ? t('Guardar alterações') : t('Gravar rascunho')}
                        </Botao>
                    )}

                    {!soLeitura && (
                        <Botao
                            className="w-full"
                            cor="primaria"
                            tom="solida"
                            altura="grande"
                            icone="fa-paper-plane"
                            aTrabalhar={guardar.isPending && guardar.variables === 'sent'}
                            disabled={!o.permissoes.pode_criar && id === undefined}
                            onClick={() => guardar.mutate('sent')}
                        >
                            {t('Guardar e enviar')}
                        </Botao>
                    )}

                    <Botao className="w-full" icone="fa-arrow-left" onClick={() => (window.location.href = o.rota)}>
                        {soLeitura ? t('Voltar à lista') : t('Cancelar')}
                    </Botao>
                </div>
            </div>

            </div>

            {descricaoAberta !== null && linhas[descricaoAberta] && (
                <Suspense fallback={null}>
                    <EditorDeDescricao
                        artigo={conhecidos.um(linhas[descricaoAberta]!.product_id)?.name ?? t('Linha :n', { n: descricaoAberta + 1 })}
                        valor={linhas[descricaoAberta]!.description}
                        aoGuardar={(html) => {
                            mudarLinha(descricaoAberta, 'description', html);
                            porDescricaoAberta(null);
                        }}
                        aoFechar={() => porDescricaoAberta(null)}
                    />
                </Suspense>
            )}
        </div>
    );
}

/**
 * O IVA DE UMA LINHA: o valor, e por baixo a taxa — ou «Isento» com o código
 * da isenção (o motivo inteiro no título). Vem do `/calcular`, a mesma conta
 * que vai para o documento; enquanto o servidor conta, fica esbatido.
 */
function IvaDaLinha({ calculada, aContar }: { calculada: LinhaCalculada | undefined; aContar: boolean }) {
    if (!calculada) {
        return <span className="flex h-10 items-center justify-end text-slate-300">—</span>;
    }

    const isento = calculada.tax_rate <= 0;

    return (
        <span
            className={cls('flex h-10 flex-col items-end justify-center leading-tight transition-opacity', aContar && 'opacity-50')}
            title={isento ? (calculada.exemption_reason ?? undefined) : undefined}
        >
            <span className="font-semibold tabular-nums text-slate-800">{kz(calculada.imposto)}</span>
            <span className={cls('mt-0.5 rounded-full px-1.5 text-[10px] font-semibold', isento ? 'bg-amber-100 text-amber-800' : 'bg-indigo-50 text-indigo-700')}>
                {isento
                    ? `${t('Isento')}${calculada.exemption_code ? ` · ${calculada.exemption_code}` : ''}`
                    : `${t('IVA')} ${String(calculada.tax_rate).replace('.', ',')}%`}
            </span>
        </span>
    );
}

/**
 * A DESCRIÇÃO NA LINHA: o começo do texto e um clique para o editor. Nas
 * proformas de venda e nos orçamentos a descrição é o texto da proposta, e
 * não cabia numa caixa de uma linha cortada a meio.
 */
function DescricaoDaLinha({ texto, n, aoAbrir }: { texto: string; n: number; aoAbrir: () => void }) {
    const simples = textoDaDescricao(texto);

    return (
        <button
            type="button"
            onClick={aoAbrir}
            aria-label={t('Descrição da linha :n', { n })}
            className={cls(
                'group flex min-h-10 w-full min-w-[12rem] items-start gap-2 border border-dashed border-slate-300 bg-white px-3 py-2 text-left text-sm',
                'transition-colors duration-200 hover:border-indigo-400 hover:bg-indigo-50/40 disabled:cursor-default disabled:hover:bg-white',
                RAIO,
                FOCO,
            )}
        >
            <span className={cls('line-clamp-2 flex-1 whitespace-pre-line', simples ? 'text-slate-700' : 'text-slate-400')}>
                {simples || t('Escrever a descrição…')}
            </span>
            <i className="fas fa-pen-to-square mt-0.5 text-indigo-500 opacity-70 group-hover:opacity-100" aria-hidden="true" />
        </button>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <NaoAbriu titulo={t('Não foi possível abrir o emissor')} mensagem={daApi?.message ?? t('Verifique a ligação.')} />
    );
}
