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

// Função de impressão de ticket
function printTicket() {
    const printContents = document.getElementById('ticket-print').innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}
</script>
