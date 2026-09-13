import type { Registo } from '../motor/base';

/**
 * O TALÃO DE 80 mm — a Factura-Recibo do balcão, com rede ou sem ela.
 *
 * NÃO SE TRADUZ NADA AQUI DENTRO. É documento fiscal AGT e sai em português
 * nas três línguas (PLANO-MULTILINGUA.md, decisão 3): traduzir «Isento de IVA»
 * é fabricar um documento que a AGT não reconhece. Traduz-se o ecrã, não o
 * talão.
 */

export function money(v: unknown): string {
    return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v) || 0);
}

export function esc(s: unknown): string {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/** A forma de pagamento EM PORTUGUÊS — para o talão fiscal. */
export function paymentLabel(code: unknown): string {
    const mapa: Record<string, string> = {
        cash: 'DINHEIRO', transfer: 'TRANSFERÊNCIA', multicaixa: 'MULTICAIXA',
        tpa: 'TPA / CARTÃO', card: 'CARTÃO', mbway: 'MB WAY', mobile: 'MULTICAIXA EXPRESS',
    };
    const c = String(code || '');

    return mapa[c.toLowerCase()] || (c ? c.toUpperCase() : 'DINHEIRO');
}

export function buildTicketHtml(sale: Registo, company: Registo = {}): string {
    const synced = sale._synced === 1;
    const number = synced ? (sale._server_number || sale.provisional_number) : sale.provisional_number;
    const date = new Date(sale.created_at || Date.now());
    const dateStr = date.toLocaleDateString('pt-AO') + ' ' + date.toLocaleTimeString('pt-AO', { hour: '2-digit', minute: '2-digit' });
    const change = (Number(sale.amount_received) || 0) - (Number(sale.total) || 0);

    let itemsHtml = '';
    for (const it of sale.items || []) {
        const qty = Number(it.quantity) || 0;
        const price = Number(it.unit_price) || 0;
        const lineSub = qty * price;
        const taxRate = Number(it.tax_rate) || 0;
        itemsHtml += `
                <tr class="row-item">
                    <td>${esc(it.product_name)}</td>
                    <td class="tc">${qty.toLocaleString('pt-PT')}</td>
                    <td class="tr">${money(price)}</td>
                    <td class="tr">${money(lineSub)}</td>
                </tr>
                <tr class="row-tax"><td colspan="4">${taxRate > 0 ? ('IVA ' + taxRate + '%: ' + money(lineSub * taxRate / 100) + ' Kz') : 'Isento de IVA'}</td></tr>
            `;
    }

    // O QR só depois de emitida: sem número fiscal não há QR que se possa mostrar.
    const qrHtml = synced && sale._server_qr
        ? `
                <div class="qr-wrap">
                    <img src="${sale._server_qr}" alt="QR Code AGT" class="qr"/>
                    ${sale._server_atcud ? `<p class="atcud">ATCUD: ${esc(sale._server_atcud)}</p>` : ''}
                </div>`
        : '';

    const provisionalBanner = synced ? '' : `
            <div class="prov-banner">
                ★ DOCUMENTO PROVISÓRIO ★<br>
                Aguarda sincronização AGT
            </div>`;

    // O TÍTULO DIZ O QUE O DOCUMENTO É: a comanda pode sair como FACTURA (a
    // pagar depois), e um papel que se chama recibo sem ninguém ter recebido
    // nada é um documento errado.
    const docTitulo = String(sale.doc_type || 'FR').toUpperCase() === 'FT' ? 'FACTURA' : 'FACTURA RECIBO';

    // De onde veio a conta: a mesa, quando há uma.
    const origemHtml = sale.origem
        ? `<div class="line"><span class="b">MESA:</span><span>${esc(sale.origem)}${sale.origem_numero ? ' · ' + esc(sale.origem_numero) : ''}</span></div>`
        : '';

    const logoHtml = company.logo ? `<img src="${company.logo}" alt="logo" class="logo"/>` : '';

    return `
            <div class="hdr">
                <div class="hdr-l">
                    ${logoHtml}
                    <h3>${esc(company.name || 'Empresa')}</h3>
                    <p>NIF: ${esc(company.nif || 'N/A')}</p>
                    <p>${esc(company.address || '')}</p>
                    <p>Tel: ${esc(company.phone || '')}</p>
                </div>
                ${qrHtml}
            </div>

            <h4>${docTitulo}</h4>
            ${provisionalBanner}

            <div class="meta">
                <div class="line"><span class="b">${synced ? 'FATURA:' : 'PROVISÓRIO:'}</span><span>${esc(number)}</span></div>
                ${origemHtml}
                <div class="line"><span class="b">DATA:</span><span>${esc(dateStr)}</span></div>
                <div class="line"><span class="b">CLIENTE:</span><span>${esc(sale.client_name || 'Consumidor Final')}</span></div>
                <div class="line"><span class="b">NIF:</span><span>${esc(sale.client_nif || '999999999')}</span></div>
            </div>

            <table class="items">
                <thead>
                    <tr><th class="tl">ITEM</th><th class="tc">QTD</th><th class="tr">PREÇO</th><th class="tr">SUBT.</th></tr>
                </thead>
                <tbody>${itemsHtml}</tbody>
            </table>

            <div class="totals">
                <div class="line"><span>Subtotal:</span><span>${money(sale.subtotal)} Kz</span></div>
                ${Number(sale.discount_amount) > 0 ? `<div class="line"><span>Desconto:</span><span>-${money(sale.discount_amount)} Kz</span></div>` : ''}
                <div class="line"><span>Total IVA:</span><span>${money(sale.tax)} Kz</span></div>
                <div class="line grand"><span>TOTAL:</span><span>${money(sale.total)} Kz</span></div>
            </div>

            <div class="pay">
                <div class="line"><span>Forma Pagamento:</span><span class="b">${paymentLabel(sale.payment_method)}</span></div>
                <div class="line"><span>Valor Recebido:</span><span>${money(sale.amount_received)} Kz</span></div>
                ${change > 0 ? `<div class="line b"><span>Troco:</span><span>${money(change)} Kz</span></div>` : ''}
            </div>

            ${sale.notes ? `<div class="notes"><span class="b">OBS:</span> ${esc(sale.notes)}</div>` : ''}

            <div class="foot">
                <p class="sep">═══════════════════════</p>
                <p class="b">Processado por programa validado</p>
                ${company.agt_cert ? `<p class="b">Certificado AGT N.º ${esc(company.agt_cert)}</p>` : ''}
                <p>Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
                ${synced && sale._server_hash ? `<p class="hash">HASH: ${esc(sale._server_hash)}-${esc(sale.hash_control || '1')}</p>` : ''}
                <p class="b mt">Obrigado pela sua preferência!</p>
            </div>
        `;
}

export const PRINT_CSS = `
        @page { size: 80mm auto; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; color: #000 !important; }
        html, body { width: 80mm; }
        body { font-family: 'Ubuntu', sans-serif; font-size: 10px; line-height: 1.25; width: 80mm; padding: 2mm 1mm;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; word-wrap: break-word; overflow-wrap: anywhere; }
        img { max-width: 100%; height: auto; }
        .logo { height: 34px; width: auto; margin-bottom: 3px; display: block; }
        .hdr { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dashed #999; padding-bottom: 6px; margin-bottom: 6px; }
        .hdr-l { flex: 1; }
        .hdr h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .hdr p { font-size: 10px; }
        .qr-wrap { flex-shrink: 0; text-align: center; margin-left: 6px; }
        .qr { width: 70px; height: 70px; }
        .atcud { font-size: 8px; margin-top: 2px; }
        h4 { font-size: 12px; font-weight: 700; text-align: center; margin: 5px 0; }
        .prov-banner { border: 1px dashed #000; text-align: center; font-weight: 700; font-size: 10px; padding: 3px; margin-bottom: 5px; }
        .meta, .totals, .pay { margin-bottom: 6px; }
        .line { display: flex; justify-content: space-between; font-size: 10px; }
        .b { font-weight: 700; }
        table.items { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; margin-bottom: 5px; }
        table.items th, table.items td { padding: 2px 1px; font-size: 10px; word-wrap: break-word; overflow-wrap: anywhere; vertical-align: top; }
        table.items thead tr { border-bottom: 1px solid #333; }
        .row-tax td { font-size: 8px; color: #333 !important; padding-bottom: 3px; border-bottom: 1px dotted #ccc; }
        .tl { text-align: left; } .tc { text-align: center; } .tr { text-align: right; }
        .totals { border-top: 1px solid #333; padding-top: 4px; }
        .grand { font-size: 13px; font-weight: 700; border-top: 1px solid #000; margin-top: 3px; padding-top: 3px; }
        .pay { border-top: 1px dashed #999; padding-top: 4px; }
        .notes { font-size: 9px; border-top: 1px dashed #999; padding-top: 3px; margin-bottom: 5px; }
        .foot { text-align: center; font-size: 9px; margin-top: 6px; }
        .foot .sep { font-weight: 700; }
        .foot .mt { margin-top: 4px; }
        .hash { font-family: monospace; font-size: 8px; word-break: break-all; margin-top: 3px; }
    `;
