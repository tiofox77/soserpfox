import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    contratos,
    type FichaDeContrato,
    type FiltrosDosContratos,
    type LinhaDeContrato,
    type OpcoesDosContratos,
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
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS CONTRATOS — o ecrã que nunca existiu.
 *
 * A tabela `hr_contracts` está no sistema desde o princípio e o
 * `PayrollService` lê-a ANTES da ficha do funcionário: o contrato activo
 * decide o salário que é pago. Não havia ecrã nenhum por onde o ver, criar ou
 * corrigir — uma linha errada aqui pagava mal a alguém todos os meses e não
 * havia sequer onde olhar.
 *
 * O CARTÃO «A TERMINAR» É A RAZÃO DE ISTO SE ABRIR. Um contrato a termo que
 * caduca sem ninguém dar por isso converte-se em permanente por lei — e é uma
 * decisão que a empresa devia tomar de olhos abertos, não por distracção.
 *
 * ACTIVAR UM TERMINA O ANTERIOR, e o formulário diz isso antes de gravar:
 * `activeContract` é um `hasOne` com `latest()`, e com dois contratos activos
 * o salário pago passava a depender da ordem de inserção.
 */

const TOM: Record<string, string> = {
    active: 'bom', expired: 'aviso', terminated: 'perigo', suspended: 'neutra',
};

const SINAL: Record<string, string> = {
    active: 'fa-circle-check', expired: 'fa-hourglass-end', terminated: 'fa-ban', suspended: 'fa-pause',
};

type Valores = Record<string, unknown>;

export default function Contratos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDosContratos>({ page: 1 });
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aCessar, porACessar] = useState<LinhaDeContrato | null>(null);
    const [aApagar, porAApagar] = useState<LinhaDeContrato | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['rh', 'contratos', 'opcoes'], queryFn: contratos.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({
        queryKey: ['rh', 'contratos', filtros],
        queryFn: () => contratos.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['rh', 'contratos'] });

    const gravar = useMutation({
        mutationFn: (v: Valores) => (aEditar ? contratos.actualizar(aEditar, v) : contratos.guardar(v)),
        onSuccess: (r) => { invalidar(); porFormulario(null); porAEditar(null); porErros({}); porRecado(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const cessar = useMutation({
        mutationFn: (v: { id: number; termination_date: string; termination_reason: string }) =>
            contratos.cessar(v.id, { termination_date: v.termination_date, termination_reason: v.termination_reason }),
        onSuccess: (r) => { invalidar(); porACessar(null); porRecado(r.message); },
    });

    const apagar = useMutation({
        mutationFn: (l: LinhaDeContrato) => contratos.eliminar(l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const resumo = lista.data?.resumo;

    const abrirNovo = () => {
        porAEditar(null);
        porErros({});
        porFormulario({
            employee_id: '', contract_type: 'Indeterminado', status: 'active',
            start_date: new Date().toISOString().slice(0, 10), end_date: '', trial_period_end: '',
            base_salary: '', food_allowance: '0', transport_allowance: '0', housing_allowance: '0', other_allowances: '0',
            payment_frequency: 'Mensal', weekly_hours: 40, work_start_time: '', work_end_time: '',
            has_health_insurance: false, has_life_insurance: false, vacation_days_per_year: 22,
            subject_to_irt: true, subject_to_inss: true, irt_percentage: '', notes: '',
        });
    };

    const abrirFicha = async (l: LinhaDeContrato) => {
        porAEditar(l.id);
        porErros({});

        const { documento } = await contratos.ficha(l.id);

        porFormulario({ ...documento, employee_id: String(documento.employee_id) } as Valores);
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Contratos')}
                subtitulo={t('O vínculo de cada pessoa — e o salário que a folha vai pagar')}
                icone="fa-file-signature"
                cor="teal"
                accoes={o.permissoes.pode_criar && (
                    <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                        {t('Novo Contrato')}
                    </button>
                )}
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <AvisoDeErro erro={cessar.error ?? apagar.error} />

            <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', lista.isFetching && 'opacity-70')}>
                <CartaoNumero aspecto="claro" rotulo={t('Contratos')} tom="indigo" icone="fa-file-signature"
                    nota={t('nesta empresa')} valor={(resumo?.total ?? 0).toLocaleString(etiquetaIntl())} />
                <CartaoNumero aspecto="claro" rotulo={t('Em vigor')} tom="verde" icone="fa-circle-check"
                    valor={(resumo?.em_vigor ?? 0).toLocaleString(etiquetaIntl())} />
                {/* O CARTÃO QUE FAZ ABRIR ESTE ECRÃ. */}
                <CartaoNumero aspecto="claro" rotulo={t('A terminar em 60 dias')} tom={resumo && resumo.a_terminar > 0 ? 'ambar' : 'cinza'}
                    icone="fa-hourglass-half" nota={t('renovar ou deixar caducar')}
                    valor={(resumo?.a_terminar ?? 0).toLocaleString(etiquetaIntl())} />
                <CartaoNumero aspecto="claro" rotulo={t('Massa salarial contratada')} tom="teal" icone="fa-money-bill-wave"
                    sufixo="Kz" valor={kz(resumo?.massa_salarial ?? 0)} />
            </div>

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input type="search" value={filtros.procura ?? ''} placeholder={t('Número, nome ou nº de funcionário')}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} className={entrada} />
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Estado')}</span>
                        <select value={filtros.estado ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Tipo')}</span>
                        <select value={filtros.tipo ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Funcionário')}</span>
                        <select value={filtros.funcionario ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, funcionario: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.funcionarios.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                        </select>
                    </label>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                    <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                        <i className="fas fa-file-signature text-4xl text-slate-300" aria-hidden="true" />
                    </div>
                    <p className="text-lg font-bold text-slate-800">{t('Nenhum contrato com estes filtros')}</p>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                        {t('O contrato activo decide o salário que a folha paga. Registe o de cada pessoa.')}
                    </p>
                </div>
            ) : (
                <Cartao titulo={t('Contratos')} icone="fa-list" semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[54rem] text-sm">
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <th className="px-4 py-3 font-bold">{t('Contrato')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Funcionário')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Tipo')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Vigência')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Salário base')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Estado')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((l, i) => (
                                    <tr key={l.id} className="entra transition-all duration-200 hover:bg-teal-50/50"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-3 font-mono text-xs font-semibold text-slate-700">{l.numero}</td>
                                        <td className="px-4 py-3">
                                            <span className="block font-semibold text-slate-900">{l.funcionario}</span>
                                            {l.numero_do_funcionario && (
                                                <span className="block font-mono text-xs text-slate-400">{l.numero_do_funcionario}</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{t(l.tipo)}</td>
                                        <td className="px-4 py-3">
                                            <span className="block tabular-nums text-slate-700">
                                                {l.inicio ? data(l.inicio) : '—'} → {l.fim ? data(l.fim) : t('sem termo')}
                                            </span>
                                            <Vigencia l={l} />
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                            {kz(l.base)}
                                            {l.total > l.base && (
                                                <span className="block text-[10px] font-normal text-slate-400">
                                                    {t('com subsídios')}: {kz(l.total)}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Etiqueta cor={(TOM[l.estado] ?? 'neutra') as never} icone={SINAL[l.estado]}>
                                                {o.estados.find((e) => e.valor === l.estado)?.rotulo ?? l.estado}
                                            </Etiqueta>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {o.permissoes.pode_editar && (
                                                    <button type="button" onClick={() => void abrirFicha(l)} title={t('Abrir')}
                                                        aria-label={t('Abrir o contrato :n', { n: l.numero })}
                                                        className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 hover:text-teal-700', RAIO, FOCO)}>
                                                        <i className="fas fa-pen" aria-hidden="true" />
                                                    </button>
                                                )}
                                                {o.permissoes.pode_editar && l.estado === 'active' && (
                                                    <button type="button" onClick={() => porACessar(l)} title={t('Cessar')}
                                                        aria-label={t('Cessar o contrato :n', { n: l.numero })}
                                                        className={cls('p-2 text-amber-600 transition-all duration-200 hover:scale-110 hover:bg-amber-50', RAIO, FOCO)}>
                                                        <i className="fas fa-file-circle-xmark" aria-hidden="true" />
                                                    </button>
                                                )}
                                                {o.permissoes.pode_eliminar && (
                                                    <button type="button" onClick={() => porAApagar(l)} title={t('Eliminar')}
                                                        aria-label={t('Eliminar o contrato :n', { n: l.numero })}
                                                        className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50', RAIO, FOCO)}>
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {formulario && (
                <Formulario
                    o={o}
                    v={formulario}
                    erros={erros}
                    aEditar={aEditar !== null}
                    aGravar={gravar.isPending}
                    erro={gravar.error}
                    aoMudar={(campo, valor) => porFormulario((f) => ({ ...f!, [campo]: valor }))}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            <Cessacao
                l={aCessar}
                aTrabalhar={cessar.isPending}
                aoFechar={() => porACessar(null)}
                aoCessar={(d, motivo) => aCessar && cessar.mutate({ id: aCessar.id, termination_date: d, termination_reason: motivo })}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar contrato')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Eliminar')}</Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">{t('Vai eliminar o contrato :n de :nome.', { n: aApagar?.numero ?? '', nome: aApagar?.funcionario ?? '' })}</p>
                <p className="mt-2 text-sm text-slate-500">
                    {t('Só se elimina um contrato que ainda não começou. Um que esteve em vigor explica salários já pagos — esse cessa-se.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

/** Quanto falta para o fim — e o aviso quando falta pouco. */
function Vigencia({ l }: { l: LinhaDeContrato }) {
    if (l.dias_para_o_fim === null || l.estado !== 'active') return null;

    const dias = l.dias_para_o_fim;

    if (dias < 0) {
        return <span className="text-xs font-bold text-red-600">{t('já passou do fim')}</span>;
    }

    if (dias <= 60) {
        return (
            <span className="text-xs font-bold text-amber-700">
                <i className="fas fa-hourglass-half mr-1" aria-hidden="true" />
                {t('faltam :n dias', { n: dias })}
            </span>
        );
    }

    return <span className="text-xs text-slate-400">{t('faltam :n dias', { n: dias })}</span>;
}

function Formulario({ o, v, erros, aEditar, aGravar, erro, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDosContratos;
    v: Valores;
    erros: Record<string, string[]>;
    aEditar: boolean;
    aGravar: boolean;
    erro: unknown;
    aoMudar: (campo: string, valor: unknown) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const [aba, porAba] = useState('vinculo');

    const texto = (campo: string) => String(v[campo] ?? '');
    const ligado = (campo: string) => v[campo] === true;

    const aTermo = v.contract_type === 'Determinado' || v.contract_type === 'Estágio';
    const escolhido = o.funcionarios.find((f) => f.valor === texto('employee_id'));

    const ABA_DO_CAMPO: Record<string, string> = {
        employee_id: 'vinculo', contract_type: 'vinculo', status: 'vinculo',
        start_date: 'vinculo', end_date: 'vinculo', trial_period_end: 'vinculo',
        base_salary: 'dinheiro', food_allowance: 'dinheiro', transport_allowance: 'dinheiro',
        housing_allowance: 'dinheiro', other_allowances: 'dinheiro', payment_frequency: 'dinheiro',
        subject_to_irt: 'dinheiro', subject_to_inss: 'dinheiro', irt_percentage: 'dinheiro',
        weekly_hours: 'condicoes', work_start_time: 'condicoes', work_end_time: 'condicoes',
        vacation_days_per_year: 'condicoes', has_health_insurance: 'condicoes',
        has_life_insurance: 'condicoes', notes: 'condicoes',
    };

    // A ABA ONDE ESTÁ O ERRO — senão o formulário recusa gravar e o campo
    // vermelho está escondido na aba que não se vê.
    const abasComErro = Object.keys(erros).reduce<Record<string, number>>((c, campo) => {
        const aba = ABA_DO_CAMPO[campo] ?? 'vinculo';
        c[aba] = (c[aba] ?? 0) + 1;

        return c;
    }, {});

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={aEditar ? t('Editar contrato') : t('Novo contrato')}
            subtitulo={t('O contrato activo decide o salário que a folha paga')}
            icone="fa-file-signature"
            cor="teal"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <Separadores
                abas={[
                    { chave: 'vinculo', rotulo: t('Vínculo'), icone: 'fa-file-signature', erros: abasComErro.vinculo },
                    { chave: 'dinheiro', rotulo: t('Remuneração'), icone: 'fa-money-bill-wave', erros: abasComErro.dinheiro },
                    { chave: 'condicoes', rotulo: t('Condições'), icone: 'fa-clock', erros: abasComErro.condicoes },
                ]}
                activa={aba}
                aoMudar={porAba}
            />

            <PainelDoSeparador chave="vinculo" activa={aba}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Funcionário')} erro={erros.employee_id} obrigatorio className="sm:col-span-2">
                        <select value={texto('employee_id')} disabled={aEditar}
                            onChange={(e) => {
                                const f = o.funcionarios.find((x) => x.valor === e.target.value);
                                // O SALÁRIO DA FICHA PROPOSTO: escrever outro
                                // há-de ser uma decisão, não um descuido de
                                // quem não sabia qual era.
                                aoMudar('employee_id', e.target.value);
                                if (f && ! v.base_salary) aoMudar('base_salary', String(f.salario));
                            }}
                            className={cls(entrada, 'disabled:bg-slate-100')}>
                            <option value="">{t('Escolher…')}</option>
                            {o.funcionarios.map((f) => (
                                <option key={f.valor} value={f.valor}>{f.rotulo}{f.nota ? ` · ${f.nota}` : ''}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Tipo de contrato')} erro={erros.contract_type} obrigatorio>
                        <select value={texto('contract_type')} onChange={(e) => aoMudar('contract_type', e.target.value)} className={entrada}>
                            {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Estado')} erro={erros.status} obrigatorio>
                        <select value={texto('status')} onChange={(e) => aoMudar('status', e.target.value)} className={entrada}>
                            {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Início')} erro={erros.start_date} obrigatorio>
                        <input type="date" value={texto('start_date')} onChange={(e) => aoMudar('start_date', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Campo
                        etiqueta={t('Fim')}
                        erro={erros.end_date}
                        obrigatorio={aTermo}
                        ajuda={aTermo ? t('Um contrato a termo ou de estágio tem de dizer quando acaba.') : t('Vazio num contrato sem termo.')}
                    >
                        <input type="date" value={texto('end_date')} onChange={(e) => aoMudar('end_date', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Fim do período experimental')} erro={erros.trial_period_end}>
                        <input type="date" value={texto('trial_period_end')} onChange={(e) => aoMudar('trial_period_end', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    {/* O AVISO, ANTES DE ACONTECER. */}
                    {v.status === 'active' && escolhido && (
                        <div className={cls('border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-2', RAIO)}>
                            <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                            {t('Ao gravar em vigor, qualquer outro contrato activo de :nome passa a caducado na véspera desta data. Há um contrato activo por pessoa — com dois, o salário pago dependia da ordem de inserção.', { nome: escolhido.rotulo })}
                        </div>
                    )}
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="dinheiro" activa={aba}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Salário base')} erro={erros.base_salary} obrigatorio
                        ajuda={t('É este que a folha usa — vem antes do salário da ficha do funcionário.')}>
                        <input type="number" step="0.01" min="0" value={texto('base_salary')}
                            onChange={(e) => aoMudar('base_salary', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Periodicidade do pagamento')} erro={erros.payment_frequency} obrigatorio>
                        <select value={texto('payment_frequency')} onChange={(e) => aoMudar('payment_frequency', e.target.value)} className={entrada}>
                            {o.periodicidades.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    {([
                        ['food_allowance', t('Subsídio de alimentação')],
                        ['transport_allowance', t('Subsídio de transporte')],
                        ['housing_allowance', t('Subsídio de habitação')],
                        ['other_allowances', t('Outros subsídios')],
                    ] as const).map(([campo, rotulo]) => (
                        <Campo key={campo} etiqueta={rotulo} erro={erros[campo]}>
                            <input type="number" step="0.01" min="0" value={texto(campo)}
                                onChange={(e) => aoMudar(campo, e.target.value)} className={cls(entrada, 'tabular-nums')} />
                        </Campo>
                    ))}

                    <Interruptor rotulo={t('Sujeito a IRT')} ligado={ligado('subject_to_irt')} aoMudar={(x) => aoMudar('subject_to_irt', x)} />
                    <Interruptor rotulo={t('Sujeito a INSS')} ligado={ligado('subject_to_inss')} aoMudar={(x) => aoMudar('subject_to_inss', x)} />

                    <Campo etiqueta={t('Taxa de IRT fixa (%)')} erro={erros.irt_percentage}
                        ajuda={t('Vazio para usar os escalões. Só para os casos em que o contrato fixa uma taxa.')}>
                        <input type="number" step="0.01" min="0" max="100" value={texto('irt_percentage')}
                            onChange={(e) => aoMudar('irt_percentage', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <div className={cls('flex items-center justify-between border border-slate-200 bg-slate-50 px-4 py-3 sm:col-span-2', RAIO)}>
                        <span className="text-sm font-semibold text-slate-600">{t('Total contratado')}</span>
                        <span className="text-lg font-bold tabular-nums text-emerald-700">
                            {kz(['base_salary', 'food_allowance', 'transport_allowance', 'housing_allowance', 'other_allowances']
                                .reduce((s, c) => s + (Number(v[c]) || 0), 0))} <span className="text-xs font-normal text-slate-400">Kz</span>
                        </span>
                    </div>
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="condicoes" activa={aba}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Horas por semana')} erro={erros.weekly_hours} obrigatorio>
                        <input type="number" min="1" max="60" value={texto('weekly_hours')}
                            onChange={(e) => aoMudar('weekly_hours', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Dias de férias por ano')} erro={erros.vacation_days_per_year} obrigatorio>
                        <input type="number" min="0" max="60" value={texto('vacation_days_per_year')}
                            onChange={(e) => aoMudar('vacation_days_per_year', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Entrada')} erro={erros.work_start_time}>
                        <input type="time" value={texto('work_start_time')} onChange={(e) => aoMudar('work_start_time', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Campo etiqueta={t('Saída')} erro={erros.work_end_time}>
                        <input type="time" value={texto('work_end_time')} onChange={(e) => aoMudar('work_end_time', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                    </Campo>

                    <Interruptor rotulo={t('Tem seguro de saúde')} ligado={ligado('has_health_insurance')} aoMudar={(x) => aoMudar('has_health_insurance', x)} />
                    <Interruptor rotulo={t('Tem seguro de vida')} ligado={ligado('has_life_insurance')} aoMudar={(x) => aoMudar('has_life_insurance', x)} />

                    <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2">
                        <textarea rows={3} value={texto('notes')} onChange={(e) => aoMudar('notes', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                </div>
            </PainelDoSeparador>
        </Modal>
    );
}

function Interruptor({ rotulo, ligado, aoMudar }: { rotulo: string; ligado: boolean; aoMudar: (v: boolean) => void }) {
    return (
        <label className={cls('flex cursor-pointer items-center justify-between border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-indigo-300', RAIO)}>
            <span className="text-sm font-semibold text-slate-700">{rotulo}</span>
            <input
                type="checkbox"
                checked={ligado}
                onChange={(e) => aoMudar(e.target.checked)}
                className={cls('h-5 w-9 cursor-pointer appearance-none rounded-full bg-slate-300 transition-colors checked:bg-indigo-600',
                    'relative after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform checked:after:translate-x-4',
                    FOCO)}
            />
        </label>
    );
}

/**
 * CESSAR — e não apagar.
 *
 * Um contrato cessado é o registo de um vínculo que existiu, e a folha dos
 * meses em que ele valeu foi calculada por ele.
 */
function Cessacao({ l, aTrabalhar, aoFechar, aoCessar }: {
    l: LinhaDeContrato | null;
    aTrabalhar: boolean;
    aoFechar: () => void;
    aoCessar: (data: string, motivo: string) => void;
}) {
    const [quando, porQuando] = useState('');
    const [motivo, porMotivo] = useState('');

    return (
        <Modal
            aberto={l !== null}
            aoFechar={() => { porQuando(''); porMotivo(''); aoFechar(); }}
            titulo={t('Cessar o contrato')}
            subtitulo={l?.numero}
            icone="fa-file-circle-xmark"
            cor="aviso"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={() => { porQuando(''); porMotivo(''); aoFechar(); }}>{t('Cancelar')}</Botao>
                    <Botao cor="aviso" tom="solida" icone="fa-check" aTrabalhar={aTrabalhar}
                        disabled={! quando || motivo.trim().length === 0}
                        onClick={() => aoCessar(quando, motivo)}>
                        {t('Cessar')}
                    </Botao>
                </>
            }
        >
            <p className="mb-4 text-sm text-slate-700">
                {t('Vai cessar o contrato de :nome. O contrato fica no registo — é ele que explica os salários já pagos.', { nome: l?.funcionario ?? '' })}
            </p>

            <div className="space-y-4">
                <Campo etiqueta={t('Data da cessação')} obrigatorio>
                    <input type="date" value={quando} onChange={(e) => porQuando(e.target.value)} className={cls(entrada, 'tabular-nums')} />
                </Campo>

                <Campo etiqueta={t('Motivo')} obrigatorio ajuda={t('Fica escrito no contrato, e é o que se lê daqui a dois anos.')}>
                    <textarea rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                </Campo>
            </div>
        </Modal>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os contratos')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
