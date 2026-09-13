    {{--
        O ALPINE, SEM O LIVEWIRE.

        Vinha dentro do pacote do Livewire, que já não tem componente nenhum. É
        o mesmo Alpine 3 que o PWA já serve do disco. O `x-collapse` (o menu da
        barra lateral a abrir e a fechar) era um plugin que o Livewire trazia:
        fica aqui em ponto pequeno, com a mesma animação de altura.
    --}}
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.directive('collapse', (el, _, { effect, cleanup }) => {
                el.style.overflow = 'hidden';
                el._x_transition = {
                    in(antes = () => {}, depois = () => {}) {
                        antes();
                        el.style.height = '0px';
                        requestAnimationFrame(() => {
                            el.style.transition = 'height .25s ease';
                            el.style.height = el.scrollHeight + 'px';
                            setTimeout(() => { el.style.height = ''; el.style.transition = ''; depois(); }, 260);
                        });
                    },
                    out(antes = () => {}, depois = () => {}) {
                        antes();
                        el.style.height = el.scrollHeight + 'px';
                        requestAnimationFrame(() => {
                            el.style.transition = 'height .25s ease';
                            el.style.height = '0px';
                            setTimeout(() => { el.style.transition = ''; depois(); }, 260);
                        });
                    },
                };
            });
        });
    </script>
    <script defer src="/vendor/js/alpine.min.js"></script>
