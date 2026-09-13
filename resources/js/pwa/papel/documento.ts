import type { Registo } from '../motor/base';
import { esc, money } from './talao';

/**
 * O DOCUMENTO de recurso (proforma, FT, NC) em A4 — só quando o aparelho ainda
 * não tem o MOLDE do servidor.
 *
 * EM A4, NÃO EM TALÃO: a primeira versão imprimia tudo a 80 mm e a proforma
 * saía uma tira ao canto da folha. Sincronizado leva o número verdadeiro; por
 * sincronizar leva a faixa de PROVISÓRIO e uma referência local que não finge
 * ser numeração; a proforma leva o aviso permanente de que não serve de
 * factura. NÃO SE TRADUZ: é o documento, não o ecrã.
 */
export function buildDocumentHtml(doc: Registo, company: Registo = {}): string {
    const synced = doc._synced === 1;
    const tipo = String(doc.doc_type || 'FT').toLowerCase();
    const eProforma = tipo === 'proforma';

    const titulo = ({ proforma: 'FACTURA PROFORMA', ft: 'FACTURA', nc: 'NOTA DE CRÉDITO', fr: 'FACTURA RECIBO' } as Record<string, string>)[tipo] || 'FACTURA';

    const number = synced && doc._server_number
        ? doc._server_number
        : 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase();

    const criado = new Date(doc.created_at || Date.now());
    const dataDoc = doc.invoice_date || criado.toLocaleDateString('pt-AO');

    let linhas = '';
    let descontoTotal = 0;

    (doc.items || []).forEach((it: Registo, i: number) => {
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

    // A proforma nunca é provisória — nunca leva numeração fiscal.
    const banner = eProforma
        ? '<div class="banner banner-info">ESTE DOCUMENTO NÃO SERVE DE FACTURA</div>'
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
        ? '<p class="b">Documento sem valor fiscal</p>'
        : `<p class="b">Processado por programa validado</p>
                           ${company.agt_cert ? `<p class="b">Certificado AGT N.º ${esc(company.agt_cert)}</p>` : ''}`}
                    <p>Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
                    <p class="b">Obrigado pela sua preferência!</p>
                </div>
            </div>
        `;
}

export const DOCUMENT_CSS = `
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
