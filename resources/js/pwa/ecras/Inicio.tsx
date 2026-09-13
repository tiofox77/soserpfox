import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { hora, kz, useBaseViva } from '../ganchos';
import { db, lerMeta, type Registo } from '../motor/base';
import { getShift, type Turno } from '../motor/turno';
import { FacturasDeHoje } from './inicio/FacturasDeHoje';
import { Ferramentas } from './inicio/Ferramentas';
import { PorEnviar } from './inicio/PorEnviar';

/**
 * O ECRÃ INICIAL DO PWA — era o `invoicing/offline/index.blade.php`.
 *
 * É o primeiro que o empregado vê de manhã, e estava a servir duas plateias ao
 * mesmo tempo: quem vem vender e quem vem resolver um problema. A ordem das
 * coisas é a decisão deste ecrã (e é o que `tests/browser/pwa-inicio.spec.js`
 * fixa): à superfície o que se usa para vender; as ferramentas numa gaveta
 * fechada; e o que apaga dados SEMPRE atrás dessa gaveta.
 *
 * Tudo o que se mostra vem da base local e é uma consulta VIVA: a sincronização
 * escreve e o ecrã muda sozinho — o Blade ouvia `pwa:synced` e relia tudo.
 */
export function Inicio() {
    const { menu = [] } = usePwa();
    const tem = (chave: string) => menu.some((m) => m.chave === chave);

    return (
        <div className="max-w-2xl mx-auto">
            <QuemEOnde />
            <CartaoDoTurno podeIrAoPos={tem('pos')} />
            <PorEnviar />
            <Atalhos pos={tem('pos')} restaurante={tem('restaurante')} documentos={tem('documentos')} clientes={tem('clientes')} />
            <Contagens />
            <FacturasDeHoje />
            <Ferramentas />
        </div>
    );
}

/**
 * QUEM SOU E ONDE ESTOU, em duas linhas.
 *
 * Era um bloco azul que ocupava um terço do ecrã para dizer o nome, o email, a
 * empresa, o NIF, o armazém e a última sincronização — seis coisas que quase
 * nunca mudam, todas em destaque, antes de qualquer coisa que se possa fazer.
 * O que ali interessa mesmo é UMA: em que empresa é que estou, porque quem
 * trabalha em duas precisa de saber isso ANTES de vender. O resto passou para
 * as Ferramentas («Estado do aparelho»).
 *
 * O nome vem do `meta.user` e não das props do servidor: quem entrou por PIN
 * passa a ser o operador activo (é ele que o talão e a AGT registam), e a
 * sessão do servidor é a do último que sincronizou.
 */
function QuemEOnde() {
    const { utilizador } = usePwa();
    const quem = useBaseViva<{ nome: string; empresa: string; armazem: string } | null>(async () => {
        const user = await lerMeta<Registo>('user');
        const empresa = await lerMeta<Registo>('company');
        const armazem = await lerMeta<Registo>('warehouse');

        return {
            nome: String(user?.name || utilizador?.nome || ''),
            empresa: String(empresa?.name || ''),
            armazem: String(armazem?.name || ''),
        };
    }, [utilizador?.nome], null);

    const nome = quem ? (quem.nome.trim() || t('Utilizador')) : '…';
    const inicial = quem ? (quem.nome.trim().charAt(0).toUpperCase() || '?') : '?';

    return (
        <div className="pwa-entra flex items-center gap-3 mb-4">
            <div className="w-11 h-11 shrink-0 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-bold text-lg shadow-lg shadow-blue-600/20"
                 aria-hidden="true">
                {inicial}
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-base font-bold text-gray-800 leading-tight truncate">{nome}</p>
                <p className="text-xs text-gray-500 truncate">
                    {quem?.empresa && (
                        <span><i className="fas fa-building mr-1 text-gray-400" aria-hidden="true" />{quem.empresa}</span>
                    )}
                    {quem?.armazem && (
                        <span className="text-gray-400">{quem.empresa ? ' · ' : ''}<i className="fas fa-warehouse mr-1" aria-hidden="true" />{quem.armazem}</span>
                    )}
                </p>
            </div>
        </div>
    );
}

/**
 * O TURNO.
 *
 * Vem do cache local, por isso continua a dizer a verdade sem internet — que é
 * quando faz falta: quem está na caixa precisa de saber se o turno está aberto,
 * desde quando, e com quanto começou.
 */
function CartaoDoTurno({ podeIrAoPos }: { podeIrAoPos: boolean }) {
    const { rotas } = usePwa();
    const turno = useBaseViva<Turno | null>(() => getShift(), [], null);
    const aberto = !!turno?.open;

    // A data vem em ISO com fuso, por isso o telemóvel mostra-a na hora dele —
    // que é a de quem está ao balcão. Se vier ilegível, `hora()` devolve nada
    // em vez de «Invalid Date».
    const desde = turno?.opened_at ? hora(turno.opened_at) : '';

    return (
        <div className={`pwa-entra rounded-2xl shadow-sm p-4 mb-4 border transition-colors duration-300 ${aberto
            ? 'bg-gradient-to-br from-emerald-50 to-teal-50 border-emerald-200'
            : 'bg-gray-50 border-gray-200'}`}
             style={{ animationDelay: '40ms' }}>
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2 min-w-0">
                    <div className={`relative w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${aberto ? 'bg-emerald-100' : 'bg-gray-200'}`}>
                        <i className={`fas fa-cash-register ${aberto ? 'text-emerald-600' : 'text-gray-500'}`} aria-hidden="true" />
                        {aberto && (
                            <span className="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white animate-pulse" aria-hidden="true" />
                        )}
                    </div>
                    <div className="min-w-0">
                        <p className={`font-bold text-sm ${aberto ? 'text-emerald-900' : 'text-gray-700'}`}>
                            {aberto ? t('Turno aberto') : t('Sem turno aberto')}
                        </p>
                        {aberto && turno?.number && (
                            <p className="text-xs text-gray-500 truncate">
                                {t('Turno :numero', { numero: `#${turno.number}` })}
                                {desde && ` · ${t('desde :hora', { hora: desde })}`}
                            </p>
                        )}
                        {turno && !aberto && (
                            <p className="text-xs text-gray-500">{t('Abra um turno no POS antes de começar a vender.')}</p>
                        )}
                    </div>
                </div>

                {/* O turno abre-se no POS. Sem acesso ao POS não há botão: levava
                    a um 403 e o cartão do turno passava a parecer avariado. */}
                {podeIrAoPos && turno && (
                    <a href={rotas.pos}
                       className={`pwa-toque shrink-0 px-3 py-2 rounded-xl text-xs font-bold text-white shadow-sm ${aberto
                           ? 'bg-emerald-600 hover:bg-emerald-700'
                           : 'bg-blue-600 hover:bg-blue-700 pwa-pulsa'}`}>
                        <i className={`fas ${aberto ? 'fa-arrow-right' : 'fa-lock-open'} mr-1`} aria-hidden="true" />
                        {aberto ? t('Ir para o POS') : t('Abrir turno')}
                    </a>
                )}
            </div>

            {aberto && (
                <div className="pwa-entra grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-emerald-200 tabular-nums">
                    <div className="min-w-0">
                        <p className="text-[10px] uppercase font-bold text-gray-500">{t('Abertura')}</p>
                        <p className="text-sm font-bold text-gray-900 truncate">{kz(turno?.opening_balance)}</p>
                    </div>
                    <div className="min-w-0">
                        <p className="text-[10px] uppercase font-bold text-gray-500">{t('Dinheiro')}</p>
                        <p className="text-sm font-bold text-gray-900 truncate">{kz(turno?.cash_sales)}</p>
                    </div>
                    <div className="min-w-0">
                        <p className="text-[10px] uppercase font-bold text-gray-500">{t('Total vendido')}</p>
                        <p className="text-sm font-bold text-emerald-700 truncate">{kz(turno?.total_sales)}</p>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * AS ACÇÕES.
 *
 * Seguem a MESMA regra do menu de baixo e das rotas — módulo, permissão e a
 * escolha da empresa (App\Support\MenuDoPwa, que chega no `menu`). Um atalho
 * para um ecrã que responde 403 é pior do que não ter atalho nenhum: parece
 * avaria.
 *
 * HIERARQUIA, e não um arco-íris. Eram quatro faixas de largura inteira em
 * quatro cores a gritar — o vermelho, o laranja, o verde e o roxo todos com o
 * mesmo peso. Sem hierarquia, o olho não sabe onde pousar, e «Cliente» pesava
 * tanto como o POS, que é a razão de a aplicação existir. Agora: o que se usa a
 * toda a hora fica grande e a cor; o resto fica numa grelha calma por baixo.
 *
 * O «Vender» tem de caber no primeiro ecrã a 360×800 sem rolar — é um ensaio.
 */
function Atalhos({ pos, restaurante, documentos, clientes }: { pos: boolean; restaurante: boolean; documentos: boolean; clientes: boolean }) {
    const { rotas } = usePwa();

    if (!pos && !restaurante && !documentos && !clientes) return null;

    // A entrada (`pwa-entra`) vai numa caixa à volta e o toque (`pwa-toque`,
    // `pwa-cartao`) no próprio atalho: a animação acaba em `transform: none`
    // e, no mesmo elemento, tapava o encolher ao toque e o levantar no hover.
    return (
        <div className="space-y-2.5">
            {pos && (
                <div className="pwa-entra" style={{ animationDelay: '80ms' }}>
                    <a href={rotas.pos}
                       className="pwa-toque group flex items-center gap-4 bg-gradient-to-br from-orange-500 to-red-600 text-white rounded-2xl shadow-lg shadow-orange-600/20 p-4 hover:shadow-xl hover:shadow-orange-600/30">
                        <span className="w-12 h-12 shrink-0 rounded-2xl bg-white/20 flex items-center justify-center">
                            <i className="fas fa-cash-register text-xl pwa-flutua" aria-hidden="true" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-lg font-bold leading-tight">{t('Vender')}</span>
                            <span className="block text-xs opacity-85">{t('Balcão — Fatura-Recibo em segundos')}</span>
                        </span>
                        <i className="fas fa-chevron-right opacity-60 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </a>
                </div>
            )}

            {restaurante && (
                <div className="pwa-entra" style={{ animationDelay: '110ms' }}>
                    <a href={rotas.restaurante}
                       className="pwa-toque group flex items-center gap-4 bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-2xl shadow-lg shadow-amber-600/20 p-4 hover:shadow-xl hover:shadow-amber-600/30">
                        <span className="w-12 h-12 shrink-0 rounded-2xl bg-white/20 flex items-center justify-center">
                            <i className="fas fa-utensils text-xl" aria-hidden="true" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-lg font-bold leading-tight">{t('Mesas')}</span>
                            <span className="block text-xs opacity-85">{t('Sala, comandas e conta')}</span>
                        </span>
                        <i className="fas fa-chevron-right opacity-60 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </a>
                </div>
            )}

            {/* O que se faz de vez em quando: sem cor de fundo, para não competir
                com o que se faz a toda a hora. */}
            {(documentos || clientes) && (
                <div className="grid grid-cols-2 gap-2.5">
                    {documentos && (
                        <div className="pwa-entra" style={{ animationDelay: '140ms' }}>
                            <a href={rotas.novoDocumento}
                               className="pwa-toque pwa-cartao group block h-full bg-white rounded-2xl shadow-sm p-4 border border-transparent hover:border-emerald-100">
                                <span className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2 transition-transform group-hover:scale-110">
                                    <i className="fas fa-file-circle-plus" aria-hidden="true" />
                                </span>
                                <span className="block text-sm font-bold text-gray-800">{t('Novo documento')}</span>
                                <span className="block text-[11px] text-gray-400 leading-tight">{t('Fatura ou proforma')}</span>
                            </a>
                        </div>
                    )}
                    {clientes && (
                        <div className="pwa-entra" style={{ animationDelay: '170ms' }}>
                            <a href={rotas.novoCliente}
                               className="pwa-toque pwa-cartao group block h-full bg-white rounded-2xl shadow-sm p-4 border border-transparent hover:border-purple-100">
                                <span className="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-2 transition-transform group-hover:scale-110">
                                    <i className="fas fa-user-plus" aria-hidden="true" />
                                </span>
                                <span className="block text-sm font-bold text-gray-800">{t('Novo cliente')}</span>
                                <span className="block text-[11px] text-gray-400 leading-tight">{t('Sobe quando houver rede')}</span>
                            </a>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * O QUE ESTE APARELHO TEM, numa linha.
 *
 * Eram quatro caixas grandes a dizer «PRODUTOS EM CACHE» e «CLIENTES EM CACHE»
 * — palavras de quem escreve o programa, não de quem o usa: ao balcão ninguém
 * sabe o que é uma cache, e a pergunta que se faz é «tenho aqui os artigos?».
 * Além disso, «Por sincronizar» repetia o cartão «Por enviar», que já mostra O
 * QUÊ está preso e não só quantos.
 */
function Contagens() {
    const c = useBaseViva<{ artigos: number; clientes: number; documentos: number } | null>(async () => ({
        artigos: await db.products.count(),
        clientes: await db.clients.count(),
        documentos: await db.draft_documents.count(),
    }), [], null);

    const caixas: { rotulo: string; valor: number | undefined; icone: string; cor: string }[] = [
        { rotulo: t('Artigos'), valor: c?.artigos, icone: 'fa-box', cor: 'text-blue-500' },
        { rotulo: t('Clientes'), valor: c?.clientes, icone: 'fa-users', cor: 'text-purple-500' },
        { rotulo: t('Documentos'), valor: c?.documentos, icone: 'fa-file-lines', cor: 'text-emerald-500' },
    ];

    return (
        <div className="pwa-entra flex items-stretch bg-white rounded-2xl shadow-sm mt-4 mb-4 divide-x divide-gray-100" style={{ animationDelay: '200ms' }}>
            {caixas.map((x) => (
                <div key={x.rotulo} className="flex-1 px-3 py-3 text-center">
                    <p className="text-xl font-bold text-gray-800 leading-none tabular-nums">{x.valor ?? '—'}</p>
                    <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mt-1">
                        <i className={`fas ${x.icone} ${x.cor} mr-1`} aria-hidden="true" />{x.rotulo}
                    </p>
                </div>
            ))}
        </div>
    );
}
