import type { Cliente, Produto, Registo } from '../../motor/base';
import { dataDaquiA, dataDeHoje, numero, uuidV4 } from '../../motor/util';

/**
 * O ESTADO DO FORMULÁRIO DO DOCUMENTO — e a passagem para o motor.
 *
 * Os campos numéricos vivem como TEXTO enquanto se escreve: um `<input
 * type="number">` controlado com número perde o «1,» a meio de «1,5» e o
 * campo vazio vira 0 debaixo dos dedos. As contas do ecrã (`contasDoDocumento`)
 * já lêem com `parseFloat`, e o que vai para a base passa a número em
 * `paraOMotor` — o servidor e o papel recebem sempre números.
 */

export interface LinhaDoFormulario {
    /** Só do ecrã: mantém cada linha no seu sítio quando se apaga uma do meio. */
    chave: string;
    product_id: number | null;
    product_name: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_rate: string;
    // A ESCOLHA do IEC e do Selo. Nunca o valor: quem apura é o servidor, pelo
    // ImpostosDaLinha. Um valor calculado no aparelho daria dois apuramentos do
    // mesmo imposto no documento, e a AGT recusa-o.
    iec_pautal: string | null;
    is_verba: string | null;
}

export interface Formulario {
    // `string` e não a lista dos três tipos: o bloco da referência da nota de
    // crédito continua a existir (ver NovoDocumento) e compara com 'NC'.
    doc_type: string;
    client_id: number | null;
    client_local_uuid: string | null;
    client_name: string;
    invoice_date: string;
    due_date: string;
    reference: string;
    notes: string;
    // Descontos do documento. O comercial incide ANTES do IVA e o financeiro
    // depois — quem apura é o servidor, aqui só se enviam.
    discount_commercial: string;
    discount_financial: string;
    delivery_date: string;
    delivery_location: string;
    is_service: boolean;
    withholding_percentage: string;
    items: LinhaDoFormulario[];
}

export interface TipoDeDocumento {
    code: 'FT' | 'FR' | 'proforma';
    label: string;
    icon: string;
    /** O gradiente do botão escolhido. */
    activo: string;
    sombra: string;
}

/**
 * Os tipos oferecidos. As etiquetas traduzem-se no ecrã (`t(label)`).
 *
 * A Nota de Crédito saiu daqui: não é um documento de venda, e o servidor
 * escrevia-a na tabela das VENDAS — nascia uma factura que não estornava nada
 * e contava como receita. As notas de crédito fazem-se no sistema online,
 * contra o documento original. Há um ensaio a guardar isto.
 */
export const TIPOS_DE_DOCUMENTO: TipoDeDocumento[] = [
    { code: 'FT', label: 'Fatura', icon: 'fa-file-invoice', activo: 'from-blue-600 to-indigo-600 border-blue-600', sombra: 'shadow-blue-500/30' },
    { code: 'FR', label: 'Fatura-Recibo', icon: 'fa-receipt', activo: 'from-emerald-500 to-green-600 border-emerald-600', sombra: 'shadow-emerald-500/30' },
    { code: 'proforma', label: 'Proforma', icon: 'fa-file-lines', activo: 'from-amber-500 to-orange-500 border-amber-600', sombra: 'shadow-amber-500/30' },
];

/**
 * O formulário vazio.
 *
 * As datas saem do relógio de QUEM VENDE (`dataDeHoje`/`dataDaquiA`). O Blade
 * usava `toISOString().slice(0, 10)`, que é UTC: Angola está uma hora à frente,
 * e um documento feito à 00:30 saía com a data FISCAL do dia anterior.
 */
export function formularioVazio(): Formulario {
    return {
        doc_type: 'FT',
        client_id: null,
        client_local_uuid: null,
        client_name: '',
        invoice_date: dataDeHoje(),
        due_date: dataDaquiA(30),
        reference: '',
        notes: '',
        discount_commercial: '0',
        discount_financial: '0',
        delivery_date: '',
        delivery_location: '',
        is_service: false,
        // 6,5% é a taxa corrente do IRT sobre serviços.
        withholding_percentage: '6.5',
        items: [],
    };
}

/** A taxa como valor de `<option>`: 14 → "14", 6.5 → "6.5". */
export const valorDaTaxa = (v: unknown): string => String(numero(v));

/** A linha nova de um artigo do catálogo. */
export function linhaDoProduto(p: Produto): LinhaDoFormulario {
    const taxa = parseFloat(String(p.tax_rate));

    return {
        chave: uuidV4(),
        // Um artigo criado no aparelho ainda não tem id do servidor.
        product_id: Number.isInteger(p.id) ? (p.id as number) : null,
        product_name: p.name || '',
        quantity: '1',
        unit_price: String(numero(p.price)),
        // NÃO usar «|| 14»: 0% (isento) é falsy e viraria 14%.
        tax_rate: Number.isFinite(taxa) ? String(taxa) : '0',
        discount_percent: '0',
        iec_pautal: null,
        is_verba: null,
    };
}

/** O cliente escolhido, nos três campos que o motor lê. `null` = Consumidor Final. */
export function camposDoCliente(c: Cliente | null): Pick<Formulario, 'client_id' | 'client_local_uuid' | 'client_name'> {
    if (!c) {
        // Dado gravado e enviado — não se traduz.
        return { client_id: null, client_local_uuid: null, client_name: 'Consumidor Final' };
    }

    // Id numérico = já sincronizado → client_id; senão vai o local_uuid, e o
    // servidor resolve-o (ou responde 409 e o documento espera pelo cliente).
    return Number.isInteger(c.id)
        ? { client_id: c.id as number, client_local_uuid: null, client_name: c.name || '' }
        : { client_id: null, client_local_uuid: c.local_uuid || null, client_name: c.name || '' };
}

/**
 * O que se entrega ao `createDraftOffline`: números a sério e sem as chaves do
 * ecrã. O motor ainda passa tudo por `soDados` antes de gravar.
 */
export function paraOMotor(f: Formulario): Registo {
    const retencao = f.withholding_percentage.trim() === '' ? 6.5 : numero(f.withholding_percentage);

    return {
        doc_type: f.doc_type,
        client_id: f.client_id,
        client_local_uuid: f.client_local_uuid,
        client_name: f.client_name,
        invoice_date: f.invoice_date,
        due_date: f.due_date,
        reference: f.reference,
        notes: f.notes,
        discount_commercial: numero(f.discount_commercial),
        discount_financial: numero(f.discount_financial),
        delivery_date: f.delivery_date,
        delivery_location: f.delivery_location,
        is_service: f.is_service,
        withholding_percentage: retencao,
        items: f.items.map((l) => ({
            product_id: l.product_id,
            product_name: l.product_name,
            quantity: numero(l.quantity),
            unit_price: numero(l.unit_price),
            discount_percent: numero(l.discount_percent),
            tax_rate: numero(l.tax_rate),
            iec_pautal: l.iec_pautal || null,
            is_verba: l.is_verba || null,
        })),
    };
}
