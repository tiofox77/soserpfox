{{-- MULTI-LÍNGUA: não há aqui nada para traduzir, e é de propósito.

     Este ficheiro só faz som e sincronização — não escreve texto nenhum no
     ecrã. O que parece texto não é interface:

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
</script>
@endscript
