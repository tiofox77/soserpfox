<div>
    @if($shouldSend)
        {{-- Componente invisível que envia email após página carregar --}}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🔷 Iniciando envio de email de boas-vindas (REQUEST SEPARADO)');
                
                // Aguardar 1 segundo para simular timing da modal
                setTimeout(function() {
                    console.log('📧 Enviando email via Livewire...');
                    
                    // Chamar método Livewire
                    @this.call('send').then(() => {
                        console.log('✅ Email de boas-vindas enviado com sucesso');
                    }).catch((error) => {
                        console.error('❌ Erro ao enviar email:', error);
                    });
                }, 1000);
            });
        </script>
    @endif
</div>
