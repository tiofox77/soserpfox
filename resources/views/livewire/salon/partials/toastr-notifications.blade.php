{{-- Toastr Notifications for Salon Module --}}
<script>
    document.addEventListener('livewire:init', () => {
        // Listen for 'notify' event
        Livewire.on('notify', (event) => {
            const data = event[0] || event;
            const type = data.type || 'info';
            const message = data.message || 'Ação realizada';
            
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };
                
                switch(type) {
                    case 'success':
                        toastr.success(message, 'Sucesso');
                        break;
                    case 'error':
                        toastr.error(message, 'Erro');
                        break;
                    case 'warning':
                        toastr.warning(message, 'Atenção');
                        break;
                    case 'info':
                    default:
                        toastr.info(message, 'Info');
                        break;
                }
            }
        });

        // Listen for 'success' event (alternative)
        Livewire.on('success', (event) => {
            const data = event[0] || event;
            const message = data.message || 'Operação realizada com sucesso!';
            
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000"
                };
                toastr.success(message, 'Sucesso');
            }
        });

        // Listen for 'error' event (alternative)
        Livewire.on('error', (event) => {
            const data = event[0] || event;
            const message = data.message || 'Ocorreu um erro!';
            
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "5000"
                };
                toastr.error(message, 'Erro');
            }
        });
    });
</script>
