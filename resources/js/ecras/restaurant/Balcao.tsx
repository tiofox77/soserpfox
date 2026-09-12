import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type ArtigoDaCarta, type Mesa } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { GrelhaDeArtigos, PainelDaComanda, SemTurno } from './PecasDaComanda';

/**
 * O BALCÃO DO RESTAURANTE.
 *
 * Um ecrã só, com duas metades: à esquerda escolhe-se (as mesas, depois os
 * artigos), à direita está a comanda. Não há navegação nenhuma no meio — é
 * um serviço cheio, e cada página que abre é tempo com o cliente à frente.
 *
 * O CANAL DECIDE O CAMINHO. Mesa toca-se; balcão, take-away e entrega abrem
 * pelo botão próprio — e a entrega pede morada, porque uma entrega sem morada
 * é uma comanda que ninguém sabe entregar.
 *
 * SEM TURNO NÃO SE ABRE NADA, e é dito com um modal que leva ao sítio de o
 * abrir: um aviso que se apaga sozinho não diz para onde ir.
 */

const CORES_DA_MESA: Record<string, string> = {
    available: 'bg-emerald-50 border-emerald-200 hover:border-emerald-400 text-emerald-800',
    reserved: 'bg-indigo-50 border-indigo-200 hover:border-indigo-400 text-indigo-800',
    occupied: 'bg-amber-50 border-amber-200 hover:border-amber-400 text-amber-800',
    waiting_kitchen: 'bg-orange-50 border-orange-200 hover:border-orange-400 text-orange-800',
    served: 'bg-sky-50 border-sky-200 hover:border-sky-400 text-sky-800',
    billing: 'bg-violet-50 border-violet-200 hover:border-violet-400 text-violet-800',
    cleaning: 'bg-slate-100 border-slate-300 text-slate-600',
    blocked: 'bg-red-50 border-red-200 text-red-700',
};

const paraForaVazio = (canal: 'takeaway' | 'delivery') => ({
    channel: canal, customer_name: '', customer_phone: '', delivery_address: '', delivery_fee: '0',
});

const artigoRapidoVazio = () => ({ name: '', price: '', category_id: '', tax_rate_id: '', manage_stock: false });

export default function Balcao() {
    const cache = useQueryClient();

    const [casa, porCasa] = useState<number | ''>('');
    const [zona, porZona] = useState<number | ''>('');
    const [comanda, porComanda] = useState<number | null>(null);
    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [semTurno, porSemTurno] = useState(false);

    const [aAbrir, porAAbrir] = useState<Mesa | null>(null);
    const [pessoas, porPessoas] = useState('2');
    const [paraFora, porParaFora] = useState<ReturnType<typeof paraForaVazio> | null>(null);
    const [rapido, porRapido] = useState<ReturnType<typeof artigoRapidoVazio> | null>(null);
    const [observacoes, porObservacoes] = useState<{ artigo: ArtigoDaCarta; nota: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'comandas', 'opcoes'],
        queryFn: restaurante.comandas.opcoes,
        staleTime: 5 * 60_000,
    });

    const opcoesDaSala = useQuery({
        queryKey: ['restaurante', 'sala', 'opcoes'],
        queryFn: restaurante.sala.opcoes,
        staleTime: 5 * 60_000,
    });

    const mapa = useQuery({
        queryKey: ['restaurante', 'sala', 'mapa', casa, zona],
        queryFn: () => restaurante.sala.mapa(casa, zona),
        enabled: comanda === null,
        refetchInterval: 20_000,
    });

    useEffect(() => {
        if (casa === '' && opcoesDaSala.data?.estabelecimentos[0]) {
            porCasa(Number(opcoesDaSala.data.estabelecimentos[0].valor));
        }
    }, [opcoesDaSala.data]);

    const falhou = (e: unknown) => {
        if (e instanceof ErroDaApi && e.estado === 409 && (e.corpo as { falta_turno?: boolean }).falta_turno) {
            porSemTurno(true);

            return;
        }

        porErro(e);
        porErros(e instanceof ErroDaApi ? e.erros : {});
    };

    const entrarNaComanda = (id: number) => {
        porComanda(id);
        porErro(null);
        porErros({});
        void cache.invalidateQueries({ queryKey: ['restaurante', 'sala'] });
    };

    const abrirMesa = useMutation({
        mutationFn: () => restaurante.sala.abrir({ table_id: aAbrir!.id, guest_count: Number(pessoas) }),
        onSuccess: (r) => { porAAbrir(null); entrarNaComanda(r.order_id); },
        onError: falhou,
    });

    const abrirBalcao = useMutation({
        mutationFn: () => restaurante.sala.abrirSemMesa({ venue_id: casa, channel: 'counter' }),
        onSuccess: (r) => entrarNaComanda(r.order_id),
        onError: falhou,
    });

    const abrirParaFora = useMutation({
        mutationFn: () => restaurante.sala.abrirSemMesa({
            ...paraFora, venue_id: casa, delivery_fee: Number(paraFora!.delivery_fee) || 0,
        }),
        onSuccess: (r) => { porParaFora(null); entrarNaComanda(r.order_id); },
        onError: falhou,
    });

    const acrescentar = useMutation({
        mutationFn: ({ artigo, nota }: { artigo: ArtigoDaCarta; nota?: string }) =>
            restaurante.comandas.acrescentar(comanda!, { product_id: artigo.id, quantity: 1, notes: nota || null }),
        onSuccess: () => {
            porErro(null);
            void cache.invalidateQueries({ queryKey: ['restaurante', 'comanda', comanda] });
        },
        onError: falhou,
    });

    const criarArtigo = useMutation({
        mutationFn: () => restaurante.comandas.artigoRapido({
            ...rapido, price: Number(rapido!.price),
            category_id: rapido!.category_id ? Number(rapido!.category_id) : null,
            tax_rate_id: Number(rapido!.tax_rate_id),
        }),
        onSuccess: (r) => {
            porRapido(null); porErros({}); porErro(null); porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['restaurante', 'artigos'] });
        },
        onError: falhou,
    });

    if (opcoes.isPending || opcoesDaSala.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;
    if (opcoesDaSala.isError) return <AvisoDeErro erro={opcoesDaSala.error} />;

    const o = opcoes.data;
    const os = opcoesDaSala.data;
    const m = mapa.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Balcão do Restaurante')}
                subtitulo={comanda ? t('A atender') : t('Escolha uma mesa ou abra uma venda ao balcão')}
                icone="fa-cash-register"
                cor="laranja"
                accoes={
                    comanda ? (
                        <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porComanda(null)}>
                            <i className="fas fa-arrow-left" aria-hidden="true" />
                            {t('Voltar às mesas')}
                        </button>
                    ) : (
                        <>
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                disabled={abrirBalcao.isPending}
                                onClick={() => abrirBalcao.mutate()}
                            >
                                <i className="fas fa-cash-register" aria-hidden="true" />
                                {t('Venda ao balcão')}
                            </button>
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => { porErros({}); porParaFora(paraForaVazio('takeaway')); }}
                            >
                                <i className="fas fa-bag-shopping" aria-hidden="true" />
                                {t('Take-away')}
                            </button>
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => { porErros({}); porParaFora(paraForaVazio('delivery')); }}
                            >
                                <i className="fas fa-motorcycle" aria-hidden="true" />
                                {t('Entrega')}
                            </button>
                        </>
                    )
                }
            >
                {!o.tem_turno && (
                    <EstadoNaFaixa icone="fa-triangle-exclamation">
                        {t('Sem turno aberto')}
                    </EstadoNaFaixa>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_26rem]">
                <div className={cls(CARTAO, 'flex min-h-[32rem] flex-col p-4 lg:h-[calc(100dvh-16rem)]')}>
                    {comanda === null ? (
                        <>
                            <div className="mb-3 flex flex-wrap items-end gap-3">
                                <Campo etiqueta={t('Estabelecimento')} className="w-52">
                                    <select
                                        value={casa}
                                        onChange={(e) => { porCasa(e.target.value ? Number(e.target.value) : ''); porZona(''); }}
                                        className={entrada}
                                    >
                                        {os.estabelecimentos.map((v) => <option key={v.valor} value={v.valor}>{v.rotulo}</option>)}
                                    </select>
                                </Campo>

                                {(m?.zonas.length ?? 0) > 0 && (
                                    <Campo etiqueta={t('Zona')} className="w-44">
                                        <select value={zona} onChange={(e) => porZona(e.target.value ? Number(e.target.value) : '')} className={entrada}>
                                            <option value="">{t('Todas')}</option>
                                            {m?.zonas.map((z) => <option key={z.valor} value={z.valor}>{z.rotulo}</option>)}
                                        </select>
                                    </Campo>
                                )}
                            </div>

                            <div className="min-h-0 flex-1 overflow-y-auto">
                                {mapa.isPending ? (
                                    <Carregando linhas={5} />
                                ) : (m?.mesas.length ?? 0) === 0 ? (
                                    <SemNada
                                        icone="fa-chair"
                                        titulo={t('Sem mesas nesta sala')}
                                        frase={t('Pode na mesma vender ao balcão, em take-away ou em entrega.')}
                                    />
                                ) : (
                                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4">
                                        {m?.mesas.map((mesa) => (
                                            <button
                                                key={mesa.id}
                                                type="button"
                                                disabled={['blocked'].includes(mesa.estado)}
                                                onClick={() => {
                                                    if (mesa.comanda) { entrarNaComanda(mesa.comanda.id); return; }

                                                    porPessoas(String(Math.min(2, Math.max(1, mesa.lugares))));
                                                    porErros({});
                                                    porAAbrir(mesa);
                                                }}
                                                className={cls(
                                                    'card-hover flex flex-col items-start gap-0.5 border-2 p-3 text-left shadow-sm disabled:cursor-not-allowed disabled:opacity-60',
                                                    RAIO, FOCO,
                                                    CORES_DA_MESA[mesa.estado] ?? CORES_DA_MESA.available,
                                                )}
                                            >
                                                <span className="w-full truncate text-base font-bold text-slate-900">
                                                    {mesa.nome || mesa.codigo}
                                                </span>
                                                <span className="text-xs font-semibold">{mesa.estado_rotulo}</span>
                                                <span className="text-xs text-slate-500">
                                                    <i className="fas fa-user-group mr-1" aria-hidden="true" />{mesa.lugares}
                                                </span>
                                                {mesa.comanda && (
                                                    <span className="mt-1 w-full border-t border-white/60 pt-1 text-xs font-bold tabular-nums text-slate-900">
                                                        {kz(mesa.comanda.total)} Kz
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <GrelhaDeArtigos
                            categorias={o.categorias}
                            aoEscolher={(a) => acrescentar.mutate({ artigo: a })}
                            aoPedirNota={(a) => porObservacoes({ artigo: a, nota: '' })}
                            accoes={
                                <>
                                    {!o.definicoes.exige_ficha && (
                                        <Botao
                                            icone="fa-plus"
                                            onClick={() => {
                                                porErros({});
                                                porRapido({ ...artigoRapidoVazio(), tax_rate_id: o.impostos.at(-1)?.valor ?? '' });
                                            }}
                                        >
                                            {t('Prato novo')}
                                        </Botao>
                                    )}
                                </>
                            }
                        />
                    )}
                </div>

                <div className={cls(CARTAO, 'flex min-h-[24rem] flex-col p-4 lg:h-[calc(100dvh-16rem)]')}>
                    {comanda === null ? (
                        <SemNada
                            icone="fa-receipt"
                            titulo={t('Nenhuma comanda aberta')}
                            frase={t('Toque numa mesa, ou abra uma venda ao balcão.')}
                        />
                    ) : (
                        <PainelDaComanda
                            key={comanda}
                            id={comanda}
                            opcoes={o}
                            compacto
                            aoRecado={porRecado}
                            aoTrocarDeComanda={porComanda}
                            aoFechar={() => porComanda(null)}
                        />
                    )}
                </div>
            </div>

            {/* ─── Abrir mesa ─── */}
            <Modal
                aberto={aAbrir !== null}
                aoFechar={() => porAAbrir(null)}
                titulo={t('Quantas pessoas?')}
                subtitulo={aAbrir ? (aAbrir.nome || aAbrir.codigo) : undefined}
                icone="fa-user-group"
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAAbrir(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={abrirMesa.isPending} onClick={() => abrirMesa.mutate()}>
                            {t('Abrir comanda')}
                        </Botao>
                    </>
                }
            >
                <Campo
                    etiqueta={t('Pessoas')}
                    obrigatorio
                    erro={erros.guest_count}
                    ajuda={aAbrir ? t('Esta mesa tem :n lugares.', { n: String(aAbrir.lugares) }) : undefined}
                >
                    <input
                        type="number" min="1" max="100" autoFocus
                        value={pessoas}
                        onChange={(e) => porPessoas(e.target.value)}
                        className={entrada}
                    />
                </Campo>
            </Modal>

            {/* ─── Take-away / entrega ─── */}
            <Modal
                aberto={paraFora !== null}
                aoFechar={() => porParaFora(null)}
                titulo={paraFora?.channel === 'delivery' ? t('Entrega') : t('Take-away')}
                subtitulo={t('Para quem é a comida, e para onde vai')}
                icone={paraFora?.channel === 'delivery' ? 'fa-motorcycle' : 'fa-bag-shopping'}
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porParaFora(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={abrirParaFora.isPending} onClick={() => abrirParaFora.mutate()}>
                            {t('Abrir comanda')}
                        </Botao>
                    </>
                }
            >
                {paraFora && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} erro={erros.customer_name}>
                            <input
                                value={paraFora.customer_name}
                                onChange={(e) => porParaFora({ ...paraFora, customer_name: e.target.value })}
                                className={entrada}
                            />
                        </Campo>

                        <Campo etiqueta={t('Telefone')} obrigatorio erro={erros.customer_phone}>
                            <input
                                value={paraFora.customer_phone}
                                onChange={(e) => porParaFora({ ...paraFora, customer_phone: e.target.value })}
                                className={entrada}
                            />
                        </Campo>

                        {paraFora.channel === 'delivery' && (
                            <>
                                <Campo etiqueta={t('Morada')} obrigatorio erro={erros.delivery_address}>
                                    <textarea
                                        value={paraFora.delivery_address}
                                        onChange={(e) => porParaFora({ ...paraFora, delivery_address: e.target.value })}
                                        rows={2}
                                        className={cls(entrada, 'h-auto py-2')}
                                    />
                                </Campo>

                                <Campo
                                    etiqueta={t('Taxa de entrega')}
                                    erro={erros.delivery_fee}
                                    ajuda={t('Vai uma só vez, mesmo que a conta se divida.')}
                                >
                                    <input
                                        type="number" step="0.01" min="0"
                                        value={paraFora.delivery_fee}
                                        onChange={(e) => porParaFora({ ...paraFora, delivery_fee: e.target.value })}
                                        className={cls(entrada, 'text-right tabular-nums')}
                                    />
                                </Campo>
                            </>
                        )}
                    </div>
                )}
            </Modal>

            {/* ─── Prato novo sem sair do balcão ─── */}
            <Modal
                aberto={rapido !== null}
                aoFechar={() => porRapido(null)}
                titulo={t('Prato novo')}
                subtitulo={t('Fica no catálogo — aparece também na carta e na facturação')}
                icone="fa-plus"
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porRapido(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={criarArtigo.isPending} onClick={() => criarArtigo.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {rapido && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input
                                value={rapido.name}
                                onChange={(e) => porRapido({ ...rapido, name: e.target.value })}
                                className={entrada}
                                autoFocus
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Preço')} obrigatorio erro={erros.price}>
                                <input
                                    type="number" step="0.01" min="0"
                                    value={rapido.price}
                                    onChange={(e) => porRapido({ ...rapido, price: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Imposto')} obrigatorio erro={erros.tax_rate_id}>
                                <select
                                    value={rapido.tax_rate_id}
                                    onChange={(e) => porRapido({ ...rapido, tax_rate_id: e.target.value })}
                                    className={entrada}
                                >
                                    <option value="">{t('Escolher…')}</option>
                                    {o.impostos.map((i) => <option key={i.valor} value={i.valor}>{i.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Categoria')} erro={erros.category_id}>
                            <select
                                value={rapido.category_id}
                                onChange={(e) => porRapido({ ...rapido, category_id: e.target.value })}
                                className={entrada}
                            >
                                <option value="">{t('Sem categoria')}</option>
                                {o.categorias.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                            </select>
                        </Campo>

                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={rapido.manage_stock}
                                onChange={(e) => porRapido({ ...rapido, manage_stock: e.target.checked })}
                                className="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            {t('Controlar stock deste artigo')}
                        </label>
                    </div>
                )}
            </Modal>

            {/* ─── Uma observação para a cozinha ─── */}
            <Modal
                aberto={observacoes !== null}
                aoFechar={() => porObservacoes(null)}
                titulo={t('Observação para a cozinha')}
                subtitulo={observacoes?.artigo.nome}
                icone="fa-comment-dots"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porObservacoes(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            onClick={() => {
                                if (observacoes) acrescentar.mutate({ artigo: observacoes.artigo, nota: observacoes.nota });
                                porObservacoes(null);
                            }}
                        >
                            {t('Acrescentar')}
                        </Botao>
                    </>
                }
            >
                {observacoes && (
                    <Campo etiqueta={t('Observação')} ajuda={t('Sai no bilhete da cozinha: «sem cebola», «bem passado».')}>
                        <input
                            value={observacoes.nota}
                            onChange={(e) => porObservacoes({ ...observacoes, nota: e.target.value })}
                            className={entrada}
                            autoFocus
                        />
                    </Campo>
                )}
            </Modal>

            <SemTurno aberto={semTurno} aoFechar={() => porSemTurno(false)} />
        </div>
    );
}
