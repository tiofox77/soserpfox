{{-- MULTI-LÍNGUA: não há aqui nada para traduzir, e é de propósito.

     Este ficheiro faz som, sincronização e a pergunta do medicamento
     controlado — mas não escreve texto nenhum. A frase da confirmação vem
     traduzida do servidor dentro do evento, precisamente para não haver aqui
     uma cadeia solta que ninguém se lembra de traduzir.

     O resto do que parece texto não é interface:

       · 'pos_sound_enabled' e a POS_KEY são chaves de localStorage. Traduzi-las
         apagava o carrinho guardado de quem já o tem — e um caixa offline nem
         sequer ficaria a saber porquê;
       · 'cart-updated', 'item-added', 'pos-cart-sync' são nomes de eventos
         Livewire, e do outro lado o PHP dispara-os por este nome exacto;
       · 'add' / 'remove' / 'error' são tipos de som, não palavras.

     O aviso "Sessão expirada — carrinho guardado" que o comentário menciona
     vive no layout, não aqui. --}}
<script>
// Marca esta página como POS (usado pelo handler de sessão-expirada no layout:
// mostra o overlay "Sessão expirada — carrinho guardado" em vez de reload cego).
window.__isPOS = true;

// Sistema de Som POS
function playPosSound(type = 'beep') {
    // Verificar se som está ativado nas configurações
    const soundEnabled = localStorage.getItem('pos_sound_enabled') !== 'false';
    
    if (!soundEnabled) return;
    
    // Criar contexto de áudio
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    // Configurar som baseado no tipo
    if (type === 'add') {
        // Som de adicionar (frequência alta, curta)
        oscillator.frequency.value = 800;
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    } else if (type === 'remove') {
        // Som de remover (frequência baixa, curta)
        oscillator.frequency.value = 400;
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15);
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.15);
    } else if (type === 'error') {
        // Som de erro (buzzer duplo descendente)
        oscillator.frequency.value = 300;
        gainNode.gain.setValueAtTime(0.4, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);
        
        // Segundo beep de erro
        setTimeout(() => {
            const audioContext2 = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator2 = audioContext2.createOscillator();
            const gainNode2 = audioContext2.createGain();
            
            oscillator2.connect(gainNode2);
            gainNode2.connect(audioContext2.destination);
            
            oscillator2.frequency.value = 250;
            gainNode2.gain.setValueAtTime(0.4, audioContext2.currentTime);
            gainNode2.gain.exponentialRampToValueAtTime(0.01, audioContext2.currentTime + 0.2);
            oscillator2.start(audioContext2.currentTime);
            oscillator2.stop(audioContext2.currentTime + 0.2);
        }, 100);
    } else {
        // Som padrão (beep)
        oscillator.frequency.value = 600;
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    }
}

// Escutar eventos Livewire para tocar sons
document.addEventListener('livewire:init', () => {
    Livewire.on('cart-updated', (event) => {
        const action = event[0]?.action || 'add';
        playPosSound(action);
    });
    
    Livewire.on('item-added', () => {
        playPosSound('add');
    });
    
    Livewire.on('item-removed', () => {
        playPosSound('remove');
    });
    
    Livewire.on('stock-error', () => {
        playPosSound('error');
    });
});

// Nota: window.printTicket() esta definida em partials/print-modal.blade.php
</script>

{{-- Persistência do carrinho no cliente (rede de segurança contra expiração de sessão) --}}
@script
<script>
(() => {
    // O IIFE não é estilo: o conteúdo destes blocos script do Livewire é
    // avaliado como EXPRESSÃO Alpine, e o Alpine só embrulha sozinho o que
    // COMEÇA por let/const — um comentário à frente cega-lhe a heurística e
    // o bloco inteiro morria com «Unexpected token: const»: sem espelho do
    // carrinho, sem restauro, e sem a confirmação dos psicotrópicos (o
    // clique no artigo não fazia nada). Nota: nunca escrever a directiva
    // arroba-script por extenso nos comentários — o Blade apanha-a até aqui
    // dentro e desfaz o bloco.

    // Chave de armazenamento (mesma que o servidor usa em loadCart()).
    const POS_KEY = @js('pos_cart_' . auth()->id() . '_' . ($this->currentShift?->id ?? 0));
    // Já vimos um carrinho com itens nesta sessão de página? (só então limpamos o espelho ao esvaziar)
    let posHadItems = false;

    // 1) Restauro determinístico no arranque: se o servidor não tem carrinho mas há
    //    cópia guardada no cliente (sessão expirou antes), repor a venda no servidor.
    (function posInitRestore() {
        let current = [];
        try { current = $wire.get('cartItems') || []; } catch (_) {}
        const count = Array.isArray(current) ? current.length : 0;
        if (count > 0) { posHadItems = true; return; }   // servidor já tem carrinho → nada a restaurar
        let saved = null;
        try { saved = JSON.parse(localStorage.getItem(POS_KEY) || 'null'); } catch (_) {}
        if (saved && Array.isArray(saved.items) && saved.items.length) {
            $wire.restoreCartFromClient(saved.items);     // revalida stock/preço no servidor
        }
    })();

    // 1b) Medicamento controlado (psicotrópico/estupefaciente): o servidor
    //     recusa-se a pôr o artigo no carrinho sem uma resposta humana e pede-a
    //     por aqui. Confirmada, volta a chamar o mesmo addToCart com o sinal
    //     ligado — as validações de stock e lote correm outra vez, nada fica
    //     por verificar.
    //
    //     Vive neste bloco (e não no listener global lá em cima) porque precisa
    //     do $wire do componente. O confirm() do navegador é o mesmo que o
    //     wire:confirm usa em "Limpar carrinho": um caixa não tem de aprender
    //     duas caixas de diálogo diferentes.
    $wire.on('pos-confirmar-controlado', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        if (!data || !data.productId) return;
        // O som é acessório; a pergunta é que não pode faltar. O playPosSound
        // vive no <script> normal lá em cima, que numa navegação SPA pode não
        // ter voltado a correr — e um ReferenceError aqui engolia a confirmação
        // inteira: o operador clicava no artigo e não acontecia nada, sem aviso
        // nenhum e sem o artigo entrar no carrinho.
        try { playPosSound('error'); } catch (_) {}
        if (window.confirm(data.message)) {
            $wire.addToCart(data.productId, true);
        }
    });

    // 2) Manter o espelho sincronizado a cada alteração do carrinho.
    $wire.on('pos-cart-sync', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        if (!data || !data.key) return;
        const count = Number(data.count || 0);
        if (count > 0) {
            posHadItems = true;
            try { localStorage.setItem(data.key, JSON.stringify({ ts: Date.now(), items: data.items || [] })); } catch (_) {}
        } else if (posHadItems) {
            // Esvaziado por ação do utilizador (remover tudo / finalizar venda) → apagar espelho.
            try { localStorage.removeItem(data.key); } catch (_) {}
            posHadItems = false;
        }
        // count 0 && !posHadItems → NÃO apagar (preserva a cópia para restauro pós-login).
    });
})();
</script>
@endscript
