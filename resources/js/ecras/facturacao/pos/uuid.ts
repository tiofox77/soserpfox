/**
 * UM IDENTIFICADOR ÚNICO PARA CADA TENTATIVA DE VENDA.
 *
 * É o que torna a venda idempotente: se a rede tossir e o operador carregar
 * outra vez em «Finalizar», o servidor reconhece o mesmo identificador e
 * devolve a factura que já gravou, em vez de gravar uma segunda com o mesmo
 * dinheiro, o mesmo stock e a mesma tesouraria.
 *
 * NÃO SE USA `crypto.randomUUID()` SOZINHO, e a razão apanhou-me em cheio:
 * essa função só existe em CONTEXTO SEGURO — HTTPS, ou `localhost`. Um balcão
 * corre quase sempre em HTTP, na rede local da loja (`http://caixa.loja`), e
 * aí `crypto.randomUUID` é `undefined`. O ecrã rebentava com
 * «crypto.randomUUID is not a function» ao confirmar a venda — precisamente
 * no clique que não pode falhar.
 *
 * A escada é: a função nativa quando existe; senão `getRandomValues`, que já
 * existe em HTTP; e só em último caso o relógio com um número ao calhas. O
 * último degrau é fraco para criptografia e forte que chegue para o que isto
 * é — distinguir duas tentativas do mesmo caixa, no mesmo minuto.
 */
export function identificadorDaVenda(): string {
    const c = globalThis.crypto as Crypto | undefined;

    if (typeof c?.randomUUID === 'function') {
        return c.randomUUID();
    }

    if (typeof c?.getRandomValues === 'function') {
        const b = c.getRandomValues(new Uint8Array(16));

        // A versão (4) e a variante, como manda o RFC 4122.
        b[6] = (b[6]! & 0x0f) | 0x40;
        b[8] = (b[8]! & 0x3f) | 0x80;

        const hex = [...b].map((n) => n.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    return `venda-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}
