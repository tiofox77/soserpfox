import { t, tn } from '@/i18n';

import type { Registo } from '../motor/base';
import { esc, money } from './talao';

/**
 * O RELATÓRIO DE FECHO DE TURNO (X/Z) impresso no aparelho.
 *
 * Ao contrário do talão, ESTE traduz-se: é documento interno de caixa, não sai
 * para o cliente nem para a AGT, e quem o lê é o operador — na língua dele.
 * MULTICAIXA, MB WAY e MULTICAIXA EXPRESS são marcas, não palavras.
 */
function paymentLabelTraduzido(code: string): string {
    const mapa: Record<string, string> = {
        cash: t('DINHEIRO'),
        transfer: t('TRANSFERÊNCIA'),
        multicaixa: 'MULTICAIXA',
        tpa: t('TPA / CARTÃO'),
        card: t('CARTÃO'),
        mbway: 'MB WAY',
        mobile: 'MULTICAIXA EXPRESS',
    };

    return mapa[code.toLowerCase()] || (code ? code.toUpperCase() : t('DINHEIRO'));
}

export function buildShiftReportHtml(shiftData: Registo = {}, vendas: Registo[] = [], company: Registo = {}): string {
    const sales = Array.isArray(vendas) ? vendas : [];
    const agora = new Date();
    const dateStr = agora.toLocaleDateString('pt-AO') + ' ' + agora.toLocaleTimeString('pt-AO', { hour: '2-digit', minute: '2-digit' });

    const porMetodo: Record<string, { count: number; total: number }> = {};
    let totalSales = 0;
    let totalTax = 0;

    for (const s of sales) {
        const m = s.payment_method || 'cash';
        const linha = (porMetodo[m] ??= { count: 0, total: 0 });
        linha.count++;
        linha.total += parseFloat(s.total) || 0;
        totalSales += parseFloat(s.total) || 0;
        totalTax += parseFloat(s.tax) || 0;
    }

    const pendingCount = sales.filter((s) => !s._synced).length;
    const syncedCount = sales.filter((s) => s._synced).length;

    let methodRows = '';
    for (const [code, dados] of Object.entries(porMetodo)) {
        methodRows += `
                <div class="line"><span>${paymentLabelTraduzido(code)} (${dados.count}x)</span><span class="b">${money(dados.total)} Kz</span></div>`;
    }

    const openBal = parseFloat(shiftData.opening_balance) || 0;
    const cashFromServer = parseFloat(shiftData.cash_sales) || 0;
    const cashLocal = porMetodo.cash?.total || 0;
    const expectedCash = openBal + cashFromServer;
    const openedAt = shiftData.opened_at ? new Date(shiftData.opened_at).toLocaleString('pt-AO') : '—';

    const pendingWarning = pendingCount > 0 ? `
            <div class="prov-banner" style="margin-top:6px">
                ⚠ ${tn(':n venda ainda por sincronizar|:n vendas ainda por sincronizar', pendingCount, { n: pendingCount })}<br>
                <small>${t('Totais podem diferir do fecho no servidor')}</small>
            </div>` : '';

    // Os dois pontos vão DENTRO das cadeias: em francês escreve-se «Ouverture :».
    return `
            <div class="hdr">
                <div class="hdr-l">
                    <h3>${esc(company.name || t('Empresa'))}</h3>
                    <p>NIF: ${esc(company.nif || 'N/A')}</p>
                </div>
            </div>

            <h4>${t('RELATÓRIO DE FECHO DE TURNO')}</h4>
            <h4 style="font-size:10px;font-weight:400">${t('(Documento Local / Offline)')}</h4>
            ${pendingWarning}

            <div class="meta">
                <div class="line"><span class="b">${t('Turno:')}</span><span>${esc(shiftData.number || '—')}</span></div>
                <div class="line"><span class="b">${t('Abertura:')}</span><span>${esc(openedAt)}</span></div>
                <div class="line"><span class="b">${t('Fecho:')}</span><span>${esc(dateStr)}</span></div>
                <div class="line"><span class="b">${t('Total vendas:')}</span><span>${sales.length}</span></div>
                <div class="line"><span class="b">${t('Sincronizadas:')}</span><span>${syncedCount}</span></div>
                <div class="line"><span class="b">${t('Por sincronizar:')}</span><span>${pendingCount}</span></div>
            </div>

            <p class="sep" style="text-align:center;margin:5px 0">─────────────────────</p>
            <p class="b" style="margin-bottom:3px">${t('VENDAS POR MÉTODO DE PAGAMENTO')}</p>
            <div class="totals">${methodRows || `<div class="line"><span>${t('Sem vendas')}</span><span>—</span></div>`}</div>

            <div class="totals" style="margin-top:6px">
                <div class="line"><span>${t('Total IVA cobrado:')}</span><span>${money(totalTax)} Kz</span></div>
                <div class="line grand"><span>${t('TOTAL VENDIDO:')}</span><span>${money(totalSales)} Kz</span></div>
            </div>

            <p class="sep" style="text-align:center;margin:6px 0">─────────────────────</p>
            <p class="b" style="margin-bottom:3px">${t('RESUMO DE CAIXA (DINHEIRO)')}</p>
            <div class="pay">
                <div class="line"><span>${t('Saldo inicial:')}</span><span>${money(openBal)} Kz</span></div>
                ${cashLocal > 0 ? `<div class="line"><span>${t('Vendas dinheiro (local):')}</span><span>${money(cashLocal)} Kz</span></div>` : ''}
                ${cashFromServer > 0 ? `<div class="line"><span>${t('Vendas sincronizadas:')}</span><span>${money(cashFromServer)} Kz</span></div>` : ''}
                <div class="line grand"><span>${t('ESPERADO EM CAIXA:')}</span><span>${money(expectedCash)} Kz</span></div>
            </div>

            <div class="foot">
                <p class="sep">═══════════════════════</p>
                <p>${t('Impresso offline — :data', { data: esc(dateStr) })}</p>
                <p class="b">Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
            </div>
        `;
}
