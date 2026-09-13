import { t } from '@/i18n';

import { PosOfflineTicket } from '../papel';
import { contasDoDocumento, type ExtrasDoPapel } from '../papel/molde';
import { db, lerMeta, type Registo } from './base';
import { enqueue } from './fila';
import { checkRealOnline } from './rede';
import { sync } from './sincronizar';
import { getCompany } from './vendas';
import { arredondar2, comLimite, dataDeHoje, idLocal, numero, soDados } from './util';

/**
 * OS DOCUMENTOS feitos no aparelho — factura, factura-recibo, proforma — e o
 * seu papel: impressão e PDF, com rede e sem ela.
 */

/**
 * A percentagem de retenção, como o servidor a lê (DraftController): a indicada
 * manda — INCLUINDO 0% —, e só sem nenhuma vale 6,5%. Era `parseFloat(x) || 6.5`,
 * e um serviço isento de retenção saía do aparelho com 6,5% retidos.
 */
export function percentagemDeRetencao(valor: unknown): number {
    if (valor === null || valor === undefined || valor === '') return 6.5;
    const n = parseFloat(String(valor));

    return Number.isFinite(n) ? n : 6.5;
}

/**
 * Cria o documento no aparelho e põe-no na fila.
 *
 * Os TOTAIS guardados são os mesmos do ecrã e do papel (`contasDoDocumento`):
 * com os descontos do documento e a retenção. O motor antigo guardava um total
 * sem eles, e a lista mostrava um valor que não era o do documento emitido.
 */
export async function createDraftOffline(entrada: Registo): Promise<Registo> {
    const draft = soDados(entrada);
    const local_uuid = idLocal('d');
    const contas = contasDoDocumento(draft);

    const registo: Registo = {
        local_uuid,
        doc_type: draft.doc_type,
        client_id: draft.client_id || null,
        client_local_uuid: draft.client_local_uuid || null,
        client_name: draft.client_name || '',
        items: draft.items || [],
        notes: draft.notes || '',
        reference: draft.reference || '',
        invoice_date: draft.invoice_date || dataDeHoje(),
        due_date: draft.due_date || null,
        subtotal: arredondar2(contas.subtotal),
        tax: arredondar2(contas.iva),
        total: arredondar2(contas.total),
        // O que o PAPEL precisa: sem isto o papel sem rede saía sem descontos,
        // retenção, entrega e pagamento.
        discount_commercial: numero(draft.discount_commercial),
        discount_financial: numero(draft.discount_financial),
        is_service: !!draft.is_service,
        withholding_percentage: draft.is_service ? percentagemDeRetencao(draft.withholding_percentage) : null,
        delivery_date: draft.delivery_date || null,
        delivery_location: draft.delivery_location || null,
        payment_method: draft.payment_method || null,
        amount_received: draft.amount_received != null ? parseFloat(draft.amount_received) : null,
        _synced: 0,
        _server_id: null,
        _server_number: null,
        created_at: new Date().toISOString(),
    };

    await db.draft_documents.put(registo);

    await enqueue('create_draft', {
        local_uuid,
        doc_type: draft.doc_type,
        client_id: draft.client_id || null,
        client_local_uuid: draft.client_local_uuid || null,
        notes: draft.notes || '',
        reference: draft.reference || '',
        invoice_date: registo.invoice_date,
        due_date: registo.due_date,
        // Os descontos do documento e a entrega: sem isto o operador via um
        // total com desconto e saía uma factura sem ele.
        discount_commercial: numero(draft.discount_commercial),
        discount_financial: numero(draft.discount_financial),
        delivery_date: draft.delivery_date || null,
        delivery_location: draft.delivery_location || null,
        is_service: !!draft.is_service,
        withholding_percentage: draft.is_service ? percentagemDeRetencao(draft.withholding_percentage) : null,
        items: draft.items,
    });

    return registo;
}

/**
 * Os documentos, cada um com o ESTADO DA FILA ao lado. A lista dizia
 * «Pendente» para sempre, mesmo quando o trabalho já tinha falhado cinco vezes
 * — quem olhava via um documento à espera; havia um documento morto.
 */
export async function getDrafts(): Promise<Registo[]> {
    const docs = await db.draft_documents.orderBy('created_at').reverse().toArray();

    let fila: Registo[] = [];
    try { fila = await db.sync_queue.filter((j) => j.op === 'create_draft').toArray(); } catch { /* ignora */ }
    const porUuid = new Map(fila.map((j) => [j.payload?.local_uuid, j]));

    return docs.map((d) => {
        const j = porUuid.get(d.local_uuid);

        return {
            ...d,
            _estado_fila: d._synced ? 'done' : (j?.status || 'pending'),
            _erro: !d._synced && j?.last_error ? j.last_error : null,
        };
    });
}

/** O que o papel precisa e o registo não tem: molde, NIF do cliente, códigos, operador, isenção. */
export async function dadosParaOPapel(doc: Registo): Promise<ExtrasDoPapel> {
    const tipoDoc = String(doc.doc_type || '').toUpperCase();
    const tipo = tipoDoc === 'PROFORMA' ? 'proforma' : (tipoDoc === 'FR' ? 'FR' : 'FT');
    const molde = (await lerMeta<Registo>('molde_' + tipo))?.html || null;

    let clienteNif: string | null = null;
    try {
        const cli = doc.client_id
            ? await db.clients.get(doc.client_id)
            : (doc.client_local_uuid ? await db.clients.where('local_uuid').equals(doc.client_local_uuid).first() : null);
        clienteNif = cli?.nif || null;
    } catch { /* ignora */ }

    const codigos: Record<string, string> = {};
    for (const it of doc.items || []) {
        if (!Number.isInteger(it.product_id)) continue;
        try {
            const p = await db.products.get(it.product_id);
            if (p?.code) codigos[it.product_id] = p.code;
        } catch { /* ignora */ }
    }

    const operador = (await lerMeta<Registo>('user'))?.name || null;

    let isencao: ExtrasDoPapel['isencao'] = null;
    try {
        const taxas = await db.tax_rates.toArray();
        const tx = taxas.find((x) => x.is_default && (parseFloat(x.rate) || 0) <= 0)
            || taxas.find((x) => (parseFloat(x.rate) || 0) <= 0 && x.exemption_code);
        if (tx) isencao = { codigo: tx.exemption_code || null, motivo: tx.exemption_reason || null };
    } catch { /* ignora */ }

    return { molde, clienteNif, codigos, operador, isencao };
}

const idDaEmpresa = async () => window.SOS_TENANT_ID || (await lerMeta<number>('tenant_id'));

/**
 * A pré-visualização DEFINITIVA do servidor, guardada para reimprimir sem rede.
 * Só se guarda se for autónoma (imagens em data:, sem folhas externas).
 */
export async function previewDefinitivo(tipo: string, doc: Registo): Promise<string> {
    const chave = `preview_emitido_${await idDaEmpresa()}_${tipo}_${doc.doc_type || 'FR'}_${doc._server_id}`;
    const guardada = await lerMeta<string>(chave);
    if (guardada) return guardada;

    if (!navigator.onLine || !(await checkRealOnline())) {
        throw new Error(t('A pré-visualização definitiva ainda não está guardada. Abra este documento com internet primeiro.'));
    }

    const caminho = tipo !== 'venda' && doc.doc_type === 'proforma' ? '/invoicing/sales/proformas/' : '/invoicing/sales/invoices/';
    const resposta = await fetch(caminho + doc._server_id + '/preview', { credentials: 'same-origin', signal: AbortSignal.timeout(15000) });

    if (!resposta.ok || resposta.redirected) throw new Error(t('Não foi possível guardar a pré-visualização definitiva.'));

    const html = await resposta.text();
    const lido = new DOMParser().parseFromString(html, 'text/html');

    if ([...lido.images].some((img) => !img.getAttribute('src')?.startsWith('data:')) || lido.querySelector('link[rel="stylesheet"]')) {
        throw new Error(t('A pré-visualização contém recursos externos e não pode ser guardada offline.'));
    }

    await db.meta.put({ key: chave, value: html });

    return html;
}

/**
 * O PDF (Blob) de uma venda ou de um documento, com o nome do ficheiro.
 *
 * UM DESENHO SÓ: emitido, os bytes do servidor (guardados na primeira vez, para
 * não reconstruir um documento emitido a partir de um catálogo velho); por
 * emitir, feito no aparelho a partir do mesmo HTML da impressora.
 */
export async function pdfDe(tipo: 'venda' | 'documento', registo: Registo): Promise<{ blob: Blob; nome: string; origem: string }> {
    const nome = (registo._server_number
        ? String(registo._server_number)
        : 'PROVISORIO-' + String(registo.local_uuid || '').slice(-6).toUpperCase()
    ).replace(/[^A-Za-z0-9._-]+/g, '-') + '.pdf';

    const chaveCanonica = `pdf_emitido_${await idDaEmpresa()}_${tipo}_${registo.doc_type || 'FR'}_${registo._server_id}`;
    const canonico = registo._synced && registo._server_id ? await lerMeta<Registo>(chaveCanonica) : null;

    if (canonico?.blob) return { blob: canonico.blob, nome: canonico.nome || nome, origem: 'copia-servidor' };

    // Emitido e com rede: o PDF do servidor, que é o verdadeiro.
    if (registo._synced && registo._server_id && navigator.onLine && (await checkRealOnline())) {
        const caminho = tipo === 'venda' || String(registo.doc_type || '').toLowerCase() !== 'proforma'
            ? '/invoicing/sales/invoices/' + registo._server_id + '/pdf'
            : '/invoicing/sales/proformas/' + registo._server_id + '/pdf';

        try {
            const r = await fetch(caminho, { credentials: 'same-origin', signal: AbortSignal.timeout(15000) });

            if (r.ok && (r.headers.get('content-type') || '').includes('pdf')) {
                const blob = await r.blob();
                if ((await blob.slice(0, 5).text()) !== '%PDF-') throw new Error('PDF inválido');
                await db.meta.put({ key: chaveCanonica, value: { blob, nome } });
                try { await previewDefinitivo(tipo, registo); } catch { /* o PDF já está guardado */ }

                return { blob, nome, origem: 'servidor' };
            }
        } catch {
            // Sem servidor à mão: faz-se cá.
        }
    }

    if (registo._synced && registo._server_id) {
        throw new Error(t('O PDF definitivo ainda não está guardado neste aparelho. Ligue à internet e abra o documento uma vez para o guardar.'));
    }

    const empresa = (await getCompany()) || {};
    const blob = tipo === 'venda'
        ? await PosOfflineTicket.pdfDoTalao(registo, empresa)
        : await PosOfflineTicket.pdfDoDocumento(registo, empresa, await dadosParaOPapel(registo));

    return { blob, nome, origem: 'aparelho' };
}

/**
 * O PDF de uma venda ou de um documento, para partilhar — o WhatsApp, em regra,
 * pela folha de partilha do sistema. Onde não há, descarrega-se e anexa-se.
 */
export async function partilharPdf(tipo: 'venda' | 'documento', localUuid: string) {
    const registo = tipo === 'venda' ? await db.pos_sales.get(localUuid) : await db.draft_documents.get(localUuid);
    if (!registo) throw new Error(t('Documento não encontrado neste aparelho.'));

    const { blob, nome, origem } = await pdfDe(tipo, registo);
    const ficheiro = new File([blob], nome, { type: 'application/pdf' });

    if (navigator.canShare?.({ files: [ficheiro] })) {
        await navigator.share({
            files: [ficheiro],
            title: nome.replace(/\.pdf$/i, ''),
            text: registo._server_number
                ? t('Documento :numero', { numero: registo._server_number })
                : t('Documento provisório — a numeração fiscal é atribuída na sincronização.'),
        });

        return { modo: 'partilhado' as const, nome, bytes: blob.size, origem };
    }

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { a.remove(); URL.revokeObjectURL(url); }, 4000);

    return { modo: 'descarregado' as const, nome, bytes: blob.size, origem };
}

/** O HTML do papel de um documento, tal como vai para a impressora — para ensaios. */
export async function htmlDoPapel(localUuid: string): Promise<string | null> {
    const doc = await db.draft_documents.get(localUuid);
    if (!doc) throw new Error(t('Documento não encontrado neste aparelho.'));

    return PosOfflineTicket.htmlDoDocumento(doc, (await getCompany()) || {}, await dadosParaOPapel(doc));
}

/**
 * Imprime um documento feito no aparelho.
 *
 *   · sincronizado e com rede → a PRÉ-VISUALIZAÇÃO do site (o mesmo papel do
 *     ecrã grande, com QR AGT e contas bancárias);
 *   · por sincronizar e COM rede → espera até 8 s pelo número fiscal;
 *   · SEM rede → o papel de recurso, marcado como PROVISÓRIO.
 */
export async function imprimirDocumento(localUuid: string): Promise<Registo> {
    let doc = await db.draft_documents.get(localUuid);
    if (!doc) throw new Error(t('Documento não encontrado neste aparelho.'));

    const comRede = navigator.onLine && (await checkRealOnline());

    if (!doc._synced && comRede) {
        await comLimite(sync(false), 8000);
        doc = (await db.draft_documents.get(localUuid)) || doc;
    }

    if (doc._synced && doc._server_id) {
        const html = await previewDefinitivo('documento', doc);
        const janela = window.open('', '_blank');
        if (!janela) throw new Error(t('O navegador bloqueou a janela. Permita pop-ups para este site.'));
        janela.document.open();
        janela.document.write(html);
        janela.document.close();

        return doc;
    }

    PosOfflineTicket.printDocument(doc, (await getCompany()) || {}, await dadosParaOPapel(doc));

    return doc;
}

declare global {
    interface Window {
        SOS_TENANT_ID?: number | null;
        SOS_USER_ID?: number | null;
        SOS_USER_NAME?: string | null;
    }
}
