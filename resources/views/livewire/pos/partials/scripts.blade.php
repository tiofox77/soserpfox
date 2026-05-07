<script>
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

// Função de impressão de ticket via iframe (não destrói o DOM)
function printTicket() {
    const ticketEl = document.getElementById('ticket-print');
    if (!ticketEl) return;

    const printContents = ticketEl.innerHTML;

    // Remover iframe anterior se existir
    let oldFrame = document.getElementById('print-frame');
    if (oldFrame) oldFrame.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'print-frame';
    iframe.style.position = 'fixed';
    iframe.style.top = '-10000px';
    iframe.style.left = '-10000px';
    iframe.style.width = '80mm';
    iframe.style.height = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Courier New', monospace; font-size: 12px; padding: 5mm; width: 80mm; }
                img { max-width: 100%; height: auto; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 2px 0; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-left { text-align: left; }
                .font-bold { font-weight: bold; }
                .text-xs { font-size: 11px; }
                .text-lg { font-size: 16px; }
                .text-base { font-size: 14px; }
                .border-b { border-bottom: 1px dashed #999; }
                .border-b-2 { border-bottom: 2px dashed #666; }
                .border-t-2 { border-top: 2px solid #333; }
                .mb-1 { margin-bottom: 2px; }
                .mb-2 { margin-bottom: 4px; }
                .mb-3 { margin-bottom: 8px; }
                .mt-1 { margin-top: 2px; }
                .mt-2 { margin-top: 4px; }
                .pb-2 { padding-bottom: 4px; }
                .pb-3 { padding-bottom: 8px; }
                .pt-1 { padding-top: 2px; }
                .pt-2 { padding-top: 4px; }
                .py-1 { padding-top: 2px; padding-bottom: 2px; }
                .pl-2 { padding-left: 4px; }
                .space-y-1 > * + * { margin-top: 2px; }
                .flex { display: flex; }
                .justify-between { justify-content: space-between; }
                .mx-auto { margin-left: auto; margin-right: auto; display: block; }
                .w-auto { width: auto; }
                .h-12 { height: 48px; }
                .uppercase { text-transform: uppercase; }
                .break-all { word-break: break-all; }
                p { margin: 0; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>${printContents}</body>
        </html>
    `);
    doc.close();

    iframe.contentWindow.focus();
    setTimeout(() => {
        iframe.contentWindow.print();
        setTimeout(() => { iframe.remove(); }, 1000);
    }, 300);
}
</script>
