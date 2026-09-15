import { etiquetaIntl, t } from '@/i18n';

/** Peças pequenas partilhadas pelos ecrãs das cópias. */

export function tamanho(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 })} KB`;
    if (bytes < 1073741824) return `${(bytes / 1048576).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 })} MB`;

    return `${(bytes / 1073741824).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })} GB`;
}

export function dataHora(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);

    return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(etiquetaIntl(), { dateStyle: 'short', timeStyle: 'short' });
}

/** «há 3 horas», «daqui a 2 horas» — pelo Intl do browser, na língua de quem lê. */
export function relativo(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso).getTime();
    if (Number.isNaN(d)) return iso;
    const segundos = Math.round((d - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(etiquetaIntl(), { numeric: 'auto' });
    const passos: Array<[number, Intl.RelativeTimeFormatUnit]> = [[60, 'second'], [3600, 'minute'], [86400, 'hour'], [2592000, 'day'], [31536000, 'month']];

    let divisor = 1;
    for (const [tecto, unidade] of passos) {
        if (Math.abs(segundos) < tecto) {
            return rtf.format(Math.round(segundos / divisor), unidade);
        }
        divisor = tecto;
    }

    return rtf.format(Math.round(segundos / 31536000), 'year');
}

export const ORIGENS: Record<string, { rotulo: () => string; icone: string; cor: string }> = {
    automatica: { rotulo: () => t('Automática'), icone: 'fa-clock-rotate-left', cor: 'bg-indigo-50 text-indigo-700' },
    manual: { rotulo: () => t('Manual'), icone: 'fa-hand-pointer', cor: 'bg-sky-50 text-sky-700' },
    antes_de_restaurar: { rotulo: () => t('Antes de repor'), icone: 'fa-life-ring', cor: 'bg-amber-50 text-amber-700' },
    carregada: { rotulo: () => t('Carregada'), icone: 'fa-upload', cor: 'bg-slate-100 text-slate-700' },
};
