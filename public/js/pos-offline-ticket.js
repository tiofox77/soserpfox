/**
 * SOS ERP — Impressão de Ticket POS Offline (80mm térmico)
 *
 * Renderiza e imprime o ticket de uma venda POS criada offline, mesmo sem rede.
 * Quando a venda ainda não foi sincronizada, imprime com número PROVISÓRIO e
 * aviso de "Aguarda sincronização AGT". Após sincronizar, pode reimprimir-se
 * com o número fiscal definitivo + QR Code AGT.
 *
 * API: window.PosOfflineTicket.print(saleRecord, company)
 */
(function () {
    'use strict';

    function money(v) {
        return new Intl.NumberFormat('pt-PT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(v) || 0);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function paymentLabel(code) {
        const map = {
            cash: 'DINHEIRO', transfer: 'TRANSFERÊNCIA', multicaixa: 'MULTICAIXA',
            tpa: 'TPA / CARTÃO', card: 'CARTÃO', mbway: 'MB WAY', mobile: 'MULTICAIXA EXPRESS',
        };
        return map[(code || '').toLowerCase()] || (code ? code.toUpperCase() : 'DINHEIRO');
    }

    /**
     * Constrói o HTML interno do ticket a partir do registo da venda.
     */
    function buildTicketHtml(sale, company) {
        company = company || {};
        const synced = sale._synced === 1;
        const number = synced ? (sale._server_number || sale.provisional_number) : sale.provisional_number;
        const date = new Date(sale.created_at || Date.now());
        const dateStr = date.toLocaleDateString('pt-AO') + ' ' +
            date.toLocaleTimeString('pt-AO', { hour: '2-digit', minute: '2-digit' });

        const change = (Number(sale.amount_received) || 0) - (Number(sale.total) || 0);

        let itemsHtml = '';
        for (const it of (sale.items || [])) {
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

        // QR Code (apenas se sincronizado e disponível)
        let qrHtml = '';
        if (synced && sale._server_qr) {
            qrHtml = `
                <div class="qr-wrap">
                    <img src="${sale._server_qr}" alt="QR Code AGT" class="qr"/>
                    ${sale._server_atcud ? `<p class="atcud">ATCUD: ${esc(sale._server_atcud)}</p>` : ''}
                </div>`;
        }

        const provisionalBanner = synced ? '' : `
            <div class="prov-banner">
                ★ DOCUMENTO PROVISÓRIO ★<br>
                Aguarda sincronização AGT
            </div>`;

        const logoHtml = company.logo
            ? `<img src="${company.logo}" alt="logo" class="logo"/>`
            : '';

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

            <h4>FACTURA RECIBO</h4>
            ${provisionalBanner}

            <div class="meta">
                <div class="line"><span class="b">${synced ? 'FATURA:' : 'PROVISÓRIO:'}</span><span>${esc(number)}</span></div>
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

    const PRINT_CSS = `
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

    function printHtml(innerHtml) {
        const win = window.open('', '_blank', 'width=380,height=700');
        if (!win) {
            alert('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.');
            return;
        }
        win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Ticket POS</title>
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
            <style>${PRINT_CSS}</style></head><body>${innerHtml}</body></html>`);
        win.document.close();

        const trigger = () => {
            try { win.focus(); win.print(); } catch (e) { console.error(e); }
            setTimeout(() => { try { win.close(); } catch (e) {} }, 800);
        };

        const imgs = win.document.images;
        if (!imgs || imgs.length === 0) { setTimeout(trigger, 300); return; }
        let loaded = 0;
        const done = () => { if (++loaded >= imgs.length) setTimeout(trigger, 150); };
        for (let i = 0; i < imgs.length; i++) {
            if (imgs[i].complete) done();
            else { imgs[i].addEventListener('load', done); imgs[i].addEventListener('error', done); }
        }
        setTimeout(() => { if (loaded < imgs.length) trigger(); }, 3000);
    }

    /**
     * Constrói HTML do relatório de fecho de turno (X/Z Report offline).
     * @param {Object} shiftData  - estado do turno (opening_balance, cash_sales, …)
     * @param {Array}  sales      - array de pos_sales do turno (já ou por sincronizar)
     * @param {Object} company    - dados da empresa
     */
    function buildShiftReportHtml(shiftData, sales, company) {
        company = company || {};
        shiftData = shiftData || {};
        sales = Array.isArray(sales) ? sales : [];

        const now = new Date();
        const dateStr = now.toLocaleDateString('pt-AO') + ' ' +
            now.toLocaleTimeString('pt-AO', { hour: '2-digit', minute: '2-digit' });

        // Agrupar vendas por método de pagamento
        const byMethod = {};
        let totalSales = 0, totalTax = 0;
        for (const s of sales) {
            const m = s.payment_method || 'cash';
            if (!byMethod[m]) byMethod[m] = { count: 0, total: 0 };
            byMethod[m].count++;
            byMethod[m].total += parseFloat(s.total) || 0;
            totalSales += parseFloat(s.total) || 0;
            totalTax += parseFloat(s.tax) || 0;
        }

        const pendingCount = sales.filter(s => !s._synced).length;
        const syncedCount = sales.filter(s => s._synced).length;

        let methodRows = '';
        for (const [code, data] of Object.entries(byMethod)) {
            methodRows += `
                <div class="line"><span>${paymentLabel(code)} (${data.count}x)</span><span class="b">${money(data.total)} Kz</span></div>`;
        }

        const openBal = parseFloat(shiftData.opening_balance) || 0;
        const cashFromServer = parseFloat(shiftData.cash_sales) || 0;
        const cashLocal = (byMethod['cash'] || {}).total || 0;
        const expectedCash = openBal + cashFromServer;

        const openedAt = shiftData.opened_at
            ? new Date(shiftData.opened_at).toLocaleString('pt-AO') : '—';

        const pendingWarning = pendingCount > 0 ? `
            <div class="prov-banner" style="margin-top:6px">
                ⚠ ${pendingCount} venda(s) ainda por sincronizar<br>
                <small>Totais podem diferir do fecho no servidor</small>
            </div>` : '';

        return `
            <div class="hdr">
                <div class="hdr-l">
                    <h3>${esc(company.name || 'Empresa')}</h3>
                    <p>NIF: ${esc(company.nif || 'N/A')}</p>
                </div>
            </div>

            <h4>RELATÓRIO DE FECHO DE TURNO</h4>
            <h4 style="font-size:10px;font-weight:400">(Documento Local / Offline)</h4>
            ${pendingWarning}

            <div class="meta">
                <div class="line"><span class="b">Turno:</span><span>${esc(shiftData.number || '—')}</span></div>
                <div class="line"><span class="b">Abertura:</span><span>${esc(openedAt)}</span></div>
                <div class="line"><span class="b">Fecho:</span><span>${esc(dateStr)}</span></div>
                <div class="line"><span class="b">Total vendas:</span><span>${sales.length}</span></div>
                <div class="line"><span class="b">Sincronizadas:</span><span>${syncedCount}</span></div>
                <div class="line"><span class="b">Por sincronizar:</span><span>${pendingCount}</span></div>
            </div>

            <p class="sep" style="text-align:center;margin:5px 0">─────────────────────</p>
            <p class="b" style="margin-bottom:3px">VENDAS POR MÉTODO DE PAGAMENTO</p>
            <div class="totals">${methodRows || '<div class="line"><span>Sem vendas</span><span>—</span></div>'}</div>

            <div class="totals" style="margin-top:6px">
                <div class="line"><span>Total IVA cobrado:</span><span>${money(totalTax)} Kz</span></div>
                <div class="line grand"><span>TOTAL VENDIDO:</span><span>${money(totalSales)} Kz</span></div>
            </div>

            <p class="sep" style="text-align:center;margin:6px 0">─────────────────────</p>
            <p class="b" style="margin-bottom:3px">RESUMO DE CAIXA (DINHEIRO)</p>
            <div class="pay">
                <div class="line"><span>Saldo inicial:</span><span>${money(openBal)} Kz</span></div>
                ${cashLocal > 0 ? `<div class="line"><span>Vendas dinheiro (local):</span><span>${money(cashLocal)} Kz</span></div>` : ''}
                ${cashFromServer > 0 ? `<div class="line"><span>Vendas sincronizadas:</span><span>${money(cashFromServer)} Kz</span></div>` : ''}
                <div class="line grand"><span>ESPERADO EM CAIXA:</span><span>${money(expectedCash)} Kz</span></div>
            </div>

            <div class="foot">
                <p class="sep">═══════════════════════</p>
                <p>Impresso offline — ${esc(dateStr)}</p>
                <p class="b">Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
            </div>
        `;
    }

    window.PosOfflineTicket = {
        buildTicketHtml,
        buildShiftReportHtml,
        print(sale, company) {
            printHtml(buildTicketHtml(sale, company));
        },
        printShiftReport(shiftData, sales, company) {
            printHtml(buildShiftReportHtml(shiftData, sales, company));
        },
    };
})();
