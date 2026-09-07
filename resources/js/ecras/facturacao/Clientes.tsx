import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    clientes,
    type Cliente,
    type ClienteParaGravar,
    type FiltrosDeClientes,
    type OpcoesDosClientes,
    type ResumoDosClientes,
} from '@/api/clientes';
import { ErroDaApi } from '@/api/cliente';
import { etiquetaIntl, t } from '@/i18n';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Modal } from '@/ui/Modal';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { CARTAO, FOCO, GRADIENTES, RAIO, cls } from '@/ui/tokens';
import { ACCAO_DA_FAIXA, Faixa } from './faixa';

/**
 * OS CLIENTES — o primeiro ecrã que também ESCREVE.
 *
 * A lista provou a leitura. Aqui prova-se o resto: gravar, ver os erros do
 * servidor no campo certo, e apagar sem deixar um documento fiscal órfão.
 *
 * QUEM DECIDE CONTINUA A SER O SERVIDOR. As permissões vêm nas opções e o
 * `pode_apagar` de cada linha vem contado de lá — este ecrã esconde botões,
 * o que é conveniência e nunca segurança.
 */

const VAZIO: ClienteParaGravar = {
    type: 'pessoa_juridica',
    name: '',
    nif: '',
    email: '',
    phone: '',
    mobile: '',
    payment_term_id: null,
    address: '',
    city: '',
    province: '',
    municipality: '',
    neighbourhood: '',
    postal_code: '',
    country: 'AO',
    portal_access: false,
    portal_password: '',
    portal_repor_senha: false,
};

/** O país que manda na morada: só em Angola há divisão para escolher. */
const ANGOLA = 'AO';

export default function Clientes() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDeClientes>({ procura: '', tipo: '', page: 1 });
    const [aEditar, porAEditar] = useState<Cliente | null>(null);
    const [formulario, porFormulario] = useState<ClienteParaGravar | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Cliente | null>(null);
    const [recado, porRecado] = useState<string>('');
    /** O ficheiro escolhido no formulário — sobe DEPOIS da ficha gravar. */
    const [logotipo, porLogotipo] = useState<File | null>(null);

    const opcoes = useQuery({
        queryKey: ['clientes', 'opcoes'],
        queryFn: clientes.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['clientes', filtros],
        queryFn: () => clientes.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const gravar = useMutation({
        /*
         * DUAS VIAGENS, E POR ESTA ORDEM: a ficha primeiro, o logótipo a
         * seguir. Um ficheiro não cabe em JSON, e ao CRIAR nem sequer há
         * ainda `id` nem pasta onde o pôr — só depois de a ficha existir.
         *
         * SE A IMAGEM FALHAR, O QUE SE PERDEU FOI A IMAGEM. Rebentar a
         * gravação inteira por causa dela mandava reescrever a ficha toda,
         * que é a parte que já estava certa — é a mesma regra do ecrã dos
         * artigos.
         */
        mutationFn: async (dados: ClienteParaGravar) => {
            const resposta = aEditar ? await clientes.guardar(aEditar.id, dados) : await clientes.criar(dados);

            if (!logotipo) {
                return { cliente: resposta.data, avisoDoLogotipo: '' };
            }

            try {
                const comLogo = await clientes.logotipo(resposta.data.id, logotipo);

                return { cliente: comLogo.data, avisoDoLogotipo: '' };
            } catch (e) {
                return {
                    cliente: resposta.data,
                    avisoDoLogotipo:
                        e instanceof ErroDaApi
                            ? (e.erros.logotipo?.[0] ?? e.message)
                            : t('Não foi possível enviar o logótipo.'),
                };
            }
        },
        onSuccess: ({ avisoDoLogotipo }) => {
            void cache.invalidateQueries({ queryKey: ['clientes'] });
            porFormulario(null);
            porAEditar(null);
            porErros({});
            porLogotipo(null);

            const feito = aEditar ? t('Cliente guardado.') : t('Cliente criado.');

            porRecado(
                avisoDoLogotipo
                    ? t(':feito, mas o logótipo ficou por enviar: :aviso', { feito, aviso: avisoDoLogotipo })
                    : feito,
            );
        },
        onError: (e) => {
            // Os erros do servidor vão para o campo a que pertencem. Um
            // «não foi possível gravar» sem dizer onde obriga a adivinhar.
            porErros(e instanceof ErroDaApi ? e.erros : {});
        },
    });

    /**
     * TIRAR O LOGÓTIPO QUE JÁ LÁ ESTÁ é uma ordem à parte, e imediata.
     *
     * Não espera pelo «Guardar»: o ficheiro está no disco e a coluna aponta
     * para ele — quem carrega em «Remover» quer vê-lo desaparecer, e não
     * descobrir que continuava lá porque fechou o modal sem gravar.
     */
    const removerLogotipo = useMutation({
        mutationFn: (c: Cliente) => clientes.apagarLogotipo(c.id),
        onSuccess: ({ data }) => {
            void cache.invalidateQueries({ queryKey: ['clientes'] });
            porAEditar(data);
            porLogotipo(null);
        },
    });

    const apagar = useMutation({
        mutationFn: (c: Cliente) => clientes.apagar(c.id),
        onSuccess: () => {
            void cache.invalidateQueries({ queryKey: ['clientes'] });
            porAApagar(null);
            porRecado(t('Cliente apagado.'));
        },
    });

    const permissoes = opcoes.data?.permissoes;

    function abrirNovo() {
        porAEditar(null);
        porErros({});
        porLogotipo(null);
        porFormulario({
            ...VAZIO,
            country: opcoes.data?.pais_padrao ?? 'AO',
            /*
             * UM CLIENTE NOVO NASCE COM A CONDIÇÃO PADRÃO DA EMPRESA — é o que
             * o formulário de sempre pré-seleccionava. O modelo também a põe
             * a quem vier sem nenhuma, mas mostrá-la aqui deixa a pessoa
             * TROCÁ-LA antes de gravar, em vez de descobrir o prazo depois,
             * na primeira factura.
             */
            payment_term_id: opcoes.data?.condicoes_pagamento.find((c) => c.padrao)?.id ?? null,
        });
    }

    function abrirEdicao(c: Cliente) {
        porAEditar(c);
        porErros({});
        porLogotipo(null);
        porFormulario({
            type: c.type,
            name: c.name,
            nif: c.nif,
            email: c.email ?? '',
            phone: c.phone ?? '',
            mobile: c.mobile ?? '',
            payment_term_id: c.payment_term_id,
            address: c.address ?? '',
            city: c.city ?? '',
            province: c.province ?? '',
            municipality: c.municipality ?? '',
            neighbourhood: c.neighbourhood ?? '',
            postal_code: c.postal_code ?? '',
            country: c.country,
            // O acesso vem como está; a senha NUNCA vem do servidor e o
            // formulário abre sempre sem ela. Guardar a ficha não pode
            // trocar a senha de quem já entra no portal.
            portal_access: c.portal_access,
            portal_password: '',
            portal_repor_senha: false,
        });
    }

    if (lista.isError) {
        return <Falhou erro={lista.error} />;
    }

    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    return (
        <div className="space-y-4">
            {/* O CABEÇALHO DE SEMPRE — a faixa verde com o ícone no quadrado
                translúcido e o botão principal à direita. Era assim em Blade, e
                a migração deixou a página a começar por uma caixa branca. */}
            <Faixa
                titulo={t('Clientes')}
                subtitulo={t('Gerir clientes')}
                icone="fa-users"
                cor="bom"
                accoes={
                    permissoes?.pode_criar && (
                        <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i
                                className="fas fa-plus transition-transform duration-300 group-hover:rotate-90"
                                aria-hidden="true"
                            />
                            {t('Novo Cliente')}
                        </button>
                    )
                }
            />

            {recado && (
                <div
                    role="status"
                    className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}
                >
                    <span>
                        <i className="fas fa-circle-check mr-2" aria-hidden="true" />
                        {recado}
                    </span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar aviso')}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            {/* OS CARTÕES DO TOPO, como o ecrã em Blade tinha — e a contarem o
                conjunto FILTRADO INTEIRO, que é o que o de Blade fazia. */}
            <Cartoes
                resumo={lista.data?.resumo}
                tipos={opcoes.data?.tipos}
                aActualizar={lista.isFetching}
            />

            {/* Sem botão de criar aqui: ele vive NA FAIXA, como no ecrã de
                sempre — dois botões iguais na mesma página não ajudam. */}
            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block lg:col-span-2">
                        <Rotulo>{t('Procurar')}</Rotulo>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Nome, NIF, email ou telefone')}
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <Rotulo>{t('Tipo')}</Rotulo>
                        <select
                            value={filtros.tipo ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            {/* Os rótulos dos tipos vêm traduzidos do servidor. */}
                            {opcoes.data?.tipos.map((tipo) => (
                                <option key={tipo.valor} value={tipo.valor}>
                                    {tipo.rotulo}
                                </option>
                            ))}
                        </select>
                    </label>

                    {/* A PROVÍNCIA já era aceite pela API desde o primeiro dia e
                        nunca teve por onde se escolher. Com clientes espalhados
                        por Angola, é o corte mais útil desta lista. */}
                    <label className="block">
                        <Rotulo>{t('Província')}</Rotulo>
                        <select
                            value={filtros.provincia ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, provincia: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todas')}</option>
                            {opcoes.data?.provincias.map((p) => (
                                <option key={p} value={p}>
                                    {p}
                                </option>
                            ))}
                        </select>
                    </label>

                    {/* A CIDADE escrita à mão, como o `cityFilter` do ecrã de
                        sempre: procura por dentro, para «Luanda» apanhar
                        «Luanda Sul». */}
                    <label className="block">
                        <Rotulo>{t('Cidade')}</Rotulo>
                        <input
                            type="search"
                            value={filtros.cidade ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, cidade: e.target.value, page: 1 }))}
                            placeholder={t('Ex.: Luanda')}
                            className={entrada}
                        />
                    </label>

                    <IntervaloDeDatas
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(campo, valor) => porFiltros((f) => ({ ...f, [campo]: valor, page: 1 }))}
                    />
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas
                            ? t(':quantos cliente(s)', { quantos: contas.total.toLocaleString('pt-PT') })
                            : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <PorPagina
                            valor={filtros.por_pagina}
                            aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                        />
                        <Botao
                            altura="pequeno"
                            icone="fa-eraser"
                            onClick={() => porFiltros({ procura: '', tipo: '', page: 1 })}
                        >
                            {t('Limpar')}
                        </Botao>
                    </div>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <EstadoVazio
                    icone="fa-users"
                    titulo={t('Nenhum cliente com esta procura')}
                    frase={
                        permissoes?.pode_criar
                            ? t('Limpe a procura, ou crie o primeiro cliente.')
                            : t('Limpe a procura para ver mais.')
                    }
                    accao={
                        permissoes?.pode_criar && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                                {t('Novo cliente')}
                            </Botao>
                        )
                    }
                />
            ) : (
                <Cartao semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            {/* O cabeçalho de sempre: fundo cinzento claro,
                                maiúsculas pequenas e um ícone por coluna — todos
                                no mesmo tom, que no Blade cada um tinha a sua cor
                                e seis cores num cabeçalho não ajudam a encontrar
                                nada. */}
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <Cabecalho icone="fa-user">{t('Nome')}</Cabecalho>
                                    <Cabecalho icone="fa-id-card">{t('NIF')}</Cabecalho>
                                    <Cabecalho icone="fa-tag">{t('Tipo')}</Cabecalho>
                                    <Cabecalho icone="fa-envelope">{t('Contacto')}</Cabecalho>
                                    <Cabecalho icone="fa-location-dot">{t('Morada')}</Cabecalho>
                                    <Cabecalho icone="fa-file-lines" direita>{t('Documentos')}</Cabecalho>
                                    <Cabecalho icone="fa-gear" direita>{t('Acções')}</Cabecalho>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((c, i) => (
                                    <tr
                                        key={c.id}
                                        className="entra transition-all duration-200 hover:bg-indigo-50/60"
                                        style={cascata(i)}
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {/* A MEDALHA COM AS INICIAIS, como o ecrã em
                                                    Blade tinha. Fica fora da árvore de
                                                    acessibilidade: o nome está escrito ao
                                                    lado, e «GA» lido em voz alta não
                                                    acrescenta nada. */}
                                                <Medalha nome={c.name} logo={c.logo} />
                                                <div className="min-w-0">
                                                    <span className="font-semibold text-slate-800">{c.name}</span>
                                                    {/* A CONDIÇÃO DE PAGAMENTO à vista, debaixo do
                                                        nome: é dela que sai o vencimento das
                                                        facturas deste cliente, e ver «30 dias» na
                                                        lista poupa abrir a ficha para o saber. Vai
                                                        aqui, e não numa coluna nova, para a tabela
                                                        não engordar mais uma. */}
                                                    {c.condicao_pagamento && (
                                                        <span className="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                                                            <i className="fas fa-calendar-check" aria-hidden="true" />
                                                            {c.condicao_pagamento}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-600">{c.nif}</td>
                                        <td className="px-4 py-3">
                                            <Etiqueta
                                                cor={c.type === 'pessoa_fisica' ? 'neutra' : 'primaria'}
                                                icone={c.type === 'pessoa_fisica' ? 'fa-user' : 'fa-building'}
                                            >
                                                {c.tipo_rotulo}
                                            </Etiqueta>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {c.email && <div>{c.email}</div>}
                                            {(c.phone || c.mobile) && (
                                                <div className="text-xs text-slate-400">{c.phone || c.mobile}</div>
                                            )}
                                            {!c.email && !c.phone && !c.mobile && <span className="text-slate-300">—</span>}
                                            {/* Quem já entra no portal do cliente. */}
                                            {c.portal_access && (
                                                <div className="mt-1 text-xs font-semibold text-indigo-600">
                                                    <i className="fas fa-user-lock mr-1" aria-hidden="true" />
                                                    {t('Portal activo')}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {[c.city, c.province].filter(Boolean).join(', ') || (
                                                <span className="text-slate-300">—</span>
                                            )}
                                            {c.pais_nome && <div className="text-xs text-slate-400">{c.pais_nome}</div>}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-slate-700">
                                            {c.documentos}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {permissoes?.pode_editar && (
                                                    <button
                                                        type="button"
                                                        onClick={() => abrirEdicao(c)}
                                                        title={t('Editar')}
                                                        aria-label={t('Editar :nome', { nome: c.name })}
                                                        className={cls('p-2 text-slate-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-slate-100', RAIO, FOCO)}
                                                    >
                                                        <i className="fas fa-pen" aria-hidden="true" />
                                                    </button>
                                                )}

                                                {/* Um cliente com documentos não se apaga: a factura
                                                    aponta para ele. Fica apagado e diz porquê. */}
                                                {permissoes?.pode_apagar &&
                                                    (c.pode_apagar ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => porAApagar(c)}
                                                            title={t('Apagar')}
                                                            aria-label={t('Apagar :nome', { nome: c.name })}
                                                            className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-red-50', RAIO, FOCO)}
                                                        >
                                                            <i className="fas fa-trash" aria-hidden="true" />
                                                        </button>
                                                    ) : (
                                                        <span
                                                            title={t(
                                                                'Tem :quantos documento(s) — um documento fiscal não pode ficar sem cliente',
                                                                { quantos: c.documentos },
                                                            )}
                                                            aria-disabled="true"
                                                            className={cls('cursor-not-allowed p-2 text-slate-300', RAIO)}
                                                        >
                                                            <i className="fas fa-trash" aria-hidden="true" />
                                                        </span>
                                                    ))}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label={t('Páginas')}>
                    <Botao
                        icone="fa-chevron-left"
                        altura="pequeno"
                        disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}
                    >
                        {t('Anterior')}
                    </Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        {t('Página :actual de :total', {
                            actual: contas.current_page,
                            total: contas.last_page,
                        })}
                    </span>
                    <Botao
                        icone="fa-chevron-right"
                        altura="pequeno"
                        disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}
                    >
                        {t('Seguinte')}
                    </Botao>
                </nav>
            )}

            <Formulario
                dados={formulario}
                aEditar={aEditar}
                erros={erros}
                aGravar={gravar.isPending}
                erroDeGravar={gravar.error}
                opcoes={opcoes.data}
                logotipo={logotipo}
                aRemoverLogotipo={removerLogotipo.isPending}
                aoEscolherLogotipo={porLogotipo}
                aoRemoverLogotipo={() => aEditar && removerLogotipo.mutate(aEditar)}
                aoMudar={porFormulario}
                aoFechar={() => {
                    porFormulario(null);
                    porAEditar(null);
                    porErros({});
                    porLogotipo(null);
                }}
                aoGravar={(d) => gravar.mutate(d)}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar cliente')}
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}
                        >
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('Apagar')} <strong>{aApagar?.name}</strong>? {t('Deixa de aparecer nas listas.')}
                </p>
                {apagar.isError && (
                    <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">
                        {apagar.error instanceof ErroDaApi
                            ? apagar.error.message
                            : t('Não foi possível apagar.')}
                    </p>
                )}
            </Modal>
        </div>
    );
}

/* ─── O aspecto da lista ──────────────────────────────────────────────── */

/**
 * A ENTRADA EM CASCATA das linhas.
 *
 * O `--i` é o atraso da linha; a animação `entra` está no layout, com a guarda
 * de `prefers-reduced-motion`. O índice tem tecto: com 100 linhas por página,
 * 22ms cada dava dois segundos a ver a tabela a montar-se, que é o contrário
 * do que a cascata serve.
 */
function cascata(i: number): React.CSSProperties {
    return { '--i': Math.min(i, 12) } as React.CSSProperties;
}

/** Uma coluna do cabeçalho: o rótulo com o seu ícone, sempre no mesmo tom. */
function Cabecalho({
    icone,
    direita = false,
    children,
}: {
    icone: string;
    direita?: boolean;
    children: React.ReactNode;
}) {
    return (
        <th className={cls('px-4 py-3 font-bold', direita && 'text-right')}>
            <i className={`fas ${icone} mr-1.5 text-slate-400`} aria-hidden="true" />
            {children}
        </th>
    );
}

/**
 * O círculo com as duas primeiras letras, como no ecrã de sempre — ou o
 * LOGÓTIPO do cliente, se ele tiver um. Quem se dá ao trabalho de carregar o
 * logótipo quer vê-lo, e é por ele que se encontra a linha numa lista longa.
 */
function Medalha({ nome, logo }: { nome: string; logo?: string | null }) {
    if (logo) {
        return (
            <img
                src={logo}
                alt=""
                aria-hidden="true"
                loading="lazy"
                className="h-10 w-10 flex-none rounded-full object-cover shadow-sm ring-2 ring-white transition-transform duration-200 hover:scale-110"
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className={cls(
                'grid h-10 w-10 flex-none place-items-center rounded-full text-xs font-bold text-white shadow-sm transition-transform duration-200 hover:scale-110',
                GRADIENTES.primaria,
            )}
        >
            {nome.slice(0, 2).toUpperCase()}
        </span>
    );
}

/**
 * O ESTADO VAZIO COM DESENHO — o círculo de 80px com o ícone lá dentro, como o
 * ecrã em Blade tinha. Uma linha de texto solta numa caixa branca lê-se como um
 * erro de carregamento; isto lê-se como uma resposta, e diz o que fazer a
 * seguir.
 */
function EstadoVazio({
    icone,
    titulo,
    frase,
    accao,
}: {
    icone: string;
    titulo: string;
    frase?: string;
    accao?: React.ReactNode;
}) {
    return (
        <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
            <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                <i className={`fas ${icone} text-4xl text-slate-300`} aria-hidden="true" />
            </div>
            <p className="text-lg font-bold text-slate-800">{titulo}</p>
            {frase && <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{frase}</p>}
            {accao && <div className="mt-5 flex justify-center">{accao}</div>}
        </div>
    );
}

/**
 * OS CARTÕES DE NÚMERO DO TOPO — os do ecrã em Blade, de volta.
 *
 * Eram três (total, pessoas jurídicas, pessoas físicas), BRANCOS, com o ícone
 * num quadrado de gradiente que levanta quando se aponta o cartão, o número
 * grande a preto e o rótulo na cor do ícone. Não eram cartões de gradiente
 * cheio: aqui o assunto é o NÚMERO, e um 214 a branco sobre roxo lê-se pior do
 * que a preto sobre branco.
 *
 * E CONTAM O CONJUNTO INTEIRO, não a página. O primeiro corte desta migração
 * pôs os cartões a contarem as quinze linhas à vista e a dizê-lo em letra
 * pequena — um resumo que muda ao virar a página não resume nada. Os números
 * vêm agora do servidor, sobre a mesma consulta filtrada.
 */
function Cartoes({
    resumo,
    tipos,
    aActualizar,
}: {
    resumo?: ResumoDosClientes;
    tipos?: Array<{ valor: string; rotulo: string }>;
    aActualizar: boolean;
}) {
    const rotulo = (valor: string, omissao: string) => tipos?.find((x) => x.valor === valor)?.rotulo ?? omissao;
    const conta = (n?: number) => (n === undefined ? '—' : n.toLocaleString(etiquetaIntl()));

    return (
        <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', aActualizar && 'opacity-70')}>
            <CartaoNumero
                aspecto="claro"
                rotulo={t('Total Clientes')}
                tom="verde"
                icone="fa-users"
                nota={t('com os filtros actuais')}
                valor={conta(resumo?.total)}
            />
            <CartaoNumero
                aspecto="claro"
                rotulo={rotulo('pessoa_juridica', t('Pessoa Jurídica'))}
                tom="azul"
                icone="fa-building"
                nota={t('Empresas')}
                valor={conta(resumo?.juridicas)}
            />
            <CartaoNumero
                aspecto="claro"
                rotulo={rotulo('pessoa_fisica', t('Pessoa Física'))}
                tom="roxo"
                icone="fa-user"
                nota={t('Particulares')}
                valor={conta(resumo?.fisicas)}
            />
            {/* Quem entra no portal do cliente — é o que a lista assinala linha
                a linha, contado à cabeça. */}
            <CartaoNumero
                aspecto="claro"
                rotulo={t('Portal activo')}
                tom="indigo"
                icone="fa-user-lock"
                nota={t('Com acesso ao portal')}
                valor={conta(resumo?.com_portal)}
            />
        </div>
    );
}

/* ─── As peças do formulário ──────────────────────────────────────────── */

/**
 * A CONDIÇÃO DE PAGAMENTO DO CLIENTE.
 *
 * É daqui que sai o VENCIMENTO das facturas dele: o catálogo é por empresa e
 * cada condição tem os seus dias. A migração para React deixou o campo cair, e
 * sem ele todo o cliente novo ficava com a condição padrão sem hipótese de a
 * trocar — o prazo só se descobria na primeira factura.
 *
 * O atalho para gerir o catálogo só aparece a quem pode abrir as definições,
 * como o `@can` do ecrã de sempre fazia.
 */
function CondicaoDePagamento({
    dados,
    erros,
    opcoes,
    aoMudar,
}: {
    dados: ClienteParaGravar;
    erros: Record<string, string[]>;
    opcoes: OpcoesDosClientes | undefined;
    aoMudar: (d: ClienteParaGravar) => void;
}) {
    const condicoes = opcoes?.condicoes_pagamento ?? [];

    return (
        // O ATALHO FICA FORA DO `Campo`, que é um `<label>`: um link dentro de
        // um rótulo rouba-lhe o clique — carregar em «Gerir» abria a página E
        // punha o cursor no select.
        <div className="sm:col-span-2">
            <Campo etiqueta={t('Condição de Pagamento')} erro={erros.payment_term_id}>
                <select
                    value={dados.payment_term_id === null ? '' : String(dados.payment_term_id)}
                    onChange={(e) =>
                        aoMudar({ ...dados, payment_term_id: e.target.value ? Number(e.target.value) : null })
                    }
                    className={entrada}
                >
                    <option value="">{t('— Sem condição —')}</option>
                    {condicoes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.nome}
                            {c.dias > 0 ? ` (${t(':n dias', { n: c.dias })})` : ''}
                        </option>
                    ))}
                </select>
            </Campo>

            {opcoes?.pode_gerir_condicoes && (
                <a
                    href={opcoes.url_condicoes}
                    target="_blank"
                    rel="noreferrer"
                    className="group mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:text-indigo-800 hover:underline"
                >
                    <i
                        className="fas fa-gear transition-transform duration-500 group-hover:rotate-180"
                        aria-hidden="true"
                    />
                    {t('Gerir condições de pagamento')}
                </a>
            )}
        </div>
    );
}

/**
 * O LOGÓTIPO DO CLIENTE.
 *
 * O que está gravado à esquerda, o que se acabou de escolher à direita — em
 * verde, como o ecrã de sempre assinalava a imagem nova. O ficheiro sobe
 * DEPOIS de a ficha gravar (ao criar não há ainda `id` nem pasta), e por isso
 * aqui só se escolhe: quem grava é o `gravar` lá em cima.
 */
function Logotipo({
    actual,
    escolhido,
    erro,
    aRemover,
    aoEscolher,
    aoRemover,
}: {
    actual: string | null;
    escolhido: File | null;
    erro?: string[];
    aRemover: boolean;
    aoEscolher: (f: File | null) => void;
    aoRemover?: () => void;
}) {
    // A pré-visualização é do ficheiro em memória: não passa pelo servidor, e
    // some quando o ficheiro muda.
    const espreitar = escolhido ? URL.createObjectURL(escolhido) : null;

    return (
        // TAMBÉM FORA DO `Campo`: aqui há botões, e um botão dentro de um
        // `<label>` faz o clique cair no `<input type="file">` — carregar em
        // «Remover» abria o explorador de ficheiros.
        <div className="sm:col-span-2">
            <Rotulo>{t('Logótipo')}</Rotulo>

            <div className="flex flex-wrap items-center gap-4">
                {actual && !espreitar && (
                    <figure className="animate-fade-in text-center">
                        <img
                            src={actual}
                            alt={t('Logótipo actual')}
                            className="h-20 w-20 rounded-xl object-cover shadow-md ring-1 ring-slate-200"
                        />
                        <figcaption className="mt-1 text-[11px] text-slate-400">{t('Actual')}</figcaption>
                    </figure>
                )}

                {espreitar && (
                    <figure className="animate-scale-in text-center">
                        <img
                            src={espreitar}
                            alt={t('Nova imagem')}
                            className="h-20 w-20 rounded-xl object-cover shadow-lg ring-2 ring-emerald-400"
                        />
                        <figcaption className="mt-1 text-[11px] font-semibold text-emerald-600">
                            <i className="fas fa-circle-check mr-1" aria-hidden="true" />
                            {t('Nova')}
                        </figcaption>
                    </figure>
                )}

                <div className="min-w-[12rem] flex-1 space-y-2">
                    <input
                        type="file"
                        accept="image/*"
                        aria-label={t('Logótipo')}
                        onChange={(e) => aoEscolher(e.target.files?.[0] ?? null)}
                        className={cls(
                            'w-full text-sm text-slate-600 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0',
                            'file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700',
                            'file:transition-colors hover:file:bg-indigo-100',
                        )}
                    />

                    <div className="flex flex-wrap gap-2">
                        {escolhido && (
                            <Botao altura="pequeno" icone="fa-rotate-left" onClick={() => aoEscolher(null)}>
                                {t('Cancelar escolha')}
                            </Botao>
                        )}
                        {aoRemover && !escolhido && (
                            <Botao
                                cor="perigo"
                                altura="pequeno"
                                icone="fa-trash"
                                aTrabalhar={aRemover}
                                onClick={aoRemover}
                            >
                                {t('Remover logótipo')}
                            </Botao>
                        )}
                    </div>

                    <p className="text-xs text-slate-400">{t('Máximo 2MB — PNG, JPG ou GIF')}</p>

                    {erro?.[0] && (
                        <p role="alert" className="text-xs font-medium text-red-600">
                            {erro[0]}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─── O formulário ────────────────────────────────────────────────────── */

function Formulario({
    dados,
    aEditar,
    erros,
    aGravar,
    erroDeGravar,
    opcoes,
    logotipo,
    aRemoverLogotipo,
    aoEscolherLogotipo,
    aoRemoverLogotipo,
    aoMudar,
    aoFechar,
    aoGravar,
}: {
    dados: ClienteParaGravar | null;
    aEditar: Cliente | null;
    erros: Record<string, string[]>;
    aGravar: boolean;
    erroDeGravar: unknown;
    opcoes: OpcoesDosClientes | undefined;
    logotipo: File | null;
    aRemoverLogotipo: boolean;
    aoEscolherLogotipo: (f: File | null) => void;
    aoRemoverLogotipo: () => void;
    aoMudar: (d: ClienteParaGravar) => void;
    aoFechar: () => void;
    aoGravar: (d: ClienteParaGravar) => void;
}) {
    if (!dados) {
        return null;
    }

    const campo = <K extends keyof ClienteParaGravar>(chave: K, valor: ClienteParaGravar[K]) =>
        aoMudar({ ...dados, [chave]: valor });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={aEditar ? t('Editar Cliente') : t('Novo Cliente')}
            subtitulo={aEditar?.name}
            // A janela leva a cor do ecrã — o verde da faixa lá em cima.
            cor="bom"
            icone="fa-users"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="primaria"
                        tom="solida"
                        icone="fa-check"
                        aTrabalhar={aGravar}
                        onClick={() => aoGravar(dados)}
                    >
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroDeGravar} />

            <form
                className="grid gap-4 sm:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    aoGravar(dados);
                }}
            >
                <Campo etiqueta={t('Tipo')} erro={erros.type}>
                    <select
                        value={dados.type}
                        onChange={(e) => campo('type', e.target.value as ClienteParaGravar['type'])}
                        className={entrada}
                    >
                        <option value="pessoa_juridica">{t('Empresa')}</option>
                        <option value="pessoa_fisica">{t('Particular')}</option>
                    </select>
                </Campo>

                <Campo etiqueta={t('NIF')} erro={erros.nif} obrigatorio>
                    <input
                        value={dados.nif}
                        onChange={(e) => campo('nif', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta={t('Nome')} erro={erros.name} obrigatorio className="sm:col-span-2">
                    <input
                        value={dados.name}
                        onChange={(e) => campo('name', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta={t('Email')} erro={erros.email}>
                    <input
                        type="email"
                        value={dados.email ?? ''}
                        onChange={(e) => campo('email', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                    <input
                        value={dados.phone ?? ''}
                        onChange={(e) => campo('phone', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                {/* O CELULAR. Estava na ficha de sempre e a lista já o mostra
                    quando não há telefone — mas não havia por onde o escrever:
                    a migração deixou-o no tipo e no carregamento da edição, sem
                    campo nenhum. Em Angola é o número que a maioria dos
                    clientes atende. */}
                <Campo etiqueta={t('Celular')} erro={erros.mobile}>
                    <input
                        value={dados.mobile ?? ''}
                        onChange={(e) => campo('mobile', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <CondicaoDePagamento dados={dados} erros={erros} opcoes={opcoes} aoMudar={aoMudar} />

                <Logotipo
                    actual={aEditar?.logo ?? null}
                    escolhido={logotipo}
                    erro={erros.logotipo}
                    aRemover={aRemoverLogotipo}
                    aoEscolher={aoEscolherLogotipo}
                    aoRemover={aEditar?.logo ? aoRemoverLogotipo : undefined}
                />

                <Campo etiqueta={t('Morada')} erro={erros.address} className="sm:col-span-2">
                    <input
                        value={dados.address ?? ''}
                        onChange={(e) => campo('address', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Morada dados={dados} erros={erros} opcoes={opcoes} aoMudar={aoMudar} />

                <AcessoAoPortal
                    dados={dados}
                    aEditar={aEditar}
                    erros={erros}
                    portalUrl={opcoes?.portal_url}
                    aoMudar={aoMudar}
                />
            </form>
        </Modal>
    );
}

/* ─── A morada ────────────────────────────────────────────────────────── */

/**
 * PAÍS → PROVÍNCIA → MUNICÍPIO → BAIRRO, e é o país que manda.
 *
 * A mesma cascata do `<x-morada>` que o resto do sistema usa: em Angola a
 * divisão administrativa escolhe-se de listas que vêm do servidor (nas
 * `opcoes`), fora de Angola não há divisão que se possa impor e escreve-se.
 *
 * As listas VÊM DO SERVIDOR de propósito. As províncias já estiveram escritas
 * à mão em cinco sítios e acabaram diferentes; uma sexta cópia em TypeScript
 * era o mesmo erro com outra extensão.
 */
function Morada({
    dados,
    erros,
    opcoes,
    aoMudar,
}: {
    dados: ClienteParaGravar;
    erros: Record<string, string[]>;
    opcoes: OpcoesDosClientes | undefined;
    aoMudar: (d: ClienteParaGravar) => void;
}) {
    const ehAngola = dados.country === ANGOLA;
    const provincias = opcoes?.provincias ?? [];
    const novas = opcoes?.provincias_novas ?? [];
    const municipios = opcoes?.municipios?.[dados.province ?? ''] ?? [];
    const bairros = opcoes?.bairros?.[dados.municipality ?? ''] ?? [];

    return (
        <>
            {/* O país é um código ISO de duas letras porque é assim que viaja
                para a AGT em `customerCountry`. E vem primeiro porque é ele
                que decide se a morada tem divisão para escolher. */}
            <Campo etiqueta={t('País')} erro={erros.country} obrigatorio>
                <select
                    value={dados.country}
                    onChange={(e) => {
                        // Sair de Angola (ou voltar a ela) esvazia a divisão:
                        // uma província angolana não é um estado brasileiro, e
                        // deixá-la lá gravava uma morada que não existe.
                        const trocaDeMundo = (e.target.value === ANGOLA) !== ehAngola;

                        aoMudar({
                            ...dados,
                            country: e.target.value,
                            ...(trocaDeMundo
                                ? { province: '', municipality: '', neighbourhood: '' }
                                : {}),
                        });
                    }}
                    className={entrada}
                >
                    {Object.entries(opcoes?.paises ?? {}).map(([codigo, nome]) => (
                        <option key={codigo} value={codigo}>
                            {nome} ({codigo})
                        </option>
                    ))}
                </select>
            </Campo>

            <Campo etiqueta={t('Código postal')} erro={erros.postal_code}>
                <input
                    value={dados.postal_code ?? ''}
                    onChange={(e) => aoMudar({ ...dados, postal_code: e.target.value })}
                    className={entrada}
                    autoComplete="off"
                />
            </Campo>

            {ehAngola ? (
                <>
                    <Campo etiqueta={t('Província')} erro={erros.province}>
                        <select
                            value={dados.province ?? ''}
                            // Trocar de província deixa cair o município e o
                            // bairro: um município da Huíla não pertence a
                            // Luanda, e ficava lá calado até ao SAFT.
                            onChange={(e) =>
                                aoMudar({
                                    ...dados,
                                    province: e.target.value,
                                    municipality: '',
                                    neighbourhood: '',
                                    // A cidade segue o município (o servidor
                                    // copia-a quando vem vazia); deixá-la com
                                    // o município antigo era mostrar uma
                                    // localidade que já não é a desta morada.
                                    city: '',
                                })
                            }
                            className={entrada}
                        >
                            <option value="">{t('Escolha a província…')}</option>
                            {provincias.map((p) => (
                                <option key={p} value={p}>
                                    {p}
                                    {novas.includes(p) ? ` · ${t('nova em 2024')}` : ''}
                                </option>
                            ))}
                            {/* Um valor gravado antes da reforma de 2024
                                continua a aparecer, em vez de o select o
                                deitar fora em silêncio ao gravar. */}
                            {dados.province && !provincias.includes(dados.province) && (
                                <option value={dados.province}>
                                    {dados.province} · {t('divisão anterior')}
                                </option>
                            )}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Município')} erro={erros.municipality}>
                        <select
                            value={dados.municipality ?? ''}
                            onChange={(e) =>
                                aoMudar({
                                    ...dados,
                                    municipality: e.target.value,
                                    neighbourhood: '',
                                    city: '',
                                })
                            }
                            disabled={!dados.province}
                            className={entrada}
                        >
                            <option value="">
                                {dados.province ? t('Escolha o município…') : t('Escolha primeiro a província')}
                            </option>
                            {municipios.map((m) => (
                                <option key={m} value={m}>
                                    {m}
                                </option>
                            ))}
                            {dados.municipality && !municipios.includes(dados.municipality) && (
                                <option value={dados.municipality}>{dados.municipality}</option>
                            )}
                        </select>
                    </Campo>

                    {/* SUGERE, NÃO FECHA. Angola não tem registo nacional de
                        bairros: nascem, mudam de nome e raramente entram numa
                        lista oficial. Um select fechado obrigava a escolher o
                        bairro errado por o certo não estar lá. */}
                    <Campo etiqueta={t('Bairro')} erro={erros.neighbourhood} className="sm:col-span-2">
                        <input
                            value={dados.neighbourhood ?? ''}
                            onChange={(e) => aoMudar({ ...dados, neighbourhood: e.target.value })}
                            list="bairros-sugeridos"
                            placeholder={t('Bairro ou zona — pode escrever outro')}
                            className={entrada}
                            autoComplete="off"
                        />
                        <datalist id="bairros-sugeridos">
                            {bairros.map((b) => (
                                <option key={b} value={b} />
                            ))}
                        </datalist>
                    </Campo>
                </>
            ) : (
                <>
                    <Campo etiqueta={t('Província / Estado')} erro={erros.province}>
                        <input
                            value={dados.province ?? ''}
                            onChange={(e) => aoMudar({ ...dados, province: e.target.value })}
                            placeholder={t('Província, estado ou região')}
                            className={entrada}
                            autoComplete="off"
                        />
                    </Campo>

                    <Campo etiqueta={t('Cidade')} erro={erros.city}>
                        <input
                            value={dados.city ?? ''}
                            onChange={(e) => aoMudar({ ...dados, city: e.target.value })}
                            className={entrada}
                            autoComplete="off"
                        />
                    </Campo>
                </>
            )}
        </>
    );
}

/* ─── O acesso ao portal do cliente ───────────────────────────────────── */

/**
 * DAR, TIRAR OU REPOR A PORTA DO PORTAL.
 *
 * O servidor aceita `portal_access`, `portal_password` e `portal_repor_senha`
 * desde sempre — mas o ecrã não os mostrava, e uma funcionalidade que nenhum
 * ecrã alcança é uma funcionalidade que não existe: nenhum cliente conseguia
 * receber uma senha.
 *
 * A REGRA QUE NÃO PODE CAIR: guardar a ficha não troca a senha de quem já tem
 * acesso. Só mudar o telefone deixaria o cliente de fora do portal sem
 * ninguém perceber porquê. Por isso a senha abre sempre vazia e só viaja
 * quando é escrita, e a reposição é um botão que se carrega de propósito.
 */
function AcessoAoPortal({
    dados,
    aEditar,
    erros,
    portalUrl,
    aoMudar,
}: {
    dados: ClienteParaGravar;
    aEditar: Cliente | null;
    erros: Record<string, string[]>;
    portalUrl: string | undefined;
    aoMudar: (d: ClienteParaGravar) => void;
}) {
    const jaTinha = Boolean(aEditar?.portal_access);
    const semEmail = !dados.email?.trim();

    return (
        <div className="mt-2 border-t border-slate-200 pt-4 sm:col-span-2">
            <label className="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    checked={Boolean(dados.portal_access)}
                    onChange={(e) =>
                        aoMudar({
                            ...dados,
                            portal_access: e.target.checked,
                            // Tirar o acesso não deixa uma ordem de repor
                            // senha pendurada por baixo.
                            portal_password: e.target.checked ? dados.portal_password : '',
                            portal_repor_senha: e.target.checked ? dados.portal_repor_senha : false,
                        })
                    }
                    className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                />
                <span>
                    <span className="font-semibold">
                        <i className="fas fa-user-lock mr-1 text-indigo-600" aria-hidden="true" />
                        {t('Dar acesso ao portal do cliente')}
                    </span>
                    <span className="mt-0.5 block text-xs text-slate-500">
                        {portalUrl ? (
                            <>
                                {t(
                                    'O cliente passa a poder ver as suas facturas, proformas e extracto de conta em',
                                )}{' '}
                                <span className="font-mono">{portalUrl}</span>
                            </>
                        ) : (
                            t('O cliente passa a poder ver as suas facturas, proformas e extracto de conta')
                        )}
                        .
                    </span>
                </span>
            </label>

            {dados.portal_access && (
                <div className="mt-3 space-y-3 pl-6">
                    {/* O PORTAL AUTENTICA PELO EMAIL. O servidor exige-o, e o
                        ecrã diz porquê ANTES de recusar — descobrir a regra
                        num 422 é descobri-la tarde. */}
                    {semEmail && (
                        <p role="alert" className="text-xs font-medium text-amber-700">
                            <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />
                            {t(
                                'Escreva o email: é por lá que o cliente entra no portal, e sem ele o acesso não se liga.',
                            )}
                        </p>
                    )}

                    {/* AVISAR, OU NÃO.
                        Nem todo o acesso se anuncia: prepara-se a conta hoje e
                        entrega-se a senha em mão na visita da semana que vem.
                        Por omissão avisa-se, que é o caso normal. */}
                    <label className="flex items-start gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={dados.portal_avisar !== false}
                            onChange={(e) => aoMudar({ ...dados, portal_avisar: e.target.checked })}
                            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                        />
                        <span>
                            {t('Avisar o cliente por email')}
                            <span className="mt-0.5 block text-xs text-slate-500">
                                {t('Desligue se preferir entregar a senha em mão.')}
                            </span>
                        </span>
                    </label>

                    <Campo etiqueta={t('Senha do portal')} erro={erros.portal_password}>
                        <input
                            type="text"
                            value={dados.portal_password ?? ''}
                            onChange={(e) => aoMudar({ ...dados, portal_password: e.target.value })}
                            placeholder={t('Deixe vazio para ser gerada')}
                            maxLength={60}
                            className={entrada}
                            autoComplete="off"
                        />
                    </Campo>

                    {jaTinha &&
                        (dados.portal_repor_senha ? (
                            <p className="flex flex-wrap items-center gap-2 text-xs text-amber-800">
                                <span>
                                    <i className="fas fa-key mr-1" aria-hidden="true" />
                                    {t(
                                        'A senha vai ser trocada ao guardar e enviada por email. A antiga deixa de servir.',
                                    )}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => aoMudar({ ...dados, portal_repor_senha: false })}
                                    className={cls('font-semibold text-indigo-600 underline', FOCO)}
                                >
                                    {t('Afinal não')}
                                </button>
                            </p>
                        ) : (
                            <div className="flex flex-wrap items-center gap-2">
                                <Botao
                                    type="button"
                                    altura="pequeno"
                                    icone="fa-key"
                                    onClick={() => aoMudar({ ...dados, portal_repor_senha: true })}
                                >
                                    {t('Repor a senha')}
                                </Botao>
                                <span className="text-xs text-slate-500">
                                    {t('A guardar sem escrever nada, a senha actual mantém-se.')}
                                </span>
                            </div>
                        ))}
                </div>
            )}
        </div>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */



function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">{t('A sessão expirou')}</h2>
                <p className="mb-4 text-sm text-amber-900">
                    {t('Entre outra vez para continuar. Nada se perdeu.')}
                </p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    {t('Voltar a entrar')}
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível carregar os clientes')}</h2>
            <p className="text-sm text-red-800">
                {daApi?.message ?? t('Verifique a ligação e tente outra vez.')}
            </p>
        </div>
    );
}
