import type { Registo } from '../motor/base';

/**
 * O MOLDE DO SERVIDOR, PREENCHIDO NO APARELHO.
 *
 * O servidor manda o SEU modelo — o mesmo do PDF — com marcas no lugar dos
 * valores (App\Services\Pwa\MoldeDoDocumento), e é aqui que se preenche. Nada
 * aqui desenha: substitui marcas, repete linhas e tira o que não há. As contas
 * espelham as do servidor (DraftController::calculateTotals e o resumo de
 * impostos) porque o papel tem de mostrar os mesmos números.
 */

export function fmt2(v: unknown): string {
    const n = Math.round((parseFloat(String(v)) || 0) * 100) / 100;
    const [int = '0', dec = '00'] = Math.abs(n).toFixed(2).split('.');

    return (n < 0 ? '-' : '') + int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
}

export function fmt0(v: unknown): string {
    const n = Math.round(parseFloat(String(v)) || 0);

    return (n < 0 ? '-' : '') + String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** A taxa como o resumo a escreve: 14 → "14", 6.5 → "6,5". */
export function fmtTaxa(t: unknown): string {
    const n = parseFloat(String(t)) || 0;

    return String(Math.round(n * 100) / 100).replace('.', ',');
}

export function fmtData(iso: unknown): string {
    if (!iso) return '';
    const s = String(iso);
    const d = new Date(/^\d{4}-\d{2}-\d{2}$/.test(s) ? s + 'T00:00:00' : s);
    if (Number.isNaN(d.getTime())) return s;

    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
}

export function fmtHora(iso: unknown): string {
    const d = new Date(String(iso));

    return Number.isNaN(d.getTime()) ? '' : String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function escapar(s: unknown): string {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// O valor por extenso — o mesmo que o servidor escreve (NumberToWordsHelper).
const UNIDADES = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
const DEZ = ['dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezasseis', 'dezassete', 'dezoito', 'dezanove'];
const DEZENAS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
const CENTENAS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

function inteiroPorExtenso(valor: number): string {
    const n = Math.floor(valor);
    if (n === 0) return '';
    if (n < 10) return UNIDADES[n]!;
    if (n < 20) return DEZ[n - 10]!;
    if (n < 100) {
        const d = Math.floor(n / 10), u = n % 10;

        return DEZENAS[d]! + (u > 0 ? ' e ' + UNIDADES[u] : '');
    }
    if (n === 100) return 'cem';
    if (n < 1000) {
        const c = Math.floor(n / 100), r = n % 100;

        return CENTENAS[c]! + (r > 0 ? ' e ' + inteiroPorExtenso(r) : '');
    }

    const degraus: Array<[number, string, string]> = [[1000000000, 'mil milhões', 'mil milhões'], [1000000, 'um milhão', 'milhões'], [1000, 'mil', 'mil']];
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

export function valorPorExtenso(v: unknown): string {
    const valor = parseFloat(String(v)) || 0;
    const inteiro = Math.floor(valor);
    const cent = Math.round((valor - inteiro) * 100);
    let s = inteiro === 0 ? 'zero' : inteiroPorExtenso(inteiro);
    s += ' ' + (inteiro === 1 ? 'kwanza' : 'kwanzas');
    if (cent > 0) s += ' e ' + inteiroPorExtenso(cent) + ' ' + (cent === 1 ? 'cêntimo' : 'cêntimos');

    return s.charAt(0).toUpperCase() + s.slice(1);
}

/**
 * As contas do documento, como o servidor as faz (sem IEC nem Selo, que só o
 * servidor apura). É A MESMA função que o formulário usa para o total no ecrã:
 * o que o operador vê é o que o papel mostra.
 */
export function contasDoDocumento(doc: Registo) {
    const linhas: Array<{ item: Registo; qtd: number; preco: number; taxa: number; descPct: number; bruto: number; desconto: number; liquido: number; iva: number; total: number }> = [];
    let subtotal = 0;
    let imposto = 0;

    for (const it of doc.items || []) {
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

    // O comercial sai do líquido e o imposto recalcula-se sobre o que sobra; o
    // financeiro sai do total já com imposto; a retenção só em serviços, e
    // baixa o total a receber sem mexer no imposto.
    const comercial = Math.min(parseFloat(doc.discount_commercial) || 0, subtotal);
    const liquidoDoc = subtotal - comercial;
    const ivaDoc = subtotal > 0 ? imposto * (liquidoDoc / subtotal) : 0;
    const financeiro = parseFloat(doc.discount_financial) || 0;
    const pct = doc.withholding_percentage != null && doc.withholding_percentage !== '' ? parseFloat(doc.withholding_percentage) : 6.5;
    const retencao = doc.is_service ? Math.round(liquidoDoc * (Number.isFinite(pct) ? pct : 6.5)) / 100 : 0;
    const total = Math.max(0, liquidoDoc + ivaDoc - financeiro - retencao);

    return { linhas, subtotal, comercial, financeiro, iva: ivaDoc, retencao, total, aPagar: total - retencao };
}

export interface ExtrasDoPapel {
    molde?: string | null;
    clienteNif?: string | null;
    codigos?: Record<string, string>;
    operador?: string | null;
    isencao?: { codigo?: string | null; motivo?: string | null } | null;
}

/** Preenche o molde com um documento local. Devolve o HTML inteiro. */
export function preencherMolde(moldeHtml: string, doc: Registo, extras: ExtrasDoPapel = {}): string {
    const c = contasDoDocumento(doc);
    const sincronizado = !!(doc._synced && doc._server_number);
    const numero = sincronizado ? String(doc._server_number) : 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase();

    const d = new DOMParser().parseFromString(moldeHtml, 'text/html');
    const linhasTr = () => Array.from(d.querySelectorAll('tr'));

    // 1) As linhas dos artigos. A primeira é o molde; as auxiliares saem.
    linhasTr().filter((tr) => (tr.textContent || '').includes('LINHA-AUXILIAR')).forEach((tr) => tr.remove());
    const linhaMolde = linhasTr().find((tr) => tr.innerHTML.includes('%%ITEM_NOME%%'));

    if (linhaMolde) {
        const molde = linhaMolde.outerHTML;
        const html = c.linhas.map((l) => {
            const codigo = extras.codigos?.[String(l.item.product_id)] || '-';
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

            h = l.item.description
                ? h.split('%%ITEM_DESCRICAO%%').join(escapar(l.item.description))
                : h.replace(/<span class="item-descricao">[^<]*%%ITEM_DESCRICAO%%[^<]*<\/span>/g, '').split('%%ITEM_DESCRICAO%%').join('');

            return h;
        }).join('');

        linhaMolde.insertAdjacentHTML('beforebegin', html);
        linhaMolde.remove();
    }

    // 2) O resumo de impostos: uma linha por taxa; a isenta e o motivo só se houver.
    const grupos = new Map<string, { taxa: number; base: number; iva: number }>();
    for (const l of c.linhas) {
        const k = String(l.taxa);
        const g = grupos.get(k) || { taxa: l.taxa, base: 0, iva: 0 };
        g.base += l.liquido - l.desconto;
        g.iva += l.iva;
        grupos.set(k, g);
    }
    const taxadas = [...grupos.values()].filter((g) => g.taxa > 0);
    const isentas = [...grupos.values()].filter((g) => g.taxa <= 0);

    const linhaTaxada = linhasTr().find((tr) => tr.innerHTML.includes('%%IMPOSTO_BASE%%'));
    if (linhaTaxada) {
        const molde = linhaTaxada.outerHTML;
        linhaTaxada.insertAdjacentHTML('beforebegin', taxadas.map((g) => molde
            .split('%%TAXA%%').join(fmtTaxa(g.taxa) + '%')
            .split('%%IMPOSTO_BASE%%').join(fmt2(g.base))
            .split('%%IMPOSTO_VALOR%%').join(fmt2(g.iva))).join(''));
        linhaTaxada.remove();
    }

    const linhaIsenta = linhasTr().find((tr) => tr.innerHTML.includes('%%ISENTO_BASE%%'));
    if (linhaIsenta) {
        if (isentas.length) linhaIsenta.innerHTML = linhaIsenta.innerHTML.split('%%ISENTO_BASE%%').join(fmt2(isentas.reduce((s, g) => s + g.base, 0)));
        else linhaIsenta.remove();
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

    // 3) A entrega: só o que há; o bloco inteiro sai quando não há nada.
    const blocoEntrega = Array.from(d.querySelectorAll('div')).find((el) => el.children.length <= 1 && (el.textContent || '').includes('%%ENTREGA_DATA%%'));
    if (blocoEntrega) {
        const partes: string[] = [];
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
        (d.querySelector('.main-content') || d.body).insertAdjacentHTML('afterbegin',
            '<div class="pwa-provisorio" style="border:2px dashed #b45309;color:#7c2d12;background:#fff7ed;'
            + 'font:bold 11px/1.4 Arial,sans-serif;text-align:center;padding:6px 8px;margin:0 0 8px 0;">'
            + '★ DOCUMENTO PROVISÓRIO ★ — A numeração fiscal é atribuída na sincronização · REF. LOCAL ' + escapar(numero) + '</div>');
    }

    // 6) O resto das marcas, no documento inteiro.
    const valores: Record<string, unknown> = {
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
