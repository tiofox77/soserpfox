import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    funcionarios,
    type Beneficiario,
    type FichaDoFuncionario,
    type FiltrosDeFuncionarios,
    type LinhaDeFuncionario,
    type OpcoesDoFuncionario,
} from '@/api/rh';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { PorPagina } from '@/ui/FiltrosComuns';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz, type Cor } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * A FICHA DO FUNCIONÁRIO.
 *
 * O ecrã mais denso do RH: cinquenta campos, nove documentos e uma
 * fotografia. Em Blade eram 695 linhas de componente e dois blades; aqui é
 * uma lista, um formulário em cinco abas, uma ficha de leitura e a
 * importação de quem já está na casa noutro módulo.
 *
 * O QUE O ECRÃ NÃO FAZ: decidir. As listas, as escolhas, as permissões e a
 * validação vêm do servidor. Esconder um botão aqui é conveniência; a guarda
 * é a do `FuncionariosApiController`, que exige a permissão por verbo — ver a
 * lista é uma coisa, mexer no salário de alguém é outra.
 */

/** As cinco abas do formulário, pela ordem por que se preenche uma ficha. */
const ABAS = [
    { chave: 'pessoais', rotulo: 'Pessoais', icone: 'fa-user' },
    { chave: 'documentos', rotulo: 'Documentos', icone: 'fa-id-card' },
    { chave: 'morada', rotulo: 'Morada', icone: 'fa-location-dot' },
    { chave: 'vinculo', rotulo: 'Vínculo', icone: 'fa-briefcase' },
    { chave: 'remuneracao', rotulo: 'Remuneração', icone: 'fa-money-bill-wave' },
] as const;

/** Onde cada campo vive — é o que leva quem grava à aba do erro. */
const ABA_DO_CAMPO: Record<string, string> = {
    first_name: 'pessoais', last_name: 'pessoais', birth_date: 'pessoais', gender: 'pessoais',
    nif: 'pessoais', social_security_number: 'pessoais', email: 'pessoais', phone: 'pessoais', mobile: 'pessoais',
    address: 'morada', province: 'morada', city: 'morada',
    department_id: 'vinculo', position_id: 'vinculo', shift_id: 'vinculo', manager_id: 'vinculo',
    hire_date: 'vinculo', termination_date: 'vinculo', employment_type: 'vinculo', status: 'vinculo', notes: 'vinculo',
    salary: 'remuneracao', bonus: 'remuneracao', transport_allowance: 'remuneracao', meal_allowance: 'remuneracao',
    family_allowance: 'remuneracao', position_subsidy: 'remuneracao', performance_subsidy: 'remuneracao',
    bank_name: 'remuneracao', bank_account: 'remuneracao', iban: 'remuneracao', beneficiaries: 'remuneracao',
};

/** Os documentos com número e validade, na ordem em que se pedem. */
const DOCUMENTOS_COM_VALIDADE = [
    { chave: 'bi', rotulo: 'Bilhete de Identidade' },
    { chave: 'passport', rotulo: 'Passaporte' },
    { chave: 'work_permit', rotulo: 'Autorização de Trabalho' },
    { chave: 'residence_permit', rotulo: 'Autorização de Residência' },
    { chave: 'driver_license', rotulo: 'Carta de Condução' },
    { chave: 'health_insurance', rotulo: 'Seguro de Saúde' },
] as const;

type Valores = Record<string, unknown>;

export default function Funcionarios() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDeFuncionarios>({ procura: '', page: 1 });
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [aEditar, porAEditar] = useState<FichaDoFuncionario | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aVer, porAVer] = useState<LinhaDeFuncionario | null>(null);
    const [aApagar, porAApagar] = useState<LinhaDeFuncionario | null>(null);
    const [aImportar, porAImportar] = useState(false);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['rh', 'funcionarios', 'opcoes'], queryFn: funcionarios.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({
        queryKey: ['rh', 'funcionarios', filtros],
        queryFn: () => funcionarios.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['rh', 'funcionarios'] });

    const gravar = useMutation({
        mutationFn: (v: Valores) => (aEditar ? funcionarios.actualizar(aEditar.id, v) : funcionarios.guardar(v)),
        onSuccess: (r) => {
            invalidar();
            porRecado(r.message);
            porErros({});
            /*
             * DEPOIS DE CRIAR, O FORMULÁRIO FICA ABERTO NA FICHA NOVA.
             *
             * Os documentos e a fotografia só sobem quando a ficha já tem id;
             * fechar aqui obrigava a procurar a pessoa outra vez para lhe
             * carregar o BI.
             */
            porAEditar(r.documento);
            porFormulario(paraFormulario(r.documento));
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({
        mutationFn: (l: LinhaDeFuncionario) => funcionarios.eliminar(l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const abrirNovo = () => { porAEditar(null); porErros({}); porFormulario(fichaEmBranco()); };
    const abrirEdicao = async (l: LinhaDeFuncionario) => {
        const r = await funcionarios.abrir(l.id);
        porAEditar(r.documento);
        porErros({});
        porFormulario(paraFormulario(r.documento));
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Funcionários')}
                subtitulo={t('A ficha de cada pessoa — e o que a folha depois calcula')}
                icone="fa-users"
                cor="primaria"
                accoes={
                    o.permissoes.pode_criar && (
                        <>
                            <button type="button" onClick={() => porAImportar(true)} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                <i className="fas fa-file-import transition-transform duration-300 group-hover:-translate-y-0.5" aria-hidden="true" />
                                {t('Importar')}
                            </button>
                            <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                                {t('Novo Funcionário')}
                            </button>
                        </>
                    )
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error} />

            {/* AS CONTAGENS SÃO DE TODA A EMPRESA, e não da página à frente. */}
            <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', lista.isFetching && 'opacity-70')}>
                <CartaoNumero aspecto="claro" rotulo={t('Funcionários')} tom="indigo" icone="fa-users"
                    nota={t('nesta empresa')} valor={resumo === undefined ? '—' : resumo.total.toLocaleString(etiquetaIntl())} />
                <CartaoNumero aspecto="claro" rotulo={t('Activos')} tom="verde" icone="fa-user-check"
                    nota={t('ao serviço')} valor={resumo === undefined ? '—' : resumo.activos.toLocaleString(etiquetaIntl())} />
                <CartaoNumero aspecto="claro" rotulo={t('De licença')} tom="ambar" icone="fa-user-clock"
                    nota={t('ausentes com justificação')} valor={resumo === undefined ? '—' : resumo.de_licenca.toLocaleString(etiquetaIntl())} />
                {/* O AVISO QUE PAGA A RENDA DESTE ECRÃ: um BI caducado é uma
                    pessoa que não pode ser paga em condições, e ninguém vai a
                    cada ficha conferir sete datas. */}
                <CartaoNumero aspecto="claro" rotulo={t('Documentos a vencer')} tom={resumo && resumo.documentos_a_vencer > 0 ? 'vermelho' : 'cinza'}
                    icone="fa-triangle-exclamation" nota={t('nos próximos 60 dias')}
                    valor={resumo === undefined ? '—' : resumo.documentos_a_vencer.toLocaleString(etiquetaIntl())} />
            </div>

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <label className="block lg:col-span-2">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Nome, número, email, telefone ou NIF')} className={entrada} />
                    </label>

                    <Escolher rotulo={t('Departamento')} valor={filtros.departamento} opcoes={o.departamentos}
                        aoMudar={(v) => porFiltros((f) => ({ ...f, departamento: v, page: 1 }))} />
                    <Escolher rotulo={t('Cargo')} valor={filtros.cargo} opcoes={o.cargos}
                        aoMudar={(v) => porFiltros((f) => ({ ...f, cargo: v, page: 1 }))} />
                    <Escolher rotulo={t('Estado')} valor={filtros.estado} opcoes={o.estados}
                        aoMudar={(v) => porFiltros((f) => ({ ...f, estado: v, page: 1 }))} />
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas ? t(':quantos funcionário(s)', { quantos: contas.total.toLocaleString(etiquetaIntl()) }) : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))} />
                        <Botao altura="pequeno" icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>{t('Limpar')}</Botao>
                    </div>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                    <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                        <i className="fas fa-users text-4xl text-slate-300" aria-hidden="true" />
                    </div>
                    <p className="text-lg font-bold text-slate-800">{t('Nenhum funcionário com estes filtros')}</p>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('Limpe os filtros, ou registe o primeiro.')}</p>
                </div>
            ) : (
                <Cartao titulo={t('Funcionários')} icone="fa-list" semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[52rem] text-sm">
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-user mr-1.5 text-slate-400" aria-hidden="true" />{t('Funcionário')}</th>
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-building mr-1.5 text-slate-400" aria-hidden="true" />{t('Departamento')}</th>
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-phone mr-1.5 text-slate-400" aria-hidden="true" />{t('Contacto')}</th>
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-calendar mr-1.5 text-slate-400" aria-hidden="true" />{t('Admissão')}</th>
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-circle-info mr-1.5 text-slate-400" aria-hidden="true" />{t('Estado')}</th>
                                    <th className="px-4 py-3 text-right font-bold"><i className="fas fa-money-bill mr-1.5 text-slate-400" aria-hidden="true" />{t('Salário')}</th>
                                    <th className="px-4 py-3 text-right font-bold"><i className="fas fa-gear mr-1.5 text-slate-400" aria-hidden="true" />{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((l, i) => (
                                    <LinhaDaTabela
                                        key={l.id}
                                        i={i}
                                        l={l}
                                        estados={o.estados}
                                        podeEditar={o.permissoes.pode_editar}
                                        podeApagar={o.permissoes.pode_apagar}
                                        aoVer={() => porAVer(l)}
                                        aoEditar={() => void abrirEdicao(l)}
                                        aoApagar={() => porAApagar(l)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label={t('Páginas')}>
                    <Botao icone="fa-chevron-left" altura="pequeno" disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        {t('Página :pagina de :paginas', { pagina: contas.current_page, paginas: contas.last_page })}
                    </span>
                    <Botao icone="fa-chevron-right" altura="pequeno" disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                </nav>
            )}

            {formulario && (
                <FormularioDaFicha
                    o={o}
                    ficha={aEditar}
                    valores={formulario}
                    erros={erros}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); }}
                    aoGravar={() => gravar.mutate(formulario)}
                    aoTrocarFicheiros={(nova) => { porAEditar(nova); invalidar(); }}
                />
            )}

            <FichaDeLeitura linha={aVer} estados={o.estados} aoFechar={() => porAVer(null)} />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Remover funcionário')}
                icone="fa-user-minus"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Remover')}</Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('Vai remover :nome da lista.', { nome: aApagar?.nome ?? '' })}
                </p>
                {/* DIZ-SE O QUE ACONTECE MESMO: a ficha sai da lista, a linha
                    fica. As folhas antigas apontam para ela. */}
                <p className="mt-2 text-sm text-slate-500">
                    {t('A ficha deixa de aparecer, mas o histórico de folhas de pagamento continua a apontar para ela.')}
                </p>
            </Modal>

            {aImportar && (
                <Importacao
                    aoFechar={() => porAImportar(false)}
                    aoImportar={(m) => { porAImportar(false); porRecado(m); invalidar(); }}
                />
            )}
        </div>
    );
}

/* ─── A linha da tabela ─────────────────────────────────────────────── */

function LinhaDaTabela({ i, l, estados, podeEditar, podeApagar, aoVer, aoEditar, aoApagar }: {
    i: number;
    l: LinhaDeFuncionario;
    estados: OpcoesDoFuncionario['estados'];
    podeEditar: boolean;
    podeApagar: boolean;
    aoVer: () => void;
    aoEditar: () => void;
    aoApagar: () => void;
}) {
    const estado = estados.find((e) => e.valor === l.estado);

    return (
        <tr className="entra transition-all duration-200 hover:bg-indigo-50/60" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
            <td className="px-4 py-3">
                <span className="flex items-center gap-3">
                    <Retrato url={l.fotografia} nome={l.nome} />
                    <span className="min-w-0">
                        <span className="block truncate font-semibold text-slate-900">{l.nome}</span>
                        <span className="block font-mono text-xs text-slate-400">{l.numero}</span>
                    </span>
                </span>
            </td>
            <td className="px-4 py-3 text-slate-700">
                {l.departamento ?? <span className="text-slate-300">—</span>}
                {l.cargo && <span className="block text-xs text-slate-400">{l.cargo}</span>}
            </td>
            <td className="px-4 py-3 text-slate-600">
                {l.email && <span className="block truncate text-xs">{l.email}</span>}
                {l.telefone && <span className="block font-mono text-xs">{l.telefone}</span>}
                {!l.email && !l.telefone && <span className="text-slate-300">—</span>}
            </td>
            <td className="px-4 py-3 tabular-nums text-slate-600">{l.admissao ? data(l.admissao) : <span className="text-slate-300">—</span>}</td>
            <td className="px-4 py-3">
                <span className="flex flex-wrap items-center gap-1.5">
                    <Etiqueta cor={estado?.cor ?? 'neutra'}>{estado?.rotulo ?? l.estado}</Etiqueta>
                    {/* O crachá dos documentos a vencer, na linha de quem os tem. */}
                    {l.documentos_a_vencer > 0 && (
                        <Etiqueta cor="perigo" icone="fa-triangle-exclamation">
                            {t(':quantos documento(s)', { quantos: l.documentos_a_vencer })}
                        </Etiqueta>
                    )}
                </span>
            </td>
            <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                {l.salario > 0 ? kz(l.salario) : <span className="font-normal text-slate-300">—</span>}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center justify-end gap-1">
                    <button type="button" onClick={aoVer} title={t('Ver ficha')} aria-label={t('Ver ficha de :nome', { nome: l.nome })}
                        className={cls('p-2 text-indigo-600 transition-all duration-200 hover:scale-110 hover:bg-indigo-50 active:scale-100', RAIO, FOCO)}>
                        <i className="fas fa-eye" aria-hidden="true" />
                    </button>
                    <a href={`/hr/employees/${l.id}/sheet`} target="_blank" rel="noreferrer" title={t('Ficha em PDF')}
                        aria-label={t('Ficha de :nome em PDF', { nome: l.nome })}
                        className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50 active:scale-100', RAIO, FOCO)}>
                        <i className="fas fa-file-pdf" aria-hidden="true" />
                    </a>
                    {podeEditar && (
                        <button type="button" onClick={aoEditar} title={t('Editar')} aria-label={t('Editar :nome', { nome: l.nome })}
                            className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 hover:text-indigo-600 active:scale-100', RAIO, FOCO)}>
                            <i className="fas fa-pen" aria-hidden="true" />
                        </button>
                    )}
                    {podeApagar && (
                        <button type="button" onClick={aoApagar} title={t('Remover')} aria-label={t('Remover :nome', { nome: l.nome })}
                            className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50 active:scale-100', RAIO, FOCO)}>
                            <i className="fas fa-trash" aria-hidden="true" />
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

/**
 * O RETRATO — a fotografia, ou as iniciais.
 *
 * Uma coluna de quadrados cinzentos iguais não ajuda a encontrar ninguém; as
 * iniciais numa cor derivada do nome fazem a lista ler-se de relance.
 */
function Retrato({ url, nome, grande = false }: { url: string | null; nome: string; grande?: boolean }) {
    const tamanho = grande ? 'h-20 w-20 text-2xl' : 'h-9 w-9 text-xs';

    if (url) {
        return <img src={url} alt="" className={cls('flex-none rounded-full object-cover ring-2 ring-white', tamanho)} />;
    }

    const iniciais = nome.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('');
    const tons = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700', 'bg-sky-100 text-sky-700'];
    const tom = tons[[...nome].reduce((s, c) => s + c.charCodeAt(0), 0) % tons.length];

    return (
        <span aria-hidden="true" className={cls('grid flex-none place-items-center rounded-full font-bold', tamanho, tom)}>
            {iniciais || '?'}
        </span>
    );
}

function Escolher({ rotulo, valor, opcoes, aoMudar }: {
    rotulo: string;
    valor?: string;
    opcoes: Array<{ valor: string; rotulo: string }>;
    aoMudar: (v: string) => void;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</span>
            <select value={valor ?? ''} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                <option value="">{t('Todos')}</option>
                {opcoes.map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
            </select>
        </label>
    );
}

/* ─── O formulário, em cinco abas ───────────────────────────────────── */

function fichaEmBranco(): Valores {
    return {
        first_name: '', last_name: '', birth_date: '', gender: '', nif: '', social_security_number: '',
        email: '', phone: '', mobile: '', address: '', province: '', city: '',
        department_id: '', position_id: '', shift_id: '', manager_id: '',
        hire_date: new Date().toISOString().slice(0, 10), termination_date: '',
        employment_type: 'Contrato', status: 'active', notes: '',
        salary: '', bonus: '', transport_allowance: '', meal_allowance: '',
        family_allowance: '', position_subsidy: '', performance_subsidy: '',
        bank_name: '', bank_account: '', iban: '', beneficiaries: [],
        bi_number: '', bi_expiry_date: '', passport_number: '', passport_expiry_date: '',
        work_permit_number: '', work_permit_expiry_date: '', residence_permit_number: '', residence_permit_expiry_date: '',
        driver_license_number: '', driver_license_expiry_date: '', driver_license_category: '',
        health_insurance_number: '', health_insurance_expiry_date: '', health_insurance_provider: '',
        criminal_record_number: '', criminal_record_issue_date: '',
        contract_expiry_date: '', probation_end_date: '',
    };
}

/** A ficha do servidor no formato do formulário: nulo é caixa vazia. */
function paraFormulario(f: FichaDoFuncionario): Valores {
    const v = fichaEmBranco();

    for (const chave of Object.keys(v)) {
        if (chave === 'beneficiaries') {
            v[chave] = Array.isArray(f.beneficiaries) ? f.beneficiaries : [];
            continue;
        }

        const bruto = f[chave];
        v[chave] = bruto === null || bruto === undefined ? '' : String(bruto);
    }

    return v;
}

function FormularioDaFicha({ o, ficha, valores, erros, aGravar, erroGeral, aoMudar, aoFechar, aoGravar, aoTrocarFicheiros }: {
    o: OpcoesDoFuncionario;
    ficha: FichaDoFuncionario | null;
    valores: Valores;
    erros: Record<string, string[]>;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: Valores) => void;
    aoFechar: () => void;
    aoGravar: () => void;
    aoTrocarFicheiros: (nova: FichaDoFuncionario) => void;
}) {
    const [aba, porAba] = useState<string>('pessoais');

    const mudar = (chave: string, valor: unknown) => {
        const novo = { ...valores, [chave]: valor };

        // A morada concorda consigo própria: trocar de província limpa o
        // município, que já não pertence a ela.
        if (chave === 'province') novo.city = '';
        // E o cargo tem de pertencer ao departamento escolhido.
        if (chave === 'department_id') {
            const cargo = o.cargos.find((c) => c.valor === String(novo.position_id ?? ''));
            if (cargo && cargo.departamento !== null && String(cargo.departamento) !== String(valor)) novo.position_id = '';
        }

        aoMudar(novo);
    };

    /* QUANTOS ERROS TEM CADA ABA — é o ponto vermelho no separador. */
    const errosPorAba = ABAS.reduce<Record<string, number>>((conta, a) => {
        conta[a.chave] = Object.keys(erros).filter((campo) => {
            const raiz = campo.split('.')[0] ?? campo;

            return (ABA_DO_CAMPO[raiz] ?? 'documentos') === a.chave;
        }).length;

        return conta;
    }, {});

    /*
     * OS ERROS LEVAM À PRIMEIRA ABA QUE OS TEM.
     *
     * Um campo obrigatório por preencher numa aba escondida era um formulário
     * que se recusava a gravar sem dizer porquê — o defeito clássico deste
     * ecrã em Blade, que tinha um mapa inteiro de campo→aba só para isto.
     *
     * O SALTO É QUANDO OS ERROS CHEGAM, e não a seguir a carregar em gravar:
     * a resposta do servidor vem depois, e ler a contagem logo ali era ler a
     * de antes — sempre zero, e a aba nunca mudava.
     */
    useEffect(() => {
        const comErro = ABAS.find((a) => (errosPorAba[a.chave] ?? 0) > 0);

        if (comErro) porAba(comErro.chave);
        // O gatilho é a resposta do servidor; `errosPorAba` deriva dela.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [erros]);

    const cargosDoDepartamento = valores.department_id
        ? o.cargos.filter((c) => c.departamento === null || String(c.departamento) === String(valores.department_id))
        : o.cargos;

    const municipios = o.geografia.municipios[String(valores.province ?? '')] ?? [];

    const campo = (chave: string) => ({
        valor: valores[chave],
        erro: erros[chave],
        aoMudar: (v: unknown) => mudar(chave, v),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={ficha ? t('Editar funcionário') : t('Novo funcionário')}
            subtitulo={ficha ? `${ficha.numero} · ${valores.first_name} ${valores.last_name}` : t('Cinco separadores; só o nome é obrigatório para começar.')}
            icone="fa-user-pen"
            cor="primaria"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{ficha ? t('Fechar') : t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {ficha ? t('Guardar alterações') : t('Criar ficha')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroGeral} />

            <div className="space-y-4">
                <Separadores
                    abas={ABAS.map((a) => ({ ...a, rotulo: t(a.rotulo), erros: errosPorAba[a.chave] }))}
                    activa={aba}
                    aoMudar={porAba}
                />

                {/* ── Pessoais ── */}
                <PainelDoSeparador chave="pessoais" activa={aba}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2 flex items-center gap-4">
                            <Retrato url={ficha?.fotografia ?? null} nome={`${valores.first_name} ${valores.last_name}`} grande />
                            <div className="min-w-0">
                                <p className="text-sm font-semibold text-slate-700">{t('Fotografia')}</p>
                                {ficha ? (
                                    <FicheiroDaFicha
                                        rotulo={t('Escolher fotografia')}
                                        aceita="image/*"
                                        aoEscolher={(f) => funcionarios.fotografia(ficha.id, f).then((r) => aoTrocarFicheiros(r.documento))}
                                    />
                                ) : (
                                    <p className="mt-1 text-xs text-slate-400">{t('Grave a ficha primeiro — depois pode carregar a fotografia.')}</p>
                                )}
                            </div>
                        </div>

                        <Texto rotulo={t('Primeiro nome')} obrigatorio {...campo('first_name')} />
                        <Texto rotulo={t('Último nome')} obrigatorio {...campo('last_name')} />
                        <Data rotulo={t('Data de nascimento')} {...campo('birth_date')} />
                        <Lista rotulo={t('Género')} opcoes={o.generos} {...campo('gender')} />
                        <Texto rotulo={t('NIF')} {...campo('nif')} />
                        <Texto rotulo={t('Segurança Social')} ajuda={t('O número do INSS, que a folha declara.')} {...campo('social_security_number')} />
                        <Texto rotulo={t('Email')} tipo="email" {...campo('email')} />
                        <Texto rotulo={t('Telefone')} {...campo('phone')} />
                        <Texto rotulo={t('Telemóvel')} {...campo('mobile')} />
                    </div>
                </PainelDoSeparador>

                {/* ── Documentos ── */}
                <PainelDoSeparador chave="documentos" activa={aba}>
                    <div className="space-y-3">
                        {DOCUMENTOS_COM_VALIDADE.map((d) => (
                            <BlocoDeDocumento
                                key={d.chave}
                                titulo={t(d.rotulo)}
                                chave={d.chave}
                                ficha={ficha}
                                aoTrocarFicheiros={aoTrocarFicheiros}
                            >
                                <Texto rotulo={t('Número')} {...campo(`${d.chave}_number`)} />
                                <Data rotulo={t('Validade')} {...campo(`${d.chave}_expiry_date`)} />
                                {d.chave === 'driver_license' && (
                                    <Lista rotulo={t('Categoria')} opcoes={o.categorias_de_carta} {...campo('driver_license_category')} />
                                )}
                                {d.chave === 'health_insurance' && (
                                    <Texto rotulo={t('Seguradora')} {...campo('health_insurance_provider')} />
                                )}
                            </BlocoDeDocumento>
                        ))}

                        <BlocoDeDocumento titulo={t('Registo Criminal')} chave="criminal_record" ficha={ficha} aoTrocarFicheiros={aoTrocarFicheiros}>
                            <Texto rotulo={t('Número')} {...campo('criminal_record_number')} />
                            <Data rotulo={t('Data de emissão')} {...campo('criminal_record_issue_date')} />
                        </BlocoDeDocumento>

                        <BlocoDeDocumento titulo={t('Contrato')} chave="contract" ficha={ficha} aoTrocarFicheiros={aoTrocarFicheiros}>
                            <Data rotulo={t('Fim do contrato')} {...campo('contract_expiry_date')} />
                        </BlocoDeDocumento>

                        <BlocoDeDocumento titulo={t('Período Experimental')} chave="probation" ficha={ficha} aoTrocarFicheiros={aoTrocarFicheiros}>
                            <Data rotulo={t('Fim do período experimental')} {...campo('probation_end_date')} />
                        </BlocoDeDocumento>
                    </div>
                </PainelDoSeparador>

                {/* ── Morada ── */}
                <PainelDoSeparador chave="morada" activa={aba}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Morada')} erro={erros.address} className="sm:col-span-2">
                            <textarea rows={2} value={String(valores.address ?? '')} onChange={(e) => mudar('address', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                        <Campo etiqueta={t('Província')} erro={erros.province}>
                            <select value={String(valores.province ?? '')} onChange={(e) => mudar('province', e.target.value)} className={entrada}>
                                <option value="">—</option>
                                {o.geografia.provincias.map((p) => <option key={p} value={p}>{p}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Município')} erro={erros.city}>
                            {municipios.length > 0 ? (
                                <select value={String(valores.city ?? '')} onChange={(e) => mudar('city', e.target.value)} className={entrada}>
                                    <option value="">—</option>
                                    {municipios.map((m) => <option key={m} value={m}>{m}</option>)}
                                </select>
                            ) : (
                                <input value={String(valores.city ?? '')} onChange={(e) => mudar('city', e.target.value)} className={entrada} />
                            )}
                        </Campo>
                    </div>
                </PainelDoSeparador>

                {/* ── Vínculo ── */}
                <PainelDoSeparador chave="vinculo" activa={aba}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Lista rotulo={t('Departamento')} opcoes={o.departamentos} {...campo('department_id')} />
                        <Lista rotulo={t('Cargo')} opcoes={cargosDoDepartamento} {...campo('position_id')} />
                        <Lista rotulo={t('Turno')} opcoes={o.turnos} ajuda={t('É por ele que o atraso e a hora extra se medem.')} {...campo('shift_id')} />
                        <Lista rotulo={t('Chefia')} opcoes={o.chefias} {...campo('manager_id')} />
                        <Data rotulo={t('Admissão')} {...campo('hire_date')} />
                        <Data rotulo={t('Cessação')} ajuda={t('Só quando a pessoa sai.')} {...campo('termination_date')} />
                        <Lista rotulo={t('Tipo de vínculo')} opcoes={o.vinculos} obrigatorio {...campo('employment_type')} />
                        <Lista rotulo={t('Estado')} opcoes={o.estados} obrigatorio {...campo('status')} />
                        <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2">
                            <textarea rows={3} value={String(valores.notes ?? '')} onChange={(e) => mudar('notes', e.target.value)}
                                placeholder={t('Observações internas sobre esta pessoa…')} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                    </div>
                </PainelDoSeparador>

                {/* ── Remuneração ── */}
                <PainelDoSeparador chave="remuneracao" activa={aba}>
                    <div className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Dinheiro rotulo={t('Salário base (Kz)')} {...campo('salary')} />
                            <Dinheiro rotulo={t('Bónus (Kz)')} {...campo('bonus')} />
                            <Dinheiro rotulo={t('Subsídio de transporte (Kz)')} {...campo('transport_allowance')} />
                            <Dinheiro rotulo={t('Subsídio de alimentação (Kz)')} {...campo('meal_allowance')} />
                            {/* OS TRÊS QUE A FOLHA CALCULA E O ECRÃ NUNCA PEDIU. */}
                            <Dinheiro rotulo={t('Abono de família (Kz)')} {...campo('family_allowance')} />
                            <Dinheiro rotulo={t('Subsídio de cargo (Kz)')} {...campo('position_subsidy')} />
                            <Dinheiro rotulo={t('Subsídio de desempenho (Kz)')} {...campo('performance_subsidy')} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Banco')} erro={erros.bank_name}>
                                <select value={String(valores.bank_name ?? '')} onChange={(e) => mudar('bank_name', e.target.value)} className={entrada}>
                                    <option value="">—</option>
                                    {o.bancos.map((b) => <option key={b.valor} value={b.valor}>{b.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Texto rotulo={t('Conta bancária')} {...campo('bank_account')} />
                            <Texto rotulo={t('IBAN')} {...campo('iban')} />
                        </div>

                        <Beneficiarios
                            lista={(valores.beneficiaries as Beneficiario[]) ?? []}
                            erros={erros}
                            aoMudar={(b) => mudar('beneficiaries', b)}
                        />
                    </div>
                </PainelDoSeparador>
            </div>
        </Modal>
    );
}

/**
 * UM DOCUMENTO: o número, a validade e o ficheiro.
 *
 * O ficheiro só sobe depois de a ficha existir — antes disso não há onde o
 * pôr, e diz-se em vez de se oferecer um botão que não faz nada.
 */
function BlocoDeDocumento({ titulo, chave, ficha, aoTrocarFicheiros, children }: {
    titulo: string;
    chave: string;
    ficha: FichaDoFuncionario | null;
    aoTrocarFicheiros: (nova: FichaDoFuncionario) => void;
    children: React.ReactNode;
}) {
    const guardado = ficha?.documentos?.find((d) => d.chave === chave)?.url ?? null;

    return (
        <div className={cls('border border-slate-200 p-4 transition-colors hover:border-slate-300', RAIO)}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h4 className="text-sm font-bold text-slate-800">
                    <i className="fas fa-id-card mr-2 text-slate-400" aria-hidden="true" />
                    {titulo}
                </h4>

                <div className="flex items-center gap-2">
                    {guardado && (
                        <a href={guardado} target="_blank" rel="noreferrer"
                            className={cls('inline-flex items-center gap-1.5 border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition-all hover:-translate-y-0.5', RAIO, FOCO)}>
                            <i className="fas fa-paperclip" aria-hidden="true" />
                            {t('Ver ficheiro')}
                        </a>
                    )}
                    {ficha ? (
                        <>
                            <FicheiroDaFicha
                                rotulo={guardado ? t('Substituir') : t('Carregar')}
                                aceita=".pdf,.jpg,.jpeg,.png"
                                aoEscolher={(f) => funcionarios.documento(ficha.id, chave, f).then((r) => aoTrocarFicheiros(r.documento))}
                            />
                            {guardado && (
                                <button type="button"
                                    onClick={() => void funcionarios.apagarDocumento(ficha.id, chave).then((r) => aoTrocarFicheiros(r.documento))}
                                    title={t('Remover ficheiro')}
                                    className={cls('p-1.5 text-red-500 transition-all hover:scale-110 hover:bg-red-50', RAIO, FOCO)}>
                                    <i className="fas fa-trash text-xs" aria-hidden="true" />
                                </button>
                            )}
                        </>
                    ) : (
                        <span className="text-xs text-slate-400">{t('Grave a ficha para anexar')}</span>
                    )}
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">{children}</div>
        </div>
    );
}

/** Um botão que abre o selector de ficheiros — e mostra que está a subir. */
function FicheiroDaFicha({ rotulo, aceita, aoEscolher }: {
    rotulo: string;
    aceita: string;
    aoEscolher: (f: File) => Promise<unknown>;
}) {
    const [aSubir, porASubir] = useState(false);

    return (
        <label className={cls(
            'inline-flex cursor-pointer items-center gap-1.5 border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600',
            'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-400 hover:text-indigo-700',
            aSubir && 'pointer-events-none opacity-60',
            RAIO,
        )}>
            <i className={cls('fas', aSubir ? 'fa-spinner fa-spin' : 'fa-upload')} aria-hidden="true" />
            {aSubir ? t('A carregar…') : rotulo}
            <input
                type="file"
                accept={aceita}
                className="hidden"
                onChange={(e) => {
                    const f = e.target.files?.[0];
                    e.target.value = '';

                    if (!f) return;

                    porASubir(true);
                    void aoEscolher(f).finally(() => porASubir(false));
                }}
            />
        </label>
    );
}

/**
 * OS BENEFICIÁRIOS — quem recebe o que a lei manda pagar a quem fica.
 *
 * A base guardava a coluna e o ecrã nunca a ofereceu: as fichas ficavam todas
 * sem beneficiário, e é o campo que só faz falta no pior dia.
 */
function Beneficiarios({ lista, erros, aoMudar }: {
    lista: Beneficiario[];
    erros: Record<string, string[]>;
    aoMudar: (b: Beneficiario[]) => void;
}) {
    const mudar = (i: number, chave: keyof Beneficiario, valor: string) =>
        aoMudar(lista.map((b, j) => (j === i ? { ...b, [chave]: valor } : b)));

    return (
        <div className={cls('border border-slate-200 p-4', RAIO)}>
            <div className="mb-3 flex items-center justify-between">
                <h4 className="text-sm font-bold text-slate-800">
                    <i className="fas fa-user-shield mr-2 text-slate-400" aria-hidden="true" />
                    {t('Beneficiários')}
                </h4>
                <Botao altura="pequeno" icone="fa-plus" onClick={() => aoMudar([...lista, { nome: '', parentesco: '', contacto: '' }])}>
                    {t('Acrescentar')}
                </Botao>
            </div>

            {lista.length === 0 ? (
                <p className="py-3 text-center text-xs text-slate-400">{t('Sem beneficiários registados.')}</p>
            ) : (
                <div className="space-y-2">
                    {lista.map((b, i) => (
                        <div key={i} className="entra grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                            <input value={b.nome} onChange={(e) => mudar(i, 'nome', e.target.value)}
                                placeholder={t('Nome')} aria-label={t('Nome do beneficiário :n', { n: i + 1 })} className={entrada} />
                            <input value={b.parentesco ?? ''} onChange={(e) => mudar(i, 'parentesco', e.target.value)}
                                placeholder={t('Parentesco')} aria-label={t('Parentesco do beneficiário :n', { n: i + 1 })} className={entrada} />
                            <input value={b.contacto ?? ''} onChange={(e) => mudar(i, 'contacto', e.target.value)}
                                placeholder={t('Contacto')} aria-label={t('Contacto do beneficiário :n', { n: i + 1 })} className={entrada} />
                            <button type="button" onClick={() => aoMudar(lista.filter((_, j) => j !== i))}
                                aria-label={t('Remover beneficiário :n', { n: i + 1 })}
                                className={cls('p-2 text-red-500 transition-all hover:scale-110 hover:bg-red-50', RAIO, FOCO)}>
                                <i className="fas fa-trash" aria-hidden="true" />
                            </button>
                            {erros[`beneficiaries.${i}.nome`]?.[0] && (
                                <p role="alert" className="text-xs text-red-600 sm:col-span-4">
                                    {erros[`beneficiaries.${i}.nome`]?.[0]}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ─── Campos ────────────────────────────────────────────────────────── */

function Texto({ rotulo, valor, erro, aoMudar, obrigatorio, tipo = 'text', ajuda }: {
    rotulo: string; valor: unknown; erro?: string[]; aoMudar: (v: string) => void;
    obrigatorio?: boolean; tipo?: string; ajuda?: string;
}) {
    return (
        <Campo etiqueta={rotulo} erro={erro} obrigatorio={obrigatorio} ajuda={ajuda}>
            <input type={tipo} value={String(valor ?? '')} onChange={(e) => aoMudar(e.target.value)} className={entrada} />
        </Campo>
    );
}

function Data({ rotulo, valor, erro, aoMudar, ajuda }: {
    rotulo: string; valor: unknown; erro?: string[]; aoMudar: (v: string) => void; ajuda?: string;
}) {
    return (
        <Campo etiqueta={rotulo} erro={erro} ajuda={ajuda}>
            <input type="date" value={String(valor ?? '')} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'tabular-nums')} />
        </Campo>
    );
}

function Dinheiro({ rotulo, valor, erro, aoMudar }: {
    rotulo: string; valor: unknown; erro?: string[]; aoMudar: (v: string) => void;
}) {
    return (
        <Campo etiqueta={rotulo} erro={erro}>
            <input type="number" min="0" step="0.01" value={String(valor ?? '')} onChange={(e) => aoMudar(e.target.value)}
                className={cls(entrada, 'text-right tabular-nums')} />
        </Campo>
    );
}

function Lista({ rotulo, valor, erro, aoMudar, opcoes, obrigatorio, ajuda }: {
    rotulo: string; valor: unknown; erro?: string[]; aoMudar: (v: string) => void;
    opcoes: Array<{ valor: string; rotulo: string }>; obrigatorio?: boolean; ajuda?: string;
}) {
    return (
        <Campo etiqueta={rotulo} erro={erro} obrigatorio={obrigatorio} ajuda={ajuda}>
            <select value={String(valor ?? '')} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                {!obrigatorio && <option value="">—</option>}
                {opcoes.map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
            </select>
        </Campo>
    );
}

/* ─── A ficha de leitura ────────────────────────────────────────────── */

/**
 * VER SEM EDITAR — o modal que a lista em Blade tinha.
 *
 * Quem só quer conferir um contacto não abre o formulário, onde se estraga
 * uma ficha por engano.
 */
function FichaDeLeitura({ linha, estados, aoFechar }: {
    linha: LinhaDeFuncionario | null;
    estados: OpcoesDoFuncionario['estados'];
    aoFechar: () => void;
}) {
    const q = useQuery({
        queryKey: ['rh', 'funcionarios', 'ficha', linha?.id],
        queryFn: () => funcionarios.abrir(linha!.id),
        enabled: linha !== null,
    });

    if (!linha) return null;

    const f = q.data?.documento;
    const estado = estados.find((e) => e.valor === linha.estado);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={linha.nome}
            subtitulo={linha.numero}
            icone="fa-address-card"
            cor="primaria"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>
                    <a href={`/hr/employees/${linha.id}/sheet`} target="_blank" rel="noreferrer"
                        className={cls('inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl', RAIO)}>
                        <i className="fas fa-file-pdf" aria-hidden="true" />
                        {t('Ficha em PDF')}
                    </a>
                </>
            }
        >
            {q.isPending || !f ? (
                <Carregando />
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-4">
                        <Retrato url={linha.fotografia} nome={linha.nome} grande />
                        <div>
                            <p className="text-lg font-bold text-slate-900">{linha.nome}</p>
                            <p className="text-sm text-slate-500">{[linha.cargo, linha.departamento].filter(Boolean).join(' · ') || '—'}</p>
                            <span className="mt-1 inline-block"><Etiqueta cor={estado?.cor ?? 'neutra'}>{estado?.rotulo ?? linha.estado}</Etiqueta></span>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Seccao titulo={t('Contacto')} icone="fa-phone">
                            <Linha rotulo={t('Email')} valor={f.email} />
                            <Linha rotulo={t('Telefone')} valor={f.phone} />
                            <Linha rotulo={t('Telemóvel')} valor={f.mobile} />
                            <Linha rotulo={t('Morada')} valor={[f.address, f.city, f.province].filter(Boolean).join(', ')} />
                        </Seccao>

                        <Seccao titulo={t('Identidade')} icone="fa-id-card">
                            <Linha rotulo={t('NIF')} valor={f.nif} />
                            <Linha rotulo={t('BI')} valor={f.bi_number} />
                            <Linha rotulo={t('Segurança Social')} valor={f.social_security_number} />
                            <Linha rotulo={t('Data de nascimento')} valor={f.birth_date ? data(String(f.birth_date)) : null} />
                        </Seccao>

                        <Seccao titulo={t('Vínculo')} icone="fa-briefcase">
                            <Linha rotulo={t('Admissão')} valor={f.hire_date ? data(String(f.hire_date)) : null} />
                            <Linha rotulo={t('Tipo de vínculo')} valor={f.employment_type} />
                            <Linha rotulo={t('Cessação')} valor={f.termination_date ? data(String(f.termination_date)) : null} />
                        </Seccao>

                        <Seccao titulo={t('Remuneração')} icone="fa-money-bill-wave">
                            <Linha rotulo={t('Salário base')} valor={f.salary ? `${kz(Number(f.salary))} Kz` : null} />
                            <Linha rotulo={t('Subsídio de transporte')} valor={f.transport_allowance ? `${kz(Number(f.transport_allowance))} Kz` : null} />
                            <Linha rotulo={t('Subsídio de alimentação')} valor={f.meal_allowance ? `${kz(Number(f.meal_allowance))} Kz` : null} />
                            <Linha rotulo={t('Banco')} valor={[f.bank_name, f.iban].filter(Boolean).join(' · ')} />
                        </Seccao>
                    </div>

                    {/* OS DOCUMENTOS ANEXADOS, com a validade ao lado — é o que
                        se vem cá ver quando o crachá vermelho acende. */}
                    <Seccao titulo={t('Documentos')} icone="fa-paperclip">
                        <div className="grid gap-2 sm:grid-cols-2">
                            {f.documentos.map((d) => {
                                const validade = f[`${d.chave}_expiry_date`];

                                return (
                                    <div key={d.chave} className="flex items-center justify-between gap-2 py-1 text-sm">
                                        <span className="min-w-0 truncate text-slate-600">{d.rotulo}</span>
                                        <span className="flex flex-none items-center gap-2">
                                            {validade && <ValidadeDoDocumento valor={String(validade)} />}
                                            {d.url ? (
                                                <a href={d.url} target="_blank" rel="noreferrer" className="text-indigo-600 hover:underline">
                                                    <i className="fas fa-paperclip" aria-hidden="true" />
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-300">{t('sem ficheiro')}</span>
                                            )}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </Seccao>
                </div>
            )}
        </Modal>
    );
}

/** A validade com cor: vermelha se passou, âmbar se está perto. */
function ValidadeDoDocumento({ valor }: { valor: string }) {
    const dias = Math.round((new Date(valor).getTime() - Date.now()) / 86_400_000);

    return (
        <span className={cls(
            'rounded px-1.5 py-0.5 text-xs font-semibold tabular-nums',
            dias < 0 ? 'bg-red-100 text-red-700' : dias <= 60 ? 'bg-amber-100 text-amber-800' : 'text-slate-500',
        )}>
            {data(valor)}
        </span>
    );
}

function Seccao({ titulo, icone, children }: { titulo: string; icone: string; children: React.ReactNode }) {
    return (
        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
            <h4 className="mb-2 text-sm font-bold text-slate-800">
                <i className={cls('fas', icone, 'mr-2 text-slate-400')} aria-hidden="true" />
                {titulo}
            </h4>
            <div className="space-y-1">{children}</div>
        </section>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: unknown }) {
    return (
        <div className="flex items-baseline gap-2 text-sm">
            <span className="w-36 flex-none text-slate-500">{rotulo}</span>
            <span className="min-w-0 break-words font-medium text-slate-800">
                {valor ? String(valor) : <span className="font-normal text-slate-300">—</span>}
            </span>
        </div>
    );
}

/* ─── Importar de outros módulos ────────────────────────────────────── */

/**
 * QUEM JÁ ESTÁ NA CASA — técnicos da oficina, pessoal do hotel.
 *
 * Já foram registados uma vez. Reescrever nome, contacto e documento para os
 * pôr no RH é trabalho feito duas vezes, e duas fichas da mesma pessoa que
 * depois divergem.
 */
function Importacao({ aoFechar, aoImportar }: { aoFechar: () => void; aoImportar: (m: string) => void }) {
    const [origem, porOrigem] = useState<'tecnicos' | 'hotel'>('tecnicos');
    const [escolhidos, porEscolhidos] = useState<Set<number>>(new Set());

    const q = useQuery({ queryKey: ['rh', 'funcionarios', 'importaveis'], queryFn: funcionarios.importaveis });

    const importar = useMutation({
        mutationFn: () => funcionarios.importar(origem, [...escolhidos]),
        onSuccess: (r) => aoImportar(r.message),
    });

    const candidatos = (origem === 'tecnicos' ? q.data?.tecnicos : q.data?.hotel) ?? [];

    const alternar = (id: number) => porEscolhidos((antes) => {
        const novo = new Set(antes);
        novo.has(id) ? novo.delete(id) : novo.add(id);

        return novo;
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Importar funcionários')}
            subtitulo={t('De quem já está registado noutro módulo')}
            icone="fa-file-import"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={importar.isPending}
                        disabled={escolhidos.size === 0} onClick={() => importar.mutate()}>
                        {t('Importar :quantos', { quantos: escolhidos.size })}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={importar.error} />

            <div className="space-y-3">
                <div className="grid grid-cols-2 gap-2">
                    {([['tecnicos', t('Técnicos'), 'fa-screwdriver-wrench'], ['hotel', t('Pessoal do hotel'), 'fa-hotel']] as const).map(([chave, rotulo, icone]) => (
                        <button
                            key={chave}
                            type="button"
                            onClick={() => { porOrigem(chave); porEscolhidos(new Set()); }}
                            className={cls(
                                'flex items-center justify-center gap-2 border-2 px-3 py-2.5 text-sm font-semibold transition-all duration-200',
                                RAIO, FOCO,
                                origem === chave
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 text-slate-500 hover:border-slate-300',
                            )}
                        >
                            <i className={cls('fas', icone)} aria-hidden="true" />
                            {rotulo}
                        </button>
                    ))}
                </div>

                <div className={cls('max-h-64 divide-y divide-slate-100 overflow-y-auto border border-slate-200', RAIO)}>
                    {q.isPending ? (
                        <Carregando linhas={3} />
                    ) : candidatos.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-400">
                            {t('Não há ninguém por importar desta origem.')}
                        </p>
                    ) : (
                        candidatos.map((c, i) => (
                            <label key={c.id} style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                                className={cls('entra flex cursor-pointer items-center gap-3 px-3 py-2.5 text-sm transition-colors',
                                    escolhidos.has(c.id) ? 'bg-emerald-50/70' : 'hover:bg-slate-50')}>
                                <input type="checkbox" checked={escolhidos.has(c.id)} onChange={() => alternar(c.id)}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-semibold text-slate-800">{c.nome}</span>
                                    {c.nota && <span className="block truncate text-xs text-slate-400">{c.nota}</span>}
                                </span>
                            </label>
                        ))
                    )}
                </div>

                <p className="text-xs text-slate-500">
                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                    {t('Quem já tiver ficha com o mesmo email ou telefone é saltado — não se criam duas fichas da mesma pessoa.')}
                </p>
            </div>
        </Modal>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os funcionários')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
