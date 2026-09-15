import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { conta, type Privacidade as DadosDePrivacidade } from '@/api/conta';
import { etiquetaIntl, t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

/**
 * O SEPARADOR «PRIVACIDADE» — os direitos do titular, a funcionar.
 *
 * Pedido de 2026-09-15: as regras europeias (RGPD), brasileiras (LGPD) e
 * angolanas (Lei 22/11) sobre o que se recolhe de cada pessoa — localização,
 * morada, IP. Aqui a pessoa VÊ o que se guarda sobre ela (com os IPs e os
 * aparelhos das suas sessões e entradas), DESCARREGA tudo, MUDA os
 * consentimentos, FECHA as sessões dos outros aparelhos e FAZ PEDIDOS com
 * prazo de resposta.
 *
 * O inventário («para quê, com que fundamento, durante quanto tempo») vem do
 * servidor — é a mesma lista da Política de Privacidade, para os dois nunca
 * dizerem coisas diferentes.
 */

const quando = (valor: string | null | undefined) => {
    if (!valor) return '—';
    const d = new Date(valor.includes('T') ? valor : valor.replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? valor : d.toLocaleString(etiquetaIntl(), { dateStyle: 'short', timeStyle: 'short' });
};

const ESTADOS_DO_PEDIDO: Record<string, { rotulo: () => string; cor: 'aviso' | 'primaria' | 'bom' | 'perigo' }> = {
    recebido: { rotulo: () => t('Recebido'), cor: 'aviso' },
    em_analise: { rotulo: () => t('Em análise'), cor: 'primaria' },
    respondido: { rotulo: () => t('Respondido'), cor: 'bom' },
    recusado: { rotulo: () => t('Recusado'), cor: 'perigo' },
};

export function Privacidade() {
    const cliente = useQueryClient();
    const ficha = useQuery({ queryKey: ['conta', 'privacidade'], queryFn: conta.privacidade.ler });

    const [escolha, porEscolha] = useState({ estatisticas: false, marketing: false });
    const [aFechar, porAFechar] = useState(false);
    const [incluirAplicacao, porIncluirAplicacao] = useState(false);
    const [pedido, porPedido] = useState({ tipo: 'acesso', mensagem: '' });
    const [aberta, porAberta] = useState<string | null>(null);

    // A escolha deste browser manda; sem ela, a última gravada na conta.
    useEffect(() => {
        const d = ficha.data;
        if (!d) return;
        const c = d.dados.consentimentos;
        porEscolha(d.escolha_neste_browser ?? {
            estatisticas: c.estatisticas?.aceite ?? false,
            marketing: c.marketing?.aceite ?? false,
        });
    }, [ficha.data]);

    const recarregar = () => cliente.invalidateQueries({ queryKey: ['conta', 'privacidade'] });

    const guardarEscolha = useMutation({
        mutationFn: () => conta.privacidade.consentimentos(escolha),
        onSuccess: () => {
            recarregar();
            // As páginas públicas abertas noutras abas lêem o mesmo cookie.
            window.dispatchEvent(new CustomEvent('sos:consentimento', { detail: { escolha } }));
        },
    });
    const terminar = useMutation({
        mutationFn: () => conta.privacidade.terminarSessoes(incluirAplicacao),
        onSuccess: () => { porAFechar(false); recarregar(); },
    });
    const pedir = useMutation({
        mutationFn: () => conta.privacidade.pedir(pedido),
        onSuccess: () => { porPedido({ tipo: 'acesso', mensagem: '' }); recarregar(); },
    });

    if (ficha.isPending) return <Carregando linhas={8} />;
    if (ficha.isError) return <AvisoDeErro erro={ficha.error} />;

    const p: DadosDePrivacidade = ficha.data;
    const d = p.dados;
    const outrasSessoes = d.sessoes.filter((s) => !s.esta).length;
    const pedidosAbertos = d.pedidos.filter((x) => x.estado === 'recebido' || x.estado === 'em_analise').length;
    const errosDoPedido = (pedir.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    return (
        <div className="space-y-5">
            {/* ─── O essencial, à cabeça ─────────────────────────────── */}
            <div className={cls('entra flex flex-wrap items-start gap-4 border border-indigo-100 bg-gradient-to-r from-indigo-50 via-white to-orange-50 p-5', RAIO)}>
                <span className="icon-float grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xl text-white shadow-lg">
                    <i className="fas fa-user-shield" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="text-lg font-bold text-slate-900">{t('Os seus dados, as suas regras')}</h3>
                    <p className="mt-1 text-sm text-slate-600">
                        {t('Veja o que guardamos sobre si — incluindo IPs, aparelhos e moradas —, descarregue uma cópia e exerça os seus direitos. Aplicamos a Lei 22/11 de Angola, o RGPD europeu e a LGPD brasileira.')}
                    </p>
                    <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                        <a href={p.politica} target="_blank" rel="noreferrer" className={cls('font-semibold text-indigo-700 hover:underline', FOCO)}>
                            <i className="fas fa-file-shield mr-1" aria-hidden="true" />{t('Política de Privacidade')}
                        </a>
                        <a href={p.cookies} target="_blank" rel="noreferrer" className={cls('font-semibold text-indigo-700 hover:underline', FOCO)}>
                            <i className="fas fa-cookie-bite mr-1" aria-hidden="true" />{t('Política de Cookies')}
                        </a>
                        <span><i className="fas fa-envelope mr-1" aria-hidden="true" />{p.responsavel.email}</span>
                    </p>
                </div>
                <a
                    href={conta.privacidade.exportar}
                    className={cls('inline-flex items-center gap-2 bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:-translate-y-0.5 hover:bg-indigo-700 hover:shadow-md', RAIO, TRANSICAO, FOCO)}
                >
                    <i className="fas fa-file-arrow-down" aria-hidden="true" />
                    {t('Descarregar os meus dados')}
                </a>
            </div>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Sessões abertas')} valor={d.sessoes.length} icone="fa-laptop" tom="azul" nota={t(':n noutros aparelhos', { n: outrasSessoes })} />
                <CartaoNumero rotulo={t('Entradas registadas')} valor={d.entradas.length} icone="fa-right-to-bracket" tom="indigo" nota={t('as mais recentes')} />
                <CartaoNumero rotulo={t('Empresas')} valor={d.empresas.length} icone="fa-building" tom="teal" />
                <CartaoNumero rotulo={t('Pedidos em curso')} valor={pedidosAbertos} icone="fa-inbox" tom={pedidosAbertos ? 'ambar' : 'verde'} nota={t('resposta em até :n dias', { n: p.prazo_de_resposta_dias })} />
            </div>

            <div className="grid gap-5 xl:grid-cols-2">
                {/* ─── Consentimentos ────────────────────────────────── */}
                <section className={cls(CARTAO, 'entra min-w-0 p-5')} style={cascata(1)}>
                    <h3 className="flex items-center gap-2 font-bold text-slate-900">
                        <i className="fas fa-sliders text-indigo-500" aria-hidden="true" />{t('Consentimentos')}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">{t('O que autoriza além do necessário. Pode mudar a qualquer momento; o que foi feito antes continua válido.')}</p>

                    <div className="mt-4 space-y-3">
                        <Interruptor
                            icone="fa-shield-halved" titulo={t('Necessários')} sempre
                            descricao={t('Sessão, protecção de formulários e esta escolha. Sem eles não é possível entrar.')}
                        />
                        <Interruptor
                            icone="fa-chart-line" titulo={t('Estatísticas')} ligado={escolha.estatisticas}
                            aoMudar={(v) => porEscolha({ ...escolha, estatisticas: v })}
                            descricao={t('Páginas vistas, cidade aproximada pelo IP truncado e Google Analytics. Sem isto, contamos a visita sem cookie, IP ou cidade.')}
                        />
                        <Interruptor
                            icone="fa-bullhorn" titulo={t('Marketing')} ligado={escolha.marketing}
                            aoMudar={(v) => porEscolha({ ...escolha, marketing: v })}
                            descricao={t('Meta Pixel (Facebook/Instagram) e Google Ads, para medir campanhas.')}
                        />
                    </div>

                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <p className="text-xs text-slate-500">
                            {d.consentimentos.termos
                                ? t('Termos aceites a :dia (:origem).', { dia: quando(d.consentimentos.termos.quando), origem: d.consentimentos.termos.origem })
                                : t('A aceitação dos Termos é anterior ao registo de consentimentos.')}
                        </p>
                        <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardarEscolha.isPending} onClick={() => guardarEscolha.mutate()}>
                            {t('Guardar escolha')}
                        </Botao>
                    </div>
                </section>

                {/* ─── Sessões e entradas ────────────────────────────── */}
                <section className={cls(CARTAO, 'entra min-w-0 p-5')} style={cascata(2)}>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="flex items-center gap-2 font-bold text-slate-900">
                            <i className="fas fa-laptop-code text-indigo-500" aria-hidden="true" />{t('Onde tem sessão aberta')}
                        </h3>
                        <Botao cor="perigo" altura="pequeno" icone="fa-power-off" disabled={!outrasSessoes} onClick={() => porAFechar(true)}>
                            {t('Terminar as outras')}
                        </Botao>
                    </div>
                    <ul className="mt-3 divide-y divide-slate-100">
                        {d.sessoes.length === 0 && <li className="py-3 text-sm text-slate-500">{t('Sem sessões registadas.')}</li>}
                        {d.sessoes.map((s, i) => (
                            <li key={i} className={cls('flex items-center gap-3 py-2.5', TRANSICAO)}>
                                <span className={cls('grid h-9 w-9 shrink-0 place-items-center rounded-xl', s.esta ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500')}>
                                    <i className={cls('fas', /Android|iPhone/.test(s.aparelho) ? 'fa-mobile-screen' : 'fa-desktop')} aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">
                                        {s.aparelho}
                                        {s.esta && <span className="ml-2"><Etiqueta cor="bom" ponto>{t('Esta sessão')}</Etiqueta></span>}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        <span className="font-mono">{s.ip ?? '—'}</span> · {quando(s.ultima_actividade)}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <h4 className="mt-4 text-xs font-bold uppercase tracking-wider text-slate-500">{t('Últimas entradas e saídas')}</h4>
                    <div className="mt-2 overflow-x-auto">
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-slate-100">
                                {d.entradas.length === 0 && (
                                    <tr><td className="py-2 text-slate-500">{t('Sem entradas registadas.')}</td></tr>
                                )}
                                {d.entradas.map((e, i) => (
                                    <tr key={i} className="hover:bg-slate-50">
                                        <td className="py-2 pr-3 font-medium text-slate-700">
                                            <i className={cls('fas mr-1.5', e.evento === t('Entrada') ? 'fa-arrow-right-to-bracket text-emerald-500' : 'fa-arrow-right-from-bracket text-slate-400')} aria-hidden="true" />
                                            {e.evento}
                                        </td>
                                        <td className="py-2 pr-3 font-mono text-xs text-slate-600">{e.ip ?? '—'}</td>
                                        <td className="py-2 pr-3 text-slate-600">{e.aparelho}</td>
                                        <td className="whitespace-nowrap py-2 text-right tabular-nums text-slate-500">{quando(e.quando)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {/* ─── Os dados guardados ─────────────────────────────────── */}
            <section className={cls(CARTAO, 'entra p-5')} style={cascata(3)}>
                <h3 className="flex items-center gap-2 font-bold text-slate-900">
                    <i className="fas fa-database text-indigo-500" aria-hidden="true" />{t('O que está guardado sobre si')}
                </h3>
                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <Bloco icone="fa-id-card" titulo={t('Conta')}>
                        <Linha rotulo={t('Nome')} valor={d.perfil.nome} />
                        <Linha rotulo={t('Email')} valor={d.perfil.email} />
                        <Linha rotulo={t('Telefone')} valor={d.perfil.telefone} />
                        <Linha rotulo={t('Conta criada')} valor={quando(d.perfil.criada_em)} />
                        <Linha rotulo={t('Senha mudada')} valor={quando(d.perfil.senha_mudada_em)} />
                        <Linha rotulo={t('PIN do POS')} valor={d.perfil.tem_pin_de_turno ? t('Definido (cifrado)') : t('Não definido')} />
                    </Bloco>
                    <Bloco icone="fa-location-dot" titulo={t('Empresas e moradas')}>
                        {d.empresas.length === 0 && <p className="text-sm text-slate-500">{t('Nenhuma.')}</p>}
                        {d.empresas.map((e) => (
                            <div key={e.id} className="border-b border-slate-100 pb-2 last:border-0">
                                <p className="text-sm font-semibold text-slate-800">{e.nome}</p>
                                <p className="text-xs text-slate-500">{e.morada ?? t('Sem morada')}{e.nif ? ` · NIF ${e.nif}` : ''}</p>
                                <p className="text-xs text-slate-400">{t('Último acesso: :dia', { dia: quando(e.ultimo_acesso) })}</p>
                            </div>
                        ))}
                    </Bloco>
                    <Bloco icone="fa-earth-africa" titulo={t('Localização das visitas')}>
                        <Linha rotulo={t('Eventos com a sua sessão')} valor={String(d.estatisticas.eventos)} />
                        <Linha rotulo={t('Países')} valor={d.estatisticas.paises?.join(', ') || '—'} />
                        <Linha rotulo={t('Cidades')} valor={d.estatisticas.cidades?.join(', ') || '—'} />
                        <p className="mt-2 text-xs text-slate-500">
                            <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                            {t('Não usamos o GPS. A cidade deduz-se do IP truncado, e só com consentimento de estatísticas.')}
                        </p>
                    </Bloco>
                </div>
            </section>

            {/* ─── O inventário ───────────────────────────────────────── */}
            <section className={cls(CARTAO, 'entra p-5')} style={cascata(4)}>
                <h3 className="flex items-center gap-2 font-bold text-slate-900">
                    <i className="fas fa-list-check text-indigo-500" aria-hidden="true" />{t('Para quê, com que fundamento e durante quanto tempo')}
                </h3>
                <div className="mt-4 grid grid-cols-1 items-start gap-3 md:grid-cols-2">
                    {p.inventario.map((c, i) => {
                        const aberto = aberta === c.chave;

                        return (
                            <div key={c.chave} className={cls('min-w-0 border border-slate-200', RAIO, TRANSICAO, aberto ? 'shadow-md' : 'hover:-translate-y-0.5 hover:shadow-sm')} style={cascata(i)}>
                                <button
                                    type="button"
                                    aria-expanded={aberto}
                                    onClick={() => porAberta(aberto ? null : c.chave)}
                                    className={cls('flex w-full items-center gap-3 p-3 text-left', FOCO, RAIO)}
                                >
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-orange-50 text-orange-600">
                                        <i className={cls('fas', c.icone)} aria-hidden="true" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold text-slate-800">{c.titulo}</span>
                                        <span className="block truncate text-xs text-slate-500">{c.dados.join(' · ')}</span>
                                    </span>
                                    <i className={cls('fas fa-chevron-down text-slate-400 transition-transform duration-300', aberto && 'rotate-180')} aria-hidden="true" />
                                </button>
                                {aberto && (
                                    <dl className="entra grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 border-t border-slate-100 p-3 text-xs">
                                        <dt className="font-semibold text-slate-500">{t('Dados')}</dt><dd className="text-slate-700">{c.dados.join('; ')}</dd>
                                        <dt className="font-semibold text-slate-500">{t('Para quê')}</dt><dd className="text-slate-700">{c.finalidade}</dd>
                                        <dt className="font-semibold text-slate-500">{t('Fundamento')}</dt><dd className="text-slate-700">{c.base_legal}</dd>
                                        <dt className="font-semibold text-slate-500">{t('Quanto tempo')}</dt><dd className="text-slate-700">{c.retencao}</dd>
                                        <dt className="font-semibold text-slate-500">{t('Quem recebe')}</dt><dd className="text-slate-700">{c.destinatarios}</dd>
                                    </dl>
                                )}
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* ─── Direitos e pedidos ─────────────────────────────────── */}
            <div className="grid gap-5 xl:grid-cols-5">
                <section className={cls(CARTAO, 'entra p-5 xl:col-span-2')} style={cascata(5)}>
                    <h3 className="flex items-center gap-2 font-bold text-slate-900">
                        <i className="fas fa-scale-balanced text-indigo-500" aria-hidden="true" />{t('Os seus direitos')}
                    </h3>
                    <ul className="mt-3 space-y-2.5">
                        {p.direitos.map((r) => (
                            <li key={r.chave} className="flex gap-3">
                                <i className={cls('fas mt-1 w-4 text-center text-orange-500', r.icone)} aria-hidden="true" />
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{r.nome}</p>
                                    <p className="text-xs text-slate-500">{r.descricao}</p>
                                    <p className="text-[11px] text-slate-400">{r.artigos}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className={cls(CARTAO, 'entra p-5 xl:col-span-3')} style={cascata(6)}>
                    <h3 className="flex items-center gap-2 font-bold text-slate-900">
                        <i className="fas fa-paper-plane text-indigo-500" aria-hidden="true" />{t('Fazer um pedido')}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {t('Respondemos em até :n dias. O apagamento não abrange o que a lei nos obriga a guardar (documentos fiscais) — dizemos-lhe o que fica e porquê.', { n: p.prazo_de_resposta_dias })}
                    </p>
                    <div className="mt-4 grid gap-3 md:grid-cols-3">
                        <Campo etiqueta={t('Tipo de pedido')} obrigatorio erro={errosDoPedido.tipo}>
                            <select className={entrada} value={pedido.tipo} onChange={(e) => porPedido({ ...pedido, tipo: e.target.value })}>
                                {p.tipos_de_pedido.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('O que pretende')} className="md:col-span-2" erro={errosDoPedido.mensagem}
                            ajuda={t('Obrigatório para rectificação, oposição, limitação e outros.')}>
                            <textarea className={cls(entrada, 'min-h-[84px]')} maxLength={2000} value={pedido.mensagem}
                                onChange={(e) => porPedido({ ...pedido, mensagem: e.target.value })} />
                        </Campo>
                    </div>
                    <div className="mt-3 flex justify-end">
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane" aTrabalhar={pedir.isPending} onClick={() => pedir.mutate()}>
                            {t('Enviar pedido')}
                        </Botao>
                    </div>

                    {d.pedidos.length > 0 && (
                        <ul className="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                            {d.pedidos.map((x) => {
                                const estado = ESTADOS_DO_PEDIDO[x.estado] ?? { rotulo: () => x.estado, cor: 'aviso' as const };

                                return (
                                    <li key={x.id} className="flex flex-wrap items-center gap-2 py-2.5 text-sm">
                                        <span className="font-mono text-xs text-slate-400">#{x.id}</span>
                                        <span className="font-semibold text-slate-700">{p.tipos_de_pedido.find((o) => o.valor === x.tipo)?.rotulo ?? x.tipo}</span>
                                        <Etiqueta cor={estado.cor} ponto>{estado.rotulo()}</Etiqueta>
                                        <span className="ml-auto text-xs text-slate-500">
                                            {x.respondido_em ? t('Respondido a :dia', { dia: quando(x.respondido_em) }) : t('Resposta até :dia', { dia: quando(x.prazo_em) })}
                                        </span>
                                        {x.resposta && <p className="w-full rounded-lg bg-slate-50 p-2 text-xs text-slate-600">{x.resposta}</p>}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>

            <Modal
                aberto={aFechar}
                aoFechar={() => porAFechar(false)}
                titulo={t('Terminar as outras sessões')}
                subtitulo={t('Os outros aparelhos voltam a pedir a senha.')}
                icone="fa-power-off"
                cor="perigo"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAFechar(false)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-power-off" aTrabalhar={terminar.isPending} onClick={() => terminar.mutate()}>
                            {t('Terminar')}
                        </Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Vão ser terminadas :n sessão(ões) noutros aparelhos. Esta sessão continua aberta.', { n: outrasSessoes })}
                </p>
                <label className="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                    <input type="checkbox" className="mt-1 h-4 w-4" checked={incluirAplicacao} onChange={(e) => porIncluirAplicacao(e.target.checked)} />
                    <span className="text-sm text-slate-700">
                        <span className="font-semibold">{t('Desligar também a aplicação móvel')}</span>
                        <span className="block text-xs text-slate-500">{t('A app vai pedir para entrar outra vez.')}</span>
                    </span>
                </label>
                <AvisoDeErro erro={terminar.error} />
            </Modal>
        </div>
    );
}

function Interruptor({ icone, titulo, descricao, ligado = true, sempre = false, aoMudar }: {
    icone: string;
    titulo: string;
    descricao: string;
    ligado?: boolean;
    sempre?: boolean;
    aoMudar?: (v: boolean) => void;
}) {
    return (
        <div className={cls('flex items-start gap-3 border border-slate-200 p-3', RAIO, TRANSICAO, 'hover:border-indigo-200 hover:shadow-sm')}>
            <span className={cls('grid h-9 w-9 shrink-0 place-items-center rounded-xl', sempre ? 'bg-emerald-50 text-emerald-600' : 'bg-indigo-50 text-indigo-600')}>
                <i className={cls('fas', icone)} aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-slate-800">{titulo}</p>
                <p className="text-xs text-slate-500">{descricao}</p>
            </div>
            {sempre ? (
                <Etiqueta cor="bom">{t('Sempre activos')}</Etiqueta>
            ) : (
                <button
                    type="button"
                    role="switch"
                    aria-checked={ligado}
                    aria-label={titulo}
                    onClick={() => aoMudar?.(!ligado)}
                    className={cls('relative h-7 w-12 shrink-0 rounded-full', TRANSICAO, FOCO, ligado ? 'bg-emerald-500' : 'bg-slate-300')}
                >
                    <span className={cls('absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all duration-300', ligado ? 'left-6' : 'left-1')} />
                </button>
            )}
        </div>
    );
}

function Bloco({ icone, titulo, children }: { icone: string; titulo: string; children: React.ReactNode }) {
    return (
        <div className={cls('min-w-0 border border-slate-200 p-4', RAIO, TRANSICAO, 'hover:shadow-sm')}>
            <p className="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                <i className={cls('fas text-orange-500', icone)} aria-hidden="true" />{titulo}
            </p>
            <div className="space-y-1.5">{children}</div>
        </div>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: string | null | undefined }) {
    return (
        <p className="flex justify-between gap-3 text-sm">
            <span className="text-slate-500">{rotulo}</span>
            <span className="truncate text-right font-medium text-slate-800">{valor || '—'}</span>
        </p>
    );
}
