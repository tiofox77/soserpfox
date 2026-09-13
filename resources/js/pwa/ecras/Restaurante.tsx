import { useCallback, useState } from 'react';

import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { useBaseViva, useEvento } from '../ganchos';
import type { Registo } from '../motor/base';
import { restaurante } from '../motor/restaurante';
import { getShift, type Turno } from '../motor/turno';
import { FolhasDoTurno, useTurno } from '../turno/Turno';
import { Comanda } from './restaurante/Comanda';
import { FolhaRecibo } from './restaurante/Receber';
import { Sala } from './restaurante/Sala';
import type { Recibo, Vista } from './restaurante/comum';

/**
 * O POS DE RESTAURANTE DO PWA — as mesas, sem rede.
 *
 * Era o `invoicing/offline/restaurant.blade.php` (Alpine). A diferença para o
 * balcão é a MESA: a venda fica aberta enquanto as pessoas comem, recebe mais
 * pratos, e só no fim vira documento. As contas são do motor
 * (`motor/restaurante.ts`); aqui é só o que se vê.
 *
 * Duas vistas — a SALA (`restaurante/Sala.tsx`) e a COMANDA
 * (`restaurante/Comanda.tsx`) — e os modais de receber e do recibo
 * (`restaurante/Receber.tsx`). O turno é o MESMO do POS de balcão
 * (`turno/Turno.tsx`): antes o restaurante só sabia LER o estado do turno e
 * mandava o empregado ao outro ecrã para o abrir — uma viagem que ninguém faz
 * com a sala cheia, e as comandas saíam na mesma para serem recusadas depois,
 * com a comida já servida.
 */
export function Restaurante() {
    const { rotas } = usePwa();
    const c = useTurno();

    // `null` = ainda não se sabe. Distingue «a carregar» de «não tem o módulo».
    const activo = useBaseViva<boolean | null>(() => restaurante.activo(), [], null);
    // O turno lido aqui também, com o seu `null`: a faixa «Turno fechado» não
    // pode piscar antes de a base responder que, afinal, está aberto.
    const turnoLido = useBaseViva<Turno | null>(() => getShift(), [], null);
    const turnoAberto = !!turnoLido?.open;

    const definicoes = useBaseViva<Registo>(() => restaurante.definicoes(), [], {});
    const salas = useBaseViva<Registo[]>(() => restaurante.salas(), [], []);
    const todasAsMesas = useBaseViva<Registo[] | null>(() => restaurante.mesas(), [], null);

    const [vista, setVista] = useState<Vista>('sala');
    const [comandaUuid, setComandaUuid] = useState<string | null>(null);
    const [salaEscolhida, setSalaEscolhida] = useState<number | null>(null);
    const [zonaId, setZonaId] = useState<number | null>(null);
    const [avisos, setAvisos] = useState<string[]>([]);
    const [recibo, setRecibo] = useState<Recibo | null>(null);

    /*
     * A SALA POR OMISSÃO É UMA QUE TENHA MESAS, e não a primeira da lista.
     * Numa empresa com mais do que uma sala, a primeira por ordem pode estar
     * vazia — e o empregado abria o PWA num salão sem mesa nenhuma, com as
     * mesas todas no aparelho, na sala do lado. Foi o que aconteceu a testar
     * num Android.
     *
     * Derivada, e não gravada uma vez: se as mesas chegarem depois das salas
     * (a primeira sincronização), a escolha corrige-se sozinha.
     */
    const salaPorOmissao = salas.find((s) => (todasAsMesas ?? []).some((m) => m.venue_id === s.id)) ?? salas[0];
    const salaId: number | null = salas.some((s) => s.id === salaEscolhida) ? salaEscolhida : (salaPorOmissao?.id ?? null);

    // Uma mesa que passou a balcão ou um preço que mudou não pode ficar só no
    // registo do servidor: quem está na sala tem de o ver.
    useEvento<Registo | undefined>('pwa:comanda-sincronizada', (d) => {
        if (d?.avisos?.length) setAvisos((a) => [...a, ...d.avisos.map(String)]);
    });

    const abrirComanda = useCallback((uuid: string) => {
        setComandaUuid(uuid);
        setVista('comanda');
        window.scrollTo({ top: 0 });
    }, []);

    const voltarSala = useCallback(async () => {
        const uuid = comandaUuid;

        setComandaUuid(null);
        setVista('sala');

        // Uma comanda aberta por engano, sem nada dentro, não fica a ocupar a
        // mesa: quem toca na mesa errada não devia ter de a ir desbloquear a
        // outro sítio. Lê-se da base e não do ecrã — o último toque pode ainda
        // estar a gravar.
        if (uuid) {
            const comanda = await restaurante.comanda(uuid);

            if (comanda && !(comanda.items || []).length && !comanda.enviada_cozinha) {
                await restaurante.descartar(uuid).catch(() => {});
            }
        }
    }, [comandaUuid]);

    // Estável: a comanda usa-o num efeito (volta à sala se a comanda desaparecer).
    const sairDaComanda = useCallback(() => { void voltarSala(); }, [voltarSala]);

    const aoFecharConta = useCallback((r: Recibo) => {
        setRecibo(r);
        // A conta já não está aberta: por trás do recibo fica a sala, que é
        // para onde o «Continuar» leva.
        setComandaUuid(null);
        setVista('sala');
    }, []);

    // ============ A EMPRESA NÃO TEM O MÓDULO ============
    // Só se chega aqui escrevendo o endereço à mão (a rota é fechada pelo
    // módulo e o menu não mostra a entrada). Mas o aparelho pode ter a página
    // guardada de quando ainda tinha o módulo, e aí é este ecrã que aparece —
    // e não uma sala vazia sem explicação.
    if (activo === false) {
        return (
            <div className="p-2 text-center">
                <div className="pwa-entra bg-white rounded-2xl shadow-sm p-8 max-w-md mx-auto mt-10">
                    <span className="w-20 h-20 mx-auto mb-4 rounded-3xl bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center pwa-flutua">
                        <i className="fas fa-utensils text-4xl text-slate-400" aria-hidden="true" />
                    </span>
                    <h2 className="text-lg font-bold text-slate-800">{t('Restaurante não activo')}</h2>
                    <p className="text-sm text-slate-500 mt-2">
                        {t('Esta empresa não tem o módulo de Restaurante. Fale com o administrador para o activar.')}
                    </p>
                    <a href={rotas.inicio}
                       className="pwa-toque inline-block mt-5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-600/20">
                        <i className="fas fa-house mr-1.5" aria-hidden="true" />{t('Voltar ao início')}
                    </a>
                </div>
            </div>
        );
    }

    if (activo === null) {
        return (
            <div className="flex justify-center py-20 text-orange-500" role="status" aria-label={t('A carregar…')}>
                <i className="fas fa-utensils fa-2x pwa-flutua" aria-hidden="true" />
            </div>
        );
    }

    return (
        <div className="-mx-4 -my-4">

            {/* ============ AVISOS DA SINCRONIZAÇÃO ============ */}
            {avisos.length > 0 && (
                <div role="status" className="pwa-desce mx-3 mt-3 rounded-2xl bg-amber-50 border border-amber-200 p-3 shadow-sm">
                    <div className="flex items-start gap-2">
                        <i className="fas fa-triangle-exclamation text-amber-500 mt-0.5 animate-pulse" aria-hidden="true" />
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-bold text-amber-800">{t('A sincronização mudou alguma coisa')}</p>
                            <ul className="mt-1 space-y-0.5">
                                {avisos.map((a, i) => <li key={i} className="text-[11px] text-amber-700">{a}</li>)}
                            </ul>
                        </div>
                        <button type="button" onClick={() => setAvisos([])} aria-label={t('Fechar')}
                                className="text-amber-400 hover:text-amber-600 text-lg leading-none px-1 transition-colors">&times;</button>
                    </div>
                </div>
            )}

            {/* ============ TURNO FECHADO ============
                O servidor recusa abrir comandas sem turno. Dizê-lo agora, e não
                quando a comanda já subiu e voltou recusada com a comida servida. */}
            {turnoLido !== null && !turnoAberto && (
                <div role="alert" className="pwa-desce mx-3 mt-3 rounded-2xl bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 p-3 flex items-center gap-3 shadow-sm">
                    <span className="w-9 h-9 shrink-0 rounded-xl bg-red-100 text-red-500 flex items-center justify-center">
                        <i className="fas fa-lock" aria-hidden="true" />
                    </span>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs font-bold text-red-800">{t('Turno fechado')}</p>
                        <p className="text-[11px] text-red-600">{t('Sem turno aberto as comandas não sobem.')}</p>
                    </div>
                    {/* ABRE AQUI, e não noutro ecrã.
                        Mandava para o POS de balcão: com a sala cheia, ninguém faz essa
                        viagem — tiravam-se as comandas na mesma, o servidor recusava-as
                        por falta de turno, e a comida já tinha saído. */}
                    <button type="button" onClick={c.abrirAbertura}
                            className="pwa-toque pwa-pulsa shrink-0 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white text-xs font-bold px-3 py-2 rounded-xl shadow">
                        <i className="fas fa-lock-open mr-1" aria-hidden="true" />{t('Abrir turno')}
                    </button>
                </div>
            )}

            {vista === 'sala' && (
                <Sala c={c} turnoAberto={turnoAberto}
                      salas={salas} salaId={salaId}
                      escolherSala={(id) => { setSalaEscolhida(id); setZonaId(null); }}
                      zonaId={zonaId} escolherZona={setZonaId}
                      todasAsMesas={todasAsMesas} abrirComanda={abrirComanda} />
            )}

            {vista === 'comanda' && comandaUuid && (
                <Comanda key={comandaUuid} uuid={comandaUuid} c={c}
                         todasAsMesas={todasAsMesas ?? []} definicoes={definicoes}
                         voltarSala={sairDaComanda} aoFechar={aoFecharConta} />
            )}

            {recibo && <FolhaRecibo recibo={recibo} aoFechar={() => setRecibo(null)} />}

            {/* Os modais do turno: os MESMOS do POS de balcão, não uma segunda versão deles. */}
            <FolhasDoTurno c={c} />
        </div>
    );
}
