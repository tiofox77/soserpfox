import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Bem, OpcoesDoImobilizado } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { PorPagina } from '@/ui/FiltrosComuns';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * O IMOBILIZADO.
 *
 * O ECRÃ ANTIGO ERA UMA FACHADA. O formulário estava todo lá e o gravar dizia
 * «Ativo salvo com sucesso! (Funcionalidade completa será implementada em
 * breve)» — não gravava nada. A lista era um paginador vazio construído à mão, os
 * quatro totais eram zeros literais, e «Calcular Depreciações» flashava outra
 * promessa. As tabelas existiam desde 2025 e nunca receberam uma linha: quem lá
 * entrasse registava bens que desapareciam sem aviso.
 *
 * O REGISTO É AGORA A SÉRIO, e as amortizações calculam-se de verdade. CALCULAR
 * NÃO É LANÇAR: as linhas nascem em rascunho e é um segundo gesto que as mete na
 * contabilidade — débito no gasto, crédito nas amortizações acumuladas, pela
 * porta única dos lançamentos.
 */
export default function Imobilizado() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ procura?: string; estado?: string; categoria?: number | ''; por_pagina?: number; page?: number }>({
        por_pagina: 25, page: 1,
    });
    const [recado, porRecado] = useRecadoNoCanto('');
    const [modal, porModal] = useState<{ aberto: boolean; id: number | null }>({ aberto: false, id: null });
    const [aVer, porAVer] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Bem | null>(null);
    const [aCalcular, porACalcular] = useState(false);

    const opcoes = useQuery({
        queryKey: ['contabilidade', 'imobilizado', 'opcoes'],
        queryFn: contabilidade.imobilizado.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['contabilidade', 'imobilizado', filtros],
        queryFn: () => contabilidade.imobilizado.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade'] });
    }

    const apagar = useMutation({
        mutationFn: (b: Bem) => contabilidade.imobilizado.apagar(b.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o imobilizado')}</h2>
                <p className="text-sm text-red-800">
                    {opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const mudar = (campos: Partial<typeof filtros>) => porFiltros((f) => ({ ...f, ...campos, page: 1 }));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Imobilizado')}
                subtitulo={t('Os bens da empresa e as suas amortizações')}
                icone="fa-building-columns"
                cor="roxo"
                accoes={
                    o.permissoes.gerir && (
                        <>
                            <a href="/accounting/fixed-asset-categories" className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-layer-group" aria-hidden="true" />
                                {t('Famílias')}
                            </a>
                            <button type="button" onClick={() => porACalcular(true)} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-calculator" aria-hidden="true" />
                                {t('Calcular amortizações')}
                            </button>
                            <button type="button" onClick={() => porModal({ aberto: true, id: null })} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo Bem')}
                            </button>
                        </>
                    )
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <ErroDaAccao erro={apagar.error} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Bens')} valor={resumo.bens.toLocaleString('pt-PT')} icone="fa-building-columns" tom="roxo" aspecto="claro" />
                    <CartaoNumero rotulo={t('Valor de aquisição')} valor={kz(resumo.aquisicao)} sufixo="Kz" icone="fa-cart-shopping" tom="azul" aspecto="claro" />
                    <CartaoNumero rotulo={t('Amortizado')} valor={kz(resumo.amortizado)} sufixo="Kz" icone="fa-arrow-trend-down" tom="ambar" aspecto="claro" />
                    <CartaoNumero rotulo={t('Valor líquido')} valor={kz(resumo.liquido)} sufixo="Kz" icone="fa-scale-balanced" tom="verde" aspecto="claro" nota={t('É o que entra no balanço')} />
                </div>
            )}

            {/* AS AMORTIZAÇÕES POR LANÇAR são trabalho a meio: calculadas e
                fora da contabilidade. */}
            {(resumo?.por_lancar ?? 0) > 0 && (
                <div className={cls('border-2 border-amber-300 bg-amber-50 px-5 py-3.5 text-sm text-amber-900', RAIO)} role="status">
                    <i className="fas fa-calculator mr-2" aria-hidden="true" />
                    {t('Há :n amortização(ões) calculada(s) e ainda fora da contabilidade. Abra a ficha do bem para as lançar.', {
                        n: resumo?.por_lancar ?? 0,
                    })}
                </div>
            )}

            <Cartao titulo={t('Filtrar')} icone="fa-filter">
                <div className="grid gap-4 md:grid-cols-3">
                    <Campo etiqueta={t('Pesquisar')}>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => mudar({ procura: e.target.value })}
                            placeholder={t('Código, nome, série ou localização…')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Estado')}>
                        <select value={filtros.estado ?? ''} onChange={(e) => mudar({ estado: e.target.value })} className={entrada}>
                            <option value="">{t('Todos os estados')}</option>
                            {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Família')}>
                        <select
                            value={filtros.categoria ?? ''}
                            onChange={(e) => mudar({ categoria: e.target.value ? Number(e.target.value) : '' })}
                            className={entrada}
                        >
                            <option value="">{t('Todas')}</option>
                            {o.categorias.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>
            </Cartao>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-gradient-to-r from-purple-50 to-pink-50">
                            <tr>
                                {[t('Código'), t('Nome'), t('Família')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-purple-700">{c}</th>
                                ))}
                                {[t('Aquisição'), t('Amortizado'), t('Valor líquido')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-purple-700">{c}</th>
                                ))}
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-purple-700">{t('Estado')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-purple-700">{t('Ações')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.isPending && (
                                <tr><td colSpan={8} className="px-4 py-8"><Carregando linhas={5} /></td></tr>
                            )}

                            {!lista.isPending && linhas.length === 0 && (
                                <tr>
                                    <td colSpan={8}>
                                        <SemNada
                                            icone="fa-building-columns"
                                            titulo={t('Ainda não há bens registados')}
                                            frase={o.permissoes.gerir
                                                ? t('Registe o primeiro bem: o valor de aquisição, a vida útil e as três contas por onde a amortização se lança.')
                                                : t('Peça a quem gere o imobilizado para registar os bens.')}
                                        />
                                    </td>
                                </tr>
                            )}

                            {linhas.map((b, i) => (
                                <tr key={b.id} style={cascata(i)} className="entra transition-colors hover:bg-purple-50/50">
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{b.codigo}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-semibold text-slate-900">{b.nome}</p>
                                        <p className="truncate text-xs text-slate-400">
                                            {[b.serie, b.localizacao].filter(Boolean).join(' · ') || b.metodo_rotulo}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{b.categoria ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">
                                        {kz(b.valor)}
                                        <span className="block text-xs text-slate-400">{b.aquisicao}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-amber-700">
                                        {kz(b.amortizado)}
                                        {b.amortizacoes > 0 && (
                                            <span className="block text-xs text-slate-400">
                                                {t(':n de :total lançadas', { n: b.amortizacoes_lancadas, total: b.amortizacoes })}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900">{kz(b.liquido)}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center">
                                        <Etiqueta
                                            cor={b.estado === 'active' ? 'bom' : b.estado === 'fully_depreciated' ? 'primaria' : 'neutra'}
                                            ponto
                                        >
                                            {b.estado_rotulo}
                                        </Etiqueta>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => porAVer(b.id)}
                                                title={t('Ver as amortizações')}
                                                aria-label={t('Ver as amortizações de :bem', { bem: b.nome })}
                                                className={cls(BOTAO_DE_ACCAO, 'border-cyan-200 bg-cyan-50 text-cyan-700')}
                                            >
                                                <i className="fas fa-list-ol" aria-hidden="true" />
                                            </button>

                                            {o.permissoes.gerir && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => porModal({ aberto: true, id: b.id })}
                                                        title={t('Editar')}
                                                        aria-label={t('Editar :bem', { bem: b.nome })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-blue-200 bg-blue-50 text-blue-700')}
                                                    >
                                                        <i className="fas fa-edit" aria-hidden="true" />
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() => porAApagar(b)}
                                                        title={t('Eliminar')}
                                                        aria-label={t('Eliminar :bem', { bem: b.nome })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                    >
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* O «Por página» fica à vista mesmo com uma página só. */}
                <Paginacao
                    pagina={contas?.current_page ?? 1}
                    ultima={contas?.last_page ?? 1}
                    aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                    total={contas?.total}
                    de={contas?.from}
                    ate={contas?.to}
                    aCarregar={lista.isFetching}
                    extra={<PorPagina valor={filtros.por_pagina} aoMudar={(n) => mudar({ por_pagina: n })} />}
                />
            </section>

            <ModalDoBem
                aberto={modal.aberto}
                id={modal.id}
                o={o}
                aoFechar={() => porModal({ aberto: false, id: null })}
                aoGravar={(mensagem) => {
                    porModal({ aberto: false, id: null });
                    feito(mensagem);
                }}
            />

            <FichaDoBemModal
                id={aVer}
                podeLancar={o.permissoes.gerir && o.permissoes.lancar}
                aoFechar={() => porAVer(null)}
                aoLancar={(mensagem) => feito(mensagem)}
            />

            <ModalDeCalcular
                aberto={aCalcular}
                aoFechar={() => porACalcular(false)}
                aoCalcular={(mensagem) => {
                    porACalcular(false);
                    feito(mensagem);
                }}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar bem')}
                subtitulo={aApagar ? `${aApagar.codigo} · ${aApagar.nome}` : undefined}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />

                <p className="text-sm text-slate-700">
                    {t('O bem e as amortizações em rascunho desaparecem.')}
                </p>

                {(aApagar?.amortizacoes_lancadas ?? 0) > 0 && (
                    <div className={cls('mt-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Este bem tem :n amortização(ões) já lançada(s) — o servidor vai recusar. Marque-o como vendido ou abatido em vez de o apagar.', {
                            n: aApagar?.amortizacoes_lancadas ?? 0,
                        })}
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── A janela do bem ─────────────────────────────────────────────────── */

type Formulario = {
    code: string; name: string; description: string; category_id: string;
    account_id: string; depreciation_account_id: string; accumulated_depreciation_account_id: string;
    acquisition_date: string; acquisition_value: string; residual_value: string;
    useful_life_years: string; depreciation_method: string; depreciation_rate: string;
    status: string; location: string; serial_number: string;
    disposal_date: string; disposal_value: string;
};

const VAZIO: Formulario = {
    code: '', name: '', description: '', category_id: '',
    account_id: '', depreciation_account_id: '', accumulated_depreciation_account_id: '',
    acquisition_date: new Date().toISOString().slice(0, 10), acquisition_value: '', residual_value: '0',
    useful_life_years: '5', depreciation_method: 'linear', depreciation_rate: '',
    status: 'active', location: '', serial_number: '',
    disposal_date: '', disposal_value: '',
};

function ModalDoBem({
    aberto,
    id,
    o,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    id: number | null;
    o: OpcoesDoImobilizado;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState<Formulario>(VAZIO);

    const ficha = useQuery({
        queryKey: ['contabilidade', 'imobilizado', 'ficha', id],
        queryFn: () => contabilidade.imobilizado.ficha(id as number),
        enabled: aberto && id !== null,
    });

    useEffect(() => {
        if (!aberto) return;

        if (id === null) {
            porF(VAZIO);

            return;
        }

        const b = ficha.data?.data;

        if (b) {
            porF({
                code: b.codigo,
                name: b.nome,
                description: b.descricao ?? '',
                category_id: b.categoria_id ? String(b.categoria_id) : '',
                account_id: b.conta_id ? String(b.conta_id) : '',
                depreciation_account_id: b.conta_de_gasto_id ? String(b.conta_de_gasto_id) : '',
                accumulated_depreciation_account_id: b.conta_acumulada_id ? String(b.conta_acumulada_id) : '',
                acquisition_date: b.aquisicao ?? '',
                acquisition_value: String(b.valor),
                residual_value: String(b.residual),
                useful_life_years: String(b.vida_util),
                depreciation_method: b.metodo,
                depreciation_rate: b.taxa === null ? '' : String(b.taxa),
                status: b.estado,
                location: b.localizacao ?? '',
                serial_number: b.serie ?? '',
                disposal_date: b.abate ?? '',
                disposal_value: b.valor_do_abate === null ? '' : String(b.valor_do_abate),
            });
        }
    }, [aberto, id, ficha.data]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.imobilizado.guardar(id, {
            ...f,
            category_id: f.category_id ? Number(f.category_id) : null,
            account_id: f.account_id ? Number(f.account_id) : null,
            depreciation_account_id: f.depreciation_account_id ? Number(f.depreciation_account_id) : null,
            accumulated_depreciation_account_id: f.accumulated_depreciation_account_id ? Number(f.accumulated_depreciation_account_id) : null,
            acquisition_value: f.acquisition_value ? Number(f.acquisition_value) : null,
            residual_value: f.residual_value ? Number(f.residual_value) : 0,
            useful_life_years: f.useful_life_years ? Number(f.useful_life_years) : null,
            depreciation_rate: f.depreciation_rate ? Number(f.depreciation_rate) : null,
            disposal_date: f.disposal_date || null,
            disposal_value: f.disposal_value ? Number(f.disposal_value) : null,
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    /** Escolher a família herda as omissões dela — vida útil, método e taxa. */
    const escolherFamilia = (valor: string) => {
        const familia = o.categorias.find((c) => c.valor === valor);

        porF((x) => ({
            ...x,
            category_id: valor,
            ...(familia ? {
                useful_life_years: String(familia.vida_util),
                depreciation_method: familia.metodo,
                depreciation_rate: familia.taxa === null ? '' : String(familia.taxa),
            } : {}),
        }));
    };

    const valor = Number(f.acquisition_value) || 0;
    const residual = Number(f.residual_value) || 0;
    const anos = Number(f.useful_life_years) || 0;
    const base = Math.max(0, valor - residual);
    const porMes = anos > 0 ? base / (anos * 12) : 0;

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={id ? t('Editar Bem') : t('Novo Bem')}
            subtitulo={t('O valor, a vida útil e as contas por onde a amortização se lança')}
            icone="fa-building-columns"
            cor="roxo"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {id ? t('Atualizar') : t('Guardar')}
                    </Botao>
                </>
            }
        >
            {ficha.isPending && id !== null ? (
                <Carregando linhas={8} />
            ) : (
                <>
                    <AvisoDeErro erro={gravar.error} />

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Campo etiqueta={t('Código')} obrigatorio erro={erros.code}>
                            <input
                                type="text"
                                value={f.code}
                                onChange={(e) => porF((x) => ({ ...x, code: e.target.value }))}
                                placeholder={t('Ex.: IMO-0001')}
                                className={cls(entrada, 'font-mono')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name} className="lg:col-span-2">
                            <input
                                type="text"
                                value={f.name}
                                onChange={(e) => porF((x) => ({ ...x, name: e.target.value }))}
                                placeholder={t('Ex.: Viatura Toyota Hilux')}
                                className={entrada}
                            />
                        </Campo>

                        <Campo
                            etiqueta={t('Família')}
                            erro={erros.category_id}
                            ajuda={t('Escolher a família traz a vida útil e o método dela.')}
                        >
                            <select value={f.category_id} onChange={(e) => escolherFamilia(e.target.value)} className={entrada}>
                                <option value="">{t('Nenhuma')}</option>
                                {o.categorias.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Localização')} erro={erros.location}>
                            <input
                                type="text"
                                value={f.location}
                                onChange={(e) => porF((x) => ({ ...x, location: e.target.value }))}
                                placeholder={t('Ex.: Armazém de Viana')}
                                className={entrada}
                            />
                        </Campo>

                        <Campo etiqueta={t('Número de série')} erro={erros.serial_number}>
                            <input
                                type="text"
                                value={f.serial_number}
                                onChange={(e) => porF((x) => ({ ...x, serial_number: e.target.value }))}
                                className={cls(entrada, 'font-mono')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Data de aquisição')} obrigatorio erro={erros.acquisition_date}>
                            <input
                                type="date"
                                value={f.acquisition_date}
                                onChange={(e) => porF((x) => ({ ...x, acquisition_date: e.target.value }))}
                                className={entrada}
                            />
                        </Campo>

                        <Campo etiqueta={t('Valor de aquisição (Kz)')} obrigatorio erro={erros.acquisition_value}>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={f.acquisition_value}
                                onChange={(e) => porF((x) => ({ ...x, acquisition_value: e.target.value }))}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        <Campo
                            etiqueta={t('Valor residual (Kz)')}
                            erro={erros.residual_value}
                            ajuda={t('O que se espera valer no fim da vida útil. NÃO se amortiza.')}
                        >
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={f.residual_value}
                                onChange={(e) => porF((x) => ({ ...x, residual_value: e.target.value }))}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Vida útil (anos)')} obrigatorio erro={erros.useful_life_years}>
                            <input
                                type="number"
                                min={1}
                                max={100}
                                value={f.useful_life_years}
                                onChange={(e) => porF((x) => ({ ...x, useful_life_years: e.target.value }))}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Método')} obrigatorio erro={erros.depreciation_method}>
                            <select
                                value={f.depreciation_method}
                                onChange={(e) => porF((x) => ({ ...x, depreciation_method: e.target.value }))}
                                className={entrada}
                            >
                                {o.metodos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo
                            etiqueta={t('Taxa anual (%)')}
                            erro={erros.depreciation_rate}
                            ajuda={f.depreciation_method === 'declining_balance'
                                ? t('Vazio usa o dobro da quota linear.')
                                : t('Só as quotas degressivas a usam.')}
                        >
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={f.depreciation_rate}
                                disabled={f.depreciation_method !== 'declining_balance'}
                                onChange={(e) => porF((x) => ({ ...x, depreciation_rate: e.target.value }))}
                                className={cls(entrada, 'text-right tabular-nums disabled:bg-slate-50 disabled:text-slate-400')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Estado')} erro={erros.status}>
                            <select value={f.status} onChange={(e) => porF((x) => ({ ...x, status: e.target.value }))} className={entrada}>
                                {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-2 lg:col-span-3">
                            <textarea
                                rows={2}
                                value={f.description}
                                onChange={(e) => porF((x) => ({ ...x, description: e.target.value }))}
                                className={cls(entrada, 'h-auto py-2')}
                            />
                        </Campo>
                    </div>

                    {/* O QUE A AMORTIZAÇÃO VAI DAR, enquanto se escreve: é aqui
                        que se percebe se a vida útil está certa. */}
                    {base > 0 && anos > 0 && (
                        <p className={cls('mt-4 border border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-900', RAIO)} role="status">
                            <i className="fas fa-calculator mr-2" aria-hidden="true" />
                            {f.depreciation_method === 'linear'
                                ? t('Amortiza :base Kz em :meses meses — :mes Kz por mês.', {
                                    base: kz(base), meses: anos * 12, mes: kz(porMes),
                                })
                                : t('Amortiza :base Kz ao longo de :anos ano(s), mais no princípio do que no fim.', {
                                    base: kz(base), anos,
                                })}
                        </p>
                    )}

                    {/* AS TRÊS CONTAS: é com elas que a amortização se lança. */}
                    <div className="mt-5">
                        <h3 className="mb-2 flex items-center gap-2 text-sm font-bold text-slate-900">
                            <i className="fas fa-sitemap text-emerald-600" aria-hidden="true" />
                            {t('As contas da amortização')}
                        </h3>

                        <p className="mb-3 text-xs text-slate-500">
                            {t('A amortização do período vai a DÉBITO da conta de gasto e a CRÉDITO da de amortizações acumuladas — que é a que desconta o activo no balanço.')}
                        </p>

                        <div className="grid gap-4 lg:grid-cols-3">
                            <Campo etiqueta={t('Conta do bem')} obrigatorio erro={erros.account_id}>
                                <select value={f.account_id} onChange={(e) => porF((x) => ({ ...x, account_id: e.target.value }))} className={entrada}>
                                    <option value="">{t('Escolha a conta…')}</option>
                                    {o.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Conta de gasto')} obrigatorio erro={erros.depreciation_account_id}>
                                <select
                                    value={f.depreciation_account_id}
                                    onChange={(e) => porF((x) => ({ ...x, depreciation_account_id: e.target.value }))}
                                    className={entrada}
                                >
                                    <option value="">{t('Escolha a conta…')}</option>
                                    {o.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Amortizações acumuladas')} obrigatorio erro={erros.accumulated_depreciation_account_id}>
                                <select
                                    value={f.accumulated_depreciation_account_id}
                                    onChange={(e) => porF((x) => ({ ...x, accumulated_depreciation_account_id: e.target.value }))}
                                    className={entrada}
                                >
                                    <option value="">{t('Escolha a conta…')}</option>
                                    {o.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>
                    </div>

                    {/* O ABATE, só quando o bem já saiu. */}
                    {(f.status === 'sold' || f.status === 'scrapped') && (
                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Data do abate')} erro={erros.disposal_date}>
                                <input
                                    type="date"
                                    value={f.disposal_date}
                                    onChange={(e) => porF((x) => ({ ...x, disposal_date: e.target.value }))}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Valor do abate (Kz)')} erro={erros.disposal_value}>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={f.disposal_value}
                                    onChange={(e) => porF((x) => ({ ...x, disposal_value: e.target.value }))}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        </div>
                    )}
                </>
            )}
        </Modal>
    );
}

/* ─── A ficha e as amortizações ───────────────────────────────────────── */

function FichaDoBemModal({
    id,
    podeLancar,
    aoFechar,
    aoLancar,
}: {
    id: number | null;
    podeLancar: boolean;
    aoFechar: () => void;
    aoLancar: (mensagem: string) => void;
}) {
    const cache = useQueryClient();

    const ficha = useQuery({
        queryKey: ['contabilidade', 'imobilizado', 'ficha', id],
        queryFn: () => contabilidade.imobilizado.ficha(id as number),
        enabled: id !== null,
    });

    const lancar = useMutation({
        mutationFn: (linha: number) => contabilidade.imobilizado.lancar(linha),
        onSuccess: (r) => {
            void cache.invalidateQueries({ queryKey: ['contabilidade', 'imobilizado'] });
            aoLancar(r.message);
        },
    });

    const b = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={b ? `${b.codigo} · ${b.nome}` : t('Amortizações')}
            subtitulo={t('Calcular não é lançar: uma linha em rascunho ainda está fora da contabilidade')}
            icone="fa-list-ol"
            cor="ciano"
            largura="xl"
            rodape={<Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>}
        >
            {ficha.isPending || !b ? (
                <Carregando linhas={8} />
            ) : (
                <>
                    <ErroDaAccao erro={lancar.error} />

                    <div className="mb-4 grid gap-3 sm:grid-cols-4">
                        <Numero rotulo={t('Aquisição')} valor={b.valor} tom="azul" />
                        <Numero rotulo={t('Residual')} valor={b.residual} tom="ardosia" />
                        <Numero rotulo={t('Amortizado')} valor={b.amortizado} tom="vermelho" />
                        <Numero rotulo={t('Valor líquido')} valor={b.liquido} tom="verde" />
                    </div>

                    <dl className={cls('mb-4 grid gap-3 border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm sm:grid-cols-3', RAIO)}>
                        <Valor rotulo={t('Método')}>{b.metodo_rotulo}</Valor>
                        <Valor rotulo={t('Vida útil')}>{t(':n ano(s)', { n: b.vida_util })}</Valor>
                        <Valor rotulo={t('Por amortizar')}>{kz(b.por_amortizar)} Kz</Valor>
                        <Valor rotulo={t('Conta do bem')}>{b.conta ?? '—'}</Valor>
                        <Valor rotulo={t('Conta de gasto')}>{b.conta_de_gasto ?? '—'}</Valor>
                        <Valor rotulo={t('Amortizações acumuladas')}>{b.conta_acumulada ?? '—'}</Valor>
                    </dl>

                    {b.linhas.length === 0 ? (
                        <SemNada
                            icone="fa-calculator"
                            titulo={t('Sem amortizações calculadas')}
                            frase={t('Carregue em «Calcular amortizações» na barra do ecrã. Cada mês desde a aquisição ganha a sua linha.')}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[t('Mês'), t('Período')].map((c) => (
                                            <th key={c} scope="col" className="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                        ))}
                                        {[t('Amortização'), t('Acumulado'), t('Valor líquido')].map((c) => (
                                            <th key={c} scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                        ))}
                                        <th scope="col" className="px-3 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-slate-500">{t('Estado')}</th>
                                        <th scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t('Ações')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {b.linhas.map((l, i) => (
                                        <tr key={l.id} style={cascata(i)} className="entra">
                                            <td className="whitespace-nowrap px-3 py-2.5 text-slate-600">{l.dia}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-slate-600">{l.periodo ?? '—'}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-900">{kz(l.valor)}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-amber-700">{kz(l.acumulado)}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-700">{kz(l.liquido)}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-center">
                                                {l.estado === 'posted' ? (
                                                    <Etiqueta cor="bom" ponto>{t('Lançada')}</Etiqueta>
                                                ) : (
                                                    <Etiqueta cor="aviso" ponto>{t('Em rascunho')}</Etiqueta>
                                                )}
                                                {l.lancamento && (
                                                    <span className="mt-0.5 block font-mono text-[11px] text-slate-400">{l.lancamento}</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right">
                                                {podeLancar && l.pode_lancar ? (
                                                    <Botao
                                                        altura="pequeno"
                                                        cor="bom"
                                                        icone="fa-file-import"
                                                        aTrabalhar={lancar.isPending && lancar.variables === l.id}
                                                        onClick={() => lancar.mutate(l.id)}
                                                    >
                                                        {t('Lançar')}
                                                    </Botao>
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            )}
        </Modal>
    );
}

/* ─── Calcular ────────────────────────────────────────────────────────── */

/**
 * O BOTÃO QUE SÓ FLASHAVA UMA PROMESSA.
 *
 * «Funcionalidade de cálculo de depreciações será implementada em breve!» era o
 * corpo inteiro do método. Agora calcula — e diz que calcular não é lançar.
 */
function ModalDeCalcular({
    aberto,
    aoFechar,
    aoCalcular,
}: {
    aberto: boolean;
    aoFechar: () => void;
    aoCalcular: (mensagem: string) => void;
}) {
    const fimDoMes = (() => {
        const d = new Date();

        return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
    })();

    const [ate, porAte] = useState(fimDoMes);

    useEffect(() => {
        if (aberto) porAte(fimDoMes);
    }, [aberto, fimDoMes]);

    const calcular = useMutation({
        mutationFn: () => contabilidade.imobilizado.calcular({ ate }),
        onSuccess: (r) => aoCalcular(r.message),
    });

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Calcular amortizações')}
            subtitulo={t('Uma linha por bem e por mês, desde a aquisição')}
            icone="fa-calculator"
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-calculator" aTrabalhar={calcular.isPending} onClick={() => calcular.mutate()}>
                        {t('Calcular')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={calcular.error} />

            <Campo etiqueta={t('Até')} obrigatorio ajuda={t('Calcula os meses em falta até ao fim deste mês.')}>
                <input type="date" value={ate} onChange={(e) => porAte(e.target.value)} className={entrada} />
            </Campo>

            <ul className={cls('mt-3 space-y-1.5 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                <li>
                    <i className="fas fa-circle-info mr-1.5 text-blue-600" aria-hidden="true" />
                    {t('As linhas nascem em RASCUNHO: calcular não é lançar. Lançar na contabilidade faz-se depois, linha a linha, na ficha do bem.')}
                </li>
                <li>
                    <i className="fas fa-shield-halved mr-1.5 text-emerald-600" aria-hidden="true" />
                    {t('Uma amortização já LANÇADA nunca é tocada — corrige-se por estorno, como qualquer lançamento.')}
                </li>
                <li>
                    <i className="fas fa-scale-balanced mr-1.5 text-purple-600" aria-hidden="true" />
                    {t('Nunca abaixo do valor residual: a última prestação é o que falta, não a prestação inteira.')}
                </li>
                <li>
                    <i className="fas fa-calendar-xmark mr-1.5 text-amber-600" aria-hidden="true" />
                    {t('Os meses sem período contabilístico montado ficam de fora — uma amortização pertence a um período.')}
                </li>
            </ul>
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

const NUMEROS = {
    ardosia: 'border-slate-200 bg-slate-50 text-slate-700',
    verde: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    vermelho: 'border-red-200 bg-red-50 text-red-800',
    azul: 'border-blue-200 bg-blue-50 text-blue-900',
} as const;

function Numero({ rotulo, valor, tom }: { rotulo: string; valor: number; tom: keyof typeof NUMEROS }) {
    return (
        <div className={cls('border px-3 py-2.5', RAIO, NUMEROS[tom])}>
            <p className="text-[11px] font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums">{kz(valor)}</p>
        </div>
    );
}

function Valor({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="text-sm font-semibold text-slate-900">{children}</dd>
        </div>
    );
}

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const geral = daApi?.erros.geral?.[0];

    return (
        <div role="alert" className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {geral ?? daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}
