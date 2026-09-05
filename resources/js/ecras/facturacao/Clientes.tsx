import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    clientes,
    type Cliente,
    type ClienteParaGravar,
    type FiltrosDeClientes,
} from '@/api/clientes';
import { ErroDaApi } from '@/api/cliente';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Modal } from '@/ui/Modal';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';

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
    address: '',
    city: '',
    province: '',
    municipality: '',
    neighbourhood: '',
    postal_code: '',
    country: 'AO',
};

export default function Clientes() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDeClientes>({ procura: '', tipo: '', page: 1 });
    const [aEditar, porAEditar] = useState<Cliente | null>(null);
    const [formulario, porFormulario] = useState<ClienteParaGravar | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Cliente | null>(null);
    const [recado, porRecado] = useState<string>('');

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
        mutationFn: (dados: ClienteParaGravar) =>
            aEditar ? clientes.guardar(aEditar.id, dados) : clientes.criar(dados),
        onSuccess: () => {
            void cache.invalidateQueries({ queryKey: ['clientes'] });
            porFormulario(null);
            porAEditar(null);
            porErros({});
            porRecado(aEditar ? 'Cliente guardado.' : 'Cliente criado.');
        },
        onError: (e) => {
            // Os erros do servidor vão para o campo a que pertencem. Um
            // «não foi possível gravar» sem dizer onde obriga a adivinhar.
            porErros(e instanceof ErroDaApi ? e.erros : {});
        },
    });

    const apagar = useMutation({
        mutationFn: (c: Cliente) => clientes.apagar(c.id),
        onSuccess: () => {
            void cache.invalidateQueries({ queryKey: ['clientes'] });
            porAApagar(null);
            porRecado('Cliente apagado.');
        },
    });

    const permissoes = opcoes.data?.permissoes;

    function abrirNovo() {
        porAEditar(null);
        porErros({});
        porFormulario({ ...VAZIO, country: opcoes.data?.pais_padrao ?? 'AO' });
    }

    function abrirEdicao(c: Cliente) {
        porAEditar(c);
        porErros({});
        porFormulario({
            type: c.type,
            name: c.name,
            nif: c.nif,
            email: c.email ?? '',
            phone: c.phone ?? '',
            mobile: c.mobile ?? '',
            address: c.address ?? '',
            city: c.city ?? '',
            province: c.province ?? '',
            municipality: c.municipality ?? '',
            neighbourhood: c.neighbourhood ?? '',
            postal_code: c.postal_code ?? '',
            country: c.country,
        });
    }

    if (lista.isError) {
        return <Falhou erro={lista.error} />;
    }

    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    return (
        <div className="space-y-4">
            {recado && (
                <div
                    role="status"
                    className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}
                >
                    <span>
                        <i className="fas fa-circle-check mr-2" aria-hidden="true" />
                        {recado}
                    </span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar aviso">
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <Cartao
                titulo="Clientes"
                accoes={
                    permissoes?.pode_criar && (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                            Novo cliente
                        </Botao>
                    )
                }
            >
                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="block sm:col-span-2">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Procurar
                        </span>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder="Nome, NIF, email ou telefone"
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Tipo
                        </span>
                        <select
                            value={filtros.tipo ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">Todos</option>
                            {opcoes.data?.tipos.map((t) => (
                                <option key={t.valor} value={t.valor}>
                                    {t.rotulo}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <p className="mt-4 text-sm text-slate-500">
                    {contas ? `${contas.total.toLocaleString('pt-PT')} cliente(s)` : 'A contar…'}
                    {lista.isFetching && <span className="ml-2 text-xs">a actualizar…</span>}
                </p>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'px-6 py-14 text-center')}>
                    <i className="fas fa-users mb-3 text-4xl text-slate-300" aria-hidden="true" />
                    <p className="font-semibold text-slate-700">Nenhum cliente com esta procura</p>
                </div>
            ) : (
                <Cartao semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">Nome</th>
                                    <th className="px-4 py-3 font-semibold">NIF</th>
                                    <th className="px-4 py-3 font-semibold">Tipo</th>
                                    <th className="px-4 py-3 font-semibold">Contacto</th>
                                    <th className="px-4 py-3 font-semibold">Morada</th>
                                    <th className="px-4 py-3 text-right font-semibold">Documentos</th>
                                    <th className="px-4 py-3 text-right font-semibold">Acções</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((c) => (
                                    <tr key={c.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-medium text-slate-800">{c.name}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-600">{c.nif}</td>
                                        <td className="px-4 py-3">
                                            <Etiqueta cor={c.type === 'pessoa_fisica' ? 'neutra' : 'primaria'}>
                                                {c.tipo_rotulo}
                                            </Etiqueta>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {c.email && <div>{c.email}</div>}
                                            {(c.phone || c.mobile) && (
                                                <div className="text-xs text-slate-400">{c.phone || c.mobile}</div>
                                            )}
                                            {!c.email && !c.phone && !c.mobile && <span className="text-slate-300">—</span>}
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
                                                        title="Editar"
                                                        aria-label={`Editar ${c.name}`}
                                                        className={cls('p-2 text-slate-500 transition hover:bg-slate-100', RAIO, FOCO)}
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
                                                            title="Apagar"
                                                            aria-label={`Apagar ${c.name}`}
                                                            className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}
                                                        >
                                                            <i className="fas fa-trash" aria-hidden="true" />
                                                        </button>
                                                    ) : (
                                                        <span
                                                            title={`Tem ${c.documentos} documento(s) — um documento fiscal não pode ficar sem cliente`}
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
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label="Páginas">
                    <Botao
                        icone="fa-chevron-left"
                        altura="pequeno"
                        disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}
                    >
                        Anterior
                    </Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        Página {contas.current_page} de {contas.last_page}
                    </span>
                    <Botao
                        icone="fa-chevron-right"
                        altura="pequeno"
                        disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}
                    >
                        Seguinte
                    </Botao>
                </nav>
            )}

            <Formulario
                dados={formulario}
                aEditar={aEditar}
                erros={erros}
                aGravar={gravar.isPending}
                erroDeGravar={gravar.error}
                provincias={opcoes.data?.provincias ?? []}
                paises={opcoes.data?.paises ?? {}}
                aoMudar={porFormulario}
                aoFechar={() => {
                    porFormulario(null);
                    porAEditar(null);
                    porErros({});
                }}
                aoGravar={(d) => gravar.mutate(d)}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo="Apagar cliente"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>Cancelar</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}
                        >
                            Apagar
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    Apagar <strong>{aApagar?.name}</strong>? Deixa de aparecer nas listas.
                </p>
                {apagar.isError && (
                    <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">
                        {apagar.error instanceof ErroDaApi
                            ? apagar.error.message
                            : 'Não foi possível apagar.'}
                    </p>
                )}
            </Modal>
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
    provincias,
    paises,
    aoMudar,
    aoFechar,
    aoGravar,
}: {
    dados: ClienteParaGravar | null;
    aEditar: Cliente | null;
    erros: Record<string, string[]>;
    aGravar: boolean;
    erroDeGravar: unknown;
    provincias: string[];
    paises: Record<string, string>;
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
            titulo={aEditar ? `Editar ${aEditar.name}` : 'Novo cliente'}
            rodape={
                <>
                    <Botao onClick={aoFechar}>Cancelar</Botao>
                    <Botao
                        cor="primaria"
                        tom="solida"
                        icone="fa-check"
                        aTrabalhar={aGravar}
                        onClick={() => aoGravar(dados)}
                    >
                        Guardar
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
                <Campo etiqueta="Tipo" erro={erros.type}>
                    <select
                        value={dados.type}
                        onChange={(e) => campo('type', e.target.value as ClienteParaGravar['type'])}
                        className={entrada}
                    >
                        <option value="pessoa_juridica">Empresa</option>
                        <option value="pessoa_fisica">Particular</option>
                    </select>
                </Campo>

                <Campo etiqueta="NIF" erro={erros.nif} obrigatorio>
                    <input
                        value={dados.nif}
                        onChange={(e) => campo('nif', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta="Nome" erro={erros.name} obrigatorio className="sm:col-span-2">
                    <input
                        value={dados.name}
                        onChange={(e) => campo('name', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta="Email" erro={erros.email}>
                    <input
                        type="email"
                        value={dados.email ?? ''}
                        onChange={(e) => campo('email', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta="Telefone" erro={erros.phone}>
                    <input
                        value={dados.phone ?? ''}
                        onChange={(e) => campo('phone', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta="Morada" erro={erros.address} className="sm:col-span-2">
                    <input
                        value={dados.address ?? ''}
                        onChange={(e) => campo('address', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                <Campo etiqueta="Província" erro={erros.province}>
                    <select
                        value={dados.province ?? ''}
                        onChange={(e) => campo('province', e.target.value)}
                        className={entrada}
                    >
                        <option value="">—</option>
                        {provincias.map((p) => (
                            <option key={p} value={p}>
                                {p}
                            </option>
                        ))}
                    </select>
                </Campo>

                <Campo etiqueta="Cidade" erro={erros.city}>
                    <input
                        value={dados.city ?? ''}
                        onChange={(e) => campo('city', e.target.value)}
                        className={entrada}
                        autoComplete="off"
                    />
                </Campo>

                {/* O país é um código ISO de duas letras porque é assim que
                    viaja para a AGT em `customerCountry`. */}
                <Campo etiqueta="País" erro={erros.country} obrigatorio className="sm:col-span-2">
                    <select
                        value={dados.country}
                        onChange={(e) => campo('country', e.target.value)}
                        className={entrada}
                    >
                        {Object.entries(paises).map(([codigo, nome]) => (
                            <option key={codigo} value={codigo}>
                                {nome}
                            </option>
                        ))}
                    </select>
                </Campo>
            </form>
        </Modal>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */



function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">A sessão expirou</h2>
                <p className="mb-4 text-sm text-amber-900">Entre outra vez para continuar. Nada se perdeu.</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    Voltar a entrar
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível carregar os clientes</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? 'Verifique a ligação e tente outra vez.'}</p>
        </div>
    );
}
