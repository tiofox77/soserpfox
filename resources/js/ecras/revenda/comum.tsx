import { useState, type ReactNode } from 'react';

import type { Comissao, EstadoDaEmpresa } from '@/api/revenda';
import { t } from '@/i18n';
import { Etiqueta } from '@/ui/Etiqueta';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, kz } from '@/ui/tokens';

export { Cabecalho, Numero } from '../cliente/comum';

/**
 * AS PEÇAS DO PORTAL DO REVENDEDOR (programa de revendedores, 16/09/2026).
 *
 * A identidade do portal: violeta → esmeralda, a parceria que dá frutos. O
 * cabeçalho e os cartões de números são os do portal do cliente.
 */
export const GRADIENTE_DO_PORTAL = 'from-violet-600 via-purple-600 to-emerald-600';

export const kwanzas = (v: number | null | undefined) => `${kz(v ?? 0)} Kz`;

const COR_DO_ESTADO: Record<EstadoDaEmpresa['chave'], 'bom' | 'primaria' | 'perigo' | 'neutra' | 'aviso'> = {
    teste: 'primaria',
    activa: 'bom',
    vencida: 'perigo',
    sem_plano: 'neutra',
    suspensa: 'neutra',
};

const ICONE_DO_ESTADO: Record<EstadoDaEmpresa['chave'], string> = {
    teste: 'fa-hourglass-half',
    activa: 'fa-circle-check',
    vencida: 'fa-circle-xmark',
    sem_plano: 'fa-circle-minus',
    suspensa: 'fa-ban',
};

/** O estado de uma empresa: o da subscrição e as duas marcas que pedem atenção. */
export function EstadoDaEmpresaEtiquetas({ e }: { e: EstadoDaEmpresa }) {
    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            <Etiqueta cor={COR_DO_ESTADO[e.chave]} icone={ICONE_DO_ESTADO[e.chave]}>{e.rotulo}</Etiqueta>
            {e.a_vencer && <Etiqueta cor="aviso" icone="fa-clock">{e.dias === 0 ? t('Termina hoje') : t('Faltam :n dias', { n: e.dias ?? 0 })}</Etiqueta>}
            {e.por_pagar && <Etiqueta cor="aviso" icone="fa-hand-holding-dollar">{t('Por pagar')}</Etiqueta>}
        </span>
    );
}

const COR_DA_COMISSAO: Record<Comissao['estado'], 'aviso' | 'bom' | 'neutra'> = { por_pagar: 'aviso', paga: 'bom', anulada: 'neutra' };

export function EstadoDaComissao({ c }: { c: Comissao }) {
    return (
        <Etiqueta cor={COR_DA_COMISSAO[c.estado]} icone={c.estado === 'paga' ? 'fa-check' : c.estado === 'anulada' ? 'fa-ban' : 'fa-hourglass-half'}>
            {c.estado_rotulo}
        </Etiqueta>
    );
}

/** Copiar para a área de transferência, com o «Copiado» no botão durante um instante. */
export function Copiar({ texto, rotulo, className }: { texto: string; rotulo?: string; className?: string }) {
    const [feito, porFeito] = useState(false);

    return (
        <button
            type="button"
            onClick={() => {
                void navigator.clipboard?.writeText(texto).then(() => {
                    porFeito(true);
                    window.setTimeout(() => porFeito(false), 1800);
                });
            }}
            className={cls('inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold', RAIO, TRANSICAO, FOCO,
                feito ? 'bg-emerald-600 text-white' : 'bg-white/90 text-violet-700 hover:-translate-y-0.5 hover:bg-white hover:shadow', className)}
        >
            <i className={cls('fas', feito ? 'fa-check' : 'fa-copy')} aria-hidden="true" />
            {feito ? t('Copiado') : rotulo ?? t('Copiar')}
        </button>
    );
}

/**
 * O LINK DO REVENDEDOR — o cartão que o revendedor mostra e partilha: o link, o
 * código, o QR, e os atalhos para o WhatsApp.
 */
export function LinkDeAfiliado({ link, codigo, regra, compacto = false }: { link: string | null; codigo: string | null; regra?: string; compacto?: boolean }) {
    const [verQr, porVerQr] = useState(false);

    if (!link || !codigo) return null;

    const mensagem = t('Conheça o SOSERP, o sistema de gestão feito para Angola: facturação certificada pela AGT, stock, POS e muito mais. Registe a sua empresa por aqui: :link', { link });

    return (
        <section className={cls('entra relative overflow-hidden bg-gradient-to-br p-6 text-white shadow-xl', GRADIENTE_DO_PORTAL, RAIO_GRANDE)}>
            <span aria-hidden="true" className="pointer-events-none absolute -right-12 -top-16 h-48 w-48 rounded-full bg-white/10 blur-2xl" />
            <span aria-hidden="true" className="pointer-events-none absolute -bottom-20 left-10 h-40 w-40 rounded-full bg-emerald-300/20 blur-2xl" />
            <div className="relative flex flex-wrap items-start gap-6">
                <div className="min-w-0 flex-1 basis-72">
                    <p className="text-xs font-bold uppercase tracking-widest text-white/70"><i className="fas fa-link mr-1.5" aria-hidden="true" />{t('O seu link de revendedor')}</p>
                    <p className="mt-2 break-all rounded-xl bg-black/20 px-3 py-2 font-mono text-sm sm:text-base">{link}</p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <Copiar texto={link} rotulo={t('Copiar link')} />
                        <a href={`https://wa.me/?text=${encodeURIComponent(mensagem)}`} target="_blank" rel="noreferrer"
                            className={cls('inline-flex items-center gap-2 bg-[#25D366] px-3 py-2 text-sm font-semibold text-white hover:-translate-y-0.5 hover:shadow', RAIO, TRANSICAO, FOCO)}>
                            <i className="fab fa-whatsapp" aria-hidden="true" />{t('Partilhar no WhatsApp')}
                        </a>
                        {!compacto && (
                            <button type="button" onClick={() => porVerQr(!verQr)} aria-expanded={verQr}
                                className={cls('inline-flex items-center gap-2 bg-white/15 px-3 py-2 text-sm font-semibold text-white hover:bg-white/25', RAIO, TRANSICAO, FOCO)}>
                                <i className="fas fa-qrcode" aria-hidden="true" />{verQr ? t('Esconder QR') : t('Mostrar QR')}
                            </button>
                        )}
                    </div>
                    {regra && <p className="mt-4 text-sm text-white/85"><i className="fas fa-percent mr-1.5" aria-hidden="true" />{t('A sua comissão: :regra', { regra })}</p>}
                </div>
                <div className="shrink-0 text-center">
                    <p className="text-xs font-bold uppercase tracking-widest text-white/70">{t('Código')}</p>
                    <p className="mt-1 rounded-xl bg-white px-4 py-2 font-mono text-2xl font-black tracking-widest text-violet-700 shadow-lg">{codigo}</p>
                    <div className="mt-2"><Copiar texto={codigo} rotulo={t('Copiar código')} /></div>
                </div>
            </div>
            {verQr && !compacto && (
                <div className="animate-fade-in relative mt-5 flex flex-wrap items-center gap-4 rounded-xl bg-white p-4 text-gray-700">
                    <img src="/revendedor/api/qr" alt={t('QR do link de revendedor')} className="h-40 w-40" />
                    <p className="max-w-sm text-sm">{t('Mostre este QR ao cliente: ao lê-lo com a câmara do telemóvel, abre o site já ligado a si. Pode também imprimi-lo com o botão direito do rato.')}</p>
                </div>
            )}
        </section>
    );
}

/** Uma linha de dados — rótulo em cima, valor em baixo. */
export function Dado({ rotulo, children, icone }: { rotulo: string; children: ReactNode; icone?: string }) {
    return (
        <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">{icone && <i className={cls('fas mr-1.5', icone)} aria-hidden="true" />}{rotulo}</p>
            <div className="mt-0.5 break-words text-sm font-medium text-gray-900">{children || '—'}</div>
        </div>
    );
}

export const dataOuTraco = (d: string | null | undefined) => (d ? data(d) : '—');
