import { useRef, useState } from 'react';

import { t } from '@/i18n';

import { avisar } from '../../ui/Dialogos';
import { Folha } from '../../ui/Folha';

/**
 * A FOLHA DO DIAGNÓSTICO — o relatório dos dois lados (servidor e aparelho).
 *
 * É para ser mandado a quem dá suporte: o texto tem de se poder seleccionar e
 * copiar inteiro. No Android instalado a janela nova não abre e o `alert()`
 * cortava o texto; aqui fica numa folha, com Copiar e, onde o telemóvel sabe,
 * Partilhar (vai direito ao WhatsApp).
 */
export function Diagnostico({ relatorio, aoFechar }: {
    relatorio: { texto: string; copiado: boolean } | null;
    aoFechar: () => void;
}) {
    const texto = useRef<HTMLPreElement>(null);
    const [copiado, setCopiado] = useState(false);

    const fechar = () => {
        setCopiado(false);
        aoFechar();
    };

    const copiar = async () => {
        if (!relatorio) return;
        let ok = false;

        try {
            await navigator.clipboard.writeText(relatorio.texto);
            ok = true;
        } catch {
            // Sem a API (página sem HTTPS, ou permissão negada): selecciona-se o
            // texto e copia-se à moda antiga — e fica seleccionado, para o
            // polegar poder carregar em «Copiar» do próprio telemóvel.
            const el = texto.current;
            const seleccao = window.getSelection();
            if (el && seleccao) {
                const intervalo = document.createRange();
                intervalo.selectNodeContents(el);
                seleccao.removeAllRanges();
                seleccao.addRange(intervalo);
                try { ok = document.execCommand('copy'); } catch { ok = false; }
            }
        }

        setCopiado(ok);
        avisar(ok ? t('Copiado para a área de transferência.') : t('Não foi possível copiar — seleccione o texto à mão.'), ok ? 'ok' : 'aviso', { duracao: 2500 });
    };

    const podePartilhar = typeof navigator !== 'undefined' && typeof navigator.share === 'function';

    const partilhar = async () => {
        if (!relatorio) return;
        try {
            await navigator.share({ title: t('Diagnóstico do PWA'), text: relatorio.texto });
        } catch {
            // Cancelado por quem tocou: não há nada a dizer.
        }
    };

    const jaCopiado = copiado || !!relatorio?.copiado;

    return (
        <Folha aberta={!!relatorio} aoFechar={fechar} titulo={t('Diagnóstico')} icone="fa-stethoscope"
               cor="from-slate-700 to-slate-900" largura="sm:max-w-2xl" zIndex="z-[80]"
               subtitulo={jaCopiado ? t('Copiado para a área de transferência.') : t('Seleccione o texto ou toque em Copiar.')}
               data-ensaio="inicio-diagnostico"
               rodape={(
                   <div className="flex gap-2">
                       <button type="button" onClick={fechar}
                               className="pwa-toque flex-1 py-3 border-2 border-gray-300 bg-white text-gray-700 rounded-xl font-bold text-sm">
                           {t('Fechar')}
                       </button>
                       {podePartilhar && (
                           <button type="button" onClick={() => void partilhar()}
                                   className="pwa-toque flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm shadow-lg">
                               <i className="fas fa-share-nodes mr-1" aria-hidden="true" />{t('Partilhar')}
                           </button>
                       )}
                       <button type="button" onClick={() => void copiar()}
                               className="pwa-toque flex-[2] py-3 bg-gradient-to-r from-slate-700 to-slate-900 text-white rounded-xl font-bold text-sm shadow-lg">
                           <i className={`fas ${copiado ? 'fa-check' : 'fa-copy'} mr-1`} aria-hidden="true" />
                           {copiado ? t('Copiado') : t('Copiar')}
                       </button>
                   </div>
               )}>
            <pre ref={texto} tabIndex={0} aria-label={t('Relatório do diagnóstico')}
                 className="select-text cursor-text bg-slate-950 text-emerald-200 rounded-xl p-3 text-[11px] leading-relaxed font-mono whitespace-pre-wrap break-all focus:outline-none focus:ring-2 focus:ring-slate-400">
                {relatorio?.texto}
            </pre>
        </Folha>
    );
}
