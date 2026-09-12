import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { DefinicoesDaContabilidade, EventoDaIntegracao } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * AS DEFINIÇÕES DA CONTABILIDADE — montar o módulo e ligar a facturação.
 *
 * O QUE ESTAVA PARTIDO, e é o mesmo defeito das notificações: a página abria com
 * `settings.view` e TODAS AS ESCRITAS ERAM LIVRES. Só o botão de apagar
 * verificava `settings.edit`. Quem pudesse VER podia correr os seeders todos,
 * LIGAR a integração automática — que decide se cada factura, recebimento e
 * pagamento gera lançamentos — e reescrever os MAPEAMENTOS, que dizem contra que
 * contas esses lançamentos saem.
 *
 * AS SINCRONIZAÇÕES SÃO INCREMENTAIS: acrescentam o que falta e nunca alteram
 * nem apagam o que a empresa já tem. Diz-se, porque o botão parecia destrutivo.
 */
export default function Definicoes() {
    const cache = useQueryClient();

    const [recado, porRecado] = useState('');
    const [aviso, porAviso] = useState('');
    const [aEditar, porAEditar] = useState<EventoDaIntegracao | null>(null);
    const [aApagar, porAApagar] = useState(false);

    const d = useQuery({
        queryKey: ['contabilidade', 'definicoes'],
        queryFn: contabilidade.definicoes.ler,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade'] });
    }

    const sincronizar = useMutation({
        mutationFn: ({ peca, ano }: { peca: string; ano?: number }) =>
            contabilidade.definicoes.sincronizar(peca, ano),
        onSuccess: (r) => feito(r.message),
    });

    const integracao = useMutation({
        mutationFn: (ligada: boolean) => contabilidade.definicoes.integracao(ligada),
        onSuccess: (r) => {
            feito(r.message);
            porAviso(r.aviso ? r.message : '');
        },
    });

    const apagar = useMutation({
        mutationFn: () => contabilidade.definicoes.apagarTudo(),
        onSuccess: (r) => {
            porAApagar(false);
            feito(r.message);
        },
    });

    if (d.isPending) return <Carregando linhas={12} />;

    if (d.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as definições')}</h2>
                <p className="text-sm text-red-800">
                    {d.error instanceof ErroDaApi ? d.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const o = d.data;
    const podeEditar = o.permissoes.editar;

    const PECAS = [
        { chave: 'contas', rotulo: t('Plano de contas'), icone: 'fa-sitemap', quantos: o.montagem.contas, nota: t('As contas do PGC-AO') },
        { chave: 'diarios', rotulo: t('Diários'), icone: 'fa-book', quantos: o.montagem.diarios, nota: t('Por onde entram os lançamentos') },
        { chave: 'impostos', rotulo: t('Impostos'), icone: 'fa-percent', quantos: o.montagem.impostos, nota: t('As taxas e as contas de IVA') },
        { chave: 'centros-de-custo', rotulo: t('Centros de custo'), icone: 'fa-building', quantos: o.montagem.centros_de_custo, nota: t('Onde o gasto foi feito') },
        { chave: 'tipos-de-documento', rotulo: t('Tipos de documento'), icone: 'fa-file-lines', quantos: o.montagem.tipos_de_documento, nota: t('O que cada documento faz aos mapas') },
        { chave: 'periodos', rotulo: t('Períodos de :ano', { ano: o.ano }), icone: 'fa-calendar-check', quantos: o.montagem.periodos_do_ano, nota: t('Os doze meses do exercício') },
    ];

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Definições da Contabilidade')}
                subtitulo={t('Montar o módulo e ligar a facturação')}
                icone="fa-sliders"
                cor="bom"
                accoes={
                    podeEditar && (
                        <button
                            type="button"
                            onClick={() => sincronizar.mutate({ peca: 'tudo', ano: o.ano })}
                            disabled={sincronizar.isPending}
                            className={ACCAO_DA_FAIXA}
                        >
                            <i className={cls('fas', sincronizar.isPending ? 'fa-spinner fa-spin' : 'fa-rotate')} aria-hidden="true" />
                            {t('Sincronizar tudo')}
                        </button>
                    )
                }
            >
                <EstadoNaFaixa icone={o.integracao.ligada ? 'fa-plug-circle-check' : 'fa-plug-circle-xmark'}>
                    {o.integracao.ligada
                        ? t('Integração automática ligada · :n mapeamento(s)', { n: o.integracao.mapeamentos_activos })
                        : t('Integração automática desligada')}
                </EstadoNaFaixa>
            </Faixa>

            {/* VER E MEXER SÃO DIREITOS DIFERENTES, e o ecrã di-lo. */}
            {!podeEditar && (
                <div className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)} role="status">
                    <i className="fas fa-eye mr-2" aria-hidden="true" />
                    {t('Está a ver as definições. Mexer nelas pede a permissão de editar configurações de contabilidade.')}
                </div>
            )}

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            {aviso && (
                <div role="alert" className={cls('border-2 border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {aviso}
                </div>
            )}

            <ErroDaAccao erro={sincronizar.error ?? integracao.error ?? apagar.error} />

            {/* ─── A MONTAGEM ────────────────────────────────────────── */}
            <Cartao
                titulo={t('Montar o módulo')}
                icone="fa-screwdriver-wrench"
                subtitulo={t('Cada botão ACRESCENTA o que falta — nunca altera nem apaga o que já existe')}
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {PECAS.map((p, i) => (
                        <div
                            key={p.chave}
                            style={cascata(i)}
                            className={cls('entra flex items-start justify-between gap-3 border border-slate-200 px-4 py-3',
                                'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm', RAIO)}
                        >
                            <span className="min-w-0">
                                <span className="flex items-center gap-2 text-sm font-bold text-slate-900">
                                    <i className={cls('fas', p.icone, 'text-emerald-600')} aria-hidden="true" />
                                    {p.rotulo}
                                </span>
                                <span className="mt-0.5 block text-2xl font-bold tabular-nums text-slate-900">
                                    {p.quantos.toLocaleString('pt-PT')}
                                </span>
                                <span className="block text-xs text-slate-400">{p.nota}</span>
                            </span>

                            {podeEditar && (
                                <Botao
                                    altura="pequeno"
                                    icone="fa-rotate"
                                    aTrabalhar={sincronizar.isPending && sincronizar.variables?.peca === p.chave}
                                    onClick={() => sincronizar.mutate({ peca: p.chave, ano: o.ano })}
                                >
                                    {t('Sincronizar')}
                                </Botao>
                            )}
                        </div>
                    ))}
                </div>

                <p className={cls('mt-4 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                    <i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />
                    {t('Correr outra vez não estraga nada: um período fechado não reabre, uma conta editada à mão não volta atrás, e o que já existe fica como está.')}
                </p>
            </Cartao>

            {/* ─── A INTEGRAÇÃO ──────────────────────────────────────── */}
            <Cartao
                titulo={t('Integração automática com a facturação')}
                icone="fa-plug"
                subtitulo={t('Se cada factura, recebimento e pagamento gera lançamentos sozinho')}
            >
                <div className={cls('flex flex-wrap items-center justify-between gap-3 border-2 px-4 py-3.5', RAIO,
                    o.integracao.ligada ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50')}>
                    <div className="min-w-0">
                        <p className={cls('text-sm font-bold', o.integracao.ligada ? 'text-emerald-900' : 'text-slate-700')}>
                            <i className={cls('fas mr-2', o.integracao.ligada ? 'fa-plug-circle-check' : 'fa-plug-circle-xmark')} aria-hidden="true" />
                            {o.integracao.ligada ? t('Ligada') : t('Desligada')}
                        </p>
                        <p className="mt-0.5 text-xs text-slate-600">
                            {o.integracao.mapeamentos_activos > 0
                                ? t(':n evento(s) com contas configuradas.', { n: o.integracao.mapeamentos_activos })
                                : t('Sem mapeamentos, ligar não produz lançamento nenhum.')}
                        </p>
                    </div>

                    {podeEditar && (
                        <Botao
                            cor={o.integracao.ligada ? 'aviso' : 'bom'}
                            tom="solida"
                            icone={o.integracao.ligada ? 'fa-plug-circle-xmark' : 'fa-plug-circle-check'}
                            aTrabalhar={integracao.isPending}
                            onClick={() => integracao.mutate(!o.integracao.ligada)}
                        >
                            {o.integracao.ligada ? t('Desligar') : t('Ligar')}
                        </Botao>
                    )}
                </div>

                {/* OS MAPEAMENTOS: por que diário e contra que contas cada evento lança. */}
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                            <tr>
                                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Evento')}</th>
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Estado')}</th>
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Confirma sozinho')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Ações')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {o.eventos.map((e, i) => (
                                <tr key={e.evento} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/50">
                                    <td className="px-4 py-3 font-semibold text-slate-900">{e.rotulo}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center">
                                        {!e.configurado ? (
                                            <Etiqueta cor="aviso" icone="fa-circle-question">{t('Por configurar')}</Etiqueta>
                                        ) : e.activo ? (
                                            <Etiqueta cor="bom" ponto>{t('Activo')}</Etiqueta>
                                        ) : (
                                            <Etiqueta cor="neutra" ponto>{t('Inactivo')}</Etiqueta>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center">
                                        {e.configurado
                                            ? (e.confirma_sozinho
                                                ? <Etiqueta cor="primaria" icone="fa-check">{t('Sim')}</Etiqueta>
                                                : <Etiqueta cor="neutra">{t('Fica em rascunho')}</Etiqueta>)
                                            : <span className="text-xs text-slate-400">—</span>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        {podeEditar ? (
                                            <Botao altura="pequeno" icone="fa-edit" onClick={() => porAEditar(e)}>
                                                {e.configurado ? t('Editar') : t('Configurar')}
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

                <p className={cls('mt-3 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                    <i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />
                    {t('A resolução automática acerta na classe e no sinal, mas não na conta exacta: num plano importado de 1.500 contas aterra no cabeçalho de classe («31 CLIENTES» em vez de «311 Clientes correntes»). É isso que se corrige aqui.')}
                </p>
            </Cartao>

            {/* ─── A ACÇÃO DESTRUTIVA ────────────────────────────────── */}
            {podeEditar && (
                <div className={cls('border-2 border-red-200 bg-red-50 px-5 py-4', RAIO)}>
                    <h3 className="flex items-center gap-2 text-sm font-bold text-red-900">
                        <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                        {t('Apagar os dados contabilísticos')}
                    </h3>
                    <p className="mt-1 text-sm text-red-800">
                        {t('Apaga o plano de contas, os diários e os impostos para voltar a montar de raiz. Só funciona se não houver lançamento nenhum — apagar o plano por baixo dos lançamentos deixava-os a apontar para contas que não existem.')}
                    </p>

                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <Botao cor="perigo" tom="solida" icone="fa-trash" onClick={() => porAApagar(true)}>
                            {t('Apagar e voltar a montar')}
                        </Botao>

                        {o.montagem.lancamentos > 0 && (
                            <span className="text-sm font-semibold text-red-900">
                                {t('Há :n lançamento(s): esta acção vai ser recusada.', { n: o.montagem.lancamentos })}
                            </span>
                        )}
                    </div>
                </div>
            )}

            <ModalDoMapeamento
                evento={aEditar}
                o={o}
                aoFechar={() => porAEditar(null)}
                aoGravar={(mensagem) => {
                    porAEditar(null);
                    feito(mensagem);
                }}
            />

            <Modal
                aberto={aApagar}
                aoFechar={() => porAApagar(false)}
                titulo={t('Apagar os dados contabilísticos')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(false)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => apagar.mutate()}>
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />

                <p className="text-sm text-slate-700">
                    {t('Vão-se o plano de contas, os diários e os impostos desta empresa. Os centros de custo que outros módulos usam ficam.')}
                </p>

                <p className="mt-2 text-sm font-semibold text-red-800">
                    {t('Esta operação não se desfaz.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── O mapeamento de um evento ───────────────────────────────────────── */

/**
 * POR QUE DIÁRIO E CONTRA QUE CONTAS um evento da facturação lança.
 *
 * Até haver este ecrã, corrigir uma conta mal resolvida só se fazia com SQL
 * directo na base.
 */
function ModalDoMapeamento({
    evento,
    o,
    aoFechar,
    aoGravar,
}: {
    evento: EventoDaIntegracao | null;
    o: DefinicoesDaContabilidade;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState({
        journal_id: '', debit_account_id: '', credit_account_id: '', vat_account_id: '',
        auto_post: true, active: true,
    });

    useEffect(() => {
        if (!evento) return;

        porF({
            journal_id: evento.diario_id ? String(evento.diario_id) : '',
            debit_account_id: evento.debito_id ? String(evento.debito_id) : '',
            credit_account_id: evento.credito_id ? String(evento.credito_id) : '',
            vat_account_id: evento.imposto_id ? String(evento.imposto_id) : '',
            auto_post: evento.configurado ? evento.confirma_sozinho : true,
            active: evento.configurado ? evento.activo : true,
        });
    }, [evento]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.definicoes.mapeamento({
            event: evento?.evento,
            journal_id: f.journal_id ? Number(f.journal_id) : null,
            debit_account_id: f.debit_account_id ? Number(f.debit_account_id) : null,
            credit_account_id: f.credit_account_id ? Number(f.credit_account_id) : null,
            vat_account_id: f.vat_account_id ? Number(f.vat_account_id) : null,
            auto_post: f.auto_post,
            active: f.active,
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal
            aberto={evento !== null}
            aoFechar={aoFechar}
            titulo={evento ? evento.rotulo : t('Mapeamento')}
            subtitulo={t('Por que diário e contra que contas este evento lança')}
            icone="fa-diagram-project"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-4">
                <Campo etiqueta={t('Diário')} obrigatorio erro={erros.journal_id}>
                    <select value={f.journal_id} onChange={(e) => porF((x) => ({ ...x, journal_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Escolha o diário…')}</option>
                        {o.diarios.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Conta a débito')} obrigatorio erro={erros.debit_account_id}>
                    <select value={f.debit_account_id} onChange={(e) => porF((x) => ({ ...x, debit_account_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Escolha a conta…')}</option>
                        {o.contas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Conta a crédito')} obrigatorio erro={erros.credit_account_id}>
                    <select value={f.credit_account_id} onChange={(e) => porF((x) => ({ ...x, credit_account_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Escolha a conta…')}</option>
                        {o.contas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Conta de IVA')}
                    erro={erros.vat_account_id}
                    ajuda={t('Onde o imposto do documento é separado. Vazio não separa.')}
                >
                    <select value={f.vat_account_id} onChange={(e) => porF((x) => ({ ...x, vat_account_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Nenhuma')}</option>
                        {o.contas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <label className={cls('flex cursor-pointer items-start gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3', RAIO)}>
                    <input
                        type="checkbox"
                        checked={f.active}
                        onChange={(e) => porF((x) => ({ ...x, active: e.target.checked }))}
                        className="mt-0.5 h-5 w-5 flex-none rounded border-slate-300 text-emerald-600 focus-visible:ring-2 focus-visible:ring-emerald-500"
                    />
                    <span>
                        <span className="block text-sm font-bold text-slate-900">{t('Activo')}</span>
                        <span className="block text-xs text-slate-600">
                            {t('Um mapeamento inactivo não gera lançamento nenhum, mesmo com a integração ligada.')}
                        </span>
                    </span>
                </label>

                <label className={cls('flex cursor-pointer items-start gap-3 border border-blue-200 bg-blue-50 px-4 py-3', RAIO)}>
                    <input
                        type="checkbox"
                        checked={f.auto_post}
                        onChange={(e) => porF((x) => ({ ...x, auto_post: e.target.checked }))}
                        className="mt-0.5 h-5 w-5 flex-none rounded border-slate-300 text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-500"
                    />
                    <span>
                        <span className="block text-sm font-bold text-slate-900">{t('Confirma sozinho')}</span>
                        <span className="block text-xs text-slate-600">
                            {t('O lançamento nasce confirmado e conta logo nos saldos. Desligado, fica em rascunho à espera de quem o confira.')}
                        </span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const geral = daApi?.erros.geral?.[0];

    return (
        <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {geral ?? daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}
