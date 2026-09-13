import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { papeis as api, type GrupoDePermissoes, type Papel } from '@/api/utilizadores';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS PAPÉIS E AS PERMISSÕES.
 *
 * O sistema tem umas trezentas e quarenta permissões. Mostrá-las todas, com o
 * nome técnico e em bloco, era mostrar um muro: ninguém sabia o que é que
 * `hotel.rooms.edit` fazia, nem porque é que uma empresa sem hotel a via.
 *
 * Por isso o catálogo é O QUE ESTA EMPRESA PODE VER — o núcleo mais os módulos
 * ACTIVOS —, em português, agrupado por entidade, com atalhos para marcar um
 * módulo inteiro ou só a consulta, e para começar a partir de um papel que já
 * existe (que é como toda a gente faz o segundo papel).
 *
 * O QUE AQUI DEIXOU DE HAVER: criar permissões novas. A permissão nascia
 * GLOBAL, sem empresa nenhuma, escrita a partir de dentro de uma casa — e, com
 * os curingas desligados, uma permissão inventada não é verificada em lado
 * nenhum do código. Era uma escrita que atravessava empresas para não dar poder
 * nenhum a ninguém.
 */

const VAZIO = { name: '', description: '' };

export default function PapeisEPermissoes() {
    const cache = useQueryClient();

    const [aba, porAba] = useState('papeis');
    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [escolhidas, porEscolhidas] = useState<number[]>([]);
    const [grupoAberto, porGrupoAberto] = useState('');
    const [pesquisa, porPesquisa] = useState('');
    const [copiarDe, porCopiarDe] = useState('');

    const [aApagar, porAApagar] = useState<Papel | null>(null);

    /* O separador de atribuir. */
    const [procura, porProcura] = useState('');
    const [aAtribuir, porAAtribuir] = useState<{ id: number; nome: string; papeis: number[] } | null>(null);

    const opcoes = useQuery({ queryKey: ['papeis', 'opcoes'], queryFn: () => api.opcoes() });
    const lista = useQuery({ queryKey: ['papeis', 'lista'], queryFn: () => api.listar() });
    const pessoas = useQuery({
        queryKey: ['papeis', 'utilizadores', procura],
        queryFn: () => api.utilizadores(procura),
        enabled: aba === 'atribuir',
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['papeis'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => api.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); fechar(); },
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => api.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: (e) => { porErro(e); porAApagar(null); },
    });

    const atribuir = useMutation({
        mutationFn: (d: { id: number; papeis: number[] }) => api.atribuir(d.id, d.papeis),
        onSuccess: (r) => { feito(r.message); porAAtribuir(null); },
        onError: porErro,
    });

    const ficha = useMutation({
        mutationFn: (id: number) => api.ficha(id),
        onSuccess: (r) => {
            porAEditar(r.data.id);
            porFormulario({ name: r.data.nome, description: r.data.descricao ?? '' });
            porEscolhidas(r.data.permissoes);
            porGrupoAberto(opcoes.data?.grupos[0]?.slug ?? '');
            porPesquisa('');
            porCopiarDe('');
        },
        onError: porErro,
    });

    const paraCopiar = useMutation({
        mutationFn: (id: number) => api.ficha(id),
        onSuccess: (r) => {
            porEscolhidas(r.data.permissoes);
            porCopiarDe('');
            porRecado(t('Permissões copiadas de :papel. Ajuste e guarde.', { papel: r.data.nome }));
        },
        onError: porErro,
    });

    const grupos = useMemo(() => opcoes.data?.grupos ?? [], [opcoes.data]);

    const aberto = useMemo<GrupoDePermissoes | undefined>(
        () => grupos.find((g) => g.slug === grupoAberto) ?? grupos[0],
        [grupos, grupoAberto],
    );

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const marcadasNo = (g: GrupoDePermissoes) => g.ids.filter((id) => escolhidas.includes(id)).length;

    const fechar = () => {
        porFormulario(null);
        porAEditar(null);
        porEscolhidas([]);
        porPesquisa('');
        porCopiarDe('');
    };

    const abrirNovo = () => {
        porAEditar(null);
        porFormulario({ ...VAZIO });
        porEscolhidas([]);
        porGrupoAberto(grupos[0]?.slug ?? '');
        porPesquisa('');
        porCopiarDe('');
    };

    const alternar = (id: number) => porEscolhidas(
        escolhidas.includes(id) ? escolhidas.filter((x) => x !== id) : [...escolhidas, id],
    );

    /** O módulo inteiro: marca tudo, ou tira tudo se já estava tudo marcado. */
    const alternarGrupo = (g: GrupoDePermissoes) => porEscolhidas(
        marcadasNo(g) === g.total
            ? escolhidas.filter((id) => !g.ids.includes(id))
            : [...new Set([...escolhidas, ...g.ids])],
    );

    /** SÓ CONSULTA: ver, aceder, relatórios e painel — nada que escreva. */
    const soLeitura = (g: GrupoDePermissoes) => {
        const leitura = g.entidades.flatMap((e) => e.linhas.filter((l) => l.leitura).map((l) => l.id));

        porEscolhidas([...new Set([...escolhidas.filter((id) => !g.ids.includes(id)), ...leitura])]);
    };

    const termo = pesquisa.trim().toLowerCase();

    const entidadesVisiveis = (aberto?.entidades ?? [])
        .map((e) => ({
            nome: e.nome,
            linhas: termo === ''
                ? e.linhas
                : e.linhas.filter((l) => `${l.rotulo} ${l.nome}`.toLowerCase().includes(termo)),
        }))
        .filter((e) => e.linhas.length > 0);

    const abas = [
        { chave: 'papeis', rotulo: t('Papéis'), icone: 'fa-user-shield' },
        { chave: 'catalogo', rotulo: t('Catálogo de permissões'), icone: 'fa-list-check' },
        { chave: 'atribuir', rotulo: t('Atribuir'), icone: 'fa-user-check' },
    ];

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Papéis e Permissões')}
                subtitulo={t('O que cada função pode fazer dentro desta empresa')}
                icone="fa-user-shield"
                cor="roxo"
                accoes={
                    <>
                        <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNovo}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Novo papel')}
                        </button>
                        <a href="/users" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-users" aria-hidden="true" />
                            {t('Utilizadores')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-user-shield">
                        {t(':n papéis', { n: numero(resumo?.papeis ?? 0) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-key">
                        {t(':n permissões nesta empresa', { n: numero(resumo?.permissoes_visiveis ?? 0) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <Separadores abas={abas} activa={aba} aoMudar={porAba} />

            {/* ─── Os papéis ─────────────────────────────────────────── */}

            <PainelDoSeparador chave="papeis" activa={aba}>
                <div className="space-y-5">
                    {resumo && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <CartaoNumero
                                rotulo={t('Papéis')} valor={numero(resumo.papeis)} icone="fa-user-shield" tom="roxo"
                            />
                            <CartaoNumero
                                rotulo={t('Sem ninguém')} valor={numero(resumo.sem_utilizadores)}
                                icone="fa-user-slash" tom={resumo.sem_utilizadores > 0 ? 'ambar' : 'verde'}
                                nota={t('Papéis desenhados e nunca atribuídos')}
                            />
                            <CartaoNumero
                                rotulo={t('Sem permissões')} valor={numero(resumo.sem_permissoes)}
                                icone="fa-ban" tom={resumo.sem_permissoes > 0 ? 'vermelho' : 'verde'}
                                nota={t('Quem os tem não consegue fazer nada')}
                            />
                        </div>
                    )}

                    {lista.isPending ? (
                        <Carregando linhas={5} />
                    ) : lista.isError ? (
                        <AvisoDeErro erro={lista.error} />
                    ) : lista.data.data.length === 0 ? (
                        <SemNada
                            icone="fa-user-shield"
                            titulo={t('Nenhum papel')}
                            frase={t('Um papel é um conjunto de permissões com um nome — «Caixa», «Gerente» — e é o que se dá a cada pessoa.')}
                            accao={
                                <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                                    {t('Novo papel')}
                                </Botao>
                            }
                        />
                    ) : (
                        <ul className="space-y-2">
                            {lista.data.data.map((x, i) => (
                                <li key={x.id} style={cascata(i)}
                                    className={cls('entra flex flex-wrap items-center gap-3 p-4 transition hover:shadow-md', CARTAO)}>
                                    <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-purple-50 text-purple-600">
                                        <i className="fas fa-user-shield" aria-hidden="true" />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">{x.nome}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {x.descricao || t('Sem descrição')}
                                        </p>
                                    </div>

                                    <Etiqueta cor={x.permissoes > 0 ? 'primaria' : 'perigo'} icone="fa-key">
                                        {t(':n permissões', { n: numero(x.permissoes) })}
                                    </Etiqueta>

                                    <Etiqueta cor={x.utilizadores > 0 ? 'bom' : 'neutra'} icone="fa-users">
                                        {t(':n utilizadores', { n: numero(x.utilizadores) })}
                                    </Etiqueta>

                                    <div className="flex flex-none items-center gap-1.5">
                                        <Botao altura="pequeno" icone="fa-pen"
                                            aTrabalhar={ficha.isPending && ficha.variables === x.id}
                                            onClick={() => ficha.mutate(x.id)}
                                            aria-label={t('Editar papel')} />
                                        {/* UM PAPEL COM GENTE NÃO SE APAGA: quem
                                            o tinha ficava sem permissão nenhuma
                                            e sem aviso. */}
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                            disabled={x.utilizadores > 0}
                                            title={x.utilizadores > 0
                                                ? t('Está atribuído a alguém. Mude-os de papel primeiro.')
                                                : undefined}
                                            onClick={() => porAApagar(x)}
                                            aria-label={t('Eliminar papel')} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </PainelDoSeparador>

            {/* ─── O catálogo ────────────────────────────────────────── */}

            <PainelDoSeparador chave="catalogo" activa={aba}>
                <div className="space-y-4">
                    <p className={cls('border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900', RAIO)}>
                        <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                        {t('Esta é a lista das permissões que ESTA empresa pode dar: o núcleo mais os módulos activos. Não se inventam permissões — elas vêm do código que as verifica.')}
                    </p>

                    <div className="grid gap-4 lg:grid-cols-2">
                        {grupos.map((g, i) => (
                            <div key={g.slug} style={cascata(i)} className={cls('entra p-4', CARTAO)}>
                                <p className="flex items-center gap-2 text-sm font-bold text-slate-800">
                                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-purple-50 text-purple-600">
                                        <i className={`fas ${g.icone}`} aria-hidden="true" />
                                    </span>
                                    {g.nome}
                                    <span className="ml-auto text-xs font-semibold tabular-nums text-slate-400">
                                        {numero(g.total)}
                                    </span>
                                </p>

                                <ul className="mt-3 space-y-2">
                                    {g.entidades.map((e) => (
                                        <li key={e.nome} className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                            <span className="text-xs font-semibold text-slate-600">{e.nome}</span>
                                            {e.linhas.map((l) => (
                                                <span key={l.id}
                                                    title={l.nome}
                                                    className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">
                                                    {l.rotulo}
                                                </span>
                                            ))}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            </PainelDoSeparador>

            {/* ─── Atribuir ──────────────────────────────────────────── */}

            <PainelDoSeparador chave="atribuir" activa={aba}>
                <div className="space-y-4">
                    <Campo etiqueta={t('Procurar')} className="max-w-md">
                        <div className="relative">
                            <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input type="search" value={procura}
                                onChange={(e) => porProcura(e.target.value)}
                                placeholder={t('Nome ou e-mail…')} className={cls(entrada, 'pl-9')} />
                        </div>
                    </Campo>

                    {pessoas.isPending ? (
                        <Carregando linhas={5} />
                    ) : pessoas.isError ? (
                        <AvisoDeErro erro={pessoas.error} />
                    ) : pessoas.data.data.length === 0 ? (
                        <SemNada icone="fa-users" titulo={t('Ninguém encontrado')}
                            frase={t('Só aparecem aqui os utilizadores desta empresa.')} />
                    ) : (
                        <ul className="space-y-2">
                            {pessoas.data.data.map((u, i) => (
                                <li key={u.id} style={cascata(i)}
                                    className={cls('entra flex flex-wrap items-center gap-3 p-4 transition hover:shadow-md', CARTAO)}>
                                    <span className={cls(
                                        'grid h-10 w-10 flex-none place-items-center rounded-xl text-xs font-bold uppercase',
                                        u.activo ? 'bg-purple-50 text-purple-600' : 'bg-slate-100 text-slate-400',
                                    )}>
                                        {u.nome.slice(0, 2)}
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">{u.nome}</p>
                                        <p className="truncate text-xs text-slate-500">{u.email}</p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {u.papeis.length === 0 ? (
                                            <Etiqueta cor="neutra">{t('Sem papel')}</Etiqueta>
                                        ) : u.papeis.map((pp) => (
                                            <Etiqueta key={pp.id} cor="primaria" icone="fa-user-shield">{pp.nome}</Etiqueta>
                                        ))}
                                    </div>

                                    <Botao altura="pequeno" icone="fa-user-gear"
                                        onClick={() => porAAtribuir({
                                            id: u.id, nome: u.nome, papeis: u.papeis.map((pp) => pp.id),
                                        })}>
                                        {t('Papéis')}
                                    </Botao>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </PainelDoSeparador>

            {/* ─── O modal do papel ──────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={fechar}
                titulo={aEditar ? t('Editar papel') : t('Novo papel')}
                subtitulo={t('Só os módulos que esta empresa tem activos aparecem aqui')}
                icone="fa-user-shield"
                cor="roxo"
                largura="xl"
                rodape={
                    <>
                        <Botao onClick={fechar}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({ ...formulario, permissoes: escolhidas })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome do papel')} obrigatorio erro={erros.name}
                                ajuda={t('«Caixa», «Gerente», «Recepção» — o nome da função.')}>
                                <input type="text" value={formulario.name}
                                    onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Descrição')} erro={erros.description}>
                                <input type="text" value={formulario.description}
                                    onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        {/* COMEÇAR A PARTIR DE OUTRO PAPEL: é assim que toda a
                            gente faz o segundo papel de uma empresa. */}
                        <div className="flex flex-wrap items-end gap-3">
                            <Campo etiqueta={t('Começar a partir de')} className="w-64"
                                ajuda={t('Copia as permissões de um papel que já existe.')}>
                                <select value={copiarDe} onChange={(e) => porCopiarDe(e.target.value)}
                                    className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {opcoes.data.papeis
                                        .filter((x) => Number(x.valor) !== aEditar)
                                        .map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Botao icone="fa-copy" disabled={!copiarDe}
                                aTrabalhar={paraCopiar.isPending}
                                onClick={() => copiarDe && paraCopiar.mutate(Number(copiarDe))}>
                                {t('Copiar permissões')}
                            </Botao>

                            <span className="ml-auto text-sm font-semibold tabular-nums text-slate-600">
                                {t(':n permissões marcadas', { n: numero(escolhidas.length) })}
                            </span>
                        </div>

                        <div className="grid gap-4 md:grid-cols-[14rem_1fr]">
                            {/* A lista dos módulos, com a contagem de cada um. */}
                            <ul className="space-y-1">
                                {grupos.map((g) => {
                                    const marcadas = marcadasNo(g);
                                    const activa = (aberto?.slug ?? '') === g.slug;

                                    return (
                                        <li key={g.slug}>
                                            <button
                                                type="button"
                                                onClick={() => { porGrupoAberto(g.slug); porPesquisa(''); }}
                                                className={cls(
                                                    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition',
                                                    RAIO, FOCO,
                                                    activa
                                                        ? 'bg-purple-50 font-semibold text-purple-700'
                                                        : 'text-slate-600 hover:bg-slate-50',
                                                )}
                                            >
                                                <i className={`fas ${g.icone} w-4 text-center`} aria-hidden="true" />
                                                <span className="min-w-0 flex-1 truncate">{g.nome}</span>
                                                <span className={cls(
                                                    'rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums',
                                                    marcadas === 0 ? 'bg-slate-100 text-slate-400'
                                                        : marcadas === g.total ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-amber-100 text-amber-700',
                                                )}>
                                                    {marcadas}/{g.total}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>

                            {/* As linhas do módulo aberto, por entidade. */}
                            <div className="space-y-3">
                                {aberto && (
                                    <>
                                        <div className="flex flex-wrap items-end gap-2">
                                            <Campo etiqueta={t('Procurar permissão')} className="min-w-[10rem] flex-1">
                                                <input type="search" value={pesquisa}
                                                    onChange={(e) => porPesquisa(e.target.value)}
                                                    placeholder={t('Ver, criar, faturas…')} className={entrada} />
                                            </Campo>
                                            <Botao altura="pequeno" icone="fa-check-double"
                                                onClick={() => alternarGrupo(aberto)}>
                                                {marcadasNo(aberto) === aberto.total ? t('Tirar tudo') : t('Marcar tudo')}
                                            </Botao>
                                            <Botao altura="pequeno" icone="fa-eye"
                                                onClick={() => soLeitura(aberto)}>
                                                {t('Só consulta')}
                                            </Botao>
                                        </div>

                                        {entidadesVisiveis.length === 0 ? (
                                            <p className="py-6 text-center text-sm text-slate-500">
                                                {t('Nada que corresponda a essa procura.')}
                                            </p>
                                        ) : (
                                            <ul className="max-h-[24rem] space-y-3 overflow-y-auto pr-1">
                                                {entidadesVisiveis.map((e) => (
                                                    <li key={e.nome}>
                                                        <p className="mb-1.5 text-xs font-bold uppercase tracking-wide text-slate-400">
                                                            {e.nome}
                                                        </p>
                                                        <div className="flex flex-wrap gap-2">
                                                            {e.linhas.map((l) => {
                                                                const marcada = escolhidas.includes(l.id);

                                                                return (
                                                                    <label key={l.id} title={l.nome}
                                                                        className={cls(
                                                                            'inline-flex cursor-pointer items-center gap-2 border px-2.5 py-1.5 text-xs font-medium transition',
                                                                            RAIO,
                                                                            marcada
                                                                                ? 'border-purple-300 bg-purple-50 text-purple-800'
                                                                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                                                                        )}>
                                                                        <input type="checkbox" checked={marcada}
                                                                            onChange={() => alternar(l.id)}
                                                                            className="h-4 w-4 rounded text-purple-600" />
                                                                        {l.rotulo}
                                                                    </label>
                                                                );
                                                            })}
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── Atribuir papéis a alguém ──────────────────────────── */}

            <Modal
                aberto={aAtribuir !== null}
                aoFechar={() => porAAtribuir(null)}
                titulo={t('Papéis nesta empresa')}
                subtitulo={aAtribuir?.nome}
                icone="fa-user-gear"
                cor="roxo"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => porAAtribuir(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={atribuir.isPending}
                            onClick={() => aAtribuir && atribuir.mutate({ id: aAtribuir.id, papeis: aAtribuir.papeis })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {aAtribuir && (
                    <div className="space-y-3">
                        <AvisoDeErro erro={atribuir.error} />

                        <p className="text-sm text-slate-600">
                            {t('O que aqui se muda vale só nesta empresa. Os papéis que esta pessoa tem noutras casas não se tocam.')}
                        </p>

                        <ul className="space-y-2">
                            {(lista.data?.data ?? []).map((pp) => {
                                const marcado = aAtribuir.papeis.includes(pp.id);

                                return (
                                    <li key={pp.id}>
                                        <label className={cls(
                                            'flex cursor-pointer items-center gap-3 border p-3 transition',
                                            RAIO,
                                            marcado ? 'border-purple-200 bg-purple-50/60' : 'border-slate-200 bg-white hover:bg-slate-50',
                                        )}>
                                            <input type="checkbox" checked={marcado}
                                                onChange={() => porAAtribuir({
                                                    ...aAtribuir,
                                                    papeis: marcado
                                                        ? aAtribuir.papeis.filter((x) => x !== pp.id)
                                                        : [...aAtribuir.papeis, pp.id],
                                                })}
                                                className="h-5 w-5 rounded text-purple-600" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold text-slate-800">{pp.nome}</span>
                                                <span className="block truncate text-xs text-slate-500">
                                                    {pp.descricao || t(':n permissões', { n: numero(pp.permissoes) })}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </Modal>

            {/* ─── Eliminar ──────────────────────────────────────────── */}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar papel')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('O papel desaparece desta empresa. Só se elimina um papel que não esteja atribuído a ninguém — senão quem o tinha ficava sem permissão nenhuma e sem aviso.')}
                </p>
            </Modal>
        </div>
    );
}
