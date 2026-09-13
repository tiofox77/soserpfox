import { PosOfflineTicket } from '../papel';
import * as acesso from './acesso';
import { arrancarMotor } from './arranque';
import { db } from './base';
import { exportarCopia } from './copia';
import * as documentos from './documentos';
import { state } from './estado';
import * as fila from './fila';
import { isStandalone, promptInstall } from './instalar';
import { checkRealOnline, estadoDaLigacao } from './rede';
import { restaurante } from './restaurante';
import { emitirJa, sync } from './sincronizar';
import * as turno from './turno';
import * as vendas from './vendas';

/**
 * O MOTOR DO PWA — a fachada `window.SosPwa`.
 *
 * Era o `public/js/pwa-invoicing.js`. A forma pública é a MESMA: os ensaios de
 * browser chamam-na, o service worker fala com ela, e quem depura um telemóvel
 * no balcão abre a consola e escreve `SosPwa.getQueue()`.
 */

fila.ligarFila({
    sincronizar: sync,
    depoisDeEmitirDocumento: (doc) => documentos.pdfDe('documento', doc),
});

export const SosPwa = {
    db,
    state,
    sync,
    enqueue: fila.enqueue,
    refreshPendingCount: () => fila.refreshPendingCount(),
    estadoDaLigacao,
    checkRealOnline,
    emitirJa,

    getProducts: vendas.getProducts,
    getClients: vendas.getClients,
    createClientOffline: vendas.createClientOffline,
    createPosSaleOffline: vendas.createPosSaleOffline,
    getPosSales: vendas.getPosSales,
    getCompany: vendas.getCompany,
    getWarehouse: vendas.getWarehouse,
    getLastSyncDate: vendas.getLastSyncDate,
    getMeta: vendas.getMeta,
    modulos: vendas.modulos,
    temModulo: vendas.temModulo,

    createDraftOffline: documentos.createDraftOffline,
    getDrafts: documentos.getDrafts,
    partilharPdf: documentos.partilharPdf,
    pdfDe: documentos.pdfDe,
    dadosParaOPapel: documentos.dadosParaOPapel,
    previewDefinitivo: documentos.previewDefinitivo,
    htmlDoPapel: documentos.htmlDoPapel,
    imprimirDocumento: documentos.imprimirDocumento,

    restaurante,

    getQueue: () => fila.getQueue(),
    getFailedJobs: fila.getFailedJobs,
    retryFailedJob: fila.retryFailedJob,
    retryAllFailed: fila.retryAllFailed,

    enableOfflineAuth: acesso.enableOfflineAuth,
    isOfflineAuthEnabled: acesso.isOfflineAuthEnabled,
    getOfflineAuthInfo: acesso.getOfflineAuthInfo,
    verifyOfflineAuth: acesso.verifyOfflineAuth,
    reporPinOffline: acesso.reporPinOffline,
    regrasDoPin: acesso.regrasDoPin,
    recusaDoPinNovo: acesso.recusaDoPinNovo,
    clearOfflineAuth: acesso.clearOfflineAuth,
    isPwaUnlocked: acesso.isPwaUnlocked,
    sair: acesso.sair,
    _bcryptEngine: acesso.bcryptEngine,
    _bcryptCompare: acesso.bcryptCompare,

    getShift: turno.getShift,
    openShiftOffline: turno.openShiftOffline,
    closeShiftOffline: turno.closeShiftOffline,

    promptInstall,
    isStandalone,
    exportarCopia,
};

export type MotorDoPwa = typeof SosPwa;

declare global {
    interface Window {
        SosPwa?: MotorDoPwa;
        PosOfflineTicket?: typeof PosOfflineTicket;
    }
}

/** Liga o motor à janela e arranca-o. Uma vez por página. */
export function instalarMotor(): MotorDoPwa {
    window.SosPwa = SosPwa;
    window.PosOfflineTicket = PosOfflineTicket;
    void arrancarMotor();

    return SosPwa;
}
