import { cls } from './tokens';
import { t } from '@/i18n';

/**
 * O que se vê enquanto o ecrã não chegou.
 *
 * Um esqueleto e não um roda-roda: mostra a FORMA do que vem a seguir, e a
 * página não salta quando o conteúdo aparece.
 */
export function Carregando({ linhas = 6 }: { linhas?: number }) {
    return (
        <div className="animate-pulse space-y-3" aria-busy="true" aria-live="polite">
            <span className="sr-only">{t('A carregar…')}</span>
            <div className="h-9 w-1/3 rounded-xl bg-slate-200" />
            {Array.from({ length: linhas }).map((_, i) => (
                <div
                    key={i}
                    className={cls('h-12 rounded-xl bg-slate-100', i % 2 === 1 && 'bg-slate-50')}
                />
            ))}
        </div>
    );
}
