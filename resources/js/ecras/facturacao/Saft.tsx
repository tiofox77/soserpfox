import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { saft, type OpcoesDoSaft, type PeriodoDoSaft } from '@/api/saft';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { EstadoNaFaixa, Faixa, cascata } from './faixa';

/**
 * O GERADOR SAFT-AO.
 *
 * O XML é do servidor (`GeradorDeSaft`), o mesmo que o ecrã de sempre usa.
 * Aqui escolhe-se o período, o tipo e as secções, vêem-se as contagens, e
 * a descarga é uma ida ao servidor com a sessão — um ficheiro não viaja
 * em JSON.
 */
const kz = (v: number) => `${new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)} Kz`;

export default function Saft() {
    const opcoes = useQuery({ queryKey: ['saft', 'opcoes'], queryFn: saft.opcoes, staleTime: 5 * 60_000 });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o gerador')}</h2>
                <p className="text-sm text-red-800">{opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    return <Gerador o={opcoes.data} />;
}

function Gerador({ o }: { o: OpcoesDoSaft }) {
    const [periodo, porPeriodo] = useState<PeriodoDoSaft>({ startDate: o.periodo.de, endDate: o.periodo.ate, documentType: 'all' });
    const [seccoes, porSeccoes] = useState<Record<string, boolean>>(() => Object.fromEntries(o.seccoes.map((s) => [s.chave, true])));
    const completo = periodo.startDate !== '' && periodo.endDate !== '';
    const stats = useQuery({ queryKey: ['saft', 'estatisticas', periodo], queryFn: () => saft.estatisticas(periodo), enabled: completo, placeholderData: keepPreviousData });

    const descarregar = () => {
        const p = new URLSearchParams(periodo);
        o.seccoes.forEach((s) => p.set(s.chave, seccoes[s.chave] ? '1' : '0'));
        window.location.assign(`${o.descarga}?${p.toString()}`);
    };

    const e = stats.data?.data;
    /*
     * A COR DE CADA CONTAGEM SEGUE O QUE ELA É, e é sempre a mesma: as
     * facturas no azul da casa, o dinheiro em verde, o que se credita em
     * vermelho. Assim quem vê o mesmo mapa todos os meses reconhece o cartão
     * antes de ler o rótulo — que era o que os cartões do Blade faziam.
     */
    const cartoes: Array<{ rotulo: string; valor: string; icone: string; tom: TomDoCartao }> = e
        ? [
            { rotulo: 'Facturas', valor: String(e.totalInvoices), icone: 'fa-file-invoice', tom: 'azul' },
            { rotulo: 'Valor facturado', valor: kz(e.totalValue), icone: 'fa-coins', tom: 'verde' },
            { rotulo: 'Notas de crédito', valor: String(e.totalCreditNotes), icone: 'fa-file-circle-minus', tom: 'vermelho' },
            { rotulo: 'Notas de débito', valor: String(e.totalDebitNotes), icone: 'fa-file-circle-plus', tom: 'laranja' },
            { rotulo: 'Recibos', valor: String(e.totalReceipts), icone: 'fa-receipt', tom: 'verde' },
            { rotulo: 'Movimentos de stock', valor: String(e.totalMovements), icone: 'fa-boxes-stacked', tom: 'ambar' },
            { rotulo: 'Clientes', valor: String(e.totalCustomers), icone: 'fa-users', tom: 'indigo' },
            { rotulo: 'Fornecedores', valor: String(e.totalSuppliers), icone: 'fa-truck', tom: 'cinza' },
            { rotulo: 'Produtos', valor: String(e.totalProducts), icone: 'fa-box', tom: 'roxo' },
        ]
        : [];

    return (
        <div className="space-y-4">
            <Faixa
                icone="fa-file-code"
                titulo={t('Gerador SAFT-AO')}
                subtitulo={t('Standard Audit File for Tax — Angola (AGT)')}
                accoes={<EstadoNaFaixa icone="fa-code-branch">{t('Versão SAFT')} <strong>1.01_01</strong></EstadoNaFaixa>}
            />

            <AvisoDeErro erro={stats.error} />

            <Cartao
                titulo={t('SAFT-AO do período')}
                icone="fa-calendar-days"
                accoes={o.permissoes.pode_gerar && <Botao cor="primaria" tom="solida" icone="fa-download" disabled={!completo || stats.isPending} onClick={descarregar}>{t('Gerar e descarregar')}</Botao>}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta={t('De')} obrigatorio><input type="date" value={periodo.startDate} onChange={(ev) => porPeriodo({ ...periodo, startDate: ev.target.value })} className={entrada} /></Campo>
                    <Campo etiqueta={t('Até')} obrigatorio><input type="date" value={periodo.endDate} onChange={(ev) => porPeriodo({ ...periodo, endDate: ev.target.value })} className={entrada} /></Campo>
                    <Campo etiqueta={t('Documentos')}>
                        <select value={periodo.documentType} onChange={(ev) => porPeriodo({ ...periodo, documentType: ev.target.value })} className={entrada}>
                            {o.tipos.map((t) => <option key={t.valor} value={t.valor}>{t.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>
                <fieldset className="mt-4">
                    <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Secções a incluir')}</legend>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {o.seccoes.map((s) => (
                            <label key={s.chave} className="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={seccoes[s.chave] ?? true} onChange={(ev) => porSeccoes({ ...seccoes, [s.chave]: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />
                                {s.rotulo}
                            </label>
                        ))}
                    </div>
                </fieldset>
                {!o.permissoes.pode_gerar && <p className="mt-4 text-sm text-slate-500">{t('Pode ver as contagens; gerar o ficheiro é permissão à parte.')}</p>}
            </Cartao>

            <div data-estatisticas className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-3', stats.isFetching && 'opacity-60')}>
                {stats.isPending && <div className="lg:col-span-3"><Carregando linhas={3} /></div>}
                {cartoes.map((c, i) => (
                    <div key={c.rotulo} className="entra" style={cascata(i)}>
                        <CartaoNumero rotulo={t(c.rotulo)} valor={c.valor} icone={c.icone} tom={c.tom} />
                    </div>
                ))}
            </div>
        </div>
    );
}
