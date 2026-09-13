import { t } from '@/i18n';

import { anunciar, notificar, state } from './estado';

/**
 * INSTALAR A APLICAÇÃO — o convite do Chrome, ou as instruções do iPhone.
 */

interface ConviteDeInstalacao extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: string }>;
}

let convite: ConviteDeInstalacao | null = null;

export function isStandalone(): boolean {
    return window.matchMedia?.('(display-mode: standalone)')?.matches
        || (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
}

export function isIos(): boolean {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

function actualizar(): void {
    state.instalavel = !isStandalone() && (!!convite || isIos());
    notificar();
}

export async function promptInstall(): Promise<void> {
    if (isStandalone()) return;

    if (convite) {
        await convite.prompt();
        await convite.userChoice;
        convite = null;
        actualizar();

        return;
    }

    // Um aviso da aplicação (o `<Dialogos />` da casca ouve-o), e não um
    // `alert()`: num telemóvel instalado a caixa do navegador pára tudo.
    anunciar('pwa:aviso', {
        id: Date.now(),
        tipo: 'info',
        titulo: t('Instalar aplicação'),
        texto: isIos()
            ? t('Para instalar no iPhone/iPad:\n\n1. Toque no botão Partilhar (□↑)\n2. Escolha "Adicionar ao ecrã principal"')
            : t('Para instalar: abra o menu do navegador (⋮) e escolha "Instalar aplicação" / "Adicionar ao ecrã principal".'),
        duracao: 12000,
    });
}

export function ligarInstalacao(): void {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        convite = e as ConviteDeInstalacao;
        actualizar();
    });

    window.addEventListener('appinstalled', () => {
        convite = null;
        actualizar();
    });

    actualizar();
}

export function conviteDispensado(): boolean {
    try { return localStorage.getItem('pwa-install-dismissed') === '1'; } catch { return false; }
}

export function dispensarConvite(): void {
    try { localStorage.setItem('pwa-install-dismissed', '1'); } catch { /* ignora */ }
    notificar();
}
