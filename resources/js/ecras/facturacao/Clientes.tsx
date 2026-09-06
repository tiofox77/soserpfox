import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    clientes,
    type Cliente,
    type ClienteParaGravar,
    type FiltrosDeClientes,
    type OpcoesDosClientes,
} from '@/api/clientes';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
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
            porRecado(aEditar ? t('Cliente guardado.') : t('Cliente criado.'));
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
            porRecado(t('Cliente apagado.'));
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

            <Cartao
                titulo={t('Clientes')}
                accoes={
                    permissoes?.pode_criar && (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                            {t('Novo cliente')}
                        </Botao>
                    )
                }
            >
                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="block sm:col-span-2">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('Procurar')}
                        </span>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Nome, NIF, email ou telefone')}
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('Tipo')}
                        </span>
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
                </div>

                <p className="mt-4 text-sm text-slate-500">
                    {contas
                        ? t(':quantos cliente(s)', { quantos: contas.total.toLocaleString('pt-PT') })
                        : t('A contar…')}
                    {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                </p>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'px-6 py-14 text-center')}>
                    <i className="fas fa-users mb-3 text-4xl text-slate-300" aria-hidden="true" />
                    <p className="font-semibold text-slate-700">{t('Nenhum cliente com esta procura')}</p>
                </div>
            ) : (
                <Cartao semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">{t('Nome')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('NIF')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Tipo')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Contacto')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Morada')}</th>
                                    <th className="px-4 py-3 text-right font-semibold">{t('Documentos')}</th>
                                    <th className="px-4 py-3 text-right font-semibold">{t('Acções')}</th>
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
                                                            title={t('Apagar')}
                                                            aria-label={t('Apagar :nome', { nome: c.name })}
                                                            className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}
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

/* ─── O formulário ────────────────────────────────────────────────────── */

function Formulario({
    dados,
    aEditar,
    erros,
    aGravar,
    erroDeGravar,
    opcoes,
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
            titulo={aEditar ? t('Editar :nome', { nome: aEditar.name }) : t('Novo cliente')}
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
