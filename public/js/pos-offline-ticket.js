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

    /**
     * Forma de pagamento EM PORTUGUÊS — para o talão fiscal.
     *
     * O talão é a Factura-Recibo: conteúdo de documento AGT, e a língua legal
     * desse documento é o português, seja qual for a língua do caixa. Ver
     * docs/PLANO-MULTILINGUA.md, decisão 3.
     */
    function paymentLabel(code) {
        const map = {
            cash: 'DINHEIRO', transfer: 'TRANSFERÊNCIA', multicaixa: 'MULTICAIXA',
            tpa: 'TPA / CARTÃO', card: 'CARTÃO', mbway: 'MB WAY', mobile: 'MULTICAIXA EXPRESS',
        };
        return map[(code || '').toLowerCase()] || (code ? code.toUpperCase() : 'DINHEIRO');
    }

    /**
     * A mesma forma de pagamento, na língua do operador — para o relatório de
     * turno, que é documento interno e não sai para o cliente nem para a AGT.
     *
     * MULTICAIXA, MB WAY e MULTICAIXA EXPRESS ficam de fora do __(): são marcas
     * angolanas, não palavras.
     */
    function paymentLabelTraduzido(code) {
        const map = {
            cash: __('DINHEIRO'),
            transfer: __('TRANSFERÊNCIA'),
            multicaixa: 'MULTICAIXA',
            tpa: __('TPA / CARTÃO'),
            card: __('CARTÃO'),
            mbway: 'MB WAY',
            mobile: 'MULTICAIXA EXPRESS',
        };
        return map[(code || '').toLowerCase()] || (code ? code.toUpperCase() : __('DINHEIRO'));
    }

    /**
     * Constrói o HTML interno do ticket a partir do registo da venda.
     *
     * NÃO TRADUZIR NADA AQUI DENTRO. Este talão é a Factura-Recibo — documento
     * fiscal AGT — e sai em português nas três línguas, como a lei angolana
     * manda. Traduzir "Isento de IVA" ou "Processado por programa validado" é
     * fabricar um documento que a AGT não reconhece. O que se traduz é o ecrã
     * que produz o talão, não o talão. (PLANO-MULTILINGUA.md, decisão 3.)
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

        // O TÍTULO DIZ O QUE O DOCUMENTO É.
        //
        // Estava aqui "FACTURA RECIBO" fixo, porque o balcão só emite isso. A
        // comanda de restaurante também pode sair como FACTURA (a pagar
        // depois), e um papel que se chama recibo a si próprio, sem ninguém ter
        // recebido nada, é um documento errado.
        const docTitulo = String(sale.doc_type || 'FR').toUpperCase() === 'FT'
            ? 'FACTURA'
            : 'FACTURA RECIBO';

        // De onde veio a conta: a mesa, quando há uma. É o que o cliente
        // confere primeiro quando lhe entregam o papel.
        const origemHtml = sale.origem
            ? `<div class="line"><span class="b">MESA:</span><span>${esc(sale.origem)}${sale.origem_numero ? ' · ' + esc(sale.origem_numero) : ''}</span></div>`
            : '';

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

    function printHtml(innerHtml, css, janela) {
        // O <title> é da janela do navegador, não do documento impresso — traduz-se.
        imprimirDocumentoHtml(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${__('Ticket POS')}</title>
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
            <style>${css || PRINT_CSS}</style></head><body>${innerHtml}</body></html>`, janela);
    }

    /** Um documento HTML inteiro (o molde do servidor, preenchido) — abre e imprime. */
    function imprimirDocumentoHtml(documentoHtml, janela) {
        const win = window.open('', '_blank', janela || 'width=380,height=700');
        if (!win) {
            alert(__('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.'));
            return;
        }
        win.document.write(documentoHtml);
        win.document.close();

        const trigger = () => {
            try { win.focus(); win.print(); } catch (e) { console.error(e); }
            setTimeout(() => { try { win.close(); } catch (e) {} }, 800);
        };

        // SÓ SE IMPRIME COM A PÁGINA ASSENTE. O molde do servidor passa dos
        // 700 KB (leva o logótipo embutido); chamar o print 150 ms depois de
        // escrever dava, no Android, «There was a problem printing the page».
        const quandoPronto = (fn) => {
            const espera = () => (win.document.readyState === 'complete' ? setTimeout(fn, 600) : setTimeout(espera, 100));
            espera();
        };

        const imgs = win.document.images;
        if (!imgs || imgs.length === 0) { quandoPronto(trigger); return; }
        let loaded = 0;
        const done = () => { if (++loaded >= imgs.length) quandoPronto(trigger); };
        for (let i = 0; i < imgs.length; i++) {
            if (imgs[i].complete) done();
            else { imgs[i].addEventListener('load', done); imgs[i].addEventListener('error', done); }
        }
        setTimeout(() => { if (loaded < imgs.length) trigger(); }, 3000);
    }

    /**
     * Constrói HTML do relatório de fecho de turno (X/Z Report offline).
     *
     * Ao contrário do talão, ESTE traduz-se: é um documento interno de caixa,
     * não sai para o cliente nem para a AGT, e quem o lê é o operador — na
     * língua dele.
     *
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
                <div class="line"><span>${paymentLabelTraduzido(code)} (${data.count}x)</span><span class="b">${money(data.total)} Kz</span></div>`;
        }

        const openBal = parseFloat(shiftData.opening_balance) || 0;
        const cashFromServer = parseFloat(shiftData.cash_sales) || 0;
        const cashLocal = (byMethod['cash'] || {}).total || 0;
        const expectedCash = openBal + cashFromServer;

        const openedAt = shiftData.opened_at
            ? new Date(shiftData.opened_at).toLocaleString('pt-AO') : '—';

        // "venda(s)" não se traduz — plural a sério.
        const pendingWarning = pendingCount > 0 ? `
            <div class="prov-banner" style="margin-top:6px">
                ⚠ ${__n(':n venda ainda por sincronizar|:n vendas ainda por sincronizar', pendingCount, { n: pendingCount })}<br>
                <small>${__('Totais podem diferir do fecho no servidor')}</small>
            </div>` : '';

        // Os dois pontos vão DENTRO das cadeias: em francês escreve-se
        // "Ouverture :", com espaço antes, e isso é decisão do tradutor.
        return `
            <div class="hdr">
                <div class="hdr-l">
                    <h3>${esc(company.name || __('Empresa'))}</h3>
                    <p>NIF: ${esc(company.nif || 'N/A')}</p>
                </div>
            </div>

            <h4>${__('RELATÓRIO DE FECHO DE TURNO')}</h4>
            <h4 style="font-size:10px;font-weight:400">${__('(Documento Local / Offline)')}</h4>
            ${pendingWarning}

            <div class="meta">
                <div class="line"><span class="b">${__('Turno:')}</span><span>${esc(shiftData.number || '—')}</span></div>
                <div class="line"><span class="b">${__('Abertura:')}</span><span>${esc(openedAt)}</span></div>
                <div class="line"><span class="b">${__('Fecho:')}</span><span>${esc(dateStr)}</span></div>
                <div class="line"><span class="b">${__('Total vendas:')}</span><span>${sales.length}</span></div>
                <div class="line"><span class="b">${__('Sincronizadas:')}</span><span>${syncedCount}</span></div>
                <div class="line"><span class="b">${__('Por sincronizar:')}</span><span>${pendingCount}</span></div>
            </div>

            <p class="sep" style="text-align:center;margin:5px 0">─────────────────────</p>
            <p class="b" style="margin-bottom:3px">${__('VENDAS POR MÉTODO DE PAGAMENTO')}</p>
            <div class="totals">${methodRows || `<div class="line"><span>${__('Sem vendas')}</span><span>—</span></div>`}</div>

            <div class="totals" style="margin-top:6px">
                <div class="line"><span>${__('Total IVA cobrado:')}</span><span>${money(totalTax)} Kz</span></div>
                <div class="line grand"><span>${__('TOTAL VENDIDO:')}</span><span>${money(totalSales)} Kz</span></div>
            </div>

            <p class="sep" style="text-align:center;margin:6px 0">─────────────────────</p>
            <p class="b" style="margin-bottom:3px">${__('RESUMO DE CAIXA (DINHEIRO)')}</p>
            <div class="pay">
                <div class="line"><span>${__('Saldo inicial:')}</span><span>${money(openBal)} Kz</span></div>
                ${cashLocal > 0 ? `<div class="line"><span>${__('Vendas dinheiro (local):')}</span><span>${money(cashLocal)} Kz</span></div>` : ''}
                ${cashFromServer > 0 ? `<div class="line"><span>${__('Vendas sincronizadas:')}</span><span>${money(cashFromServer)} Kz</span></div>` : ''}
                <div class="line grand"><span>${__('ESPERADO EM CAIXA:')}</span><span>${money(expectedCash)} Kz</span></div>
            </div>

            <div class="foot">
                <p class="sep">═══════════════════════</p>
                <p>${__('Impresso offline — :data', { data: esc(dateStr) })}</p>
                <p class="b">Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
            </div>
        `;
    }

    /**
     * O DOCUMENTO criado no modo offline — proforma, factura, nota de crédito.
     *
     * EM A4, NÃO EM TALÃO. A primeira versão disto imprimia tudo no formato
     * de 80mm do balcão, e saía uma tira minúscula ao canto de uma folha A4:
     * a proforma e a factura de venda são DOCUMENTOS — vão por email, ficam
     * em arquivo, assinam-se — e o talão é o formato da Factura-Recibo do
     * balcão. Dois papéis, dois formatos:
     *
     *   · proforma / FT / NC → página A4 (este construtor);
     *   · FR → o talão de sempre (o printDocument encaminha).
     *
     * As regras do papel são as mesmas do talão: sincronizado leva o número
     * verdadeiro; por sincronizar leva a faixa de PROVISÓRIO e uma referência
     * local que não finge ser numeração; a proforma leva o aviso permanente
     * de que não serve de factura.
     *
     * NÃO TRADUZIR NADA AQUI DENTRO: é o documento, não o ecrã — sai em
     * português nas três línguas (PLANO-MULTILINGUA.md, decisão 3).
     */
    function buildDocumentHtml(doc, company) {
        company = company || {};

        const synced = doc._synced === 1;
        const tipo = String(doc.doc_type || 'FT').toLowerCase();
        const eProforma = tipo === 'proforma';

        const titulo = {
            proforma: 'FACTURA PROFORMA',
            ft: 'FACTURA',
            nc: 'NOTA DE CRÉDITO',
            fr: 'FACTURA RECIBO',
        }[tipo] || 'FACTURA';

        const number = synced && doc._server_number
            ? doc._server_number
            : 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase();

        const criado = new Date(doc.created_at || Date.now());
        const dataDoc = doc.invoice_date || criado.toLocaleDateString('pt-AO');

        let linhas = '';
        let descontoTotal = 0;

        (doc.items || []).forEach((it, i) => {
            const qty = Number(it.quantity) || 0;
            const price = Number(it.unit_price) || 0;
            const discPct = Number(it.discount_percent) || 0;
            const bruto = qty * price;
            const liquido = bruto * (1 - discPct / 100);
            descontoTotal += bruto - liquido;
            const taxRate = Number(it.tax_rate) || 0;

            linhas += `
                <tr>
                    <td class="tc">${i + 1}</td>
                    <td>${esc(it.product_name)}</td>
                    <td class="tr">${qty.toLocaleString('pt-PT')}</td>
                    <td class="tr">${money(price)}</td>
                    <td class="tr">${discPct > 0 ? discPct + '%' : '—'}</td>
                    <td class="tr">${taxRate > 0 ? taxRate + '%' : 'Isento'}</td>
                    <td class="tr">${money(liquido)}</td>
                </tr>`;
        });

        // A faixa certa para o papel certo: a proforma nunca é provisória —
        // nunca leva numeração fiscal; o aviso dela é outro, e é permanente.
        const banner = eProforma
            ? `<div class="banner banner-info">ESTE DOCUMENTO NÃO SERVE DE FACTURA</div>`
            : (synced ? '' : `
            <div class="banner banner-prov">★ DOCUMENTO PROVISÓRIO ★ — A numeração fiscal é atribuída na sincronização</div>`);

        const logoHtml = company.logo ? `<img src="${company.logo}" alt="logo" class="logo"/>` : '';

        const rotuloVencimento = eProforma ? 'Válido até' : 'Vencimento';

        return `
            <div class="folha">
                <div class="topo">
                    <div class="empresa">
                        ${logoHtml}
                        <h1>${esc(company.name || 'Empresa')}</h1>
                        <p>NIF: ${esc(company.nif || 'N/A')}</p>
                        ${company.address ? `<p>${esc(company.address)}</p>` : ''}
                        ${company.phone ? `<p>Tel: ${esc(company.phone)}</p>` : ''}
                        ${company.email ? `<p>${esc(company.email)}</p>` : ''}
                    </div>
                    <div class="doc-caixa">
                        <h2>${titulo}</h2>
                        <table class="doc-meta">
                            <tr><td>${synced && doc._server_number ? 'Documento' : 'Ref. local'}</td><td>${esc(number)}</td></tr>
                            <tr><td>Data</td><td>${esc(dataDoc)}</td></tr>
                            ${doc.due_date ? `<tr><td>${rotuloVencimento}</td><td>${esc(doc.due_date)}</td></tr>` : ''}
                            ${doc.reference ? `<tr><td>Referência</td><td>${esc(doc.reference)}</td></tr>` : ''}
                        </table>
                    </div>
                </div>

                ${banner}

                <div class="cliente">
                    <p class="rotulo">Cliente</p>
                    <p class="nome">${esc(doc.client_name || 'Consumidor Final')}</p>
                    ${doc.client_nif ? `<p>NIF: ${esc(doc.client_nif)}</p>` : ''}
                </div>

                <table class="linhas">
                    <thead>
                        <tr>
                            <th class="tc" style="width:6%">#</th>
                            <th style="width:40%">Descrição</th>
                            <th class="tr" style="width:9%">Qtd.</th>
                            <th class="tr" style="width:15%">Pr. unit.</th>
                            <th class="tr" style="width:9%">Desc.</th>
                            <th class="tr" style="width:8%">IVA</th>
                            <th class="tr" style="width:15%">Total</th>
                        </tr>
                    </thead>
                    <tbody>${linhas}</tbody>
                </table>

                <div class="rodape-contas">
                    <div class="obs">${doc.notes ? `<p class="rotulo">Observações</p><p>${esc(doc.notes)}</p>` : ''}</div>
                    <table class="totais">
                        <tr><td>Subtotal</td><td class="tr">${money(doc.subtotal)} Kz</td></tr>
                        ${descontoTotal > 0.004 ? `<tr><td>Descontos</td><td class="tr">−${money(descontoTotal)} Kz</td></tr>` : ''}
                        <tr><td>Total IVA</td><td class="tr">${money(doc.tax)} Kz</td></tr>
                        <tr class="grande"><td>TOTAL</td><td class="tr">${money(doc.total)} Kz</td></tr>
                    </table>
                </div>

                <div class="pe">
                    ${eProforma
                        ? `<p class="b">Documento sem valor fiscal</p>`
                        : `<p class="b">Processado por programa validado</p>
                           ${company.agt_cert ? `<p class="b">Certificado AGT N.º ${esc(company.agt_cert)}</p>` : ''}`}
                    <p>Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
                    <p class="b">Obrigado pela sua preferência!</p>
                </div>
            </div>
        `;
    }

    const DOCUMENT_CSS = `
        @page { size: A4; margin: 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; color: #111 !important;
            -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: 'Ubuntu', sans-serif; font-size: 11px; line-height: 1.45; background: #fff; }
        .folha { max-width: 186mm; margin: 0 auto; padding: 4mm 0; }
        .logo { height: 52px; width: auto; margin-bottom: 6px; display: block; }
        .topo { display: flex; justify-content: space-between; gap: 10mm; align-items: flex-start;
                border-bottom: 2px solid #111; padding-bottom: 5mm; margin-bottom: 5mm; }
        .empresa h1 { font-size: 16px; text-transform: uppercase; margin-bottom: 2px; }
        .empresa p { font-size: 10.5px; }
        .doc-caixa { border: 1.5px solid #111; padding: 4mm 5mm; min-width: 62mm; }
        .doc-caixa h2 { font-size: 14px; text-align: center; margin-bottom: 3mm; letter-spacing: .04em; }
        .doc-meta { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .doc-meta td { padding: 1px 0; }
        .doc-meta td:first-child { font-weight: 700; padding-right: 5mm; white-space: nowrap; }
        .doc-meta td:last-child { text-align: right; }
        .banner { text-align: center; font-weight: 700; font-size: 11.5px;
                  padding: 2.5mm; margin-bottom: 5mm; letter-spacing: .03em; }
        .banner-prov { border: 2px dashed #111; }
        .banner-info { border: 1.5px solid #111; }
        .cliente { border: 1px solid #999; padding: 3mm 4mm; margin-bottom: 5mm; }
        .rotulo { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #555 !important; }
        .cliente .nome { font-size: 12.5px; font-weight: 700; }
        table.linhas { width: 100%; border-collapse: collapse; margin-bottom: 5mm; font-size: 10.5px; }
        table.linhas th { border-bottom: 1.5px solid #111; padding: 2mm 1.5mm; text-align: left;
                          font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; }
        table.linhas td { border-bottom: 1px solid #ddd; padding: 2mm 1.5mm; vertical-align: top; }
        .tc { text-align: center; } .tr { text-align: right; }
        .rodape-contas { display: flex; justify-content: space-between; gap: 10mm; align-items: flex-start; }
        .obs { flex: 1; font-size: 10px; }
        table.totais { border-collapse: collapse; min-width: 64mm; font-size: 11px; }
        table.totais td { padding: 1.5mm 2mm; }
        table.totais td:first-child { font-weight: 700; }
        table.totais tr.grande td { border-top: 2px solid #111; font-size: 14px; font-weight: 700; padding-top: 2.5mm; }
        .pe { margin-top: 12mm; border-top: 1px solid #999; padding-top: 3mm;
              text-align: center; font-size: 9.5px; }
        .pe .b { font-weight: 700; }
    `;

    /** Espera que as imagens de um documento carreguem — ou desiste ao fim do prazo. */
    function esperarImagens(doc, prazoMs) {
        const imgs = Array.from(doc.images || []);
        if (!imgs.length) return Promise.resolve();
        return new Promise((resolve) => {
            let feitas = 0;
            const uma = () => { if (++feitas >= imgs.length) resolve(); };
            imgs.forEach((img) => {
                if (img.complete) { uma(); return; }
                img.addEventListener('load', uma, { once: true });
                img.addEventListener('error', uma, { once: true });
            });
            setTimeout(resolve, prazoMs || 3000);
        });
    }

    /**
     * O PDF NO PRÓPRIO APARELHO, sem rede, a partir do MESMO HTML que vai
     * para a impressora.
     *
     * Não há um segundo desenho do talão nem do documento: o html2canvas
     * desenha o HTML de sempre num canvas, e o jsPDF embrulha a imagem num
     * PDF. É um PDF de imagem — não se selecciona o texto — e chega para o
     * que serve: ir pelo WhatsApp ao cliente. Emitido e com rede, o PDF a
     * sério é o do servidor (ver SosPwa.pdfDe), não este.
     *
     * Desenha-se num iframe escondido com a largura do papel (80mm para o
     * talão, A4 para o documento), porque o html2canvas precisa do HTML no
     * DOM para o medir.
     *
     * @returns {Promise<Blob>}
     */
    async function pdfHtml(innerHtml, css, opcoes) {
        const o = Object.assign({ larguraMm: 80, a4: false, escala: 2 }, opcoes || {});
        if (!window.html2canvas || !window.jspdf || !window.jspdf.jsPDF) {
            throw new Error(__('O gerador de PDF ainda não carregou. Tente outra vez.'));
        }

        const larguraPx = Math.round(o.larguraMm * 96 / 25.4);
        const iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:' + larguraPx + 'px;height:10px;border:0;opacity:0;pointer-events:none;';
        document.body.appendChild(iframe);

        try {
            const d = iframe.contentDocument;
            d.open();
            // `completo`: já é um documento HTML inteiro (o molde do servidor
            // preenchido) e entra tal como está.
            d.write(o.completo
                ? innerHtml
                : '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' + (css || PRINT_CSS)
                    + ' html,body{margin:0;padding:0;background:#fff;}</style></head><body>' + innerHtml + '</body></html>');
            d.close();

            /*
             * A BARRA DE DESLOCAMENTO ROUBAVA 15 PIXÉIS À FOLHA.
             *
             * A moldura nasce com 10 px de altura e o documento tem 1123: o
             * browser mete logo uma barra vertical, e a folha passa a medir
             * 779 px em vez dos 794 que são 210 mm. A altura continua a ser a
             * de uma página inteira, a proporção deixa de ser A4, e um
             * documento de duas linhas saía em DUAS páginas.
             *
             * Por isso: sem barra, e a moldura cresce ANTES de se medir.
             */
            const semBarra = d.createElement('style');
            semBarra.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{display:none}';
            (d.head || d.documentElement).appendChild(semBarra);
            iframe.style.height = '4000px';

            await esperarImagens(d, 3000);

            const corpo = d.body;

            // A altura é a do DOCUMENTO. Medir também o documentElement trazia
            // as margens à volta da folha e empurrava conteúdo para mais uma
            // página, que saía com uma tira do documento e o resto em branco.
            const altura = Math.max(corpo.scrollHeight, 1);
            iframe.style.height = altura + 'px';

            const canvas = await window.html2canvas(corpo, {
                scale: o.escala, useCORS: true, backgroundColor: '#ffffff', logging: false,
                width: larguraPx, height: altura, windowWidth: larguraPx, windowHeight: altura,
            });

            const { jsPDF } = window.jspdf;
            let pdf;

            if (o.a4) {
                // Fatia-se a imagem em páginas A4 — um documento com muitas
                // linhas não cabe numa só.
                const pagW = 210, pagH = 297;
                const fatiaPx = Math.floor(canvas.width * pagH / pagW);

                // A TOLERÂNCIA NÃO É UM ENFEITE. A altura da fatia arredonda
                // para baixo, por isso um documento que ocupa exactamente uma
                // folha fica um ou dois pixéis acima do corte e gera uma
                // segunda página em branco. Um milímetro de folga resolve, e
                // não chega para esconder conteúdo real.
                const tolerancia = Math.max(2, Math.ceil(canvas.width * 0.004));

                pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
                let primeira = true;
                for (let topo = 0; topo + tolerancia < canvas.height; topo += fatiaPx) {
                    const parte = document.createElement('canvas');
                    parte.width = canvas.width;
                    parte.height = Math.min(fatiaPx, canvas.height - topo);
                    parte.getContext('2d').drawImage(canvas, 0, topo, canvas.width, parte.height, 0, 0, canvas.width, parte.height);
                    if (!primeira) pdf.addPage();
                    pdf.addImage(parte.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pagW, parte.height * pagW / canvas.width);
                    primeira = false;
                }
            } else {
                // O talão: uma página só, com a altura do próprio talão.
                const alturaMm = Math.max(canvas.height * o.larguraMm / canvas.width, 40);
                pdf = new jsPDF({ unit: 'mm', format: [o.larguraMm, alturaMm], orientation: 'portrait' });
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, o.larguraMm, canvas.height * o.larguraMm / canvas.width);
            }

            return pdf.output('blob');
        } finally {
            iframe.remove();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // O MOLDE DO SERVIDOR, PREENCHIDO NO APARELHO
    //
    // O papel do documento sem rede era um segundo desenho, e não parecia o
    // da pré-visualização. Agora o servidor manda o SEU modelo — o mesmo do
    // PDF — com marcas no lugar dos valores (ver App\Services\Pwa\
    // MoldeDoDocumento), e é aqui que se preenche. O que está abaixo não
    // desenha nada: substitui marcas, repete linhas e tira o que não há.
    //
    // Os cálculos espelham os do servidor (DraftController::calculateTotals
    // e o partial tax-summary) tal como estão, porque o papel tem de mostrar
    // os mesmos números que o servidor mostra.
    // ═══════════════════════════════════════════════════════════════════

    function fmt2(v) {
        const n = Math.round((parseFloat(v) || 0) * 100) / 100;
        const [int, dec] = Math.abs(n).toFixed(2).split('.');
        return (n < 0 ? '-' : '') + int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
    }

    function fmt0(v) {
        const n = Math.round(parseFloat(v) || 0);
        return (n < 0 ? '-' : '') + String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /** A taxa como o resumo a escreve: 14 → "14", 6.5 → "6,5" (number_format sem zeros a mais). */
    function fmtTaxa(t) {
        const n = parseFloat(t) || 0;
        return String(Math.round(n * 100) / 100).replace('.', ',');
    }

    function fmtData(iso) {
        if (!iso) return '';
        const s = String(iso);
        const soData = /^\d{4}-\d{2}-\d{2}$/.test(s);
        const d = new Date(soData ? s + 'T00:00:00' : s);
        if (isNaN(d)) return s;
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    function fmtHora(iso) {
        const d = new Date(iso);
        return isNaN(d) ? '' : String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function escapar(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // O valor por extenso — o mesmo que o servidor escreve (NumberToWordsHelper).
    const EXT_UNIDADES = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
    const EXT_DEZ      = ['dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezasseis', 'dezassete', 'dezoito', 'dezanove'];
    const EXT_DEZENAS  = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    const EXT_CENTENAS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    function inteiroPorExtenso(n) {
        n = Math.floor(n);
        if (n === 0) return '';
        if (n < 10) return EXT_UNIDADES[n];
        if (n < 20) return EXT_DEZ[n - 10];
        if (n < 100) { const d = Math.floor(n / 10), u = n % 10; return EXT_DEZENAS[d] + (u > 0 ? ' e ' + EXT_UNIDADES[u] : ''); }
        if (n === 100) return 'cem';
        if (n < 1000) { const c = Math.floor(n / 100), r = n % 100; return EXT_CENTENAS[c] + (r > 0 ? ' e ' + inteiroPorExtenso(r) : ''); }
        const degraus = [[1000000000, 'mil milhões', 'mil milhões'], [1000000, 'um milhão', 'milhões'], [1000, 'mil', 'mil']];
        for (const [base, singular, plural] of degraus) {
            if (n >= base) {
                const q = Math.floor(n / base), r = n % base;
                let s = q === 1 ? singular : inteiroPorExtenso(q) + ' ' + plural;
                if (r > 0) s += (r < 100 ? ' e ' : ' ') + inteiroPorExtenso(r);
                return s;
            }
        }
        return '';
    }

    function valorPorExtenso(v) {
        v = parseFloat(v) || 0;
        const inteiro = Math.floor(v);
        const cent = Math.round((v - inteiro) * 100);
        let s = inteiro === 0 ? 'zero' : inteiroPorExtenso(inteiro);
        s += ' ' + (inteiro === 1 ? 'kwanza' : 'kwanzas');
        if (cent > 0) s += ' e ' + inteiroPorExtenso(cent) + ' ' + (cent === 1 ? 'cêntimo' : 'cêntimos');
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    /** As contas do documento, como o servidor as faz (sem IEC/Selo, que só o servidor apura). */
    function contasDoDocumento(doc) {
        const linhas = [];
        let subtotal = 0, imposto = 0;

        for (const it of (doc.items || [])) {
            const qtd = parseFloat(it.quantity) || 0;
            const preco = parseFloat(it.unit_price) || 0;
            const taxa = parseFloat(it.tax_rate) || 0;
            const descPct = parseFloat(it.discount_percent) || 0;
            const bruto = qtd * preco;
            const desconto = bruto * descPct / 100;
            const liquido = bruto - desconto;
            const iva = liquido * taxa / 100;
            subtotal += liquido;
            imposto += iva;
            linhas.push({ item: it, qtd, preco, taxa, descPct, bruto, desconto, liquido, iva, total: liquido + iva });
        }

        const comercial = Math.min(parseFloat(doc.discount_commercial) || 0, subtotal);
        const liquidoDoc = subtotal - comercial;
        const ivaDoc = subtotal > 0 ? imposto * (liquidoDoc / subtotal) : 0;
        const financeiro = parseFloat(doc.discount_financial) || 0;
        const pct = (doc.withholding_percentage != null && doc.withholding_percentage !== '') ? parseFloat(doc.withholding_percentage) : 6.5;
        const retencao = doc.is_service ? Math.round(liquidoDoc * pct) / 100 : 0;
        const total = Math.max(0, liquidoDoc + ivaDoc - financeiro - retencao);

        return { linhas, subtotal, comercial, financeiro, iva: ivaDoc, retencao, total, aPagar: total - retencao };
    }

    /**
     * Preenche o molde do servidor com um documento local. Devolve o
     * documento HTML inteiro, pronto para a impressora ou para o PDF.
     *
     * @param {string} moldeHtml  o molde, tal como veio do servidor
     * @param {Object} doc        o registo de draft_documents
     * @param {Object} extras     { clienteNif, codigos: {product_id: code}, operador, isencao: {codigo, motivo} }
     */
    function preencherMolde(moldeHtml, doc, extras) {
        extras = extras || {};
        const c = contasDoDocumento(doc);
        const sincronizado = !!(doc._synced && doc._server_number);
        const numero = sincronizado
            ? String(doc._server_number)
            : 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase();

        const d = new DOMParser().parseFromString(moldeHtml, 'text/html');
        const linhasTr = () => Array.from(d.querySelectorAll('tr'));

        // 1) As linhas dos artigos. A primeira é o molde; as auxiliares saem.
        linhasTr().filter((tr) => tr.textContent.includes('LINHA-AUXILIAR')).forEach((tr) => tr.remove());
        const linhaMolde = linhasTr().find((tr) => tr.innerHTML.includes('%%ITEM_NOME%%'));
        if (linhaMolde) {
            const molde = linhaMolde.outerHTML;
            const html = c.linhas.map((l) => {
                const codigo = (extras.codigos && extras.codigos[l.item.product_id]) || '-';
                let h = molde
                    .split('%%ITEM_CODIGO%%').join(escapar(codigo))
                    .split('%%ITEM_NOME%%').join(escapar(l.item.product_name || ''))
                    .split('%%ITEM_QTD%%').join(fmt0(l.qtd))
                    .split('%%ITEM_PRECO%%').join(fmt2(l.preco))
                    .split('%%ITEM_BRUTO%%').join(fmt2(l.bruto))
                    .split('%%ITEM_DESCONTO%%').join(fmt0(l.descPct) + '%')
                    .split('%%TAXA%%').join(fmt0(l.taxa) + '%')
                    .split('%%ITEM_IMPOSTO%%').join(fmt2(l.iva))
                    .split('%%ITEM_TOTAL%%').join(fmt2(l.total));
                if (l.item.description) {
                    h = h.split('%%ITEM_DESCRICAO%%').join(escapar(l.item.description));
                } else {
                    h = h.replace(/<span class="item-descricao">[^<]*%%ITEM_DESCRICAO%%[^<]*<\/span>/g, '').split('%%ITEM_DESCRICAO%%').join('');
                }
                return h;
            }).join('');
            linhaMolde.insertAdjacentHTML('beforebegin', html);
            linhaMolde.remove();
        }

        // 2) O resumo de impostos: uma linha por taxa; a isenta e o motivo só se houver.
        //    A base é subtotal − desconto da linha, tal como o servidor a soma.
        const grupos = new Map();
        for (const l of c.linhas) {
            const k = String(l.taxa);
            const g = grupos.get(k) || { taxa: l.taxa, base: 0, iva: 0 };
            g.base += l.liquido - l.desconto;
            g.iva += l.iva;
            grupos.set(k, g);
        }
        const taxadas = Array.from(grupos.values()).filter((g) => g.taxa > 0);
        const isentas = Array.from(grupos.values()).filter((g) => g.taxa <= 0);

        const linhaTaxada = linhasTr().find((tr) => tr.innerHTML.includes('%%IMPOSTO_BASE%%'));
        if (linhaTaxada) {
            const molde = linhaTaxada.outerHTML;
            const html = taxadas.map((g) => molde
                .split('%%TAXA%%').join(fmtTaxa(g.taxa) + '%')
                .split('%%IMPOSTO_BASE%%').join(fmt2(g.base))
                .split('%%IMPOSTO_VALOR%%').join(fmt2(g.iva))).join('');
            linhaTaxada.insertAdjacentHTML('beforebegin', html);
            linhaTaxada.remove();
        }
        const linhaIsenta = linhasTr().find((tr) => tr.innerHTML.includes('%%ISENTO_BASE%%'));
        if (linhaIsenta) {
            if (isentas.length) {
                linhaIsenta.innerHTML = linhaIsenta.innerHTML.split('%%ISENTO_BASE%%').join(fmt2(isentas.reduce((s, g) => s + g.base, 0)));
            } else {
                linhaIsenta.remove();
            }
        }
        const linhaMotivo = linhasTr().find((tr) => tr.innerHTML.includes('%%ISENCAO_CODIGO%%'));
        if (linhaMotivo) {
            const isencao = extras.isencao || {};
            if (isentas.length && (isencao.codigo || isencao.motivo)) {
                linhaMotivo.innerHTML = linhaMotivo.innerHTML
                    .replace(/\s*—\s*%%ISENCAO_MOTIVO%%/, isencao.motivo ? ' — ' + escapar(isencao.motivo) : '')
                    .split('%%ISENCAO_CODIGO%%').join(escapar(isencao.codigo || ''))
                    .split('%%ISENCAO_MOTIVO%%').join(escapar(isencao.motivo || ''));
            } else {
                linhaMotivo.remove();
            }
        }

        // 3) A entrega: só o que há, e o bloco inteiro sai quando não há nada.
        const blocoEntrega = Array.from(d.querySelectorAll('div')).find((el) => el.children.length <= 1 && el.textContent.includes('%%ENTREGA_DATA%%'));
        if (blocoEntrega) {
            const partes = [];
            if (doc.delivery_date) partes.push('Data: ' + escapar(fmtData(doc.delivery_date)));
            if (doc.delivery_location) partes.push('Local: ' + escapar(doc.delivery_location));
            if (partes.length) {
                const rotulo = blocoEntrega.querySelector('strong');
                blocoEntrega.innerHTML = (rotulo ? rotulo.outerHTML : '<strong>Entrega:</strong>') + ' ' + partes.join(' | ');
            } else {
                blocoEntrega.remove();
            }
        }

        // 4) Sem assinatura fiscal no aparelho: a barra lateral e a linha do hash saem.
        d.querySelectorAll('.agt-signature-sidebar').forEach((el) => el.remove());
        d.querySelectorAll('.system-info').forEach((el) => {
            el.innerHTML = el.innerHTML.replace(/\s*<br\s*\/?>\s*<strong>HASH e SAFT-AO:<\/strong>\s*"%%HASH4%%"/i, '');
        });

        // 5) A faixa de provisório, enquanto não há número fiscal.
        if (!sincronizado) {
            const topo = d.querySelector('.main-content') || d.body;
            topo.insertAdjacentHTML('afterbegin',
                '<div class="pwa-provisorio" style="border:2px dashed #b45309;color:#7c2d12;background:#fff7ed;'
                + 'font:bold 11px/1.4 Arial,sans-serif;text-align:center;padding:6px 8px;margin:0 0 8px 0;">'
                + '★ DOCUMENTO PROVISÓRIO ★ — A numeração fiscal é atribuída na sincronização · REF. LOCAL ' + escapar(numero) + '</div>');
        }

        // 6) O resto das marcas, no documento inteiro.
        const valores = {
            NUMERO: numero,
            CLIENTE_NOME: doc.client_name || 'Consumidor Final',
            CLIENTE_NIF: extras.clienteNif || '999999999',
            DATA: fmtData(doc.invoice_date || doc.created_at),
            HORA: fmtHora(doc.created_at),
            VENCIMENTO: doc.due_date ? fmtData(doc.due_date) : 'N/A',
            OPERADOR: extras.operador || 'Sistema',
            REFERENCIA: doc.reference || numero,
            SUBTOTAL: fmt2(c.subtotal),
            DESC_COMERCIAL: fmt2(c.comercial),
            DESC_FINANCEIRO: fmt2(c.financeiro),
            IVA: fmt2(c.iva),
            TOTAL: fmt2(c.total),
            RETENCAO: fmt2(c.retencao),
            A_PAGAR: fmt2(c.aPagar),
            RECEBIDO: fmt2(doc.amount_received != null ? doc.amount_received : c.total),
            PAGAMENTO: String(doc.payment_method || 'Dinheiro').toUpperCase(),
            EXTENSO: valorPorExtenso(c.aPagar),
            NOTAS: doc.notes || '',
            ENTREGA_DATA: '', ENTREGA_LOCAL: '',
            HASH4: '', HASH32: '', ID: doc._server_id || '', ASSINATURA_DATA: '',
        };
        let html = '<!DOCTYPE html>' + d.documentElement.outerHTML;
        for (const [k, v] of Object.entries(valores)) {
            html = html.split('%%' + k + '%%').join(escapar(v));
        }

        return html;
    }

    window.PosOfflineTicket = {
        buildTicketHtml,
        buildShiftReportHtml,
        buildDocumentHtml,
        preencherMolde,
        /** O papel de um documento: o molde do servidor preenchido, ou nada se não há molde. */
        htmlDoDocumento(doc, company, extras) {
            return extras && extras.molde ? preencherMolde(extras.molde, doc, extras) : null;
        },
        print(sale, company) {
            printHtml(buildTicketHtml(sale, company));
        },
        /** O talão da venda em PDF (80mm), feito no aparelho. */
        pdfDoTalao(sale, company) {
            return pdfHtml(buildTicketHtml(sale, company), PRINT_CSS, { larguraMm: 80 });
        },
        /** O documento em PDF — A4; a FR vai como talão, como na impressão. */
        pdfDoDocumento(doc, company, extras) {
            if (extras && extras.molde) {
                return pdfHtml(preencherMolde(extras.molde, doc, extras), null, { larguraMm: 210, a4: true, completo: true });
            }

            if (String(doc.doc_type || '').toUpperCase() === 'FR') {
                return pdfHtml(buildTicketHtml({
                    ...doc,
                    provisional_number: 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase(),
                    payment_method: doc.payment_method || 'cash',
                    amount_received: doc.amount_received != null ? doc.amount_received : doc.total,
                }, company), PRINT_CSS, { larguraMm: 80 });
            }

            return pdfHtml(buildDocumentHtml(doc, company), DOCUMENT_CSS, { larguraMm: 210, a4: true });
        },
        /**
         * Cada papel no seu formato: a FR é o talão do balcão (80mm); a
         * proforma, a FT e a NC são documentos e saem em A4. A primeira
         * versão mandava tudo para o talão e a proforma saía uma tira
         * minúscula ao canto da folha.
         */
        printDocument(doc, company, extras) {
            // COM MOLDE, o papel é o do servidor — igual à pré-visualização.
            // Sem molde (aparelho que nunca sincronizou depois desta versão)
            // fica o desenho de recurso, para não deixar ninguém sem papel.
            if (extras && extras.molde) {
                imprimirDocumentoHtml(preencherMolde(extras.molde, doc, extras), 'width=820,height=900');

                return;
            }

            if (String(doc.doc_type || '').toUpperCase() === 'FR') {
                // O talão espera os campos da venda do POS; a FR dos
                // Documentos é paga no acto por definição — o recebido é o
                // total, e a referência local faz de número provisório.
                printHtml(buildTicketHtml({
                    ...doc,
                    provisional_number: 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase(),
                    payment_method: doc.payment_method || 'cash',
                    amount_received: doc.amount_received != null ? doc.amount_received : doc.total,
                }, company));

                return;
            }

            printHtml(buildDocumentHtml(doc, company), DOCUMENT_CSS, 'width=820,height=900');
        },
        printShiftReport(shiftData, sales, company) {
            printHtml(buildShiftReportHtml(shiftData, sales, company));
        },
    };
})();
